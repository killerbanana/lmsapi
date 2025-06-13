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
        Schema::create('quiz_assessment_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_assessment_id')
                ->constrained('quiz_assessments')
                ->onDelete('cascade');
            $table->string('student_idnumber');
            $table->unsignedInteger('score')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('answer_text')->nullable(); // if text answer
            $table->string('file_path')->nullable(); 
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
