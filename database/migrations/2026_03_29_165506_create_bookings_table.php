<?php

use App\Models\Offer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('lodgify_booking_id')->unique();
            $table->string('guest_name');
            $table->string('guest_phone');
            $table->string('guest_email')->nullable();
            $table->string('guest_nationality')->nullable();
            $table->tinyInteger('num_guests');
            $table->string('suite_name');
            $table->date('check_in');
            $table->date('check_out');
            $table->tinyInteger('num_nights');
            $table->string('booking_source');
            $table->string('booking_status');
            $table->decimal('total_amount', 10, 2);
            $table->string('currency', 3)->default('MAD');
            $table->text('special_requests')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamp('lodgify_synced_at')->nullable();
            $table->foreignIdFor(Offer::class, 'current_upsell_offer_id')->nullable()->constrained('offers')->nullOnDelete();
            $table->timestamp('upsell_offer_sent_at')->nullable();
            $table->timestamps();

            $table->index('guest_phone');
            $table->index('booking_status');
            $table->index('check_in');
            $table->index('check_out');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
