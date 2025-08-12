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
use Modules\Community\Services\ListingImageService;

class RealEstateController extends Controller
{
    /**
     * Display a listing of real estate listings.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // Get the real_estate category ID
        $realEstateCategory = DB::table('listing_categories')
            ->where('name', 'real_estate')
            ->first();
            
        if (!$realEstateCategory) {
            return response()->json([
                'success' => false,
                'message' => 'Real estate category not found'
            ], 404);
        }
        
        $query = DB::table('listings')
            ->join('real_estate_listings', 'listings.id', '=', 'real_estate_listings.listing_id')
            ->leftJoin('listing_subcategories', 'listings.subcategory_id', '=', 'listing_subcategories.id')
            ->where('listings.category_id', $realEstateCategory->id)
            ->select(
                'listings.*', 
                'real_estate_listings.*',
                'listing_subcategories.name as property_type_name',
                'listing_subcategories.display_name as property_type_display_name'
            );
        
        // Apply filters
        if ($request->has('subcategory_id')) {
            $query->where('listings.subcategory_id', $request->subcategory_id);
        }
        
        if ($request->has('offer_type')) {
            $query->where('real_estate_listings.offer_type', $request->offer_type);
        }
        
        if ($request->has('min_price')) {
            $query->where('listings.price', '>=', $request->min_price);
        }
        
        if ($request->has('max_price')) {
            $query->where('listings.price', '<=', $request->max_price);
        }
        
        if ($request->has('min_area')) {
            $query->where('real_estate_listings.property_area', '>=', $request->min_area);
        }
        
        if ($request->has('max_area')) {
            $query->where('real_estate_listings.property_area', '<=', $request->max_area);
        }
        
        if ($request->has('rooms')) {
            $query->where('real_estate_listings.room_number', $request->rooms);
        }
        
        if ($request->has('bathrooms')) {
            $query->where('real_estate_listings.bathrooms', $request->bathrooms);
        }
        
        if ($request->has('is_room_rental')) {
            $query->where('real_estate_listings.is_room_rental', $request->is_room_rental);
        }
        
        if ($request->has('facility_under_construction')) {
            $query->where('listings.facility_under_construction', $request->facility_under_construction);
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
     * Show the form for creating a new real estate listing.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        // Get real estate category
        $realEstateCategory = DB::table('listing_categories')
            ->where('name', 'real_estate')
            ->first();
            
        if (!$realEstateCategory) {
            return response()->json([
                'success' => false,
                'message' => 'Real estate category not found'
            ], 404);
        }
        
        // Get property types (subcategories)
        $propertyTypes = DB::table('listing_subcategories')
            ->where('category_id', $realEstateCategory->id)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();
        
        $offerTypes = [
            'rent', 'sell'
        ];
        
        return response()->json([
            'success' => true,
            'category' => $realEstateCategory,
            'property_types' => $propertyTypes,
            'offer_types' => $offerTypes
        ]);
    }

    /**
     * Store a newly created real estate listing in storage.
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
            'offer_type' => 'required|string|in:rent,sell',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'city' => 'required|string|max:255',
            'area' => 'required|string|max:255',
            'property_area' => 'required|numeric',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        try {
            // Get the real_estate category ID
            $realEstateCategory = DB::table('listing_categories')
                ->where('name', 'real_estate')
                ->first();
                
            if (!$realEstateCategory) {
                return response()->json([
                    'success' => false,
                    'message' => 'Real estate category not found'
                ], 404);
            }
            
            DB::beginTransaction();
            
            // Create the base listing
            $postNumber = 'RE-' . Str::random(8);
            
            $listing = [
                'user_id' => Auth::id(),
                'title' => $request->title,
                'description' => $request->description,
                'price' => $request->price,
                'price_type' => $request->price_type,
                'currency' => $request->currency,
                'post_number' => $postNumber,
                'phone_number' => $request->phone_number,
                'category_id' => $realEstateCategory->id,
                'subcategory_id' => $request->subcategory_id,
                'listing_type' => 'real_estate', // Keep for backward compatibility
                'purpose' => $request->purpose,
                'status' => 'active',
                'facility_under_construction' => $request->facility_under_construction ?? false,
                'expected_completion_date' => $request->expected_completion_date,
                'construction_progress_percent' => $request->construction_progress_percent,
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
            
            // Get property type from subcategory
            $subcategory = DB::table('listing_subcategories')
                ->where('id', $request->subcategory_id)
                ->first();
                
            // Create the real estate specific listing
            $realEstateListing = [
                'listing_id' => $listingId,
                'property_type' => $subcategory->name, // Use subcategory name as property_type
                'offer_type' => $request->offer_type,
                'room_number' => $request->room_number,
                'bathrooms' => $request->bathrooms,
                'property_area' => $request->property_area,
                'floors' => $request->floors,
                'floor_number' => $request->floor_number,
                'has_parking' => $request->has_parking ?? false,
                'has_garden' => $request->has_garden ?? false,
                'balcony' => $request->balcony ?? 0,
                'has_pool' => $request->has_pool ?? false,
                'has_elevator' => $request->has_elevator ?? false,
                'furnished' => $request->furnished ?? 'no',
                'year_built' => $request->year_built,
                'ownership_type' => $request->ownership_type,
                'legal_status' => $request->legal_status,
                'amenities' => $request->amenities ? json_encode($request->amenities) : null,
                'is_room_rental' => $request->is_room_rental ?? false,
                'room_area' => $request->room_area,
                'created_at' => now(),
                'updated_at' => now()
            ];
            
            DB::table('real_estate_listings')->insert($realEstateListing);
            
            // Handle image uploads
            if ($request->hasFile('images')) {
                $imageService = app(ListingImageService::class);
                $imageService->uploadListingImages($listingId, $request->file('images'));
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Real estate listing created successfully',
                'data' => [
                    'listing_id' => $listingId,
                    'post_number' => $postNumber
                ]
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create real estate listing',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified real estate listing.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $listing = DB::table('listings')
            ->join('real_estate_listings', 'listings.id', '=', 'real_estate_listings.listing_id')
            ->leftJoin('listing_categories', 'listings.category_id', '=', 'listing_categories.id')
            ->leftJoin('listing_subcategories', 'listings.subcategory_id', '=', 'listing_subcategories.id')
            ->where('listings.id', $id)
            ->select(
                'listings.*', 
                'real_estate_listings.*',
                'listing_categories.name as category_name',
                'listing_categories.display_name as category_display_name',
                'listing_subcategories.name as subcategory_name',
                'listing_subcategories.display_name as subcategory_display_name'
            )
            ->first();
            
        if (!$listing) {
            return response()->json([
                'success' => false,
                'message' => 'Real estate listing not found'
            ], 404);
        }
        
        // Get listing images
        $images = DB::table('listing_images')
            ->where('listing_id', $id)
            ->orderBy('display_order')
            ->get();
            
        // Increment view count
        DB::table('listings')
            ->where('id', $id)
            ->increment('views_count');
            
        return response()->json([
            'success' => true,
            'data' => [
                'listing' => $listing,
                'images' => $images
            ]
        ]);
    }

    /**
     * Remove the specified real estate listing from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $listing = DB::table('listings')
                ->where('id', $id)
                ->where('user_id', Auth::id()) // This will now be a UUID
                ->first();
                
            if (!$listing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Real estate listing not found or you do not have permission to delete it'
                ], 404);
            }
            
            DB::beginTransaction();
            
            // Delete images from storage
            $imageService = app(ListingImageService::class);
            $imageService->deleteListingImages($id);
            
            // Delete related records
            DB::table('real_estate_listings')->where('listing_id', $id)->delete();
            DB::table('listings')->where('id', $id)->delete();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Real estate listing deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete real estate listing',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Search for real estate listings based on criteria.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function search(Request $request)
    {
        // Get the real_estate category ID
        $realEstateCategory = DB::table('listing_categories')
            ->where('name', 'real_estate')
            ->first();
            
        if (!$realEstateCategory) {
            return response()->json([
                'success' => false,
                'message' => 'Real estate category not found'
            ], 404);
        }
        
        $query = DB::table('listings')
            ->join('real_estate_listings', 'listings.id', '=', 'real_estate_listings.listing_id')
            ->where('listings.category_id', $realEstateCategory->id)
            ->select('listings.*', 'real_estate_listings.*');
            
        // Apply filters
        if ($request->has('subcategory_id')) {
            $query->where('listings.subcategory_id', $request->subcategory_id);
        } else if ($request->has('property_type')) {
            // For backward compatibility
            $query->where('real_estate_listings.property_type', $request->property_type);
        }
        
        if ($request->has('offer_type')) {
            $query->where('real_estate_listings.offer_type', $request->offer_type);
        }
        
        if ($request->has('min_price')) {
            $query->where('listings.price', '>=', $request->min_price);
        }
        
        if ($request->has('max_price')) {
            $query->where('listings.price', '<=', $request->max_price);
        }
        
        if ($request->has('min_area')) {
            $query->where('real_estate_listings.property_area', '>=', $request->min_area);
        }
        
        if ($request->has('max_area')) {
            $query->where('real_estate_listings.property_area', '<=', $request->max_area);
        }
        
        if ($request->has('rooms')) {
            $query->where('real_estate_listings.room_number', $request->rooms);
        }
        
        if ($request->has('bathrooms')) {
            $query->where('real_estate_listings.bathrooms', $request->bathrooms);
        }
        
        if ($request->has('is_room_rental')) {
            $query->where('real_estate_listings.is_room_rental', $request->is_room_rental);
        }
        
        if ($request->has('facility_under_construction')) {
            $query->where('listings.facility_under_construction', $request->facility_under_construction);
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
    
    /**
     * Toggle favorite status for a real estate listing.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function toggleFavorite($id)
    {
        $userId = Auth::id(); // This will now be a UUID
        
        $favorite = DB::table('user_favorites')
            ->where('user_id', $userId)
            ->where('listing_id', $id)
            ->first();
            
        if ($favorite) {
            // Remove from favorites
            DB::table('user_favorites')
                ->where('user_id', $userId)
                ->where('listing_id', $id)
                ->delete();
                
            DB::table('listings')
                ->where('id', $id)
                ->decrement('favorites_count');
                
            return response()->json([
                'success' => true,
                'message' => 'Removed from favorites',
                'is_favorite' => false
            ]);
        } else {
            // Add to favorites
            DB::table('user_favorites')->insert([
                'user_id' => $userId,
                'listing_id' => $id,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            DB::table('listings')
                ->where('id', $id)
                ->increment('favorites_count');
                
            return response()->json([
                'success' => true,
                'message' => 'Added to favorites',
                'is_favorite' => true
            ]);
        }
    }
    
    /**
     * Report a real estate listing.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function report(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string',
            'details' => 'nullable|string'
        ]);
        
        $userId = Auth::id(); // This will now be a UUID
        
        // Check if listing exists
        $listing = DB::table('listings')
            ->where('id', $id)
            ->where('listing_type', 'real_estate')
            ->first();
            
        if (!$listing) {
            return response()->json([
                'success' => false,
                'message' => 'Real estate listing not found'
            ], 404);
        }
        
        // Create report
        DB::table('listing_reports')->insert([
            'user_id' => $userId,
            'listing_id' => $id,
            'reason' => $request->reason,
            'details' => $request->details,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        // Increment reports count
        DB::table('listings')
            ->where('id', $id)
            ->increment('reports_count');
            
        return response()->json([
            'success' => true,
            'message' => 'Listing reported successfully'
        ]);
    }
} 