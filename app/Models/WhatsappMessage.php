<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'direction',
        'phone_number',
        'message_body',
        'agent_source',
        'twochat_message_id',
        'sent_at',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
