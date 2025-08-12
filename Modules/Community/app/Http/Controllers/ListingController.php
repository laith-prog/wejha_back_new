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
use Modules\Community\Models\Listing;

class ListingController extends Controller
{
    /**
     * Display a listing of listings with optional filtering.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = DB::table('listings')
            ->leftJoin('listing_categories', 'listings.category_id', '=', 'listing_categories.id')
            ->leftJoin('listing_subcategories', 'listings.subcategory_id', '=', 'listing_subcategories.id')
            ->select(
                'listings.*', 
                'listing_categories.name as category_name',
                'listing_categories.display_name as category_display_name',
                'listing_subcategories.name as subcategory_name',
                'listing_subcategories.display_name as subcategory_display_name'
            );
            
        // Apply category filter if provided
        if ($request->has('category_id')) {
            $query->where('listings.category_id', $request->category_id);
        }
        
        // Apply subcategory filter if provided
        if ($request->has('subcategory_id')) {
            $query->where('listings.subcategory_id', $request->subcategory_id);
        }
        
        // Apply status filter
        if ($request->has('status')) {
            $query->where('listings.status', $request->status);
        } else {
            $query->where('listings.status', 'active');
        }
        
        // Apply search filter
        if ($request->has('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function($q) use ($search) {
                $q->where('listings.title', 'like', $search)
                  ->orWhere('listings.description', 'like', $search);
            });
        }
        
        // Apply sorting
        $sortBy = $request->sort_by ?? 'created_at';
        $sortDirection = $request->sort_direction ?? 'desc';
        $query->orderBy("listings.$sortBy", $sortDirection);
        
        // Paginate results
        $perPage = $request->per_page ?? 10;
        $listings = $query->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => $listings->items(),
            'pagination' => [
                'total' => $listings->total(),
                'per_page' => $listings->perPage(),
                'current_page' => $listings->currentPage(),
                'last_page' => $listings->lastPage(),
            ]
        ]);
    }

    /**
     * Get all available categories and subcategories.
     *
     * @return \Illuminate\Http\Response
     */
    public function getCategories()
    {
        $categories = DB::table('listing_categories')
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();
            
        $categoriesWithSubcategories = [];
        
        foreach ($categories as $category) {
            $subcategories = DB::table('listing_subcategories')
                ->where('category_id', $category->id)
                ->where('is_active', true)
                ->orderBy('display_order')
                ->get();
                
            $categoriesWithSubcategories[] = [
                'id' => $category->id,
                'name' => $category->name,
                'display_name' => $category->display_name,
                'icon' => $category->icon,
                'description' => $category->description,
                'subcategories' => $subcategories
            ];
        }
        
        return response()->json([
            'success' => true,
            'data' => $categoriesWithSubcategories
        ]);
    }

    /**
     * Display the specified listing.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $listing = DB::table('listings')
            ->leftJoin('listing_categories', 'listings.category_id', '=', 'listing_categories.id')
            ->leftJoin('listing_subcategories', 'listings.subcategory_id', '=', 'listing_subcategories.id')
            ->where('listings.id', $id)
            ->select(
                'listings.*', 
                'listing_categories.name as category_name',
                'listing_categories.display_name as category_display_name',
                'listing_subcategories.name as subcategory_name',
                'listing_subcategories.display_name as subcategory_display_name'
            )
            ->first();
            
        if (!$listing) {
            return response()->json([
                'success' => false,
                'message' => 'Listing not found'
            ], 404);
        }
        
        // Get listing images
        $images = DB::table('listing_images')
            ->where('listing_id', $id)
            ->orderBy('display_order')
            ->get();
            
        // Get category-specific details
        $specificDetails = $this->getSpecificListingDetails($listing->category_name, $id);
            
        // Increment view count
        DB::table('listings')
            ->where('id', $id)
            ->increment('views_count');
            
        return response()->json([
            'success' => true,
            'data' => [
                'listing' => $listing,
                'specific_details' => $specificDetails,
                'images' => $images
            ]
        ]);
    }

    /**
     * Get category-specific listing details.
     *
     * @param  string  $categoryName
     * @param  int  $listingId
     * @return object|null
     */
    protected function getSpecificListingDetails($categoryName, $listingId)
    {
        $tableName = $this->getCategoryTableName($categoryName);
        
        if (!$tableName) {
            return null;
        }
        
        return DB::table($tableName)
            ->where('listing_id', $listingId)
            ->first();
    }

    /**
     * Get the table name for a specific category.
     *
     * @param  string  $categoryName
     * @return string|null
     */
    protected function getCategoryTableName($categoryName)
    {
        $tableMap = [
            'real_estate' => 'real_estate_listings',
            'vehicle' => 'vehicle_listings',
            'service' => 'service_listings',
            'job' => 'job_listings',
            'bid' => 'bid_listings'
        ];
        
        return $tableMap[$categoryName] ?? null;
    }

    /**
     * Toggle favorite status for a listing.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function toggleFavorite($id)
    {
        $userId = Auth::id();
        
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
     * Report a listing.
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
        
        $userId = Auth::id();
        
        // Check if listing exists
        $listing = DB::table('listings')
            ->where('id', $id)
            ->first();
            
        if (!$listing) {
            return response()->json([
                'success' => false,
                'message' => 'Listing not found'
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

    /**
     * Remove the specified listing from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $listing = DB::table('listings')
                ->where('id', $id)
                ->where('user_id', Auth::id())
                ->first();
                
            if (!$listing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Listing not found or you do not have permission to delete it'
                ], 404);
            }
            
            DB::beginTransaction();
            
            // Delete images from storage
            $images = DB::table('listing_images')
                ->where('listing_id', $id)
                ->get();
                
            foreach ($images as $image) {
                Storage::disk('public')->delete($image->image_path);
                if ($image->thumbnail_path && $image->thumbnail_path !== $image->image_path) {
                    Storage::disk('public')->delete($image->thumbnail_path);
                }
            }
            
            // Delete related records based on category
            $tableName = $this->getCategoryTableName($listing->listing_type);
            if ($tableName) {
                DB::table($tableName)->where('listing_id', $id)->delete();
            }
            
            // Delete common related records
            DB::table('listing_images')->where('listing_id', $id)->delete();
            DB::table('user_favorites')->where('listing_id', $id)->delete();
            DB::table('listing_reports')->where('listing_id', $id)->delete();
            DB::table('listing_inquiries')->where('listing_id', $id)->delete();
            
            // Delete the main listing
            DB::table('listings')->where('id', $id)->delete();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Listing deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete listing',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 