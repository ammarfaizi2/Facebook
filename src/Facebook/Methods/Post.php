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
		if (!preg_match("/<div.+?id=\"structured_composer_async_container\".+?>(.+?)<\/body>/", $o, $m)) {
			throw new \Exception("Cannot find structured_composer_async_container!");
		}

		if (!preg_match_all("/<div class=\"[a-z]\"><a href=\"(.+?)\">(\d+)<\/a>/", $m[1], $m)) {
			throw new \Exception("Cannot find posts!");
		}

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
	private function saveTimelineYears(string $username, array $years)
	{
		$years = json_encode($years, JSON_INTERNAL_FLAGS);
		$dir = $this->getUserCacheDir($username);
		file_put_contents("{$dir}/timeline_years.json", $years);
	}

	/**
	 * @param  string $username
	 * @return array
	 */
	public function getTimelineYears(string $username): array
	{
		$o = $this->http("/{$username}", "GET");
		$o = $o["out"];

		// file_put_contents("tmp.html", $o);
		// $o = file_get_contents("tmp.html");

		$o = $this->parseTimelineYears($o);
		$this->saveTimelineYears($username, $o);
		return $o;
	}
}
