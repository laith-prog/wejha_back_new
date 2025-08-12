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

class JobController extends Controller
{
    /**
     * Display a listing of job listings.
     */
    public function index(Request $request)
    {
        // Get the job category ID
        $jobCategory = DB::table('listing_categories')
            ->where('name', 'job')
            ->first();
            
        if (!$jobCategory) {
            return response()->json([
                'success' => false,
                'message' => 'Job category not found'
            ], 404);
        }
        
        $query = DB::table('listings')
            ->join('job_listings', 'listings.id', '=', 'job_listings.listing_id')
            ->leftJoin('listing_subcategories', 'listings.subcategory_id', '=', 'listing_subcategories.id')
            ->where('listings.category_id', $jobCategory->id)
            ->select(
                'listings.*', 
                'job_listings.*',
                'listing_subcategories.name as job_category_name',
                'listing_subcategories.display_name as job_category_display_name'
            );
        
        // Apply filters
        if ($request->has('subcategory_id')) {
            $query->where('listings.subcategory_id', $request->subcategory_id);
        }
        
        if ($request->has('job_type')) {
            $query->where('job_listings.job_type', $request->job_type);
        }
        
        if ($request->has('attendance_type')) {
            $query->where('job_listings.attendance_type', $request->attendance_type);
        }
        
        if ($request->has('min_salary')) {
            $query->where('job_listings.salary', '>=', $request->min_salary);
        }
        
        if ($request->has('max_salary')) {
            $query->where('job_listings.salary', '<=', $request->max_salary);
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
     * Show the form for creating a new job listing.
     */
    public function create()
    {
        // Get job category
        $jobCategory = DB::table('listing_categories')
            ->where('name', 'job')
            ->first();
            
        if (!$jobCategory) {
            return response()->json([
                'success' => false,
                'message' => 'Job category not found'
            ], 404);
        }
        
        // Get job types (subcategories)
        $jobTypes = DB::table('listing_subcategories')
            ->where('category_id', $jobCategory->id)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();
        
        return response()->json([
            'success' => true,
            'category' => $jobCategory,
            'job_types' => $jobTypes
        ]);
    }

    /**
     * Store a newly created job listing in storage.
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
            // Job specific validations
            'company_name' => 'required|string|max:255',
            'job_type' => 'required|string|max:50',
            'employment_type' => 'required|string|max:50',
            'experience_level' => 'required|string|max:50',
            'education_level' => 'required|string|max:100',
            'salary_range' => 'nullable|string|max:100',
            'application_deadline' => 'nullable|date',
            'benefits' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        try {
            // Get the job category ID
            $jobCategory = DB::table('listing_categories')
                ->where('name', 'job')
                ->first();
                
            if (!$jobCategory) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job category not found'
                ], 404);
            }
            
            DB::beginTransaction();
            
            // Create the base listing
            $postNumber = 'JB-' . Str::random(8);
            
            $listing = [
                'user_id' => Auth::id(),
                'title' => $request->title,
                'description' => $request->description,
                'price' => $request->price,
                'price_type' => $request->price_type,
                'currency' => $request->currency,
                'post_number' => $postNumber,
                'phone_number' => $request->phone_number,
                'category_id' => $jobCategory->id,
                'subcategory_id' => $request->subcategory_id,
                'listing_type' => 'job',
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
            
            // Create the job specific listing
            $jobListing = [
                'listing_id' => $listingId,
                'job_title' => $request->job_title,
                'company_name' => $request->company_name,
                'job_type' => $request->job_type,
                'attendance_type' => $request->attendance_type,
                'job_category' => $request->job_category,
                'job_subcategory' => $request->job_subcategory,
                'gender_preference' => $request->gender_preference,
                'salary' => $request->salary,
                'salary_period' => $request->salary_period,
                'salary_currency' => $request->salary_currency ?? 'USD',
                'is_salary_negotiable' => $request->is_salary_negotiable ?? false,
                'experience_years_min' => $request->experience_years_min,
                'education_level' => $request->education_level,
                'required_language' => $request->required_language,
                'company_size' => $request->company_size,
                'benefits' => $request->benefits ? json_encode($request->benefits) : null,
                'application_link' => $request->application_link,
                'created_at' => now(),
                'updated_at' => now()
            ];
            
            DB::table('job_listings')->insert($jobListing);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Job listing created successfully',
                'data' => [
                    'listing_id' => $listingId,
                    'post_number' => $postNumber
                ]
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create job listing',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified job listing.
     */
    public function show($id)
    {
        $listing = DB::table('listings')
            ->join('job_listings', 'listings.id', '=', 'job_listings.listing_id')
            ->leftJoin('listing_categories', 'listings.category_id', '=', 'listing_categories.id')
            ->leftJoin('listing_subcategories', 'listings.subcategory_id', '=', 'listing_subcategories.id')
            ->where('listings.id', $id)
            ->select(
                'listings.*', 
                'job_listings.*',
                'listing_categories.name as category_name',
                'listing_categories.display_name as category_display_name',
                'listing_subcategories.name as subcategory_name',
                'listing_subcategories.display_name as subcategory_display_name'
            )
            ->first();
            
        if (!$listing) {
            return response()->json([
                'success' => false,
                'message' => 'Job listing not found'
            ], 404);
        }
        
        // Increment view count
        DB::table('listings')
            ->where('id', $id)
            ->increment('views_count');
            
        return response()->json([
            'success' => true,
            'data' => $listing
        ]);
    }

    /**
     * Search for job listings based on criteria.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function search(Request $request)
    {
        // Get the job category ID
        $jobCategory = DB::table('listing_categories')
            ->where('name', 'job')
            ->first();
            
        if (!$jobCategory) {
            return response()->json([
                'success' => false,
                'message' => 'Job category not found'
            ], 404);
        }
        
        $query = DB::table('listings')
            ->join('job_listings', 'listings.id', '=', 'job_listings.listing_id')
            ->where('listings.category_id', $jobCategory->id)
            ->select('listings.*', 'job_listings.*');
            
        // Apply filters
        if ($request->has('subcategory_id')) {
            $query->where('listings.subcategory_id', $request->subcategory_id);
        }
        
        if ($request->has('job_type')) {
            $query->where('job_listings.job_type', $request->job_type);
        }
        
        if ($request->has('employment_type')) {
            $query->where('job_listings.employment_type', $request->employment_type);
        }
        
        if ($request->has('experience_level')) {
            $query->where('job_listings.experience_level', $request->experience_level);
        }
        
        if ($request->has('education_level')) {
            $query->where('job_listings.education_level', $request->education_level);
        }
        
        if ($request->has('company_name')) {
            $query->where('job_listings.company_name', 'like', '%' . $request->company_name . '%');
        }
        
        if ($request->has('min_salary')) {
            $query->where('listings.price', '>=', $request->min_salary);
        }
        
        if ($request->has('max_salary')) {
            $query->where('listings.price', '<=', $request->max_salary);
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