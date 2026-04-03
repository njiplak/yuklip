<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->timestamp('pending_send_at')->nullable()->after('received_at');
            $table->index('pending_send_at');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('detected_language', 5)->nullable()->after('guest_nationality');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropIndex(['pending_send_at']);
            $table->dropColumn('pending_send_at');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('detected_language');
        });
    }
};
