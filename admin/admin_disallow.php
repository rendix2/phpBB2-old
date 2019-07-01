<?php
/***************************************************************************
 *                            admin_disallow.php
 *                            -------------------
 *   begin                : Tuesday, Oct 05, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: admin_disallow.php 5352 2005-12-18 13:57:51Z grahamje $
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

define('IN_PHPBB', 1);

if (!empty($setmodules)) {
	$filename = basename(__FILE__);
	$module['Users']['Disallow'] = $filename;

	return;
}

//
// Include required files and check permissions
//
$phpbb_root_path = './../';

require_once './pagestart.php';

if (isset($_POST['add_name'])) {
	$disallowed_user = isset($_POST['disallowed_user']) ? trim($_POST['disallowed_user']) : trim($_GET['disallowed_user']);

	if ($disallowed_user === '') {
		message_die(GENERAL_MESSAGE, $lang['Fields_empty']);
	}

	if (Validator::userName($disallowed_user, $lang, $userdata->toArray())) {
		dibi::insert(DISALLOW_TABLE, ['disallow_username' => $disallowed_user])->execute();

		$message = $lang['Disallow_successful'];
	} else {
		$message = $lang['Disallowed_already'];
	}

	$message .= '<br /><br />' . sprintf($lang['Click_return_disallowadmin'], '<a href="' . Session::appendSid('admin_disallow.php') . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

	message_die(GENERAL_MESSAGE, $message);
} elseif (isset($_POST['delete_name'])) {
	$disallowed_id = isset($_POST['disallowed_id']) ? (int)$_POST['disallowed_id'] : (int)$_GET['disallowed_id'];

	if ($disallowed_id === -1) {
        $message .= $lang['No_disallowed'] . '<br /><br />' . sprintf($lang['Click_return_disallowadmin'], '<a href="' . Session::appendSid('admin_disallow.php') . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

        message_die(GENERAL_MESSAGE, $message);
    }

    dibi::delete(DISALLOW_TABLE)
        ->where('disallow_id = %i', $disallowed_id)
        ->execute();

	$message .= $lang['Disallowed_deleted'] . '<br /><br />' . sprintf($lang['Click_return_disallowadmin'], '<a href="' . Session::appendSid('admin_disallow.php') . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

	message_die(GENERAL_MESSAGE, $message);
}

//
// Grab the current list of disallowed usernames...
//
$disallowed = dibi::select('*')
    ->from(DISALLOW_TABLE)
    ->fetchPairs('disallow_id', 'disallow_username');

//
// Ok now generate the info for the template, which will be put out no matter
// what mode we are in.
//
$disallow_select = Select::dissalow($lang, $disallowed);

$template->setFileNames(['body' => 'admin/disallow_body.tpl']);

$template->assignVars(
    [
        'S_DISALLOW_SELECT' => $disallow_select,
        'S_FORM_ACTION'     => Session::appendSid('admin_disallow.php'),

        'L_INFO'             => $output_info,
        'L_DISALLOW_TITLE'   => $lang['Disallow_control'],
        'L_DISALLOW_EXPLAIN' => $lang['Disallow_explain'],
        'L_DELETE'           => $lang['Delete_disallow'],
        'L_DELETE_DISALLOW'  => $lang['Delete_disallow_title'],
        'L_DELETE_EXPLAIN'   => $lang['Delete_disallow_explain'],
        'L_ADD'              => $lang['Add_disallow'],
        'L_ADD_DISALLOW'     => $lang['Add_disallow_title'],
        'L_ADD_EXPLAIN'      => $lang['Add_disallow_explain'],
        'L_USERNAME'         => $lang['Username']
    ]
);

$template->pparse('body');

require_once './page_footer_admin.php';

?>