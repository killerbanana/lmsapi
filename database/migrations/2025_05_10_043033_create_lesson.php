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
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            
            // Foreign key to users (e.g., instructor's idnumber)
            $table->string('idnumber');
            $table->foreign('idnumber')->references('idnumber')->on('users')->onDelete('cascade');
            
            // Foreign key to classes table
            $table->string('class_id'); // Change from integer
            $table->foreign('class_id')->references('class_id')->on('classes')->onDelete('cascade');

            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
