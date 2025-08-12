<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class LanguageController extends Controller
{
    /**
     * Get all available translations for a specific locale
     *
     * @param  string  $locale
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTranslations($locale = null)
    {
        // Set default locale if not provided
        if (!$locale) {
            $locale = App::getLocale();
        }
        
        // Validate the locale
        if (!in_array($locale, ['en', 'ar'])) {
            $locale = 'en'; // Default to English if invalid
        }
        
        // Load translations from the messages file
        $translations = require base_path("resources/lang/{$locale}/messages.php");
        
        return response()->json([
            'locale' => $locale,
            'translations' => $translations
        ]);
    }
    
    /**
     * Switch the application locale
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function switchLocale(Request $request)
    {
        $locale = $request->locale;
        
        // Validate the locale
        if (!in_array($locale, ['en', 'ar'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid locale. Supported locales are: en, ar'
            ], 400);
        }
        
        // Return the translations for the requested locale
        return $this->getTranslations($locale);
    }
} 