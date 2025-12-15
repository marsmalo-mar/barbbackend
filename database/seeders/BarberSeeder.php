<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BarberSeeder extends Seeder
{
    public function run(): void
    {
        $barbers = [
            [
                'name' => 'Michael Johnson',
                'specialty' => 'Classic Cuts & Traditional Styling',
                'bio' => '15 years of experience in traditional barbering. Specializes in classic cuts and hot towel shaves.',
                'phone' => '+1 (555) 123-4567',
                'image_path' => 'uploads/profile.png',
                'created_at' => now(),
            ],
            [
                'name' => 'David Martinez',
                'specialty' => 'Modern Styles & Fade Expert',
                'bio' => 'Master of modern hairstyles and precision fades. Trained in latest cutting techniques.',
                'phone' => '+1 (555) 234-5678',
                'image_path' => 'uploads/profile.png',
                'created_at' => now(),
            ],
            [
                'name' => 'James Wilson',
                'specialty' => 'Beard Specialist',
                'bio' => 'Dedicated beard grooming expert with 10 years experience. Certified in advanced beard styling.',
                'phone' => '+1 (555) 345-6789',
                'image_path' => 'uploads/profile.png',
                'created_at' => now(),
            ],
            [
                'name' => 'Robert Garcia',
                'specialty' => 'All-Around Professional',
                'bio' => 'Versatile barber skilled in all services. Known for attention to detail and customer satisfaction.',
                'phone' => '+1 (555) 456-7890',
                'image_path' => 'uploads/profile.png',
                'created_at' => now(),
            ],
        ];

        DB::table('barbers')->insert($barbers);
    }
}

