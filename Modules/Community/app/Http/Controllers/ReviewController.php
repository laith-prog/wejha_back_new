<?php

namespace Modules\Community\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReviewController extends Controller
{
    /**
     * Create a review for a service provider
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createServiceProviderReview(Request $request)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'provider_id' => 'required|exists:users,id',
                'rating' => 'required|integer|min:1|max:5',
                'comment' => 'nullable|string|max:500',
                'listing_id' => 'nullable|exists:listings,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if the service provider exists and has the service_provider role
            $serviceProvider = DB::table('users')
                ->join('roles', 'users.role_id', '=', 'roles.id')
                ->where('users.id', $request->provider_id)
                ->where('roles.name', 'service_provider')
                ->first();

            if (!$serviceProvider) {
                return response()->json([
                    'success' => false,
                    'message' => 'Service provider not found'
                ], 404);
            }

            // Check if the user has already reviewed this service provider
            $existingReview = DB::table('service_provider_reviews')
                ->where('user_id', Auth::id())
                ->where('provider_id', $request->provider_id)
                ->first();

            if ($existingReview) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already reviewed this service provider',
                    'review_id' => $existingReview->id
                ], 409);
            }

            // Create the review
            $reviewId = DB::table('service_provider_reviews')->insertGetId([
                'user_id' => Auth::id(),
                'provider_id' => $request->provider_id,
                'rating' => $request->rating,
                'comment' => $request->comment,
                'listing_id' => $request->listing_id,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Update service provider stats
            $this->updateServiceProviderStats($request->provider_id);

            return response()->json([
                'success' => true,
                'message' => 'Review created successfully',
                'data' => [
                    'review_id' => $reviewId
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create review',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a review for a listing
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createListingReview(Request $request)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'listing_id' => 'required|exists:listings,id',
                'rating' => 'required|integer|min:1|max:5',
                'comment' => 'nullable|string|max:500',
                'title' => 'nullable|string|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if the listing exists and is active
            $listing = DB::table('listings')
                ->where('id', $request->listing_id)
                ->where('status', 'active')
                ->first();

            if (!$listing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Listing not found or inactive'
                ], 404);
            }

            // Check if the user has already reviewed this listing
            $existingReview = DB::table('listing_reviews')
                ->where('user_id', Auth::id())
                ->where('listing_id', $request->listing_id)
                ->first();

            if ($existingReview) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already reviewed this listing',
                    'review_id' => $existingReview->id
                ], 409);
            }

            // Create the review
            $reviewId = DB::table('listing_reviews')->insertGetId([
                'user_id' => Auth::id(),
                'listing_id' => $request->listing_id,
                'rating' => $request->rating,
                'title' => $request->title,
                'comment' => $request->comment,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Update listing stats
            $this->updateListingStats($request->listing_id);

            return response()->json([
                'success' => true,
                'message' => 'Review created successfully',
                'data' => [
                    'review_id' => $reviewId
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create review',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get reviews for a service provider
     *
     * @param Request $request
     * @param string $serviceProviderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getServiceProviderReviews(Request $request, $serviceProviderId)
    {
        try {
            // Check if the service provider exists
            $serviceProvider = DB::table('users')
                ->where('id', $serviceProviderId)
                ->first();

            if (!$serviceProvider) {
                return response()->json([
                    'success' => false,
                    'message' => 'Service provider not found'
                ], 404);
            }

            // Get reviews
            $query = DB::table('service_provider_reviews')
                ->where('provider_id', $serviceProviderId)
                ->join('users', 'service_provider_reviews.user_id', '=', 'users.id')
                ->select(
                    'service_provider_reviews.id',
                    'service_provider_reviews.rating',
                    'service_provider_reviews.comment',
                    'service_provider_reviews.created_at',
                    'users.id as user_id',
                    'users.name as user_name',
                    'users.profile_image'
                );

            // Apply filters
            if ($request->has('min_rating')) {
                $query->where('service_provider_reviews.rating', '>=', $request->min_rating);
            }

            // Sort
            $sortBy = $request->sort_by ?? 'created_at';
            $sortDirection = $request->sort_direction ?? 'desc';
            $query->orderBy("service_provider_reviews.$sortBy", $sortDirection);

            // Paginate
            $perPage = $request->per_page ?? 10;
            $page = $request->page ?? 1;
            $reviews = $query->paginate($perPage, ['*'], 'page', $page);

            // Get stats
            $stats = $this->getServiceProviderReviewStats($serviceProviderId);

            return response()->json([
                'success' => true,
                'data' => [
                    'reviews' => $reviews->items(),
                    'stats' => $stats,
                    'pagination' => [
                        'total' => $reviews->total(),
                        'per_page' => $reviews->perPage(),
                        'current_page' => $reviews->currentPage(),
                        'last_page' => $reviews->lastPage(),
                        'total_results' => $reviews->total()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get reviews',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get reviews for a listing
     *
     * @param Request $request
     * @param int $listingId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getListingReviews(Request $request, $listingId)
    {
        try {
            // Check if the listing exists
            $listing = DB::table('listings')
                ->where('id', $listingId)
                ->first();

            if (!$listing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Listing not found'
                ], 404);
            }

            // Get reviews
            $query = DB::table('listing_reviews')
                ->where('listing_id', $listingId)
                ->join('users', 'listing_reviews.user_id', '=', 'users.id')
                ->select(
                    'listing_reviews.id',
                    'listing_reviews.rating',
                    'listing_reviews.title',
                    'listing_reviews.comment',
                    'listing_reviews.created_at',
                    'users.id as user_id',
                    'users.name as user_name',
                    'users.profile_image'
                );

            // Apply filters
            if ($request->has('min_rating')) {
                $query->where('listing_reviews.rating', '>=', $request->min_rating);
            }

            // Sort
            $sortBy = $request->sort_by ?? 'created_at';
            $sortDirection = $request->sort_direction ?? 'desc';
            $query->orderBy("listing_reviews.$sortBy", $sortDirection);

            // Paginate
            $perPage = $request->per_page ?? 10;
            $page = $request->page ?? 1;
            $reviews = $query->paginate($perPage, ['*'], 'page', $page);

            // Get stats
            $stats = $this->getListingReviewStats($listingId);

            return response()->json([
                'success' => true,
                'data' => [
                    'reviews' => $reviews->items(),
                    'stats' => $stats,
                    'pagination' => [
                        'total' => $reviews->total(),
                        'per_page' => $reviews->perPage(),
                        'current_page' => $reviews->currentPage(),
                        'last_page' => $reviews->lastPage(),
                        'total_results' => $reviews->total()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get reviews',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a service provider review
     *
     * @param Request $request
     * @param int $reviewId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateServiceProviderReview(Request $request, $reviewId)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'rating' => 'required|integer|min:1|max:5',
                'comment' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if the review exists and belongs to the authenticated user
            $review = DB::table('service_provider_reviews')
                ->where('id', $reviewId)
                ->where('user_id', Auth::id())
                ->first();

            if (!$review) {
                return response()->json([
                    'success' => false,
                    'message' => 'Review not found or you are not authorized to update it'
                ], 404);
            }

            // Update the review
            DB::table('service_provider_reviews')
                ->where('id', $reviewId)
                ->update([
                    'rating' => $request->rating,
                    'comment' => $request->comment,
                    'updated_at' => now()
                ]);

            // Update service provider stats
            $this->updateServiceProviderStats($review->provider_id);

            return response()->json([
                'success' => true,
                'message' => 'Review updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update review',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a listing review
     *
     * @param Request $request
     * @param int $reviewId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateListingReview(Request $request, $reviewId)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'rating' => 'required|integer|min:1|max:5',
                'comment' => 'nullable|string|max:500',
                'title' => 'nullable|string|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if the review exists and belongs to the authenticated user
            $review = DB::table('listing_reviews')
                ->where('id', $reviewId)
                ->where('user_id', Auth::id())
                ->first();

            if (!$review) {
                return response()->json([
                    'success' => false,
                    'message' => 'Review not found or you are not authorized to update it'
                ], 404);
            }

            // Update the review
            DB::table('listing_reviews')
                ->where('id', $reviewId)
                ->update([
                    'rating' => $request->rating,
                    'title' => $request->title,
                    'comment' => $request->comment,
                    'updated_at' => now()
                ]);

            // Update listing stats
            $this->updateListingStats($review->listing_id);

            return response()->json([
                'success' => true,
                'message' => 'Review updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update review',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a service provider review
     *
     * @param int $reviewId
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteServiceProviderReview($reviewId)
    {
        try {
            // Check if the review exists and belongs to the authenticated user
            $review = DB::table('service_provider_reviews')
                ->where('id', $reviewId)
                ->where('user_id', Auth::id())
                ->first();

            if (!$review) {
                return response()->json([
                    'success' => false,
                    'message' => 'Review not found or you are not authorized to delete it'
                ], 404);
            }

            // Delete the review
            DB::table('service_provider_reviews')
                ->where('id', $reviewId)
                ->delete();

            // Update service provider stats
            $this->updateServiceProviderStats($review->provider_id);

            return response()->json([
                'success' => true,
                'message' => 'Review deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete review',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a listing review
     *
     * @param int $reviewId
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteListingReview($reviewId)
    {
        try {
            // Check if the review exists and belongs to the authenticated user
            $review = DB::table('listing_reviews')
                ->where('id', $reviewId)
                ->where('user_id', Auth::id())
                ->first();

            if (!$review) {
                return response()->json([
                    'success' => false,
                    'message' => 'Review not found or you are not authorized to delete it'
                ], 404);
            }

            // Delete the review
            DB::table('listing_reviews')
                ->where('id', $reviewId)
                ->delete();

            // Update listing stats
            $this->updateListingStats($review->listing_id);

            return response()->json([
                'success' => true,
                'message' => 'Review deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete review',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update service provider stats
     *
     * @param string $serviceProviderId
     * @return void
     */
    private function updateServiceProviderStats($serviceProviderId)
    {
        // Calculate average rating and total reviews
        $stats = DB::table('service_provider_reviews')
            ->where('provider_id', $serviceProviderId)
            ->selectRaw('COUNT(*) as total_reviews, AVG(rating) as average_rating')
            ->first();

        // Calculate rating distribution
        $distribution = [];
        for ($i = 1; $i <= 5; $i++) {
            $count = DB::table('service_provider_reviews')
                ->where('provider_id', $serviceProviderId)
                ->where('rating', $i)
                ->count();
            $distribution[$i] = $count;
        }

        // Update or create service provider stats
        $existingStats = DB::table('service_provider_stats')
            ->where('user_id', $serviceProviderId)
            ->first();

        if ($existingStats) {
            DB::table('service_provider_stats')
                ->where('user_id', $serviceProviderId)
                ->update([
                    'average_rating' => round($stats->average_rating, 1) ?? 0,
                    'total_reviews' => $stats->total_reviews ?? 0,
                    'rating_distribution' => json_encode($distribution),
                    'updated_at' => now()
                ]);
        } else {
            DB::table('service_provider_stats')
                ->insert([
                    'user_id' => $serviceProviderId,
                    'average_rating' => round($stats->average_rating, 1) ?? 0,
                    'total_reviews' => $stats->total_reviews ?? 0,
                    'rating_distribution' => json_encode($distribution),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
        }
    }

    /**
     * Update listing stats
     *
     * @param int $listingId
     * @return void
     */
    private function updateListingStats($listingId)
    {
        // Calculate average rating and total reviews
        $stats = DB::table('listing_reviews')
            ->where('listing_id', $listingId)
            ->selectRaw('COUNT(*) as total_reviews, AVG(rating) as average_rating')
            ->first();

        // Update listing with review stats
        DB::table('listings')
            ->where('id', $listingId)
            ->update([
                'average_rating' => round($stats->average_rating, 1) ?? 0,
                'reviews_count' => $stats->total_reviews ?? 0,
                'updated_at' => now()
            ]);
    }

    /**
     * Get service provider review stats
     *
     * @param string $serviceProviderId
     * @return array
     */
    private function getServiceProviderReviewStats($serviceProviderId)
    {
        $stats = DB::table('service_provider_stats')
            ->where('user_id', $serviceProviderId)
            ->first();

        if (!$stats) {
            return [
                'average_rating' => 0,
                'total_reviews' => 0,
                'rating_distribution' => [
                    '1' => 0,
                    '2' => 0,
                    '3' => 0,
                    '4' => 0,
                    '5' => 0
                ]
            ];
        }

        return [
            'average_rating' => $stats->average_rating,
            'total_reviews' => $stats->total_reviews,
            'rating_distribution' => json_decode($stats->rating_distribution, true)
        ];
    }

    /**
     * Get listing review stats
     *
     * @param int $listingId
     * @return array
     */
    private function getListingReviewStats($listingId)
    {
        $listing = DB::table('listings')
            ->where('id', $listingId)
            ->select('average_rating', 'reviews_count')
            ->first();

        if (!$listing) {
            return [
                'average_rating' => 0,
                'total_reviews' => 0
            ];
        }

        // Calculate rating distribution
        $distribution = [];
        for ($i = 1; $i <= 5; $i++) {
            $count = DB::table('listing_reviews')
                ->where('listing_id', $listingId)
                ->where('rating', $i)
                ->count();
            $distribution[$i] = $count;
        }

        return [
            'average_rating' => $listing->average_rating,
            'total_reviews' => $listing->reviews_count,
            'rating_distribution' => $distribution
        ];
    }
} 