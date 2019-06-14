<?php
/***************************************************************************
 *                            usercp_register.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: usercp_register.php 6775 2006-12-17 10:51:27Z acydburn $
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

/*

	This code has been modified from its original form by psoTFX @ phpbb.com
	Changes introduce the back-ported phpBB 2.2 visual confirmation code. 

	NOTE: Anyone using the modified code contained within this script MUST include
	a relevant message such as this in usercp_register.php ... failure to do so 
	will affect a breach of Section 2a of the GPL and our copyright

	png visual confirmation system : (c) phpBB Group, 2003 : All Rights Reserved

*/

if (!defined('IN_PHPBB')) {
	die('Hacking attempt');
}

$unhtml_specialchars_match   = ['#&gt;#', '#&lt;#', '#&quot;#', '#&amp;#'];
$unhtml_specialchars_replace = ['>', '<', '"', '&'];

// ---------------------------------------
// Load agreement template since user has not yet
// agreed to registration conditions/coppa
//
function show_coppa()
{
	global $template, $lang;

    $template->setFileNames(['body' => 'agreement.tpl']);

    $template->assignVars(
        [
            'REGISTRATION'   => $lang['Registration'],
            'AGREEMENT'      => $lang['Reg_agreement'],
            'AGREE_OVER_13'  => $lang['Agree_over_13'],
            'AGREE_UNDER_13' => $lang['Agree_under_13'],
            'DO_NOT_AGREE'   => $lang['Agree_not'],

            'U_AGREE_OVER13'  => Session::appendSid('profile.php?mode=register&amp;agreed=true'),
            'U_AGREE_UNDER13' => Session::appendSid('profile.php?mode=register&amp;agreed=true&amp;coppa=true')
        ]
    );

    $template->pparse('body');
}
//
// ---------------------------------------

$error = false;
$error_msg = '';
$page_title = $mode === 'editprofile' ? $lang['Edit_profile'] : $lang['Register'];

if ($mode === 'register' && !isset($_POST['agreed']) && !isset($_GET['agreed']) ) {
	include $phpbb_root_path . 'includes/page_header.php';

	show_coppa();

	include $phpbb_root_path . 'includes/page_tail.php';
}

$coppa = empty($_POST['coppa']) && empty($_GET['coppa']) ? 0 : true;

