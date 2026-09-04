<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ለአድሚኑ ስልክ ቁጥር ጨምረናል
        User::create([
            'name' => 'System Admin',
            'email' => 'admin@gmail.com',
            'password' => Hash::make('password'),
            'phone_number' => '0911223344', // ይህንን የግድ መጨመር አለብህ
            'role' => 'admin',
        ]);

        // የቬንዩ ሴደሩን መጥራት
        $this->call(VenueSeeder::class);
    }
}