<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticketing_registration_questions', function (Blueprint $table) {
            // null/empty = the question applies to every ticket type.
            $table->json('ticket_type_ids')->nullable();
        });

        // Carry over the previous single-type scoping.
        foreach (DB::table('ticketing_registration_questions')->whereNotNull('ticket_type_id')->get() as $row) {
            DB::table('ticketing_registration_questions')
                ->where('id', $row->id)
                ->update(['ticket_type_ids' => json_encode([(int) $row->ticket_type_id])]);
        }

        Schema::table('ticketing_registration_questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ticket_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('ticketing_registration_questions', function (Blueprint $table) {
            $table->foreignId('ticket_type_id')->nullable()
                ->constrained('ticketing_ticket_types')->nullOnDelete();
        });

        foreach (DB::table('ticketing_registration_questions')->whereNotNull('ticket_type_ids')->get() as $row) {
            $ids = json_decode((string) $row->ticket_type_ids, true);

            DB::table('ticketing_registration_questions')
                ->where('id', $row->id)
                ->update(['ticket_type_id' => is_array($ids) && $ids !== [] ? (int) $ids[0] : null]);
        }

        Schema::table('ticketing_registration_questions', function (Blueprint $table) {
            $table->dropColumn('ticket_type_ids');
        });
    }
};
