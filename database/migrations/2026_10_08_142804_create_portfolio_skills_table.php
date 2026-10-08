<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up(): void {
		Schema::create('portfolio_skills', function (Blueprint $table) {
			$table->id();

			$table->foreignId('portfolio_id')
				->constrained()
				->cascadeOnDelete();

			$table->foreignId('skill_id')
				->constrained()
				->cascadeOnDelete();

			$table->unsignedTinyInteger('level')->nullable();

			$table->unsignedInteger('sort_order')->default(0);

			$table->timestamp('created_at')->useCurrent();
			$table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

			$table->unique(['portfolio_id', 'skill_id']);
		});
	}

	public function down(): void {
		Schema::dropIfExists('portfolio_skills');
	}
};
