<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomCors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // List of allowed origins
        $allowedOrigins = [
            'http://localhost:3000',
            'http://localhost:5173',
            'http://127.0.0.1:3000',
            'http://127.0.0.1:5173',
            'https://frontend-reactjs-production.up.railway.app',
            env('FRONTEND_URL', 'http://localhost:3000'),
        ];

        // Remove null/empty values
        $allowedOrigins = array_filter($allowedOrigins);

        // Get the origin from the request
        $origin = $request->headers->get('Origin');
        
        // Fallback: jika tidak ada Origin header, cek Referer header
        if (!$origin) {
            $referer = $request->headers->get('Referer');
            if ($referer) {
                $parsedUrl = parse_url($referer);
                if ($parsedUrl) {
                    $origin = ($parsedUrl['scheme'] ?? 'https') . '://' . ($parsedUrl['host'] ?? '');
                    if (isset($parsedUrl['port'])) {
                        $origin .= ':' . $parsedUrl['port'];
                    }
                }
            }
        }

        // Log for debugging
        \Log::info('CORS Middleware', [
            'origin' => $origin,
            'allowed_origins' => $allowedOrigins,
            'is_allowed' => $origin && in_array($origin, $allowedOrigins),
            'path' => $request->path(),
            'method' => $request->method(),
            'referer' => $request->headers->get('Referer'),
        ]);

        // Check if origin is allowed
        $allowedOrigin = ($origin && in_array($origin, $allowedOrigins)) ? $origin : null;

        // Handle preflight OPTIONS request - HARUS di-handle di middleware SEBELUM route matching
        // Ini critical untuk Safari yang strict dengan CORS
        // Jika OPTIONS tidak di-handle, Safari akan fallback ke GET dan menyebabkan MethodNotAllowed
        if ($request->getMethod() === 'OPTIONS') {
            \Log::info('CORS: Handling OPTIONS preflight request', [
                'path' => $request->path(),
                'origin' => $origin,
                'allowed' => $allowedOrigin ? 'yes' : 'no',
                'full_url' => $request->fullUrl()
            ]);
            
            // Return empty response dengan status 200 dan CORS headers
            // JANGAN pakai response()->json() karena bisa menyebabkan masalah
            $response = response('', 200);
            
            // Set CORS headers untuk OPTIONS - HARUS di-set dengan benar
            // Gunakan origin yang diizinkan atau fallback ke '*'
            $responseOrigin = $allowedOrigin ?: '*';
            
            $response->headers->set('Access-Control-Allow-Origin', $responseOrigin, true);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH', true);
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-XSRF-TOKEN', true);
            $response->headers->set('Access-Control-Allow-Credentials', 'false', true);
            $response->headers->set('Access-Control-Max-Age', '3600', true);
            
            \Log::info('CORS: OPTIONS response headers set', [
                'origin' => $responseOrigin,
                'headers' => $response->headers->all()
            ]);
            
            return $response;
        }

        // Process request
        $response = $next($request);

        // Set CORS headers - HARUS di-set untuk semua response
        // Gunakan set() dan remove() untuk memastikan tidak ada conflict
        if ($allowedOrigin) {
            // Remove any existing CORS headers yang mungkin conflict
            $response->headers->remove('Access-Control-Allow-Origin');
            $response->headers->remove('Access-Control-Allow-Methods');
            $response->headers->remove('Access-Control-Allow-Headers');
            $response->headers->remove('Access-Control-Allow-Credentials');
            
            // Set CORS headers dengan cara yang lebih reliable
            // NOTE: Access-Control-Allow-Credentials di-disable karena Railway Proxy
            // mengeluarkan CORS wildcard (*) yang tidak bisa di-override
            $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin, true);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH', true);
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-XSRF-TOKEN', true);
            // $response->headers->set('Access-Control-Allow-Credentials', 'true', true); // Disabled karena Railway Proxy conflict
            $response->headers->set('Access-Control-Max-Age', '3600', true);
            $response->headers->set('Access-Control-Expose-Headers', 'Content-Length, Content-Type', true);
            
            \Log::info('CORS headers set', [
                'origin' => $allowedOrigin,
                'path' => $request->path(),
                'headers' => $response->headers->all(),
            ]);
        } else {
            // Log jika origin tidak diizinkan
            \Log::warning('CORS: Origin not allowed', [
                'origin' => $origin,
                'allowed_origins' => $allowedOrigins,
                'path' => $request->path(),
                'method' => $request->method(),
            ]);
        }

        return $response;
    }
}

