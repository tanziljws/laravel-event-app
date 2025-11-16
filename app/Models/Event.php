<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'event_date',
        'start_time',
        'end_time',
        'location',
        'category',
        'price',
        'is_free',
        'max_participants',
        'organizer',
        'flyer_path',
        'image_path',
        'certificate_template_path',
        'is_published',
        'registration_closes_at',
        'created_by'
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'is_free' => 'boolean',
        'price' => 'decimal:2',
        'event_date' => 'date',
        'registration_closes_at' => 'datetime'
    ];

    // Ensure API includes absolute URLs without frontend guessing
    protected $appends = [
        'flyer_url',
        'image_url',
        'certificate_template_url',
    ];

    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    /**
     * The user who created the event.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Photos/Gallery for this event (assuming galery_id = event_id)
     */
    public function fotos()
    {
        return $this->hasMany(Foto::class, 'galery_id', 'id');
    }

    /**
     * Accessor: Absolute URL for flyer image
     */
    public function getFlyerUrlAttribute(): ?string
    {
        if (empty($this->flyer_path)) {
            return null;
        }
        // If path starts with 'http', return as-is (already absolute URL)
        if (str_starts_with($this->flyer_path, 'http')) {
            return $this->flyer_path;
        }
        // Remove 'public/' prefix if exists (Laravel storage stores without it)
        $path = str_replace('public/', '', $this->flyer_path);
        // Return full URL using asset helper for storage files
        return asset('storage/' . $path);
    }

    /**
     * Accessor: Absolute URL for main image
     */
    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image_path)) {
            return null;
        }
        // If path starts with 'http', return as-is (already absolute URL)
        if (str_starts_with($this->image_path, 'http')) {
            return $this->image_path;
        }
        // Remove 'public/' prefix if exists
        $path = str_replace('public/', '', $this->image_path);
        // Return full URL using asset helper for storage files
        return asset('storage/' . $path);
    }

    /**
     * Accessor: Absolute URL for certificate template
     */
    public function getCertificateTemplateUrlAttribute(): ?string
    {
        if (empty($this->certificate_template_path)) {
            return null;
        }
        // If path starts with 'http', return as-is (already absolute URL)
        if (str_starts_with($this->certificate_template_path, 'http')) {
            return $this->certificate_template_path;
        }
        // Remove 'public/' prefix if exists
        $path = str_replace('public/', '', $this->certificate_template_path);
        // Return full URL using asset helper for storage files
        return asset('storage/' . $path);
    }
}
