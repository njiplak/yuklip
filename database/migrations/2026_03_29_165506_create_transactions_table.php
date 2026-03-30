<?php

use App\Models\Booking;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Booking::class)->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('category');
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('MAD');
            $table->date('transaction_date');
            $table->string('payment_method')->nullable();
            $table->string('reference')->nullable();
            $table->string('recorded_by')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('category');
            $table->index('transaction_date');
            $table->index('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
