<?php

require __DIR__."/vendor/autoload.php";
require __DIR__."/auth.php";

use Facebook\Facebook;

$fb = new Facebook($session_dir);
$fb->setProxy($tor_proxy);
$fb->setBaseUrl("https://mbasic.facebookwkhpilnemxj7asaniu7vnjjbiltxjqhye3mhbshg7kx5tfyd.onion");
$fb->setCookieString($cookie);

// $years = $fb->getTimelineYears("HonkaiStarRail.ID");
// var_dump($years);

// $years = $fb->getTimelineYears("ThePandaSpot");
// var_dump($years);

// $years = $fb->getTimelineYears("ammarfaizi2");
// var_dump($years);

// $years = $fb->getTimelineYears("1111111111111111111111111111");
// var_dump($years);

// $posts = $fb->getTimelinePosts("HonkaiStarRail.ID");
// var_dump($posts);

// $posts = $fb->getTimelinePosts("ThePandaSpot");
// var_dump($posts);

// $posts = $fb->getTimelinePosts("ammarfaizi2");
// var_dump($posts);

$post = $fb->getPost("pfbid02hA3iHVhnqKrWksEjTEfxboBgB7jxpcErKcbBrsdL3MkLBz2cDphh2VWTXZ29fiMnl");
var_dump($post);

$post = $fb->getPost("pfbid0jeyGAtmz47eqviE4DkvqVoSaq2CKfpTaxo6tp2m7cs9Hz3oJtPDiPy7eyHC8hoK2l");
var_dump($post);

// // photo
// $post = $fb->getPost("283120917378331");
// var_dump($post);
