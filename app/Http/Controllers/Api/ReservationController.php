<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Landlord;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReservationController extends Controller
{
    /**
     * Create a reservation and assign a tenant to a property.
     *
     * Existing user:
     * - use user_id
     * - create tenant profile if needed
     * - create reservation
     *
     * New user:
     * - create user
     * - create tenant
     * - create reservation
     */
    public function store(Request $request, Property $property)
    {
        $validator = Validator::make($request->all(), [

            // Existing user
            'user_id' => 'nullable|exists:users,id',

            // Required only for a new user
            'phone' => 'nullable|string|max:20|unique:users,phone',
            'first_name' => 'nullable|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',

            // Reservation details
            'total_rooms_rented' => 'required|integer|min:1',
            'monthly_rent' => 'required|numeric|min:0',
            'security_deposit' => 'nullable|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Find authenticated landlord
        |--------------------------------------------------------------------------
        */

        $landlord = Landlord::where('user_id', $request->user()->id)->first();

        if (!$landlord) {
            return response()->json([
                'message' => 'Only landlords can create reservations.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Make sure property belongs to this landlord
        |--------------------------------------------------------------------------
        */

        if ($property->landlord_id != $landlord->id) {
            return response()->json([
                'message' => 'You are not authorized to manage this property.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Existing user OR new user must be supplied
        |--------------------------------------------------------------------------
        */

        if (!$request->user_id && !$request->phone) {
            return response()->json([
                'message' => 'Either user_id or phone is required.',
            ], 422);
        }

        try {

            $result = DB::transaction(function () use ($request, $property) {

                /*
                |--------------------------------------------------------------------------
                | CASE 1: Existing user
                |--------------------------------------------------------------------------
                */

                if ($request->filled('user_id')) {

                    $user = User::findOrFail($request->user_id);

                    /*
                     * If the existing user has never been a tenant before,
                     * create the tenant profile now.
                     */
                    $tenant = Tenant::where('user_id', $user->id)->first();

                    if (!$tenant) {

                        $tenant = Tenant::create([
                            'user_id' => $user->id,

                            /*
                             * If your tenants table requires these fields,
                             * frontend should send them when needed.
                             */
                            'first_name' => $request->first_name
                                ?? $user->first_name
                                ?? 'Tenant',

                            'middle_name' => $request->middle_name,

                            'last_name' => $request->last_name
                                ?? $user->last_name
                                ?? '',
                        ]);
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | CASE 2: New user
                |--------------------------------------------------------------------------
                */

                else {

                    if (
                        !$request->filled('phone') ||
                        !$request->filled('first_name') ||
                        !$request->filled('last_name')
                    ) {
                        abort(response()->json([
                            'message' => 'Phone, first name and last name are required for a new tenant.',
                        ], 422));
                    }

                    /*
                     * Create new user.
                     *
                     * Do NOT mark the phone as verified here.
                     * The tenant will verify the number during first login.
                     */
                    $user = User::create([
                        'phone' => $request->phone,

                        // Keep this if your users table has a role column.
                        'role' => 'tenant',
                    ]);

                    /*
                     * Create tenant profile.
                     */
                    $tenant = Tenant::create([
                        'user_id' => $user->id,
                        'first_name' => $request->first_name,
                        'middle_name' => $request->middle_name,
                        'last_name' => $request->last_name,
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Check existing active reservation
                |--------------------------------------------------------------------------
                |
                | Prevent exactly the same tenant from being assigned to the
                | same property twice while an active reservation already exists.
                |
                */

                $alreadyAssigned = Reservation::where(
                    'property_id',
                    $property->id
                )
                    ->where('tenant_id', $tenant->id)
                    ->where('status', Reservation::STATUS_ACTIVE)
                    ->exists();

                if ($alreadyAssigned) {
                    abort(response()->json([
                        'message' => 'This tenant is already assigned to this property.',
                    ], 422));
                }

                /*
                |--------------------------------------------------------------------------
                | Create reservation
                |--------------------------------------------------------------------------
                */

                $reservation = Reservation::create([
                    'property_id' => $property->id,
                    'tenant_id' => $tenant->id,

                    'total_rooms_rented' => $request->total_rooms_rented,

                    'monthly_rent' => $request->monthly_rent,

                    'security_deposit' => $request->security_deposit ?? 0,

                    'start_date' => $request->start_date,

                    'end_date' => $request->end_date,

                    'status' => Reservation::STATUS_ACTIVE,

                    'notes' => $request->notes,
                ]);

                $reservation->load([
                    'tenant',
                    'tenant.user',
                    'property',
                ]);

                return [
                    'user' => $user,
                    'tenant' => $tenant,
                    'reservation' => $reservation,
                ];
            });

            return response()->json([
                'message' => 'Tenant assigned to property successfully.',
                'data' => $result,
            ], 201);

        } catch (\Throwable $e) {

            return response()->json([
                'message' => 'Failed to create reservation.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}