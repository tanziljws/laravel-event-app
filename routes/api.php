<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\CertificateController;
use App\Http\Controllers\Api\AdminReportController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminParticipantController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\UserEventHistoryController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\ChangePasswordController;
use App\Http\Controllers\Api\ContactMessageController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\Admin\BannerController as AdminBannerController;
use App\Http\Controllers\Api\Admin\FotoController as AdminFotoController;

// CRITICAL FIX: Handle OPTIONS preflight requests for Safari
// Safari mengirim OPTIONS untuk preflight, jika tidak di-handle akan fallback ke GET
// Ini menyebabkan MethodNotAllowedHttpException
Route::options('{any}', function () {
    $origin = request()->headers->get('Origin');
    $allowedOrigins = [
        'https://frontend-reactjs-production.up.railway.app',
        'http://localhost:3000',
        'http://localhost:5173',
    ];
    
    // Check if origin is allowed
    $allowedOrigin = in_array($origin, $allowedOrigins) ? $origin : '*';
    
    return response()->json(['status' => 'ok'], 200)
        ->header('Access-Control-Allow-Origin', $allowedOrigin)
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-XSRF-TOKEN')
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Credentials', 'false')
        ->header('Access-Control-Max-Age', '3600');
})->where('any', '.*');

// Auth routes - TIDAK pakai session middleware karena Railway Proxy conflict
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/verify-email', [AuthController::class, 'verifyEmail']);
Route::post('/auth/resend-otp', [AuthController::class, 'resendOtp']);

// Auth routes yang tidak memerlukan session
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/request-reset', [AuthController::class, 'requestReset']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

// Public routes
Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{event}', [EventController::class, 'show']);

// Contact Messages - Public can send
Route::post('/contact', [ContactMessageController::class, 'store']);

// Banners - Public can view
Route::get('/banners', [BannerController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // Change Password
    Route::post('/auth/change-password', [ChangePasswordController::class, 'changePassword']);
    
    // Event registration (requires auth)
    Route::post('/events/{event}/register', [RegistrationController::class, 'register']);
    
    // Attendance
    Route::get('/events/{event}/attendance/status', [AttendanceController::class, 'status']);
    Route::post('/events/{event}/attendance', [AttendanceController::class, 'submit']);
    
    // User Data
    Route::get('/user/event-history', [UserEventHistoryController::class, 'getEventHistory']);
    Route::get('/user/event-details/{registration}', [UserEventHistoryController::class, 'getEventDetail']);
    Route::get('/user/certificates', [UserController::class, 'getCertificates']);
    Route::get('/user/certificates/{certificate}/download', [UserController::class, 'downloadCertificate']);
    
    // Wishlist
    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist', [WishlistController::class, 'store']);
    Route::delete('/wishlist/{eventId}', [WishlistController::class, 'destroy']);
    Route::get('/wishlist/check/{eventId}', [WishlistController::class, 'check']);
});

// publik
Route::get('/certificates/search', [CertificateController::class, 'search']);
Route::get('/certificates/{certificate}/download', [CertificateController::class, 'download']);

// Midtrans webhook (no auth required)
Route::post('/payments/notification', [PaymentController::class, 'handleNotification']);

// Admin Dashboard - NO AUTH for testing
Route::get('/admin/dashboard', [AdminDashboardController::class, 'dashboard']);
Route::get('/admin/export', [AdminDashboardController::class, 'exportData']);

// Admin Participants - NO AUTH for testing
Route::get('/admin/participants', [AdminParticipantController::class, 'index']);
Route::get('/admin/participants/statistics', [AdminParticipantController::class, 'statistics']);

// Admin Messages - NO AUTH for testing
Route::get('/admin/messages', [ContactMessageController::class, 'index']);
Route::get('/admin/messages/{id}', [ContactMessageController::class, 'show']);
Route::post('/admin/messages/{id}/mark-read', [ContactMessageController::class, 'markAsRead']);
Route::delete('/admin/messages/{id}', [ContactMessageController::class, 'destroy']);

// Admin Banners - NO AUTH for testing
Route::get('/admin/banners', [AdminBannerController::class, 'index']);
Route::get('/admin/banners/{banner}', [AdminBannerController::class, 'show']);
Route::post('/admin/banners', [AdminBannerController::class, 'store']);
Route::post('/admin/banners/{banner}', [AdminBannerController::class, 'update']);
Route::delete('/admin/banners/{banner}', [AdminBannerController::class, 'destroy']);
Route::post('/admin/banners/{banner}/toggle', [AdminBannerController::class, 'toggleActive']);

// Admin Fotos - NO AUTH for testing
Route::get('/admin/events/{event}/fotos', [AdminFotoController::class, 'index']);
Route::post('/admin/events/{event}/fotos', [AdminFotoController::class, 'store']);
Route::post('/admin/events/{event}/fotos/{foto}', [AdminFotoController::class, 'update']);
Route::delete('/admin/events/{event}/fotos/{foto}', [AdminFotoController::class, 'destroy']);

// Admin Export - NO AUTH for testing
Route::get('/admin/events/{event}/export-participants', [AdminReportController::class, 'exportParticipants']);
Route::get('/admin/export-all-participants', [AdminReportController::class, 'exportAllParticipants']);

Route::middleware(['auth:sanctum', 'inactivity'])->group(function () {
    // peserta
    Route::delete('/registrations/{registration}', [RegistrationController::class, 'cancelRegistration']);
    Route::get('/me/registrations', [RegistrationController::class, 'myRegistrations']);
    Route::post('/events/{event}/attendance', [AttendanceController::class, 'submit']);
    Route::get('/me/history', [RegistrationController::class, 'myHistory']);
    Route::get('/me/certificates', [CertificateController::class, 'myCertificates']);
    
    // Certificate generation
    Route::post('/registrations/{registration}/generate-certificate', [CertificateController::class, 'generate']);
    Route::get('/registrations/{registration}/certificate-status', [CertificateController::class, 'status']);
    
    // Payment routes
    Route::post('/events/{event}/payment', [PaymentController::class, 'createPayment']);
    Route::get('/payments/{payment}/status', [PaymentController::class, 'checkPaymentStatus']);

    // admin-only
    Route::middleware('can:admin')->group(function () {
        Route::post('/admin/events', [EventController::class, 'store']);
        Route::put('/admin/events/{event}', [EventController::class, 'update']);
        Route::post('/admin/events/{event}/publish', [EventController::class, 'publish']);
        Route::delete('/admin/events/{event}', [EventController::class, 'destroy']);
        
        // Admin Reports (existing)
        Route::get('/admin/reports/monthly-events', [AdminReportController::class, 'monthlyEvents']);
        Route::get('/admin/reports/monthly-attendees', [AdminReportController::class, 'monthlyAttendees']);
        Route::get('/admin/reports/top10-events', [AdminReportController::class, 'top10Events']);
        Route::get('/admin/events/{event}/export', [AdminReportController::class, 'exportParticipants']);
        Route::get('/admin/reports/export-all-participants', [AdminReportController::class, 'exportAllParticipants']);
    });
});
