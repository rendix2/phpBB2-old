<?php
/***************************************************************************
 *                             usercp_email.php 
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: usercp_email.php 6772 2006-12-16 13:11:28Z acydburn $
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

// Is send through board enabled? No, return to index
if ($userdata['user_level'] !== ADMIN && !$board_config['board_email_form']) {
    message_die(GENERAL_MESSAGE, $lang['Board_prevent_email']);
}

if (!empty($_GET[POST_USERS_URL]) || !empty($_POST[POST_USERS_URL]))  {
    $user_id = !empty($_GET[POST_USERS_URL]) ? (int)$_GET[POST_USERS_URL] : (int)$_POST[POST_USERS_URL];
} else {
	message_die(GENERAL_MESSAGE, $lang['No_user_specified']);
}

if (!$userdata['session_logged_in']) {
	redirect(Session::appendSid('login.php?redirect=profile.php&mode=email&' . POST_USERS_URL . "=$user_id", true));
}

$user = dibi::select(['username', 'user_email', 'user_lang'])
    ->from(Tables::USERS_TABLE)
    ->where('[user_id] = %i', $user_id)
    ->fetch();

if (!$user) {
    message_die(GENERAL_MESSAGE, $lang['User_not_exist']);
}

$sep = DIRECTORY_SEPARATOR;

if (time() - $userdata['user_email_time'] < $board_config['flood_interval']) {
    message_die(GENERAL_MESSAGE, $lang['Flood_email_limit']);
}

$error = false;

if (isset($_POST['submit'])) {
    if (empty($_POST['subject'])) {
        $error = true;
        $error_msg = !empty($error_msg) ? $error_msg . '<br />' . $lang['Empty_subject_email'] : $lang['Empty_subject_email'];
    } else {
        $subject = trim(stripslashes($_POST['subject']));
    }

    if (empty($_POST['message'])) {
        $error = true;
        $error_msg = !empty($error_msg) ? $error_msg . '<br />' . $lang['Empty_message_email'] : $lang['Empty_message_email'];
    } else {
        $message = trim(stripslashes($_POST['message']));
    }

    if (!$error) {
        $result = dibi::update(Tables::USERS_TABLE, ['user_email_time' => time()])
            ->where('[user_id] = %i', $userdata['user_id'])
            ->execute();

        if (!$result) {
            message_die(GENERAL_ERROR, 'Could not update last email time');
        }

        $params =
            [
                'SITENAME' => $board_config['sitename'],
                'BOARD_EMAIL' => $board_config['board_email'],
                'FROM_USERNAME' => $userdata['username'],
                'TO_USERNAME' => $user->username,
                'MESSAGE' => $message
            ];

        $mailer = new \phpBB2\Mailer(
            new LatteFactory($storage, $userdata),
            $board_config,
            'profile_send_email',
            $user->user_lang,
            $params,
            $subject,
            $user->user_email
        );

        if (!empty($_POST['cc_email'])) {
            $mailer->getMessage()->setFrom($userdata['user_email']);
            $mailer->getMessage()->addReplyTo($userdata['user_email']);
            $mailer->getMessage()->addTo($userdata['user_email']);
            $mailer->getMessage()->setSubject($subject);

            $mailer->send();
        }

        $template->assignVars(
            [
                'META' => '<meta http-equiv="refresh" content="5;url=' . Session::appendSid('index.php') . '">'
            ]
        );

        $message  = $lang['Email_sent'] . '<br /><br />';
        $message .= sprintf($lang['Click_return_index'], '<a href="' . Session::appendSid('index.php') . '">', '</a>');

        message_die(GENERAL_MESSAGE, $message);
    }
}

PageHelper::header($template, $userdata, $board_config, $lang, $images, $theme, $lang['Send_email_msg'], $gen_simple_header);

$template->setFileNames(['body' => 'profile_send_email.tpl']);

make_jumpbox('viewforum.php');

if ($error) {
    $template->setFileNames(['reg_header' => 'error_body.tpl']);
    $template->assignVars(['ERROR_MESSAGE' => $error_msg]);
    $template->assignVarFromHandle('ERROR_BOX', 'reg_header');
}

$template->assignVars(
    [
        'USERNAME' => $user->username,

        'S_HIDDEN_FIELDS' => '',
        'S_POST_ACTION' => Session::appendSid('profile.php?mode=email&amp;' . POST_USERS_URL . "=$user_id"),

        'L_SEND_EMAIL_MSG' => $lang['Send_email_msg'],
        'L_RECIPIENT' => $lang['Recipient'],
        'L_SUBJECT' => $lang['Subject'],
        'L_MESSAGE_BODY' => $lang['Message_body'],
        'L_MESSAGE_BODY_DESC' => $lang['Email_message_desc'],
        'L_EMPTY_SUBJECT_EMAIL' => $lang['Empty_subject_email'],
        'L_EMPTY_MESSAGE_EMAIL' => $lang['Empty_message_email'],
        'L_OPTIONS' => $lang['Options'],
        'L_CC_EMAIL' => $lang['CC_email'],
        'L_SPELLCHECK' => $lang['Spellcheck'],
        'L_SEND_EMAIL' => $lang['Send_email']
    ]
);

$template->pparse('body');

PageHelper::footer($template, $userdata, $lang, $gen_simple_header);


?>