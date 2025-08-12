<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Illuminate\Validation\Rules\Password;
use Tymon\JWTAuth\Facades\JWTAuth;

class RegisterController extends Controller
{
    /**
     * Complete registration after email verification.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function completeRegistration(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email',
            'password' => [
                'required',
                'confirmed',
                'min:6'], // Simplified password validation
            'phone' => 'nullable|string',
            'gender' => 'nullable|in:male,female,other',
            'birthday' => 'nullable|date',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'role_id' => 'required|exists:roles,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if email is verified
        $verification = DB::table('verification_codes')
            ->where('email', $request->email)
            ->where('verified', true)
            ->where('type', 'registration')
            ->first();

        if (!$verification) {
            return response()->json([
                'message' => 'Email not verified. Please verify your email first.',
                'status' => 'error'
            ], 422);
        }

        try {
            // Handle photo upload if provided
            $photoPath = null;
            if ($request->hasFile('photo')) {
                $photoPath = $request->file('photo')->store('user_photos', 'public');
                $photoPath = asset('storage/' . $photoPath); // Get the full URL
            }
            
            // Create the user with additional fields
            $user = User::create([
                'fname' => $verification->fname,
                'lname' => $verification->lname,
                'email' => $verification->email,
                'password' => Hash::make($request->password),
                'email_verified_at' => now(),
                'phone' => $request->phone,
                'gender' => $request->gender,
                'birthday' => $request->birthday,
                'photo' => $photoPath,
                'role_id' => $request->role_id, // Directly set the role_id
            ]);
            
            // Delete the verification code record
            DB::table('verification_codes')
                ->where('email', $request->email)
                ->where('type', 'registration')
                ->delete();
            
            // Generate JWT tokens
            Auth::guard('api')->login($user);
            
            // Create access token
            $access_token = JWTAuth::fromUser($user);
            
            // Create refresh token with custom claims
            $refresh_token = JWTAuth::customClaims([
                'sub' => $user->id,
                'refresh' => true,
                'exp' => now()->addDays(30)->timestamp // 30 days expiry for refresh token
            ])->fromUser($user);

            return response()->json([
                'message' => 'Registration completed successfully',
                'status' => 'success',
                'data' => [
                    'user' => $user
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Registration failed',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }
} 