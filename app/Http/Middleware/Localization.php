<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class Localization
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
        // Check if the locale is set in the session
        if (Session::has('locale')) {
            App::setLocale(Session::get('locale'));
        }
        
        // Check if the locale is set in the URL
        if ($request->has('lang')) {
            $locale = $request->lang;
            // Check if the locale is valid (only 'en' or 'ar')
            if (in_array($locale, ['en', 'ar'])) {
                Session::put('locale', $locale);
                App::setLocale($locale);
            }
        }
        
        return $next($request);
    }
} 