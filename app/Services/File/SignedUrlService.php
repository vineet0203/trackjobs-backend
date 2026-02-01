<?php
// app/Services/File/SignedUrlService.php

namespace App\Services\File;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\TemporaryUpload;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class SignedUrlService
{
    /**
     * Generate a signed URL for temporary file
     */
    public function generateTemporarySignedUrl(
        string $path,
        int $expirationMinutes = 60
    ): ?array {
        try {
            // Validate path
            if ($this->containsPathTraversal($path)) {
                Log::warning('Path traversal attempt detected', ['path' => $path]);
                return null;
            }

            // Find temporary upload record
            $tempUpload = TemporaryUpload::where('storage_path', $path)
                ->where('is_used', false)
                ->where('expires_at', '>', Carbon::now())
                ->first();

            if (!$tempUpload) {
                Log::warning('Temporary file not found or expired', ['path' => $path]);
                return null;
            }

            // Generate signed URL with all parameters
            $signedUrl = $this->generateSignedUrl([
                'type' => 'temporary',
                'path' => $tempUpload->storage_path,
                'filename' => $tempUpload->original_name,
                'temp_id' => $tempUpload->temp_id,
            ], $expirationMinutes);

            if (!$signedUrl) {
                return null;
            }

            // Log access
            Log::info('Signed URL generated for temporary file', [
                'temp_id' => $tempUpload->temp_id,
                'path' => $path,
                'expires_in_minutes' => $expirationMinutes,
                'user_id' => auth()->id(),
            ]);

            return [
                'url' => $signedUrl,
                'expires_at' => now()->addMinutes($expirationMinutes)->toISOString(),
                'filename' => $tempUpload->original_name,
                'size' => $tempUpload->size,
                'mime_type' => $tempUpload->mime_type,
                'temp_id' => $tempUpload->temp_id,
            ];
        } catch (\Exception $e) {
            Log::error('Error generating signed URL for temporary file', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Generate signed URL for parameters
     */
    private function generateSignedUrl(array $params, int $expirationMinutes): string
    {
        // Add expiration timestamp
        $params['expires'] = now()->addMinutes($expirationMinutes)->timestamp;

        // Sort parameters alphabetically
        ksort($params);

        // Build query string WITHOUT urlencoding (use raw parameters)
        $queryString = $this->buildQueryString($params);

        // Generate signature from the raw query string
        $signature = $this->generateSignature($params);

        // Add signature to parameters
        $params['signature'] = $signature;

        // Generate route signature (the one in the URL path)
        $routeSignature = hash('sha256', json_encode($params));

        // Build final URL
        return url("/api/v1/files/signed/{$routeSignature}") . '?' . http_build_query($params);
    }

    /**
     * Build query string without urlencoding values
     */
    private function buildQueryString(array $params): string
    {
        $parts = [];
        foreach ($params as $key => $value) {
            $parts[] = $key . '=' . $value;
        }
        return implode('&', $parts);
    }

    /**
     * Generate signature from parameters
     */
    private function generateSignature(array $params): string
    {
        // Sort parameters
        ksort($params);

        // Create a string to sign
        $stringToSign = '';
        foreach ($params as $key => $value) {
            $stringToSign .= $key . '=' . $value . '&';
        }
        $stringToSign = rtrim($stringToSign, '&');

        // Generate HMAC signature
        return hash_hmac('sha256', $stringToSign, config('app.key'));
    }

    /**
     * Serve file from signed URL
     */
    public function serveSignedFile(Request $request): ?\Symfony\Component\HttpFoundation\StreamedResponse
    {
        try {
            $routeSignature = $request->route('signature');
            $requestSignature = $request->query('signature');
            $type = $request->query('type', 'temporary');
            $path = $request->query('path');
            $filename = $request->query('filename', basename($path));
            $tempId = $request->query('temp_id');
            $expires = $request->query('expires');

            // Validate required parameters
            if (!$path || !$expires || !$requestSignature) {
                Log::warning('Missing parameters in signed URL', $request->all());
                return null;
            }

            // Check if URL has expired
            if (Carbon::createFromTimestamp($expires)->isPast()) {
                Log::warning('Signed URL has expired', [
                    'path' => $path,
                    'expires' => $expires,
                    'current_time' => now()->timestamp,
                ]);
                return null;
            }

            // Validate path
            if ($this->containsPathTraversal($path)) {
                Log::warning('Path traversal attempt detected', ['path' => $path]);
                return null;
            }

            // Get all query parameters except signature for verification
            $allParams = $request->query();
            $verifyParams = $allParams;
            unset($verifyParams['signature']);

            // Sort parameters for consistent verification
            ksort($verifyParams);

            // Recreate the signature to verify
            $expectedSignature = $this->generateSignature($verifyParams);

            // Verify signature
            if (!hash_equals($expectedSignature, $requestSignature)) {
                Log::warning('Invalid signature in signed URL', [
                    'expected' => $expectedSignature,
                    'received' => $requestSignature,
                    'params' => $verifyParams,
                    'string_to_sign' => $this->getStringToSign($verifyParams),
                ]);
                return null;
            }

            // For temporary files, validate the file exists and is not expired
            if ($type === 'temporary' && $tempId) {
                $tempUpload = TemporaryUpload::where('temp_id', $tempId)
                    ->where('is_used', false)
                    ->where('expires_at', '>', Carbon::now())
                    ->first();

                if (!$tempUpload) {
                    Log::warning('Temporary file not found or expired via signed URL', [
                        'temp_id' => $tempId,
                        'path' => $path,
                    ]);

                    // Debug: List all temporary uploads
                    $allTempUploads = TemporaryUpload::select('temp_id', 'storage_path', 'is_used', 'expires_at')
                        ->where('created_at', '>', now()->subDay())
                        ->get()
                        ->toArray();

                    Log::warning('Recent temporary uploads', $allTempUploads);

                    return null;
                }

                // Use the original filename from the database
                $filename = $tempUpload->original_name;
            }

            // Determine disk based on type
            $disk = match ($type) {
                'public' => Storage::disk('public'),
                'private' => Storage::disk('local'),
                'temporary' => Storage::disk('local'),
                default => Storage::disk('local'),
            };

            if (!$disk->exists($path)) {
                Log::warning('File not found in storage', [
                    'type' => $type,
                    'path' => $path,
                    'disk_root' => $disk->path(''),
                    'storage_files' => $this->listStorageFiles($disk, dirname($path)),
                ]);
                return null;
            }

            $mimeType = $disk->mimeType($path);

            // Determine if we should show inline or force download
            $inlineTypes = [
                'image/png',
                'image/jpeg',
                'image/jpg',
                'image/gif',
                'image/webp',
                'image/svg+xml',
                'application/pdf',
                'text/plain',
                'text/html',
                'text/css',
                'text/javascript',
                'application/json',
            ];

            $disposition = in_array($mimeType, $inlineTypes) ? 'inline' : 'attachment';

            // Log successful access
            Log::info('File served via signed URL', [
                'type' => $type,
                'path' => $path,
                'filename' => $filename,
                'mime_type' => $mimeType,
            ]);

            return $disk->download($path, $filename, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => $disposition . '; filename="' . $filename . '"',
                'Content-Length' => $disk->size($path),
                'Cache-Control' => 'private, max-age=3600, must-revalidate',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (\Exception $e) {
            Log::error('Error serving signed file', [
                'request' => $request->all(),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Helper to get string to sign for debugging
     */
    private function getStringToSign(array $params): string
    {
        ksort($params);
        $stringToSign = '';
        foreach ($params as $key => $value) {
            $stringToSign .= $key . '=' . $value . '&';
        }
        return rtrim($stringToSign, '&');
    }

    /**
     * List files in storage for debugging
     */
    private function listStorageFiles($disk, string $directory): array
    {
        try {
            return $disk->files($directory);
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Check for path traversal attempts
     */
    private function containsPathTraversal(string $path): bool
    {
        return Str::contains($path, ['..', '//', '\\', '%2e%2e', '%252e%252e']);
    }

    /**
     * Debug method to check what's happening with a specific URL
     */
    public function debugSignedUrl(string $url): array
    {
        $parsedUrl = parse_url($url);
        parse_str($parsedUrl['query'] ?? '', $queryParams);

        // Recreate signature
        $signatureParams = $queryParams;
        unset($signatureParams['signature']);
        ksort($signatureParams);

        $stringToSign = $this->getStringToSign($signatureParams);
        $expectedSignature = hash_hmac('sha256', $stringToSign, config('app.key'));

        return [
            'url' => $url,
            'parsed_query' => $queryParams,
            'string_to_sign' => $stringToSign,
            'expected_signature' => $expectedSignature,
            'actual_signature' => $queryParams['signature'] ?? null,
            'signature_matches' => hash_equals($expectedSignature, $queryParams['signature'] ?? ''),
            'expires_timestamp' => $queryParams['expires'] ?? null,
            'expires_human' => isset($queryParams['expires']) ?
                Carbon::createFromTimestamp($queryParams['expires'])->toDateTimeString() : null,
            'is_expired' => isset($queryParams['expires']) &&
                Carbon::createFromTimestamp($queryParams['expires'])->isPast(),
        ];
    }
}
