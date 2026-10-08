<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialLink extends Model {
	protected $fillable = [
		'portfolio_id',
		'platform',
		'url',
		'sort_order',
	];

	public function portfolio(): BelongsTo {
		return $this->belongsTo(Portfolio::class);
	}
}
