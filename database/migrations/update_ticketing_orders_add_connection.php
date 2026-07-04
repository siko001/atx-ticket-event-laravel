<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticketing_orders', function (Blueprint $table) {
            // Which WordPress connection the order came through (null = direct
            // API / unknown) and whether it was placed in test mode — both
            // snapshotted at purchase time so refunds use the same keys.
            $table->foreignId('connection_id')->nullable()
                ->constrained('ticketing_connections')->nullOnDelete();
            $table->boolean('is_test')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('ticketing_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('connection_id');
        });

        Schema::table('ticketing_orders', function (Blueprint $table) {
            $table->dropIndex(['is_test']);
            $table->dropColumn('is_test');
        });
    }
};
