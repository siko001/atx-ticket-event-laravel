<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticketing_events', function (Blueprint $table) {
            $table->string('image')->nullable()->after('description');
            $table->json('gallery')->nullable()->after('image');
        });
    }

    public function down(): void
    {
        Schema::table('ticketing_events', function (Blueprint $table) {
            $table->dropColumn(['image', 'gallery']);
        });
    }
};
