<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        'App\Model' => 'App\Policies\ModelPolicy',
        'TCG\Voyager\Models\Setting' => 'TCG\Voyager\Policies\SettingPolicy',
        'TCG\Voyager\Models\MenuItem' => 'TCG\Voyager\Policies\MenuItemPolicy',
        'TCG\Voyager\Models\User' => 'TCG\Voyager\Policies\UserPolicy',
        'TCG\Voyager\Models\Menu' => 'TCG\Voyager\Policies\BasePolicy',
        'TCG\Voyager\Models\Role' => 'TCG\Voyager\Policies\BasePolicy',
        'TCG\Voyager\Models\Category' => 'TCG\Voyager\Policies\BasePolicy',
        'TCG\Voyager\Models\Post' => 'TCG\Voyager\Policies\BasePolicy',
        'TCG\Voyager\Models\Page' => 'TCG\Voyager\Policies\BasePolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        //
    }
}
