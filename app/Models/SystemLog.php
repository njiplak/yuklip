<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent',
        'action',
        'booking_id',
        'payload',
        'status',
        'error_message',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
