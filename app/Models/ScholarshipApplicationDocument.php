<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ScholarshipApplicationDocument extends Model
{
    protected $fillable = [
        'scholarship_application_id',
        'student_identifier',
        'scheme_id',
        'document_type',
        'file_path',
        'storage_disk',
        'original_file_name',
        'stored_file_name',
        'file_extension',
        'mime_type',
        'file_size',
        'source',
        'uploaded_by',
        'uploaded_at',
        'is_verified',
        'verified_by',
        'verified_at',
        'remarks',
        'version',
        'is_current',
        'replaced_by',
        'replaced_at',
        'previous_document_id',
        'editable_after_return',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(ScholarshipApplication::class, 'scholarship_application_id');
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function replacer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replaced_by');
    }

    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_document_id');
    }

    public function isImage(): bool
    {
        return Str::startsWith((string) $this->mime_type, 'image/');
    }

    public function isPdf(): bool
    {
        return (string) $this->mime_type === 'application/pdf'
            || Str::lower((string) $this->file_extension) === 'pdf';
    }

    public function shouldOpenInline(): bool
    {
        return $this->isImage() || $this->isPdf();
    }

    public function displayName(): string
    {
        return $this->original_file_name
            ?: $this->stored_file_name
            ?: basename((string) $this->file_path)
            ?: str_replace('_', ' ', $this->document_type);
    }

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
            'version' => 'integer',
            'is_current' => 'boolean',
            'replaced_at' => 'datetime',
            'editable_after_return' => 'boolean',
        ];
    }
}
