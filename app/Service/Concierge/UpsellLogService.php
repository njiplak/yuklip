<?php

namespace App\Service\Concierge;

use App\Contract\Concierge\UpsellLogContract;
use App\Models\UpsellLog;
use App\Service\BaseService;

class UpsellLogService extends BaseService implements UpsellLogContract
{
    protected array $relation = ['booking', 'offer'];

    public function __construct(UpsellLog $model)
    {
        parent::__construct($model);
    }
}
