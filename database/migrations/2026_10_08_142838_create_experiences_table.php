<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up(): void {
		Schema::create('experiences', function (Blueprint $table) {
			$table->id();

			$table->foreignId('portfolio_id')
				->constrained()
				->cascadeOnDelete();

			$table->string('company');
			$table->string('position');

			$table->text('description')->nullable();
			$table->string('location')->nullable();

			$table->date('start_date')->nullable();
			$table->date('end_date')->nullable();

			$table->boolean('is_current')->default(false);

			$table->unsignedInteger('sort_order')->default(0);

			$table->timestamp('created_at')->useCurrent();
			$table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
		});
	}

	public function down(): void {
		Schema::dropIfExists('experiences');
	}
};
