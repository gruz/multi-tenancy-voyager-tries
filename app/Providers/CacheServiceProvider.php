<?php

namespace App\Providers;

use Illuminate\Cache\FileStore;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Support\ServiceProvider;


class CacheServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $namespace = function($app) {

            if (PHP_SAPI === 'cli') {
                return $app['config']['cache.default'];
            }

            $fqdn = request()->getHost();

            $uuid = \DB::table('hostnames')
                ->select('websites.uuid')
                ->join('websites', 'hostnames.website_id', '=', 'websites.id')
                ->where('fqdn', $fqdn)
                ->value('uuid');

            return $uuid;
        };

        $cacheDriver = config('cache.default');
        switch ($cacheDriver) {
            case 'file':
                \Cache::extend($cacheDriver, function ($app) use ($namespace){
                    $namespace = $namespace($app);

                    return \Cache::repository(new FileStore(
                        $app['files'],
                        $app['config']['cache.stores.file.path'].$namespace
                    ));
                });
                break;
            case 'database':
                \Cache::extend($cacheDriver, function ($app) use ($namespace){
                    $namespace = $namespace($app);

                    return \Cache::repository(new DatabaseStore(
                        $app['db.connection'],
                        'cache',
                        $namespace
                    ));
                });
                break;
            case 'redis':
                // But if not yet instantiated, then we are able to redifine namespace (prefix). Works for Redis only
                if (PHP_SAPI === 'cli') {
                    $namespace = str_slug(env('APP_NAME', 'laravel'), '_').'_cache';
                } else {
                    $fqdn = request()->getHost();
                    $namespace = \DB::table('hostnames')
                        ->select('websites.uuid')
                        ->join('websites', 'hostnames.website_id', '=', 'websites.id')
                        ->where('fqdn', $fqdn)
                        ->value('uuid');
                }
                \Cache::setPrefix($namespace);
                break;
            default:
        }
    }
}
