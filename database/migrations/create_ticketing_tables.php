<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticketing_event_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('colour', 20)->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('ticketing_event_categories')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ticketing_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('description')->nullable();
            $table->string('venue_name')->nullable();
            $table->string('venue_address')->nullable();
            $table->decimal('venue_lat', 10, 7)->nullable();
            $table->decimal('venue_lng', 10, 7)->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->string('timezone', 64)->default('UTC');
            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence_rule')->nullable();
            $table->unsignedInteger('max_capacity')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ticketing_event_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('ticketing_events')->cascadeOnDelete();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->string('status', 20)->default('scheduled')->index();
            $table->timestamps();
            $table->unique(['event_id', 'starts_at']);
        });

        Schema::create('ticketing_event_category', function (Blueprint $table) {
            $table->foreignId('event_id')->constrained('ticketing_events')->cascadeOnDelete();
            $table->foreignId('event_category_id')->constrained('ticketing_event_categories')->cascadeOnDelete();
            $table->primary(['event_id', 'event_category_id']);
        });

        Schema::create('ticketing_speakers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->longText('bio')->nullable();
            $table->string('photo')->nullable();
            $table->string('organisation')->nullable();
            $table->json('social_links')->nullable();
            $table->timestamps();
        });

        Schema::create('ticketing_event_speaker', function (Blueprint $table) {
            $table->foreignId('event_id')->constrained('ticketing_events')->cascadeOnDelete();
            $table->foreignId('speaker_id')->constrained('ticketing_speakers')->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->primary(['event_id', 'speaker_id']);
        });

        Schema::create('ticketing_sponsors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('logo')->nullable();
            $table->string('url')->nullable();
            $table->string('tier')->nullable();
            $table->timestamps();
        });

        Schema::create('ticketing_event_sponsor', function (Blueprint $table) {
            $table->foreignId('event_id')->constrained('ticketing_events')->cascadeOnDelete();
            $table->foreignId('sponsor_id')->constrained('ticketing_sponsors')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->primary(['event_id', 'sponsor_id']);
        });

        Schema::create('ticketing_ticket_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('ticketing_events')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('base_price');
            $table->string('currency', 3);
            $table->unsignedInteger('quantity_available')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['event_id', 'is_active']);
        });

        Schema::create('ticketing_pricing_rules', function (Blueprint $table) {
            $table->id();
            // Null ticket_type_id = a global rule evaluated for every ticket type.
            $table->foreignId('ticket_type_id')->nullable()->constrained('ticketing_ticket_types')->cascadeOnDelete();
            $table->string('type', 50)->index();
            $table->json('config');
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('ticketing_discount_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('type', 20);
            $table->unsignedInteger('value');
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            // Null = applies to all ticket types.
            $table->json('ticket_type_ids')->nullable();
            $table->timestamps();
        });

        Schema::create('ticketing_registration_questions', function (Blueprint $table) {
            $table->id();
            // Null event_id = a global question asked for every event.
            $table->foreignId('event_id')->nullable()->constrained('ticketing_events')->cascadeOnDelete();
            $table->string('label');
            $table->string('type', 30);
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('ticketing_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 32)->unique();
            // Orders are financial records: block event deletion while orders exist.
            $table->foreignId('event_id')->constrained('ticketing_events')->restrictOnDelete();
            $table->foreignId('event_occurrence_id')->constrained('ticketing_event_occurrences')->restrictOnDelete();
            $table->foreignId('discount_code_id')->nullable()->constrained('ticketing_discount_codes')->nullOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->string('currency', 3);
            $table->unsignedInteger('subtotal')->default(0);
            $table->unsignedInteger('discount_total')->default(0);
            $table->unsignedInteger('vat_total')->default(0);
            $table->unsignedInteger('total')->default(0);
            $table->string('purchaser_name');
            $table->string('purchaser_email')->index();
            $table->string('purchaser_phone', 64)->nullable();
            $table->string('purchaser_organisation')->nullable();
            $table->string('purchaser_country', 64)->nullable();
            $table->string('stripe_checkout_session_id')->nullable()->unique();
            $table->string('stripe_payment_intent_id')->nullable()->index();
            $table->text('success_url')->nullable();
            $table->text('cancel_url')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            $table->index(['event_id', 'status']);
            $table->index(['event_occurrence_id', 'status']);
        });

        Schema::create('ticketing_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('ticketing_orders')->cascadeOnDelete();
            // Keep the historical record: block ticket type deletion while sold.
            $table->foreignId('ticket_type_id')->constrained('ticketing_ticket_types')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price');
            // Snapshot of applied pricing rules at purchase time; never re-derived.
            $table->json('pricing_snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('ticketing_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained('ticketing_order_items')->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->index();
            $table->string('phone', 64)->nullable();
            $table->string('organisation')->nullable();
            $table->string('country', 64)->nullable();
            $table->string('checkin_token', 64)->unique();
            $table->timestamp('checked_in_at')->nullable();
            $table->string('ticket_pdf_path')->nullable();
            $table->timestamps();
        });

        Schema::create('ticketing_registration_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendee_id')->constrained('ticketing_attendees')->cascadeOnDelete();
            // Label is snapshotted so answers stay meaningful if the question is deleted.
            // Explicit constraint name: the auto-generated one exceeds MySQL's
            // 64-character identifier limit.
            $table->foreignId('registration_question_id')->nullable()
                ->constrained('ticketing_registration_questions', 'id', 'ticketing_responses_question_fk')
                ->nullOnDelete();
            $table->string('label');
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('ticketing_check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendee_id')->constrained('ticketing_attendees')->cascadeOnDelete();
            $table->timestamp('checked_in_at');
            // No FK constraint: the user model/table is host-app configurable.
            $table->unsignedBigInteger('checked_in_by')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticketing_check_ins');
        Schema::dropIfExists('ticketing_registration_responses');
        Schema::dropIfExists('ticketing_attendees');
        Schema::dropIfExists('ticketing_order_items');
        Schema::dropIfExists('ticketing_orders');
        Schema::dropIfExists('ticketing_registration_questions');
        Schema::dropIfExists('ticketing_discount_codes');
        Schema::dropIfExists('ticketing_pricing_rules');
        Schema::dropIfExists('ticketing_ticket_types');
        Schema::dropIfExists('ticketing_event_sponsor');
        Schema::dropIfExists('ticketing_sponsors');
        Schema::dropIfExists('ticketing_event_speaker');
        Schema::dropIfExists('ticketing_speakers');
        Schema::dropIfExists('ticketing_event_category');
        Schema::dropIfExists('ticketing_event_occurrences');
        Schema::dropIfExists('ticketing_events');
        Schema::dropIfExists('ticketing_event_categories');
    }
};
