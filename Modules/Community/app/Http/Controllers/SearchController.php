<?php

namespace Modules\Community\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SearchController extends Controller
{
    /**
     * Search listings with basic parameters
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        try {
            $query = DB::table('listings')
                ->where('status', 'active');
            
            // Apply basic search filters
            if ($request->has('keyword')) {
                $keyword = $request->keyword;
                $query->where(function($q) use ($keyword) {
                    $q->where('title', 'like', "%{$keyword}%")
                      ->orWhere('description', 'like', "%{$keyword}%");
                });
            }
            
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }
            
            if ($request->has('subcategory_id')) {
                $query->where('subcategory_id', $request->subcategory_id);
            }
            
            // Location-based filters
            if ($request->has('city')) {
                $query->where('city', $request->city);
            }
            
            if ($request->has('area')) {
                $query->where('area', $request->area);
            }
            
            // Radius search if lat, lng, and radius are provided
            if ($request->has('lat') && $request->has('lng') && $request->has('radius')) {
                $lat = $request->lat;
                $lng = $request->lng;
                $radius = $request->radius; // in kilometers
                
                $query->selectRaw(
                    '*, (6371 * acos(cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat)))) AS distance', 
                    [$lat, $lng, $lat]
                )
                ->having('distance', '<=', $radius)
                ->orderBy('distance');
            }
            
            // Price range filter
            if ($request->has('min_price')) {
                $query->where('price', '>=', $request->min_price);
            }
            
            if ($request->has('max_price')) {
                $query->where('price', '<=', $request->max_price);
            }
            
            // Sort results
            $sortBy = $request->sort_by ?? 'created_at';
            $sortDirection = $request->sort_direction ?? 'desc';
            $query->orderBy($sortBy, $sortDirection);
            
            // Paginate results
            $perPage = $request->per_page ?? 10;
            $page = $request->page ?? 1;
            $results = $query->paginate($perPage, ['*'], 'page', $page);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'listings' => $this->enrichListings($results->items()),
                    'pagination' => [
                        'total' => $results->total(),
                        'per_page' => $results->perPage(),
                        'current_page' => $results->currentPage(),
                        'last_page' => $results->lastPage(),
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search listings',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Advanced search with more specific filters
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function advancedSearch(Request $request)
    {
        try {
            // Start with basic query
            $query = DB::table('listings')
                ->where('status', 'active');
            
            // Apply all basic filters first
            if ($request->has('keyword')) {
                $keyword = $request->keyword;
                $query->where(function($q) use ($keyword) {
                    $q->where('title', 'like', "%{$keyword}%")
                      ->orWhere('description', 'like', "%{$keyword}%");
                });
            }
            
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
                
                // Join with specific listing type tables based on category
                $category = DB::table('listing_categories')
                    ->where('id', $request->category_id)
                    ->first();
                
                if ($category) {
                    switch ($category->name) {
                        case 'real_estate':
                            $query->join('real_estate_listings', 'listings.id', '=', 'real_estate_listings.listing_id');
                            break;
                        case 'vehicle':
                            $query->join('vehicle_listings', 'listings.id', '=', 'vehicle_listings.listing_id');
                            break;
                        case 'service':
                            $query->join('service_listings', 'listings.id', '=', 'service_listings.listing_id');
                            break;
                        case 'job':
                            $query->join('job_listings', 'listings.id', '=', 'job_listings.listing_id');
                            break;
                        case 'bid':
                            $query->join('bid_listings', 'listings.id', '=', 'bid_listings.listing_id');
                            break;
                    }
                }
            }
            
            if ($request->has('subcategory_id')) {
                $query->where('subcategory_id', $request->subcategory_id);
            }
            
            // Location-based filters
            if ($request->has('city')) {
                $query->where('city', $request->city);
            }
            
            if ($request->has('area')) {
                $query->where('area', $request->area);
            }
            
            // Radius search if lat, lng, and radius are provided
            if ($request->has('lat') && $request->has('lng') && $request->has('radius')) {
                $lat = $request->lat;
                $lng = $request->lng;
                $radius = $request->radius; // in kilometers
                
                $query->selectRaw(
                    '*, (6371 * acos(cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat)))) AS distance', 
                    [$lat, $lng, $lat]
                )
                ->having('distance', '<=', $radius)
                ->orderBy('distance');
            }
            
            // Price range filter
            if ($request->has('min_price')) {
                $query->where('price', '>=', $request->min_price);
            }
            
            if ($request->has('max_price')) {
                $query->where('price', '<=', $request->max_price);
            }
            
            // Purpose filter (sell, rent, etc.)
            if ($request->has('purpose')) {
                $query->where('purpose', $request->purpose);
            }
            
            // Category-specific filters
            if ($request->has('category_id')) {
                $category = DB::table('listing_categories')
                    ->where('id', $request->category_id)
                    ->first();
                
                if ($category) {
                    switch ($category->name) {
                        case 'real_estate':
                            // Real estate specific filters
                            if ($request->has('property_type')) {
                                $query->where('real_estate_listings.property_type', $request->property_type);
                            }
                            
                            if ($request->has('min_bedrooms')) {
                                $query->where('real_estate_listings.bedrooms', '>=', $request->min_bedrooms);
                            }
                            
                            if ($request->has('max_bedrooms')) {
                                $query->where('real_estate_listings.bedrooms', '<=', $request->max_bedrooms);
                            }
                            
                            if ($request->has('min_bathrooms')) {
                                $query->where('real_estate_listings.bathrooms', '>=', $request->min_bathrooms);
                            }
                            
                            if ($request->has('min_area')) {
                                $query->where('real_estate_listings.property_area', '>=', $request->min_area);
                            }
                            
                            if ($request->has('max_area')) {
                                $query->where('real_estate_listings.property_area', '<=', $request->max_area);
                            }
                            
                            if ($request->has('furnished')) {
                                $query->where('real_estate_listings.furnished', $request->furnished);
                            }
                            break;
                            
                        case 'vehicle':
                            // Vehicle specific filters
                            if ($request->has('vehicle_type')) {
                                $query->where('vehicle_listings.vehicle_type', $request->vehicle_type);
                            }
                            
                            if ($request->has('make')) {
                                $query->where('vehicle_listings.make', $request->make);
                            }
                            
                            if ($request->has('model')) {
                                $query->where('vehicle_listings.model', 'like', "%{$request->model}%");
                            }
                            
                            if ($request->has('min_year')) {
                                $query->where('vehicle_listings.year', '>=', $request->min_year);
                            }
                            
                            if ($request->has('max_year')) {
                                $query->where('vehicle_listings.year', '<=', $request->max_year);
                            }
                            
                            if ($request->has('transmission')) {
                                $query->where('vehicle_listings.transmission', $request->transmission);
                            }
                            
                            if ($request->has('fuel_type')) {
                                $query->where('vehicle_listings.fuel_type', $request->fuel_type);
                            }
                            
                            if ($request->has('min_mileage')) {
                                $query->where('vehicle_listings.mileage', '>=', $request->min_mileage);
                            }
                            
                            if ($request->has('max_mileage')) {
                                $query->where('vehicle_listings.mileage', '<=', $request->max_mileage);
                            }
                            break;
                            
                        case 'service':
                            // Service specific filters
                            if ($request->has('service_type')) {
                                $query->where('service_listings.service_type', $request->service_type);
                            }
                            
                            if ($request->has('min_experience')) {
                                $query->where('service_listings.experience_years', '>=', $request->min_experience);
                            }
                            
                            if ($request->has('is_mobile')) {
                                $query->where('service_listings.is_mobile', $request->is_mobile);
                            }
                            break;
                            
                        case 'job':
                            // Job specific filters
                            if ($request->has('job_type')) {
                                $query->where('job_listings.job_type', $request->job_type);
                            }
                            
                            if ($request->has('employment_type')) {
                                $query->where('job_listings.employment_type', $request->employment_type);
                            }
                            
                            if ($request->has('company_name')) {
                                $query->where('job_listings.company_name', 'like', "%{$request->company_name}%");
                            }
                            
                            if ($request->has('experience_level')) {
                                $query->where('job_listings.experience_level', $request->experience_level);
                            }
                            
                            if ($request->has('education_level')) {
                                $query->where('job_listings.education_level', $request->education_level);
                            }
                            break;
                            
                        case 'bid':
                            // Bid specific filters
                            if ($request->has('bid_type')) {
                                $query->where('bid_listings.bid_type', $request->bid_type);
                            }
                            
                            if ($request->has('sector')) {
                                $query->where('bid_listings.sector', $request->sector);
                            }
                            
                            if ($request->has('min_investment')) {
                                $query->where('bid_listings.investment_amount_min', '>=', $request->min_investment);
                            }
                            
                            if ($request->has('max_investment')) {
                                $query->where('bid_listings.investment_amount_max', '<=', $request->max_investment);
                            }
                            
                            if ($request->has('organization_name')) {
                                $query->where('bid_listings.organization_name', 'like', "%{$request->organization_name}%");
                            }
                            break;
                    }
                }
            }
            
            // Featured filter
            if ($request->has('is_featured') && $request->is_featured) {
                $query->where('is_featured', true);
            }
            
            // Sort results
            $sortBy = $request->sort_by ?? 'created_at';
            $sortDirection = $request->sort_direction ?? 'desc';
            $query->orderBy($sortBy, $sortDirection);
            
            // Paginate results
            $perPage = $request->per_page ?? 10;
            $page = $request->page ?? 1;
            $results = $query->paginate($perPage, ['*'], 'page', $page);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'listings' => $this->enrichListings($results->items()),
                    'pagination' => [
                        'total' => $results->total(),
                        'per_page' => $results->perPage(),
                        'current_page' => $results->currentPage(),
                        'last_page' => $results->lastPage(),
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to perform advanced search',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Advanced search specifically for real estate rentals
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function realEstateRentalSearch(Request $request)
    {
        try {
            // Start with basic query for real estate listings
            $query = DB::table('listings')
                ->join('real_estate_listings', 'listings.id', '=', 'real_estate_listings.listing_id')
                ->where('listings.status', 'active')
                ->where('listings.category_id', function($q) {
                    $q->select('id')
                      ->from('listing_categories')
                      ->where('name', 'real_estate')
                      ->first();
                })
                ->where('listings.purpose', 'rent');
            
            // Location filters
            if ($request->has('city')) {
                $query->where('listings.city', $request->city);
            }
            
            if ($request->has('area')) {
                $query->where('listings.area', $request->area);
            }
            
            // Map location filter
            if ($request->has('lat') && $request->has('lng') && $request->has('radius')) {
                $lat = $request->lat;
                $lng = $request->lng;
                $radius = $request->radius; // in kilometers
                
                $query->selectRaw(
                    '*, (6371 * acos(cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat)))) AS distance', 
                    [$lat, $lng, $lat]
                )
                ->having('distance', '<=', $radius)
                ->orderBy('distance');
            }
            
            // Property type filter (apartment, villa, etc.)
            if ($request->has('property_type')) {
                $query->where('real_estate_listings.property_type', $request->property_type);
            }
            
            // Price range filter
            if ($request->has('min_price')) {
                $query->where('listings.price', '>=', $request->min_price);
            }
            
            if ($request->has('max_price')) {
                $query->where('listings.price', '<=', $request->max_price);
            }
            
            // Area size filter
            if ($request->has('min_area')) {
                $query->where('real_estate_listings.property_area', '>=', $request->min_area);
            }
            
            if ($request->has('max_area')) {
                $query->where('real_estate_listings.property_area', '<=', $request->max_area);
            }
            
            // Bedrooms filter
            if ($request->has('bedrooms')) {
                // Handle special case for "4+" bedrooms
                if ($request->bedrooms === '4+') {
                    $query->where('real_estate_listings.bedrooms', '>=', 4);
                } else {
                    $query->where('real_estate_listings.bedrooms', $request->bedrooms);
                }
            }
            
            // Bathrooms filter
            if ($request->has('bathrooms')) {
                // Handle special case for "3+" bathrooms
                if ($request->bathrooms === '3+') {
                    $query->where('real_estate_listings.bathrooms', '>=', 3);
                } else {
                    $query->where('real_estate_listings.bathrooms', $request->bathrooms);
                }
            }
            
            // Furnished status
            if ($request->has('furnished')) {
                $query->where('real_estate_listings.furnished', $request->furnished);
            }
            // Under construction filter (for sales specifically)
            if ($request->has('under_construction')) {
                $query->where('listings.facility_under_construction', $request->under_construction == 'true');
            }
            
            // Amenities filters
            if ($request->has('amenities') && is_array($request->amenities)) {
                foreach ($request->amenities as $amenity) {
                    $query->whereRaw("JSON_CONTAINS(real_estate_listings.amenities, '\"$amenity\"')");
                }
            }
            
            // Sort results
            $sortBy = $request->sort_by ?? 'created_at';
            $sortDirection = $request->sort_direction ?? 'desc';
            $query->orderBy("listings.$sortBy", $sortDirection);
            
            // Paginate results
            $perPage = $request->per_page ?? 10;
            $page = $request->page ?? 1;
            $results = $query->paginate($perPage, ['*'], 'page', $page);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'listings' => $this->enrichRealEstateListings($results->items()),
                    'pagination' => [
                        'total' => $results->total(),
                        'per_page' => $results->perPage(),
                        'current_page' => $results->currentPage(),
                        'last_page' => $results->lastPage(),
                        'total_results' => $results->total()
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search real estate rentals',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Advanced search specifically for real estate sales
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function realEstateSaleSearch(Request $request)
    {
        try {
            // Start with basic query for real estate listings
            $query = DB::table('listings')
                ->join('real_estate_listings', 'listings.id', '=', 'real_estate_listings.listing_id')
                ->where('listings.status', 'active')
                ->where('listings.category_id', function($q) {
                    $q->select('id')
                      ->from('listing_categories')
                      ->where('name', 'real_estate')
                      ->first();
                })
                ->where('listings.purpose', 'sell');
            
            // Location filters
            if ($request->has('city')) {
                $query->where('listings.city', $request->city);
            }
            
            if ($request->has('area')) {
                $query->where('listings.area', $request->area);
            }
            
            // Map location filter
            if ($request->has('lat') && $request->has('lng') && $request->has('radius')) {
                $lat = $request->lat;
                $lng = $request->lng;
                $radius = $request->radius; // in kilometers
                
                $query->selectRaw(
                    '*, (6371 * acos(cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat)))) AS distance', 
                    [$lat, $lng, $lat]
                )
                ->having('distance', '<=', $radius)
                ->orderBy('distance');
            }
            
            // Property type filter (apartment, villa, etc.)
            if ($request->has('property_type')) {
                $query->where('real_estate_listings.property_type', $request->property_type);
            }
            
            // Price range filter
            if ($request->has('min_price')) {
                $query->where('listings.price', '>=', $request->min_price);
            }
            
            if ($request->has('max_price')) {
                $query->where('listings.price', '<=', $request->max_price);
            }
            
            // Area size filter
            if ($request->has('min_area')) {
                $query->where('real_estate_listings.property_area', '>=', $request->min_area);
            }
            
            if ($request->has('max_area')) {
                $query->where('real_estate_listings.property_area', '<=', $request->max_area);
            }
            
            // Bedrooms filter
            if ($request->has('bedrooms')) {
                // Handle special case for "4+" bedrooms
                if ($request->bedrooms === '4+') {
                    $query->where('real_estate_listings.bedrooms', '>=', 4);
                } else {
                    $query->where('real_estate_listings.bedrooms', $request->bedrooms);
                }
            }
            
            // Bathrooms filter
            if ($request->has('bathrooms')) {
                // Handle special case for "3+" bathrooms
                if ($request->bathrooms === '3+') {
                    $query->where('real_estate_listings.bathrooms', '>=', 3);
                } else {
                    $query->where('real_estate_listings.bathrooms', $request->bathrooms);
                }
            }
            
            // Furnished status
            if ($request->has('furnished')) {
                $query->where('real_estate_listings.furnished', $request->furnished);
            }
            // Under construction filter (for sales specifically)
            if ($request->has('under_construction')) {
                $query->where('listings.facility_under_construction', $request->under_construction == 'true');
            }
            
            // Amenities filters
            if ($request->has('amenities') && is_array($request->amenities)) {
                foreach ($request->amenities as $amenity) {
                    $query->whereRaw("JSON_CONTAINS(real_estate_listings.amenities, '\"$amenity\"')");
                }
            }
            
            // Sort results
            $sortBy = $request->sort_by ?? 'created_at';
            $sortDirection = $request->sort_direction ?? 'desc';
            $query->orderBy("listings.$sortBy", $sortDirection);
            
            // Paginate results
            $perPage = $request->per_page ?? 10;
            $page = $request->page ?? 1;
            $results = $query->paginate($perPage, ['*'], 'page', $page);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'listings' => $this->enrichRealEstateListings($results->items()),
                    'pagination' => [
                        'total' => $results->total(),
                        'per_page' => $results->perPage(),
                        'current_page' => $results->currentPage(),
                        'last_page' => $results->lastPage(),
                        'total_results' => $results->total()
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search real estate sales',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get recent search history for the authenticated user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRecentSearches(Request $request)
    {
        try {
            if (!Auth::check()) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }
            
            $userId = Auth::id();
            $limit = $request->limit ?? 5;
            
            $recentSearches = DB::table('user_search_history')
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();
                
            return response()->json([
                'success' => true,
                'data' => $recentSearches
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve recent searches',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Save a search query to user's history
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveSearch(Request $request)
    {
        try {
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            $request->validate([
                'search_query' => 'required|string|max:255',
                'search_params' => 'nullable|json',
            ]);
            
            $userId = Auth::id();
            
            // Check if this search already exists
            $existingSearch = DB::table('user_search_history')
                ->where('user_id', $userId)
                ->where('search_query', $request->search_query)
                ->first();
                
            if ($existingSearch) {
                // Update the timestamp
                DB::table('user_search_history')
                    ->where('id', $existingSearch->id)
                    ->update([
                        'updated_at' => now()
                    ]);
                    
                return response()->json([
                    'success' => true,
                    'message' => 'Search history updated',
                    'data' => $existingSearch->id
                ]);
            }
            
            // Create new search history entry
            $searchId = DB::table('user_search_history')->insertGetId([
                'user_id' => $userId,
                'search_query' => $request->search_query,
                'search_params' => $request->search_params,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Search saved to history',
                'data' => $searchId
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save search',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete a search from user's history
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteSearch(Request $request)
    {
        try {
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            $request->validate([
                'search_id' => 'required|integer',
            ]);
            
            $userId = Auth::id();
            
            // Delete the search if it belongs to the user
            $deleted = DB::table('user_search_history')
                ->where('id', $request->search_id)
                ->where('user_id', $userId)
                ->delete();
                
            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Search not found or not authorized to delete'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Search deleted from history'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete search',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get popular search terms
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPopularSearches(Request $request)
    {
        try {
            $limit = $request->limit ?? 10;
            
            $popularSearches = DB::table('user_search_history')
                ->select('search_query', DB::raw('COUNT(*) as search_count'))
                ->groupBy('search_query')
                ->orderBy('search_count', 'desc')
                ->limit($limit)
                ->get();
                
            return response()->json([
                'success' => true,
                'data' => $popularSearches
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve popular searches',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get filters for advanced search based on category
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSearchFilters(Request $request)
    {
        try {
            $request->validate([
                'category_id' => 'required|exists:listing_categories,id',
            ]);
            
            $category = DB::table('listing_categories')
                ->where('id', $request->category_id)
                ->first();
                
            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Category not found'
                ], 404);
            }
            
            $subcategories = DB::table('listing_subcategories')
                ->where('category_id', $request->category_id)
                ->where('is_active', true)
                ->orderBy('display_order')
                ->get();
                
            $filters = [
                'subcategories' => $subcategories,
                'purposes' => ['sell', 'rent', 'lease', 'exchange'],
                'cities' => $this->getAvailableCities($request->category_id),
                'price_range' => $this->getPriceRange($request->category_id),
            ];
            
            // Add category-specific filters
            switch ($category->name) {
                case 'real_estate':
                    $filters['property_types'] = ['apartment', 'house', 'villa', 'land', 'commercial', 'office', 'warehouse'];
                    $filters['bedroom_range'] = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
                    $filters['bathroom_range'] = [1, 2, 3, 4, 5, 6];
                    $filters['furnished_options'] = ['yes', 'no', 'partially'];
                    break;
                    
                case 'vehicle':
                    $filters['vehicle_types'] = ['car', 'truck', 'motorcycle', 'boat', 'rv', 'other'];
                    $filters['makes'] = $this->getVehicleMakes();
                    $filters['transmissions'] = ['automatic', 'manual', 'semi-automatic'];
                    $filters['fuel_types'] = ['gasoline', 'diesel', 'electric', 'hybrid', 'other'];
                    $filters['year_range'] = ['min' => 1950, 'max' => date('Y') + 1];
                    break;
                    
                case 'service':
                    $filters['service_types'] = $this->getServiceTypes();
                    break;
                    
                case 'job':
                    $filters['job_types'] = ['full-time', 'part-time', 'contract', 'temporary', 'internship', 'remote'];
                    $filters['employment_types'] = ['employee', 'freelance', 'internship'];
                    $filters['experience_levels'] = ['entry', 'mid-level', 'senior', 'executive'];
                    $filters['education_levels'] = ['high school', 'bachelor', 'master', 'phd', 'none'];
                    break;
                    
                case 'bid':
                    $filters['bid_types'] = ['investment', 'partnership', 'tender', 'rfp', 'other'];
                    $filters['sectors'] = ['technology', 'healthcare', 'education', 'construction', 'retail', 'manufacturing', 'other'];
                    break;
            }
            
            return response()->json([
                'success' => true,
                'data' => $filters
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve search filters',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get real estate filter options
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRealEstateFilters(Request $request)
    {
        try {
            // Get real estate category
            $category = DB::table('listing_categories')
                ->where('name', 'real_estate')
                ->first();
                
            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Real estate category not found'
                ], 404);
            }
            
            // Get property types
            $propertyTypes = [
                ['id' => 'apartment', 'name' => 'عقارات للإيجار', 'icon' => 'home'],
                ['id' => 'villa', 'name' => 'عقارات للبيع', 'icon' => 'home-sale'],
                ['id' => 'office', 'name' => 'مكاتب', 'icon' => 'office'],
                ['id' => 'commercial', 'name' => 'المركبات', 'icon' => 'car'],
                ['id' => 'land', 'name' => 'الخدمات', 'icon' => 'services'],
                ['id' => 'warehouse', 'name' => 'مناقصة قيد التسليم', 'icon' => 'tender'],
                ['id' => 'other', 'name' => 'العقارات للإيجارة اليومي', 'icon' => 'daily-rental'],
                ['id' => 'job', 'name' => 'فرص عمل', 'icon' => 'job']
            ];
            
            // Get available cities
            $cities = $this->getAvailableCities($category->id);
            
            // Get price range for rentals
            $priceRange = $this->getRentalPriceRange();
            
            // Get area size range
            $areaRange = $this->getAreaSizeRange();
            
            // Bedroom options
            $bedroomOptions = [
                ['id' => '1', 'name' => '1'],
                ['id' => '2', 'name' => '2'],
                ['id' => '3', 'name' => '3'],
                ['id' => '4+', 'name' => '4+']
            ];
            
            // Bathroom options
            $bathroomOptions = [
                ['id' => '1', 'name' => '1'],
                ['id' => '2', 'name' => '2'],
                ['id' => '3+', 'name' => '3+']
            ];
            
            // Furnished options
            $furnishedOptions = [
                ['id' => 'yes', 'name' => 'مفروش'],
                ['id' => 'no', 'name' => 'غير مفروش'],
                ['id' => 'partially', 'name' => 'مفروش جزئيًا']
            ];
            
            // Amenities
            $amenities = [
                ['id' => 'balcony', 'name' => 'شرفة'],
                ['id' => 'pool', 'name' => 'مسبح'],
                ['id' => 'gym', 'name' => 'صالة رياضية'],
                ['id' => 'parking', 'name' => 'موقف سيارات'],
                ['id' => 'security', 'name' => 'أمن'],
                ['id' => 'elevator', 'name' => 'مصعد'],
                ['id' => 'central_ac', 'name' => 'تكييف مركزي'],
                ['id' => 'garden', 'name' => 'حديقة']
            ];
            
            return response()->json([
                'success' => true,
                'data' => [
                    'property_types' => $propertyTypes,
                    'cities' => $cities,
                    'price_range' => $priceRange,
                    'area_range' => $areaRange,
                    'bedroom_options' => $bedroomOptions,
                    'bathroom_options' => $bathroomOptions,
                    'furnished_options' => $furnishedOptions,
                    'amenities' => $amenities
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve real estate filters',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get real estate sale filter options
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRealEstateSaleFilters(Request $request)
    {
        try {
            // Get real estate category
            $category = DB::table('listing_categories')
                ->where('name', 'real_estate')
                ->first();
                
            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Real estate category not found'
                ], 404);
            }
            
            // Get property types
            $propertyTypes = [
                ['id' => 'apartment', 'name' => 'شقة', 'icon' => 'apartment'],
                ['id' => 'villa', 'name' => 'فيلا', 'icon' => 'villa'],
                ['id' => 'house', 'name' => 'بيت', 'icon' => 'house'],
                ['id' => 'land', 'name' => 'أرض', 'icon' => 'land'],
                ['id' => 'commercial', 'name' => 'تجاري', 'icon' => 'commercial'],
                ['id' => 'office', 'name' => 'مكتب', 'icon' => 'office'],
                ['id' => 'building', 'name' => 'مبنى', 'icon' => 'building'],
                ['id' => 'warehouse', 'name' => 'مستودع', 'icon' => 'warehouse']
            ];
            
            // Get available cities
            $cities = $this->getAvailableCities($category->id);
            
            // Get price range for sales
            $priceRange = $this->getSalePriceRange();
            
            // Get area size range
            $areaRange = $this->getAreaSizeRange();
            
            // Bedroom options
            $bedroomOptions = [
                ['id' => '1', 'name' => '1'],
                ['id' => '2', 'name' => '2'],
                ['id' => '3', 'name' => '3'],
                ['id' => '4+', 'name' => '4+']
            ];
            
            // Bathroom options
            $bathroomOptions = [
                ['id' => '1', 'name' => '1'],
                ['id' => '2', 'name' => '2'],
                ['id' => '3+', 'name' => '3+']
            ];
            
            // Furnished options
            $furnishedOptions = [
                ['id' => 'yes', 'name' => 'مفروش'],
                ['id' => 'no', 'name' => 'غير مفروش'],
                ['id' => 'partially', 'name' => 'مفروش جزئيًا']
            ];
            $propertyAgeOptions = [
                ['id' => 'new', 'name' => 'جديد'],
                ['id' => '0-5', 'name' => '0-5 سنوات'],
                ['id' => '5-10', 'name' => '5-10 سنوات'],
                ['id' => '10+', 'name' => 'أكثر من 10 سنوات']
            ];
            
            // Construction status options
            $constructionOptions = [
                ['id' => 'true', 'name' => 'قيد الإنشاء'],
                ['id' => 'false', 'name' => 'جاهز']
            ];
            
            // Amenities
            $amenities = [
                ['id' => 'balcony', 'name' => 'شرفة'],
                ['id' => 'pool', 'name' => 'مسبح'],
                ['id' => 'gym', 'name' => 'صالة رياضية'],
                ['id' => 'parking', 'name' => 'موقف سيارات'],
                ['id' => 'security', 'name' => 'أمن'],
                ['id' => 'elevator', 'name' => 'مصعد'],
                ['id' => 'central_ac', 'name' => 'تكييف مركزي'],
                ['id' => 'garden', 'name' => 'حديقة'],
                ['id' => 'maid_room', 'name' => 'غرفة خادمة'],
                ['id' => 'storage', 'name' => 'مخزن']
            ];
            
            return response()->json([
                'success' => true,
                'data' => [
                    'property_types' => $propertyTypes,
                    'cities' => $cities,
                    'price_range' => $priceRange,
                    'area_range' => $areaRange,
                    'bedroom_options' => $bedroomOptions,
                    'bathroom_options' => $bathroomOptions,
                    'furnished_options' => $furnishedOptions,
                    'construction_options' => $constructionOptions,
                    'amenities' => $amenities
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve real estate sale filters',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get available cities for a category
     *
     * @param int $categoryId
     * @return array
     */
    private function getAvailableCities($categoryId)
    {
        return DB::table('listings')
            ->where('category_id', $categoryId)
            ->where('status', 'active')
            ->select('city')
            ->distinct()
            ->pluck('city')
            ->toArray();
    }
    
