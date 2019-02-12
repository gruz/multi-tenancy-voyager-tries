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

