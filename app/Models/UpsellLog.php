<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UpsellLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'offer_id',
        'message_sent',
        'sent_at',
        'guest_reply',
        'reply_received_at',
        'outcome',
        'revenue_generated',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'reply_received_at' => 'datetime',
            'revenue_generated' => 'decimal:2',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }
}
