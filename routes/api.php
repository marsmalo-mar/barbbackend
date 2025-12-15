<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\BarberController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\AdminController;

// ---- Test Route ----
Route::get('/test', function () {
    return response()->json([
        'message' => 'API is working!',
        'status' => 'success',
        'timestamp' => now()->toIso8601String()
    ]);
});

// ---- Database Test Route ----
Route::get('/test/db', function () {
    try {
        // Test database connection
        \DB::connection()->getPdo();
        
        // Run a simple query to verify database is working
        $result = \DB::select('SELECT 1 as test');
        
        // Get database info
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");
        
        return response()->json([
            'message' => 'Database connection successful!',
            'status' => 'success',
            'connection' => $connection,
            'driver' => $driver,
            'timestamp' => now()->toIso8601String()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Database connection failed!',
            'status' => 'error',
            'error' => $e->getMessage(),
            'timestamp' => now()->toIso8601String()
        ], 500);
    }
});

// ---- Public Auth ----
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/verify-code', [AuthController::class, 'verifyEmail']);  // Verify with code
Route::post('/auth/resend',   [AuthController::class, 'resendVerify']);
Route::post('/auth/login',    [AuthController::class, 'login']);
Route::post('/auth/forgot',   [AuthController::class, 'forgot']);
Route::post('/auth/reset',    [AuthController::class, 'reset']);

// OTP-based forgot password
Route::post('/auth/forgot-password/send-otp',   [AuthController::class, 'sendOtp']);
Route::post('/auth/forgot-password/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/auth/forgot-password/reset',      [AuthController::class, 'resetWithOtp']);

// Public routes (no auth required)
Route::get('/services', [ServiceController::class, 'index']);
Route::get('/services/{id}', [ServiceController::class, 'show']);
Route::get('/barbers', [BarberController::class, 'index']);
Route::get('/barbers/{id}', [BarberController::class, 'show']);

// ---- Protected (auth.token) ----
Route::middleware('auth.token')->group(function () {

    // session-like token endpoints
    Route::post('/auth/logout',      [AuthController::class, 'logout']);

    // user profile
    Route::get ('/users/me',         [AuthController::class, 'me']);
    Route::put ('/users/me',         [AuthController::class, 'updateMe']);
    Route::post('/users/me/password',[AuthController::class, 'changePassword']);

    // avatar upload must support multipart; accept POST + method override
    Route::post('/users/me/avatar', [AuthController::class, 'avatarUpload']);

    // services (admin only for create/update/delete)
    Route::post  ('/services',            [ServiceController::class, 'store']);
    Route::match(['POST','PUT'],'/services/{id}', [ServiceController::class, 'update']);
    Route::delete('/services/{id}',       [ServiceController::class, 'destroy']);

    // barbers (admin only for create/update/delete)
    Route::post  ('/barbers',            [BarberController::class, 'store']);
    Route::match(['POST','PUT'],'/barbers/{id}', [BarberController::class, 'update']);
    Route::delete('/barbers/{id}',       [BarberController::class, 'destroy']);

    // bookings
    Route::get   ('/bookings',           [BookingController::class, 'index']);
    Route::get   ('/bookings/my',        [BookingController::class, 'myBookings']);
    Route::get   ('/bookings/{id}',      [BookingController::class, 'show']);
    Route::post  ('/bookings',           [BookingController::class, 'store']);
    Route::put   ('/bookings/{id}',      [BookingController::class, 'update']);
    Route::patch ('/bookings/{id}/status', [BookingController::class, 'updateStatus']);
    Route::delete('/bookings/{id}',      [BookingController::class, 'destroy']);

    // available time slots
    Route::get('/available-slots', [BookingController::class, 'availableSlots']);

    // ---- Admin Routes (admin middleware) ----
    Route::middleware('admin')->prefix('admin')->group(function () {
        // Appointments
        Route::get('/appointments', [AdminController::class, 'getAllAppointments']);
        Route::patch('/appointments/{id}/approve', [AdminController::class, 'approveAppointment']);
        Route::patch('/appointments/{id}/decline', [AdminController::class, 'declineAppointment']);

        // Services Management
        Route::get('/services', [AdminController::class, 'getAllServices']);
        Route::post('/services', [AdminController::class, 'createService']);
        Route::match(['POST', 'PUT'], '/services/{id}', [AdminController::class, 'updateService']);
        Route::delete('/services/{id}', [AdminController::class, 'deleteService']);

        // Users Management
        Route::get('/users', [AdminController::class, 'getAllUsers']);
        Route::post('/users', [AdminController::class, 'createUser']);
        Route::match(['POST', 'PUT'], '/users/{id}', [AdminController::class, 'updateUser']);
        Route::delete('/users/{id}', [AdminController::class, 'deleteUser']);

        // Admin Profile
        Route::match(['POST', 'PUT'], '/profile', [AdminController::class, 'updateProfile']);
    });

    // ---- Barber Routes (barber middleware) ----
    Route::middleware('barber')->prefix('barber')->group(function () {
        // Appointments
        Route::get('/appointments', [BarberController::class, 'getMyAppointments']);
        Route::patch('/appointments/{id}/complete', [BarberController::class, 'markComplete']);

        // Profile
        Route::get('/profile', [BarberController::class, 'getProfile']);
        Route::post('/profile', [BarberController::class, 'updateProfile']);
        Route::post('/profile/image', [BarberController::class, 'uploadProfileImage']);

        // Credentials
        Route::post('/credentials', [BarberController::class, 'updateCredentials']);
    });
});
