<?php
/***************************************************************************
 *                              page_header.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: page_header.php 5283 2005-10-30 15:17:14Z acydburn $
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
 ***************************************************************************/

if (!defined('IN_PHPBB')) {
	die('Hacking attempt');
}

define('HEADER_INC', true);

//
// gzip_compression
//
$do_gzip_compress = false;

if ($board_config['gzip_compress']) {
	$phpver = PHP_VERSION;

	$useragent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : getenv('HTTP_USER_AGENT');

	if ($phpver >= '4.0.4pl1' && ( false !== strpos($useragent, 'compatible') || false !== strpos($useragent, 'Gecko'))) {
		if (extension_loaded('zlib')) {
			ob_start('ob_gzhandler');
		}
	} elseif ($phpver > '4.0') {
		if (false !== strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) {
			if (extension_loaded('zlib')) {
				$do_gzip_compress = true;
				ob_start();
				ob_implicit_flush(0);

				header('Content-Encoding: gzip');
			}
		}
	}
}

//
// Parse and show the overall header.
//
$template->setFileNames(['overall_header' => empty($gen_simple_header) ? 'overall_header.tpl' : 'simple_header.tpl']);

//
// Generate logged in/logged out status
//
if ($userdata['session_logged_in']) {
	$u_login_logout = 'login.php?logout=true&amp;sid=' . $userdata['session_id'];
	$l_login_logout = $lang['Logout'] . ' [ ' . $userdata['username'] . ' ]';
} else {
	$u_login_logout = 'login.php';
	$l_login_logout = $lang['Login'];
}

$s_last_visit = $userdata['session_logged_in'] ? create_date($board_config['default_dateformat'], $userdata['user_lastvisit'], $board_config['board_timezone']) : '';

//
// Obtain number of new private messages
// if user is logged in
//
if ($userdata['session_logged_in'] && empty($gen_simple_header)) {
    if ($userdata['user_new_privmsg']) {
		$l_message_new = $userdata['user_new_privmsg'] === 1 ? $lang['New_pm'] : $lang['New_pms'];
		$l_privmsgs_text = sprintf($l_message_new, $userdata['user_new_privmsg']);

		if ($userdata['user_last_privmsg'] > $userdata['user_lastvisit']) {
		    dibi::update(USERS_TABLE, ['user_last_privmsg' => $userdata['user_lastvisit']])
                ->where('user_id = %i', $userdata['user_id'])
                ->execute();

			$s_privmsg_new = 1;
			$icon_pm = $images['pm_new_msg'];
		} else {
			$s_privmsg_new = 0;
			$icon_pm = $images['pm_new_msg'];
		}
	} else {
		$l_privmsgs_text = $lang['No_new_pm'];

		$s_privmsg_new = 0;
		$icon_pm = $images['pm_no_new_msg'];
	}

	if ($userdata['user_unread_privmsg']) {
		$l_message_unread = $userdata['user_unread_privmsg'] === 1 ? $lang['Unread_pm'] : $lang['Unread_pms'];
		$l_privmsgs_text_unread = sprintf($l_message_unread, $userdata['user_unread_privmsg']);
	} else {
		$l_privmsgs_text_unread = $lang['No_unread_pm'];
	}
} else {
	$icon_pm = $images['pm_no_new_msg'];
	$l_privmsgs_text = $lang['Login_check_pm'];
	$l_privmsgs_text_unread = '';
	$s_privmsg_new = 0;
}

//
// Generate HTML required for Mozilla Navigation bar
//
if (!isset($nav_links)) {
	$nav_links = [];
}

$nav_links_html = '';
$nav_link_proto = '<link rel="%s" href="%s" title="%s" />' . "\n";

foreach ($nav_links as $nav_item => $nav_array) {
	if (!empty($nav_array['url'])) {
		$nav_links_html .= sprintf($nav_link_proto, $nav_item, Session::appendSid($nav_array['url']), $nav_array['title']);
	} else {
		// We have a nested array, used for items like <link rel='chapter'> that can occur more than once.
		foreach ($nav_array as $nested_array) {
			$nav_links_html .= sprintf($nav_link_proto, $nav_item, $nested_array['url'], $nested_array['title']);
		}
	}
}

