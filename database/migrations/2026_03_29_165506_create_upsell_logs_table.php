<?php

use App\Models\Booking;
use App\Models\Offer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upsell_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Booking::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Offer::class)->constrained()->cascadeOnDelete();
            $table->text('message_sent');
            $table->timestamp('sent_at');
            $table->text('guest_reply')->nullable();
            $table->timestamp('reply_received_at')->nullable();
            $table->string('outcome')->nullable();
            $table->decimal('revenue_generated', 8, 2)->nullable();
            $table->timestamps();

            $table->index('booking_id');
            $table->index('offer_id');
            $table->index('outcome');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upsell_logs');
    }
};
