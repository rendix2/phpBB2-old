<?php
/***************************************************************************
 *                            function_selects.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: functions_selects.php 5142 2005-05-06 20:50:13Z acydburn $
 *
 *
 ***************************************************************************/

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 *
 ***************************************************************************/

//
// Pick a language, any language ...
//
function language_select($default, $select_name = 'language', $dirname= 'language')
{
	global $phpbb_root_path;

	$dir = opendir($phpbb_root_path . $dirname);

	$langs = [];
    while ($file = readdir($dir)) {
		if (preg_match('#^lang_#i', $file) && !is_file(@phpbb_realpath($phpbb_root_path . $dirname . '/' . $file)) && !is_link(@phpbb_realpath($phpbb_root_path . $dirname . '/' . $file))) {
			$filename = trim(str_replace('lang_', '', $file));
			$displayName = preg_replace('/^(.*?)_(.*)$/', "\\1 [ \\2 ]", $filename);
			$displayName = preg_replace("/\[(.*?)_(.*)\]/", "[ \\1 - \\2 ]", $displayName);
			$langs[$displayName] = $filename;
		}
	}

	closedir($dir);

	@asort($langs);

	$default = strtolower($default);

    $lang_select = '<select name="' . $select_name . '" id="' . $select_name . '"">';

	foreach ($langs as $displayName => $filename) {
		$selected = $default === strtolower($filename) ? ' selected="selected"' : '';
		$lang_select .= '<option value="' . $filename . '"' . $selected . '>' . ucwords($displayName) . '</option>';
	}

	$lang_select .= '</select>';

	return $lang_select;
}

?>