<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TemplateSection extends Model
{
    use HasFactory;

    protected $table = 'template_sections';

    protected $fillable = [
        'template_id',
        'section_key',
        'is_enabled',
        'display_order',
        'settings',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'settings' => 'array',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'template_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(TemplateSectionField::class, 'template_section_id');
    }
}
