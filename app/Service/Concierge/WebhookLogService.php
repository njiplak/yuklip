<?php

namespace App\Service\Concierge;

use App\Contract\Concierge\WebhookLogContract;
use App\Models\WebhookLog;
use App\Service\BaseService;

class WebhookLogService extends BaseService implements WebhookLogContract
{
    protected array $relation = [];

    public function __construct(WebhookLog $model)
    {
        parent::__construct($model);
    }
}
