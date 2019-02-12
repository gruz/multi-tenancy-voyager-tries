# Laravel Multitenant + Voyager installation guide

## Intro

We will install docker environment, [laravel-tenancy.com](https://laravel-tenancy.com/) and [Voyager admin panel](https://laravelvoyager.com/) per-tenant.

We will use CLI commands to create tenants (domains). After the tenant is created, you will be able to login into a Voyager admin per tenant.

We will use Postgres database.

> Please note:
>
> 1. This tutorial is written as a Linux bash script with comments. So a linux user can copy/paste the bash script code into one's terminal.
> 2. After docker initialization you will need to run at least some commands (like `composer update`, `php artisan ...`) inside the docker (linux) environment. This means you will become a linux user, thus see p.1

## Prerequisites

### Software needed

* docker
* docker-compose
* git

### System configuration

Make sure you have all other dockers down and standard ports free to avoid conflicts.

## Installation

### Add domains for tenant tests to your hosts file

#### Manual action

Edit you hosts file to add test domains.

* Linux/MacOS: Edit /etc/hosts
* Windows: https://www.google.com/search?q=windows+hosts+file+path&ie=utf-8&oe=utf-8&client=firefox-b

Add lines like the following ones to have local development domains.

```bash
127.0.0.1 voyager.test
127.0.0.1 kyiv.voyager.test
127.0.0.1 dnipro.voyager.test
127.0.0.1 lviv.voyager.test
```

etc...

#### Linux script way bash

Open terminal window and run `sudo -i` to login as sudo user.

Next paste the script into your terminal.

```bash
FILE=/etc/hosts;
NEW_IP=127.0.0.1;
HOSTS=("voyager.test" "kyiv.voyager.test" "dnipro.voyager.test" "lviv.voyager.test" "odesa.voyager.test" );

for HOST in ${HOSTS[*]}
do
    printf "   %s\n" $HOST
    sed -i.bak -e '$a\' -e "$NEW_IP\t$HOST" -e "/.*$HOST/d" $FILE
done
exit;

```

### Docker setup

```bash
## Create your project folder and install laradock (a docker for laravel)

# Store project name to a variable to be easily changed
PROJECT_NAME="multi-tenancy-voyager";

# Create project folder and switch to it
mkdir $PROJECT_NAME;
cd $PROJECT_NAME;

# Install laradock
git init
git submodule add https://github.com/Laradock/laradock.git
cd laradock
cp env-example .env

# Enable PHP exif used by Voyager Media manager
sed -i "s/PHP_FPM_INSTALL_EXIF=false/PHP_FPM_INSTALL_EXIF=true/g" .env

# Run docker containers and login into the workspace container
    # > Building docker containers can take significant time for the first run.
    # > We run adminer container to have a database management UI tool.
        # Available under localhost:8080
        # System: PostgreSQL
        # Server: postgres
        # Username: default
        # Password: secret
docker-compose up -d postgres nginx adminer
docker-compose exec --user=laradock workspace bash

```

You should see smth. like `laradock@5326c549f4cb:/var/www` in your terminal. That means you are logged in into the docker linux container. We will work next here.

### In-docker workspace setup

Whether you use linux or no, since this moment we work inside the docker container in a linux bash terminal.

So you can just copy/paste the script below in your terminal.

Otherwise read comments to get better understanding what is going on.

```bash
# 01 Create laravel project.
# We need an intermediate tmp folder as our current folder is not
# empty (contains laradoc folder) and laravel installation would fail otherwise
composer create-project --prefer-dist laravel/laravel tmp
# Enable hidden files move
shopt -s dotglob
# Move laravel project files from ./tmp to the project root
mv ./tmp/* .
# Remove the temporary folder.
rm -rf ./tmp

## Update default mysql connection
## Manual:
# Edit you .env file DB connection

# DB_CONNECTION=system
# DB_HOST=postgres
# DB_PORT=5432
# DB_DATABASE=default
# DB_USERNAME=default
# DB_PASSWORD=secret

## Script way
sed -i "s/DB_CONNECTION=mysql/DB_CONNECTION=system/g" .env
sed -i "s/DB_HOST=127\.0\.0\.1/DB_HOST=postgres/g" .env
sed -i "s/DB_PORT=3306/DB_PORT=5432/g" .env
sed -i "s/DB_DATABASE=homestead/DB_DATABASE=default/g" .env
sed -i "s/DB_USERNAME=homestead/DB_USERNAME=default/g" .env

# 02 Laravel-tenancy installation

## Change connection name to from `pgsql` to `system` in `./config/database.php`
sed -i "s/'pgsql' => \[/'system' => [/g" ./config/database.php

## Install package and configure the mulitenancy package
composer require "hyn/multi-tenant:5.3.*"
php artisan vendor:publish --tag=tenancy

## Allow auto deleting tenant folders on tenant delete in `config/tenancy.php`. Optional.
## You should read the file carefully and realize which options it has.
sed -i "s/'auto-delete-tenant-directory' => false/'auto-delete-tenant-directory' => true/g" ./config/tenancy.php

# Edit .env file and change APP_URL to our system URL (the main web-site URL)
SYSTEM_FQDN="voyager.test"
APP_URL="http://"$SYSTEM_FQDN;
sed -i "s|APP_URL=http:\/\/localhost|APP_URL=${APP_URL}|g" .env

## Append to your laravel .env file with the following options. Remember 
echo '' >> .env
echo '# Laravel-tenancy config' >> .env
echo 'TENANCY_DATABASE_AUTO_DELETE=true' >> .env
echo 'TENANCY_DATABASE_AUTO_DELETE_USER=true' >> .env
echo 'TENANCY_ABORT_WITHOUT_HOSTNAME=true' >> .env
echo '' >> .env
echo '# System site' >> .env
# TENANCY_MAIN_SITE="voyager.text"
echo 'TENANCY_MAIN_SITE="'$SYSTEM_FQDN'"' >> .env

## Move user handling migrations to tenant folder to have per-tenant user tables 
## and to avoid creating unneeded tables in your system database.

# Make `database/migrations/tenant` folder
mkdir database/migrations/tenant
# Copy `2014_10_12_000000_create_users_table.php` and `2014_10_12_100000_create_password_resets_table.php`
# to the newly created folder so we will create user tables per tenant.
cp database/migrations/2014_10_12_000000_create_users_table.php database/migrations/tenant/
cp database/migrations/2014_10_12_100000_create_password_resets_table.php database/migrations/tenant/

# Run database migrations for the system DB only.
# After that you'll find the tables in your `defualt` database:
# `hostnames`, `migrations`, `websites`
php artisan migrate --database=system

# 03 Adding CLI laravel commands to create tenants

# Make folder for console commands
mkdir app/Console/Commands

# Create console commands to create/delete tenants
# File path and contents is obvious from the bash commands below.

# For non-linux user. The following code should be read as:
# Create(replace if exists) file `path/to/a/file.php` with content `... some contents ...`
# `cat << 'EOF' > path/to/a/file.php
#  ... some contents ...
# EOF`

cat << 'EOF' > app/Console/Commands/CreateTenant.php
<?php

namespace App\Console\Commands;

// use App\Notifications\TenantCreated;
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

# Add the main class, which will do the tenant creation/deletions job.
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

    public static function registerTenant($name, $email = null, $password = null): Tenant
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

        // We rename temporary tenant migrations to avoid creating system tenant tables in the tenant database
        $migrations = getcwd() . '/database/migrations/';
        $files_to_preserve = glob($migrations . '*.php');

        foreach ($files_to_preserve as $file) {
            rename($file, $file . '.txt');
        }

        // \Artisan::call('voyager:install');
        \Artisan::call('config:clear');
        \Artisan::call('voyager:install', ['--with-dummy' => true ]);

        foreach ($files_to_preserve as $file) {
            rename($file.'.txt', $file);
        }

        // Cleanup Voyager dummy migrations from system migration folder
        $voyager_migrations = getcwd() . '/vendor/tcg/voyager/publishable/database/migrations/*.php';
        $files_to_kill = glob($voyager_migrations);
        $files_to_kill = array_map('basename', $files_to_kill);

        foreach ($files_to_kill as $file) {
            $path = $migrations. '/'. $file;
            unlink($path);
        }

        // Make the registered user the default Admin of the site.
        $admin = null;
        if ($email) {
            $admin = static::makeAdmin($name, $email, $password);
        }

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
        // $name = $name . '.' . env('APP_URL_BASE');
        return Hostname::where('fqdn', $name)->exists();
    }
}
EOF

