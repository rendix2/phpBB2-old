<?php
/***************************************************************************
 *                              viewonline.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: viewonline.php 5142 2005-05-06 20:50:13Z acydburn $
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

//
// Start session management
//
$userdata = init_userprefs(PAGE_VIEW_ONLINE);
//
// End session management
//

//
// Output page header and load viewonline template
//
PageHelper::header($template, $userdata, $board_config, $lang, $images, $theme, $lang['Who_is_Online'], $gen_simple_header);

$template->setFileNames(
    [
	   'body' => 'viewonline_body.tpl'        
    ]
);
make_jumpbox('viewforum.php');

$template->assignVars(
    [
        'L_WHOSONLINE'     => $lang['Who_is_Online'],
        'L_ONLINE_EXPLAIN' => $lang['Online_explain'],
        'L_USERNAME'       => $lang['Username'],
        'L_FORUM_LOCATION' => $lang['Forum_Location'],
        'L_LAST_UPDATE'    => $lang['Last_updated']
    ]
);

//
// Forum info
//
$forums = dibi::select(['forum_id', 'forum_name'])
    ->from(FORUMS_TABLE)
    ->fetchPairs('forum_id', 'forum_name');

//
// Get auth data
//
$is_auth = Auth::authorize(AUTH_VIEW, AUTH_LIST_ALL, $userdata);

//
// Get user list
//
$columns = ['u.user_id', 'u.username', 'u.user_allow_viewonline', 'u.user_level', 's.session_logged_in', 's.session_time', 's.session_page', 's.session_ip'];

$userTimeZone = isset($userdata['user_timezone']) ? $userdata['user_timezone'] : $board_config['board_timezone'];

$time = new DateTime();
$time->setTimezone(new DateTimeZone($userTimeZone));
$time->sub(new DateInterval('PT' . ONLINE_TIME_DIFF . 'S'));

$rows = dibi::select($columns)
    ->from(USERS_TABLE)
    ->as('u')
    ->innerJoin(SESSIONS_TABLE)
    ->as('s')
    ->on('u.user_id = s.session_user_id')
    ->where('s.session_time >= %i', $time->getTimestamp())
    ->orderBy('u.username', dibi::ASC)
    ->orderBy('s.session_ip', dibi::ASC)
    ->fetchAll();

$guestUsers      = 0;
$registeredUsers = 0;
$hiddenUsers     = 0;

$regCounter     = 0;
$guestCounter   = 0;
$previousUserId = 0;
$previousIp     = '';

foreach ($rows as $row) {
	$viewOnline = false;

    if ($row->session_logged_in) {
		$userId = $row->user_id;

        if ($userId !== $previousUserId) {
			$userName = $row->username;

            $styleColor = '';
            if ($row->user_level === ADMIN) {
                $userName = '<b style="color:#' . $theme['fontcolor3'] . '">' . $userName . '</b>';
            } elseif ($row->user_level === MOD) {
                $userName = '<b style="color:#' . $theme['fontcolor2'] . '">' . $userName . '</b>';
            }

            if ($row->user_allow_viewonline) {
                $viewOnline = true;
                $registeredUsers++;
            } else {
				$viewOnline = $userdata['user_level'] === ADMIN;
				$hiddenUsers++;

				$userName = '<i>' . $userName . '</i>';
			}

			$whichCounter   = 'reg_counter';
			$whichRow       = 'reg_user_row';
			$previousUserId = $userId;
		}
	}
	else {
        if ($row->session_ip !== $previousIp) {
			$userName   = $lang['Guest'];
			$viewOnline = true;
			$guestUsers++;
	
			$whichCounter = 'guest_counter';
			$whichRow     = 'guest_user_row';
		}
	}

	$previousIp = $row->session_ip;

    if ($viewOnline) {
        if ($row->session_page < 1 || !$is_auth[$row->session_page]['auth_view']) {
            switch ($row->session_page) {
				case PAGE_INDEX:
					$location    = $lang['Forum_index'];
					$locationUrl = 'index.php';
					break;
				case PAGE_POSTING:
					$location    = $lang['Posting_message'];
					$locationUrl = 'index.pphp';
					break;
				case PAGE_LOGIN:
					$location    = $lang['Logging_on'];
					$locationUrl = 'index.php';
					break;
				case PAGE_SEARCH:
					$location    = $lang['Searching_forums'];
					$locationUrl = 'search.php';
					break;
				case PAGE_PROFILE:
					$location    = $lang['Viewing_profile'];
					$locationUrl = 'index.php';
					break;
				case PAGE_VIEW_ONLINE:
					$location    = $lang['Viewing_online'];
					$locationUrl = 'viewonline.php';
					break;
				case PAGE_VIEW_MEMBERS:
					$location    = $lang['Viewing_member_list'];
					$locationUrl = 'memberlist.php';
					break;
				case PAGE_PRIVMSGS:
					$location    = $lang['Viewing_priv_msgs'];
					$locationUrl = 'privmsg.php';
					break;
				case PAGE_FAQ:
					$location    = $lang['Viewing_FAQ'];
					$locationUrl = 'faq.php';
					break;
				default:
					$location    = $lang['Forum_index'];
					$locationUrl = 'index.php';
			}
		} else {
			$locationUrl = Session::appendSid('viewforum.php?' . POST_FORUM_URL . '=' . $row->session_page);
			$location    = $forums[$row->session_page];
		}

		$rowColor = ( $$whichCounter % 2 ) ? $theme['td_color1'] : $theme['td_color2'];
		$rowClass = ( $$whichCounter % 2 ) ? $theme['td_class1'] : $theme['td_class2'];

        $template->assignBlockVars((string)$whichRow,
            [
                'ROW_COLOR'      => '#' . $rowColor,
                'ROW_CLASS'      => $rowClass,
                'USERNAME'       => $userName,
                'LASTUPDATE'     => create_date($board_config['default_dateformat'], $row->session_time, $board_config['board_timezone']),
                'FORUM_LOCATION' => $location,

                'U_USER_PROFILE'   => Session::appendSid('profile.php?mode=viewprofile&amp;' . POST_USERS_URL . '=' . $userId),
                'U_FORUM_LOCATION' => Session::appendSid($locationUrl)
            ]
        );

        $$whichCounter++;
	}
}

if ($registeredUsers === 0) {
    $l_r_user_s = $lang['Reg_users_zero_online'];
} elseif ($registeredUsers === 1) {
    $l_r_user_s = $lang['Reg_user_online'];
} else {
    $l_r_user_s = $lang['Reg_users_online'];
}

if ($hiddenUsers === 0) {
    $l_h_user_s = $lang['Hidden_users_zero_online'];
} elseif ($hiddenUsers === 1) {
    $l_h_user_s = $lang['Hidden_user_online'];
} else {
    $l_h_user_s = $lang['Hidden_users_online'];
}

if ($guestUsers === 0) {
    $l_g_user_s = $lang['Guest_users_zero_online'];
} elseif ($guestUsers === 1) {
    $l_g_user_s = $lang['Guest_user_online'];
} else {
    $l_g_user_s = $lang['Guest_users_online'];
}

$template->assignVars(
    [
        'TOTAL_REGISTERED_USERS_ONLINE' => sprintf($l_r_user_s, $registeredUsers) . sprintf($l_h_user_s, $hiddenUsers),
        'TOTAL_GUEST_USERS_ONLINE'      => sprintf($l_g_user_s, $guestUsers)
    ]
);

if ($registeredUsers + $hiddenUsers === 0) {
    $template->assignVars(['L_NO_REGISTERED_USERS_BROWSING' => $lang['No_users_browsing']]);
}

if ($guestUsers === 0) {
    $template->assignVars(['L_NO_GUESTS_BROWSING' => $lang['No_users_browsing']]);
}

$template->pparse('body');

PageHelper::footer($template, $userdata, $lang, $gen_simple_header);

?>