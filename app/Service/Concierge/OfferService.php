<?php

namespace App\Service\Concierge;

use App\Contract\Concierge\OfferContract;
use App\Models\Offer;
use App\Service\BaseService;

class OfferService extends BaseService implements OfferContract
{
    public function __construct(Offer $model)
    {
        parent::__construct($model);
    }
}