# 04 Voyager installation

# Disable autodiscover for Voyager to load it only after your AppServiceProvider is loaded.
# This is needed, because you must be sure Voyager loads all it's staff after the
# DB connection is switch to tenant

# Alas CLI composer update fails
# config extra.laravel.dont-discover tcg/voyager
# So we need to update composer.json on our own.

# Manual
# In composer.json add `tcg/voyager` to `dont-disover` array:
# "extra": {
#     "laravel": {
#         "dont-discover": [
#             "tcg/voyager"
#         ]
#     }
# },

# Bash script 
sed -i "s/\"dont\-discover\"\: \[\]/\"dont\-discover\"\: [\"tcg\/voyager\"]/g" composer.json

# Install Voyager composer package
composer require tcg/voyager

# 05 Voyager/tenancy setup

# Add `TCG\Voyager\VoyagerServiceProvider::class` to config/app.php providers array
sed -i "s/\(App\\\Providers\\\RouteServiceProvider::class,\)/\1\n        TCG\\\Voyager\\\VoyagerServiceProvider::class,/g" config/app.php

# Register Voyager install command to app/Console/Kernel.php. Will be needed to create tenants via system Voyager.
sed -i "s/\(protected \$commands = \[\)/\1\n        \\\TCG\\\Voyager\\\Commands\\\InstallCommand::class,/g" app/Console/Kernel.php

