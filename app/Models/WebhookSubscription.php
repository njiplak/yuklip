<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookSubscription extends Model
{
    protected $fillable = [
        'source',
        'event',
        'subscription_id',
        'secret',
        'target_url',
    ];
}
