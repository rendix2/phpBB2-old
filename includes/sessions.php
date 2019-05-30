<?php
/***************************************************************************
 *                                sessions.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: sessions.php 5930 2006-05-18 19:23:07Z grahamje $
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
// Adds/updates a new session to the database for the given userid.
// Returns the new session ID on success.
//
function session_begin($user_id, $user_ip, $page_id, $auto_create = 0, $enable_autologin = 0, $admin = 0)
{
	global $db, $board_config;
	global $SID;

	$cookiename = $board_config['cookie_name'];
	$cookiepath = $board_config['cookie_path'];
	$cookiedomain = $board_config['cookie_domain'];
	$cookiesecure = $board_config['cookie_secure'];

	if ( isset($_COOKIE[$cookiename . '_sid']) || isset($_COOKIE[$cookiename . '_data']) ) {
		$session_id = isset($_COOKIE[$cookiename . '_sid']) ? $_COOKIE[$cookiename . '_sid'] : '';
		$sessiondata = isset($_COOKIE[$cookiename . '_data']) ? unserialize(stripslashes($_COOKIE[$cookiename . '_data'])) : [];
		$sessionmethod = SESSION_METHOD_COOKIE;
	} else {
		$sessiondata = [];
		$session_id = isset($_GET['sid']) ? $_GET['sid'] : '';
		$sessionmethod = SESSION_METHOD_GET;
	}

	//
	if (!preg_match('/^[A-Za-z0-9]*$/', $session_id))  {
		$session_id = '';
	}

	$page_id = (int) $page_id;

	$last_visit = 0;
	$current_time = time();

	//
	// Are auto-logins allowed?
	// If allow_autologin is not set or is true then they are
	// (same behaviour as old 2.0.x session code)
	//
	if (isset($board_config['allow_autologin']) && !$board_config['allow_autologin']) {
		$enable_autologin = $sessiondata['autologinid'] = false;
	}

	// 
	// First off attempt to join with the autologin value if we have one
	// If not, just use the user_id value
	//
	$userdata = [];

	if ($user_id != ANONYMOUS) {
		if (isset($sessiondata['autologinid']) && (string) $sessiondata['autologinid'] != '' && $user_id) {
            $userdata = dibi::select('u.*')
                ->from(USERS_TABLE)
                ->as('u')
                ->from(SESSIONS_KEYS_TABLE)
                ->as('k')
                ->where('u.user_id = %i', (int) $user_id)
                ->where('u.user_active = %i', 1)
                ->where('k.user_id = u.user_id')
                ->where('k.key_id = %s', md5($sessiondata['autologinid']))
                ->fetch();

			$userdata = $userdata->toArray();
		
			$enable_autologin = $login = 1;
		} elseif (!$auto_create) {
			$sessiondata['autologinid'] = '';
			$sessiondata['userid'] = $user_id;

			$userdata = dibi::select('*')
                ->from(USERS_TABLE)
                ->where('user_id = %i', (int) $user_id)
                ->where('user_active = %i', 1)
                ->fetch();

			if (!$userdata) {
				message_die(CRITICAL_ERROR, 'Error doing DB query userdata row fetch', '', __LINE__, __FILE__, $sql);
			}

			// we need it as array, no object.. :(
			$userdata = $userdata->toArray();

			$login = 1;
		}
	}

	//
	// At this point either $userdata should be populated or
	// one of the below is true
	// * Key didn't match one in the DB
	// * User does not exist
	// * User is inactive
	//
	if (!count($userdata) || !is_array($userdata) || !$userdata) {
		$sessiondata['autologinid'] = '';
		$sessiondata['userid'] = $user_id = ANONYMOUS;
		$enable_autologin = $login = 0;

		$userdata = dibi::select('*')
            ->from(USERS_TABLE)
            ->where('user_id = %i', $user_id)
            ->fetch();

		if (!$userdata) {
			message_die(CRITICAL_ERROR, 'Error doing DB query userdata row fetch');
		}
	}

	//
	// Initial ban check against user id, IP and email address
	//
	preg_match('/(..)(..)(..)(..)/', $user_ip, $user_ip_parts);

	$ban_ip_array = [
	    $user_ip_parts[1] . $user_ip_parts[2] . $user_ip_parts[3] . $user_ip_parts[4],
        $user_ip_parts[1] . $user_ip_parts[2] . $user_ip_parts[3] . 'ff',
        $user_ip_parts[1] . $user_ip_parts[2] . 'ffff',
        $user_ip_parts[1] . 'ffffff'
    ];

	// check if banned :)
	if ( $user_id != ANONYMOUS ) {
	    $ban_email = $userdata['user_email'];
	    $ban_email2 = substr( $userdata['user_email'], strpos($userdata['user_email'], '@'));

        $ban_info = dibi::select(['ban_ip', 'ban_userid', 'ban_email'])
            ->from(BANLIST_TABLE)
            ->where(
                'ban_ip IN %in OR ban_userid = %i OR ban_email LIKE %~like~ OR ban_email LIKE %~like~',
                $ban_ip_array,
                $user_id,
                $ban_email,
                $ban_email2
            )->fetch();
	} else {
        $ban_info = dibi::select(['ban_ip', 'ban_userid', 'ban_email'])
            ->from(BANLIST_TABLE)
            ->where(
                'ban_ip IN %in OR ban_userid = %i',
                $ban_ip_array,
                $user_id
            )->fetch();
    }

    if ($ban_info && ($ban_info->ban_ip || $ban_info->ban_userid || $ban_info->ban_email)) {
        message_die(CRITICAL_MESSAGE, 'You_been_banned');
    }

	//
	// Create or update the session
	//
    $update_data = [
        'session_user_id' => $user_id,
        'session_start' => $current_time,
        'session_time' => $current_time,
        'session_page' => $page_id,
        'session_logged_in' => $login,
        'session_admin' => $admin
    ];

	$result = dibi::update(SESSIONS_TABLE, $update_data)
        ->where('session_id = %s', $session_id)
        ->where('session_ip = %s', $user_ip)
        ->execute();

	if(!$result || !dibi::getAffectedRows()) {
        $session_id = md5(dss_rand());

        $insert_data = [
            'session_id' => $session_id,
            'session_user_id' => $user_id,
            'session_start' => $current_time,
            'session_time' => $current_time,
            'session_ip' => $user_ip,
            'session_page' => $page_id,
            'session_logged_in' => $login,
            'session_admin' => $admin
        ];

        dibi::insert(SESSIONS_TABLE, $insert_data)->execute();
    }

	if ( $user_id != ANONYMOUS ) {
		$last_visit = ( $userdata['user_session_time'] > 0 ) ? $userdata['user_session_time'] : $current_time; 

		if (!$admin) {
		    $update_data = [
		        'user_session_time' => $current_time,
                'user_session_page' => $page_id,
                'user_lastvisit'    => $last_visit
            ];

		    dibi::update(USERS_TABLE, $update_data)
                ->where('user_id = %i', $user_id)
                ->execute();
		}

		$userdata['user_lastvisit'] = $last_visit;

		//
		// Regenerate the auto-login key
		//
		if ($enable_autologin) {
			$auto_login_key = dss_rand() . dss_rand();
			
			if (isset($sessiondata['autologinid']) && (string) $sessiondata['autologinid'] != '') {
			    $update_data = [
			       'last_ip' =>  $user_ip,
                    'key_id' => md5($auto_login_key),
                    'last_login' => $current_time
                ];

			    dibi::update(SESSIONS_KEYS_TABLE, $update_data)
                    ->where('key_id = %s', md5($sessiondata['autologinid']))
                    ->execute();
			} else {
			    $insert_data = [
			        'key_id'     => md5($auto_login_key),
                    'user_id'    => $user_id,
                    'last_ip'    => $user_ip,
                    'last_login' => $current_time
                ];

			    dibi::insert(SESSIONS_KEYS_TABLE, $insert_data)->execute();
			}
			
			$sessiondata['autologinid'] = $auto_login_key;
			unset($auto_login_key);
		} else {
			$sessiondata['autologinid'] = '';
		}

//		$sessiondata['autologinid'] = (!$admin) ? (( $enable_autologin && $sessionmethod == SESSION_METHOD_COOKIE ) ? $auto_login_key : '') : $sessiondata['autologinid'];
		$sessiondata['userid'] = $user_id;
	}

	$userdata['session_id'] = $session_id;
	$userdata['session_ip'] = $user_ip;
	$userdata['session_user_id'] = $user_id;
	$userdata['session_logged_in'] = $login;
	$userdata['session_page'] = $page_id;
	$userdata['session_start'] = $current_time;
	$userdata['session_time'] = $current_time;
	$userdata['session_admin'] = $admin;
	$userdata['session_key'] = $sessiondata['autologinid'];

	setcookie($cookiename . '_data', serialize($sessiondata), $current_time + 31536000, $cookiepath, $cookiedomain, $cookiesecure);
	setcookie($cookiename . '_sid', $session_id, 0, $cookiepath, $cookiedomain, $cookiesecure);

	$SID = 'sid=' . $session_id;

	return $userdata;
}

//
// Checks for a given user session, tidies session table and updates user
// sessions at each page refresh
//
function session_pagestart($user_ip, $thispage_id)
{
	global $db, $lang, $board_config;
	global $SID;

	$cookiename = $board_config['cookie_name'];
	$cookiepath = $board_config['cookie_path'];
	$cookiedomain = $board_config['cookie_domain'];
	$cookiesecure = $board_config['cookie_secure'];

	$current_time = time();
	unset($userdata);

	if ( isset($_COOKIE[$cookiename . '_sid']) || isset($_COOKIE[$cookiename . '_data']) ) {
		$sessiondata = isset( $_COOKIE[$cookiename . '_data'] ) ? unserialize(stripslashes($_COOKIE[$cookiename . '_data'])) : [];
		$session_id = isset( $_COOKIE[$cookiename . '_sid'] ) ? $_COOKIE[$cookiename . '_sid'] : '';
		$sessionmethod = SESSION_METHOD_COOKIE;
	} else {
		$sessiondata = [];
		$session_id = isset($_GET['sid']) ? $_GET['sid'] : '';
		$sessionmethod = SESSION_METHOD_GET;
	}

	// 
	if (!preg_match('/^[A-Za-z0-9]*$/', $session_id)) {
		$session_id = '';
	}

	$thispage_id = (int) $thispage_id;

	//
	// Does a session exist?
	//
	if ( !empty($session_id) ) {
		//
		// session_id exists so go ahead and attempt to grab all
		// data in preparation
		//
        $userdata = dibi::select('u.*, s.*')
            ->from(SESSIONS_TABLE)
            ->as('s')
            ->from(USERS_TABLE)
            ->as('u')
            ->where('session_id = %s', $session_id)
            ->where('u.user_id = s.session_user_id')
            ->fetch();

		//
		// Did the session exist in the DB?
		//
		if ( isset($userdata['user_id']) ) {
			//
			// Do not check IP assuming equivalence, if IPv4 we'll check only first 24
			// bits ... I've been told (by vHiker) this should alleviate problems with 
			// load balanced et al proxies while retaining some reliance on IP security.
			//
			$ip_check_s = substr($userdata['session_ip'], 0, 6);
			$ip_check_u = substr($user_ip, 0, 6);

			if ($ip_check_s == $ip_check_u) {
				$SID = ($sessionmethod == SESSION_METHOD_GET || defined('IN_ADMIN')) ? 'sid=' . $session_id : '';

				//
				// Only update session DB a minute or so after last update
				//
				if ( $current_time - $userdata['session_time'] > 60 ) {
					// A little trick to reset session_admin on session re-usage

                    $update_data = [
                        'session_time' => $current_time,
                        'session_page' => $thispage_id
                    ];

					if (!defined('IN_ADMIN') && $current_time - $userdata['session_time'] > ($board_config['session_length']+60)) {
                        $update_data['session_admin'] = 0;
                    }

                    dibi::update(SESSIONS_TABLE, $update_data)
                        ->where('session_id = %s', $userdata['session_id'])
                        ->execute();

					if ( $userdata['user_id'] != ANONYMOUS ) {
					    dibi::update(USERS_TABLE, ['user_session_time' => $current_time, 'user_session_page' => $thispage_id])
                            ->where('user_id = %i', $userdata['user_id'])
                            ->execute();
					}

					session_clean($userdata['session_id']);

					setcookie($cookiename . '_data', serialize($sessiondata), $current_time + 31536000, $cookiepath, $cookiedomain, $cookiesecure);
					setcookie($cookiename . '_sid', $session_id, 0, $cookiepath, $cookiedomain, $cookiesecure);
				}

				// Add the session_key to the userdata array if it is set
				if ( isset($sessiondata['autologinid']) && $sessiondata['autologinid'] != '' ) {
					$userdata['session_key'] = $sessiondata['autologinid'];
				}

				return $userdata;
			}
		}
	}

	//
	// If we reach here then no (valid) session exists. So we'll create a new one,
	// using the cookie user_id if available to pull basic user prefs.
	//
	$user_id = isset($sessiondata['userid']) ? (int)$sessiondata['userid'] : ANONYMOUS;

	if ( !($userdata = session_begin($user_id, $user_ip, $thispage_id, TRUE)) ) {
		message_die(CRITICAL_ERROR, 'Error creating user session');
	}

	return $userdata;

}

/**
* Terminates the specified session
* It will delete the entry in the sessions table for this session,
* remove the corresponding auto-login key and reset the cookies
*/
function session_end($session_id, $user_id)
{
	global $db, $lang, $board_config, $userdata;
	global $SID;

	$cookiename = $board_config['cookie_name'];
	$cookiepath = $board_config['cookie_path'];
	$cookiedomain = $board_config['cookie_domain'];
	$cookiesecure = $board_config['cookie_secure'];

	$current_time = time();

	if (!preg_match('/^[A-Za-z0-9]*$/', $session_id)) {
		return;
	}
	
	//
	// Delete existing session
	//

    dibi::delete(SESSIONS_TABLE)
        ->where('session_id = %s', $session_id)
        ->where('session_user_id = %i', $user_id)
        ->execute();

	//
	// Remove this auto-login entry (if applicable)
	//
	if ( isset($userdata['session_key']) && $userdata['session_key'] != '' ) {
		$autologin_key = md5($userdata['session_key']);

		dibi::delete(SESSIONS_KEYS_TABLE)
            ->where('user_id = %i',(int) $user_id)
            ->where('key_id = %s', $autologin_key)
            ->execute();
	}

	//
	// We expect that message_die will be called after this function,
	// but just in case it isn't, reset $userdata to the details for a guest
	//
    $userdata = dibi::select('*')
        ->from(USERS_TABLE)
        ->where('user_id = %i', ANONYMOUS)
        ->fetch();

    if (!$userdata) {
        message_die(CRITICAL_ERROR, 'Error obtaining user details');
    }

	setcookie($cookiename . '_data', '', $current_time - 31536000, $cookiepath, $cookiedomain, $cookiesecure);
	setcookie($cookiename . '_sid', '', $current_time - 31536000, $cookiepath, $cookiedomain, $cookiesecure);

	return true;
}

