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
	 * @param  bool   $take_content
	 * @return array
	 */
	public function getTimelinePosts(string $username, int $year = -1, bool $take_content = false, int $limit = -1): array
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

		$i = 0;
		$posts = [];
		foreach ($m[1] as $k => $v) {
			if ($limit !== -1 && $i >= $limit)
				break;

			$i++;
			$info = json_decode(html_decode($v), true);
			if (!is_array($info)) {
				continue;
			}

			if (!$take_content) {
				$posts[] = [
					"info" => $info,
				];
				continue;
			}

			/*
			 * If $take_content is true. Visit the post to grab the
			 * content as well.
			 */
			$content = NULL;
			if (isset($info["top_level_post_id"])) {
				$tmp = $this->getPost($info["top_level_post_id"]);
				if (!empty($tmp["content"]))
					$content = $tmp["content"];
			}

			$posts[] = [
				"content" => $content,
				"info" => $info
			];
		}

		return $posts;
	}

	/**
	 * @param string $o
	 * @return ?array
	 */
	private function parseEmbeddedLink(string $o): ?array
	{
		if (!preg_match("/<div[^>]+?data-ft=\"&#123;&quot;tn&quot;:&quot;H&quot;&#125;\"[^>]*>(.+?)<\/body>/", $o, $m)) {
			return NULL;
		}

		$o = get_inside_tag("<div[^>]*?>", "<\/div[^>]*?>", $m[1]);
		if (!$m) {
			return NULL;
		}

		$desc = preg_replace("/<\/h\d>/", "\n", $o);
		$desc = preg_replace("/<\/p>/", "\n", $desc);
		$desc = html_decode(full_html_clean($desc));

		/*
		 * Parse the image preview.
		 */
		$img = NULL;
		if (preg_match("/<img[^>]+?src=\"([^\"]+?)\"/", $o, $mm)) {
			$img = [
				"url" => html_decode($mm[1]),
				"width" => NULL,
				"height" => NULL,
				"alt" => NULL
			];

			$m = $mm[0];
			if (preg_match("/<img[^>]+?width=\"(\d+)\"/", $m, $mm)) {
				$img["width"] = intval($mm[1]);
			}

			if (preg_match("/<img[^>]+?height=\"(\d+)\"/", $m, $mm)) {
				$img["height"] = intval($mm[1]);
			}

			if (preg_match("/<img[^>]+?alt=\"([^\"]+?)\"/", $m, $mm)) {
				$img["alt"] = html_decode($mm[1]);
			}
		}

		/*
		 * Parse the URL.
		 */
		$url = NULL;
		if (preg_match("/<a[^>]+href=\"[^\"]+[\\&\\?]u=([^\\&\\?]+)[^\"]+\"[^>]+?>/", $o, $mm)) {
			$url = html_decode($mm[1]);
			$url = urldecode($url);
		}

		return [
			"url" => $url,
			"desc" => $desc,
			"img_preview" => $img
		];
	}

	/**
	 * @param string &$o
	 * @return ?array
	 */
	private function parsePostInfo(string &$o): ?array
	{
		if (!preg_match("/<[^>]+?data-ft=\"([^\"]+?&quot;top_level_post_id&quot;[^\"]+?)\".*?>(.+?)<[^>]+?id=\"ufi_.+?\">/", $o, $m)) {
			return NULL;
		}

		$ret = json_decode(html_decode($m[1]), true);
		if (!is_array($ret)) {
			return NULL;
		}

		$o = $m[2];
		return $ret;
	}

	/**
	 * @param string $o
	 * @return ?array
	 */
	private function tryParseTextPost(string &$o): ?array
	{
		if (!preg_match("/<[^>]+?data-ft=\"&#123;&quot;tn&quot;:&quot;\*s&quot;&#125;\"[^>]*?>(.+?)$/s", $o, $m)) {
			return NULL;
		}

		$m = get_inside_tag("<div[^>]*?>", "<\/div[^>]*?>", $m[1]);
		if (!$m) {
			return NULL;
		}

		return [
			"type" => "text",
			"text" => full_html_clean($m),
		];
	}

	/**
	 * @param  string $o
	 * @return ?array
	 */
	private function tryParsePhotoPost(string $o): ?array
	{
		$caption = NULL;
		$photos = [];
		$p = [
			"url" => NULL,
			"width" => NULL,
			"height" => NULL,
			"alt" => NULL,
		];

		/*
		 * Parse photo URLs. Currently, only one photo is parsed.
		 */
		if (!preg_match("/<div style=\"text-align:center;\">(<img[^>]+>)/", $o, $m)) {
			return NULL;
		}

		$oo = $m[1];

		/*
		 * Get width and height of the photo.
		 */
		if (preg_match("/width=\"(\d+)\" height=\"(\d+)\"/", $oo, $m)) {
			$p["width"] = intval($m[1]);
			$p["height"] = intval($m[2]);
		}

		/*
		 * Get photo URL.
		 */
		if (preg_match("/src=\"([^\"]+)\"/", $oo, $m)) {
			$p["url"] = html_decode($m[1]);
		}

		/*
		 * Get photo alt.
		 */
		if (preg_match("/alt=\"([^\"]+)\"/", $oo, $m)) {
			$p["alt"] = html_decode($m[1]);
		}

		$photos[] = $p;

		/*
		 * Parse photo caption.
		 */
		if (preg_match("/<[^>]+?data-ft=\"&#123;&quot;tn&quot;:&quot;,g&quot;&#125;\"[^>]*?>(.+?)$/s", $o, $m)) {
			/*
			 * Remove the poster name, don't include it in the caption.
			 */
			$m[1] = preg_replace("/<[^>]+?class=\"actor-link\".+?<\/a>/", "", $m[1]);

			$m = get_inside_tag("<div[^>]*?>", "<\/div[^>]*?>", $m[1]);
			if ($m)
				$caption = full_html_clean($m);
		}

		return [
			"type"   => "photo",
			"text"   => $caption,
			"photos" => $photos
		];
	}

	/**
	 * @param  string $o
	 * @return array
	 */
	private function parsePostContent(string $o): array
	{
		$ret = $this->tryParseTextPost($o);
		if ($ret) {
			return $ret;
		}

		$ret = $this->tryParsePhotoPost($o);
		if ($ret) {
			return $ret;
		}

		throw new \Exception("Cannot parse post!");
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

		$o = $this->http("/{$post_id}", "GET")["out"];
		// file_put_contents("tmp.html", $o);
		// $o = file_get_contents("tmp.html");

		/*
		 * parsePostInfo() may change $o.
		 */
		$orig = $o;
		$info = $this->parsePostInfo($o);
		$content = $this->parsePostContent($o);
		$content["embedded_link"] = $this->parseEmbeddedLink($orig);

		return [
			"content" => $content,
			"info"    => $info
		];
	}
}
