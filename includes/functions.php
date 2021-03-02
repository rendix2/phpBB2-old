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

use Dibi\Exception;
use Dibi\Row;
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

/**
 * @param string $mode
 *
 * @return Row|false
 */
function get_db_stat($mode)
{
    switch ($mode) {
        case 'usercount':
            return dibi::select('COUNT(user_id) - 1')
                ->as('total')
                ->from(Tables::USERS_TABLE)
                ->fetchSingle();

        case 'newestuser':
            return dibi::select(['user_id', 'username'])
                ->from(Tables::USERS_TABLE)
                ->where('[user_id] <> %i', ANONYMOUS)
                ->orderBy('user_id', dibi::DESC)
                ->fetch();

        case 'postcount':
            return dibi::select('SUM(forum_posts)')
                ->as('post_total')
                ->from(Tables::FORUMS_TABLE)
                ->fetchSingle();

        case 'topiccount':
            return dibi::select('SUM(forum_topics)')
                ->as('topic_total')
                ->from(Tables::FORUMS_TABLE)
                ->fetchSingle();
    }
}

// added at phpBB 2.0.11 to properly format the username
function phpbb_clean_username($username)
{
	$username = mb_substr(htmlspecialchars(str_replace("\'", "'", trim($username)), ENT_QUOTES), 0, 25);
	$username = rtrim($username, "\\");
	$username = str_replace("'", "\'", $username);

	return $username;
}

/**
 *
 * Get Userdata, $user can be username or user_id. If force_str is true, the username will be forced.
 *
 * TODO try to force use by user_id, NOT username
 *
 * @param int  $user_id
 * @param bool $force_str
 *
 * @return Row|false
 */
function get_userdata($user_id, $force_str = false)
{
    if (!is_numeric($user_id) || $force_str) {
        $user_id = phpbb_clean_username($user_id);
    } else {
        $user_id = (int)$user_id;
    }

    $user = dibi::select('*')
        ->from(Tables::USERS_TABLE);

    if (is_int($user_id)) {
        $user->where('[user_id] = %i', $user_id);
    } else {
        $user->where('[username] = %s', $user_id);
    }

    $user = $user->where('[user_id] <> %i', ANONYMOUS)->fetch();

	return $user;
}

function make_jumpbox($action, $match_forum_id = 0)
{
    /**
     * @var BaseTemplate $template
     */
	global $template;
	global $userdata, $lang, $SID;

//	$is_auth = auth(AUTH_VIEW, AUTH_LIST_ALL, $userdata);

    $jumpBox = '';

    $categories = dibi::select(['c.cat_id', 'c.cat_title', 'c.cat_order'])
        ->from(Tables::CATEGORIES_TABLE)
        ->as('c')
        ->innerJoin(Tables::FORUMS_TABLE)
        ->as('f')
        ->on('[f.cat_id] = [c.cat_id]')
        ->groupBy('c.cat_id')
        ->groupBy('c.cat_title')
        ->groupBy('c.cat_order')
        ->orderBy('c.cat_order')
        ->fetchAll();

    if (count($categories)) {
        $forums = dibi::select('*')
            ->from(Tables::FORUMS_TABLE)
            ->orderBy('cat_id')
            ->orderBy('forum_order')
            ->fetchAll();

		$jumpBox = '<select name="' . POST_FORUM_URL . '" onchange="if (this.options[this.selectedIndex].value != -1){ forms[\'jumpbox\'].submit() }"><option value="-1">' . $lang['Select_forum'] . '</option>';

        if (count($forums)) {
            foreach ($categories as $category) {
				$boxstring_forums = '';

				foreach ($forums as $forum) {
					if ($forum->cat_id === $category->cat_id && $forum->auth_view <= Auth::AUTH_REG) {

//					if ($forum_rows[$j]['cat_id'] === $category_rows[$i]['cat_id'] && $is_auth[$forum_rows[$j]['forum_id']]['auth_view'] )
//					{
						$selected = $forum->forum_id === $match_forum_id ? 'selected="selected"' : '';
						$boxstring_forums .=  '<option value="' . $forum->forum_id . '"' . $selected . '>' . htmlspecialchars($forum->forum_name, ENT_QUOTES) . '</option>';
					}
				}

                if ($boxstring_forums !== '') {
                    $jumpBox .= '<optgroup label="'.$category->cat_title .'">' . $boxstring_forums . '</optgroup>';
				}
			}
		}

		$jumpBox .= '</select>';
	} else {
		$jumpBox .= '<select name="' . POST_FORUM_URL . '" onchange="if (this.options[this.selectedIndex].value != -1){ forms[\'jumpbox\'].submit() }"></select>';
	}

	// Let the jumpbox work again in sites having additional session id checks.
//	if (!empty($SID) )
//	{
		$jumpBox .= '<input type="hidden" name="sid" value="' . $userdata['session_id'] . '" />';
//	}

    $template->setFileNames(['jumpbox' => 'jumpbox.tpl']);

    $template->assignVars(
        [
            'L_GO'           => $lang['Go'],
            'L_JUMP_TO'      => $lang['Jump_to'],
            'L_SELECT_FORUM' => $lang['Select_forum'],

            'S_JUMPBOX_SELECT' => $jumpBox,
            'S_JUMPBOX_ACTION' => Session::appendSid($action)
        ]
    );
    $template->assignVarFromHandle('JUMPBOX', 'jumpbox');
}

