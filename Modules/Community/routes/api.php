<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Community\Http\Controllers\CommunityController;
use Modules\Community\Http\Controllers\ListingController;
use Modules\Community\Http\Controllers\ListingCategoryController;
use Modules\Community\Http\Controllers\RealEstateController;
use Modules\Community\Http\Controllers\VehicleController;
use Modules\Community\Http\Controllers\ServiceController;
use Modules\Community\Http\Controllers\JobController;
use Modules\Community\Http\Controllers\BidController;
use Modules\Community\Http\Controllers\ListingImageController;
use Modules\Community\Http\Controllers\HomeController;
use Modules\Community\Http\Controllers\SearchController;
use Modules\Community\Http\Controllers\ReviewController;
use Modules\Community\Http\Controllers\ReportController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Basic Listing Routes
Route::prefix('listings')->group(function () {
    // Public routes
    Route::get('/', [ListingController::class, 'index']);
    Route::get('/search', [ListingController::class, 'search']);
    Route::get('/{id}', [ListingController::class, 'show']);
    
    // Protected routes - only for service providers
    Route::middleware(['auth:api', 'service_provider'])->group(function () {
        Route::post('/', [ListingController::class, 'store']);
        Route::put('/{id}', [ListingController::class, 'update']);
        Route::delete('/{id}', [ListingController::class, 'destroy']);
    });
});

// Real Estate Listing Routes
Route::prefix('real-estate')->group(function () {
    // Public routes
    Route::get('/', [RealEstateController::class, 'index']);
    Route::get('/search', [RealEstateController::class, 'search']);
    Route::get('/{id}', [RealEstateController::class, 'show']);
    Route::get('/create', [RealEstateController::class, 'create']); // Moved to public for form data
    
    // Protected routes - only for service providers
    Route::middleware(['auth:api', 'service_provider'])->group(function () {
        Route::post('/', [RealEstateController::class, 'store']);
    });
});

// Vehicle Listing Routes
Route::prefix('vehicles')->group(function () {
    // Public routes
    Route::get('/', [VehicleController::class, 'index']);
    Route::get('/search', [VehicleController::class, 'search']);
    Route::get('/{id}', [VehicleController::class, 'show']);
    Route::get('/create', [VehicleController::class, 'create']); // Moved to public for form data
    
    // Protected routes - only for service providers
    Route::middleware(['auth:api', 'service_provider'])->group(function () {
        Route::post('/', [VehicleController::class, 'store']);
    });
});

// Service Listing Routes
Route::prefix('services')->group(function () {
    // Public routes
    Route::get('/', [ServiceController::class, 'index']);
    Route::get('/search', [ServiceController::class, 'search']);
    Route::get('/{id}', [ServiceController::class, 'show']);
    Route::get('/create', [ServiceController::class, 'create']); // Moved to public for form data
    
    // Protected routes - only for service providers
    Route::middleware(['auth:api', 'service_provider'])->group(function () {
        Route::post('/', [ServiceController::class, 'store']);
    });
});

// Job Listing Routes
Route::prefix('jobs')->group(function () {
    // Public routes
    Route::get('/', [JobController::class, 'index']);
    Route::get('/search', [JobController::class, 'search']);
    Route::get('/{id}', [JobController::class, 'show']);
    Route::get('/create', [JobController::class, 'create']); // Moved to public for form data
    
    // Protected routes - only for service providers
    Route::middleware(['auth:api', 'service_provider'])->group(function () {
        Route::post('/', [JobController::class, 'store']);
    });
});

// Bid/Tender Listing Routes
Route::prefix('bids')->group(function () {
    // Public routes
    Route::get('/', [BidController::class, 'index']);
    Route::get('/search', [BidController::class, 'search']);
    Route::get('/{id}', [BidController::class, 'show']);
    Route::get('/create', [BidController::class, 'create']); // Moved to public for form data
    
    // Protected routes - only for service providers
    Route::middleware(['auth:api', 'service_provider'])->group(function () {
        Route::post('/', [BidController::class, 'store']);
    });
});

// Community Home Routes
Route::prefix('community')->group(function () {
    Route::get('/home', [HomeController::class, 'index']);
    Route::get('/category', [HomeController::class, 'getListingsByCategory']);
    Route::get('/subcategory', [HomeController::class, 'getListingsBySubcategory']);
});

// Search Routes
Route::prefix('search')->group(function () {
    // Public search routes
    Route::get('/', [SearchController::class, 'search']);
    Route::get('/advanced', [SearchController::class, 'advancedSearch']);
    Route::get('/filters', [SearchController::class, 'getSearchFilters']);
    Route::get('/popular', [SearchController::class, 'getPopularSearches']);
    
    // Authenticated search routes
    Route::middleware('auth:api')->group(function () {
        Route::get('/recent', [SearchController::class, 'getRecentSearches']);
        Route::post('/save', [SearchController::class, 'saveSearch']);
        Route::delete('/delete', [SearchController::class, 'deleteSearch']);
    });
});

