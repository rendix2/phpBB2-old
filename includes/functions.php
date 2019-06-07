<?php
/***************************************************************************
 *                               functions.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: functions.php 8377 2008-02-10 12:52:05Z acydburn $
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

function get_db_stat($mode)
{
	switch( $mode) {
		case 'usercount':
		    return dibi::select('COUNT(user_id)')
                ->as('total')
                ->from(USERS_TABLE)
                ->where('user_id <> %i', ANONYMOUS)
                ->fetchSingle();

		case 'newestuser':
		    return dibi::select(['user_id', 'username'])
                ->from(USERS_TABLE)
                ->where('user_id <> %i', ANONYMOUS)
                ->orderBy('user_id', dibi::DESC)
                ->fetch();

		case 'postcount':
            return dibi::select('SUM(forum_topics)')->as('topic_total')
                ->from(FORUMS_TABLE)
                ->fetchSingle();

		case 'topiccount':
		   return dibi::select('SUM(forum_topics)')->as('topic_total')
                ->from(FORUMS_TABLE)
                ->fetchSingle();
	}
}

// added at phpBB 2.0.11 to properly format the username
function phpbb_clean_username($username)
{
	$username = substr(htmlspecialchars(str_replace("\'", "'", trim($username))), 0, 25);
	$username = rtrim($username, "\\");
	$username = str_replace("'", "\'", $username);

	return $username;
}

/**
* Our own generator of random values
* This uses a constantly changing value as the base for generating the values
* The board wide setting is updated once per page if this code is called
* With thanks to Anthrax101 for the inspiration on this one
* Added in phpBB 2.0.20
*/
function dss_rand()
{
	global $board_config, $dss_seeded;

	$val = $board_config['rand_seed'] . microtime();
	$val = md5($val);
	$board_config['rand_seed'] = md5($board_config['rand_seed'] . $val . 'a');

    if ($dss_seeded !== true) {
        dibi::update(CONFIG_TABLE, ['config_value' => $board_config['rand_seed']])
            ->where('config_name = %s', 'rand_seed')
            ->execute();

        $dss_seeded = true;
    }

	return substr($val, 4, 16);
}
//
// Get Userdata, $user can be username or user_id. If force_str is true, the username will be forced.
//
// TODO try to force use by user_id, NOT username
function get_userdata($user, $force_str = false)
{
    if (!is_numeric($user) || $force_str) {
        $user = phpbb_clean_username($user);
    } else {
        $user = (int)$user;
    }

    $user_row = dibi::select('*')
        ->from(USERS_TABLE);

    if (is_int($user)) {
        $user_row->where('user_id = %i', $user);
    } else {
        $user_row->where('username = %s', $user);
    }

    $user_row = $user_row->where('user_id <> %i', ANONYMOUS)->fetch();

	return $user_row;
}

