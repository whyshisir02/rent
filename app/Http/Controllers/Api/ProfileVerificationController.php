<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ProfileVerificationController extends Controller
{
    // PATCH /admin/users/{user}/verification — admin decides verification status of a user's profile
    public function update(Request $request, User $user): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:approved,rejected,pending',
            'rejection_reason' => 'required_if:status,rejected|nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $profile = $user->user_type === 'landlord' ? $user->landlord : $user->tenant;

        if (!$profile) {
            return response()->json([
                'message' => 'No profile found for user',
            ], 404);
        }

        $status = $request->input('status');
        $admin = $request->user();

        $profile->update([
            'verification_status' => $status,
            'verification_rejection_reason' => $status === 'rejected'
                ? $request->input('rejection_reason')
                : $profile->verification_rejection_reason,
            'verified_by' => $admin->id,
            'verified_at' => match ($status) {
                'approved', 'rejected' => now(),
                default => null,
            },
        ]);

        return response()->json([
            'message' => 'Profile verification ' . $status,
            'data' => [
                'id' => $user->id,
                'name' => $user->name ?? $profile->first_name . ' ' . $profile->last_name,
                'email' => $user->phone,
                'user_type' => $user->user_type,
                'verification_status' => $profile->verification_status,
                'verification_rejection_reason' => $profile->verification_rejection_reason,
                'verified_by' => $profile->verified_by,
                'verified_at' => $profile->verified_at?->toDateTimeString(),
            ],
        ]);
    }
}
