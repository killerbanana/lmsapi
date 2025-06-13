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
        Schema::create('dropbox_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->onDelete('cascade'); 
            $table->string('title');
            $table->unsignedInteger('max_score')->default(100);
            $table->unsignedInteger('points')->nullable();
            $table->string('category')->nullable();
            $table->dateTime('start_date')->nullable();
            $table->dateTime('due_date');
            $table->string('grading_scale')->default('Default');
            $table->unsignedTinyInteger('max_attempts')->default(1);
            $table->boolean('allow_late')->default(false);
            $table->enum('grading', ['Normal', 'Bonus', 'Penalty'])->default('Normal');
            $table->text('instructions')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dropbox_assessments');
    }
};