// Format Timezone. We are unable to use array_pop here, because of PHP3 compatibility
$l_timezone = isset($userdata['user_timezone']) ? $userdata['user_timezone'] : $board_config['board_timezone'];
//
// The following assigns all _common_ variables that may be used at any point
// in a template.
//
$template->assignVars(
    [
        'SITENAME'                    => htmlspecialchars($board_config['sitename'], ENT_QUOTES),
        'SITE_DESCRIPTION'            => htmlspecialchars($board_config['site_desc'], ENT_QUOTES),
        'PAGE_TITLE'                  => $page_title,
        'LAST_VISIT_DATE'             => sprintf($lang['You_last_visit'], $s_last_visit),
        'CURRENT_TIME'                => sprintf($lang['Current_time'], create_date($board_config['default_dateformat'], time(), $board_config['board_timezone'])),
        'RECORD_USERS'                => sprintf($lang['Record_online_users'], $board_config['record_online_users'], create_date($board_config['default_dateformat'], $board_config['record_online_date'], $board_config['board_timezone'])),
        'PRIVATE_MESSAGE_INFO'        => $l_privmsgs_text,
        'PRIVATE_MESSAGE_INFO_UNREAD' => $l_privmsgs_text_unread,
        'PRIVATE_MESSAGE_NEW_FLAG'    => $s_privmsg_new,

        'PRIVMSG_IMG' => $icon_pm,

        'L_USERNAME'          => $lang['Username'],
        'L_PASSWORD'          => $lang['Password'],
        'L_LOGIN_LOGOUT'      => $l_login_logout,
        'L_LOGIN'             => $lang['Login'],
        'L_LOG_ME_IN'         => $lang['Log_me_in'],
        'L_AUTO_LOGIN'        => $lang['Log_me_in'],
        'L_INDEX'             => sprintf($lang['Forum_Index'], $board_config['sitename']),
        'L_REGISTER'          => $lang['Register'],
        'L_PROFILE'           => $lang['Profile'],
        'L_SEARCH'            => $lang['Search'],
        'L_PRIVATEMSGS'       => $lang['Private_Messages'],
        'L_WHO_IS_ONLINE'     => $lang['Who_is_Online'],
        'L_MEMBERLIST'        => $lang['Memberlist'],
        'L_FAQ'               => $lang['FAQ'],
        'L_USERGROUPS'        => $lang['Usergroups'],
        'L_SEARCH_NEW'        => $lang['Search_new'],
        'L_SEARCH_UNANSWERED' => $lang['Search_unanswered'],
        'L_SEARCH_SELF'       => $lang['Search_your_posts'],
        'L_WHOSONLINE_ADMIN'  => sprintf($lang['Admin_online_color'], '<span style="color:#' . $theme['fontcolor3'] . '">', '</span>'),
        'L_WHOSONLINE_MOD'    => sprintf($lang['Mod_online_color'], '<span style="color:#' . $theme['fontcolor2'] . '">', '</span>'),

        'U_SEARCH_UNANSWERED' => Session::appendSid('search.php?search_id=unanswered'),
        'U_SEARCH_SELF'       => Session::appendSid('search.php?search_id=egosearch'),
        'U_SEARCH_NEW'        => Session::appendSid('search.php?search_id=newposts'),
        'U_INDEX'             => Session::appendSid('index.php'),
        'U_REGISTER'          => Session::appendSid('profile.php?mode=register'),
        'U_PROFILE'           => Session::appendSid('profile.php?mode=editprofile'),
        'U_PRIVATEMSGS'       => Session::appendSid('privmsg.php?folder=inbox'),
        'U_PRIVATEMSGS_POPUP' => Session::appendSid('privmsg.php?mode=newpm'),
        'U_SEARCH'            => Session::appendSid('search.php'),
        'U_MEMBERLIST'        => Session::appendSid('memberlist.php'),
        'U_MODCP'             => Session::appendSid('modcp.php'),
        'U_FAQ'               => Session::appendSid('faq.php'),
        'U_VIEWONLINE'        => Session::appendSid('viewonline.php'),
        'U_LOGIN_LOGOUT'      => Session::appendSid($u_login_logout),
        'U_GROUP_CP'          => Session::appendSid('groupcp.php'),

        'S_CONTENT_DIRECTION' => $lang['DIRECTION'],
        'S_CONTENT_ENCODING'  => $lang['ENCODING'],
        'S_CONTENT_DIR_LEFT'  => $lang['LEFT'],
        'S_CONTENT_DIR_RIGHT' => $lang['RIGHT'],
        'S_TIMEZONE'          => sprintf($lang['All_times'], $l_timezone),
        'S_LOGIN_ACTION'      => Session::appendSid('login.php'),

        'T_HEAD_STYLESHEET' => $theme['head_stylesheet'],
        'T_BODY_BACKGROUND' => $theme['body_background'],
        'T_BODY_BGCOLOR'    => '#' . $theme['body_bgcolor'],
        'T_BODY_TEXT'       => '#' . $theme['body_text'],
        'T_BODY_LINK'       => '#' . $theme['body_link'],
        'T_BODY_VLINK'      => '#' . $theme['body_vlink'],
        'T_BODY_ALINK'      => '#' . $theme['body_alink'],
        'T_BODY_HLINK'      => '#' . $theme['body_hlink'],
        'T_TR_COLOR1'       => '#' . $theme['tr_color1'],
        'T_TR_COLOR2'       => '#' . $theme['tr_color2'],
        'T_TR_COLOR3'       => '#' . $theme['tr_color3'],
        'T_TR_CLASS1'       => $theme['tr_class1'],
        'T_TR_CLASS2'       => $theme['tr_class2'],
        'T_TR_CLASS3'       => $theme['tr_class3'],
        'T_TH_COLOR1'       => '#' . $theme['th_color1'],
        'T_TH_COLOR2'       => '#' . $theme['th_color2'],
        'T_TH_COLOR3'       => '#' . $theme['th_color3'],
        'T_TH_CLASS1'       => $theme['th_class1'],
        'T_TH_CLASS2'       => $theme['th_class2'],
        'T_TH_CLASS3'       => $theme['th_class3'],
        'T_TD_COLOR1'       => '#' . $theme['td_color1'],
        'T_TD_COLOR2'       => '#' . $theme['td_color2'],
        'T_TD_COLOR3'       => '#' . $theme['td_color3'],
        'T_TD_CLASS1'       => $theme['td_class1'],
        'T_TD_CLASS2'       => $theme['td_class2'],
        'T_TD_CLASS3'       => $theme['td_class3'],
        'T_FONTFACE1'       => $theme['fontface1'],
        'T_FONTFACE2'       => $theme['fontface2'],
        'T_FONTFACE3'       => $theme['fontface3'],
        'T_FONTSIZE1'       => $theme['fontsize1'],
        'T_FONTSIZE2'       => $theme['fontsize2'],
        'T_FONTSIZE3'       => $theme['fontsize3'],
        'T_FONTCOLOR1'      => '#' . $theme['fontcolor1'],
        'T_FONTCOLOR2'      => '#' . $theme['fontcolor2'],
        'T_FONTCOLOR3'      => '#' . $theme['fontcolor3'],
        'T_SPAN_CLASS1'     => $theme['span_class1'],
        'T_SPAN_CLASS2'     => $theme['span_class2'],
        'T_SPAN_CLASS3'     => $theme['span_class3'],

        'NAV_LINKS' => $nav_links_html
    ]
);

