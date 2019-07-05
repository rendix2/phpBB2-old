<?php
/***************************************************************************
 *                             admin_forums.php
 *                            -------------------
 *   begin                : Thursday, Jul 12, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: admin_forums.php 6981 2007-02-10 12:14:24Z acydburn $
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
	$file = basename(__FILE__);
	$module['Forums']['Manage'] = $file;
	return;
}

//
// Load default header
//
$phpbb_root_path = './../';

require_once './pagestart.php';

$forum_auth_ary = [
    'auth_view'       => AUTH_ALL,
    'auth_read'       => AUTH_ALL,
    'auth_post'       => AUTH_REG,
    'auth_reply'      => AUTH_REG,
    'auth_edit'       => AUTH_REG,
    'auth_delete'     => AUTH_REG,
    'auth_sticky'     => AUTH_MOD,
    'auth_announce'   => AUTH_MOD,
    'auth_vote'       => AUTH_REG,
    'auth_pollcreate' => AUTH_REG
];

//
// Mode setting
//
if (isset($_POST[POST_MODE]) || isset($_GET[POST_MODE])) {
    $mode = isset($_POST[POST_MODE]) ? $_POST[POST_MODE] : $_GET[POST_MODE];
    $mode = htmlspecialchars($mode);
} else {
    $mode = '';
}

/**
 *
 * TODO we need 'number' only in one cas!
 *
 * @param $mode
 * @param $id
 *
 * @return \Dibi\Row|false
 */
function get_info($mode, $id)
{
	switch($mode) {
		case 'category':
			$table = CATEGORIES_TABLE;
			$idfield = 'cat_id';
			$namefield = 'cat_title';
			break;

		case 'forum':
			$table = FORUMS_TABLE;
			$idfield = 'forum_id';
			$namefield = 'forum_name';
			break;

		default:
			message_die(GENERAL_ERROR, 'Wrong mode for generating select list', '', __LINE__, __FILE__);
			break;
	}

    $count = dibi::select('COUNT(*)')
        ->as('total')
        ->from($table)
        ->fetchSingle();

	$res = dibi::select('*')
        ->from($table)
        ->where('%n = %i', $idfield, $id)
	    ->fetch();

    $res->number = $count;

	return $res;
}

/**
 * @param string $mode
 * @param int    $id
 * @param int    $select
 *
 * @return string
 */
function get_list($mode, $id, $select)
{
	switch($mode) {
		case 'category':
			$table = CATEGORIES_TABLE;
			$idfield = 'cat_id';
			$namefield = 'cat_title';
			break;

		case 'forum':
			$table = FORUMS_TABLE;
			$idfield = 'forum_id';
			$namefield = 'forum_name';
			break;

		default:
			message_die(GENERAL_ERROR, 'Wrong mode for generating select list', '', __LINE__, __FILE__);
			break;
	}

    $res = dibi::select([$idfield, $namefield])
        ->from($table);

	if ($select === 0) {
	    $res->where('%n <> %i', $idfield, $id);
    }

	$rows = $res->fetchPairs($idfield, $namefield);

    $cat_list = '';

	foreach ($rows as $key => $value) {
		$selected = '';

		if ($key === $id) {
            $selected = ' selected="selected"';
		}

        $cat_list .= '<option value="'.$key.'" '.$selected.'>' . htmlspecialchars($value, ENT_QUOTES) . "</option>\n";
	}

	return $cat_list;
}

/**
 * @param string $mode
 * @param int    $cat
 *
 * @throws \Dibi\Exception
 */
function renumber_order($mode, $cat = 0)
{
	switch($mode)
	{
		case 'category':
			$table = CATEGORIES_TABLE;
			$idfield = 'cat_id';
			$orderfield = 'cat_order';
			$cat = 0;
			break;

		case 'forum':
			$table = FORUMS_TABLE;
			$idfield = 'forum_id';
			$orderfield = 'forum_order';
			$catfield = 'cat_id';
			break;

		default:
			message_die(GENERAL_ERROR, 'Wrong mode for generating select list', '', __LINE__, __FILE__);
			break;
	}

	$res = dibi::select('*')
        ->from($table);

	if ($cat !== 0) {
	    $res->where('%n = %i', $catfield, $cat);
	}

	$rows = $res->orderBy($orderfield, dibi::ASC)
    ->fetchAll();

	$i = 10;
	$inc = 10;

	foreach ($rows as $row) {
	    dibi::update($table, [$orderfield => $i])
            ->where('%n = %i', $idfield, $row->{$idfield})
            ->execute();

		$i += 10;
	}
}