    /**
     * Get price range for a category
     *
     * @param int $categoryId
     * @return array
     */
    private function getPriceRange($categoryId)
    {
        $min = DB::table('listings')
            ->where('category_id', $categoryId)
            ->where('status', 'active')
            ->min('price') ?? 0;
            
        $max = DB::table('listings')
            ->where('category_id', $categoryId)
            ->where('status', 'active')
            ->max('price') ?? 1000000;
            
        return [
            'min' => $min,
            'max' => $max
        ];
    }
    
    /**
     * Get vehicle makes
     *
     * @return array
     */
    private function getVehicleMakes()
    {
        return DB::table('vehicle_listings')
            ->join('listings', 'vehicle_listings.listing_id', '=', 'listings.id')
            ->where('listings.status', 'active')
            ->select('make')
            ->distinct()
            ->pluck('make')
            ->toArray();
    }
    
    /**
     * Get service types
     *
     * @return array
     */
    private function getServiceTypes()
    {
        return DB::table('service_listings')
            ->join('listings', 'service_listings.listing_id', '=', 'listings.id')
            ->where('listings.status', 'active')
            ->select('service_type')
            ->distinct()
            ->pluck('service_type')
            ->toArray();
    }

    /**
     * Get rental price range
     *
     * @return array
     */
    private function getRentalPriceRange()
    {
        $min = DB::table('listings')
            ->join('real_estate_listings', 'listings.id', '=', 'real_estate_listings.listing_id')
            ->where('listings.purpose', 'rent')
            ->where('listings.status', 'active')
            ->min('price') ?? 0;
            
        $max = DB::table('listings')
            ->join('real_estate_listings', 'listings.id', '=', 'real_estate_listings.listing_id')
            ->where('listings.purpose', 'rent')
            ->where('listings.status', 'active')
            ->max('price') ?? 10000;
            
        return [
            'min' => $min,
            'max' => $max
        ];
    }

