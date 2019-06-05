<?php
/***************************************************************************
 *                           usercp_viewprofile.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: usercp_viewprofile.php 5204 2005-09-14 18:14:30Z acydburn $
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
 *
 ***************************************************************************/

if ( !defined('IN_PHPBB') ) {
	die("Hacking attempt");
}

if ( empty($_GET[POST_USERS_URL]) || $_GET[POST_USERS_URL] === ANONYMOUS ) {
	message_die(GENERAL_MESSAGE, $lang['No_user_id_specified']);
}

$profile_data = get_userdata($_GET[POST_USERS_URL]);

if (!$profile_data) {
	message_die(GENERAL_MESSAGE, $lang['No_user_id_specified']);
}

$ranks = dibi::select('*')
    ->from(RANKS_TABLE)
    ->orderBy('rank_special')
    ->orderBy('rank_min')
    ->fetchAll();

//
// Output page header and profile_view template
//
$template->set_filenames(['body' => 'profile_view_body.tpl']);
make_jumpbox('viewforum.php');

//
// Calculate the number of days this user has been a member ($memberdays)
// Then calculate their posts per day
//
$regdate = $profile_data['user_regdate'];
$memberdays = max(1, round( ( time() - $regdate ) / 86400 ));
$posts_per_day = $profile_data['user_posts'] / $memberdays;

// Get the users percentage of total posts
if ($profile_data['user_posts'] !== 0  ) {
	$total_posts = get_db_stat('postcount');
	$percentage = $total_posts ? min(100, ($profile_data['user_posts'] / $total_posts) * 100) : 0;
} else {
	$percentage = 0;
}

$avatar_img = '';
if ($profile_data['user_avatar_type'] && $profile_data['user_allowavatar'] ) {
	switch($profile_data['user_avatar_type'] ) {
		case USER_AVATAR_UPLOAD:
			$avatar_img = $board_config['allow_avatar_upload'] ? '<img src="' . $board_config['avatar_path'] . '/' . $profile_data['user_avatar'] . '" alt="" border="0" />' : '';
			break;
		case USER_AVATAR_REMOTE:
			$avatar_img = $board_config['allow_avatar_remote'] ? '<img src="' . $profile_data['user_avatar'] . '" alt="" border="0" />' : '';
			break;
		case USER_AVATAR_GALLERY:
			$avatar_img = $board_config['allow_avatar_local'] ? '<img src="' . $board_config['avatar_gallery_path'] . '/' . $profile_data['user_avatar'] . '" alt="" border="0" />' : '';
			break;
	}
}

$poster_rank = '';
$rank_image = '';

if ($profile_data['user_rank'] ) {
    foreach ($ranks as $rank) {
        if ($profile_data['user_rank'] === $rank->rank_id && $rank->rank_special ) {
            $poster_rank = $rank->rank_title;
            $rank_image = $rank->rank_image ? '<img src="' . $rank->rank_image . '" alt="' . $poster_rank . '" title="' . $poster_rank . '" border="0" /><br />' : '';
        }
    }
} else {
    foreach ($ranks as $rank) {
        if ($profile_data['user_posts'] >= $rank->rank_min && !$rank->rank_special ) {
            $poster_rank = $rank->rank_title;
            $rank_image = $rank->rank_image ? '<img src="' . $rank->rank_image . '" alt="' . $poster_rank . '" title="' . $poster_rank . '" border="0" /><br />' : '';
        }
    }
}

$temp_url = append_sid("privmsg.php?mode=post&amp;" . POST_USERS_URL . "=" . $profile_data['user_id']);
$pm_img = '<a href="' . $temp_url . '"><img src="' . $images['icon_pm'] . '" alt="' . $lang['Send_private_message'] . '" title="' . $lang['Send_private_message'] . '" border="0" /></a>';
$pm = '<a href="' . $temp_url . '">' . $lang['Send_private_message'] . '</a>';

