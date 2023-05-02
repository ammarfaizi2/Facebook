<?php

require __DIR__."/../../vendor/autoload.php";

use Facebook\Facebook;

const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT;
$content_is_json = true;

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

	if (isset($_GET["limit"]) && is_numeric($_GET["limit"])) {
		$limit = (int)$_GET["limit"];
	} else {
		$limit = -1;
	}

	if (isset($_GET["take_content"])) {
		$take_content = (bool)$_GET["take_content"];
	} else {
		$take_content = false;
	}

	res(200, $fb->getTimelinePosts($_GET["username"], $year, $take_content, $limit));
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

function http_write_body_callback($curl, $data)
{
	echo $data;
	return strlen($data);
}

function http_write_header_callback($curl, $data)
{
	$len = strlen($data);
	if (preg_match("/^(content-|date|access-)/i", $data)) {
		header($data);
	}
	return $len;
}

function fb_http_get(Facebook $fb, string $url): int
{
	$p = parse_url($url);
	if ($p === false) {
		err(400, "Bad request: invalid URL");
		return 1;
	}

	if ($p["scheme"] !== "https") {
		err(400, "Bad request: invalid URL scheme (only HTTPS is allowed)");
		return 1;
	}

	if (!preg_match("/facebook|fbcdn/", $p["host"])) {
		err(400, "Bad request: invalid URL host");
		return 1;
	}

	if (isset($_GET["follow_redirect"])) {
		$follow_redirect = (bool)$_GET["follow_redirect"];
	} else {
		$follow_redirect = true;
	}
	ob_get_clean();

	$content_is_json = false;
	$fb->http($url, "GET", [
		"curl_options" => [
			CURLOPT_RETURNTRANSFER => false,
			CURLOPT_WRITEFUNCTION => "http_write_body_callback",
			CURLOPT_HEADERFUNCTION => "http_write_header_callback",
		],
		"follow_redirect" => $follow_redirect,
	]);
	return 0;
}

function httpGet(Facebook $fb)
{
	global $content_is_json;

	if (!isset($_GET["url"]) || !is_string($_GET["url"])) {
		err(400, "Bad request: missing \"url\" string parameter");
		return 0;
	}

	if (!fb_http_get($fb, $data))
		exit(0);
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
	case "httpGet":
		return httpGet($fb);
	default:
		err(400, "Bad request: unknown action");
		return 0;
	}
}

function rewriteOnionURL(?string $str): ?string
{
	if (is_null($str))
		return NULL;

	$signature = md5($str.API_SECRET, true);

	/**
	 * Try to compress the URL. If the result is bigger, don't compress it.
	 */
	$str_comp = gzdeflate($str, 9);
	if (strlen($str_comp) < $str) {
		$str = $str_comp;
		$is_compressed = "\1";
	} else {
		$is_compressed = "\0";
	}

	if (isset($_SERVER["HTTPS"])) {
		$url = "https://";
	} else {
		$url = "http://";
	}

	if (isset($_SERVER["HTTP_HOST"])) {
		$url .= $_SERVER["HTTP_HOST"];
	} else {
		$url .= "localhost";
	}

	$url .= "/api.php?u=";
	return $url.urlencode(base64_encode($is_compressed.$signature.$str));
}

function handle_url_proxy(Facebook $fb, string $url)
{
	$url = @base64_decode($url);
	if (!$url || strlen($url) < 17) {
		err(404, "Not found");
		return 0;
	}

	$is_compressed = ($url[0] === "\1");
	$signature = substr($url, 1, 16);
	$data = substr($url, 17);

	if ($is_compressed) {
		$data = gzinflate($data);
	}

	if ($signature !== md5($data.API_SECRET, true)) {
		err(400, "Invalid signature");
		return 0;
	}

	if (!fb_http_get($fb, $data))
		exit(0);
}

function main()
{
	require __DIR__."/../auth.php";
	if (!isset($_GET["u"]) || !is_string($_GET["u"])) {
		if (!isset($_GET["key"]) || $_GET["key"] !== $auth_key) {
			err(401, "Unauthorized");
			return 0;
		}

		if (!isset($_GET["action"]) || !is_string($_GET["action"])) {
			err(400, "Bad request: missing \"action\" string parameter");
			return 0;
		}

		$handle_url_proxy = false;
	} else {
		$handle_url_proxy = true;
	}

	$fb = new Facebook($session_dir);
	$fb->registerRewriteURLCallback("rewriteOnionURL");
	$fb->setProxy($tor_proxy);
	$fb->setBaseUrl("https://mbasic.facebookwkhpilnemxj7asaniu7vnjjbiltxjqhye3mhbshg7kx5tfyd.onion");
	$fb->setCookieString($cookie);

	try {
		if ($handle_url_proxy) {
			$ret = handle_url_proxy($fb, $_GET["u"]);
		} else {
			$ret = handle_action($fb, $_GET["action"]);
		}
	} catch (Exception $e) {
		err(422, "Error: ".$e->getMessage());
		return 0;
	}
}

ob_start();
main();
if ($content_is_json) {
	header("Content-Type: application/json");
}
echo ob_get_clean();
