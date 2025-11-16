# 🔍 Debug: Foto Tidak Muncul di Local

## Checklist

### 1. ✅ Storage Link Sudah Dibuat
```bash
cd laravel-event-app
php artisan storage:link
# Output: The [public/storage] link has been connected to [storage/app/public].
```

### 2. ❌ Laravel Server HARUS Running
```bash
cd laravel-event-app
php artisan serve
# Server akan running di http://127.0.0.1:8000
```

**PENTING:** Frontend TIDAK BISA akses foto jika Laravel server tidak running!

### 3. ✅ Frontend Config untuk Local
File `.env.local` sudah dibuat dengan:
```
REACT_APP_API_BASE_URL=http://127.0.0.1:8000/api
```

### 4. Test Akses File Langsung

Setelah Laravel server running, test di browser:
```
http://127.0.0.1:8000/storage/flyers/93dNpmOObi451HftUtSw4IB9s4NXCgzzSnurVxsR.jpg
http://127.0.0.1:8000/storage/banners/1762913474_6913ecc2615bb.jpeg
```

Jika file bisa diakses, berarti storage link OK.

### 5. Test API Response

Test API endpoint:
```bash
curl http://127.0.0.1:8000/api/events | jq '.data[0] | {id, title, flyer_path, flyer_url, image_path, image_url}'
```

Pastikan response mengandung:
- `flyer_url`: URL lengkap seperti `http://127.0.0.1:8000/storage/flyers/xxx.jpg`
- `flyer_path`: Path relatif seperti `flyers/xxx.jpg`

### 6. Cek Browser Console

Buka browser console (F12) dan cek:
- Apakah ada error 404 untuk image URLs?
- Apakah URL yang di-request benar?
- Apakah ada CORS error?

### 7. Flow Image Loading

1. **API mengembalikan:**
   - `flyer_url`: `http://127.0.0.1:8000/storage/flyers/xxx.jpg` (prioritas 1)
   - `image_url`: `http://127.0.0.1:8000/storage/images/xxx.jpg` (prioritas 2)
   - `flyer_path`: `flyers/xxx.jpg` (prioritas 3, akan di-resolve)
   - `image_path`: `images/xxx.jpg` (prioritas 4, akan di-resolve)

2. **Frontend logic:**
   - Gunakan `flyer_url` atau `image_url` jika ada (sudah absolute URL)
   - Jika tidak ada, gunakan `resolveMediaUrl(flyer_path || image_path)`
   - Jika semua tidak ada, gunakan placeholder

3. **resolveMediaUrl:**
   - Jika path sudah absolute (http/https), return as-is
   - Jika path relatif (flyers/xxx.jpg), tambahkan `/storage/` prefix
   - Tambahkan backend origin dari `API_BASE_URL`

## Troubleshooting

### Problem: Foto tidak muncul, console error 404
**Solution:** 
- Pastikan Laravel server running
- Pastikan storage link sudah dibuat
- Test akses file langsung di browser

### Problem: Foto tidak muncul, console error CORS
**Solution:**
- Pastikan `config/cors.php` sudah dikonfigurasi
- Untuk local development, biasanya sudah OK

### Problem: Foto tidak muncul, tapi file ada di folder
**Solution:**
- Pastikan `php artisan storage:link` sudah dijalankan
- Cek apakah `public/storage` adalah symlink ke `storage/app/public`
- Test akses file langsung di browser

### Problem: API mengembalikan flyer_url tapi foto tidak muncul
**Solution:**
- Cek apakah URL di `flyer_url` bisa diakses langsung
- Pastikan Laravel server running
- Cek browser console untuk error detail

## Quick Fix Commands

```bash
# 1. Start Laravel server
cd laravel-event-app
php artisan serve

# 2. Di terminal lain, start frontend
cd frontend-react.js
npm start

# 3. Test file access
curl http://127.0.0.1:8000/storage/flyers/93dNpmOObi451HftUtSw4IB9s4NXCgzzSnurVxsR.jpg

# 4. Test API
curl http://127.0.0.1:8000/api/events | head -20
```

