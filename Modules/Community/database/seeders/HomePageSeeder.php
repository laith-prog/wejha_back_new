<?php

namespace Modules\Community\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class HomePageSeeder extends Seeder
{
    public function run(): void
    {
        // Avoid duplicating demo data if listings already exist
        if (DB::table('listings')->count() > 0) {
            $this->command?->warn('Listings already exist. Skipping HomePageSeeder.');
            return;
        }

        DB::beginTransaction();
        try {
            // Ensure we have a user to own the listings
            $user = DB::table('users')->where('email', 'test@example.com')->first();
            if (!$user) {
                // Create a fallback demo user (UUID primary key)
                $userId = (string) Str::uuid();
                $customerRoleId = DB::table('roles')->where('name', 'customer')->value('id') ?? 3;
                DB::table('users')->insert([
                    'id' => $userId,
                    'fname' => 'Demo',
                    'lname' => 'User',
                    'email' => 'test@example.com',
                    'email_verified_at' => now(),
                    'password' => Hash::make('Password123'),
                    'role_id' => $customerRoleId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $user = (object) ['id' => $userId];
            }

            // Fetch categories and subcategories
            $categoryByName = DB::table('listing_categories')->pluck('id', 'name');

            $getSubcategoryId = function (string $categoryName, string $subcategoryName) use ($categoryByName) {
                $categoryId = $categoryByName[$categoryName] ?? null;
                if (!$categoryId) {
                    return null;
                }
                return DB::table('listing_subcategories')
                    ->where('category_id', $categoryId)
                    ->where('name', $subcategoryName)
                    ->value('id');
            };

            // Define demo listings across categories
            $demoListings = [
                [
                    'title' => 'Modern Apartment in City Center',
                    'description' => 'Spacious 2-bedroom apartment with balcony, close to amenities.',
                    'price' => 1200.00,
                    'price_type' => 'monthly',
                    'currency' => 'USD',
                    'listing_type' => 'real_estate',
                    'purpose' => 'rent',
                    'status' => 'active',
                    'city' => 'Amman',
                    'area' => 'Abdali',
                    'lat' => 31.953949,
                    'lng' => 35.910635,
                    'is_featured' => true,
                    'is_promoted' => true,
                    'promoted_until' => now()->addDays(14),
                    'views_count' => 150,
                    'category' => 'real_estate',
                    'subcategory' => 'apartment',
                    'image' => 'images/real_estate/apartment1.jpg',
                ],
                [
                    'title' => 'Full Home Deep Cleaning',
                    'description' => 'Professional team offering full home deep cleaning with eco products.',
                    'price' => 200.00,
                    'price_type' => 'total',
                    'currency' => 'USD',
                    'listing_type' => 'service',
                    'purpose' => 'offer',
                    'status' => 'active',
                    'city' => 'Amman',
                    'area' => 'Jabal Amman',
                    'lat' => 31.951569,
                    'lng' => 35.923963,
                    'is_featured' => true,
                    'is_promoted' => false,
                    'promoted_until' => null,
                    'views_count' => 90,
                    'category' => 'service',
                    'subcategory' => 'cleaning',
                    'image' => 'images/services/cleaning1.jpg',
                ],
                [
                    'title' => '2018 Toyota Corolla - Excellent Condition',
                    'description' => 'Low mileage, regularly serviced, single owner.',
                    'price' => 11500.00,
                    'price_type' => 'total',
                    'currency' => 'USD',
                    'listing_type' => 'vehicle',
                    'purpose' => 'sell',
                    'status' => 'active',
                    'city' => 'Amman',
                    'area' => 'Khalda',
                    'lat' => 31.999533,
                    'lng' => 35.843132,
                    'is_featured' => false,
                    'is_promoted' => true,
                    'promoted_until' => now()->addDays(7),
                    'views_count' => 240,
                    'category' => 'vehicle',
                    'subcategory' => 'car',
                    'image' => 'images/vehicles/car1.jpg',
                ],
                [
                    'title' => 'Senior PHP Developer',
                    'description' => 'Looking for an experienced Laravel developer to join our team.',
                    'price' => null,
                    'price_type' => null,
                    'currency' => 'USD',
                    'listing_type' => 'job',
                    'purpose' => 'seek',
                    'status' => 'active',
                    'city' => 'Amman',
                    'area' => 'Shmeisani',
                    'lat' => 31.971221,
                    'lng' => 35.910678,
                    'is_featured' => true,
                    'is_promoted' => false,
                    'promoted_until' => null,
                    'views_count' => 60,
                    'category' => 'job',
                    'subcategory' => 'full_time',
                    'image' => 'images/jobs/job1.jpg',
                ],
                [
                    'title' => 'Office Renovation Project - Bid Now',
                    'description' => 'Seeking contractors for complete office renovation project.',
                    'price' => null,
                    'price_type' => null,
                    'currency' => 'USD',
                    'listing_type' => 'bid',
                    'purpose' => 'tender',
                    'status' => 'active',
                    'city' => 'Amman',
                    'area' => 'Sweifieh',
                    'lat' => 31.957493,
                    'lng' => 35.860069,
                    'is_featured' => false,
                    'is_promoted' => false,
                    'promoted_until' => null,
                    'views_count' => 35,
                    'category' => 'bid',
                    'subcategory' => 'tender',
                    'image' => 'images/bids/bid1.jpg',
                ],
            ];

            foreach ($demoListings as $demo) {
                $categoryId = $categoryByName[$demo['category']] ?? null;
                $subcategoryId = $getSubcategoryId($demo['category'], $demo['subcategory']);

                if (!$categoryId || !$subcategoryId) {
                    // Skip if required taxonomy missing
                    $this->command?->warn("Skipping listing '{$demo['title']}' due to missing category/subcategory.");
                    continue;
                }

                $listingId = DB::table('listings')->insertGetId([
                    'user_id' => $user->id,
                    'title' => $demo['title'],
                    'description' => $demo['description'],
                    'price' => $demo['price'],
                    'price_type' => $demo['price_type'],
                    'currency' => $demo['currency'],
                    'post_number' => 'PN-'.strtoupper(Str::random(8)),
                    'phone_number' => '+962-7'.random_int(700000000, 799999999),
                    'category_id' => $categoryId,
                    'subcategory_id' => $subcategoryId,
                    'listing_type' => $demo['listing_type'],
                    'purpose' => $demo['purpose'],
                    'status' => $demo['status'],
                    'lat' => $demo['lat'],
                    'lng' => $demo['lng'],
                    'city' => $demo['city'],
                    'area' => $demo['area'],
                    'features' => json_encode([]),
                    'similar_options' => json_encode([]),
                    'published_at' => now(),
                    'is_featured' => $demo['is_featured'],
                    'is_promoted' => $demo['is_promoted'],
                    'promoted_until' => $demo['promoted_until'],
                    'expires_at' => now()->addMonths(3),
                    'views_count' => $demo['views_count'],
                    'favorites_count' => 0,
                    'reports_count' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Primary image
                DB::table('listing_images')->insert([
                    'listing_id' => $listingId,
                    'image_path' => $demo['image'],
                    'thumbnail_path' => null,
                    'is_primary' => true,
                    'display_order' => 0,
                    'caption' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Optional: add a secondary image for variety
                DB::table('listing_images')->insert([
                    'listing_id' => $listingId,
                    'image_path' => str_replace('.jpg', '_2.jpg', $demo['image']),
                    'thumbnail_path' => null,
                    'is_primary' => false,
                    'display_order' => 1,
                    'caption' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();
            $this->command?->info('HomePageSeeder completed: demo listings and images inserted.');
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->command?->error('HomePageSeeder failed: '.$e->getMessage());
            throw $e;
        }
    }
} 