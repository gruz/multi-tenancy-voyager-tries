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

MULTITENANCY_VERSION="5.*"
LARAVEL_VERSION="5.*"

# 01 Create laravel project.
# We need an intermediate tmp folder as our current folder is not
# empty (contains laradoc folder) and laravel installation would fail otherwise
# If you don't use docker, just install a new laravel project and
# change directory to it
composer create-project --prefer-dist laravel/laravel tmp $LARAVEL_VERSION
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
# DB_CONNECTION=systemm
# DB_HOST=mysql
# DB_PORT=3306
# DB_DATABASE=default
# DB_USERNAME=default
# DB_PASSWORD=secret
# LIMIT_UUID_LENGTH_32=true

## Script way
if [ "$DATABASE_TYPE" == "Postgres" ]; then
    sed -i "s/DB_CONNECTION=mysql/DB_CONNECTION=system/g" .env
    sed -i "s/DB_HOST=127\.0\.0\.1/DB_HOST=postgres/g" .env
    sed -i "s/DB_PORT=3306/DB_PORT=5432/g" .env
    sed -i "s/DB_DATABASE=.*/DB_DATABASE=default/g" .env
    sed -i "s/DB_USERNAME=.*/DB_USERNAME=default/g" .env
    sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=secret/g" .env
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
@@file : app/DatabasePasswordGenerator.php
    sed -i "s/'password-generator' => Hyn\\\Tenancy\\\Generators\\\Database\\\DefaultPasswordGenerator::class,/'password-generator' => App\\\DatabasePasswordGenerator::class,/g" ./config/tenancy.php
fi

## Install package and configure the mulitenancy package
composer require "hyn/multi-tenant:"$MULTITENANCY_VERSION
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

@@file : app/Tenant.php

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
@@file : app/Providers/AppServiceProvider.php

# Override Hyn Laravel tenanty Mediacontroller to make it work with Voyager.
# Hyn forces to use `media` folder to store files while Voyager reads root
# of the storage folder.
# So we create our own controller.
@@file : app/Http/Controllers/HynOverrideMediaController.php

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
@@file : database/seeds/HostnamesTableSeeder.php

composer dump-autoload
php artisan db:seed --class=HostnamesTableSeeder

# Install system voyager with dummy data. We need dummy data to have some fallback data for tenants,
# if they use dummy as well.
php artisan voyager:install --with-dummy

# Create a controller for the system Voyager to manage tenants
@@file : app/Http/Controllers/VoyagerTenantsController.php

# Create Bread for hostnames in system Voyager
composer require --dev gruz/voyager-bread-generator

@@file : database/seeds/HostnamesBreadSeeder.php

composer dump-autoload
php artisan db:seed --class=HostnamesBreadSeeder
php artisan db:seed --class=PermissionRoleTableSeeder


# Alter action buttons at system hostnames Voyager view to have login button, alter view button and block system domain deletion
mkdir app/Actions/
@@file : app/Actions/TenantDeleteAction.php

@@file : app/Actions/TenantLoginAction.php

@@file : app/Actions/TenantViewAction.php

# Override a Voyager template to show 'System domain' text for a system domain in system Voyager

mkdir -p resources/views/vendor/voyager/hostnames

@@file : resources/views/vendor/voyager/hostnames/browse.blade.php

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