if ( !empty($profile_data['user_viewemail']) || $userdata['user_level'] === ADMIN ) {
	$email_uri = $board_config['board_email_form'] ? append_sid("profile.php?mode=email&amp;" . POST_USERS_URL .'=' . $profile_data['user_id']) : 'mailto:' . $profile_data['user_email'];

	$email_img = '<a href="' . $email_uri . '"><img src="' . $images['icon_email'] . '" alt="' . $lang['Send_email'] . '" title="' . $lang['Send_email'] . '" border="0" /></a>';
	$email = '<a href="' . $email_uri . '">' . $lang['Send_email'] . '</a>';
} else {
	$email_img = '&nbsp;';
	$email = '&nbsp;';
}

$www_img = $profile_data['user_website'] ? '<a href="' . $profile_data['user_website'] . '" target="_userwww"><img src="' . $images['icon_www'] . '" alt="' . $lang['Visit_website'] . '" title="' . $lang['Visit_website'] . '" border="0" /></a>' : '&nbsp;';
$www = $profile_data['user_website'] ? '<a href="' . $profile_data['user_website'] . '" target="_userwww">' . $profile_data['user_website'] . '</a>' : '&nbsp;';

if ( !empty($profile_data['user_icq']) ) {
	$icq_status_img = '<a href="http://wwp.icq.com/' . $profile_data['user_icq'] . '#pager"><img src="http://web.icq.com/whitepages/online?icq=' . $profile_data['user_icq'] . '&img=5" width="18" height="18" border="0" /></a>';
	$icq_img = '<a href="http://wwp.icq.com/scripts/search.dll?to=' . $profile_data['user_icq'] . '"><img src="' . $images['icon_icq'] . '" alt="' . $lang['ICQ'] . '" title="' . $lang['ICQ'] . '" border="0" /></a>';
	$icq =  '<a href="http://wwp.icq.com/scripts/search.dll?to=' . $profile_data['user_icq'] . '">' . $lang['ICQ'] . '</a>';
} else {
	$icq_status_img = '&nbsp;';
	$icq_img = '&nbsp;';
	$icq = '&nbsp;';
}

$aim_img = $profile_data['user_aim'] ? '<a href="aim:goim?screenname=' . $profile_data['user_aim'] . '&amp;message=Hello+Are+you+there?"><img src="' . $images['icon_aim'] . '" alt="' . $lang['AIM'] . '" title="' . $lang['AIM'] . '" border="0" /></a>' : '&nbsp;';
$aim = $profile_data['user_aim'] ? '<a href="aim:goim?screenname=' . $profile_data['user_aim'] . '&amp;message=Hello+Are+you+there?">' . $lang['AIM'] . '</a>' : '&nbsp;';

$msn_img = $profile_data['user_msnm'] ? $profile_data['user_msnm'] : '&nbsp;';
$msn = $msn_img;

$yim_img = $profile_data['user_yim'] ? '<a href="http://edit.yahoo.com/config/send_webmesg?.target=' . $profile_data['user_yim'] . '&amp;.src=pg"><img src="' . $images['icon_yim'] . '" alt="' . $lang['YIM'] . '" title="' . $lang['YIM'] . '" border="0" /></a>' : '';
$yim = $profile_data['user_yim'] ? '<a href="http://edit.yahoo.com/config/send_webmesg?.target=' . $profile_data['user_yim'] . '&amp;.src=pg">' . $lang['YIM'] . '</a>' : '';

$temp_url = append_sid("search.php?search_author=" . urlencode($profile_data['username']) . "&amp;showresults=posts");
$search_img = '<a href="' . $temp_url . '"><img src="' . $images['icon_search'] . '" alt="' . sprintf($lang['Search_user_posts'], $profile_data['username']) . '" title="' . sprintf($lang['Search_user_posts'], $profile_data['username']) . '" border="0" /></a>';
$search = '<a href="' . $temp_url . '">' . sprintf($lang['Search_user_posts'], $profile_data['username']) . '</a>';

