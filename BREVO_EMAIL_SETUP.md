# Konfigurasi Email Brevo

## ⚠️ PENTING: Perbedaan API Key vs SMTP Key

**API Key** (xkeysib-...) digunakan untuk API calls, **BUKAN** untuk SMTP authentication.

Untuk SMTP, Anda perlu:
1. **SMTP Key** yang di-generate dari Brevo dashboard
2. **Email login Brevo** sebagai username (bukan email sender)

## Cara Mendapatkan SMTP Key:

1. Login ke Brevo dashboard: https://app.brevo.com
2. Buka **Settings** > **SMTP & API** > Tab **SMTP**
3. Klik **Generate a new SMTP key**
4. Beri nama (contoh: "Laravel App")
5. Copy SMTP key yang di-generate (hanya muncul sekali!)

## Environment Variables (.env)

Update variabel berikut di file `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=EMAIL_LOGIN_BREVO_ANDA@example.com
MAIL_PASSWORD=SMTP_KEY_YANG_DI_GENERATE
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=kadangjugabaik@gmail.com
MAIL_FROM_NAME="EduFest"
```

**Catatan:**
- `MAIL_USERNAME`: Email yang digunakan untuk login ke Brevo account (bisa sama atau berbeda dengan sender email)
- `MAIL_PASSWORD`: SMTP key (bukan API key!)
- `MAIL_FROM_ADDRESS`: Email sender yang sudah terverifikasi di Brevo

## Verifikasi Sender Email

1. Login ke Brevo dashboard
2. Buka **Settings** > **Senders, Domains, IPs** > **Senders**
3. Pastikan `kadangjugabaik@gmail.com` sudah terverifikasi
4. Jika belum, klik **Add a sender** dan verifikasi email tersebut

## SMTP Settings

- **Host:** `smtp-relay.brevo.com`
- **Port:** `587` (TLS) atau `465` (SSL)
- **Encryption:** `tls` untuk port 587, `ssl` untuk port 465
- **Username:** Email login Brevo account
- **Password:** SMTP key (bukan API key)

## Test Email

```bash
php artisan test:email your-email@example.com
```

## Troubleshooting

### Error: "Authentication failed" (535)
**Penyebab:**
- `MAIL_USERNAME` bukan email login Brevo
- `MAIL_PASSWORD` menggunakan API key, bukan SMTP key

**Solusi:**
1. Pastikan `MAIL_USERNAME` adalah email yang digunakan untuk login ke Brevo
2. Generate SMTP key baru di Brevo dashboard
3. Gunakan SMTP key sebagai `MAIL_PASSWORD`
4. Clear config cache: `php artisan config:clear`

### Error: "Sender not verified"
- Pastikan email sender (`MAIL_FROM_ADDRESS`) sudah terverifikasi di Brevo
- Cek di Settings > Senders, Domains, IPs > Senders

### Cek Log
```bash
tail -f storage/logs/laravel.log
```

## Catatan Tambahan

- API Key (xkeysib-...) hanya untuk API calls, tidak bisa digunakan untuk SMTP
- SMTP Key berbeda dengan API Key
- Username SMTP harus email login Brevo account
- Email sender bisa berbeda dengan email login
