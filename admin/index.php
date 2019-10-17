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
$sep = DIRECTORY_SEPARATOR;
$phpbb_root_path = '.' . $sep . '..' . $sep;

require_once '.' . $sep . 'pagestart.php';

//
// Generate relevant output
//
if (isset($_GET['pane']) && $_GET['pane'] === 'left') {
    $modules = [];

    $modules['General']['Configuration'] = 'admin_board.php';
    $modules['General']['Mass_Email'] = 'admin_mass_email.php';
    $modules['General']['Smilies'] = 'admin_smilies.php';
    $modules['General']['Word_Censor'] = 'admin_words.php';

    $modules['Database']['DB_Maintenance'] = 'admin_db_maintenance.php';
    $modules['Database']['Backup_DB'] = 'admin_db_utilities.php?perform=backup';
    $modules['Database']['Restore_DB'] = 'admin_db_utilities.php?perform=restore';

    $modules['Users']['Ban_Management'] = 'admin_user_ban.php';
    $modules['Users']['Disallow'] = 'admin_disallow.php';
    $modules['Users']['Manage'] = 'admin_users.php';
    $modules['Users']['Permissions'] = 'admin_ug_auth.php?mode=user';
    $modules['Users']['Ranks'] = 'admin_ranks.php';
    $modules['Users']['Who_is_Online'] = 'admin_online.php';

    $modules['Forums']['Prune'] = 'admin_forum_prune.php';
    $modules['Forums']['Permissions'] = 'admin_forumauth.php';
    $modules['Forums']['Manage'] = 'admin_forums.php';

    $modules['Groups']['Add_new'] = 'admin_groups.php?mode=new';
    $modules['Groups']['Manage'] = 'admin_groups.php';
    $modules['Groups']['Permissions'] = 'admin_ug_auth.php?mode=group';

    $modules['Styles']['Add_new'] = 'admin_styles.php?mode=addnew';
    $modules['Styles']['Create_new'] = 'admin_styles.php?mode=create';
    $modules['Styles']['Manage'] = 'admin_styles.php';
    $modules['Styles']['Export'] = 'admin_styles.php?mode=export';

    require_once  '.' . $sep . 'page_header_admin.php';

    $template->setFileNames(['body' => 'admin/index_navigate.tpl']);

    $template->assignVars(
        [
            'U_FORUM_INDEX' => Session::appendSid('../index.php'),
            'U_ADMIN_INDEX' => Session::appendSid('index.php?pane=right'),

            'L_FORUM_INDEX'   => $lang['Main_index'],
            'L_ADMIN_INDEX'   => $lang['Admin_Index'],
            'L_PREVIEW_FORUM' => $lang['Preview_forum']
        ]
    );

    ksort($modules);

	foreach ($modules as $category => $actions) {
		$category = !empty($lang[$category]) ? $lang[$category] : str_replace('_', ' ', $category);

        $template->assignBlockVars('catrow', ['ADMIN_CATEGORY' => $category]);

        ksort($actions);

		$rowCount = 0;

		foreach ($actions as $action => $fileName) {
			$rowColor = !($rowCount%2) ? $theme['td_color1'] : $theme['td_color2'];
			$rowClass = !($rowCount%2) ? $theme['td_class1'] : $theme['td_class2'];

			$action = !empty($lang[$action]) ? $lang[$action] : str_replace('_', ' ', $action);

            $template->assignBlockVars('catrow.modulerow',
                [
                    'ROW_COLOR' => '#' . $rowColor,
                    'ROW_CLASS' => $rowClass,

                    'ADMIN_MODULE'   => $action,
                    'U_ADMIN_MODULE' => Session::appendSid($fileName)
                ]
            );
            $rowCount++;
		}
	}

	$template->pparse('body');

    require_once '.' . $sep . 'page_footer_admin.php';
} elseif (isset($_GET['pane']) && $_GET['pane'] === 'right') {

    require_once '.' . $sep . 'page_header_admin.php';

    $template->setFileNames(['body' => 'admin/index_body.tpl']);

    $template->assignVars(
        [
            'L_WELCOME'     => $lang['Welcome_phpBB'],
            'L_ADMIN_INTRO' => $lang['Admin_intro'],
            'L_USERNAME'    => $lang['Username'],
            'L_LOCATION'    => $lang['Location'],
            'L_LAST_UPDATE' => $lang['Last_updated'],
            'L_IP_ADDRESS'  => $lang['IP_Address'],

            'L_NUMBER_FORUMS'     => $lang['Number_forums'],
            'L_NUMBER_CATEGORIES' => $lang['Number_categories'],

            'L_NUMBER_POSTS'  => $lang['Number_posts'],
            'L_NUMBER_TOPICS' => $lang['Number_topics'],
            'L_NUMBER_USERS'  => $lang['Number_users'],

            'L_POSTS_PER_DAY'  => $lang['Posts_per_day'],
            'L_TOPICS_PER_DAY' => $lang['Topics_per_day'],
            'L_USERS_PER_DAY'  => $lang['Users_per_day'],

            'L_AVATAR_DIR_SIZE' => $lang['Avatar_dir_size'],
            'L_DB_SIZE'         => $lang['Database_size'],

            'L_BOARD_STARTED' => $lang['Board_started'],

            'L_FORUM_LOCATION'   => $lang['Forum_Location'],
            'L_STARTED'          => $lang['Login'],
            'L_GZIP_COMPRESSION' => $lang['Gzip_compression'],

            'L_NUMBER_ONLINE_USERS'    => $lang['Online_users'],
            'L_NUMBER_ONLINE_REGISTERED_USERS'    => $lang['Online_registered_users'],
            'L_NUMBER_MODERATORS'      => $lang['Thereof_Moderators'],
            'L_NUMBER_ADMINISTRATORS'  => $lang['Thereof_Administrators'],
            'L_NUMBER_ACTIVE_USERS'    => $lang['Thereof_activated_users'],
            'L_NUMBER_NONACTIVE_USERS' => $lang['Thereof_deactivated_users'],

            'L_FORUM_STATISTICS' => $lang['Forum_stats'],
            'L_BOARD_STATISTICS' => $lang['Board_Statistics'],

            'L_VERSION_STATISTICS'  => $lang['Version_Statistics'],
            'L_MEMBERS_STATISTICS'  => $lang['Members_Statistics'],
            'L_DATABASE_STATISTICS' => $lang['Database_Statistics'],

            'L_NUMBER_THEMES' => $lang['Number_themes'],
            'L_NUMBER_WATCHING' => $lang['Number_watching'],

            'L_NUMBER_GROUPS' => $lang['Number_groups'],
            'L_NUMBER_SINGLE_GROUPS' => $lang['Number_single_groups'],
            'L_NUMBER_NOT_SINGLE_GROUPS' =>  $lang['Number_not_single_groups'],

            'L_NUMBER_AUTO_LOGGED_IN_USERS' => $lang['Number_auto_logged'],
            'L_PERCENT_AUTO_LOGGED_IN' => $lang['Percent_auto_logged'],

            'L_PHPBB_VERSION' => $lang['Version_of_board'],
            'L_PHP_VERSION'   => $lang['Version_of_PHP'],
            'L_MYSQL_VERSION' => $lang['Version_of_MySQL'],
        ]
    );

    //
	// Get forum statistics
	//
	$totalPosts  = get_db_stat('postcount');
	$totalUsers  = get_db_stat('usercount');
	$totalTopics = get_db_stat('topiccount');

    $mysql_version = dibi::query('SELECT VERSION() AS mysql_version')->fetchSingle();

	$totalForumsCount = dibi::select('COUNT(*)')
        ->as('total')
        ->from(FORUMS_TABLE)
        ->fetchSingle();

    $totalCategoriesCount = dibi::select('COUNT(*)')
        ->as('total')
        ->from(CATEGORIES_TABLE)
        ->fetchSingle();

	$totalOnlineUsers = dibi::select('COUNT(*)')
        ->from(SESSIONS_TABLE)
        ->fetchSingle();

    $registeredOnlineUsers = dibi::select('COUNT(*)')
        ->from(SESSIONS_TABLE)
        ->where('session_logged_in = %i', 1)
        ->groupBy('session_user_id')
        ->fetchSingle();

    // admin stats mod BEGIN
    $totalUnActiveUsers = dibi::select('COUNT(*)')
        ->as('total')
        ->from(USERS_TABLE)
        ->where('user_active = %i', 0)
        ->where('user_id != %i', ANONYMOUS)
        ->fetchSingle();

    $totalModerators = dibi::select('COUNT(user_id)')
        ->as('total')
        ->from(USERS_TABLE)
        ->where('user_level = %i', MOD)
        ->where('user_id != %i', ANONYMOUS)
        ->fetchSingle();

    $totalAdministrators = dibi::select('COUNT(user_id)')
        ->as('total')
        ->from(USERS_TABLE)
        ->where('user_level = %i', ADMIN)
        ->where('user_id != %i', ANONYMOUS)
        ->fetchSingle();

    $totalTemplates = dibi::select('COUNT(themes_id)')
        ->as('total')
        ->from(THEMES_TABLE)
        ->fetchSingle();

    $totalTopicWatching = dibi::select('COUNT(*)')
        ->as('total')
        ->from(TOPICS_WATCH_TABLE)
        ->fetchSingle();

    $totalGroups = dibi::select('COUNT(group_id)')
        ->from(GROUPS_TABLE)
        ->fetchSingle();

    $totalSingleGroups = dibi::select('COUNT(group_id)')
        ->from(GROUPS_TABLE)
        ->where('[group_single_user] = %i', 1)
        ->fetchSingle();

    $totalNotSingleGroups = dibi::select('COUNT(group_id)')
        ->from(GROUPS_TABLE)
        ->where('[group_single_user] = %i', 0)
        ->fetchSingle();

    $totalAutoLoggedInUsers = dibi::select('COUNT(user_id)')
        ->from(
            dibi::select('user_id')
            ->from(SESSIONS_KEYS_TABLE)
            ->groupBy('user_id')
        )
        ->as('x')
        ->fetchSingle();

    $percentAutoLoggedUsers = $totalAutoLoggedInUsers / $totalUsers;

    $totalActiveUsers = $totalUsers - $totalUnActiveUsers;

	$startDate = create_date($board_config['default_dateformat'], $board_config['board_startdate'], $board_config['board_timezone']);

    $userTimeZone = isset($profileData['user_timezone']) ? $profileData['user_timezone'] : $board_config['board_timezone'];

    $zone = new DateTimeZone($userTimeZone);

    $boardStartDay = new DateTime();
    $boardStartDay->setTimezone($zone);
    $boardStartDay->setTimestamp($board_config['board_startdate']);

    $boardRunningDays = new DateTime();
    $boardRunningDays->setTimezone($zone);
    $boardRunningDays = $boardRunningDays->diff($boardStartDay)->days;

	$postsPerDay  = sprintf('%.2f', $totalPosts / $boardRunningDays);
	$topicsPerDay = sprintf('%.2f', $totalTopics / $boardRunningDays);
	$usersPerDay  = sprintf('%.2f', $totalUsers / $boardRunningDays);

	$avatarDirSize   = 0;
	$enabledExtensions = ['*.jpg', '*.jpeg', '*.pjpeg', '*.gif', '*.png'];

    $avatars = Finder::findFiles($enabledExtensions)->in($phpbb_root_path . $board_config['avatar_path']);

    if (count($avatars)) {
        /**
         * @var SplFileInfo $avatar
         */
        foreach ($avatars as $avatar) {
            $avatarDirSize += $avatar->getSize();
        }

        $avatarDirSize = get_formatted_filesize($avatarDirSize);
    } else {
        // Couldn't open Avatar dir.
        $avatarDirSize = $lang['Not_available'];
    }

    if ($postsPerDay > $totalPosts) {
        $postsPerDay = $totalPosts;
    }

    if ($topicsPerDay > $totalTopics) {
        $topicsPerDay = $totalTopics;
    }

    if ($usersPerDay > $totalUsers) {
        $usersPerDay = $totalUsers;
    }

    $dbSize = get_database_size();

    $template->assignVars(
        [
            'START_DATE' => $startDate,

            'POSTS_PER_DAY'  => $postsPerDay,
            'TOPICS_PER_DAY' => $topicsPerDay,
            'USERS_PER_DAY'  => $usersPerDay,

            'AVATAR_DIR_SIZE' => $avatarDirSize,
            'DB_SIZE'         => $dbSize,

            'GZIP_COMPRESSION' => $board_config['gzip_compress'] ? $lang['ON'] : $lang['OFF'],

            'NUMBER_OF_CATEGORIES' => $totalCategoriesCount,
            'NUMBER_OF_FORUMS'     => $totalForumsCount,
            'NUMBER_OF_TOPICS'     => $totalTopics,
            'NUMBER_OF_POSTS'      => $totalPosts,
            'NUMBER_OF_USERS'      => $totalUsers,

            'NUMBER_OF_ONLINE_USERS' => $totalOnlineUsers,

            'NUMBER_OF_REGISTERED_ONLINE_USERS' => $registeredOnlineUsers,

            'NUMBER_OF_ACTIVE_USERS'    => $totalActiveUsers,
            'NUMBER_OF_NONACTIVE_USERS' => $totalUnActiveUsers,

            'NUMBER_OF_MODERATORS'      => $totalModerators,
            'NUMBER_OF_ADMINISTRATORS'  => $totalAdministrators,

            'NUMBER_OF_THEMES' => $totalTemplates,
            'NUMBER_OF_TOPIC_WATCHING' => $totalTopicWatching,

            'NUMBER_OF_GROUPS' => $totalGroups,
            'NUMBER_OF_SINGLE_GROUPS' => $totalSingleGroups,
            'NUMBER_OF_NOT_SINGLE_GROUPS' =>  $totalNotSingleGroups,

            'NUMBER_OF_AUTO_LOGGED_IN_USERS' => $totalAutoLoggedInUsers,
            'PERCENT_AUTO_LOGGED_IN' => round($percentAutoLoggedUsers, 2),

            'PHPBB_VERSION' => '2' . $board_config['version'],
            'PHP_VERSION'   => PHP_VERSION,
            'MYSQL_VERSION' => $mysql_version,
        ]
    );

    $template->pparse('body');

    require_once '.' . $sep . 'page_footer_admin.php';
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