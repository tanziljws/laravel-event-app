# 📧 Dokumentasi Logika Send Email (Brevo) - Laravel Event App

## 📋 Daftar Isi

1. [Overview](#overview)
2. [Konfigurasi](#konfigurasi)
3. [Alur Registration OTP](#alur-registration-otp)
4. [Alur Reset Password OTP](#alur-reset-password-otp)
5. [BrevoService](#brevoservice)
6. [Email Templates](#email-templates)
7. [Troubleshooting](#troubleshooting)

---

## 🎯 Overview

Aplikasi ini menggunakan **Brevo (sebelumnya Sendinblue)** sebagai email service untuk mengirim OTP (One-Time Password) via API. Email dikirim untuk:

- ✅ **Registration OTP**: Verifikasi email saat registrasi
- ✅ **Reset Password OTP**: Verifikasi saat reset password

### Teknologi yang Digunakan:

- **Brevo API v3**: REST API untuk mengirim email
- **Laravel HTTP Client**: Untuk request ke Brevo API
- **Blade Templates**: Template email HTML
- **Database Storage**: OTP disimpan di table `email_otps`

---

## ⚙️ Konfigurasi

### 1. Environment Variables (`.env`)

```env
# Brevo API Configuration
BREVO_API_KEY=xkeysib-xxxxxxxxxxxxxxxxxxxxxxxxxxxxx

# Email From Configuration
MAIL_FROM_ADDRESS=tanziljws@gmail.com
MAIL_FROM_NAME="EduFest"
```

### 2. Config Files

**`config/services.php`:**

```php
'brevo' => [
    'api_key' => env('BREVO_API_KEY'),
],
```

**`config/mail.php`:**

```php
'from' => [
    'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
    'name' => env('MAIL_FROM_NAME', 'Example'),
],
```

---

## 📝 Alur Registration OTP

### Flow Diagram:

```
User Register
    ↓
1. Validasi Input (RegisterRequest)
    ↓
2. Create User di Database
   └─> users table: name, email, phone, address, education, password (hashed)
    ↓
3. Generate OTP (6 digit random)
    ↓
4. Simpan OTP ke Database
   └─> email_otps table: user_id, code_hash (hashed), type='verification', expires_at
    ↓
5. Kirim OTP via Email (BrevoService->sendEmailWithView)
   └─> Langsung execute (tidak pakai Job/Queue)
    ↓
6. Return Response dengan user_id
    ↓
User Masukkan OTP di Frontend
    ↓
7. Verify OTP (verifyEmail endpoint)
    ↓
8. Cek OTP di Database
   ├─> Valid & Not Expired? → Update email_verified_at
   └─> Invalid/Expired? → Return Error
```

### Detail Step-by-Step:

#### **Step 1: Validasi Input**

**File**: `app/Http/Requests/Auth/RegisterRequest.php`

```php
// Validasi form
'name' => 'required|string|max:255',
'email' => 'required|email|unique:users,email',
'phone' => 'required|string',
'address' => 'required|string',
'education' => 'required|string',
'password' => 'required|string|min:8|confirmed',
```

#### **Step 2: Create User di Database**

**File**: `app/Http/Controllers/Api/AuthController.php` → `register()`

```php
$user = User::create([
    'name' => $req->name,
    'email' => $req->email,
    'phone' => $req->phone,
    'address' => $req->address,
    'education' => $req->education,
    'password' => Hash::make($req->password)
]);
```

**PENTING**: User langsung dibuat di database, bukan disimpan di session!

#### **Step 3-4: Generate & Simpan OTP ke Database**

```php
// Generate 6 digit OTP (000000 - 999999)
$otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

// Simpan OTP ke database (hashed)
EmailOtp::create([
    'user_id' => $user->id,
    'code_hash' => Hash::make($otp),  // OTP di-hash untuk security
    'type' => 'verification',
    'expires_at' => now()->addMinutes(10)
]);
```

**Database Schema (`email_otps` table):**

```sql
- id (bigint)
- user_id (bigint, foreign key ke users)
- code_hash (string) - OTP yang sudah di-hash
- type (string) - 'verification' atau 'reset'
- expires_at (datetime) - Expiry time (10 menit)
- used_at (datetime, nullable) - Kapan OTP digunakan
- created_at, updated_at
```

#### **Step 5: Kirim Email via Brevo**

**File**: `app/Http/Controllers/Api/AuthController.php` → `register()`

```php
// Langsung panggil BrevoService (tidak pakai Job/Queue)
$brevoService = new \App\Services\BrevoService();
$result = $brevoService->sendEmailWithView(
    $user->email,
    $user->name,
    'Verifikasi Email - EduFest',
    'emails.otp',  // Blade template
    [
        'user' => $user,
        'otp' => $otp,  // Plain OTP (belum di-hash)
        'type' => 'verification'
    ]
);

// Log hasil
if ($result['success']) {
    \Log::info('OTP email sent successfully', [
        'user_id' => $user->id,
        'email' => $user->email,
        'message_id' => $result['message_id']
    ]);
} else {
    \Log::error('Failed to send OTP email', [
        'error' => $result['error']
    ]);
}
```

**Mengapa Langsung Execute (Tidak Pakai Job)?**

- ✅ Memastikan email langsung terkirim saat register
- ✅ Tidak perlu queue worker berjalan
- ✅ Error handling lebih mudah
- ✅ Logging lebih detail

#### **Step 7-8: Verify OTP**

**File**: `app/Http/Controllers/Api/AuthController.php` → `verifyEmail()`

```php
// Cari OTP yang valid (belum digunakan, belum expired)
$otp = EmailOtp::where('user_id', $req->user_id)
    ->where('type', 'verification')
    ->whereNull('used_at')  // Belum digunakan
    ->where('expires_at', '>=', now())  // Belum expired
    ->latest()  // Ambil yang terbaru
    ->first();

if (!$otp) {
    return response()->json([
        'message' => 'No valid OTP found. Please request new OTP.'
    ], 404);
}

// Verify OTP (compare dengan hash)
if (!Hash::check($req->code, $otp->code_hash)) {
    return response()->json(['message' => 'Invalid OTP'], 422);
}

// Mark OTP as used
$otp->update(['used_at' => now()]);

// Update user email_verified_at
\DB::table('users')
    ->where('id', $req->user_id)
    ->update(['email_verified_at' => now()]);
```

---

## 🔐 Alur Reset Password OTP

### Flow Diagram:

```
User Request Reset Password
    ↓
1. Validasi Email (harus terdaftar)
    ↓
2. Cari User by Email
    ↓
3. Generate OTP (6 digit random)
    ↓
4. Simpan OTP ke Database
   └─> email_otps table: user_id, code_hash, type='reset', expires_at
    ↓
5. Kirim OTP via Email (BrevoService)
    ↓
6. Return Response dengan user_id
    ↓
User Masukkan OTP
    ↓
7. Verify OTP
    ↓
8. OTP Valid?
    ├─> YES: Allow reset password
    └─> NO: Return Error
    ↓
User Input Password Baru
    ↓
9. Update Password di Database
    ↓
10. Mark OTP as Used
```

### Detail Step-by-Step:

#### **Step 1-2: Validasi & Cari User**

**File**: `app/Http/Controllers/Api/AuthController.php` → `requestReset()`

```php
$req->validate([
    'email' => 'required|email|exists:users,email'
]);

$user = User::where('email', $req->email)->first();
```

#### **Step 3-4: Generate & Simpan OTP**

```php
// Generate OTP
$otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

// Simpan ke database
EmailOtp::create([
    'user_id' => $user->id,
    'code_hash' => Hash::make($otp),
    'type' => 'reset',  // Type berbeda dengan verification
    'expires_at' => now()->addMinutes(10)
]);
```

#### **Step 5: Kirim Email**

```php
// Pakai Job untuk reset password (bisa async)
SendOtpJob::dispatch($user, $otp, 'reset');
```

#### **Step 7-8: Verify OTP**

**File**: `app/Http/Controllers/Api/AuthController.php` → `resetPassword()`

```php
// Cari OTP reset yang valid
$otp = EmailOtp::where('user_id', $req->user_id)
    ->where('type', 'reset')
    ->whereNull('used_at')
    ->where('expires_at', '>=', now())
    ->latest()
    ->first();

// Verify
if (!Hash::check($req->code, $otp->code_hash)) {
    return response()->json(['message' => 'Invalid or expired OTP'], 404);
}

// Update password
$user->update(['password' => Hash::make($req->password)]);

// Mark OTP as used
$otp->update(['used_at' => now()]);
```

---

## 🔧 BrevoService

### Class Structure:

**File**: `app/Services/BrevoService.php`

```php
class BrevoService
{
    protected $apiKey;          // BREVO_API_KEY dari config
    protected $apiUrl = 'https://api.brevo.com/v3/smtp/email';
    protected $fromEmail;       // MAIL_FROM_ADDRESS
    protected $fromName;        // MAIL_FROM_NAME

    public function __construct()
    {
        $this->apiKey = config('services.brevo.api_key');
        $this->fromEmail = config('mail.from.address');
        $this->fromName = config('mail.from.name');
    }

    // Method 1: Send email dengan HTML content langsung
    public function sendEmail(
        string $to,
        string $toName,
        string $subject,
        string $htmlContent,
        ?string $textContent = null
    ): array

    // Method 2: Send email dengan Blade template view
    public function sendEmailWithView(
        string $to,
        string $toName,
        string $subject,
        string $view,
        array $data = []
    ): array
}
```

### Proses Send Email:

#### **Method 1: sendEmail() - HTML Content Langsung**

```php
public function sendEmail(
    string $to,
    string $toName,
    string $subject,
    string $htmlContent,
    ?string $textContent = null
): array {
    // 1. Prepare payload
    $payload = [
        'sender' => [
            'name' => $this->fromName,
            'email' => $this->fromEmail,
        ],
        'to' => [
            ['email' => $to, 'name' => $toName]
        ],
        'subject' => $subject,
        'htmlContent' => $htmlContent,
    ];

    // 2. HTTP Request ke Brevo API
    $response = Http::withHeaders([
        'accept' => 'application/json',
        'api-key' => $this->apiKey,
        'content-type' => 'application/json',
    ])->timeout(30)->post($this->apiUrl, $payload);

    // 3. Handle response
    if ($response->successful()) {
        return [
            'success' => true,
            'message_id' => $response->json('messageId'),
            'response' => $response->json(),
        ];
    } else {
        return [
            'success' => false,
            'error' => $response->json()['message'] ?? 'Failed to send email',
            'status' => $response->status(),
        ];
    }
}
```

#### **Method 2: sendEmailWithView() - Blade Template**

```php
public function sendEmailWithView(
    string $to,
    string $toName,
    string $subject,
    string $view,
    array $data = []
): array {
    try {
        // 1. Render Blade template
        \Log::info('Rendering email view', ['view' => $view]);
        $htmlContent = view($view, $data)->render();
        
        // 2. Call sendEmail() dengan HTML content
        return $this->sendEmail($to, $toName, $subject, $htmlContent);
    } catch (\Exception $e) {
        \Log::error('Failed to render email view', [
            'view' => $view,
            'error' => $e->getMessage()
        ]);
        return [
            'success' => false,
            'error' => 'Failed to render email template: ' . $e->getMessage()
        ];
    }
}
```

### Brevo API Request Structure:

```json
POST https://api.brevo.com/v3/smtp/email
Headers:
  - api-key: xkeysib-xxxxxxxxxx
  - accept: application/json
  - content-type: application/json

Body:
{
  "sender": {
    "name": "EduFest",
    "email": "tanziljws@gmail.com"
  },
  "to": [
    {
      "email": "user@example.com",
      "name": "User Name"
    }
  ],
  "subject": "Verifikasi Email - EduFest",
  "htmlContent": "<html>...</html>"
}
```

### Response Structure:

**Success:**
```json
{
  "messageId": "<202511160247.12404705936@smtp-relay.mailin.fr>"
}
```

**Error:**
```json
{
  "message": "Unable to send email. Your SMTP account is not yet activated.",
  "code": "permission_denied"
}
```

---

## 📧 Email Templates

### Location:

- `resources/views/emails/otp.blade.php` - OTP Email Template

### Structure:

```blade
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $type === 'verification' ? 'Verifikasi Email' : 'Reset Password' }}</title>
    <style>
        /* CSS untuk email styling */
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1>🎓 EduFest</h1>
            <p>{{ $type === 'verification' ? 'Verifikasi Email Anda' : 'Reset Password' }}</p>
        </div>
        <div class="email-body">
            <p>Halo, {{ $user->name }}! 👋</p>
            <div class="otp-container">
                <div class="otp-code">{{ $otp }}</div>
                <p>⏰ Berlaku selama <strong>10 menit</strong></p>
            </div>
        </div>
    </div>
</body>
</html>
```

### Variables yang Dikirim:

- `$user`: User object (name, email, dll)
- `$otp`: 6 digit OTP code (plain text, contoh: "123456")
- `$type`: 'verification' atau 'reset'

---

## 🐛 Troubleshooting

### Error: "Brevo API key not configured"

**Solusi:**

1. Pastikan `.env` ada `BREVO_API_KEY=...`
2. Run `php artisan config:clear`
3. Cek `config('services.brevo.api_key')` tidak null

### Error: "MAIL_FROM_ADDRESS tidak dikonfigurasi"

**Solusi:**

1. Set `MAIL_FROM_ADDRESS` di `.env`
2. Pastikan email sudah **diverifikasi** di Brevo dashboard
3. Login ke: https://app.brevo.com → Senders, Domains & Dedicated IPs → Senders

### Error: "SMTP account is not yet activated"

**Solusi:**

1. Login ke Brevo dashboard: https://app.brevo.com
2. Go to: Senders, Domains & Dedicated IPs → Senders
3. Pastikan sender email sudah **verified** dan **diaktifkan untuk sending**
4. Atau hubungi Brevo support: contact@brevo.com

### Error: "Failed to send email" (API Error)

**Solusi:**

1. Cek logs: `storage/logs/laravel.log`
2. Pastikan API key valid di Brevo dashboard
3. Cek quota email Brevo (free tier: 300 email/hari)
4. Pastikan sender email sudah verified

### Email Terkirim Tapi Tidak Masuk Inbox

**Kemungkinan:**

- Email masuk **Spam/Junk folder**
- Brevo reputation masih rendah (baru setup)
- **Solusi**: Minta user cek spam, atau verify domain di Brevo

### OTP Tidak Valid Padahal Benar

**Kemungkinan:**

- OTP sudah expired (10 menit)
- OTP sudah digunakan (used_at tidak null)
- **Solusi**: Request OTP baru via resend endpoint

### Resend OTP

**Endpoint**: `POST /api/auth/resend-otp`

**File**: `app/Http/Controllers/Api/AuthController.php` → `resendOtp()`

```php
public function resendOtp(Request $req) {
    $req->validate(['user_id' => 'required|exists:users,id']);
    
    $user = User::findOrFail($req->user_id);
    
    // Generate OTP baru
    $otp = str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
    
    // Simpan OTP baru ke database
    EmailOtp::create([
        'user_id' => $user->id,
        'code_hash' => Hash::make($otp),
        'type' => 'verification',
        'expires_at' => now()->addMinutes(10)
    ]);
    
    // Kirim email (sync)
    $brevoService = new BrevoService();
    $brevoService->sendEmailWithView(
        $user->email,
        $user->name,
        'Verifikasi Email - EduFest',
        'emails.otp',
        ['user' => $user, 'otp' => $otp, 'type' => 'verification']
    );
    
    return response()->json([
        'message' => 'Kode OTP baru telah dikirim ke email Anda.'
    ]);
}
```

---

## 📊 Summary Flow

### Registration:

```
User Input → Validate → Create User (DB) → Generate OTP → Save OTP (DB) 
→ Send Email (BrevoService) → Verify OTP → Update email_verified_at
```

### Reset Password:

```
User Input Email → Find User → Generate OTP → Save OTP (DB) 
→ Send Email (BrevoService) → Verify OTP → Update Password → Mark OTP Used
```

### Key Points:

1. ✅ OTP disimpan di **database** (`email_otps` table) untuk registration dan reset password
2. ✅ User langsung dibuat di database saat registration (bukan di session)
3. ✅ OTP di-hash sebelum disimpan (security)
4. ✅ OTP expiry: **10 menit**
5. ✅ Email dikirim via **Brevo API v3** (langsung execute, tidak pakai queue)
6. ✅ Template email menggunakan **Blade** (`emails.otp`)
7. ✅ Error handling dengan **logging** detail
8. ✅ Service class: **BrevoService** (bukan BrevoMailService)

---

## 📚 Referensi

- [Brevo API Documentation](https://developers.brevo.com/)
- [Laravel HTTP Client](https://laravel.com/docs/http-client)
- [Laravel Blade Templates](https://laravel.com/docs/blade)

---

**Last Updated**: 2025-01-16  
**Version**: 2.0

