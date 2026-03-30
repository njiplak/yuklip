<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->string('offer_code')->unique();
            $table->string('title');
            $table->text('description');
            $table->string('category');
            $table->string('timing_rule');
            $table->decimal('price', 8, 2)->nullable();
            $table->string('currency', 3)->default('MAD');
            $table->boolean('is_active')->default(true);
            $table->tinyInteger('max_sends_per_stay')->default(1);
            $table->timestamps();

            $table->index('timing_rule');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
