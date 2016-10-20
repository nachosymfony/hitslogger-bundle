<?php

namespace nacholibre\HitsLoggerBundle\Services;

class HitLogger implements HitLoggerInterface {
    function __construct($container, $tokenStorage) {
        $redisServiceName = $container->getParameter('nacholibre.hits_logger.redis_service_name');

        $redisServiceName = str_replace('@', '', $redisServiceName);

        $this->tokenStorage = $tokenStorage;

        $redis = $container->get($redisServiceName);
        $this->redis = $redis;
        $this->historyCount = $container->getParameter('nacholibre.hits_logger.history_count');
        $this->daysOffset = 0;

        $indentificator = $container->getParameter('secret');
        $this->siteUniqueIdentificator = md5($indentificator . 'a13c123c');

        $this->botUserAgents = [
            "YandexBot",
            "Googlebot",
            "Google Wireless Transcoder",
            "Mediapartners-Google",
            "Google Web Preview",
            "bingbot",
            "msnbot",
            "Yahoo! Slurp",
            "BlackBerry",
            "Baiduspider",
        ];
    }

    public function setDaysOffset($offset) {
        $this->daysOffset = $offset;
    }

    public function getDaysOffset() {
        return $this->daysOffset;
    }

    private function getUniqueSiteIdentifier() {
        return $this->siteUniqueIdentificator;
    }

    private function getHistoryCount() {
        return $this->historyCount;
    }

    private function isBot($userAgent) {
        if (!$userAgent) {
            return false;
        }

        $userAgent = strtolower($userAgent);

        foreach($this->botUserAgents as $botUserAgent) {
            $botUserAgent = strtolower($botUserAgent);

            if (strpos($userAgent, $botUserAgent) !== false){
                return $botUserAgent;
            }
        }

        return false;
    }

    private function makeTime() {
        $time = mktime(0, 0, 0, date("m")  , date("d") - $this->getDaysOffset(), date("Y"));

        return $time;
    }

    public function getTime() {
        return $this->makeTime();
    }

    private function generateDayKey($time=false) {
        if (!$time) {
            $time = $this->makeTime();
        }

        $dk = $this->getUniqueSiteIdentifier() . ":" . date("Ymd", $time);

        return $dk;
    }

    public function getMonthUniqueVisits() {
        $mk = $this->generateMonthKey();

        return $this->redis->get($mk . ":unique");
    }

    public function getMonthAllVisits() {
        $mk = $this->generateMonthKey();

        return $this->redis->get($mk . ":all");
    }

    private function generateMonthKey() {
        $time = $this->makeTime();

        $mk = $this->getUniqueSiteIdentifier() . ":" . date("Ym",  $time);

        return $mk;
    }

    public function getDayUniqueVisits($time=false) {
        $dk = $this->generateDayKey($time);

        $visits = $this->redis->get($dk . ":unique");
        if (!$visits) {
            $visits = 0;
        }

        return $visits;
    }

    public function getDayAllVisits($time=false) {
        $dk = $this->generateDayKey($time);

        $visits = $this->redis->get($dk . ":all");
        if (!$visits) {
            $visits = 0;
        }

        return $visits;
    }

    private function generateBotDayHitsKey($userAgent, $time=false) {
        return $this->generateDayKey($time) . ":all:" . $userAgent;
    }

    private function generateBotMonthHitsKey($userAgent) {
        return $this->generateMonthKey() . ":all:" . $userAgent;
    }

    public function recordDayHitBot($userAgent) {
        $this->redis->incr($this->generateBotDayHitsKey($userAgent));
    }

    public function recordMonthHitBot($userAgent) {
        $this->redis->incr($this->generateBotMonthHitsKey($userAgent));
    }

    public function recordHitBot($userAgent, $ip) {
        $r = $this->redis;

        // increase IP score...
        $uaKey = $this->generateUserAgentHitKey($userAgent);
        $r->zincrby($uaKey, 1, $ip);
        $r->expire($uaKey, 3600 * 49);

        // update all clicks...
        $this->recordDayHitBot($userAgent);
        $this->recordMonthHitBot($userAgent);
    }

    public function getDayHitBot($userAgent, $time=false) {
        $userAgent = strtolower($userAgent);

        $hits = $this->redis->get($this->generateBotDayHitsKey($userAgent, $time));

        if (!$hits) {
            $hits = 0;
        }

        return $hits;
    }

    public function getMonthHitBot($userAgent) {
        $userAgent = strtolower($userAgent);

        $hits = $this->redis->get($this->generateBotMonthHitsKey($userAgent));

        if (!$hits) {
            $hits = 0;
        }

        return $hits;
    }

    public function getBotHits() {
        $res = [];
        foreach($this->getBotUserAgents() as $botUserAgent) {
            $res[$botUserAgent] = [
                'daily' => $this->getDayHitBot($botUserAgent),
                'monthly' => $this->getMonthHitBot($botUserAgent),
            ];
        }

        return $res;
    }

