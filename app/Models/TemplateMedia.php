<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateMedia extends Model
{
    use HasFactory;

    protected $table = 'template_media';

    protected $fillable = [
        'template_id',
        'media_path',
        'media_type',
        'display_order',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'template_id');
    }
}
