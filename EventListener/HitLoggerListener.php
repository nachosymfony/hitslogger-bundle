<?php

namespace nacholibre\HitsLoggerBundle\EventListener;

use Symfony\Component\HttpKernel\Event\GetResponseEvent;

class HitLoggerListener {
    function __construct($hitLogger) {
        $this->hitLogger = $hitLogger;
    }

    public function onKernelRequest(GetResponseEvent $event) {
        $request = $event->getRequest();

        $this->hitLogger->logHit($request);
    }
}
