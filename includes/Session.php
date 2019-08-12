<?php

/**
 * Class Session
 *
 * replacement of sessions.php file
 *
 * @author rendix2
 */
class Session
{
    /**
     * Adds/updates a new session to the database for the given userid.
     * Returns the new session ID on success.
     *
     * @param int    $userId
     * @param string $userIp
     * @param int    $pageId
     * @param bool   $autoCreate
     * @param bool   $enableAutoLogin
     * @param bool   $isAdmin
     *
     * @return array
     */
    public static function begin(
        $userId,
        $userIp,
        $pageId,
        $autoCreate = false,
        $enableAutoLogin = false,
        $isAdmin = false
    ) {
        global $board_config;
        global $SID;

        $cookieName = $board_config['cookie_name'];
        $cookiePath = $board_config['cookie_path'];
        $cookieDomain = $board_config['cookie_domain'];
        $cookieSecure = $board_config['cookie_secure'];

        $dataCookieName = $cookieName . '_data';
        $sidCookieName  = $cookieName . '_sid';

        if (isset($_COOKIE[$sidCookieName]) || isset($_COOKIE[$dataCookieName])) {
            $sessionId  = isset($_COOKIE[$sidCookieName])  ? $_COOKIE[$sidCookieName] : '';
            $sessionData = isset($_COOKIE[$dataCookieName]) ? unserialize(stripslashes($_COOKIE[$dataCookieName])) : [];
            $sessionMethod = SESSION_METHOD_COOKIE;
        } else {
            $sessionData = [];
            $sessionId = isset($_GET['sid']) ? $_GET['sid'] : '';
            $sessionMethod = SESSION_METHOD_GET;
        }

        //
        if (!preg_match('/^[A-Za-z0-9]*$/', $sessionId))  {
            $sessionId = '';
        }

        $pageId = (int) $pageId;

        $lastVisit = 0;
        $currentTime = time();

        //
        // Are auto-logins allowed?
        // If allow_autologin is not set or is true then they are
        // (same behaviour as old 2.0.x session code)
        //
        if (isset($board_config['allow_autologin']) && !$board_config['allow_autologin']) {
            $enableAutoLogin = $sessionData['autologinid'] = false;
        }

        //
        // First off attempt to join with the autologin value if we have one
        // If not, just use the user_id value
        //
        $userData = [];

        if ($userId !== ANONYMOUS) {
            if (isset($sessionData['autologinid']) && (string) $sessionData['autologinid'] !== '' && $userId) {
                $userData = dibi::select('u.*')
                    ->from(USERS_TABLE)
                    ->as('u')
                    ->innerJoin(SESSIONS_KEYS_TABLE)
                    ->as('k')
                    ->on('k.user_id = u.user_id')
                    ->where('u.user_id = %i', (int) $userId)
                    ->where('u.user_active = %i', 1)
                    ->where('k.key_id = %s', hash('sha512',$sessionData['autologinid']))
                    ->fetch();

                $userData = $userData->toArray();

                $enableAutoLogin = $login = true;
            } elseif (!$autoCreate) {
                $sessionData['autologinid'] = '';
                $sessionData['userid'] = $userId;

                $userData = dibi::select('*')
                    ->from(USERS_TABLE)
                    ->where('user_id = %i', (int) $userId)
                    ->where('user_active = %i', 1)
                    ->fetch();

                if (!$userData) {
                    message_die(CRITICAL_ERROR, 'Error doing DB query userdata row fetch');
                }

                // we need it as array, no object.. :(
                $userData = $userData->toArray();

                $login = true;
            }
        }

        //
        // At this point either $userdata should be populated or
        // one of the below is true
        // * Key didn't match one in the DB
        // * User does not exist
        // * User is inactive
        //
        if (!count($userData) || !is_array($userData) || !$userData) {
            $sessionData['autologinid'] = '';
            $sessionData['userid']      = $userId = ANONYMOUS;
            $enableAutoLogin            = $login = false;

            $userData = dibi::select('*')
                ->from(USERS_TABLE)
                ->where('user_id = %i', $userId)
                ->fetch();

            if (!$userData) {
                message_die(CRITICAL_ERROR, 'Error doing DB query userdata row fetch');
            }
        }

        //
        // Initial ban check against user id, IP and email address
        //
        preg_match('/(..)(..)(..)(..)/', $userIp, $user_ip_parts);

        $ban_ip_array = [
            $user_ip_parts[1] . $user_ip_parts[2] . $user_ip_parts[3] . $user_ip_parts[4],
            $user_ip_parts[1] . $user_ip_parts[2] . $user_ip_parts[3] . 'ff',
            $user_ip_parts[1] . $user_ip_parts[2] . 'ffff',
            $user_ip_parts[1] . 'ffffff'
        ];

        // check if banned :)
        if ($userId !== ANONYMOUS) {
            $ban_email = $userData['user_email'];
            $ban_email2 = substr( $userData['user_email'], strpos($userData['user_email'], '@'));

            $ban_info = dibi::select(['ban_ip', 'ban_userid', 'ban_email'])
                ->from(BANLIST_TABLE)
                ->where(
                    'ban_ip IN %in OR ban_userid = %i OR ban_email LIKE %~like~ OR ban_email LIKE %~like~',
                    $ban_ip_array,
                    $userId,
                    $ban_email,
                    $ban_email2
                )->fetch();
        } else {
            $ban_info = dibi::select(['ban_ip', 'ban_userid', 'ban_email'])
                ->from(BANLIST_TABLE)
                ->where(
                    'ban_ip IN %in OR ban_userid = %i',
                    $ban_ip_array,
                    $userId
                )->fetch();
        }

        if ($ban_info && ($ban_info->ban_ip || $ban_info->ban_userid || $ban_info->ban_email)) {
            message_die(CRITICAL_MESSAGE, 'You_been_banned');
        }

        //
        // Create or update the session
        //
        $updateData = [
            'session_user_id' => $userId,
            'session_start' => $currentTime,
            'session_time' => $currentTime,
            'session_page' => $pageId,
            'session_logged_in' => $login,
            'session_admin' => $isAdmin
        ];

        $result = dibi::update(SESSIONS_TABLE, $updateData)
            ->where('session_id = %s', $sessionId)
            ->where('session_ip = %s', $userIp)
            ->execute();

        if (!$result || !dibi::getAffectedRows()) {
            $sessionId = md5(dss_rand());

            $insertData = [
                'session_id' => $sessionId,
                'session_user_id' => $userId,
                'session_start' => $currentTime,
                'session_time' => $currentTime,
                'session_ip' => $userIp,
                'session_page' => $pageId,
                'session_logged_in' => $login,
                'session_admin' => $isAdmin
            ];

            dibi::insert(SESSIONS_TABLE, $insertData)->execute();
        }

        if ($userId !== ANONYMOUS) {
            $lastVisit = ( $userData['user_session_time'] > 0 ) ? $userData['user_session_time'] : $currentTime;

            if (!$isAdmin) {
                $updateData = [
                    'user_session_time' => $currentTime,
                    'user_session_page' => $pageId,
                    'user_lastvisit'    => $lastVisit
                ];

                dibi::update(USERS_TABLE, $updateData)
                    ->where('user_id = %i', $userId)
                    ->execute();
            }

            $userData['user_lastvisit'] = $lastVisit;

            //
            // Regenerate the auto-login key
            //
            if ($enableAutoLogin) {
                $auto_login_key = dss_rand() . dss_rand();

                if (isset($sessionData['autologinid']) && (string) $sessionData['autologinid'] !== '') {
                    $updateData = [
                        'last_ip' =>  $userIp,
                        'key_id' => hash('sha512',$auto_login_key),
                        'last_login' => $currentTime
                    ];

                    dibi::update(SESSIONS_KEYS_TABLE, $updateData)
                        ->where('key_id = %s', hash('sha512', $sessionData['autologinid']))
                        ->execute();
                } else {
                    $insertData = [
                        'key_id'     => hash('sha512', $auto_login_key),
                        'user_id'    => $userId,
                        'last_ip'    => $userIp,
                        'last_login' => $currentTime
                    ];

                    dibi::insert(SESSIONS_KEYS_TABLE, $insertData)->execute();
                }

                $sessionData['autologinid'] = $auto_login_key;
                unset($auto_login_key);
            } else {
                $sessionData['autologinid'] = '';
            }

            //		$sessiondata['autologinid'] = (!$admin) ? (( $enable_autologin && $sessionmethod == SESSION_METHOD_COOKIE ) ? $auto_login_key : '') : $sessiondata['autologinid'];
            $sessionData['userid'] = $userId;
        }

        $userData['session_id'] = $sessionId;
        $userData['session_ip'] = $userIp;
        $userData['session_user_id'] = $userId;
        $userData['session_logged_in'] = $login;
        $userData['session_page'] = $pageId;
        $userData['session_start'] = $currentTime;
        $userData['session_time'] = $currentTime;
        $userData['session_admin'] = $isAdmin;
        $userData['session_key'] = $sessionData['autologinid'];

        $userTimezone = isset($userData['user_timezone']) ? $userData['user_timezone'] : $board_config['board_timezone'];

        $expireDate = new DateTime();
        $expireDate->setTimestamp($currentTime);
        $expireDate->setTimezone(new DateTimeZone($userTimezone));
        $expireDate->add(new DateInterval('P1Y'));

        setcookie($dataCookieName, serialize($sessionData), $expireDate->getTimestamp(), $cookiePath, $cookieDomain, $cookieSecure);
        setcookie($sidCookieName, $sessionId, 0, $cookiePath, $cookieDomain, $cookieSecure);

        $SID = 'sid=' . $sessionId;

        return $userData;
    }

