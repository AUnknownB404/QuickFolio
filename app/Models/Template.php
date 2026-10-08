<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Template extends Model {
	protected $fillable = [
		'name',
		'slug',
		'description',
		'preview_image',
		'thumbnail_image',
		'version',
		'is_active',
		'is_featured',
	];

	protected $casts = [
		'is_active'   => 'boolean',
		'is_featured' => 'boolean',
	];

	public function portfolios(): HasMany {
		return $this->hasMany(Portfolio::class);
	}
}
