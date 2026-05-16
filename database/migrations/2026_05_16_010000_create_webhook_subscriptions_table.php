<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('event');
            $table->string('subscription_id')->nullable();
            $table->string('secret');
            $table->text('target_url');
            $table->timestamps();

            $table->unique(['source', 'event']);
            $table->index('subscription_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_subscriptions');
    }
};
