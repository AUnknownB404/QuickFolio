<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Experience extends Model {
	protected $fillable = [
		'portfolio_id',
		'company',
		'position',
		'description',
		'location',
		'start_date',
		'end_date',
		'is_current',
		'sort_order',
	];

	protected $casts = [
		'start_date' => 'date',
		'end_date'   => 'date',
		'is_current' => 'boolean',
	];

	public function portfolio(): BelongsTo {
		return $this->belongsTo(Portfolio::class);
	}
}
