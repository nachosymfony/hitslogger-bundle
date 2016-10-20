<?php

namespace nacholibre\HitsLoggerBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

class DefaultController extends Controller {
    /**
     * @Route("/charts", name="nacholibre.hits_logger.charts.show")
     */
    public function chartsAction() {
        $hitsLogger = $this->get('nacholibre_services.hit_logger');

        return $this->render('nacholibreHitsLoggerBundle:Default:charts.html.twig', [
            'hitsLogger' => $hitsLogger,
        ]);
    }

    /**
     * @Route("/{offsetDays}", name="nacholibre.hits_logger.show", defaults={"offsetDays": 0})
     */
    public function indexAction($offsetDays) {
        $hitsLogger = $this->get('nacholibre_services.hit_logger');

        $hitsLogger->setDaysOffset($offsetDays);

        return $this->render('nacholibreHitsLoggerBundle:Default:index.html.twig', [
            'hitsLogger' => $hitsLogger,
            'offsetDays' => $offsetDays,
        ]);
    }

    /**
     * @Route("/ip/{ip}", name="nacholibre.hits_logger.ip.show")
     */
    public function ipAction($ip) {
        $hitsLogger = $this->get('nacholibre_services.hit_logger');

        return $this->render('nacholibreHitsLoggerBundle:Default:ip.html.twig', [
            'hitsLogger' => $hitsLogger,
            'ip' => $ip,
            'ipData' => [$ip => $hitsLogger->getIPData($ip)]
        ]);
    }

    /**
     * @Route("/user/{id}", name="nacholibre.hits_logger.user.show")
     */
    public function userAction($id) {
        $hitsLogger = $this->get('nacholibre_services.hit_logger');

        return $this->render('nacholibreHitsLoggerBundle:Default:user.html.twig', [
            'latestUserHits' => $hitsLogger->getLatestUserHits($id),
            'userID' => $id,
        ]);
    }
}
