<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Education extends Model {
	protected $fillable = [
		'portfolio_id',
		'institution',
		'degree',
		'field_of_study',
		'description',
		'start_date',
		'end_date',
		'sort_order',
	];

	protected $casts = [
		'start_date' => 'date',
		'end_date'   => 'date',
	];

	public function portfolio(): BelongsTo {
		return $this->belongsTo(Portfolio::class);
	}
}
