<?php

namespace Modules\Community\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    /**
     * Get community home screen information
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Get featured listings (across all categories)
            $featuredListings = $this->_getFeaturedListings();

            // Get recent listings (across all categories)
            $recentListings = $this->_getRecentListings();

            // Get popular listings (most viewed)
            $popularListings = $this->_getPopularListings();

            // Get listing categories with counts
            $categories = $this->getCategories();

            // Get nearby listings if location is provided
            $nearbyListings = null;
            if ($request->has('lat') && $request->has('lng')) {
                $nearbyListings = $this->_getNearbyListings($request->lat, $request->lng, $request->radius ?? 10);
            }

            // Get recommended listings based on user preferences if authenticated
            $recommendedListings = null;
            if (Auth::check()) {
                $recommendedListings = $this->_getRecommendedListings();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'featured_listings' => $featuredListings,
                    'recent_listings' => $recentListings,
                    'popular_listings' => $popularListings,
                    'categories' => $categories,
                    'nearby_listings' => $nearbyListings,
                    'recommended_listings' => $recommendedListings
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve home screen data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get featured listings
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFeaturedListings(Request $request)
    {
        try {
            $limit = $request->limit ?? 10;

            $listings = DB::table('listings')
                ->where('is_featured', true)
                ->where('status', 'active')
                ->orderBy('promoted_until', 'desc')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $this->enrichListings($listings)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve featured listings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get featured listings (private method for internal use)
     *
     * @param int $limit
     * @return array
     */
    private function _getFeaturedListings($limit = 10)
    {
        $listings = DB::table('listings')
            ->where('is_featured', true)
            ->where('status', 'active')
            ->orderBy('promoted_until', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $this->enrichListings($listings);
    }

    /**
     * Get recent listings
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRecentListings(Request $request)
    {
        try {
            $limit = $request->limit ?? 10;

            $listings = DB::table('listings')
                ->where('status', 'active')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $this->enrichListings($listings)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve recent listings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recent listings (private method for internal use)
     *
     * @param int $limit
     * @return array
     */
    private function _getRecentListings($limit = 10)
    {
        $listings = DB::table('listings')
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $this->enrichListings($listings);
    }

    /**
     * Get popular listings (most viewed)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPopularListings(Request $request)
    {
        try {
            $limit = $request->limit ?? 10;

            $listings = DB::table('listings')
                ->where('status', 'active')
                ->orderBy('views_count', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $this->enrichListings($listings)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve popular listings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get popular listings (most viewed) (private method for internal use)
     *
     * @param int $limit
     * @return array
     */
    private function _getPopularListings($limit = 10)
    {
        $listings = DB::table('listings')
            ->where('status', 'active')
            ->orderBy('views_count', 'desc')
            ->limit($limit)
            ->get();

        return $this->enrichListings($listings);
    }

    /**
     * Get nearby listings based on location
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getNearbyListings(Request $request)
    {
        try {
            $request->validate([
                'lat' => 'required|numeric',
                'lng' => 'required|numeric',
                'radius' => 'nullable|numeric|min:1|max:100',
                'limit' => 'nullable|integer|min:1|max:50',
            ]);

            $lat = $request->lat;
            $lng = $request->lng;
            $radius = $request->radius ?? 10;
            $limit = $request->limit ?? 10;

            $listings = DB::table('listings')
                ->where('status', 'active')
                ->selectRaw(
                    '*, (6371 * acos(cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat)))) AS distance',
                    [$lat, $lng, $lat]
                )
                ->having('distance', '<=', $radius)
                ->orderBy('distance')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $this->enrichListings($listings)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve nearby listings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get nearby listings based on location (private method for internal use)
     *
     * @param float $lat
     * @param float $lng
     * @param int $radius
     * @param int $limit
     * @return array
     */
    private function _getNearbyListings($lat, $lng, $radius = 10, $limit = 10)
    {
        $listings = DB::table('listings')
            ->where('status', 'active')
            ->selectRaw(
                '*, (6371 * acos(cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat)))) AS distance',
                [$lat, $lng, $lat]
            )
            ->having('distance', '<=', $radius)
            ->orderBy('distance')
            ->limit($limit)
            ->get();

        return $this->enrichListings($listings);
    }

    /**
     * Get recommended listings based on user preferences
     * If user is not authenticated, returns popular listings
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRecommendedListings(Request $request)
    {
        try {
            $limit = $request->limit ?? 10;

            // If user is not authenticated, return popular listings instead
            if (!Auth::check()) {
                $listings = DB::table('listings')
                    ->where('status', 'active')
                    ->orderBy('views_count', 'desc')
                    ->limit($limit)
                    ->get();

                return response()->json([
                    'success' => true,
                    'data' => $this->enrichListings($listings),
                    'message' => 'Showing popular listings. Log in for personalized recommendations.'
                ]);
            }

            $user = Auth::user();

            // Get user's favorite categories
            $favoriteCategories = DB::table('user_favorites')
                ->join('listings', 'user_favorites.listing_id', '=', 'listings.id')
                ->where('user_favorites.user_id', $user->id)
                ->select('listings.category_id')
                ->distinct()
                ->pluck('category_id')
                ->toArray();

            // If user has no favorites, return popular listings
            if (empty($favoriteCategories)) {
                $listings = DB::table('listings')
                    ->where('status', 'active')
                    ->orderBy('views_count', 'desc')
                    ->limit($limit)
                    ->get();
            } else {
                // Get listings from user's favorite categories
                $listings = DB::table('listings')
                    ->whereIn('category_id', $favoriteCategories)
                    ->where('status', 'active')
                    ->where('user_id', '!=', $user->id) // Exclude user's own listings
                    ->orderBy('created_at', 'desc')
                    ->limit($limit)
                    ->get();
            }

            return response()->json([
                'success' => true,
                'data' => $this->enrichListings($listings)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve recommended listings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recommended listings based on user preferences (private method for internal use)
     *
     * @param int $limit
     * @return array
     */
    private function _getRecommendedListings($limit = 10)
    {
        $user = Auth::user();

        // Get user's favorite categories
        $favoriteCategories = DB::table('user_favorites')
            ->join('listings', 'user_favorites.listing_id', '=', 'listings.id')
            ->where('user_favorites.user_id', $user->id)
            ->select('listings.category_id')
            ->distinct()
            ->pluck('category_id')
            ->toArray();

        // If user has no favorites, return popular listings
        if (empty($favoriteCategories)) {
            return $this->_getPopularListings($limit);
        }

        // Get listings from user's favorite categories
        $listings = DB::table('listings')
            ->whereIn('category_id', $favoriteCategories)
            ->where('status', 'active')
            ->where('user_id', '!=', $user->id) // Exclude user's own listings
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $this->enrichListings($listings);
    }

    /**
     * Get listing categories with counts
     *
     * @return array
     */
    private function getCategories()
    {
        return DB::table('listing_categories')
            ->leftJoin('listings', 'listing_categories.id', '=', 'listings.category_id')
            ->select(
                'listing_categories.id',
                'listing_categories.name',
                'listing_categories.display_name',
                'listing_categories.icon',
                DB::raw('COUNT(listings.id) as listing_count')
            )
            ->where('listing_categories.is_active', true)
            ->groupBy('listing_categories.id', 'listing_categories.name', 'listing_categories.display_name', 'listing_categories.icon')
            ->orderBy('listing_categories.display_order')
            ->get();
    }

    /**
     * Enrich listings with additional data
     *
     * @param \Illuminate\Support\Collection $listings
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
     * Get listings by category
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getListingsByCategory(Request $request)
    {
        try {
            $request->validate([
                'category_id' => 'required|exists:listing_categories,id',
                'limit' => 'nullable|integer|min:1|max:50',
                'page' => 'nullable|integer|min:1',
            ]);

            $limit = $request->limit ?? 10;
            $page = $request->page ?? 1;
            $offset = ($page - 1) * $limit;

            // Get listings by category
            $listings = DB::table('listings')
                ->where('category_id', $request->category_id)
                ->where('status', 'active')
                ->orderBy('created_at', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->get();

            // Get total count
            $totalCount = DB::table('listings')
                ->where('category_id', $request->category_id)
                ->where('status', 'active')
                ->count();

            // Get category info
            $category = DB::table('listing_categories')
                ->where('id', $request->category_id)
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'listings' => $this->enrichListings($listings),
                    'category' => $category,
                    'pagination' => [
                        'total' => $totalCount,
                        'per_page' => $limit,
                        'current_page' => $page,
                        'last_page' => ceil($totalCount / $limit),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve listings by category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get listings by subcategory
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getListingsBySubcategory(Request $request)
    {
        try {
            $request->validate([
                'subcategory_id' => 'required|exists:listing_subcategories,id',
                'limit' => 'nullable|integer|min:1|max:50',
                'page' => 'nullable|integer|min:1',
            ]);

            $limit = $request->limit ?? 10;
            $page = $request->page ?? 1;
            $offset = ($page - 1) * $limit;

            // Get listings by subcategory
            $listings = DB::table('listings')
                ->where('subcategory_id', $request->subcategory_id)
                ->where('status', 'active')
                ->orderBy('created_at', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->get();

            // Get total count
            $totalCount = DB::table('listings')
                ->where('subcategory_id', $request->subcategory_id)
                ->where('status', 'active')
                ->count();

            // Get subcategory info
            $subcategory = DB::table('listing_subcategories')
                ->where('id', $request->subcategory_id)
                ->first();

            // Get parent category
            $category = DB::table('listing_categories')
                ->where('id', $subcategory->category_id)
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'listings' => $this->enrichListings($listings),
                    'subcategory' => $subcategory,
                    'category' => $category,
                    'pagination' => [
                        'total' => $totalCount,
                        'per_page' => $limit,
                        'current_page' => $page,
                        'last_page' => ceil($totalCount / $limit),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve listings by subcategory',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
