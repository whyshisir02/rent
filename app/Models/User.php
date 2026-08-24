<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Models\Landlord;
use App\Models\Tenant;
use App\Models\VerificationDocument;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens;

class User extends Model
{
    use HasApiTokens, HasFactory;
    protected $fillable = [
        'phone',
        'user_type',
        'status',
    ];

    public function landlord(): HasOne
    {
        return $this->hasOne(Landlord::class);
    }

    public function tenant(): HasOne
    {
        return $this->hasOne(Tenant::class);
    }

    public function verificationDocuments(): HasMany
    {
        return $this->hasMany(VerificationDocument::class);
    }

    public function verifiedDocuments(): HasMany
    {
        return $this->hasMany(
            VerificationDocument::class,
            'verified_by'
        );
    }
}