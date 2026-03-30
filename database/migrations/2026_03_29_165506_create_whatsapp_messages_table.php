<?php

use App\Models\Booking;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Booking::class)->nullable()->constrained()->nullOnDelete();
            $table->string('direction');
            $table->string('phone_number');
            $table->text('message_body');
            $table->string('agent_source')->nullable();
            $table->string('twochat_message_id')->nullable()->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index('phone_number');
            $table->index('booking_id');
            $table->index('direction');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
