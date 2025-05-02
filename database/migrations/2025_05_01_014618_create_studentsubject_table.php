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
        Schema::create('studentsubjects', function (Blueprint $table) {
            $table->id();
            $table->string('idnumber');
            $table->string('subject_id');
            $table->enum('usertype', ['Administrator', 'Student', 'Teacher', 'Parent'])->default('Student');
            $table->timestamps(); 
            $table->foreign('idnumber')->references('idnumber')->on('users')->onDelete('cascade');
            $table->foreign('subject_id')->references('subject_id')->on('subjects')->onDelete('cascade');
            $table->unique(['idnumber', 'subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('studentsubjects');
    }
};
