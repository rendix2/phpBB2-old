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

if (!defined('IN_PHPBB')) {
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

if (trim($user->user_actkey) !== '' && trim($user->user_actkey) !== trim($_GET['act_key'])) {
    message_die(GENERAL_MESSAGE, $lang['Wrong_activation']);
}

if ($user->user_active && trim($user->user_actkey) === '') {
    $template->assignVars(
        ['META' => '<meta http-equiv="refresh" content="10;url=' . Session::appendSid('index.php') . '">']
    );

    message_die(GENERAL_MESSAGE, $lang['Already_activated']);
}

// we have done some basic checks
// now we can active user

if ((int)$board_config['require_activation'] === USER_ACTIVATION_ADMIN && $user->user_newpasswd === '') {
    if (!$userdata['session_logged_in']) {
        redirect(Session::appendSid('login.php?redirect=profile.php&mode=activate&' . POST_USERS_URL . '=' . $user->user_id . '&act_key=' . trim($_GET['act_key'])));
    } elseif ($userdata['user_level'] !== ADMIN) {
        message_die(GENERAL_MESSAGE, $lang['Not_Authorised']);
    }
}

$update_data = [
    'user_active' => 1,
    'user_actkey' => ''
];

$updatePassword = false;

if ($user->user_newpasswd !== '') {
    $updatePassword = true;

    $update_data['user_password'] = $user->user_newpasswd;
    $update_data['user_newpasswd'] = '';
}

dibi::update(USERS_TABLE, $update_data)
    ->where('user_id = %i', $user->user_id)
    ->execute();

if (!$updatePassword && (int)$board_config['require_activation'] === USER_ACTIVATION_ADMIN) {
    include $phpbb_root_path . 'includes/Emailer.php';
    $emailer = new Emailer($board_config['smtp_delivery']);

    $emailer->setFrom($board_config['board_email']);
    $emailer->setReplyTo($board_config['board_email']);

    $emailer->use_template('admin_welcome_activated', $user->user_lang);
    $emailer->setEmailAddress($user->user_email);
    $emailer->setSubject($lang['Account_activated_subject']);

    $emailer->assignVars(
        [
            'SITENAME'  => $board_config['sitename'],
            'USERNAME'  => $user->username,
            'EMAIL_SIG' => !empty($board_config['board_email_sig']) ? str_replace('<br />', "\n", "-- \n" . $board_config['board_email_sig']) : ''
        ]
    );
    $emailer->send();
    $emailer->reset();

    $template->assignVars(
        [
            'META' => '<meta http-equiv="refresh" content="10;url=' . Session::appendSid('index.php') . '">'
        ]
    );

    message_die(GENERAL_MESSAGE, $lang['Account_active_admin']);
} else {
    $template->assignVars(
        [
            'META' => '<meta http-equiv="refresh" content="10;url=' . Session::appendSid('index.php') . '">'
        ]
    );

    $message = $updatePassword ? $lang['Password_activated'] : $lang['Account_active'];
    message_die(GENERAL_MESSAGE, $message);
}

?>