    /**
     * Get area size range
     *
     * @return array
     */
    private function getAreaSizeRange()
    {
        $min = DB::table('real_estate_listings')
            ->join('listings', 'real_estate_listings.listing_id', '=', 'listings.id')
            ->where('listings.status', 'active')
            ->min('property_area') ?? 0;
            
        $max = DB::table('real_estate_listings')
            ->join('listings', 'real_estate_listings.listing_id', '=', 'listings.id')
            ->where('listings.status', 'active')
            ->max('property_area') ?? 1000;
            
        return [
            'min' => $min,
            'max' => $max
        ];
    }

    /**
     * Get sale price range
     *
     * @return array
     */
    private function getSalePriceRange()
    {
        $min = DB::table('listings')
            ->join('real_estate_listings', 'listings.id', '=', 'real_estate_listings.listing_id')
            ->where('listings.purpose', 'sell')
            ->where('listings.status', 'active')
            ->min('price') ?? 0;
            
        $max = DB::table('listings')
            ->join('real_estate_listings', 'listings.id', '=', 'real_estate_listings.listing_id')
            ->where('listings.purpose', 'sell')
            ->where('listings.status', 'active')
            ->max('price') ?? 1000000;
            
        return [
            'min' => $min,
            'max' => $max
        ];
    }
    
    /**
     * Enrich listings with additional data
     *
     * @param array $listings
     * @return array
     */
    private function enrichListings($listings)
    {
        $enrichedListings = [];
        
        foreach ($listings as $listing) {
            // Get primary image
            $primaryImage = DB::table('listing_images')
                ->where('listing_id', $listing->id)
                ->where('is_primary', true)
                ->first();
                
            // Get category and subcategory info
            $category = DB::table('listing_categories')
                ->where('id', $listing->category_id)
                ->first();
                
            $subcategory = DB::table('listing_subcategories')
                ->where('id', $listing->subcategory_id)
                ->first();
                
            // Add enriched data
            $enrichedListing = (array) $listing;
            $enrichedListing['primary_image'] = $primaryImage ? $primaryImage->image_path : null;
            $enrichedListing['category_name'] = $category ? $category->name : null;
            $enrichedListing['category_display_name'] = $category ? $category->display_name : null;
            $enrichedListing['subcategory_name'] = $subcategory ? $subcategory->name : null;
            $enrichedListing['subcategory_display_name'] = $subcategory ? $subcategory->display_name : null;
            
            $enrichedListings[] = $enrichedListing;
        }
        
        return $enrichedListings;
    }

    /**
     * Enrich real estate listings with additional data
     *
     * @param array $listings
     * @return array
     */
    private function enrichRealEstateListings($listings)
    {
        $enrichedListings = [];
        
        foreach ($listings as $listing) {
            // Get images (up to 5)
            $images = DB::table('listing_images')
                ->where('listing_id', $listing->id)
                ->orderBy('is_primary', 'desc')
                ->orderBy('display_order')
                ->limit(5)
                ->get()
                ->pluck('image_path')
                ->toArray();
                
            // Get category and subcategory info
            $category = DB::table('listing_categories')
                ->where('id', $listing->category_id)
                ->first();
                
            $subcategory = DB::table('listing_subcategories')
                ->where('id', $listing->subcategory_id)
                ->first();
                
            // Parse amenities from JSON
            $amenities = [];
            if (isset($listing->amenities) && !empty($listing->amenities)) {
                $amenities = json_decode($listing->amenities, true);
            }
            
            // Add enriched data
            $enrichedListing = (array) $listing;
            $enrichedListing['images'] = $images;
            $enrichedListing['primary_image'] = !empty($images) ? $images[0] : null;
            $enrichedListing['category_name'] = $category ? $category->name : null;
            $enrichedListing['category_display_name'] = $category ? $category->display_name : null;
            $enrichedListing['subcategory_name'] = $subcategory ? $subcategory->name : null;
            $enrichedListing['subcategory_display_name'] = $subcategory ? $subcategory->display_name : null;
            $enrichedListing['amenities_list'] = $amenities;
            
            $enrichedListings[] = $enrichedListing;
        }
        
        return $enrichedListings;
    }

