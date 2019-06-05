<?php
/***************************************************************************
*                             admin_db_utilities.php
*                              -------------------
*     begin                : Thu May 31, 2001
*     copyright            : (C) 2001 The phpBB Group
*     email                : support@phpbb.com
*
*     $Id: admin_db_utilities.php 5539 2006-02-10 20:35:40Z grahamje $
*
****************************************************************************/

use Ifsnop\Mysqldump\Mysqldump;

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 ***************************************************************************/

/***************************************************************************
*	We will attempt to create a file based backup of all of the data in the
*	users phpBB database.  The resulting file should be able to be imported by
*	the db_restore.php function, or by using the mysql command_line
*
*	Some functions are adapted from the upgrade_20.php script and others
*	adapted from the unoficial phpMyAdmin 2.2.0.
***************************************************************************/

define('IN_PHPBB', 1);

if (!empty($setmodules) ) {
	$filename = basename(__FILE__);
	$module['Database']['Backup_DB'] = $filename . '?perform=backup';

	$file_uploads = @ini_get('file_uploads');

	if ((empty($file_uploads) || $file_uploads !== 0) && (strtolower($file_uploads) !== 'off')) {
		$module['Database']['Restore_DB'] = $filename . '?perform=restore';
	}

	return;
}

//
// Load default header
//
$no_page_header = TRUE;
$phpbb_root_path = './../';

require './pagestart.php';
include $phpbb_root_path . 'includes/sql_parse.php';

//
// Set VERBOSE to 1  for debugging info..
//
define('VERBOSE', 0);

//
// Increase maximum execution time, but don't complain about it if it isn't
// allowed.
//
@set_time_limit(1200);

//
// Begin program proper
//
if (isset($_GET['perform']) || isset($_POST['perform']) ) {
	$perform = isset($_POST['perform']) ? $_POST['perform'] : $_GET['perform'];

	switch($perform) {
		case 'backup':
			$dumper = new Mysqldump(
				$dbms.':host='.$dbhost.';dbname='.$dbname.';charset=utf8',
				$dbuser,
				$dbpasswd
			);

			$dir_separator = DIRECTORY_SEPARATOR;
			$backup_file_name = $phpbb_root_path . $dir_separator . 'temp' . $dir_separator .'phpbb_db_backup.sql';

			$dumper->start($backup_file_name);

			header('Content-Type: application/octet-stream');
			header('Content-Transfer-Encoding: Binary');
			header('Content-disposition: attachment; filename="' . basename($backup_file_name) . '"');
			readfile($backup_file_name); // do the double-download-dance (dirty but worky)
			unlink($backup_file_name);
			exit;

		case 'restore':
			if (!isset($_POST['restore_start'])) {
				//
				// Define Template files...
				//
				include './page_header_admin.php';

				$template->set_filenames(['body' => 'admin/db_utils_restore_body.tpl']);

				$s_hidden_fields = "<input type=\"hidden\" name=\"perform\" value=\"restore\" /><input type=\"hidden\" name=\"perform\" value=\"$perform\" />";

				$template->assign_vars(
					[
                        'L_DATABASE_RESTORE' => $lang['Database_Utilities'] . ' : ' . $lang['Restore'],
                        'L_RESTORE_EXPLAIN'  => $lang['Restore_explain'],
                        'L_SELECT_FILE'      => $lang['Select_file'],
                        'L_START_RESTORE'    => $lang['Start_Restore'],

                        'S_DBUTILS_ACTION' => append_sid('admin_db_utilities.php'),
                        'S_HIDDEN_FIELDS'  => $s_hidden_fields
					]
				);
				$template->pparse('body');

				break;

			} else {
				//
				// Handle the file upload ....
				// If no file was uploaded report an error...
				//
				$backup_file_name = !empty($_FILES['backup_file']['name']) ? $_FILES['backup_file']['name'] : '';
				$backup_file_tmpname = ($_FILES['backup_file']['tmp_name'] !== 'none') ? $_FILES['backup_file']['tmp_name'] : '';
				$backup_file_type = !empty($_FILES['backup_file']['type']) ? $_FILES['backup_file']['type'] : '';

				if ($backup_file_tmpname === '' || $backup_file_name === '') {
					message_die(GENERAL_MESSAGE, $lang['Restore_Error_no_file']);
				}
				//
				// If I file was actually uploaded, check to make sure that we
				// are actually passed the name of an uploaded file, and not
				// a hackers attempt at getting us to process a local system
				// file.
				//
				if (file_exists(phpbb_realpath($backup_file_tmpname)) ) {
					dibi::loadFile($backup_file_tmpname);
				} else {
					message_die(GENERAL_ERROR, $lang['Restore_Error_uploading']);
				}

				include './page_header_admin.php';

				$template->set_filenames(['body' => 'admin/admin_message_body.tpl']);

				$message = $lang['Restore_success'];

				$template->assign_vars(
					[
                        'MESSAGE_TITLE' => $lang['Database_Utilities'] . ' : ' . $lang['Restore'],
                        'MESSAGE_TEXT'  => $message
					]
				);

				$template->pparse('body');
				break;
			}
			break;
	}
}

include './page_footer_admin.php';

?>
