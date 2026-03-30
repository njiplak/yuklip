<?php

namespace App\Providers;

use App\Contract\Auth\UserAuthContract;
use App\Contract\AuthContract;
use App\Contract\BaseContract;
use App\Contract\Concierge\BookingContract;
use App\Contract\Concierge\OfferContract;
use App\Contract\Concierge\SystemLogContract;
use App\Contract\Concierge\TransactionContract;
use App\Contract\Concierge\UpsellLogContract;
use App\Contract\Concierge\WhatsappMessageContract;
use App\Contract\Setting\PermissionContract;
use App\Contract\Setting\RoleContract;
use App\Contract\Setting\SettingContract;
use App\Service\Auth\UserAuthService;
use App\Service\AuthService;
use App\Service\BaseService;
use App\Service\Concierge\BookingService;
use App\Service\Concierge\OfferService;
use App\Service\Concierge\SystemLogService;
use App\Service\Concierge\TransactionService;
use App\Service\Concierge\UpsellLogService;
use App\Service\Concierge\WhatsappMessageService;
use App\Service\Setting\PermissionService;
use App\Service\Setting\RoleService;
use App\Service\Setting\SettingService;
use Illuminate\Support\ServiceProvider;

class ContractProvider extends ServiceProvider
{
    public array $bindings = [
        // Base
        BaseContract::class => BaseService::class,
        AuthContract::class => AuthService::class,
        UserAuthContract::class => UserAuthService::class,

        // Setting
        SettingContract::class => SettingService::class,
        RoleContract::class => RoleService::class,
        PermissionContract::class => PermissionService::class,

        // Concierge
        BookingContract::class => BookingService::class,
        OfferContract::class => OfferService::class,
        UpsellLogContract::class => UpsellLogService::class,
        TransactionContract::class => TransactionService::class,
        WhatsappMessageContract::class => WhatsappMessageService::class,
        SystemLogContract::class => SystemLogService::class,
    ];

    public function register(): void
    {
        foreach ($this->bindings as $contract => $service) {
            $this->app->bind($contract, $service);
        }
    }

    public function boot(): void {}
}
