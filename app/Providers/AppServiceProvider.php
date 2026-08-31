<?php

namespace App\Providers;

use App\Models\WhatsappBusinessProfile;
use App\Services\PermissionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
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

        // Las credenciales de WhatsApp Cloud API se administran desde el panel
        // (Configuración del bot → Credenciales de WhatsApp) y viven en la tabla
        // whatsapp_business_profiles, no en el .env. Se sobreescriben aquí los
        // valores de config() para que TODO el código (WhatsappService y demás)
        // use siempre lo último guardado en el panel sin necesitar redeploy.
        if (Schema::hasTable('whatsapp_business_profiles')) {
            $profile = WhatsappBusinessProfile::first();
            if ($profile) {
                config([
                    'whatsapp.token' => $profile->access_token ?: config('whatsapp.token'),
                    'whatsapp.phone_number_id' => $profile->phone_number_id ?: config('whatsapp.phone_number_id'),
                    'whatsapp.phone_number' => $profile->phone_number ?: config('whatsapp.phone_number'),
                    'whatsapp.business_id' => $profile->whatsapp_business_id ?: config('whatsapp.business_id'),
                ]);
            }
        }

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