//
// Check and initialize some variables if needed
//
if (
	isset($_POST['submit']) ||
	isset($_POST['avatargallery']) ||
	isset($_POST['submitavatar']) ||
	isset($_POST['cancelavatar']) ||
	$mode === 'register'
) {
	include $phpbb_root_path . 'includes/functions_validate.php';
	include $phpbb_root_path . 'includes/bbcode.php';
	include $phpbb_root_path . 'includes/functions_post.php';

    if ($mode === 'editprofile') {
		$user_id = (int)$_POST['user_id'];
		$current_email = trim(htmlspecialchars($_POST['current_email']));
	}

    // Strip all tags from data ... may p**s some people off, bah, strip_tags is
    // doing the job but can still break HTML output ... have no choice, have
    // to use htmlspecialchars ... be prepared to be moaned at.
    $email        = !empty($_POST['email'])        ? trim(htmlspecialchars($_POST['email']))        : '';
    $icq          = !empty($_POST['icq'])          ? trim(htmlspecialchars($_POST['icq']))          : '';
    $aim          = !empty($_POST['aim'])          ? trim(htmlspecialchars($_POST['aim']))          : '';
    $msn          = !empty($_POST['msn'])          ? trim(htmlspecialchars($_POST['msn']))          : '';
    $yim          = !empty($_POST['yim'])          ? trim(htmlspecialchars($_POST['yim']))          : '';
    $website      = !empty($_POST['website'])      ? trim(htmlspecialchars($_POST['website']))      : '';
    $location     = !empty($_POST['location'])     ? trim(htmlspecialchars($_POST['location']))     : '';
    $interests    = !empty($_POST['interests'])    ? trim(htmlspecialchars($_POST['interests']))    : '';
    $confirm_code = !empty($_POST['confirm_code']) ? trim(htmlspecialchars($_POST['confirm_code'])) : '';

    $username         = !empty($_POST['username'])         ? phpbb_clean_username($_POST['username']) : '';
    $cur_password     = !empty($_POST['cur_password'])     ? trim($_POST['cur_password'])             : '';
    $new_password     = !empty($_POST['new_password'])     ? trim($_POST['new_password'])             : '';
    $password_confirm = !empty($_POST['password_confirm']) ? trim($_POST['password_confirm'])         : '';
    $signature        = !empty($_POST['signature'])        ? trim($_POST['signature'])                : '';

	$signature = isset($signature) ? str_replace('<br />', "\n", $signature) : '';
	$signature_bbcode_uid = '';

	// Run some validation on the optional fields. These are pass-by-ref, so they'll be changed to
	// empty strings if they fail.
	validate_optional_fields($icq, $aim, $msn, $yim, $website, $location, $occupation, $interests, $signature);

    $viewemail       = isset($_POST['viewemail'])   ? (bool)$_POST['hideonline']  : 0;
    $allowviewonline = isset($_POST['hideonline'])  ? (bool)!$_POST['hideonline'] : true;
    $notifyreply     = isset($_POST['notifyreply']) ? (bool)$_POST['notifyreply'] : 0;
    $notifypm        = isset($_POST['notifypm'])    ? (bool)$_POST['notifypm']    : true;
    $popup_pm        = isset($_POST['popup_pm'])    ? (bool)$_POST['popup_pm']    : true;
    $sid             = isset($_POST['sid'])         ? $_POST['sid']               : 0;
    $user_style      = isset($_POST['style'])       ? (int)$_POST['style']        : $board_config['default_style'];

	if ($mode === 'register') {
        $attachsig    = isset($_POST['attachsig'])    ? (bool)$_POST['attachsig']    : $board_config['allow_sig'];
        $allowhtml    = isset($_POST['allowhtml'])    ? (bool)$_POST['allowhtml']    : $board_config['allow_html'];
        $allowbbcode  = isset($_POST['allowbbcode'])  ? (bool)$_POST['allowbbcode']  : $board_config['allow_bbcode'];
        $allowsmilies = isset($_POST['allowsmilies']) ? (bool)$_POST['allowsmilies'] : $board_config['allow_smilies'];
	} else {
		$attachsig    = isset($_POST['attachsig'])    ? (bool)$_POST['attachsig']    : $userdata['user_attachsig'];
		$allowhtml    = isset($_POST['allowhtml'])    ? (bool)$_POST['allowhtml']    : $userdata['user_allowhtml'];
		$allowbbcode  = isset($_POST['allowbbcode'])  ? (bool)$_POST['allowbbcode']  : $userdata['user_allowbbcode'];
		$allowsmilies = isset($_POST['allowsmilies']) ? (bool)$_POST['allowsmilies'] : $userdata['user_allowsmile'];
	}

    if (!empty($_POST['language'])) {
        if (preg_match('/^[a-z_]+$/i', $_POST['language'])) {
            $user_lang = htmlspecialchars($_POST['language']);
        } else {
            $error = true;
            $error_msg = $lang['Fields_empty'];
        }
    } else {
        $user_lang = $board_config['default_lang'];
    }

	$user_timezone = isset($_POST['timezone']) ? $_POST['timezone'] : $board_config['board_timezone'];

	// TODO i think i have this value already in $board_config, why i get it again???
	$board_default_dateformat = dibi::select('config_value')
        ->from(CONFIG_TABLE)
        ->where('config_name = %s', 'default_dateformat')
        ->fetchSingle();

	$board_config['default_dateformat'] = $board_default_dateformat;
	$user_dateformat = !empty($_POST['dateformat']) ? trim(htmlspecialchars($_POST['dateformat'])) : $board_config['default_dateformat'];

    if (isset($_POST['avatarselect']) && !empty($_POST['submitavatar']) && $board_config['allow_avatar_local']) {
        $user_avatar_local = htmlspecialchars($_POST['avatarselect']);
    } else {
        $user_avatar_local = isset($_POST['avatarlocal']) ? htmlspecialchars($_POST['avatarlocal']) : '';
    }

	$user_avatar_category = isset($_POST['avatarcatname']) && $board_config['allow_avatar_local'] ? htmlspecialchars($_POST['avatarcatname']) : '' ;

	$user_avatar_remoteurl = !empty($_POST['avatarremoteurl']) ? trim(htmlspecialchars($_POST['avatarremoteurl'])) : '';

    if (!empty($_POST['avatarurl'])) {
        $user_avatar_upload = trim($_POST['avatarurl']);
    } else {
        $user_avatar_upload = isset($_FILES['avatar']['tmp_name']) ? $_FILES['avatar']['tmp_name'] : '';
    }

	$user_avatar_name     = !empty($_FILES['avatar']['name']) ? $_FILES['avatar']['name'] : '';
	$user_avatar_size     = !empty($_FILES['avatar']['size']) ? $_FILES['avatar']['size'] : 0;
	$user_avatar_filetype = !empty($_FILES['avatar']['type']) ? $_FILES['avatar']['type'] : '';

	$user_avatar      = ( empty($user_avatar_local) && $mode === 'editprofile' ) ? $userdata['user_avatar'] : '';
	$user_avatar_type = ( empty($user_avatar_local) && $mode === 'editprofile' ) ? $userdata['user_avatar_type'] : '';

    if ((isset($_POST['avatargallery']) || isset($_POST['submitavatar']) || isset($_POST['cancelavatar'])) && !isset($_POST['submit'])) {
		$username = stripslashes($username);
		$email = stripslashes($email);
		$cur_password = htmlspecialchars(stripslashes($cur_password));
		$new_password = htmlspecialchars(stripslashes($new_password));
		$password_confirm = htmlspecialchars(stripslashes($password_confirm));

		$icq = stripslashes($icq);
		$aim = stripslashes($aim);
		$msn = stripslashes($msn);
		$yim = stripslashes($yim);

		$website = stripslashes($website);
		$location = stripslashes($location);
		$occupation = stripslashes($occupation);
		$interests = stripslashes($interests);
		$signature = htmlspecialchars(stripslashes($signature));

		$user_lang = stripslashes($user_lang);
		$user_dateformat = stripslashes($user_dateformat);

        if (!isset($_POST['cancelavatar'])) {
			$user_avatar = $user_avatar_category . '/' . $user_avatar_local;
			$user_avatar_type = USER_AVATAR_GALLERY;
		}
	}
}

//
// Let's make sure the user isn't logged in while registering,
// and ensure that they were trying to register a second time
// (Prevents double registrations)
//
if ($mode === 'register' && ($userdata['session_logged_in'] || $username === $userdata['username'])) {
	message_die(GENERAL_MESSAGE, $lang['Username_taken'], '', __LINE__, __FILE__);
}

