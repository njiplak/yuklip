<?php

namespace App\Service\Concierge;

use App\Contract\Concierge\TransactionContract;
use App\Models\Transaction;
use App\Service\BaseService;

class TransactionService extends BaseService implements TransactionContract
{
    protected array $relation = ['booking'];

    public function __construct(Transaction $model)
    {
        parent::__construct($model);
    }
}
