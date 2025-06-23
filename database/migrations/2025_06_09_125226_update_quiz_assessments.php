<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * This method creates the table.
     */
    public function up(): void
    {
        // This check prevents errors if you accidentally run the migration twice.
        // However, the correct workflow is to use `php artisan migrate:fresh` or `migrate:refresh` if you need to start over.
        if (!Schema::hasTable('quiz_assessments')) {
            Schema::create('quiz_assessments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('section_id')->constrained()->onDelete('cascade');
                $table->string('title')->default('Untitled Quiz');
                $table->text('instructions')->nullable();
                $table->unsignedInteger('points')->default(100);
                $table->unsignedInteger('max_score')->default(100);
                $table->string('category')->nullable();
                $table->dateTime('start')->nullable();
                $table->dateTime('due');
                $table->enum('grading_scale', ['Default', 'Custom'])->default('Default');
                $table->enum('grading', ['Normal', 'Curve'])->default('Normal');
                $table->integer('max_attempts')->default(1);
                $table->boolean('allow_late')->default(false);
                $table->boolean('timed')->default(false);
                $table->boolean('instant_feedback')->default(false);
                $table->enum('release_grades', ['Instant', 'Manual'])->default('Instant');
                $table->enum('grading_method', ['latest', 'best'])->default('latest');
                $table->boolean('disable_past_due')->default(false);
                $table->boolean('autocomplete_on_retake')->default(false);
                $table->boolean('randomize_order')->default(true);
                $table->boolean('allow_review')->default(true);
                $table->boolean('allow_jump')->default(true);
                $table->json('show_in_results')->nullable();
                $table->enum('library', ['Personal', 'Organization'])->default('Personal');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     * This method should completely undo the 'up' method.
     * For a 'create' migration, this means dropping the table.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_assessments');
    }
};
