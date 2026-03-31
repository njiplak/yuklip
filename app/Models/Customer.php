<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'phone',
        'name',
        'email',
        'nationality',
        'language',
        'total_stays',
        'first_stay_at',
        'last_stay_at',
        'profile_summary',
        'raw_preferences',
    ];

    protected function casts(): array
    {
        return [
            'raw_preferences' => 'array',
            'first_stay_at' => 'date',
            'last_stay_at' => 'date',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function isReturning(): bool
    {
        return $this->total_stays > 1;
    }
}
