<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortfolioTheme extends Model {
	protected $fillable = [
		'portfolio_id',
		'primary_color',
		'secondary_color',
		'font',
		'settings',
	];

	protected $casts = [
		'settings' => 'array',
	];

	public function portfolio(): BelongsTo {
		return $this->belongsTo(Portfolio::class);
	}
}
