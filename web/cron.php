<?php

require __DIR__."/../vendor/autoload.php";
require __DIR__."/auth.php";

use Facebook\Facebook;

$fb = new Facebook($session_dir);
$fb->clearExpiredCaches();
