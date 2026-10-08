<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up(): void {
		Schema::create('projects', function (Blueprint $table) {
			$table->id();

			$table->foreignId('portfolio_id')
				->constrained()
				->cascadeOnDelete();

			$table->string('title');
			$table->string('slug');
			$table->text('description')->nullable();

			$table->string('image')->nullable();
			$table->string('project_url')->nullable();
			$table->string('github_url')->nullable();

			$table->date('start_date')->nullable();
			$table->date('end_date')->nullable();

			$table->boolean('is_featured')->default(false);
			$table->unsignedInteger('sort_order')->default(0);

			$table->timestamp('created_at')->useCurrent();
			$table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

			$table->unique(['portfolio_id', 'slug']);
		});
	}

	public function down(): void {
		Schema::dropIfExists('projects');
	}
};
