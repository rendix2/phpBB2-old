<?php
/***************************************************************************
 *                              memberlist.php
 *                            -------------------
 *   begin                : Friday, May 11, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: memberlist.php 6772 2006-12-16 13:11:28Z acydburn $
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
$userdata = init_userprefs(PAGE_VIEW_MEMBERS);
//
// End session management
//

$start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
$start = $start < 0 ? 0 : $start;

if (isset($_GET[POST_MODE]) || isset($_POST[POST_MODE])) {
    $mode = isset($_POST[POST_MODE]) ? htmlspecialchars($_POST[POST_MODE]) : htmlspecialchars($_GET[POST_MODE]);
} else {
    $mode = 'joined';
}

if (isset($_POST['order'])) {
    $sortOrder = $_POST['order'] === 'ASC' ? 'ASC' : 'DESC';
} elseif (isset($_GET['order'])) {
    $sortOrder = $_GET['order'] === 'ASC' ? 'ASC' : 'DESC';
} else {
    $sortOrder = 'ASC';
}

//
// Memberlist sorting
//
$modeTypes = [
    'joined'   => $lang['Sort_Joined'],
    'username' => $lang['Sort_Username'],
    'location' => $lang['Sort_Location'],
    'posts'    => $lang['Sort_Posts'],
    'topics'   => $lang['Sort_Topics'],
    'thanks'   => $lang['Sort_Thanks'],
    'topics_watches' => $lang['Sort_Topics_watches'],
    'email'    => $lang['Sort_Email'],
    'website'  => $lang['Sort_Website'],
    'topten'   => $lang['Sort_Top_Ten'],
    'online'   => $lang['Sort_Online'],
];

$selectSortModeValues= '';

foreach ($modeTypes as $key => $value) {
	$selected       = $mode === $key ? 'selected="selected"' : '';
    $selectSortModeValues .= '<option value="' . $key . '" ' . $selected . '>' . $value . '</option>';
}

$selectSortMode  = '<select name="mode">' . $selectSortModeValues . '</select>';

$selectSortOrderValues = '';

if ($sortOrder === 'ASC') {
    $selectSortOrderValues .= '<option value="ASC" selected="selected">' . $lang['Sort_Ascending'] . '</option><option value="DESC">' . $lang['Sort_Descending'] . '</option>';
} else {
    $selectSortOrderValues .= '<option value="ASC">' . $lang['Sort_Ascending'] . '</option><option value="DESC" selected="selected">' . $lang['Sort_Descending'] . '</option>';
}

$selectSortOrder = '<select name="order">' . $selectSortOrderValues . '</select>';

//
// Generate page
//
PageHelper::header($template, $userdata, $board_config, $lang, $images, $theme, $lang['Memberlist'], $gen_simple_header);

$template->setFileNames(['body' => 'memberlist_body.tpl']);

make_jumpbox('viewforum.php');

$template->assignVars(
    [
        'L_SELECT_SORT_METHOD' => $lang['Select_sort_method'],
        'L_EMAIL'              => $lang['Email'],
        'L_WEBSITE'            => $lang['Website'],
        'L_FROM'               => $lang['Location'],
        'L_ORDER'              => $lang['Order'],
        'L_SORT'               => $lang['Sort'],
        'L_SUBMIT'             => $lang['Sort'],
        'L_JOINED'             => $lang['Joined'],
        'L_POSTS'              => $lang['Posts'],
        'L_TOPICS'             => $lang['Topics'],
        'L_THANKS'             => $lang['Thanks'],
        'L_TOPICS_WATCHES'     => $lang['Topic_watches'],
        'L_PM'                 => $lang['Private_Message'],

        'S_MODE_SELECT'  => $selectSortMode,
        'S_ORDER_SELECT' => $selectSortOrder,
        'S_MODE_ACTION'  => Session::appendSid('memberlist.php')
    ]
);

$columns = [
    'username',
    'user_id',
    'user_posts',
    'user_topics',
    'user_thanks',
    'user_topic_watches',
    'user_regdate',
    'user_from',
    'user_website',
    'user_email',
    'user_avatar',
    'user_avatar_type',
    'user_allowavatar',
    'user_allow_viewonline',
    'user_session_time'
];

$users = dibi::select($columns)
    ->from(Tables::USERS_TABLE)
    ->where('user_id <> %i', ANONYMOUS);

switch ($mode) {
    case 'joined':
    default:
        $users->orderBy('user_id', $sortOrder)
        ->limit($board_config['members_per_page'])
        ->offset($start);
        break;
    case 'username':
        $users->orderBy('username', $sortOrder)
            ->limit($board_config['members_per_page'])
            ->offset($start);
        break;
    case 'location':
        $users->orderBy('user_from', $sortOrder)
            ->limit($board_config['members_per_page'])
            ->offset($start);
        break;
    case 'posts':
        $users->orderBy('user_posts', $sortOrder)
            ->limit($board_config['members_per_page'])
            ->offset($start);
        break;
    case 'topics':
        $users->orderBy('user_topics', $sortOrder)
            ->limit($board_config['members_per_page'])
            ->offset($start);
        break;
    case 'thanks':
        $users->orderBy('user_thanks', $sortOrder)
            ->limit($board_config['members_per_page'])
            ->offset($start);
        break;
    case 'topics_watches':
        $users->orderBy('user_topic_watches', $sortOrder)
            ->limit($board_config['members_per_page'])
            ->offset($start);
        break;
    case 'email':
        $users->orderBy('user_email', $sortOrder)
            ->limit($board_config['members_per_page'])
            ->offset($start);
        break;
    case 'website':
        $users->orderBy('user_website', $sortOrder)
            ->limit($board_config['members_per_page'])
            ->offset($start);
        break;
    case 'topten':
        $users->orderBy('user_posts', $sortOrder)
            ->limit($board_config['members_per_page']);
        break;
    case 'online':
        $users->orderBy('user_session_time', $sortOrder)
            ->limit($board_config['members_per_page']);
        break;
}

$users = $users->fetchAll();

foreach ($users as $i => $user) {
    $from         = !empty($user->user_from) ? htmlspecialchars($user->user_from, ENT_QUOTES) : '&nbsp;';
    $posterAvatar = '';

    if ($user->user_avatar_type && $user->user_id !== ANONYMOUS && $user->user_allowavatar) {
        switch ($user->user_avatar_type) {
            case USER_AVATAR_UPLOAD:
                $posterAvatar = $board_config['allow_avatar_upload'] ? '<img src="' . $board_config['avatar_path'] . '/' . $user->user_avatar . '" alt="" border="0" />' : '';
                break;
            case USER_AVATAR_REMOTE:
                $posterAvatar = $board_config['allow_avatar_remote'] ? '<img src="' . $user->user_avatar . '" alt="" border="0" />' : '';
                break;
            case USER_AVATAR_GALLERY:
                $posterAvatar = $board_config['allow_avatar_local'] ? '<img src="' . $board_config['avatar_gallery_path'] . '/' . $user->user_avatar . '" alt="" border="0" />' : '';
                break;
        }
    }

    // <!-- BEGIN Another Online/Offline indicator -->
    if (!$user->user_allow_viewonline && $userdata['user_level'] === ADMIN || $user->user_allow_viewonline) {
        $expiry_time = time() - ONLINE_TIME_DIFF;

        if ($user->user_session_time >= $expiry_time) {
            $user_onlinestatus = '<img src="' . $images['Online'] . '" alt="' . $lang['Online'] . '" title="' . $lang['Online'] . '" border="0" />';

            if (!$user->user_allow_viewonline && $userdata['user_level'] === ADMIN) {
                $user_onlinestatus = '<img src="' . $images['Hidden_Admin'] . '" alt="' . $lang['Hidden'] . '" title="' . $lang['Hidden'] . '" border="0" />';
            }
        } else {
            $user_onlinestatus = '<img src="' . $images['Offline'] . '" alt="' . $lang['Offline'] . '" title="' . $lang['Offline'] . '" border="0" />';

            if (!$user->user_allow_viewonline && $userdata['user_level'] === ADMIN) {
                $user_onlinestatus = '<img src="' . $images['Offline'] . '" alt="' . $lang['Hidden'] . '" title="' . $lang['Hidden'] . '" border="0" />';
            }
        }
    } else {
        $user_onlinestatus = '<img src="' . $images['Offline'] . '" alt="' . $lang['Offline'] . '" title="' . $lang['Offline'] . '" border="0" />';
    }
    // <!-- END Another Online/Offline indicator -->

    if ($board_config['board_email_form'] || $userdata['user_level'] === ADMIN) {
        $emailUrl    = Session::appendSid('profile.php?mode=email&amp;' . POST_USERS_URL . '=' . $user->user_id);
        $emailImage = '<a href="' . $emailUrl . '"><img src="' . $images['icon_email'] . '" alt="' . $lang['Send_email'] . '" title="' . $lang['Send_email'] . '" border="0" /></a>';
        $email      = '<a href="' . $emailUrl . '">' . $lang['Send_email'] . '</a>';
    } else {
        $emailImage = '&nbsp;';
        $email      = '&nbsp;';
    }

    $profileUrl   = Session::appendSid('profile.php?mode=viewprofile&amp;' . POST_USERS_URL . "=$user->user_id");
    $profileImage = '<a href="' . $profileUrl . '"><img src="' . $images['icon_profile'] . '" alt="' . $lang['Read_profile'] . '" title="' . $lang['Read_profile'] . '" border="0" /></a>';
    $profile      = '<a href="' . $profileUrl . '">' . $lang['Read_profile'] . '</a>';

    $pmUrl   = Session::appendSid('privmsg.php?mode=post&amp;' . POST_USERS_URL . "=$user->user_id");
    $pmImage = '<a href="' . $pmUrl . '"><img src="' . $images['icon_pm'] . '" alt="' . $lang['Send_private_message'] . '" title="' . $lang['Send_private_message'] . '" border="0" /></a>';
    $pm      = '<a href="' . $pmUrl . '">' . $lang['Send_private_message'] . '</a>';

    $wwwImage = $user->user_website ? '<a href="' . $user->user_website . '" target="_userwww"><img src="' . $images['icon_www'] . '" alt="' . $lang['Visit_website'] . '" title="' . $lang['Visit_website'] . '" border="0" /></a>' : '';
    $www      = $user->user_website ? '<a href="' . $user->user_website . '" target="_userwww">' . $lang['Visit_website'] . '</a>' : '';

    $searchUrl   = Session::appendSid('search.php?search_author=' . urlencode($user->username) . '&amp;show_results=posts');
    $searchImage = '<a href="' . $searchUrl . '"><img src="' . $images['icon_search'] . '" alt="' . sprintf($lang['Search_user_posts'], $user->username) . '" title="' . sprintf($lang['Search_user_posts'], $user->username) . '" border="0" /></a>';
    $search      = '<a href="' . $searchUrl . '">' . sprintf($lang['Search_user_posts'], $user->username) . '</a>';

    $rowColor = !($i % 2) ? $theme['td_color1'] : $theme['td_color2'];
    $rowClass = !($i % 2) ? $theme['td_class1'] : $theme['td_class2'];

    $template->assignBlockVars('memberrow',
        [
            'ROW_NUMBER' => $i + ($start + 1),
            'ROW_COLOR' => '#' . $rowColor,
            'ROW_CLASS' => $rowClass,
            'USERNAME' => $user->username,

            // <!-- BEGIN Another Online/Offline indicator -->
            'ONLINESTATUS' => $user_onlinestatus,
            // <!-- END Another Online/Offline indicator -->

            'FROM' => $from,
            'JOINED' => create_date($lang['DATE_FORMAT'], $user->user_regdate, $board_config['board_timezone']),
            'POSTS' => $user->user_posts ? $user->user_posts : 0,
            'TOPICS' => $user->user_topics ? $user->user_topics : 0,
            'THANKS' => $user->user_thanks ? $user->user_thanks : 0,
            'TOPIC_WATCHES' => $user->user_topic_watches ? $user->user_topic_watches : 0,

            'AVATAR_IMG' => $posterAvatar,
            'PROFILE_IMG' => $profileImage,

            'PROFILE' => $profile,
            'SEARCH_IMG' => $searchImage,

            'SEARCH' => $search,
            'PM_IMG' => $pmImage,
            'PM' => $pm,

            'EMAIL_IMG' => $emailImage,
            'EMAIL' => $email,

            'WWW_IMG' => $wwwImage,
            'WWW' => $www,

            'U_VIEWPROFILE' => Session::appendSid('profile.php?mode=viewprofile&amp;' . POST_USERS_URL . "=$user->user_id")
        ]
    );
}

if ($mode !== 'topten' || $board_config['members_per_page'] < 10) {
    $total_members = dibi::select('COUNT(*) - 1')
        ->as('total')
        ->from(Tables::USERS_TABLE)
        ->fetchSingle();

    if ($total_members) {
        $pagination = generate_pagination("memberlist.php?mode=$mode&amp;order=$sortOrder", $total_members, $board_config['members_per_page'], $start) . '&nbsp;';
    }
} else {
    $pagination = '&nbsp;';
    $total_members = 10;
}

$template->assignVars(
    [
        'PAGINATION'  => $pagination,
        'PAGE_NUMBER' => sprintf($lang['Page_of'], floor($start / $board_config['members_per_page']) + 1, ceil($total_members / $board_config['members_per_page'])),

        'L_GOTO_PAGE' => $lang['Goto_page']
    ]
);

$template->pparse('body');

PageHelper::footer($template, $userdata, $lang, $gen_simple_header);

?>