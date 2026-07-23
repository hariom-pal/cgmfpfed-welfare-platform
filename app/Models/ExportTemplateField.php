<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportTemplateField extends Model
{
    protected $fillable = [
        'template_id',
        'field_name',
        'display_name',
        'column_order',
        'is_visible',
    ];

    protected function casts(): array
    {
        return [
            'column_order' => 'integer',
            'is_visible' => 'boolean',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ExportTemplate::class, 'template_id');
    }
}