    public function getBotsHitsStatsByDay() {
        $now = time();

        $data = [];
        for ($i = 0; $i <= 20; $i++) {
            $time = time() + ($i*86400);

            $hits = [];
            foreach($this->getBotUserAgents() as $botUA) {
                $hits[$botUA] = $this->getDayHitBot($botUA, $time);
            }

            $data[$time] = $hits;
        }

        $data = array_reverse($data, true);

        return $data;
    }

    public function getBotHitsStats() {
        $res = [
            'daily' => 0,
            'monthly' => 0,
        ];

        foreach($this->getBotHits() as $userAgent => $hits) {
            $res['daily'] += $hits['daily'];
            $res['monthly'] += $hits['monthly'];
        }

        return $res;
    }

    public function generateIPKey($ip) {
        $dk = $this->generateDayKey();

        $ik = $dk . ":" . $ip;

        return $ik;
    }

    public function generateLoggedUserKey($userID) {
        $key = $this->getUniqueSiteIdentifier(). '_user_hits_' . $userID;
        $dk = $this->generateDayKey();

        return $dk . $key;
    }

    public function generateLoggedUserKeyAll() {
        $key = $this->getUniqueSiteIdentifier() . '_user_hitss';
        $dk = $this->generateDayKey();

        return $dk . $key;
    }

    public function getTopUsersByRequests() {
        $key = $this->generateLoggedUserKeyAll();

        $data = [];

        foreach($this->redis->zrevrange($key, 0, $this->getHistoryCount(), 'WITHSCORES') as $userID => $hitsCount) {
            $userHits = $this->getLatestUserHits($userID);

            $lastUserHit = null;

            if (count($userHits)) {
                $lastUserHit = $userHits[0];
            }

            $data[] = [
                'hitsCount' => $hitsCount,
                'lastUserHit' => $lastUserHit,
            ];
        }

        return $data;
    }

    public function getLatestUserHits($userID) {
        $key = $this->generateLoggedUserKey($userID);
        $hits = $this->redis->lrange($key, 0, $this->getHistoryCount());

        $hits = array_map(function($hit) {
            return json_decode($hit);
        }, $hits);

        return $hits;
    }

    public function logLoggedUserHit($user, $clientIP, $httpReferer, $userAgent, $fullURI) {
        $key = $this->generateLoggedUserKey($user->getID());

        $keyAll = $this->generateLoggedUserKeyAll();

        $package = [
            'user_id' => $user->getID(),
            'client_ip' => $clientIP,
            'referer' => $httpReferer,
            'user_agent' => $userAgent,
            'fullURI' => $fullURI,
            'date_made' => time(),
        ];

        $jsonData = json_encode($package);

        $this->redis->lpush($key, $jsonData);
        $this->redis->ltrim($key, 0, $this->getHistoryCount() - 1);
        $this->redis->expire($key, 86400*30);

        $this->redis->zincrby($keyAll, 1, $user->getID());
        $this->redis->expire($keyAll, 86400*5);
    }

    public function logHit($request) {
        $user = null;
        if ($this->tokenStorage->getToken()) {
            $user = $this->tokenStorage->getToken()->getUser();
        }

        if ($user === 'anon.') {
            $user = null;
        }

        $fullURI = $request->getUri();
        $clientIP = $request->getClientIP();
        $userAgent = $request->headers->get('User-Agent');
        $requestURI = $request->getRequestUri();

        if (strpos($requestURI, '/_fragment?') !== false) {
            //meaning render controller is called
            return;
        } else if (strpos($requestURI, 'callback=fos.Router.setData') !== false) {
            //meaning this is fos.Router bundle js ajax request
            return;
        }


        if ($request->server->get("HTTP_X_REAL_IP")) {
            $clientIP = $request->server->get("HTTP_X_REAL_IP");
        }

        $time_offset = 0;
        if ($request->server->get("time_offset")) {
            $time_offset = (int) $request->server->get("time_offset");
        }

        if ($request->server->get("HTTP_CF_CONNECTING_IP")) {
            $clientIP = $request->server->get("HTTP_CF_CONNECTING_IP");
        }

        $httpReferer = null;
        if ($request->server->get("HTTP_REFERER")) {
            $httpReferer = $request->server->get("HTTP_REFERER");
        }

        if ($user) {
            $this->logLoggedUserHit($user, $clientIP, $httpReferer, $userAgent, $fullURI);
        }

        //$clientIP = '127.0.0.2';

        //var_dump($clientIP);
        //var_dump($userAgent);
        //var_dump($fullURI);
        //var_dump($httpReferer);

        //
        $r = $this->redis;
        $ip = $clientIP;
        $site = $this->getUniqueSiteIdentifier();

        //$time_offset = (int) @$_REQUEST["time_offset"];

        $time = $this->makeTime();
        //$time = mktime(0, 0, 0, date("m")  , date("d") - $time_offset, date("Y"));

        $dk = $this->generateDayKey();
        $mk = $this->generateMonthKey();
        //$dk = $site . ":" . date("Ymd", $time);
        //$mk = $site . ":" . date("Ym",  $time);
        $ik = $this->generateIPKey($ip);
        $url = $fullURI;

        // //
        // update all clicks...
        $r->incr($dk . ":all");
        $r->incr($mk . ":all");

        // update unique clicks...
        if (! $r->exists($ik) ){
            $r->incr($dk . ":unique");
            $r->incr($mk . ":unique");

            // create an IP key...
            $r->hmset($ik, array(
                "count"		=> 0,
                "ua"		=> $userAgent,
                "referal"	=> $httpReferer,
                "first"		=> time(),
                "last"		=> time()
            ));

        //	$r->sadd("ua", md5(@$_SERVER["HTTP_USER_AGENT"]));
        }

        // check if this is search engine
        if ($botUserAgent = $this->isBot($userAgent)) {
            $this->recordHitBot($botUserAgent, $ip);
            //// increase IP score...
            //$r->zincrby($dk . ":z:" . $userAgent, 1, $ip);
            //$r->expire($dk . ":z:" . $userAgent, 3600 * 49);

            //// update all clicks...
            //$r->incr($mk . ":all:" . $userAgent);
            //$r->incr($dk . ":all:" . $userAgent);
        } else {
            // increase IP score...
            $this->recordNormalUserHit($ip);
            //$r->zincrby($dk . ":z:ip", 1, $ip);
            //$r->expire($dk . ":z:ip", 3600 * 49);
        }

        // update the IP key...

        $r->hincrby($ik, "count", 1);

        $r->hmset($ik, array(
                "referal"	=> $httpReferer	,
                "last"		=> time()
        ));

        $r->expire($ik, 3600 * 25);



        // store last 100 clicks

        $r->lpush( $ik . ":url", md5($ip . $url) );
        $r->ltrim( $ik . ":url", 0, $this->getHistoryCount() - 1);
        $r->expire($ik . ":url", 3600 * 49);



        // Store the clickstream...
        // probably good idea to shorten this.
        $click_key = $ik . ":" . "url" . ":" .  md5($ip . $url);

        $r->hmset($click_key, array(
                "url"		=> $url				,
                "referal"	=> $httpReferer	,
                "time"		=> time()
        ));

        $r->expire($click_key, 3600 * 49);
    }

