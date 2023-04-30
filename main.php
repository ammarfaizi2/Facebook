<?php

require __DIR__."/vendor/autoload.php";
require __DIR__."/auth.php";

use Facebook\Facebook;

$fb = new Facebook($session_dir);
$fb->setProxy($tor_proxy);
$fb->setBaseUrl("https://mbasic.facebookwkhpilnemxj7asaniu7vnjjbiltxjqhye3mhbshg7kx5tfyd.onion");
$fb->setCookieString($cookie);

$years = $fb->getTimelineYears("HonkaiStarRail.ID");
var_dump($years);

$posts = $fb->getTimelineYears("ThePandaSpot");
var_dump($posts);
