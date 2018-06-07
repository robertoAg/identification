<?php

namespace Msol\Identification\Middleware;

use Closure;

class IdentificationMiddleware
{
    /**
     * Exclude route from IdentificationMiddleware
     * @var array
     */
    protected $excludeRoutes = [
        'connect3G',
        'conexion/retornoIdentificacion',
        'startSubscription',
        'terms',
        'tc'
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        /**
         * Exclude route from IdentificationMiddleware
         */
        foreach($this->excludeRoutes as $route) {
            if ($request->is($route)) {
                return $next($request);
            }
        }

        dd('asdf');
        return $next($request);
    }
}
