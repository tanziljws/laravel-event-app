<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Foto;
use App\Models\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class FotoController extends Controller
{
    /**
     * Get all photos for an event
     */
    public function index(Request $request, Event $event)
    {
        $fotos = Foto::where('galery_id', $event->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($foto) {
                return [
                    'id' => $foto->id,
                    'galery_id' => $foto->galery_id,
                    'file' => $foto->file,
                    'url' => asset('storage/fotos/' . $foto->file),
                    'likes' => $foto->likes,
                    'dislikes' => $foto->dislikes,
                    'created_at' => $foto->created_at,
                    'updated_at' => $foto->updated_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $fotos
        ]);
    }

    /**
     * Upload new photo for an event
     */
    public function store(Request $request, Event $event)
    {
        $request->validate([
            'file' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // max 5MB
        ]);

        try {
            // Store file
            $file = $request->file('file');
            $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('fotos', $fileName, 'public');

            // Create foto record
            $foto = Foto::create([
                'galery_id' => $event->id,
                'file' => $fileName,
                'likes' => 0,
                'dislikes' => 0,
            ]);

            Log::info('Foto uploaded successfully', [
                'event_id' => $event->id,
                'foto_id' => $foto->id,
                'file' => $fileName
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Foto berhasil diupload',
                'data' => [
                    'id' => $foto->id,
                    'galery_id' => $foto->galery_id,
                    'file' => $foto->file,
                    'url' => asset('storage/fotos/' . $foto->file),
                    'likes' => $foto->likes,
                    'dislikes' => $foto->dislikes,
                ]
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error uploading foto', [
                'event_id' => $event->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal upload foto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update foto (likes/dislikes or replace file)
     */
    public function update(Request $request, Event $event, Foto $foto)
    {
        // Verify foto belongs to event
        if ($foto->galery_id != $event->id) {
            return response()->json([
                'success' => false,
                'message' => 'Foto tidak ditemukan untuk event ini'
            ], 404);
        }

        $request->validate([
            'file' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:5120',
            'likes' => 'sometimes|integer|min:0',
            'dislikes' => 'sometimes|integer|min:0',
        ]);

        try {
            // If new file is uploaded, replace old file
            if ($request->hasFile('file')) {
                // Delete old file
                if (Storage::disk('public')->exists('fotos/' . $foto->file)) {
                    Storage::disk('public')->delete('fotos/' . $foto->file);
                }

                // Store new file
                $file = $request->file('file');
                $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('fotos', $fileName, 'public');
                $foto->file = $fileName;
            }

            // Update likes/dislikes if provided
            if ($request->has('likes')) {
                $foto->likes = $request->likes;
            }
            if ($request->has('dislikes')) {
                $foto->dislikes = $request->dislikes;
            }

            $foto->save();

            Log::info('Foto updated successfully', [
                'foto_id' => $foto->id,
                'event_id' => $event->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Foto berhasil diupdate',
                'data' => [
                    'id' => $foto->id,
                    'galery_id' => $foto->galery_id,
                    'file' => $foto->file,
                    'url' => asset('storage/fotos/' . $foto->file),
                    'likes' => $foto->likes,
                    'dislikes' => $foto->dislikes,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating foto', [
                'foto_id' => $foto->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal update foto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete foto
     */
    public function destroy(Event $event, Foto $foto)
    {
        // Verify foto belongs to event
        if ($foto->galery_id != $event->id) {
            return response()->json([
                'success' => false,
                'message' => 'Foto tidak ditemukan untuk event ini'
            ], 404);
        }

        try {
            // Delete file from storage
            if (Storage::disk('public')->exists('fotos/' . $foto->file)) {
                Storage::disk('public')->delete('fotos/' . $foto->file);
            }

            // Delete record
            $foto->delete();

            Log::info('Foto deleted successfully', [
                'foto_id' => $foto->id,
                'event_id' => $event->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Foto berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting foto', [
                'foto_id' => $foto->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal hapus foto: ' . $e->getMessage()
            ], 500);
        }
    }
}

