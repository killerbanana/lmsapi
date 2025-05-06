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
        Schema::create('class_students', function (Blueprint $table) {
            $table->id();
            $table->string('idnumber');
            $table->string('class_id');
            $table->foreign('class_id')->references('class_id')->on('classes')->onDelete('cascade');
            $table->foreign('idnumber')->references('idnumber')->on('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_stud');
    }
};
