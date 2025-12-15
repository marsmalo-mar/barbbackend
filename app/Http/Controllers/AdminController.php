<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Service;
use App\Models\Barber;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    /** Get upload directory path */
    private function getUploadPath(): string
    {
        // Get the barbershop directory (parent of backend)
        $barbershopDir = dirname(base_path());
        $uploadDir = $barbershopDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        return $uploadDir;
    }
    // ============================================
    // APPOINTMENTS MANAGEMENT
    // ============================================

    // GET /api/admin/appointments
    public function getAllAppointments(Request $req)
    {
        $search = trim($req->query('search', ''));
        $status = $req->query('status', '');

        $q = Booking::with(['user', 'service', 'barber']);

        if ($search !== '') {
            $q->where(function ($x) use ($search) {
                $x->whereHas('user', function($query) use ($search) {
                    $query->where('name', 'LIKE', "%$search%")
                          ->orWhere('email', 'LIKE', "%$search%");
                })
                ->orWhereHas('service', function($query) use ($search) {
                    $query->where('name', 'LIKE', "%$search%");
                })
                ->orWhereHas('barber', function($query) use ($search) {
                    $query->where('name', 'LIKE', "%$search%");
                })
                ->orWhere('booking_date', 'LIKE', "%$search%");
            });
        }

        if ($status !== '') {
            $q->where('status', $status);
        }

        // Custom status sorting: pending -> confirmed -> completed -> cancelled
        // Then by date and time (most recent first)
        $bookings = $q->orderByRaw("CASE 
                        WHEN status = 'pending' THEN 1 
                        WHEN status = 'confirmed' THEN 2 
                        WHEN status = 'completed' THEN 3 
                        WHEN status = 'cancelled' THEN 4 
                        ELSE 5 
                     END")
                     ->orderBy('booking_date', 'desc')
                     ->orderBy('booking_time', 'desc')
                     ->get();

        return response()->json($bookings);
    }

    // PATCH /api/admin/appointments/{id}/approve
    public function approveAppointment($id)
    {
        $booking = Booking::find($id);
        if (!$booking) {
            return response()->json(['error' => 'Appointment not found'], 404);
        }

        // Before approving, find and decline all other pending appointments
        // for the same barber, date, and time
        $conflictingBookings = Booking::where('barber_id', $booking->barber_id)
            ->where('booking_date', $booking->booking_date)
            ->where('booking_time', $booking->booking_time)
            ->where('id', '!=', $booking->id) // Exclude the current booking
            ->where('status', 'pending') // Only decline pending ones
            ->get();

        // Decline all conflicting pending appointments
        $declinedCount = 0;
        foreach ($conflictingBookings as $conflicting) {
            $conflicting->status = 'cancelled';
            $conflicting->save();
            $declinedCount++;
        }

        // Now approve the selected booking
        $booking->status = 'confirmed';
        $booking->save();

        $message = 'Appointment approved';
        if ($declinedCount > 0) {
            $message .= " ({$declinedCount} conflicting appointment(s) automatically declined)";
        }

        return response()->json([
            'message' => $message,
            'booking' => $booking->load(['user', 'service', 'barber']),
            'declined_count' => $declinedCount
        ]);
    }

    // PATCH /api/admin/appointments/{id}/decline
    public function declineAppointment($id)
    {
        $booking = Booking::find($id);
        if (!$booking) {
            return response()->json(['error' => 'Appointment not found'], 404);
        }

        $booking->status = 'cancelled';
        $booking->save();

        return response()->json(['message' => 'Appointment declined', 'booking' => $booking->load(['user', 'service', 'barber'])]);
    }

    // ============================================
    // SERVICES MANAGEMENT
    // ============================================

    // GET /api/admin/services
    public function getAllServices(Request $req)
    {
        $services = Service::orderBy('id', 'asc')->get()->map(function ($s) {
            $s->image_path = 'storage/' . $s->image_path;
            return $s;
        });

        return response()->json($services);
    }

    // POST /api/admin/services
    public function createService(Request $req)
    {
        $data = $req->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string',
            'price'       => 'required|numeric|min:0',
            'duration'    => 'required|integer|min:1',
            'image'       => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:3072',
        ]);

        $filename = 'uploads/default.png';
        if ($req->hasFile('image')) {
            $ext = strtolower($req->file('image')->getClientOriginalExtension() ?: 'jpg');
            $name = 'service_'.(int)(microtime(true) * 1000).'.'.$ext;
            $uploadDir = $this->getUploadPath();
            $req->file('image')->move($uploadDir, $name);
            $filename = 'uploads/' . $name;
        }

        $service = Service::create([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'price'       => $data['price'],
            'duration'    => $data['duration'],
            'image_path'  => $filename,
        ]);

        $service->image_path = 'storage/' . $service->image_path;
        return response()->json(['message' => 'Service created', 'service' => $service], 201);
    }

    // PUT /api/admin/services/{id}
    public function updateService(Request $req, $id)
    {
        $service = Service::find($id);
        if (!$service) {
            return response()->json(['error' => 'Service not found'], 404);
        }

        $data = $req->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string',
            'price'       => 'required|numeric|min:0',
            'duration'    => 'required|integer|min:1',
            'image'       => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:3072',
        ]);

        if ($req->hasFile('image')) {
            $ext = strtolower($req->file('image')->getClientOriginalExtension() ?: 'jpg');
            $name = 'service_'.(int)(microtime(true) * 1000).'.'.$ext;
            $uploadDir = $this->getUploadPath();
            $req->file('image')->move($uploadDir, $name);
            $newName = 'uploads/' . $name;
            
            if ($service->image_path && $service->image_path !== 'uploads/default.png') {
                $oldPath = dirname(base_path()) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . $service->image_path;
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }
            $service->image_path = $newName;
        }

        $service->name        = $data['name'];
        $service->description = $data['description'] ?? null;
        $service->price       = $data['price'];
        $service->duration    = $data['duration'];
        $service->save();

        $service->image_path = 'storage/' . $service->image_path;
        return response()->json(['message' => 'Service updated', 'service' => $service]);
    }

    // DELETE /api/admin/services/{id}
    public function deleteService($id)
    {
        $service = Service::find($id);
        if (!$service) {
            return response()->json(['error' => 'Service not found'], 404);
        }

        if ($service->image_path && $service->image_path !== 'uploads/default.png') {
            $filePath = dirname(base_path()) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . $service->image_path;
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        $service->delete();
        return response()->json(['message' => 'Service deleted']);
    }

    // ============================================
    // USERS MANAGEMENT
    // ============================================

    // GET /api/admin/users
    public function getAllUsers(Request $req)
    {
        $search = trim($req->query('search', ''));
        $userType = $req->query('user_type', '');

        $q = User::query();

        if ($search !== '') {
            $q->where(function ($x) use ($search) {
                $x->where('name', 'LIKE', "%$search%")
                  ->orWhere('email', 'LIKE', "%$search%")
                  ->orWhere('username', 'LIKE', "%$search%");
            });
        }

        if ($userType !== '') {
            $q->where('user_type', $userType);
        }

        // Sort by user_type: admin first, then barber, then user
        // Within each type, sort by id ascending
        $users = $q->orderByRaw("CASE 
            WHEN user_type = 'admin' THEN 1 
            WHEN user_type = 'barber' THEN 2 
            WHEN user_type = 'user' THEN 3 
            ELSE 4 
        END")
        ->orderBy('id', 'asc')
        ->get()
        ->map(function ($u) {
            $u->avatar = $u->avatar ? 'storage/' . $u->avatar : null;
            return $u;
        });

        return response()->json($users);
    }

    // POST /api/admin/users
    public function createUser(Request $req)
    {
        $data = $req->validate([
            'name'      => 'required|string',
            'email'     => 'required|email|unique:users,email',
            'username'  => 'required|string|unique:users,username',
            'password'  => 'required|string|min:6',
            'user_type' => 'required|in:user,barber,admin',
            'avatar'    => 'nullable|file|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        $avatarPath = null;
        if ($req->hasFile('avatar')) {
            $file = $req->file('avatar');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
            $name = 'avatar_' . time() . '.' . $ext;
            $uploadDir = $this->getUploadPath();
            $file->move($uploadDir, $name);
            $avatarPath = 'uploads/' . $name;
        }

        $user = User::create([
            'name'              => $data['name'],
            'email'             => strtolower($data['email']),
            'username'          => $data['username'],
            'password'          => Hash::make($data['password']),
            'user_type'         => $data['user_type'],
            'avatar'            => $avatarPath,
            'email_verified_at' => null, // User must verify email before account is activated
        ]);

        // If user type is barber, automatically create a barber record
        if ($data['user_type'] === 'barber') {
            // Check if barber record already exists (by email)
            $existingBarber = Barber::where('email', $user->email)->first();
            
            if (!$existingBarber) {
                Barber::create([
                    'name'       => $user->name,
                    'email'      => $user->email,
                    'specialty'  => 'Professional Barber',
                    'bio'        => '',
                    'phone'      => null,
                    // Sync avatar to barber's image_path
                    'image_path' => $avatarPath ?? 'uploads/default.png',
                ]);
            } else {
                // Sync avatar to existing barber's image_path
                if ($avatarPath) {
                    $existingBarber->image_path = $avatarPath;
                    $existingBarber->save();
                }
            }
        }

        $user->avatar = $user->avatar ? 'storage/' . $user->avatar : null;
        return response()->json(['message' => 'User created', 'user' => $user], 201);
    }

    // PUT /api/admin/users/{id}
    public function updateUser(Request $req, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $data = $req->validate([
            'name'      => 'required|string',
            'email'     => 'required|email|unique:users,email,' . $id,
            'username'  => 'required|string|unique:users,username,' . $id,
            'password'  => 'nullable|string|min:6',
            'user_type' => 'required|in:user,barber,admin',
            'avatar'    => 'nullable|file|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        if ($req->hasFile('avatar')) {
            $file = $req->file('avatar');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
            $name = 'avatar_' . time() . '_' . $user->id . '.' . $ext;
            $uploadDir = $this->getUploadPath();
            $file->move($uploadDir, $name);
            
            if ($user->avatar && $user->avatar !== 'uploads/default.png') {
                $oldPath = dirname(base_path()) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . $user->avatar;
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }
            
            $user->avatar = 'uploads/' . $name;
        }

        $oldUserType = $user->user_type;
        $oldEmail = $user->email;
        
        $user->name      = $data['name'];
        $user->email     = strtolower($data['email']);
        $user->username  = $data['username'];
        $user->user_type = $data['user_type'];

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        // Handle barber record creation/update/deletion when user type changes
        if ($data['user_type'] === 'barber' && $oldUserType !== 'barber') {
            // User was changed to barber - create barber record if it doesn't exist
            $existingBarber = Barber::where('email', $user->email)->first();
            
            if (!$existingBarber) {
                Barber::create([
                    'name'       => $user->name,
                    'email'      => $user->email,
                    'specialty'  => 'Professional Barber',
                    'bio'        => '',
                    'phone'      => null,
                    // Sync user's avatar to barber's image_path
                    'image_path' => $user->avatar ?? 'uploads/default.png',
                ]);
            } else {
                // Update existing barber record with new user info
                $existingBarber->name = $user->name;
                $existingBarber->email = $user->email;
                // Sync user's avatar to barber's image_path
                if ($user->avatar) {
                    $existingBarber->image_path = $user->avatar;
                }
                $existingBarber->save();
            }
        } elseif ($oldUserType === 'barber' && $data['user_type'] !== 'barber') {
            // User was changed from barber to another type - delete barber record
            Barber::where('email', $oldEmail)->delete();
        } elseif ($data['user_type'] === 'barber' && $oldUserType === 'barber') {
            // User is still a barber - always sync barber record with user data
            $barber = Barber::where('email', $oldEmail)->first();
            if ($barber) {
                // Update existing barber record with any changes
                $barber->name = $user->name;
                $barber->email = $user->email;
                // Sync avatar to barber's image_path if avatar was updated
                if ($req->hasFile('avatar')) {
                    $barber->image_path = $user->avatar;
                }
                $barber->save();
            } else {
                // Barber record doesn't exist, create it
                Barber::create([
                    'name'       => $user->name,
                    'email'      => $user->email,
                    'specialty'  => 'Professional Barber',
                    'bio'        => '',
                    'phone'      => null,
                    'image_path' => $user->avatar ?? 'uploads/default.png',
                ]);
            }
        }

        $user->avatar = $user->avatar ? 'storage/' . $user->avatar : null;
        return response()->json(['message' => 'User updated', 'user' => $user]);
    }

    // DELETE /api/admin/users/{id}
    public function deleteUser($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Prevent deleting super admin (id=1)
        if ($id == 1) {
            return response()->json(['error' => 'Cannot delete the super admin account'], 403);
        }

        // Prevent deleting yourself
        $currentUser = request()->attributes->get('auth_user');
        if ($currentUser && $currentUser->id == $id) {
            // Prevent regular admins (not super admin) from deleting their own account
            if ($currentUser->user_type === 'admin' && $currentUser->id != 1) {
                return response()->json(['error' => 'Admins cannot delete their own account'], 403);
            }
            return response()->json(['error' => 'Cannot delete your own account'], 400);
        }

        // If user is a barber, delete the associated barber record
        if ($user->user_type === 'barber') {
            Barber::where('email', $user->email)->delete();
        }

        if ($user->avatar && $user->avatar !== 'uploads/default.png') {
            $filePath = dirname(base_path()) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . $user->avatar;
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        $user->delete();
        return response()->json(['message' => 'User deleted']);
    }

    // ============================================
    // ADMIN PROFILE
    // ============================================

    // PUT /api/admin/profile
    public function updateProfile(Request $req)
    {
        $user = $req->attributes->get('auth_user');

        $data = $req->validate([
            'name'    => 'required|string',
            'email'   => 'required|email|unique:users,email,' . $user->id,
            'username' => 'required|string|unique:users,username,' . $user->id,
            'avatar'  => 'nullable|file|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        if ($req->hasFile('avatar')) {
            $file = $req->file('avatar');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
            $name = 'avatar_' . time() . '_' . $user->id . '.' . $ext;
            $uploadDir = $this->getUploadPath();
            $file->move($uploadDir, $name);
            
            if ($user->avatar && $user->avatar !== 'uploads/default.png') {
                $oldPath = dirname(base_path()) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . $user->avatar;
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }
            
            $user->avatar = 'uploads/' . $name;
        }

        $user->name     = $data['name'];
        $user->email    = strtolower($data['email']);
        $user->username = $data['username'];
        $user->save();

        $user->avatar = $user->avatar ? 'storage/' . $user->avatar : null;
        return response()->json(['message' => 'Profile updated', 'user' => $user]);
    }
}

