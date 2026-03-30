<?php

namespace App\Service\Concierge;

use App\Contract\Concierge\BookingContract;
use App\Models\Booking;
use App\Service\BaseService;

class BookingService extends BaseService implements BookingContract
{
    protected array $relation = ['currentOffer'];

    public function __construct(Booking $model)
    {
        parent::__construct($model);
    }
}
