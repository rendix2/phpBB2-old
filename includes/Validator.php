<?php

use Dibi\Row;

/**
 * Class Validate
 *
 * @author rendix2
 */
class Validator
{
    /**
     * Check to see if the username has been taken, or if it is disallowed.
     * Also checks if it includes the " character, which we don't allow in usernames.
     * Used for registering, changing names, and posting anonymously with a username
     *
     * @param string    $userName
     * @param array     $lang
     * @param array|Row $userdata
     *
     * @return array
     */
    public static function userName($userName, array $lang, $userdata)
    {
        // Remove doubled up spaces
        $username = preg_replace('#\s+#', ' ', trim($userName));
        $username = phpbb_clean_username($username);

        $lowerUserName = mb_strtolower($username);

        $dbUserNme = dibi::select('username')
            ->from(USERS_TABLE)
            ->where('LOWER(username) = %s', $lowerUserName)
            ->fetch();

        if ($dbUserNme) {
            if (($userdata['session_logged_in'] && $dbUserNme->username !== $userdata['username']) || !$userdata['session_logged_in']) {

                return ['error' => true, 'error_msg' => $lang['Username_taken']];
            }
        }

        $db_group_name = dibi::select('group_name')
            ->from(GROUPS_TABLE)
            ->where('LOWER(group_name) = %s', $lowerUserName)
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

    /**
     * Check to see if email address is banned
     * or already present in the DB
     *
     * @param string $email
     * @param array  $lang
     *
     * @return array
     */
    public static function email($email, array $lang)
    {
        if ($email !== '' && preg_match('/^[a-z0-9&\'\.\-_\+]+@[a-z0-9\-]+\.([a-z0-9\-]+\.)*?[a-z]+$/is', $email)) {
            $bans = dibi::select('ban_email')
                ->from(BANLIST_TABLE)
                ->fetchAll();

            foreach ($bans as $ban) {
                $match_email = str_replace('*', '.*?', $ban->ban_email);

                if (preg_match('/^' . $match_email . '$/is', $email)) {
                    return ['error' => true, 'error_msg' => $lang['Email_banned']];
                }
            }

            $dbEmail = dibi::select('user_email')
                ->from(USERS_TABLE)
                ->where('user_email = %s', $email)
                ->fetch();

            if ($dbEmail) {
                return ['error' => true, 'error_msg' => $lang['Email_taken']];
            }

            return ['error' => false, 'error_msg' => ''];
        }

        return ['error' => true, 'error_msg' => $lang['Email_invalid']];
    }

    /**
     *
     * Does supplementary validation of optional profile fields. This expects common stuff like trim() and strip_tags()
     * to have already been run. Params are passed by-ref, so we can set them to the empty string if they fail.
     *
     * @param string $website
     * @param string $location
     * @param string $occupation
     * @param string $interests
     * @param string $sig
     */
    public static function optionalFields(
        &$website,
        &$location,
        &$occupation,
        &$interests,
        &$sig
    ) {
        if (mb_strlen($location) < 2) {
            $location = '';
        }

        if (mb_strlen($occupation) < 2) {
            $occupation = '';
        }

        if (mb_strlen($interests) < 2) {
            $interests = '';
        }

        if (mb_strlen($sig) < 2) {
            $sig = '';
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
}
