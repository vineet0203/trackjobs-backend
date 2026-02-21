<?php
// app/Models/JobAttachment.php

namespace App\Models;

use App\Traits\HasSignedUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class JobAttachment extends Model
{
    use HasFactory, SoftDeletes, HasSignedUrl;

    protected $table = 'job_attachments';

    protected $fillable = [
        'job_id',
        'file_name',
        'file_path',
        'file_type',
        'mime_type',
        'file_size',
        'disk',
        'metadata',
        'uploaded_by',
    ];

    protected $casts = [
        'metadata' => 'array',
        'file_size' => 'integer',
    ];

    /**
     * Get file extension
     */
    public function getExtensionAttribute(): string
    {
        return pathinfo($this->file_name, PATHINFO_EXTENSION);
    }

    /**
     * Get formatted file size
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get icon based on file type
     */
    public function getIconAttribute(): string
    {
        $extension = strtolower($this->extension);
        
        $icons = [
            'pdf' => 'picture_as_pdf',
            'jpg' => 'image',
            'jpeg' => 'image',
            'png' => 'image',
            'gif' => 'image',
            'svg' => 'image',
            'doc' => 'description',
            'docx' => 'description',
            'xls' => 'table_chart',
            'xlsx' => 'table_chart',
            'ppt' => 'slideshow',
            'pptx' => 'slideshow',
            'zip' => 'archive',
            'rar' => 'archive',
            'txt' => 'article',
        ];

        return $icons[$extension] ?? 'attach_file';
    }

    /**
     * Relationships
     */
    public function Job()
    {
        return $this->belongsTo(Job::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}