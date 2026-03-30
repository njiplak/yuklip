<?php

use App\Models\Booking;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->string('agent');
            $table->string('action');
            $table->foreignIdFor(Booking::class)->nullable()->constrained()->nullOnDelete();
            $table->json('payload')->nullable();
            $table->string('status');
            $table->text('error_message')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->timestamps();

            $table->index('agent');
            $table->index('status');
            $table->index('booking_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_logs');
    }
};
