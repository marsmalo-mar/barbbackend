<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            [
                'name' => 'Classic Haircut',
                'description' => 'Traditional men\'s haircut with styling and finished with hot towel',
                'price' => 25.00,
                'duration' => 30,
                'image_path' => 'uploads/default.png',
                'created_at' => now(),
            ],
            [
                'name' => 'Beard Trim & Shape',
                'description' => 'Professional beard trimming and shaping with hot towel treatment',
                'price' => 15.00,
                'duration' => 20,
                'image_path' => 'uploads/default.png',
                'created_at' => now(),
            ],
            [
                'name' => 'Haircut & Beard Combo',
                'description' => 'Complete grooming package with haircut and beard styling',
                'price' => 35.00,
                'duration' => 45,
                'image_path' => 'uploads/default.png',
                'created_at' => now(),
            ],
            [
                'name' => 'Hot Shave',
                'description' => 'Traditional hot towel straight razor shave with premium products',
                'price' => 30.00,
                'duration' => 30,
                'image_path' => 'uploads/default.png',
                'created_at' => now(),
            ],
            [
                'name' => 'Hair Coloring',
                'description' => 'Professional hair coloring service with premium products',
                'price' => 45.00,
                'duration' => 60,
                'image_path' => 'uploads/default.png',
                'created_at' => now(),
            ],
            [
                'name' => 'Kids Haircut',
                'description' => 'Special haircut service for children under 12',
                'price' => 18.00,
                'duration' => 20,
                'image_path' => 'uploads/default.png',
                'created_at' => now(),
            ],
        ];

        DB::table('services')->insert($services);
    }
}

