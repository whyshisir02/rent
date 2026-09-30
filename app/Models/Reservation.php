<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'tenant_id',
        'total_rooms_rented',
        'monthly_rent',
        'security_deposit',
        'start_date',
        'end_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'total_rooms_rented' => 'integer',

        'monthly_rent' => 'decimal:2',
        'security_deposit' => 'decimal:2',

        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Property related to this reservation.
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Tenant related to this reservation.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}