<?php
// app/Services/File/FileService.php

namespace App\Services\File;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\TemporaryUpload;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Str;

class FileService
{
    /**
     * Get disk configuration
     */
    private function getDisk(string $type = 'private')
    {
        return Storage::disk($type === 'public' ? 'public' : 'local');
    }

    /**
     * Serve file from storage
     */
    public function serveFile(
        string $path,
        string $diskType = 'private',
        ?string $originalName = null,
        bool $forceDownload = false
    ): ?StreamedResponse {
        try {
            $disk = $this->getDisk($diskType);

            if (!$disk->exists($path)) {
                Log::warning('File not found in storage', [
                    'path' => $path,
                    'disk' => $diskType,
                ]);
                return null;
            }

            $mimeType = $disk->mimeType($path);
            $filename = $originalName ?? basename($path);

            // Determine content disposition
            $disposition = $this->getContentDisposition($mimeType, $forceDownload);

            // Generate response
            return $disk->download($path, $filename, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => $disposition . '; filename="' . $filename . '"',
                'Content-Length' => $disk->size($path),
                'Cache-Control' => $this->getCacheControl($diskType),
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (\Exception $e) {
            Log::error('File serving error', [
                'path' => $path,
                'disk' => $diskType,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Serve temporary file with validation
     */
    public function serveTemporaryFile(
        string $path,
        bool $forceDownload = false
    ): ?StreamedResponse {
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

        // Optional: User permission check
        // if ($tempUpload->user_id && $tempUpload->user_id !== auth()->id()) {
        //     Log::warning('Unauthorized access to temporary file', [
        //         'user_id' => auth()->id(),
        //         'file_user_id' => $tempUpload->user_id,
        //     ]);
        //     return null;
        // }

        // Log access for auditing
        Log::info('Temporary file accessed', [
            'temp_id' => $tempUpload->temp_id,
            'path' => $path,
            'user_id' => auth()->id(),
            'file_name' => $tempUpload->original_name,
        ]);

        return $this->serveFile(
            $path,
            'private',
            $tempUpload->original_name,
            $forceDownload
        );
    }

    /**
     * Serve public file
     */
    public function servePublicFile(
        string $path,
        bool $forceDownload = false
    ): ?StreamedResponse {
        // Validate path
        if ($this->containsPathTraversal($path)) {
            Log::warning('Path traversal attempt detected in public files', ['path' => $path]);
            return null;
        }

        // Remove leading slash if present
        $path = ltrim($path, '/');

        return $this->serveFile($path, 'public', null, $forceDownload);
    }

    /**
     * Serve private file (company-specific or user-specific)
     */
    public function servePrivateFile(
        string $path,
        bool $forceDownload = false,
        ?int $companyId = null
    ): ?StreamedResponse {
        // Validate path
        if ($this->containsPathTraversal($path)) {
            Log::warning('Path traversal attempt detected in private files', ['path' => $path]);
            return null;
        }

        // Optional: Add company-specific access control
        // if ($companyId && !$this->hasCompanyAccess($companyId)) {
        //     return null;
        // }

        return $this->serveFile($path, 'local', null, $forceDownload);
    }

    /**
     * Get file information
     */
    public function getFileInfo(string $path, string $diskType = 'private'): ?array
    {
        try {
            $disk = $this->getDisk($diskType);

            if (!$disk->exists($path)) {
                return null;
            }

            return [
                'name' => basename($path),
                'size' => $disk->size($path),
                'mime_type' => $disk->mimeType($path),
                'last_modified' => $disk->lastModified($path),
                'url' => $disk->url($path),
                'path' => $path,
                'disk' => $diskType,
            ];
        } catch (\Exception $e) {
            Log::error('Error getting file info', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Check if file exists
     */
    public function fileExists(string $path, string $diskType = 'private'): bool
    {
        return $this->getDisk($diskType)->exists($path);
    }

    /**
     * Delete file
     */
    public function deleteFile(string $path, string $diskType = 'private'): bool
    {
        try {
            $disk = $this->getDisk($diskType);

            if ($disk->exists($path)) {
                $disk->delete($path);
                Log::info('File deleted', ['path' => $path, 'disk' => $diskType]);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Error deleting file', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get content disposition based on file type
     */
    private function getContentDisposition(string $mimeType, bool $forceDownload): string
    {
        if ($forceDownload) {
            return 'attachment';
        }

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

        return in_array($mimeType, $inlineTypes) ? 'inline' : 'attachment';
    }

    /**
     * Get cache control header
     */
    private function getCacheControl(string $diskType): string
    {
        if ($diskType === 'public') {
            return 'public, max-age=31536000'; // 1 year for public files
        }

        return 'private, max-age=3600'; // 1 hour for private files
    }

    /**
     * Check for path traversal attempts
     */
    private function containsPathTraversal(string $path): bool
    {
        return Str::contains($path, ['..', '//', '\\', '%2e%2e', '%252e%252e']);
    }

    /**
     * Validate file path
     */
    public function validatePath(string $path): bool
    {
        if (empty($path)) {
            return false;
        }

        if ($this->containsPathTraversal($path)) {
            return false;
        }

        // Additional path validation
        if (!preg_match('/^[a-zA-Z0-9_\-\/\.]+$/', $path)) {
            return false;
        }

        return true;
    }
}