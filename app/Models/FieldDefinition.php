<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FieldDefinition extends Model
{
    use HasFactory;

    protected $table = 'field_definitions';

    protected $fillable = [
        'field_key',
        'label',
        'data_type',
        'is_repeatable',
    ];

    protected $casts = [
        'is_repeatable' => 'boolean',
    ];

    public function templateSectionFields(): HasMany
    {
        return $this->hasMany(TemplateSectionField::class, 'field_definition_id');
    }

    public function portfolioFieldValues(): HasMany
    {
        return $this->hasMany(PortfolioFieldValue::class, 'field_definition_id');
    }
}
