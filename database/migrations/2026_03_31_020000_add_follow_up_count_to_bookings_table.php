<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedTinyInteger('follow_up_count')->default(0)->after('pref_special_requests');
            $table->boolean('preferences_briefing_sent')->default(false)->after('follow_up_count');
            $table->boolean('revenue_logged')->default(false)->after('preferences_briefing_sent');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['follow_up_count', 'preferences_briefing_sent', 'revenue_logged']);
        });
    }
};
