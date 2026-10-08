<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up(): void {
		Schema::create('templates', function (Blueprint $table) {
			$table->id();

			$table->string('name');
			$table->string('slug')->unique();

			$table->text('description')->nullable();

			$table->string('preview_image')->nullable();
			$table->string('thumbnail_image')->nullable();

			$table->string('version')->default('1.0.0');

			$table->boolean('is_active')->default(true);
			$table->boolean('is_featured')->default(false);

			$table->timestamp('created_at')->useCurrent();
			$table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
		});
	}

	public function down(): void {
		Schema::dropIfExists('templates');
	}
};