function make_jumpbox($action, $match_forum_id = 0)
{
	global $template, $userdata, $lang, $nav_links, $SID;

//	$is_auth = auth(AUTH_VIEW, AUTH_LIST_ALL, $userdata);

    $boxstring = '';

    $categories = dibi::select(['c.cat_id', 'c.cat_title', 'c.cat_order'])
        ->from(CATEGORIES_TABLE)
        ->as('c')
        ->innerJoin(FORUMS_TABLE)
        ->as('f')
        ->on('f.cat_id = c.cat_id')
        ->groupBy('c.cat_id')
        ->groupBy('c.cat_title')
        ->groupBy(' c.cat_order')
        ->orderBy('c.cat_order')
        ->fetchAll();

    if (count($categories)) {
        $forums = dibi::select('*')
            ->from(FORUMS_TABLE)
            ->orderBy('cat_id')
            ->orderBy('forum_order')
            ->fetchAll();

		$boxstring = '<select name="' . POST_FORUM_URL . '" onchange="if (this.options[this.selectedIndex].value != -1){ forms[\'jumpbox\'].submit() }"><option value="-1">' . $lang['Select_forum'] . '</option>';

        if (count($forums)) {
            foreach ($categories as $category) {
				$boxstring_forums = '';

				foreach ($forums as $forum) {
					if ( $forum->cat_id === $category->cat_id && $forum->auth_view <= AUTH_REG) {

//					if ( $forum_rows[$j]['cat_id'] == $category_rows[$i]['cat_id'] && $is_auth[$forum_rows[$j]['forum_id']]['auth_view'] )
//					{
						$selected = ( $forum->forum_id === $match_forum_id ) ? 'selected="selected"' : '';
						$boxstring_forums .=  '<option value="' . $forum->forum_id . '"' . $selected . '>' . $forum->forum_name . '</option>';

						//
						// Add an array to $nav_links for the Mozilla navigation bar.
						// 'chapter' and 'forum' can create multiple items, therefore we are using a nested array.
						//
						$nav_links['chapter forum'][$forum->forum_id] = array (
                            'url' => Session::appendSid('viewforum.php?' . POST_FORUM_URL . '=' . $forum->forum_id),
                            'title' => $forum->forum_name
						);
					}
				}

				if ( $boxstring_forums !== '' )
				{
					$boxstring .= '<option value="-1">&nbsp;</option>';
					$boxstring .= '<option value="-1">' . $category->cat_title . '</option>';
					$boxstring .= '<option value="-1">----------------</option>';
					$boxstring .= $boxstring_forums;
				}
			}
		}

		$boxstring .= '</select>';
	} else {
		$boxstring .= '<select name="' . POST_FORUM_URL . '" onchange="if (this.options[this.selectedIndex].value != -1){ forms[\'jumpbox\'].submit() }"></select>';
	}

	// Let the jumpbox work again in sites having additional session id checks.
//	if ( !empty($SID) )
//	{
		$boxstring .= '<input type="hidden" name="sid" value="' . $userdata['session_id'] . '" />';
//	}

    $template->setFileNames(['jumpbox' => 'jumpbox.tpl']);

    $template->assignVars(
        [
            'L_GO'           => $lang['Go'],
            'L_JUMP_TO'      => $lang['Jump_to'],
            'L_SELECT_FORUM' => $lang['Select_forum'],

            'S_JUMPBOX_SELECT' => $boxstring,
            'S_JUMPBOX_ACTION' => Session::appendSid($action)
        ]
    );
    $template->assignVarFromHandle('JUMPBOX', 'jumpbox');
}

