<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up(): void {
		Schema::create('certifications', function (Blueprint $table) {
			$table->id();

			$table->foreignId('portfolio_id')
				->constrained()
				->cascadeOnDelete();

			$table->string('name');
			$table->string('organization');

			$table->string('credential_id')->nullable();
			$table->string('credential_url')->nullable();

			$table->date('issue_date')->nullable();
			$table->date('expiry_date')->nullable();

			$table->string('image')->nullable();

			$table->timestamp('created_at')->useCurrent();
			$table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
		});
	}

	public function down(): void {
		Schema::dropIfExists('certifications');
	}
};
