<?php

namespace nacholibre\HitsLoggerBundle\Services;

interface HitLoggerInterface {
    public function getLatestUserHits($userID);
    public function getVisitsStatsByDay();
    public function getBotsHitsStatsByDay();
}