//
// Initialise user settings on page load
function init_userprefs($userdata)
{
	global $board_config, $theme, $images;
	global $template, $lang, $phpbb_root_path;
	global $nav_links;

    $default_lang = '';

    if ($userdata['user_id'] !== ANONYMOUS) {
        if (!empty($userdata['user_lang'])) {
            $default_lang = ltrim(basename(rtrim($userdata['user_lang'])), "'");
        }

        if (!empty($userdata['user_dateformat'])) {
            $board_config['default_dateformat'] = $userdata['user_dateformat'];
        }

        if (isset($userdata['user_timezone'])) {
            $board_config['board_timezone'] = $userdata['user_timezone'];
        }
    } else {
        $default_lang = ltrim(basename(rtrim($board_config['default_lang'])), "'");
    }

	if ( !file_exists(@phpbb_realpath($phpbb_root_path . 'language/lang_' . $default_lang . '/lang_main.php'))) {
		if ( $userdata['user_id'] !== ANONYMOUS) {
			// For logged in users, try the board default language next
			$default_lang = ltrim(basename(rtrim($board_config['default_lang'])), "'");
		} else {
			// For guests it means the default language is not present, try english
			// This is a long shot since it means serious errors in the setup to reach here,
			// but english is part of a new install so it's worth us trying
			$default_lang = 'english';
		}

        if (!file_exists(@phpbb_realpath($phpbb_root_path . 'language/lang_' . $default_lang . '/lang_main.php'))) {
            message_die(CRITICAL_ERROR, 'Could not locate valid language pack');
        }
	}

	// If we've had to change the value in any way then let's write it back to the database
	// before we go any further since it means there is something wrong with it
	if ( $userdata['user_id'] !== ANONYMOUS && $userdata['user_lang'] !== $default_lang) {
	    dibi::update(USERS_TABLE, ['user_lang' => $default_lang])
            ->where('user_lang = %s', $userdata['user_lang'])
            ->execute();

		$userdata['user_lang'] = $default_lang;
	} elseif ( $userdata['user_id'] === ANONYMOUS && $board_config['default_lang'] !== $default_lang) {
        dibi::update(CONFIG_TABLE, ['config_value' => $default_lang])
            ->where('config_name = %s', 'default_lang')
            ->execute();
	}

	$board_config['default_lang'] = $default_lang;

	include $phpbb_root_path . 'language/lang_' . $board_config['default_lang'] . '/lang_main.php';

	if ( defined('IN_ADMIN')) {
		if (!file_exists(@phpbb_realpath($phpbb_root_path . 'language/lang_' . $board_config['default_lang'] . '/lang_admin.php'))) {
			$board_config['default_lang'] = 'english';
		}

		include $phpbb_root_path . 'language/lang_' . $board_config['default_lang'] . '/lang_admin.php';
	}

	//
	// Set up style
	//
	if ( !$board_config['override_user_style']) {
		if ( $userdata['user_id'] !== ANONYMOUS && $userdata['user_style'] > 0) {
			if ( $theme = setup_style($userdata['user_style'])) {
				return;
			}
		}
	}

	$theme = setup_style($board_config['default_style']);

	//
	// Mozilla navigation bar
	// Default items that should be valid on all pages.
	// Defined here to correctly assign the Language Variables
	// and be able to change the variables within code.
	//
    $nav_links['top'] = [
        'url' => Session::appendSid($phpbb_root_path . 'index.php'),
        'title' => sprintf($lang['Forum_Index'], $board_config['sitename'])
    ];
    $nav_links['search'] = [
        'url' => Session::appendSid($phpbb_root_path . 'search.php'),
        'title' => $lang['Search']
    ];
    $nav_links['help'] = [
        'url' => Session::appendSid($phpbb_root_path . 'faq.php'),
        'title' => $lang['FAQ']
    ];
    $nav_links['author'] = [
        'url' => Session::appendSid($phpbb_root_path . 'memberlist.php'),
        'title' => $lang['Memberlist']
    ];
}

function setup_style($style)
{
	global $board_config, $template, $images, $phpbb_root_path;

	$theme = dibi::select('*')
        ->from(THEMES_TABLE)
        ->where('themes_id = %i', (int) $style)
        ->fetch();

	if ( !$theme) {
        if ($board_config['default_style'] === $style) {
            message_die(CRITICAL_ERROR, 'Could not set up default theme');
        }

	    $default_theme = dibi::select('*')
            ->from(THEMES_TABLE)
            ->where('themes_id = %i',(int) $board_config['default_style'])
            ->fetch();

	    if ($default_theme) {
	        dibi::update(USERS_TABLE, ['user_style' => (int) $board_config['default_style']])
                ->where('user_style = %s', $style)
                ->execute();
        } else {
            message_die(CRITICAL_ERROR, "Could not get theme data for themes_id [$style]");
        }
	}

	$template_path = 'templates/' ;
	$template_name = $theme->template_name;

	$template = new Template($phpbb_root_path . $template_path . $template_name);

	if ( $template) {
		$current_template_path = $template_path . $template_name;
		@include $phpbb_root_path . $template_path . $template_name . '/' . $template_name . '.cfg';

		if ( !defined('TEMPLATE_CONFIG')) {
			message_die(CRITICAL_ERROR, "Could not open $template_name template config file", '', __LINE__, __FILE__);
		}

		$img_lang = file_exists(@phpbb_realpath($phpbb_root_path . $current_template_path . '/images/lang_' . $board_config['default_lang'])) ? $board_config['default_lang'] : 'english';

		foreach ($images as $key => $value) {
			if ( !is_array($value)) {
				$images[$key] = str_replace('{LANG}', 'lang_' . $img_lang, $value);
			}
		}
	}

	return $theme;
}