    /**
     * Advanced search specifically for room rentals
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function roomRentalSearch(Request $request)
    {
        try {
            // Start with basic query for real estate listings that are rooms
            $query = DB::table('listings')
                ->join('real_estate_listings', 'listings.id', '=', 'real_estate_listings.listing_id')
                ->where('listings.status', 'active')
                ->where('listings.category_id', function($q) {
                    $q->select('id')
                      ->from('listing_categories')
                      ->where('name', 'real_estate')
                      ->first();
                })
                ->where('listings.purpose', 'rent')
                ->where('real_estate_listings.property_type', 'room');
            
            // Location filters
            if ($request->has('city')) {
                $query->where('listings.city', $request->city);
            }
            
            if ($request->has('area')) {
                $query->where('listings.area', $request->area);
            }
            
            // Map location filter
            if ($request->has('lat') && $request->has('lng') && $request->has('radius')) {
                $lat = $request->lat;
                $lng = $request->lng;
                $radius = $request->radius; // in kilometers
                
                $query->selectRaw(
                    '*, (6371 * acos(cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat)))) AS distance', 
                    [$lat, $lng, $lat]
                )
                ->having('distance', '<=', $radius)
                ->orderBy('distance');
            }
            
            // Price range filter
            if ($request->has('min_price')) {
                $query->where('listings.price', '>=', $request->min_price);
            }
            
            if ($request->has('max_price')) {
                $query->where('listings.price', '<=', $request->max_price);
            }
            
            // Room size filter
            if ($request->has('min_area')) {
                $query->where('real_estate_listings.property_area', '>=', $request->min_area);
            }
            
            if ($request->has('max_area')) {
                $query->where('real_estate_listings.property_area', '<=', $request->max_area);
            }
            
            // Bathroom type filter (private, shared)
            if ($request->has('bathroom_type')) {
                $query->where('real_estate_listings.bathroom_type', $request->bathroom_type);
            }
            
            // Room furnishing status
            if ($request->has('furnished')) {
                $query->where('real_estate_listings.furnished', $request->furnished);
            }
            
            // Gender preference
            if ($request->has('gender_preference')) {
                $query->where('real_estate_listings.gender_preference', $request->gender_preference);
            }
            
            // Utilities included
            if ($request->has('utilities_included') && $request->utilities_included) {
                $query->where('real_estate_listings.utilities_included', true);
            }
            
            // Internet included
            if ($request->has('internet_included') && $request->internet_included) {
                $query->where('real_estate_listings.internet_included', true);
            }
            
            // Amenities filters
            if ($request->has('amenities') && is_array($request->amenities)) {
                foreach ($request->amenities as $amenity) {
                    $query->whereRaw("JSON_CONTAINS(real_estate_listings.amenities, '\"$amenity\"')");
                }
            }
            
            // Sort results
            $sortBy = $request->sort_by ?? 'created_at';
            $sortDirection = $request->sort_direction ?? 'desc';
            $query->orderBy("listings.$sortBy", $sortDirection);
            
            // Paginate results
            $perPage = $request->per_page ?? 10;
            $page = $request->page ?? 1;
            $results = $query->paginate($perPage, ['*'], 'page', $page);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'listings' => $this->enrichRoomListings($results->items()),
                    'pagination' => [
                        'total' => $results->total(),
                        'per_page' => $results->perPage(),
                        'current_page' => $results->currentPage(),
                        'last_page' => $results->lastPage(),
                        'total_results' => $results->total()
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search room rentals',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get room rental filter options
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRoomRentalFilters(Request $request)
    {
        try {
            // Get real estate category
            $category = DB::table('listing_categories')
                ->where('name', 'real_estate')
                ->first();
                
            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Real estate category not found'
                ], 404);
            }
            
            // Get available cities with rooms
            $cities = DB::table('listings')
                ->join('real_estate_listings', 'listings.id', '=', 'real_estate_listings.listing_id')
                ->where('listings.category_id', $category->id)
                ->where('listings.purpose', 'rent')
                ->where('real_estate_listings.property_type', 'room')
                ->where('listings.status', 'active')
                ->select('city')
                ->distinct()
                ->pluck('city')
                ->toArray();
            
            // Get price range for room rentals
            $priceRange = $this->getRoomRentalPriceRange();
            
            // Get area size range for rooms
            $areaRange = $this->getRoomSizeRange();
            
            // Bathroom type options
            $bathroomTypeOptions = [
                ['id' => 'private', 'name' => 'حمام خاص'],
                ['id' => 'shared', 'name' => 'حمام مشترك']
            ];
            
            // Furnished options
            $furnishedOptions = [
                ['id' => 'yes', 'name' => 'مفروش'],
                ['id' => 'no', 'name' => 'غير مفروش'],
                ['id' => 'partially', 'name' => 'مفروش جزئيًا']
            ];
            
            // Gender preference options
            $genderPreferenceOptions = [
                ['id' => 'male', 'name' => 'ذكور فقط'],
                ['id' => 'female', 'name' => 'إناث فقط'],
                ['id' => 'any', 'name' => 'الجميع']
            ];
            
            // Utilities options
            $utilitiesOptions = [
                ['id' => 'included', 'name' => 'الخدمات مشمولة'],
                ['id' => 'not_included', 'name' => 'الخدمات غير مشمولة']
            ];
            
            // Internet options
            $internetOptions = [
                ['id' => 'included', 'name' => 'الإنترنت مشمول'],
                ['id' => 'not_included', 'name' => 'الإنترنت غير مشمول']
            ];
            
            // Room amenities
            $amenities = [
                ['id' => 'air_conditioning', 'name' => 'تكييف'],
                ['id' => 'balcony', 'name' => 'شرفة'],
                ['id' => 'private_entrance', 'name' => 'مدخل خاص'],
                ['id' => 'parking', 'name' => 'موقف سيارات'],
                ['id' => 'kitchen_access', 'name' => 'وصول للمطبخ'],
                ['id' => 'washing_machine', 'name' => 'غسالة'],
                ['id' => 'tv', 'name' => 'تلفزيون'],
                ['id' => 'wifi', 'name' => 'واي فاي'],
                ['id' => 'closet', 'name' => 'خزانة ملابس'],
                ['id' => 'desk', 'name' => 'مكتب']
            ];
            
            return response()->json([
                'success' => true,
                'data' => [
                    'cities' => $cities,
                    'price_range' => $priceRange,
                    'area_range' => $areaRange,
                    'bathroom_type_options' => $bathroomTypeOptions,
                    'furnished_options' => $furnishedOptions,
                    'gender_preference_options' => $genderPreferenceOptions,
                    'utilities_options' => $utilitiesOptions,
                    'internet_options' => $internetOptions,
                    'amenities' => $amenities
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve room rental filters',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get room rental price range
     *
     * @return array
     */
    private function getRoomRentalPriceRange()
    {
        $min = DB::table('listings')
            ->join('real_estate_listings', 'listings.id', '=', 'real_estate_listings.listing_id')
            ->where('listings.purpose', 'rent')
            ->where('real_estate_listings.property_type', 'room')
            ->where('listings.status', 'active')
            ->min('price') ?? 0;
            
        $max = DB::table('listings')
            ->join('real_estate_listings', 'listings.id', '=', 'real_estate_listings.listing_id')
            ->where('listings.purpose', 'rent')
            ->where('real_estate_listings.property_type', 'room')
            ->where('listings.status', 'active')
            ->max('price') ?? 5000;
            
        return [
            'min' => $min,
            'max' => $max
        ];
    }

    /**
     * Get room size range
     *
     * @return array
     */
    private function getRoomSizeRange()
    {
        $min = DB::table('real_estate_listings')
            ->join('listings', 'real_estate_listings.listing_id', '=', 'listings.id')
            ->where('listings.purpose', 'rent')
            ->where('real_estate_listings.property_type', 'room')
            ->where('listings.status', 'active')
            ->min('property_area') ?? 0;
            
        $max = DB::table('real_estate_listings')
            ->join('listings', 'real_estate_listings.listing_id', '=', 'listings.id')
            ->where('listings.purpose', 'rent')
            ->where('real_estate_listings.property_type', 'room')
            ->where('listings.status', 'active')
            ->max('property_area') ?? 50;
            
        return [
            'min' => $min,
            'max' => $max
        ];
    }

    /**
     * Enrich room listings with additional data
     *
     * @param array $listings
     * @return array
     */
    private function enrichRoomListings($listings)
    {
        $enrichedListings = [];
        
        foreach ($listings as $listing) {
            // Get images (up to 5)
            $images = DB::table('listing_images')
                ->where('listing_id', $listing->id)
                ->orderBy('is_primary', 'desc')
                ->orderBy('display_order')
                ->limit(5)
                ->get()
                ->pluck('image_path')
                ->toArray();
            
            // Get category and subcategory info
            $category = DB::table('listing_categories')
                ->where('id', $listing->category_id)
                ->first();
            
            $subcategory = DB::table('listing_subcategories')
                ->where('id', $listing->subcategory_id)
                ->first();
            
            // Parse amenities from JSON
            $amenities = [];
            if (isset($listing->amenities) && !empty($listing->amenities)) {
                $amenities = json_decode($listing->amenities, true);
            }
            
            // Get user/owner info
            $owner = DB::table('users')
                ->where('id', $listing->user_id)
                ->select('id', 'fname', 'lname')
                ->first();
            
            // Add enriched data
            $enrichedListing = (array) $listing;
            $enrichedListing['images'] = $images;
            $enrichedListing['primary_image'] = !empty($images) ? $images[0] : null;
            $enrichedListing['category_name'] = $category ? $category->name : null;
            $enrichedListing['category_display_name'] = $category ? $category->display_name : null;
            $enrichedListing['subcategory_name'] = $subcategory ? $subcategory->name : null;
            $enrichedListing['subcategory_display_name'] = $subcategory ? $subcategory->display_name : null;
            $enrichedListing['amenities_list'] = $amenities;
            $enrichedListing['owner'] = $owner ? [
                'id' => $owner->id,
                'name' => $owner->fname . ' ' . $owner->lname
            ] : null;
            
            $enrichedListings[] = $enrichedListing;
        }
        
        return $enrichedListings;
    }

    /**
     * Advanced search specifically for under construction and investment properties
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function investmentPropertySearch(Request $request)
    {
        try {
            // Start with basic query for real estate listings that are under construction or for investment
            $query = DB::table('listings')
                ->join('real_estate_listings', 'listings.id', '=', 'real_estate_listings.listing_id')
                ->where('listings.status', 'active')
                ->where('listings.category_id', function($q) {
                    $q->select('id')
                      ->from('listing_categories')
                      ->where('name', 'real_estate')
                      ->first();
                })
                ->where(function($q) {
                    $q->where('listings.facility_under_construction', true)
                      ->orWhere('listings.purpose', 'investment');
                });
            
            // Location filters
            if ($request->has('city')) {
                $query->where('listings.city', $request->city);
            }
            
            if ($request->has('area')) {
                $query->where('listings.area', $request->area);
            }
            
            // Map location filter
            if ($request->has('lat') && $request->has('lng') && $request->has('radius')) {
                $lat = $request->lat;
                $lng = $request->lng;
                $radius = $request->radius; // in kilometers
                
                $query->selectRaw(
                    '*, (6371 * acos(cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat)))) AS distance', 
                    [$lat, $lng, $lat]
                )
                ->having('distance', '<=', $radius)
                ->orderBy('distance');
            }
            
            // Property type filter (apartment, villa, etc.)
            if ($request->has('property_type')) {
                $query->where('real_estate_listings.property_type', $request->property_type);
            }
            
            // Price range filter
            if ($request->has('min_price')) {
                $query->where('listings.price', '>=', $request->min_price);
            }
            
            if ($request->has('max_price')) {
                $query->where('listings.price', '<=', $request->max_price);
            }
            
            // Area size filter
            if ($request->has('min_area')) {
                $query->where('real_estate_listings.property_area', '>=', $request->min_area);
            }
            
            if ($request->has('max_area')) {
                $query->where('real_estate_listings.property_area', '<=', $request->max_area);
            }
            
            // Bedrooms filter
            if ($request->has('bedrooms')) {
                // Handle special case for "4+" bedrooms
                if ($request->bedrooms === '4+') {
                    $query->where('real_estate_listings.bedrooms', '>=', 4);
                } else {
                    $query->where('real_estate_listings.bedrooms', $request->bedrooms);
                }
            }
            
            // Bathrooms filter
            if ($request->has('bathrooms')) {
                // Handle special case for "3+" bathrooms
                if ($request->bathrooms === '3+') {
                    $query->where('real_estate_listings.bathrooms', '>=', 3);
                } else {
                    $query->where('real_estate_listings.bathrooms', $request->bathrooms);
                }
            }
            
            // Construction progress filter
            if ($request->has('min_progress')) {
                $query->where('listings.construction_progress_percent', '>=', $request->min_progress);
            }
            
            if ($request->has('max_progress')) {
                $query->where('listings.construction_progress_percent', '<=', $request->max_progress);
            }
            
            // Expected completion date filter
            if ($request->has('min_completion_date')) {
                $query->where('listings.expected_completion_date', '>=', $request->min_completion_date);
            }
            
            if ($request->has('max_completion_date')) {
                $query->where('listings.expected_completion_date', '<=', $request->max_completion_date);
            }
            
            // Investment type filter
            if ($request->has('investment_type')) {
                $query->where('real_estate_listings.investment_type', $request->investment_type);
            }
            
            // Expected ROI filter
            if ($request->has('min_roi')) {
                $query->where('real_estate_listings.expected_roi', '>=', $request->min_roi);
            }
            
            // Payment plan filter
            if ($request->has('payment_plan') && $request->payment_plan) {
                $query->where('real_estate_listings.has_payment_plan', true);
            }
            
            // Amenities filters
            if ($request->has('amenities') && is_array($request->amenities)) {
                foreach ($request->amenities as $amenity) {
                    $query->whereRaw("JSON_CONTAINS(real_estate_listings.amenities, '\"$amenity\"')");
                }
            }
            
            // Sort results
            $sortBy = $request->sort_by ?? 'created_at';
            $sortDirection = $request->sort_direction ?? 'desc';
            $query->orderBy("listings.$sortBy", $sortDirection);
            
            // Paginate results
            $perPage = $request->per_page ?? 10;
            $page = $request->page ?? 1;
            $results = $query->paginate($perPage, ['*'], 'page', $page);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'listings' => $this->enrichInvestmentListings($results->items()),
                    'pagination' => [
                        'total' => $results->total(),
                        'per_page' => $results->perPage(),
                        'current_page' => $results->currentPage(),
                        'last_page' => $results->lastPage(),
                        'total_results' => $results->total()
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search investment properties',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get investment property filter options
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getInvestmentPropertyFilters(Request $request)
    {
        try {
            // Get real estate category
            $category = DB::table('listing_categories')
                ->where('name', 'real_estate')
                ->first();
                
            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Real estate category not found'
                ], 404);
            }
            
            // Get property types
            $propertyTypes = [
                ['id' => 'apartment', 'name' => 'شقة', 'icon' => 'apartment'],
                ['id' => 'villa', 'name' => 'فيلا', 'icon' => 'villa'],
                ['id' => 'building', 'name' => 'مبنى', 'icon' => 'building'],
                ['id' => 'commercial', 'name' => 'تجاري', 'icon' => 'commercial'],
                ['id' => 'land', 'name' => 'أرض', 'icon' => 'land']
            ];
            
            // Get available cities
            $cities = DB::table('listings')
                ->join('real_estate_listings', 'listings.id', '=', 'real_estate_listings.listing_id')
                ->where('listings.category_id', $category->id)
                ->where(function($q) {
                    $q->where('listings.facility_under_construction', true)
                      ->orWhere('listings.purpose', 'investment');
                })
                ->where('listings.status', 'active')
                ->select('city')
                ->distinct()
                ->pluck('city')
                ->toArray();
            
            // Get price range for investment properties
            $priceRange = $this->getInvestmentPriceRange();
            
            // Get area size range
            $areaRange = $this->getAreaSizeRange();
            
            // Bedroom options
            $bedroomOptions = [
                ['id' => '1', 'name' => '1'],
                ['id' => '2', 'name' => '2'],
                ['id' => '3', 'name' => '3'],
                ['id' => '4+', 'name' => '4+']
            ];
            
            // Bathroom options
            $bathroomOptions = [
                ['id' => '1', 'name' => '1'],
                ['id' => '2', 'name' => '2'],
                ['id' => '3+', 'name' => '3+']
            ];
            
            // Construction progress options
            $progressOptions = [
                ['id' => '0-25', 'name' => '0% - 25%'],
                ['id' => '26-50', 'name' => '26% - 50%'],
                ['id' => '51-75', 'name' => '51% - 75%'],
                ['id' => '76-100', 'name' => '76% - 100%']
            ];
            
            // Investment type options
            $investmentTypeOptions = [
                ['id' => 'residential', 'name' => 'سكني'],
                ['id' => 'commercial', 'name' => 'تجاري'],
                ['id' => 'mixed_use', 'name' => 'متعدد الاستخدامات']
            ];
            
            // Expected ROI options
            $roiOptions = [
                ['id' => '0-5', 'name' => '0% - 5%'],
                ['id' => '5-10', 'name' => '5% - 10%'],
                ['id' => '10-15', 'name' => '10% - 15%'],
                ['id' => '15+', 'name' => 'أكثر من 15%']
            ];
            
            // Payment plan options
            $paymentPlanOptions = [
                ['id' => 'yes', 'name' => 'متوفر'],
                ['id' => 'no', 'name' => 'غير متوفر']
            ];
            
            return response()->json([
                'success' => true,
                'data' => [
                    'property_types' => $propertyTypes,
                    'cities' => $cities,
                    'price_range' => $priceRange,
                    'area_range' => $areaRange,
                    'bedroom_options' => $bedroomOptions,
                    'bathroom_options' => $bathroomOptions,
                    'progress_options' => $progressOptions,
                    'investment_type_options' => $investmentTypeOptions,
                    'roi_options' => $roiOptions,
                    'payment_plan_options' => $paymentPlanOptions
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve investment property filters',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get investment property price range
     *
     * @return array
     */
    private function getInvestmentPriceRange()
    {
        $min = DB::table('listings')
            ->join('real_estate_listings', 'listings.id', '=', 'real_estate_listings.listing_id')
            ->where(function($q) {
                $q->where('listings.facility_under_construction', true)
                  ->orWhere('listings.purpose', 'investment');
            })
            ->where('listings.status', 'active')
            ->min('price') ?? 0;
            
        $max = DB::table('listings')
            ->join('real_estate_listings', 'listings.id', '=', 'real_estate_listings.listing_id')
            ->where(function($q) {
                $q->where('listings.facility_under_construction', true)
                  ->orWhere('listings.purpose', 'investment');
            })
            ->where('listings.status', 'active')
            ->max('price') ?? 2000000;
            
        return [
            'min' => $min,
            'max' => $max
        ];
    }

