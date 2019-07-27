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

use Nette\Caching\Cache;

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 *
 ***************************************************************************/

if (!defined('IN_PHPBB')) {
	die('Hacking attempt');
}

if (!isset($_GET[POST_USERS_URL])) {
    message_die(GENERAL_MESSAGE, $lang['No_user_id_specified']);
}

if (!is_numeric($_GET[POST_USERS_URL])) {
    message_die(GENERAL_MESSAGE, $lang['No_user_id_specified']);
}

if ($_GET[POST_USERS_URL] === ANONYMOUS) {
    message_die(GENERAL_MESSAGE, $lang['No_user_id_specified']);
}

$profileData = get_userdata($_GET[POST_USERS_URL]);

if (!$profileData) {
	message_die(GENERAL_MESSAGE, $lang['No_user_id_specified']);
}

$cache = new Cache($storage, RANKS_TABLE);
$key   = RANKS_TABLE . '_ordered_by_rank_special_rank_min';
$sep   = DIRECTORY_SEPARATOR;

$cachedRanks = $cache->load($key);

if ($cachedRanks !== null) {
    $ranks = $cachedRanks;
} else {
    $ranks = dibi::select('*')
        ->from(RANKS_TABLE)
        ->orderBy('rank_special')
        ->orderBy('rank_min')
        ->fetchAll();

    $cache->save($key, $ranks);
}

//
// Output page header and profile_view template
//
$template->setFileNames(['body' => 'profile_view_body.tpl']);
make_jumpbox('viewforum.php');

//
// Calculate the number of days this user has been a member ($memberdays)
// Then calculate their posts per day
//
$userTimezone = isset($profileData->user_timezone) ? $profileData->user_timezone : $board_config['board_timezone'];

$zone = new DateTimeZone($userTimezone);

$regdate = new DateTime();
$regdate->setTimezone($zone);
$regdate->setTimestamp($profileData->user_regdate);

$memberdays = new DateTime('now', $zone);
$memberdays = $memberdays->diff($regdate)->days;

$postsPerDay  = $profileData->user_posts / $memberdays;
$topicsPerDay = $profileData->user_topics / $memberdays;

// Get the users percentage of total posts
if ($profileData->user_posts !== 0) {
    $totalPosts      = get_db_stat('postcount');
    $percentagePosts = $totalPosts ? min(100, ($profileData->user_posts / $totalPosts) * 100) : 0;
} else {
    $percentagePosts = 0;
}

// Get the users percentage of total topics
if ($profileData->user_topics !== 0) {
    $totalTopics = get_db_stat('topiccount');
    $percentageTopics= $totalTopics ? min(100, ($profileData->user_topics / $totalTopics) * 100) : 0;
} else {
    $percentageTopics = 0;
}

$avatar_img = '';

if ($profileData->user_avatar_type && $profileData->user_allowavatar) {
	switch($profileData->user_avatar_type) {
		case USER_AVATAR_UPLOAD:
			$avatar_img = $board_config['allow_avatar_upload'] ? '<img src="' . $board_config['avatar_path'] . '/' . $profileData->user_avatar . '" alt="" border="0" />' : '';
			break;
		case USER_AVATAR_REMOTE:
			$avatar_img = $board_config['allow_avatar_remote'] ? '<img src="' . $profileData->user_avatar . '" alt="" border="0" />' : '';
			break;
		case USER_AVATAR_GALLERY:
			$avatar_img = $board_config['allow_avatar_local'] ? '<img src="' . $board_config['avatar_gallery_path'] . '/' . $profileData->user_avatar . '" alt="" border="0" />' : '';
			break;
	}
}

$poster_rank = '';
$rank_image = '';

if ($profileData->user_rank) {
    foreach ($ranks as $rank) {
        if ($profileData->user_rank === $rank->rank_id && $rank->rank_special) {
            $poster_rank = $rank->rank_title;
            $rank_image = $rank->rank_image ? '<img src="' . $rank->rank_image . '" alt="' . $poster_rank . '" title="' . $poster_rank . '" border="0" /><br />' : '';
        }
    }
} else {
    foreach ($ranks as $rank) {
        if ($profileData->user_posts >= $rank->rank_min && !$rank->rank_special) {
            $poster_rank = $rank->rank_title;
            $rank_image = $rank->rank_image ? '<img src="' . $rank->rank_image . '" alt="' . $poster_rank . '" title="' . $poster_rank . '" border="0" /><br />' : '';
        }
    }
}

$temp_url = Session::appendSid('privmsg.php?mode=post&amp;' . POST_USERS_URL . '=' . $profileData->user_id);
$pm_img = '<a href="' . $temp_url . '"><img src="' . $images['icon_pm'] . '" alt="' . $lang['Send_private_message'] . '" title="' . $lang['Send_private_message'] . '" border="0" /></a>';
$pm = '<a href="' . $temp_url . '">' . $lang['Send_private_message'] . '</a>';

