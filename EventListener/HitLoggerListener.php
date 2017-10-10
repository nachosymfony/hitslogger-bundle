<?php

namespace nacholibre\HitsLoggerBundle\EventListener;

use Symfony\Component\HttpKernel\Event\GetResponseEvent;

class HitLoggerListener {
    function __construct($hitLogger) {
        $this->hitLogger = $hitLogger;

        $this->logTimeMS = 0;
    }

    public function onKernelRequest(GetResponseEvent $event) {
        $request = $event->getRequest();

        $start = microtime(true);

        $this->hitLogger->logHit($request);

        $this->logTimeMS =  microtime(true) - $start;
    }

    public function getLogTime() {
        return $this->logTimeMS;
    }
}
