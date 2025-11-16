<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Models\User;
use App\Models\EmailOtp;
use App\Jobs\SendOtpJob;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AuthController extends Controller {
    public function register(RegisterRequest $req){
        try {
            // Normalize email (lowercase, trim)
            $email = strtolower(trim($req->email));
            
            // Cek apakah email sudah terdaftar
            if (User::where('email', $email)->exists()) {
                return response()->json([
                    'message' => 'Email sudah terdaftar. Silakan gunakan email lain atau login.',
                    'error_type' => 'email_exists'
                ], 422);
            }

            // Generate OTP (6 digit random)
            $otp = str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
            
            // Simpan data di SESSION (BUKAN database!)
            session([
                'pending_registration' => [
                    'name' => trim($req->name),
                    'email' => $email,
                    'phone' => trim($req->phone),
                    'address' => trim($req->address),
                    'education' => $req->education,
                    'password' => $req->password, // Akan di-hash saat create user
                    'otp_code' => $otp,
                    'otp_expires_at' => Carbon::now()->addMinutes(10)->toDateTimeString(),
                ]
            ]);

            Log::info('Registration data saved to session', [
                'email' => $email,
                'otp_expires_at' => session('pending_registration')['otp_expires_at'],
                'session_id' => session()->getId(),
                'has_session' => session()->has('pending_registration')
            ]);

            // Send OTP via email (sync untuk memastikan langsung terkirim)
            try {
                Log::info('Sending OTP email for registration', [
                    'email' => $email,
                    'otp' => $otp
                ]);
                
                // Call BrevoService directly
                $brevoService = new \App\Services\BrevoService();
                $result = $brevoService->sendEmailWithView(
                    $email,
                    trim($req->name),
                    'Verifikasi Email - EduFest',
                    'emails.otp',
                    [
                        'user' => (object)[
                            'name' => trim($req->name),
                            'email' => $email
                        ],
                        'otp' => $otp,
                        'type' => 'verification'
                    ]
                );
                
                if ($result['success']) {
                    Log::info('OTP email sent successfully', [
                        'email' => $email,
                        'message_id' => $result['message_id'] ?? 'N/A',
                        'otp' => $otp, // Log OTP untuk debugging (HAPUS di production!)
                        'session_id' => session()->getId()
                    ]);
                } else {
                    Log::error('Failed to send OTP email during registration', [
                        'email' => $email,
                        'error' => $result['error'] ?? 'Unknown error',
                        'full_error' => $result['full_error'] ?? null
                    ]);
                    // Clear session jika email gagal
                    session()->forget('pending_registration');
                    return response()->json([
                        'message' => 'Gagal mengirim email OTP. Silakan coba lagi.',
                        'error' => $result['error'] ?? 'Unknown error'
                    ], 500);
                }
            } catch (\Exception $emailError) {
                Log::error('Exception sending OTP email during registration', [
                    'email' => $email,
                    'error' => $emailError->getMessage(),
                    'trace' => $emailError->getTraceAsString()
                ]);
                // Clear session jika email gagal
                session()->forget('pending_registration');
                return response()->json([
                    'message' => 'Gagal mengirim email OTP: ' . $emailError->getMessage()
                ], 500);
            }

            return response()->json([
                'message' => 'Registrasi berhasil. Silakan periksa email Anda untuk kode OTP.',
                'email' => $email
            ], 201);
        } catch (\Exception $e) {
            Log::error('Registration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Clear session on error
            session()->forget('pending_registration');
        return response()->json([
                'message' => 'Registrasi gagal: ' . $e->getMessage()
            ], 500);
        }
    }

    public function verifyEmail(VerifyOtpRequest $req){
        try {
            // Ambil data dari session
            $pendingRegistration = session('pending_registration');
            
            if (!$pendingRegistration) {
            return response()->json([
                    'message' => 'Sesi registrasi tidak ditemukan. Silakan daftar ulang.',
                    'error_type' => 'session_expired'
            ], 404);
        }

            // 1. Cek OTP Expiry (10 menit)
            $otpExpiresAt = Carbon::parse($pendingRegistration['otp_expires_at']);
            if (Carbon::now()->greaterThan($otpExpiresAt)) {
                // Clear expired session
                session()->forget('pending_registration');
                return response()->json([
                    'message' => 'OTP kedaluwarsa. Silakan daftar ulang atau request OTP baru.',
                    'error_type' => 'otp_expired'
                ], 422);
            }

            // 2. Verify OTP (compare plain text, tidak perlu hash karena di session)
            if ($req->code !== $pendingRegistration['otp_code']) {
                return response()->json([
                    'message' => 'OTP tidak valid. Silakan periksa kembali kode OTP Anda.',
                    'error_type' => 'invalid_otp'
                ], 422);
            }

            // 3. OTP Valid! Cek apakah email masih available (race condition check)
            $email = $pendingRegistration['email'];
            if (User::where('email', $email)->exists()) {
                session()->forget('pending_registration');
                return response()->json([
                    'message' => 'Email sudah terdaftar. Silakan login.',
                    'error_type' => 'email_exists'
                ], 409);
            }

            // 4. Create User di Database
            $user = User::create([
                'name' => $pendingRegistration['name'],
                'email' => $email,
                'phone' => $pendingRegistration['phone'],
                'address' => $pendingRegistration['address'],
                'education' => $pendingRegistration['education'],
                'password' => Hash::make($pendingRegistration['password']),
                'email_verified_at' => now(), // Langsung verified karena sudah verify OTP
            ]);

            Log::info('User created after OTP verification', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            // 5. Bersihkan session
            session()->forget('pending_registration');

            // 6. Auto login user (create token)
            $token = $user->createToken('api')->plainTextToken;

        return response()->json([
                'message' => 'Email berhasil diverifikasi. Akun Anda telah dibuat.',
                'user' => $user,
                'token' => $token,
                'is_admin' => $user->isAdmin()
            ], 200);
        } catch (\Exception $e) {
            Log::error('Email verification failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Verifikasi gagal: ' . $e->getMessage()
            ], 500);
        }
    }

    public function login(LoginRequest $req){
        $user = User::where('email',$req->email)->first();

        if(!$user){
            return response()->json([
                'message' => 'Email tidak terdaftar. Silakan daftar terlebih dahulu untuk membuat akun baru.',
                'error_type' => 'user_not_found',
                'suggestion' => 'Apakah Anda belum memiliki akun? Silakan daftar di halaman registrasi.'
            ], 404);
        }

        if(!Hash::check($req->password, $user->password)){
            return response()->json([
                'message' => 'Password salah. Silakan periksa kembali password Anda.',
                'error_type' => 'invalid_password'
            ], 401);
        }

        // Debug: Check email verification status
        if(!$user->email_verified_at){
            return response()->json([
                'message' => 'Email belum terverifikasi. Silakan verifikasi email Anda terlebih dahulu untuk melanjutkan login.',
                'error_type' => 'email_not_verified',
                    'user_id' => $user->id,
                'suggestion' => 'Periksa inbox email Anda untuk kode OTP verifikasi. Jika tidak menerima email, silakan request OTP baru.'
            ], 403);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
            'is_admin' => $user->isAdmin()
        ]);
    }

    public function requestReset(Request $req){
        $req->validate([
            'email' => 'required|email|exists:users,email'
        ]);

        $user = User::where('email', $req->email)->first();

        if(!$user->email_verified_at){
            return response()->json(['message'=>'Email not verified. Please verify your email first.'],403);
        }

        // Generate OTP for password reset
        $otp = str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
        EmailOtp::create([
            'user_id'=>$user->id,
            'code_hash'=>Hash::make($otp),
            'type'=>'reset',
            'expires_at'=>now()->addMinutes(10)
        ]);

        // Send OTP via email
        SendOtpJob::dispatch($user, $otp, 'reset');

        return response()->json([
            'message'=>'Password reset OTP sent to your email.',
            'user_id'=>$user->id
        ]);
    }

    public function resetPassword(Request $req){
        $req->validate([
            'user_id' => 'required|exists:users,id',
            'code' => 'required|string',
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]{8,}$/'
            ]
        ], [
            'password.regex' => 'Password harus mengandung minimal 8 karakter dengan kombinasi: huruf kecil, huruf besar, angka, dan karakter spesial (@$!%*?&). Contoh: Password123#',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $otp = EmailOtp::where('user_id',$req->user_id)
            ->where('type','reset')
            ->whereNull('used_at')
            ->where('expires_at','>=',now())
            ->latest()
            ->first();

        if(!$otp){
            return response()->json(['message'=>'Invalid or expired OTP'],404);
        }

        if(!Hash::check($req->code, $otp->code_hash)){
            $user = User::findOrFail($req->user_id);
            $token = $user->createToken('api')->plainTextToken;
            return response()->json([
                'user' => $user,
                'token' => $token,
                'is_admin' => $user->isAdmin()
            ]);
        }

        // Update password
        $user = User::findOrFail($req->user_id);
        $user->update(['password' => Hash::make($req->password)]);

        // Mark OTP as used
        $otp->update(['used_at'=>now()]);

        return response()->json(['message'=>'Password reset successfully']);
    }

    public function resendOtp(Request $req){
        try {
            // Ambil data dari session
            $pendingRegistration = session('pending_registration');
            
            if (!$pendingRegistration) {
                return response()->json([
                    'message' => 'Sesi registrasi tidak ditemukan. Silakan daftar ulang.',
                    'error_type' => 'session_expired'
                ], 404);
            }

            // Generate OTP baru
            $otp = str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
            
            // Update OTP di session
            $pendingRegistration['otp_code'] = $otp;
            $pendingRegistration['otp_expires_at'] = Carbon::now()->addMinutes(10)->toDateTimeString();
            session(['pending_registration' => $pendingRegistration]);

            Log::info('Resending OTP for registration', [
                'email' => $pendingRegistration['email']
            ]);

            // Send OTP via email (sync untuk memastikan langsung terkirim)
            try {
                $brevoService = new \App\Services\BrevoService();
                $result = $brevoService->sendEmailWithView(
                    $pendingRegistration['email'],
                    $pendingRegistration['name'],
                    'Verifikasi Email - EduFest',
                    'emails.otp',
                    [
                        'user' => (object)[
                            'name' => $pendingRegistration['name'],
                            'email' => $pendingRegistration['email']
                        ],
                        'otp' => $otp,
                        'type' => 'verification'
                    ]
                );
                
                if ($result['success']) {
                    Log::info('Resend OTP email sent successfully', [
                        'email' => $pendingRegistration['email'],
                        'message_id' => $result['message_id'] ?? 'N/A'
                    ]);
                } else {
                    Log::error('Failed to resend OTP email', [
                        'email' => $pendingRegistration['email'],
                        'error' => $result['error'] ?? 'Unknown error'
                    ]);
                    return response()->json([
                        'message' => 'Gagal mengirim email OTP: ' . ($result['error'] ?? 'Unknown error')
                    ], 500);
                }
            } catch (\Exception $emailError) {
                Log::error('Exception resending OTP email', [
                    'email' => $pendingRegistration['email'],
                    'error' => $emailError->getMessage()
                ]);
                return response()->json([
                    'message' => 'Gagal mengirim email OTP: ' . $emailError->getMessage()
                ], 500);
            }

            return response()->json([
                'message' => 'Kode OTP baru telah dikirim ke email Anda. Silakan periksa inbox email Anda.',
                'email' => $pendingRegistration['email']
            ]);
        } catch (\Exception $e) {
            Log::error('Resend OTP failed', [
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'message' => 'Gagal mengirim ulang OTP: ' . $e->getMessage()
            ], 500);
        }
    }
}
