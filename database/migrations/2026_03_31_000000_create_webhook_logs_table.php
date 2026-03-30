<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('method', 10);
            $table->text('url');
            $table->json('headers')->nullable();
            $table->json('payload')->nullable();
            $table->integer('status_code')->nullable();
            $table->json('response_body')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index('source');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
