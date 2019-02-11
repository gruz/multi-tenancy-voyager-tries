<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use TCG\Voyager\Policies\BasePolicy;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use TCG\Voyager\Facades\Voyager as VoyagerFacade;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        'App\Model' => 'App\Policies\ModelPolicy',
        // 'TCG\Voyager\Models\Setting' => 'TCG\Voyager\Policies\SettingPolicy',
        // 'TCG\Voyager\Models\MenuItem' => 'TCG\Voyager\Policies\MenuItemPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();
        return;
        try {
            if (Schema::hasTable('data_types')) {
                $dataType = VoyagerFacade::model('DataType');
                $dataTypes = $dataType->select('policy_name', 'model_name')->get();

                foreach ($dataTypes as $dataType) {
                    $policyClass = BasePolicy::class;
                    if (isset($dataType->policy_name) && $dataType->policy_name !== ''
                        && class_exists($dataType->policy_name)) {
                        $policyClass = $dataType->policy_name;
                    }

                    $this->policies[$dataType->model_name] = $policyClass;
                }

                $this->registerPolicies();
            }
        } catch (\PDOException $e) {
            Log::error('No Database connection yet in VoyagerServiceProvider registerGates()');
        }
    }
}