function encode_ip($dotquad_ip)
{
	$ip_sep = explode('.', $dotquad_ip);
	return sprintf('%02x%02x%02x%02x', $ip_sep[0], $ip_sep[1], $ip_sep[2], $ip_sep[3]);
}

function decode_ip($int_ip)
{
	$hexipbang = explode('.', chunk_split($int_ip, 2, '.'));
	return hexdec($hexipbang[0]). '.' . hexdec($hexipbang[1]) . '.' . hexdec($hexipbang[2]) . '.' . hexdec($hexipbang[3]);
}

/**
 * Create date/time from format and timezone
 *
 * @param string $format
 * @param int    $time
 * @param string $time_zone
 *
 * @return string
 * @throws Exception
 */
function create_date($format, $time, $time_zone)
{
    $started = new DateTime('now', new DateTimeZone($time_zone));
    $started->setTimestamp((int)$time);
    return $started->format($format);
}

//
// Pagination routine, generates
// page number sequence
//
function generate_pagination($base_url, $num_items, $per_page, $start_item, $add_prevnext_text = true)
{
	global $lang;

	$total_pages = ceil($num_items/$per_page);

	if ( $total_pages === 1) {
		return '';
	}

	$on_page = floor($start_item / $per_page) + 1;

	$page_string = '';
	if ( $total_pages > 10) {
		$init_page_max = ( $total_pages > 3 ) ? 3 : $total_pages;

		for ($i = 1; $i < $init_page_max + 1; $i++) {
			$page_string .= ( $i === $on_page ) ? '<b>' . $i . '</b>' : '<a href="' . Session::appendSid($base_url . '&amp;start=' . ( ( $i - 1 ) * $per_page ) ) . '">' . $i . '</a>';

			if ( $i <  $init_page_max) {
				$page_string .= ', ';
			}
		}

		if ( $total_pages > 3) {
			if ( $on_page > 1  && $on_page < $total_pages) {
				$page_string .= ( $on_page > 5 ) ? ' ... ' : ', ';

				$init_page_min = ( $on_page > 4 ) ? $on_page : 5;
				$init_page_max = ( $on_page < $total_pages - 4 ) ? $on_page : $total_pages - 4;

				for ($i = $init_page_min - 1; $i < $init_page_max + 2; $i++) {
					$page_string .= ($i === $on_page) ? '<b>' . $i . '</b>' : '<a href="' . Session::appendSid($base_url . '&amp;start=' . ( ( $i - 1 ) * $per_page ) ) . '">' . $i . '</a>';

					if ( $i <  $init_page_max + 1) {
						$page_string .= ', ';
					}
				}

				$page_string .= ( $on_page < $total_pages - 4 ) ? ' ... ' : ', ';
			} else {
				$page_string .= ' ... ';
			}

			for ($i = $total_pages - 2; $i < $total_pages + 1; $i++) {
				$page_string .= ( $i === $on_page ) ? '<b>' . $i . '</b>'  : '<a href="' . Session::appendSid($base_url . '&amp;start=' . ( ( $i - 1 ) * $per_page ) ) . '">' . $i . '</a>';

				if ($i <  $total_pages) {
					$page_string .= ', ';
				}
			}
		}
	}
	else {
		for ($i = 1; $i < $total_pages + 1; $i++) {
			$page_string .= ( $i === $on_page ) ? '<b>' . $i . '</b>' : '<a href="' . Session::appendSid($base_url . '&amp;start=' . ( ( $i - 1 ) * $per_page ) ) . '">' . $i . '</a>';

			if ( $i <  $total_pages) {
				$page_string .= ', ';
			}
		}
	}

    if ($add_prevnext_text) {
        if ($on_page > 1) {
            $page_string = ' <a href="' . Session::appendSid($base_url . '&amp;start=' . (($on_page - 2) * $per_page)) . '">' . $lang['Previous'] . '</a>&nbsp;&nbsp;' . $page_string;
        }

        if ($on_page < $total_pages) {
            $page_string .= '&nbsp;&nbsp;<a href="' . Session::appendSid($base_url . '&amp;start=' . ($on_page * $per_page)) . '">' . $lang['Next'] . '</a>';
        }

    }

	$page_string = $lang['Goto_page'] . ' ' . $page_string;

	return $page_string;
}

