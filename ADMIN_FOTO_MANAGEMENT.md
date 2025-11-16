# 📸 Admin Foto Management

## Info Admin

**Admin Account:**
- **ID:** 1
- **Email:** `admin@example.com`
- **Password:** `admin123`
- **Role:** admin

## API Endpoints untuk Manage Foto

### 1. Get All Fotos untuk Event
```
GET /api/admin/events/{event_id}/fotos
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "galery_id": 1,
      "file": "1762991881_0.jpeg",
      "url": "http://127.0.0.1:8000/storage/fotos/1762991881_0.jpeg",
      "likes": 0,
      "dislikes": 0,
      "created_at": "2025-11-12T23:58:01.000000Z",
      "updated_at": "2025-11-15T01:00:01.000000Z"
    }
  ]
}
```

### 2. Upload Foto Baru
```
POST /api/admin/events/{event_id}/fotos
Content-Type: multipart/form-data

Body:
- file: (image file, max 5MB, formats: jpeg, png, jpg, gif)
```

**Response:**
```json
{
  "success": true,
  "message": "Foto berhasil diupload",
  "data": {
    "id": 17,
    "galery_id": 1,
    "file": "1734379200_abc123.jpg",
    "url": "http://127.0.0.1:8000/storage/fotos/1734379200_abc123.jpg",
    "likes": 0,
    "dislikes": 0
  }
}
```

### 3. Update Foto
```
POST /api/admin/events/{event_id}/fotos/{foto_id}
Content-Type: multipart/form-data

Body (optional):
- file: (new image file, max 5MB) - untuk replace foto
- likes: (integer) - update jumlah likes
- dislikes: (integer) - update jumlah dislikes
```

**Response:**
```json
{
  "success": true,
  "message": "Foto berhasil diupdate",
  "data": {
    "id": 1,
    "galery_id": 1,
    "file": "1734379200_new123.jpg",
    "url": "http://127.0.0.1:8000/storage/fotos/1734379200_new123.jpg",
    "likes": 5,
    "dislikes": 1
  }
}
```

### 4. Delete Foto
```
DELETE /api/admin/events/{event_id}/fotos/{foto_id}
```

**Response:**
```json
{
  "success": true,
  "message": "Foto berhasil dihapus"
}
```

## Contoh Penggunaan

### Upload Foto dengan cURL
```bash
curl -X POST http://127.0.0.1:8000/api/admin/events/1/fotos \
  -F "file=@/path/to/your/image.jpg"
```

### Update Foto (Replace File)
```bash
curl -X POST http://127.0.0.1:8000/api/admin/events/1/fotos/1 \
  -F "file=@/path/to/new/image.jpg"
```

### Update Likes/Dislikes
```bash
curl -X POST http://127.0.0.1:8000/api/admin/events/1/fotos/1 \
  -F "likes=10" \
  -F "dislikes=2"
```

### Delete Foto
```bash
curl -X DELETE http://127.0.0.1:8000/api/admin/events/1/fotos/1
```

## Catatan

1. **File Storage:** Foto disimpan di `storage/app/public/fotos/`
2. **File Naming:** File otomatis di-rename dengan format `{timestamp}_{uniqid}.{extension}`
3. **File Size:** Maksimal 5MB per file
4. **Supported Formats:** jpeg, png, jpg, gif
5. **Auto Cleanup:** File lama otomatis dihapus saat foto di-update atau di-delete
6. **No Auth Required:** Saat ini endpoint tidak memerlukan authentication (untuk testing)

## Frontend Integration

Foto yang di-upload akan otomatis muncul di frontend karena:
- API endpoint `GET /api/events` sudah include `fotos` array
- Frontend sudah diupdate untuk menggunakan foto dari tabel `fotos` sebagai fallback image

## Troubleshooting

### Foto tidak muncul setelah upload?
1. Pastikan storage link sudah dibuat: `php artisan storage:link`
2. Pastikan Laravel server running: `php artisan serve`
3. Cek apakah file ada di `storage/app/public/fotos/`
4. Test akses file langsung: `curl http://127.0.0.1:8000/storage/fotos/{filename}`

### Error upload?
1. Pastikan folder `storage/app/public/fotos/` ada dan writable
2. Cek ukuran file (max 5MB)
3. Cek format file (harus jpeg, png, jpg, atau gif)
4. Cek log: `storage/logs/laravel.log`