    public function getBotUserAgents() {
        return $this->botUserAgents;
    }

    public function generateNormalUserHitKey() {
        $dk = $this->generateDayKey();

        return $dk . ":z:ip";
    }

    public function generateUserAgentHitKey($ua) {
        $ua = strtolower($ua);
        $dk = $this->generateDayKey();

        return $dk . ":z:" . $ua;
    }

    public function recordNormalUserHit($clientIP) {
        $dk = $this->generateDayKey();

        $key = $this->generateNormalUserHitKey($clientIP);
        $this->redis->zincrby($key, 1, $clientIP);
        $this->redis->expire($key, 3600 * 49);
    }

    public function generateIPDataKey($ip) {
        $dk = $this->generateDayKey();

        $ik = $dk . ":" . $ip;

        return $ik;
    }

    public function getIPData($ip) {
        return $this->redis->hgetall($this->generateIPDataKey($ip));
    }

    public function getNormalUserHitsData() {
        $key = $this->generateNormalUserHitKey();

        $data = [];

        foreach($this->redis->zrevrange($key, 0, $this->getHistoryCount()) as $ip) {
            $ipData = $this->getIPData($ip);
            $data[$ip] = $ipData;
        }

        return $data;
    }

    public function getBotHitsData() {
        $data = [];

        foreach($this->getBotUserAgents() as $botUserAgent) {
            $data[$botUserAgent] = $this->getUserAgentHitsData($botUserAgent);
        }

        return $data;
    }

    public function getUserAgentHitsData($userAgent) {
        $key = $this->generateUserAgentHitKey($userAgent);

        $data = [];

        foreach($this->redis->zrevrange($key, 0, $this->getHistoryCount()) as $ip) {
            $ipData = $this->getIPData($ip);
            $data[$ip] = $ipData;
        }

        return $data;
    }

    public function getIPLastClicks($ip) {
        $ipKey = $this->generateIPKey($ip);

        $data = [];
        foreach($this->redis->lrange($ipKey . ":url", 0, $this->getHistoryCount() * 10) as $md5url) {
            $click_key = $ipKey . ":" . "url" . ":" .  $md5url;

            $ipdata = $this->redis->hgetall($click_key);
            $data[] = $ipdata;
        }

        return $data;
    }

    public function getVisitsStatsByDay() {
        $now = time();

        $data = [];
        for ($i = 0; $i <= 20; $i++) {
            $time = time() + ($i*86400);
            $unique = $this->getDayUniqueVisits($time);
            $reloads = $this->getDayAllVisits($time);

            $data[$time] = [
                'unique' => $unique,
                'reloads' => $reloads,
            ];
        }

        $data = array_reverse($data, true);

        return $data;
    }

    //public function getClientTypeLatestData($clientIP, $userAgent) {
    //    $dk = $this->generateDayKey();

    //    foreach($redis->zrevrange($dk . ":z:" . $clientIP, 0, $this->getHistoryCount()) as $ip) {
    //    }
    //}
}
