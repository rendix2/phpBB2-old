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

use Nette\Utils\Finder;

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
if (isset($_GET['pane']) && $_GET['pane'] === 'left') {
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
		$cat = !empty($lang[$cat]) ? $lang[$cat] : str_replace('_', ' ', $cat);

        $template->assignBlockVars('catrow', ['ADMIN_CATEGORY' => $cat]);

        ksort($action_array);

		$rowCount = 0;

		foreach ($action_array as $action => $file) {
			$row_color = !($rowCount%2) ? $theme['td_color1'] : $theme['td_color2'];
			$row_class = !($rowCount%2) ? $theme['td_class1'] : $theme['td_class2'];

			$action = !empty($lang[$action]) ? $lang[$action] : str_replace('_', ' ', $action);

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
            'L_GZIP_COMPRESSION' => $lang['Gzip_compression'],
            'L_ONLINE_USERS'     => $lang['Online_users'],
        ]
    );

    //
	// Get forum statistics
	//
	$total_posts = get_db_stat('postcount');
	$total_users = get_db_stat('usercount');
	$total_topics = get_db_stat('topiccount');

	$usersOnline = dibi::select('COUNT(*)')
        ->from(SESSIONS_TABLE)
        ->fetchSingle();

	$start_date = create_date($board_config['default_dateformat'], $board_config['board_startdate'], $board_config['board_timezone']);

    $user_timezone = isset($profileData['user_timezone']) ? $profileData['user_timezone'] : $board_config['board_timezone'];

    $zone = new DateTimeZone($user_timezone);

    $boardStartDay = new DateTime();
    $boardStartDay->setTimezone($zone);
    $boardStartDay->setTimestamp($board_config['board_startdate']);

    $boardRunningDays = new DateTime();
    $boardRunningDays->setTimezone($zone);
    $boardRunningDays = $boardRunningDays->diff($boardStartDay)->d;

	$posts_per_day  = sprintf('%.2f', $total_posts / $boardRunningDays);
	$topics_per_day = sprintf('%.2f', $total_topics / $boardRunningDays);
	$users_per_day  = sprintf('%.2f', $total_users / $boardRunningDays);

	$avatar_dir_size   = 0;
	$enabledExtensions = ['*.jpg', '*.jpeg', '*.pjpeg', '*.gif', '*.png'];

    $files = Finder::findFiles($enabledExtensions)->in($phpbb_root_path . $board_config['avatar_path']);

    if (count($files)) {
        /**
         * @var SplFileInfo $file
         */
        foreach ($files as $file) {
            $avatar_dir_size += $file->getSize();
        }

        $avatar_dir_size = get_formatted_filesize($avatar_dir_size);
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

    include $phpbb_root_path .'includes/functions_admin.php';

    $dbsize = get_database_size();

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
            'GZIP_COMPRESSION' => $board_config['gzip_compress'] ? $lang['ON'] : $lang['OFF'],
            'ONLINE_USERS'     => $usersOnline
        ]
    );

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