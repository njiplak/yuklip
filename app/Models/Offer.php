<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Offer extends Model
{
    use HasFactory;

    protected $fillable = [
        'offer_code',
        'title',
        'description',
        'category',
        'timing_rule',
        'price',
        'currency',
        'is_active',
        'max_sends_per_stay',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function upsellLogs(): HasMany
    {
        return $this->hasMany(UpsellLog::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
