<?php

namespace App\Http\Controllers;

use App\Models\Barber;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;

class BarberController extends Controller
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
    // PUBLIC BARBER LISTING (for clients)
    // ============================================

    // GET /api/barbers
    public function index(Request $req)
    {
        $search = trim($req->query('search', ''));
        
        // Fetch barbers from users table where user_type = 'barber'
        $query = User::where('user_type', 'barber');
        
        // Apply search filter if provided
        if ($search !== '') {
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('username', 'LIKE', "%{$search}%");
            });
        }
        
        $barbers = $query->orderBy('name')->get();
        
        // Format barber data for frontend
        // Look up the corresponding Barber record to get profile details and image
        $barbers = $barbers->map(function($user) {
            // Find the corresponding barber record by email
            $barberRecord = Barber::where('email', $user->email)->first();
            
            // Determine image URL - prioritize barber table image, then user avatar
            $imageUrl = url('storage/uploads/profile.png'); // Default
            $imagePath = null;
            
            if ($barberRecord && $barberRecord->image_path && $barberRecord->image_path !== 'uploads/default.png') {
                $imagePath = $barberRecord->image_path;
                $imageUrl = url('storage/' . $barberRecord->image_path);
            } elseif ($user->avatar) {
                $imagePath = $user->avatar;
                $imageUrl = url('storage/' . $user->avatar);
            }
            
            return [
                'id' => $user->id, // User ID
                'barber_id' => $barberRecord ? $barberRecord->id : null,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'specialty' => $barberRecord->specialty ?? 'Expert Barber',
                'bio' => $barberRecord->bio ?? '',
                'phone' => $barberRecord->phone ?? null,
                'image_path' => $imagePath,
                'image_url' => $imageUrl,
                'avatar' => $user->avatar ? url('storage/' . $user->avatar) : null,
            ];
        });

        return response()->json($barbers);
    }

    // GET /api/barbers/{id}
    public function show($id)
    {
        // Fetch barber from users table
        $user = User::where('id', $id)->where('user_type', 'barber')->first();
        if (!$user) {
            return response()->json(['error' => 'Barber not found'], 404);
        }

        // Find the corresponding barber record by email
        $barberRecord = Barber::where('email', $user->email)->first();
        
        // Determine image URL - prioritize barber table image, then user avatar
        $imageUrl = url('storage/uploads/profile.png'); // Default
        $imagePath = null;
        
        if ($barberRecord && $barberRecord->image_path && $barberRecord->image_path !== 'uploads/default.png') {
            $imagePath = $barberRecord->image_path;
            $imageUrl = url('storage/' . $barberRecord->image_path);
        } elseif ($user->avatar) {
            $imagePath = $user->avatar;
            $imageUrl = url('storage/' . $user->avatar);
        }

        $barber = [
            'id' => $user->id, // User ID
            'barber_id' => $barberRecord ? $barberRecord->id : null,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'specialty' => $barberRecord->specialty ?? 'Expert Barber',
            'bio' => $barberRecord->bio ?? '',
            'phone' => $barberRecord->phone ?? null,
            'image_path' => $imagePath,
            'image_url' => $imageUrl,
            'avatar' => $user->avatar ? url('storage/' . $user->avatar) : null,
        ];

        return response()->json($barber);
    }

    // ============================================
    // BARBER DASHBOARD - APPOINTMENTS
    // ============================================

    // GET /api/barber/appointments - Get appointments for logged-in barber
    public function getMyAppointments(Request $req)
    {
        $user = $req->attributes->get('auth_user');

        // Find the barber record associated with this user
        // Assuming barbers table has an email or user_id field
        // For now, we'll match by email
        $barber = Barber::where('email', $user->email)->first();

        if (!$barber) {
            return response()->json(['error' => 'Barber profile not found'], 404);
        }

        $search = trim($req->query('search', ''));
        $status = $req->query('status', '');

        $q = Booking::with(['user', 'service', 'barber'])
            ->where('barber_id', $barber->id);

        if ($search !== '') {
            $q->where(function ($x) use ($search) {
                $x->whereHas('user', function($query) use ($search) {
                    $query->where('name', 'LIKE', "%$search%")
                          ->orWhere('email', 'LIKE', "%$search%");
                })
                ->orWhereHas('service', function($query) use ($search) {
                    $query->where('name', 'LIKE', "%$search%");
                })
                ->orWhere('booking_date', 'LIKE', "%$search%");
            });
        }

        if ($status !== '') {
            $q->where('status', $status);
        }

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

    // PATCH /api/barber/appointments/{id}/complete - Mark appointment as completed
    public function markComplete(Request $req, $id)
    {
        $user = $req->attributes->get('auth_user');

        // Find the barber record
        $barber = Barber::where('email', $user->email)->first();

        if (!$barber) {
            return response()->json(['error' => 'Barber profile not found'], 404);
        }

        $booking = Booking::find($id);
        if (!$booking) {
            return response()->json(['error' => 'Appointment not found'], 404);
        }

        // Verify this appointment belongs to this barber
        if ($booking->barber_id !== $barber->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Can only mark as completed if it's confirmed/approved
        if ($booking->status !== 'confirmed') {
            return response()->json(['error' => 'Can only complete approved appointments'], 422);
        }

        $booking->status = 'completed';
        $booking->save();

        $booking->load(['user', 'service', 'barber']);

        return response()->json([
            'message' => 'Appointment marked as completed',
            'booking' => $booking
        ]);
    }

    // ============================================
    // BARBER PROFILE MANAGEMENT
    // ============================================

    // GET /api/barber/profile - Get logged-in barber's profile
    public function getProfile(Request $req)
    {
        $user = $req->attributes->get('auth_user');

        $barber = Barber::where('email', $user->email)->first();

        if (!$barber) {
            return response()->json(['error' => 'Barber profile not found'], 404);
        }

        // Set image URL - use profile.png as default if no custom image or if using default.png
        if ($barber->image_path && $barber->image_path !== 'uploads/default.png') {
            $barber->image_url = url('storage/' . $barber->image_path);
        } else {
            $barber->image_url = url('storage/uploads/profile.png');
        }

        return response()->json([
            'barber' => $barber,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'avatar' => ($user->avatar && $user->avatar !== 'uploads/default.png') ? url('storage/' . $user->avatar) : url('storage/uploads/profile.png'),
            ]
        ]);
    }

    // POST /api/barber/profile - Update barber profile
    public function updateProfile(Request $req)
    {
        $user = $req->attributes->get('auth_user');

        $barber = Barber::where('email', $user->email)->first();

        if (!$barber) {
            return response()->json(['error' => 'Barber profile not found'], 404);
        }

        $data = $req->validate([
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'specialty' => 'nullable|string|max:255',
            'bio' => 'nullable|string',
        ]);

        // Update barber record
        if (isset($data['name'])) {
            $barber->name = $data['name'];
        }
        if (isset($data['phone'])) {
            $barber->phone = $data['phone'];
        }
        if (isset($data['specialty'])) {
            $barber->specialty = $data['specialty'];
        }
        if (isset($data['bio'])) {
            $barber->bio = $data['bio'];
        }

        $barber->save();

        // Also update user name if provided
        if (isset($data['name'])) {
            $user->name = $data['name'];
            $user->save();
        }

        // Set image URL - use profile.png as default if no custom image or if using default.png
        if ($barber->image_path && $barber->image_path !== 'uploads/default.png') {
            $barber->image_url = url('storage/' . $barber->image_path);
        } else {
            $barber->image_url = url('storage/uploads/profile.png');
        }

        return response()->json([
            'message' => 'Profile updated successfully',
            'barber' => $barber
        ]);
    }

    // POST /api/barber/profile/image - Upload barber profile image
    public function uploadProfileImage(Request $req)
    {
        $user = $req->attributes->get('auth_user');

        $barber = Barber::where('email', $user->email)->first();

        if (!$barber) {
            return response()->json(['error' => 'Barber profile not found'], 404);
        }

        $req->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Delete old image if exists
        if ($barber->image_path) {
            $oldPath = dirname(base_path()) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . $barber->image_path;
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        // Store new image
        $file = $req->file('image');
        $filename = 'barber_' . time() . '_' . $barber->id . '.' . $file->getClientOriginalExtension();
        $uploadDir = $this->getUploadPath();
        $file->move($uploadDir, $filename);
        $path = 'uploads/' . $filename;

        $barber->image_path = $path;
        $barber->save();

        // Sync image to user's avatar
        $user->avatar = $path;
        $user->save();

        return response()->json([
            'message' => 'Profile image updated successfully',
            'image_url' => url('storage/' . $path)
        ]);
    }

    // POST /api/barber/credentials - Update barber login credentials
    public function updateCredentials(Request $req)
    {
        $user = $req->attributes->get('auth_user');

        $data = $req->validate([
            'username' => 'required|string|max:255',
            'current_password' => 'required|string',
            'new_password' => 'nullable|string|min:6',
        ]);

        // Verify current password
        if (!Hash::check($data['current_password'], $user->password)) {
            return response()->json(['error' => 'Current password is incorrect'], 422);
        }

        // Check if username is taken by another user
        $existingUser = User::where('username', $data['username'])
            ->where('id', '!=', $user->id)
            ->first();

        if ($existingUser) {
            return response()->json(['error' => 'Username is already taken'], 422);
        }

        // Update username
        $user->username = $data['username'];

        // Update password if provided
        if (!empty($data['new_password'])) {
            $user->password = Hash::make($data['new_password']);
        }

        $user->save();

        return response()->json([
            'message' => 'Credentials updated successfully',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'name' => $user->name,
            ]
        ]);
    }

    // ============================================
    // ADMIN BARBER MANAGEMENT (existing methods)
    // ============================================

    // POST /api/barbers - Create barber (admin only)
    public function store(Request $req)
    {
        $data = $req->validate([
            'name'           => 'required|string|max:255',
            'email'          => 'required|email|unique:barbers,email',
            'phone'          => 'nullable|string|max:20',
            'specialty' => 'nullable|string|max:255',
            'bio'            => 'nullable|string',
            'image'          => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $imagePath = null;
        if ($req->hasFile('image')) {
            $file = $req->file('image');
            $filename = 'barber_' . time() . '.' . $file->getClientOriginalExtension();
            $uploadDir = $this->getUploadPath();
            $file->move($uploadDir, $filename);
            $imagePath = 'uploads/' . $filename;
        }

        $barber = Barber::create([
            'name'           => $data['name'],
            'email'          => $data['email'],
            'phone'          => $data['phone'] ?? null,
            'specialty' => $data['specialty'] ?? null,
            'bio'            => $data['bio'] ?? null,
            'image_path'     => $imagePath,
        ]);

        // Sync barber's image to user's avatar if user exists
        if ($imagePath) {
            $user = User::where('email', $barber->email)->first();
            if ($user) {
                $user->avatar = $imagePath;
                $user->save();
            }
        }

        if ($barber->image_path) {
            $barber->image_url = url('storage/' . $barber->image_path);
        }

        return response()->json(['barber' => $barber], 201);
    }

    // POST/PUT /api/barbers/{id} - Update barber (admin only)
    public function update(Request $req, $id)
    {
        $barber = Barber::find($id);
        if (!$barber) {
            return response()->json(['error' => 'Barber not found'], 404);
        }

        $oldEmail = $barber->email;
        
        $data = $req->validate([
            'name'           => 'nullable|string|max:255',
            'email'          => 'nullable|email|unique:barbers,email,' . $id,
            'phone'          => 'nullable|string|max:20',
            'specialty' => 'nullable|string|max:255',
            'bio'            => 'nullable|string',
            'image'          => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if (isset($data['name'])) {
            $barber->name = $data['name'];
        }
        if (isset($data['email'])) {
            $barber->email = $data['email'];
        }
        if (isset($data['phone'])) {
            $barber->phone = $data['phone'];
        }
        if (isset($data['specialty'])) {
            $barber->specialty = $data['specialty'];
        }
        if (isset($data['bio'])) {
            $barber->bio = $data['bio'];
        }

        $imageUpdated = false;
        if ($req->hasFile('image')) {
            // Delete old image
            if ($barber->image_path) {
                $oldPath = dirname(base_path()) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . $barber->image_path;
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }

            $file = $req->file('image');
            $filename = 'barber_' . time() . '_' . $id . '.' . $file->getClientOriginalExtension();
            $uploadDir = $this->getUploadPath();
            $file->move($uploadDir, $filename);
            $imagePath = 'uploads/' . $filename;
            $barber->image_path = $imagePath;
            $imageUpdated = true;
        }

        $barber->save();

        // Sync barber's image to user's avatar if user exists
        if ($imageUpdated && $barber->image_path) {
            $user = User::where('email', $oldEmail)->orWhere('email', $barber->email)->first();
            if ($user) {
                $user->avatar = $barber->image_path;
                $user->save();
            }
        }

        if ($barber->image_path) {
            $barber->image_url = url('storage/' . $barber->image_path);
        }

        return response()->json(['barber' => $barber]);
    }

    // DELETE /api/barbers/{id} - Delete barber (admin only)
    public function destroy($id)
    {
        $barber = Barber::find($id);
        if (!$barber) {
            return response()->json(['error' => 'Barber not found'], 404);
        }

        // Delete image if exists
        if ($barber->image_path) {
            $filePath = dirname(base_path()) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . $barber->image_path;
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        $barber->delete();

        return response()->json(['message' => 'Barber deleted successfully']);
    }
}
