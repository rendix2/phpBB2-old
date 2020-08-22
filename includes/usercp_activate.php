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

$columns = ['user_active', 'user_id', 'username', 'user_email', 'user_new_password', 'user_lang', 'user_act_key'];

$user = dibi::select($columns)
    ->from(Tables::USERS_TABLE)
    ->where('[user_id] = %i', (int)$_GET[POST_USERS_URL])
    ->fetch();

if (!$user) {
    message_die(GENERAL_MESSAGE, $lang['No_such_user']);
}

if (trim($user->user_act_key) !== '' && trim($user->user_act_key) !== trim($_GET['act_key'])) {
    message_die(GENERAL_MESSAGE, $lang['Wrong_activation']);
}

if ($user->user_active && trim($user->user_act_key) === '') {
    $template->assignVars(
        ['META' => '<meta http-equiv="refresh" content="10;url=' . Session::appendSid('index.php') . '">']
    );

    message_die(GENERAL_MESSAGE, $lang['Already_activated']);
}

// we have done some basic checks
// now we can active user

if ((int)$board_config['require_activation'] === USER_ACTIVATION_ADMIN && $user->user_new_password === '') {
    if (!$userdata['session_logged_in']) {
        redirect(Session::appendSid('login.php?redirect=profile.php&mode=activate&' . POST_USERS_URL . '=' . $user->user_id . '&act_key=' . trim($_GET['act_key'])));
    } elseif ($userdata['user_level'] !== ADMIN) {
        message_die(GENERAL_MESSAGE, $lang['Not_Authorised']);
    }
}

$updateData = [
    'user_active' => 1,
    'user_act_key' => ''
];

$updatePassword = false;

if ($user->user_new_password !== '') {
    $updatePassword = true;

    $updateData['user_password']  = $user->user_new_password;
    $updateData['user_new_password'] = '';
}

dibi::update(Tables::USERS_TABLE, $updateData)
    ->where('[user_id] = %i', $user->user_id)
    ->execute();

if (!$updatePassword && (int)$board_config['require_activation'] === USER_ACTIVATION_ADMIN) {
    $params =
    [
        'SITENAME'  => $board_config['sitename'],
        'USERNAME'  => $user->username,
        'EMAIL_SIG' => $board_config['board_email_sig'],
    ];

    $mailer = new \phpBB2\Mailer(
        new LatteFactory($storage, $userdata),
        $board_config,
        'admin_welcome_activated',
        $params,
        $user->user_lang,
        $lang['Account_activated_subject'],
        $user->user_email
    );

    $mailer->send();

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