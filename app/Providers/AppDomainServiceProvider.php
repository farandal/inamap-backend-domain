<?php

namespace Domain\App\Providers;

use App\Providers\AppServiceProvider;
use Domain\App\Console\Commands\PruneRouteSessionsCommand;
use Domain\App\Models\Inamap\Layout;
use Domain\App\Models\Inamap\Path;
use Domain\App\Models\Inamap\PathLink;
use Domain\App\Observers\GraphInvalidationObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Provider del dominio INAMAP. El core lo registra solo si la clase existe
 * (config/app.php) y después de sus propios providers, por lo que todo lo que
 * se haga aquí puede sobrescribir defaults del core.
 *
 * No llama a parent::register()/boot(): el AppServiceProvider del core ya
 * corre como su propia instancia.
 */
class AppDomainServiceProvider extends AppServiceProvider
{
    public function register()
    {
        $this->app->singleton(\Domain\App\Services\Inamap\Tenancy\TenantContext::class);
    }

    public function boot()
    {
        App::register(CommandServiceProvider::class);

        $this->registerRateLimiters();
        $this->registerGraphInvalidation();
        $this->mergeDomainTenantSettings();
        $this->registerPlanLimitFormats();
        $this->registerSchedule();
    }

    /** Throttle de la API pública (RNF-05). */
    private function registerRateLimiters(): void
    {
        RateLimiter::for('inamap-public', fn (Request $request) => Limit::perMinute((int) config('inamap.public_rate_limit', 120))
            ->by($request->ip()));

        RateLimiter::for('inamap-sessions', fn (Request $request) => Limit::perMinute((int) config('inamap.session_rate_limit', 20))
            ->by($request->ip() . '|' . $request->input('inamap')));
    }

    /** Cualquier cambio de caminos, enlaces o layouts invalida el grafo cacheado. */
    private function registerGraphInvalidation(): void
    {
        foreach ([Path::class, PathLink::class, Layout::class] as $model) {
            $model::observe(GraphInvalidationObserver::class);
        }
    }

    /**
     * Anexa los ajustes de tenant del dominio a config('tenants.*') para que
     * la UI de ajustes del tenant los muestre (S3: velocidad peatonal).
     */
    private function mergeDomainTenantSettings(): void
    {
        $path = base_path('domain/config/tenant_settings.php');
        if (!file_exists($path)) {
            return;
        }

        $domain = require $path;

        foreach (['setting_formats', 'encrypted_setting_keys', 'attribute_formats'] as $key) {
            config(["tenants.{$key}" => array_merge(config("tenants.{$key}", []), $domain[$key] ?? [])]);
        }
    }

    /**
     * El formulario de límites del plan lo define el core (config 'subscription_plans.limit_formats')
     * con los campos de KitchnTabs (productos, notificaciones push) y sin los de este dominio, así
     * que el System admin no podía fijar max_inamaps. Aquí el dominio publica su propia lista.
     * Reemplaza (no suma): un dominio solo ve sus propios límites.
     */
    private function registerPlanLimitFormats(): void
    {
        $formats = config('inamap.plan_limit_formats');

        if (is_array($formats) && $formats !== []) {
            config(['subscription_plans.limit_formats' => array_values($formats)]);
        }
    }

    private function registerSchedule(): void
    {
        $this->callAfterResolving(\Illuminate\Console\Scheduling\Schedule::class, function ($schedule) {
            $schedule->command(PruneRouteSessionsCommand::class)
                ->daily()
                ->name('inamap-prune-route-sessions')
                ->withoutOverlapping();
        });
    }
}
