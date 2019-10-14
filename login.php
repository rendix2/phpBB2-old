<?php
/***************************************************************************
 *                                login.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: login.php 6772 2006-12-16 13:11:28Z acydburn $
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

/**
 * @param BaseTemplate $template
 * @param array        $lang
 */
function loginFailed(BaseTemplate $template, array $lang)
{
    $redirect = !empty($_POST['redirect']) ? str_replace('&amp;', '&', htmlspecialchars($_POST['redirect'])) : '';
    $redirect = str_replace('?', '&', $redirect);

    if (false !== strpos(urldecode($redirect), "\n") || false !== strpos(urldecode($redirect), "\r") || false !== strpos(urldecode($redirect), ';url')) {
        message_die(GENERAL_ERROR, 'Tried to redirect to potentially insecure url.');
    }

    $redirectLink = $redirect ? '?redirect='.$redirect : '';

    $template->assignVars(
        [
            'META' => '<meta http-equiv="refresh" content="3;url=login.php' . $redirectLink . '">'
        ]
    );

    $message = $lang['Error_login'] . '<br /><br />' . sprintf($lang['Click_return_login'], '<a href="login.php' . $redirectLink  .'">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_index'], '<a href="' . Session::appendSid('index.php') . '">', '</a>');

    message_die(GENERAL_MESSAGE, $message);
}

//
// Allow people to reach login page if
// board is shut down
//
define('IN_LOGIN', true);
define('IN_PHPBB', true);

$sep = DIRECTORY_SEPARATOR;
$phpbb_root_path = '.' . $sep;

require_once $phpbb_root_path . 'common.php';

//
// Set page ID for session management
//
$userdata = init_userprefs(PAGE_LOGIN);
//
// End session management
//

// session id check
if (isset($_POST['sid']) || isset($_GET['sid'])) {
	$sid = isset($_POST['sid']) ? $_POST['sid'] : $_GET['sid'];
} else {
	$sid = '';
}

