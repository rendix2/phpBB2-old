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
$phpbb_root_path = './';

include $phpbb_root_path . 'common.php';

//
// Start session management
//
$userdata = session_pagestart($user_ip, PAGE_VIEWMEMBERS);
init_userprefs($userdata);
//
// End session management
//

$start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
$start = $start < 0 ? 0 : $start;

if (isset($_GET['mode']) || isset($_POST['mode'])) {
    $mode = isset($_POST['mode']) ? htmlspecialchars($_POST['mode']) : htmlspecialchars($_GET['mode']);
} else {
    $mode = 'joined';
}

if (isset($_POST['order'])) {
    $sort_order = ($_POST['order'] === 'ASC') ? 'ASC' : 'DESC';
} elseif (isset($_GET['order'])) {
    $sort_order = ($_GET['order'] === 'ASC') ? 'ASC' : 'DESC';
} else {
    $sort_order = 'ASC';
}

//
// Memberlist sorting
//
$mode_types = [
    'joined'   => $lang['Sort_Joined'],
    'username' => $lang['Sort_Username'],
    'location' => $lang['Sort_Location'],
    'posts'    => $lang['Sort_Posts'],
    'email'    => $lang['Sort_Email'],
    'website'  => $lang['Sort_Website'],
    'topten'   => $lang['Sort_Top_Ten']
];

$select_sort_mode = '<select name="mode">';

$count_mode_type_text = count($mode_types);

foreach ($mode_types as $mode_type_key => $mode_types_value) {
	$selected = ( $mode === $mode_type_key ) ? ' selected="selected"' : '';
	$select_sort_mode .= '<option value="' . $mode_type_key . '"' . $selected . '>' . $mode_types_value . '</option>';
}

$select_sort_mode .= '</select>';
$select_sort_order = '<select name="order">';

if ($sort_order === 'ASC') {
	$select_sort_order .= '<option value="ASC" selected="selected">' . $lang['Sort_Ascending'] . '</option><option value="DESC">' . $lang['Sort_Descending'] . '</option>';
} else {
	$select_sort_order .= '<option value="ASC">' . $lang['Sort_Ascending'] . '</option><option value="DESC" selected="selected">' . $lang['Sort_Descending'] . '</option>';
}

$select_sort_order .= '</select>';

//
// Generate page
//
$page_title = $lang['Memberlist'];

include $phpbb_root_path . 'includes/page_header.php';

$template->set_filenames(['body' => 'memberlist_body.tpl']);

make_jumpbox('viewforum.php');

$template->assign_vars(
    [
        'L_SELECT_SORT_METHOD' => $lang['Select_sort_method'],
        'L_EMAIL'              => $lang['Email'],
        'L_WEBSITE'            => $lang['Website'],
        'L_FROM'               => $lang['Location'],
        'L_ORDER'              => $lang['Order'],
        'L_SORT'               => $lang['Sort'],
        'L_SUBMIT'             => $lang['Sort'],
        'L_AIM'                => $lang['AIM'],
        'L_YIM'                => $lang['YIM'],
        'L_MSNM'               => $lang['MSNM'],
        'L_ICQ'                => $lang['ICQ'],
        'L_JOINED'             => $lang['Joined'],
        'L_POSTS'              => $lang['Posts'],
        'L_PM'                 => $lang['Private_Message'],

        'S_MODE_SELECT'  => $select_sort_mode,
        'S_ORDER_SELECT' => $select_sort_order,
        'S_MODE_ACTION'  => append_sid("memberlist.php")
    ]
);

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
    'user_msnm',
    'user_avatar',
    'user_avatar_type',
    'user_allowavatar'
];

$users = dibi::select($columns)
    ->from(USERS_TABLE)
    ->where('user_id <> %i', ANONYMOUS);

