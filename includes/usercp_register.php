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

$sep = DIRECTORY_SEPARATOR;

// ---------------------------------------
// Load agreement template since user has not yet
// agreed to registration conditions/coppa
//
function show_coppa()
{
    /**
     * @var BaseTemplate $template
     */
    global $template;
	global $lang;

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
$errorMessage = '';
$page_title = $mode === 'editprofile' ? $lang['Edit_profile'] : $lang['Register'];

if ($mode === 'register' && !isset($_POST['agreed']) && !isset($_GET['agreed'])) {
    PageHelper::header($template, $userdata, $board_config, $lang, $images,  $theme, $page_title, $gen_simple_header);

	show_coppa();

    PageHelper::footer($template, $userdata, $lang, $gen_simple_header);
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
    require_once $phpbb_root_path . 'includes' . $sep . 'bbcode.php';

    if ($mode === 'editprofile') {
        //CSRF::validatePost();

		$userId        = (int)$_POST['user_id'];
		$current_email = trim(htmlspecialchars($_POST['current_email']));
	}

    // Strip all tags from data ... may p**s some people off, bah, strip_tags is
    // doing the job but can still break HTML output ... have no choice, have
    // to use htmlspecialchars ... be prepared to be moaned at.
    $email       = !empty($_POST['email'])        ? trim($_POST['email'])        : '';
    $website     = !empty($_POST['website'])      ? trim($_POST['website'])      : '';
    $location    = !empty($_POST['location'])     ? trim($_POST['location'])     : '';
    $occupation  = !empty($_POST['occupation'])   ? trim($_POST['occupation'])   : '';
    $interests   = !empty($_POST['interests'])    ? trim($_POST['interests'])    : '';
    $confirmCode = !empty($_POST['confirm_code']) ? trim($_POST['confirm_code']) : '';

    $userName        = !empty($_POST['username'])         ? phpbb_clean_username($_POST['username']) : '';
    $currentPassword = !empty($_POST['cur_password'])     ? trim($_POST['cur_password'])             : '';
    $newPassword     = !empty($_POST['new_password'])     ? trim($_POST['new_password'])             : '';
    $confirmPassword = !empty($_POST['password_confirm']) ? trim($_POST['password_confirm'])         : '';
    $signature       = !empty($_POST['signature'])        ? trim($_POST['signature'])                : '';
    $userLanguage    = !empty($_POST['language'])         ? trim($_POST['language'])                 : '';

	$signature = isset($signature) ? str_replace('<br />', "\n", $signature) : '';
	$signature_bbcode_uid = '';

	// Run some validation on the optional fields. These are pass-by-ref, so they'll be changed to
	// empty strings if they fail.
	Validator::optionalFields($website, $location, $occupation, $interests, $signature);

    $allowViewOnline = isset($_POST['hideonline'])  ? (bool)!$_POST['hideonline'] : true;
    $notifyReply     = isset($_POST['notifyreply']) ? (bool)$_POST['notifyreply'] : 0;
    $notifyPm        = isset($_POST['notifypm'])    ? (bool)$_POST['notifypm']    : true;
    $popupPm         = isset($_POST['popup_pm'])    ? (bool)$_POST['popup_pm']    : true;
    $sid             = isset($_POST['sid'])         ? $_POST['sid']               : 0;
    $userStyle       = isset($_POST['style'])       ? (int)$_POST['style']        : $board_config['default_style'];

	if ($mode === 'register') {
        $attachSignature = isset($_POST['attachsig'])    ? (bool)$_POST['attachsig']    : $board_config['allow_sig'];
        $allowHtml       = isset($_POST['allowhtml'])    ? (bool)$_POST['allowhtml']    : $board_config['allow_html'];
        $allowbbcode     = isset($_POST['allowbbcode'])  ? (bool)$_POST['allowbbcode']  : $board_config['allow_bbcode'];
        $allowSmileys    = isset($_POST['allowsmilies']) ? (bool)$_POST['allowsmilies'] : $board_config['allow_smilies'];
	} else {
		$attachSignature = isset($_POST['attachsig'])    ? (bool)$_POST['attachsig']    : $userdata['user_attachsig'];
		$allowHtml       = isset($_POST['allowhtml'])    ? (bool)$_POST['allowhtml']    : $userdata['user_allowhtml'];
		$allowbbcode     = isset($_POST['allowbbcode'])  ? (bool)$_POST['allowbbcode']  : $userdata['user_allowbbcode'];
		$allowSmileys    = isset($_POST['allowsmilies']) ? (bool)$_POST['allowsmilies'] : $userdata['user_allowsmile'];
	}

    if (!empty($_POST['language'])) {
        if (preg_match('/^[a-z_]+$/i', $_POST['language'])) {
            $userLanguage = htmlspecialchars($_POST['language']);
        } else {
            $error        = true;
            $errorMessage = $lang['Fields_empty'];
        }
    } else {
        $userLanguage = $board_config['default_lang'];
    }

	$userTimeZone = isset($_POST['timezone']) ? $_POST['timezone'] : $board_config['board_timezone'];

	// TODO i think i have this value already in $board_config, why i get it again???
	$board_default_dateformat = dibi::select('config_value')
        ->from(Tables::CONFIG_TABLE)
        ->where('config_name = %s', 'default_dateformat')
        ->fetchSingle();

	$board_config['default_dateformat'] = $board_default_dateformat;
	$userDateFormat                     = !empty($_POST['dateformat']) ? trim(htmlspecialchars($_POST['dateformat'])) : $board_config['default_dateformat'];

    if (isset($_POST['avatarselect']) && !empty($_POST['submitavatar']) && $board_config['allow_avatar_local']) {
        $userAvatarLocal = htmlspecialchars($_POST['avatarselect']);
    } else {
        $userAvatarLocal = isset($_POST['avatarlocal']) ? htmlspecialchars($_POST['avatarlocal']) : '';
    }

	$userAvatarCategory = isset($_POST['avatarcatname']) && $board_config['allow_avatar_local'] ? htmlspecialchars($_POST['avatarcatname']) : '' ;
	$userAvatarRemoteUrl = !empty($_POST['avatarremoteurl']) ? trim(htmlspecialchars($_POST['avatarremoteurl'])) : '';

    if (!empty($_POST['avatarurl'])) {
        $userAvatarUpload = trim($_POST['avatarurl']);
    } else {
        $userAvatarUpload = isset($_FILES['avatar']['tmp_name']) ? $_FILES['avatar']['tmp_name'] : '';
    }

	$userAvatarName     = !empty($_FILES['avatar']['name']) ? $_FILES['avatar']['name'] : '';
	$userAvatarSize     = !empty($_FILES['avatar']['size']) ? $_FILES['avatar']['size'] : 0;
	$userAvatarFileType = !empty($_FILES['avatar']['type']) ? $_FILES['avatar']['type'] : '';

    $userAvatar     = (empty($userAvatarLocal) && $mode === 'editprofile') ? $userdata['user_avatar'] : '';
    $userAvatarType = (empty($userAvatarLocal) && $mode === 'editprofile') ? $userdata['user_avatar_type'] : '';

    if ((isset($_POST['avatargallery']) || isset($_POST['submitavatar']) || isset($_POST['cancelavatar'])) && !isset($_POST['submit'])) {
		$userName        = stripslashes($userName);
		$email           = stripslashes($email);
		$currentPassword = htmlspecialchars(stripslashes($currentPassword));
		$newPassword     = htmlspecialchars(stripslashes($newPassword));
		$confirmPassword = htmlspecialchars(stripslashes($confirmPassword));

		$website = stripslashes($website);
		$location = stripslashes($location);
		$occupation = stripslashes($occupation);
		$interests = stripslashes($interests);
		$signature = htmlspecialchars(stripslashes($signature));

		$userLanguage   = stripslashes($userLanguage);
		$userDateFormat = stripslashes($userDateFormat);

        if (!isset($_POST['cancelavatar'])) {
			$userAvatar = $userAvatarCategory . $sep . $userAvatarLocal;
			$userAvatarType = USER_AVATAR_GALLERY;
		}
	}
}

//
// Let's make sure the user isn't logged in while registering,
// and ensure that they were trying to register a second time
// (Prevents double registrations)
//
if ($mode === 'register' && ($userdata['session_logged_in'] || $userName === $userdata['username'])) {
	message_die(GENERAL_MESSAGE, $lang['Username_taken'], '', __LINE__, __FILE__);
}

//
// Did the user submit? In this case build a query to update the users profile in the DB
//
if (isset($_POST['submit'])) {
	// session id check
	if ($sid === '' || $sid !== $userdata['session_id']) {
		$error        = true;
		$errorMessage .= ( isset($errorMessage) ? '<br />' : '' ) . $lang['Session_invalid'];
	}

    if ($mode === 'editprofile') {
        if ($userId !== $userdata['user_id']) {
			$error        = true;
			$errorMessage .= ( isset($errorMessage) ? '<br />' : '' ) . $lang['Wrong_Profile'];
		}
    } elseif ($mode === 'register') {
        if (empty($userName) || empty($newPassword) || empty($confirmPassword) || empty($email)) {
			$error        = true;
			$errorMessage .= ( isset($errorMessage) ? '<br />' : '' ) . $lang['Fields_empty'];
		}
	}

	if ($board_config['enable_confirm'] && $mode === 'register') {
		if (empty($_POST['confirm_id'])) {
			$error        = true;
			$errorMessage .= ( isset($errorMessage) ? '<br />' : '' ) . $lang['Confirm_code_wrong'];
		} else {
			$confirmId = htmlspecialchars($_POST['confirm_id']);

			if (!preg_match('/^[A-Za-z0-9]+$/', $confirmId)) {
				$confirmId = '';
			}

			// todo maybe fetchSingle()??
			$row = dibi::select('code')
                ->from(Tables::CONFIRM_TABLE)
                ->where('confirm_id = %s', $confirmId)
                ->where('session_id = %s', $userdata['session_id'])
                ->fetch();

			if ($row) {
                if ($row->code !== $confirmCode) {
                    $error        = true;
					$errorMessage .= ( isset($errorMessage) ? '<br />' : '' ) . $lang['Confirm_code_wrong'];
				} else {
				    dibi::delete(Tables::CONFIRM_TABLE)
                        ->where('confirm_id = %s', $confirmId)
                        ->where('session_id = %s', $userdata['session_id'])
                        ->execute();
				}
			} else {
                $error        = true;
				$errorMessage .= ( isset($errorMessage) ? '<br />' : '' ) . $lang['Confirm_code_wrong'];
			}
		}
	}

	$user_password_data = [];

    if (!empty($newPassword) && !empty($confirmPassword)) {
        $passwordLength = mb_strlen($newPassword);

        if ($newPassword !== $confirmPassword) {
            $error        = true;
            $errorMessage .= (isset($errorMessage) ? '<br />' : '') . $lang['Password_mismatch'];
        } elseif ($passwordLength < USER_MIN_PASSWORD_LENGTH) {
            $error        = true;
            $errorMessage .= (isset($errorMessage) ? '<br />' : '') . $lang['Password_short'];
        } elseif ($passwordLength > USER_MAX_PASSWORD_LENGTH) {
            $error        = true;
            $errorMessage .= (isset($errorMessage) ? '<br />' : '') . $lang['Password_long'];
		} else {
            if ($mode === 'editprofile') {
			    $db_password = dibi::select('user_password')
                    ->from(Tables::USERS_TABLE)
                    ->where('user_id = %i', $userId)
                    ->fetchSingle();

                if (!$db_password) {
                    message_die(GENERAL_ERROR, 'Could not obtain user_password information');
                }

                if (!password_verify($currentPassword, $db_password)) {
                    $error        = true;
                    $errorMessage .= (isset($errorMessage) ? '<br />' : '') . $lang['Current_password_mismatch'];
				}
			}

            if (!$error) {
				$newPassword        = password_hash($newPassword, PASSWORD_BCRYPT);
                $user_password_data = ['user_password' => $newPassword];
			}
		}
    } elseif ((empty($newPassword) && !empty($confirmPassword)) || (!empty($newPassword) && empty($confirmPassword))) {
        $error        = true;
        $errorMessage .= (isset($errorMessage) ? '<br />' : '') . $lang['Password_mismatch'];
	}

	//
	// Do a ban check on this email address
	//
    if ($email !== $userdata['user_email'] || $mode === 'register') {
        $result = Validator::email($email, $lang);

        if ($result['error']) {
            $email = $userdata['user_email'];

            $error        = true;
            $errorMessage .= (isset($errorMessage) ? '<br />' : '') . $result['error_msg'];
		}

        if ($mode === 'editprofile') {
            $db_password = dibi::select('user_password')
                ->from(Tables::USERS_TABLE)
                ->where('user_id = %i', $userId)
                ->fetchSingle();

            if (!$db_password) {
                message_die(GENERAL_ERROR, 'Could not obtain user_password information');
            }

			if (!password_verify($currentPassword, $db_password)) {
				$email = $userdata['user_email'];
                $error = true;

                $errorMessage .= (isset($errorMessage) ? '<br />' : '') . $lang['Current_password_mismatch'];
			}
		}
	}

	$username_data = [];
	if ($board_config['allow_namechange'] || $mode === 'register') {
        if (empty($userName)) {
			// Error is already triggered, since one field is empty.
            $error = true;
        } elseif ($userName !== $userdata['username'] || $mode === 'register') {
            if (mb_strtolower($userName) !== mb_strtolower($userdata['username']) || $mode === 'register') {
				$result = Validator::userName($userName, $lang, $userdata);

                if ($result['error']) {
                    $error = true;
					$errorMessage .= ( isset($errorMessage) ? '<br />' : '' ) . $result['error_msg'];
				}
			}

			if (!$error) {
				$username_data =  ['username' => $userName];
			}
		}
	}

    if ($signature !== '') {
        if (mb_strlen($signature) > $board_config['max_sig_chars']) {
            $error = true;
			$errorMessage .= ( isset($errorMessage) ? '<br />' : '' ) . $lang['Signature_too_long'];
		}

        if (!isset($signature_bbcode_uid) || $signature_bbcode_uid === '') {
			$signature_bbcode_uid = $allowbbcode ? Random::generate(BBCODE_UID_LEN) : '';
		}
		$signature = PostHelper::prepareMessage($signature, $allowHtml, $allowbbcode, $allowSmileys, $signature_bbcode_uid);
	}

    if ($website !== '') {
		rawurlencode($website);
	}

	$avatarData = [];

	if (isset($_POST['avatardel']) && $mode === 'editprofile') {
		$avatarData = AvatarHelper::userAvatarDelete($userdata['user_avatar_type'], $userdata['user_avatar']);
	} elseif (( !empty($userAvatarUpload) || !empty($userAvatarName) ) && $board_config['allow_avatar_upload']) {
		if (!empty($userAvatarUpload)) {
			$avatarMode = empty($userAvatarName) ? 'remote' : 'local';
			$avatarData = AvatarHelper::userAvatarUpload($mode, $avatarMode, $userdata['user_avatar'], $userdata['user_avatar_type'], $error, $errorMessage, $userAvatarUpload, $userAvatarName, $userAvatarSize, $userAvatarFileType);
		} elseif (!empty($userAvatarName)) {
			$l_avatar_size = sprintf($lang['Avatar_filesize'], round($board_config['avatar_filesize'] / 1024));

			$error        = true;
			$errorMessage .= ( !empty($errorMessage) ? '<br />' : '' ) . $l_avatar_size;
		}
	} elseif ($userAvatarRemoteUrl !== '' && $board_config['allow_avatar_remote']) {
        AvatarHelper::userAvatarDelete($userdata['user_avatar_type'], $userdata['user_avatar']);
		$avatarData = AvatarHelper::userAvatarUrl($mode, $error, $errorMessage, $userAvatarRemoteUrl);
	} elseif ($userAvatarLocal !== '' && $board_config['allow_avatar_local']) {
        AvatarHelper::userAvatarDelete($userdata['user_avatar_type'], $userdata['user_avatar']);
		$avatarData = AvatarHelper::userAvatarGallery($mode, $error, $errorMessage, $userAvatarLocal, $userAvatarCategory);
	}

	if (!$error) {
	    /*
	     * TODO
		if ($avatar_sql === '') {
			$avatar_sql = ( $mode === 'editprofile' ) ? '' : "'', " . USER_AVATAR_NONE;
		}
	    */

        if ($mode === 'editprofile') {
            if ($email !== $userdata['user_email'] && $board_config['require_activation'] !== USER_ACTIVATION_NONE && $userdata['user_level'] !== ADMIN) {
				$userActive = 0;

				$userActivationKey = Random::generate(25);
				$keyLength         = 54 - mb_strlen($serverUrl);
				$keyLength         = $keyLength > 6 ? $keyLength : 6;
				$userActivationKey = substr($userActivationKey, 0, $keyLength);

                if ($userdata['session_logged_in']) {
                    Session::end($userdata['session_id'], $userdata['user_id']);
				}
			} else {
				$userActive        = 1;
				$userActivationKey = '';
			}

            $updateData = [
                'user_email'            => $email,
                'user_website'          => $website,
                'user_occ'              => $occupation,
                'user_from'             => $location,
                'user_interests'        => $interests,
                'user_sig'              => $signature,
                'user_sig_bbcode_uid'   => $signature_bbcode_uid,
                'user_attachsig'        => $attachSignature,
                'user_allowsmile'       => $allowSmileys,
                'user_allowhtml'        => $allowHtml,
                'user_allowbbcode'      => $allowbbcode,
                'user_allow_viewonline' => $allowViewOnline,
                'user_notify'           => $notifyReply,
                'user_notify_pm'        => $notifyPm,
                'user_popup_pm'         => $popupPm,
                'user_timezone'         => $userTimeZone,
                'user_dateformat'       => $userDateFormat,
                'user_lang'             => $userLanguage,
                'user_style'            => $userStyle,
                'user_active'           => $userActive,
                'user_actkey'           => $userActivationKey
            ];

			$updateData = array_merge($updateData, $avatarData, $username_data, $user_password_data);

			dibi::update(Tables::USERS_TABLE, $updateData)
                ->where('user_id = %i', $userId)
                ->execute();

			// We remove all stored login keys since the password has been updated
			// and change the current one (if applicable)
            if (count($user_password_data)) {
                Session::resetKeys($userId, $user_ip);
            }

            if (!$userActive) {
				//
				// The users account has been deactivated, send them an email with a new activation key
				//
				$emailer = new Emailer($board_config['smtp_delivery']);

                if ($board_config['require_activation'] !== USER_ACTIVATION_ADMIN) {
 					$emailer->setFrom($board_config['board_email']);
 					$emailer->setReplyTo($board_config['board_email']);

 					$emailer->useTemplate('user_activate', stripslashes($userLanguage));
 					$emailer->setEmailAddress($email);
 					$emailer->setSubject($lang['Reactivate']);

                    $emailer->assignVars(
                        [
                            'SITENAME'  => $board_config['sitename'],
                            'USERNAME'  => preg_replace(PostHelper::$unHtmlSpecialCharsMatch, PostHelper::$unHtmlSpecialCharsReplace, substr(str_replace("\'", "'", $userName), 0, 25)),
                            'EMAIL_SIG' => !empty($board_config['board_email_sig']) ? str_replace('<br />', "\n", "-- \n" . $board_config['board_email_sig']) : '',

                            'U_ACTIVATE' => $serverUrl . '?mode=activate&' . POST_USERS_URL . '=' . $userId . '&act_key=' . $userActivationKey
                        ]
                    );
                    $emailer->send();
 					$emailer->reset();
                } elseif ($board_config['require_activation'] === USER_ACTIVATION_ADMIN) {
 				    $admins = dibi::select(['user_email', 'user_lang'])
                        ->from(Tables::USERS_TABLE)
                        ->where('user_level = %i', ADMIN)
                        ->fetchAll();

                    foreach ($admins as $admin) {
                        $emailer->setFrom($board_config['board_email']);
                        $emailer->setReplyTo($board_config['board_email']);

                        $emailer->setEmailAddress(trim($admin->user_email));
                        $emailer->useTemplate('admin_activate', $admin->user_lang);
                        $emailer->setSubject($lang['Reactivate']);

                        $emailer->assignVars(
                            [
                                'USERNAME'  => preg_replace(PostHelper::$unHtmlSpecialCharsMatch, PostHelper::$unHtmlSpecialCharsReplace, substr(str_replace("\'", "'", $userName), 0, 25)),
                                'EMAIL_SIG' => str_replace('<br />', "\n", "-- \n" . $board_config['board_email_sig']),

                                'U_ACTIVATE' => $serverUrl . '?mode=activate&' . POST_USERS_URL . '=' . $userId . '&act_key=' . $userActivationKey
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

            $insertData = [
                'username' => $userName,
                'user_regdate' => time(),
                'user_password' => $newPassword,
                'user_email' => $email,
                'user_website' => $website,
                'user_occ' =>  $occupation,
                'user_from' => $location,
                'user_interests' => $interests,
                'user_sig' => $signature,
                'user_sig_bbcode_uid' => $signature_bbcode_uid,
                //'user_avatar' => null, // TODO $avatar_sql,
                //'user_avatar_type' => null, // TODO $avatar_sql,
                'user_attachsig' => $attachSignature,
                'user_allowsmile' => $allowSmileys,
                'user_allowhtml' => $allowHtml,
                'user_allowbbcode' => $allowbbcode,
                'user_allow_viewonline' => $allowViewOnline,
                'user_notify' => $notifyReply,
                'user_popup_pm' => $popupPm,
                'user_timezone' => $userTimeZone,
                'user_dateformat' => $userDateFormat,
                'user_lang' => $userLanguage,
                'user_style' => $userStyle,
                'user_level' => USER,
                'user_allow_pm' => 1,
                'user_active' => null,
                'user_actkey' => null
            ];

            $insertData = array_merge($insertData, $avatarData);

            if ($board_config['require_activation'] === USER_ACTIVATION_SELF || $board_config['require_activation'] === USER_ACTIVATION_ADMIN || $coppa) {
				$userActivationKey = Random::generate(25);
				$keyLength         = 54 - mb_strlen($serverUrl);
				$keyLength         = $keyLength > 6 ? $keyLength : 6;
				$userActivationKey = substr($userActivationKey, 0, $keyLength);

                $insertData['user_active'] = 0;
                $insertData['user_actkey'] = $userActivationKey;
			} else {
                $insertData['user_active'] = 1;
                $insertData['user_actkey'] = '';
			}

			$userId = dibi::insert(Tables::USERS_TABLE, $insertData)->execute();

            $group_insert_data = [
                'group_name'        => '',
                'group_description' => 'Personal User',
                'group_single_user' => 1,
                'group_moderator'   => 0
            ];

            $groupId = dibi::insert(Tables::GROUPS_TABLE, $group_insert_data)->execute(dibi::IDENTIFIER);

			$userGroupDataInsert = [
                'user_id' => $userId,
                'group_id' => $groupId,
                'user_pending' => 0
            ];

			dibi::insert(Tables::USERS_GROUPS_TABLE, $userGroupDataInsert)->execute();

            if ($coppa) {
				$message = $lang['COPPA'];
				$emailTemplate = 'coppa_welcome_inactive';
            } elseif ($board_config['require_activation'] === USER_ACTIVATION_SELF) {
				$message = $lang['Account_inactive'];
				$emailTemplate = 'user_welcome_inactive';
            } elseif ($board_config['require_activation'] === USER_ACTIVATION_ADMIN) {
				$message = $lang['Account_inactive_admin'];
				$emailTemplate = 'admin_welcome_inactive';
			} else {
				$message = $lang['Account_added'];
				$emailTemplate = 'user_welcome';
			}

			$emailer = new Emailer($board_config['smtp_delivery']);

			$emailer->setFrom($board_config['board_email']);
			$emailer->setReplyTo($board_config['board_email']);

			$emailer->useTemplate($emailTemplate, stripslashes($userLanguage));
			$emailer->setEmailAddress($email);
			$emailer->setSubject(sprintf($lang['Welcome_subject'], $board_config['sitename']));

            if ($coppa) {
                $emailer->assignVars(
                    [
                        'SITENAME'    => $board_config['sitename'],
                        'WELCOME_MSG' => sprintf($lang['Welcome_subject'], $board_config['sitename']),
                        'USERNAME'    => preg_replace(PostHelper::$unHtmlSpecialCharsMatch, PostHelper::$unHtmlSpecialCharsReplace, substr(str_replace("\'", "'", $userName), 0, 25)),
                        'PASSWORD'    => $confirmPassword,
                        'EMAIL_SIG'   => str_replace('<br />', "\n", "-- \n" . $board_config['board_email_sig']),

                        'FAX_INFO'      => $board_config['coppa_fax'],
                        'MAIL_INFO'     => $board_config['coppa_mail'],
                        'EMAIL_ADDRESS' => $email,
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
                        'USERNAME'    => preg_replace(PostHelper::$unHtmlSpecialCharsMatch, PostHelper::$unHtmlSpecialCharsReplace, substr(str_replace("\'", "'", $userName), 0, 25)),
                        'PASSWORD'    => $confirmPassword,
                        'EMAIL_SIG'   => str_replace('<br />', "\n", "-- \n" . $board_config['board_email_sig']),

                        'U_ACTIVATE' => $serverUrl . '?mode=activate&' . POST_USERS_URL . '=' . $userId . '&act_key=' . $userActivationKey
                    ]
                );
            }

			$emailer->send();
			$emailer->reset();

            if ($board_config['require_activation'] === USER_ACTIVATION_ADMIN) {
			    $admins = dibi::select(['user_email', 'user_lang'])
                    ->from(Tables::USERS_TABLE)
                    ->where('user_level = %i', ADMIN)
                    ->fetchAll();

                foreach ($admins as $admin) {
					$emailer->setFrom($board_config['board_email']);
					$emailer->setReplyTo($board_config['board_email']);

					$emailer->setEmailAddress(trim($admin->user_email));
					$emailer->useTemplate('admin_activate', $admin->user_lang);
					$emailer->setSubject($lang['New_account_subject']);

                    $emailer->assignVars(
                        [
                            'USERNAME'  => preg_replace(PostHelper::$unHtmlSpecialCharsMatch, PostHelper::$unHtmlSpecialCharsReplace, substr(str_replace("\'", "'", $userName), 0, 25)),
                            'EMAIL_SIG' => str_replace('<br />', "\n", "-- \n" . $board_config['board_email_sig']),

                            'U_ACTIVATE' => $serverUrl . '?mode=activate&' . POST_USERS_URL . '=' . $userId . '&act_key=' . $userActivationKey
                        ]
                    );
                    $emailer->send();
					$emailer->reset();
				}
			}

			$message .= sprintf($lang['Click_return_index'], '<a href="' . Session::appendSid('index.php') . '">', '</a>') . '<br /><br />';

			message_die(GENERAL_MESSAGE, $message);
		} // if mode === register
	}
} // End of submit

if ($error) {
	//
	// If an error occured we need to stripslashes on returned data
	//
	$userName = stripslashes($userName);
	$email = stripslashes($email);
	$currentPassword = '';
	$newPassword  = '';
	$confirmPassword = '';

	$website = stripslashes($website);
	$location = stripslashes($location);
	$occupation = stripslashes($occupation);
	$interests = stripslashes($interests);
	$signature = stripslashes($signature);
	$signature = $signature_bbcode_uid !== '' ? preg_replace("/:(([a-z0-9]+:)?)$signature_bbcode_uid(=|\])/si", '\\3', $signature) : $signature;

	$userLanguage   = stripslashes($userLanguage);
	$userDateFormat = stripslashes($userDateFormat);
} elseif ($mode === 'editprofile' && !isset($_POST['avatargallery']) && !isset($_POST['submitavatar']) && !isset($_POST['cancelavatar'])) {
	$userId = $userdata['user_id'];
	$userName = $userdata['username'];
	$email = $userdata['user_email'];
	$currentPassword = '';
	$newPassword     = '';
	$confirmPassword = '';

	$website = $userdata['user_website'];
	$location = $userdata['user_from'];
	$occupation = $userdata['user_occ'];
	$interests = $userdata['user_interests'];
	$signature_bbcode_uid = $userdata['user_sig_bbcode_uid'];
	$signature = ($signature_bbcode_uid !== '') ? preg_replace("/:(([a-z0-9]+:)?)$signature_bbcode_uid(=|\])/si", '\\3', $userdata['user_sig']) : $userdata['user_sig'];

	$notifyPm = $userdata['user_notify_pm'];
	$popupPm = $userdata['user_popup_pm'];
	$notifyReply = $userdata['user_notify'];
	$attachSignature = $userdata['user_attachsig'];
	$allowHtml = $userdata['user_allowhtml'];
	$allowbbcode = $userdata['user_allowbbcode'];
	$allowSmileys = $userdata['user_allowsmile'];
	$allowViewOnline = $userdata['user_allow_viewonline'];

	$userAvatar = $userdata['user_allowavatar'] ? $userdata['user_avatar'] : '';
	$userAvatarType = $userdata['user_allowavatar'] ? $userdata['user_avatar_type'] : USER_AVATAR_NONE;

	$userStyle = $userdata['user_style'];
	$userLanguage = $userdata['user_lang'];
	$userTimeZone = $userdata['user_timezone'];
	$userDateFormat = $userdata['user_dateformat'];
}

//
// Default pages
//
PageHelper::header($template, $userdata, $board_config, $lang, $images,  $theme, $page_title, $gen_simple_header);

make_jumpbox('viewforum.php');

if (($mode === 'editprofile') && $userId !== $userdata['user_id']) {
    $error = true;
    $errorMessage = $lang['Wrong_Profile'];
}

if (isset($_POST['avatargallery']) && !$error) {
	$avatarCategory = !empty($_POST['avatarcategory']) ? htmlspecialchars($_POST['avatarcategory']) : '';

    $template->setFileNames(['body' => 'profile_avatar_gallery.tpl']);

    $allowViewOnline = !$allowViewOnline;

	AvatarHelper::displayAvatarGallery($mode, $avatarCategory, $userId, $email, $current_email, $coppa, $userName,$newPassword, $currentPassword, $confirmPassword, $website, $location, $occupation, $interests, $signature,  $notifyPm, $popupPm, $notifyReply, $attachSignature, $allowHtml, $allowbbcode, $allowSmileys, $allowViewOnline, $userStyle, $userLanguage, $userTimeZone, $userDateFormat, $userdata['session_id'], false, $template, $userActive, $allow_avatar, $allow_pm);
} else {
    if (!isset($coppa)) {
        $coppa = false;
    }

    if (!isset($userStyle)) {
        $userStyle = $board_config['default_style'];
    }

	$avatarImage = '';

    if ($userAvatarType) {
        switch ($userAvatarType) {
			case USER_AVATAR_UPLOAD:
				$avatarImage = $board_config['allow_avatar_upload'] ? '<img src="' . $board_config['avatar_path'] . '/' . $userAvatar . '" alt="" />' : '';
				break;
			case USER_AVATAR_REMOTE:
				$avatarImage = $board_config['allow_avatar_remote'] ? '<img src="' . $userAvatar . '" alt="" />' : '';
				break;
			case USER_AVATAR_GALLERY:
				$avatarImage = $board_config['allow_avatar_local'] ? '<img src="' . $board_config['avatar_gallery_path'] . '/' . $userAvatar . '" alt="" />' : '';
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

    if (!empty($userAvatarLocal)) {
		$s_hidden_fields .= '<input type="hidden" name="avatarlocal" value="' . $userAvatarLocal . '" /><input type="hidden" name="avatarcatname" value="' . $userAvatarCategory . '" />';
	}

	$htmlStatus    = $userdata['user_allowhtml'] && $board_config['allow_html']     ? $lang['HTML_is_ON']     : $lang['HTML_is_OFF'];
	$bbcodeStatus  = $userdata['user_allowbbcode'] && $board_config['allow_bbcode'] ? $lang['BBCode_is_ON']   : $lang['BBCode_is_OFF'];
	$smileysStatus = $userdata['user_allowsmile'] && $board_config['allow_smilies'] ? $lang['Smilies_are_ON'] : $lang['Smilies_are_OFF'];

    if ($error) {
        $template->setFileNames(['reg_header' => 'error_body.tpl']);
        $template->assignVars(['ERROR_MESSAGE' => $errorMessage]);
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
            ->from(Tables::SESSIONS_TABLE)
            ->fetchPairs(null, 'session_id');

	    if (count($sessions)) {
	        dibi::delete(Tables::CONFIRM_TABLE)
                ->where('session_id NOT IN %in', $sessions)
                ->execute();
        }

        $attempts = dibi::select('COUNT(session_id)')
            ->as('attempts')
            ->from(Tables::CONFIRM_TABLE)
            ->where('session_id = %s', $userdata['session_id'])
            ->fetchSingle();

        // TODO use constant
        if ($attempts > 3) {
            message_die(GENERAL_MESSAGE, $lang['Too_many_registers']);
        }

		// Generate the required confirmation code
		// NB 0 (zero) could get confused with O (the letter) so we make change it
		$code = Random::generate(45);
		$code = substr(str_replace('0', 'Z', mb_strtoupper(base_convert($code, 16, 35))), 2, 6);

        $confirmId = mb_substr(hash('sha512', Random::generate(32)), 0, 32);

		$confirm_insert_data = [
            'confirm_id' => $confirmId,
            'session_id' => $userdata['session_id'],
            'code'       => $code
        ];

		dibi::insert(Tables::CONFIRM_TABLE, $confirm_insert_data)->execute();

		unset($code);

		$confirm_image = '<img src="' . Session::appendSid("profile.php?mode=confirm&amp;id=$confirmId") . '" alt="" title="" />';
		$s_hidden_fields .= '<input type="hidden" name="confirm_id" value="' . $confirmId . '" />';

		$template->assignBlockVars('switch_confirm', []);
	}

	//
	// Let's do an overall check for settings/versions which would prevent
	// us from doing file uploads....
	//
	$form_enctype = ( @ini_get('file_uploads') === '0' || mb_strtolower(@ini_get('file_uploads')) === 'off' || PHP_VERSION === '4.0.4pl1' || !$board_config['allow_avatar_upload'] || ( PHP_VERSION < '4.0.3' && @ini_get('open_basedir') !== '' ) ) ? '' : 'enctype="multipart/form-data"';

    $template->assignVars(
        [
            'USERNAME'         => isset($userName) ? $userName : '',
            'CUR_PASSWORD'     => isset($currentPassword) ? $currentPassword : '',
            'NEW_PASSWORD'     => isset($newPassword) ? $newPassword : '',
            'PASSWORD_CONFIRM' => isset($confirmPassword) ? $confirmPassword : '',
            'EMAIL'            => isset($email) ? $email : '',
            'CONFIRM_IMG'      => $confirm_image,
            'OCCUPATION'       => htmlspecialchars($occupation, ENT_QUOTES),
            'INTERESTS'        => htmlspecialchars($interests, ENT_QUOTES),
            'LOCATION'         => htmlspecialchars($location, ENT_QUOTES),
            'WEBSITE'          => htmlspecialchars($website, ENT_QUOTES),
            'SIGNATURE'        => str_replace('<br />', "\n", $signature),

            'HIDE_USER_YES' => !$allowViewOnline ? 'checked="checked"' : '',
            'HIDE_USER_NO'  => $allowViewOnline ? 'checked="checked"' : '',

            'NOTIFY_PM_YES' => $notifyPm ? 'checked="checked"' : '',
            'NOTIFY_PM_NO'  => !$notifyPm ? 'checked="checked"' : '',

            'POPUP_PM_YES' => $popupPm ? 'checked="checked"' : '',
            'POPUP_PM_NO'  => !$popupPm ? 'checked="checked"' : '',

            'ALWAYS_ADD_SIGNATURE_YES' => $attachSignature ? 'checked="checked"' : '',
            'ALWAYS_ADD_SIGNATURE_NO'  => !$attachSignature ? 'checked="checked"' : '',

            'NOTIFY_REPLY_YES' => $notifyReply ? 'checked="checked"' : '',
            'NOTIFY_REPLY_NO'  => !$notifyReply ? 'checked="checked"' : '',

            'ALWAYS_ALLOW_BBCODE_YES' => $allowbbcode ? 'checked="checked"' : '',
            'ALWAYS_ALLOW_BBCODE_NO'  => !$allowbbcode ? 'checked="checked"' : '',

            'ALWAYS_ALLOW_HTML_YES' => $allowHtml ? 'checked="checked"' : '',
            'ALWAYS_ALLOW_HTML_NO'  => !$allowHtml ? 'checked="checked"' : '',

            'ALWAYS_ALLOW_SMILIES_YES' => $allowSmileys ? 'checked="checked"' : '',
            'ALWAYS_ALLOW_SMILIES_NO'  => !$allowSmileys ? 'checked="checked"' : '',

            'ALLOW_AVATAR'    => $board_config['allow_avatar_upload'],
            'AVATAR'          => $avatarImage,
            'AVATAR_SIZE'     => $board_config['avatar_filesize'],
            'LANGUAGE_SELECT' => Select::language($userLanguage),
            'STYLE_SELECT'    => Select::style($userStyle),
            'TIMEZONE_SELECT' => Select::timezone($userTimeZone),
            'DATE_FORMAT'     => $userDateFormat,
            'HTML_STATUS'     => $htmlStatus,
            'BBCODE_STATUS'   => sprintf($bbcodeStatus, '<a href="' . Session::appendSid('faq.php?mode=bbcode') . '" target="_phpbbcode">', '</a>'),
            'SMILIES_STATUS'  => $smileysStatus,

            'F_LOGIN_FORM_TOKEN' => CSRF::getInputHtml(),

            'L_CURRENT_PASSWORD'            => $lang['Current_password'],
            'L_NEW_PASSWORD'                => $mode === 'register' ? $lang['Password'] : $lang['New_password'],
            'L_CONFIRM_PASSWORD'            => $lang['Confirm_password'],
            'L_CONFIRM_PASSWORD_EXPLAIN'    => $mode === 'editprofile' ? $lang['Confirm_password_explain'] : '',
            'L_PASSWORD_IF_CHANGED'         => $mode === 'editprofile' ? $lang['password_if_changed'] : '',
            'L_PASSWORD_CONFIRM_IF_CHANGED' => $mode === 'editprofile' ? $lang['password_confirm_if_changed'] : '',
            'L_SUBMIT'                      => $lang['Submit'],
            'L_RESET'                       => $lang['Reset'],
            'L_WEBSITE'                     => $lang['Website'],
            'L_LOCATION'                    => $lang['Location'],
            'L_OCCUPATION'                  => $lang['Occupation'],
            'L_BOARD_LANGUAGE'              => $lang['Board_lang'],
            'L_BOARD_STYLE'                 => $lang['Board_style'],
            'L_TIMEZONE'                    => $lang['Timezone'],
            'L_DATE_FORMAT'                 => $lang['Date_format'],
            'L_DATE_FORMAT_EXPLAIN'         => $lang['Date_format_explain'],
            'L_YES'                         => $lang['Yes'],
            'L_NO'                          => $lang['No'],
            'L_INTERESTS'                   => $lang['Interests'],
            'L_ALWAYS_ALLOW_SMILIES'        => $lang['Always_smile'],
            'L_ALWAYS_ALLOW_BBCODE'         => $lang['Always_bbcode'],
            'L_ALWAYS_ALLOW_HTML'           => $lang['Always_html'],
            'L_HIDE_USER'                   => $lang['Hide_user'],
            'L_ALWAYS_ADD_SIGNATURE'        => $lang['Always_add_sig'],

            'L_AVATAR_PANEL'               => $lang['Avatar_panel'],
            'L_AVATAR_EXPLAIN'             => sprintf($lang['Avatar_explain'], $board_config['avatar_max_width'], $board_config['avatar_max_height'], get_formatted_filesize($board_config['avatar_filesize'])),
            'L_UPLOAD_AVATAR_FILE'         => $lang['Upload_Avatar_file'],
            'L_UPLOAD_AVATAR_URL'          => $lang['Upload_Avatar_URL'],
            'L_UPLOAD_AVATAR_URL_EXPLAIN'  => $lang['Upload_Avatar_URL_explain'],
            'L_AVATAR_GALLERY'             => $lang['Select_from_gallery'],
            'L_SHOW_GALLERY'               => $lang['View_avatar_gallery'],
            'L_LINK_REMOTE_AVATAR'         => $lang['Link_remote_Avatar'],
            'L_LINK_REMOTE_AVATAR_EXPLAIN' => $lang['Link_remote_Avatar_explain'],
            'L_DELETE_AVATAR'              => $lang['Delete_Image'],
            'L_CURRENT_IMAGE'              => $lang['Current_Image'],

            'L_SIGNATURE'                => $lang['Signature'],
            'L_SIGNATURE_EXPLAIN'        => sprintf($lang['Signature_explain'], $board_config['max_sig_chars']),
            'L_NOTIFY_ON_REPLY'          => $lang['Always_notify'],
            'L_NOTIFY_ON_REPLY_EXPLAIN'  => $lang['Always_notify_explain'],
            'L_NOTIFY_ON_PRIVMSG'        => $lang['Notify_on_privmsg'],
            'L_POPUP_ON_PRIVMSG'         => $lang['Popup_on_privmsg'],
            'L_POPUP_ON_PRIVMSG_EXPLAIN' => $lang['Popup_on_privmsg_explain'],
            'L_PREFERENCES'              => $lang['Preferences'],
            'L_ITEMS_REQUIRED'           => $lang['Items_required'],
            'L_REGISTRATION_INFO'        => $lang['Registration_info'],
            'L_PROFILE_INFO'             => $lang['Profile_info'],
            'L_PROFILE_INFO_NOTICE'      => $lang['Profile_info_warn'],
            'L_EMAIL_ADDRESS'            => $lang['Email_address'],

            'L_CONFIRM_CODE_IMPAIRED' => sprintf($lang['Confirm_code_impaired'], '<a href="mailto:' . $board_config['board_email'] . '">', '</a>'),
            'L_CONFIRM_CODE'          => $lang['Confirm_code'],
            'L_CONFIRM_CODE_EXPLAIN'  => $lang['Confirm_code_explain'],

            'S_ALLOW_AVATAR_UPLOAD' => $board_config['allow_avatar_upload'],
            'S_ALLOW_AVATAR_LOCAL'  => $board_config['allow_avatar_local'],
            'S_ALLOW_AVATAR_REMOTE' => $board_config['allow_avatar_remote'],
            'S_HIDDEN_FIELDS'       => $s_hidden_fields,
            'S_FORM_ENCTYPE'        => $form_enctype,
            'S_PROFILE_ACTION'      => Session::appendSid('profile.php')
        ]
    );

    //
	// This is another cheat using the block_var capability
	// of the templates to 'fake' an IF...ELSE...ENDIF solution
	// it works well :)
	//
    if (
        $mode !== 'register' &&
        $userdata['user_allowavatar'] &&
            (
                $board_config['allow_avatar_upload'] ||
                $board_config['allow_avatar_local'] ||
                $board_config['allow_avatar_remote']
            )
    ) {
        $template->assignBlockVars('switch_avatar_block', [] );

        if ($board_config['allow_avatar_upload'] && file_exists(@realpath('.' . $sep . $board_config['avatar_path']))) {
            if ($form_enctype !== '') {
                $template->assignBlockVars('switch_avatar_block.switch_avatar_local_upload', []);
            }

            $template->assignBlockVars('switch_avatar_block.switch_avatar_remote_upload', []);
        }

        if ($board_config['allow_avatar_remote']) {
            $template->assignBlockVars('switch_avatar_block.switch_avatar_remote_link', []);
        }

        if ($board_config['allow_avatar_local'] && file_exists(@realpath('.' . $sep . $board_config['avatar_gallery_path']))) {
            $template->assignBlockVars('switch_avatar_block.switch_avatar_local_gallery', []);
        }
    }
}

$template->pparse('body');

PageHelper::footer($template, $userdata, $lang, $gen_simple_header);

?>