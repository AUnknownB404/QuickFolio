<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up(): void {
		Schema::create('project_skills', function (Blueprint $table) {
			$table->id();

			$table->foreignId('project_id')
				->constrained()
				->cascadeOnDelete();

			$table->foreignId('skill_id')
				->constrained()
				->cascadeOnDelete();

			$table->timestamp('created_at')->useCurrent();
			$table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

			$table->unique(['project_id', 'skill_id']);
		});
	}

	public function down(): void {
		Schema::dropIfExists('project_skills');
	}
};