//
// Generate page
//
$page_title = $lang['Viewing_profile'];
include $phpbb_root_path . 'includes/page_header.php';

if (function_exists('get_html_translation_table')) {
	$u_search_author = urlencode(strtr($profile_data['username'], array_flip(get_html_translation_table(HTML_ENTITIES))));
} else {
    $u_search_author = urlencode(str_replace(['&amp;', '&#039;', '&quot;', '&lt;', '&gt;'], ['&', "'", '"', '<', '>'], $profile_data['username']));
}

$template->assign_vars(array(
        'USERNAME' => $profile_data['username'],
        'JOINED' => create_date($lang['DATE_FORMAT'], $profile_data['user_regdate'], $board_config['board_timezone']),
        'POSTER_RANK' => $poster_rank,
        'RANK_IMAGE' => $rank_image,
        'POSTS_PER_DAY' => $posts_per_day,
        'POSTS' => $profile_data['user_posts'],
        'PERCENTAGE' => $percentage . '%',
        'POST_DAY_STATS' => sprintf($lang['User_post_day_stats'], $posts_per_day),
        'POST_PERCENT_STATS' => sprintf($lang['User_post_pct_stats'], $percentage),

        'SEARCH_IMG' => $search_img,
        'SEARCH' => $search,
        'PM_IMG' => $pm_img,
        'PM' => $pm,
        'EMAIL_IMG' => $email_img,
        'EMAIL' => $email,
        'WWW_IMG' => $www_img,
        'WWW' => $www,
        'ICQ_STATUS_IMG' => $icq_status_img,
        'ICQ_IMG' => $icq_img,
        'ICQ' => $icq,
        'AIM_IMG' => $aim_img,
        'AIM' => $aim,
        'MSN_IMG' => $msn_img,
        'MSN' => $msn,
        'YIM_IMG' => $yim_img,
        'YIM' => $yim,

        'LOCATION' => $profile_data['user_from'] ? $profile_data['user_from'] : '&nbsp;',
        'OCCUPATION' => $profile_data['user_occ'] ? $profile_data['user_occ'] : '&nbsp;',
        'INTERESTS' => $profile_data['user_interests'] ? $profile_data['user_interests'] : '&nbsp;',
        'AVATAR_IMG' => $avatar_img,

        'L_VIEWING_PROFILE' => sprintf($lang['Viewing_user_profile'], $profile_data['username']),
        'L_ABOUT_USER' => sprintf($lang['About_user'], $profile_data['username']),
        'L_AVATAR' => $lang['Avatar'],
        'L_POSTER_RANK' => $lang['Poster_rank'],
        'L_JOINED' => $lang['Joined'],
        'L_TOTAL_POSTS' => $lang['Total_posts'],
        'L_SEARCH_USER_POSTS' => sprintf($lang['Search_user_posts'], $profile_data['username']),
        'L_CONTACT' => $lang['Contact'],
        'L_EMAIL_ADDRESS' => $lang['Email_address'],
        'L_EMAIL' => $lang['Email'],
        'L_PM' => $lang['Private_Message'],
        'L_ICQ_NUMBER' => $lang['ICQ'],
        'L_YAHOO' => $lang['YIM'],
        'L_AIM' => $lang['AIM'],
        'L_MESSENGER' => $lang['MSNM'],
        'L_WEBSITE' => $lang['Website'],
        'L_LOCATION' => $lang['Location'],
        'L_OCCUPATION' => $lang['Occupation'],
        'L_INTERESTS' => $lang['Interests'],

        'U_SEARCH_USER' => append_sid("search.php?search_author=" . $u_search_author),

        'S_PROFILE_ACTION' => append_sid("profile.php"))
);

$template->pparse('body');

include $phpbb_root_path . 'includes/page_tail.php';

?>