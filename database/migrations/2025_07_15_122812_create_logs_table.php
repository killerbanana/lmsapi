<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('logs', function (Blueprint $table) {
        // The unique ID for the log entry.
        $table->id();

        // The severity level of the log (e.g., 'info', 'error', 'debug').
        // The index improves performance when searching for logs of a specific level.
        $table->string('level')->index();

        // The main log message. Using 'text' allows for longer messages.
        $table->text('message');

        // A place to store extra contextual data as a JSON object.
        // This is very useful for storing request details, user ID, etc.
        $table->longText('context')->nullable();

        // The standard 'created_at' and 'updated_at' timestamps.
        // 'created_at' will mark exactly when the log was recorded.
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logs');
    }
};
