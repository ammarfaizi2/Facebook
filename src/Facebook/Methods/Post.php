<?php
// SPDX-License-Identifier: GPL-2.0-only
/*
 * Copyright (C) 2023  Ammar Faizi <ammarfaizi2@gnuweeb.org>
 */

namespace Facebook\Methods;

trait Post
{
	/**
	 * @param  string $o
	 * @return array
	 */
	private function parseTimelineYears(string $o): array
	{
		if (!preg_match("/<div.+?id=\"structured_composer_async_container\".*?>(.+?)<\/body>/", $o, $m)) {
			throw new \Exception("Cannot find structured_composer_async_container!");
		}

		$o = $m[1];
		if (preg_match_all("/<div class=\"[a-z]\"><a href=\"(.+?)\">(\d+)<\/a>/", $o, $m)) {
			goto out;
		}

		if (preg_match_all("/<div class=\"[a-z]{3} [a-z]{3}\"><a href=\"(.+?)\">(?:<span>)?(\d+)(?:<\/span>)?<\/a>/", $o, $m)) {
			goto out;
		}

		throw new \Exception("Cannot find timeline years!");

	out:
		$years = [];
		foreach ($m[1] as $k => $v) {
			$years[$m[2][$k]] = html_decode($v);
		}

		return $years;
	}

	/**
	 * Cache timeline year links. 
	 *
	 * @param  string $username
	 * @param  array  $years
	 * @return void
	 */
	private function setCacheTimelineYears(string $username, array $years)
	{
		$years = json_encode($years, JSON_INTERNAL_FLAGS);
		$dir = $this->getUserCacheDir($username);
		file_put_contents("{$dir}/timeline_years.json", $years);
	}

	/**
	 * @param  string $username
	 * @return array|null
	 */
	private function getCacheTimelineYears(string $username): ?array
	{
		$dir = $this->getUserCacheDir($username);
		$file = "{$dir}/timeline_years.json";

		if (!file_exists($file)) {
			return NULL;
		}

		/*
		 * Max cache time: 10 minutes.
		 */
		if (time() - filemtime($file) > 600) {
			unlink($file);
			return NULL;
		}

		$years = json_decode(file_get_contents($file), true);
		if (!is_array($years)) {
			return NULL;
		}

		return $years;
	}

	/**
	 * @param  string $username
	 * @return array
	 */
	public function getTimelineYears(string $username): array
	{
		$username = trim($username);
		if ($username === "") {
			throw new \Exception("Username cannot be empty!");
		}

		$username = urlencode($username);
		$o = $this->http("/profile.php?id={$username}", "GET");
		try {
			$ret = $this->parseTimelineYears($o["out"]);
			if (count($ret) > 0) {
				$this->setCacheTimelineYears($username, $ret);
				return $ret;
			}
		} catch (\Exception $e) {
			// Pass
		}

		if (isset($o["inf"]["redirect_count"]) && $o["inf"]["redirect_count"] == 0) {
			throw new \Exception("Cannot find timeline years of this user!");
		}

		$new_url = $o["inf"]["url"];
		$new_url = explode("?", $new_url, 2)[0];
		$new_url = "{$new_url}?v=timeline";
		$o = $this->http($new_url, "GET")["out"];

		// file_put_contents("tmp.html", $o);
		// $o = file_get_contents("tmp.html");

		$ret = $this->parseTimelineYears($o);
		if (count($ret) > 0) {
			$this->setCacheTimelineYears($username, $ret);
		}

		return $ret;
	}

	/**
	 * Get timeline posts.
	 *
	 * @param  string $username
	 * @param  int    $year
	 * @return array
	 */
	public function getTimelinePosts(string $username, int $year = -1): array
	{
		$years = $this->getCacheTimelineYears($username);
		if (!is_array($years)) {
			$years = $this->getTimelineYears($username);
		}

		if ($year === -1) {
			$year = max(array_keys($years));
		}

		if (!isset($years[$year])) {
			throw new \Exception("Year {$year} not found!");
		}

		$o = $this->http($years[$year], "GET");
		$o = $o["out"];

		// file_put_contents("tmp.html", $o);
		// $o = file_get_contents("tmp.html");

		if (!preg_match("/<div.+?id=\"structured_composer_async_container\".*?>(.+?)<\/body>/", $o, $m)) {
			throw new \Exception("Cannot find structured_composer_async_container!");
		}

		if (!preg_match_all("/<[^>]+?data-ft=\"([^\"]+?&quot;top_level_post_id&quot;[^\"]+?)\"/", $m[1], $m)) {
			throw new \Exception("Cannot find posts!");
		}

		$posts = [];
		foreach ($m[1] as $k => $v) {
			$posts[] = json_decode(html_decode($v), true);
		}

		return $posts;
	}

	/**
	 * @param  string $post_id
	 * @return array
	 */
	public function getPost(string $post_id): array
	{
		/**
		 * $post_id must be numeric or a string starts with "pfbid".
		 */
		if (!is_numeric($post_id) && substr($post_id, 0, 5) !== "pfbid") {
			throw new \Exception("Invalid post id: \$post_id must be numeric or a string starts with \"pfbid\".");
		}

		$o = $this->http("/{$post_id}", "GET");

		$t = $o["out"];
		// file_put_contents("tmp.html", $o["out"]);
		// $t = file_get_contents("tmp.html");

		if (!preg_match("/<[^>]+?data-ft=\"([^\"]+?&quot;top_level_post_id&quot;[^\"]+?)\".*?>(.+?)<[^>]+?id=\"ufi_.+?\">/", $t, $m)) {
			throw new \Exception("Cannot parse post!");
		}

		$info = json_decode(html_decode($m[1]), true);
		if (!preg_match("/<[^>]+?data-ft=\"&#123;&quot;tn&quot;:&quot;\*s&quot;&#125;\"[^>]*?>(.+?)$/s", $m[2], $m)) {
			throw new \Exception("Cannot parse post content!");
		}

		$m = get_inside_tag("<div[^>]*?>", "<\/div[^>]*?>", $m[1]);
		if (!$m) {
			throw new \Exception("Cannot parse post content! get_inside_tag()");
		}

		return [
			"content" => full_html_clean($m),
			"info" => $info
		];
	}
}
