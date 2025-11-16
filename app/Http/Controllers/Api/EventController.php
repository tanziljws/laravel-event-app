<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Event;
// FormRequests removed; we'll validate inline to avoid missing class errors
use Carbon\Carbon;

class EventController extends Controller
{
    public function index(Request $r) // publik katalog
    {
        try {
            // Check if admin request (show all events including unpublished)
            $isAdminRequest = $r->get('admin') === 'true';
            
            $q = Event::query();
            
            // Only filter by published status if not admin request
            if (!$isAdminRequest) {
                $q->where('is_published', true);
            }

            if ($s = $r->get('q')) {
                $q->where(fn($w) =>
                    $w->where('title', 'like', "%$s%")
                      ->orWhere('description', 'like', "%$s%")
                      ->orWhere('location', 'like', "%$s%")
                );
            }

            // Filter by category
            if ($category = $r->get('category')) {
                if ($category !== 'all') {
                    $q->where('category', $category);
                }
            }

            // sort: default newest (event yang baru dibuat)
            $sort = $r->get('sort', 'newest'); // newest|soonest|latest|date
            if ($sort === 'newest') {
                // Sort by created_at DESC - event yang baru dibuat muncul di atas
                $q->orderByDesc('created_at');
            } elseif ($sort === 'soonest') {
                $q->orderBy('event_date')->orderBy('start_time');
            } elseif ($sort === 'latest') {
                $q->orderByDesc('event_date')->orderByDesc('start_time');
            }

            // Add participants count for admin view
            if ($isAdminRequest) {
                $q->withCount('registrations as participants_count');
            }

            $events = $q->paginate(12);
            
            // Add registration status and flyer URL for each event
            $eventsWithStatus = $events->getCollection()->map(function ($event) {
                try {
                    $now = now();
                    // Fix datetime parsing - event_date already contains full datetime
                    $eventDateTime = Carbon::parse($event->event_date);
                    if ($event->start_time && $event->start_time !== '00:00:00') {
                        // Only add time if it's not default and different from date
                        $eventDateTime = Carbon::parse($event->event_date)->setTimeFromTimeString($event->start_time);
                    }
                    
                    $event->registration_open = $now->lt($eventDateTime);
                    $event->can_register = $event->registration_open;
                    
                    // Add full URL for flyer if exists
                    if ($event->flyer_path) {
                        $event->flyer_url = url('storage/' . $event->flyer_path);
                    }
                    
                    // Add full URL for certificate template if exists
                    if ($event->certificate_template_path) {
                        $event->certificate_template_url = url('storage/' . $event->certificate_template_path);
                    }
                    
                    // Add photos from fotos table (with error handling)
                    try {
                        $fotos = \App\Models\Foto::where('galery_id', $event->id)->get();
                        $event->fotos = $fotos->map(function ($foto) {
                            return [
                                'id' => $foto->id,
                                'url' => asset('storage/fotos/' . $foto->file),
                                'file' => $foto->file,
                                'likes' => $foto->likes ?? 0,
                                'dislikes' => $foto->dislikes ?? 0,
                            ];
                        });
                        
                        // If no flyer/image but has fotos, use first foto as image_url
                        if (!$event->flyer_url && !$event->image_url && $event->fotos->count() > 0) {
                            $firstFoto = $event->fotos->first();
                            if ($firstFoto && isset($firstFoto['url'])) {
                                $event->image_url = $firstFoto['url'];
                            }
                        }
                    } catch (\Exception $fotoError) {
                        \Log::warning('Error loading fotos for event ' . $event->id . ': ' . $fotoError->getMessage());
                        $event->fotos = collect([]);
                    }
                } catch (\Exception $e) {
                    \Log::error('Events index error: ' . $e->getMessage());
                    // Set default values if parsing fails
                    $event->registration_open = true;
                    $event->can_register = true;
                    $event->fotos = collect([]);
                }
                
                return $event;
            });
                
            $events->setCollection($eventsWithStatus);
            
            return response()->json([
                'data' => $events->items(),
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'total' => $events->total()
            ]);
        } catch (\Exception $e) {
            \Log::error('Events index error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch events',
                'message' => $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    public function store(Request $r)
    {
        // Validasi H-3: Event harus dibuat minimal 3 hari sebelum tanggal event
        if ($r->event_date) {
            $today = Carbon::today();
            $eventDate = Carbon::parse($r->event_date)->startOfDay();
            $diffDays = $today->diffInDays($eventDate, false);
            
            // diffInDays dengan false parameter:
            // - Positive jika eventDate > today (event di masa depan)
            // - Negative jika eventDate < today (event sudah lewat)
            // H-3 berarti diffDays harus >= 3
            if ($diffDays < 3) {
                return response()->json([
                    'message' => 'Event harus dibuat minimal H-3 (3 hari dari hari ini). Minimal tanggal: ' . $today->copy()->addDays(3)->format('d/m/Y')
                ], 422);
            }
        }

        // Inline validation
        $validated = $r->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'event_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'nullable',
            'location' => 'required|string|max:255',
            'category' => 'required|in:teknologi,seni_budaya,olahraga,akademik,sosial',
            'is_free' => 'sometimes|boolean',
            'price' => 'nullable|required_if:is_free,false|numeric|min:0',
            'max_participants' => 'nullable|integer|min:1',
            'organizer' => 'nullable|string|max:255',
            'is_published' => 'sometimes|boolean',
            'flyer' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'certificate_template' => 'nullable|file|mimes:jpeg,png,jpg,gif,pdf|max:2048',
        ]);

        $data = $validated;

        // Support both naming variants for files
        if ($r->hasFile('flyer')) {
            $flyerPath = $r->file('flyer')->store('flyers', 'public');
            $data['flyer_path'] = $flyerPath;
            \Log::info('Flyer uploaded successfully', ['path' => $flyerPath]);
        } elseif ($r->hasFile('flyer_path')) {
            $flyerPath = $r->file('flyer_path')->store('flyers', 'public');
            $data['flyer_path'] = $flyerPath;
            \Log::info('Flyer uploaded successfully (flyer_path)', ['path' => $flyerPath]);
        }
        
        if ($r->hasFile('certificate_template')) {
            $certPath = $r->file('certificate_template')->store('cert_templates', 'public');
            $data['certificate_template_path'] = $certPath;
            \Log::info('Certificate template uploaded successfully', ['path' => $certPath]);
        } elseif ($r->hasFile('certificate_template_path')) {
            $certPath = $r->file('certificate_template_path')->store('cert_templates', 'public');
            $data['certificate_template_path'] = $certPath;
            \Log::info('Certificate template uploaded successfully (certificate_template_path)', ['path' => $certPath]);
        }

        $data['created_by'] = $r->user()->id ?? null;
        $data['registration_closes_at'] = Carbon::parse($r->event_date.' '.$r->start_time);

        // Normalize pricing: if free, force price to 0
        if (array_key_exists('is_free', $data)) {
            $data['is_free'] = (bool) $r->boolean('is_free');
        } else {
            $data['is_free'] = true; // default free if not provided
        }
        if ($data['is_free']) {
            $data['price'] = 0;
        }

        // Remove validation fields that are not in database
        unset($data['flyer']);
        unset($data['certificate_template']);

        $event = Event::create($data);
        
        // Add flyer URL to response
        if ($event->flyer_path) {
            $event->flyer_url = url('storage/' . $event->flyer_path);
        }
        
        \Log::info('Event created successfully', [
            'id' => $event->id,
            'title' => $event->title,
            'flyer_path' => $event->flyer_path,
            'flyer_url' => $event->flyer_url ?? null
        ]);
        
        return response()->json($event, 201);
    }

    public function show(Event $event)
    {
        try {
            // Add full URL for flyer if exists
            if ($event->flyer_path) {
                $event->flyer_url = url('storage/' . $event->flyer_path);
            }
            
            // Add full URL for certificate template if exists
            if ($event->certificate_template_path) {
                $event->certificate_template_url = url('storage/' . $event->certificate_template_path);
            }
            
            // Add photos from fotos table (with error handling)
            try {
                $fotos = \App\Models\Foto::where('galery_id', $event->id)->get();
                $event->fotos = $fotos->map(function ($foto) {
                    return [
                        'id' => $foto->id,
                        'url' => asset('storage/fotos/' . $foto->file),
                        'file' => $foto->file,
                        'likes' => $foto->likes ?? 0,
                        'dislikes' => $foto->dislikes ?? 0,
                    ];
                });
                
                // If no flyer/image but has fotos, use first foto as image_url
                if (!$event->flyer_url && !$event->image_url && $event->fotos->count() > 0) {
                    $firstFoto = $event->fotos->first();
                    if ($firstFoto && isset($firstFoto['url'])) {
                        $event->image_url = $firstFoto['url'];
                    }
                }
            } catch (\Exception $fotoError) {
                \Log::warning('Error loading fotos for event ' . $event->id . ': ' . $fotoError->getMessage());
                $event->fotos = collect([]);
            }
            
            return response()->json($event);
        } catch (\Exception $e) {
            \Log::error('Error in EventController@show: ' . $e->getMessage(), [
                'event_id' => $event->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Failed to fetch event',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $r, Event $event)
    {
        // Debug logging
        \Log::info('Update event request received', [
            'event_id' => $event->id,
            'has_flyer' => $r->hasFile('flyer'),
            'has_flyer_path' => $r->hasFile('flyer_path'),
            'all_files' => array_keys($r->allFiles()),
            'is_free' => $r->get('is_free'),
            'price' => $r->get('price')
        ]);
        
        $data = $r->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'event_date' => 'sometimes|required|date',
            'start_time' => 'sometimes|required',
            'end_time' => 'nullable',
            'location' => 'sometimes|required|string|max:255',
            'category' => 'sometimes|required|in:teknologi,seni_budaya,olahraga,akademik,sosial',
            'is_free' => 'sometimes|boolean',
            'price' => 'nullable|required_if:is_free,false|numeric|min:0',
            'max_participants' => 'nullable|integer|min:1',
            'organizer' => 'nullable|string|max:255',
            'is_published' => 'sometimes|boolean',
            'flyer' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'certificate_template' => 'nullable|file|mimes:jpeg,png,jpg,gif,pdf|max:2048',
        ]);
        
        if ($r->hasFile('flyer')) {
            $flyerPath = $r->file('flyer')->store('flyers', 'public');
            $data['flyer_path'] = $flyerPath;
            \Log::info('Flyer updated successfully', ['event_id' => $event->id, 'path' => $flyerPath]);
        } elseif ($r->hasFile('flyer_path')) {
            $flyerPath = $r->file('flyer_path')->store('flyers', 'public');
            $data['flyer_path'] = $flyerPath;
            \Log::info('Flyer updated successfully (flyer_path)', ['event_id' => $event->id, 'path' => $flyerPath]);
        }
        
        if ($r->hasFile('certificate_template')) {
            $certPath = $r->file('certificate_template')->store('cert_templates', 'public');
            $data['certificate_template_path'] = $certPath;
            \Log::info('Certificate template updated successfully', ['event_id' => $event->id, 'path' => $certPath]);
        } elseif ($r->hasFile('certificate_template_path')) {
            $certPath = $r->file('certificate_template_path')->store('cert_templates', 'public');
            $data['certificate_template_path'] = $certPath;
            \Log::info('Certificate template updated successfully (certificate_template_path)', ['event_id' => $event->id, 'path' => $certPath]);
        }

        // Normalize pricing on update: if explicitly marked free, force price to 0
        if ($r->has('is_free')) {
            $data['is_free'] = (bool) $r->boolean('is_free');
            if ($data['is_free']) {
                $data['price'] = 0;
            }
        }
        
        // Remove validation fields that are not in database
        unset($data['flyer']);
        unset($data['certificate_template']);
        
        $event->update($data);
        
        // Add flyer URL to response
        if ($event->flyer_path) {
            $event->flyer_url = url('storage/' . $event->flyer_path);
        }
        
        return $event;
    }

    public function publish(Request $r, Event $event)
    {
        $event->update(['is_published' => (bool) $r->boolean('is_published')]);
        return response()->json(['is_published' => $event->is_published]);
    }

    /**
     * Delete event
     * - If event has registrations: unpublish only (soft delete)
     * - If no registrations: hard delete
     */
    public function destroy(Event $event)
    {
        try {
            // Check if event has any registrations
            $hasRegistrations = $event->registrations()->exists();
            
            if ($hasRegistrations) {
                // Soft delete: unpublish event instead of deleting
                $event->update(['is_published' => false]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Event tidak dapat dihapus karena sudah ada peserta terdaftar. Event telah disembunyikan dari publik.',
                    'soft_delete' => true
                ], 200);
            } else {
                // Hard delete: no registrations, safe to delete
                // Delete associated files
                if ($event->flyer_path) {
                    \Storage::disk('public')->delete($event->flyer_path);
                }
                if ($event->certificate_template_path) {
                    \Storage::disk('public')->delete($event->certificate_template_path);
                }
                
                $event->delete();
                
                return response()->json([
                    'success' => true,
                    'message' => 'Event berhasil dihapus.',
                    'hard_delete' => true
                ], 200);
            }
        } catch (\Exception $e) {
            \Log::error('Error deleting event', [
                'event_id' => $event->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus event: ' . $e->getMessage()
            ], 500);
        }
    }
}
