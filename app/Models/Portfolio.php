<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Portfolio extends Model
{
    use HasFactory;

    protected $table = 'portfolios';

    protected $fillable = [
        'user_id',
        'template_id',
        'title',
        'slug',
        'status',
        'design_settings',
        'google_form_url',
        'google_form_name_entry',
        'google_form_email_entry',
        'google_form_message_entry',
    ];

    protected $casts = [
        'design_settings' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'template_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(PortfolioSection::class, 'portfolio_id');
    }
}
