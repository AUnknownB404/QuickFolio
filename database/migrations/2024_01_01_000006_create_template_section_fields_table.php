<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('template_section_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_section_id')->constrained('template_sections')->cascadeOnDelete();
            $table->foreignId('field_definition_id')->constrained('field_definitions')->cascadeOnDelete();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->json('config')->nullable();
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->unique(['template_section_id', 'field_definition_id'], 'tpl_sec_field_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_section_fields');
    }
};