if (!empty($profileData->user_viewemail) || $userdata['user_level'] === ADMIN) {
	$email_uri = $board_config['board_email_form'] ? Session::appendSid('profile.php?mode=email&amp;' . POST_USERS_URL .'=' . $profileData->user_id) : 'mailto:' . $profileData->user_email;

	$email_img = '<a href="' . $email_uri . '"><img src="' . $images['icon_email'] . '" alt="' . $lang['Send_email'] . '" title="' . $lang['Send_email'] . '" border="0" /></a>';
	$email = '<a href="' . $email_uri . '">' . $lang['Send_email'] . '</a>';
} else {
	$email_img = '&nbsp;';
	$email = '&nbsp;';
}

$www_img = $profileData->user_website ? '<a href="' . $profileData->user_website . '" target="_userwww"><img src="' . $images['icon_www'] . '" alt="' . $lang['Visit_website'] . '" title="' . $lang['Visit_website'] . '" border="0" /></a>' : '&nbsp;';
$www     = $profileData->user_website ? '<a href="' . $profileData->user_website . '" target="_userwww">' . $profileData->user_website . '</a>' : '&nbsp;';

$temp_url = Session::appendSid('search.php?search_author=' . urlencode($profileData->username) . '&amp;show_results=posts');
$search_img = '<a href="' . $temp_url . '"><img src="' . $images['icon_search'] . '" alt="' . sprintf($lang['Search_user_posts'], $profileData->username) . '" title="' . sprintf($lang['Search_user_posts'], $profileData->username) . '" border="0" /></a>';
$search = '<a href="' . $temp_url . '">' . sprintf($lang['Search_user_posts'], $profileData->username) . '</a>';

//
// Generate page
//
$page_title = $lang['Viewing_profile'];

require_once $phpbb_root_path . 'includes' . $sep . 'page_header.php';

if (function_exists('get_html_translation_table')) {
	$u_search_author = urlencode(strtr($profileData->username, array_flip(get_html_translation_table(HTML_ENTITIES))));
} else {
    $u_search_author = urlencode(str_replace(['&amp;', '&#039;', '&quot;', '&lt;', '&gt;'], ['&', "'", '"', '<', '>'], $profileData->username));
}

$template->assignVars(
    [
        'USERNAME' => $profileData->username,
        'JOINED' => create_date($lang['DATE_FORMAT'], $profileData->user_regdate, $board_config['board_timezone']),
        'POSTER_RANK' => htmlspecialchars($poster_rank, ENT_QUOTES),
        'RANK_IMAGE' => $rank_image,

        'POSTS' => $profileData->user_posts,
        'POST_DAY_STATS' => sprintf($lang['User_post_day_stats'], $postsPerDay),
        'POST_PERCENT_STATS' => sprintf($lang['User_post_pct_stats'], $percentagePosts),

        'TOPICS' => $profileData->user_topics,
        'TOPIC_DAY_STATS' => sprintf($lang['User_topic_day_stats'], $topicsPerDay),
        'TOPIC_PERCENT_STATS' => sprintf($lang['User_post_pct_stats'], $percentageTopics),


        'SEARCH_IMG' => $search_img,
        'SEARCH' => $search,

        'PM_IMG' => $pm_img,
        'PM' => $pm,

        'EMAIL_IMG' => $email_img,
        'EMAIL' => $email,

        'WWW_IMG' => $www_img,
        'WWW' => $www,

        'LOCATION' => $profileData->user_from ? htmlspecialchars($profileData->user_from, ENT_QUOTES) : '&nbsp;',
        'OCCUPATION' => $profileData->user_occ ? htmlspecialchars($profileData->user_occ, ENT_QUOTES) : '&nbsp;',
        'INTERESTS' => $profileData->user_interests ? htmlspecialchars($profileData->user_interests, ENT_QUOTES) : '&nbsp;',
        'AVATAR_IMG' => $avatar_img,

        'L_VIEWING_PROFILE' => sprintf($lang['Viewing_user_profile'], $profileData->username),
        'L_ABOUT_USER' => sprintf($lang['About_user'], $profileData->username),
        'L_AVATAR' => $lang['Avatar'],
        'L_POSTER_RANK' => $lang['Poster_rank'],
        'L_JOINED' => $lang['Joined'],
        'L_TOTAL_POSTS' => $lang['Total_posts'],
        'L_TOTAL_TOPICS' => $lang['Total_topics'],
        'L_SEARCH_USER_POSTS' => sprintf($lang['Search_user_posts'], $profileData->username),
        'L_SEARCH_USER_TOPICS' => sprintf($lang['Search_user_topics'], $profileData->username),
        'L_CONTACT' => $lang['Contact'],
        'L_EMAIL_ADDRESS' => $lang['Email_address'],
        'L_EMAIL' => $lang['Email'],
        'L_PM' => $lang['Private_Message'],
        'L_WEBSITE' => $lang['Website'],
        'L_LOCATION' => $lang['Location'],
        'L_OCCUPATION' => $lang['Occupation'],
        'L_INTERESTS' => $lang['Interests'],

        'U_SEARCH_USER_POSTS' => Session::appendSid('search.php?search_author=' . $u_search_author . '&amp;show_results=posts'),
        'U_SEARCH_USER_TOPICS' => Session::appendSid('search.php?search_author=' . $u_search_author . '&amp;show_results=topics'),

        'S_PROFILE_ACTION' => Session::appendSid('profile.php')
    ]
);

$template->pparse('body');

require_once $phpbb_root_path . 'includes' . $sep . 'page_tail.php';

?>