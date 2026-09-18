<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Models\Landlord;
use App\Models\Tenant;
use App\Models\HouseBill;
use App\Models\VerificationDocument;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
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

    public function houseBills(): HasMany
    {
        return $this->hasMany(HouseBill::class);
    }

    // Upload folder segment: {id}-{slugified-name}, plain id when no profile exists
    public function uploadFolderName(): string
    {
        $profile = $this->user_type === 'landlord'
            ? $this->landlord
            : $this->tenant;

        if (!$profile) {
            return (string) $this->id;
        }

        $fullName = implode(' ', array_filter([
            $profile->first_name,
            $profile->middle_name,
            $profile->last_name,
        ]));

        $slug = Str::slug($fullName);

        return $slug !== ''
            ? $this->id . '-' . $slug
            : (string) $this->id;
    }

    public function verifiedDocuments(): HasMany
    {
        return $this->hasMany(
            VerificationDocument::class,
            'verified_by'
        );
    }
}