# Create a model for system Voyager
php artisan make:model Hostname

# Add a system Voyager controller to create/delete tenants
cat << 'EOF' > app/Http/Controllers/VoyagerTenantsController.php
<?php

namespace App\Http\Controllers;

use App\Tenant;
use Hyn\Tenancy\Environment;
use Illuminate\Http\Request;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Events\BreadDataDeleted;

class VoyagerTenantsController extends \TCG\Voyager\Http\Controllers\VoyagerBaseController
{
    /**
     * Check if current request is an add of a tenant
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return bool
     */
    private function isTenantAdd(Request $request) {
        $slug = $this->getSlug($request);

        $env = app(Environment::class);
        $fqdn = optional($env->hostname())->fqdn;
        $mainSite = env('TENANCY_MAIN_SITE');

        if (env('TENANCY_MAIN_SITE') !== $fqdn || 'hostnames' !== $slug) {
            return false;
        }

        return true;
    }

    /**
     * POST BRE(A)D - Store data.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        if (!$this->isTenantAdd($request)) {
            return parent::store($request);
        }

        $slug = $this->getSlug($request);

        $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();

        // Check permission
        $this->authorize('add', app($dataType->model_name));

        // Validate fields with ajax
        $val = $this->validateBread($request->all(), $dataType->addRows);

        if ($val->fails()) {
            return response()->json(['errors' => $val->messages()]);
        }

        if (!$request->has('_validate')) {
            $name = $request->get('fqdn');

            if (Tenant::tenantExists($name)) {
                $messages = ["A tenant with name '{$name}' already exists."];
                return response()->json(['errors' => $messages]);
            }

            $tenant = Tenant::registerTenant($name);
            // $this->info("Tenant '{$name}' is created and is now accessible at {$tenant->hostname->fqdn}");

            // $data = $this->insertUpdateData($request, $slug, $dataType->addRows, new $dataType->model_name());

            // event(new BreadDataAdded($dataType, $data));

            if ($request->ajax()) {
                return response()->json(['success' => true, 'data' => $data]);
            }

            return redirect()
                ->route("voyager.{$dataType->slug}.index")
                ->with([
                        'message'    => __('voyager::generic.successfully_added_new')." {$dataType->display_name_singular}",
                        'alert-type' => 'success',
                    ]);
        }
    }


    //***************************************
    //                _____
    //               |  __ \
    //               | |  | |
    //               | |  | |
    //               | |__| |
    //               |_____/
    //
    //         Delete an item BREA(D)
    //
    //****************************************

    public function destroy(Request $request, $id)
    {
        if (!$this->isTenantAdd($request)) {
            return parent::store($request);
        }

        $slug = $this->getSlug($request);

        $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();

        // Check permission
        $this->authorize('delete', app($dataType->model_name));

        // Init array of IDs
        $ids = [];
        if (empty($id)) {
            // Bulk delete, get IDs from POST
            $ids = explode(',', $request->ids);
        } else {
            // Single item delete, get ID from URL
            $ids[] = $id;
        }

        foreach ($ids as $id) {
            $data = call_user_func([$dataType->model_name, 'findOrFail'], $id, $columns = array('fqdn') );
            $this->cleanup($dataType, $data);
            $hostname = $data->fqdn;
            $res = Tenant::delete($hostname);
        }


        $displayName = count($ids) > 1 ? $dataType->display_name_plural : $dataType->display_name_singular;


        // $res = $data->destroy($ids);
        $data = $res
            ? [
                'message'    => __('voyager::generic.successfully_deleted')." {$displayName}",
                'alert-type' => 'success',
            ]
            : [
                'message'    => __('voyager::generic.error_deleting')." {$displayName}",
                'alert-type' => 'error',
            ];

        if ($res) {
            event(new BreadDataDeleted($dataType, $data));
        }

        return redirect()->route("voyager.{$dataType->slug}.index")->with($data);
    }

}
EOF

# Update your AppServiceProvider.php to switch to tenant DB and filesystem
# when requesting a tenant URL
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
            if (env('TENANCY_MAIN_SITE') !== $fqdn ) {
                config(['database.default' => 'tenant']);
                config(['voyager.storage.disk' => 'tenant']);
            }
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

# Override Hyn Laravel tenanty Mediacontroller to make it work with Voyager.
# Hyn forces to use `media` folder to store files while Voyager reads root
# of the storage folder.
# So we create our own controller.
cat << 'EOF' > app/Http/Controllers/HynOverrideMediaController.php
<?php

namespace App\Http\Controllers;

use Hyn\Tenancy\Website\Directory;
use Illuminate\Support\Facades\Storage;

/**
 * Class MediaController
 *
 * @use Route::get('/storage/{path}', App\MediaController::class)
 *          ->where('path', '.+')
 *          ->name('tenant.media');
 */
class HynOverrideMediaController extends \Hyn\Tenancy\Controllers\MediaController
{
    /**
     * @var Directory
     */
    private $directory;