function jumpBoxLatte($action, $match_forum_id = 0)
{
    /**
     * @var BaseTemplate $template
     */
    global $template;
    global $userdata, $lang, $SID;

//	$is_auth = auth(AUTH_VIEW, AUTH_LIST_ALL, $userdata);

    $jumpBox = '';

    $categories = dibi::select(['c.cat_id', 'c.cat_title', 'c.cat_order'])
        ->from(Tables::CATEGORIES_TABLE)
        ->as('c')
        ->innerJoin(Tables::FORUMS_TABLE)
        ->as('f')
        ->on('[f.cat_id] = [c.cat_id]')
        ->groupBy('c.cat_id')
        ->groupBy('c.cat_title')
        ->groupBy('c.cat_order')
        ->orderBy('c.cat_order')
        ->fetchAll();

    if (count($categories)) {
        $forums = dibi::select('*')
            ->from(Tables::FORUMS_TABLE)
            ->orderBy('cat_id')
            ->orderBy('forum_order')
            ->fetchAll();

        $jumpBox = '<select name="' . POST_FORUM_URL . '" onchange="if (this.options[this.selectedIndex].value != -1){ forms[\'jumpbox\'].submit() }"><option value="-1">' . $lang['Select_forum'] . '</option>';

        if (count($forums)) {
            foreach ($categories as $category) {
                $boxstring_forums = '';

                foreach ($forums as $forum) {
                    if ($forum->cat_id === $category->cat_id && $forum->auth_view <= Auth::AUTH_REG) {

//					if ($forum_rows[$j]['cat_id'] === $category_rows[$i]['cat_id'] && $is_auth[$forum_rows[$j]['forum_id']]['auth_view'] )
//					{
                        $selected = $forum->forum_id === $match_forum_id ? 'selected="selected"' : '';
                        $boxstring_forums .=  '<option value="' . $forum->forum_id . '"' . $selected . '>' . htmlspecialchars($forum->forum_name, ENT_QUOTES) . '</option>';
                    }
                }

                if ($boxstring_forums !== '') {
                    $jumpBox .= '<optgroup label="'.$category->cat_title .'">' . $boxstring_forums . '</optgroup>';
                }
            }
        }

        $jumpBox .= '</select>';
    } else {
        $jumpBox .= '<select name="' . POST_FORUM_URL . '" onchange="if (this.options[this.selectedIndex].value != -1){ forms[\'jumpbox\'].submit() }"></select>';
    }

    // Let the jumpbox work again in sites having additional session id checks.
//	if (!empty($SID) )
//	{
    $jumpBox .= '<input type="hidden" name="sid" value="' . $userdata['session_id'] . '" />';
//	}

    $template->setFileNames(['jumpbox' => 'jumpbox.tpl']);

    $template->assignVars(
        [
            'L_GO'           => $lang['Go'],
            'L_JUMP_TO'      => $lang['Jump_to'],
            'L_SELECT_FORUM' => $lang['Select_forum'],

            'S_JUMPBOX_SELECT' => $jumpBox,
            'S_JUMPBOX_ACTION' => Session::appendSid($action)
        ]
    );


    return $jumpBox;
}

