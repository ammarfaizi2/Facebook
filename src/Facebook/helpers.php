<?php
// SPDX-License-Identifier: GPL-2.0-only
/*
 * Copyright (C) 2023  Ammar Faizi <ammarfaizi2@gnuweeb.org>
 */

const JSON_INTERNAL_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

/**
 * @param  string $str
 * @return string
 */
function html_decode(string $str)
{
	return html_entity_decode($str, ENT_QUOTES, "UTF-8");
}

/**
 * @param  string $pat
 * @param  string $end
 * @param  string $subject
 * @return string|null
 */
function get_inside_tag(string $pat, string $end, string $subject): ?string
{
	$ret = "";
	$depth = 0;
	$st = preg_split("/({$pat})/s", $subject, -1, PREG_SPLIT_DELIM_CAPTURE);
	if (!$st)
		return NULL;

	foreach ($st as $k => $v) {
		$depth++;

		$ed = preg_split("/({$end})/s", $v, -1, PREG_SPLIT_DELIM_CAPTURE);
		if (!$ed) {
			$ret .= $v;
			continue;
		}

		foreach ($ed as $k2 => $v2) {
			if (--$depth == -1)
				goto out;

			$ret .= $v2;
		}
	}

out:
	return $ret;
}

/**
 * @param  string $m
 * @return string
 */
function full_html_clean(string $m): string
{
	$m = str_replace(["<br />"], "\n", $m);
	$m = str_replace(["</p>"], "\n\n", $m);
	$m = strip_tags($m);
	$m = html_decode($m);
	$m = explode("\n", $m);
	foreach ($m as &$c)
		$c = trim($c);

	return trim(implode("\n", $m));
}
