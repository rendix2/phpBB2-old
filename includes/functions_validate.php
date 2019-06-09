<?php
/***************************************************************************
 *                          functions_validate.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: functions_validate.php 8361 2008-02-01 12:49:38Z acydburn $
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

//
// Check to see if the username has been taken, or if it is disallowed.
// Also checks if it includes the " character, which we don't allow in usernames.
// Used for registering, changing names, and posting anonymously with a username
//
function validate_username($username)
{
	global $lang, $userdata;

	// Remove doubled up spaces
	$username = preg_replace('#\s+#', ' ', trim($username)); 
	$username = phpbb_clean_username($username);

	$lower_user_name = mb_strtolower($username);

	$db_user_name = dibi::select('username')
        ->from(USERS_TABLE)
        ->where('LOWER(username) = %s', $lower_user_name)
        ->fetch();

	if ($db_user_name) {
        if (($userdata['session_logged_in'] && $db_user_name->username !== $userdata['username']) || !$userdata['session_logged_in']) {

            return ['error' => true, 'error_msg' => $lang['Username_taken']];
        }
    }

	$db_group_name = dibi::select('group_name')
        ->from(GROUPS_TABLE)
        ->where('LOWER(group_name) = %s', $lower_user_name)
        ->fetch();

	if ($db_group_name) {
        return ['error' => true, 'error_msg' => $lang['Username_taken']];
    }

	$disallows = dibi::select('disallow_username')
        ->from(DISALLOW_TABLE)
        ->fetchAll();

	foreach ($disallows as $disallow) {
        if (preg_match("#\b(" . str_replace("\*", '.*?', preg_quote($disallow->disallow_username, '#')) . ")\b#i", $username)) {
            return ['error' => true, 'error_msg' => $lang['Username_disallowed']];
        }
    }

	$words = dibi::select('word')
        ->from(WORDS_TABLE)
        ->fetchAll();

	foreach ($words as $word) {
        if (preg_match("#\b(" . str_replace("\*", '.*?', preg_quote($word->word, '#')) . ")\b#i", $username)) {

            return ['error' => true, 'error_msg' => $lang['Username_disallowed']];
        }
    }

	// Don't allow " and ALT-255 in username.
	if (false !== strpos($username, '"') || false !== strpos($username, '&quot;') || false !== strpos($username, chr(160)) || false !== strpos($username, chr(173))) {
        return ['error' => true, 'error_msg' => $lang['Username_invalid']];
	}

    return ['error' => false, 'error_msg' => ''];
}

//
// Check to see if email address is banned
// or already present in the DB
//
function validate_email($email)
{
	global $lang;

	if ($email !== '') {
		if (preg_match('/^[a-z0-9&\'\.\-_\+]+@[a-z0-9\-]+\.([a-z0-9\-]+\.)*?[a-z]+$/is', $email)) {

		    $bans = dibi::select('ban_email')
                ->from(BANLIST_TABLE)
                ->fetchAll();

		    foreach ($bans as $ban) {
                $match_email = str_replace('*', '.*?', $ban->ban_email);

                if (preg_match('/^' . $match_email . '$/is', $email)) {

                    return ['error' => true, 'error_msg' => $lang['Email_banned']];
                }
            }

			$db_email = dibi::select('user_email')
                ->from(USERS_TABLE)
                ->where('user_email = %s', $email)
                ->fetch();
		
			if ($db_email) {
                return ['error' => true, 'error_msg' => $lang['Email_taken']];
            }

            return ['error' => false, 'error_msg' => ''];
        }
    }

    return ['error' => true, 'error_msg' => $lang['Email_invalid']];
}

//
// Does supplementary validation of optional profile fields. This expects common stuff like trim() and strip_tags()
// to have already been run. Params are passed by-ref, so we can set them to the empty string if they fail.
//
function validate_optional_fields(&$icq, &$aim, &$msnm, &$yim, &$website, &$location, &$occupation, &$interests, &$sig)
{
    $check_var_length = ['aim', 'msnm', 'yim', 'location', 'occupation', 'interests', 'sig'];

    foreach ($check_var_length as $value) {
		if (mb_strlen($$value) < 2) {
			$$value = '';
		}
	}

	// ICQ number has to be only numbers.
	if (!preg_match('/^[0-9]+$/', $icq)) {
		$icq = '';
	}
	
	// website has to start with http://, followed by something with length at least 3 that
	// contains at least one dot.
	if ($website !== '') {
		if (!preg_match('#^http[s]?:\/\/#i', $website)) {
			$website = 'http://' . $website;
		}

		if (!preg_match('#^http[s]?\\:\\/\\/[a-z0-9\-]+\.([a-z0-9\-]+\.)?[a-z]+#i', $website)) {
			$website = '';
		}
	}
}

?>