<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\User\Http\Controllers\UserController;
use Modules\User\Http\Controllers\RoleController;
use Modules\User\Http\Controllers\PermissionController;

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

// Public routes
Route::get('/service-providers/{id}', [UserController::class, 'getServiceProviderProfile']);

// Routes that require authentication
Route::middleware(['auth:api'])->prefix('v1')->group(function () {
    Route::get('/profile', [UserController::class, 'getProfile'])->name('user.profile');
    Route::post('/become-service-provider', [UserController::class, 'becomeServiceProvider']);
    Route::post('/become-customer', [UserController::class, 'becomeCustomer']);
    Route::get('/my-service-provider-profile', [UserController::class, 'getServiceProviderProfile']);
    
    Route::apiResource('users', UserController::class)->names('user');
    Route::apiResource('roles', RoleController::class)->names('role');
    Route::apiResource('permissions', PermissionController::class)->names('permission');
});