// Real Estate Rental Search Routes
Route::prefix('real-estate/rentals')->group(function () {
    Route::get('/search', [SearchController::class, 'realEstateRentalSearch']);
    Route::get('/filters', [SearchController::class, 'getRealEstateFilters']);
});

// Real Estate Sale Search Routes
Route::prefix('real-estate/sales')->group(function () {
    Route::get('/search', [SearchController::class, 'realEstateSaleSearch']);
    Route::get('/filters', [SearchController::class, 'getRealEstateSaleFilters']);
});

// Room Rental Search Routes
Route::prefix('real-estate/rooms')->group(function () {
    Route::get('/search', [SearchController::class, 'roomRentalSearch']);
    Route::get('/filters', [SearchController::class, 'getRoomRentalFilters']);
});

// Investment Property Search Routes
Route::prefix('real-estate/investment')->group(function () {
    Route::get('/search', [SearchController::class, 'investmentPropertySearch']);
    Route::get('/filters', [SearchController::class, 'getInvestmentPropertyFilters']);
});

// Services Search Routes
Route::prefix('services')->group(function () {
    Route::get('/search', [SearchController::class, 'serviceSearch']);
    Route::get('/filters', [SearchController::class, 'getServiceFilters']);
});

// Vehicles Search Routes
Route::prefix('vehicles')->group(function () {
    Route::get('/search', [SearchController::class, 'vehicleSearch']);
    Route::get('/filters', [SearchController::class, 'getVehicleFilters']);
});

// Jobs Search Routes
Route::prefix('jobs')->group(function () {
    Route::get('/search', [SearchController::class, 'jobSearch']);
    Route::get('/filters', [SearchController::class, 'getJobFilters']);
});

// Similar listings route
Route::get('/listings/{id}/similar', [SearchController::class, 'getSimilarListings']);

// Admin routes for reports
Route::middleware(['auth:api', 'admin'])->prefix('admin')->group(function () {
    Route::get('/listings/{id}/reports', [ListingController::class, 'getListingReports']);
});

// Image management routes
// Temporarily disable middleware for testing
Route::post('/listings/{id}/images', [ListingImageController::class, 'upload']);
Route::delete('/images/{id}', [ListingImageController::class, 'delete']);
Route::put('/images/{id}/primary', [ListingImageController::class, 'setPrimary']);
Route::put('/listings/{id}/images/order', [ListingImageController::class, 'updateOrder']);
Route::get('/listings/{id}/images', [ListingImageController::class, 'getListingImages']);

// Category management routes
Route::post('/listings/categories', [ListingCategoryController::class, 'store']);
Route::post('/listings/subcategories', [ListingCategoryController::class, 'storeSubcategory']);

// Review routes
Route::prefix('reviews')->middleware('auth:api')->group(function () {
    // Service provider reviews
    Route::post('/service-providers', [ReviewController::class, 'createServiceProviderReview']);
    Route::put('/service-providers/{id}', [ReviewController::class, 'updateServiceProviderReview']);
    Route::delete('/service-providers/{id}', [ReviewController::class, 'deleteServiceProviderReview']);
    
    // Listing reviews
    Route::post('/listings', [ReviewController::class, 'createListingReview']);
    Route::put('/listings/{id}', [ReviewController::class, 'updateListingReview']);
    Route::delete('/listings/{id}', [ReviewController::class, 'deleteListingReview']);
});

// Public review routes
Route::get('/service-providers/{id}/reviews', [ReviewController::class, 'getServiceProviderReviews']);
Route::get('/listings/{id}/reviews', [ReviewController::class, 'getListingReviews']);

// Report routes
Route::prefix('reports')->group(function () {
    // Public routes for submitting reports (requires authentication)
    Route::middleware('auth:api')->group(function () {
        Route::post('/listings', [ReportController::class, 'reportListing']);
        Route::post('/service-providers', [ReportController::class, 'reportServiceProvider']);
    });
    
    // Public routes for getting report reasons
    Route::get('/listings/reasons', [ReportController::class, 'getListingReportReasons']);
    Route::get('/service-providers/reasons', [ReportController::class, 'getServiceProviderReportReasons']);
    
    // Admin routes for managing reports
    Route::middleware(['auth:api', 'admin'])->group(function () {
        Route::get('/listings', [ReportController::class, 'getListingReports']);
        Route::get('/service-providers', [ReportController::class, 'getServiceProviderReports']);
        Route::put('/listings/{id}', [ReportController::class, 'updateListingReportStatus']);
        Route::put('/service-providers/{id}', [ReportController::class, 'updateServiceProviderReportStatus']);
    });
});

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('communities', CommunityController::class)->names('community');
});
