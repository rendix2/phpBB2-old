<?php
/***************************************************************************
 *                               groupcp.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: groupcp.php 8357 2008-02-01 11:59:05Z Kellanved $
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

define('IN_PHPBB', true);
$phpbb_root_path = './';

include $phpbb_root_path . 'common.php';

// -------------------------
//
function generate_user_info(&$row, $date_format, $group_mod, &$from, &$posts, &$joined, &$poster_avatar, &$profile_img, &$profile, &$search_img, &$search, &$pm_img, &$pm, &$email_img, &$email, &$www_img, &$www, &$icq_status_img, &$icq_img, &$icq, &$aim_img, &$aim, &$msn_img, &$msn, &$yim_img, &$yim)
{
	global $lang, $images, $board_config;

	$from = !empty($row->user_from) ? $row->user_from : '&nbsp;';
	$joined = create_date($date_format, $row->user_regdate, $board_config['board_timezone']);
	$posts = $row->user_posts ? $row->user_posts : 0;
	$poster_avatar = '';

    /**
     * TODO this never be true
     * add this columns into selects
     */
	if ($row->user_avatar_type && $row->user_id !== ANONYMOUS && $row->user_allowavatar) {
		switch( $row->user_avatar_type) {
			case USER_AVATAR_UPLOAD:
				$poster_avatar = $board_config['allow_avatar_upload'] ? '<img src="' . $board_config['avatar_path'] . '/' . $row->user_avatar . '" alt="" border="0" />' : '';
				break;
			case USER_AVATAR_REMOTE:
				$poster_avatar = $board_config['allow_avatar_remote'] ? '<img src="' . $row->user_avatar . '" alt="" border="0" />' : '';
				break;
			case USER_AVATAR_GALLERY:
				$poster_avatar = $board_config['allow_avatar_local'] ? '<img src="' . $board_config['avatar_gallery_path'] . '/' . $row->user_avatar . '" alt="" border="0" />' : '';
				break;
		}
	}

	if (!empty($row->user_viewemail) || $group_mod ) {
		$email_uri = $board_config['board_email_form'] ? Session::appendSid('profile.php?mode=email&amp;' . POST_USERS_URL .'=' . $row->user_id) : 'mailto:' . $row->user_email;

		$email_img = '<a href="' . $email_uri . '"><img src="' . $images['icon_email'] . '" alt="' . $lang['Send_email'] . '" title="' . $lang['Send_email'] . '" border="0" /></a>';
		$email = '<a href="' . $email_uri . '">' . $lang['Send_email'] . '</a>';
	}  else {
		$email_img = '&nbsp;';
		$email = '&nbsp;';
	}

	$temp_url = Session::appendSid('profile.php?mode=viewprofile&amp;' . POST_USERS_URL . '=' . $row->user_id);
	$profile_img = '<a href="' . $temp_url . '"><img src="' . $images['icon_profile'] . '" alt="' . $lang['Read_profile'] . '" title="' . $lang['Read_profile'] . '" border="0" /></a>';
	$profile = '<a href="' . $temp_url . '">' . $lang['Read_profile'] . '</a>';

	$temp_url = Session::appendSid('privmsg.php?mode=post&amp;' . POST_USERS_URL . '=' . $row->user_id);
	$pm_img = '<a href="' . $temp_url . '"><img src="' . $images['icon_pm'] . '" alt="' . $lang['Send_private_message'] . '" title="' . $lang['Send_private_message'] . '" border="0" /></a>';
	$pm = '<a href="' . $temp_url . '">' . $lang['Send_private_message'] . '</a>';

	$www_img = $row->user_website ? '<a href="' . $row->user_website . '" target="_userwww"><img src="' . $images['icon_www'] . '" alt="' . $lang['Visit_website'] . '" title="' . $lang['Visit_website'] . '" border="0" /></a>' : '';
	$www = $row->user_website ? '<a href="' . $row->user_website . '" target="_userwww">' . $lang['Visit_website']
	 . '</a>' : '';

	if (!empty($row->user_icq)) {
		$icq_status_img = '<a href="http://wwp.icq.com/' . $row->user_icq . '#pager"><img src="http://web.icq.com/whitepages/online?icq=' . $row->user_icq . '&img=5" width="18" height="18" border="0" /></a>';
		$icq_img = '<a href="http://wwp.icq.com/scripts/search.dll?to=' . $row->user_icq . '"><img src="' . $images['icon_icq'] . '" alt="' . $lang['ICQ'] . '" title="' . $lang['ICQ'] . '" border="0" /></a>';
		$icq =  '<a href="http://wwp.icq.com/scripts/search.dll?to=' . $row->user_icq . '">' . $lang['ICQ'] . '</a>';
	} else {
		$icq_status_img = '';
		$icq_img = '';
		$icq = '';
	}

	$aim_img = $row->user_aim ? '<a href="aim:goim?screenname=' . $row->user_aim . '&amp;message=Hello+Are+you+there?"><img src="' . $images['icon_aim'] . '" alt="' . $lang['AIM'] . '" title="' . $lang['AIM'] . '" border="0" /></a>' : '';
	$aim = $row->user_aim ? '<a href="aim:goim?screenname=' . $row->user_aim . '&amp;message=Hello+Are+you+there?">' . $lang['AIM'] . '</a>' : '';

	$temp_url = Session::appendSid('profile.php?mode=viewprofile&amp;' . POST_USERS_URL . '=' . $row->user_id);
	$msn_img = $row->user_msnm ? '<a href="' . $temp_url . '"><img src="' . $images['icon_msnm'] . '" alt="' . $lang['MSNM'] . '" title="' . $lang['MSNM'] . '" border="0" /></a>' : '';
	$msn = $row->user_msnm ? '<a href="' . $temp_url . '">' . $lang['MSNM'] . '</a>' : '';

	$yim_img = $row->user_yim ? '<a href="http://edit.yahoo.com/config/send_webmesg?.target=' . $row->user_yim . '&amp;.src=pg"><img src="' . $images['icon_yim'] . '" alt="' . $lang['YIM'] . '" title="' . $lang['YIM'] . '" border="0" /></a>' : '';
	$yim = $row->user_yim ? '<a href="http://edit.yahoo.com/config/send_webmesg?.target=' . $row->user_yim . '&amp;.src=pg">' . $lang['YIM'] . '</a>' : '';

	$temp_url = Session::appendSid('search.php?search_author=' . urlencode($row->username) . '&amp;showresults=posts');
	$search_img = '<a href="' . $temp_url . '"><img src="' . $images['icon_search'] . '" alt="' . sprintf($lang['Search_user_posts'], $row->username) . '" title="' . sprintf($lang['Search_user_posts'], $row->username) . '" border="0" /></a>';
	$search = '<a href="' . $temp_url . '">' . sprintf($lang['Search_user_posts'], $row->username) . '</a>';
}
//
// --------------------------

