<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticketing_connections', function (Blueprint $table) {
            // Encrypted at rest via the model's 'encrypted' casts.
            $table->text('stripe_live_secret')->nullable();
            $table->text('stripe_live_webhook_secret')->nullable();
            $table->text('stripe_test_secret')->nullable();
            $table->text('stripe_test_webhook_secret')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ticketing_connections', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_live_secret',
                'stripe_live_webhook_secret',
                'stripe_test_secret',
                'stripe_test_webhook_secret',
            ]);
        });
    }
};
