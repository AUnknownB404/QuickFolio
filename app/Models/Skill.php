<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Skill extends Model {
	protected $fillable = [
		'name',
		'slug',
	];

	public function portfolios(): BelongsToMany {
		return $this->belongsToMany(Portfolio::class)
			->withPivot(['level', 'sort_order'])
			->withTimestamps();
	}

	public function projects(): BelongsToMany {
		return $this->belongsToMany(Project::class)
			->withTimestamps();
	}
}
