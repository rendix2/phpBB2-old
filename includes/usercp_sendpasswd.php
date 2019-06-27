<?php
/***************************************************************************
 *                           usercp_sendpasswd.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: usercp_sendpasswd.php 5204 2005-09-14 18:14:30Z acydburn $
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

if (isset($_POST['submit'])) {
    CSRF::validatePost();

	$username = !empty($_POST['username']) ? phpbb_clean_username($_POST['username'])            : '';
	$email    = !empty($_POST['email'])    ? trim(strip_tags(htmlspecialchars($_POST['email']))) : '';

	$user = dibi::select(['user_id', 'username', 'user_email', 'user_active', 'user_lang'])
        ->from(USERS_TABLE)
        ->where('user_email = %s', $email)
        ->where('username = %s', $username)
        ->fetch();

	if (!$user) {
        message_die(GENERAL_MESSAGE, $lang['No_email_match']);
    }

    if (!$user->user_active) {
        message_die(GENERAL_MESSAGE, $lang['No_send_account_inactive']);
    }

    $user_actkey   = gen_rand_string(true);
    $key_len       = 54 - mb_strlen($server_url);
    $key_len       = $key_len > 6 ? $key_len : 6;
    $user_actkey   = substr($user_actkey, 0, $key_len);
    $user_password = gen_rand_string(false);

    $update_data = [
        'user_newpasswd' => password_hash($user_password, PASSWORD_BCRYPT),
        'user_actkey'    => $user_actkey
    ];

    dibi::update(USERS_TABLE, $update_data)
        ->where('user_id = %i', $user->user_id)
        ->execute();

    $emailer = new Emailer($board_config['smtp_delivery']);

    $emailer->setFrom($board_config['board_email']);
    $emailer->setReplyTo($board_config['board_email']);

    $emailer->use_template('user_activate_passwd', $user->user_lang);
    $emailer->setEmailAddress($user->user_email);
    $emailer->setSubject($lang['New_password_activation']);

    $emailer->assignVars(
        [
            'SITENAME'  => $board_config['sitename'],
            'USERNAME'  => $user->username,
            'PASSWORD'  => $user_password,
            'EMAIL_SIG' => !empty($board_config['board_email_sig']) ? str_replace('<br />', "\n", "-- \n" . $board_config['board_email_sig']) : '',

            'U_ACTIVATE' => $server_url . '?mode=activate&' . POST_USERS_URL . '=' . $user->user_id . '&act_key=' . $user_actkey
        ]
    );
    $emailer->send();
    $emailer->reset();

    $template->assignVars(
        [
            'META' => '<meta http-equiv="refresh" content="15;url=' . Session::appendSid('index.php') . '">'
        ]
    );

    $message = $lang['Password_updated'] . '<br /><br />' . sprintf($lang['Click_return_index'], '<a href="' . Session::appendSid('index.php') . '">', '</a>');

    message_die(GENERAL_MESSAGE, $message);
}

//
// Output basic page
//
require_once $phpbb_root_path . 'includes/page_header.php';

$template->setFileNames(['body' => 'profile_send_pass.tpl']);
make_jumpbox('viewforum.php');

$template->assignVars(
    [
        'USERNAME' => $user->username,
        'EMAIL'    => $email,

        'F_LOGIN_FORM_TOKEN' => CSRF::getInputHtml(),

        'L_SEND_PASSWORD'  => $lang['Send_password'],
        'L_ITEMS_REQUIRED' => $lang['Items_required'],
        'L_EMAIL_ADDRESS'  => $lang['Email_address'],
        'L_SUBMIT'         => $lang['Submit'],
        'L_RESET'          => $lang['Reset'],

        'S_HIDDEN_FIELDS'  => '',
        'S_PROFILE_ACTION' => Session::appendSid('profile.php?mode=sendpassword')
    ]
);

$template->pparse('body');

require_once $phpbb_root_path . 'includes/page_tail.php';

?>