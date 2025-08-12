<?php

use Illuminate\Support\Facades\Route;
use Modules\Community\Http\Controllers\CommunityController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::prefix('community')->group(function() {
    Route::get('/', [CommunityController::class, 'index'])->name('community.index');
    Route::get('/create', [CommunityController::class, 'create'])->name('community.create');
    Route::post('/', [CommunityController::class, 'store'])->name('community.store');
    Route::get('/{id}', [CommunityController::class, 'show'])->name('community.show');
    Route::get('/{id}/edit', [CommunityController::class, 'edit'])->name('community.edit');
    Route::put('/{id}', [CommunityController::class, 'update'])->name('community.update');
    Route::delete('/{id}', [CommunityController::class, 'destroy'])->name('community.destroy');
    
    // Community membership routes
    Route::post('/{id}/join', [CommunityController::class, 'join'])->name('community.join');
    Route::post('/{id}/leave', [CommunityController::class, 'leave'])->name('community.leave');
});
