<?php

namespace App\Providers;

use Hyn\Tenancy\Environment;
use TCG\Voyager\Facades\Voyager;
use App\Actions\TenantViewAction;
use App\Actions\TenantLoginAction;
use App\Actions\TenantDeleteAction;
use TCG\Voyager\Actions\ViewAction;
use TCG\Voyager\Actions\DeleteAction;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $env = app(Environment::class);

        $isSystem = true; 

        if ($fqdn = optional($env->hostname())->fqdn) {
            if (\App\Tenant::getRootFqdn() !== $fqdn ) {
                config(['database.default' => 'tenant']);
                config(['voyager.storage.disk' => 'tenant']);
                $isSystem = false; 
            }
        }

        if ($isSystem) {
            Voyager::addAction(TenantLoginAction::class);
            Voyager::replaceAction(ViewAction::class, TenantViewAction::class);
            Voyager::replaceAction(DeleteAction::class, TenantDeleteAction::class);
        }
        //
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
