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
			$displayname = preg_replace('/^(.*?)_(.*)$/', "\\1 [ \\2 ]", $filename);
			$displayname = preg_replace("/\[(.*?)_(.*)\]/", "[ \\1 - \\2 ]", $displayname);
			$langs[$displayname] = $filename;
		}
	}

	closedir($dir);

	@asort($langs);

	$lang_select = '<select name="' . $select_name . '">';

	foreach ($langs as $displayname => $filename) {
		$selected = ( strtolower($default) === strtolower($filename) ) ? ' selected="selected"' : '';
		$lang_select .= '<option value="' . $filename . '"' . $selected . '>' . ucwords($displayname) . '</option>';
	}

	$lang_select .= '</select>';

	return $lang_select;
}

//
// Pick a template/theme combo, 
//
function style_select($default_style, $select_name = 'style', $dirname = 'templates')
{
    $themes = dibi::select(['themes_id', 'style_name'])
        ->from(THEMES_TABLE)
        ->orderBy('template_name')
        ->orderBy('themes_id')
        ->fetchPairs('themes_id', 'style_name');

    if (!count($themes)) {
        message_die(GENERAL_ERROR, 'Could not query themes table.');
    }

	$style_select = '<select name="' . $select_name . '">';

	foreach ($themes as $themes_id => $style_name) {
		$selected = ( $themes_id === $default_style ) ? ' selected="selected"' : '';

		$style_select .= '<option value="' . $themes_id . '"' . $selected . '>' . $style_name . '</option>';
	}

	$style_select .= '</select>';

	return $style_select;
}

?>