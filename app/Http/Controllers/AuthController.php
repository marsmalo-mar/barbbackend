<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Token;
use App\Models\Barber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AuthController extends Controller
{
    protected string $appUrl;
    protected string $frontendUrl;

    public function __construct()
    {
        $this->appUrl      = config('app.url', 'http://127.0.0.1:8000');
        $this->frontendUrl = env('FRONTEND_URL', 'https://marsmalo-mar.github.io/barbbooking');
    }

    /* ============================
     * Registration & Verification
     * ============================ */

    // POST /api/auth/register
    public function register(Request $req)
    {
        $data = $req->validate([
            'name'      => 'required|string',
            'email'     => 'required|email|unique:users,email',
            'username'  => 'required|string|unique:users,username',
            'password'  => 'required|string|min:6',
            'avatar'    => 'nullable|file|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        // Handle avatar upload
        $avatarPath = null;
        if ($req->hasFile('avatar')) {
            $file = $req->file('avatar');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
            $name = 'avatar_' . time() . '.' . $ext;
            $barbershopDir = dirname(base_path());
            $uploadDir = $barbershopDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $file->move($uploadDir, $name);
            $avatarPath = 'uploads/' . $name;
        }

        $u = User::create([
            'name'      => $data['name'],
            'email'     => strtolower($data['email']),
            'username'  => $data['username'],
            'password'  => Hash::make($data['password']),
            'avatar'    => $avatarPath,
            'user_type' => 'user', // Default to regular user
        ]);

        // Generate 6-digit verification code
        $code = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        
        // Delete any old verification codes for this email
        DB::table('email_verifications')->where('email', $u->email)->delete();
        
        // Create email verification with code
        DB::table('email_verifications')->insert([
            'email'      => $u->email,
            'token'      => $code,
            'expires_at' => Carbon::now()->addHours(24),
            'created_at' => Carbon::now(),
        ]);

        $verifyUrl = $this->frontendUrl . '/verify-email.html?email=' . urlencode($u->email);
        $html = "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2 style='color: #1e3a8a;'>Welcome to StyleCut Barbershop!</h2>
                    <p>Hi {$u->name},</p>
                    <p>Thank you for registering. Please use the verification code below to verify your email address:</p>
                    <div style='background: #f0f9ff; border: 2px solid #1e3a8a; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0;'>
                        <h1 style='color: #1e3a8a; font-size: 36px; letter-spacing: 8px; margin: 0;'>{$code}</h1>
                    </div>
                    <p style='text-align: center; margin: 20px 0;'>
                        <a href='{$verifyUrl}' style='display: inline-block; background: #1e3a8a; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: 600;'>Verify Email Address</a>
                    </p>
                    <p>This code will expire in <strong>24 hours</strong>.</p>
                    <p>If you didn't create an account, please ignore this email.</p>
                    <hr style='border: none; border-top: 1px solid #e5e7eb; margin: 20px 0;'>
                    <p style='color: #6b7280; font-size: 12px;'>StyleCut Barbershop - Premium Grooming Services</p>
                 </div>";

        $this->sendMail($u->email, 'Verify Your StyleCut Account - Code: ' . $code, $html);

        return response()->json([
            'message' => 'Registered successfully! Please check your email for the verification code.',
            'email' => $u->email
        ], 201);
    }

    // POST /api/auth/verify-code
    public function verifyEmail(Request $req)
    {
        $data = $req->validate([
            'email' => 'required|email',
            'code'  => 'required|string|size:6',
        ]);

        $verification = DB::table('email_verifications')
            ->where('email', strtolower($data['email']))
            ->where('token', $data['code'])
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$verification) {
            return response()->json(['error' => 'Invalid or expired verification code'], 400);
        }

        $u = User::where('email', $verification->email)->first();
        if (!$u) {
            return response()->json(['error' => 'User not found'], 400);
        }

        $u->update(['email_verified_at' => Carbon::now()]);
        DB::table('email_verifications')->where('email', $verification->email)->delete();

        return response()->json(['message' => 'Email verified successfully! You can now login.']);
    }

    // POST /api/auth/resend
    public function resendVerify(Request $req)
    {
        $data = $req->validate([
            'email' => 'required|email',
        ]);

        $u = User::where('email', strtolower($data['email']))->first();
        if (!$u) {
            // do not reveal whether user exists
            return response()->json(['message' => 'If an account exists, a verification link was sent.']);
        }

        if ($u->email_verified_at !== null) {
            return response()->json(['message' => 'Account already verified.'], 200);
        }

        // Generate new 6-digit verification code
        $code = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        
        // Delete old verification codes for this email
        DB::table('email_verifications')->where('email', $u->email)->delete();
        
        // Create new verification code
        DB::table('email_verifications')->insert([
            'email'      => $u->email,
            'token'      => $code,
            'expires_at' => Carbon::now()->addHours(24),
            'created_at' => Carbon::now(),
        ]);

        $verifyUrl = $this->frontendUrl . '/verify-email.html?email=' . urlencode($u->email);
        $html = "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2 style='color: #1e3a8a;'>Verification Code</h2>
                    <p>Hi {$u->name},</p>
                    <p>Here is your new verification code:</p>
                    <div style='background: #f0f9ff; border: 2px solid #1e3a8a; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0;'>
                        <h1 style='color: #1e3a8a; font-size: 36px; letter-spacing: 8px; margin: 0;'>{$code}</h1>
                    </div>
                    <p style='text-align: center; margin: 20px 0;'>
                        <a href='{$verifyUrl}' style='display: inline-block; background: #1e3a8a; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: 600;'>Verify Email Address</a>
                    </p>
                    <p>This code will expire in <strong>24 hours</strong>.</p>
                    <p style='color: #6b7280; font-size: 12px;'>StyleCut Barbershop</p>
                 </div>";

        $this->sendMail($u->email, 'Your Verification Code - ' . $code, $html);

        return response()->json(['message' => 'New verification code sent to your email.']);
    }

    /* ==============
     * Login / Logout
     * ============== */

    // POST /api/auth/login
    public function login(Request $req)
    {
        try {
            $data = $req->validate([
                'username' => 'required',
                'password' => 'required',
            ]);

            $u = User::where('username', $data['username'])->first();

            if (!$u || !Hash::check($data['password'], $u->password)) {
                return response()->json(['error' => 'Invalid credentials'], 401);
            }

            if ($u->email_verified_at === null) {
                // Generate and send a new verification code
                $code = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                
                // Delete any old verification codes for this email
                DB::table('email_verifications')->where('email', $u->email)->delete();
                
                // Create new email verification with code
                DB::table('email_verifications')->insert([
                    'email'      => $u->email,
                    'token'      => $code,
                    'expires_at' => Carbon::now()->addHours(24),
                    'created_at' => Carbon::now(),
                ]);

                // Send verification email (don't fail if email sending fails)
                try {
                    $verifyUrl = $this->frontendUrl . '/verify-email.html?email=' . urlencode($u->email);
                    $html = "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                                <h2 style='color: #1e3a8a;'>Verify Your StyleCut Account</h2>
                                <p>Hi {$u->name},</p>
                                <p>Your account requires email verification before you can log in. Please use the verification code below:</p>
                                <div style='background: #f0f9ff; border: 2px solid #1e3a8a; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0;'>
                                    <h1 style='color: #1e3a8a; font-size: 36px; letter-spacing: 8px; margin: 0;'>{$code}</h1>
                                </div>
                                <p style='text-align: center; margin: 20px 0;'>
                                    <a href='{$verifyUrl}' style='display: inline-block; background: #1e3a8a; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: 600;'>Verify Email Address</a>
                                </p>
                                <p>This code will expire in <strong>24 hours</strong>.</p>
                                <p>If you didn't request this, please ignore this email.</p>
                                <hr style='border: none; border-top: 1px solid #e5e7eb; margin: 20px 0;'>
                                <p style='color: #6b7280; font-size: 12px;'>StyleCut Barbershop - Premium Grooming Services</p>
                             </div>";

                    $this->sendMail($u->email, 'Verify Your StyleCut Account - Code: ' . $code, $html);
                } catch (\Exception $mailError) {
                    // Log but don't fail the request if email fails
                    Log::warning('Failed to send verification email: ' . $mailError->getMessage());
                }

                return response()->json([
                    'error' => 'Your account needs to be activated. A verification code has been sent to your email.',
                    'email' => $u->email,
                    'requires_verification' => true
                ], 403);
            }

            $token = Str::uuid()->toString();
            
            // Create token with error handling
            try {
                Token::create([
                    'user_id'    => $u->id,
                    'token'      => $token,
                    'expires_at' => Carbon::now()->addHours(24),
                ]);
            } catch (\Illuminate\Database\QueryException $tokenError) {
                Log::error('Token creation failed: ' . $tokenError->getMessage(), [
                    'user_id' => $u->id,
                    'sql' => $tokenError->getSql() ?? 'N/A',
                    'bindings' => $tokenError->getBindings() ?? []
                ]);
                throw new \Exception('Failed to create authentication token. Please try again.');
            }
            
            return response()->json([
                'token' => $token,
                'user'  => [
                    'id'        => $u->id,
                    'email'     => $u->email,
                    'username'  => $u->username,
                    'name'      => $u->name,
                    'avatar'    => $this->getAvatarUrl($u->avatar),
                    'user_type' => $u->user_type ?? 'user',
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'message' => 'Username and password are required',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Login database error: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'sql' => $e->getSql() ?? 'N/A',
                'bindings' => $e->getBindings() ?? []
            ]);
            
            return response()->json([
                'error' => 'Database error',
                'message' => config('app.debug', false) 
                    ? 'Database connection failed: ' . $e->getMessage()
                    : 'Database error occurred. Please contact support.'
            ], 500);
        } catch (\Exception $e) {
            Log::error('Login error: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return more detailed error in development, generic in production
            $errorMessage = config('app.debug', false) 
                ? $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()
                : 'An error occurred during login. Please try again.';
            
            return response()->json([
                'error' => 'Login failed',
                'message' => $errorMessage
            ], 500);
        }
    }

    // POST /api/auth/logout (protected)
    public function logout(Request $req)
    {
        $currentToken = $req->attributes->get('auth_token') ?? $req->get('auth_token');
        if ($currentToken) {
            Token::where('token', $currentToken)->delete();
        }
        return response()->json(['message' => 'Logged out']);
    }

    /* ==================
     * Profile / Password
     * ================== */

    // GET /api/users/me (protected)
    public function me(Request $req)
    {
        $u = $req->attributes->get('auth_user') ?? $req->get('auth_user');
        return response()->json(['user' => [
            'id'        => $u->id,
            'email'     => $u->email,
            'username'  => $u->username,
            'name'      => $u->name,
            'avatar'    => $this->getAvatarUrl($u->avatar),
            'user_type' => $u->user_type ?? 'user',
        ]]);
    }

    private function getAvatarUrl($path)
    {
        if (!$path) {
            return 'storage/uploads/profile.png';
        }

        // Normalize path
        if (strpos($path, 'storage/uploads/') === 0) {
            return $path;
        }

        if (strpos($path, 'uploads/') === 0) {
            return 'storage/' . $path;
        }

        return 'storage/uploads/' . $path;
    }

    // PUT /api/users/me (protected)
    public function updateMe(Request $req)
    {
        $u = $req->attributes->get('auth_user') ?? $req->get('auth_user');
        $data = $req->validate([
            'name'     => 'required|string',
            'username' => 'required|string|unique:users,username,' . $u->id,
            'email'    => 'required|email|unique:users,email,' . $u->id,
        ]);

        $u->update($data);

        return response()->json(['message' => 'Profile updated']);
    }

    // PUT /api/users/me/password (protected)
    public function changePassword(Request $req)
    {
        $u = $req->attributes->get('auth_user') ?? $req->get('auth_user');
        $data = $req->validate([
            'oldPassword' => 'required|string',
            'newPassword' => 'required|string|min:6',
        ]);

        if (!Hash::check($data['oldPassword'], $u->password)) {
            return response()->json(['error' => 'Old password incorrect'], 400);
        }

        $u->password = Hash::make($data['newPassword']);
        $u->save();

        // Invalidate all tokens for this user to force re-login with new password
        Token::where('user_id', $u->id)->delete();

        return response()->json(['message' => 'Password changed successfully. Please log in again.']);
    }

    // POST|PUT /api/users/me/avatar (protected, multipart)
    public function avatarUpload(Request $req)
    {
        $u = $req->attributes->get('auth_user') ?? $req->get('auth_user');

        if (!$req->hasFile('avatar') || !$req->file('avatar')->isValid()) {
            return response()->json(['error' => 'No file uploaded'], 400);
        }

        $f = $req->file('avatar');
        $ext = strtolower($f->getClientOriginalExtension());
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            return response()->json(['error' => 'Only image files are allowed'], 400);
        }

        $filename = 'avatar_' . time() . '_' . $u->id . '.' . $ext;
        $barbershopDir = dirname(base_path());
        $uploadDir = $barbershopDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $f->move($uploadDir, $filename);

        $u->avatar = 'uploads/' . $filename;
        $u->save();

        // If user is a barber, sync avatar to barber's image_path
        if ($u->user_type === 'barber') {
            $barber = Barber::where('email', $u->email)->first();
            if ($barber) {
                $barber->image_path = 'uploads/' . $filename;
                $barber->save();
            }
        }

        return response()->json(['avatar' => 'storage/uploads/' . $filename]);
    }

    /* ======================
     * Forgot / Reset password
     * ====================== */

    // POST /api/auth/forgot
    public function forgot(Request $req)
    {
        $data = $req->validate([
            'email' => 'required|email',
        ]);

        $email = strtolower($data['email']);
        $u = User::where('email', $email)->first();

        if ($u) {
            $token = Str::uuid()->toString();
            DB::table('password_resets')->insert([
                'user_id'    => $u->id,
                'token'      => $token,
                'expires_at' => Carbon::now()->addMinutes(60),
            ]);

            // front-end reset page
            $link = $this->frontendUrl . '/reset.html?token=' . urlencode($token);
            $html = "<p>Hi {$u->name},</p>
                     <p>You requested a password reset. The link below is valid for 60 minutes:</p>
                     <p><a href=\"{$link}\">Reset your password</a></p>";

            $this->sendMail($u->email, 'Reset your Train Management password', $html);
        }

        // Always return success to avoid user enumeration
        return response()->json(['message' => 'If an account exists, a reset link has been sent.']);
    }

    // POST /api/auth/reset
    public function reset(Request $req)
    {
        $data = $req->validate([
            'token'    => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        $rec = DB::table('password_resets')
            ->where('token', $data['token'])
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$rec) {
            return response()->json(['error' => 'Invalid or expired token'], 400);
        }

        $u = User::find($rec->user_id);
        if (!$u) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $u->password = Hash::make($data['password']);
        $u->save();

        // Clean up tokens & reset record
        Token::where('user_id', $u->id)->delete();
        DB::table('password_resets')->where('token', $data['token'])->delete();

        // Redirect to frontend login or return JSON
        return response()->json(['message' => 'Password updated. You may now log in.']);
    }

    // POST /api/auth/forgot-password/send-otp
    public function sendOtp(Request $req)
    {
        $data = $req->validate([
            'email' => 'required|email',
        ]);

        $email = strtolower($data['email']);
        $u = User::where('email', $email)->first();

        if (!$u) {
            // Do not reveal if user exists
            return response()->json(['message' => 'If an account exists, an OTP has been sent.'], 200);
        }

        // Generate 6-digit OTP
        $otp = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store OTP in password_resets table (reuse for OTP)
        DB::table('password_resets')->updateOrInsert(
            ['user_id' => $u->id],
            [
                'token'      => $otp,
                'expires_at' => Carbon::now()->addMinutes(10), // OTP valid for 10 minutes
            ]
        );

        // Send OTP via email
        $html = "<p>Hi {$u->name},</p>
                 <p>Your OTP for password reset is: <strong>{$otp}</strong></p>
                 <p>This OTP is valid for 10 minutes.</p>";

        $this->sendMail($u->email, 'Your OTP for Train Management password reset', $html);

        return response()->json(['message' => 'If an account exists, an OTP has been sent.'], 200);
    }

    // POST /api/auth/forgot-password/verify-otp
    public function verifyOtp(Request $req)
    {
        $data = $req->validate([
            'email' => 'required|email',
            'otp'   => 'required|string|size:6',
        ]);

        $email = strtolower($data['email']);
        $u = User::where('email', $email)->first();

        if (!$u) {
            return response()->json(['error' => 'Invalid email or OTP'], 400);
        }

        $rec = DB::table('password_resets')
            ->where('user_id', $u->id)
            ->where('token', $data['otp'])
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$rec) {
            return response()->json(['error' => 'Invalid or expired OTP'], 400);
        }

        // OTP verified, but don't delete yet - keep for reset step
        return response()->json(['message' => 'OTP verified successfully'], 200);
    }

    // POST /api/auth/forgot-password/reset
    public function resetWithOtp(Request $req)
    {
        $data = $req->validate([
            'email'       => 'required|email',
            'otp'         => 'required|string|size:6',
            'new_password' => 'required|string|min:6',
        ]);

        $email = strtolower($data['email']);
        $u = User::where('email', $email)->first();

        if (!$u) {
            return response()->json(['error' => 'Invalid email or OTP'], 400);
        }

        $rec = DB::table('password_resets')
            ->where('user_id', $u->id)
            ->where('token', $data['otp'])
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$rec) {
            return response()->json(['error' => 'Invalid or expired OTP'], 400);
        }

        // Update password
        $u->password = Hash::make($data['new_password']);
        $u->save();

        // Clean up OTP and invalidate tokens
        DB::table('password_resets')->where('user_id', $u->id)->delete();
        Token::where('user_id', $u->id)->delete();

        return response()->json(['message' => 'Password reset successfully. You may now log in.'], 200);
    }

    /* =============
     * Mail helper
     * ============= */
    private function sendMail(string $to, string $subject, string $html): void
    {
        try {
            Mail::html($html, function ($message) use ($to, $subject) {
                $message->to($to)
                        ->subject($subject)
                        ->from(config('mail.from.address'), config('mail.from.name'));
            });
        } catch (\Throwable $e) {
            // In dev, you can log the error but don't block the request
            // logger($e->getMessage());
        }
    }
}

