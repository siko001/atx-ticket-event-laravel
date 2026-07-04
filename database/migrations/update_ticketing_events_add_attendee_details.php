<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticketing_events', function (Blueprint $table) {
            $table->boolean('requires_attendee_details')->default(false)->after('max_capacity');
        });
    }

    public function down(): void
    {
        Schema::table('ticketing_events', function (Blueprint $table) {
            $table->dropColumn('requires_attendee_details');
        });
    }
};
