<?php
/***************************************************************************
 *                             (admin) index.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: index.php 5318 2005-12-04 12:55:28Z grahamje $
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

define('IN_PHPBB', 1);

//
// Load default header
//
$no_page_header = true;
$phpbb_root_path = './../';

require './pagestart.php';

//
// Generate relevant output
//
if (isset($_GET['pane']) && $_GET['pane'] === 'left' )
{
	$dir = @opendir('.');

	$setmodules = 1;

    while ($file = @readdir($dir)) {
        if (preg_match("/^admin_.*?\.php$/", $file)) {
            include './' . $file;
        }
    }

	@closedir($dir);

	unset($setmodules);

	include './page_header_admin.php';

    $template->setFileNames(['body' => 'admin/index_navigate.tpl']);

    $template->assignVars([
        'U_FORUM_INDEX' => Session::appendSid('../index.php'),
        'U_ADMIN_INDEX' => Session::appendSid('index.php?pane=right'),

        'L_FORUM_INDEX'   => $lang['Main_index'],
        'L_ADMIN_INDEX'   => $lang['Admin_Index'],
        'L_PREVIEW_FORUM' => $lang['Preview_forum']
        ]);

    ksort($module);

	foreach ($module as $cat => $action_array) {
		$cat = !empty($lang[$cat]) ? $lang[$cat] : preg_replace('/_/', ' ', $cat);

        $template->assignBlockVars('catrow', ['ADMIN_CATEGORY' => $cat]);

        ksort($action_array);

		$rowCount = 0;

		foreach ($action_array as $action => $file) {
			$row_color = !($rowCount%2) ? $theme['td_color1'] : $theme['td_color2'];
			$row_class = !($rowCount%2) ? $theme['td_class1'] : $theme['td_class2'];

			$action = !empty($lang[$action]) ? $lang[$action] : preg_replace('/_/', ' ', $action);

            $template->assignBlockVars('catrow.modulerow',
                [
                    'ROW_COLOR' => '#' . $row_color,
                    'ROW_CLASS' => $row_class,

                    'ADMIN_MODULE'   => $action,
                    'U_ADMIN_MODULE' => Session::appendSid($file)
                ]
            );
            $rowCount++;
		}
	}

	$template->pparse('body');

	include './page_footer_admin.php';
} elseif (isset($_GET['pane']) && $_GET['pane'] === 'right') {

	include './page_header_admin.php';

    $template->setFileNames(['body' => 'admin/index_body.tpl']);

    $template->assignVars(
        [
            'L_WELCOME'          => $lang['Welcome_phpBB'],
            'L_ADMIN_INTRO'      => $lang['Admin_intro'],
            'L_FORUM_STATS'      => $lang['Forum_stats'],
            'L_WHO_IS_ONLINE'    => $lang['Who_is_Online'],
            'L_USERNAME'         => $lang['Username'],
            'L_LOCATION'         => $lang['Location'],
            'L_LAST_UPDATE'      => $lang['Last_updated'],
            'L_IP_ADDRESS'       => $lang['IP_Address'],
            'L_STATISTIC'        => $lang['Statistic'],
            'L_VALUE'            => $lang['Value'],
            'L_NUMBER_POSTS'     => $lang['Number_posts'],
            'L_POSTS_PER_DAY'    => $lang['Posts_per_day'],
            'L_NUMBER_TOPICS'    => $lang['Number_topics'],
            'L_TOPICS_PER_DAY'   => $lang['Topics_per_day'],
            'L_NUMBER_USERS'     => $lang['Number_users'],
            'L_USERS_PER_DAY'    => $lang['Users_per_day'],
            'L_BOARD_STARTED'    => $lang['Board_started'],
            'L_AVATAR_DIR_SIZE'  => $lang['Avatar_dir_size'],
            'L_DB_SIZE'          => $lang['Database_size'],
            'L_FORUM_LOCATION'   => $lang['Forum_Location'],
            'L_STARTED'          => $lang['Login'],
            'L_GZIP_COMPRESSION' => $lang['Gzip_compression']
        ]
    );

    //
	// Get forum statistics
	//
	$total_posts = get_db_stat('postcount');
	$total_users = get_db_stat('usercount');
	$total_topics = get_db_stat('topiccount');

	$start_date = create_date($board_config['default_dateformat'], $board_config['board_startdate'], $board_config['board_timezone']);

    $user_timezone = isset($profileData['user_timezone']) ? $profileData['user_timezone'] : $board_config['board_timezone'];

    $zone = new DateTimeZone($user_timezone);

    $boardStartDay = new DateTime();
    $boardStartDay->setTimezone($zone);
    $boardStartDay->setTimestamp($board_config['board_startdate']);

    $boardRunningDays = new DateTime();
    $boardRunningDays->setTimezone($zone);
    $boardRunningDays = $boardRunningDays->diff($boardStartDay)->d;

	$posts_per_day = sprintf('%.2f', $total_posts / $boardRunningDays);
	$topics_per_day = sprintf('%.2f', $total_topics / $boardRunningDays);
	$users_per_day = sprintf('%.2f', $total_users / $boardRunningDays);

	$avatar_dir_size = 0;

    if ($avatar_dir = @opendir($phpbb_root_path . $board_config['avatar_path'])) {
        while ($file = @readdir($avatar_dir)) {
            if ($file !== '.' && $file !== '..') {
                $avatar_dir_size += @filesize($phpbb_root_path . $board_config['avatar_path'] . '/' . $file);
            }
        }

		@closedir($avatar_dir);

		//
		// This bit of code translates the avatar directory size into human readable format
		// Borrowed the code from the PHP.net annoted manual, origanally written by:
		// Jesse (jesse@jess.on.ca)
		//

        if ($avatar_dir_size >= 1048576) {
            $avatar_dir_size = round($avatar_dir_size / 1048576 * 100) / 100 . ' MB';
        } elseif ($avatar_dir_size >= 1024) {
            $avatar_dir_size = round($avatar_dir_size / 1024 * 100) / 100 . ' KB';
        } else {
            $avatar_dir_size .= ' Bytes';
        }
	} else {
		// Couldn't open Avatar dir.
		$avatar_dir_size = $lang['Not_available'];
	}

    if ($posts_per_day > $total_posts) {
        $posts_per_day = $total_posts;
    }

    if ($topics_per_day > $total_topics) {
        $topics_per_day = $total_topics;
    }

    if ($users_per_day > $total_users) {
        $users_per_day = $total_users;
    }

	//
	// DB size ... MySQL only
	//
	// This code is heavily influenced by a similar routine
	// in phpMyAdmin 2.2.0
	//
	if (preg_match('/^mysql/', $dbms)) {
        $row = dibi::query('SELECT VERSION() AS mysql_version')->fetch();

		if ($row) {
			$version = $row->mysql_version;

			if (preg_match("/^(3\.23|4\.|5\.)/", $version)) {
				$db_name = preg_match("/^(3\.23\.[6-9])|(3\.23\.[1-9][1-9])|(4\.)|(5\.)/", $version) ? "`$dbname`" : $dbname;

				$tables = dibi::query('SHOW TABLE STATUS FROM %SQL', $db_name)->fetchAll();

                if (count($tables)) {
					$dbsize = 0;

                    foreach ($tables as $table) {
                        if ($table->Type !== 'MRG_MyISAM') {
                            if ($table_prefix !== '') {
                                if (false !== strpos($table->Name, $table_prefix)) {
                                    $dbsize += $table->Data_length + $table->Index_length;
                                }
                            } else {
                                $dbsize += $table->Data_length + $table->Index_length;
                            }
                        }
                    }
				} // Else we couldn't get the table status.
            } else {
                $dbsize = $lang['Not_available'];
            }
        } else {
            $dbsize = $lang['Not_available'];
        }
	} elseif (preg_match('/^mssql/', $dbms)) {
        $dbsize = dibi::select('((SUM(size) * 8.0) * 1024.0)')
            ->as('dbsize')
            ->from('sysfiles')
            ->fetchSingle();

        if (!$dbsize) {
            $lang['Not_available'] ;
        }
	} else {
		$dbsize = $lang['Not_available'];
	}

    if (is_int($dbsize)) {
        if ($dbsize >= 1048576) {
            $dbsize = sprintf('%.2f MB', $dbsize / 1048576);
        } elseif ($dbsize >= 1024) {
            $dbsize = sprintf('%.2f KB', $dbsize / 1024);
        } else {
            $dbsize = sprintf('%.2f Bytes', $dbsize);
        }
    }

    $template->assignVars(
        [
            'NUMBER_OF_POSTS'  => $total_posts,
            'NUMBER_OF_TOPICS' => $total_topics,
            'NUMBER_OF_USERS'  => $total_users,
            'START_DATE'       => $start_date,
            'POSTS_PER_DAY'    => $posts_per_day,
            'TOPICS_PER_DAY'   => $topics_per_day,
            'USERS_PER_DAY'    => $users_per_day,
            'AVATAR_DIR_SIZE'  => $avatar_dir_size,
            'DB_SIZE'          => $dbsize,
            'GZIP_COMPRESSION' => $board_config['gzip_compress'] ? $lang['ON'] : $lang['OFF']
        ]
    );
    //
    // End forum statistics
	//

	//
	// Get users online information.
	//
    $user_timezone = isset($userdata['user_timezone']) ? $userdata['user_timezone'] : $board_config['board_timezone'];

    $time = new DateTime();
    $time->setTimezone(new DateTimeZone($user_timezone));
    $time->sub(new DateInterval('PT300S'));

    $onlinerow_reg = dibi::select(['u.user_id', 'u.username', 'u.user_session_time', 'u.user_session_page', 'u.user_allow_viewonline', 's.session_logged_in', 's.session_ip', 's.session_start'])
        ->from(USERS_TABLE)
        ->as('u')
        ->innerJoin(SESSIONS_TABLE)
        ->as('s')
        ->on('u.user_id = s.session_user_id')
        ->where('s.session_logged_in = %i', 1)
        ->where('u.user_id <> %i', ANONYMOUS)
        ->where('s.session_time >= %i', $time->getTimestamp())
        ->orderBy('u.user_session_time', dibi::DESC)
        ->fetchAll();

    $onlinerow_guest = dibi::select(['session_page', 'session_logged_in', 'session_time', 'session_ip', 'session_start'])
        ->from(SESSIONS_TABLE)
        ->where('session_logged_in = %i', 0)
        ->where('session_time >= %i', $time->getTimestamp())
        ->orderBy('session_time', 'DESC')
        ->fetchAll();

	$forums_result = dibi::select(['forum_name', 'forum_id'])
        ->from(FORUMS_TABLE)
        ->fetchPairs('forum_id', 'forum_name');

	$reg_userid_ary = [];

    if (count($onlinerow_reg)) {
        $registered_users = 0;

        foreach ($onlinerow_reg as $online_user) {
            if (!in_array($online_user->user_id, $reg_userid_ary, true)) {
				$reg_userid_ary[] = $online_user->user_id;

				$username = $online_user->username;

                if ($online_user->user_allow_viewonline || $userdata['user_level'] === ADMIN) {
                    $registered_users++;
                    $hidden = false;
                } else {
                    $hidden_users++;
                    $hidden = true;
                }

				if ($online_user->user_session_page < 1) {
					switch($online_user->user_session_page) {
						case PAGE_INDEX:
							$location = $lang['Forum_index'];
							$location_url = 'index.php?pane=right';
							break;
						case PAGE_POSTING:
							$location = $lang['Posting_message'];
							$location_url = 'index.php?pane=right';
							break;
						case PAGE_LOGIN:
							$location = $lang['Logging_on'];
							$location_url = 'index.php?pane=right';
							break;
						case PAGE_SEARCH:
							$location = $lang['Searching_forums'];
							$location_url = 'index.php?pane=right';
							break;
						case PAGE_PROFILE:
							$location = $lang['Viewing_profile'];
							$location_url = 'index.php?pane=right';
							break;
						case PAGE_VIEWONLINE:
							$location = $lang['Viewing_online'];
							$location_url = 'index.php?pane=right';
							break;
						case PAGE_VIEWMEMBERS:
							$location = $lang['Viewing_member_list'];
							$location_url = 'index.php?pane=right';
							break;
						case PAGE_PRIVMSGS:
							$location = $lang['Viewing_priv_msgs'];
							$location_url = 'index.php?pane=right';
							break;
						case PAGE_FAQ:
							$location = $lang['Viewing_FAQ'];
							$location_url = 'index.php?pane=right';
							break;
						default:
							$location = $lang['Forum_index'];
							$location_url = 'index.php?pane=right';
					}
				} else {
					$location_url = Session::appendSid('admin_forums.php?mode=editforum&amp;' . POST_FORUM_URL . '=' . $online_user->user_session_page);
					$location = $forum_data[$online_user->user_session_page];
				}

                $row_color = ($registered_users % 2) ? $theme['td_color1'] : $theme['td_color2'];
                $row_class = ($registered_users % 2) ? $theme['td_class1'] : $theme['td_class2'];

				$reg_ip = decode_ip($online_user->session_ip);

                $template->assignBlockVars('reg_user_row',
                    [
                        'ROW_COLOR'      => '#' . $row_color,
                        'ROW_CLASS'      => $row_class,
                        'USERNAME'       => $username,
                        'STARTED'        => create_date($board_config['default_dateformat'], $online_user->session_start, $board_config['board_timezone']),
                        'LASTUPDATE'     => create_date($board_config['default_dateformat'], $online_user->user_session_time, $board_config['board_timezone']),
                        'FORUM_LOCATION' => $location,
                        'IP_ADDRESS'     => $reg_ip,

                        'U_WHOIS_IP'       => "http://network-tools.com/default.asp?host=$reg_ip",
                        'U_USER_PROFILE'   => Session::appendSid('admin_users.php?mode=edit&amp;' . POST_USERS_URL . '=' . $online_user->user_id),
                        'U_FORUM_LOCATION' => Session::appendSid($location_url)
                    ]
                );
            }
		}

	} else {
        $template->assignVars(['L_NO_REGISTERED_USERS_BROWSING' => $lang['No_users_browsing']]);
    }

    //
	// Guest users
	//
	if (count($onlinerow_guest)) {
		$guest_users = 0;

        foreach ($onlinerow_guest as $guest) {
			$guest_userip_ary[] = $guest->session_ip;
			$guest_users++;

			if ($guest->session_page < 1) {
				switch( $guest->session_page) {
					case PAGE_INDEX:
						$location = $lang['Forum_index'];
						$location_url = 'index.php?pane=right';
						break;
					case PAGE_POSTING:
						$location = $lang['Posting_message'];
						$location_url = 'index.php?pane=right';
						break;
					case PAGE_LOGIN:
						$location = $lang['Logging_on'];
						$location_url = 'index.php?pane=right';
						break;
					case PAGE_SEARCH:
						$location = $lang['Searching_forums'];
						$location_url = 'index.php?pane=right';
						break;
					case PAGE_PROFILE:
						$location = $lang['Viewing_profile'];
						$location_url = 'index.php?pane=right';
						break;
					case PAGE_VIEWONLINE:
						$location = $lang['Viewing_online'];
						$location_url = 'index.php?pane=right';
						break;
					case PAGE_VIEWMEMBERS:
						$location = $lang['Viewing_member_list'];
						$location_url = 'index.php?pane=right';
						break;
					case PAGE_PRIVMSGS:
						$location = $lang['Viewing_priv_msgs'];
						$location_url = 'index.php?pane=right';
						break;
					case PAGE_FAQ:
						$location = $lang['Viewing_FAQ'];
						$location_url = 'index.php?pane=right';
						break;
					default:
						$location = $lang['Forum_index'];
						$location_url = 'index.php?pane=right';
				}
			} else {
				$location_url = Session::appendSid('admin_forums.php?mode=editforum&amp;' . POST_FORUM_URL . '=' . $guest->session_page);
				$location = $forum_data[$guest->session_page];
			}

			$row_color = ( $guest_users % 2 ) ? $theme['td_color1'] : $theme['td_color2'];
			$row_class = ( $guest_users % 2 ) ? $theme['td_class1'] : $theme['td_class2'];

			$guest_ip = decode_ip($guest->session_ip);

            $template->assignBlockVars('guest_user_row', [
                    'ROW_COLOR'      => '#' . $row_color,
                    'ROW_CLASS'      => $row_class,
                    'USERNAME'       => $lang['Guest'],
                    'STARTED'        => create_date($board_config['default_dateformat'], $guest->session_start, $board_config['board_timezone']),
                    'LASTUPDATE'     => create_date($board_config['default_dateformat'], $guest->session_time, $board_config['board_timezone']),
                    'FORUM_LOCATION' => $location,
                    'IP_ADDRESS'     => $guest_ip,

                    'U_WHOIS_IP'       => "http://network-tools.com/default.asp?host=$guest_ip",
                    'U_FORUM_LOCATION' => Session::appendSid($location_url)
                ]
            );
        }
    } else {
        $template->assignVars(['L_NO_GUESTS_BROWSING' => $lang['No_users_browsing']]);
    }

    $template->pparse('body');

	include './page_footer_admin.php';
} else {
	//
	// Generate frameset
	//
    $template->setFileNames(['body' => 'admin/index_frameset.tpl']);

    $template->assignVars(
        [
            'S_FRAME_NAV'  => Session::appendSid('index.php?pane=left'),
            'S_FRAME_MAIN' => Session::appendSid('index.php?pane=right')
        ]
    );

    header ('Expires: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT');
	header ('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

	$template->pparse('body');

	dibi::disconnect();
	exit;
}

?>