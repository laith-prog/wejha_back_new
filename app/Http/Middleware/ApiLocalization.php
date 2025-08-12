<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class ApiLocalization
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if the request has Accept-Language header
        if ($request->hasHeader('Accept-Language')) {
            $locale = $request->header('Accept-Language');
            
            // Validate the locale (only 'en' or 'ar')
            if (in_array($locale, ['en', 'ar'])) {
                App::setLocale($locale);
            }
        }
        
        // Check if the locale is set in the query parameter (for testing)
        if ($request->has('lang')) {
            $locale = $request->lang;
            
            // Validate the locale (only 'en' or 'ar')
            if (in_array($locale, ['en', 'ar'])) {
                App::setLocale($locale);
            }
        }
        
        return $next($request);
    }
} 