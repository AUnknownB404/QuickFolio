<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortfolioFieldValue extends Model
{
    use HasFactory;

    protected $table = 'portfolio_field_values';

    protected $fillable = [
        'portfolio_section_id',
        'field_definition_id',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public function portfolioSection(): BelongsTo
    {
        return $this->belongsTo(PortfolioSection::class, 'portfolio_section_id');
    }

    public function fieldDefinition(): BelongsTo
    {
        return $this->belongsTo(FieldDefinition::class, 'field_definition_id');
    }
}
