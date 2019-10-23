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

$sep = DIRECTORY_SEPARATOR;
$phpbb_root_path = '.' . $sep;

require_once $phpbb_root_path . 'common.php';

// -------------------------
//
function generate_user_info(
    &$row,
    $dateFormat,
    $group_mod,
    &$from,
    &$posts,
    &$topics,
    &$joined,
    &$posterAvatar,
    &$profileImage,
    &$profile,
    &$searchImage,
    &$search,
    &$pmImage,
    &$pm,
    &$emailImage,
    &$email,
    &$wwwImage,
    &$www
) {
	global $lang, $images, $board_config;

	$from = !empty($row->user_from) ? htmlspecialchars($row->user_from, ENT_QUOTES) : '&nbsp;';
	$joined = create_date($dateFormat, $row->user_regdate, $board_config['board_timezone']);
	$posts = $row->user_posts ? $row->user_posts : 0;
	$topics = $row->user_topics ? $row->user_topics : 0;
	$posterAvatar = '';

    /**
     * TODO this never be true
     * add this columns into selects
     */
	if ($row->user_avatar_type && $row->user_id !== ANONYMOUS && $row->user_allowavatar) {
		switch( $row->user_avatar_type) {
			case USER_AVATAR_UPLOAD:
				$posterAvatar = $board_config['allow_avatar_upload'] ? '<img src="' . $board_config['avatar_path'] . '/' . $row->user_avatar . '" alt="" border="0" />' : '';
				break;
			case USER_AVATAR_REMOTE:
				$posterAvatar = $board_config['allow_avatar_remote'] ? '<img src="' . $row->user_avatar . '" alt="" border="0" />' : '';
				break;
			case USER_AVATAR_GALLERY:
				$posterAvatar = $board_config['allow_avatar_local'] ? '<img src="' . $board_config['avatar_gallery_path'] . '/' . $row->user_avatar . '" alt="" border="0" />' : '';
				break;
		}
	}

	if (!empty($row->user_viewemail) || $group_mod) {
		$emailUri = $board_config['board_email_form'] ? Session::appendSid('profile.php?mode=email&amp;' . POST_USERS_URL .'=' . $row->user_id) : 'mailto:' . $row->user_email;

		$emailImage = '<a href="' . $emailUri . '"><img src="' . $images['icon_email'] . '" alt="' . $lang['Send_email'] . '" title="' . $lang['Send_email'] . '" border="0" /></a>';
		$email = '<a href="' . $emailUri . '">' . $lang['Send_email'] . '</a>';
	}  else {
		$emailImage = '&nbsp;';
		$email = '&nbsp;';
	}

	$tempUrl = Session::appendSid('profile.php?mode=viewprofile&amp;' . POST_USERS_URL . '=' . $row->user_id);
	$profileImage = '<a href="' . $tempUrl . '"><img src="' . $images['icon_profile'] . '" alt="' . $lang['Read_profile'] . '" title="' . $lang['Read_profile'] . '" border="0" /></a>';
	$profile = '<a href="' . $tempUrl . '">' . $lang['Read_profile'] . '</a>';

	$tempUrl = Session::appendSid('privmsg.php?mode=post&amp;' . POST_USERS_URL . '=' . $row->user_id);
	$pmImage = '<a href="' . $tempUrl . '"><img src="' . $images['icon_pm'] . '" alt="' . $lang['Send_private_message'] . '" title="' . $lang['Send_private_message'] . '" border="0" /></a>';
	$pm = '<a href="' . $tempUrl . '">' . $lang['Send_private_message'] . '</a>';

	$wwwImage = $row->user_website ? '<a href="' . $row->user_website . '" target="_userwww"><img src="' . $images['icon_www'] . '" alt="' . $lang['Visit_website'] . '" title="' . $lang['Visit_website'] . '" border="0" /></a>' : '';
	$www = $row->user_website ? '<a href="' . $row->user_website . '" target="_userwww">' . $lang['Visit_website'] . '</a>' : '';

	$tempUrl = Session::appendSid('search.php?search_author=' . urlencode($row->username) . '&amp;show_results=posts');
	$searchImage = '<a href="' . $tempUrl . '"><img src="' . $images['icon_search'] . '" alt="' . sprintf($lang['Search_user_posts'], $row->username) . '" title="' . sprintf($lang['Search_user_posts'], $row->username) . '" border="0" /></a>';
	$search = '<a href="' . $tempUrl . '">' . sprintf($lang['Search_user_posts'], $row->username) . '</a>';
}
//
// --------------------------

//
// Start session management
//
$userdata = init_userprefs(PAGE_GROUPCP);
//
// End session management
//

$scriptName = preg_replace('/^\/?(.*?)\/?$/', "\\1", trim($board_config['script_path']));
$scriptName = $scriptName !== '' ? $scriptName . '/groupcp.php' : 'groupcp.php';
$serverUrl  = getServerUrl($board_config, $scriptName);