    /**
     * Enrich investment property listings with additional data
     *
     * @param array $listings
     * @return array
     */
    private function enrichInvestmentListings($listings)
    {
        $enrichedListings = [];
        
        foreach ($listings as $listing) {
            // Get images (up to 5)
            $images = DB::table('listing_images')
                ->where('listing_id', $listing->id)
                ->orderBy('is_primary', 'desc')
                ->orderBy('display_order')
                ->limit(5)
                ->get()
                ->pluck('image_path')
                ->toArray();
            
            // Get category and subcategory info
            $category = DB::table('listing_categories')
                ->where('id', $listing->category_id)
                ->first();
            
            $subcategory = DB::table('listing_subcategories')
                ->where('id', $listing->subcategory_id)
                ->first();
            
            // Parse amenities from JSON
            $amenities = [];
            if (isset($listing->amenities) && !empty($listing->amenities)) {
                $amenities = json_decode($listing->amenities, true);
            }
            
            // Get developer info if available
            $developer = null;
            if (isset($listing->developer_id)) {
                $developer = DB::table('users')
                    ->where('id', $listing->developer_id)
                    ->select('id', 'fname', 'lname', 'company_name')
                    ->first();
            }
            
            // Add enriched data
            $enrichedListing = (array) $listing;
            $enrichedListing['images'] = $images;
            $enrichedListing['primary_image'] = !empty($images) ? $images[0] : null;
            $enrichedListing['category_name'] = $category ? $category->name : null;
            $enrichedListing['category_display_name'] = $category ? $category->display_name : null;
            $enrichedListing['subcategory_name'] = $subcategory ? $subcategory->name : null;
            $enrichedListing['subcategory_display_name'] = $subcategory ? $subcategory->display_name : null;
            $enrichedListing['amenities_list'] = $amenities;
            $enrichedListing['developer'] = $developer ? [
                'id' => $developer->id,
                'name' => $developer->fname . ' ' . $developer->lname,
                'company_name' => $developer->company_name
            ] : null;
            
            // Format completion date
            if (!empty($listing->expected_completion_date)) {
                $enrichedListing['formatted_completion_date'] = date('M Y', strtotime($listing->expected_completion_date));
            }
            
            $enrichedListings[] = $enrichedListing;
        }
        
        return $enrichedListings;
    }

    /**
     * Advanced search specifically for services
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function serviceSearch(Request $request)
    {
        try {
            // Start with basic query for service listings
            $query = DB::table('listings')
                ->join('service_listings', 'listings.id', '=', 'service_listings.listing_id')
                ->where('listings.status', 'active')
                ->where('listings.category_id', function($q) {
                    $q->select('id')
                      ->from('listing_categories')
                      ->where('name', 'service')
                      ->first();
                });
            
            // Location filters
            if ($request->has('city')) {
                $query->where('listings.city', $request->city);
            }
            
            if ($request->has('area')) {
                $query->where('listings.area', $request->area);
            }
            
            // Map location filter
            if ($request->has('lat') && $request->has('lng') && $request->has('radius')) {
                $lat = $request->lat;
                $lng = $request->lng;
                $radius = $request->radius; // in kilometers
                
                $query->selectRaw(
                    '*, (6371 * acos(cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat)))) AS distance', 
                    [$lat, $lng, $lat]
                )
                ->having('distance', '<=', $radius)
                ->orderBy('distance');
            }
            
            // Service type filter
            if ($request->has('service_type')) {
                $query->where('service_listings.service_type', $request->service_type);
            }
            
            // Subcategory filter
            if ($request->has('subcategory_id')) {
                $query->where('listings.subcategory_id', $request->subcategory_id);
            }
            
            // Price range filter
            if ($request->has('min_price')) {
                $query->where('listings.price', '>=', $request->min_price);
            }
            
            if ($request->has('max_price')) {
                $query->where('listings.price', '<=', $request->max_price);
            }
            
            // Experience years filter
            if ($request->has('min_experience')) {
                $query->where('service_listings.experience_years', '>=', $request->min_experience);
            }
            
            // Rating filter
            if ($request->has('min_rating')) {
                $query->whereExists(function ($q) use ($request) {
                    $q->select(DB::raw(1))
                      ->from('service_provider_reviews')
                      ->whereRaw('service_provider_reviews.service_provider_id = listings.user_id')
                      ->havingRaw('AVG(service_provider_reviews.rating) >= ?', [$request->min_rating]);
                });
            }
            
            // Mobile service filter
            if ($request->has('is_mobile') && $request->is_mobile !== null) {
                $query->where('service_listings.is_mobile', $request->is_mobile == 'true');
            }
            
            // Availability filter
            if ($request->has('available_day')) {
                $query->whereRaw("JSON_CONTAINS(service_listings.availability, '\"{$request->available_day}\"')");
            }
            
            // Certification filter
            if ($request->has('is_certified') && $request->is_certified) {
                $query->where('service_listings.is_certified', true);
            }
            
            // Sort results
            $sortBy = $request->sort_by ?? 'created_at';
            $sortDirection = $request->sort_direction ?? 'desc';
            $query->orderBy("listings.$sortBy", $sortDirection);
            
            // Paginate results
            $perPage = $request->per_page ?? 10;
            $page = $request->page ?? 1;
            $results = $query->paginate($perPage, ['*'], 'page', $page);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'listings' => $this->enrichServiceListings($results->items()),
                    'pagination' => [
                        'total' => $results->total(),
                        'per_page' => $results->perPage(),
                        'current_page' => $results->currentPage(),
                        'last_page' => $results->lastPage(),
                        'total_results' => $results->total()
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search services',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get service filter options
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getServiceFilters(Request $request)
    {
        try {
            // Get service category
            $category = DB::table('listing_categories')
                ->where('name', 'service')
                ->first();
                
            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Service category not found'
                ], 404);
            }
            
            // Get subcategories
            $subcategories = DB::table('listing_subcategories')
                ->where('category_id', $category->id)
                ->where('is_active', true)
                ->orderBy('display_order')
                ->get(['id', 'name', 'display_name']);
            
            // Get service types
            $serviceTypes = [
                ['id' => 'home_maintenance', 'name' => 'صيانة منزلية', 'icon' => 'home-repair'],
                ['id' => 'cleaning', 'name' => 'تنظيف', 'icon' => 'cleaning'],
                ['id' => 'beauty', 'name' => 'تجميل', 'icon' => 'beauty'],
                ['id' => 'education', 'name' => 'تعليم', 'icon' => 'education'],
                ['id' => 'health', 'name' => 'صحة', 'icon' => 'health'],
                ['id' => 'it_services', 'name' => 'خدمات تقنية', 'icon' => 'it'],
                ['id' => 'legal', 'name' => 'خدمات قانونية', 'icon' => 'legal'],
                ['id' => 'transportation', 'name' => 'نقل', 'icon' => 'transport'],
                ['id' => 'events', 'name' => 'مناسبات', 'icon' => 'events']
            ];
            
            // Get available cities
            $cities = DB::table('listings')
                ->join('service_listings', 'listings.id', '=', 'service_listings.listing_id')
                ->where('listings.category_id', $category->id)
                ->where('listings.status', 'active')
                ->select('city')
                ->distinct()
                ->pluck('city')
                ->toArray();
            
            // Get price range
            $priceRange = $this->getServicePriceRange();
            
            // Experience options
            $experienceOptions = [
                ['id' => '0-1', 'name' => 'أقل من سنة'],
                ['id' => '1-3', 'name' => '1-3 سنوات'],
                ['id' => '3-5', 'name' => '3-5 سنوات'],
                ['id' => '5-10', 'name' => '5-10 سنوات'],
                ['id' => '10+', 'name' => 'أكثر من 10 سنوات']
            ];
            
            // Rating options
            $ratingOptions = [
                ['id' => '5', 'name' => '5 نجوم'],
                ['id' => '4', 'name' => '4+ نجوم'],
                ['id' => '3', 'name' => '3+ نجوم'],
                ['id' => '2', 'name' => '2+ نجوم'],
                ['id' => '1', 'name' => '1+ نجوم']
            ];
            
            // Mobile service options
            $mobileOptions = [
                ['id' => 'true', 'name' => 'خدمة متنقلة'],
                ['id' => 'false', 'name' => 'في الموقع فقط']
            ];
            
            // Days of week options
            $daysOptions = [
                ['id' => 'sunday', 'name' => 'الأحد'],
                ['id' => 'monday', 'name' => 'الإثنين'],
                ['id' => 'tuesday', 'name' => 'الثلاثاء'],
                ['id' => 'wednesday', 'name' => 'الأربعاء'],
                ['id' => 'thursday', 'name' => 'الخميس'],
                ['id' => 'friday', 'name' => 'الجمعة'],
                ['id' => 'saturday', 'name' => 'السبت']
            ];
            
            // Certification options
            $certificationOptions = [
                ['id' => 'true', 'name' => 'معتمد'],
                ['id' => 'false', 'name' => 'غير معتمد']
            ];
            
            return response()->json([
                'success' => true,
                'data' => [
                    'subcategories' => $subcategories,
                    'service_types' => $serviceTypes,
                    'cities' => $cities,
                    'price_range' => $priceRange,
                    'experience_options' => $experienceOptions,
                    'rating_options' => $ratingOptions,
                    'mobile_options' => $mobileOptions,
                    'days_options' => $daysOptions,
                    'certification_options' => $certificationOptions
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve service filters',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get service price range
     *
     * @return array
     */
    private function getServicePriceRange()
    {
        $min = DB::table('listings')
            ->join('service_listings', 'listings.id', '=', 'service_listings.listing_id')
            ->where('listings.status', 'active')
            ->min('price') ?? 0;
            
        $max = DB::table('listings')
            ->join('service_listings', 'listings.id', '=', 'service_listings.listing_id')
            ->where('listings.status', 'active')
            ->max('price') ?? 1000;
            
        return [
            'min' => $min,
            'max' => $max
        ];
    }

