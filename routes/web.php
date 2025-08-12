<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LanguageController;

Route::get('/', function () {
    return view('welcome');
});

// Language Switcher
Route::get('language/{locale}', [LanguageController::class, 'switchLang'])->name('language.switch');

// Test route for localization example
Route::get('/example', function () {
    return view('example');
});
