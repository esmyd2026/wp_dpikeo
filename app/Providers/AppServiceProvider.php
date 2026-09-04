<?php

namespace App\Providers;

use App\Services\PermissionService;
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

        // Las credenciales de WhatsApp Cloud API viven por empresa en
        // whatsapp_business_profiles (columna company_id) y cada WhatsappService
        // las resuelve desde el perfil activo (ver WhatsappCredentialService).
        // config('whatsapp.*') solo se usa como fallback de arranque cuando
        // todavía no existe ningún perfil en la base (instalación nueva).

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

                $authorizedCompanies = $user->authorizedCompanies();
                $view->with('authorizedCompanies', $authorizedCompanies);

                // Este composer corre en TODAS las vistas admin.* -- si
                // CompanyContext::current() falla (ej. la empresa activa
                // tiene 2+ números conectados y ninguno marcado principal,
                // WHATSAPP_PRIMARY_PROFILE_NOT_CONFIGURED), no puede tumbar
                // el panel entero. Cae al primer nombre autorizado solo para
                // el chrome del sidebar; la pantalla que sí necesita el
                // perfil resuelto (dashboard, catálogo, etc.) sigue fallando
                // explícito como corresponde.
                $activeCompany = $authorizedCompanies->first();
                if ($authorizedCompanies->count() > 1) {
                    try {
                        $activeCompany = \App\Support\CompanyContext::current()->company;
                    } catch (\Throwable $e) {
                        // Se mantiene el fallback de arriba.
                    }
                }
                $view->with('activeCompany', $activeCompany);
            }
        });
    }
}
