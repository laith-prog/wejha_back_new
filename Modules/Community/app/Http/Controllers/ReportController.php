<?php

namespace Modules\Community\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    /**
     * Create a report for a listing
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function reportListing(Request $request)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'listing_id' => 'required|exists:listings,id',
                'reason' => 'required|string|in:inappropriate,spam,fraud,duplicate,wrong_category,other',
                'details' => 'nullable|string|max:500'
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
                ->first();

            if (!$listing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Listing not found'
                ], 404);
            }

            // Check if the user has already reported this listing
            $existingReport = DB::table('listing_reports')
                ->where('user_id', Auth::id())
                ->where('listing_id', $request->listing_id)
                ->first();

            if ($existingReport) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already reported this listing',
                    'report_id' => $existingReport->id
                ], 409);
            }

            // Create the report
            $reportId = DB::table('listing_reports')->insertGetId([
                'user_id' => Auth::id(),
                'listing_id' => $request->listing_id,
                'reason' => $request->reason,
                'details' => $request->details,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Update listing reports count
            DB::table('listings')
                ->where('id', $request->listing_id)
                ->increment('reports_count');

            // Check if reports threshold is reached to automatically flag the listing
            $reportsCount = DB::table('listing_reports')
                ->where('listing_id', $request->listing_id)
                ->count();

            if ($reportsCount >= 5) { // Threshold for automatic flagging
                DB::table('listings')
                    ->where('id', $request->listing_id)
                    ->update([
                        'is_flagged' => true,
                        'updated_at' => now()
                    ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Report submitted successfully',
                'data' => [
                    'report_id' => $reportId
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a report for a service provider
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function reportServiceProvider(Request $request)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'service_provider_id' => 'required|exists:users,id',
                'reason' => 'required|string|in:inappropriate_behavior,spam,fraud,misrepresentation,other',
                'details' => 'nullable|string|max:500',
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
                ->where('users.id', $request->service_provider_id)
                ->where('roles.name', 'service_provider')
                ->first();

            if (!$serviceProvider) {
                return response()->json([
                    'success' => false,
                    'message' => 'Service provider not found'
                ], 404);
            }

            // Check if the user has already reported this service provider
            $existingReport = DB::table('service_provider_reports')
                ->where('user_id', Auth::id())
                ->where('service_provider_id', $request->service_provider_id)
                ->first();

            if ($existingReport) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already reported this service provider',
                    'report_id' => $existingReport->id
                ], 409);
            }

            // Create the report
            $reportId = DB::table('service_provider_reports')->insertGetId([
                'user_id' => Auth::id(),
                'service_provider_id' => $request->service_provider_id,
                'reason' => $request->reason,
                'details' => $request->details,
                'listing_id' => $request->listing_id,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Update service provider reports count in stats
            $this->updateServiceProviderReportsCount($request->service_provider_id);

            return response()->json([
                'success' => true,
                'message' => 'Report submitted successfully',
                'data' => [
                    'report_id' => $reportId
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get listing reports (Admin only)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getListingReports(Request $request)
    {
        try {
            // Build query
            $query = DB::table('listing_reports')
                ->join('listings', 'listing_reports.listing_id', '=', 'listings.id')
                ->join('users as reporters', 'listing_reports.user_id', '=', 'reporters.id')
                ->join('users as owners', 'listings.user_id', '=', 'owners.id')
                ->select(
                    'listing_reports.id',
                    'listing_reports.reason',
                    'listing_reports.details',
                    'listing_reports.status',
                    'listing_reports.created_at',
                    'listing_reports.reviewed_at',
                    'listing_reports.reviewed_by',
                    'listings.id as listing_id',
                    'listings.title as listing_title',
                    'listings.listing_type',
                    'reporters.id as reporter_id',
                    'reporters.name as reporter_name',
                    'owners.id as owner_id',
                    'owners.name as owner_name'
                );

            // Apply filters
            if ($request->has('status')) {
                $query->where('listing_reports.status', $request->status);
            }

            if ($request->has('listing_id')) {
                $query->where('listing_reports.listing_id', $request->listing_id);
            }

            if ($request->has('listing_type')) {
                $query->where('listings.listing_type', $request->listing_type);
            }

            if ($request->has('reason')) {
                $query->where('listing_reports.reason', $request->reason);
            }

            // Sort
            $sortBy = $request->sort_by ?? 'created_at';
            $sortDirection = $request->sort_direction ?? 'desc';
            $query->orderBy("listing_reports.$sortBy", $sortDirection);

            // Paginate
            $perPage = $request->per_page ?? 10;
            $page = $request->page ?? 1;
            $reports = $query->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'data' => [
                    'reports' => $reports->items(),
                    'pagination' => [
                        'total' => $reports->total(),
                        'per_page' => $reports->perPage(),
                        'current_page' => $reports->currentPage(),
                        'last_page' => $reports->lastPage(),
                        'total_results' => $reports->total()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get reports',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get service provider reports (Admin only)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getServiceProviderReports(Request $request)
    {
        try {
            // Build query
            $query = DB::table('service_provider_reports')
                ->join('users as service_providers', 'service_provider_reports.service_provider_id', '=', 'service_providers.id')
                ->join('users as reporters', 'service_provider_reports.user_id', '=', 'reporters.id')
                ->leftJoin('listings', 'service_provider_reports.listing_id', '=', 'listings.id')
                ->select(
                    'service_provider_reports.id',
                    'service_provider_reports.reason',
                    'service_provider_reports.details',
                    'service_provider_reports.status',
                    'service_provider_reports.created_at',
                    'service_provider_reports.reviewed_at',
                    'service_provider_reports.reviewed_by',
                    'service_providers.id as service_provider_id',
                    'service_providers.name as service_provider_name',
                    'reporters.id as reporter_id',
                    'reporters.name as reporter_name',
                    'listings.id as listing_id',
                    'listings.title as listing_title'
                );

            // Apply filters
            if ($request->has('status')) {
                $query->where('service_provider_reports.status', $request->status);
            }

            if ($request->has('service_provider_id')) {
                $query->where('service_provider_reports.service_provider_id', $request->service_provider_id);
            }

            if ($request->has('reason')) {
                $query->where('service_provider_reports.reason', $request->reason);
            }

            // Sort
            $sortBy = $request->sort_by ?? 'created_at';
            $sortDirection = $request->sort_direction ?? 'desc';
            $query->orderBy("service_provider_reports.$sortBy", $sortDirection);

            // Paginate
            $perPage = $request->per_page ?? 10;
            $page = $request->page ?? 1;
            $reports = $query->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'data' => [
                    'reports' => $reports->items(),
                    'pagination' => [
                        'total' => $reports->total(),
                        'per_page' => $reports->perPage(),
                        'current_page' => $reports->currentPage(),
                        'last_page' => $reports->lastPage(),
                        'total_results' => $reports->total()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get reports',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update listing report status (Admin only)
     *
     * @param Request $request
     * @param int $reportId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateListingReportStatus(Request $request, $reportId)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'status' => 'required|string|in:pending,reviewed,rejected',
                'admin_notes' => 'nullable|string|max:500',
                'action_taken' => 'nullable|string|in:none,warning,temporary_ban,permanent_ban,listing_removed'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if the report exists
            $report = DB::table('listing_reports')
                ->where('id', $reportId)
                ->first();

            if (!$report) {
                return response()->json([
                    'success' => false,
                    'message' => 'Report not found'
                ], 404);
            }

            // Update the report
            DB::table('listing_reports')
                ->where('id', $reportId)
                ->update([
                    'status' => $request->status,
                    'admin_notes' => $request->admin_notes,
                    'action_taken' => $request->action_taken,
                    'reviewed_at' => now(),
                    'reviewed_by' => Auth::id(),
                    'updated_at' => now()
                ]);

            // If action is to remove listing, update listing status
            if ($request->action_taken === 'listing_removed') {
                DB::table('listings')
                    ->where('id', $report->listing_id)
                    ->update([
                        'status' => 'removed',
                        'updated_at' => now()
                    ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Report status updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update report status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update service provider report status (Admin only)
     *
     * @param Request $request
     * @param int $reportId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateServiceProviderReportStatus(Request $request, $reportId)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'status' => 'required|string|in:pending,reviewed,rejected',
                'admin_notes' => 'nullable|string|max:500',
                'action_taken' => 'nullable|string|in:none,warning,temporary_ban,permanent_ban'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if the report exists
            $report = DB::table('service_provider_reports')
                ->where('id', $reportId)
                ->first();

            if (!$report) {
                return response()->json([
                    'success' => false,
                    'message' => 'Report not found'
                ], 404);
            }

            // Update the report
            DB::table('service_provider_reports')
                ->where('id', $reportId)
                ->update([
                    'status' => $request->status,
                    'admin_notes' => $request->admin_notes,
                    'action_taken' => $request->action_taken,
                    'reviewed_at' => now(),
                    'reviewed_by' => Auth::id(),
                    'updated_at' => now()
                ]);

            // If action is to ban user, update user status
            if ($request->action_taken === 'temporary_ban' || $request->action_taken === 'permanent_ban') {
                $banUntil = $request->action_taken === 'temporary_ban' ? now()->addDays(30) : null;
                
                DB::table('users')
                    ->where('id', $report->service_provider_id)
                    ->update([
                        'is_banned' => true,
                        'banned_until' => $banUntil,
                        'updated_at' => now()
                    ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Report status updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update report status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get report reasons for listings
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getListingReportReasons()
    {
        $reasons = [
            ['id' => 'inappropriate', 'name' => 'محتوى غير لائق'],
            ['id' => 'spam', 'name' => 'رسائل مزعجة'],
            ['id' => 'fraud', 'name' => 'احتيال'],
            ['id' => 'duplicate', 'name' => 'إعلان مكرر'],
            ['id' => 'wrong_category', 'name' => 'تصنيف خاطئ'],
            ['id' => 'other', 'name' => 'سبب آخر']
        ];

        return response()->json([
            'success' => true,
            'data' => $reasons
        ]);
    }

    /**
     * Get report reasons for service providers
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getServiceProviderReportReasons()
    {
        $reasons = [
            ['id' => 'inappropriate_behavior', 'name' => 'سلوك غير لائق'],
            ['id' => 'spam', 'name' => 'رسائل مزعجة'],
            ['id' => 'fraud', 'name' => 'احتيال'],
            ['id' => 'misrepresentation', 'name' => 'تزييف المعلومات'],
            ['id' => 'other', 'name' => 'سبب آخر']
        ];

        return response()->json([
            'success' => true,
            'data' => $reasons
        ]);
    }

    /**
     * Update service provider reports count
     *
     * @param string $serviceProviderId
     * @return void
     */
    private function updateServiceProviderReportsCount($serviceProviderId)
    {
        // Count total reports
        $reportsCount = DB::table('service_provider_reports')
            ->where('service_provider_id', $serviceProviderId)
            ->count();

        // Update or create service provider stats
        $existingStats = DB::table('service_provider_stats')
            ->where('user_id', $serviceProviderId)
            ->first();

        if ($existingStats) {
            DB::table('service_provider_stats')
                ->where('user_id', $serviceProviderId)
                ->update([
                    'reports_count' => $reportsCount,
                    'updated_at' => now()
                ]);
        } else {
            DB::table('service_provider_stats')
                ->insert([
                    'user_id' => $serviceProviderId,
                    'reports_count' => $reportsCount,
                    'average_rating' => 0,
                    'total_reviews' => 0,
                    'rating_distribution' => json_encode([
                        '1' => 0,
                        '2' => 0,
                        '3' => 0,
                        '4' => 0,
                        '5' => 0
                    ]),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
        }

        // Check if reports threshold is reached to automatically flag the service provider
        if ($reportsCount >= 10) { // Threshold for automatic flagging
            DB::table('users')
                ->where('id', $serviceProviderId)
                ->update([
                    'is_flagged' => true,
                    'updated_at' => now()
                ]);
        }
    }
} 