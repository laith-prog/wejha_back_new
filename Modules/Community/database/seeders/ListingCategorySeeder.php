<?php

namespace Modules\Community\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ListingCategorySeeder extends Seeder
{
    public function run()
    {
        $categories = [
            [
                'name' => 'real_estate',
                'display_name' => 'Real Estate',
                'description' => 'Property listings including apartments, houses, villas, and land',
                'subcategories' => [
                    ['name' => 'apartment', 'display_name' => 'Apartment'],
                    ['name' => 'house', 'display_name' => 'House'],
                    ['name' => 'villa', 'display_name' => 'Villa'],
                    ['name' => 'land', 'display_name' => 'Land'],
                    ['name' => 'commercial', 'display_name' => 'Commercial'],
                    ['name' => 'room', 'display_name' => 'Room']
                ]
            ],
            [
                'name' => 'service',
                'display_name' => 'Services',
                'description' => 'Service offerings including cleaning, maintenance, and professional services',
                'subcategories' => [
                    ['name' => 'cleaning', 'display_name' => 'Cleaning'],
                    ['name' => 'maintenance', 'display_name' => 'Maintenance'],
                    ['name' => 'professional', 'display_name' => 'Professional Services'],
                    ['name' => 'education', 'display_name' => 'Education']
                ]
            ],
            [
                'name' => 'vehicle',
                'display_name' => 'Vehicles',
                'description' => 'Vehicle listings including cars, motorcycles, and boats',
                'subcategories' => [
                    ['name' => 'car', 'display_name' => 'Car'],
                    ['name' => 'motorcycle', 'display_name' => 'Motorcycle'],
                    ['name' => 'boat', 'display_name' => 'Boat'],
                    ['name' => 'truck', 'display_name' => 'Truck']
                ]
            ],
            [
                'name' => 'job',
                'display_name' => 'Jobs',
                'description' => 'Job listings across various industries',
                'subcategories' => [
                    ['name' => 'full_time', 'display_name' => 'Full Time'],
                    ['name' => 'part_time', 'display_name' => 'Part Time'],
                    ['name' => 'contract', 'display_name' => 'Contract'],
                    ['name' => 'freelance', 'display_name' => 'Freelance']
                ]
            ],
            [
                'name' => 'bid',
                'display_name' => 'Bids',
                'description' => 'Auction and bidding listings',
                'subcategories' => [
                    ['name' => 'auction', 'display_name' => 'Auction'],
                    ['name' => 'tender', 'display_name' => 'Tender'],
                    ['name' => 'project_bid', 'display_name' => 'Project Bid']
                ]
            ]
        ];

        foreach ($categories as $category) {
            $subcategories = $category['subcategories'];
            unset($category['subcategories']);

            $categoryId = DB::table('listing_categories')->insertGetId([
                'name' => $category['name'],
                'display_name' => $category['display_name'],
                'description' => $category['description'],
                'created_at' => now(),
                'updated_at' => now()
            ]);

            foreach ($subcategories as $subcategory) {
                DB::table('listing_subcategories')->insert([
                    'category_id' => $categoryId,
                    'name' => $subcategory['name'],
                    'display_name' => $subcategory['display_name'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }
    }
} 