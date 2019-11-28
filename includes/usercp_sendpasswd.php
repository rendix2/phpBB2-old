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

use Nette\Utils\Random;

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

$sep = DIRECTORY_SEPARATOR;

if (isset($_POST['submit'])) {
    CSRF::validatePost();

	$username = !empty($_POST['username']) ? phpbb_clean_username($_POST['username'])            : '';
	$email    = !empty($_POST['email'])    ? trim(strip_tags(htmlspecialchars($_POST['email']))) : '';

	$user = dibi::select(['user_id', 'username', 'user_email', 'user_active', 'user_lang'])
        ->from(Tables::USERS_TABLE)
        ->where('user_email = %s', $email)
        ->where('username = %s', $username)
        ->fetch();

	if (!$user) {
        message_die(GENERAL_MESSAGE, $lang['No_email_match']);
    }

    if (!$user->user_active) {
        message_die(GENERAL_MESSAGE, $lang['No_send_account_inactive']);
    }

    $userActivationKey = Random::generate(25);
    $keyLength         = 54 - mb_strlen($serverUrl); // TODO $serverUrl does not exist!
    $keyLength         = $keyLength > 6 ? $keyLength : 6;
    $userActivationKey = substr($userActivationKey, 0, $keyLength);
    $userPassword      = Random::generate(12);

    $updateData = [
        'user_newpasswd' => password_hash($userPassword, PASSWORD_BCRYPT),
        'user_actkey'    => $userActivationKey
    ];

    dibi::update(Tables::USERS_TABLE, $updateData)
        ->where('user_id = %i', $user->user_id)
        ->execute();

    $emailer = new Emailer($board_config['smtp_delivery']);

    $emailer->setFrom($board_config['board_email']);
    $emailer->setReplyTo($board_config['board_email']);

    $emailer->useTemplate('user_activate_passwd', $user->user_lang);
    $emailer->setEmailAddress($user->user_email);
    $emailer->setSubject($lang['New_password_activation']);

    $emailer->assignVars(
        [
            'SITENAME'  => $board_config['sitename'],
            'USERNAME'  => $user->username,
            'PASSWORD'  => $userPassword,
            'EMAIL_SIG' => !empty($board_config['board_email_sig']) ? str_replace('<br />', "\n", "-- \n" . $board_config['board_email_sig']) : '',

            'U_ACTIVATE' => $serverUrl . '?mode=activate&' . POST_USERS_URL . '=' . $user->user_id . '&act_key=' . $userActivationKey
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
PageHelper::header($template, $userdata, $board_config, $lang, $images,  $theme, $page_title, $gen_simple_header);

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

PageHelper::footer($template, $userdata, $lang, $gen_simple_header);

?>