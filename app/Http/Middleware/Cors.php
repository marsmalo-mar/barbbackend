<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Cors
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowHeaders = 'Content-Type, Authorization, X-Requested-With, X-HTTP-Method-Override';
        $allowMethods = 'GET,POST,PUT,DELETE,OPTIONS,PATCH';

        if ($request->getMethod() === 'OPTIONS') {
            return response('', 204)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Headers', $allowHeaders)
                ->header('Access-Control-Allow-Methods', $allowMethods)
                ->header('Access-Control-Max-Age', '86400');
        }

        $response = $next($request);
        
        // Add CORS headers
        $response = $response
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Headers', $allowHeaders)
            ->header('Access-Control-Allow-Methods', $allowMethods);
        
        // For image requests, ensure proper Content-Type and prevent CORB
        $path = $request->path();
        if (preg_match('/\.(jpg|jpeg|png|gif|webp|svg|ico)$/i', $path)) {
            $response->header('X-Content-Type-Options', 'nosniff');
            // Ensure the response has a proper image content type
            if (!$response->headers->has('Content-Type')) {
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $mimeTypes = [
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'webp' => 'image/webp',
                    'svg' => 'image/svg+xml',
                    'ico' => 'image/x-icon',
                ];
                if (isset($mimeTypes[$ext])) {
                    $response->header('Content-Type', $mimeTypes[$ext]);
                }
            }
        }
        
        return $response;
    }
}
