<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up(): void {
		Schema::create('portfolio_themes', function (Blueprint $table) {
			$table->id();

			$table->foreignId('portfolio_id')
				->constrained()
				->cascadeOnDelete();

			$table->string('primary_color')->nullable();
			$table->string('secondary_color')->nullable();

			$table->string('font')->nullable();

			// Template-specific customization
			$table->json('settings')->nullable();

			$table->timestamp('created_at')->useCurrent();
			$table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

			$table->unique('portfolio_id');
		});
	}

	public function down(): void {
		Schema::dropIfExists('portfolio_themes');
	}
};