//
// Login box?
//
if ($userdata['session_logged_in']) {
    $template->assignBlockVars('switch_user_logged_in', []);

    if (!empty($userdata['user_popup_pm'])) {
        $template->assignBlockVars('switch_enable_pm_popup', []);
    }
} else {
    $template->assignBlockVars('switch_user_logged_out', []);
    //
    // Allow autologin?
    //
    if (!isset($board_config['allow_autologin']) || $board_config['allow_autologin']) {
        $template->assignBlockVars('switch_allow_autologin', []);
        $template->assignBlockVars('switch_user_logged_out.switch_allow_autologin', []);
    }
}

// Add no-cache control for cookies if they are set
//$c_no_cache = (isset($_COOKIE[$board_config['cookie_name'] . '_sid']) || isset($_COOKIE[$board_config['cookie_name'] . '_data'])) ? 'no-cache="set-cookie", ' : '';

// Work around for "current" Apache 2 + PHP module which seems to not
// cope with private cache control setting
if (!empty($_SERVER['SERVER_SOFTWARE']) && false !== strpos($_SERVER['SERVER_SOFTWARE'], 'Apache/2')) {
    header('Cache-Control: no-cache, pre-check=0, post-check=0');
} else {
    header('Cache-Control: private, pre-check=0, post-check=0, max-age=0');
}
header('Expires: 0');
header('Pragma: no-cache');

$template->pparse('overall_header');

?>