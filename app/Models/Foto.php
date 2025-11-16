<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Foto extends Model
{
    use HasFactory;

    protected $table = 'fotos';

    protected $fillable = [
        'galery_id',
        'file',
        'likes',
        'dislikes',
    ];

    protected $casts = [
        'likes' => 'integer',
        'dislikes' => 'integer',
    ];

    /**
     * Get the URL for the photo file
     */
    public function getUrlAttribute(): string
    {
        // Assuming photos are stored in storage/app/public/fotos/
        // If file path doesn't start with http, it's a relative path
        if (str_starts_with($this->file, 'http')) {
            return $this->file;
        }
        
        // Return full URL using asset helper
        return asset('storage/fotos/' . $this->file);
    }

    /**
     * Relationship: Foto belongs to Event (assuming galery_id = event_id)
     */
    public function event()
    {
        return $this->belongsTo(Event::class, 'galery_id', 'id');
    }
}

