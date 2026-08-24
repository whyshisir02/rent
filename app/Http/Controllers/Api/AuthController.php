<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Generate temporary OTP.
     *
     * Development/testing only.
     * OTP is not stored in Cache or database.
     */
    public function requestOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = $request->phone;

        // Temporary OTP for testing.
        $otp = (string) random_int(100000, 999999);

        return response()->json([
            'message' => 'OTP generated successfully',
            'phone' => $phone,
            'otp' => $otp,
        ], 200);
    }


    /**
     * Verify OTP and login/register user.
     *
     * Existing user:
     *      Login and return full profile.
     *
     * New user:
     *      Create basic user and return token.
     *      Profile is created later through /landlords or /tenants.
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:20',
            'otp' => 'required|string|size:6',
            'user_type' => 'nullable|in:landlord,tenant',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = $request->phone;

        /*
        |--------------------------------------------------------------------------
        | Temporary OTP verification
        |--------------------------------------------------------------------------
        |
        | OTP is not stored anywhere at this stage.
        | Therefore, we only check that a 6-digit OTP was provided.
        |
        | This will later be replaced by Firebase OTP verification.
        |
        */

        /*
        |--------------------------------------------------------------------------
        | Find existing user
        |--------------------------------------------------------------------------
        */

        $user = User::where('phone', $phone)
            ->with(['landlord', 'tenant'])
            ->first();


        /*
        |--------------------------------------------------------------------------
        | Existing User
        |--------------------------------------------------------------------------
        */

        if ($user) {

            $token = $user->createToken('rent-app')->plainTextToken;

            return response()->json([
                'message' => 'Login successful',
                'is_new_user' => false,

                'user' => $user,

                'token' => $token,
            ], 200);
        }


        /*
        |--------------------------------------------------------------------------
        | New User
        |--------------------------------------------------------------------------
        */

        if (!$request->user_type) {
            return response()->json([
                'message' => 'OTP verified successfully. User type is required.',
                'is_new_user' => true,
                'phone' => $phone,
            ], 422);
        }


        /*
        |--------------------------------------------------------------------------
        | Create Basic User
        |--------------------------------------------------------------------------
        |
        | We only create the users record here.
        |
        | Landlord/Tenant profile will be created separately after
        | authentication using the Bearer token.
        |
        */

        $user = User::create([
            'phone' => $phone,
            'user_type' => $request->user_type,
            'status' => 'active',
        ]);


        /*
        |--------------------------------------------------------------------------
        | Generate Sanctum Token
        |--------------------------------------------------------------------------
        */

        $token = $user->createToken('rent-app')->plainTextToken;


        /*
        |--------------------------------------------------------------------------
        | New User Response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'message' => 'OTP verified successfully. Complete your profile.',
            'is_new_user' => true,

            'user' => [
                'id' => $user->id,
                'phone' => $user->phone,
                'user_type' => $user->user_type,
                'status' => $user->status,
                'landlord' => null,
                'tenant' => null,
            ],

            'token' => $token,

            'next_step' => 'complete_profile',
        ], 201);
    }
}