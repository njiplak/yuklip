<?php

namespace Database\Seeders;

use App\Models\Offer;
use Illuminate\Database\Seeder;

class OfferSeeder extends Seeder
{
    public function run(): void
    {
        $offers = [
            [
                'offer_code' => 'WELCOME_DRINK',
                'title' => 'Welcome Drink & Date Platter',
                'description' => 'A refreshing welcome drink with a traditional Moroccan date platter served in the suite upon arrival.',
                'category' => 'dining',
                'timing_rule' => 'arrival_day',
                'price' => 150.00,
                'currency' => 'MAD',
            ],
            [
                'offer_code' => 'HAMMAM_D2',
                'title' => 'Traditional Hammam & Gommage for Two',
                'description' => 'A relaxing traditional hammam experience with gommage (exfoliation) for two guests at a nearby luxury spa.',
                'category' => 'wellness',
                'timing_rule' => 'day_2',
                'price' => 800.00,
                'currency' => 'MAD',
            ],
            [
                'offer_code' => 'ROOFTOP_DINNER_D2',
                'title' => 'Private Rooftop Dinner Under the Stars',
                'description' => 'An intimate private dinner on the rooftop terrace with a multi-course Moroccan menu, candles, and lanterns.',
                'category' => 'dining',
                'timing_rule' => 'day_2',
                'price' => 1200.00,
                'currency' => 'MAD',
            ],
            [
                'offer_code' => 'MEDINA_TOUR_D3',
                'title' => 'Half-Day Medina Guided Walking Tour',
                'description' => 'A guided half-day walking tour through the historic Marrakech Medina, visiting souks, palaces, and hidden gems.',
                'category' => 'experience',
                'timing_rule' => 'day_3',
                'price' => 500.00,
                'currency' => 'MAD',
            ],
            [
                'offer_code' => 'CAMEL_TREK_D3',
                'title' => 'Camel Trek at Sunset, Palmeraie',
                'description' => 'A sunset camel trek through the Palmeraie palm grove with traditional mint tea and pastries.',
                'category' => 'experience',
                'timing_rule' => 'day_3',
                'price' => 600.00,
                'currency' => 'MAD',
            ],
            [
                'offer_code' => 'COOKING_CLASS_D4',
                'title' => 'Moroccan Cooking Class with the Chef',
                'description' => 'A hands-on Moroccan cooking class with our riad chef. Learn to make tagine, couscous, and pastilla.',
                'category' => 'experience',
                'timing_rule' => 'day_4',
                'price' => 450.00,
                'currency' => 'MAD',
            ],
            [
                'offer_code' => 'LATE_CHECKOUT',
                'title' => 'Late Checkout Until 14:00',
                'description' => 'Extend your stay with a late checkout until 14:00 for a relaxed last morning at the riad.',
                'category' => 'transport',
                'timing_rule' => 'day_1_before_checkout',
                'price' => 300.00,
                'currency' => 'MAD',
            ],
            [
                'offer_code' => 'AIRPORT_TRANSFER',
                'title' => 'Airport Transfer by Private Car',
                'description' => 'Private car transfer from the riad to Marrakech Menara Airport with bottled water and WiFi.',
                'category' => 'transport',
                'timing_rule' => 'day_1_before_checkout',
                'price' => 250.00,
                'currency' => 'MAD',
            ],
        ];

        Offer::insert($offers);
    }
}
