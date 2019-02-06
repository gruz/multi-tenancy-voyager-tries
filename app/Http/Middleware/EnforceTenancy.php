<?php

namespace App\Http\Middleware;

use Closure;

class EnforceTenancy
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        \Illuminate\Support\Facades\Config::set('database.default', 'tenant');

        return $next($request);
    }
}