    /**
     * Enrich service listings with additional data
     *
     * @param array $listings
     * @return array
     */
    private function enrichServiceListings($listings)
    {
        $enrichedListings = [];
        
        foreach ($listings as $listing) {
            // Get images (up to 5)
            $images = DB::table('listing_images')
                ->where('listing_id', $listing->id)
                ->orderBy('is_primary', 'desc')
                ->orderBy('display_order')
                ->limit(5)
                ->get()
                ->pluck('image_path')
                ->toArray();
                
            // Get category and subcategory info
            $category = DB::table('listing_categories')
                ->where('id', $listing->category_id)
                ->first();
                
            $subcategory = DB::table('listing_subcategories')
                ->where('id', $listing->subcategory_id)
                ->first();
                
            // Parse availability from JSON
            $availability = [];
            if (isset($listing->availability) && !empty($listing->availability)) {
                $availability = json_decode($listing->availability, true);
            }
            
            // Get service provider info
            $provider = DB::table('users')
                ->where('id', $listing->user_id)
                ->select('id', 'fname', 'lname')
                ->first();
            
            // Get service provider rating
            $rating = DB::table('service_provider_reviews')
                ->where('service_provider_id', $listing->user_id)
                ->avg('rating') ?? 0;
            
            // Get review count
            $reviewCount = DB::table('service_provider_reviews')
                ->where('service_provider_id', $listing->user_id)
                ->count();
            
            // Add enriched data
            $enrichedListing = (array) $listing;
            $enrichedListing['images'] = $images;
            $enrichedListing['primary_image'] = !empty($images) ? $images[0] : null;
            $enrichedListing['category_name'] = $category ? $category->name : null;
            $enrichedListing['category_display_name'] = $category ? $category->display_name : null;
            $enrichedListing['subcategory_name'] = $subcategory ? $subcategory->name : null;
            $enrichedListing['subcategory_display_name'] = $subcategory ? $subcategory->display_name : null;
            $enrichedListing['availability_days'] = $availability;
            $enrichedListing['provider'] = $provider ? [
                'id' => $provider->id,
                'name' => $provider->fname . ' ' . $provider->lname
            ] : null;
            $enrichedListing['rating'] = round($rating, 1);
            $enrichedListing['review_count'] = $reviewCount;
            
            // Format price based on price_type
            if ($listing->price_type === 'hourly') {
                $enrichedListing['formatted_price'] = $listing->price . ' ' . $listing->currency . '/ساعة';
            } elseif ($listing->price_type === 'daily') {
                $enrichedListing['formatted_price'] = $listing->price . ' ' . $listing->currency . '/يوم';
            } elseif ($listing->price_type === 'fixed') {
                $enrichedListing['formatted_price'] = $listing->price . ' ' . $listing->currency;
            } else {
                $enrichedListing['formatted_price'] = $listing->price . ' ' . $listing->currency;
            }
            
            $enrichedListings[] = $enrichedListing;
        }
        
        return $enrichedListings;
    }

    /**
     * Advanced search specifically for vehicles
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function vehicleSearch(Request $request)
    {
        try {
            // Start with basic query for vehicle listings
            $query = DB::table('listings')
                ->join('vehicle_listings', 'listings.id', '=', 'vehicle_listings.listing_id')
                ->where('listings.status', 'active')
                ->where('listings.category_id', function($q) {
                    $q->select('id')
                      ->from('listing_categories')
                      ->where('name', 'vehicle')
                      ->first();
                });
            
            // Location filters
            if ($request->has('city')) {
                $query->where('listings.city', $request->city);
            }
            
            if ($request->has('area')) {
                $query->where('listings.area', $request->area);
            }
            
            // Map location filter
            if ($request->has('lat') && $request->has('lng') && $request->has('radius')) {
                $lat = $request->lat;
                $lng = $request->lng;
                $radius = $request->radius; // in kilometers
                
                $query->selectRaw(
                    '*, (6371 * acos(cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat)))) AS distance', 
                    [$lat, $lng, $lat]
                )
                ->having('distance', '<=', $radius)
                ->orderBy('distance');
            }
            
            // Vehicle type filter (car, truck, etc.)
            if ($request->has('vehicle_type')) {
                $query->where('vehicle_listings.vehicle_type', $request->vehicle_type);
            }
            
            // Make filter
            if ($request->has('make')) {
                $query->where('vehicle_listings.make', $request->make);
            }
            
            // Model filter
            if ($request->has('model')) {
                $query->where('vehicle_listings.model', 'like', "%{$request->model}%");
            }
            
            // Year range filter
            if ($request->has('min_year')) {
                $query->where('vehicle_listings.year', '>=', $request->min_year);
            }
            
            if ($request->has('max_year')) {
                $query->where('vehicle_listings.year', '<=', $request->max_year);
            }
            
            // Price range filter
            if ($request->has('min_price')) {
                $query->where('listings.price', '>=', $request->min_price);
            }
            
            if ($request->has('max_price')) {
                $query->where('listings.price', '<=', $request->max_price);
            }
            
            // Mileage range filter
            if ($request->has('min_mileage')) {
                $query->where('vehicle_listings.mileage', '>=', $request->min_mileage);
            }
            
            if ($request->has('max_mileage')) {
                $query->where('vehicle_listings.mileage', '<=', $request->max_mileage);
            }
            
            // Transmission filter
            if ($request->has('transmission')) {
                $query->where('vehicle_listings.transmission', $request->transmission);
            }
            
            // Fuel type filter
            if ($request->has('fuel_type')) {
                $query->where('vehicle_listings.fuel_type', $request->fuel_type);
            }
            
            // Color filter
            if ($request->has('color')) {
                $query->where('vehicle_listings.color', $request->color);
            }
            
            // Body type filter
            if ($request->has('body_type')) {
                $query->where('vehicle_listings.body_type', $request->body_type);
            }
            
            // Condition filter
            if ($request->has('condition')) {
                $query->where('vehicle_listings.condition', $request->condition);
            }
            
            // Features filter
            if ($request->has('features') && is_array($request->features)) {
                foreach ($request->features as $feature) {
                    $query->whereRaw("JSON_CONTAINS(vehicle_listings.features, '\"$feature\"')");
                }
            }
            
            // Sort results
            $sortBy = $request->sort_by ?? 'created_at';
            $sortDirection = $request->sort_direction ?? 'desc';
            $query->orderBy("listings.$sortBy", $sortDirection);
            
            // Paginate results
            $perPage = $request->per_page ?? 10;
            $page = $request->page ?? 1;
            $results = $query->paginate($perPage, ['*'], 'page', $page);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'listings' => $this->enrichVehicleListings($results->items()),
                    'pagination' => [
                        'total' => $results->total(),
                        'per_page' => $results->perPage(),
                        'current_page' => $results->currentPage(),
                        'last_page' => $results->lastPage(),
                        'total_results' => $results->total()
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search vehicles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get vehicle filter options
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getVehicleFilters(Request $request)
    {
        try {
            // Get vehicle category
            $category = DB::table('listing_categories')
                ->where('name', 'vehicle')
                ->first();
                
            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vehicle category not found'
                ], 404);
            }
            
            // Get vehicle types
            $vehicleTypes = [
                ['id' => 'car', 'name' => 'سيارة', 'icon' => 'car'],
                ['id' => 'suv', 'name' => 'دفع رباعي', 'icon' => 'suv'],
                ['id' => 'truck', 'name' => 'شاحنة', 'icon' => 'truck'],
                ['id' => 'motorcycle', 'name' => 'دراجة نارية', 'icon' => 'motorcycle'],
                ['id' => 'bus', 'name' => 'حافلة', 'icon' => 'bus'],
                ['id' => 'boat', 'name' => 'قارب', 'icon' => 'boat'],
                ['id' => 'other', 'name' => 'أخرى', 'icon' => 'other']
            ];
            
            // Get available makes
            $makes = DB::table('vehicle_listings')
                ->join('listings', 'vehicle_listings.listing_id', '=', 'listings.id')
                ->where('listings.category_id', $category->id)
                ->where('listings.status', 'active')
                ->select('make')
                ->distinct()
                ->orderBy('make')
                ->pluck('make')
                ->toArray();
            
            // Get available models (if make is specified)
            $models = [];
            if ($request->has('make')) {
                $models = DB::table('vehicle_listings')
                    ->join('listings', 'vehicle_listings.listing_id', '=', 'listings.id')
                    ->where('listings.category_id', $category->id)
                    ->where('listings.status', 'active')
                    ->where('vehicle_listings.make', $request->make)
                    ->select('model')
                    ->distinct()
                    ->orderBy('model')
                    ->pluck('model')
                    ->toArray();
            }
            
            // Get available cities
            $cities = DB::table('listings')
                ->join('vehicle_listings', 'listings.id', '=', 'vehicle_listings.listing_id')
                ->where('listings.category_id', $category->id)
                ->where('listings.status', 'active')
                ->select('city')
                ->distinct()
                ->pluck('city')
                ->toArray();
            
            // Get price range
            $priceRange = $this->getVehiclePriceRange();
            
            // Get year range
            $yearRange = $this->getVehicleYearRange();
            
            // Get mileage range
            $mileageRange = $this->getVehicleMileageRange();
            
            // Transmission options
            $transmissionOptions = [
                ['id' => 'automatic', 'name' => 'أوتوماتيك'],
                ['id' => 'manual', 'name' => 'يدوي'],
                ['id' => 'semi-automatic', 'name' => 'نصف أوتوماتيك']
            ];
            
            // Fuel type options
            $fuelTypeOptions = [
                ['id' => 'gasoline', 'name' => 'بنزين'],
                ['id' => 'diesel', 'name' => 'ديزل'],
                ['id' => 'hybrid', 'name' => 'هجين'],
                ['id' => 'electric', 'name' => 'كهربائي'],
                ['id' => 'other', 'name' => 'أخرى']
            ];
            
            // Color options
            $colorOptions = [
                ['id' => 'white', 'name' => 'أبيض'],
                ['id' => 'black', 'name' => 'أسود'],
                ['id' => 'silver', 'name' => 'فضي'],
                ['id' => 'gray', 'name' => 'رمادي'],
                ['id' => 'red', 'name' => 'أحمر'],
                ['id' => 'blue', 'name' => 'أزرق'],
                ['id' => 'green', 'name' => 'أخضر'],
                ['id' => 'yellow', 'name' => 'أصفر'],
                ['id' => 'brown', 'name' => 'بني'],
                ['id' => 'other', 'name' => 'أخرى']
            ];
            
            // Body type options
            $bodyTypeOptions = [
                ['id' => 'sedan', 'name' => 'سيدان'],
                ['id' => 'suv', 'name' => 'دفع رباعي'],
                ['id' => 'hatchback', 'name' => 'هاتشباك'],
                ['id' => 'coupe', 'name' => 'كوبيه'],
                ['id' => 'convertible', 'name' => 'مكشوفة'],
                ['id' => 'wagon', 'name' => 'ستيشن'],
                ['id' => 'van', 'name' => 'فان'],
                ['id' => 'truck', 'name' => 'بيك أب'],
                ['id' => 'other', 'name' => 'أخرى']
            ];
            
            // Condition options
            $conditionOptions = [
                ['id' => 'new', 'name' => 'جديدة'],
                ['id' => 'excellent', 'name' => 'ممتازة'],
                ['id' => 'good', 'name' => 'جيدة'],
                ['id' => 'fair', 'name' => 'مقبولة'],
                ['id' => 'poor', 'name' => 'سيئة']
            ];
            
            // Features options
            $featuresOptions = [
                ['id' => 'air_conditioning', 'name' => 'تكييف'],
                ['id' => 'power_windows', 'name' => 'نوافذ كهربائية'],
                ['id' => 'power_steering', 'name' => 'مقود كهربائي'],
                ['id' => 'abs', 'name' => 'نظام فرامل ABS'],
                ['id' => 'airbags', 'name' => 'وسائد هوائية'],
                ['id' => 'leather_seats', 'name' => 'مقاعد جلد'],
                ['id' => 'sunroof', 'name' => 'فتحة سقف'],
                ['id' => 'navigation', 'name' => 'نظام ملاحة'],
                ['id' => 'bluetooth', 'name' => 'بلوتوث'],
                ['id' => 'cruise_control', 'name' => 'مثبت سرعة'],
                ['id' => 'backup_camera', 'name' => 'كاميرا خلفية'],
                ['id' => 'parking_sensors', 'name' => 'حساسات ركن']
            ];
            
            return response()->json([
                'success' => true,
                'data' => [
                    'vehicle_types' => $vehicleTypes,
                    'makes' => $makes,
                    'models' => $models,
                    'cities' => $cities,
                    'price_range' => $priceRange,
                    'year_range' => $yearRange,
                    'mileage_range' => $mileageRange,
                    'transmission_options' => $transmissionOptions,
                    'fuel_type_options' => $fuelTypeOptions,
                    'color_options' => $colorOptions,
                    'body_type_options' => $bodyTypeOptions,
                    'condition_options' => $conditionOptions,
                    'features_options' => $featuresOptions
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve vehicle filters',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get vehicle price range
     *
     * @return array
     */
    private function getVehiclePriceRange()
    {
        $min = DB::table('listings')
            ->join('vehicle_listings', 'listings.id', '=', 'vehicle_listings.listing_id')
            ->where('listings.status', 'active')
            ->min('price') ?? 0;
            
        $max = DB::table('listings')
            ->join('vehicle_listings', 'listings.id', '=', 'vehicle_listings.listing_id')
            ->where('listings.status', 'active')
            ->max('price') ?? 100000;
            
        return [
            'min' => $min,
            'max' => $max
        ];
    }

