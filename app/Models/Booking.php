<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'lodgify_booking_id',
        'guest_name',
        'guest_phone',
        'guest_email',
        'guest_nationality',
        'num_guests',
        'suite_name',
        'check_in',
        'check_out',
        'num_nights',
        'booking_source',
        'booking_status',
        'total_amount',
        'currency',
        'special_requests',
        'internal_notes',
        'lodgify_synced_at',
        'current_upsell_offer_id',
        'upsell_offer_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'total_amount' => 'decimal:2',
            'lodgify_synced_at' => 'datetime',
            'upsell_offer_sent_at' => 'datetime',
        ];
    }

    public function currentOffer(): BelongsTo
    {
        return $this->belongsTo(Offer::class, 'current_upsell_offer_id');
    }

    public function upsellLogs(): HasMany
    {
        return $this->hasMany(UpsellLog::class);
    }

    public function whatsappMessages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function systemLogs(): HasMany
    {
        return $this->hasMany(SystemLog::class);
    }

    public function currentDayOfStay(): ?int
    {
        if ($this->booking_status !== 'checked_in') {
            return null;
        }

        if (Carbon::today()->isBefore($this->check_in)) {
            return null;
        }

        return (int) $this->check_in->diffInDays(Carbon::today()) + 1;
    }
}
