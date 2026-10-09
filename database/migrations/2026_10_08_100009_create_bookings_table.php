<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An event booking request. `event_start` and `event_end` are both
     * event days (inclusive), so a one-day event has the same date twice.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->nullable()->unique();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_name');
            $table->string('guest_email')->nullable();
            $table->string('guest_phone')->nullable();
            $table->string('contact_type', 20)->nullable(); // whatsapp | phone | email
            $table->string('locale', 2)->nullable(); // language the guest wrote in
            $table->string('event_type', 20)->default('social');
            $table->date('event_start');
            $table->date('event_end');
            $table->unsignedSmallInteger('guests')->default(50);
            $table->string('setup_style', 20)->nullable();
            $table->boolean('catering')->default(false);
            $table->decimal('total_price', 12, 2);
            $table->string('status')->default('pending'); // pending | confirmed | cancelled
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