if (isset($_GET[POST_GROUPS_URL]) || isset($_POST[POST_GROUPS_URL])) {
    $groupId = isset($_POST[POST_GROUPS_URL]) ? (int)$_POST[POST_GROUPS_URL] : (int)$_GET[POST_GROUPS_URL];
} else {
    $groupId = '';
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
$isModerator = false;

if (isset($_POST['groupstatus']) && $groupId) {
    if (!$userdata['session_logged_in']) {
        redirect(Session::appendSid('login.php?redirect=groupcp.php&' . POST_GROUPS_URL . "=$groupId", true));
    }

    $group_moderator = dibi::select('group_moderator')
        ->from(GROUPS_TABLE)
        ->where('group_id = %i', $groupId)
        ->fetchSingle();

    if ($group_moderator !== $userdata['user_id'] && $userdata['user_level'] !== ADMIN) {
        $template->assignVars(
            [
                'META' => '<meta http-equiv="refresh" content="3;url=' . Session::appendSid('index.php') . '">'
            ]
        );

        $message = $lang['Not_group_moderator'] . '<br /><br />' . sprintf($lang['Click_return_group'], '<a href="' . Session::appendSid('groupcp.php?' . POST_GROUPS_URL . "=$groupId") . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_index'], '<a href="' . Session::appendSid('index.php') . '">', '</a>');

		message_die(GENERAL_MESSAGE, $message);
	}

	dibi::update(GROUPS_TABLE, ['group_type' => (int)$_POST['group_type'] ])
        ->where('group_id = %i', $groupId)
        ->execute();

    $template->assignVars(
        [
            'META' => '<meta http-equiv="refresh" content="3;url=' . Session::appendSid('groupcp.php?' . POST_GROUPS_URL . "=$groupId") . '">'
        ]
    );

    $message = $lang['Group_type_updated'] . '<br /><br />' . sprintf($lang['Click_return_group'], '<a href="' . Session::appendSid('groupcp.php?' . POST_GROUPS_URL . "=$groupId") . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_index'], '<a href="' . Session::appendSid('index.php') . '">', '</a>');

	message_die(GENERAL_MESSAGE, $message);

} elseif (isset($_POST['joingroup']) && $groupId) {
	//
	// First, joining a group
	// If the user isn't logged in redirect them to login
	//
    if (!$userdata['session_logged_in']) {
        redirect(Session::appendSid('login.php?redirect=groupcp.php&' . POST_GROUPS_URL . "=$groupId", true));
    } elseif ($sid !== $userdata['session_id']) {
        message_die(GENERAL_ERROR, $lang['Session_invalid']);
    }

    $rows = dibi::select(['ug.user_id', 'g.group_type'])
        ->from(USER_GROUP_TABLE)
        ->as('ug')
        ->innerJoin(GROUPS_TABLE)
        ->as('g')
        ->on('ug.group_id = g.group_id')
        ->where('g.group_id = %i', $groupId)
        ->where('g.group_type <> %i', GROUP_HIDDEN)
        ->fetchAll();

	if (!count($rows)) {
        message_die(GENERAL_MESSAGE, $lang['No_groups_exist']);
    }

    if ($rows[0]->group_type === GROUP_OPEN) {
        foreach ($rows as $row) {
            if ($userdata['user_id'] === $row->user_id) {
                $template->assignVars(
                    [
                        'META' => '<meta http-equiv="refresh" content="3;url=' . Session::appendSid('index.php') . '">'
                    ]
                );

                $message = $lang['Already_member_group'] . '<br /><br />' . sprintf($lang['Click_return_group'], '<a href="' . Session::appendSid('groupcp.php?' . POST_GROUPS_URL . "=$groupId") . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_index'], '<a href="' . Session::appendSid('index.php') . '">', '</a>');

                message_die(GENERAL_MESSAGE, $message);
            }
        }
    } else {
        $template->assignVars(
            [
                'META' => '<meta http-equiv="refresh" content="3;url=' . Session::appendSid('index.php') . '">'
            ]
        );

        $message = $lang['This_closed_group'] . '<br /><br />' . sprintf($lang['Click_return_group'], '<a href="' . Session::appendSid('groupcp.php?' . POST_GROUPS_URL . "=$groupId") . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_index'], '<a href="' . Session::appendSid('index.php') . '">', '</a>');

        message_die(GENERAL_MESSAGE, $message);
    }

	$insert_data = [
        'group_id' => $groupId,
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
        ->where('g.group_id = %i', $groupId)
        ->fetch();

	$emailer = new Emailer($board_config['smtp_delivery']);

	$emailer->setFrom($board_config['board_email']);
	$emailer->setReplyTo($board_config['board_email']);

	$emailer->useTemplate('group_request', $moderator->user_lang);
	$emailer->setEmailAddress($moderator->user_email);
	$emailer->setSubject($lang['Group_request']);

	$emailer->assignVars(
	    [
            'SITENAME' => $board_config['sitename'],
            'GROUP_MODERATOR' => $moderator->username,
            'EMAIL_SIG' => !empty($board_config['board_email_sig']) ? str_replace('<br />', "\n", "-- \n" . $board_config['board_email_sig']) : '',

            'U_GROUPCP' => $serverUrl . '?' . POST_GROUPS_URL . "=$groupId&validate=true"
        ]
	);
	$emailer->send();
	$emailer->reset();

    $template->assignVars(
        [
            'META' => '<meta http-equiv="refresh" content="3;url=' . Session::appendSid('index.php') . '">'
        ]
    );

    $message = $lang['Group_joined'] . '<br /><br />' . sprintf($lang['Click_return_group'], '<a href="' . Session::appendSid('groupcp.php?' . POST_GROUPS_URL . "=$groupId") . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_index'], '<a href="' . Session::appendSid('index.php') . '">', '</a>');

	message_die(GENERAL_MESSAGE, $message);
} elseif (isset($_POST['unsub']) || isset($_POST['unsubpending']) && $groupId) {
	//
	// Second, unsubscribing from a group
	// Check for confirmation of unsub.
	//
    if ($cancel) {
        redirect(Session::appendSid('groupcp.php', true));
    } elseif (!$userdata['session_logged_in']) {
        redirect(Session::appendSid('login.php?redirect=groupcp.php&' . POST_GROUPS_URL . "=$groupId", true));
    } elseif ($sid !== $userdata['session_id']) {
        message_die(GENERAL_ERROR, $lang['Session_invalid']);
    }

	if ($confirm) {
	    dibi::delete(USER_GROUP_TABLE)
            ->where('user_id = %i', $userdata['user_id'])
            ->where('group_id = %i', $groupId)
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

        $message = $lang['Unsub_success'] . '<br /><br />' . sprintf($lang['Click_return_group'], '<a href="' . Session::appendSid('groupcp.php?' . POST_GROUPS_URL . "=$groupId") . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_index'], '<a href="' . Session::appendSid('index.php') . '">', '</a>');

		message_die(GENERAL_MESSAGE, $message);
	} else {
		$unsub_msg = isset($_POST['unsub']) ? $lang['Confirm_unsub'] : $lang['Confirm_unsub_pending'];

		$s_hidden_fields = '<input type="hidden" name="' . POST_GROUPS_URL . '" value="' . $groupId . '" /><input type="hidden" name="unsub" value="1" />';
		$s_hidden_fields .= '<input type="hidden" name="sid" value="' . $userdata['session_id'] . '" />';

        PageHelper::header($template, $userdata, $board_config, $lang, $images,  $theme, $lang['Group_Control_Panel'], $gen_simple_header);

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

        PageHelper::footer($template, $userdata, $lang, $gen_simple_header);
	}

} elseif ($groupId) {
	//
	// Did the group moderator get here through an email?
	// If so, check to see if they are logged in.
	//
    if (isset($_GET['validate'])) {
        if (!$userdata['session_logged_in']) {
            redirect(Session::appendSid('login.php?redirect=groupcp.php&' . POST_GROUPS_URL . "=$groupId", true));
        }
    }

	//
	// For security, get the ID of the group moderator.
	//
	switch($dbms) {
		case 'postgresql':
            $groupInfo = dibi::query('SELECT g.group_moderator, g.group_type, aa.auth_mod 
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
				ORDER BY auth_mod DESC', $groupId, $groupId)
                ->fetch();
			break;

		case 'oracle':
            $groupInfo = dibi::select(['g.group_moderator', 'g.group_type', 'aa.auth_mod'])
                ->from(GROUPS_TABLE)
                ->as('g')
                ->innerJoin(AUTH_ACCESS_TABLE)
                ->as('aa')
                ->on('aa.group_id (+) = g.group_id')
                ->where('g.group_id = %i', $groupId)
                ->orderBy('aa.auth_mod', dibi::DESC)
                ->fetch();
			break;

		default:
		    // there was left join
            $groupInfo = dibi::select(['g.group_moderator', 'g.group_type', 'aa.auth_mod'])
                ->from(GROUPS_TABLE)
                ->as('g')
                ->innerJoin(AUTH_ACCESS_TABLE)
                ->as('aa')
                ->on('aa.group_id = g.group_id')
                ->where('g.group_id = %i', $groupId)
                ->orderBy('aa.auth_mod', dibi::DESC)
                ->fetch();
			break;
	}

    if ($groupInfo) {
		$group_moderator = $groupInfo->group_moderator;

        if ($group_moderator === $userdata['user_id'] || $userdata['user_level'] === ADMIN) {
            $isModerator = true;
        }
			
		//
		// Handle Additions, removals, approvals and denials
        //
        if (!empty($_POST['add']) || !empty($_POST['remove']) || isset($_POST['approve']) || isset($_POST['deny'])) {
            if (!$userdata['session_logged_in']) {
                redirect(Session::appendSid('login.php?redirect=groupcp.php&' . POST_GROUPS_URL . "=$groupId", true));
            } elseif ($sid !== $userdata['session_id']) {
                message_die(GENERAL_ERROR, $lang['Session_invalid']);
            }

            if (!$isModerator) {
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
                            'META' => '<meta http-equiv="refresh" content="3;url=' . Session::appendSid('groupcp.php?' . POST_GROUPS_URL . "=$groupId") . '">'
                        ]
                    );

                    $message = $lang['Could_not_add_user'] . '<br /><br />' . sprintf($lang['Click_return_group'], '<a href="' . Session::appendSid('groupcp.php?' . POST_GROUPS_URL . "=$groupId") . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_index'], '<a href="' . Session::appendSid('index.php') . '">', '</a>');

					message_die(GENERAL_MESSAGE, $message);
				}

                if ($row->user_id === ANONYMOUS) {
                    $template->assignVars(
                        [
                            'META' => '<meta http-equiv="refresh" content="3;url=' . Session::appendSid('groupcp.php?' . POST_GROUPS_URL . "=$groupId") . '">'

                        ]);

                    $message = $lang['Could_not_anon_user'] . '<br /><br />' . sprintf($lang['Click_return_group'], '<a href="' . Session::appendSid('groupcp.php?' . POST_GROUPS_URL . "=$groupId") . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_index'], '<a href="' . Session::appendSid('index.php') . '">', '</a>');

					message_die(GENERAL_MESSAGE, $message);
				}

				$member = dibi::select(['ug.user_id', 'u.user_level'])
                    ->from(USER_GROUP_TABLE)
                    ->as('ug')
                    ->innerJoin(USERS_TABLE)
                    ->as('u')
                    ->on('ug.user_id = u.user_id')
                    ->where('u.user_id = %i', $row->user_id)
                    ->where('ug.group_id = %i', $groupId)
                    ->fetch();

				if ($member) {
                    $template->assignVars(
                        [
                            'META' => '<meta http-equiv="refresh" content="3;url=' . Session::appendSid('groupcp.php?' . POST_GROUPS_URL . "=$groupId") . '">'
                        ]
                    );

                    $message = $lang['User_is_member_group'] . '<br /><br />' . sprintf($lang['Click_return_group'], '<a href="' . Session::appendSid('groupcp.php?' . POST_GROUPS_URL . "=$groupId") . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_index'], '<a href="' . Session::appendSid('index.php') . '">', '</a>');

                    message_die(GENERAL_MESSAGE, $message);
                } else {
				    $insert_data = [
                        'user_id' => $row->user_id,
                        'group_id' =>$groupId,
                        'user_pending' => 0
                    ];

				    dibi::insert(USER_GROUP_TABLE, $insert_data)->execute();

                    if ($row->user_level !== ADMIN && $row->user_level !== MOD && $groupInfo->auth_mod) {
                        dibi::update(USERS_TABLE, ['user_level' => MOD])
                            ->where('user_id = %i', $row->user_id)
                            ->execute();
                    }

					//
					// Get the group name
					// Email the user and tell them they're in the group
					//
                    $groupName =  dibi::select('group_name')
                        ->from(GROUPS_TABLE)
                        ->where('group_id = %i', $groupId)
                        ->fetchSingle();

					// TODO group not exists
                    if (!$groupName) {
                        message_die(GENERAL_ERROR, 'Could not get group information');
                    }

					$emailer = new Emailer($board_config['smtp_delivery']);

					$emailer->setFrom($board_config['board_email']);
					$emailer->setReplyTo($board_config['board_email']);

					$emailer->useTemplate('group_added', $row->user_lang);
					$emailer->setEmailAddress($row->user_email);
					$emailer->setSubject($lang['Group_added']);

                    $emailer->assignVars(
                        [
                            'SITENAME'   => $board_config['sitename'],
                            'GROUP_NAME' => $groupName,
                            'EMAIL_SIG'  => !empty($board_config['board_email_sig']) ? str_replace('<br />', "\n", "-- \n" . $board_config['board_email_sig']) : '',

                            'U_GROUPCP' => $serverUrl . '?' . POST_GROUPS_URL . "=$groupId"
                        ]
                    );
                    $emailer->send();
					$emailer->reset();
				}
			} else {
				if (( ( isset($_POST['approve']) || isset($_POST['deny']) ) && isset($_POST['pending_members']) ) || ( isset($_POST['remove']) && isset($_POST['members']) )) {
					$members = isset($_POST['approve']) || isset($_POST['deny']) ? $_POST['pending_members'] : $_POST['members'];

                    if (isset($_POST['approve'])) {
                        if ($groupInfo->auth_mod) {
						    dibi::update(USERS_TABLE, ['user_level' => MOD])
                                ->where('user_id IN %in', $members)
                                ->where('user_level NOT IN %in', [MOD, ADMIN])
                                ->execute();
						}

						dibi::update(USER_GROUP_TABLE, ['user_pending' => 0])
                            ->where('user_id IN %in', $members)
                            ->where('group_id = %i', $groupId)
                            ->execute();

                    } elseif (isset($_POST['deny']) || isset($_POST['remove'])) {
                        if ($groupInfo->auth_mod) {
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
                            ->where('group_id = %i', $groupId)
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
						$groupName = dibi::select('group_name')
                            ->from(GROUPS_TABLE)
                            ->where('group_id = %i', $groupId)
                            ->fetchSingle();

						if ($groupName === false) {
							message_die(GENERAL_ERROR, 'Could not get group information');
						}

						$emailer = new Emailer($board_config['smtp_delivery']);

						$emailer->setFrom($board_config['board_email']);
						$emailer->setReplyTo($board_config['board_email']);

						foreach ($bcc_list as $bcc_value) {
							$emailer->addBcc($bcc_value);
						}

						$emailer->useTemplate('group_approved');
						$emailer->setSubject($lang['Group_approved']);

                        $emailer->assignVars(
                            [
                                'SITENAME'   => $board_config['sitename'],
                                'GROUP_NAME' => $groupName,
                                'EMAIL_SIG'  => !empty($board_config['board_email_sig']) ? str_replace('<br />', "\n", "-- \n" . $board_config['board_email_sig']) : '',

                                'U_GROUPCP' => $serverUrl . '?' . POST_GROUPS_URL . "=$groupId"
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
    $groupInfo = dibi::select('*')
        ->from(GROUPS_TABLE)
        ->where('group_id = %i', $groupId)
        ->where('group_single_user = %i', 0)
        ->fetch();

    if (!$groupInfo) {
        message_die(GENERAL_MESSAGE, $lang['Group_not_exist']);
    }

    $columns = [
        'username',
        'user_id',
        'user_viewemail',
        'user_posts',
        'user_topics',
        'user_regdate',
        'user_from',
        'user_website',
        'user_email',
        'user_avatar_type',
        'user_allowavatar',
        'user_avatar',
        'user_allow_viewonline',
        'user_session_time'
    ];

    //
    // Get moderator details for this group
    //
    $group_moderator = dibi::select($columns)
        ->from(USERS_TABLE)
        ->where('user_id = %i', $groupInfo->group_moderator)
        ->fetch();

    $columns = [
        'u.username',
        'u.user_id',
        'u.user_viewemail',
        'u.user_posts',
        'u.user_topics',
        'u.user_regdate',
        'u.user_from',
        'u.user_website',
        'u.user_email',
        'u.user_avatar_type',
        'u.user_allow_viewonline',
        'u.user_session_time'
    ];

    //
    // Get user information for this group
    //
    $groupMembers = dibi::select($columns)
        ->from(USERS_TABLE)
        ->as('u')
        ->innerJoin(USER_GROUP_TABLE)
        ->as('ug')
        ->on('u.user_id = ug.user_id')
        ->where('ug.group_id = %i', $groupId)
        ->where('ug.user_pending = %i', 0)
        ->where('ug.user_id <> %i', $group_moderator->user_id)
        ->orderBy('u.username')
        ->limit($board_config['group_members_per_page'])
        ->offset($start)
        ->fetchAll();

    $membersCount = dibi::select('COUNT(*)')
        ->as('count')
        ->from(USERS_TABLE)
        ->as('u')
        ->innerJoin(USER_GROUP_TABLE)
        ->as('ug')
        ->on('u.user_id = ug.user_id')
        ->where('ug.group_id = %i', $groupId)
        ->where('ug.user_pending = %i', 0)
        ->where('ug.user_id <> %i', $group_moderator->user_id)
        ->orderBy('u.username')
        ->fetchSingle();

	$columns = [
	    'u.username',
        'u.user_id',
        'u.user_viewemail',
        'u.user_posts',
        'u.user_topics',
        'u.user_regdate',
        'u.user_from',
        'u.user_website',
        'u.user_email',
        'u.user_avatar_type',
        'u.user_allow_viewonline',
        'u.user_session_time'
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
        ->where('g.group_id = %i', $groupId)
        ->where('ug.user_pending = %i', 1)
        ->orderBy('u.username')
        ->fetchAll();

    $modgroup_pending_count = count($modgroup_pending_list);

	$isGroupMember = 0;

    foreach ($groupMembers as $groupMember) {
        if ($groupMember->user_id === $userdata['user_id'] && $userdata['session_logged_in']) {
            $isGroupMember = true;

            break;
        }
    }

	$isGroupPendingMember = 0;

    foreach ($modgroup_pending_list as $modgroup_pending_value) {
        if ($modgroup_pending_value->user_id === $userdata['user_id'] && $userdata['session_logged_in']) {
            $isGroupPendingMember = true;

            break;
        }
    }

    if ($userdata['user_level'] === ADMIN) {
        $isModerator = true;
    }

    if ($userdata['user_id'] === $groupInfo->group_moderator) {
        $isModerator = true;

        $group_details = $lang['Are_group_moderator'];

        $s_hidden_fields = '<input type="hidden" name="' . POST_GROUPS_URL . '" value="' . $groupId . '" />';
    } elseif ($isGroupMember || $isGroupPendingMember) {
        $template->assignBlockVars('switch_unsubscribe_group_input', []);

        $group_details = $isGroupPendingMember ? $lang['Pending_this_group'] : $lang['Member_this_group'];

        $s_hidden_fields = '<input type="hidden" name="' . POST_GROUPS_URL . '" value="' . $groupId . '" />';
    } elseif ($userdata['user_id'] === ANONYMOUS) {
        $group_details   = $lang['Login_to_join'];
        $s_hidden_fields = '';
    } else {
        if ($groupInfo->group_type === GROUP_OPEN) {
            $template->assignBlockVars('switch_subscribe_group_input', []);

            $group_details   = $lang['This_open_group'];
            $s_hidden_fields = '<input type="hidden" name="' . POST_GROUPS_URL . '" value="' . $groupId . '" />';
        } elseif ($groupInfo->group_type === GROUP_CLOSED) {
            $group_details   = $lang['This_closed_group'];
            $s_hidden_fields = '';
        } elseif ($groupInfo->group_type === GROUP_HIDDEN) {
            $group_details   = $lang['This_hidden_group'];
            $s_hidden_fields = '';
        }
    }

    PageHelper::header($template, $userdata, $board_config, $lang, $images,  $theme, $lang['Group_Control_Panel'], $gen_simple_header);

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

	generate_user_info($group_moderator, $board_config['default_dateformat'], $isModerator, $from, $posts, $topics, $joined, $poster_avatar, $profileImage, $profile, $searchImage, $search, $pmImage, $pm, $emailImage, $email, $wwwImage, $www);

    // <!-- BEGIN Another Online/Offline indicator -->
    if (!$group_moderator->user_allow_viewonline && $userdata['user_level'] === ADMIN || $group_moderator->user_allow_viewonline) {
        $expiry_time = time() - ONLINE_TIME_DIFF;

        if ($group_moderator->user_session_time >= $expiry_time) {
            $user_onlinestatus = '<img src="' . $images['Online'] . '" alt="' . $lang['Online'] . '" title="' . $lang['Online'] . '" border="0" />';

            if (!$group_moderator->user_allow_viewonline && $userdata['user_level'] === ADMIN) {
                $user_onlinestatus = '<img src="' . $images['Hidden_Admin'] . '" alt="' . $lang['Hidden'] . '" title="' . $lang['Hidden'] . '" border="0" />';
            }
        } else {
            $user_onlinestatus = '<img src="' . $images['Offline'] . '" alt="' . $lang['Offline'] . '" title="' . $lang['Offline'] . '" border="0" />';

            if (!$group_moderator->user_allow_viewonline && $userdata['user_level'] === ADMIN) {
                $user_onlinestatus = '<img src="' . $images['Offline'] . '" alt="' . $lang['Hidden'] . '" title="' . $lang['Hidden'] . '" border="0" />';
            }
        }
    } else {
        $user_onlinestatus = '<img src="' . $images['Offline'] . '" alt="' . $lang['Offline'] . '" title="' . $lang['Offline'] . '" border="0" />';
    }
    // <!-- END Another Online/Offline indicator -->

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
            'L_TOPICS' => $lang['Topics'],
            'L_WEBSITE' => $lang['Website'],
            'L_FROM' => $lang['Location'],
            'L_ORDER' => $lang['Order'],
            'L_SORT' => $lang['Sort'],
            'L_SUBMIT' => $lang['Sort'],
            'L_SELECT' => $lang['Select'],
            'L_REMOVE_SELECTED' => $lang['Remove_selected'],
            'L_ADD_MEMBER' => $lang['Add_member'],
            'L_FIND_USERNAME' => $lang['Find_username'],

            'GROUP_NAME' => htmlspecialchars($groupInfo->group_name, ENT_QUOTES),
            'GROUP_DESC' => htmlspecialchars($groupInfo->group_description, ENT_QUOTES),
            'GROUP_DETAILS' => $group_details,

            'MOD_ROW_COLOR' => '#' . $theme['td_color1'],
            'MOD_ROW_CLASS' => $theme['td_class1'],

            'MOD_USERNAME' => $username,

            // <!-- BEGIN Another Online/Offline indicator -->
            'MOD_ONLINE' => $user_onlinestatus,
            // <!-- END Another Online/Offline indicator -->

            'MOD_FROM' => $from,
            'MOD_JOINED' => $joined,
            'MOD_POSTS' => $posts,
            'MOD_TOPICS' => $topics,

            'MOD_AVATAR_IMG' => $poster_avatar,

            'MOD_PROFILE_IMG' => $profileImage,
            'MOD_PROFILE' => $profile,

            'MOD_SEARCH_IMG' => $searchImage,
            'MOD_SEARCH' => $search,

            'MOD_PM_IMG' => $pmImage,
            'MOD_PM' => $pm,

            'MOD_EMAIL_IMG' => $emailImage,
            'MOD_EMAIL' => $email,

            'MOD_WWW_IMG' => $wwwImage,
            'MOD_WWW' => $www,

            'U_MOD_VIEWPROFILE' => Session::appendSid('profile.php?mode=viewprofile&amp;' . POST_USERS_URL . "=$user_id"),
            'U_SEARCH_USER' => Session::appendSid('search.php?mode=searchuser'),

            'S_GROUP_OPEN_TYPE' => GROUP_OPEN,
            'S_GROUP_CLOSED_TYPE' => GROUP_CLOSED,
            'S_GROUP_HIDDEN_TYPE' => GROUP_HIDDEN,
            'S_GROUP_OPEN_CHECKED' => $groupInfo->group_type === GROUP_OPEN ? ' checked="checked"' : '',
            'S_GROUP_CLOSED_CHECKED' => $groupInfo->group_type === GROUP_CLOSED ? ' checked="checked"' : '',
            'S_GROUP_HIDDEN_CHECKED' => $groupInfo->group_type === GROUP_HIDDEN ? ' checked="checked"' : '',

            'S_HIDDEN_FIELDS' => $s_hidden_fields,

            /**
             * todo do we need this?
             */
            //'S_MODE_SELECT' => $select_sort_mode,
            //'S_ORDER_SELECT' => $select_sort_order,
            'S_GROUPCP_ACTION' => Session::appendSid('groupcp.php?' . POST_GROUPS_URL . "=$groupId")]
	);

	//
	// Dump out the remaining users
	//

    $min = min((int)$board_config['group_members_per_page'] + $start, $membersCount);

    foreach ($groupMembers as $i => $groupMember) {
		generate_user_info($groupMember, $board_config['default_dateformat'], $isModerator, $from, $posts, $topics, $joined, $poster_avatar, $profileImage, $profile, $searchImage, $search, $pmImage, $pm, $emailImage, $email, $wwwImage, $www);

        // <!-- BEGIN Another Online/Offline indicator -->
        if (!$groupMember->user_allow_viewonline && $userdata['user_level'] === ADMIN || $groupMember->user_allow_viewonline) {
            $expiry_time = time() - ONLINE_TIME_DIFF;

            if ($groupMember->user_session_time >= $expiry_time) {
                $user_onlinestatus = '<img src="' . $images['Online'] . '" alt="' . $lang['Online'] . '" title="' . $lang['Online'] . '" border="0" />';

                if (!$groupMember->user_allow_viewonline && $userdata['user_level'] === ADMIN) {
                    $user_onlinestatus = '<img src="' . $images['Hidden_Admin'] . '" alt="' . $lang['Hidden'] . '" title="' . $lang['Hidden'] . '" border="0" />';
                }
            } else {
                $user_onlinestatus = '<img src="' . $images['Offline'] . '" alt="' . $lang['Offline'] . '" title="' . $lang['Offline'] . '" border="0" />';

                if (!$groupMember->user_allow_viewonline && $userdata['user_level'] === ADMIN) {
                    $user_onlinestatus = '<img src="' . $images['Offline'] . '" alt="' . $lang['Hidden'] . '" title="' . $lang['Hidden'] . '" border="0" />';
                }
            }
        } else {
            $user_onlinestatus = '<img src="' . $images['Offline'] . '" alt="' . $lang['Offline'] . '" title="' . $lang['Offline'] . '" border="0" />';
        }
        // <!-- END Another Online/Offline indicator -->

        if ($groupInfo->group_type !== GROUP_HIDDEN || $isGroupMember || $isModerator) {
			$rowColor = !($i % 2) ? $theme['td_color1'] : $theme['td_color2'];
			$rowClass = !($i % 2) ? $theme['td_class1'] : $theme['td_class2'];

            $template->assignBlockVars('member_row',
                [
                    'ROW_COLOR'      => '#' . $rowColor,
                    'ROW_CLASS'      => $rowClass,
                    'USERNAME'       => $groupMember->username,

                    // <!-- BEGIN Another Online/Offline indicator -->
                    'ONLINESTATUS' => $user_onlinestatus,
                    // <!-- END Another Online/Offline indicator -->

                    'FROM'           => $from,
                    'JOINED'         => $joined,
                    'POSTS'          => $posts,
                    'TOPICS'         => $topics,
                    'USER_ID'        => $groupMember->user_id,
                    'AVATAR_IMG'     => $poster_avatar,
                    'PROFILE_IMG'    => $profileImage,
                    'PROFILE'        => $profile,
                    'SEARCH_IMG'     => $searchImage,
                    'SEARCH'         => $search,
                    'PM_IMG'         => $pmImage,
                    'PM'             => $pm,
                    'EMAIL_IMG'      => $emailImage,
                    'EMAIL'          => $email,
                    'WWW_IMG'        => $wwwImage,
                    'WWW'            => $www,

                    'U_VIEWPROFILE' => Session::appendSid('profile.php?mode=viewprofile&amp;' . POST_USERS_URL . "=$user_id")
                ]
            );

            if ($isModerator) {
                $template->assignBlockVars('member_row.switch_mod_option', []);
            }
		}
	}

    if (!$membersCount) {
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

    $currentPage = $membersCount ? ceil($membersCount / $board_config['group_members_per_page']) : 1;

    $template->assignVars(
        [
            'PAGINATION'  => generate_pagination('groupcp.php?' . POST_GROUPS_URL . "=$groupId", $membersCount, $board_config['group_members_per_page'], $start),
            'PAGE_NUMBER' => sprintf($lang['Page_of'], floor($start / $board_config['group_members_per_page']) + 1, $currentPage),

            'L_GOTO_PAGE' => $lang['Goto_page']
        ]
    );

    if ($groupInfo->group_type === GROUP_HIDDEN && !$isGroupMember && !$isModerator) {
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
    if ($isModerator) {
		//
		// Users pending in ONLY THIS GROUP (which is moderated by this user)
		//
        if ($modgroup_pending_count) {
            foreach ($modgroup_pending_list as $modgroup_pending_value) {
				$username = $modgroup_pending_value->username;
				$user_id = $modgroup_pending_value->user_id;

				generate_user_info(
				    $modgroup_pending_value,
                    $board_config['default_dateformat'],
                    $isModerator,
                    $from,
                    $posts,
                    $topics,
                    $joined,
                    $poster_avatar,
                    $profileImage,
                    $profile,
                    $searchImage,
                    $search,
                    $pmImage,
                    $pm,
                    $emailImage,
                    $email,
                    $wwwImage,
                    $www
                );

                // <!-- BEGIN Another Online/Offline indicator -->
                if (!$modgroup_pending_value->user_allow_viewonline && $userdata['user_level'] === ADMIN || $modgroup_pending_value->user_allow_viewonline) {
                    $expiry_time = time() - ONLINE_TIME_DIFF;

                    if ($modgroup_pending_value->user_session_time >= $expiry_time) {
                        $user_onlinestatus = '<img src="' . $images['Online'] . '" alt="' . $lang['Online'] . '" title="' . $lang['Online'] . '" border="0" />';

                        if (!$modgroup_pending_value->user_allow_viewonline && $userdata['user_level'] === ADMIN) {
                            $user_onlinestatus = '<img src="' . $images['Hidden_Admin'] . '" alt="' . $lang['Hidden'] . '" title="' . $lang['Hidden'] . '" border="0" />';
                        }
                    } else {
                        $user_onlinestatus = '<img src="' . $images['Offline'] . '" alt="' . $lang['Offline'] . '" title="' . $lang['Offline'] . '" border="0" />';

                        if (!$modgroup_pending_value->user_allow_viewonline && $userdata['user_level'] === ADMIN) {
                            $user_onlinestatus = '<img src="' . $images['Offline'] . '" alt="' . $lang['Hidden'] . '" title="' . $lang['Hidden'] . '" border="0" />';
                        }
                    }
                } else {
                    $user_onlinestatus = '<img src="' . $images['Offline'] . '" alt="' . $lang['Offline'] . '" title="' . $lang['Offline'] . '" border="0" />';
                }
                // <!-- END Another Online/Offline indicator -->

				$rowColor = !($i % 2) ? $theme['td_color1'] : $theme['td_color2'];
				$rowClass = !($i % 2) ? $theme['td_class1'] : $theme['td_class2'];

				$user_select = '<input type="checkbox" name="member[]" value="' . $user_id . '">';

                $template->assignBlockVars('pending_members_row',
                    [
                        'ROW_CLASS'      => $rowClass,
                        'ROW_COLOR'      => '#' . $rowColor,
                        'USERNAME'       => $username,

                        // <!-- BEGIN Another Online/Offline indicator -->
                        'ONLINESTATUS' => $user_onlinestatus,
                        // <!-- END Another Online/Offline indicator -->

                        'FROM'           => $from,
                        'JOINED'         => $joined,
                        'POSTS'          => $posts,
                        'USER_ID'        => $user_id,
                        'AVATAR_IMG'     => $poster_avatar,
                        'PROFILE_IMG'    => $profileImage,
                        'PROFILE'        => $profile,
                        'SEARCH_IMG'     => $searchImage,
                        'SEARCH'         => $search,
                        'PM_IMG'         => $pmImage,
                        'PM'             => $pm,
                        'EMAIL_IMG'      => $emailImage,
                        'EMAIL'          => $email,
                        'WWW_IMG'        => $wwwImage,
                        'WWW'            => $www,

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

    if ($isModerator) {
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
	$inGroup = [];

    if ($userdata['session_logged_in']) {
	    $rows = dibi::select(['g.group_id', 'g.group_name', 'g.group_type', 'ug.user_pending'])
            ->from(GROUPS_TABLE)
            ->as('g')
            ->innerJoin(USER_GROUP_TABLE)
            ->as('ug')
            ->on('ug.group_id = g.group_id')
            ->where('ug.user_id = %i', $userdata['user_id'])
            ->where('g.group_single_user <> %i', 1)
            ->orderBy('g.group_name')
            ->orderBy('ug.user_id')
            ->fetchAll();

        $inGroup              = [];
        $s_member_groups_opt  = '';
        $s_pending_groups_opt = '';

        foreach ($rows as $row) {
            $inGroup[] = $row->group_id;

            if ($row->user_pending) {
                $s_pending_groups_opt .= '<option value="' . $row->group_id . '">' . htmlspecialchars($row->group_name, ENT_QUOTES) . '</option>';
            } else {
                $s_member_groups_opt .= '<option value="' . $row->group_id . '">' . htmlspecialchars($row->group_name, ENT_QUOTES) . '</option>';
            }
        }

        $s_pending_groups = '<select name="' . POST_GROUPS_URL . '">' . $s_pending_groups_opt . '</select>';
        $s_member_groups = '<select name="' . POST_GROUPS_URL . '">' . $s_member_groups_opt . '</select>';
	}

	//
	// Select all other groups i.e. groups that this user is not a member of
	//
    $groups = dibi::select(['group_id', 'group_name', 'group_type'])
        ->from(GROUPS_TABLE)
        ->where('group_single_user <> %i', 1);

    if (count($inGroup)) {
        $groups->where('group_id NOT IN %in', $inGroup);
    }

    $groups = $groups->orderBy('group_name')
        ->fetchAll();

	$s_group_list_opt = '';

    foreach ($groups as $group) {
        if ($group->group_type !== GROUP_HIDDEN || $userdata['user_level'] === ADMIN) {
            $s_group_list_opt .= '<option value="' . $group->group_id . '">' . htmlspecialchars($group->group_name, ENT_QUOTES) . '</option>';
        }
    }
	$s_group_list = '<select name="' . POST_GROUPS_URL . '">' . $s_group_list_opt . '</select>';

    if ($s_group_list_opt !== '' || $s_pending_groups_opt !== '' || $s_member_groups_opt !== '') {
		//
		// Load and process templates
		//
        PageHelper::header($template, $userdata, $board_config, $lang, $images, $theme, $lang['Group_Control_Panel'], $gen_simple_header);

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
		message_die(GENERAL_MESSAGE, $lang['No_groups_exist']);
	}
}

PageHelper::footer($template, $userdata, $lang, $gen_simple_header);

?>