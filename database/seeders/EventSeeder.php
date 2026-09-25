<?php

namespace Database\Seeders;

use App\Models\Event;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        Event::create([
            'title' => 'Addis Champions League 2026',
            'category' => 'Football',
            'status' => 'Upcoming',
            'type' => 'Team Registration',
            'date' => 'Oct 15 - Nov 02, 2026',
            'location' => 'Abebe Bikila Stadium, Addis Ababa',
            'price' => '2,500 ETB',
            'image_url' => 'https://images.unsplash.com/photo-1508098682722-e99c43a406b2?w=800&auto=format&fit=crop&q=60'
        ]);

        Event::create([
            'title' => 'National Basketball Cup',
            'category' => 'Basketball',
            'status' => 'Ongoing',
            'type' => 'Team Registration',
            'date' => 'Sep 20 - Oct 05, 2026',
            'location' => 'Arat Kilo Sports Complex',
            'price' => '1,800 ETB',
            'image_url' => 'https://images.unsplash.com/photo-1546519638-68e109498ffc?w=800&auto=format&fit=crop&q=60'
        ]);

        Event::create([
            'title' => 'Ethio Tennis Open',
            'category' => 'Tennis',
            'status' => 'Completed',
            'type' => 'Individual',
            'date' => 'Aug 10 - Aug 18, 2026',
            'location' => 'Omedla Club, Addis Ababa',
            'price' => '1,000 ETB',
            'image_url' => 'https://images.unsplash.com/photo-1595435934249-5df7ed86e1c0?w=800&auto=format&fit=crop&q=60'
        ]);
    }
}