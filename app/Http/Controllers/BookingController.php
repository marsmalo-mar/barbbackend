<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Barber;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class BookingController extends Controller
{
    // GET /api/bookings/{id}
    public function show($id)
    {
        $booking = Booking::with(['user', 'service', 'barber'])->find($id);
        if (!$booking) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json($booking);
    }

    // GET /api/bookings
    public function index(Request $req)
    {
        $user = $req->attributes->get('auth_user');
        $search = trim($req->query('search', ''));
        $status = $req->query('status', '');

        $q = Booking::with(['user', 'service', 'barber']);

        // Users can only see their own bookings unless admin
        // For simplicity, showing all for now
        // $q->where('user_id', $user->id);

        if ($search !== '') {
            $q->where(function ($x) use ($search) {
                $x->whereHas('service', function($query) use ($search) {
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

        $items = $q->orderBy('booking_date', 'desc')
                   ->orderBy('booking_time', 'desc')
                   ->get();

        return response()->json($items);
    }

    // GET /api/bookings/my - User's own bookings
    public function myBookings(Request $req)
    {
        $user = $req->attributes->get('auth_user');
        $status = $req->query('status', '');
        
        $query = Booking::with(['service', 'barber'])
            ->where('user_id', $user->id);
        
        // Apply status filter if provided
        if ($status !== '') {
            $query->where('status', $status);
        }
        
        $bookings = $query
            ->orderByRaw("CASE 
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

    // POST /api/bookings
    public function store(Request $req)
    {
        $user = $req->attributes->get('auth_user');

        $data = $req->validate([
            'service_id'    => 'required|exists:services,id',
            'barber_id'     => 'required',
            'booking_date'  => 'required|date',
            'booking_time'  => 'required|date_format:H:i',
            'notes'         => 'nullable|string',
        ]);

        // Resolve barber_id - could be a user ID or barber ID
        $barberId = $this->resolveBarberId($data['barber_id']);
        if (!$barberId) {
            return response()->json([
                'error' => 'Invalid barber selected',
                'errors' => ['barber_id' => ['The selected barber is invalid.']]
            ], 422);
        }

        // Manually validate that date is tomorrow or later
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        if ($data['booking_date'] < $tomorrow) {
            return response()->json([
                'error' => 'Booking date must be tomorrow or later',
                'errors' => ['booking_date' => ['The booking date must be tomorrow or later.']]
            ], 422);
        }

        // Use resolved barber_id
        $data['barber_id'] = $barberId;

        // Check if the same user already has a booking for the same service, date, and time
        // Exclude cancelled bookings as they are no longer active
        $userDuplicate = Booking::where('user_id', $user->id)
            ->where('service_id', $data['service_id'])
            ->where('booking_date', $data['booking_date'])
            ->where('booking_time', $data['booking_time'])
            ->where('status', '!=', 'cancelled') // Allow re-booking if previous was cancelled
            ->exists();

        if ($userDuplicate) {
            return response()->json([
                'error' => 'You already have a booking for this service, date, and time',
                'errors' => ['booking' => ['You cannot book the same service, date, and time twice.']]
            ], 422);
        }

        // Check if time slot is already confirmed by another user (only block if confirmed, allow multiple pending)
        $exists = Booking::where('barber_id', $data['barber_id'])
            ->where('booking_date', $data['booking_date'])
            ->where('booking_time', $data['booking_time'])
            ->where('status', 'confirmed') // Only block if already confirmed
            ->exists();

        if ($exists) {
            return response()->json(['error' => 'This time slot is already booked'], 422);
        }

        $booking = Booking::create([
            'user_id'       => $user->id,
            'service_id'    => $data['service_id'],
            'barber_id'     => $data['barber_id'],
            'booking_date'  => $data['booking_date'],
            'booking_time'  => $data['booking_time'],
            'notes'         => $data['notes'] ?? null,
            'status'        => 'pending',
        ]);

        $booking->load(['service', 'barber']);
        return response()->json(['booking' => $booking], 201);
    }

    // PUT /api/bookings/{id}
    public function update(Request $req, $id)
    {
        $user = $req->attributes->get('auth_user');
        $booking = Booking::find($id);

        if (!$booking) {
            return response()->json(['error' => 'Not found'], 404);
        }

        // Users can only update their own bookings
        if ($booking->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $data = $req->validate([
            'service_id'    => 'required|exists:services,id',
            'barber_id'     => 'required',
            'booking_date'  => 'required|date',
            'booking_time'  => 'required|date_format:H:i',
            'notes'         => 'nullable|string',
            'status'        => 'nullable|in:pending,confirmed,completed,cancelled',
        ]);

        // Resolve barber_id - could be a user ID or barber ID
        $barberId = $this->resolveBarberId($data['barber_id']);
        if (!$barberId) {
            return response()->json([
                'error' => 'Invalid barber selected',
                'errors' => ['barber_id' => ['The selected barber is invalid.']]
            ], 422);
        }

        // Manually validate that date is tomorrow or later
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        if ($data['booking_date'] < $tomorrow) {
            return response()->json([
                'error' => 'Booking date must be tomorrow or later',
                'errors' => ['booking_date' => ['The booking date must be tomorrow or later.']]
            ], 422);
        }

        // Use resolved barber_id
        $data['barber_id'] = $barberId;

        // Check if the same user already has another booking for the same service, date, and time
        // Exclude the current booking being updated and cancelled bookings
        $userDuplicate = Booking::where('user_id', $user->id)
            ->where('service_id', $data['service_id'])
            ->where('booking_date', $data['booking_date'])
            ->where('booking_time', $data['booking_time'])
            ->where('id', '!=', $id) // Exclude current booking
            ->where('status', '!=', 'cancelled') // Allow re-booking if previous was cancelled
            ->exists();

        if ($userDuplicate) {
            return response()->json([
                'error' => 'You already have another booking for this service, date, and time',
                'errors' => ['booking' => ['You cannot have multiple bookings for the same service, date, and time.']]
            ], 422);
        }

        // Check if new time slot is available (excluding current booking)
        // Only block if confirmed, allow multiple pending bookings
        $exists = Booking::where('barber_id', $data['barber_id'])
            ->where('booking_date', $data['booking_date'])
            ->where('booking_time', $data['booking_time'])
            ->where('id', '!=', $id)
            ->where('status', 'confirmed') // Only block if already confirmed
            ->exists();

        if ($exists) {
            return response()->json(['error' => 'This time slot is already booked'], 422);
        }

        $booking->update($data);
        $booking->load(['service', 'barber']);

        return response()->json(['booking' => $booking]);
    }

    // PATCH /api/bookings/{id}/status
    public function updateStatus(Request $req, $id)
    {
        $booking = Booking::find($id);
        if (!$booking) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $data = $req->validate([
            'status' => 'required|in:pending,confirmed,completed,cancelled',
        ]);

        $booking->status = $data['status'];
        $booking->save();

        return response()->json(['booking' => $booking]);
    }

    // DELETE /api/bookings/{id}
    public function destroy($id)
    {
        $booking = Booking::find($id);
        if (!$booking) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $booking->delete();
        return response()->json(['message' => 'Booking cancelled']);
    }

    // GET /api/available-slots
    public function availableSlots(Request $req)
    {
        $data = $req->validate([
            'barber_id' => 'required',
            'date'      => 'required|date',
        ]);

        // Resolve barber_id - could be a user ID or barber ID
        $barberId = $this->resolveBarberId($data['barber_id']);
        if (!$barberId) {
            return response()->json([
                'error' => 'Invalid barber selected',
                'errors' => ['barber_id' => ['The selected barber is invalid.']]
            ], 422);
        }

        // Manually validate that date is tomorrow or later
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        if ($data['date'] < $tomorrow) {
            return response()->json([
                'error' => 'Booking date must be tomorrow or later',
                'errors' => ['date' => ['The booking date must be tomorrow or later.']]
            ], 422);
        }

        $date = $data['date'];

        // Define fixed time slot ranges (2-hour slots)
        $timeSlots = [
            ['start' => '08:00', 'end' => '10:00', 'display' => '8:00am-10:00am'],
            ['start' => '10:00', 'end' => '12:00', 'display' => '10:00am-12:00pm'],
            ['start' => '13:00', 'end' => '15:00', 'display' => '1:00pm-3:00pm'],
            ['start' => '15:00', 'end' => '17:00', 'display' => '3:00pm-5:00pm'],
            ['start' => '17:00', 'end' => '19:00', 'display' => '5:00pm-7:00pm'],
        ];

        // Get booked slots (start times) - only check confirmed bookings
        // Multiple pending bookings are allowed for the same slot
        $booked = Booking::where('barber_id', $barberId)
            ->where('booking_date', $date)
            ->where('status', 'confirmed') // Only check confirmed bookings
            ->pluck('booking_time')
            ->map(function($time) {
                return substr($time, 0, 5); // Format to HH:MM
            })
            ->toArray();

        // Filter out booked slots and format for display
        $available = [];
        foreach ($timeSlots as $slot) {
            if (!in_array($slot['start'], $booked)) {
                // Return both display format and start time (for storage)
                $available[] = [
                    'value' => $slot['start'], // Store start time
                    'display' => $slot['display'] // Display format
                ];
            }
        }

        return response()->json(['available_slots' => $available]);
    }

    /**
     * Resolve barber ID from either a barber ID or user ID
     * @param mixed $id User ID or Barber ID
     * @return int|null The barber ID or null if not found
     */
    private function resolveBarberId($id)
    {
        // First, check if it's already a valid barber ID
        $barber = Barber::find($id);
        if ($barber) {
            return $barber->id;
        }

        // If not, check if it's a user ID for a barber user
        $user = User::where('id', $id)
            ->where('user_type', 'barber')
            ->first();

        if ($user) {
            // Try to find barber record by email (if email column exists)
            $barber = null;
            if (Schema::hasColumn('barbers', 'email')) {
                $barber = Barber::where('email', $user->email)->first();
            }
            
            // If not found by email, try to find by name
            if (!$barber) {
                $barber = Barber::where('name', $user->name)->first();
            }
            
            // If still not found, create a barber record for this user
            if (!$barber) {
                $barber = Barber::create([
                    'name' => $user->name,
                    'email' => Schema::hasColumn('barbers', 'email') ? $user->email : null,
                    'specialty' => 'Expert Barber',
                    'bio' => '',
                ]);
            }
            
            return $barber->id;
        }

        return null;
    }
}

