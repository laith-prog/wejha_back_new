<?php

namespace Modules\User\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('user::index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('user::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {}

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('user::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('user::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id) {}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id) {}

    /**
     * Get the authenticated user's profile information
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProfile(Request $request)
    {
        $user = Auth::guard('api')->user();
        
        if (!$user) {
            return response()->json([
                'message' => 'User not found',
                'status' => 'error'
            ], 404);
        }

        // Get user role
        $role = null;
        if ($user->role_id) {
            $role = \DB::table('roles')->where('id', $user->role_id)->first();
        }

        return response()->json([
            'message' => 'User profile retrieved successfully',
            'status' => 'success',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'fname' => $user->fname,
                    'lname' => $user->lname,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'gender' => $user->gender,
                    'birthday' => $user->birthday,
                    'photo' => $user->photo,
                    'role' => $user->role ? $user->role->name : null,
                    'role_id' => $user->role_id ? (string)$user->role_id : null, 
                    'email_verified_at' => $user->email_verified_at,
                    'auth_provider' => $user->auth_provider,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'role' => $role ? $role->name : null
                ]
            ]
        ]);
    }

    /**
     * Assign the service provider role to the authenticated user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function becomeServiceProvider(Request $request)
    {
        $user = $request->user();
        
        // Check if user already has the role
        $serviceProviderRole = \DB::table('roles')->where('name', 'service_provider')->first();
        
        if (!$serviceProviderRole) {
            return response()->json([
                'success' => false,
                'message' => 'Service provider role not found'
            ], 404);
        }
        
        if ($user->role_id == $serviceProviderRole->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are already a service provider'
            ], 400);
        }
        
        // Update the user's role_id
        $user->role_id = $serviceProviderRole->id;
        $user->save();
        
        return response()->json([
            'success' => true,
            'message' => 'You are now a service provider',
            'user' => $user
        ]);
    }

    /**
     * Assign the customer role to the authenticated user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function becomeCustomer(Request $request)
    {
        $user = $request->user();
        
        // Check if user already has the role
        $customerRole = \DB::table('roles')->where('name', 'customer')->first();
        
        if (!$customerRole) {
            return response()->json([
                'success' => false,
                'message' => 'Customer role not found'
            ], 404);
        }
        
        if ($user->role_id == $customerRole->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are already a customer'
            ], 400);
        }
        
        // Update the user's role_id
        $user->role_id = $customerRole->id;
        $user->save();
        
        return response()->json([
            'success' => true,
            'message' => 'You are now a customer',
            'user' => $user
        ]);
    }

    /**
     * Get the service provider profile
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getServiceProviderProfile(Request $request, $id = null)
    {
        // If no ID is provided, get the authenticated user's profile
        if (!$id) {
            $user = Auth::guard('api')->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Please log in.'
                ], 401);
            }
            
            $id = $user->id;
        }
        
        // Get the user by ID
        $serviceProvider = \App\Models\User::find($id);
        
        if (!$serviceProvider) {
            return response()->json([
                'success' => false,
                'message' => 'Service provider not found'
            ], 404);
        }
        
        // Get user role
        $role = null;
        if ($serviceProvider->role_id) {
            $role = \DB::table('roles')->where('id', $serviceProvider->role_id)->first();
        }
        
        // Check if the user is a service provider
        if (!$role || $role->name !== 'service_provider') {
            return response()->json([
                'success' => false,
                'message' => 'User is not a service provider'
            ], 400);
        }
        
        // Get service provider stats
        $stats = \DB::table('service_provider_stats')
            ->where('user_id', $serviceProvider->id)
            ->first();
            
        // Get service provider reviews
        $reviews = \DB::table('service_provider_reviews')
            ->where('provider_id', $serviceProvider->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
            
        // Get service provider listings
        $listings = \DB::table('listings')
            ->where('user_id', $serviceProvider->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
        
        return response()->json([
            'success' => true,
            'message' => 'Service provider profile retrieved successfully',
            'data' => [
                'profile' => [
                    'id' => $serviceProvider->id,
                    'name' => $serviceProvider->fname . ' ' . $serviceProvider->lname,
                    'email' => $serviceProvider->email,
                    'phone' => $serviceProvider->phone,
                    'photo' => $serviceProvider->photo,
                    'joined_at' => $serviceProvider->created_at,
                ],
                'stats' => $stats ? [
                    'total_listings' => $stats->total_listings,
                    'active_listings' => $stats->active_listings,
                    'average_rating' => $stats->average_rating,
                    'total_reviews' => $stats->total_reviews,
                ] : null,
                'reviews' => $reviews,
                'recent_listings' => $listings
            ]
        ]);
    }
}
