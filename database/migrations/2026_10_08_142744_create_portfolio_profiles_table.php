<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void {
		Schema::create('portfolio_profiles', function (Blueprint $table) {
			$table->id();

			$table->foreignId('portfolio_id')
				->constrained()
				->cascadeOnDelete();

			$table->string('first_name');
			$table->string('last_name')->nullable();
			$table->string('headline')->nullable();
			$table->text('about')->nullable();
			$table->string('location')->nullable();
			$table->string('profile_image')->nullable();
			$table->string('resume')->nullable();
			$table->string('phone')->nullable();

			$table->timestamp('created_at')->useCurrent();
			$table->timestamp('updated_at')->useCurrentOnUpdate()->useCurrent();

			$table->unique('portfolio_id');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void {
		Schema::dropIfExists('portfolio_profiles');
	}
};
