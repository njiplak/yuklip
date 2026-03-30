<?php

namespace App\Service\Concierge;

use App\Contract\Concierge\SystemLogContract;
use App\Models\SystemLog;
use App\Service\BaseService;

class SystemLogService extends BaseService implements SystemLogContract
{
    protected array $relation = ['booking'];

    public function __construct(SystemLog $model)
    {
        parent::__construct($model);
    }
}
