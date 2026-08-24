<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tenant;
use Illuminate\Support\Facades\Validator;

class TenantController extends Controller
{
    /**
     * Create tenant profile
     */
    public function store(Request $request)
    {
        $user = $request->user();

        // Prevent duplicate tenant profile
        if ($user->tenant) {
            return response()->json([
                'message' => 'Tenant profile already exists.',
                'tenant' => $user->tenant,
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

        $tenant = Tenant::create([
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
            'message' => 'Tenant profile created successfully.',
            'tenant' => $tenant,
        ], 201);
    }

    /**
     * Show current tenant profile
     */
    public function me(Request $request)
    {
        $user = $request->user();

        $tenant = $user->tenant;

        if (!$tenant) {
            return response()->json([
                'message' => 'Tenant profile not found.',
            ], 404);
        }

        return response()->json([
            'message' => 'Tenant profile retrieved successfully.',
            'tenant' => $tenant,
        ]);
    }
}