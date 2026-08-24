<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Landlord;
use Illuminate\Support\Facades\Validator;

class LandlordController extends Controller
{
    /**
     * Create landlord profile
     */
    public function store(Request $request)
    {
        $user = $request->user();

        // Prevent duplicate landlord profile
        if ($user->landlord) {
            return response()->json([
                'message' => 'Landlord profile already exists.',
                'landlord' => $user->landlord,
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'required|string|max:100',
            'address' => 'nullable|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $landlord = Landlord::create([
            'user_id' => $user->id,
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name' => $request->last_name,
            'address' => $request->address,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'verification_status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Landlord profile created successfully.',
            'landlord' => $landlord,
        ], 201);
    }

    /**
     * Show current landlord profile
     */
    public function me(Request $request)
    {
        $user = $request->user();

        $landlord = $user->landlord;

        if (!$landlord) {
            return response()->json([
                'message' => 'Landlord profile not found.',
            ], 404);
        }

        return response()->json([
            'message' => 'Landlord profile retrieved successfully.',
            'landlord' => $landlord,
        ]);
    }
}