# Laravel Multitenant + Voyager installation guide

## Intro

What we will:

* **Optional**. Install docker environment with Postgres database.
* Install [laravel-tenancy.com](https://laravel-tenancy.com/) and [Voyager admin panel](https://laravelvoyager.com/) 
both per-tenant and as a system instance.

What we will get:

* System Voyager instane to create tenants.
* Per-tenant Voyager panel.

A short [youtube video](https://www.youtube.com/watch?v=otQfaxCdn7I&feature=youtu.be) of what result we expect.

## Prerequisites

### Software needed

#### Docker way

* docker
* docker-compose
* git

Make sure you have all other dockers down and standard ports free to avoid conflicts.

#### Dockerless way

* An http server (apache, nging etc.) running
* A database server (mysql, postgres) running

### Domain names

To work at localhost we need to have some test domains in your system.

Let's assume our system domain will be `voyager.test` and tenant domains will be some subdomains. You can use
non-subdomains domains for tenants as well.

So you need to add the domains to your hosts file.

> If you **want to use another system domain**, then replace `voyager.test` in this tutorial with your one. Especially take care of `database/seeds/HostnamesTableSeeder.php` as the system domain is imported into
the system database `hostnames` table from this file.
>
> If you get troubles with your system domain Voyager, please check the
> `hostnames` table. The system domain should be placed there withour any 
> reference to a web-site.
>
> Here is an SQL snippet to manual intervention
>
>```sql
> INSERT INTO "hostnames" ("id", "fqdn", "redirect_to", "force_https", "under_maintenance_since", "website_id", "created_at", "updated_at", "deleted_at") VALUES
> (9,	'voyager.test',	NULL,	'0',	NULL,	NULL,	'2019-02-19 10:15:31',	'2019-02-19 10:15:31',	NULL);
> ```

#### Manual hosts edit

Edit you hosts file to add test domains.

* Linux/MacOS: Edit /etc/hosts
* Windows: https://www.google.com/search?q=windows+hosts+file+path&ie=utf-8&oe=utf-8&client=firefox-b

Add lines like the following ones to have local development domains.

```bash
127.0.0.1 voyager.test
127.0.0.1 kyiv.voyager.test
127.0.0.1 dnipro.voyager.test
127.0.0.1 lviv.voyager.test
127.0.0.1 odesa.voyager.test
127.0.0.1 poltava.voyager.test
```

etc...

##### Linux script way bash

Open terminal window and run `sudo -i` to login as sudo user.

Next paste the script into your terminal.

```bash
FILE=/etc/hosts;
NEW_IP=127.0.0.1;
HOSTS=("voyager.test" "kyiv.voyager.test" "dnipro.voyager.test" "lviv.voyager.test" "odesa.voyager.test" "poltava.voyager.test" );

for HOST in ${HOSTS[*]}
do
    printf "   %s\n" $HOST
    sed -i.bak -e '$a\' -e "$NEW_IP\t$HOST" -e "/.*$HOST/d" $FILE
done
exit;

```

## Installation from scratch

### Dockerless setup

According tenancy [Elevated database user](https://laravel-tenancy.com/docs/hyn/5.3/installation#elevated-database-user) installation docs
setup your database and grant privelleges to your database user so it can create per-tenant tables and table owners.

The documentation uses `tenancy` database and user name. We use `default` instead. We use `secret` password.

So according to the tutorial:

For MariaDB or MySQL:

```sql
CREATE DATABASE IF NOT EXISTS default;
CREATE USER IF NOT EXISTS default@localhost IDENTIFIED BY 'secret';
GRANT ALL PRIVILEGES ON *.* TO default@localhost WITH GRANT OPTION;
```

For PostgreSQL:

```sql
CREATE DATABASE default;
CREATE USER default WITH CREATEDB CREATEROLE PASSWORD 'secret';
GRANT ALL PRIVILEGES ON DATABASE default to default WITH GRANT OPTION;
```

### Docker setup

> Skip, if you don't use docker.

Docker will setup a database for use, so we don't need to create it manually
or grant permissions to the database user. If you perfer to use another environment, you have to create database/user/permissions according to 
tenancy [Elevated database user](https://laravel-tenancy.com/docs/hyn/5.3/installation#elevated-database-user) installation docs

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

### Laravel Tenancy + Voyager installation and setup

> If you use docker, since this moment we work inside the docker container in a linux bash terminal.
>

If you use Linux/docker environment you can just copy/paste the script below in your terminal.

Otherwise read comments and preform steps manually to get better understanding what is going on.

**For non-linux users** 

The following code
```bash
cat << 'EOF' > path/to/a/file.php
... some contents ...
EOF
```

should be read as:

> Create(replace if exists) file `path/to/a/file.php` with content `... some contents ...`

```bash
# Set which database you use - Postgres of MySQL
DATABASE_TYPE=Postgres
USE_DOCKER=true

# 01 Create laravel project.
# We need an intermediate tmp folder as our current folder is not
# empty (contains laradoc folder) and laravel installation would fail otherwise
# If you don't use docker, just install a new laravel project and
# change directory to it
composer create-project --prefer-dist laravel/laravel tmp
# Enable hidden files move
shopt -s dotglob
# Move laravel project files from ./tmp to the project root
mv ./tmp/* .
# Remove the temporary folder.
rm -rf ./tmp

## Update default database connection

## Manual:
# Edit you .env file DB connection like this
# NOTE! DB_HOST may differs for different server configurations. Usual values are `localhost`, `127.0.0.1`

# Postgres
# DB_CONNECTION=system
# DB_HOST=postgres
# DB_PORT=5432
# DB_DATABASE=default
# DB_USERNAME=default
# DB_PASSWORD=secret

# Mysql
# DB_CONNECTION=system
# DB_HOST=mysql
# DB_PORT=3306
# DB_DATABASE=default
# DB_USERNAME=default
# DB_PASSWORD=secret

## Script way
if [ "$DATABASE_TYPE" == "Postgres" ]; then
    sed -i "s/DB_CONNECTION=mysql/DB_CONNECTION=system/g" .env
    sed -i "s/DB_HOST=127\.0\.0\.1/DB_HOST=postgres/g" .env
    sed -i "s/DB_PORT=3306/DB_PORT=5432/g" .env
    sed -i "s/DB_DATABASE=homestead/DB_DATABASE=default/g" .env
    sed -i "s/DB_USERNAME=homestead/DB_USERNAME=default/g" .env
else
    sed -i "s/DB_CONNECTION=mysql/DB_CONNECTION=system/g" .env
    sed -i "s/DB_HOST=127\.0\.0\.1/DB_HOST=mysql/g" .env
    echo '' >> .env
    echo '# Mysql additional setup' >> .env
    echo 'LIMIT_UUID_LENGTH_32=true' >> .env
    echo '' >> .env
fi

# 02 Laravel-tenancy installation

## Change connection name to from `pgsql` to `system` in `./config/database.php`
## If you use mysql, change connection name to from `mysql` to `system` instead

if [ "$DATABASE_TYPE" == "Postgres" ]; then
    sed -i "s/'pgsql' => \[/'system' => [/g" ./config/database.php
else
    sed -i "s/'mysql' => \[/'system' => [/g" ./config/database.php
    ## Override DefaultPasswordGenerator class of voyager. 
    ## MySQL was looking for a hard password which have special char also in it. 
    ## Voyager use MD5 which just have a-z and 0-9.
cat << 'EOF' > app/DatabasePasswordGenerator.php
<?php 

namespace App;

use Hyn\Tenancy\Generators\Database\DefaultPasswordGenerator;
use Hyn\Tenancy\Contracts\Website;
use Illuminate\Contracts\Foundation\Application;

class DatabasePasswordGenerator extends DefaultPasswordGenerator
{
  /**
   * @var Application
   */
  protected $app;
  
  public function __construct(Application $app)
  {
      $this->app = $app;
  }
  
  public function generate(Website $website) : string
  {
        return crypt(sprintf(
            '%s.%d',
            $this->app['config']->get('app.key'),
            $website->id
        ), '$1$rasmusle$');
  }
}

EOF
    sed -i "s/'password-generator' => Hyn\\\Tenancy\\\Generators\\\Database\\\DefaultPasswordGenerator::class,/'password-generator' => App\\\DatabasePasswordGenerator::class,/g" ./config/tenancy.php
fi

## Install package and configure the mulitenancy package
composer require "hyn/multi-tenant:5.3.*"
php artisan vendor:publish --tag=tenancy

## Allow auto deleting tenant folders on tenant delete in `config/tenancy.php`. Optional.
## You should read the file carefully and realize which options it has.
sed -i "s/'auto-delete-tenant-directory' => false/'auto-delete-tenant-directory' => true/g" ./config/tenancy.php

# Edit .env file and change APP_URL to our system domain URL (the main web-site URL). Optional.
# We will set the system domain respected by multitenance in our database, not in the .env file.
SYSTEM_FQDN="voyager.test"
APP_URL="http://"$SYSTEM_FQDN;
sed -i "s|APP_URL=http:\/\/localhost|APP_URL=${APP_URL}|g" .env

## Append to your laravel .env file with the following options. Optional.
echo '' >> .env
echo '# Laravel-tenancy config' >> .env
echo 'TENANCY_DATABASE_AUTO_DELETE=true' >> .env
echo 'TENANCY_DATABASE_AUTO_DELETE_USER=true' >> .env
echo 'TENANCY_ABORT_WITHOUT_HOSTNAME=true' >> .env
echo '' >> .env

## Copy user tables migrations to tenant folder to have per-tenant user tables
# Make `database/migrations/tenant` folder
mkdir database/migrations/tenant
# Copy `2014_10_12_000000_create_users_table.php` and `2014_10_12_100000_create_password_resets_table.php`
# to the newly created folder so we will create user tables per tenant.
cp database/migrations/2014_10_12_000000_create_users_table.php database/migrations/tenant/
cp database/migrations/2014_10_12_100000_create_password_resets_table.php database/migrations/tenant/

# Run database migrations for the system DB only.
# After that you'll find the tables in your `default` database:
# `hostnames`, `migrations`, `websites`
php artisan migrate --database=system

# 03 Add a helper class, which will do the tenant creation/deletions job

## Here is the logic of what to install per-tenant. 

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
        //\Artisan::call('passport:install');
        
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
# DB connection is switched to tenant

# Alas composer CLI way to update composer.json fails here (is not able to write as waay)
# `composer config extra.laravel.dont-discover tcg/voyager`
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

# 05 Voyager setup

# Add `TCG\Voyager\VoyagerServiceProvider::class` to config/app.php providers array. Remember, we have disabled autodiscover.
sed -i "s/\(App\\\Providers\\\RouteServiceProvider::class,\)/\1\n        TCG\\\Voyager\\\VoyagerServiceProvider::class,/g" config/app.php

# Register Voyager install command to app/Console/Kernel.php. It will be needed to create tenants via system Voyager.
sed -i "s/\(protected \$commands = \[\)/\1\n        \\\TCG\\\Voyager\\\Commands\\\InstallCommand::class,/g" app/Console/Kernel.php

# Update your AppServiceProvider.php to switch to tenant DB and filesystem when requesting a tenant URL
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

# Set all paths requesting uploaded files to use just created controller.
cat << 'EOF' >> routes/web.php
Route::get('/storage/{path}', '\App\Http\Controllers\HynOverrideMediaController')
    ->where('path', '.+')
    ->name('tenant.media');
EOF

# Create Hostname model for system Voyager
php artisan make:model Hostname

# Create a system domain seeder and run it
# Don't forget to replace 'voyager.test' with your system domain if needed.
cat << 'EOF' > database/seeds/HostnamesTableSeeder.php
<?php

use App\Hostname;
use Illuminate\Database\Seeder;

class HostnamesTableSeeder extends Seeder
{
    /**
     * Auto generated seed file.
     */
    /**
     * Auto generated seed file.
     *
     * @return void
     */
    public function run()
    {
        $hostname = Hostname::firstOrNew(['fqdn' => 'voyager.test']);

        if (!$hostname->exists) {
            $hostname->fill([
                    'fqdn' => 'voyager.test',
                ])->save();
        }
    }
}

EOF

composer dump-autoload
php artisan db:seed --class=HostnamesTableSeeder

# Install system voyager with dummy data. We need dummy data to have some fallback data for tenants,
# if they use dummy as well.
php artisan voyager:install --with-dummy

# Create a controller for the system Voyager to manage tenants
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

        $fqdn = $request->get('fqdn');
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

            parent::update($request, $id);

            return redirect()->to('//' . $request->fqdn  . '/admin/');
        } else {
            return parent::update($request, $id);
        }
    }


}

EOF

# Create Bread for hostnames in system Voyager
composer require --dev gruz/voyager-bread-generator

cat << 'EOF' > database/seeds/HostnamesBreadSeeder.php
<?php

use Illuminate\Database\Seeder;
use VoyagerBread\Traits\BreadSeeder;

class HostnamesBreadSeeder extends Seeder
{
    use BreadSeeder;

    public function bread()
    {
        return [
            // usually the name of the table
            'name'                  => 'hostnames',
            'slug'                  => 'hostnames',
            'display_name_singular' => 'Hostname',
            'display_name_plural'   => 'Hostnames',
            'icon'                  => 'voyager-ship',
            'model_name'            => 'App\Hostname',
            'controller'            => '\App\Http\Controllers\VoyagerTenantsController',
            'generate_permissions'  => 1,
            'description'           => '',
            'details'               => null
        ];
    }

    public function inputFields()
    {
        return [
            'id' => [
                'type'         => 'number',
                'display_name' => 'ID',
                'required'     => 1,
                'browse'       => 1,
                'read'         => 1,
                'edit'         => 0,
                'add'          => 0,
                'delete'       => 0,
                'details'      => new stdClass,
                'order'        => 1,
            ],
            'website_id' => [
                'type'         => 'text',
                'display_name' => 'Website Id',
                'required'     => 1,
                'browse'       => 1,
                'read'         => 1,
                'edit'         => 0,
                'add'          => 0,
                'delete'       => 0,
                'details'      => new stdClass,
                'order'        => 2,
            ],
            'fqdn' => [
                'type'         => 'text',
                'display_name' => 'Domain name',
                'required'     => 1,
                'browse'       => 1,
                'read'         => 1,
                'edit'         => 1,
                'add'          => 1,
                'delete'       => 1,
                'details'      => [
                    'description' => 'A Fully-qualified domain name. No protocol. Only domain name itself.',
                    'validation' => [
                      'rule' => 'unique:hostnames,fqdn',
                    ],
                ],
                'order'        => 3,
            ],
            'redirect_to' => [
                'type'         => 'text',
                'display_name' => 'Redirect To',
                'required'     => 0,
                'browse'       => 0,
                'read'         => 0,
                'edit'         => 0,
                'add'          => 0,
                'delete'       => 0,
                'details'      => new stdClass,
                'order'        => 4,
            ],
            'force_https' => [
                'type'         => 'text',
                'display_name' => 'Force Https',
                'required'     => 1,
                'browse'       => 0,
                'read'         => 0,
                'edit'         => 0,
                'add'          => 0,
                'delete'       => 0,
                'details'      => [  
                    'default' => '0',
                    'options' => [
                            0 => 'No',
                            1 => 'Yes',
                        ],
                ],
                'order'        => 5,
            ],
            'under_maintenance_since' => [
                'type'         => 'timestamp',
                'display_name' => 'Under Maintenance Since',
                'required'     => 0,
                'browse'       => 0,
                'read'         => 0,
                'edit'         => 0,
                'add'          => 0,
                'delete'       => 0,
                'details'      => new stdClass,
                'order'        => 6,
            ],
            'created_at' => [
                'type'         => 'timestamp',
                'display_name' => 'created_at',
                'required'     => 0,
                'browse'       => 1,
                'read'         => 1,
                'edit'         => 0,
                'add'          => 0,
                'delete'       => 0,
                'details'      => new stdClass,
                'order'        => 7,
            ],
            'updated_at' => [
                'type'         => 'timestamp',
                'display_name' => 'updated_at',
                'required'     => 0,
                'browse'       => 1,
                'read'         => 1,
                'edit'         => 0,
                'add'          => 0,
                'delete'       => 0,
                'details'      => new stdClass,
                'order'        => 8,
            ],
            'deleted_at' => [
                'type'         => 'timestamp',
                'display_name' => 'Deleted At',
                'required'     => 0,
                'browse'       => 0,
                'read'         => 0,
                'edit'         => 0,
                'add'          => 0,
                'delete'       => 0,
                'details'      => new stdClass,
                'order'        => 9,
            ],
        ];
    }

    public function menuEntry()
    {
        return [
            'role'        => 'admin',
            'title'       => 'Hostnames',
            'url'         => '',
            'route'       => 'voyager.hostnames.index',
            'target'      => '_self',
            'icon_class'  => 'voyager-ship',
            'color'       => null,
            'parent_id'   => null,
            'parameters' => null,
            'order'       => 1,

        ];
    }
}

EOF

composer dump-autoload
php artisan db:seed --class=HostnamesBreadSeeder
php artisan db:seed --class=PermissionRoleTableSeeder


# Alter action buttons at system hostnames Voyager view to have login button, alter view button and block system domain deletion
mkdir app/Actions/
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
        $route = '//'. $this->data->fqdn . '/admin';

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

# Override a Voyager template to show 'System domain' text for a system domain in system Voyager

mkdir -p resources/views/vendor/voyager/hostnames

cat << 'EOF' > resources/views/vendor/voyager/hostnames/browse.blade.php
@extends('voyager::master')

@section('page_title', __('voyager::generic.viewing').' '.$dataType->display_name_plural)

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="{{ $dataType->icon }}"></i> {{ $dataType->display_name_plural }}
        </h1>
        @can('add', app($dataType->model_name))
            <a href="{{ route('voyager.'.$dataType->slug.'.create') }}" class="btn btn-success btn-add-new">
                <i class="voyager-plus"></i> <span>{{ __('voyager::generic.add_new') }}</span>
            </a>
        @endcan
        @can('delete', app($dataType->model_name))
            @include('voyager::partials.bulk-delete')
        @endcan
        @can('edit', app($dataType->model_name))
            @if(isset($dataType->order_column) && isset($dataType->order_display_column))
                <a href="{{ route('voyager.'.$dataType->slug.'.order') }}" class="btn btn-primary">
                    <i class="voyager-list"></i> <span>{{ __('voyager::bread.order') }}</span>
                </a>
            @endif
        @endcan
        @include('voyager::multilingual.language-selector')
    </div>
@stop

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

                                            @if($row->field == 'website_id' && empty($data->website_id))
                                                <?php
                                                $data->website_id = 'System domain';
                                                ?>
                                            @endif
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

@section('css')
@if(!$dataType->server_side && config('dashboard.data_tables.responsive'))
    <link rel="stylesheet" href="{{ voyager_asset('lib/css/responsive.dataTables.min.css') }}">
@endif
@stop

@section('javascript')
    <!-- DataTables -->
    @if(!$dataType->server_side && config('dashboard.data_tables.responsive'))
        <script src="{{ voyager_asset('lib/js/dataTables.responsive.min.js') }}"></script>
    @endif
    <script>
        $(document).ready(function () {
            @if (!$dataType->server_side)
                var table = $('#dataTable').DataTable({!! json_encode(
                    array_merge([
                        "order" => $orderColumn,
                        "language" => __('voyager::datatable'),
                        "columnDefs" => [['targets' => -1, 'searchable' =>  false, 'orderable' => false]],
                    ],
                    config('voyager.dashboard.data_tables', []))
                , true) !!});
            @else
                $('#search-input select').select2({
                    minimumResultsForSearch: Infinity
                });
            @endif

            @if ($isModelTranslatable)
                $('.side-body').multilingual();
                //Reinitialise the multilingual features when they change tab
                $('#dataTable').on('draw.dt', function(){
                    $('.side-body').data('multilingual').init();
                })
            @endif
            $('.select_all').on('click', function(e) {
                $('input[name="row_id"]').prop('checked', $(this).prop('checked'));
            });
        });


        var deleteFormAction;
        $('td').on('click', '.delete', function (e) {
            $('#delete_form')[0].action = '{{ route('voyager.'.$dataType->slug.'.destroy', ['id' => '__id']) }}'.replace('__id', $(this).data('id'));
            $('#delete_modal').modal('show');
        });
    </script>
@stop

EOF

php artisan config:clear

```

## Installation from the repository

### Docker

```bash
git clone git@github.com:gruz/multi-tenancy-voyager-tries.git multi-tenancy-voyager;
cd multi-tenancy-voyager;
git submodule update --init --recursive;
cd laradock;
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

### Dockerless

```bash
git clone git@github.com:gruz/multi-tenancy-voyager-tries.git
```

It's assumed, that you setup your HTTP server to open project `public` folder for your domain. So when you try to visit your web-site, the server tries to open the `public` folder.

### Project setup

If using docker, you should be logged in inside the docker environment 
for now.

Otherwise go to the project root folder.

```bash
composer install;
php artisan vendor:publish --tag=tenancy

php artisan migrate --database=system

composer dump-autoload
php artisan db:seed --class=HostnamesTableSeeder
php artisan voyager:install --with-dummy
php artisan db:seed --class=HostnamesBreadSeeder
php artisan db:seed --class=PermissionRoleTableSeeder


php artisan config:clear

```

### Check results

Open [http://voyager.test/admin](http://voyager.test/admin) and login with `admin@admin.com`/`password`

Go to `Hostnames` sidebar menu and create a tenant like `dnipro.voyager.test` or `kyiv.voyager.test`.
Remember editing `hosts` file at the tutorial begining.

Open newly created [http://dnipro.voyager.test/admin](http://dnipro.voyager.test/admin)
in your browser and login using credentials `admin@admin.com`/`password`.

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
