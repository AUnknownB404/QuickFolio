<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certification extends Model {
	protected $fillable = [
		'portfolio_id',
		'name',
		'organization',
		'credential_id',
		'credential_url',
		'issue_date',
		'expiry_date',
		'image',
	];

	protected $casts = [
		'issue_date'  => 'date',
		'expiry_date' => 'date',
	];

	public function portfolio(): BelongsTo {
		return $this->belongsTo(Portfolio::class);
	}
}
