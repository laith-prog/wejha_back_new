<?php

namespace Modules\Community\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class HomePageSeeder extends Seeder
{
    public function run(): void
    {
        DB::beginTransaction();
        try {
            // Ensure we have a user to own the listings
            $user = DB::table('users')->where('email', 'test@example.com')->first();
            if (!$user) {
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

            // Fetch categories map
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

            // Demo listings across categories
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
                    'image_urls' => [
                        'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1600&q=80',
                        'https://images.unsplash.com/photo-1502005229762-cf1b2da7c52f?auto=format&fit=crop&w=1600&q=80'
                    ],
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
                    'image_urls' => [
                        'https://images.unsplash.com/photo-1581579188860-41f3498d7fcd?auto=format&fit=crop&w=1600&q=80',
                        'https://images.unsplash.com/photo-1585421514738-01798e348b17?auto=format&fit=crop&w=1600&q=80'
                    ],
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
                    'image_urls' => [
                        'https://images.unsplash.com/photo-1493238792000-8113da705763?auto=format&fit=crop&w=1600&q=80',
                        'https://images.unsplash.com/photo-1511914265872-c40672604a66?auto=format&fit=crop&w=1600&q=80'
                    ],
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
                    'image_urls' => [
                        'https://images.unsplash.com/photo-1521737604893-d14cc237f11d?auto=format&fit=crop&w=1600&q=80',
                        'https://images.unsplash.com/photo-1529336953121-df2f7c2a0f68?auto=format&fit=crop&w=1600&q=80'
                    ],
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
                    'image_urls' => [
                        'https://images.unsplash.com/photo-1503387762-592deb58ef4e?auto=format&fit=crop&w=1600&q=80',
                        'https://images.unsplash.com/photo-1504307651254-35680f356dfd?auto=format&fit=crop&w=1600&q=80'
                    ],
                ],
            ];

            foreach ($demoListings as $demo) {
                $categoryId = $categoryByName[$demo['category']] ?? null;
                $subcategoryId = $getSubcategoryId($demo['category'], $demo['subcategory']);
                if (!$categoryId || !$subcategoryId) {
                    $this->command?->warn("Skipping listing '{$demo['title']}' due to missing category/subcategory.");
                    continue;
                }

                // Find existing listing by title or insert a new one
                $listingId = DB::table('listings')->where('title', $demo['title'])->value('id');
                if (!$listingId) {
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
                } else {
                    // Ensure taxonomy columns are set on existing record
                    DB::table('listings')->where('id', $listingId)->update([
                        'category_id' => $categoryId,
                        'subcategory_id' => $subcategoryId,
                        'listing_type' => $demo['listing_type'],
                        'purpose' => $demo['purpose'],
                        'status' => $demo['status'],
                        'updated_at' => now(),
                    ]);
                }

                // Ensure at least one primary image exists
                $hasPrimaryImage = DB::table('listing_images')
                    ->where('listing_id', $listingId)
                    ->where('is_primary', true)
                    ->exists();

                if (!$hasPrimaryImage) {
                    $imageUrls = $demo['image_urls'] ?? [];
                    if (empty($imageUrls)) {
                        $imageUrls = ['https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1600&q=80'];
                    }

                    foreach ($imageUrls as $index => $url) {
                        DB::table('listing_images')->insert([
                            'listing_id' => $listingId,
                            'image_path' => $url,
                            'thumbnail_path' => null,
                            'is_primary' => $index === 0,
                            'display_order' => $index,
                            'caption' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                // Insert into category-specific tables if missing
                switch ($demo['category']) {
                    case 'real_estate':
                        $exists = DB::table('real_estate_listings')->where('listing_id', $listingId)->exists();
                        if (!$exists) {
                            DB::table('real_estate_listings')->insert([
                                'listing_id' => $listingId,
                                'property_type' => $demo['subcategory'],
                                'offer_type' => in_array($demo['purpose'], ['rent', 'sell']) ? $demo['purpose'] : 'rent',
                                'room_number' => 2,
                                'bathrooms' => 2,
                                'area' => 110.00,
                                'floors' => null,
                                'floor_number' => 5,
                                'has_parking' => true,
                                'has_garden' => false,
                                'balcony' => 1,
                                'has_pool' => false,
                                'has_elevator' => true,
                                'furnished' => 'semi',
                                'year_built' => 2018,
                                'ownership_type' => 'freehold',
                                'legal_status' => 'ready',
                                'amenities' => json_encode(['air_conditioning', 'security']),
                                'is_room_rental' => false,
                                'room_area' => null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                        break;

                    case 'service':
                        $exists = DB::table('service_listings')->where('listing_id', $listingId)->exists();
                        if (!$exists) {
                            DB::table('service_listings')->insert([
                                'listing_id' => $listingId,
                                'service_type' => $demo['subcategory'],
                                'availability' => json_encode(['mon_fri' => '09:00-17:00', 'sat' => '10:00-14:00']),
                                'experience_years' => 5,
                                'qualifications' => 'Certified, insured',
                                'service_area' => 'Amman',
                                'is_mobile' => true,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                        break;

                    case 'vehicle':
                        $exists = DB::table('vehicle_listings')->where('listing_id', $listingId)->exists();
                        if (!$exists) {
                            DB::table('vehicle_listings')->insert([
                                'listing_id' => $listingId,
                                'vehicle_type' => 'car',
                                'make' => 'Toyota',
                                'model' => 'Corolla',
                                'year' => 2018,
                                'mileage' => 65000,
                                'color' => 'white',
                                'transmission' => 'automatic',
                                'fuel_type' => 'gasoline',
                                'engine_size' => '1.6L',
                                'condition' => 'used',
                                'body_type' => 'sedan',
                                'doors' => 4,
                                'seats' => 5,
                                'features' => json_encode(['bluetooth', 'abs', 'airbags']),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                        break;

                    case 'job':
                        $exists = DB::table('job_listings')->where('listing_id', $listingId)->exists();
                        if (!$exists) {
                            DB::table('job_listings')->insert([
                                'listing_id' => $listingId,
                                'job_title' => 'Senior PHP Developer',
                                'company_name' => 'Wejha',
                                'job_type' => 'full_time',
                                'attendance_type' => 'hybrid',
                                'job_category' => 'programming',
                                'job_subcategory' => 'backend',
                                'gender_preference' => 'any',
                                'salary' => 2000,
                                'salary_period' => 'monthly',
                                'salary_currency' => 'USD',
                                'is_salary_negotiable' => true,
                                'experience_years_min' => 4,
                                'education_level' => 'bachelor',
                                'required_language' => 'English',
                                'company_size' => '11-50',
                                'benefits' => json_encode(['health_insurance', 'remote_days']),
                                'application_link' => 'https://example.com/apply',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                        break;

                    case 'bid':
                        $exists = DB::table('bid_listings')->where('listing_id', $listingId)->exists();
                        if (!$exists) {
                            DB::table('bid_listings')->insert([
                                'listing_id' => $listingId,
                                'bid_type' => 'tender',
                                'bid_code' => 'TND-'.strtoupper(Str::random(6)),
                                'main_category' => 'renovation',
                                'sector' => 'construction',
                                'contact_phone' => '+962-799-000000',
                                'contact_email' => 'bids@example.com',
                                'application_link' => 'https://example.com/bid',
                                'submission_start_date' => now()->toDateString(),
                                'submission_end_date' => now()->addDays(30)->toDateString(),
                                'is_facility_under_construction' => false,
                                'investment_amount_min' => 10000,
                                'investment_amount_max' => 50000,
                                'expected_return' => 12.5,
                                'return_period' => 'yearly',
                                'investment_term' => 12,
                                'risk_level' => 'medium',
                                'is_equity' => false,
                                'is_debt' => true,
                                'equity_percentage' => null,
                                'business_plan' => json_encode(['summary' => 'Renovate office']),
                                'financial_projections' => json_encode(['roi' => '12%']),
                                'documents' => json_encode([]),
                                'requirements' => json_encode(['licensed_contractor']),
                                'terms_and_conditions' => json_encode(['payment_terms' => '30% upfront']),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                        break;
                }
            }

            DB::commit();
            $this->command?->info('HomePageSeeder completed: listings, images, and sub tables inserted/ensured.');
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->command?->error('HomePageSeeder failed: '.$e->getMessage());
            throw $e;
        }
    }
} 