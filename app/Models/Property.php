<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Models\Landlord;

class Property extends Model
{
    protected $fillable = [
        'landlord_id',
        'title',
        'description',
        'property_type',
        'address',
        'city',
        'district',
        'latitude',
        'longitude',
        'number_of_floors',
        'number_of_rooms',
        'amenities',
        'status',
    ];

    protected $casts = [
        'amenities' => 'array',
    ];

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(Landlord::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
