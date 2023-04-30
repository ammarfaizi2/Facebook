<?php
// SPDX-License-Identifier: GPL-2.0-only
/*
 * Copyright (C) 2023  Ammar Faizi <ammarfaizi2@gnuweeb.org>
 */

namespace Facebook;

require __DIR__."/helpers.php";

class Facebook
{
	use Methods\Post;

	/**
	 * @var string
	 */
	private string $user_agent = "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Safari/537.36";

	/**
	 * @var string
	 */
	private string $base_url = "https://mbasic.facebook.com";

	/**
	 * @var string|null
	 */
	private ?string $proxy = NULL;

	/**
	 * @var string
	 */
	private string $session_dir;

	/**
	 * @var string
	 */
	private string $cache_dir;

	/**
	 * @var string
	 */
	private string $cookie_file;

	/**
	 * @var string
	 */
	private ?string $cookie_string = NULL;

	/**
	 * @var CurlHandle
	 */
	private $ch;

	/**
	 * @param string $session_dir
	 */
	public function __construct(string $session_dir)
	{
		$this->session_dir = $session_dir;
		$this->buildSession();
		$this->ch = curl_init();
	}

	/**
	 * Destructor.
	 */
	public function __destruct()
	{
		curl_close($this->ch);
	}

	/**
	 * @return void
	 */
	private function buildSession(): void
	{
		if (!is_dir($this->session_dir)) {
			if (!mkdir($this->session_dir, 0755, true)) {
				throw new \Exception("Cannot create session directory: {$this->session_dir}");
			}
		}

		$this->cookie_file = $this->session_dir."/cookie.txt";
		if (!file_exists($this->cookie_file)) {
			file_put_contents($this->cookie_file, "");
		}

		if (!is_writable($this->cookie_file)) {
			throw new \Exception("Cookie file is not writable: {$this->cookie_file}");
		}

		if (!is_readable($this->cookie_file)) {
			throw new \Exception("Cookie file is not readable: {$this->cookie_file}");
		}

		$this->cache_dir = $this->session_dir."/cache";
		if (!is_dir($this->cache_dir)) {
			if (!mkdir($this->cache_dir, 0755, true)) {
				throw new \Exception("Cannot create cache directory: {$this->cache_dir}");
			}
		}

		if (!is_writable($this->cache_dir)) {
			throw new \Exception("Cache directory is not writable: {$this->cache_dir}");
		}

		if (!is_readable($this->cache_dir)) {
			throw new \Exception("Cache directory is not readable: {$this->cache_dir}");
		}
	}

	/**
	 * @param string $username
	 * @return string
	 */
	public function getUserCacheDir(string $username): string
	{
		$ret = $this->cache_dir."/".$username;
		if (!is_dir($ret)) {
			if (!mkdir($ret, 0755, true)) {
				throw new \Exception("Cannot create user cache directory: {$ret}");
			}
		}

		if (!is_writable($ret)) {
			throw new \Exception("User cache directory is not writable: {$ret}");
		}

		if (!is_readable($ret)) {
			throw new \Exception("User cache directory is not readable: {$ret}");
		}

		return $ret;
	}

	/**
	 * @param string $user_agent
	 * @return void
	 */
	public function setUserAgent(string $user_agent): void
	{
		$this->user_agent = $user_agent;
	}

	/**
	 * @param string $base_url
	 * @return void
	 */
	public function setBaseUrl(string $base_url): void
	{
		$this->base_url = $base_url;
	}

	/**
	 * @param string $cookie_string
	 * @return void
	 */
	public function setCookieString(string $cookie_string): void
	{
		$this->cookie_string = $cookie_string;
	}

	/**
	 * @param string|null $proxy
	 * @return void
	 */
	public function setProxy(?string $proxy): void
	{
		$this->proxy = $proxy;
	}

	/**
	 * @param string $url
	 * @param string $method
	 * @param array  $options
	 * @return array
	 */
	public function http(string $url, string $method = "GET", array $options = [])
	{
		if (filter_var($url, FILTER_VALIDATE_URL) === false) {
			$url = $this->base_url."/".ltrim($url, "/");
		}

		if (isset($options["follow_redirect"])) {
			$follow_redirect = (bool)$options["follow_redirect"];
		} else {
			$follow_redirect = true;
		}

		$curl_options = [
			CURLOPT_URL		=> $url,
			CURLOPT_RETURNTRANSFER	=> true,
			CURLOPT_COOKIEFILE	=> $this->cookie_file,
			CURLOPT_COOKIEJAR	=> $this->cookie_file,
			CURLOPT_FOLLOWLOCATION	=> $follow_redirect,
			CURLOPT_CUSTOMREQUEST	=> strtoupper($method),
			CURLOPT_HTTPHEADER	=> [
				"Accept: */*",
				"Accept-Language: en-US,en;q=0.5",
				"Connection: keep-alive",
			],
			CURLOPT_ENCODING	=> "gzip, deflate, br",
		];

		if ($this->proxy) {
			$curl_options[CURLOPT_PROXY] = $this->proxy;
			$curl_options[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5_HOSTNAME;
		}

		if ($this->cookie_string) {
			$curl_options[CURLOPT_COOKIE] = $this->cookie_string;
		}

		if (isset($options["headers"])) {
			$curl_options[CURLOPT_HTTPHEADER] = array_merge($curl_options[CURLOPT_HTTPHEADER], $options["headers"]);
		}

		if (isset($options["data"])) {
			$curl_options[CURLOPT_POST] = true;
			$curl_options[CURLOPT_POSTFIELDS] = $options["data"];
		}

		$o = $this->curl($url, $curl_options);
		if ($o["ern"]) {
			throw new \Exception("cURL error: {$o["ern"]}: {$o["err"]}");
		}

		return $o;
	}

	/**
	 * @param string $url
	 * @param array  $options
	 * @return mixed
	 */
	private function curl(string $url, array $options = [])
	{
		curl_setopt_array($this->ch, $options);
		$ret = [
			"out" => curl_exec($this->ch),
			"err" => curl_error($this->ch),
			"ern" => curl_errno($this->ch),
			"inf" => curl_getinfo($this->ch),
		];
		return $ret;
	}
}
