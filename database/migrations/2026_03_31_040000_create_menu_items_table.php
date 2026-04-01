<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_fr')->nullable();
            $table->string('category');
            $table->text('description')->nullable();
            $table->decimal('price', 8, 2)->nullable();
            $table->string('currency', 3)->default('MAD');
            $table->boolean('is_available')->default(true);
            $table->string('availability_note')->nullable();
            $table->timestamps();

            $table->index('category');
            $table->index('is_available');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
