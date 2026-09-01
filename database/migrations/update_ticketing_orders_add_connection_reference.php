<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticketing_orders', function (Blueprint $table) {
            $table->string('connection_reference')->nullable()->index()->after('connection_id');
        });
    }

    public function down(): void
    {
        Schema::table('ticketing_orders', function (Blueprint $table) {
            $table->dropIndex(['connection_reference']);
            $table->dropColumn('connection_reference');
        });
    }
};
