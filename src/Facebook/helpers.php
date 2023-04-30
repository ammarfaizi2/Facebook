<?php
// SPDX-License-Identifier: GPL-2.0-only
/*
 * Copyright (C) 2023  Ammar Faizi <ammarfaizi2@gnuweeb.org>
 */

/**
 * @param  string $str
 * @return string
 */
function html_decode(string $str)
{
	return html_entity_decode($str, ENT_QUOTES, "UTF-8");
}
