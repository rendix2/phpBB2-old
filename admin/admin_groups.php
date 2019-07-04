<?php
/***************************************************************************
 *                             admin_groups.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: admin_groups.php 5614 2006-03-09 19:42:41Z grahamje $
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
    $module['Groups']['Manage'] = $filename;

    return;
}

//
// Load default header
//
$phpbb_root_path = './../';

require_once './pagestart.php';

if (isset($_POST[POST_GROUPS_URL]) || isset($_GET[POST_GROUPS_URL])) {
    $group_id = isset($_POST[POST_GROUPS_URL]) ? (int)$_POST[POST_GROUPS_URL] : (int)$_GET[POST_GROUPS_URL];
} else {
    $group_id = 0;
}

if (isset($_POST[POST_MODE]) || isset($_GET[POST_MODE])) {
    $mode = isset($_POST[POST_MODE]) ? $_POST[POST_MODE] : $_GET[POST_MODE];
} else {
    $mode = '';
}

//
// Ok, they are submitting a group, let's save the data based on if it's new or editing
//
if (isset($_POST['group_update'])) {
	$group_type        = isset($_POST['group_type'])        ? (int)$_POST['group_type']         : GROUP_OPEN;
	$group_name        = isset($_POST['group_name'])        ? trim($_POST['group_name'])        : '';
	$group_description = isset($_POST['group_description']) ? trim($_POST['group_description']) : '';
	$group_moderator   = isset($_POST['username'])          ? $_POST['username']                : '';

	$delete_old_moderator = isset($_POST['delete_old_moderator']);

	if ($group_name === '') {
		message_die(GENERAL_MESSAGE, $lang['No_group_name']);
	} elseif ($group_moderator === '') {
		message_die(GENERAL_MESSAGE, $lang['No_group_moderator']);
	}

	$this_userdata   = get_userdata($group_moderator, true);
	$group_moderator = $this_userdata->user_id;

	if (!$group_moderator) {
		message_die(GENERAL_MESSAGE, $lang['No_group_moderator']);
	}

	if ($mode === 'edit') {
		// TODO we dont need check group_single_user
		$group_info = dibi::select('*')
			->from(GROUPS_TABLE)
			->where('group_id = %i', $group_id)
			->where('group_single_user <> %i', 1)
			->fetch();

		if (!$group_info) {
			message_die(GENERAL_MESSAGE, $lang['Group_not_exist']);
		}

		if ($group_info->group_moderator !== $group_moderator) {
			if ($delete_old_moderator) {
				dibi::delete(USER_GROUP_TABLE)
					->where('user_id = %i', $group_info->group_moderator)
					->where('group_id = %i', $group_id)
					->execute();
			}

			$moderator = dibi::select('user_id')
				->from(USER_GROUP_TABLE)
				->where('user_id = %i', $group_moderator)
				->where('group_id = %i', $group_id)
				->fetch();

			if (!$moderator) {
				$moderator_insert_data = [
					'group_id'     => $group_id,
					'user_id'      => $group_moderator,
					'user_pending' => 0
				];

				dibi::insert(USER_GROUP_TABLE, $moderator_insert_data)->execute();
			}
		}

		$group_update_data = [
			'group_type'        => $group_type,
			'group_name'        => $group_name,
			'group_description' => $group_description,
			'group_moderator'   => $group_moderator
		];

		dibi::update(GROUPS_TABLE, $group_update_data)
			->where('group_id = %i', $group_id)
			->execute();

		$message = $lang['Updated_group'] . '<br /><br />' . sprintf($lang['Click_return_groupsadmin'], '<a href="' . Session::appendSid('admin_groups.php') . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

		message_die(GENERAL_MESSAGE, $message);
	} elseif ($mode === 'new') {
		$group_insert_data = [
			'group_type'        => $group_type,
			'group_name'        => $group_name,
			'group_description' => $group_description,
			'group_moderator'   => $group_moderator,
			'group_single_user' => 0
		];

		$new_group_id = dibi::insert(GROUPS_TABLE, $group_insert_data)->execute(dibi::IDENTIFIER);

		$user_group_insert_data = [
			'group_id' => $new_group_id,
			'user_id'  => $group_moderator,
			'user_pending' => 0
		];

		dibi::insert(USER_GROUP_TABLE, $user_group_insert_data)->execute();

		$message = $lang['Added_new_group'] . '<br /><br />' . sprintf($lang['Click_return_groupsadmin'], '<a href="' . Session::appendSid('admin_groups.php') . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

		message_die(GENERAL_MESSAGE, $message);
	} else {
		message_die(GENERAL_MESSAGE, $lang['No_group_action']);
	}
}

// add or edit view
if ($mode === 'edit' || $mode === 'new') {
	//
	// Ok they are editing a group or creating a new group
	//
	$template->setFileNames(['body' => 'admin/group_edit_body.tpl']);

	if ($mode === 'edit') {
		//
		// They're editing. Grab the vars.
		//
		$group_info = dibi::select('*')
			->from(GROUPS_TABLE)
			->where('group_id = %i', $group_id)
			->where('group_single_user <> %i', 1)
			->fetch();

		if (!$group_info) {
			message_die(GENERAL_MESSAGE, $lang['Group_not_exist']);
		}

		$moderatorName = '';

		if ($group_info->group_moderator === $userdata['user_id']) {
			$moderatorName = $userdata['username'];
		} else {
			$moderator = dibi::select(['user_id', 'username'])
				->from(USERS_TABLE)
				->where('user_id = %i', $group_info->group_moderator)
				->fetch();

			if (!$moderator) {
				message_die(GENERAL_ERROR, 'Could not obtain user info for moderator list');
			}

			$moderatorName = $moderator->username;
		}

		$template->assignVars(
			[
				'GROUP_NAME'        => htmlspecialchars($group_info->group_name, ENT_QUOTES),
				'GROUP_DESCRIPTION' => htmlspecialchars($group_info->group_description, ENT_QUOTES),
				'GROUP_MODERATOR'   => htmlspecialchars($moderatorName, ENT_QUOTES),
			]
		);

		$template->assignBlockVars('group_edit', []);
	} elseif ($mode === 'new') {
		$group_info = ['group_type' => GROUP_OPEN];

		$group_open = ' checked="checked"';

		$template->assignVars(
			[
				'GROUP_NAME'        => '',
				'GROUP_DESCRIPTION' => '',
				'GROUP_MODERATOR'   => '',
			]
		);
	}

	$group_open   = $group_info['group_type'] === GROUP_OPEN   ? ' checked="checked"' : '';
	$group_closed = $group_info['group_type'] === GROUP_CLOSED ? ' checked="checked"' : '';
	$group_hidden = $group_info['group_type'] === GROUP_HIDDEN ? ' checked="checked"' : '';

	$s_hidden_fields = '<input type="hidden" name="mode" value="' . $mode . '" />';
	$s_hidden_fields .= '<input type="hidden" name="' . POST_GROUPS_URL . '" value="' . $group_id . '" />';

	$template->assignVars(
		[
            'L_GROUP_TITLE'        => $lang['Group_administration'],
            'L_GROUP_EDIT_DELETE'  => isset($_POST['new']) ? $lang['New_group'] : $lang['Edit_group'],
            'L_GROUP_NAME'         => $lang['group_name'],
            'L_GROUP_DESCRIPTION'  => $lang['group_description'],
            'L_GROUP_MODERATOR'    => $lang['group_moderator'],
            'L_FIND_USERNAME'      => $lang['Find_username'],
            'L_GROUP_STATUS'       => $lang['group_status'],
            'L_GROUP_OPEN'         => $lang['group_open'],
            'L_GROUP_CLOSED'       => $lang['group_closed'],
            'L_GROUP_HIDDEN'       => $lang['group_hidden'],
            'L_GROUP_DELETE'       => $lang['group_delete'],
            'L_GROUP_DELETE_CHECK' => $lang['group_delete_check'],

            'L_SUBMIT' => $lang['Submit'],
            'L_RESET'  => $lang['Reset'],

            'L_DELETE_MODERATOR'         => $lang['delete_group_moderator'],
            'L_DELETE_MODERATOR_EXPLAIN' => $lang['delete_moderator_explain'],

            'L_YES' => $lang['Yes'],

            'U_SEARCH_USER' => Session::appendSid('../search.php?mode=searchuser'),

            'S_GROUP_OPEN_TYPE'      => GROUP_OPEN,
            'S_GROUP_CLOSED_TYPE'    => GROUP_CLOSED,
            'S_GROUP_HIDDEN_TYPE'    => GROUP_HIDDEN,

            'S_GROUP_OPEN_CHECKED'   => $group_open,
            'S_GROUP_CLOSED_CHECKED' => $group_closed,
            'S_GROUP_HIDDEN_CHECKED' => $group_hidden,
            'S_GROUP_ACTION'         => Session::appendSid('admin_groups.php'),
            'S_HIDDEN_FIELDS'        => $s_hidden_fields
		]
	);

	$template->pparse('body');
}

// list
if ($mode === '') {
	$groups = dibi::select('*')
		->from(GROUPS_TABLE)
		->as('g')
		->innerJoin(USERS_TABLE)
		->as('u')
		->on('g.group_moderator = u.user_id')
		->where('g.group_single_user <> %i', 1)
		->fetchAll();

	$parameters = [
		'L_GROUP_TITLE'   => $lang['Group_administration'],
		'L_GROUP_EXPLAIN' => $lang['Group_admin_explain'],

		'L_GROUP_ID' => $lang['group_id'],
		'L_GROUP_NAME' => $lang['group_name'],
		'L_GROUP_DESCRIPTION' => $lang['group_description'],
		'L_GROUP_STATUS' => $lang['group_status'],
		'L_GROUP_MODERATOR' => $lang['group_moderator'],
		'L_GROUP_DELETE' => $lang['group_delete'],
		'L_GROUP_NEW' => $lang['New_group'],

		'C_OPEN' => GROUP_OPEN,
		'C_CLOSED' => GROUP_CLOSED,
		'C_HIDDEN' => GROUP_HIDDEN,

		'C_GROUP_ID' => POST_GROUPS_URL,

		'L_OPEN' => $lang['group_open'],
		'L_CLOSED' => $lang['group_closed'],
		'L_HIDDEN' => $lang['group_hidden'],

		'S_SID' => $SID,

		'D_GROUPS' => $groups
	];

	$latte = new LatteFactory($storage, $userdata);

	$latte->render('admin/group_select_body.latte', $parameters);
}

// delete
if ($mode === 'delete') {
	//
	// Reset User Moderator Level
	//
	$auth_mod = dibi::select('auth_mod')
		->from(AUTH_ACCESS_TABLE)
		->where('group_id = %i', $group_id)
		->fetchSingle();

	if ((int)$auth_mod === 1) {
		// Yes, get the assigned users and update their Permission if they are no longer moderator of one of the forums
		$users = dibi::select('user_id')
			->from(USER_GROUP_TABLE)
			->where('group_id = %i', $group_id)
			->fetchAll();

		// TODO improve first query and join USER_TABLE and check if user is MOD or not!
		// dont check it in update query
		foreach ($users as $user) {
			$group_ids = dibi::select('g.group_id')
				->from(AUTH_ACCESS_TABLE)
				->as('a')
				->from(GROUPS_TABLE)
				->as('g')
				->from(USER_GROUP_TABLE)
				->as('ug')
				->where('a.auth_mod = %i', 1)
				->where('g.group_id = a.group_id')
				->where('a.group_id = ug.group_id')
				->where('g.group_id = ug.group_id')
				->where('ug.user_id = %i',(int)$user->user_id)
				->where('ug.group_id <> %i', $group_id)
				->fetchAll();

			if (count($group_ids) === 0) {
				dibi::update(USERS_TABLE, ['user_level' => USER])
					->where('user_level = %i', MOD)
					->where('user_id = %i', (int)$user->user_id)
					->execute();
			}
		}
	}

	//
	// Delete Group
	//
	dibi::delete(GROUPS_TABLE)
		->where('group_id = %i', $group_id)
		->execute();

	dibi::delete(USER_GROUP_TABLE)
		->where('group_id = %i', $group_id)
		->execute();

	dibi::delete(AUTH_ACCESS_TABLE)
		->where('group_id = %i', $group_id)
		->execute();

	$message = $lang['Deleted_group'] . '<br /><br />' . sprintf($lang['Click_return_groupsadmin'], '<a href="' . Session::appendSid('admin_groups.php') . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

	message_die(GENERAL_MESSAGE, $message);
}

require_once './page_footer_admin.php';

?>
