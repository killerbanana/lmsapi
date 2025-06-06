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
            $table->string('title');
            $table->unsignedInteger('max_score')->nullable(); // optional
            $table->unsignedInteger('points')->nullable();
            $table->string('category')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->string('lesson')->nullable(); // e.g., openEuler
            $table->unsignedTinyInteger('max_attempts')->default(1);
            $table->boolean('allow_late')->default(false);
            $table->string('grading')->default('Normal');
            $table->string('grading_scale')->default('Default');
            $table->text('instructions')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_assessments');
    }
};
