<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticketing_registration_questions', function (Blueprint $table) {
            // null = the question applies to every ticket type.
            $table->foreignId('ticket_type_id')->nullable()
                ->constrained('ticketing_ticket_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticketing_registration_questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ticket_type_id');
        });
    }
};
