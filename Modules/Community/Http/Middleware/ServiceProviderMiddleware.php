<?php

namespace Modules\Community\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ServiceProviderMiddleware
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
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Please log in.'
            ], 401);
        }

        $user = Auth::user();
        $role = DB::table('roles')->where('id', $user->role_id)->first();
        
        // Check if the user has the service_provider or admin role
        if (!$role || ($role->name !== 'service_provider' && $role->name !== 'admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You must be a service provider to perform this action.'
            ], 403);
        }

        return $next($request);
    }
} 