    /**
     * Get vehicle year range
     *
     * @return array
     */
    private function getVehicleYearRange()
    {
        $min = DB::table('vehicle_listings')
            ->join('listings', 'vehicle_listings.listing_id', '=', 'listings.id')
            ->where('listings.status', 'active')
            ->min('year') ?? 1990;
            
        $max = DB::table('vehicle_listings')
            ->join('listings', 'vehicle_listings.listing_id', '=', 'listings.id')
            ->where('listings.status', 'active')
            ->max('year') ?? date('Y');
            
        return [
            'min' => $min,
            'max' => $max
        ];
    }

    /**
     * Get vehicle mileage range
     *
     * @return array
     */
    private function getVehicleMileageRange()
    {
        $min = DB::table('vehicle_listings')
            ->join('listings', 'vehicle_listings.listing_id', '=', 'listings.id')
            ->where('listings.status', 'active')
            ->min('mileage') ?? 0;
            
        $max = DB::table('vehicle_listings')
            ->join('listings', 'vehicle_listings.listing_id', '=', 'listings.id')
            ->where('listings.status', 'active')
            ->max('mileage') ?? 300000;
            
        return [
            'min' => $min,
            'max' => $max
        ];
    }

    /**
     * Enrich vehicle listings with additional data
     *
     * @param array $listings
     * @return array
     */
    private function enrichVehicleListings($listings)
    {
        $enrichedListings = [];
        
        foreach ($listings as $listing) {
            // Get images (up to 5)
            $images = DB::table('listing_images')
                ->where('listing_id', $listing->id)
                ->orderBy('is_primary', 'desc')
                ->orderBy('display_order')
                ->limit(5)
                ->get()
                ->pluck('image_path')
                ->toArray();
            
            // Get category and subcategory info
            $category = DB::table('listing_categories')
                ->where('id', $listing->category_id)
                ->first();
            
            $subcategory = DB::table('listing_subcategories')
                ->where('id', $listing->subcategory_id)
                ->first();
            
            // Parse features from JSON
            $features = [];
            if (isset($listing->features) && !empty($listing->features)) {
                $features = json_decode($listing->features, true);
            }
            
            // Get seller info
            $seller = DB::table('users')
                ->where('id', $listing->user_id)
                ->select('id', 'fname', 'lname')
                ->first();
            
            // Add enriched data
            $enrichedListing = (array) $listing;
            $enrichedListing['images'] = $images;
            $enrichedListing['primary_image'] = !empty($images) ? $images[0] : null;
            $enrichedListing['category_name'] = $category ? $category->name : null;
            $enrichedListing['category_display_name'] = $category ? $category->display_name : null;
            $enrichedListing['subcategory_name'] = $subcategory ? $subcategory->name : null;
            $enrichedListing['subcategory_display_name'] = $subcategory ? $subcategory->display_name : null;
            $enrichedListing['features_list'] = $features;
            $enrichedListing['seller'] = $seller ? [
                'id' => $seller->id,
                'name' => $seller->fname . ' ' . $seller->lname
            ] : null;
            
            // Format full vehicle name
            $enrichedListing['full_name'] = "{$listing->make} {$listing->model} {$listing->year}";
            
            // Format mileage with units
            $enrichedListing['formatted_mileage'] = number_format($listing->mileage) . ' كم';
            
            $enrichedListings[] = $enrichedListing;
        }
        
        return $enrichedListings;
    }

    /**
     * Advanced search specifically for job listings
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function jobSearch(Request $request)
    {
        try {
            // Start with basic query for job listings
            $query = DB::table('listings')
                ->join('job_listings', 'listings.id', '=', 'job_listings.listing_id')
                ->where('listings.status', 'active')
                ->where('listings.category_id', function($q) {
                    $q->select('id')
                      ->from('listing_categories')
                      ->where('name', 'job')
                      ->first();
                });
            
            // Location filters
            if ($request->has('city')) {
                $query->where('listings.city', $request->city);
            }
            
            if ($request->has('area')) {
                $query->where('listings.area', $request->area);
            }
            
            // Map location filter
            if ($request->has('lat') && $request->has('lng') && $request->has('radius')) {
                $lat = $request->lat;
                $lng = $request->lng;
                $radius = $request->radius; // in kilometers
                
                $query->selectRaw(
                    '*, (6371 * acos(cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat)))) AS distance', 
                    [$lat, $lng, $lat]
                )
                ->having('distance', '<=', $radius)
                ->orderBy('distance');
            }
            
            // Job type filter (full-time, part-time, etc.)
            if ($request->has('job_type')) {
                $query->where('job_listings.job_type', $request->job_type);
            }
            
            // Subcategory filter
            if ($request->has('subcategory_id')) {
                $query->where('listings.subcategory_id', $request->subcategory_id);
            }
            
            // Salary range filter
            if ($request->has('min_salary')) {
                $query->where('job_listings.salary_min', '>=', $request->min_salary);
            }
            
            if ($request->has('max_salary')) {
                $query->where('job_listings.salary_max', '<=', $request->max_salary);
            }
            
            // Experience level filter
            if ($request->has('experience_level')) {
                $query->where('job_listings.experience_level', $request->experience_level);
            }
            
            // Education level filter
            if ($request->has('education_level')) {
                $query->where('job_listings.education_level', $request->education_level);
            }
            
            // Remote work filter
            if ($request->has('is_remote') && $request->is_remote !== null) {
                $query->where('job_listings.is_remote', $request->is_remote == 'true');
            }
            
            // Company size filter
            if ($request->has('company_size')) {
                $query->where('job_listings.company_size', $request->company_size);
            }
            
            // Industry filter
            if ($request->has('industry')) {
                $query->where('job_listings.industry', $request->industry);
            }
            
            // Benefits filter
            if ($request->has('benefits') && is_array($request->benefits)) {
                foreach ($request->benefits as $benefit) {
                    $query->whereRaw("JSON_CONTAINS(job_listings.benefits, '\"$benefit\"')");
                }
            }
            
            // Sort results
            $sortBy = $request->sort_by ?? 'created_at';
            $sortDirection = $request->sort_direction ?? 'desc';
            $query->orderBy("listings.$sortBy", $sortDirection);
            
            // Paginate results
            $perPage = $request->per_page ?? 10;
            $page = $request->page ?? 1;
            $results = $query->paginate($perPage, ['*'], 'page', $page);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'listings' => $this->enrichJobListings($results->items()),
                    'pagination' => [
                        'total' => $results->total(),
                        'per_page' => $results->perPage(),
                        'current_page' => $results->currentPage(),
                        'last_page' => $results->lastPage(),
                        'total_results' => $results->total()
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search job listings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get job filter options
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getJobFilters(Request $request)
    {
        try {
            // Get job category
            $category = DB::table('listing_categories')
                ->where('name', 'job')
                ->first();
                
            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job category not found'
                ], 404);
            }
            
            // Get subcategories
            $subcategories = DB::table('listing_subcategories')
                ->where('category_id', $category->id)
                ->where('is_active', true)
                ->orderBy('display_order')
                ->get(['id', 'name', 'display_name']);
            
            // Get job types
            $jobTypes = [
                ['id' => 'full_time', 'name' => 'دوام كامل'],
                ['id' => 'part_time', 'name' => 'دوام جزئي'],
                ['id' => 'contract', 'name' => 'عقد'],
                ['id' => 'temporary', 'name' => 'مؤقت'],
                ['id' => 'internship', 'name' => 'تدريب'],
                ['id' => 'freelance', 'name' => 'عمل حر']
            ];
            
            // Get available cities
            $cities = DB::table('listings')
                ->join('job_listings', 'listings.id', '=', 'job_listings.listing_id')
                ->where('listings.category_id', $category->id)
                ->where('listings.status', 'active')
                ->select('city')
                ->distinct()
                ->pluck('city')
                ->toArray();
            
            // Get salary range
            $salaryRange = $this->getJobSalaryRange();
            
            // Experience level options
            $experienceLevelOptions = [
                ['id' => 'entry_level', 'name' => 'مبتدئ'],
                ['id' => 'mid_level', 'name' => 'متوسط'],
                ['id' => 'senior_level', 'name' => 'خبير'],
                ['id' => 'manager', 'name' => 'مدير'],
                ['id' => 'executive', 'name' => 'تنفيذي']
            ];
            
            // Education level options
            $educationLevelOptions = [
                ['id' => 'high_school', 'name' => 'ثانوية عامة'],
                ['id' => 'diploma', 'name' => 'دبلوم'],
                ['id' => 'bachelors', 'name' => 'بكالوريوس'],
                ['id' => 'masters', 'name' => 'ماجستير'],
                ['id' => 'doctorate', 'name' => 'دكتوراه'],
                ['id' => 'not_required', 'name' => 'غير مطلوب']
            ];
            
            // Remote work options
            $remoteOptions = [
                ['id' => 'true', 'name' => 'عن بعد'],
                ['id' => 'false', 'name' => 'في المكتب']
            ];
            
            // Company size options
            $companySizeOptions = [
                ['id' => '1-10', 'name' => '1-10 موظفين'],
                ['id' => '11-50', 'name' => '11-50 موظف'],
                ['id' => '51-200', 'name' => '51-200 موظف'],
                ['id' => '201-500', 'name' => '201-500 موظف'],
                ['id' => '501-1000', 'name' => '501-1000 موظف'],
                ['id' => '1000+', 'name' => 'أكثر من 1000 موظف']
            ];
            
            // Industry options
            $industryOptions = [
                ['id' => 'technology', 'name' => 'تكنولوجيا'],
                ['id' => 'healthcare', 'name' => 'رعاية صحية'],
                ['id' => 'education', 'name' => 'تعليم'],
                ['id' => 'finance', 'name' => 'مالية'],
                ['id' => 'retail', 'name' => 'تجزئة'],
                ['id' => 'manufacturing', 'name' => 'تصنيع'],
                ['id' => 'hospitality', 'name' => 'ضيافة'],
                ['id' => 'construction', 'name' => 'إنشاءات'],
                ['id' => 'media', 'name' => 'إعلام'],
                ['id' => 'government', 'name' => 'حكومي'],
                ['id' => 'other', 'name' => 'أخرى']
            ];
            
            // Benefits options
            $benefitsOptions = [
                ['id' => 'health_insurance', 'name' => 'تأمين صحي'],
                ['id' => 'paid_time_off', 'name' => 'إجازة مدفوعة'],
                ['id' => 'retirement_plan', 'name' => 'خطة تقاعد'],
                ['id' => 'flexible_hours', 'name' => 'ساعات عمل مرنة'],
                ['id' => 'remote_work', 'name' => 'عمل عن بعد'],
                ['id' => 'professional_development', 'name' => 'تطوير مهني'],
                ['id' => 'bonuses', 'name' => 'مكافآت'],
                ['id' => 'transportation', 'name' => 'مواصلات']
            ];
            
            return response()->json([
                'success' => true,
                'data' => [
                    'subcategories' => $subcategories,
                    'job_types' => $jobTypes,
                    'cities' => $cities,
                    'salary_range' => $salaryRange,
                    'experience_level_options' => $experienceLevelOptions,
                    'education_level_options' => $educationLevelOptions,
                    'remote_options' => $remoteOptions,
                    'company_size_options' => $companySizeOptions,
                    'industry_options' => $industryOptions,
                    'benefits_options' => $benefitsOptions
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve job filters',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get job salary range
     *
     * @return array
     */
    private function getJobSalaryRange()
    {
        $min = DB::table('job_listings')
            ->join('listings', 'job_listings.listing_id', '=', 'listings.id')
            ->where('listings.status', 'active')
            ->min('salary_min') ?? 0;
            
        $max = DB::table('job_listings')
            ->join('listings', 'job_listings.listing_id', '=', 'listings.id')
            ->where('listings.status', 'active')
            ->max('salary_max') ?? 10000;
            
        return [
            'min' => $min,
            'max' => $max
        ];
    }

