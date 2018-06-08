<?php

namespace Msol\Identification\Middleware;

use Closure;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Input;
use Msol\Identification\Helpers\ApiCrypter;

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

        // Get domain and parse for use in constant
        $domain = $_SERVER['HTTP_HOST'];
        $domain = str_replace('.','_',$domain);

        // Get limiterType by domain in constants
        Session::put('limiter', self::getLimiterType($domain));
        /*
         * If limiterType is none, identification will not exist
         */
        if(Session::get('limiter') == 'none'){
            Session::put('subscriptionActive', true);
            Log::debug('-IDENTIFICATION- limiterType: ' . Session::get('limiter') . ' for ' . $domain);
            return $next($request);
        }

        // Get defaultUrlSubscription by domain in constants
        $defaultUrlSubscription = self::getDefaultUrlSubscription($domain);

        // Set session userId
        // TODO Think on flow when there is userId on session. Actually not using.
        Session::put('userId', self::getUserId());

        if(empty(Session::get('userId'))){
            dd('NO hay userId');
        }else{
            dd('si userId');
        }
        return $next($request);
    }

    public static function getLimiterType($domain){
        $limiterType = Config::get('constants.domains.' . $domain . '.limiter');
        if(empty($limiterType)){
            $limiterType = Config::get('constants.domains.default.limiter');
            if(empty($limiterType)){
                $limiterType = 'none';
            }
        }
        return $limiterType;
    }

    public static function getDefaultUrlSubscription($domain){
        $defaultUrlSubscription = Config::get('constants.domains.' . $domain . '.defaultUrlSubscription');
        if(empty($defaultUrlSubscription)){
            $defaultUrlSubscription = Config::get('constants.domains.default.defaultUrlSubscription');
            if(empty($defaultUrlSubscription)) {
                $defaultUrlSubscription = url('/connect3G');
            }
        }
        return $defaultUrlSubscription;
    }

    public static function getUserId(){

        if(!empty($_COOKIE['u'])){
            $userIdCrypted = $_COOKIE['u'];
        }
        if(isset($_GET['u'])){
            $userIdCrypted = $_GET['u'];
        }
        if(isset($userIdCrypted)){
            Log::debug('userIdCrypted: ' . $userIdCrypted);
            $apiCrypter = new ApiCrypter();
            $userId = $apiCrypter->decrypt($userIdCrypted);
            Log::debug('userId (get || cookie) u: ' . $userId);
            if( empty($userId) && !empty($_COOKIE['u'])){
                $userId = $apiCrypter->decrypt($_COOKIE['u']);
                Log::debug('userId (cookie) u: ' . $userId);
            };
        }
        if(isset($_GET['userId'])){
            Log::debug('(get) userId: ' . $_GET['userId']);
            if(empty($_GET['userId'])){
                $userId = 'unknown';
            }else{
                $userId = Input::get('userId');
            }
        }else{
            if(empty($userId)){
                $userId = false;
            }
            Log::debug('No hay (get) userId');
        }

        return $userId;

    }
}
