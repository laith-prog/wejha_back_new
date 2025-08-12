<?php

namespace Modules\Community\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Community\Models\ListingCategory;
use Modules\Community\Models\ListingSubcategory;

class ServiceController extends Controller
{
    /**
     * Display a listing of service listings.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // Get the service category ID
        $serviceCategory = DB::table('listing_categories')
            ->where('name', 'service')
            ->first();
            
        if (!$serviceCategory) {
            return response()->json([
                'success' => false,
                'message' => 'Service category not found'
            ], 404);
        }
        
        $query = DB::table('listings')
            ->join('service_listings', 'listings.id', '=', 'service_listings.listing_id')
            ->leftJoin('listing_subcategories', 'listings.subcategory_id', '=', 'listing_subcategories.id')
            ->where('listings.category_id', $serviceCategory->id)
            ->select(
                'listings.*', 
                'service_listings.*',
                'listing_subcategories.name as service_type_name',
                'listing_subcategories.display_name as service_type_display_name'
            );
        
        // Apply filters
        if ($request->has('subcategory_id')) {
            $query->where('listings.subcategory_id', $request->subcategory_id);
        }
        
        if ($request->has('min_price')) {
            $query->where('listings.price', '>=', $request->min_price);
        }
        
        if ($request->has('max_price')) {
            $query->where('listings.price', '<=', $request->max_price);
        }
        
        // Sort results
        $sortBy = $request->sort_by ?? 'created_at';
        $sortDirection = $request->sort_direction ?? 'desc';
        $query->orderBy("listings.$sortBy", $sortDirection);
        
        // Paginate results
        $perPage = $request->per_page ?? 10;
        $results = $query->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => $results->items(),
            'pagination' => [
                'total' => $results->total(),
                'per_page' => $results->perPage(),
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
            ]
        ]);
    }

    /**
     * Show the form for creating a new service listing.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        // Get service category
        $serviceCategory = DB::table('listing_categories')
            ->where('name', 'service')
            ->first();
            
        if (!$serviceCategory) {
            return response()->json([
                'success' => false,
                'message' => 'Service category not found'
            ], 404);
        }
        
        // Get service types (subcategories)
        $serviceTypes = DB::table('listing_subcategories')
            ->where('category_id', $serviceCategory->id)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();
        
        return response()->json([
            'success' => true,
            'category' => $serviceCategory,
            'service_types' => $serviceTypes
        ]);
    }

    /**
     * Store a newly created service listing in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Validate the request
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric',
            'price_type' => 'required|string',
            'currency' => 'required|string|max:10',
            'phone_number' => 'required|string|max:20',
            'purpose' => 'required|string',
            'subcategory_id' => 'required|exists:listing_subcategories,id',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'city' => 'required|string|max:255',
            'area' => 'required|string|max:255',
            // Service specific validations
            'service_type' => 'required|string|max:255',
            'availability' => 'nullable|array',
            'experience_years' => 'nullable|integer|min:0',
            'qualification' => 'nullable|string|max:255',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        try {
            // Get the service category ID
            $serviceCategory = DB::table('listing_categories')
                ->where('name', 'service')
                ->first();
                
            if (!$serviceCategory) {
                return response()->json([
                    'success' => false,
                    'message' => 'Service category not found'
                ], 404);
            }
            
            DB::beginTransaction();
            
            // Create the base listing
            $postNumber = 'SV-' . Str::random(8);
            
            $listing = [
                'user_id' => Auth::id(),
                'title' => $request->title,
                'description' => $request->description,
                'price' => $request->price,
                'price_type' => $request->price_type,
                'currency' => $request->currency,
                'post_number' => $postNumber,
                'phone_number' => $request->phone_number,
                'category_id' => $serviceCategory->id,
                'subcategory_id' => $request->subcategory_id,
                'listing_type' => 'service', // Keep for backward compatibility
                'purpose' => $request->purpose,
                'status' => 'active',
                'lat' => $request->lat,
                'lng' => $request->lng,
                'city' => $request->city,
                'area' => $request->area,
                'features' => $request->features ?? null,
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ];
            
            $listingId = DB::table('listings')->insertGetId($listing);
            
            // Get service type from subcategory
            $subcategory = DB::table('listing_subcategories')
                ->where('id', $request->subcategory_id)
                ->first();
                
            // Create the service specific listing
            $serviceListing = [
                'listing_id' => $listingId,
                'service_type' => $subcategory->name, // Use subcategory name as service_type
                'availability' => $request->availability ? json_encode($request->availability) : null,
                'experience_years' => $request->experience_years,
                'qualifications' => $request->qualifications,
                'service_area' => $request->service_area,
                'is_mobile' => $request->is_mobile ?? false,
                'created_at' => now(),
                'updated_at' => now()
            ];
            
            DB::table('service_listings')->insert($serviceListing);
            
            // Handle image uploads
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $image) {
                    $path = $image->store('listings/' . $listingId, 'public');
                    
                    DB::table('listing_images')->insert([
                        'listing_id' => $listingId,
                        'image_path' => $path,
                        'thumbnail_path' => $path, // You might want to create actual thumbnails
                        'is_primary' => $index === 0, // First image is primary
                        'display_order' => $index,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Service listing created successfully',
                'data' => [
                    'listing_id' => $listingId,
                    'post_number' => $postNumber
                ]
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create service listing',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified service listing.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $listing = DB::table('listings')
            ->join('service_listings', 'listings.id', '=', 'service_listings.listing_id')
            ->leftJoin('listing_categories', 'listings.category_id', '=', 'listing_categories.id')
            ->leftJoin('listing_subcategories', 'listings.subcategory_id', '=', 'listing_subcategories.id')
            ->where('listings.id', $id)
            ->select(
                'listings.*', 
                'service_listings.*',
                'listing_categories.name as category_name',
                'listing_categories.display_name as category_display_name',
                'listing_subcategories.name as subcategory_name',
                'listing_subcategories.display_name as subcategory_display_name'
            )
            ->first();
            
        if (!$listing) {
            return response()->json([
                'success' => false,
                'message' => 'Service listing not found'
            ], 404);
        }
        
        // Get listing images
        $images = DB::table('listing_images')
            ->where('listing_id', $id)
            ->orderBy('display_order')
            ->get();
            
        // Get service provider stats and reviews
        $providerStats = DB::table('service_provider_stats')
            ->where('user_id', $listing->user_id)
            ->first();
            
        $providerReviews = DB::table('service_provider_reviews')
            ->where('provider_id', $listing->user_id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
            
        // Increment view count
        DB::table('listings')
            ->where('id', $id)
            ->increment('views_count');
            
        return response()->json([
            'success' => true,
            'data' => [
                'listing' => $listing,
                'images' => $images,
                'provider_stats' => $providerStats,
                'provider_reviews' => $providerReviews
            ]
        ]);
    }

    /**
     * Search for service listings based on criteria.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function search(Request $request)
    {
        // Get the service category ID
        $serviceCategory = DB::table('listing_categories')
            ->where('name', 'service')
            ->first();
            
        if (!$serviceCategory) {
            return response()->json([
                'success' => false,
                'message' => 'Service category not found'
            ], 404);
        }
        
        $query = DB::table('listings')
            ->join('service_listings', 'listings.id', '=', 'service_listings.listing_id')
            ->where('listings.category_id', $serviceCategory->id)
            ->select('listings.*', 'service_listings.*');
            
        // Apply filters
        if ($request->has('subcategory_id')) {
            $query->where('listings.subcategory_id', $request->subcategory_id);
        } else if ($request->has('service_type')) {
            // For backward compatibility
            $query->where('service_listings.service_type', $request->service_type);
        }
        
        if ($request->has('min_price')) {
            $query->where('listings.price', '>=', $request->min_price);
        }
        
        if ($request->has('max_price')) {
            $query->where('listings.price', '<=', $request->max_price);
        }
        
        if ($request->has('min_experience')) {
            $query->where('service_listings.experience_years', '>=', $request->min_experience);
        }
        
        // Location-based filters
        if ($request->has('city')) {
            $query->where('listings.city', $request->city);
        }
        
        if ($request->has('area')) {
            $query->where('listings.area', $request->area);
        }
        
        // Radius search if lat, lng, and radius are provided
        if ($request->has('lat') && $request->has('lng') && $request->has('radius')) {
            $lat = $request->lat;
            $lng = $request->lng;
            $radius = $request->radius; // in kilometers
            
            // Haversine formula to calculate distance
            $query->selectRaw(
                '(6371 * acos(cos(radians(?)) * cos(radians(listings.lat)) * cos(radians(listings.lng) - radians(?)) + sin(radians(?)) * sin(radians(listings.lat)))) AS distance', 
                [$lat, $lng, $lat]
            )
            ->having('distance', '<=', $radius)
            ->orderBy('distance');
        }
        
        // Sort results
        $sortBy = $request->sort_by ?? 'created_at';
        $sortDirection = $request->sort_direction ?? 'desc';
        $query->orderBy("listings.$sortBy", $sortDirection);
        
        // Paginate results
        $perPage = $request->per_page ?? 10;
        $results = $query->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => $results->items(),
            'pagination' => [
                'total' => $results->total(),
                'per_page' => $results->perPage(),
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
            ]
        ]);
    }
} 