//
// Did the user submit? In this case build a query to update the users profile in the DB
//
if (isset($_POST['submit'])) {
	include $phpbb_root_path . 'includes/usercp_avatar.php';

	// session id check
	if ($sid === '' || $sid !== $userdata['session_id']) {
		$error = true;
		$error_msg .= ( isset($error_msg) ? '<br />' : '' ) . $lang['Session_invalid'];
	}

    if ($mode === 'editprofile') {
        if ($user_id !== $userdata['user_id']) {
			$error = true;
			$error_msg .= ( isset($error_msg) ? '<br />' : '' ) . $lang['Wrong_Profile'];
		}
    } elseif ($mode === 'register') {
        if (empty($username) || empty($new_password) || empty($password_confirm) || empty($email)) {
			$error = true;
			$error_msg .= ( isset($error_msg) ? '<br />' : '' ) . $lang['Fields_empty'];
		}
	}

	if ($board_config['enable_confirm'] && $mode === 'register') {
		if (empty($_POST['confirm_id'])) {
			$error = true;
			$error_msg .= ( isset($error_msg) ? '<br />' : '' ) . $lang['Confirm_code_wrong'];
		} else {
			$confirm_id = htmlspecialchars($_POST['confirm_id']);

			if (!preg_match('/^[A-Za-z0-9]+$/', $confirm_id)) {
				$confirm_id = '';
			}

			// todo maybe fetchSingle()??
			$row = dibi::select('code')
                ->from(CONFIRM_TABLE)
                ->where('confirm_id = %s', $confirm_id)
                ->where('session_id = %s', $userdata['session_id'])
                ->fetch();

			if ($row) {
                if ($row->code !== $confirm_code) {
                    $error = true;
					$error_msg .= ( isset($error_msg) ? '<br />' : '' ) . $lang['Confirm_code_wrong'];
				} else {
				    dibi::delete(CONFIRM_TABLE)
                        ->where('confirm_id = %s', $confirm_id)
                        ->where('session_id = %s', $userdata['session_id'])
                        ->execute();
				}
			} else {
                $error = true;
				$error_msg .= ( isset($error_msg) ? '<br />' : '' ) . $lang['Confirm_code_wrong'];
			}
		}
	}

	$user_password_data = [];

    if (!empty($new_password) && !empty($password_confirm)) {
        if ($new_password !== $password_confirm) {
            $error = true;
            $error_msg .= (isset($error_msg) ? '<br />' : '') . $lang['Password_mismatch'];
        } elseif (mb_strlen($new_password) > 32) {
            $error = true;
            $error_msg .= (isset($error_msg) ? '<br />' : '') . $lang['Password_long'];
		} else {
            if ($mode === 'editprofile') {
			    $db_password = dibi::select('user_password')
                    ->from(USERS_TABLE)
                    ->where('user_id = %i', $user_id)
                    ->fetchSingle();

                if (!$db_password) {
                    message_die(GENERAL_ERROR, 'Could not obtain user_password information');
                }

                if (!password_verify($cur_password, $db_password)) {
                    $error = true;
                    $error_msg .= (isset($error_msg) ? '<br />' : '') . $lang['Current_password_mismatch'];
				}
			}

            if (!$error) {
				$new_password = password_hash($new_password, PASSWORD_BCRYPT);
                $user_password_data = ['user_password' => $new_password];
			}
		}
    } elseif ((empty($new_password) && !empty($password_confirm)) || (!empty($new_password) && empty($password_confirm))) {
        $error = true;
        $error_msg .= (isset($error_msg) ? '<br />' : '') . $lang['Password_mismatch'];
	}

	//
	// Do a ban check on this email address
	//
    if ($email !== $userdata['user_email'] || $mode === 'register') {
        $result = validate_email($email);

        if ($result['error']) {
            $email = $userdata['user_email'];

            $error = true;
            $error_msg .= (isset($error_msg) ? '<br />' : '') . $result['error_msg'];
		}

        if ($mode === 'editprofile') {
            $db_password = dibi::select('user_password')
                ->from(USERS_TABLE)
                ->where('user_id = %i', $user_id)
                ->fetchSingle();

            if (!$db_password) {
                message_die(GENERAL_ERROR, 'Could not obtain user_password information');
            }

			if (!password_verify($cur_password, $db_password)) {
				$email = $userdata['user_email'];

                $error = true;
                $error_msg .= (isset($error_msg) ? '<br />' : '') . $lang['Current_password_mismatch'];
			}
		}
	}

	$username_data = [];
	if ($board_config['allow_namechange'] || $mode === 'register') {
        if (empty($username)) {
			// Error is already triggered, since one field is empty.
            $error = true;
        } elseif ($username !== $userdata['username'] || $mode === 'register') {
            if (strtolower($username) !== strtolower($userdata['username']) || $mode === 'register') {
				$result = validate_username($username);

                if ($result['error']) {
                    $error = true;
					$error_msg .= ( isset($error_msg) ? '<br />' : '' ) . $result['error_msg'];
				}
			}

			if (!$error) {
				$username_data =  ['username' => $username];
			}
		}
	}

    if ($signature !== '') {
        if (mb_strlen($signature) > $board_config['max_sig_chars']) {
            $error = true;
			$error_msg .= ( isset($error_msg) ? '<br />' : '' ) . $lang['Signature_too_long'];
		}

        if (!isset($signature_bbcode_uid) || $signature_bbcode_uid === '') {
			$signature_bbcode_uid = $allowbbcode ? make_bbcode_uid() : '';
		}
		$signature = prepare_message($signature, $allowhtml, $allowbbcode, $allowsmilies, $signature_bbcode_uid);
	}

    if ($website !== '') {
		rawurlencode($website);
	}

	$avatar_data = [];

	if (isset($_POST['avatardel']) && $mode === 'editprofile') {
		$avatar_data = user_avatar_delete($userdata['user_avatar_type'], $userdata['user_avatar']);
	} elseif (( !empty($user_avatar_upload) || !empty($user_avatar_name) ) && $board_config['allow_avatar_upload']) {
		if (!empty($user_avatar_upload)) {
			$avatar_mode = empty($user_avatar_name) ? 'remote' : 'local';
			$avatar_data = user_avatar_upload($mode, $avatar_mode, $userdata['user_avatar'], $userdata['user_avatar_type'], $error, $error_msg, $user_avatar_upload, $user_avatar_name, $user_avatar_size, $user_avatar_filetype);
		} elseif (!empty($user_avatar_name)) {
			$l_avatar_size = sprintf($lang['Avatar_filesize'], round($board_config['avatar_filesize'] / 1024));

			$error = true;
			$error_msg .= ( !empty($error_msg) ? '<br />' : '' ) . $l_avatar_size;
		}
	} elseif ($user_avatar_remoteurl !== '' && $board_config['allow_avatar_remote']) {
		user_avatar_delete($userdata['user_avatar_type'], $userdata['user_avatar']);
		$avatar_data = user_avatar_url($mode, $error, $error_msg, $user_avatar_remoteurl);
	} elseif ($user_avatar_local !== '' && $board_config['allow_avatar_local']) {
		user_avatar_delete($userdata['user_avatar_type'], $userdata['user_avatar']);
		$avatar_data = user_avatar_gallery($mode, $error, $error_msg, $user_avatar_local, $user_avatar_category);
	}

	if (!$error) {
	    /*
	     * TODO
		if ($avatar_sql == '') {
			$avatar_sql = ( $mode == 'editprofile' ) ? '' : "'', " . USER_AVATAR_NONE;
		}
	    */

        if ($mode === 'editprofile') {
            if ($email !== $userdata['user_email'] && $board_config['require_activation'] !== USER_ACTIVATION_NONE && $userdata['user_level'] !== ADMIN) {
				$user_active = 0;

				$user_actkey = gen_rand_string(true);
				$key_len = 54 - mb_strlen($server_url);
				$key_len = $key_len > 6 ? $key_len : 6;
				$user_actkey = substr($user_actkey, 0, $key_len);

                if ($userdata['session_logged_in']) {
                    Session::end($userdata['session_id'], $userdata['user_id']);
				}
			} else {
				$user_active = 1;
				$user_actkey = '';
			}

            $update_data = [
                'user_email'            => $email,
                'user_icq'              => $icq,
                'user_website'          => $website,
                'user_occ'              => $occupation,
                'user_from'             => $location,
                'user_interests'        => $interests,
                'user_sig'              => $signature,
                'user_sig_bbcode_uid'   => $signature_bbcode_uid,
                'user_viewemail'        => $viewemail,
                'user_aim'              => str_replace(' ', '+', $aim),
                'user_yim'              => $yim,
                'user_msnm'             => $msn,
                'user_attachsig'        => $attachsig,
                'user_allowsmile'       => $allowsmilies,
                'user_allowhtml'        => $allowhtml,
                'user_allowbbcode'      => $allowbbcode,
                'user_allow_viewonline' => $allowviewonline,
                'user_notify'           => $notifyreply,
                'user_notify_pm'        => $notifypm,
                'user_popup_pm'         => $popup_pm,
                'user_timezone'         => $user_timezone,
                'user_dateformat'       => $user_dateformat,
                'user_lang'             => $user_lang,
                'user_style'            => $user_style,
                'user_active'           => $user_active,
                'user_actkey'           => $user_actkey
            ];

			$update_data = array_merge($update_data, $avatar_data, $username_data, $user_password_data);

			dibi::update(USERS_TABLE, $update_data)
                ->where('user_id = %i', $user_id)
                ->execute();

			// We remove all stored login keys since the password has been updated
			// and change the current one (if applicable)
            if (count($user_password_data)) {
                Session::resetKeys($user_id, $user_ip);
            }

            if (!$user_active) {
				//
				// The users account has been deactivated, send them an email with a new activation key
				//
				$emailer = new Emailer($board_config['smtp_delivery']);

                if ($board_config['require_activation'] !== USER_ACTIVATION_ADMIN) {
 					$emailer->setFrom($board_config['board_email']);
 					$emailer->setReplyTo($board_config['board_email']);

 					$emailer->use_template('user_activate', stripslashes($user_lang));
 					$emailer->setEmailAddress($email);
 					$emailer->setSubject($lang['Reactivate']);

                    $emailer->assignVars(
                        [
                            'SITENAME'  => $board_config['sitename'],
                            'USERNAME'  => preg_replace($unhtml_specialchars_match, $unhtml_specialchars_replace, substr(str_replace("\'", "'", $username), 0, 25)),
                            'EMAIL_SIG' => !empty($board_config['board_email_sig']) ? str_replace('<br />', "\n", "-- \n" . $board_config['board_email_sig']) : '',

                            'U_ACTIVATE' => $server_url . '?mode=activate&' . POST_USERS_URL . '=' . $user_id . '&act_key=' . $user_actkey
                        ]
                    );
                    $emailer->send();
 					$emailer->reset();
                } elseif ($board_config['require_activation'] === USER_ACTIVATION_ADMIN) {
 				    $admins = dibi::select(['user_email', 'user_lang'])
                        ->from(USERS_TABLE)
                        ->where('user_level = %i', ADMIN)
                        ->fetchAll();

                    foreach ($admins as $admin) {
                        $emailer->setFrom($board_config['board_email']);
                        $emailer->setReplyTo($board_config['board_email']);

                        $emailer->setEmailAddress(trim($admin->user_email));
                        $emailer->use_template('admin_activate', $admin->user_lang);
                        $emailer->setSubject($lang['Reactivate']);

                        $emailer->assignVars(
                            [
                                'USERNAME'  => preg_replace($unhtml_specialchars_match, $unhtml_specialchars_replace, substr(str_replace("\'", "'", $username), 0, 25)),
                                'EMAIL_SIG' => str_replace('<br />', "\n", "-- \n" . $board_config['board_email_sig']),

                                'U_ACTIVATE' => $server_url . '?mode=activate&' . POST_USERS_URL . '=' . $user_id . '&act_key=' . $user_actkey
                            ]
                        );
                        $emailer->send();
                        $emailer->reset();
                    }
 				}

				$message = $lang['Profile_updated_inactive'] . '<br /><br />' . sprintf($lang['Click_return_index'],  '<a href="' . Session::appendSid('index.php') . '">', '</a>');
			} else {
				$message = $lang['Profile_updated'] . '<br /><br />' . sprintf($lang['Click_return_index'],  '<a href="' . Session::appendSid('index.php') . '">', '</a>');
			}

            $template->assignVars(
                [
                    'META' => '<meta http-equiv="refresh" content="5;url=' . Session::appendSid('index.php') . '">'
                ]
            );

            message_die(GENERAL_MESSAGE, $message);
		} else {
			//
			// Get current date
			//

            $insert_data = [
                'username' => $username,
                'user_regdate' => time(),
                'user_password' => $new_password,
                'user_email' => $email,
                'user_icq' => $icq,
                'user_website' => $website,
                'user_occ' =>  $occupation,
                'user_from' => $location,
                'user_interests' => $interests,
                'user_sig' => $signature,
                'user_sig_bbcode_uid' => $signature_bbcode_uid,
                //'user_avatar' => null, // TODO $avatar_sql,
                //'user_avatar_type' => null, // TODO $avatar_sql,
                'user_viewemail' => $viewemail,
                'user_aim' => str_replace(' ', '+', $aim), // TODO what is aim?
                'user_yim' => $yim, // TODO what is yim?
                'user_msnm' => $msn,
                'user_attachsig' => $attachsig,
                'user_allowsmile' => $allowsmilies,
                'user_allowhtml' => $allowhtml,
                'user_allowbbcode' => $allowbbcode,
                'user_allow_viewonline' => $allowviewonline,
                'user_notify' => $notifyreply,
                'user_popup_pm' => $popup_pm,
                'user_timezone' => $user_timezone,
                'user_dateformat' => $user_dateformat,
                'user_lang' => $user_lang,
                'user_style' => $user_style,
                'user_level' => USER,
                'user_allow_pm' => 1,
                'user_active' => null,
                'user_actkey' => null
            ];

            $insert_data = array_merge($insert_data, $avatar_data);

            if ($board_config['require_activation'] === USER_ACTIVATION_SELF || $board_config['require_activation'] === USER_ACTIVATION_ADMIN || $coppa) {
				$user_actkey = gen_rand_string(true);
				$key_len = 54 - mb_strlen($server_url);
				$key_len = $key_len > 6 ? $key_len : 6;
				$user_actkey = substr($user_actkey, 0, $key_len);

                $insert_data['user_active'] = 0;
                $insert_data['user_actkey'] = $user_actkey;
			} else {
			    $insert_data['user_active'] = 1;
			    $insert_data['user_actkey'] = '';
			}

			$user_id = dibi::insert(USERS_TABLE, $insert_data)->execute();

            $group_insert_data = [
                'group_name'        => '',
                'group_description' => 'Personal User',
                'group_single_user' => 1,
                'group_moderator'   => 0
            ];

            $group_id = dibi::insert(GROUPS_TABLE, $group_insert_data)->execute(dibi::IDENTIFIER);

			$user_group_data_insert = [
			   'user_id' => $user_id,
               'group_id' => $group_id,
               'user_pending' => 0
            ];

			dibi::insert(USER_GROUP_TABLE, $user_group_data_insert)->execute();

            if ($coppa) {
				$message = $lang['COPPA'];
				$email_template = 'coppa_welcome_inactive';
            } elseif ($board_config['require_activation'] === USER_ACTIVATION_SELF) {
				$message = $lang['Account_inactive'];
				$email_template = 'user_welcome_inactive';
            } elseif ($board_config['require_activation'] === USER_ACTIVATION_ADMIN) {
				$message = $lang['Account_inactive_admin'];
				$email_template = 'admin_welcome_inactive';
			} else {
				$message = $lang['Account_added'];
				$email_template = 'user_welcome';
			}

			$emailer = new Emailer($board_config['smtp_delivery']);

			$emailer->setFrom($board_config['board_email']);
			$emailer->setReplyTo($board_config['board_email']);

			$emailer->use_template($email_template, stripslashes($user_lang));
			$emailer->setEmailAddress($email);
			$emailer->setSubject(sprintf($lang['Welcome_subject'], $board_config['sitename']));

            if ($coppa) {
                $emailer->assignVars(
                    [
                        'SITENAME'    => $board_config['sitename'],
                        'WELCOME_MSG' => sprintf($lang['Welcome_subject'], $board_config['sitename']),
                        'USERNAME'    => preg_replace($unhtml_specialchars_match, $unhtml_specialchars_replace, substr(str_replace("\'", "'", $username), 0, 25)),
                        'PASSWORD'    => $password_confirm,
                        'EMAIL_SIG'   => str_replace('<br />', "\n", "-- \n" . $board_config['board_email_sig']),

                        'FAX_INFO'      => $board_config['coppa_fax'],
                        'MAIL_INFO'     => $board_config['coppa_mail'],
                        'EMAIL_ADDRESS' => $email,
                        'ICQ'           => $icq,
                        'AIM'           => $aim,
                        'YIM'           => $yim,
                        'MSN'           => $msn,
                        'WEB_SITE'      => $website,
                        'FROM'          => $location,
                        'OCC'           => $occupation,
                        'INTERESTS'     => $interests
                    ]
                );
            } else {
                $emailer->assignVars(
                    [
                        'SITENAME'    => $board_config['sitename'],
                        'WELCOME_MSG' => sprintf($lang['Welcome_subject'], $board_config['sitename']),
                        'USERNAME'    => preg_replace($unhtml_specialchars_match, $unhtml_specialchars_replace, substr(str_replace("\'", "'", $username), 0, 25)),
                        'PASSWORD'    => $password_confirm,
                        'EMAIL_SIG'   => str_replace('<br />', "\n", "-- \n" . $board_config['board_email_sig']),

                        'U_ACTIVATE' => $server_url . '?mode=activate&' . POST_USERS_URL . '=' . $user_id . '&act_key=' . $user_actkey
                    ]
                );
            }

			$emailer->send();
			$emailer->reset();

            if ($board_config['require_activation'] === USER_ACTIVATION_ADMIN) {
			    $admins = dibi::select(['user_email', 'user_lang'])
                    ->from(USERS_TABLE)
                    ->where('user_level = %i', ADMIN)
                    ->fetchAll();

                foreach ($admins as $admin) {
					$emailer->setFrom($board_config['board_email']);
					$emailer->setReplyTo($board_config['board_email']);

					$emailer->setEmailAddress(trim($admin->user_email));
					$emailer->use_template('admin_activate', $admin->user_lang);
					$emailer->setSubject($lang['New_account_subject']);

                    $emailer->assignVars(
                        [
                            'USERNAME'  => preg_replace($unhtml_specialchars_match, $unhtml_specialchars_replace, substr(str_replace("\'", "'", $username), 0, 25)),
                            'EMAIL_SIG' => str_replace('<br />', "\n", "-- \n" . $board_config['board_email_sig']),

                            'U_ACTIVATE' => $server_url . '?mode=activate&' . POST_USERS_URL . '=' . $user_id . '&act_key=' . $user_actkey
                        ]
                    );
                    $emailer->send();
					$emailer->reset();
				}
			}

			$message = $message . '<br /><br />' . sprintf($lang['Click_return_index'],  '<a href="' . Session::appendSid('index.php') . '">', '</a>');

			message_die(GENERAL_MESSAGE, $message);
		} // if mode == register
	}
} // End of submit

