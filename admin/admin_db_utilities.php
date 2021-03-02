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

//
// Load default header
//
$no_page_header = true;
$sep = DIRECTORY_SEPARATOR;
$phpbb_root_path = '.' . $sep . '..' . $sep;

require_once '.' . $sep . 'pagestart.php';
require_once $phpbb_root_path . 'includes/sql_parse.php';

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
if (isset($_GET['perform']) || isset($_POST['perform'])) {
	$perform = isset($_POST['perform']) ? $_POST['perform'] : $_GET['perform'];

	switch ($perform) {
		case 'backup':
			$dumper = new Mysqldump(
				Config::DATABASE_DNS,
				Config::DATABASE_USER,
				Config::DATABASE_PASSWORD
			);

			$sep = DIRECTORY_SEPARATOR;
			$backup_file_name = $phpbb_root_path . $sep . 'temp' . $sep .'phpbb_db_backup.sql';

			$dumper->start($backup_file_name);

			header('Content-Type: application/octet-stream');
			header('Content-Transfer-Encoding: Binary');
			header('Content-disposition: attachment; filename="' . basename($backup_file_name) . '"');
			readfile($backup_file_name); // do the double-download-dance (dirty but worky)
			unlink($backup_file_name);
			exit;

		case 'restore':
			if (isset($_POST['restore_start'])) {
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
				if (file_exists(realpath($backup_file_tmpname))) {
					dibi::loadFile($backup_file_tmpname);
				} else {
					message_die(GENERAL_ERROR, $lang['Restore_Error_uploading']);
				}

				require_once '.' . $sep . 'page_header_admin.php';

				$template->setFileNames(['body' => 'admin/admin_message_body.tpl']);

				$template->assignVars(
					[
						'MESSAGE_TITLE' => $lang['Database_Utilities'] . ' : ' . $lang['Restore'],
						'MESSAGE_TEXT'  => $lang['Restore_success']
					]
				);

				$template->pparse('body');
			} else {
				//
				// Define Template files...
				//
				require_once '.' . $sep . 'page_header_admin.php';

				$template->setFileNames(['body' => 'admin/db_utils_restore_body.tpl']);

				$s_hidden_fields = "<input type=\"hidden\" name=\"perform\" value=\"restore\" /><input type=\"hidden\" name=\"perform\" value=\"$perform\" />";

				$template->assignVars(
					[
						'L_DATABASE_RESTORE' => $lang['Database_Utilities'] . ' : ' . $lang['Restore'],
						'L_RESTORE_EXPLAIN'  => $lang['Restore_explain'],
						'L_SELECT_FILE'      => $lang['Select_file'],
						'L_START_RESTORE'    => $lang['Start_Restore'],

						'S_DBUTILS_ACTION' => Session::appendSid('admin_db_utilities.php'),
						'S_HIDDEN_FIELDS'  => $s_hidden_fields
					]
				);
				$template->pparse('body');
			}
			break;
	}
}

require_once '.' . $sep . 'page_footer_admin.php';

?>
