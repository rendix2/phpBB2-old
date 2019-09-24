<?php

define('IN_PHPBB', 1);

//
// Let's set the root dir for phpBB
//
$sep = DIRECTORY_SEPARATOR;
$phpbb_root_path = '.' . $sep . '..' . $sep;

require_once '.' . $sep . 'pagestart.php';

$template->setFileNames(['body' => 'admin/user_online.tpl']);

$template->assignVars( ['L_WHO_IS_ONLINE'    => $lang['Who_is_Online']]);

//
// Get users online information.
//
$user_timezone = isset($userdata['user_timezone']) ? $userdata['user_timezone'] : $board_config['board_timezone'];

$time = new DateTime();
$time->setTimezone(new DateTimeZone($user_timezone));
$time->sub(new DateInterval('PT' . ONLINE_TIME_DIFF . 'S'));

$registeredUsers = dibi::select(['u.user_id', 'u.username', 'u.user_session_time', 'u.user_session_page', 'u.user_allow_viewonline', 's.session_logged_in', 's.session_ip', 's.session_start', 'session_page'])
    ->from(USERS_TABLE)
    ->as('u')
    ->innerJoin(SESSIONS_TABLE)
    ->as('s')
    ->on('u.user_id = s.session_user_id')
    ->where('s.session_logged_in = %i', 1)
    ->where('u.user_id <> %i', ANONYMOUS)
    ->where('s.session_time >= %i', $time->getTimestamp())
    ->orderBy('u.user_id', dibi::DESC)
    ->groupBy('u.user_id')
    ->fetchAll();

$guestUsers = dibi::select(['session_page', 'session_logged_in', 'session_time', 'session_ip', 'session_start'])
    ->from(SESSIONS_TABLE)
    ->where('session_logged_in = %i', 0)
    ->where('session_time >= %i', $time->getTimestamp())
    ->orderBy('session_time', 'DESC')
    ->fetchAll();

$forums = dibi::select(['forum_name', 'forum_id'])
    ->from(FORUMS_TABLE)
    ->fetchPairs('forum_id', 'forum_name');

if (count($registeredUsers)) {
    foreach ($registeredUsers as $i => $registeredUser) {
        // check if user does some special action
        // if he is in some forum
        if ($registeredUser->session_page < 1) {
            $location_url = 'index.php?pane=right';

            $location = getForumLocation($registeredUser->user_session_page, $lang);
        } else {
            $location_url = Session::appendSid('admin_forums.php?mode=editforum&amp;' . POST_FORUM_URL . '=' . $registeredUser->user_session_page);
            $location = $forums[$registeredUser->user_session_page];
        }

        $row_color = ($i % 2) ? $theme['td_color1'] : $theme['td_color2'];
        $row_class = ($i % 2) ? $theme['td_class1'] : $theme['td_class2'];

        $template->assignBlockVars('reg_user_row',
            [
                'ROW_COLOR'  => '#' . $row_color,
                'ROW_CLASS'  => $row_class,
                'USERNAME'   => $registeredUser->username,
                'STARTED'    => create_date($board_config['default_dateformat'], $registeredUser->session_start, $board_config['board_timezone']),
                'LASTUPDATE' => create_date($board_config['default_dateformat'], $registeredUser->user_session_time, $board_config['board_timezone']),
                'FORUM_LOCATION' => $location,
                'IP_ADDRESS' => decode_ip($registeredUser->session_ip),

                'U_USER_PROFILE' => Session::appendSid('admin_users.php?mode=edit&amp;' . POST_USERS_URL . '=' . $registeredUser->user_id),
                'U_FORUM_LOCATION' => Session::appendSid($location_url)
            ]
        );
    }
} else {
    $template->assignVars(['L_NO_REGISTERED_USERS_BROWSING' => $lang['No_users_browsing']]);
}

//
// Guest users
//
if (count($guestUsers)) {
    $guest_users = 0;

    foreach ($guestUsers as $i => $guest) {
        // check if user does some special action
        // if he is in some forum
        if ($guest->session_page < 1) {
            $location_url = 'index.php?pane=right';

            $location = getForumLocation($guest->session_page, $lang);
        } else {
            $location_url = Session::appendSid('admin_forums.php?mode=editforum&amp;' . POST_FORUM_URL . '=' . $guest->session_page);

            $location = $forums[$guest->session_page];
        }

        $row_color = ($i % 2) ? $theme['td_color1'] : $theme['td_color2'];
        $row_class = ($i % 2) ? $theme['td_class1'] : $theme['td_class2'];

        $template->assignBlockVars('guest_user_row', [
                'ROW_COLOR'      => '#' . $row_color,
                'ROW_CLASS'      => $row_class,
                'USERNAME'       => $lang['Guest'],
                'STARTED'        => create_date($board_config['default_dateformat'], $guest->session_start, $board_config['board_timezone']),
                'LASTUPDATE'     => create_date($board_config['default_dateformat'], $guest->session_time, $board_config['board_timezone']),
                'FORUM_LOCATION' => $location,
                'IP_ADDRESS'     => decode_ip($guest->session_ip),

                'U_FORUM_LOCATION' => Session::appendSid($location_url)
            ]
        );
    }
} else {
    $template->assignVars(['L_NO_GUESTS_BROWSING' => $lang['No_users_browsing']]);
}

$template->pparse('body');

require_once '.' . $sep . 'page_footer_admin.php';