/**
* Removes expired sessions and auto-login keys from the database
*/
function session_clean($session_id)
{
	global $board_config, $db;

	//
	// Delete expired sessions
	//
    dibi::delete(SESSIONS_TABLE)
        ->where('session_time < %i', time() - (int) $board_config['session_length'])
        ->where('session_id <> %s', $session_id)
        ->execute();

	//
	// Delete expired auto-login keys
	// If max_autologin_time is not set then keys will never be deleted
	// (same behaviour as old 2.0.x session code)
	//
	if (!empty($board_config['max_autologin_time']) && $board_config['max_autologin_time'] > 0) {
	    dibi::delete(SESSIONS_KEYS_TABLE)
            ->where('last_login < %i', (time() - (86400 * (int) $board_config['max_autologin_time'])))
            ->execute();
	}

	return true;
}

/**
* Reset all login keys for the specified user
* Called on password changes
*/
function session_reset_keys($user_id, $user_ip)
{
	global $userdata, $board_config;

    $key_sql = $user_id == $userdata['user_id'] && !empty($userdata['session_key']);

	$delete_session_keys = dibi::delete(SESSIONS_KEYS_TABLE)
        ->where('user_id = %i', $user_id);

	if ($key_sql) {
	    $delete_session_keys->where('key_id != %s', md5($userdata['session_key']));
    }

    $delete_session_keys->execute();

    $delete_session = dibi::delete(SESSIONS_TABLE)
        ->where('session_user_id = %i', $user_id);

    if ($user_id == $userdata['user_id']) {
        $delete_session->where('session_id <> %s', $userdata['session_id']);
    }

    $delete_session->execute();

	if ($key_sql) {
		$auto_login_key = dss_rand() . dss_rand();

		$current_time = time();

		$update_data = [
		    'last_ip' => $user_ip,
            'key_id' => md5($auto_login_key),
            'last_login' => $current_time
        ];

		dibi::update(SESSIONS_KEYS_TABLE, $update_data)
            ->where('key_id = %s', md5($userdata['session_key']))
            ->execute();

		// And now rebuild the cookie
		$sessiondata['userid'] = $user_id;
		$sessiondata['autologinid'] = $auto_login_key;
		$cookiename = $board_config['cookie_name'];
		$cookiepath = $board_config['cookie_path'];
		$cookiedomain = $board_config['cookie_domain'];
		$cookiesecure = $board_config['cookie_secure'];

		setcookie($cookiename . '_data', serialize($sessiondata), $current_time + 31536000, $cookiepath, $cookiedomain, $cookiesecure);

        $userdata['session_key'] = $auto_login_key;
		unset($sessiondata);
		unset($auto_login_key);
	}
}

//
// Append $SID to a url. Borrowed from phplib and modified. This is an
// extra routine utilised by the session code above and acts as a wrapper
// around every single URL and form action. If you replace the session
// code you must include this routine, even if it's empty.
//
function append_sid($url, $non_html_amp = false)
{
	global $SID;

	if ( !empty($SID) && !preg_match('#sid=#', $url) ) {
		$url .= ( ( strpos($url, '?') !== false ) ?  ( $non_html_amp ? '&' : '&amp;' ) : '?' ) . $SID;
	}

	return $url;
}

?>