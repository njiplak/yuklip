<?php

namespace App\Service\Concierge;

use App\Contract\Concierge\MenuItemContract;
use App\Models\MenuItem;
use App\Service\BaseService;

class MenuItemService extends BaseService implements MenuItemContract
{
    public function __construct(MenuItem $model)
    {
        parent::__construct($model);
    }
}
