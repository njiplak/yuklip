<?php

namespace App\Service\Concierge;

use App\Contract\Concierge\WhatsappMessageContract;
use App\Models\WhatsappMessage;
use App\Service\BaseService;

class WhatsappMessageService extends BaseService implements WhatsappMessageContract
{
    protected array $relation = ['booking'];

    public function __construct(WhatsappMessage $model)
    {
        parent::__construct($model);
    }
}
