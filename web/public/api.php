<?php

require __DIR__."/../../vendor/autoload.php";

use Facebook\Facebook;

const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT;

function err(int $code, $msg)
{
	http_response_code($code);
	echo json_encode(["code" => $code, "error" => $msg], JSON_FLAGS);
}

function res(int $code, $msg)
{
	http_response_code($code);
	echo json_encode(["code" => $code, "res" => $msg], JSON_FLAGS);
}

function getTimelineYears(Facebook $fb)
{
	if (!isset($_GET["username"]) || !is_string($_GET["username"])) {
		err(400, "Bad request: missing \"username\" string parameter");
		return 0;
	}

	res(200, $fb->getTimelineYears($_GET["username"]));
	return 0;
}

function getTimelinePosts(Facebook $fb)
{
	if (!isset($_GET["username"]) || !is_string($_GET["username"])) {
		err(400, "Bad request: missing \"username\" string parameter");
		return 0;
	}

	if (isset($_GET["year"]) && is_numeric($_GET["year"])) {
		$year = (int)$_GET["year"];
	} else {
		$year = -1;
	}

	res(200, $fb->getTimelinePosts($_GET["username"], $year));
	return 0;
}

function getPost(Facebook $fb)
{
	if (!isset($_GET["id"]) || !is_string($_GET["id"])) {
		err(400, "Bad request: missing \"username\" string parameter");
		return 0;
	}

	res(200, $fb->getPost($_GET["id"]));
	return 0;
}

function handle_action(Facebook $fb, string $action)
{
	switch ($action) {
	case "getTimelineYears":
		return getTimelineYears($fb);
	case "getTimelinePosts":
		return getTimelinePosts($fb);
	case "getPost":
		return getPost($fb);
	default:
		err(400, "Bad request: unknown action");
		return 0;
	}
}

function main()
{
	header("Content-Type: application/json");

	require __DIR__."/../auth.php";
	if (!isset($_GET["key"]) || $_GET["key"] !== $auth_key) {
		err(401, "Unauthorized");
		return 0;
	}

	if (!isset($_GET["action"]) || !is_string($_GET["action"])) {
		err(400, "Bad request: missing \"action\" string parameter");
		return 0;
	}

	$fb = new Facebook($session_dir);
	$fb->setProxy($tor_proxy);
	$fb->setBaseUrl("https://mbasic.facebookwkhpilnemxj7asaniu7vnjjbiltxjqhye3mhbshg7kx5tfyd.onion");
	$fb->setCookieString($cookie);

	try {
		$ret = handle_action($fb, $_GET["action"]);
	} catch (Exception $e) {
		err(422, "Error: ".$e->getMessage());
		return 0;
	}
}

main();