if ($error) {
	//
	// If an error occured we need to stripslashes on returned data
	//
	$username = stripslashes($username);
	$email = stripslashes($email);
	$cur_password = '';
	$new_password = '';
	$password_confirm = '';

	$icq = stripslashes($icq);
	$aim = str_replace('+', ' ', stripslashes($aim));
	$msn = stripslashes($msn);
	$yim = stripslashes($yim);

	$website = stripslashes($website);
	$location = stripslashes($location);
	$occupation = stripslashes($occupation);
	$interests = stripslashes($interests);
	$signature = stripslashes($signature);
	$signature = $signature_bbcode_uid !== '' ? preg_replace("/:(([a-z0-9]+:)?)$signature_bbcode_uid(=|\])/si", '\\3', $signature) : $signature;

	$user_lang = stripslashes($user_lang);
	$user_dateformat = stripslashes($user_dateformat);
} elseif ($mode === 'editprofile' && !isset($_POST['avatargallery']) && !isset($_POST['submitavatar']) && !isset($_POST['cancelavatar'])) {
	$user_id = $userdata['user_id'];
	$username = $userdata['username'];
	$email = $userdata['user_email'];
	$cur_password = '';
	$new_password = '';
	$password_confirm = '';

	$icq = $userdata['user_icq'];
	$aim = str_replace('+', ' ', $userdata['user_aim']);
	$msn = $userdata['user_msnm'];
	$yim = $userdata['user_yim'];

	$website = $userdata['user_website'];
	$location = $userdata['user_from'];
	$occupation = $userdata['user_occ'];
	$interests = $userdata['user_interests'];
	$signature_bbcode_uid = $userdata['user_sig_bbcode_uid'];
	$signature = ($signature_bbcode_uid !== '') ? preg_replace("/:(([a-z0-9]+:)?)$signature_bbcode_uid(=|\])/si", '\\3', $userdata['user_sig']) : $userdata['user_sig'];

	$viewemail = $userdata['user_viewemail'];
	$notifypm = $userdata['user_notify_pm'];
	$popup_pm = $userdata['user_popup_pm'];
	$notifyreply = $userdata['user_notify'];
	$attachsig = $userdata['user_attachsig'];
	$allowhtml = $userdata['user_allowhtml'];
	$allowbbcode = $userdata['user_allowbbcode'];
	$allowsmilies = $userdata['user_allowsmile'];
	$allowviewonline = $userdata['user_allow_viewonline'];

	$user_avatar = $userdata['user_allowavatar'] ? $userdata['user_avatar'] : '';
	$user_avatar_type = $userdata['user_allowavatar'] ? $userdata['user_avatar_type'] : USER_AVATAR_NONE;

	$user_style = $userdata['user_style'];
	$user_lang = $userdata['user_lang'];
	$user_timezone = $userdata['user_timezone'];
	$user_dateformat = $userdata['user_dateformat'];
}