//
// Start session management
//
$userdata = Session::pageStart($user_ip, PAGE_GROUPCP);
init_userprefs($userdata);
//
// End session management
//

$script_name = preg_replace('/^\/?(.*?)\/?$/', "\\1", trim($board_config['script_path']));
$script_name = $script_name !== '' ? $script_name . '/groupcp.php' : 'groupcp.php';
$server_name = trim($board_config['server_name']);
$server_protocol = $board_config['cookie_secure'] ? 'https://' : 'http://';
$server_port = $board_config['server_port'] !== 80 ? ':' . trim($board_config['server_port']) . '/' : '/';

$server_url = $server_protocol . $server_name . $server_port . $script_name;

if (isset($_GET[POST_GROUPS_URL]) || isset($_POST[POST_GROUPS_URL])) {
    $group_id = isset($_POST[POST_GROUPS_URL]) ? (int)$_POST[POST_GROUPS_URL] : (int)$_GET[POST_GROUPS_URL];
} else {
    $group_id = '';
}

if (isset($_POST[POST_MODE]) || isset($_GET[POST_MODE])) {
    $mode = isset($_POST[POST_MODE]) ? $_POST[POST_MODE] : $_GET[POST_MODE];
    $mode = htmlspecialchars($mode);
} else {
    $mode = '';
}

$confirm = isset($_POST['confirm']) ? true : 0;
$cancel = isset($_POST['cancel']) ? true : 0;
$sid = isset($_POST['sid']) ? $_POST['sid'] : '';
$start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
$start = $start < 0 ? 0 : $start;

//
// Default var values
//
$is_moderator = false;