//
// Initialise user settings on page load
function init_userprefs($pageId)
{
	global $board_config, $theme, $images;
	global $template, $lang, $phpbb_root_path;
	global $storage;
	global $user_ip;

    $default_lang = '';
    $sep = DIRECTORY_SEPARATOR;

    $userData = Session::pageStart($user_ip, $pageId);

    if ($userData['user_id'] !== ANONYMOUS) {
        if (!empty($userData['user_lang'])) {
            $default_lang = ltrim(basename(rtrim($userData['user_lang'])), "'");
        }

        if (!empty($userData['user_date_format'])) {
            $board_config['default_dateformat'] = $userData['user_date_format'];
        }

        if (isset($userData['user_timezone'])) {
            $board_config['board_timezone'] = $userData['user_timezone'];
        }
    } else {
        $default_lang = ltrim(basename(rtrim($board_config['default_lang'])), "'");
    }

    if (!file_exists(@realpath($phpbb_root_path . 'app' . $sep . 'language' . $sep . 'lang_' . $default_lang . $sep . 'lang_main.php'))) {
        $cache = new Cache($storage, Tables::CONFIG_TABLE);
        $cache->remove(Tables::CONFIG_TABLE);

		if ($userData['user_id'] !== ANONYMOUS) {
			// For logged in users, try the board default language next
			$default_lang = ltrim(basename(rtrim($board_config['default_lang'])), "'");
		} else {
			// For guests it means the default language is not present, try english
			// This is a long shot since it means serious errors in the setup to reach here,
			// but english is part of a new install so it's worth us trying
			$default_lang = 'english';
		}

        if (!file_exists(@realpath($phpbb_root_path . 'app' . $sep .'language' . $sep . 'lang_' . $default_lang . $sep . 'lang_main.php'))) {
            message_die(CRITICAL_ERROR, 'Could not locate valid language pack');
        }
	}

	// If we've had to change the value in any way then let's write it back to the database
	// before we go any further since it means there is something wrong with it
	if ($userData['user_id'] !== ANONYMOUS && $userData['user_lang'] !== $default_lang) {
	    if ($userData['user_lang'] === null) {
            dibi::update(Tables::USERS_TABLE, ['user_lang' => $default_lang])
                ->where('[user_lang] IS NULL')
                ->execute();
        } else {
            dibi::update(Tables::USERS_TABLE, ['user_lang' => $default_lang])
                ->where('[user_lang] = %s', $userData['user_lang'])
                ->execute();
        }

		$userData['user_lang'] = $default_lang;
	} elseif ($userData['user_id'] === ANONYMOUS && $board_config['default_lang'] !== $default_lang) {
        dibi::update(Tables::CONFIG_TABLE, ['config_value' => $default_lang])
            ->where('[config_name] = %s', 'default_lang')
            ->execute();

        $cache = new Cache($storage, Tables::CONFIG_TABLE);
        $cache->remove(Tables::CONFIG_TABLE);
	}

	$board_config['default_lang'] = $default_lang;

    require_once $phpbb_root_path . 'app' . $sep .'language' . $sep . 'lang_' . $board_config['default_lang'] . $sep . 'lang_main.php';

	if (defined('IN_ADMIN')) {
        if (!file_exists(@realpath($phpbb_root_path . 'app' . $sep .'language' . $sep . 'lang_' . $board_config['default_lang'] . $sep . 'lang_admin.php'))) {
			$board_config['default_lang'] = 'english';
		}

        require_once $phpbb_root_path . 'app' . $sep .'language' . $sep . 'lang_' . $board_config['default_lang'] . $sep . 'lang_admin.php';
	}

    include_attach_lang();

	//
	// Set up style
	//
	if (!$board_config['override_user_style'] && $userData['user_id'] !== ANONYMOUS && $userData['user_style'] > 0) {
        $theme = setupStyle($userData['user_style']);

        if ($theme) {
            return $userData;
        }
    }

	if ($userData['user_id'] === ANONYMOUS) {
        $userData['user_style'] = $board_config['default_style'];
    }

	$theme = setupStyle($board_config['default_style']);

	return $userData;
}

