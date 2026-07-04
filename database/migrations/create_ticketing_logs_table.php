<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticketing_logs', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 20)->index();
            $table->string('level', 10)->default('info');
            $table->string('message', 500);
            $table->json('context')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticketing_logs');
    }
};