//
// Begin program proper
//
if (isset($_POST['addforum']) || isset($_POST['addcategory'])) {
	$mode = isset($_POST['addforum']) ? 'addforum' : 'addcat';

	if ($mode === 'addforum') {
		list($cat_id) = each($_POST['addforum']);
		$cat_id = (int)$cat_id;
		//
		// stripslashes needs to be run on this because slashes are added when the forum name is posted
		//
		$forumname = stripslashes($_POST['forumname'][$cat_id]);
	}
}

if (!empty($mode)) {
    switch ($mode) {
		case 'addforum':
		case 'editforum':
			//
			// Show form to create/modify a forum
			//
			if ($mode === 'editforum') {
				// $newmode determines if we are going to INSERT or UPDATE after posting?

				$l_title = $lang['Edit_forum'];
				$newmode = 'modforum';
				$buttonvalue = $lang['Update'];

				$forum_id = (int)$_GET[POST_FORUM_URL];

				$row = get_info('forum', $forum_id);

				$cat_id = $row->cat_id;
				$forumname = $row->forum_name;
				$forumdesc = $row->forum_desc;
				$forumstatus = $row->forum_status;

				//
				// start forum prune stuff.
				//
                if ($row->prune_enable) {
					$prune_enabled = 'checked="checked"';

					$pr_row = dibi::select('*')
                        ->from(PRUNE_TABLE)
                        ->where('forum_id = %i', $forum_id)
                        ->fetch();

                    if (!$pr_row) {
						 message_die(GENERAL_ERROR, "Auto-Prune: Couldn't read auto_prune table.", __LINE__, __FILE__);
        			}
				} else {
					$prune_enabled = '';
				}
			} else {
				$l_title = $lang['Create_forum'];
				$newmode = 'createforum';
				$buttonvalue = $lang['Create_forum'];

				$forumdesc = '';
				$forumstatus = FORUM_UNLOCKED;
				$forum_id = '';
				$prune_enabled = '';
			}

			$catlist = get_list('category', $cat_id, true);

			// todo! $forumlocked or $forumunlocked might be undefined
			$forumstatus === FORUM_LOCKED ? $forumlocked = 'selected="selected"' : $forumunlocked = 'selected="selected"';

			// These two options ($lang['Status_unlocked'] and $lang['Status_locked']) seem to be missing from
			// the language files.
			$lang['Status_unlocked'] = isset($lang['Status_unlocked']) ? $lang['Status_unlocked'] : 'Unlocked';
			$lang['Status_locked'] = isset($lang['Status_locked']) ? $lang['Status_locked'] : 'Locked';

			$statuslist = '<option value="' . FORUM_UNLOCKED . "\" $forumunlocked>" . $lang['Status_unlocked'] . "</option>\n";
			$statuslist .= '<option value="' . FORUM_LOCKED . "\" $forumlocked>" . $lang['Status_locked'] . "</option>\n";

            $s_hidden_fields = '<input type="hidden" name="mode" value="' . $newmode . '" /><input type="hidden" name="' . POST_FORUM_URL . '" value="' . $forum_id . '" />';

            $template->setFileNames(['body' => 'admin/forum_edit_body.tpl']);

            $template->assignVars(
                [
                    'S_FORUM_ACTION'  => Session::appendSid('admin_forums.php'),
                    'S_HIDDEN_FIELDS' => $s_hidden_fields,
                    'S_SUBMIT_VALUE'  => $buttonvalue,
                    'S_CAT_LIST'      => $catlist,
                    'S_STATUS_LIST'   => $statuslist,
                    'S_PRUNE_ENABLED' => $prune_enabled,

                    'L_FORUM_TITLE'       => $l_title,
                    'L_FORUM_EXPLAIN'     => $lang['Forum_edit_delete_explain'],
                    'L_FORUM_SETTINGS'    => $lang['Forum_settings'],
                    'L_FORUM_NAME'        => $lang['Forum_name'],
                    'L_CATEGORY'          => $lang['Category'],
                    'L_FORUM_DESCRIPTION' => $lang['Forum_desc'],
                    'L_FORUM_STATUS'      => $lang['Forum_status'],
                    'L_AUTO_PRUNE'        => $lang['Forum_pruning'],
                    'L_ENABLED'           => $lang['Enabled'],
                    'L_PRUNE_DAYS'        => $lang['prune_days'],
                    'L_PRUNE_FREQ'        => $lang['prune_freq'],
                    'L_DAYS'              => $lang['Days'],

                    'PRUNE_DAYS'  => isset($pr_row['prune_days']) ? $pr_row->prune_days : 7,
                    'PRUNE_FREQ'  => isset($pr_row['prune_freq']) ? $pr_row->prune_freq : 1,
                    'FORUM_NAME'  => htmlspecialchars($forumname, ENT_QUOTES),
                    'DESCRIPTION' => htmlspecialchars($forumdesc, ENT_QUOTES)
                ]
            );
            $template->pparse('body');
        break;

		case 'createforum':
			//
			// Create a forum in the DB
			//
			if (trim($_POST['forumname']) === '') {
				message_die(GENERAL_ERROR, "Can't create a forum without a name");
			}

            $max_order = dibi::select('MAX(forum_order)')
                ->as('max_order')
                ->from(FORUMS_TABLE)
                ->where('cat_id = %i', (int)$_POST[POST_CAT_URL])
                ->fetchSingle();

			if ($max_order === false) {
				message_die(GENERAL_ERROR, "Couldn't get order number from forums table");
			}

			$next_order = $max_order + 10;

			//
			// Default permissions of public ::
			//
            $forum_auth_ary['forum_name'] = $_POST['forumname'];
            $forum_auth_ary['cat_id'] = (int)$_POST[POST_CAT_URL];
            $forum_auth_ary['forum_desc'] = $_POST['forumdesc'];
            $forum_auth_ary['forum_order'] = $next_order;
            $forum_auth_ary['forum_status'] = (int)$_POST['forumstatus'];
            $forum_auth_ary['prune_enable'] = (int)$_POST['prune_enable'];

			// There is no problem having duplicate forum names so we won't check for it.
            $forums_id = dibi::insert(FORUMS_TABLE, $forum_auth_ary)->execute(dibi::IDENTIFIER);

            if ($_POST['prune_enable']) {

				if ($_POST['prune_days'] === '' || $_POST['prune_freq'] === '') {
					message_die(GENERAL_MESSAGE, $lang['Set_prune_data']);
				}

				$insert_data = [
				    'forum_id'   => $forums_id,
                    'prune_days' => (int)$_POST['prune_days'],
                    'prune_freq' => (int)$_POST['prune_freq']
                ];

				dibi::insert(PRUNE_TABLE, $insert_data)->execute();
			}

			$message = $lang['Forums_updated'] . '<br /><br />' . sprintf($lang['Click_return_forumadmin'], '<a href="' . Session::appendSid('admin_forums.php') . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

			message_die(GENERAL_MESSAGE, $message);

			break;

		case 'modforum':
			// Modify a forum in the DB
            if (isset($_POST['prune_enable']) && $_POST['prune_enable'] !== '1') {
                $_POST['prune_enable'] = 0;
            }

			$update_data = [
			    'forum_name'   => $_POST['forumname'],
                'cat_id'       => (int)$_POST[POST_CAT_URL],
                'forum_desc'   => $_POST['forumdesc'],
                'forum_status' => (int)$_POST['forumstatus'],
                'prune_enable' => (int)$_POST['prune_enable']
            ];

			dibi::update(FORUMS_TABLE, $update_data)
                ->where('forum_id = %i', (int)$_POST[POST_FORUM_URL])
                ->execute();

            if ($_POST['prune_enable'] === '1') {
				if ($_POST['prune_days'] === '' || $_POST['prune_freq'] === '') {
					message_die(GENERAL_MESSAGE, $lang['Set_prune_data']);
				}

				// little improvement
				$prune_count = dibi::select('COUNT(*)')
                    ->as('prune_count')
                    ->from(PRUNE_TABLE)
                    ->where('forum_id = %i', (int)$_POST[POST_FORUM_URL])
                    ->fetchSingle();

				if ($prune_count === false) {
					message_die(GENERAL_ERROR, "Couldn't get forum Prune Information");
				}

                if ($prune_count > 0) {
				    $update_data = [
				        'prune_days' => (int)$_POST['prune_days'],
                        'prune_freq' => (int)$_POST['prune_freq']
                    ];

				    dibi::update(PRUNE_TABLE, $update_data)
                        ->where('forum_id = %i', (int)$_POST[POST_FORUM_URL])
                        ->execute();
				} else {
				    $insert_data = [
				        'forum_id'   => (int)$_POST[POST_FORUM_URL],
                        'prune_days' => (int)$_POST['prune_days'],
                        'prune_freq' => (int)$_POST['prune_freq']
                    ];

				    dibi::insert(PRUNE_TABLE, $insert_data)->execute();
				}
			}

			$message = $lang['Forums_updated'] . '<br /><br />' . sprintf($lang['Click_return_forumadmin'], '<a href="' . Session::appendSid('admin_forums.php') . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

			message_die(GENERAL_MESSAGE, $message);

			break;

		case 'addcat':
			// Create a category in the DB
			if (trim($_POST['categoryname']) === '') {
				message_die(GENERAL_ERROR, "Can't create a category without a name");
			}

			$max_order = dibi::select('MAX(cat_order)')
                ->as('max_order')
                ->from(CATEGORIES_TABLE)
                ->fetchSingle();

			$next_order = $max_order + 10;

			//
			// There is no problem having duplicate forum names so we won't check for it.
			//

            $insert_data = [
                'cat_title' => $_POST['categoryname'],
                'cat_order' => $next_order
            ];

            dibi::insert(CATEGORIES_TABLE, $insert_data)->execute();

			$message = $lang['Forums_updated'] . '<br /><br />' . sprintf($lang['Click_return_forumadmin'], '<a href="' . Session::appendSid('admin_forums.php') . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

			message_die(GENERAL_MESSAGE, $message);

			break;

		case 'editcat':
			//
			// Show form to edit a category
			//
			$newmode = 'modcat';
			$buttonvalue = $lang['Update'];

			$cat_id = (int)$_GET[POST_CAT_URL];

			$row = get_info('category', $cat_id);
			$cat_title = $row->cat_title;

            $template->setFileNames(['body' => 'admin/category_edit_body.tpl']);

            $s_hidden_fields = '<input type="hidden" name="mode" value="' . $newmode . '" /><input type="hidden" name="' . POST_CAT_URL . '" value="' . $cat_id . '" />';

            $template->assignVars(
                [
                    'CAT_TITLE' => htmlspecialchars($cat_title, ENT_QUOTES),

                    'L_EDIT_CATEGORY'         => $lang['Edit_Category'],
                    'L_EDIT_CATEGORY_EXPLAIN' => $lang['Edit_Category_explain'],
                    'L_CATEGORY'              => $lang['Category'],

                    'S_HIDDEN_FIELDS' => $s_hidden_fields,
                    'S_SUBMIT_VALUE'  => $buttonvalue,
                    'S_FORUM_ACTION'  => Session::appendSid('admin_forums.php')
                ]
            );

            $template->pparse('body');
			break;

		case 'modcat':
            // Modify a category in the DB
		    dibi::update(CATEGORIES_TABLE, ['cat_title' => $_POST['cat_title']])
                ->where('cat_id = %i', (int)$_POST[POST_CAT_URL])
                ->execute();

			$message = $lang['Forums_updated'] . '<br /><br />' . sprintf($lang['Click_return_forumadmin'], '<a href="' . Session::appendSid('admin_forums.php') . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

			message_die(GENERAL_MESSAGE, $message);

			break;

		case 'deleteforum':
			// Show form to delete a forum
			$forum_id = (int)$_GET[POST_FORUM_URL];

			$select_to = '<select name="to_id">';
			$select_to .= "<option value=\"-1\"$s>" . $lang['Delete_all_posts'] . "</option>\n";
			$select_to .= get_list('forum', $forum_id, 0);
			$select_to .= '</select>';

			$buttonvalue = $lang['Move_and_Delete'];

			$newmode = 'movedelforum';

			$foruminfo = get_info('forum', $forum_id);
			$name = $foruminfo->forum_name;

            $template->setFileNames(['body' => 'admin/forum_delete_body.tpl']);

            $s_hidden_fields = '<input type="hidden" name="mode" value="' . $newmode . '" /><input type="hidden" name="from_id" value="' . $forum_id . '" />';

            $template->assignVars(
                [
                    'NAME' => $name,

                    'L_FORUM_DELETE'         => $lang['Forum_delete'],
                    'L_FORUM_DELETE_EXPLAIN' => $lang['Forum_delete_explain'],
                    'L_MOVE_CONTENTS'        => $lang['Move_contents'],
                    'L_FORUM_NAME'           => $lang['Forum_name'],

                    'S_HIDDEN_FIELDS' => $s_hidden_fields,
                    'S_FORUM_ACTION'  => Session::appendSid('admin_forums.php'),
                    'S_SELECT_TO'     => $select_to,
                    'S_SUBMIT_VALUE'  => $buttonvalue
                ]
            );

            $template->pparse('body');
			break;

		case 'movedelforum':
			//
			// Move or delete a forum in the DB
			//
			$from_id    = (int)$_POST['from_id'];
			$to_id      = (int)$_POST['to_id'];
			$delete_old = (int)$_POST['delete_old'];

			// Either delete or move all posts in a forum
			if ($to_id === -1) {
				// Delete polls in this forum

                $vote_ids = dibi::select('v.vote_id')
                    ->from(VOTE_DESC_TABLE)
                    ->as('v')
                    ->innerJoin(TOPICS_TABLE)
                    ->as('t')
                    ->on('v.topic_id = t.topic_id')
                    ->where('t.forum_id = %i', $from_id)
                    ->fetchPairs(null, 'vote_id');

                if (count($vote_ids)) {
                    dibi::delete(VOTE_DESC_TABLE)
                        ->where('vote_id IN %in', $vote_ids)
                        ->execute();

                    dibi::delete(VOTE_RESULTS_TABLE)
                        ->where('vote_id IN %in', $vote_ids)
                        ->execute();

                    dibi::delete(VOTE_USERS_TABLE)
                        ->where('vote_id IN %in', $vote_ids)
                        ->execute();
                }

                require_once $phpbb_root_path . 'includes/prune.php';
				prune($from_id, 0, true); // Delete everything from forum
			} else {
			    $forums_exists = dibi::select('*')
                    ->from(FORUMS_TABLE)
                    ->where('forum_id IN %in', [$from_id, $to_id])
                    ->fetchAll();

				if (count($forums_exists) !== 2) {
					message_die(GENERAL_ERROR, "Ambiguous forum ID's", '', __LINE__, __FILE__);
				}

				dibi::update(TOPICS_TABLE, ['forum_id' => $to_id])
                    ->where('forum_id = %i', $from_id)
                    ->execute();

				dibi::update(POSTS_TABLE, ['forum_id' => $to_id])
                    ->where('forum_id = %i', $from_id)
                    ->execute();

				sync('forum', $to_id);
			}

            // Alter Mod level if appropriate - 2.0.4
			$user_mods_ids = dibi::select('ug.user_id')
                ->from(AUTH_ACCESS_TABLE)
                ->as('a')
                ->innerJoin(USER_GROUP_TABLE)
                ->as('ug')
                ->on('ug.group_id = a.group_id')
                ->where('a.forum_id <> %i', $from_id)
                ->where('a.auth_mod = %i', 1)
                ->fetchPairs(null, 'user_id');

            if (count($user_mods_ids)) {
                $user_ids = dibi::select('ug.user_id')
                    ->from(AUTH_ACCESS_TABLE)
                    ->as('a')
                    ->innerJoin(USER_GROUP_TABLE)
                    ->as('ug')
                    ->on('ug.group_id = a.group_id')
                    ->where('a.forum_id = %i', $from_id)
                    ->where('a.auth_mod = %i', 1)
                    ->where('ug.user_id NOT IN %in', $user_mods_ids)
                    ->fetchPairs(null, 'user_id');

                if (count($user_ids)) {
                    dibi::update(USERS_TABLE, ['user_level' => USER])
                        ->where('user_id IN %in', $user_ids)
                        ->where('user_level <> %i', ADMIN)
                        ->execute();
                }
            }

            dibi::delete(FORUMS_TABLE)
                ->where('forum_id = %i', $from_id)
                ->execute();

			dibi::delete(AUTH_ACCESS_TABLE)
                ->where('forum_id = %i', $from_id)
                ->execute();

            dibi::delete(PRUNE_TABLE)
                ->where('forum_id = %i', $from_id)
                ->execute();

			$message = $lang['Forums_updated'] . '<br /><br />' . sprintf($lang['Click_return_forumadmin'], '<a href="' . Session::appendSid('admin_forums.php') . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

			message_die(GENERAL_MESSAGE, $message);

			break;

		case 'deletecat':
			//
			// Show form to delete a category
			//
			$cat_id = (int)$_GET[POST_CAT_URL];

			$buttonvalue = $lang['Move_and_Delete'];
			$newmode = 'movedelcat';

			$catinfo = get_info('category', $cat_id);
			$name = $catinfo->cat_title;

			if ($catinfo->number === 1) {
                $count = dibi::select('COUNT(*)')
                    ->select('total')
                    ->as(FORUMS_TABLE)
                    ->fetchSingle();

				if ($count === false) {
					message_die(GENERAL_ERROR, "Couldn't get Forum count");
				}

				if ($count > 0) {
					message_die(GENERAL_ERROR, $lang['Must_delete_forums']);
				} else {
					$select_to = $lang['Nowhere_to_move'];
				}
			} else {
				$select_to = '<select name="to_id">';
				$select_to .= get_list('category', $cat_id, 0);
				$select_to .= '</select>';
			}

            $template->setFileNames(['body' => 'admin/forum_delete_body.tpl']);

            $s_hidden_fields = '<input type="hidden" name="mode" value="' . $newmode . '" /><input type="hidden" name="from_id" value="' . $cat_id . '" />';

            $template->assignVars(
                [
                    'NAME' => $name,

                    'L_FORUM_DELETE'         => $lang['Forum_delete'],
                    'L_FORUM_DELETE_EXPLAIN' => $lang['Forum_delete_explain'],
                    'L_MOVE_CONTENTS'        => $lang['Move_contents'],
                    'L_FORUM_NAME'           => $lang['Forum_name'],

                    'S_HIDDEN_FIELDS' => $s_hidden_fields,
                    'S_FORUM_ACTION'  => Session::appendSid('admin_forums.php'),
                    'S_SELECT_TO'     => $select_to,
                    'S_SUBMIT_VALUE'  => $buttonvalue
                ]
            );

            $template->pparse('body');
			break;

		case 'movedelcat':
			//
			// Move or delete a category in the DB
			//
			$from_id = (int)$_POST['from_id'];
			$to_id   = (int)$_POST['to_id'];

			if (!empty($to_id)) {
			    $cat_exists = dibi::select('*')
                    ->from(CATEGORIES_TABLE)
                    ->where('cat_id IN %in', [$from_id, $to_id])
                    ->fetchAll();

				if (count($cat_exists) !== 2) {
					message_die(GENERAL_ERROR, "Ambiguous category ID's", '', __LINE__, __FILE__);
				}

				dibi::update(FORUMS_TABLE, ['cat_id' => $to_id])
                    ->where('cat_id = %i', $from_id)
                    ->execute();
			}

			dibi::delete(CATEGORIES_TABLE)
                ->where('cat_id = %i', $from_id)
                ->execute();

			$message = $lang['Forums_updated'] . '<br /><br />' . sprintf($lang['Click_return_forumadmin'], '<a href="' . Session::appendSid('admin_forums.php') . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

			message_die(GENERAL_MESSAGE, $message);

			break;

		case 'forum_order':
			//
			// Change order of forums in the DB
			//
			$move     = (int)$_GET['move'];
			$forum_id = (int)$_GET[POST_FORUM_URL];

			$forum_info = get_info('forum', $forum_id);

			$cat_id = $forum_info->cat_id;

            if ($move > 0) {
                dibi::update(FORUMS_TABLE, ['forum_order%sql' => 'forum_order + ' . $move])
                    ->where('forum_id = %i', $forum_id)
                    ->execute();
            } else {
                dibi::update(FORUMS_TABLE, ['forum_order%sql' => 'forum_order  - ' . abs($move)])
                    ->where('forum_id = %i', $forum_id)
                    ->execute();
            }

			renumber_order('forum', $forum_info->cat_id);
			$show_index = true;

			break;

		case 'cat_order':
			//
			// Change order of categories in the DB
			//
			$move   = (int)$_GET['move'];
			$cat_id = (int)$_GET[POST_CAT_URL];

            if ($move > 0) {
                dibi::update(CATEGORIES_TABLE, ['cat_order%sql' => 'cat_order + ' . $move])
                    ->where('cat_id = %i', $cat_id)
                    ->execute();
            } else {
                dibi::update(CATEGORIES_TABLE, ['cat_order%sql' => 'cat_order - ' . abs($move)])
                    ->where('cat_id = %i', $cat_id)
                    ->execute();
            }

			renumber_order('category');
			$show_index = true;

			break;

		case 'forum_sync':
			sync('forum', (int)$_GET[POST_FORUM_URL]);
			$show_index = true;

			break;

		default:
			message_die(GENERAL_MESSAGE, $lang['No_mode']);
			break;
	}

    if ($show_index !== true) {
        require_once './page_footer_admin.php';
		exit;
	}
}

//
// Start page proper
//
$template->setFileNames(['body' => 'admin/forum_admin_body.tpl']);

$template->assignVars(
    [
        'S_FORUM_ACTION'    => Session::appendSid('admin_forums.php'),
        'L_FORUM_TITLE'     => $lang['Forum_admin'],
        'L_FORUM_EXPLAIN'   => $lang['Forum_admin_explain'],
        'L_CREATE_FORUM'    => $lang['Create_forum'],
        'L_CREATE_CATEGORY' => $lang['Create_category'],
        'L_CATEGORY_NAME'   => $lang['Category_name'],
        'L_EDIT'            => $lang['Edit'],
        'L_DELETE'          => $lang['Delete'],
        'L_MOVE_UP'         => $lang['Move_up'],
        'L_MOVE_DOWN'       => $lang['Move_down'],
        'L_RESYNC'          => $lang['Resync'],
        'L_PERMISSIONS'     => $lang['Permissions'],
        'L_FORUM_PRUNE'     => $lang['Forum_Prune'],
    ]
);

$categories = dibi::select(['cat_id', 'cat_title', 'cat_order'])
    ->from(CATEGORIES_TABLE)
    ->orderBy('cat_order')
    ->fetchAll();

$category_count = count($categories);

$forums_count = 0;

if ($category_count) {
    $forums = dibi::select('*')
        ->from(FORUMS_TABLE)
        ->orderBy('cat_id')
        ->orderBy('forum_order')
        ->fetchAll();

    $forums_count = count($forums);

	//
	// Okay, let's build the index
	//
	$gen_cat = [];
	$cat_i = 0;

    $tmp = [];

    foreach ($categories as $category) {
        $tmp[$category->cat_id] = 0;
    }

    foreach ($forums as $forum) {
        $tmp[$forum->cat_id]++;
    }

    foreach ($categories as $category) {
		$cat_id = $category->cat_id;
		$cat_i++;

        $template->assignBlockVars('catrow',
            [
                'S_ADD_FORUM_SUBMIT' => "addforum[$cat_id]",
                'S_ADD_FORUM_NAME'   => "forumname[$cat_id]",

                'CAT_ID'   => $cat_id,
                'CAT_DESC' => htmlspecialchars($category->cat_title, ENT_QUOTES),

                'L_POSTS'  => $lang['Number_posts'],
                'L_FORUM_NAME'  => $lang['Forum_name'],
                'L_TOPICS' => $lang['Number_topics'],

                'U_CAT_EDIT'      => Session::appendSid('admin_forums.php?mode=editcat&amp;' . POST_CAT_URL . "=$cat_id"),
                'U_CAT_DELETE'    => Session::appendSid('admin_forums.php?mode=deletecat&amp;' . POST_CAT_URL . "=$cat_id"),
                'U_CAT_MOVE_UP'   => Session::appendSid('admin_forums.php?mode=cat_order&amp;move=-15&amp;' . POST_CAT_URL . "=$cat_id"),
                'U_CAT_MOVE_DOWN' => Session::appendSid('admin_forums.php?mode=cat_order&amp;move=15&amp;' . POST_CAT_URL . "=$cat_id"),
                'U_VIEWCAT'       => Session::appendSid($phpbb_root_path . 'index.php?' . POST_CAT_URL . "=$cat_id")
            ]
        );

        if ($category->cat_order - 15 > 0) {
            $template->assignBlockVars('catrow.up', []);
        }

        if ($cat_i !== $category_count) {
            $template->assignBlockVars('catrow.down', []);
        }

        $forum_i = 0;

        foreach ($forums as $i => $forum) {
            $forum_i++;
			$forum_id = $forum->forum_id;

			if ($forum->cat_id === $cat_id) {
                $row_color = !($i % 2) ? $theme['td_color1'] : $theme['td_color2'];
                $row_class = !($i % 2) ? $theme['td_class1'] : $theme['td_class2'];

                $template->assignBlockVars('catrow.forumrow',
                    [
                        'FORUM_NAME' => htmlspecialchars($forum->forum_name, ENT_QUOTES),
                        'FORUM_DESC' => htmlspecialchars($forum->forum_desc, ENT_QUOTES),

                        'ROW_COLOR'  => '#' . $row_color,
                        'ROW_CLASS'  => $row_class,
                        'NUM_TOPICS' => $forum->forum_topics,
                        'NUM_POSTS'  => $forum->forum_posts,

                        'U_VIEWFORUM'         => Session::appendSid($phpbb_root_path . 'viewforum.php?' . POST_FORUM_URL . "=$forum_id"),
                        'U_FORUM_EDIT'        => Session::appendSid('admin_forums.php?mode=editforum&amp;' . POST_FORUM_URL . "=$forum_id"),
                        'U_FORUM_DELETE'      => Session::appendSid('admin_forums.php?mode=deleteforum&amp;' . POST_FORUM_URL . "=$forum_id"),
                        'U_FORUM_MOVE_UP'     => Session::appendSid('admin_forums.php?mode=forum_order&amp;move=-15&amp;' . POST_FORUM_URL . "=$forum_id"),
                        'U_FORUM_MOVE_DOWN'   => Session::appendSid('admin_forums.php?mode=forum_order&amp;move=15&amp;' . POST_FORUM_URL . "=$forum_id"),
                        'U_FORUM_RESYNC'      => Session::appendSid('admin_forums.php?mode=forum_sync&amp;' . POST_FORUM_URL . "=$forum_id"),
                        'U_FORUM_PERMISSIONS' => Session::appendSid('admin_forumauth.php?' . POST_FORUM_URL . "=$forum_id"),
                        'U_FORUM_PRUNE'       => Session::appendSid('admin_forum_prune.php?' . POST_FORUM_URL . "=$forum_id")
                    ]
                );

                if ($forum->forum_order - 15 > 0) {
                    $template->assignBlockVars('catrow.forumrow.up', []);
                }

                if ($tmp[$forum->cat_id] !== $forum_i) {
                    $template->assignBlockVars('catrow.forumrow.down', []);
                }

            } else {
                $forum_i = 0;
            }

		} // for ... forums

	} // for ... categories

}// if ... total_categories

$template->pparse('body');

require_once './page_footer_admin.php';

?>