function setupStyle($style)
{
	global $board_config, $template, $images, $phpbb_root_path;
	global $storage;

	$sep = DIRECTORY_SEPARATOR;

	$cache = new Cache($storage, Tables::THEMES_TABLE);

	$key = Tables::THEMES_TABLE . '_'. (int)$style;
	$cachedTheme = $cache->load($key);

	if ($cachedTheme !== null) {
        $theme = $cachedTheme;
	} else {
        $theme = dibi::select('*')
            ->from(Tables::THEMES_TABLE)
            ->where('[themes_id] = %i', (int)$style)
            ->fetch();

        $cache->save($key, $theme);
    }

	if (!$theme) {
        if ($board_config['default_style'] === $style) {
            message_die(CRITICAL_ERROR, 'Could not set up default theme');
        }

	    $default_theme = dibi::select('*')
            ->from(Tables::THEMES_TABLE)
            ->where('[themes_id] = %i',(int) $board_config['default_style'])
            ->fetch();

	    if ($default_theme) {
	        dibi::update(Tables::USERS_TABLE, ['user_style' => (int) $board_config['default_style']])
                ->where('[user_style] = %s', $style)
                ->execute();
        } else {
            message_die(CRITICAL_ERROR, "Could not get theme data for themes_id [$style]");
        }
	}

    $templatePath = 'templates' . $sep;
	$templateName = $theme->template_name;
	$templateRootPath = $phpbb_root_path . $templatePath . $templateName;

	// decide which template engine we will use
	if ($board_config['template_engine'] === '0') {
        $template = new TemplateStandard($templateRootPath);
    } elseif ($board_config['template_engine'] === '1') {
        $template = new TemplateFile($templateRootPath);
    } elseif ($board_config['template_engine'] === '2') {
        $template = new TemplateDatabase($templateRootPath);
    } else {
        $template = new TemplateStandard($templateRootPath);
    }

	if ($template) {
	    // LOOK this variable is used in required cfg file! if you change it, you have to change it in that file!!!!
		$currentTemplatePath = $templatePath . $templateName;
        require_once $phpbb_root_path . $templatePath . $templateName . $sep . $templateName . '.cfg';

		if (!defined('TEMPLATE_CONFIG')) {
			message_die(CRITICAL_ERROR, "Could not open $templateName template config file", '', __LINE__, __FILE__);
		}

        $imgLang = file_exists(@realpath($phpbb_root_path . $currentTemplatePath . $sep . 'images' . $sep . 'lang_' . $board_config['default_lang'])) ? $board_config['default_lang'] : 'english';

		foreach ($images as $key => $value) {
			if (!is_array($value)) {
				$images[$key] = str_replace('{LANG}', 'lang_' . $imgLang, $value);
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

/**
 * @param $num_items
 * @param $per_page
 * @param $start_item
 * @return int|string
 */
function get_page($num_items, $per_page, $start_item)
{

    $total_pages = ceil($num_items / $per_page);

    if ($total_pages === 1) {
        return '1';
        exit;
    }

    $on_page = floor($start_item / $per_page) + 1;
    $page_string = '';

    for ($i = 0; $i < $total_pages + 1; $i++) {
        if ($i === $on_page) {
            $page_string = $i;
        }

    }

    return $page_string;
}

//
// Pagination routine, generates
// page number sequence
//
function generate_pagination($base_url, $num_items, $per_page, $start_item, $add_prevnext_text = true)
{
	global $lang;

	$total_pages = ceil($num_items/$per_page);

	if ($total_pages === 1) {
		return '';
	}

	$on_page = floor($start_item / $per_page) + 1;

	$page_string = '';
	if ($total_pages > 10) {
		$init_page_max = $total_pages > 3 ? 3 : $total_pages;

		for ($i = 1; $i < $init_page_max + 1; $i++) {
			$page_string .= $i === $on_page ? '<b>' . $i . '</b>' : '<a href="' . Session::appendSid($base_url . '&amp;start=' . ( ( $i - 1 ) * $per_page ) ) . '">' . $i . '</a>';

			if ($i <  $init_page_max) {
				$page_string .= ', ';
			}
		}

		if ($total_pages > 3) {
			if ($on_page > 1  && $on_page < $total_pages) {
				$page_string .= $on_page > 5 ? ' ... ' : ', ';

				$init_page_min = $on_page > 4 ? $on_page : 5;
				$init_page_max = $on_page < $total_pages - 4 ? $on_page : $total_pages - 4;

				for ($i = $init_page_min - 1; $i < $init_page_max + 2; $i++) {
					$page_string .= $i === $on_page ? '<b>' . $i . '</b>' : '<a href="' . Session::appendSid($base_url . '&amp;start=' . ( ( $i - 1 ) * $per_page ) ) . '">' . $i . '</a>';

					if ($i <  $init_page_max + 1) {
						$page_string .= ', ';
					}
				}

				$page_string .= $on_page < $total_pages - 4 ? ' ... ' : ', ';
			} else {
				$page_string .= ' ... ';
			}

			for ($i = $total_pages - 2; $i < $total_pages + 1; $i++) {
				$page_string .= $i === $on_page ? '<b>' . $i . '</b>'  : '<a href="' . Session::appendSid($base_url . '&amp;start=' . ( ( $i - 1 ) * $per_page ) ) . '">' . $i . '</a>';

				if ($i <  $total_pages) {
					$page_string .= ', ';
				}
			}
		}
	} else {
		for ($i = 1; $i < $total_pages + 1; $i++) {
			$page_string .= $i === $on_page ? '<b>' . $i . '</b>' : '<a href="' . Session::appendSid($base_url . '&amp;start=' . ( ( $i - 1 ) * $per_page ) ) . '">' . $i . '</a>';

			if ($i <  $total_pages) {
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
    global $storage;

    $cache = new Cache($storage, Tables::WORDS_TABLE);

    $cachedWords = $cache->load(Tables::WORDS_TABLE);

	//
	// Define censored word matches
	//
    if ($cachedWords !== null) {
        $words = $cachedWords;
    } else {
        $words = dibi::select(['word', 'replacement'])
            ->from(Tables::WORDS_TABLE)
            ->fetchPairs('word', 'replacement');

        $cache->save(Tables::WORDS_TABLE, $words);
    }

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
	global $template, $board_config, $theme, $lang, $phpbb_root_path, $gen_simple_header, $images;
	global $userdata, $user_ip, $session_length;

	$sep = DIRECTORY_SEPARATOR;

    //+MOD: Fix message_die for multiple errors MOD
    static $msg_history;

    if( !isset($msg_history)) {
        $msg_history = [];
    }

    $msg_history[] = [
        'msg_code'  => $msg_code,
        'msg_text'  => $msg_text,
        'msg_title' => $msg_title,
        'err_line'  => $err_line,
        'err_file'  => $err_file,
    ];
    //-MOD: Fix message_die for multiple errors MOD

    if (defined('HAS_DIED')) {
        //+MOD: Fix message_die for multiple errors MOD

        //
        // This message is printed at the end of the report.
        // Of course, you can change it to suit your own needs. ;-)
        //
        $custom_error_message = 'Please, contact the %swebmaster%s. Thank you.';

        if (!empty($board_config) && !empty($board_config['board_email'])) {
            $custom_error_message = sprintf($custom_error_message,
                '<a href="mailto:' . $board_config['board_email'] . '">', '</a>');
        } else {
            $custom_error_message = sprintf($custom_error_message, '', '');
        }

        echo "<html>\n<body>\n<b>Critical Error!</b><br />\nmessage_die() was called multiple times.<br />&nbsp;<hr />";

        foreach ($msg_history as $i => $message) {


            echo '<b>Error #' . ($i + 1) . "</b>\n<br />\n";
            if (!empty($message['msg_title'])) {
                echo '<b>' . $message['msg_title'] . "</b>\n<br />\n";
            }
            echo $message['msg_text'] . "\n<br /><br />\n";
            if (!empty($message['err_line'])) {
                echo '<b>Line :</b> ' . $message['err_line'] . '<br /><b>File :</b> ' . $message['err_file'] . "</b>\n<br />\n";
            }
            if (!empty($message->sql)) {
                echo '<b>SQL :</b> ' . $message->sql . "\n<br />\n";
            }
            echo "&nbsp;<hr />\n";
        }
        echo $custom_error_message . '<hr /><br clear="all">';
        die("</body>\n</html>");
        //-MOD: Fix message_die for multiple errors MOD
    }
	
	define('HAS_DIED', 1);

    $debug_text = '';
	
	//
	// Get SQL error if we are debugging. Do this as soon as possible to prevent 
	// subsequent queries from overwriting the status of sql_error()
	//
    if (DEBUG && ($msg_code === GENERAL_ERROR || $msg_code === CRITICAL_ERROR) && $err_line !== '' && $err_file !== '') {
        $debug_text .= '<br /><br />Line : ' . $err_line . '<br />File : ' . basename($err_file);
    }

    if (empty($userdata) && ($msg_code === GENERAL_MESSAGE || $msg_code === GENERAL_ERROR)) {
        $userdata = init_userprefs(PAGE_INDEX);
    }

    switch ($msg_code) {
        case GENERAL_MESSAGE:
            $page_title = $lang['Information'];

            if ($msg_title === '') {
                $msg_title = $lang['Information'];
            }
            break;

        case CRITICAL_MESSAGE:
            $page_title = $lang['Critical_Information'];

            if ($msg_title === '') {
                $msg_title = $lang['Critical_Information'];
            }
            break;

        case GENERAL_ERROR:
            $page_title = $lang['An_error_occured'];

            if ($msg_text === '') {
                $msg_text = $lang['An_error_occured'];
            }

            if ($msg_title === '') {
                $msg_title = $lang['General_Error'];
            }
            break;

        case CRITICAL_ERROR:
            $page_title = $lang['A_critical_error'];

            //
            // Critical errors mean we cannot rely on _ANY_ DB information being
            // available so we're going to dump out a simple echo'd statement
            //

            require_once $phpbb_root_path . 'app' . $sep .'language' . $sep . 'lang_english' . $sep . 'lang_main.php';
            if ($msg_text === '') {
                $msg_text = $lang['A_critical_error'];
            }

            if ($msg_title === '') {
                $msg_title = 'phpBB : <b>' . $lang['Critical_Error'] . '</b>';
            }
            break;
    }

	//
	// If the header hasn't been output then do it
	//
    if (!defined('HEADER_INC') && $msg_code !== CRITICAL_ERROR) {
        if (empty($lang)) {
            if (!empty($board_config['default_lang'])) {
                require_once $phpbb_root_path . 'app' . $sep .'language' . $sep . 'lang_' . $board_config['default_lang'] . $sep . 'lang_main.php';
            } else {
                require_once $phpbb_root_path . 'app' . $sep .'language' . $sep . 'lang_english' . $sep . 'lang_main.php';
            }
        }

        if (empty($template) || empty($theme)) {
            $theme = setupStyle($board_config['default_style']);
        }

        //
        // Load the Page Header
        //
        if (defined('IN_ADMIN')) {
            require_once $phpbb_root_path . 'admin' . $sep . 'page_header_admin.php';
        } else {
            PageHelper::header($template, $userdata, $board_config, $lang, $images, $theme, $page_title, $gen_simple_header);
        }
	}

	//
	// Add on DEBUG info if we've enabled debug mode and this is an error. This
	// prevents debug info being output for general messages should DEBUG be
	// set true by accident (preventing confusion for the end user!)
    //
    if (DEBUG && ($msg_code === GENERAL_ERROR || $msg_code === CRITICAL_ERROR) && $debug_text !== '') {
        $msg_text .= $debug_text . '<br /><br /><b><u>DEBUG MODE</u></b>';
    }

    if ($msg_code !== CRITICAL_ERROR) {
        if (!empty($lang[$msg_text])) {
            $msg_text = $lang[$msg_text];
        }

        if (defined('IN_ADMIN')) {
            $template->setFileNames(['message_body' => 'admin/admin_message_body.tpl']);
        } else {
            $template->setFileNames(['message_body' => 'message_body.tpl']);
        }

        $template->assignVars(
            [
                'MESSAGE_TITLE' => $msg_title,
                'MESSAGE_TEXT'  => $msg_text
            ]
        );
        $template->pparse('message_body');

        if (defined('IN_ADMIN')) {
            require_once $phpbb_root_path . 'admin' . $sep . 'page_footer_admin.php';
        } else {
            PageHelper::footer($template, $userdata, $lang, $gen_simple_header);
        }
    } else {
        echo "<html>\n<body>\n" . $msg_title . "\n<br /><br />\n" . $msg_text . "</body>\n</html>";
    }

    exit;
}

/**
 * @param string $url
 */
function redirect($url)
{
	global $board_config;

	dibi::disconnect();

	if (false !== mb_strpos(urldecode($url), "\n") || false !== mb_strpos(urldecode($url), "\r") || false !== mb_strpos(urldecode($url), ';url')) {
		message_die(GENERAL_ERROR, 'Tried to redirect to potentially insecure url.');
	}

	$serverProtocol = $board_config['cookie_secure'] ? 'https://' : 'http://';
	$serverName = preg_replace('#^\/?(.*?)\/?$#', '\1', trim($board_config['server_name']));
	$serverPort = $board_config['server_port'] !== 80 ? ':' . trim($board_config['server_port']) : '';
	$scriptName = preg_replace('#^\/?(.*?)\/?$#', '\1', trim($board_config['script_path']));
	$scriptName = $scriptName === '' ? $scriptName : '/' . $scriptName;
	$url = preg_replace('#^\/?(.*?)\/?$#', '/\1', trim($url));

	// Redirect via an HTML form for PITA webservers
	if (@preg_match('/Microsoft|WebSTAR|Xitami/', getenv('SERVER_SOFTWARE'))) {
		header('Refresh: 0; URL=' . $serverProtocol . $serverName . $serverPort . $scriptName . $url);
		echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"><html><head><meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1"><meta http-equiv="refresh" content="0; url=' . $serverProtocol . $serverName . $serverPort . $scriptName . $url . '"><title>Redirect</title></head><body><div align="center">If your browser does not support meta redirection please click <a href="' . $serverProtocol . $serverName . $serverPort . $scriptName . $url . '">HERE</a> to be redirected</div></body></html>';
		exit;
	}

	// Behave as per HTTP/1.1 spec for others
	header('Location: ' . $serverProtocol . $serverName . $serverPort . $scriptName . $url);
	exit;
}

/**
 * Return formatted string for filesizes
 *
 * copied from phpBB3
 *
 * @param mixed	$value			filesize in bytes
 *								(non-negative number; int, float or string)
 * @param bool	$string_only	true if language string should be returned
 * @param array	$allowed_units	only allow these units (data array indexes)
 *
 * @return mixed					data array if $string_only is false
 */
function get_formatted_filesize($value, $string_only = true, $allowed_units = false)
{
    global $user;

    $available_units = [
        'tb' => [
            'min' => 1099511627776, // pow(2, 40)
            'index' => 4,
            'si_unit' => 'TB',
            'iec_unit' => 'TIB',
        ],
        'gb' => [
            'min' => 1073741824, // pow(2, 30)
            'index' => 3,
            'si_unit' => 'GB',
            'iec_unit' => 'GIB',
        ],
        'mb' => [
            'min' => 1048576, // pow(2, 20)
            'index' => 2,
            'si_unit' => 'MB',
            'iec_unit' => 'MIB',
        ],
        'kb' => [
            'min' => 1024, // pow(2, 10)
            'index' => 1,
            'si_unit' => 'KB',
            'iec_unit' => 'KIB',
        ],
        'b' => [
            'min' => 0,
            'index' => 0,
            'si_unit' => 'BYTES', // Language index
            'iec_unit' => 'BYTES',  // Language index
        ],
    ];

    foreach ($available_units as $si_identifier => $unit_info) {
        if (!empty($allowed_units) && $si_identifier !== 'b' && !in_array($si_identifier, $allowed_units, true)) {
            continue;
        }

        if ($value >= $unit_info['min']) {
            $unit_info['si_identifier'] = $si_identifier;

            break;
        }
    }
    unset($available_units);

    for ($i = 0; $i < $unit_info['index']; $i++) {
        $value /= 1024;
    }
    $value = round($value, 2);

    // Default to IEC
    $unit_info['unit'] = $unit_info['iec_unit'];

    if (!$string_only) {
        $unit_info['value'] = $value;

        return $unit_info;
    }

    return $value  . ' ' . $unit_info['unit'];
}

/**
 * @param array  $boardConfig
 * @param string $scriptName
 *
 * @return string
 */
function getServerUrl(array $boardConfig, $scriptName)
{
    $server_name = trim($boardConfig['server_name']);
    $server_protocol = $boardConfig['cookie_secure'] ? 'https://' : 'http://';
    $server_port = $boardConfig['server_port'] !== 80 ? ':' . trim($boardConfig['server_port']) . '/' : '/';

    return $server_protocol . $server_name . $server_port . $scriptName;
}

/**
 * @return bool
 */
function isConnectionsSecure()
{
    return isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

?>