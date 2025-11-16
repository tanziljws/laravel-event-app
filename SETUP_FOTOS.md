# 📸 Setup Foto Event dari Tabel `fotos`

## Status
- ✅ Tabel `fotos` sudah dibuat
- ✅ Model `Foto` sudah dibuat
- ✅ Relasi dengan Event sudah dibuat
- ✅ API sudah diupdate untuk include foto
- ✅ Frontend sudah diupdate untuk menggunakan foto
- ❌ **File foto belum ada di folder `storage/app/public/fotos/`**

## Yang Perlu Dilakukan

### 1. Upload File Foto ke Folder `fotos`

File-file foto dari tabel `fotos` perlu ada di folder:
```
storage/app/public/fotos/
```

Berdasarkan data di database, file-file yang perlu ada:
- `1762991881_0.jpeg` (galery_id: 1)
- `1762992310_0.jpeg` (galery_id: 2)
- `1762992436_0.jpeg` (galery_id: 3)
- `1762992596_0.jpeg` (galery_id: 5)
- `1762992685_0.jpeg` (galery_id: 6)
- `1762992789_0.jpeg` (galery_id: 7)
- `1762992891_0.jpeg` (galery_id: 8)
- `1762992942_0.jpeg` (galery_id: 9)
- `1762993011_0.jpeg` (galery_id: 10)
- `1762993080_0.jpeg` (galery_id: 11)
- `1762993209_0.jpeg` (galery_id: 12)
- `1762993277_0.jpeg` (galery_id: 13)
- `1762993417_0.jpg` (galery_id: 14)
- `1762993474_0.jpeg` (galery_id: 15)
- `1763118598_0.png` (galery_id: 16)

### 2. Cara Upload File Foto

#### Opsi A: Upload Manual via File Manager
1. Buka folder `storage/app/public/fotos/`
2. Upload semua file foto ke folder tersebut
3. Pastikan nama file sesuai dengan yang ada di database

#### Opsi B: Upload via Terminal (jika file ada di tempat lain)
```bash
# Jika file ada di folder lain, copy ke folder fotos
cp /path/to/your/photos/*.jpeg storage/app/public/fotos/
cp /path/to/your/photos/*.jpg storage/app/public/fotos/
cp /path/to/your/photos/*.png storage/app/public/fotos/
```

#### Opsi C: Upload via Laravel (jika perlu)
Buat endpoint untuk upload foto (akan dibuat jika diperlukan)

### 3. Pastikan Storage Link Sudah Dibuat
```bash
cd laravel-event-app
php artisan storage:link
```

### 4. Test Akses File
Setelah file di-upload, test akses file:
```bash
# Test akses file langsung
curl http://127.0.0.1:8000/storage/fotos/1762991881_0.jpeg
```

Jika file bisa diakses, foto akan muncul di frontend.

## Struktur Database

Tabel `fotos`:
- `id` - Primary key
- `galery_id` - Foreign key ke `events.id` (asumsi galery_id = event_id)
- `file` - Nama file foto (contoh: `1762991881_0.jpeg`)
- `likes` - Jumlah likes (default: 0)
- `dislikes` - Jumlah dislikes (default: 0)
- `created_at` - Timestamp
- `updated_at` - Timestamp

## Prioritas Gambar di Frontend

1. `flyer_url` (dari `flyer_path`)
2. `image_url` (dari `image_path`)
3. **Foto pertama dari tabel `fotos`** (jika ada)
4. `flyer_path` atau `image_path` (di-resolve manual)
5. Placeholder berdasarkan kategori

## Catatan

- `galery_id` di tabel `fotos` diasumsikan sama dengan `event_id`
- File foto harus ada di `storage/app/public/fotos/` agar bisa diakses
- Pastikan Laravel server running untuk test akses file
- Setelah file di-upload, refresh browser untuk melihat foto

