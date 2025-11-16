# 🚀 Cara Start Aplikasi di Local

## Prerequisites
- PHP 8.2+ dengan Composer
- Node.js 16+ dengan npm
- MySQL/PostgreSQL database

## Step-by-Step

### 1. Start Laravel Backend

```bash
cd laravel-event-app

# Install dependencies (jika belum)
composer install

# Setup database (jika belum)
# Copy .env.example ke .env dan configure database
cp .env.example .env
php artisan key:generate

# Pastikan storage link sudah dibuat
php artisan storage:link

# Start Laravel server
php artisan serve
```

Backend akan berjalan di: **http://127.0.0.1:8000**

### 2. Start React Frontend

**Terminal baru:**

```bash
cd frontend-react.js

# Install dependencies (jika belum)
npm install

# Untuk local development, gunakan .env.local
# File .env.local sudah dibuat dengan config untuk localhost
# Atau bisa buat manual:
# REACT_APP_API_BASE_URL=http://127.0.0.1:8000/api
# REACT_APP_MIDTRANS_CLIENT_KEY=SB-Mid-client-baNhlx1BONirl1UQ

# Start development server
npm start
```

Frontend akan berjalan di: **http://localhost:3000**

## Troubleshooting

### Foto tidak muncul?

1. **Pastikan storage link sudah dibuat:**
   ```bash
   cd laravel-event-app
   php artisan storage:link
   ```

2. **Pastikan Laravel server running:**
   ```bash
   # Cek apakah port 8000 sudah digunakan
   lsof -ti:8000
   
   # Jika tidak ada output, start server:
   php artisan serve
   ```

3. **Pastikan frontend menggunakan .env.local:**
   - File `.env.local` akan otomatis digunakan oleh Create React App
   - Atau bisa rename `.env.local` menjadi `.env` (backup `.env` yang lama dulu)

4. **Test akses file langsung:**
   ```bash
   # Test apakah file bisa diakses via browser
   curl http://127.0.0.1:8000/storage/flyers/93dNpmOObi451HftUtSw4IB9s4NXCgzzSnurVxsR.jpg
   ```

### CORS Error?

- Pastikan `config/cors.php` sudah dikonfigurasi dengan benar
- Untuk local development, biasanya sudah OK karena frontend dan backend di localhost

### Port sudah digunakan?

```bash
# Gunakan port lain untuk Laravel
php artisan serve --port=8001

# Update .env.local frontend:
# REACT_APP_API_BASE_URL=http://127.0.0.1:8001/api
```

## Quick Start (All-in-One)

```bash
# Terminal 1: Backend
cd laravel-event-app && php artisan serve

# Terminal 2: Frontend  
cd frontend-react.js && npm start
```

## File Structure

```
laravel-event-app/
├── storage/app/public/     # File uploads (flyers, banners, certificates)
│   ├── flyers/
│   ├── banners/
│   ├── certificates/
│   └── cert_templates/
└── public/storage -> storage/app/public  # Symlink (dibuat dengan storage:link)

frontend-react.js/
├── .env.local              # Config untuk local development
└── src/
    └── utils/media.js      # Fungsi resolveMediaUrl untuk handle image URLs
```

