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

if (!isset($_GET[POST_USERS_URL]) || !is_numeric($_GET[POST_USERS_URL]) || $_GET[POST_USERS_URL] === ANONYMOUS) {
    message_die(GENERAL_MESSAGE, $lang['No_user_id_specified']);
}

$usersManager = $container->getService('UsersManager');

$profileData = $usersManager->getByPrimaryKey($_GET[POST_USERS_URL]);

if (!$profileData) {
	message_die(GENERAL_MESSAGE, $lang['No_user_id_specified']);
}

$cache = new Cache($storage, Tables::RANKS_TABLE);
$key   = Tables::RANKS_TABLE . '_ordered_by_rank_special_rank_min';
$sep   = DIRECTORY_SEPARATOR;

$cachedRanks = $cache->load($key);

if ($cachedRanks !== null) {
    $ranks = $cachedRanks;
} else {
    $ranks = dibi::select('*')
        ->from(Tables::RANKS_TABLE)
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
$thanksPerDay = $profileData->user_thanks / $memberdays;
$topicWatchesPerDay = $profileData->user_topic_watches / $memberdays;

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

// Get the users percentage of total thanks
if ($profileData->user_thanks !== 0) {
    $thanksManager = $container->getService('ThanksManager');

    $thanksCount = $thanksManager->getAllCount();

    $percentageThanks = $thanksCount ? min(100, ($profileData->user_thanks / $thanksCount) * 100) : 0;
} else {
    $percentageThanks = 0;
}

// Get the users percentage of total thanks
if ($profileData->user_topic_watches !== 0) {
    $topicsWatchesCount = dibi::select('COUNT(*)')
        ->as('count')
        ->from(Tables::TOPICS_WATCH_TABLE)
        ->fetchSingle();

    $percentageTopicsWatches = $topicsWatchesCount ? min(100, ($profileData->user_topic_watches / $topicsWatchesCount) * 100) : 0;
} else {
    $percentageTopicsWatches = 0;
}

$avatarImage = '';

if ($profileData->user_avatar_type && $profileData->user_allowavatar) {
	switch($profileData->user_avatar_type) {
		case USER_AVATAR_UPLOAD:
			$avatarImage = $board_config['allow_avatar_upload'] ? '<img src="' . $board_config['avatar_path'] . '/' . $profileData->user_avatar . '" alt="" border="0" />' : '';
			break;
		case USER_AVATAR_REMOTE:
			$avatarImage = $board_config['allow_avatar_remote'] ? '<img src="' . $profileData->user_avatar . '" alt="" border="0" />' : '';
			break;
		case USER_AVATAR_GALLERY:
			$avatarImage = $board_config['allow_avatar_local'] ? '<img src="' . $board_config['avatar_gallery_path'] . '/' . $profileData->user_avatar . '" alt="" border="0" />' : '';
			break;
	}
}

$posterRank = '';
$rankImage  = '';

if ($profileData->user_rank) {
    foreach ($ranks as $rank) {
        if ($profileData->user_rank === $rank->rank_id && $rank->rank_special) {
            $posterRank = $rank->rank_title;
            $rankImage  = $rank->rank_image ? '<img src="' . $rank->rank_image . '" alt="' . $posterRank . '" title="' . $posterRank . '" border="0" /><br />' : '';
        }
    }
} else {
    foreach ($ranks as $rank) {
        if ($profileData->user_posts >= $rank->rank_min && !$rank->rank_special) {
            $posterRank = $rank->rank_title;
            $rankImage  = $rank->rank_image ? '<img src="' . $rank->rank_image . '" alt="' . $posterRank . '" title="' . $posterRank . '" border="0" /><br />' : '';
        }
    }
}

$temp_url = Session::appendSid('privmsg.php?mode=post&amp;' . POST_USERS_URL . '=' . $profileData->user_id);
$pm_img = '<a href="' . $temp_url . '"><img src="' . $images['icon_pm'] . '" alt="' . $lang['Send_private_message'] . '" title="' . $lang['Send_private_message'] . '" border="0" /></a>';
$pm = '<a href="' . $temp_url . '">' . $lang['Send_private_message'] . '</a>';

if ($board_config['board_email_form'] || $userdata['user_level'] === ADMIN) {
	$email_uri = Session::appendSid('profile.php?mode=email&amp;' . POST_USERS_URL .'=' . $profileData->user_id);

	$emailImage = '<a href="' . $email_uri . '"><img src="' . $images['icon_email'] . '" alt="' . $lang['Send_email'] . '" title="' . $lang['Send_email'] . '" border="0" /></a>';
	$email      = '<a href="' . $email_uri . '">' . $lang['Send_email'] . '</a>';
} else {
	$emailImage = '&nbsp;';
	$email      = '&nbsp;';
}

$www_img = $profileData->user_website ? '<a href="' . $profileData->user_website . '" target="_userwww"><img src="' . $images['icon_www'] . '" alt="' . $lang['Visit_website'] . '" title="' . $lang['Visit_website'] . '" border="0" /></a>' : '&nbsp;';
$www     = $profileData->user_website ? '<a href="' . $profileData->user_website . '" target="_userwww">' . $profileData->user_website . '</a>' : '&nbsp;';

$temp_url    = Session::appendSid('search.php?search_author=' . urlencode($profileData->username) . '&amp;show_results=posts');
$searchImage = '<a href="' . $temp_url . '"><img src="' . $images['icon_search'] . '" alt="' . sprintf($lang['Search_user_posts'], $profileData->username) . '" title="' . sprintf($lang['Search_user_posts'], $profileData->username) . '" border="0" /></a>';
$search      = '<a href="' . $temp_url . '">' . sprintf($lang['Search_user_posts'], $profileData->username) . '</a>';

// <!-- BEGIN Another Online/Offline indicator -->
if (!$profileData->user_allow_viewonline && $userdata['user_level'] === ADMIN || $profileData->user_allow_viewonline) {
    $expiry_time = time() - ONLINE_TIME_DIFF;

    if ($profileData->user_session_time >= $expiry_time) {
        $user_onlinestatus = '<img src="' . $images['Online'] . '" alt="' . $lang['Online'] . '" title="' . $lang['Online'] . '" border="0" />';

        if (!$profileData->user_allow_viewonline && $userdata['user_level'] === ADMIN) {
            $user_onlinestatus = '<img src="' . $images['Hidden_Admin'] . '" alt="' . $lang['Hidden'] . '" title="' . $lang['Hidden'] . '" border="0" align="middle" />';
        }
    } else {
        $user_onlinestatus = '<img src="' . $images['Offline'] . '" alt="' . $lang['Offline'] . '" title="' . $lang['Offline'] . '" border="0" />';

        if (!$profileData->user_allow_viewonline && $userdata['user_level'] === ADMIN) {
            $user_onlinestatus = '<img src="' . $images['Offline'] . '" alt="' . $lang['Hidden'] . '" title="' . $lang['Hidden'] . '" border="0" />';
        }
    }
} else {
    $user_onlinestatus = '<img src="' . $images['Offline'] . '" alt="' . $lang['Offline'] . '" title="' . $lang['Offline'] . '" border="0" />';
}
// <!-- END Another Online/Offline indicator -->

//
// Generate page
//
PageHelper::header($template, $userdata, $board_config, $lang, $images,  $theme, $lang['Viewing_profile'], $gen_simple_header);

display_upload_attach_box_limits($profileData['user_id']);

if (function_exists('get_html_translation_table')) {
	$u_search_author = urlencode(strtr($profileData->username, array_flip(get_html_translation_table(HTML_ENTITIES))));
} else {
    $u_search_author = urlencode(str_replace(['&amp;', '&#039;', '&quot;', '&lt;', '&gt;'], ['&', "'", '"', '<', '>'], $profileData->username));
}

$template->assignVars(
    [
        'USERNAME' => $profileData->username,
        'JOINED' => create_date($lang['DATE_FORMAT'], $profileData->user_regdate, $board_config['board_timezone']),
        'LAST_VISIT' => create_date($lang['DATE_FORMAT'], $profileData->user_session_time, $board_config['board_timezone']),
        'POSTER_RANK' => htmlspecialchars($posterRank, ENT_QUOTES),
        'RANK_IMAGE' => $rankImage,

        'POSTS' => $profileData->user_posts,
        'POST_DAY_STATS' => sprintf($lang['User_post_day_stats'], $postsPerDay),
        'POST_PERCENT_STATS' => sprintf($lang['User_post_pct_stats'], $percentagePosts),

        'TOPICS' => $profileData->user_topics,
        'TOPIC_DAY_STATS' => sprintf($lang['User_topic_day_stats'], $topicsPerDay),
        'TOPIC_PERCENT_STATS' => sprintf($lang['User_post_pct_stats'], $percentageTopics),

        'THANKS' => $profileData->user_thanks,
        'THANK_DAY_STATS' => sprintf($lang['User_thank_day_stats'], $thanksPerDay),
        'THANK_PERCENT_STATS' => sprintf($lang['User_post_pct_stats'], $percentageThanks),

        'TOPICS_WATCHES' => $profileData->user_topic_watches,
        'TOPICS_WATCH_DAY_STATS' => sprintf($lang['User_topic_watch_day_stats'], $topicWatchesPerDay),
        'TOPICS_WATCHES_PERCENT_STATS' => sprintf($lang['User_post_pct_stats'], $percentageTopicsWatches),

        'SEARCH_IMG' => $searchImage,
        'SEARCH' => $search,

        'PM_IMG' => $pm_img,
        'PM' => $pm,

        'EMAIL_IMG' => $emailImage,
        'EMAIL' => $email,

        'WWW_IMG' => $www_img,
        'WWW' => $www,

        'LOCATION' => $profileData->user_from ? htmlspecialchars($profileData->user_from, ENT_QUOTES) : '&nbsp;',
        'OCCUPATION' => $profileData->user_occ ? htmlspecialchars($profileData->user_occ, ENT_QUOTES) : '&nbsp;',
        'INTERESTS' => $profileData->user_interests ? htmlspecialchars($profileData->user_interests, ENT_QUOTES) : '&nbsp;',

        // <!-- BEGIN Another Online/Offline indicator -->
        'USER_ONLINESTATUS' => $user_onlinestatus,
        // <!-- END Another Online/Offline indicator -->

        'AVATAR_IMG' => $avatarImage,

        'L_VIEWING_PROFILE' => sprintf($lang['Viewing_user_profile'], $profileData->username),
        'L_ABOUT_USER' => sprintf($lang['About_user'], $profileData->username),
        'L_AVATAR' => $lang['Avatar'],
        'L_POSTER_RANK' => $lang['Poster_rank'],
        'L_JOINED' => $lang['Joined'],
        'L_LAST_VISIT' => $lang['Last_visit'],
        'L_TOTAL_POSTS' => $lang['Total_posts'],
        'L_TOTAL_TOPICS' => $lang['Total_topics'],
        'L_TOTAL_THANKS' => $lang['Total_thanks'],
        'L_TOTAL_TOPICS_WATCHES' => $lang['Total_topics_watches'],
        'L_SEARCH_USER_POSTS' => sprintf($lang['Search_user_posts'], $profileData->username),
        'L_SEARCH_USER_TOPICS' => sprintf($lang['Search_user_topics'], $profileData->username),
        'L_SEARCH_USER_THANKS' => sprintf($lang['Search_user_thanks'], $profileData->username),
        'L_SEARCH_USER_TOPICS_WATCHES' => sprintf($lang['Search_user_topics_watches'], $profileData->username),
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
        'U_SEARCH_USER_THANKS' => Session::appendSid('search.php?search_author=' . $u_search_author . '&amp;show_results=thanks'),
        'U_SEARCH_USER_TOPICS_WATCHES' => Session::appendSid('search.php?search_author=' . $u_search_author . '&amp;show_results=topics_watches'),

        'S_PROFILE_ACTION' => Session::appendSid('profile.php')
    ]
);

$template->pparse('body');

PageHelper::footer($template, $userdata, $lang, $gen_simple_header);

?>