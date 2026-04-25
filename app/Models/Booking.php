<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    use HasFactory;

    /** Conversation states where the AI agent stops responding to the guest. */
    public const AI_PAUSED_STATES = [
        'handover_human',
        'issue_detected',
        'cancelled',
        'phone_missing',
        'group_booking',
        'suite_pending',
    ];

    protected $appends = ['ai_active'];

    protected $fillable = [
        'customer_id',
        'lodgify_booking_id',
        'guest_name',
        'guest_phone',
        'guest_email',
        'guest_nationality',
        'detected_language',
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
        'conversation_state',
        'pref_arrival_time',
        'pref_bed_type',
        'pref_airport_transfer',
        'pref_special_requests',
        'follow_up_count',
        'preferences_briefing_sent',
        'revenue_logged',
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
            'preferences_briefing_sent' => 'boolean',
            'revenue_logged' => 'boolean',
            'lodgify_synced_at' => 'datetime',
            'upsell_offer_sent_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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

    protected function aiActive(): Attribute
    {
        // Returns null when conversation_state wasn't loaded (e.g. limited
        // ->get([...]) select), so callers can't mistake "column missing"
        // for "AI is active".
        return Attribute::get(function () {
            if (!array_key_exists('conversation_state', $this->attributes)) {
                return null;
            }
            return !in_array($this->conversation_state, self::AI_PAUSED_STATES, true);
        });
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