if (isset($_POST['login']) || isset($_GET['login']) || isset($_POST['logout']) || isset($_GET['logout'])) {
	if (( isset($_POST['login']) || isset($_GET['login']) ) && (!$userdata['session_logged_in'] || isset($_POST['admin']))) {
        CSRF::validatePost();

		$username = isset($_POST['username']) ? phpbb_clean_username($_POST['username']) : '';
		$password = isset($_POST['password']) ? $_POST['password'] : '';

        $columns = [
            'user_id',
            'username',
            'user_password',
            'user_active',
            'user_level',
            'user_login_tries',
            'user_last_login_try'
        ];

		$row = dibi::select($columns)
            ->from(USERS_TABLE)
            ->where('username = %s', $username)
            ->fetch();

		// user was not found!
		if (!$row) {
            loginFailed($template, $lang);
        }

        if ($row->user_level !== ADMIN && $board_config['board_disable']) {
            redirect(Session::appendSid('index.php', true));
        } else {
            // If the last login is more than x minutes ago, then reset the login tries/time
            if ($row->user_last_login_try && $board_config['login_reset_time'] && $row->user_last_login_try < (time() - ($board_config['login_reset_time'] * 60))) {
                dibi::update(USERS_TABLE, ['user_login_tries' => 0, 'user_last_login_try' => 0])
                    ->where('user_id = %i', $row->user_id)
                    ->execute();

                $row->user_last_login_try = $row->user_login_tries = 0;
            }

            // Check to see if user is allowed to login again... if his tries are exceeded
            if (
                $row->user_last_login_try &&
                $board_config['login_reset_time'] &&
                $board_config['max_login_attempts'] &&
                $row->user_last_login_try >= (time() - ($board_config['login_reset_time'] * 60)) &&
                $row->user_login_tries >= $board_config['max_login_attempts'] &&
                $userdata['user_level'] !== ADMIN
            ) {
                $message = sprintf($lang['Login_attempts_exceeded'], $board_config['max_login_attempts'], $board_config['login_reset_time']);

                message_die(GENERAL_MESSAGE, $message);
            }

            // password matches and user is active
            // coool, everything is OK, let login and redirect
            if (password_verify($password, $row->user_password) && $row->user_active) {
                $session_id = Session::begin(
                    $row->user_id,
                    $user_ip,
                    PAGE_INDEX,
                    false,
                    isset($_POST['autologin']),
                    isset($_POST['admin'])
                );

                // Reset login tries
                dibi::update(USERS_TABLE, ['user_login_tries' => 0, 'user_last_login_try' => 0])
                    ->where('user_id = %i', $row->user_id)
                    ->execute();

                if ($session_id) {
                    $url = !empty($_POST['redirect']) ? str_replace('&amp;', '&', htmlspecialchars($_POST['redirect'])) : 'index.php';
                    redirect(Session::appendSid($url, true));
                } else {
                    message_die(CRITICAL_ERROR, "Couldn't start session : login", '', __LINE__, __FILE__);
                }
            } elseif ($row->user_active) { // Only store a failed login attempt for an active user - inactive users can't login even with a correct password
                // Save login tries and last login
                if ($row->user_id !== ANONYMOUS) {
                    $updatData = [
                        'user_login_tries%sql' => 'user_login_tries + 1',
                        'user_last_login_try' => time()
                    ];

                    dibi::update(USERS_TABLE, $updatData)
                        ->where('user_id = %i', $row->user_id)
                        ->execute();
                }
            }

            loginFailed($template, $lang);
        }
	} elseif (( isset($_GET['logout']) || isset($_POST['logout']) ) && $userdata['session_logged_in']) {
		// session id check
        if ($sid === '' || $sid !== $userdata['session_id']) {
            message_die(GENERAL_ERROR, 'Invalid_session');
        }

        Session::end($userdata['session_id'], $userdata['user_id']);

        if (!empty($_POST['redirect']) || !empty($_GET['redirect'])) {
            $url = !empty($_POST['redirect']) ? htmlspecialchars($_POST['redirect']) : htmlspecialchars($_GET['redirect']);
            $url = str_replace('&amp;', '&', $url);
            redirect(Session::appendSid($url, true));
        } else {
            redirect(Session::appendSid('index.php', true));
        }
    } else {
        $url = !empty($_POST['redirect']) ? str_replace('&amp;', '&', htmlspecialchars($_POST['redirect'])) : 'index.php';
        redirect(Session::appendSid($url, true));
    }
} else {
	//
	// Do a full login page dohickey if
	// user not already logged in
	//
	if (!$userdata['session_logged_in'] || (isset($_GET['admin']) && $userdata['session_logged_in'] && $userdata['user_level'] === ADMIN)) {
		$page_title = $lang['Login'];

        PageHelper::header($template, $userdata, $board_config, $lang, $images,  $theme, $page_title, $gen_simple_header);

        $template->setFileNames(['body' => 'login_body.tpl']);

        $forwardPage = '';

        if (isset($_POST['redirect']) || isset($_GET['redirect'])) {
            $forwardTo = $_SERVER['QUERY_STRING'];

            if (preg_match("/^redirect=([a-z0-9\.#\/\?&=\+\-_]+)/si", $forwardTo, $forward_matches)) {
                $forwardTo         = !empty($forward_matches[3]) ? $forward_matches[3] : $forward_matches[1];
                $forwardMatch      = explode('&', $forwardTo);
                $countForwardMatch = count($forwardMatch);

                if ($countForwardMatch > 1) {
                    for ($i = 1; $i < $countForwardMatch; $i++) {
                        if (!preg_match('/sid=/', $forwardMatch[$i])) {
                            if ($forwardPage !== '') {
                                $forwardPage .= '&';
                            }

                            $forwardPage .= $forwardMatch[$i];
                        }
                    }

                    $forwardPage = $forwardMatch[0] . '?' . $forwardPage;
                } else {
                    $forwardPage = $forwardMatch[0];
                }
            }
        }

        $username = $userdata['user_id'] !== ANONYMOUS ? $userdata['username'] : '';

        $s_hidden_fields = '<input type="hidden" name="redirect" value="' . $forwardPage . '" />';
        $s_hidden_fields .= isset($_GET['admin']) ? '<input type="hidden" name="admin" value="1" />' : '';

        make_jumpbox('viewforum.php');
        $template->assignVars(
            [
                'USERNAME' => $username,

                'F_LOGIN_FORM_TOKEN' => CSRF::getInputHtml(),

                'L_ENTER_PASSWORD' => isset($_GET['admin']) ? $lang['Admin_reauthenticate'] : $lang['Enter_password'],
                'L_SEND_PASSWORD' => $lang['Forgotten_password'],

                'U_SEND_PASSWORD' => Session::appendSid('profile.php?mode=sendpassword'),

                'S_HIDDEN_FIELDS' => $s_hidden_fields
            ]
        );

        $template->pparse('body');

        PageHelper::footer($template, $userdata, $lang, $gen_simple_header);
    } else {
        redirect(Session::appendSid('index.php', true));
    }
}

?>