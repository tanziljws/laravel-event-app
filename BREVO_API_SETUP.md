# Konfigurasi Brevo API untuk Email

## ✅ Setup Selesai!

Aplikasi sekarang menggunakan **Brevo API** (bukan SMTP) untuk mengirim email.

## Environment Variables (.env)

Pastikan file `.env` memiliki konfigurasi berikut:

```env
# Brevo API Configuration
BREVO_API_KEY=your_brevo_api_key_here

# Email From Address (harus terverifikasi di Brevo)
MAIL_FROM_ADDRESS=kadangjugabaik@gmail.com
MAIL_FROM_NAME=EduFest
```

**⚠️ PENTING:** Ganti `your_brevo_api_key_here` dengan API key Brevo Anda yang sebenarnya. API key tidak boleh di-commit ke repository!

## File yang Telah Diupdate

1. **app/Services/BrevoService.php** - Service untuk mengirim email via Brevo API
2. **app/Jobs/SendOtpJob.php** - Menggunakan BrevoService untuk OTP emails
3. **app/Jobs/SendRegistrationTokenJob.php** - Menggunakan BrevoService untuk registration tokens
4. **app/Console/Commands/TestEmail.php** - Command test menggunakan BrevoService
5. **config/services.php** - Menambahkan konfigurasi Brevo API key

## Cara Test

```bash
php artisan config:clear
php artisan test:email tanziljws@gmail.com
```

## Catatan Penting

1. **Verifikasi Sender Email:**
   - Pastikan `kadangjugabaik@gmail.com` sudah terverifikasi di Brevo dashboard
   - Login ke https://app.brevo.com
   - Settings > Senders, Domains, IPs > Senders
   - Verifikasi email sender jika belum

2. **API Key:**
   - API key harus disimpan di file `.env` (jangan commit ke git!)
   - Dapatkan API key dari Brevo dashboard: https://app.brevo.com → Settings → SMTP & API → API Keys
   - Pastikan API key masih aktif di Brevo dashboard

3. **Logging:**
   - Email logs tersimpan di `storage/logs/laravel.log`
   - BrevoService akan log setiap pengiriman email (success/failed)

## Troubleshooting

### Error: "Invalid API key"
- Pastikan `BREVO_API_KEY` di `.env` sudah benar
- Pastikan API key masih aktif di Brevo dashboard
- Clear config cache: `php artisan config:clear`

### Error: "Sender not verified"
- Verifikasi email sender di Brevo dashboard
- Pastikan `MAIL_FROM_ADDRESS` sama dengan email yang terverifikasi

### Cek Log
```bash
tail -f storage/logs/laravel.log
```

## API Endpoint

Brevo API endpoint yang digunakan:
- **URL:** `https://api.brevo.com/v3/smtp/email`
- **Method:** POST
- **Headers:** 
  - `api-key`: API key dari .env
  - `content-type`: application/json

