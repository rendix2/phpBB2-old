<?php
/***************************************************************************
 *                           page_footer_admin.php
 *                            -------------------
 *   begin                : Saturday, Jul 14, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: page_footer_admin.php 5214 2005-09-19 20:49:06Z grahamje $
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
 ***************************************************************************/

if (!defined('IN_PHPBB')) {
    die('Hacking attempt');
}

global $do_gzip_compress;

//
// Show the overall footer.
//
$template->set_filenames(['page_footer' => 'admin/page_footer.tpl']);

if (isset($lang['TRANSLATION_INFO']) ) {
    $translation_info = $lang['TRANSLATION_INFO'];
} else {
    $translation_info = isset($lang['TRANSLATION']) ? $lang['TRANSLATION'] : '';
}

$template->assign_vars(
    [
        'PHPBB_VERSION'    => ($userdata['user_level'] === ADMIN && $userdata['user_id'] !== ANONYMOUS) ? '2' . $board_config['version'] : '',
        'TRANSLATION_INFO' => $translation_info
    ]
);

$template->pparse('page_footer');

//
// Close our DB connection.
//
dibi::disconnect();

//
// Compress buffered output if required
// and send to browser
//
if ($do_gzip_compress ) {
	//
	// Borrowed from php.net!
	//
	$gzip_contents = ob_get_contents();
	ob_end_clean();

	$gzip_size = strlen($gzip_contents);
	$gzip_crc = crc32($gzip_contents);

	$gzip_contents = gzcompress($gzip_contents, 9);
	$gzip_contents = substr($gzip_contents, 0, -4);

	echo "\x1f\x8b\x08\x00\x00\x00\x00\x00";
	echo $gzip_contents;
	echo pack('V', $gzip_crc);
	echo pack('V', $gzip_size);
}

exit;

?>