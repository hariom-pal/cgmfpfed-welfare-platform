<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExportTemplate extends Model
{
    protected $fillable = [
        'module',
        'name',
        'is_default',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ExportTemplateField, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(ExportTemplateField::class, 'template_id')->orderBy('column_order');
    }
}
