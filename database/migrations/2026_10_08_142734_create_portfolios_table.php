<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void {
		Schema::create('portfolios', function (Blueprint $table) {
			$table->id();

			$table->foreignId('user_id')
				->constrained()
				->cascadeOnDelete();

			$table->foreignId('template_id')
				->nullable()
				->constrained()
				->nullOnDelete();

			$table->string('title');
			$table->string('slug')->unique();

			$table->enum('status', ['draft', 'published'])
				->default('draft');

			$table->timestamp('published_at')->nullable();

			$table->timestamp('created_at')->useCurrent();
			$table->timestamp('updated_at')->useCurrentOnUpdate()->useCurrent();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void {
		Schema::dropIfExists('portfolios');
	}
};
