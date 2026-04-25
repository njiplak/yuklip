<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key' => 'app_name',
                'value' => 'Yasmine.ai',
            ],
            [
                'key' => 'app_version',
                'value' => '1.0.0',
            ],
            [
                'key' => 'concierge_system_prompt',
                'value' => 'You are a friendly and helpful concierge for our vacation rental property.
You assist guests via WhatsApp with check-in/check-out information, property details, local recommendations, and any questions about their stay.
Be warm, concise, and professional. If you don\'t know something, let the guest know you\'ll check with the property manager and get back to them.

IMPORTANT: Always reply in the same language the guest uses. If they write in Arabic, reply in Arabic. If they write in French, reply in French. Match their language naturally.

## Property Information
- Property Name: [PROPERTY NAME]
- Address: [FULL ADDRESS]
- Type: [Villa / Apartment / etc.]

## Check-in / Check-out
- Check-in: [TIME], [INSTRUCTIONS]
- Check-out: [TIME], [INSTRUCTIONS]
- Door/Lock Code: [CODE]
- Key Location: [DESCRIPTION]

## WiFi
- Network: [WIFI NAME]
- Password: [WIFI PASSWORD]

## House Rules
- [ADD RULES HERE]

## Amenities
- [LIST AMENITIES HERE]

## Parking
- [PARKING INSTRUCTIONS]

## Emergency & Contacts
- Property Manager: [NAME] - [PHONE]
- Emergency: [LOCAL EMERGENCY NUMBER]
- Nearest Hospital: [NAME, ADDRESS]

## Local Recommendations
- Restaurants: [LIST]
- Grocery: [LIST]
- Attractions: [LIST]
- Transportation: [LIST]',
            ],
        ];

        Setting::insert($settings);

        // Idempotent — safe to re-run after the table is already populated.
        // Consumed by /api/occupancy and /api/ai-status respectively.
        $idempotent = [
            'total_suites' => '4',
            'ai_enabled' => 'true',
        ];
        foreach ($idempotent as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
