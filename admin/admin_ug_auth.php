<?php
/***************************************************************************
 *                            admin_ug_auth.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: admin_ug_auth.php 8378 2008-02-10 17:18:29Z acydburn $
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

//
// Load default header
//
$no_page_header = true;
$sep = DIRECTORY_SEPARATOR;
$phpbb_root_path = '.' . $sep . '..' . $sep;

require_once '.' . $sep . 'pagestart.php';

if (!empty($_POST[POST_MODE]) || !empty($_GET[POST_MODE])) {
    $mode = !empty($_POST[POST_MODE]) ? $_POST[POST_MODE] : $_GET[POST_MODE];
} else {
    $mode = '';
}

if (!empty($_POST[POST_USERS_URL]) || !empty($_GET[POST_USERS_URL])) {
    $user_id = !empty($_POST[POST_USERS_URL]) ? $_POST[POST_USERS_URL] : $_GET[POST_USERS_URL];
} else {
    $user_id = '';
}

if (!empty($_POST[POST_GROUPS_URL]) || !empty($_GET[POST_GROUPS_URL])) {
    $group_id = !empty($_POST[POST_GROUPS_URL]) ? $_POST[POST_GROUPS_URL] : $_GET[POST_GROUPS_URL];
} else {
    $group_id = '';
}

if (!empty($_POST['adv']) || !empty($_GET['adv'])) {
    $adv = !empty($_POST['adv']) ? $_POST['adv'] : $_GET['adv'];
} else {
    $adv = '';
}

$user_id = (int)$user_id;
$group_id = (int)$group_id;
$adv = (int)$adv;
$mode = htmlspecialchars($mode);

//
// Start program - define vars
//
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

$auth_field_match = [
    'auth_view'       => AUTH_VIEW,
    'auth_read'       => AUTH_READ,
    'auth_post'       => AUTH_POST,
    'auth_reply'      => AUTH_REPLY,
    'auth_edit'       => AUTH_EDIT,
    'auth_delete'     => AUTH_DELETE,
    'auth_sticky'     => AUTH_STICKY,
    'auth_announce'   => AUTH_ANNOUNCE,
    'auth_vote'       => AUTH_VOTE,
    'auth_pollcreate' => AUTH_POLLCREATE
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

if (isset($_POST['submit']) && (($mode === 'user' && $user_id) || ($mode === 'group' && $group_id))) {
	$user_level = '';

    if ($mode === 'user') {
		//
		// Get group_id for this user_id
		//

        $row = dibi::select(['g.group_id', 'u.user_level'])
            ->from(Tables::USERS_GROUPS_TABLE)
            ->as('ug')
            ->innerJoin(Tables::USERS_TABLE)
            ->as('u')
            ->on('ug.user_id = u.user_id')
            ->from(Tables::GROUPS_TABLE)
            ->as('g')
            ->on('g.group_id = ug.group_id')
            ->where('u.user_id = %i', $user_id)
            ->where('g.group_single_user = %i', 1)
            ->fetch();

		$group_id = $row->group_id;
		$user_level = $row->user_level;
	}

	//
	// Carry out requests
	//
    if ($mode === 'user' && $_POST['userlevel'] === 'admin' && $user_level !== ADMIN) {
		//
		// Make user an admin (if already user)
		//
		if ($userdata['user_id'] !== $user_id) {
		    dibi::update(Tables::USERS_TABLE, ['user_level' => ADMIN, 'user_acp_password%sql' => 'user_password'])
                ->where('user_id = %i', $user_id)
                ->execute();

			dibi::delete(Tables::AUTH_ACCESS_TABLE)
                ->where('group_id = %i', $group_id)
                ->where('auth_mod = %i', 0)
                ->execute();

			//
			// Delete any entries in auth_access, they are not required if user is becoming an
			// admin
			//

            $update_data = [
                'auth_view' => 0,
                'auth_read' => 0,
                'auth_post' => 0,
                'auth_reply' => 0,
                'auth_edit' => 0,
                'auth_delete' => 0,
                'auth_sticky' => 0,
                'auth_announce' => 0
            ];

            dibi::update(Tables::AUTH_ACCESS_TABLE, $update_data)
                ->where('group_id = %i', $group_id)
                ->execute();
		}

		$message = $lang['Auth_updated'] . '<br /><br />' . sprintf($lang['Click_return_userauth'], '<a href="' . Session::appendSid("admin_ug_auth.php?mode=$mode") . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');
		message_die(GENERAL_MESSAGE, $message);
	} else {
        if ($mode === 'user' && $_POST['userlevel'] === 'user' && $user_level === ADMIN) {
			//
			// Make admin a user (if already admin) ... ignore if you're trying
			// to change yourself from an admin to user!
			//
			if ($userdata['user_id'] !== $user_id) {
                $update_data = [
                    'auth_view' => 0,
                    'auth_read' => 0,
                    'auth_post' => 0,
                    'auth_reply' => 0,
                    'auth_edit' => 0,
                    'auth_delete' => 0,
                    'auth_sticky' => 0,
                    'auth_announce' => 0
                ];

                dibi::update(Tables::AUTH_ACCESS_TABLE, $update_data)
                    ->where('group_id = %i', $group_id)
                    ->execute();

                //
                // Update users level, reset to USER
                //
				dibi::update(Tables::USERS_TABLE, ['user_level' => USER, 'user_acp_password' => ''])
                    ->where('user_id = %i', $user_id)
                    ->execute();
			}

			$message = $lang['Auth_updated'] . '<br /><br />' . sprintf($lang['Click_return_userauth'], '<a href="' . Session::appendSid("admin_ug_auth.php?mode=$mode") . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');
		} else {
			$change_mod_list = isset($_POST['moderator']) ? $_POST['moderator'] : [];

			if (empty($adv)) {
                $forum_access = dibi::select('f.* ')
                    ->from(Tables::FORUMS_TABLE)
                    ->as('f')
                    ->innerJoin(Tables::CATEGORIES_TABLE)
                    ->as('c')
                    ->on('f.cat_id = c.cat_id')
                    ->orderBy('c.cat_order', dibi::ASC)
                    ->orderBy('f.forum_order', dibi::ASC)
                    ->fetchAll();

				$forum_auth_level_fields = [];

                foreach ($forum_access as $access) {
					$forum_id = $access->forum_id;

					foreach ($forum_auth_fields as $field) {
						$forum_auth_level_fields[$forum_id][$field] = $access->{$field} === AUTH_ACL;
					}
				}

                foreach ($_POST['private'] as $forum_id => $value) {
					foreach ($forum_auth_level_fields[$forum_id] as $auth_field => $exists) {
						if ($exists) {
							$change_acl_list[$forum_id][$auth_field] = $value;
						}
					}
				}
			} else {
				$change_acl_list = [];

                foreach ($forum_auth_fields as $field) {
					foreach ($_POST['private_' . $field] as $forum_id => $value) {
						$change_acl_list[$forum_id][$field] = $value;
					}
				}
			}

            $forum_access = dibi::select('f.*')
                ->from(Tables::FORUMS_TABLE)
                ->as('f')
                ->innerJoin(Tables::CATEGORIES_TABLE)
                ->as('c')
                ->on('f.cat_id = c.cat_id')
                ->orderBy('c.cat_order')
                ->orderBy('f.forum_order')
                ->fetchAssoc('forum_id');

            if ($mode === 'user') {
                $auth_access = dibi::select('aa.* ')
                    ->from(Tables::AUTH_ACCESS_TABLE)
                    ->as('aa')
                    ->innerJoin(Tables::USERS_GROUPS_TABLE)
                    ->as('ug')
                    ->on('aa.group_id = ug.group_id')
                    ->innerJoin(Tables::GROUPS_TABLE)
                    ->as('g')
                    ->on('g.group_id = ug.group_id')
                    ->where('ug.user_id = %i', $user_id)
                    ->where('g.group_single_user = %i', 1)
                    ->fetchAll();
            } else {
                $auth_access = dibi::select('*')
                    ->from(Tables::AUTH_ACCESS_TABLE)
                    ->where('group_id = %i', $group_id)
                    ->fetchAssoc('forum_id');
            }

			$forum_auth_action = [];
			$update_acl_status = [];
			$update_mod_status = [];

			foreach ($forum_access as $access) {
				$forum_id = $access->forum_id;

				if (
					( isset($auth_access[$forum_id]['auth_mod']) && $change_mod_list[$forum_id] !== $auth_access[$forum_id]['auth_mod'] ) ||
					( !isset($auth_access[$forum_id]['auth_mod']) && !empty($change_mod_list[$forum_id]) ) 
				) {
					$update_mod_status[$forum_id] = $change_mod_list[$forum_id];

                    if (!$update_mod_status[$forum_id]) {
                        $forum_auth_action[$forum_id] = 'delete';
                    } elseif (!isset($auth_access[$forum_id]['auth_mod'])) {
                        $forum_auth_action[$forum_id] = 'insert';
                    } else {
                        $forum_auth_action[$forum_id] = 'update';
                    }
				}

				foreach ($forum_auth_fields as $auth_field) {
					if ($access->{$auth_field} === AUTH_ACL && isset($change_acl_list[$forum_id][$auth_field])) {
						if (( empty($auth_access[$forum_id]['auth_mod']) &&
							( isset($auth_access[$forum_id][$auth_field]) && $change_acl_list[$forum_id][$auth_field] !== $auth_access[$forum_id][$auth_field] ) ||
							( !isset($auth_access[$forum_id][$auth_field]) && !empty($change_acl_list[$forum_id][$auth_field]) ) ) ||
							!empty($update_mod_status[$forum_id])
						) {
							$update_acl_status[$forum_id][$auth_field] = !empty($update_mod_status[$forum_id]) ? 0 : $change_acl_list[$forum_id][$auth_field];

                            if (isset($auth_access[$forum_id][$auth_field]) && empty($update_acl_status[$forum_id][$auth_field]) && $forum_auth_action[$forum_id] !== 'insert' && $forum_auth_action[$forum_id] !== 'update') {$forum_auth_action[$forum_id] = 'delete';
                            } elseif (!isset($auth_access[$forum_id][$auth_field]) && !($forum_auth_action[$forum_id] === 'delete' && empty($update_acl_status[$forum_id][$auth_field]))) {
                                $forum_auth_action[$forum_id] = 'insert';
                            } elseif (isset($auth_access[$forum_id][$auth_field]) && !empty($update_acl_status[$forum_id][$auth_field])) {
                                $forum_auth_action[$forum_id] = 'update';
                            }
						} elseif (( empty($auth_access[$forum_id]['auth_mod']) &&
							( isset($auth_access[$forum_id][$auth_field]) && $change_acl_list[$forum_id][$auth_field] === $auth_access[$forum_id][$auth_field] ) ) && $forum_auth_action[$forum_id] === 'delete'
                       ) {
							$forum_auth_action[$forum_id] = 'update';
						}
					}
				}
			}

			//
			// Checks complete, make updates to DB
			//
			$delete_sql = [];

            foreach ($forum_auth_action as $forum_id => $action) {
                if ($action === 'delete') {
                    $delete_sql[] = $forum_id;
                } elseif ($action === 'insert') {
                    $update_acl_status[$forum_id]['auth_mod'] = isset($update_mod_status[$forum_id]) ? $update_mod_status[$forum_id] : 0;
                    $update_acl_status[$forum_id]['forum_id'] = $forum_id;
                    $update_acl_status[$forum_id]['group_id'] = $group_id;

                    dibi::insert(Tables::AUTH_ACCESS_TABLE, $update_acl_status[$forum_id])
                        ->execute();
                } else {
                    $update_acl_status[$forum_id]['auth_mod'] = isset($update_mod_status[$forum_id]) ? $update_mod_status[$forum_id] : 0;

                    dibi::update(Tables::AUTH_ACCESS_TABLE, $update_acl_status[$forum_id])
                        ->where('group_id = %i', $group_id)
                        ->where('forum_id = %i', $forum_id)
                        ->execute();
                }
            }

            if (count($delete_sql)) {
                dibi::delete(Tables::AUTH_ACCESS_TABLE)
                    ->where('group_id = %i', $group_id)
                    ->where('forum_id IN %in', $delete_sql)
                    ->execute();
			}

			$l_auth_return = ( $mode === 'user' ) ? $lang['Click_return_userauth'] : $lang['Click_return_groupauth'];
			$message = $lang['Auth_updated'] . '<br /><br />' . sprintf($l_auth_return, '<a href="' . Session::appendSid("admin_ug_auth.php?mode=$mode") . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');
		}

		//
		// Update user level to mod for appropriate users
		//
        $set_mod = dibi::select('u.user_id')
            ->from(Tables::AUTH_ACCESS_TABLE)
            ->as('aa')
            ->innerJoin(Tables::USERS_GROUPS_TABLE)
            ->as('ug')
            ->on('ug.group_id = aa.group_id')
            ->innerJoin(Tables::USERS_TABLE)
            ->as('u')
            ->on('u.user_id = ug.user_id')
            ->where('ug.user_pending = %i', 0)
            ->where('u.user_level NOT IN %in', [MOD, ADMIN])
            ->groupBy('u.user_id')
            ->having('SUM(aa.auth_mod) > %i', 0)
            ->fetchPairs(null, 'user_id');

		//
		// Update user level to user for appropriate users
		//

        // TODO
        switch (Config::DBMS) {
			case 'postgresql':
				$sql = 'SELECT u.user_id 
					FROM ' . Tables::USERS_TABLE . ' u, ' . Tables::USERS_GROUPS_TABLE . ' ug, ' . Tables::AUTH_ACCESS_TABLE . ' aa
					WHERE ug.user_id = u.user_id 
						AND aa.group_id = ug.group_id 
						AND u.user_level NOT IN (' . USER . ', ' . ADMIN . ')
					GROUP BY u.user_id 
					HAVING SUM(aa.auth_mod) = 0 
					UNION (
						SELECT u.user_id  
						FROM ' . Tables::USERS_TABLE . ' u 
						WHERE NOT EXISTS ( 
							SELECT aa.auth_mod 
							FROM ' . Tables::USERS_GROUPS_TABLE . ' ug, ' . Tables::AUTH_ACCESS_TABLE . ' aa 
							WHERE ug.user_id = u.user_id 
								AND aa.group_id = ug.group_id
						)
						AND u.user_level NOT IN (' . USER . ', ' . ADMIN . ')  
						GROUP BY u.user_id
					)';

				dibi::query($sql)->fetchPairs(null, 'user_id');
				break;
			case 'oracle':
				$sql = 'SELECT u.user_id 
					FROM ' . Tables::USERS_TABLE . ' u, ' . Tables::USERS_GROUPS_TABLE . ' ug, ' . Tables::AUTH_ACCESS_TABLE . ' aa 
					WHERE ug.user_id = u.user_id(+)
						AND aa.group_id = ug.group_id(+) 
						AND u.user_level NOT IN (' . USER . ', ' . ADMIN . ')
					GROUP BY u.user_id 
					HAVING SUM(aa.auth_mod) = 0';

                dibi::query($sql)->fetchPairs(null, 'user_id');
				break;
			default:
			    // there was left join
                $unset_mod = dibi::select('u.user_id')
                    ->from(Tables::USERS_TABLE)
                    ->as('u')
                    ->leftJoin(Tables::USERS_GROUPS_TABLE)
                    ->as('ug')
                    ->on('ug.user_id = u.user_id')
                    ->leftJoin(Tables::AUTH_ACCESS_TABLE)
                    ->as('aa')
                    ->on('aa.group_id = ug.group_id')
                    ->where('u.user_level NOT IN %in', [USER, ADMIN])
                    ->groupBy('u.user_id')
                    ->having('SUM(aa.auth_mod) = %i', 0)
                    ->fetchPairs(null, 'user_id');
				break;
		}

        if (count($set_mod)) {
            dibi::update(Tables::USERS_TABLE, ['user_level' => MOD])
                ->where('user_id IN %in', $set_mod)
                ->execute();
		}

		if (count($unset_mod)) {
		    dibi::update(Tables::USERS_TABLE, ['user_level' => USER])
                ->where('user_id IN %in', $unset_mod)
                ->execute();
		}

        $group_user = dibi::select('user_id')
            ->from(Tables::USERS_GROUPS_TABLE)
            ->where('group_id = %i', $group_id)
            ->fetchPairs('user_id', 'user_id');

		$rows = dibi::select('ug.user_id')
            ->select('COUNT(auth_mod)')
            ->as('is_auth_mod')
            ->from(Tables::AUTH_ACCESS_TABLE)
            ->as('aa')
            ->innerJoin(Tables::USERS_GROUPS_TABLE)
            ->as('ug')
            ->on('aa.group_id = ug.group_id')
            ->where('ug.user_id IN %in', $group_user)
            ->where('aa.auth_mod = %i', 1)
            ->groupBy('ug.user_id')
            ->fetchPairs(null, 'user_id');

		foreach ($rows as $row) {
            if ($row->is_auth_mod) {
                unset($group_user->{$row->user_id});
            }
        }

		if (count($group_user)) {
		    dibi::update(Tables::USERS_TABLE, ['user_level' => USER])
                ->where('user_id IN %in', $group_user)
                ->where('user_level = %i', MOD)
                ->execute();
		}

		message_die(GENERAL_MESSAGE, $message);
	}
} elseif (($mode === 'user' && (isset($_POST['username']) || $user_id)) || ($mode === 'group' && $group_id)) {
    if (isset($_POST['username'])) {
		$this_userdata = get_userdata($_POST['username'], true);

        if (!$this_userdata) {
			message_die(GENERAL_MESSAGE, $lang['No_such_user']);
		}

		$user_id = $this_userdata->user_id;
	}

	//
	// Front end
	//
    $forum_access = dibi::select('f.*')
        ->from(Tables::FORUMS_TABLE)
        ->as('f')
        ->innerJoin(Tables::CATEGORIES_TABLE)
        ->as('c')
        ->on('f.cat_id = c.cat_id')
        ->orderBy('c.cat_order', dibi::ASC)
        ->orderBy('f.forum_order', dibi::ASC)
        ->fetchAll();

	if (empty($adv)) {
        $forum_auth_level = [];

		foreach ($forum_access as $access) {
			$forum_id = $access->forum_id;

			$forum_auth_level[$forum_id] = AUTH_ALL;

			foreach ($forum_auth_fields as $forum_auth_field) {
                if ($access->{$forum_auth_field} === AUTH_ACL) {
					$forum_auth_level[$forum_id] = AUTH_ACL;
					$forum_auth_level_fields[$forum_id][] = $forum_auth_field;
				}
			}
		}
	}

    $ug_info = dibi::select(['u.user_id', 'u.username', 'u.user_level', 'g.group_id', 'g.group_name', 'g.group_single_user', 'ug.user_pending'])
        ->from(Tables::USERS_TABLE)
        ->as('u')
        ->from(Tables::GROUPS_TABLE)
        ->as('g')
        ->from(Tables::USERS_GROUPS_TABLE)
        ->as('ug');

    if ($mode === 'user') {
	    $ug_info->where('u.user_id = %i', $user_id)
            ->where('ug.user_id = u.user_id')
            ->where('g.group_id = ug.group_id');
    } else {
        $ug_info->where('g.group_id = %i', $group_id)
            ->where('ug.group_id = g.group_id')
            ->where('u.user_id = ug.user_id');
    }

    $ug_info = $ug_info->fetchAll();

    if ($mode === 'user') {
        $rows = dibi::select(['aa.*', 'g.group_single_user'])
            ->from(Tables::AUTH_ACCESS_TABLE)
            ->as('aa')
            ->innerJoin(Tables::USERS_GROUPS_TABLE)
            ->as('ug')
            ->on('aa.group_id = ug.group_id')
            ->innerJoin(Tables::GROUPS_TABLE)
            ->as('g')
            ->on('g.group_id = ug.group_id')
            ->where('ug.user_id = %i', $user_id)
            ->where('g.group_single_user = %i', 1)
            ->fetchAll();
    } else {
        $rows = dibi::select('*')
            ->from(Tables::AUTH_ACCESS_TABLE)
            ->where('group_id = %i', $group_id)
            ->fetchAll();
    }

	$auth_access = [];
	$auth_access_count = [];

	// init $auth_access_count
    foreach ($rows as $row) {
        $auth_access_count[$row->forum_id] = 0;
    }

    foreach ($rows as $row) {
        $auth_access[$row->forum_id][] = $row;
        $auth_access_count[$row->forum_id]++;
    }

    if ($mode === 'user') {
        if ($ug_info[0]->user_level === ADMIN && $ug_info[0]->user_id !== ANONYMOUS) {
            $is_admin = 1;
        } else {
            $is_admin = 0;
        }
    } else {
        $is_admin = 0;
    }

    $auth_ug = [];
    $auth_field_acl = [];

	foreach ($forum_access as $forum_access_value) {
		$forum_id = $forum_access_value['forum_id'];

		unset($prev_acl_setting);

		foreach ($forum_auth_fields as $forum_auth_field) {
			$key = $forum_auth_field;
			$value = $forum_access_value[$key];

			switch( $value) {
				case AUTH_ALL:
				case AUTH_REG:
					$auth_ug[$forum_id][$key] = 1;
					break;

				case AUTH_ACL:
					$auth_ug[$forum_id][$key] = !empty($auth_access_count[$forum_id]) ? Auth::auth_check_user(AUTH_ACL, $key, $auth_access[$forum_id], $is_admin) : 0;
					$auth_field_acl[$forum_id][$key] = $auth_ug[$forum_id][$key];

                    if (isset($prev_acl_setting) && $prev_acl_setting !== $auth_ug[$forum_id][$key] && empty($adv)) {
                        $adv = 1;
                    }

					$prev_acl_setting = $auth_ug[$forum_id][$key];

					break;

				case AUTH_MOD:
					$auth_ug[$forum_id][$key] = !empty($auth_access_count[$forum_id]) ? Auth::auth_check_user(AUTH_MOD,
                        $key, $auth_access[$forum_id], $is_admin) : 0;
					break;

				case AUTH_ADMIN:
					$auth_ug[$forum_id][$key] = $is_admin;
					break;

				default:
					$auth_ug[$forum_id][$key] = 0;
					break;
			}
		}

		//
		// Is user a moderator?
		//
		$auth_ug[$forum_id]['auth_mod'] = !empty($auth_access_count[$forum_id]) ? Auth::auth_check_user(AUTH_MOD, 'auth_mod', $auth_access[$forum_id], 0) : 0;
	}

    $optionlist_acl_adv = [];

	$i = 0;

    foreach ($auth_ug as $forum_id => $user_ary) {
        if (empty($adv)) {
            if ($forum_auth_level[$forum_id] === AUTH_ACL) {
                $allowed = 1;

                foreach ($forum_auth_level_fields[$forum_id] as $j => $auth_level_field) {
                    if (!$auth_ug[$forum_id][$auth_level_field]) {
                        $allowed = 0;

                        break;
                    }
                }

				$optionlist_acl = '<select name="private[' . $forum_id . ']">';

                if ($is_admin || $user_ary['auth_mod']) {
                    $optionlist_acl .= '<option value="1">' . $lang['Allowed_Access'] . '</option>';
                } elseif ($allowed) {
                    $optionlist_acl .= '<option value="1" selected="selected">' . $lang['Allowed_Access'] . '</option><option value="0">' . $lang['Disallowed_Access'] . '</option>';
                } else {
                    $optionlist_acl .= '<option value="1">' . $lang['Allowed_Access'] . '</option><option value="0" selected="selected">' . $lang['Disallowed_Access'] . '</option>';
                }

				$optionlist_acl .= '</select>';
            } else {
                $optionlist_acl = '&nbsp;';
            }
		} else {
            foreach ($forum_access as $forum_access_value) {
                if ($forum_access_value['forum_id'] === $forum_id) {
                    foreach ($forum_auth_fields as $k => $field_name) {

                        if ($forum_access_value[$field_name] === AUTH_ACL) {
                            $optionlist_acl_adv[$forum_id][$k] = '<select name="private_' . $field_name . '[' . $forum_id . ']">';

                            if (isset($auth_field_acl[$forum_id][$field_name]) && !($is_admin || $user_ary['auth_mod'])) {
                                if ($auth_field_acl[$forum_id][$field_name]) {
                                    $optionlist_acl_adv[$forum_id][$k] .= '<option value="1" selected="selected">' . $lang['ON'] . '</option><option value="0">' . $lang['OFF'] . '</option>';
                                } else {
                                    $optionlist_acl_adv[$forum_id][$k] .= '<option value="1">' . $lang['ON'] . '</option><option value="0" selected="selected">' . $lang['OFF'] . '</option>';
                                }
                            } else {
                                if ($is_admin || $user_ary['auth_mod']) {
                                    $optionlist_acl_adv[$forum_id][$k] .= '<option value="1">' . $lang['ON'] . '</option>';
                                } else {
                                    $optionlist_acl_adv[$forum_id][$k] .= '<option value="1">' . $lang['ON'] . '</option><option value="0" selected="selected">' . $lang['OFF'] . '</option>';
                                }
                            }

                            $optionlist_acl_adv[$forum_id][$k] .= '</select>';
                        }
                    }
                }
            }
        }

		$optionlist_mod = '<select name="moderator[' . $forum_id . ']">';
		$optionlist_mod .= $user_ary['auth_mod'] ? '<option value="1" selected="selected">' . $lang['Is_Moderator'] . '</option><option value="0">' . $lang['Not_Moderator'] . '</option>' : '<option value="1">' . $lang['Is_Moderator'] . '</option><option value="0" selected="selected">' . $lang['Not_Moderator'] . '</option>';
		$optionlist_mod .= '</select>';

        $row_class = !($i % 2) ? $theme['td_class1'] : $theme['td_class2'];
        $row_color = !($i % 2) ? $theme['td_color1'] : $theme['td_color2'];

        $template->assignBlockVars('forums',
            [
            'ROW_COLOR'  => '#' . $row_color,
            'ROW_CLASS'  => $row_class,
            'FORUM_NAME' => htmlspecialchars($forum_access[$i]['forum_name'], ENT_QUOTES),

            'U_FORUM_AUTH' => Session::appendSid('admin_forumauth.php?f=' . $forum_access[$i]['forum_id']),

            'S_MOD_SELECT' => $optionlist_mod
            ]
        );

        if (!$adv) {
            $template->assignBlockVars('forums.aclvalues',
                [
                    'S_ACL_SELECT' => $optionlist_acl
                ]
            );
        } else {
            foreach ($optionlist_acl_adv[$forum_id] as $acl_select) {
                $template->assignBlockVars('forums.aclvalues',
                    [
                        'S_ACL_SELECT' => $acl_select
                    ]
                );
            }
        }

        $i++;
    }
//	@reset($auth_user);

    if ($mode === 'user') {
        $t_username  = $ug_info[0]->username;
        $s_user_type = $is_admin ? '<select name="userlevel"><option value="admin" selected="selected">' . $lang['Auth_Admin'] . '</option><option value="user">' . $lang['Auth_User'] . '</option></select>' : '<select name="userlevel"><option value="admin">' . $lang['Auth_Admin'] . '</option><option value="user" selected="selected">' . $lang['Auth_User'] . '</option></select>';
    } else {
        $t_groupname = htmlspecialchars($ug_info[0]->group_name, ENT_QUOTES);
    }

	$names = [];

    foreach ($ug_info as $i => $ug_info_value) {
        if (($mode === 'user' && !$ug_info_value->group_single_user) || $mode === 'group') {
            // TODO IF $mode is user.. put group? :O :O
            // check it
            if ($mode === 'user') {
                $names[$i] = ['id' => (int)$ug_info_value->group_id , 'name' => $ug_info_value->group_name];
            } else {
                $names[$i] = ['id' => (int)$ug_info_value->user_id , 'name' => $ug_info_value->username];
            }
        }
    }

	$t_usergroup_list = $t_pending_list = '';

    // TODO i hope we can do it in one foreach!
    if (count($names)) {
        foreach ($ug_info as $i => $ug_info_value) {
            $ug = ($mode === 'user') ? 'group&amp;' . POST_GROUPS_URL : 'user&amp;' . POST_USERS_URL;

            if (!$ug_info_value->user_pending) {
                $t_usergroup_list .= (($t_usergroup_list !== '') ? ', ' : '') . '<a href="' . Session::appendSid("admin_ug_auth.php?mode=$ug=" . $names[$i]['id']) . '">' . htmlspecialchars($names[$i]['name'], ENT_QUOTES) . '</a>';
            } else {
                $t_pending_list .= (($t_pending_list !== '') ? ', ' : '') . '<a href="' . Session::appendSid("admin_ug_auth.php?mode=$ug=" . $names[$i]['id']) . '">' . htmlspecialchars($names[$i]['name'], ENT_QUOTES) . '</a>';
            }
        }
    }

	$t_usergroup_list = $t_usergroup_list === '' ? $lang['None'] : $t_usergroup_list;
	$t_pending_list   = $t_pending_list === ''   ? $lang['None'] : $t_pending_list;

	$s_column_span = 2; // Two columns always present

    if (!$adv) {
        $template->assignBlockVars('acltype', ['L_UG_ACL_TYPE' => $lang['Simple_Permission']]);
        $s_column_span++;
    } else {
        foreach ($forum_auth_fields as $forum_auth_field) {
            $cell_title = $field_names[$forum_auth_field];

            $template->assignBlockVars('acltype', ['L_UG_ACL_TYPE' => $cell_title]);
            $s_column_span++;
        }
    }

    //
    // Dump in the page header ...
	//
    require_once '.' . $sep . 'page_header_admin.php';

    $template->setFileNames(['body' => 'admin/auth_ug_body.tpl']);

    $adv_switch = empty($adv) ? 1 : 0;
	$u_ug_switch = $mode === 'user' ? POST_USERS_URL . '=' . $user_id : POST_GROUPS_URL . '=' . $group_id;
	$switch_mode = Session::appendSid("admin_ug_auth.php?mode=$mode&amp;" . $u_ug_switch . "&amp;adv=$adv_switch");
	$switch_mode_text = empty($adv) ? $lang['Advanced_mode'] : $lang['Simple_mode'];
	$u_switch_mode = '<a href="' . $switch_mode . '">' . $switch_mode_text . '</a>';

	$s_hidden_fields = '<input type="hidden" name="mode" value="' . $mode . '" /><input type="hidden" name="adv" value="' . $adv . '" />';
	$s_hidden_fields .= ( $mode === 'user' ) ? '<input type="hidden" name="' . POST_USERS_URL . '" value="' . $user_id . '" />' : '<input type="hidden" name="' . POST_GROUPS_URL . '" value="' . $group_id . '" />';

    if ($mode === 'user') {
        $template->assignBlockVars('switch_user_auth', []);

        $template->assignVars(
            [
                'USERNAME'               => $t_username,
                'USER_LEVEL'             => $lang['User_Level'] . ' : ' . $s_user_type,
                'USER_GROUP_MEMBERSHIPS' => $lang['Group_memberships'] . ' : ' . $t_usergroup_list
            ]
        );
    } else {
        $template->assignBlockVars('switch_group_auth', []);

        $template->assignVars(
            [
                'USERNAME'         => $t_groupname,
                'GROUP_MEMBERSHIP' => $lang['Usergroup_members'] . ' : ' . $t_usergroup_list . '<br />' . $lang['Pending_members'] . ' : ' . $t_pending_list
            ]
        );
    }

    $template->assignVars(
        [
            'L_USER_OR_GROUPNAME' => $mode === 'user' ? $lang['Username']          : $lang['Group_name'],
            'L_AUTH_TITLE'        => $mode === 'user' ? $lang['Auth_Control_User'] : $lang['Auth_Control_Group'],
            'L_AUTH_EXPLAIN'      => $mode === 'user' ? $lang['User_auth_explain'] : $lang['Group_auth_explain'],

            'L_MODERATOR_STATUS' => $lang['Moderator_status'],
            'L_PERMISSIONS'      => $lang['Permissions'],
            'L_SUBMIT'           => $lang['Submit'],
            'L_RESET'            => $lang['Reset'],
            'L_FORUM'            => $lang['Forum'],

            'U_USER_OR_GROUP' => Session::appendSid('admin_ug_auth.php'),
            'U_SWITCH_MODE'   => $u_switch_mode,

            'S_COLUMN_SPAN'   => $s_column_span,
            'S_AUTH_ACTION'   => Session::appendSid('admin_ug_auth.php'),
            'S_HIDDEN_FIELDS' => $s_hidden_fields
        ]
    );
} else {
	//
	// Select a user/group
	//
    require_once '.' . $sep . 'page_header_admin.php';

    $template->setFileNames(['body' => ($mode === 'user') ? 'admin/user_select_body.tpl' : 'admin/auth_select_body.tpl']);

    if ($mode === 'user') {
        $template->assignVars(
            [
                'L_FIND_USERNAME' => $lang['Find_username'],

                'U_SEARCH_USER' => Session::appendSid('../search.php?mode=searchuser')
            ]
        );
    } else {
        $template->assignVars(['S_AUTH_SELECT' => Select::groups()]);
    }

    $s_hidden_fields = '<input type="hidden" name="mode" value="' . $mode . '" />';

	$l_type = $mode === 'user' ? 'USER' : 'AUTH';

    $template->assignVars(
        [
            'L_' . $l_type . '_TITLE'   => $mode === 'user' ? $lang['Auth_Control_User'] : $lang['Auth_Control_Group'],
            'L_' . $l_type . '_EXPLAIN' => $mode === 'user' ? $lang['User_auth_explain'] : $lang['Group_auth_explain'],
            'L_' . $l_type . '_SELECT'  => $mode === 'user' ? $lang['Select_a_User']     : $lang['Select_a_Group'],
            'L_LOOK_UP'                 => $mode === 'user' ? $lang['Look_up_User']      : $lang['Look_up_Group'],

            'S_HIDDEN_FIELDS'          => $s_hidden_fields,
            'S_' . $l_type . '_ACTION' => Session::appendSid('admin_ug_auth.php')
        ]
    );
}

$template->pparse('body');

require_once '.' . $sep . 'page_footer_admin.php';

?>