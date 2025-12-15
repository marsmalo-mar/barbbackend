<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;

Route::get('/', function () {
    return view('welcome');
});

// Test route to verify routing works
Route::get('/test-image-route', function () {
    return response()->json([
        'message' => 'Image route test',
        'storage_path' => storage_path('app/public'),
        'public_storage_path' => public_path('storage'),
        'storage_exists' => file_exists(storage_path('app/public')),
        'public_storage_exists' => file_exists(public_path('storage')),
    ]);
});

// Test if storage route is being hit
Route::get('/test-storage-route/{path}', function ($path) {
    $path = trim($path, '/');
    $publicPath = public_path('storage/' . $path);
    $storagePath = storage_path('app/public/' . $path);
    
    return response()->json([
        'message' => 'Storage route is working',
        'path_received' => $path,
        'full_request_uri' => request()->getRequestUri(),
        'public_path' => $publicPath,
        'storage_path' => $storagePath,
        'file_exists_public' => file_exists($publicPath),
        'file_exists_storage' => file_exists($storagePath),
        'file_exists_storage_disk' => \Storage::disk('public')->exists($path),
        'files_in_uploads' => \Storage::disk('public')->files('uploads'),
    ]);
})->where('path', '.*');

// Handle OPTIONS preflight for CORS
Route::options('/storage/{path}', function () {
    return response('', 200, [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type',
        'Access-Control-Max-Age' => '86400',
    ]);
})->where('path', '.*');

// Serve images with proper headers to prevent CORB issues
// This route handles all /storage/* requests to ensure proper headers
Route::get('/storage/{path}', function ($path) {
    try {
        // The path parameter will be like "uploads/avatar_1765123830_1.jpg"
        // Normalize it - remove any leading/trailing slashes
        $path = trim($path, '/');
        
        // Determine MIME type based on extension
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
        $mimeType = $mimeTypes[$ext] ?? 'image/jpeg';
        
        $filePath = null;
        $fileContent = null;
        
        // Use upload path: barbershop/storage/uploads/
        $uploadPath = dirname(base_path()) . '/storage/' . $path;
        if (file_exists($uploadPath) && is_file($uploadPath) && is_readable($uploadPath)) {
            $filePath = $uploadPath;
        } else {
            // File not found
            return Response::make('', 404, [
                'Content-Type' => $mimeType,
                'Access-Control-Allow-Origin' => '*',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }
        
        // Determine actual MIME type
        $actualMimeType = @mime_content_type($filePath);
        if ($actualMimeType && strpos($actualMimeType, 'image/') === 0) {
            $mimeType = $actualMimeType;
        }
        $fileContent = file_get_contents($filePath);
        
        if (empty($fileContent)) {
            throw new \Exception('File content is empty');
        }
        
        // Verify it's actually an image by checking file signature
        $imageSignatures = [
            'image/jpeg' => ["\xFF\xD8\xFF"],
            'image/png' => ["\x89\x50\x4E\x47"],
            'image/gif' => ["GIF87a", "GIF89a"],
            'image/webp' => ["RIFF"],
        ];
        
        $isValidImage = false;
        foreach ($imageSignatures as $imgType => $signatures) {
            foreach ($signatures as $sig) {
                if (substr($fileContent, 0, strlen($sig)) === $sig) {
                    $mimeType = $imgType;
                    $isValidImage = true;
                    break 2;
                }
            }
        }
        
        // If we couldn't verify, but have content, assume it's valid based on extension
        if (!$isValidImage && !empty($fileContent)) {
            $isValidImage = true; // Trust the extension if we have content
        }
        
        if (!$isValidImage) {
            throw new \Exception('File does not appear to be a valid image');
        }
        
        // Headers to set for all responses
        $headers = [
            'Content-Type' => $mimeType,
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=31536000',
            'Content-Length' => strlen($fileContent),
        ];
        
        // Return the file content with proper headers
        return Response::make($fileContent, 200, $headers);
        
    } catch (\Exception $e) {
        // Log the error for debugging
        \Log::error('Image serving error: ' . $e->getMessage(), [
            'path' => $path ?? 'unknown',
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
        
        // Return error with image content type to prevent CORB
        return Response::make('', 500, [
            'Content-Type' => 'image/jpeg',
            'Access-Control-Allow-Origin' => '*',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
})->where('path', '.*');
