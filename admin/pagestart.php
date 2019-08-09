<?php
/***************************************************************************
 *                               pagestart.php
 *                            -------------------
 *   begin                : Thursday, Aug 2, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: pagestart.php 5487 2006-01-22 17:11:09Z grahamje $
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

if (!defined('IN_PHPBB')) {
    die('Hacking attempt');
}

define('IN_ADMIN', true);

// require files
require_once $phpbb_root_path . 'common.php';
require_once $phpbb_root_path . 'includes' . $sep . 'functions_admin.php';

//
// Start session management
//
$userdata = init_userprefs(PAGE_ADMIN_INDEX);
//
// End session management
//

if (!$userdata['session_logged_in']) {
    redirect(Session::appendSid('login.php?redirect=admin/index.php', true));
} elseif ($userdata['user_level'] !== ADMIN) {
    message_die(GENERAL_MESSAGE, $lang['Not_admin']);
}

if ($_GET['sid'] !== $userdata['session_id']) {
    redirect('index.php?sid=' . $userdata['session_id']);
}

if (!$userdata['session_admin']) {
    redirect(Session::appendSid('login.php?redirect=admin/index.php&admin=1', true));
}

if (empty($no_page_header)) {
    // Not including the pageheader can be neccesarry if META tags are
    // needed in the calling script.
    require_once '.' . $sep . 'page_header_admin.php';
}

?>