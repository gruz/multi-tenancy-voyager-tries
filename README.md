```bash
# 01 Environment setup

## Store project name to a variable to be easily changed
PROJECT_NAME=multi-tenancy-voyager;
## Create project folder and switch to it
mkdir $PROJECT_NAME;
cd $PROJECT_NAME;
## Install laradock
git init
git submodule add https://github.com/Laradock/laradock.git
cd laradock
cp env-example .env
## Run docker containers and login into the workspace container
docker-compose up -d postgres nginx adminer
docker-compose exec --user=laradock workspace bash

## !!! Since this moment we work inside the container

## Create laravel project. We need an intermediate tmp folder as our current folder is not 
## empty and laravel installation would fail otherwise
composer create-project --prefer-dist laravel/laravel tmp
shopt -s dotglob
mv ./tmp/* .
rm -rf ./tmp

## Update default mysql connection
sed -i "s/DB_CONNECTION=mysql/DB_CONNECTION=system/g" .env
sed -i "s/DB_HOST=127\.0\.0\.1/DB_HOST=postgres/g" .env
sed -i "s/DB_PORT=3306/DB_PORT=5432/g" .env
sed -i "s/DB_DATABASE=homestead/DB_DATABASE=default/g" .env
sed -i "s/DB_USERNAME=homestead/DB_USERNAME=default/g" .env


# 02 Laravel-tenancy installation

## Change connection name to system
sed -i "s/'pgsql' => \[/'system' => [/g" ./config/database.php

## Install package and configure the mulitenancy package
composer require "hyn/multi-tenant:5.3.*"
php artisan vendor:publish --tag=tenancy

sed -i "s/'auto-delete-tenant-directory' => false/'auto-delete-tenant-directory' => true/g" ./config/tenancy.php

echo '' >> .env
echo '# Laravel-tenancy config' >> .env
echo 'TENANCY_DATABASE_AUTO_DELETE=true' >> .env
echo 'TENANCY_DATABASE_AUTO_DELETE_USER=true' >> .env


## Move user handling migrations to tenant folder
mkdir database/migrations/tenant
mv database/migrations/2014_10_12_000000_create_users_table.php database/migrations/tenant/
mv database/migrations/2014_10_12_100000_create_password_resets_table.php database/migrations/tenant/

php artisan migrate --database=system

## Create middleware
php artisan make:middleware EnforceTenancy

sed -i "s/return \$next(\$request);/\\\Illuminate\\\Support\\\Facades\\\Config::set('database.default', 'tenant');\n\n        return \$next(\$request);/g" app/Http/Middleware/EnforceTenancy.php

sed -i "s/protected \$routeMiddleware = \[/protected \$routeMiddleware = \[\n        'tenancy.enforce' => \\\App\\\Http\\\Middleware\\\EnforceTenancy::class,/g" app/Http/Kernel.php


# 03 Voyager installation

composer require tcg/voyager
mkdir app/Console/Commands

cat << 'EOF' > app/Console/Commands/CreateTenant.php
<?php

namespace App\Console\Commands;

# use App\Notifications\TenantCreated;
use App\Tenant;
use Illuminate\Console\Command;

class CreateTenant extends Command
{
    protected $signature = 'tenant:create {name} {password} {email}';

    protected $description = 'Creates a tenant with the provided name and email address e.g. php artisan tenant:create boise test boise@example.com';

    public function handle()
    {
        $name = $this->argument('name');
        $email = $this->argument('email');
        $password = $this->argument('password');

        if (Tenant::tenantExists($name)) {
            $this->error("A tenant with name '{$name}' already exists.");
            return;
        }

        $tenant = Tenant::registerTenant($name, $email, $password);
        $this->info("Tenant '{$name}' is created and is now accessible at {$tenant->hostname->fqdn}");

        // invite admin
        // $tenant->admin->notify(new TenantCreated($tenant->hostname));
        $this->info("Admin {$email} can log in using password {$password}");
    }
}

EOF

cat << 'EOF' > app/Console/Commands/DeleteTenant.php
<?php

namespace App\Console\Commands;

use App\Tenant;
use Illuminate\Console\Command;

class DeleteTenant extends Command
{
    protected $signature = 'tenant:delete {name}';
    protected $description = 'Deletes a tenant of the provided name. Only available on the local environment e.g. php artisan tenant:delete boise';

    public function handle()
    {
        // because this is a destructive command, we'll only allow to run this command
        // if you are on the local environment or testing
        if (!app()->isLocal()  && !app()->runningUnitTests()) {
            $this->error('This command is only available on the local environment.');

            return;
        }

        $name = $this->argument('name');
        $result = Tenant::delete($name);
        $this->info($result);
    }
}


EOF

cat << 'EOF' > app/Tenant.php
<?php

namespace App;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Hostname;
use Hyn\Tenancy\Models\Website;
use Illuminate\Support\Facades\Hash;
use Hyn\Tenancy\Contracts\Repositories\HostnameRepository;
use Hyn\Tenancy\Contracts\Repositories\WebsiteRepository;

/**
 * @property Website website
 * @property Hostname hostname
 * @property User admin
 */
class Tenant
{
    public function __construct(Website $website = null, Hostname $hostname = null, User $admin = null)
    {
        $this->website = $website;
        $this->hostname = $hostname;
        $this->admin = $admin;
    }

    public static function delete($name)
    {
        // $baseUrl = env('APP_URL_BASE');
        // $name = "{$name}.{$baseUrl}";
        if ($tenant = Hostname::where('fqdn', $name)->firstOrFail()) {
            app(HostnameRepository::class)->delete($tenant, true);
            app(WebsiteRepository::class)->delete($tenant->website, true);
            return "Tenant {$name} successfully deleted.";
        }
    }

    public static function deleteByFqdn($fqdn)
    {
        if ($tenant = Hostname::where('fqdn', $fqdn)->firstOrFail()) {
            app(HostnameRepository::class)->delete($tenant, true);
            app(WebsiteRepository::class)->delete($tenant->website, true);
            return "Tenant {$fqdn} successfully deleted.";
        }
    }

    public static function registerTenant($name, $email, $password): Tenant
    {
        // Convert all to lowercase
        $name = strtolower($name);
        $email = strtolower($email);

        $website = new Website;
        app(WebsiteRepository::class)->create($website);

        // associate the website with a hostname
        $hostname = new Hostname;
        // $baseUrl = env('APP_URL_BASE', 'localhost');
        // $hostname->fqdn = "{$name}.{$baseUrl}";
        $hostname->fqdn = $name;
        app(HostnameRepository::class)->attach($hostname, $website);

        // make hostname current
        app(Environment::class)->tenant($hostname->website);

        // \Artisan::call('voyager:install');
        \Artisan::call('voyager:install', ['--with-dummy' => true ]);

        // Make the registered user the default Admin of the site.
        $admin = static::makeAdmin($name, $email, $password);

        return new Tenant($website, $hostname, $admin);
    }

    private static function makeAdmin($name, $email, $password): User
    {
        $admin = User::create(['name' => $name, 'email' => $email, 'password' => Hash::make($password)]);
        // $admin->guard_name = 'web';
        $admin->setRole('admin')->save();

        return $admin;
    }

    public static function tenantExists($name)
    {
        $name = $name . '.' . env('APP_URL_BASE');
        return Hostname::where('fqdn', $name)->exists();
    }
}

EOF

cat << 'EOF' > app/Providers/AppServiceProvider.php
<?php

namespace App\Providers;

use Hyn\Tenancy\Environment;
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

        if ($fqdn = optional($env->hostname())->fqdn) {
            config(['database.default' => 'tenant']);
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
EOF


php artisan config:clear

## Move away tenant migrations to avoid creating system tenant tables in the tenant database
mkdir tmpmgr
mv database/migrations/*.php tmpmgr

## Create a user and run voyager:install inside the process
# php artisan tenant:delete boise.wyzoo.test
php artisan tenant:create boise.wyzoo.test 123456 boise@example.com

## Move back system tenant migrations
mv tmpmgr/*.php database/migrations/
rm -rf tmpmgr

## Cleanup Voyager dummy migrations from system migration folder
for entry in "vendor/tcg/voyager/publishable/database/migrations"/*
do
   rm database/migrations/${entry##*/}
done

## Wrap Voyager routes in tenant.enforce middleware
sed -i "s/Route::group(\['prefix' => 'admin'\], function () {/Route::group(\['prefix' => 'admin', 'middleware' => 'tenancy.enforce' \], function () {/g" routes/web.php

# php artisan vendor:publish --provider=VoyagerServiceProvider
# php artisan vendor:publish --provider=ImageServiceProviderLaravel5
# php artisan voyager:install

```
