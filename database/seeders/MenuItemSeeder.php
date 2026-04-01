<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class MenuItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            // Drinks
            ['name' => 'Moroccan Mint Tea', 'name_fr' => 'Thé à la menthe', 'category' => 'drinks', 'price' => null, 'description' => 'Traditional Moroccan mint tea, served hot'],
            ['name' => 'Fresh Orange Juice', 'name_fr' => 'Jus d\'orange frais', 'category' => 'drinks', 'price' => null, 'description' => 'Freshly squeezed Moroccan oranges'],
            ['name' => 'Coffee', 'name_fr' => 'Café', 'category' => 'drinks', 'price' => null, 'description' => 'Espresso, americano, or café au lait'],
            ['name' => 'Fresh Watermelon Juice', 'name_fr' => 'Jus de pastèque', 'category' => 'drinks', 'price' => 40.00, 'description' => 'Seasonal fresh watermelon juice', 'availability_note' => 'Seasonal — May to September'],
            ['name' => 'Fresh Avocado Smoothie', 'name_fr' => 'Smoothie avocat', 'category' => 'drinks', 'price' => 50.00, 'description' => 'Avocado blended with milk and a touch of honey'],
            ['name' => 'Soft Drinks', 'name_fr' => 'Boissons gazeuses', 'category' => 'drinks', 'price' => 25.00, 'description' => 'Coca-Cola, Fanta, Sprite, Oulmès sparkling water'],
            ['name' => 'Still Water', 'name_fr' => 'Eau plate', 'category' => 'drinks', 'price' => null, 'description' => 'Sidi Ali mineral water, complimentary'],

            // Breakfast (included)
            ['name' => 'Msemen & Baghrir', 'name_fr' => 'Msemen et Baghrir', 'category' => 'breakfast', 'price' => null, 'description' => 'Traditional Moroccan pancakes with honey and amlou'],
            ['name' => 'Eggs to Order', 'name_fr' => 'Oeufs au choix', 'category' => 'breakfast', 'price' => null, 'description' => 'Scrambled, fried, or boiled'],
            ['name' => 'Seasonal Fruit Platter', 'name_fr' => 'Plateau de fruits de saison', 'category' => 'breakfast', 'price' => null, 'description' => 'Fresh seasonal fruits'],

            // Lunch
            ['name' => 'Moroccan Salad Plate', 'name_fr' => 'Assiette de salades marocaines', 'category' => 'lunch', 'price' => 80.00, 'description' => 'Assorted Moroccan salads — zaalouk, taktouka, and fresh vegetables'],
            ['name' => 'Club Sandwich', 'name_fr' => 'Club sandwich', 'category' => 'lunch', 'price' => 90.00, 'description' => 'Grilled chicken, fresh vegetables, and fries'],

            // Dinner
            ['name' => 'Chicken Tagine with Preserved Lemon', 'name_fr' => 'Tajine de poulet au citron confit', 'category' => 'dinner', 'price' => 180.00, 'description' => 'Traditional slow-cooked tagine with olives and preserved lemon'],
            ['name' => 'Lamb Tagine with Prunes', 'name_fr' => 'Tajine d\'agneau aux pruneaux', 'category' => 'dinner', 'price' => 220.00, 'description' => 'Tender lamb with caramelized prunes and almonds'],
            ['name' => 'Vegetable Couscous', 'name_fr' => 'Couscous aux légumes', 'category' => 'dinner', 'price' => 150.00, 'description' => 'Seven-vegetable couscous with chickpeas'],
            ['name' => 'Pastilla', 'name_fr' => 'Pastilla', 'category' => 'dinner', 'price' => 160.00, 'description' => 'Crispy pastry with chicken, almonds, and cinnamon'],

            // Snacks
            ['name' => 'Moroccan Pastries', 'name_fr' => 'Pâtisseries marocaines', 'category' => 'snacks', 'price' => 60.00, 'description' => 'Assortment of cornes de gazelle, chebakia, and briouats'],
            ['name' => 'Date Platter with Nuts', 'name_fr' => 'Plateau de dattes et fruits secs', 'category' => 'snacks', 'price' => 50.00, 'description' => 'Premium Medjool dates with almonds and walnuts'],
        ];

        MenuItem::insert(array_map(fn ($item) => array_merge([
            'currency' => 'MAD',
            'is_available' => true,
            'availability_note' => null,
            'description' => null,
            'name_fr' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $item), $items));
    }
}