//
// Obtain list of naughty words and build preg style replacement arrays for use by the
// calling script, note that the vars are passed as references this just makes it easier
// to return both sets of arrays
//
function obtain_word_list(&$orig_word, &$replacement_word)
{
	//
	// Define censored word matches
	//
	$words = dibi::select(['word', 'replacement'])
        ->from(WORDS_TABLE)
        ->fetchPairs('word', 'replacement');

    foreach ($words as $word => $replacement) {
        $orig_word[] = '#\b(' . str_replace('\*', '\w*?', preg_quote($word, '#')) . ')\b#i';
        $replacement_word[] = $replacement;
	}

	return true;
}

//
// This is general replacement for die(), allows templated
// output in users (or default) language, etc.
//
// $msg_code can be one of these constants:
//
// GENERAL_MESSAGE : Use for any simple text message, eg. results 
// of an operation, authorisation failures, etc.
//
// GENERAL ERROR : Use for any error which occurs _AFTER_ the 
// common.php include and session code, ie. most errors in 
// pages/functions
//
// CRITICAL_MESSAGE : Used when basic config data is available but 
// a session may not exist, eg. banned users
//
// CRITICAL_ERROR : Used when config data cannot be obtained, eg
// no database connection. Should _not_ be used in 99.5% of cases
//
function message_die($msg_code, $msg_text = '', $msg_title = '', $err_line = '', $err_file = '')
{
	global $db, $template, $board_config, $theme, $lang, $phpbb_root_path, $nav_links, $gen_simple_header, $images;
	global $userdata, $user_ip, $session_length;

	if (defined('HAS_DIED')) {
		die("message_die() was called multiple times. This isn't supposed to happen. Was message_die() used in page_tail.php?");
	}
	
	define('HAS_DIED', 1);

    $debug_text = '';
	
	//
	// Get SQL error if we are debugging. Do this as soon as possible to prevent 
	// subsequent queries from overwriting the status of sql_error()
	//
	if ( DEBUG && ( $msg_code === GENERAL_ERROR || $msg_code === CRITICAL_ERROR )) {
		if ( $err_line !== '' && $err_file !== '') {
			$debug_text .= '<br /><br />Line : ' . $err_line . '<br />File : ' . basename($err_file);
		}
	}

	if (empty($userdata) && ( $msg_code === GENERAL_MESSAGE || $msg_code === GENERAL_ERROR )) {
		$userdata = Session::pageStart($user_ip, PAGE_INDEX);
		init_userprefs($userdata);
	}

	//
	// If the header hasn't been output then do it
	//
	if ( !defined('HEADER_INC') && $msg_code !== CRITICAL_ERROR) {
		if ( empty($lang)) {
			if ( !empty($board_config['default_lang'])) {
				include $phpbb_root_path . 'language/lang_' . $board_config['default_lang'] . '/lang_main.php';
			} else {
				include $phpbb_root_path . 'language/lang_english/lang_main.php';
			}
		}

		if ( empty($template) || empty($theme)) {
			$theme = setup_style($board_config['default_style']);
		}

		//
		// Load the Page Header
		//
		if ( !defined('IN_ADMIN')) {
			include $phpbb_root_path . 'includes/page_header.php';
		} else {
			include $phpbb_root_path . 'admin/page_header_admin.php';
		}
	}

	switch($msg_code)
	{
		case GENERAL_MESSAGE:
			if ( $msg_title === '') {
				$msg_title = $lang['Information'];
			}
			break;

		case CRITICAL_MESSAGE:
			if ( $msg_title === '') {
				$msg_title = $lang['Critical_Information'];
			}
			break;

		case GENERAL_ERROR:
			if ( $msg_text === '') {
				$msg_text = $lang['An_error_occured'];
			}

			if ( $msg_title === '') {
				$msg_title = $lang['General_Error'];
			}
			break;

		case CRITICAL_ERROR:
			//
			// Critical errors mean we cannot rely on _ANY_ DB information being
			// available so we're going to dump out a simple echo'd statement
			//
			include $phpbb_root_path . 'language/lang_english/lang_main.php';

			if ( $msg_text === '') {
				$msg_text = $lang['A_critical_error'];
			}

			if ( $msg_title === '') {
				$msg_title = 'phpBB : <b>' . $lang['Critical_Error'] . '</b>';
			}
			break;
	}

	//
	// Add on DEBUG info if we've enabled debug mode and this is an error. This
	// prevents debug info being output for general messages should DEBUG be
	// set true by accident (preventing confusion for the end user!)
	//
    if (DEBUG && ($msg_code === GENERAL_ERROR || $msg_code === CRITICAL_ERROR)) {
        if ($debug_text !== '') {
            $msg_text = $msg_text . '<br /><br /><b><u>DEBUG MODE</u></b>' . $debug_text;
        }
    }

	if ( $msg_code !== CRITICAL_ERROR )
	{
        if (!empty($lang[$msg_text])) {
            $msg_text = $lang[$msg_text];
        }

        if (!defined('IN_ADMIN')) {
            $template->setFileNames(['message_body' => 'message_body.tpl']);
        } else {
            $template->setFileNames(['message_body' => 'admin/admin_message_body.tpl']);
        }

        $template->assignVars(
            [
                'MESSAGE_TITLE' => $msg_title,
                'MESSAGE_TEXT'  => $msg_text
            ]
        );
        $template->pparse('message_body');

        if (!defined('IN_ADMIN')) {
            include $phpbb_root_path . 'includes/page_tail.php';
        } else {
            include $phpbb_root_path . 'admin/page_footer_admin.php';
        }
	} else {
		echo "<html>\n<body>\n" . $msg_title . "\n<br /><br />\n" . $msg_text . "</body>\n</html>";
	}

	exit;
}

