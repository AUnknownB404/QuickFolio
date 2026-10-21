<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PortfolioSection extends Model
{
    use HasFactory;

    protected $table = 'portfolio_sections';

    protected $fillable = [
        'portfolio_id',
        'section_key',
        'is_enabled',
        'display_order',
        'settings',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'settings' => 'array',
    ];

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class, 'portfolio_id');
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(PortfolioFieldValue::class, 'portfolio_section_id');
    }
}