    /**
     * Checks for a given user session, tidies session table and updates user
     * sessions at each page refresh
     *
     * @param $user_ip
     * @param $thisPageId
     *
     * @return array
     */
    public static function pageStart($user_ip, $thisPageId)
    {
        global $lang, $board_config;
        global $SID;

        $cookieName = $board_config['cookie_name'];
        $cookiePath = $board_config['cookie_path'];
        $cookieDomain = $board_config['cookie_domain'];
        $cookieSecure = $board_config['cookie_secure'];

        $dataCookieName = $cookieName . '_data';
        $sidCookieName  = $cookieName . '_sid';

        $current_time = time();
        unset($userData);

        if (isset($_COOKIE[$sidCookieName]) || isset($_COOKIE[$dataCookieName])) {
            $sessionData = isset($_COOKIE[$dataCookieName]) ? unserialize(stripslashes($_COOKIE[$dataCookieName])) : [];
            $sessionId = isset($_COOKIE[$sidCookieName]) ? $_COOKIE[$sidCookieName] : '';
            $sessionMethod = SESSION_METHOD_COOKIE;
        } else {
            $sessionData = [];
            $sessionId = isset($_GET['sid']) ? $_GET['sid'] : '';
            $sessionMethod = SESSION_METHOD_GET;
        }

        //
        if (!preg_match('/^[A-Za-z0-9]*$/', $sessionId)) {
            $sessionId = '';
        }

        $thisPageId = (int) $thisPageId;

        //
        // Does a session exist?
        //
        if (!empty($sessionId)) {
            //
            // session_id exists so go ahead and attempt to grab all
            // data in preparation
            //
            $userData = dibi::select('u.*, s.*')
                ->from(SESSIONS_TABLE)
                ->as('s')
                ->innerJoin(USERS_TABLE)
                ->as('u')
                ->on('u.user_id = s.session_user_id')
                ->where('session_id = %s', $sessionId)
                ->fetch();

            //
            // Did the session exist in the DB?
            //
            if (isset($userData['user_id'])) {
                //
                // Do not check IP assuming equivalence, if IPv4 we'll check only first 24
                // bits ... I've been told (by vHiker) this should alleviate problems with
                // load balanced et al proxies while retaining some reliance on IP security.
                //
                $ip_check_s = substr($userData['session_ip'], 0, 6);
                $ip_check_u = substr($user_ip, 0, 6);

                if ($ip_check_s === $ip_check_u) {
                    $SID = $sessionMethod === SESSION_METHOD_GET || defined('IN_ADMIN') ? 'sid=' . $sessionId : '';

                    //
                    // Only update session DB a minute or so after last update
                    //
                    if ($current_time - $userData['session_time'] > 60) {
                        // A little trick to reset session_admin on session re-usage

                        $update_data = [
                            'session_time' => $current_time,
                            'session_page' => $thisPageId
                        ];

                        if (!defined('IN_ADMIN') && $current_time - $userData['session_time'] > ($board_config['session_length']+60)) {
                            $update_data['session_admin'] = 0;
                        }

                        dibi::update(SESSIONS_TABLE, $update_data)
                            ->where('session_id = %s', $userData['session_id'])
                            ->execute();

                        if ($userData['user_id'] !== ANONYMOUS) {
                            dibi::update(USERS_TABLE, ['user_session_time' => $current_time, 'user_session_page' => $thisPageId])
                                ->where('user_id = %i', $userData['user_id'])
                                ->execute();
                        }

                        self::clean($userData['session_id']);

                        $user_timezone = isset($userData['user_timezone']) ? $userData['user_timezone'] : $board_config['board_timezone'];

                        $expire_date = new DateTime();
                        $expire_date->setTimestamp($current_time);
                        $expire_date->setTimezone(new DateTimeZone($user_timezone));
                        $expire_date->add(new DateInterval('P1Y'));

                        setcookie($dataCookieName, serialize($sessionData), $expire_date->getTimestamp(), $cookiePath, $cookieDomain, $cookieSecure);
                        setcookie($sidCookieName, $sessionId, 0, $cookiePath, $cookieDomain, $cookieSecure);
                    }

                    // Add the session_key to the userdata array if it is set
                    if (isset($sessionData['autologinid']) && $sessionData['autologinid'] !== '') {
                        $userData['session_key'] = $sessionData['autologinid'];
                    }

                    return $userData;
                }
            }
        }

        //
        // If we reach here then no (valid) session exists. So we'll create a new one,
        // using the cookie user_id if available to pull basic user prefs.
        //
        $user_id  = isset($sessionData['userid']) ? (int)$sessionData['userid'] : ANONYMOUS;
        $userData = self::begin($user_id, $user_ip, $thisPageId, true);

        if (!$userData) {
            message_die(CRITICAL_ERROR, 'Error creating user session');
        }

        return $userData;
    }

