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

    public static function getRootFqdn()
    {
        return Hostname::where('website_id', null)->first()->fqdn;
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

    public static function deleteById($id)
    {
        if ($tenant = Hostname::where('id', $id)->firstOrFail()) {
            app(HostnameRepository::class)->delete($tenant, true);
            app(WebsiteRepository::class)->delete($tenant->website, true);
            return "Tenant with id {$id} successfully deleted.";
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
        \Artisan::call('passport:install');

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



# Update your AppServiceProvider.php to switch to tenant DB and filesystem
# when requesting a tenant URL
cat << 'EOF' > app/Providers/AppServiceProvider.php
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

php artisan voyager:install --with-dummy

# Create a model for system Voyager
php artisan make:model Hostname

# Add a system Voyager controller to create/delete tenants
cat << 'EOF' > app/Http/Controllers/VoyagerTenantsController.php
<?php

namespace App\Http\Controllers;

use App\Tenant;
use Hyn\Tenancy\Environment;
use Illuminate\Http\Request;
use Hyn\Tenancy\Models\Hostname;
use TCG\Voyager\Facades\Voyager;
use Illuminate\Support\Facades\DB;
use TCG\Voyager\Events\BreadDataAdded;
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
    private function isTenantOperation(Request $request) {
        $slug = $this->getSlug($request);

        $env = app(Environment::class);
        $fqdn = optional($env->hostname())->fqdn;

        if (\App\Tenant::getRootFqdn() !== $fqdn || 'hostnames' !== $slug) {
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
        if (!$this->isTenantOperation($request)) {
            return parent::store($request);
        }

        $fqdn = $request->get('fqdn') . '.' . \App\Tenant::getRootFqdn();
        $request->offsetSet('fqdn', $fqdn);


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

            $tenant = Tenant::registerTenant($fqdn);

            $data = Hostname::where('fqdn', $fqdn)->firstOrFail();

            // $data = $this->insertUpdateData($request, $slug, $dataType->addRows, new $dataType->model_name());

            event(new BreadDataAdded($dataType, $data));

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
        if (!$this->isTenantOperation($request)) {
            return parent::destroy($request);
        }

        $slug = $this->getSlug($request);

        $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();

        $fqdn = Hostname::where('id', $id)->firstOrFail(['fqdn'])->fqdn;
        $systemSite = \App\Tenant::getRootFqdn();

        if ( $systemSite === $fqdn ) {
            return redirect()
                ->route("voyager.{$dataType->slug}.index")
                ->with([
                        'message'    => __('voyager::generic.system.site.cannot.be.deleted'),
                        'alert-type' => 'error',
                    ]);
        }

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

        $res = false;
        foreach ($ids as $id) {
            $data = call_user_func([$dataType->model_name, 'findOrFail'], $id, $columns = array('fqdn') );
            $this->cleanup($dataType, $data);
            $res = Tenant::deleteById($id);
        }

        $displayName = count($ids) > 1 ? $dataType->display_name_plural : $dataType->display_name_singular;

        // TODO ##mygruz20190213014253
        // If deleting several domains, we can get partial successfull result. We must properly handle the situations.
        // Currently if we have at least one (or last) success, we return a success message.
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

    // POST BR(E)AD
    public function update(Request $request, $id)
    {
        if (!$this->isTenantOperation($request)) {
            return parent::update($request, $id);
        }

        $systemSiteId = Hostname::where('website_id', null)->first()->id;
        $systemSite = \App\Tenant::getRootFqdn();

        if ( $systemSiteId === intval($id) ) {

            $slug = $this->getSlug($request);

            $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();

            // Compatibility with Model binding.
            $id = $id instanceof Model ? $id->{$id->getKeyName()} : $id;

            $data = call_user_func([$dataType->model_name, 'findOrFail'], $id);

            // Check permission
            $this->authorize('edit', $data);

            // Validate fields with ajax
            $val = $this->validateBread($request->all(), $dataType->editRows, $dataType->name, $id);

            if ($val->fails()) {
                return response()->json(['errors' => $val->messages()]);
            }

            if (!$request->ajax()) {
                $newSystemSite = $request->fqdn;
                $hostnames = Hostname::where('website_id', '<>', null)->get();

                DB::beginTransaction();
                // do all your updates here

                foreach ($hostnames as $hostname) {
                    $newFqdn = preg_replace('/(.*)(\.' . $systemSite . '$)/', '$1.' . $newSystemSite, $hostname->fqdn);

                    DB::table('hostnames')
                            ->where('id', '=', $hostname->id)
                            ->update([
                                'fqdn' => $newFqdn  // update your field(s) here
                            ]);
                }
                // when done commit
                DB::commit();

                parent::update($request, $id);

                $slug = $this->getSlug($request);

                $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();

                return redirect()
                    ->to('//' . $newSystemSite  . '/admin/');
            }

        } else {
            $fqdn = $request->get('fqdn') . '.' . \App\Tenant::getRootFqdn();
            $request->offsetSet('fqdn', $fqdn);

            return parent::update($request, $id);
        }
    }


    //***************************************
    //                _____
    //               |  __ \
    //               | |__) |
    //               |  _  /
    //               | | \ \
    //               |_|  \_\
    //
    //  Read an item of our Data Type B(R)EAD
    //
    //****************************************

    public function show(Request $request, $id)
    {
        if (!$this->isTenantOperation($request)) {
            return parent::show($request, $id);
        }

        $slug = $this->getSlug($request);

        $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();

        if (strlen($dataType->model_name) != 0) {
            $model = app($dataType->model_name);
            $dataTypeContent = call_user_func([$model, 'findOrFail'], $id);
        } else {
            // If Model doest exist, get data from table name
            $dataTypeContent = DB::table($dataType->name)->where('id', $id)->first();
        }

        // Replace relationships' keys for labels and create READ links if a slug is provided.
        $dataTypeContent = $this->resolveRelations($dataTypeContent, $dataType, true);

        $systemSite = \App\Tenant::getRootFqdn();
        $dataTypeContent->fqdn = preg_replace('/(.*)(\.' . $systemSite . '$)/', '$1', $dataTypeContent->fqdn);

        // If a column has a relationship associated with it, we do not want to show that field
        $this->removeRelationshipField($dataType, 'read');

        // Check permission
        $this->authorize('read', $dataTypeContent);

        // Check if BREAD is Translatable
        $isModelTranslatable = is_bread_translatable($dataTypeContent);

        $view = 'voyager::bread.read';

        if (view()->exists("voyager::$slug.read")) {
            $view = "voyager::$slug.read";
        }

        return Voyager::view($view, compact('dataType', 'dataTypeContent', 'isModelTranslatable'));
    }

}
EOF


# Create Voyager templates overrides for system voyager installation. This will allow better experience
# at manage tenants page

mkdir -p resources/views/vendor/voyager/formfields/;
mkdir -p resources/views/vendor/voyager/hostnames/;

cat << 'EOF' > resources/views/vendor/voyager/formfields/text.blade.php
@if($row->field == 'fqdn')
        <?php
        $systemSite = \App\Tenant::getRootFqdn();
        $dataTypeContent->fqdn = preg_replace('/(.*)(\.' . $systemSite . '$)/', '$1', $dataTypeContent->fqdn);
        ?>
@endif

<input @if($row->required == 1) required @endif type="text" class="form-control" name="{{ $row->field }}"
        placeholder="{{ isset($options->placeholder)? old($row->field, $options->placeholder): $row->display_name }}"
       {!! isBreadSlugAutoGenerator($options) !!}
       value="@if(isset($dataTypeContent->{$row->field})){{ old($row->field, $dataTypeContent->{$row->field}) }}@elseif(isset($options->default)){{ old($row->field, $options->default) }}@else{{ old($row->field) }}@endif">
EOF

cat << 'EOF' > resources/views/vendor/voyager/hostnames/read.blade.php
@extends('voyager::master')

@section('content')
    <div class="page-content read container-fluid">
        <div class="row">
            <div class="col-md-12">

                <div class="panel panel-bordered" style="padding-bottom:5px;">
                    <!-- form start -->
                    @foreach($dataType->readRows as $row)
                        <div class="panel-heading" style="border-bottom:0;">
                            <h3 class="panel-title">{{ $row->display_name }}</h3>
                        </div>

                        <div class="panel-body" style="padding-top:0;">
                            @if($row->type == "image")
                                <img class="img-responsive"
                                     src="{{ filter_var($dataTypeContent->{$row->field}, FILTER_VALIDATE_URL) ? $dataTypeContent->{$row->field} : Voyager::image($dataTypeContent->{$row->field}) }}">
                            @elseif($row->type == 'multiple_images')
                                @if(json_decode($dataTypeContent->{$row->field}))
                                    @foreach(json_decode($dataTypeContent->{$row->field}) as $file)
                                        <img class="img-responsive"
                                             src="{{ filter_var($file, FILTER_VALIDATE_URL) ? $file : Voyager::image($file) }}">
                                    @endforeach
                                @else
                                    <img class="img-responsive"
                                         src="{{ filter_var($dataTypeContent->{$row->field}, FILTER_VALIDATE_URL) ? $dataTypeContent->{$row->field} : Voyager::image($dataTypeContent->{$row->field}) }}">
                                @endif
                            @elseif($row->type == 'relationship')
                                 @include('voyager::formfields.relationship', ['view' => 'read', 'options' => $row->details])
                            @elseif($row->type == 'select_dropdown' && property_exists($row->details, 'options') &&
                                    !empty($row->details->options->{$dataTypeContent->{$row->field}})
                            )
                                <?php echo $row->details->options->{$dataTypeContent->{$row->field}};?>
                            @elseif($row->type == 'select_multiple')
                                @if(property_exists($row->details, 'relationship'))

                                    @foreach(json_decode($dataTypeContent->{$row->field}) as $item)
                                        {{ $item->{$row->field}  }}
                                    @endforeach

                                @elseif(property_exists($row->details, 'options'))
                                    @if (count(json_decode($dataTypeContent->{$row->field})) > 0)
                                        @foreach(json_decode($dataTypeContent->{$row->field}) as $item)
                                            @if (@$row->details->options->{$item})
                                                {{ $row->details->options->{$item} . (!$loop->last ? ', ' : '') }}
                                            @endif
                                        @endforeach
                                    @else
                                        {{ __('voyager::generic.none') }}
                                    @endif
                                @endif
                            @elseif($row->type == 'date' || $row->type == 'timestamp')
                                {{ property_exists($row->details, 'format') ? \Carbon\Carbon::parse($dataTypeContent->{$row->field})->formatLocalized($row->details->format) : $dataTypeContent->{$row->field} }}
                            @elseif($row->type == 'checkbox')
                                @if(property_exists($row->details, 'on') && property_exists($row->details, 'off'))
                                    @if($dataTypeContent->{$row->field})
                                    <span class="label label-info">{{ $row->details->on }}</span>
                                    @else
                                    <span class="label label-primary">{{ $row->details->off }}</span>
                                    @endif
                                @else
                                {{ $dataTypeContent->{$row->field} }}
                                @endif
                            @elseif($row->type == 'color')
                                <span class="badge badge-lg" style="background-color: {{ $dataTypeContent->{$row->field} }}">{{ $dataTypeContent->{$row->field} }}</span>
                            @elseif($row->type == 'coordinates')
                                @include('voyager::partials.coordinates')
                            @elseif($row->type == 'rich_text_box')
                                @include('voyager::multilingual.input-hidden-bread-read')
                                <p>{!! $dataTypeContent->{$row->field} !!}</p>
                            @elseif($row->type == 'file')
                                @if(json_decode($dataTypeContent->{$row->field}))
                                    @foreach(json_decode($dataTypeContent->{$row->field}) as $file)
                                        <a href="{{ Storage::disk(config('voyager.storage.disk'))->url($file->download_link) ?: '' }}">
                                            {{ $file->original_name ?: '' }}
                                        </a>
                                        <br/>
                                    @endforeach
                                @else
                                    <a href="{{ Storage::disk(config('voyager.storage.disk'))->url($row->field) ?: '' }}">
                                        {{ __('voyager::generic.download') }}
                                    </a>
                                @endif
                            @else
                                @include('voyager::multilingual.input-hidden-bread-read')
                                <p>{{ $dataTypeContent->{$row->field} }}</p>
                            @endif
                        </div><!-- panel-body -->
                        @if(!$loop->last)
                            <hr style="margin:0;">
                        @endif
                    @endforeach

                </div>
            </div>
        </div>
    </div>

    {{-- Single delete modal --}}
    <div class="modal modal-danger fade" tabindex="-1" id="delete_modal" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('voyager::generic.close') }}"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title"><i class="voyager-trash"></i> {{ __('voyager::generic.delete_question') }} {{ strtolower($dataType->display_name_singular) }}?</h4>
                </div>
                <div class="modal-footer">
                    <form action="{{ route('voyager.'.$dataType->slug.'.index') }}" id="delete_form" method="POST">
                        {{ method_field('DELETE') }}
                        {{ csrf_field() }}
                        <input type="submit" class="btn btn-danger pull-right delete-confirm"
                               value="{{ __('voyager::generic.delete_confirm') }} {{ strtolower($dataType->display_name_singular) }}">
                    </form>
                    <button type="button" class="btn btn-default pull-right" data-dismiss="modal">{{ __('voyager::generic.cancel') }}</button>
                </div>
            </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
    </div><!-- /.modal -->
@stop
EOF

cat << 'EOF' > resources/views/vendor/voyager/hostnames/browse.blade.php
@extends('voyager::master')

@section('content')
    <div class="page-content browse container-fluid">
        @include('voyager::alerts')
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-bordered">
                    <div class="panel-body">
                        @if ($isServerSide)
                            <form method="get" class="form-search">
                                <div id="search-input">
                                    <select id="search_key" name="key">
                                        @foreach($searchable as $key)
                                            <option value="{{ $key }}" @if($search->key == $key || $key == $defaultSearchKey){{ 'selected' }}@endif>{{ ucwords(str_replace('_', ' ', $key)) }}</option>
                                        @endforeach
                                    </select>
                                    <select id="filter" name="filter">
                                        <option value="contains" @if($search->filter == "contains"){{ 'selected' }}@endif>contains</option>
                                        <option value="equals" @if($search->filter == "equals"){{ 'selected' }}@endif>=</option>
                                    </select>
                                    <div class="input-group col-md-12">
                                        <input type="text" class="form-control" placeholder="{{ __('voyager::generic.search') }}" name="s" value="{{ $search->value }}">
                                        <span class="input-group-btn">
                                            <button class="btn btn-info btn-lg" type="submit">
                                                <i class="voyager-search"></i>
                                            </button>
                                        </span>
                                    </div>
                                </div>
                            </form>
                        @endif
                        <div class="table-responsive">
                            <table id="dataTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        @can('delete',app($dataType->model_name))
                                            <th>
                                                <input type="checkbox" class="select_all">
                                            </th>
                                        @endcan
                                        @foreach($dataType->browseRows as $row)
                                        <th>
                                            @if ($isServerSide)
                                                <a href="{{ $row->sortByUrl($orderBy, $sortOrder) }}">
                                            @endif
                                            {{ $row->display_name }}
                                            @if ($isServerSide)
                                                @if ($row->isCurrentSortField($orderBy))
                                                    @if ($sortOrder == 'asc')
                                                        <i class="voyager-angle-up pull-right"></i>
                                                    @else
                                                        <i class="voyager-angle-down pull-right"></i>
                                                    @endif
                                                @endif
                                                </a>
                                            @endif
                                        </th>
                                        @endforeach
                                        <th class="actions text-right">{{ __('voyager::generic.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($dataTypeContent as $data)
                                    <tr>
                                        @can('delete',app($dataType->model_name))
                                            <td>
                                                <input type="checkbox" name="row_id" id="checkbox_{{ $data->getKey() }}" value="{{ $data->getKey() }}">
                                            </td>
                                        @endcan
                                        @foreach($dataType->browseRows as $row)

                                            <td>
                                                @if($row->type == 'image')
                                                    <img src="@if( !filter_var($data->{$row->field}, FILTER_VALIDATE_URL)){{ Voyager::image( $data->{$row->field} ) }}@else{{ $data->{$row->field} }}@endif" style="width:100px">
                                                @elseif($row->type == 'relationship')
                                                    @include('voyager::formfields.relationship', ['view' => 'browse','options' => $row->details])
                                                @elseif($row->type == 'select_multiple')
                                                    @if(property_exists($row->details, 'relationship'))

                                                        @foreach($data->{$row->field} as $item)
                                                            {{ $item->{$row->field} }}
                                                        @endforeach

                                                    @elseif(property_exists($row->details, 'options'))
                                                        @if (count(json_decode($data->{$row->field})) > 0)
                                                            @foreach(json_decode($data->{$row->field}) as $item)
                                                                @if (@$row->details->options->{$item})
                                                                    {{ $row->details->options->{$item} . (!$loop->last ? ', ' : '') }}
                                                                @endif
                                                            @endforeach
                                                        @else
                                                            {{ __('voyager::generic.none') }}
                                                        @endif
                                                    @endif

                                                @elseif($row->type == 'select_dropdown' && property_exists($row->details, 'options'))

                                                    {!! isset($row->details->options->{$data->{$row->field}}) ?  $row->details->options->{$data->{$row->field}} : '' !!}

                                                @elseif($row->type == 'date' || $row->type == 'timestamp')
                                                    {{ property_exists($row->details, 'format') ? \Carbon\Carbon::parse($data->{$row->field})->formatLocalized($row->details->format) : $data->{$row->field} }}
                                                @elseif($row->type == 'checkbox')
                                                    @if(property_exists($row->details, 'on') && property_exists($row->details, 'off'))
                                                        @if($data->{$row->field})
                                                            <span class="label label-info">{{ $row->details->on }}</span>
                                                        @else
                                                            <span class="label label-primary">{{ $row->details->off }}</span>
                                                        @endif
                                                    @else
                                                    {{ $data->{$row->field} }}
                                                    @endif
                                                @elseif($row->type == 'color')
                                                    <span class="badge badge-lg" style="background-color: {{ $data->{$row->field} }}">{{ $data->{$row->field} }}</span>
                                                @elseif($row->type == 'text')
                                                    @include('voyager::multilingual.input-hidden-bread-browse')
                                                    @if($row->field == 'fqdn')
                                                        <?php 
                                                        $systemSite = \App\Tenant::getRootFqdn();
                                                        $data->fqdn = preg_replace('/(.*)(\.' . $systemSite . '$)/', '$1', $data->fqdn);
                                                        ?>
                                                    @endif
                                                    <div class="readmore">{{ mb_strlen( $data->{$row->field} ) > 200 ? mb_substr($data->{$row->field}, 0, 200) . ' ...' : $data->{$row->field} }}</div>
                                                @elseif($row->type == 'text_area')
                                                    @include('voyager::multilingual.input-hidden-bread-browse')
                                                    <div class="readmore">{{ mb_strlen( $data->{$row->field} ) > 200 ? mb_substr($data->{$row->field}, 0, 200) . ' ...' : $data->{$row->field} }}</div>
                                                @elseif($row->type == 'file' && !empty($data->{$row->field}) )
                                                    @include('voyager::multilingual.input-hidden-bread-browse')
                                                    @if(json_decode($data->{$row->field}))
                                                        @foreach(json_decode($data->{$row->field}) as $file)
                                                            <a href="{{ Storage::disk(config('voyager.storage.disk'))->url($file->download_link) ?: '' }}" target="_blank">
                                                                {{ $file->original_name ?: '' }}
                                                            </a>
                                                            <br/>
                                                        @endforeach
                                                    @else
                                                        <a href="{{ Storage::disk(config('voyager.storage.disk'))->url($data->{$row->field}) }}" target="_blank">
                                                            Download
                                                        </a>
                                                    @endif
                                                @elseif($row->type == 'rich_text_box')
                                                    @include('voyager::multilingual.input-hidden-bread-browse')
                                                    <div class="readmore">{{ mb_strlen( strip_tags($data->{$row->field}, '<b><i><u>') ) > 200 ? mb_substr(strip_tags($data->{$row->field}, '<b><i><u>'), 0, 200) . ' ...' : strip_tags($data->{$row->field}, '<b><i><u>') }}</div>
                                                @elseif($row->type == 'coordinates')
                                                    @include('voyager::partials.coordinates-static-image')
                                                @elseif($row->type == 'multiple_images')
                                                    @php $images = json_decode($data->{$row->field}); @endphp
                                                    @if($images)
                                                        @php $images = array_slice($images, 0, 3); @endphp
                                                        @foreach($images as $image)
                                                            <img src="@if( !filter_var($image, FILTER_VALIDATE_URL)){{ Voyager::image( $image ) }}@else{{ $image }}@endif" style="width:50px">
                                                        @endforeach
                                                    @endif
                                                @else
                                                    @include('voyager::multilingual.input-hidden-bread-browse')
                                                    <span>{{ $data->{$row->field} }}</span>
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="no-sort no-click" id="bread-actions">
                                            @foreach(Voyager::actions() as $action)
                                                @include('voyager::bread.partials.actions', ['action' => $action])
                                            @endforeach
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if ($isServerSide)
                            <div class="pull-left">
                                <div role="status" class="show-res" aria-live="polite">{{ trans_choice(
                                    'voyager::generic.showing_entries', $dataTypeContent->total(), [
                                        'from' => $dataTypeContent->firstItem(),
                                        'to' => $dataTypeContent->lastItem(),
                                        'all' => $dataTypeContent->total()
                                    ]) }}</div>
                            </div>
                            <div class="pull-right">
                                {{ $dataTypeContent->appends([
                                    's' => $search->value,
                                    'filter' => $search->filter,
                                    'key' => $search->key,
                                    'order_by' => $orderBy,
                                    'sort_order' => $sortOrder
                                ])->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Single delete modal --}}
    <div class="modal modal-danger fade" tabindex="-1" id="delete_modal" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('voyager::generic.close') }}"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title"><i class="voyager-trash"></i> {{ __('voyager::generic.delete_question') }} {{ strtolower($dataType->display_name_singular) }}?</h4>
                </div>
                <div class="modal-footer">
                    <form action="#" id="delete_form" method="POST">
                        {{ method_field('DELETE') }}
                        {{ csrf_field() }}
                        <input type="submit" class="btn btn-danger pull-right delete-confirm" value="{{ __('voyager::generic.delete_confirm') }}">
                    </form>
                    <button type="button" class="btn btn-default pull-right" data-dismiss="modal">{{ __('voyager::generic.cancel') }}</button>
                </div>
            </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
    </div><!-- /.modal -->
@stop
EOF

mkdir -p app/Actions;

cat << 'EOF' > app/Actions/TenantDeleteAction.php
<?php

namespace App\Actions;

use TCG\Voyager\Actions\DeleteAction;

class TenantDeleteAction extends DeleteAction
{
    public function getAttributes()
    {
        $fqdn = $this->data->fqdn; 
        $systemSite = \App\Tenant::getRootFqdn();

        if ( $systemSite === $fqdn ) {
            return [
                'class' => 'hide',
            ];
        }
        else {
            return parent::getAttributes();
        }
    }
}
EOF

cat << 'EOF' > app/Actions/TenantLoginAction.php
<?php

namespace App\Actions;

use TCG\Voyager\Actions\AbstractAction;

class TenantLoginAction extends AbstractAction
{
    public function getTitle()
    {
        return __('voyager::generic.login');
    }

    public function getIcon()
    {
        return 'voyager-ship';
    }

    public function getPolicy()
    {
        return 'read';
    }

    public function getDataType()
    {
        return 'hostnames';
    }

    public function getAttributes()
    {
        $fqdn = $this->data->fqdn; 
        $systemSite = \App\Tenant::getRootFqdn();

        if ( $systemSite === $fqdn ) {
            return [
                'class' => 'hide',
            ];
        }
        else {

            return [
                'class' => 'btn btn-sm btn-warning pull-left login',
                'target' => '_blank'
            ];
        }


    }

    public function getDefaultRoute()
    {
        $route = '//'. $this->data->fqdn . '.' . \App\Tenant::getRootFqdn()  . '/admin';

        return $route;
    }
}
EOF


cat << 'EOF' > app/Actions/TenantViewAction.php
<?php

namespace App\Actions;

use TCG\Voyager\Actions\ViewAction;

class TenantViewAction extends ViewAction
{
    public function getAttributes()
    {
        $fqdn = $this->data->fqdn; 
        $systemSite = \App\Tenant::getRootFqdn();

        if ( $systemSite === $fqdn ) {
            return [
                'class' => 'hide',
            ];
        }
        else {
            return array_merge( parent::getAttributes(), [ 'target' => '_blank'] );
        }


    }

    public function getDefaultRoute()
    {
        $route = '//'. $this->data->fqdn;

        return $route;
    }
}
EOF

Create $SYSTEM_FQDN in hostnames table

Create Bread for hostnames




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
