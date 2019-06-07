<?php
/***************************************************************************
 *                            admin_forumauth.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: admin_forumauth.php 4876 2004-03-25 15:57:20Z acydburn $
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

if (!empty($setmodules) ) {
	$filename = basename(__FILE__);
	$module['Forums']['Permissions']   = $filename;

	return;
}

//
// Load default header
//
$no_page_header = true;
$phpbb_root_path = './../';

require './pagestart.php';

//
// Start program - define vars
//
//          View      Read      Post      Reply     Edit     Delete    Sticky   Announce    Vote      Poll
$simple_auth_ary = [
    0 => [AUTH_ALL, AUTH_ALL, AUTH_ALL, AUTH_ALL, AUTH_REG, AUTH_REG, AUTH_MOD, AUTH_MOD, AUTH_REG, AUTH_REG],
    1 => [AUTH_ALL, AUTH_ALL, AUTH_REG, AUTH_REG, AUTH_REG, AUTH_REG, AUTH_MOD, AUTH_MOD, AUTH_REG, AUTH_REG],
    2 => [AUTH_REG, AUTH_REG, AUTH_REG, AUTH_REG, AUTH_REG, AUTH_REG, AUTH_MOD, AUTH_MOD, AUTH_REG, AUTH_REG],
    3 => [AUTH_ALL, AUTH_ACL, AUTH_ACL, AUTH_ACL, AUTH_ACL, AUTH_ACL, AUTH_ACL, AUTH_MOD, AUTH_ACL, AUTH_ACL],
    4 => [AUTH_ACL, AUTH_ACL, AUTH_ACL, AUTH_ACL, AUTH_ACL, AUTH_ACL, AUTH_ACL, AUTH_MOD, AUTH_ACL, AUTH_ACL],
    5 => [AUTH_ALL, AUTH_MOD, AUTH_MOD, AUTH_MOD, AUTH_MOD, AUTH_MOD, AUTH_MOD, AUTH_MOD, AUTH_MOD, AUTH_MOD],
    6 => [AUTH_MOD, AUTH_MOD, AUTH_MOD, AUTH_MOD, AUTH_MOD, AUTH_MOD, AUTH_MOD, AUTH_MOD, AUTH_MOD, AUTH_MOD],
];

$simple_auth_types = [
    $lang['Public'],
    $lang['Registered'],
    $lang['Registered'] . ' [' . $lang['Hidden'] . ']',
    $lang['Private'],
    $lang['Private'] . ' [' . $lang['Hidden'] . ']',
    $lang['Moderators'],
    $lang['Moderators'] . ' [' . $lang['Hidden'] . ']'
];

$forum_auth_fields = [
    'auth_view',
    'auth_read',
    'auth_post',
    'auth_reply',
    'auth_edit',
    'auth_delete',
    'auth_sticky',
    'auth_announce',
    'auth_vote',
    'auth_pollcreate'
];

$field_names = [
    'auth_view'       => $lang['View'],
    'auth_read'       => $lang['Read'],
    'auth_post'       => $lang['Post'],
    'auth_reply'      => $lang['Reply'],
    'auth_edit'       => $lang['Edit'],
    'auth_delete'     => $lang['Delete'],
    'auth_sticky'     => $lang['Sticky'],
    'auth_announce'   => $lang['Announce'],
    'auth_vote'       => $lang['Vote'],
    'auth_pollcreate' => $lang['Pollcreate']
];

$forum_auth_levels = ['ALL', 'REG', 'PRIVATE', 'MOD', 'ADMIN'];
$forum_auth_const  = [AUTH_ALL, AUTH_REG, AUTH_ACL, AUTH_MOD, AUTH_ADMIN];

if (isset($_GET[POST_FORUM_URL]) || isset($_POST[POST_FORUM_URL])) {
    $forum_id  = isset($_POST[POST_FORUM_URL]) ? (int)$_POST[POST_FORUM_URL] : (int)$_GET[POST_FORUM_URL];
    $forum_sql = true;
} else {
    unset($forum_id);
    $forum_sql = false;
}

// advanced
if (isset($_GET['adv'])) {
    $adv = (int)$_GET['adv'];
} else {
    unset($adv);
}

//
// Start program proper
//
if (isset($_POST['submit'])) {
    if (!empty($forum_id)) {
		if (isset($_POST['simpleauth']))
		{
			$simple_ary = $simple_auth_ary[(int)$_POST['simpleauth']];

			if (count($forum_auth_fields) === count($simple_ary)) {
                $update_data = array_combine($forum_auth_fields, $simple_ary);

                dibi::update(FORUMS_TABLE, $update_data)
                    ->where('forum_id = %i', $forum_id)
                    ->execute();
            }
		} else {
		    $update_data = [];

		    foreach ($forum_auth_fields as $forum_auth_field) {
                $value = (int)$_POST[$forum_auth_field];

                if ($forum_auth_field === 'auth_value') {
                    if ($_POST['auth_vote'] === AUTH_ALL) {
                        $value = AUTH_REG;
                    }
                }

                $update_data[$forum_auth_field] = $value;
            }

		    dibi::update(FORUMS_TABLE, $update_data)
                ->where('forum_id = %i', $forum_id)
                ->execute();
		}

		$forum_sql = false;
		$adv = 0;
	}

    $template->assignVars(
        [
            'META' => '<meta http-equiv="refresh" content="3;url=' . Session::appendSid('admin_forumauth.php?' . POST_FORUM_URL . "=$forum_id") . '">'
        ]
    );
    $message = $lang['Forum_auth_updated'] . '<br /><br />' . sprintf($lang['Click_return_forumauth'],  '<a href="' . Session::appendSid('admin_forumauth.php') . '">', '</a>');
	message_die(GENERAL_MESSAGE, $message);

} // End of submit

//
// Get required information, either all forums if
// no id was specified or just the requsted if it
// was
//
$forum_rows = dibi::select('f.*')
    ->from(FORUMS_TABLE)
    ->as('f')
    ->innerJoin(CATEGORIES_TABLE)
    ->as('c')
    ->on('c.cat_id = f.cat_id');

if ($forum_sql) {
    $forum_rows->where('forum_id = %i', $forum_id);
}

$forum_rows = $forum_rows->orderBy('c.cat_order', dibi::ASC)
    ->orderBy('f.forum_order', dibi::ASC)
    ->fetchAll();

if (empty($forum_id) ) {
	//
	// Output the selection table if no forum id was
	// specified
	//
    $template->setFileNames(['body' => 'admin/auth_select_body.tpl']);

    $select_list = '<select name="' . POST_FORUM_URL . '">';

    foreach ($forum_rows as $forum_row) {
		$select_list .= '<option value="' . $forum_row->forum_id . '">' . $forum_row->forum_name . '</option>';
	}

	$select_list .= '</select>';

    $template->assignVars(
        [
            'L_AUTH_TITLE'   => $lang['Auth_Control_Forum'],
            'L_AUTH_EXPLAIN' => $lang['Forum_auth_explain'],
            'L_AUTH_SELECT'  => $lang['Select_a_Forum'],
            'L_LOOK_UP'      => $lang['Look_up_Forum'],

            'S_AUTH_ACTION' => Session::appendSid('admin_forumauth.php'),
            'S_AUTH_SELECT' => $select_list
        ]
    );
} else {
	//
	// Output the authorisation details if an id was
	// specified
	//
    $template->setFileNames(['body' => 'admin/auth_forum_body.tpl']);

    $forum_name = $forum_rows[0]->forum_name;

    $matched_type = '';

	foreach ($simple_auth_ary as $key => $auth_levels) {
		$matched = 1;
		foreach ($auth_levels as $k => $auth_level) {
			$matched_type = $key;

			if ( $forum_rows[0]->{$forum_auth_fields[$k]} !== $auth_level ) {
				$matched = 0;
			}
		}

		if ( $matched ) {
			break;
		}
	}

	//
	// If we didn't get a match above then we
	// automatically switch into 'advanced' mode
	//
    if (!isset($adv) && !$matched) {
        $adv = 1;
    }

	$s_column_span = 0;

    if (empty($adv)) {
		$simple_auth = '<select name="simpleauth">';

        foreach ($simple_auth_types as $key => $simple_auth_type) {
			$selected = ( $matched_type === $key ) ? ' selected="selected"' : '';
			$simple_auth .= '<option value="' . $key . '"' . $selected . '>' . $simple_auth_type . '</option>';
		}

		$simple_auth .= '</select>';

        $template->assignBlockVars('forum_auth_titles', ['CELL_TITLE' => $lang['Simple_mode']]);
        $template->assignBlockVars('forum_auth_data', ['S_AUTH_LEVELS_SELECT' => $simple_auth]);

        $s_column_span++;
	} else {
		//
		// Output values of individual
		// fields
		//
		foreach ($forum_auth_fields as $key => $forum_auth_field) {
			$custom_auth[$key] = '&nbsp;<select name="' . $forum_auth_field . '">';

            foreach ($forum_auth_levels as $key2 => $forum_auth_level) {
                $selected = ( $forum_rows[0]->{$forum_auth_field} === $forum_auth_const[$key2] ) ? ' selected="selected"' : '';
                $custom_auth[$key] .= '<option value="' . $forum_auth_const[$key2] . '"' . $selected . '>' . $lang['Forum_' . $forum_auth_level] . '</option>';
            }

			$custom_auth[$key] .= '</select>&nbsp;';

			$cell_title = $field_names[$forum_auth_field];

            $template->assignBlockVars('forum_auth_titles', ['CELL_TITLE' => $cell_title]);
            $template->assignBlockVars('forum_auth_data', ['S_AUTH_LEVELS_SELECT' => $custom_auth[$key]]);

            $s_column_span++;
		}
	}

	$adv_mode = empty($adv) ? '1' : '0';
	$switch_mode = Session::appendSid('admin_forumauth.php?' . POST_FORUM_URL . '=' . $forum_id . '&adv=' . $adv_mode);
	$switch_mode_text = empty($adv) ? $lang['Advanced_mode'] : $lang['Simple_mode'];
	$u_switch_mode = '<a href="' . $switch_mode . '">' . $switch_mode_text . '</a>';

	$s_hidden_fields = '<input type="hidden" name="' . POST_FORUM_URL . '" value="' . $forum_id . '">';

	$template->assignVars([
            'FORUM_NAME' => $forum_name,

            'L_FORUM' => $lang['Forum'],
            'L_AUTH_TITLE' => $lang['Auth_Control_Forum'],
            'L_AUTH_EXPLAIN' => $lang['Forum_auth_explain'],
            'L_SUBMIT' => $lang['Submit'],
            'L_RESET' => $lang['Reset'],

            'U_SWITCH_MODE' => $u_switch_mode,

            'S_FORUMAUTH_ACTION' => Session::appendSid('admin_forumauth.php'),
            'S_COLUMN_SPAN' => $s_column_span,
            'S_HIDDEN_FIELDS' => $s_hidden_fields]
	);

}

include './page_header_admin.php';

$template->pparse('body');

include './page_footer_admin.php';

?>