    public function __construct(Directory $directory)
    {
        $this->directory = $directory;
    }

    public function __invoke(string $path)
    {
        // $path = "media/$path";

        if ($this->directory->exists($path)) {
            return response($this->directory->get($path))
                ->header('Content-Type', Storage::disk('tenant')->mimeType($path));
        }

        return abort(404);
    }
}
EOF

# And set all paths requesting uploaded files to use just created controller.
cat << 'EOF' >> routes/web.php
Route::get('/storage/{path}', '\App\Http\Controllers\HynOverrideMediaController')
    ->where('path', '.+')
    ->name('tenant.media');
EOF

# 06 Install System Voyager



# 07 Create a tenant
php artisan config:clear

# Create a couple of users.
# This will create a tenant and install Voyager databases for each user.
php artisan tenant:create "kyiv."$SYSTEM_FQDN 123456 kyiv@example.com
php artisan tenant:create dnipro.voyager.test 123456 dnipro@example.com

# Later you may use the command to delet a tenant.
# php artisan tenant:delete kyiv.voyager.test
```

### Check results

Open [http://kyiv.voyager.test/admin](http://kyiv.voyager.test/admin) and
[http://dnipro.voyager.test/admin](http://dnipro.voyager.test/admin)
in your browser and login using credentials of the user you have just created.

You can also use Voyager dummy data user to login: `admin@admin.com`/`password`.

Try editing data and uploading files to different tenants to be sure the data is different per tenant.

## Working with docker

Go to your project folder and go to `laradock` subfolder.

### Stop docker

```bash
docker-compose down
```

### Run docker

Do not run just `docker-compose up`. Laradock contains dozens of containers and will try to run all of them.

Run only needed containers.

```bash
docker-compose up -d postgres nginx
```

If you need, you can also run `adminer`

```bash
docker-compose up -d adminer
```