    /**
     * Enrich job listings with additional data
     *
     * @param array $listings
     * @return array
     */
    private function enrichJobListings($listings)
    {
        $enrichedListings = [];
        
        foreach ($listings as $listing) {
            // Get images (up to 5)
            $images = DB::table('listing_images')
                ->where('listing_id', $listing->id)
                ->orderBy('is_primary', 'desc')
                ->orderBy('display_order')
                ->limit(5)
                ->get()
                ->pluck('image_path')
                ->toArray();
                
            // Get category and subcategory info
            $category = DB::table('listing_categories')
                ->where('id', $listing->category_id)
                ->first();
                
            $subcategory = DB::table('listing_subcategories')
                ->where('id', $listing->subcategory_id)
                ->first();
                
            // Parse benefits from JSON
            $benefits = [];
            if (isset($listing->benefits) && !empty($listing->benefits)) {
                $benefits = json_decode($listing->benefits, true);
            }
            
            // Get company/employer info
            $employer = DB::table('users')
                ->where('id', $listing->user_id)
                ->select('id', 'fname', 'lname')
                ->first();
            
            // Add enriched data
            $enrichedListing = (array) $listing;
            $enrichedListing['images'] = $images;
            $enrichedListing['primary_image'] = !empty($images) ? $images[0] : null;
            $enrichedListing['category_name'] = $category ? $category->name : null;
            $enrichedListing['category_display_name'] = $category ? $category->display_name : null;
            $enrichedListing['subcategory_name'] = $subcategory ? $subcategory->name : null;
            $enrichedListing['subcategory_display_name'] = $subcategory ? $subcategory->display_name : null;
            $enrichedListing['benefits_list'] = $benefits;
            $enrichedListing['employer'] = $employer ? [
                'id' => $employer->id,
                'name' => $employer->fname . ' ' . $employer->lname
            ] : null;
            
            // Format salary range
            if (!empty($listing->salary_min) && !empty($listing->salary_max)) {
                $enrichedListing['formatted_salary'] = number_format($listing->salary_min) . ' - ' . number_format($listing->salary_max) . ' ' . $listing->currency;
            } elseif (!empty($listing->salary_min)) {
                $enrichedListing['formatted_salary'] = 'من ' . number_format($listing->salary_min) . ' ' . $listing->currency;
            } elseif (!empty($listing->salary_max)) {
                $enrichedListing['formatted_salary'] = 'حتى ' . number_format($listing->salary_max) . ' ' . $listing->currency;
            } else {
                $enrichedListing['formatted_salary'] = 'غير محدد';
            }
            
            // Format job type in Arabic
            switch ($listing->job_type) {
                case 'full_time':
                    $enrichedListing['formatted_job_type'] = 'دوام كامل';
                    break;
                case 'part_time':
                    $enrichedListing['formatted_job_type'] = 'دوام جزئي';
                    break;
                case 'contract':
                    $enrichedListing['formatted_job_type'] = 'عقد';
                    break;
                case 'temporary':
                    $enrichedListing['formatted_job_type'] = 'مؤقت';
                    break;
                case 'internship':
                    $enrichedListing['formatted_job_type'] = 'تدريب';
                    break;
                case 'freelance':
                    $enrichedListing['formatted_job_type'] = 'عمل حر';
                    break;
                default:
                    $enrichedListing['formatted_job_type'] = $listing->job_type;
            }
            
            $enrichedListings[] = $enrichedListing;
        }
        
        return $enrichedListings;
    }

    /**
     * Get similar listings for a specific listing
     *
     * @param Request $request
     * @param int $listingId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSimilarListings(Request $request, $listingId)
    {
        try {
            // Get the current listing
            $listing = DB::table('listings')
                ->where('id', $listingId)
                ->where('status', 'active')
                ->first();
                
            if (!$listing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Listing not found'
                ], 404);
            }
            
            // Get the category and subcategory of the current listing
            $categoryId = $listing->category_id;
            $subcategoryId = $listing->subcategory_id;
            $city = $listing->city;
            $area = $listing->area;
            $lat = $listing->lat;
            $lng = $listing->lng;
            
            // Start building the query for similar listings
            $query = DB::table('listings')
                ->where('listings.id', '!=', $listingId) // Exclude current listing
                ->where('listings.status', 'active');
            
            // Filter by the same category
            $query->where('listings.category_id', $categoryId);
            
            // If subcategory exists, prefer listings from the same subcategory
            if ($subcategoryId) {
                $query->orderByRaw('CASE WHEN listings.subcategory_id = ? THEN 0 ELSE 1 END', [$subcategoryId]);
            }
            
            // If location exists, add location relevance
            if ($city) {
                $query->orderByRaw('CASE WHEN listings.city = ? THEN 0 ELSE 1 END', [$city]);
            }
            
            // Add specific filters based on listing type
            $categoryName = DB::table('listing_categories')
                ->where('id', $categoryId)
                ->value('name');
                
            switch ($categoryName) {
                case 'real_estate':
                    $this->addRealEstateSimilarityFilters($query, $listingId);
                    break;
                case 'vehicle':
                    $this->addVehicleSimilarityFilters($query, $listingId);
                    break;
                case 'service':
                    $this->addServiceSimilarityFilters($query, $listingId);
                    break;
                case 'job':
                    $this->addJobSimilarityFilters($query, $listingId);
                    break;
                case 'bid':
                    $this->addBidSimilarityFilters($query, $listingId);
                    break;
            }
            
            // If coordinates exist, calculate distance and sort by proximity
            if ($lat && $lng) {
                $query->selectRaw(
                    'listings.*, (6371 * acos(cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat)))) AS distance', 
                    [$lat, $lng, $lat]
                );
                $query->orderBy('distance');
            }
            
            // Add price similarity (within 20% range)
            if ($listing->price) {
                $minPrice = $listing->price * 0.8;
                $maxPrice = $listing->price * 1.2;
                $query->where(function ($q) use ($minPrice, $maxPrice) {
                    $q->whereBetween('listings.price', [$minPrice, $maxPrice])
                      ->orWhereNull('listings.price');
                });
            }
            
            // Get the most recent listings if there are not enough similar ones
            $query->orderBy('listings.created_at', 'desc');
            
            // Limit the number of similar listings
            $limit = $request->limit ?? 6;
            $similarListings = $query->limit($limit)->get();
            
            // Enrich the listings with additional data
            $enrichedListings = $this->enrichListingsByType($similarListings, $categoryName);
            
            return response()->json([
                'success' => true,
                'data' => $enrichedListings
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get similar listings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add real estate specific similarity filters
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param int $listingId
     * @return void
     */
    private function addRealEstateSimilarityFilters($query, $listingId)
    {
        // Get the real estate details
        $realEstateListing = DB::table('real_estate_listings')
            ->where('listing_id', $listingId)
            ->first();
            
        if (!$realEstateListing) {
            return;
        }
        
        // Join with real_estate_listings table
        $query->join('real_estate_listings', 'listings.id', '=', 'real_estate_listings.listing_id');
        
        // Match property type (apartment, villa, etc.)
        if ($realEstateListing->property_type) {
            $query->where('real_estate_listings.property_type', $realEstateListing->property_type);
        }
        
        // Match purpose (rent, sell)
        $mainListing = DB::table('listings')->where('id', $listingId)->first();
        if ($mainListing && $mainListing->purpose) {
            $query->where('listings.purpose', $mainListing->purpose);
        }
        
        // Similar number of bedrooms (±1)
        if (isset($realEstateListing->bedrooms) && $realEstateListing->bedrooms) {
            $minBedrooms = max(1, $realEstateListing->bedrooms - 1);
            $maxBedrooms = $realEstateListing->bedrooms + 1;
            $query->whereBetween('real_estate_listings.bedrooms', [$minBedrooms, $maxBedrooms]);
        }
        
        // Similar area size (±20%)
        if ($realEstateListing->property_area) {
            $minArea = $realEstateListing->property_area * 0.8;
            $maxArea = $realEstateListing->property_area * 1.2;
            $query->whereBetween('real_estate_listings.property_area', [$minArea, $maxArea]);
        }
    }

    /**
     * Add vehicle specific similarity filters
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param int $listingId
     * @return void
     */
    private function addVehicleSimilarityFilters($query, $listingId)
    {
        // Get the vehicle details
        $vehicleListing = DB::table('vehicle_listings')
            ->where('listing_id', $listingId)
            ->first();
            
        if (!$vehicleListing) {
            return;
        }
        
        // Join with vehicle_listings table
        $query->join('vehicle_listings', 'listings.id', '=', 'vehicle_listings.listing_id');
        
        // Match make
        if ($vehicleListing->make) {
            $query->where('vehicle_listings.make', $vehicleListing->make);
        }
        
        // Similar model year (±3 years)
        if ($vehicleListing->year) {
            $minYear = $vehicleListing->year - 3;
            $maxYear = $vehicleListing->year + 3;
            $query->whereBetween('vehicle_listings.year', [$minYear, $maxYear]);
        }
        
        // Match vehicle type (car, truck, etc.)
        if ($vehicleListing->vehicle_type) {
            $query->where('vehicle_listings.vehicle_type', $vehicleListing->vehicle_type);
        }
        
        // Similar mileage (±30%)
        if ($vehicleListing->mileage) {
            $minMileage = $vehicleListing->mileage * 0.7;
            $maxMileage = $vehicleListing->mileage * 1.3;
            $query->whereBetween('vehicle_listings.mileage', [$minMileage, $maxMileage]);
        }
    }

    /**
     * Add service specific similarity filters
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param int $listingId
     * @return void
     */
    private function addServiceSimilarityFilters($query, $listingId)
    {
        // Get the service details
        $serviceListing = DB::table('service_listings')
            ->where('listing_id', $listingId)
            ->first();
            
        if (!$serviceListing) {
            return;
        }
        
        // Join with service_listings table
        $query->join('service_listings', 'listings.id', '=', 'service_listings.listing_id');
        
        // Match service type
        if ($serviceListing->service_type) {
            $query->where('service_listings.service_type', $serviceListing->service_type);
        }
        
        // Match mobility (mobile service or not)
        if ($serviceListing->is_mobile !== null) {
            $query->where('service_listings.is_mobile', $serviceListing->is_mobile);
        }
    }

    /**
     * Add job specific similarity filters
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param int $listingId
     * @return void
     */
    private function addJobSimilarityFilters($query, $listingId)
    {
        // Get the job details
        $jobListing = DB::table('job_listings')
            ->where('listing_id', $listingId)
            ->first();
            
        if (!$jobListing) {
            return;
        }
        
        // Join with job_listings table
        $query->join('job_listings', 'listings.id', '=', 'job_listings.listing_id');
        
        // Match job type (full-time, part-time, etc.)
        if ($jobListing->job_type) {
            $query->where('job_listings.job_type', $jobListing->job_type);
        }
        
        // Match experience level
        if ($jobListing->experience_level) {
            $query->where('job_listings.experience_level', $jobListing->experience_level);
        }
        
        // Match industry
        if ($jobListing->industry) {
            $query->where('job_listings.industry', $jobListing->industry);
        }
        
        // Match remote work preference
        if ($jobListing->is_remote !== null) {
            $query->where('job_listings.is_remote', $jobListing->is_remote);
        }
    }

    /**
     * Add bid specific similarity filters
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param int $listingId
     * @return void
     */
    private function addBidSimilarityFilters($query, $listingId)
    {
        // Get the bid details
        $bidListing = DB::table('bid_listings')
            ->where('listing_id', $listingId)
            ->first();
            
        if (!$bidListing) {
            return;
        }
        
        // Join with bid_listings table
        $query->join('bid_listings', 'listings.id', '=', 'bid_listings.listing_id');
        
        // Match bid type
        if ($bidListing->bid_type) {
            $query->where('bid_listings.bid_type', $bidListing->bid_type);
        }
        
        // Match project type
        if ($bidListing->project_type) {
            $query->where('bid_listings.project_type', $bidListing->project_type);
        }
    }

    /**
     * Enrich listings based on their type
     *
     * @param array $listings
     * @param string $listingType
     * @return array
     */
    private function enrichListingsByType($listings, $listingType)
    {
        switch ($listingType) {
            case 'real_estate':
                return $this->enrichRealEstateListings($listings);
            case 'vehicle':
                return $this->enrichVehicleListings($listings);
            case 'service':
                return $this->enrichServiceListings($listings);
            case 'job':
                return $this->enrichJobListings($listings);
            default:
                return $this->enrichListings($listings);
        }
    }
} 


