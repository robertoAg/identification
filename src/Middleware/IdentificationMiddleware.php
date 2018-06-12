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

        // Get operator by GET param
        if(isset($_GET['operatorName'])) {
            Log::debug('(get) operatorName: ' . $_GET['operatorName']);
            if(null !== Session::get('operatorName') && Session::get('operatorName') !== strtoupper($_GET['operatorName'])){
                Session::flush();
            }
            $operatorName = strtoupper($_GET['operatorName']);
            session(['operatorName' => $operatorName]);
        }

        // Get domain and parse for use in constant
        $domain = $_SERVER['HTTP_HOST'];
        $domain = str_replace('.','_',$domain);

        // Get limiterType by domain in constants
        Session::put('limiter', self::getConstantByDomain($domain, 'limiter', 'none'));

        /*
         * If limiterType is none, identification will not exist
         */
        if(Session::get('limiter') == 'none'){
            Session::put('subscriptionActive', true);
            Log::debug('-IDENTIFICATION- limiterType: ' . Session::get('limiter') . ' for ' . $domain);
            return $next($request);
        }

        // Get defaultUrlSubscription by domain in constants
        $defaultUrlSubscription = self::getConstantByDomain($domain, 'defaultUrlSubscription', '/connect3G');

        // Set session userId
        // TODO Think on flow when there is userId on session. Actually not using.
        Session::put('userId', self::getUserId());

        if(empty(Session::get('userId'))){

            $ip = \Request::ip();
            $response = self::clientCurl( self::getUrlPortalInfo('ip', $ip, $domain) );
            if($response === false){
                Session::put('subscriptionActive', true);
            }else{
                if(!empty($response->result) && $response->result == "OK" && !empty($response->urlSubscription)){
                    self::fillSession($response);
                    $returnUrl = \Request::url();
                    if(empty($response->urlIdentification)) {
                        $redirect = $response->urlSubscription;
                    }else{
                        if(strpos($response->urlIdentification, '?') === false){
                            $redirect = $response->urlIdentification . "?returnUrl=" . $returnUrl;
                        }else{
                            $redirect = $response->urlIdentification . "&returnUrl=" . $returnUrl;
                        }
                    }
                    Log::debug('-IDENTIFICATION- redirigido a la url para identificarse: ' . $redirect);
                    return \Redirect::to($redirect);
                }else{
                    //Cant get urlSubscription
                    Log::debug('-IDENTIFICATION- portalInfo HAS ERROR');
                    Session::put('subscriptionUrl', $defaultUrlSubscription);
                    $userId = 'unknown';
                }
            }

        }else{
            $userId = Session::get('userId');
            Log::debug('-IDENTIFICATION- HAD (session) userId: ' . $userId);
            if(empty(Session::get('subscriptionUrl'))){

                //Limpia userId para evitar # en los numeros de telefono que son ALIAS
                $userIdFormat = str_replace("#", "%23", $userId);
                $response = self::clientCurl( self::getUrlPortalInfo('userId', $userIdFormat, $domain) );
                if($response === false){
                    Session::put('subscriptionActive', true);
                }else{
                    if (!empty($response->result) && $response->result == "OK" && !empty($response->urlSubscription)) {
                        self::fillSession($response);
                    }else{
                        //Cant get urlSubscription
                        Log::debug('-IDENTIFICATION- portalInfo HAS ERROR');
                        Session::put('subscriptionUrl', $defaultUrlSubscription);
                    }
                }
            }
        }

        /* KEY BACK DOOR */
        if ($userId == 'nicesports'){
            Session::put('subscriptionActive', true);
        }

        return $next($request);
    }

    public static function getUrlPortalInfo($type, $value, $domain){
        $db_portal = self::getConstantByDomain($domain, 'db_portal', 'realmadrid.mobi');
        $url = Config::get('constants.api.msol.endpoint') . Config::get('constants.api.msol.portalInfo') . '?' . $type . '=' . $value . '&portalName=' . $db_portal;
        if(!empty($operatorName)){
            $url .= '&operatorName=' . $operatorName;
        }
        return $url;
    }

    public static function fillSession($data){
        (!isset($data->gracePeriod))?: Session::put('gracePeriod', $data->gracePeriod);
        (!isset($data->urlCancellation))?: Session::put('urlCancellation', $data->urlCancellation);
        (!isset($data->tariff))?: Session::put('tariff', $data->tariff);
        (!isset($data->currencyCode))?: Session::put('currencyCode', $data->currencyCode);
        (!isset($data->frequency))?: Session::put('frequency', $data->frequency);
        (!isset($data->operatorNames))?: Session::put('operatorName', $data->operatorNames[0]);
        (!isset($data->urlSubscription))?: Session::put('subscriptionUrl', $data->urlSubscription);
    }

    public static function clientCurl($url){
        Log::debug('-IDENTIFICATION- portalInfo request: ' . $url);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        // Control the timeout response error (curl 28) and any other error
        if ($error_number = curl_errno($ch)) {
            Log::debug('-IDENTIFICATION- BackOffice Down: ' . $error_number);
            $response = false;
        }
        else
        {
            Log::debug('-IDENTIFICATION- portalInfo response: ' . $response);
            $response = json_decode($response);
            if( $response && $response->result ){
                Log::debug('-IDENTIFICATION- BackOffice Up');
            }else{
                Log::debug('-IDENTIFICATION- BackOffice ERROR on response and subscription active set true');
                $response = false;
            }
        }
        return $response;
    }

    public static function getConstantByDomain($domain, $key, $default){
        $value = Config::get('constants.domains.' . $domain . '.' . $key);
        if(empty($value)){
            $value = Config::get('constants.domains.default.' . $key);
            if(empty($value)){
                $value = $default;
            }
        }
        return $value;
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