//
// Default pages
//
include $phpbb_root_path . 'includes/page_header.php';

make_jumpbox('viewforum.php');

if ($mode === 'editprofile') {
	if ($user_id !== $userdata['user_id']) {
		$error = true;
		$error_msg = $lang['Wrong_Profile'];
	}
}

if (isset($_POST['avatargallery']) && !$error) {
	include $phpbb_root_path . 'includes/usercp_avatar.php';

	$avatar_category = !empty($_POST['avatarcategory']) ? htmlspecialchars($_POST['avatarcategory']) : '';

    $template->setFileNames(['body' => 'profile_avatar_gallery.tpl']);

    $allowviewonline = !$allowviewonline;

	display_avatar_gallery($mode, $avatar_category, $user_id, $email, $current_email, $coppa, $username, $email, $new_password, $cur_password, $password_confirm, $icq, $aim, $msn, $yim, $website, $location, $occupation, $interests, $signature, $viewemail, $notifypm, $popup_pm, $notifyreply, $attachsig, $allowhtml, $allowbbcode, $allowsmilies, $allowviewonline, $user_style, $user_lang, $user_timezone, $user_dateformat, $userdata['session_id']);
} else {
	include $phpbb_root_path . 'includes/functions_selects.php';

    if (!isset($coppa)) {
        $coppa = false;
    }

    if (!isset($user_style)) {
        $user_style = $board_config['default_style'];
    }

	$avatar_img = '';

    if ($user_avatar_type) {
        switch ($user_avatar_type) {
			case USER_AVATAR_UPLOAD:
				$avatar_img = $board_config['allow_avatar_upload'] ? '<img src="' . $board_config['avatar_path'] . '/' . $user_avatar . '" alt="" />' : '';
				break;
			case USER_AVATAR_REMOTE:
				$avatar_img = $board_config['allow_avatar_remote'] ? '<img src="' . $user_avatar . '" alt="" />' : '';
				break;
			case USER_AVATAR_GALLERY:
				$avatar_img = $board_config['allow_avatar_local'] ? '<img src="' . $board_config['avatar_gallery_path'] . '/' . $user_avatar . '" alt="" />' : '';
				break;
		}
	}

	$s_hidden_fields = '<input type="hidden" name="mode" value="' . $mode . '" /><input type="hidden" name="agreed" value="true" /><input type="hidden" name="coppa" value="' . $coppa . '" />';
	$s_hidden_fields .= '<input type="hidden" name="sid" value="' . $userdata['session_id'] . '" />';
	if ($mode === 'editprofile') {
		$s_hidden_fields .= '<input type="hidden" name="user_id" value="' . $userdata['user_id'] . '" />';
		//
		// Send the users current email address. If they change it, and account activation is turned on
		// the user account will be disabled and the user will have to reactivate their account.
		//
		$s_hidden_fields .= '<input type="hidden" name="current_email" value="' . $userdata['user_email'] . '" />';
	}

    if (!empty($user_avatar_local)) {
		$s_hidden_fields .= '<input type="hidden" name="avatarlocal" value="' . $user_avatar_local . '" /><input type="hidden" name="avatarcatname" value="' . $user_avatar_category . '" />';
	}

	$html_status    = $userdata['user_allowhtml'] && $board_config['allow_html']     ? $lang['HTML_is_ON']     : $lang['HTML_is_OFF'];
	$bbcode_status  = $userdata['user_allowbbcode'] && $board_config['allow_bbcode'] ? $lang['BBCode_is_ON']   : $lang['BBCode_is_OFF'];
	$smilies_status = $userdata['user_allowsmile'] && $board_config['allow_smilies'] ? $lang['Smilies_are_ON'] : $lang['Smilies_are_OFF'];

    if ($error) {
        $template->setFileNames(['reg_header' => 'error_body.tpl']);
        $template->assignVars(['ERROR_MESSAGE' => $error_msg]);
        $template->assignVarFromHandle('ERROR_BOX', 'reg_header');
    }

    $template->setFileNames(['body' => 'profile_add_body.tpl']);

    if ($mode === 'editprofile') {
        $template->assignBlockVars('switch_edit_profile', []);
    }

    if ($mode === 'register' || $board_config['allow_namechange']) {
        $template->assignBlockVars('switch_namechange_allowed', []);
    } else {
        $template->assignBlockVars('switch_namechange_disallowed', []);
    }

	// Visual Confirmation
	$confirm_image = '';
	if (!empty($board_config['enable_confirm']) && $mode === 'register') {
	    $sessions = dibi::select('session_id')
            ->from(SESSIONS_TABLE)
            ->fetchPairs(null, 'session_id');

	    if (count($sessions)) {
	        dibi::delete(CONFIRM_TABLE)
                ->where('session_id NOT IN %in', $sessions)
                ->execute();
        }

        $attempts = dibi::select('COUNT(session_id)')
            ->as('attempts')
            ->from(CONFIRM_TABLE)
            ->where('session_id = %s', $userdata['session_id'])
            ->fetchSingle();

        // TODO use constant
        if ($attempts > 3) {
            message_die(GENERAL_MESSAGE, $lang['Too_many_registers']);
        }

		// Generate the required confirmation code
		// NB 0 (zero) could get confused with O (the letter) so we make change it
		$code = dss_rand();
		$code = substr(str_replace('0', 'Z', strtoupper(base_convert($code, 16, 35))), 2, 6);

		$confirm_id = md5(uniqid($user_ip));

		$confirm_insert_data = [
		    'confirm_id' => $confirm_id,
            'session_id' => $userdata['session_id'],
            'code'       => $code
        ];

		dibi::insert(CONFIRM_TABLE, $confirm_insert_data)->execute();

		unset($code);

		$confirm_image = '<img src="' . Session::appendSid("profile.php?mode=confirm&amp;id=$confirm_id") . '" alt="" title="" />';
		$s_hidden_fields .= '<input type="hidden" name="confirm_id" value="' . $confirm_id . '" />';

		$template->assignBlockVars('switch_confirm', []);
	}


	//
	// Let's do an overall check for settings/versions which would prevent
	// us from doing file uploads....
	//
	$form_enctype = ( @ini_get('file_uploads') === '0' || strtolower(@ini_get('file_uploads')) === 'off' || PHP_VERSION === '4.0.4pl1' || !$board_config['allow_avatar_upload'] || ( PHP_VERSION < '4.0.3' && @ini_get('open_basedir') !== '' ) ) ? '' : 'enctype="multipart/form-data"';

	$template->assignVars(array(
            'USERNAME' => isset($username) ? $username : '',
            'CUR_PASSWORD' => isset($cur_password) ? $cur_password : '',
            'NEW_PASSWORD' => isset($new_password) ? $new_password : '',
            'PASSWORD_CONFIRM' => isset($password_confirm) ? $password_confirm : '',
            'EMAIL' => isset($email) ? $email : '',
            'CONFIRM_IMG' => $confirm_image,
            'YIM' => $yim,
            'ICQ' => $icq,
            'MSN' => $msn,
            'AIM' => $aim,
            'OCCUPATION' => $occupation,
            'INTERESTS' => $interests,
            'LOCATION' => $location,
            'WEBSITE' => $website,
            'SIGNATURE' => str_replace('<br />', "\n", $signature),

            'VIEW_EMAIL_YES' => $viewemail ? 'checked="checked"' : '',
            'VIEW_EMAIL_NO' => !$viewemail ? 'checked="checked"' : '',

            'HIDE_USER_YES' => !$allowviewonline ? 'checked="checked"' : '',
            'HIDE_USER_NO' => $allowviewonline ? 'checked="checked"' : '',

            'NOTIFY_PM_YES' => $notifypm ? 'checked="checked"' : '',
            'NOTIFY_PM_NO' => !$notifypm ? 'checked="checked"' : '',

            'POPUP_PM_YES' => $popup_pm ? 'checked="checked"' : '',
            'POPUP_PM_NO' => !$popup_pm ? 'checked="checked"' : '',

            'ALWAYS_ADD_SIGNATURE_YES' => $attachsig ? 'checked="checked"' : '',
            'ALWAYS_ADD_SIGNATURE_NO' => !$attachsig ? 'checked="checked"' : '',

            'NOTIFY_REPLY_YES' => $notifyreply ? 'checked="checked"' : '',
            'NOTIFY_REPLY_NO' => !$notifyreply ? 'checked="checked"' : '',

            'ALWAYS_ALLOW_BBCODE_YES' => $allowbbcode ? 'checked="checked"' : '',
            'ALWAYS_ALLOW_BBCODE_NO' => !$allowbbcode ? 'checked="checked"' : '',

            'ALWAYS_ALLOW_HTML_YES' => $allowhtml ? 'checked="checked"' : '',
            'ALWAYS_ALLOW_HTML_NO' => !$allowhtml ? 'checked="checked"' : '',

            'ALWAYS_ALLOW_SMILIES_YES' => $allowsmilies ? 'checked="checked"' : '',
            'ALWAYS_ALLOW_SMILIES_NO' => !$allowsmilies ? 'checked="checked"' : '',

            'ALLOW_AVATAR' => $board_config['allow_avatar_upload'],
            'AVATAR' => $avatar_img,
            'AVATAR_SIZE' => $board_config['avatar_filesize'],
            'LANGUAGE_SELECT' => language_select($user_lang),
            'STYLE_SELECT' => Select::style_select($user_style),
            'TIMEZONE_SELECT' => Select::timezone($user_timezone),
            'DATE_FORMAT' => $user_dateformat,
            'HTML_STATUS' => $html_status,
            'BBCODE_STATUS' => sprintf($bbcode_status, '<a href="' . Session::appendSid('faq.php?mode=bbcode') . '" target="_phpbbcode">', '</a>'),
            'SMILIES_STATUS' => $smilies_status,

            'L_CURRENT_PASSWORD' => $lang['Current_password'],
            'L_NEW_PASSWORD' => $mode === 'register' ? $lang['Password'] : $lang['New_password'],
            'L_CONFIRM_PASSWORD' => $lang['Confirm_password'],
            'L_CONFIRM_PASSWORD_EXPLAIN' => $mode === 'editprofile' ? $lang['Confirm_password_explain'] : '',
            'L_PASSWORD_IF_CHANGED' => $mode === 'editprofile' ? $lang['password_if_changed'] : '',
            'L_PASSWORD_CONFIRM_IF_CHANGED' => $mode === 'editprofile' ? $lang['password_confirm_if_changed'] : '',
            'L_SUBMIT' => $lang['Submit'],
            'L_RESET' => $lang['Reset'],
            'L_ICQ_NUMBER' => $lang['ICQ'],
            'L_MESSENGER' => $lang['MSNM'],
            'L_YAHOO' => $lang['YIM'],
            'L_WEBSITE' => $lang['Website'],
            'L_AIM' => $lang['AIM'],
            'L_LOCATION' => $lang['Location'],
            'L_OCCUPATION' => $lang['Occupation'],
            'L_BOARD_LANGUAGE' => $lang['Board_lang'],
            'L_BOARD_STYLE' => $lang['Board_style'],
            'L_TIMEZONE' => $lang['Timezone'],
            'L_DATE_FORMAT' => $lang['Date_format'],
            'L_DATE_FORMAT_EXPLAIN' => $lang['Date_format_explain'],
            'L_YES' => $lang['Yes'],
            'L_NO' => $lang['No'],
            'L_INTERESTS' => $lang['Interests'],
            'L_ALWAYS_ALLOW_SMILIES' => $lang['Always_smile'],
            'L_ALWAYS_ALLOW_BBCODE' => $lang['Always_bbcode'],
            'L_ALWAYS_ALLOW_HTML' => $lang['Always_html'],
            'L_HIDE_USER' => $lang['Hide_user'],
            'L_ALWAYS_ADD_SIGNATURE' => $lang['Always_add_sig'],

            'L_AVATAR_PANEL' => $lang['Avatar_panel'],
            'L_AVATAR_EXPLAIN' => sprintf($lang['Avatar_explain'], $board_config['avatar_max_width'], $board_config['avatar_max_height'], round($board_config['avatar_filesize'] / 1024)),
            'L_UPLOAD_AVATAR_FILE' => $lang['Upload_Avatar_file'],
            'L_UPLOAD_AVATAR_URL' => $lang['Upload_Avatar_URL'],
            'L_UPLOAD_AVATAR_URL_EXPLAIN' => $lang['Upload_Avatar_URL_explain'],
            'L_AVATAR_GALLERY' => $lang['Select_from_gallery'],
            'L_SHOW_GALLERY' => $lang['View_avatar_gallery'],
            'L_LINK_REMOTE_AVATAR' => $lang['Link_remote_Avatar'],
            'L_LINK_REMOTE_AVATAR_EXPLAIN' => $lang['Link_remote_Avatar_explain'],
            'L_DELETE_AVATAR' => $lang['Delete_Image'],
            'L_CURRENT_IMAGE' => $lang['Current_Image'],

            'L_SIGNATURE' => $lang['Signature'],
            'L_SIGNATURE_EXPLAIN' => sprintf($lang['Signature_explain'], $board_config['max_sig_chars']),
            'L_NOTIFY_ON_REPLY' => $lang['Always_notify'],
            'L_NOTIFY_ON_REPLY_EXPLAIN' => $lang['Always_notify_explain'],
            'L_NOTIFY_ON_PRIVMSG' => $lang['Notify_on_privmsg'],
            'L_POPUP_ON_PRIVMSG' => $lang['Popup_on_privmsg'],
            'L_POPUP_ON_PRIVMSG_EXPLAIN' => $lang['Popup_on_privmsg_explain'],
            'L_PREFERENCES' => $lang['Preferences'],
            'L_PUBLIC_VIEW_EMAIL' => $lang['Public_view_email'],
            'L_ITEMS_REQUIRED' => $lang['Items_required'],
            'L_REGISTRATION_INFO' => $lang['Registration_info'],
            'L_PROFILE_INFO' => $lang['Profile_info'],
            'L_PROFILE_INFO_NOTICE' => $lang['Profile_info_warn'],
            'L_EMAIL_ADDRESS' => $lang['Email_address'],

            'L_CONFIRM_CODE_IMPAIRED'	=> sprintf($lang['Confirm_code_impaired'], '<a href="mailto:' . $board_config['board_email'] . '">', '</a>'),
            'L_CONFIRM_CODE'			=> $lang['Confirm_code'],
            'L_CONFIRM_CODE_EXPLAIN'	=> $lang['Confirm_code_explain'],

            'S_ALLOW_AVATAR_UPLOAD' => $board_config['allow_avatar_upload'],
            'S_ALLOW_AVATAR_LOCAL' => $board_config['allow_avatar_local'],
            'S_ALLOW_AVATAR_REMOTE' => $board_config['allow_avatar_remote'],
            'S_HIDDEN_FIELDS' => $s_hidden_fields,
            'S_FORM_ENCTYPE' => $form_enctype,
            'S_PROFILE_ACTION' => Session::appendSid('profile.php'))
	);

	//
	// This is another cheat using the block_var capability
	// of the templates to 'fake' an IF...ELSE...ENDIF solution
	// it works well :)
	//
    if ($mode !== 'register') {
        if ($userdata['user_allowavatar'] && ($board_config['allow_avatar_upload'] || $board_config['allow_avatar_local'] || $board_config['allow_avatar_remote'])) {
			$template->assignBlockVars('switch_avatar_block', [] );

            if ($board_config['allow_avatar_upload'] && file_exists(@phpbb_realpath('./' . $board_config['avatar_path']))) {
                if ($form_enctype !== '') {
                    $template->assignBlockVars('switch_avatar_block.switch_avatar_local_upload', []);
                }

                $template->assignBlockVars('switch_avatar_block.switch_avatar_remote_upload', []);
            }

            if ($board_config['allow_avatar_remote']) {
                $template->assignBlockVars('switch_avatar_block.switch_avatar_remote_link', []);
            }

            if ($board_config['allow_avatar_local'] && file_exists(@phpbb_realpath('./' . $board_config['avatar_gallery_path']))) {
                $template->assignBlockVars('switch_avatar_block.switch_avatar_local_gallery', []);
            }
		}
	}
}

$template->pparse('body');

include $phpbb_root_path . 'includes/page_tail.php';

?>