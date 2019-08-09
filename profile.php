<?php
/***************************************************************************
 *                                profile.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: profile.php 5777 2006-04-09 16:17:28Z grahamje $
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

define('IN_PHPBB', true);

$sep = DIRECTORY_SEPARATOR;
$phpbb_root_path = '.' . $sep;

require_once $phpbb_root_path . 'common.php';

//
// Start session management
//
$userdata = init_userprefs(PAGE_PROFILE);
//
// End session management
//

// session id check
if (!empty($_POST['sid']) || !empty($_GET['sid'])) {
	$sid = !empty($_POST['sid']) ? $_POST['sid'] : $_GET['sid'];
} else {
	$sid = '';
}

//
// Set default email variables
//
$script_name = preg_replace('/^\/?(.*?)\/?$/', '\1', trim($board_config['script_path']));
$script_name = $script_name !== '' ? $script_name . '/profile.php' : 'profile.php';
$server_url = getServerUrl($board_config, $script_name);

// -----------------------
// Page specific functions
//
function gen_rand_string($hash)
{
	$rand_str = dss_rand();

	return $hash ? md5($rand_str) : substr($rand_str, 0, 8);
}
//
// End page specific functions
// ---------------------------

//
// Start of program proper
//
if (isset($_GET[POST_MODE]) || isset($_POST[POST_MODE])) {
    $mode = isset($_GET[POST_MODE]) ? $_GET[POST_MODE] : $_POST[POST_MODE];

    switch ($mode) {
        case 'viewprofile':
            require_once $phpbb_root_path . 'includes' . $sep . 'usercp_viewprofile.php';
            break;

            // yes both!
        case 'editprofile':
        case 'register':

            if (!$userdata['session_logged_in'] && $mode === 'editprofile') {
                redirect(Session::appendSid('login.php?redirect=profile.php&mode=editprofile', true));
            }

        require_once $phpbb_root_path . 'includes' . $sep . 'usercp_register.php';

            break;

        case 'confirm':
            // Visual Confirmation
            if ($userdata['session_logged_in']) {
                exit;
            }

            require_once $phpbb_root_path . 'includes' . $sep . 'usercp_confirm.php';

            break;

        case 'sendpassword':
            require_once $phpbb_root_path . 'includes' . $sep . 'usercp_sendpasswd.php';

            break;

        case 'activate':
            require_once $phpbb_root_path . 'includes' . $sep . 'usercp_activate.php';

            break;

        case 'email':
            require_once $phpbb_root_path . 'includes' . $sep . 'usercp_email.php';
            break;
        default:
            message_die(GENERAL_MESSAGE, 'Unknown mode.');
    }

    exit;
}

redirect(Session::appendSid('index.php', true));

?>