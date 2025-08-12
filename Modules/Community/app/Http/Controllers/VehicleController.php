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

class VehicleController extends Controller
{
    /**
     * Display a listing of vehicle listings.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // Get the vehicle category ID
        $vehicleCategory = DB::table('listing_categories')
            ->where('name', 'vehicle')
            ->first();
            
        if (!$vehicleCategory) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle category not found'
            ], 404);
        }
        
        $query = DB::table('listings')
            ->join('vehicle_listings', 'listings.id', '=', 'vehicle_listings.listing_id')
            ->leftJoin('listing_subcategories', 'listings.subcategory_id', '=', 'listing_subcategories.id')
            ->where('listings.category_id', $vehicleCategory->id)
            ->select(
                'listings.*', 
                'vehicle_listings.*',
                'listing_subcategories.name as vehicle_type_name',
                'listing_subcategories.display_name as vehicle_type_display_name'
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
        
        if ($request->has('min_year')) {
            $query->where('vehicle_listings.year', '>=', $request->min_year);
        }
        
        if ($request->has('max_year')) {
            $query->where('vehicle_listings.year', '<=', $request->max_year);
        }
        
        if ($request->has('make')) {
            $query->where('vehicle_listings.make', $request->make);
        }
        
        if ($request->has('model')) {
            $query->where('vehicle_listings.model', $request->model);
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
     * Show the form for creating a new vehicle listing.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        // Get vehicle category
        $vehicleCategory = DB::table('listing_categories')
            ->where('name', 'vehicle')
            ->first();
            
        if (!$vehicleCategory) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle category not found'
            ], 404);
        }
        
        // Get vehicle types (subcategories)
        $vehicleTypes = DB::table('listing_subcategories')
            ->where('category_id', $vehicleCategory->id)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();
        
        return response()->json([
            'success' => true,
            'category' => $vehicleCategory,
            'vehicle_types' => $vehicleTypes
        ]);
    }

    /**
     * Store a newly created vehicle listing in storage.
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
            // Vehicle specific validations
            'make' => 'required|string|max:255',
            'model' => 'required|string|max:255',
            'year' => 'required|integer|min:1900|max:2100',
            'mileage' => 'required|numeric',
            'color' => 'required|string|max:50',
            'transmission' => 'required|string|max:50',
            'fuel_type' => 'required|string|max:50',
            'engine_size' => 'required|string|max:50',
            'condition' => 'required|string|max:50',
            'body_type' => 'required|string|max:50',
            'doors' => 'required|integer|min:0|max:10',
            'seats' => 'required|integer|min:0|max:20',
            'vehicle_features' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        try {
            // Get the vehicle category ID
            $vehicleCategory = DB::table('listing_categories')
                ->where('name', 'vehicle')
                ->first();
                
            if (!$vehicleCategory) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vehicle category not found'
                ], 404);
            }
            
            DB::beginTransaction();
            
            // Create the base listing
            $postNumber = 'VH-' . Str::random(8);
            
            $listing = [
                'user_id' => Auth::id(),
                'title' => $request->title,
                'description' => $request->description,
                'price' => $request->price,
                'price_type' => $request->price_type,
                'currency' => $request->currency,
                'post_number' => $postNumber,
                'phone_number' => $request->phone_number,
                'category_id' => $vehicleCategory->id,
                'subcategory_id' => $request->subcategory_id,
                'listing_type' => 'vehicle', // Keep for backward compatibility
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
            
            // Get vehicle type from subcategory
            $subcategory = DB::table('listing_subcategories')
                ->where('id', $request->subcategory_id)
                ->first();
                
            // Create the vehicle specific listing
            $vehicleListing = [
                'listing_id' => $listingId,
                'vehicle_type' => $subcategory->name, // Use subcategory name as vehicle_type
                'make' => $request->make,
                'model' => $request->model,
                'year' => $request->year,
                'mileage' => $request->mileage,
                'color' => $request->color,
                'transmission' => $request->transmission,
                'fuel_type' => $request->fuel_type,
                'engine_size' => $request->engine_size,
                'condition' => $request->condition,
                'body_type' => $request->body_type,
                'doors' => $request->doors,
                'seats' => $request->seats,
                'features' => $request->vehicle_features ? json_encode($request->vehicle_features) : null,
                'created_at' => now(),
                'updated_at' => now()
            ];
            
            DB::table('vehicle_listings')->insert($vehicleListing);
            
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
                'message' => 'Vehicle listing created successfully',
                'data' => [
                    'listing_id' => $listingId,
                    'post_number' => $postNumber
                ]
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create vehicle listing',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified vehicle listing.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $listing = DB::table('listings')
            ->join('vehicle_listings', 'listings.id', '=', 'vehicle_listings.listing_id')
            ->leftJoin('listing_categories', 'listings.category_id', '=', 'listing_categories.id')
            ->leftJoin('listing_subcategories', 'listings.subcategory_id', '=', 'listing_subcategories.id')
            ->where('listings.id', $id)
            ->select(
                'listings.*', 
                'vehicle_listings.*',
                'listing_categories.name as category_name',
                'listing_categories.display_name as category_display_name',
                'listing_subcategories.name as subcategory_name',
                'listing_subcategories.display_name as subcategory_display_name'
            )
            ->first();
            
        if (!$listing) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle listing not found'
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
     * Search for vehicle listings based on criteria.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function search(Request $request)
    {
        // Get the vehicle category ID
        $vehicleCategory = DB::table('listing_categories')
            ->where('name', 'vehicle')
            ->first();
            
        if (!$vehicleCategory) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle category not found'
            ], 404);
        }
        
        $query = DB::table('listings')
            ->join('vehicle_listings', 'listings.id', '=', 'vehicle_listings.listing_id')
            ->where('listings.category_id', $vehicleCategory->id)
            ->select('listings.*', 'vehicle_listings.*');
            
        // Apply filters
        if ($request->has('subcategory_id')) {
            $query->where('listings.subcategory_id', $request->subcategory_id);
        } else if ($request->has('vehicle_type')) {
            // For backward compatibility
            $query->where('vehicle_listings.vehicle_type', $request->vehicle_type);
        }
        
        if ($request->has('min_price')) {
            $query->where('listings.price', '>=', $request->min_price);
        }
        
        if ($request->has('max_price')) {
            $query->where('listings.price', '<=', $request->max_price);
        }
        
        if ($request->has('min_year')) {
            $query->where('vehicle_listings.year', '>=', $request->min_year);
        }
        
        if ($request->has('max_year')) {
            $query->where('vehicle_listings.year', '<=', $request->max_year);
        }
        
        if ($request->has('make')) {
            $query->where('vehicle_listings.make', $request->make);
        }
        
        if ($request->has('model')) {
            $query->where('vehicle_listings.model', $request->model);
        }
        
        if ($request->has('transmission')) {
            $query->where('vehicle_listings.transmission', $request->transmission);
        }
        
        if ($request->has('fuel_type')) {
            $query->where('vehicle_listings.fuel_type', $request->fuel_type);
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