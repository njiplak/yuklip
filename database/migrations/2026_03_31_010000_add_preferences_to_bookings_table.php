<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('conversation_state')->default('waiting_preferences')->after('internal_notes');
            $table->string('pref_arrival_time')->nullable()->after('conversation_state');
            $table->string('pref_bed_type')->nullable()->after('pref_arrival_time');
            $table->string('pref_airport_transfer')->nullable()->after('pref_bed_type');
            $table->string('pref_special_requests')->nullable()->after('pref_airport_transfer');

            $table->index('conversation_state');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['conversation_state']);
            $table->dropColumn([
                'conversation_state',
                'pref_arrival_time',
                'pref_bed_type',
                'pref_airport_transfer',
                'pref_special_requests',
            ]);
        });
    }
};
