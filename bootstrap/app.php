<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        health: '/up',
        then: function () {
            $loadRoutes = function ($directory, $middleware) {
                if (!is_dir($directory)) {
                    return;
                }

                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
                );

                foreach ($iterator as $file) {
                    if ($file->isFile() && $file->getExtension() === 'php') {
                        Route::middleware($middleware)->group($file->getPathname());
                    }
                }
            };

            $loadRoutes(base_path('routes/web'), 'web');
            $loadRoutes(base_path('routes/api'), 'api');
        }
    )
    ->withSchedule(function (Schedule $schedule) {
        $schedule->job(new \App\Jobs\CheckoutArchiveJob, 'agents')->dailyAt('02:00')->timezone('Africa/Casablanca');
        $schedule->job(new \App\Jobs\FollowUpJob, 'agents')->dailyAt('02:30')->timezone('Africa/Casablanca');
        $schedule->job(new \App\Jobs\StaffBriefingJob, 'agents')->dailyAt('08:00')->timezone('Africa/Casablanca');
        $schedule->job(new \App\Jobs\UpsellBroadcastJob, 'agents')->dailyAt('09:00')->timezone('Africa/Casablanca');
        $schedule->job(new \App\Jobs\UpsellBroadcastJob, 'agents')->dailyAt('15:00')->timezone('Africa/Casablanca');
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->redirectGuestsTo('/auth/login');
        $middleware->redirectUsersTo('/backoffice');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