    /**
     * Terminates the specified session
     * It will delete the entry in the sessions table for this session,
     * remove the corresponding auto-login key and reset the cookies
     *
     * @param string $sessionId
     * @param int    $userId
     *
     * @return bool|void
     * @throws \Dibi\Exception
     */
    public static function end($sessionId, $userId)
    {
        global $board_config, $userdata;

        $cookieName = $board_config['cookie_name'];
        $cookiePath = $board_config['cookie_path'];
        $cookieDomain = $board_config['cookie_domain'];
        $cookiesecure = $board_config['cookie_secure'];

        $dataCookieName = $cookieName . '_data';
        $sidCookieName  = $cookieName . '_sid';

        $currentTime = time();

        if (!preg_match('/^[A-Za-z0-9]*$/', $sessionId)) {
            return;
        }

        //
        // Delete existing session
        //

        dibi::delete(SESSIONS_TABLE)
            ->where('session_id = %s', $sessionId)
            ->where('session_user_id = %i', $userId)
            ->execute();

        //
        // Remove this auto-login entry (if applicable)
        //
        if (isset($userdata['session_key']) && $userdata['session_key'] !== '') {
            $autoLoginKey = hash('sha512', $userdata['session_key']);

            dibi::delete(SESSIONS_KEYS_TABLE)
                ->where('user_id = %i',(int) $userId)
                ->where('key_id = %s', $autoLoginKey)
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

        $userTimezone = isset($userdata['user_timezone']) ? $userdata['user_timezone'] : $board_config['board_timezone'];

        $expireDate = new DateTime();
        $expireDate->setTimestamp($currentTime);
        $expireDate->setTimezone(new DateTimeZone($userTimezone));
        $expireDate->sub(new DateInterval('P1Y'));

        setcookie($dataCookieName, '', $expireDate->getTimestamp(), $cookiePath, $cookieDomain, $cookiesecure);
        setcookie($sidCookieName, '', $expireDate->getTimestamp(), $cookiePath, $cookieDomain, $cookiesecure);

        return true;
    }

    /**
     * Removes expired sessions and auto-login keys from the database
     *
     * @param string $session_id
     *
     * @return bool
     * @throws \Dibi\Exception
     */
    public static function clean($session_id)
    {
        global $board_config;
        global $userdata;

        //
        // Delete expired sessions
        //
        $userTimezone = isset($userdata['user_timezone']) ? $userdata['user_timezone'] : $board_config['board_timezone'];

        $time = new DateTime();
        $time->setTimezone(new DateTimeZone($userTimezone));
        $time->sub(new DateInterval('PT' . $board_config['session_length'] . 'S'));

        dibi::delete(SESSIONS_TABLE)
            ->where('session_time < %i', $time->getTimestamp())
            ->where('session_id <> %s', $session_id)
            ->execute();

        //
        // Delete expired auto-login keys
        // If max_autologin_time is not set then keys will never be deleted
        // (same behaviour as old 2.0.x session code)
        //
        if (!empty($board_config['max_autologin_time']) && $board_config['max_autologin_time'] > 0) {
            $userTimezone = isset($userdata['user_timezone']) ? $userdata['user_timezone'] : $board_config['board_timezone'];

            $time = new DateTime();
            $time->setTimezone(new DateTimeZone($userTimezone));
            $time->sub(new DateInterval('P' . (int)$board_config['max_autologin_time'] . 'D'));

            dibi::delete(SESSIONS_KEYS_TABLE)
                ->where('last_login < %i', $time->getTimestamp())
                ->execute();
        }

        return true;
    }

    /**
     * Reset all login keys for the specified user
     * Called on password changes
     *
     * @param int    $userId
     * @param string $userIp
     *
     * @throws \Dibi\Exception
     */
    public static function resetKeys($userId, $userIp)
    {
        global $userdata, $board_config;

        $key_sql = $userId === $userdata['user_id'] && !empty($userdata['session_key']);

        $deleteSessionKeys = dibi::delete(SESSIONS_KEYS_TABLE)
            ->where('user_id = %i', $userId);

        if ($key_sql) {
            $deleteSessionKeys->where('key_id != %s', hash('sha512', $userdata['session_key']));
        }

        $deleteSessionKeys->execute();

        $deleteSession = dibi::delete(SESSIONS_TABLE)
            ->where('session_user_id = %i', $userId);

        if ($userId === $userdata['user_id']) {
            $deleteSession->where('session_id <> %s', $userdata['session_id']);
        }

        $deleteSession->execute();

        if ($key_sql) {
            $autoLoginKey = dss_rand() . dss_rand();
            $currentTime = time();

            $update_data = [
                'last_ip' => $userIp,
                'key_id' => hash('sha512', $autoLoginKey),
                'last_login' => $currentTime
            ];

            dibi::update(SESSIONS_KEYS_TABLE, $update_data)
                ->where('key_id = %s', hash('sha512', $userdata['session_key']))
                ->execute();

            // And now rebuild the cookie
            $sessionData['userid'] = $userId;
            $sessionData['autologinid'] = $autoLoginKey;
            $cookieName = $board_config['cookie_name'];
            $cookiePath = $board_config['cookie_path'];
            $cookieDomain = $board_config['cookie_domain'];
            $cookieSecure = $board_config['cookie_secure'];

            $userTimezone = isset($userdata['user_timezone']) ? $userdata['user_timezone'] : $board_config['board_timezone'];

            $expireDate = new DateTime();
            $expireDate->setTimestamp($currentTime);
            $expireDate->setTimezone(new DateTimeZone($userTimezone));
            $expireDate->add(new DateInterval('P1Y'));

            setcookie($cookieName . '_data', serialize($sessionData), $expireDate->getTimestamp(), $cookiePath, $cookieDomain, $cookieSecure);

            $userdata['session_key'] = $autoLoginKey;
            unset($sessionData);
            unset($autoLoginKey);
        }
    }

    /**
     * Append $SID to a url. Borrowed from phplib and modified. This is an
     * extra routine utilised by the session code above and acts as a wrapper
     * around every single URL and form action. If you replace the session
     * code you must include this routine, even if it's empty.
     *
     * @param      $url
     * @param bool $non_html_amp
     *
     * @return string
     */
    public static function appendSid($url, $non_html_amp = false)
    {
        global $SID;

        if (!empty($SID) && !preg_match('#sid=#', $url)) {
            $url .= ( ( strpos($url, '?') !== false ) ?  ( $non_html_amp ? '&' : '&amp;' ) : '?' ) . $SID;
        }

        return $url;
    }

}
