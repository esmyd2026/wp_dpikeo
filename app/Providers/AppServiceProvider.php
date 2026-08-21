<?php

namespace App\Providers;

use App\Services\PermissionService;
use App\Services\PlatformBillingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::setLocale(config('app.locale', 'es'));

        // Sin esto, url()/asset() usan el Host de la petición entrante en vez
        // de APP_URL. En local eso genera links con 127.0.0.1, que Meta no
        // puede descargar al enviar imágenes por WhatsApp (error 131053).
        URL::forceRootUrl(config('app.url'));
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Blade::if('perm', function (string $permission) {
            $user = auth()->user();

            return $user && app(PermissionService::class)->userCan($user, $permission);
        });

        view()->composer(['admin.*', 'admin.layouts.app'], function ($view) {
            $user = auth()->user();
            $view->with('canPerm', function (string $permission) use ($user) {
                return $user && app(PermissionService::class)->userCan($user, $permission);
            });

            if ($user) {
                $billing = app(PlatformBillingService::class);
                $view->with('platformFeatureAccess', [
                    'chat_blocked' => $billing->isChatSuspended($user),
                    'orders_blocked' => $billing->isOrdersSuspended($user),
                    'bot_blocked' => $billing->isBotSuspended(null),
                ]);

                if (app(PermissionService::class)->userCan($user, 'message_failures.menu')) {
                    $view->with(
                        'unresolvedFailuresCount',
                        \App\Models\WhatsappMessageFailure::unresolved()->count()
                    );
                }
            }
        });
    }
}