if (isset($_POST['groupstatus']) && $group_id) {
    if (!$userdata['session_logged_in']) {
        redirect(Session::appendSid('login.php?redirect=groupcp.php&' . POST_GROUPS_URL . "=$group_id", true));
    }

    $group_moderator = dibi::select('group_moderator')
        ->from(GROUPS_TABLE)
        ->where('group_id = %i', $group_id)
        ->fetchSingle();

    if ($group_moderator !== $userdata['user_id'] && $userdata['user_level'] !== ADMIN) {
        $template->assignVars(
            [
                'META' => '<meta http-equiv="refresh" content="3;url=' . Session::appendSid('index.php') . '">'
            ]
        );

        $message = $lang['Not_group_moderator'] . '<br /><br />' . sprintf($lang['Click_return_group'], '<a href="' . Session::appendSid('groupcp.php?' . POST_GROUPS_URL . "=$group_id") . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_index'], '<a href="' . Session::appendSid('index.php') . '">', '</a>');

		message_die(GENERAL_MESSAGE, $message);
	}

	dibi::update(GROUPS_TABLE, ['group_type' => (int)$_POST['group_type'] ])
        ->where('group_id = %i', $group_id)
        ->execute();

    $template->assignVars(
        [
            'META' => '<meta http-equiv="refresh" content="3;url=' . Session::appendSid('groupcp.php?' . POST_GROUPS_URL . "=$group_id") . '">'
        ]
    );

    $message = $lang['Group_type_updated'] . '<br /><br />' . sprintf($lang['Click_return_group'], '<a href="' . Session::appendSid('groupcp.php?' . POST_GROUPS_URL . "=$group_id") . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_index'], '<a href="' . Session::appendSid('index.php') . '">', '</a>');

	message_die(GENERAL_MESSAGE, $message);

} elseif (isset($_POST['joingroup']) && $group_id) {
	//
	// First, joining a group
	// If the user isn't logged in redirect them to login
	//
    if (!$userdata['session_logged_in']) {
        redirect(Session::appendSid('login.php?redirect=groupcp.php&' . POST_GROUPS_URL . "=$group_id", true));
    } elseif ($sid !== $userdata['session_id']) {
        message_die(GENERAL_ERROR, $lang['Session_invalid']);
    }

    $rows = dibi::select(['ug.user_id', 'g.group_type'])
        ->from(USER_GROUP_TABLE)
        ->as('ug')
        ->from(GROUPS_TABLE)
        ->as('g')
        ->where('g.group_id = %i', $group_id)
        ->where('g.group_type <> %i', GROUP_HIDDEN)
        ->where('ug.group_id = g.group_id')
        ->fetchAll();

	if (!count($rows)) {
        message_die(GENERAL_MESSAGE, $lang['No_groups_exist']);
    }

    if ($row[0]->group_type === GROUP_OPEN) {
        foreach ($rows as $row) {
            if ($userdata['user_id'] === $row->user_id) {
                $template->assignVars(
                    [
                        'META' => '<meta http-equiv="refresh" content="3;url=' . Session::appendSid('index.php') . '">'
                    ]
                );

                $message = $lang['Already_member_group'] . '<br /><br />' . sprintf($lang['Click_return_group'], '<a href="' . Session::appendSid('groupcp.php?' . POST_GROUPS_URL . "=$group_id") . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_index'], '<a href="' . Session::appendSid('index.php') . '">', '</a>');

                message_die(GENERAL_MESSAGE, $message);
            }
        }
    } else {
        $template->assignVars(
            [
                'META' => '<meta http-equiv="refresh" content="3;url=' . Session::appendSid('index.php') . '">'
            ]
        );

        $message = $lang['This_closed_group'] . '<br /><br />' . sprintf($lang['Click_return_group'], '<a href="' . Session::appendSid('groupcp.php?' . POST_GROUPS_URL . "=$group_id") . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_index'], '<a href="' . Session::appendSid('index.php') . '">', '</a>');

        message_die(GENERAL_MESSAGE, $message);
    }

	$insert_data = [
	   'group_id' => $group_id,
        'user_id' => $userdata['user_id'],
        'user_pending' => 1
    ];

	dibi::insert(USER_GROUP_TABLE, $insert_data)->execute();

    $moderator = dibi::select(['u.user_email', 'u.username', 'u.user_lang', 'g.group_name'])
        ->from(USERS_TABLE)
        ->as('u')
        ->innerJoin(GROUPS_TABLE)
        ->as('g')
        ->on('u.user_id = g.group_moderator ')
        ->where('g.group_id = %i', $group_id)
        ->fetch();

	include $phpbb_root_path . 'includes/Emailer.php';
	$emailer = new Emailer($board_config['smtp_delivery']);

	$emailer->setFrom($board_config['board_email']);
	$emailer->setReplyTo($board_config['board_email']);

	$emailer->use_template('group_request', $moderator->user_lang);
	$emailer->setEmailAddress($moderator->user_email);
	$emailer->setSubject($lang['Group_request']);

	$emailer->assignVars(
	    [
            'SITENAME' => $board_config['sitename'],
            'GROUP_MODERATOR' => $moderator->username,
            'EMAIL_SIG' => !empty($board_config['board_email_sig']) ? str_replace('<br />', "\n", "-- \n" . $board_config['board_email_sig']) : '',

            'U_GROUPCP' => $server_url . '?' . POST_GROUPS_URL . "=$group_id&validate=true"
        ]
	);
	$emailer->send();
	$emailer->reset();

    $template->assignVars(
        [
            'META' => '<meta http-equiv="refresh" content="3;url=' . Session::appendSid('index.php') . '">'
        ]
    );

    $message = $lang['Group_joined'] . '<br /><br />' . sprintf($lang['Click_return_group'], '<a href="' . Session::appendSid('groupcp.php?' . POST_GROUPS_URL . "=$group_id") . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_index'], '<a href="' . Session::appendSid('index.php') . '">', '</a>');

	message_die(GENERAL_MESSAGE, $message);
} elseif (isset($_POST['unsub']) || isset($_POST['unsubpending']) && $group_id) {
	//
	// Second, unsubscribing from a group
	// Check for confirmation of unsub.
	//
    if ($cancel) {
        redirect(Session::appendSid('groupcp.php', true));
    } elseif (!$userdata['session_logged_in']) {
        redirect(Session::appendSid('login.php?redirect=groupcp.php&' . POST_GROUPS_URL . "=$group_id", true));
    } elseif ($sid !== $userdata['session_id']) {
        message_die(GENERAL_ERROR, $lang['Session_invalid']);
    }

	if ($confirm) {
	    dibi::delete(USER_GROUP_TABLE)
            ->where('user_id = %i', $userdata['user_id'])
            ->where('group_id = %i', $group_id)
            ->execute();

        if ($userdata['user_level'] !== ADMIN && $userdata['user_level'] === MOD) {
            $is_auth_mod = dibi::select('COUNT(auth_mod)')
                ->as('is_auth_mod')
                ->from(AUTH_ACCESS_TABLE)
                ->as('aa')
                ->innerJoin(USER_GROUP_TABLE)
                ->as('ug')
                ->on('aa.group_id = ug.group_id')
                ->where('ug.user_id = %i', $userdata['user_id'])
                ->where('aa.auth_mod = %i', 1)
                ->fetchSingle();

            if (!$is_auth_mod) {
                dibi::update(USERS_TABLE, ['user_level' => USER])
                    ->where('user_id = %i', $userdata['user_id'])
                    ->execute();
            }
        }

        $template->assignVars(
            [
                'META' => '<meta http-equiv="refresh" content="3;url=' . Session::appendSid('index.php') . '">'
            ]
        );

        $message = $lang['Unsub_success'] . '<br /><br />' . sprintf($lang['Click_return_group'], '<a href="' . Session::appendSid('groupcp.php?' . POST_GROUPS_URL . "=$group_id") . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_index'], '<a href="' . Session::appendSid('index.php') . '">', '</a>');

		message_die(GENERAL_MESSAGE, $message);
	} else {
		$unsub_msg = isset($_POST['unsub']) ? $lang['Confirm_unsub'] : $lang['Confirm_unsub_pending'];

		$s_hidden_fields = '<input type="hidden" name="' . POST_GROUPS_URL . '" value="' . $group_id . '" /><input type="hidden" name="unsub" value="1" />';
		$s_hidden_fields .= '<input type="hidden" name="sid" value="' . $userdata['session_id'] . '" />';

		$page_title = $lang['Group_Control_Panel'];
		include $phpbb_root_path . 'includes/page_header.php';

        $template->setFileNames(['confirm' => 'confirm_body.tpl']);

        $template->assignVars(
            [
                'MESSAGE_TITLE'    => $lang['Confirm'],
                'MESSAGE_TEXT'     => $unsub_msg,
                'L_YES'            => $lang['Yes'],
                'L_NO'             => $lang['No'],
                'S_CONFIRM_ACTION' => Session::appendSid('groupcp.php'),
                'S_HIDDEN_FIELDS'  => $s_hidden_fields
            ]
        );

        $template->pparse('confirm');

		include $phpbb_root_path . 'includes/page_tail.php';
	}

} elseif ($group_id) {
	//
	// Did the group moderator get here through an email?
	// If so, check to see if they are logged in.
	//
    if (isset($_GET['validate'])) {
        if (!$userdata['session_logged_in']) {
            redirect(Session::appendSid('login.php?redirect=groupcp.php&' . POST_GROUPS_URL . "=$group_id", true));
        }
    }

	//
	// For security, get the ID of the group moderator.
	//
	switch($dbms) {
		case 'postgresql':
            $group_info = dibi::query('SELECT g.group_moderator, g.group_type, aa.auth_mod 
				FROM " . GROUPS_TABLE . " g, " . AUTH_ACCESS_TABLE . " aa 
				WHERE g.group_id = %i
					AND aa.group_id = g.group_id 
					UNION (
						SELECT g.group_moderator, g.group_type, NULL 
						FROM " . GROUPS_TABLE . " g
						WHERE g.group_id = %i
							AND NOT EXISTS (
							SELECT aa.group_id 
							FROM " . AUTH_ACCESS_TABLE . " aa 
							WHERE aa.group_id = g.group_id  
						)
					)
				ORDER BY auth_mod DESC', $group_id, $group_id)
                ->fetch();
			break;

		case 'oracle':
            $group_info = dibi::select(['g.group_moderator', 'g.group_type', 'aa.auth_mod'])
                ->from(GROUPS_TABLE)
                ->as('g')
                ->innerJoin(AUTH_ACCESS_TABLE)
                ->as('aa')
                ->on('aa.group_id (+) = g.group_id')
                ->where('g.group_id = %i', $group_id)
                ->orderBy('aa.auth_mod', dibi::DESC)
                ->fetch();
			break;

		default:
		    // there was left join
            $group_info = dibi::select(['g.group_moderator', 'g.group_type', 'aa.auth_mod'])
                ->from(GROUPS_TABLE)
                ->as('g')
                ->innerJoin(AUTH_ACCESS_TABLE)
                ->as('aa')
                ->on('aa.group_id = g.group_id')
                ->where('g.group_id = %i', $group_id)
                ->orderBy('aa.auth_mod', dibi::DESC)
                ->fetch();
			break;
	}

    if ($group_info) {
		$group_moderator = $group_info->group_moderator;

        if ($group_moderator === $userdata['user_id'] || $userdata['user_level'] === ADMIN) {
            $is_moderator = true;
        }
			
		//
		// Handle Additions, removals, approvals and denials
        //
        if (!empty($_POST['add']) || !empty($_POST['remove']) || isset($_POST['approve']) || isset($_POST['deny'])) {
            if (!$userdata['session_logged_in']) {
                redirect(Session::appendSid('login.php?redirect=groupcp.php&' . POST_GROUPS_URL . "=$group_id", true));
            } elseif ($sid !== $userdata['session_id']) {
                message_die(GENERAL_ERROR, $lang['Session_invalid']);
            }

            if (!$is_moderator) {
                $template->assignVars(
                    [
                        'META' => '<meta http-equiv="refresh" content="3;url=' . Session::appendSid('index.php') . '">'
                    ]
                );

                $message = $lang['Not_group_moderator'] . '<br /><br />' . sprintf($lang['Click_return_index'], '<a href="' . Session::appendSid('index.php') . '">', '</a>');

				message_die(GENERAL_MESSAGE, $message);
			}

            if (isset($_POST['add'])) {
				$username = isset($_POST['username']) ? phpbb_clean_username($_POST['username']) : '';

				$row = dibi::select(['user_id', 'user_email', 'user_lang', 'user_level'])
                    ->from(USERS_TABLE)
                    ->where('username = %s', $username)
                    ->fetch();

                if (!$row) {
                    $template->assignVars(
                        [
                            'META' => '<meta http-equiv="refresh" content="3;url=' . Session::appendSid('groupcp.php?' . POST_GROUPS_URL . "=$group_id") . '">'
                        ]
                    );

                    $message = $lang['Could_not_add_user'] . '<br /><br />' . sprintf($lang['Click_return_group'], '<a href="' . Session::appendSid('groupcp.php?' . POST_GROUPS_URL . "=$group_id") . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_index'], '<a href="' . Session::appendSid('index.php') . '">', '</a>');

					message_die(GENERAL_MESSAGE, $message);
				}

                if ($row->user_id === ANONYMOUS) {
                    $template->assignVars(
                        [
                            'META' => '<meta http-equiv="refresh" content="3;url=' . Session::appendSid('groupcp.php?' . POST_GROUPS_URL . "=$group_id") . '">'

                        ]);

                    $message = $lang['Could_not_anon_user'] . '<br /><br />' . sprintf($lang['Click_return_group'], '<a href="' . Session::appendSid('groupcp.php?' . POST_GROUPS_URL . "=$group_id") . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_index'], '<a href="' . Session::appendSid('index.php') . '">', '</a>');

					message_die(GENERAL_MESSAGE, $message);
				}

				$member = dibi::select(['ug.user_id', 'u.user_level'])
                    ->from(USER_GROUP_TABLE)
                    ->as('ug')
                    ->innerJoin(USERS_TABLE)
                    ->as('u')
                    ->on('ug.user_id = u.user_id')
                    ->where('u.user_id = %i', $row->user_id)
                    ->where('ug.group_id = %i', $group_id)
                    ->fetch();

				if ($member) {
                    $template->assignVars(
                        [
                            'META' => '<meta http-equiv="refresh" content="3;url=' . Session::appendSid('groupcp.php?' . POST_GROUPS_URL . "=$group_id") . '">'
                        ]
                    );

                    $message = $lang['User_is_member_group'] . '<br /><br />' . sprintf($lang['Click_return_group'], '<a href="' . Session::appendSid('groupcp.php?' . POST_GROUPS_URL . "=$group_id") . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_index'], '<a href="' . Session::appendSid('index.php') . '">', '</a>');

                    message_die(GENERAL_MESSAGE, $message);
                } else {
				    $insert_data = [
				        'user_id' => $row['user_id'],
                        'group_id' =>$group_id,
                        'user_pending' => 0
                    ];

				    dibi::insert(USER_GROUP_TABLE, $insert_data)->execute();

                    if ($row->user_level !== ADMIN && $row->user_level !== MOD && $group_info->auth_mod) {
                        dibi::update(USERS_TABLE, ['user_level' => MOD])
                            ->where('user_id = %i', $row->user_id)
                            ->execute();
                    }

					//
					// Get the group name
					// Email the user and tell them they're in the group
					//
                    $group_name =  dibi::select('group_name')
                        ->from(GROUPS_TABLE)
                        ->where('group_id = %i', $group_id)
                        ->fetchSingle();

					// TODO group not exists
                    if (!$group_name) {
                        message_die(GENERAL_ERROR, 'Could not get group information');
                    }

					include $phpbb_root_path . 'includes/Emailer.php';
					$emailer = new Emailer($board_config['smtp_delivery']);

					$emailer->setFrom($board_config['board_email']);
					$emailer->setReplyTo($board_config['board_email']);

					$emailer->use_template('group_added', $row->user_lang);
					$emailer->setEmailAddress($row->user_email);
					$emailer->setSubject($lang['Group_added']);

                    $emailer->assignVars(
                        [
                            'SITENAME'   => $board_config['sitename'],
                            'GROUP_NAME' => $group_name,
                            'EMAIL_SIG'  => !empty($board_config['board_email_sig']) ? str_replace('<br />', "\n", "-- \n" . $board_config['board_email_sig']) : '',

                            'U_GROUPCP' => $server_url . '?' . POST_GROUPS_URL . "=$group_id"
                        ]
                    );
                    $emailer->send();
					$emailer->reset();
				}
			} else {
				if (( ( isset($_POST['approve']) || isset($_POST['deny']) ) && isset($_POST['pending_members']) ) || ( isset($_POST['remove']) && isset($_POST['members']) )) {
					$members = isset($_POST['approve']) || isset($_POST['deny']) ? $_POST['pending_members'] : $_POST['members'];

                    if (isset($_POST['approve'])) {
                        if ($group_info->auth_mod) {
						    dibi::update(USERS_TABLE, ['user_level' => MOD])
                                ->where('user_id IN %in', $members)
                                ->where('user_level NOT IN %in', [MOD, ADMIN])
                                ->execute();
						}

						dibi::update(USER_GROUP_TABLE, ['user_pending' => 0])
                            ->where('user_id IN %in', $members)
                            ->where('group_id = %i', $group_id)
                            ->execute();

                    } elseif (isset($_POST['deny']) || isset($_POST['remove'])) {
                        if ($group_info->auth_mod) {
                            $user_ids = dibi::select(['ug.user_id'])
                                ->from(AUTH_ACCESS_TABLE)
                                ->as('aa')
                                ->innerJoin(USER_GROUP_TABLE)
                                ->as('ug')
                                ->on('aa.group_id = ug.group_id')
                                ->where('ug.user_id IN %in', $members)
                                ->where('aa.auth_mod = %i', 1)
                                ->groupBy('ug.user_id')
                                ->groupBy('ug.group_id')
                                ->orderBy('ug.user_id')
                                ->orderBy('ug.group_id')
                                ->fetchPairs(null, 'user_id');

                            if (count($user_ids)) {
                                dibi::update(USERS_TABLE, ['user_level' => USER])
                                    ->where('user_id IN %in', $user_ids)
                                    ->where('user_level <> %i', ADMIN)
                                    ->execute();
                            }
						}

						dibi::delete(USER_GROUP_TABLE)
                            ->where('user_id IN %in', $members)
                            ->where('group_id = %i', $group_id)
                            ->execute();
					}

					//
					// Email users when they are approved
					//
                    if (isset($_POST['approve'])) {
                        $bcc_list = dibi::select('user_email')
                            ->from(USERS_TABLE)
                            ->where('user_id IN %in', $members)
                            ->fetchPairs(null, 'user_email');

						//
						// Get the group name
						//
						$group_name = dibi::select('group_name')
                            ->from(GROUPS_TABLE)
                            ->where('group_id = %i', $group_id)
                            ->fetchSingle();

						if ($group_name === false) {
							message_die(GENERAL_ERROR, 'Could not get group information');
						}

						include $phpbb_root_path . 'includes/Emailer.php';
						$emailer = new Emailer($board_config['smtp_delivery']);

						$emailer->setFrom($board_config['board_email']);
						$emailer->setReplyTo($board_config['board_email']);

						foreach($bcc_list as $bcc_value) {
							$emailer->addBcc($bcc_value);
						}

						$emailer->use_template('group_approved');
						$emailer->setSubject($lang['Group_approved']);

                        $emailer->assignVars(
                            [
                                'SITENAME'   => $board_config['sitename'],
                                'GROUP_NAME' => $group_name,
                                'EMAIL_SIG'  => !empty($board_config['board_email_sig']) ? str_replace('<br />', "\n", "-- \n" . $board_config['board_email_sig']) : '',

                                'U_GROUPCP' => $server_url . '?' . POST_GROUPS_URL . "=$group_id"
                            ]
                        );
                        $emailer->send();
						$emailer->reset();
					}
				}
			}
		}
		//
		// END approve or deny
		//
	} else {
		message_die(GENERAL_MESSAGE, $lang['No_groups_exist']);
	}

	//
	// Get group details
	//
    $group_info = dibi::select('*')
        ->from(GROUPS_TABLE)
        ->where('group_id = %i', $group_id)
        ->where('group_single_user = %i', 0)
        ->fetch();

    if (!$group_info) {
        message_die(GENERAL_MESSAGE, $lang['Group_not_exist']);
    }

    $columns = [
        'username',
        'user_id',
        'user_viewemail',
        'user_posts',
        'user_regdate',
        'user_from',
        'user_website',
        'user_email',
        'user_icq',
        'user_aim',
        'user_yim',
        'user_msnm'
    ];

    //
    // Get moderator details for this group
    //
    $group_moderator = dibi::select($columns)
        ->from(USERS_TABLE)
        ->where('user_id = %i', $group_info->group_moderator)
        ->fetch();

    $columns = [
        'u.username',
        'u.user_id',
        'u.user_viewemail',
        'u.user_posts',
        'u.user_regdate',
        'u.user_from',
        'u.user_website',
        'u.user_email',
        'u.user_icq',
        'u.user_aim',
        'u.user_yim',
        'u.user_msnm'
    ];

    //
    // Get user information for this group
    //
    $group_members = dibi::select($columns)
        ->from(USERS_TABLE)
        ->as('u')
        ->innerJoin(USER_GROUP_TABLE)
        ->as('ug')
        ->on('u.user_id = ug.user_id')
        ->where('ug.group_id = %i', $group_id)
        ->where('ug.user_pending = %i', 0)
        ->where('ug.user_id <> %i', $group_moderator->user_id)
        ->orderBy('u.username')
        ->fetchAll();

    $members_count = count($group_members);

	$columns = [
	    'u.username',
        'u.user_id',
        'u.user_viewemail',
        'u.user_posts',
        'u.user_regdate',
        'u.user_from',
        'u.user_website',
        'u.user_email',
        'u.user_icq',
        'u.user_aim',
        'u.user_yim',
        'u.user_msnm'
    ];

    $modgroup_pending_list = dibi::select($columns)
        ->from(GROUPS_TABLE)
        ->as('g')
        ->innerJoin(USER_GROUP_TABLE)
        ->as('ug')
        ->on('g.group_id = ug.group_id')
        ->innerJoin(USERS_TABLE)
        ->as('u')
        ->on('u.user_id = ug.user_id')
        ->where('g.group_id = %i', $group_id)
        ->where('ug.user_pending = %i', 1)
        ->orderBy('u.username')
        ->fetchAll();

    $modgroup_pending_count = count($modgroup_pending_list);

	$is_group_member = 0;

    foreach ($group_members as $group_member) {
        if ($group_member->user_id === $userdata['user_id'] && $userdata['session_logged_in']) {
            $is_group_member = true;
        }
    }

	$is_group_pending_member = 0;

    foreach ($modgroup_pending_list as $modgroup_pending_value) {
        if ($modgroup_pending_value->user_id === $userdata['user_id'] && $userdata['session_logged_in']) {
            $is_group_pending_member = true;
        }
    }

    if ($userdata['user_level'] === ADMIN) {
        $is_moderator = true;
    }

    if ($userdata['user_id'] === $group_info->group_moderator) {
        $is_moderator = true;

        $group_details = $lang['Are_group_moderator'];

        $s_hidden_fields = '<input type="hidden" name="' . POST_GROUPS_URL . '" value="' . $group_id . '" />';
    } elseif ($is_group_member || $is_group_pending_member) {
        $template->assignBlockVars('switch_unsubscribe_group_input', []);

        $group_details = $is_group_pending_member ? $lang['Pending_this_group'] : $lang['Member_this_group'];

        $s_hidden_fields = '<input type="hidden" name="' . POST_GROUPS_URL . '" value="' . $group_id . '" />';
    } elseif ($userdata['user_id'] === ANONYMOUS) {
        $group_details   = $lang['Login_to_join'];
        $s_hidden_fields = '';
    } else {
        if ($group_info->group_type === GROUP_OPEN) {
            $template->assignBlockVars('switch_subscribe_group_input', []);

            $group_details   = $lang['This_open_group'];
            $s_hidden_fields = '<input type="hidden" name="' . POST_GROUPS_URL . '" value="' . $group_id . '" />';
        } elseif ($group_info->group_type === GROUP_CLOSED) {
            $group_details   = $lang['This_closed_group'];
            $s_hidden_fields = '';
        } elseif ($group_info->group_type === GROUP_HIDDEN) {
            $group_details   = $lang['This_hidden_group'];
            $s_hidden_fields = '';
        }
    }

	$page_title = $lang['Group_Control_Panel'];
	include $phpbb_root_path . 'includes/page_header.php';

	//
	// Load templates
	//
    $template->setFileNames(['info' => 'groupcp_info_body.tpl', 'pendinginfo' => 'groupcp_pending_info.tpl']);
    make_jumpbox('viewforum.php');

	//
	// Add the moderator
	//
	$username = $group_moderator->username;
	$user_id = $group_moderator->user_id;

	generate_user_info($group_moderator, $board_config['default_dateformat'], $is_moderator, $from, $posts, $joined, $poster_avatar, $profile_img, $profile, $search_img, $search, $pm_img, $pm, $email_img, $email, $www_img, $www, $icq_status_img, $icq_img, $icq, $aim_img, $aim, $msn_img, $msn, $yim_img, $yim);

	$s_hidden_fields .= '<input type="hidden" name="sid" value="' . $userdata['session_id'] . '" />';

	$template->assignVars([
            'L_GROUP_INFORMATION' => $lang['Group_Information'],
            'L_GROUP_NAME' => $lang['Group_name'],
            'L_GROUP_DESC' => $lang['Group_description'],
            'L_GROUP_TYPE' => $lang['Group_type'],
            'L_GROUP_MEMBERSHIP' => $lang['Group_membership'],
            'L_SUBSCRIBE' => $lang['Subscribe'],
            'L_UNSUBSCRIBE' => $lang['Unsubscribe'],
            'L_JOIN_GROUP' => $lang['Join_group'],
            'L_UNSUBSCRIBE_GROUP' => $lang['Unsubscribe'],
            'L_GROUP_OPEN' => $lang['Group_open'],
            'L_GROUP_CLOSED' => $lang['Group_closed'],
            'L_GROUP_HIDDEN' => $lang['Group_hidden'],
            'L_UPDATE' => $lang['Update'],
            'L_GROUP_MODERATOR' => $lang['Group_Moderator'],
            'L_GROUP_MEMBERS' => $lang['Group_Members'],
            'L_PENDING_MEMBERS' => $lang['Pending_members'],
            'L_SELECT_SORT_METHOD' => $lang['Select_sort_method'],
            'L_PM' => $lang['Private_Message'],
            'L_EMAIL' => $lang['Email'],
            'L_POSTS' => $lang['Posts'],
            'L_WEBSITE' => $lang['Website'],
            'L_FROM' => $lang['Location'],
            'L_ORDER' => $lang['Order'],
            'L_SORT' => $lang['Sort'],
            'L_SUBMIT' => $lang['Sort'],
            'L_AIM' => $lang['AIM'],
            'L_YIM' => $lang['YIM'],
            'L_MSNM' => $lang['MSNM'],
            'L_ICQ' => $lang['ICQ'],
            'L_SELECT' => $lang['Select'],
            'L_REMOVE_SELECTED' => $lang['Remove_selected'],
            'L_ADD_MEMBER' => $lang['Add_member'],
            'L_FIND_USERNAME' => $lang['Find_username'],

            'GROUP_NAME' => $group_info->group_name,
            'GROUP_DESC' => $group_info->group_description,
            'GROUP_DETAILS' => $group_details,

            'MOD_ROW_COLOR' => '#' . $theme['td_color1'],
            'MOD_ROW_CLASS' => $theme['td_class1'],
            'MOD_USERNAME' => $username,
            'MOD_FROM' => $from,
            'MOD_JOINED' => $joined,
            'MOD_POSTS' => $posts,
            'MOD_AVATAR_IMG' => $poster_avatar,
            'MOD_PROFILE_IMG' => $profile_img,
            'MOD_PROFILE' => $profile,
            'MOD_SEARCH_IMG' => $search_img,
            'MOD_SEARCH' => $search,
            'MOD_PM_IMG' => $pm_img,
            'MOD_PM' => $pm,
            'MOD_EMAIL_IMG' => $email_img,
            'MOD_EMAIL' => $email,
            'MOD_WWW_IMG' => $www_img,
            'MOD_WWW' => $www,
            'MOD_ICQ_STATUS_IMG' => $icq_status_img,
            'MOD_ICQ_IMG' => $icq_img,
            'MOD_ICQ' => $icq,
            'MOD_AIM_IMG' => $aim_img,
            'MOD_AIM' => $aim,
            'MOD_MSN_IMG' => $msn_img,
            'MOD_MSN' => $msn,
            'MOD_YIM_IMG' => $yim_img,
            'MOD_YIM' => $yim,

            'U_MOD_VIEWPROFILE' => Session::appendSid('profile.php?mode=viewprofile&amp;' . POST_USERS_URL . "=$user_id"),
            'U_SEARCH_USER' => Session::appendSid('search.php?mode=searchuser'),

            'S_GROUP_OPEN_TYPE' => GROUP_OPEN,
            'S_GROUP_CLOSED_TYPE' => GROUP_CLOSED,
            'S_GROUP_HIDDEN_TYPE' => GROUP_HIDDEN,
            'S_GROUP_OPEN_CHECKED' => $group_info->group_type === GROUP_OPEN ? ' checked="checked"' : '',
            'S_GROUP_CLOSED_CHECKED' => $group_info->group_type === GROUP_CLOSED ? ' checked="checked"' : '',
            'S_GROUP_HIDDEN_CHECKED' => $group_info->group_type === GROUP_HIDDEN ? ' checked="checked"' : '',
            'S_HIDDEN_FIELDS' => $s_hidden_fields,
            'S_MODE_SELECT' => $select_sort_mode,
            'S_ORDER_SELECT' => $select_sort_order,
            'S_GROUPCP_ACTION' => Session::appendSid('groupcp.php?' . POST_GROUPS_URL . "=$group_id")]
	);

	//
	// Dump out the remaining users
	//

    $min = min($board_config['topics_per_page'] + $start, $members_count);

    for ($i = $start; $i < $min; $i++) {
		$username = $group_members[$i]['username'];
		$user_id = $group_members[$i]['user_id'];

		generate_user_info($group_members[$i], $board_config['default_dateformat'], $is_moderator, $from, $posts, $joined, $poster_avatar, $profile_img, $profile, $search_img, $search, $pm_img, $pm, $email_img, $email, $www_img, $www, $icq_status_img, $icq_img, $icq, $aim_img, $aim, $msn_img, $msn, $yim_img, $yim);

		if ($group_info->group_type !== GROUP_HIDDEN || $is_group_member || $is_moderator) {
			$row_color = !($i % 2) ? $theme['td_color1'] : $theme['td_color2'];
			$row_class = !($i % 2) ? $theme['td_class1'] : $theme['td_class2'];

            $template->assignBlockVars('member_row',
                [
                    'ROW_COLOR'      => '#' . $row_color,
                    'ROW_CLASS'      => $row_class,
                    'USERNAME'       => $username,
                    'FROM'           => $from,
                    'JOINED'         => $joined,
                    'POSTS'          => $posts,
                    'USER_ID'        => $user_id,
                    'AVATAR_IMG'     => $poster_avatar,
                    'PROFILE_IMG'    => $profile_img,
                    'PROFILE'        => $profile,
                    'SEARCH_IMG'     => $search_img,
                    'SEARCH'         => $search,
                    'PM_IMG'         => $pm_img,
                    'PM'             => $pm,
                    'EMAIL_IMG'      => $email_img,
                    'EMAIL'          => $email,
                    'WWW_IMG'        => $www_img,
                    'WWW'            => $www,
                    'ICQ_STATUS_IMG' => $icq_status_img,
                    'ICQ_IMG'        => $icq_img,
                    'ICQ'            => $icq,
                    'AIM_IMG'        => $aim_img,
                    'AIM'            => $aim,
                    'MSN_IMG'        => $msn_img,
                    'MSN'            => $msn,
                    'YIM_IMG'        => $yim_img,
                    'YIM'            => $yim,

                    'U_VIEWPROFILE' => Session::appendSid('profile.php?mode=viewprofile&amp;' . POST_USERS_URL . "=$user_id")
                ]
            );

            if ($is_moderator) {
                $template->assignBlockVars('member_row.switch_mod_option', []);
            }
		}
	}

    if (!$members_count) {
        //
        // No group members
        //
        $template->assignBlockVars('switch_no_members', []);
        $template->assignVars(
            [
                'L_NO_MEMBERS' => $lang['No_group_members']
            ]
        );
    }

    $current_page = $members_count ? ceil($members_count / $board_config['topics_per_page']) : 1;

    $template->assignVars(
        [
            'PAGINATION'  => generate_pagination('groupcp.php?' . POST_GROUPS_URL . "=$group_id", $members_count, $board_config['topics_per_page'], $start),
            'PAGE_NUMBER' => sprintf($lang['Page_of'], floor($start / $board_config['topics_per_page']) + 1, $current_page),

            'L_GOTO_PAGE' => $lang['Goto_page']
        ]
    );

    if ($group_info->group_type === GROUP_HIDDEN && !$is_group_member && !$is_moderator) {
		//
		// No group members
		//
        $template->assignBlockVars('switch_hidden_group', []);
        $template->assignVars(
            [
                'L_HIDDEN_MEMBERS' => $lang['Group_hidden_members']
            ]
        );
    }

    //
	// We've displayed the members who belong to the group, now we 
	// do that pending memebers... 
	//
    if ($is_moderator) {
		//
		// Users pending in ONLY THIS GROUP (which is moderated by this user)
		//
        if ($modgroup_pending_count) {
            foreach ($modgroup_pending_list as $modgroup_pending_value) {
				$username = $modgroup_pending_value->username;
				$user_id = $modgroup_pending_value->user_id;

				generate_user_info($modgroup_pending_value, $board_config['default_dateformat'], $is_moderator,
                    $from, $posts, $joined, $poster_avatar, $profile_img, $profile, $search_img, $search, $pm_img, $pm, $email_img, $email, $www_img, $www, $icq_status_img, $icq_img, $icq, $aim_img, $aim, $msn_img, $msn, $yim_img, $yim);

				$row_color = !($i % 2) ? $theme['td_color1'] : $theme['td_color2'];
				$row_class = !($i % 2) ? $theme['td_class1'] : $theme['td_class2'];

				$user_select = '<input type="checkbox" name="member[]" value="' . $user_id . '">';

                $template->assignBlockVars('pending_members_row',
                    [
                        'ROW_CLASS'      => $row_class,
                        'ROW_COLOR'      => '#' . $row_color,
                        'USERNAME'       => $username,
                        'FROM'           => $from,
                        'JOINED'         => $joined,
                        'POSTS'          => $posts,
                        'USER_ID'        => $user_id,
                        'AVATAR_IMG'     => $poster_avatar,
                        'PROFILE_IMG'    => $profile_img,
                        'PROFILE'        => $profile,
                        'SEARCH_IMG'     => $search_img,
                        'SEARCH'         => $search,
                        'PM_IMG'         => $pm_img,
                        'PM'             => $pm,
                        'EMAIL_IMG'      => $email_img,
                        'EMAIL'          => $email,
                        'WWW_IMG'        => $www_img,
                        'WWW'            => $www,
                        'ICQ_STATUS_IMG' => $icq_status_img,
                        'ICQ_IMG'        => $icq_img,
                        'ICQ'            => $icq,
                        'AIM_IMG'        => $aim_img,
                        'AIM'            => $aim,
                        'MSN_IMG'        => $msn_img,
                        'MSN'            => $msn,
                        'YIM_IMG'        => $yim_img,
                        'YIM'            => $yim,

                        'U_VIEWPROFILE' => Session::appendSid('profile.php?mode=viewprofile&amp;' . POST_USERS_URL . "=$user_id")
                    ]
                );
            }

			$template->assignBlockVars('switch_pending_members', [] );

            $template->assignVars(
                [
                    'L_SELECT'           => $lang['Select'],
                    'L_APPROVE_SELECTED' => $lang['Approve_selected'],
                    'L_DENY_SELECTED'    => $lang['Deny_selected']
                ]
            );

            $template->assignVarFromHandle('PENDING_USER_BOX', 'pendinginfo');
		
		}
	}

    if ($is_moderator) {
        $template->assignBlockVars('switch_mod_option', []);
        $template->assignBlockVars('switch_add_member', []);
    }

	$template->pparse('info');
} else {
	//
	// Show the main groupcp.php screen where the user can select a group.
	//
	// Select all group that the user is a member of or where the user has
	// a pending membership.
	//
	$in_group = [];

    if ($userdata['session_logged_in']) {
	    $rows = dibi::select(['g.group_id', 'g.group_name', 'g.group_type', 'ug.user_pending'])
            ->from(GROUPS_TABLE)
            ->as('g')
            ->innerJoin(USER_GROUP_TABLE)
            ->as('ug')
            ->on('ug.group_id = g.group_id')
            ->where('ug.user_id = %i', $userdata['user_id'])
            ->where('g.group_single_user <>', 1)
            ->orderBy('g.group_name')
            ->orderBy('ug.user_id')
            ->fetchAll();

        $in_group = [];
        $s_member_groups_opt = '';
        $s_pending_groups_opt = '';

        foreach ($rows as $row) {
            $in_group[] = $row->group_id;

            if ($row->user_pending) {
                $s_pending_groups_opt .= '<option value="' . $row->group_id . '">' . $row->group_name . '</option>';
            } else {
                $s_member_groups_opt .= '<option value="' . $row->group_id . '">' . $row->group_name . '</option>';
            }
        }

        $s_pending_groups = '<select name="' . POST_GROUPS_URL . '">' . $s_pending_groups_opt . '</select>';
        $s_member_groups = '<select name="' . POST_GROUPS_URL . '">' . $s_member_groups_opt . '</select>';
	}

	//
	// Select all other groups i.e. groups that this user is not a member of
	//

    $group_rows = dibi::select(['group_id', 'group_name', 'group_type'])
        ->from(GROUPS_TABLE)
        ->where('group_single_user <> %i', 1);

    if (count($in_group)) {
        $group_rows->where('group_id NOT IN %in', $in_group);
    }

    $group_rows = $group_rows->orderBy('group_name')
        ->fetchAll();

	$s_group_list_opt = '';

    foreach ($group_rows as $group_row) {
        if ($group_row->group_type !== GROUP_HIDDEN || $userdata['user_level'] === ADMIN) {
            $s_group_list_opt .= '<option value="' . $group_row->group_id . '">' . $group_row->group_name . '</option>';
        }
    }
	$s_group_list = '<select name="' . POST_GROUPS_URL . '">' . $s_group_list_opt . '</select>';

    if ($s_group_list_opt !== '' || $s_pending_groups_opt !== '' || $s_member_groups_opt !== '') {
		//
		// Load and process templates
		//
		$page_title = $lang['Group_Control_Panel'];
		include $phpbb_root_path . 'includes/page_header.php';

        $template->setFileNames(['user' => 'groupcp_user_body.tpl']);
        make_jumpbox('viewforum.php');

        if ($s_pending_groups_opt !== '' || $s_member_groups_opt !== '') {
            $template->assignBlockVars('switch_groups_joined', []);
        }

        if ($s_member_groups_opt !== '') {
            $template->assignBlockVars('switch_groups_joined.switch_groups_member', []);
        }

        if ($s_pending_groups_opt !== '') {
            $template->assignBlockVars('switch_groups_joined.switch_groups_pending', []);
        }

        if ($s_group_list_opt !== '') {
            $template->assignBlockVars('switch_groups_remaining', []);
        }

		$s_hidden_fields = '<input type="hidden" name="sid" value="' . $userdata['session_id'] . '" />';

        $template->assignVars(
            [
                'L_GROUP_MEMBERSHIP_DETAILS' => $lang['Group_member_details'],
                'L_JOIN_A_GROUP'             => $lang['Group_member_join'],
                'L_YOU_BELONG_GROUPS'        => $lang['Current_memberships'],
                'L_SELECT_A_GROUP'           => $lang['Non_member_groups'],
                'L_PENDING_GROUPS'           => $lang['Memberships_pending'],
                'L_SUBSCRIBE'                => $lang['Subscribe'],
                'L_UNSUBSCRIBE'              => $lang['Unsubscribe'],
                'L_VIEW_INFORMATION'         => $lang['View_Information'],

                'S_USERGROUP_ACTION' => Session::appendSid('groupcp.php'),
                'S_HIDDEN_FIELDS'    => $s_hidden_fields,

                'GROUP_LIST_SELECT'    => $s_group_list,
                'GROUP_PENDING_SELECT' => $s_pending_groups,
                'GROUP_MEMBER_SELECT'  => $s_member_groups
            ]
        );

        $template->pparse('user');
	} else{
	    bdump('c');
		message_die(GENERAL_MESSAGE, $lang['No_groups_exist']);
	}
}

include $phpbb_root_path . 'includes/page_tail.php';

?>