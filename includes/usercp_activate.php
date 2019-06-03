<?php
/***************************************************************************
 *                            usercp_activate.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: usercp_activate.php 5204 2005-09-14 18:14:30Z acydburn $
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

if ( !defined('IN_PHPBB') ) {
	die('Hacking attempt');
}

$columns = ['user_active', 'user_id', 'username', 'user_email', 'user_newpasswd', 'user_lang', 'user_actkey'];

$user = dibi::select($columns)
    ->from(USERS_TABLE)
    ->where('user_id = %i', (int)$_GET[POST_USERS_URL])
    ->fetch();

if (!$user) {
    message_die(GENERAL_MESSAGE, $lang['No_such_user']);
}

if (trim($user->user_actkey) !== '' && trim($row['user_actkey']) !== trim($_GET['act_key'])) {
    message_die(GENERAL_MESSAGE, $lang['Wrong_activation']);
}

if ($row['user_active'] && trim($row['user_actkey']) == '') {
    $template->assign_vars(
        ['META' => '<meta http-equiv="refresh" content="10;url=' . append_sid("index.php") . '">']
    );

    message_die(GENERAL_MESSAGE, $lang['Already_activated']);
}

// we have done some basic checks
// now we can active user

if ((int)$board_config['require_activation'] == USER_ACTIVATION_ADMIN && $row['user_newpasswd'] == '') {
    if (!$userdata['session_logged_in']) {
        redirect(append_sid('login.php?redirect=profile.php&mode=activate&' . POST_USERS_URL . '=' . $row['user_id'] . '&act_key=' . trim($_GET['act_key'])));
    } elseif ($userdata['user_level'] !== ADMIN) {
        message_die(GENERAL_MESSAGE, $lang['Not_Authorised']);
    }
}

$update_data = [
    'user_active' => 1,
    'user_actkey' => ''
];

if ($row['user_newpasswd'] !== '') {
    $update_data['user_password'] = $row['user_newpasswd'];
    $update_data['user_newpasswd'] = '';
}

dibi::update(USERS_TABLE, $update_data)
    ->where('user_id = %i', $row['user_id'])
    ->execute();

if ((int)$board_config['require_activation'] == USER_ACTIVATION_ADMIN && $sql_update_pass == '') {
    include $phpbb_root_path . 'includes/emailer.php';
    $emailer = new emailer($board_config['smtp_delivery']);

    $emailer->from($board_config['board_email']);
    $emailer->replyto($board_config['board_email']);

    $emailer->use_template('admin_welcome_activated', $row['user_lang']);
    $emailer->email_address($row['user_email']);
    $emailer->set_subject($lang['Account_activated_subject']);

    $emailer->assign_vars([
        'SITENAME'  => $board_config['sitename'],
        'USERNAME'  => $row['username'],
        'PASSWORD'  => $password_confirm,
        'EMAIL_SIG' => !empty($board_config['board_email_sig']) ? str_replace('<br />', "\n",
            "-- \n" . $board_config['board_email_sig']) : ''
    ]);
    $emailer->send();
    $emailer->reset();

    $template->assign_vars([
        'META' => '<meta http-equiv="refresh" content="10;url=' . append_sid("index.php") . '">'
    ]);

    message_die(GENERAL_MESSAGE, $lang['Account_active_admin']);
} else {
    $template->assign_vars([
        'META' => '<meta http-equiv="refresh" content="10;url=' . append_sid("index.php") . '">'
    ]);

    $message = ($sql_update_pass == '') ? $lang['Account_active'] : $lang['Password_activated'];
    message_die(GENERAL_MESSAGE, $message);
}

?>