switch ($mode) {
    case 'joined':
        $users->orderBy('user_regdate', $sort_order)
        ->limit($board_config['members_per_page'])
        ->offset($start);
        break;
    case 'username':
        $users->orderBy('username', $sort_order)
            ->limit($board_config['members_per_page'])
            ->offset($start);
        break;
    case 'location':
        $users->orderBy('user_from', $sort_order)
            ->limit($board_config['members_per_page'])
            ->offset($start);
        break;
    case 'posts':
        $users->orderBy('user_posts', $sort_order)
            ->limit($board_config['members_per_page'])
            ->offset($start);
        break;
    case 'email':
        $users->orderBy('user_email', $sort_order)
            ->limit($board_config['members_per_page'])
            ->offset($start);
        break;
    case 'website':
        $users->orderBy('user_website', $sort_order)
            ->limit($board_config['members_per_page'])
            ->offset($start);
        break;
    case 'topten':
        $users->orderBy('user_posts', $sort_order)
            ->limit($board_config['members_per_page']);
        break;
    default:
        $users->orderBy('user_regdate', $sort_order)
            ->limit($board_config['members_per_page'])
            ->offset($start);
        break;
}

$users = $users->fetchAll();

	$i = 0;
	
	foreach ($users as $user) {
		$username = $user->username;
		$user_id = $user->user_id;

		$from = !empty($user->user_from) ? $user->user_from : '&nbsp;';
		$joined = create_date($lang['DATE_FORMAT'], $user->user_regdate, $board_config['board_timezone']);
		$posts = $user->user_posts ? $user->user_posts : 0;

		$poster_avatar = '';

        if ($user->user_avatar_type && $user_id !== ANONYMOUS && $user->user_allowavatar) {
            switch ($user->user_avatar_type) {
				case USER_AVATAR_UPLOAD:
					$poster_avatar = $board_config['allow_avatar_upload'] ? '<img src="' . $board_config['avatar_path'] . '/' . $user->user_avatar . '" alt="" border="0" />' : '';
					break;
				case USER_AVATAR_REMOTE:
					$poster_avatar = $board_config['allow_avatar_remote'] ? '<img src="' . $user->user_avatar . '" alt="" border="0" />' : '';
					break;
				case USER_AVATAR_GALLERY:
					$poster_avatar = $board_config['allow_avatar_local'] ? '<img src="' . $board_config['avatar_gallery_path'] . '/' . $user->user_avatar . '" alt="" border="0" />' : '';
					break;
			}
		}

        if (!empty($user->user_viewemail) || $userdata['user_level'] === ADMIN) {
			$email_uri = $board_config['board_email_form'] ? append_sid("profile.php?mode=email&amp;" . POST_USERS_URL .'=' . $user_id) : 'mailto:' . $user->user_email;

			$email_img = '<a href="' . $email_uri . '"><img src="' . $images['icon_email'] . '" alt="' . $lang['Send_email'] . '" title="' . $lang['Send_email'] . '" border="0" /></a>';
			$email = '<a href="' . $email_uri . '">' . $lang['Send_email'] . '</a>';
		} else {
			$email_img = '&nbsp;';
			$email = '&nbsp;';
		}

		$temp_url = append_sid("profile.php?mode=viewprofile&amp;" . POST_USERS_URL . "=$user_id");
		$profile_img = '<a href="' . $temp_url . '"><img src="' . $images['icon_profile'] . '" alt="' . $lang['Read_profile'] . '" title="' . $lang['Read_profile'] . '" border="0" /></a>';
		$profile = '<a href="' . $temp_url . '">' . $lang['Read_profile'] . '</a>';

		$temp_url = append_sid("privmsg.php?mode=post&amp;" . POST_USERS_URL . "=$user_id");
		$pm_img = '<a href="' . $temp_url . '"><img src="' . $images['icon_pm'] . '" alt="' . $lang['Send_private_message'] . '" title="' . $lang['Send_private_message'] . '" border="0" /></a>';
		$pm = '<a href="' . $temp_url . '">' . $lang['Send_private_message'] . '</a>';

		$www_img = $user->user_website ? '<a href="' . $user->user_website . '" target="_userwww"><img src="' . $images['icon_www'] . '" alt="' . $lang['Visit_website'] . '" title="' . $lang['Visit_website'] . '" border="0" /></a>' : '';
		$www = $user->user_website ? '<a href="' . $user->user_website . '" target="_userwww">' . $lang['Visit_website'] . '</a>' : '';

        if (!empty($user->user_icq)) {
			$icq_status_img = '<a href="http://wwp.icq.com/' . $user->user_icq . '#pager"><img src="http://web.icq.com/whitepages/online?icq=' . $user->user_icq . '&img=5" width="18" height="18" border="0" /></a>';
			$icq_img = '<a href="http://wwp.icq.com/scripts/search.dll?to=' . $user->user_icq . '"><img src="' . $images['icon_icq'] . '" alt="' . $lang['ICQ'] . '" title="' . $lang['ICQ'] . '" border="0" /></a>';
			$icq =  '<a href="http://wwp.icq.com/scripts/search.dll?to=' . $user->user_icq . '">' . $lang['ICQ'] . '</a>';
		} else {
			$icq_status_img = '';
			$icq_img = '';
			$icq = '';
		}

		$aim_img = $user->user_aim ? '<a href="aim:goim?screenname=' . $user->user_aim . '&amp;message=Hello+Are+you+there?"><img src="' . $images['icon_aim'] . '" alt="' . $lang['AIM'] . '" title="' . $lang['AIM'] . '" border="0" /></a>' : '';
		$aim = $user->user_aim ? '<a href="aim:goim?screenname=' . $user->user_aim . '&amp;message=Hello+Are+you+there?">' . $lang['AIM'] . '</a>' : '';

		$temp_url = append_sid("profile.php?mode=viewprofile&amp;" . POST_USERS_URL . "=$user_id");
		$msn_img = $user->user_msnm ? '<a href="' . $temp_url . '"><img src="' . $images['icon_msnm'] . '" alt="' . $lang['MSNM'] . '" title="' . $lang['MSNM'] . '" border="0" /></a>' : '';
		$msn = $user->user_msnm ? '<a href="' . $temp_url . '">' . $lang['MSNM'] . '</a>' : '';

		$yim_img = $user->user_yim ? '<a href="http://edit.yahoo.com/config/send_webmesg?.target=' . $user->user_yim . '&amp;.src=pg"><img src="' . $images['icon_yim'] . '" alt="' . $lang['YIM'] . '" title="' . $lang['YIM'] . '" border="0" /></a>' : '';
		$yim = $user->user_yim ? '<a href="http://edit.yahoo.com/config/send_webmesg?.target=' . $user->user_yim . '&amp;.src=pg">' . $lang['YIM'] . '</a>' : '';

		$temp_url = append_sid("search.php?search_author=" . urlencode($username) . "&amp;showresults=posts");
		$search_img = '<a href="' . $temp_url . '"><img src="' . $images['icon_search'] . '" alt="' . sprintf($lang['Search_user_posts'], $username) . '" title="' . sprintf($lang['Search_user_posts'], $username) . '" border="0" /></a>';
		$search = '<a href="' . $temp_url . '">' . sprintf($lang['Search_user_posts'], $username) . '</a>';

		$row_color = ( !($i % 2) ) ? $theme['td_color1'] : $theme['td_color2'];
		$row_class = ( !($i % 2) ) ? $theme['td_class1'] : $theme['td_class2'];

        $template->assign_block_vars('memberrow',
            [
                'ROW_NUMBER'     => $i + ($start + 1),
                'ROW_COLOR'      => '#' . $row_color,
                'ROW_CLASS'      => $row_class,
                'USERNAME'       => $username,
                'FROM'           => $from,
                'JOINED'         => $joined,
                'POSTS'          => $posts,
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

                'U_VIEWPROFILE' => append_sid("profile.php?mode=viewprofile&amp;" . POST_USERS_URL . "=$user_id")
            ]
        );

        $i++;
	}


if ($mode !== 'topten' || $board_config['members_per_page'] < 10) {
    $total_members = dibi::select('COUNT(*)')
        ->as('total')
        ->from(USERS_TABLE)
        ->where('user_id <> %i', ANONYMOUS)
        ->fetchSingle();

    if ($total_members) {
        $pagination = generate_pagination("memberlist.php?mode=$mode&amp;order=$sort_order", $total_members, $board_config['members_per_page'], $start) . '&nbsp;';
    }
} else {
    $pagination = '&nbsp;';
    $total_members = 10;
}

$template->assign_vars(
    [
        'PAGINATION'  => $pagination,
        'PAGE_NUMBER' => sprintf($lang['Page_of'], floor($start / $board_config['members_per_page']) + 1, ceil($total_members / $board_config['members_per_page'])),

        'L_GOTO_PAGE' => $lang['Goto_page']
    ]
);

$template->pparse('body');

include $phpbb_root_path . 'includes/page_tail.php';

?>