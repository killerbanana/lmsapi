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
        Schema::create('quiz_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->onDelete('cascade'); 
            $table->string('title')->default('Untitled Quiz');
            $table->text('instructions')->nullable();
            $table->integer('points');
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
