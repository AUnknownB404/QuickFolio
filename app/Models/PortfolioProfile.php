<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortfolioProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'portfolio_id',
        'first_name',
        'last_name',
        'headline',
        'about',
        'location',
        'profile_image',
        'resume',
        'phone',
    ];

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }
}
