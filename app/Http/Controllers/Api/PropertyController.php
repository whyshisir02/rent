<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\Landlord;
use App\Models\Property;

class PropertyController extends Controller
{
    /**
     * Create a property.
     *
     * Landlord only — and only after the landlord's verification
     * documents have been approved by admin.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->user_type !== 'landlord') {
            return response()->json([
                'message' => 'Only landlords can add properties.',
            ], 403);
        }

        $landlord = $user->landlord;

        if (!$landlord) {
            return response()->json([
                'message' => 'Landlord profile not found. Complete your profile first.',
            ], 404);
        }

        // Verification gate: no property can be added while the
        // landlord's documents are still pending or rejected.
        if ($landlord->verification_status !== 'approved') {
            return response()->json([
                'message' => 'Your verification documents must be approved before you can add a property.',
                'verification_status' => $landlord->verification_status,
                'verification_rejection_reason' => $landlord->verification_rejection_reason,
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'property_type' => 'required|in:house,apartment,room,other',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'number_of_floors' => 'required|integer|min:0',
            'number_of_rooms' => 'required|integer|min:0',
            'amenities' => 'nullable|array',
            'amenities.*' => 'string|max:100',
            'status' => 'nullable|in:available,rented,sold',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $property = Property::create([
            'landlord_id' => $landlord->id,
            'title' => $request->title,
            'description' => $request->description,
            'property_type' => $request->property_type,
            'address' => $request->address,
            'city' => $request->city,
            'district' => $request->district,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'number_of_floors' => $request->number_of_floors,
            'number_of_rooms' => $request->number_of_rooms,
            'amenities' => $request->amenities,
            'status' => $request->status ?? 'available',
        ]);

        return response()->json([
            'message' => 'Property created successfully.',
            'property' => $property,
        ], 201);
    }

    /**
     * List the authenticated landlord's properties.
     */
    public function index(Request $request): JsonResponse
    {
        $landlord = $request->user()->landlord;

        if (!$landlord) {
            return response()->json([
                'message' => 'Landlord profile not found.',
            ], 404);
        }

        return response()->json([
            'message' => 'Properties retrieved successfully.',
            'properties' => $landlord->properties()->latest()->get(),
        ]);
    }

    /**
     * Show one of the authenticated landlord's properties.
     */
    public function show(Request $request, Property $property): JsonResponse
    {
        if (!$this->resolveOwnedProperty($request, $property)) {
            return response()->json([
                'message' => 'Property not found.',
            ], 404);
        }

        return response()->json([
            'message' => 'Property retrieved successfully.',
            'property' => $property,
        ]);
    }

    /**
     * Update one of the authenticated landlord's properties.
     */
    public function update(Request $request, Property $property): JsonResponse
    {
        if (!$this->resolveOwnedProperty($request, $property)) {
            return response()->json([
                'message' => 'Property not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'property_type' => 'sometimes|required|in:house,apartment,room,other',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'number_of_floors' => 'sometimes|required|integer|min:0',
            'number_of_rooms' => 'sometimes|required|integer|min:0',
            'amenities' => 'nullable|array',
            'amenities.*' => 'string|max:100',
            'status' => 'sometimes|required|in:available,rented,sold',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Only submitted fields are updated (landlord_id is never updatable).
        $property->update($validator->validated());

        return response()->json([
            'message' => 'Property updated successfully.',
            'property' => $property->fresh(),
        ]);
    }

    /**
     * Delete one of the authenticated landlord's properties.
     */
    public function destroy(Request $request, Property $property): JsonResponse
    {
        if (!$this->resolveOwnedProperty($request, $property)) {
            return response()->json([
                'message' => 'Property not found.',
            ], 404);
        }

        $property->delete();

        return response()->json([
            'message' => 'Property deleted successfully.',
        ]);
    }

    /**
     * Verify the authenticated landlord owns the given property.
     * Returns the landlord profile, or null when not the owner.
     */
    private function resolveOwnedProperty(Request $request, Property $property): ?Landlord
    {
        $landlord = $request->user()->landlord;

        if (!$landlord || $property->landlord_id !== $landlord->id) {
            return null;
        }

        return $landlord;
    }
}