//
// This function is for compatibility with PHP 4.x's realpath()
// function.  In later versions of PHP, it needs to be called
// to do checks with some functions.  Older versions of PHP don't
// seem to need this, so we'll just return the original value.
// dougk_ff7 <October 5, 2002>
function phpbb_realpath($path)
{
	global $phpbb_root_path;

	return (!@function_exists('realpath') || !@realpath($phpbb_root_path . 'includes/functions.php')) ? $path : @realpath($path);
}

/**
 * @param string $url
 */
function redirect($url)
{
	global $board_config;

	dibi::disconnect();

	if (false !== strpos(urldecode($url), "\n") || false !== strpos(urldecode($url), "\r") || false !== strpos(urldecode($url),
            ';url')) {
		message_die(GENERAL_ERROR, 'Tried to redirect to potentially insecure url.');
	}

	$server_protocol = $board_config['cookie_secure'] ? 'https://' : 'http://';
	$server_name = preg_replace('#^\/?(.*?)\/?$#', '\1', trim($board_config['server_name']));
	$server_port = ($board_config['server_port'] !== 80) ? ':' . trim($board_config['server_port']) : '';
	$script_name = preg_replace('#^\/?(.*?)\/?$#', '\1', trim($board_config['script_path']));
	$script_name = ($script_name === '') ? $script_name : '/' . $script_name;
	$url = preg_replace('#^\/?(.*?)\/?$#', '/\1', trim($url));

	// Redirect via an HTML form for PITA webservers
	if (@preg_match('/Microsoft|WebSTAR|Xitami/', getenv('SERVER_SOFTWARE'))) {
		header('Refresh: 0; URL=' . $server_protocol . $server_name . $server_port . $script_name . $url);
		echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"><html><head><meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1"><meta http-equiv="refresh" content="0; url=' . $server_protocol . $server_name . $server_port . $script_name . $url . '"><title>Redirect</title></head><body><div align="center">If your browser does not support meta redirection please click <a href="' . $server_protocol . $server_name . $server_port . $script_name . $url . '">HERE</a> to be redirected</div></body></html>';
		exit;
	}

	// Behave as per HTTP/1.1 spec for others
	header('Location: ' . $server_protocol . $server_name . $server_port . $script_name . $url);
	exit;
}

?>