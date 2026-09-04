<?php

namespace Database\Seeders;

use App\Models\Venue;
use Illuminate\Database\Seeder;

class VenueSeeder extends Seeder
{
    public function run(): void
    {
        Venue::create([
            'name' => 'Combolojo Sports Center',
            'description' => 'ዘመናዊ እና ለኳስ ጨዋታ ምቹ የሆነ ምርጥ ሜዳ።',
            'location' => 'ቦሌ መድኃኒዓለም',
            'city' => 'Addis Ababa',
            'sub_city' => 'Bole',
            'capacity' => 14, // ሜዳው ስንት ሰው ይይዛል
            'price_per_hour' => 500.00,
            'owner_id' => 1, // መጀመሪያ DatabaseSeeder ላይ የሚፈጠረው User ID
            'image_url' => 'https://images.unsplash.com/photo-1574629810360-7efbbe195018?w=800',
            'is_active' => true, // የግድ true መሆን አለበት እንዲታይ
            'sport_types' => json_encode(['Football', 'Basketball']),
            'facilities' => json_encode(['Parking', 'Shower', 'Locker Room']),
        ]);

        Venue::create([
            'name' => 'Jan Meda Turf',
            'description' => 'ሰፊ እና ለልምምድ አመቺ የሆነ ሰው ሰራሽ ሳር ሜዳ።',
            'location' => '6 ኪሎ',
            'city' => 'Addis Ababa',
            'sub_city' => 'Arada',
            'capacity' => 22,
            'price_per_hour' => 400.00,
            'owner_id' => 1,
            'image_url' => 'https://images.unsplash.com/photo-1529900948632-58674ba193cb?w=800',
            'is_active' => true,
            'sport_types' => json_encode(['Football']),
            'facilities' => json_encode(['Parking']),
        ]);
    }
}