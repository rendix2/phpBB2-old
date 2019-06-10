<?php

/**
 * Class Auth
 *
 * @author Tomáš Babický tomas.babicky@websta.de
 */
class Auth
{
    /**
     *
     *  $type's accepted (pre-pend with AUTH_):
     *  VIEW, READ, POST, REPLY, EDIT, DELETE, STICKY, ANNOUNCE, VOTE, POLLCREATE
     *
     *  Possible options ($type/forum_id combinations):
     *
     *  If you include a type and forum_id then a specific lookup will be done and
     *  the single result returned
     *
     *  If you set type to AUTH_ALL and specify a forum_id an array of all auth types
     *  will be returned
     *
     *  If you provide a forum_id a specific lookup on that forum will be done
     *
     *  If you set forum_id to AUTH_LIST_ALL and specify a type an array listing the
     *  results for all forums will be returned
     *
     *  If you set forum_id to AUTH_LIST_ALL and type to AUTH_ALL a multidimensional
     *  array containing the auth permissions for all types and all forums for that
     *  user is returned
     *
     *  All results are returned as associative arrays, even when a single auth type is
     *  specified.
     *
     *  If available you can send an array (either one or two dimensional) containing the
     *  forum auth levels, this will prevent the auth function having to do its own
     *  lookup
     *
     * @param int    $type
     * @param int    $forum_id
     * @param array  $userdata
     * @param string $f_access
     *
     * @return array
     */
    public static function authorize($type, $forum_id, $userdata, $f_access = '')
    {
        global $lang;

        $a_sql = [];
        $auth_fields = [];

        switch( $type )
        {
            case AUTH_ALL:
                $a_sql = ['a.auth_view', 'a.auth_read', 'a.auth_post', 'a.auth_reply', 'a.auth_edit', 'a.auth_delete', 'a.auth_sticky', 'a.auth_announce', 'a.auth_vote', 'a.auth_pollcreate'];
                $auth_fields = ['auth_view', 'auth_read', 'auth_post', 'auth_reply', 'auth_edit', 'auth_delete', 'auth_sticky', 'auth_announce', 'auth_vote', 'auth_pollcreate'];
                break;

            case AUTH_VIEW:
                $a_sql = ['a.auth_view'];
                $auth_fields = ['auth_view'];
                break;

            case AUTH_READ:
                $a_sql = ['a.auth_read'];
                $auth_fields = ['auth_read'];
                break;

            case AUTH_POST:
                $a_sql = ['a.auth_post'];
                $auth_fields = ['auth_post'];
                break;

            case AUTH_REPLY:
                $a_sql = ['a.auth_reply'];
                $auth_fields = ['auth_reply'];
                break;

            case AUTH_EDIT:
                $a_sql = ['a.auth_edit'];
                $auth_fields = ['auth_edit'];
                break;

            case AUTH_DELETE:
                $a_sql = ['a.auth_delete'];
                $auth_fields = ['auth_delete'];
                break;

            case AUTH_ANNOUNCE:
                $a_sql = ['a.auth_announce'];
                $auth_fields = ['auth_announce'];
                break;
            case AUTH_STICKY:
                $a_sql = ['a.auth_sticky'];
                $auth_fields = ['auth_sticky'];
                break;

            case AUTH_POLLCREATE:
                $a_sql = ['a.auth_pollcreate'];
                $auth_fields = ['auth_pollcreate'];
                break;

            case AUTH_VOTE:
                $a_sql = ['a.auth_vote'];
                $auth_fields = ['auth_vote'];
                break;

            case AUTH_ATTACH:
                break;

            default:
                break;
        }

        //
        // If f_access has been passed, or auth is needed to return an array of forums
        // then we need to pull the auth information on the given forum (or all forums)
        //
        if (empty($f_access)) {
            if ($forum_id !== AUTH_LIST_ALL) {
                $f_access = dibi::select('a.forum_id')
                    ->select($a_sql)
                    ->from(FORUMS_TABLE)
                    ->as('a')
                    ->where('a.forum_id = %i', $forum_id)
                    ->fetch();
            } else {
                $f_access = dibi::select('a.forum_id')
                    ->select($a_sql)
                    ->from(FORUMS_TABLE)
                    ->as('a')
                    ->fetchAll();
            }

            if ($f_access === false || !count($f_access)) {
                return [];
            }
        }

        //
        // If the user isn't logged on then all we need do is check if the forum
        // has the type set to ALL, if yes they are good to go, if not then they
        // are denied access
        //
        $u_access = [];
        if ($userdata['session_logged_in']) {
            $rows = dibi::select('a.forum_id')
                ->select($a_sql)
                ->select('a.auth_mod ')
                ->from(AUTH_ACCESS_TABLE)
                ->as('a')
                ->innerJoin(USER_GROUP_TABLE)
                ->as('ug')
                ->on('a.group_id = ug.group_id')
                ->where('ug.user_id = %i', $userdata['user_id'])
                ->where('ug.user_pending = %i', 0);

            if ($forum_id !== AUTH_LIST_ALL) {
                $rows->where('a.forum_id = %i', $forum_id);
            }

            $rows = $rows->fetchAll();

            foreach ($rows as $row) {
                if ($forum_id !== AUTH_LIST_ALL) {
                    $u_access[] = $row;
                } else {
                    $u_access[$row->forum_id][] = $row;
                }
            }
        }

        $is_admin = ( $userdata['user_level'] === ADMIN && $userdata['session_logged_in'] ) ? true : 0;

        $auth_user = [];

        foreach ($auth_fields as $auth_field) {
            $key = $auth_field;

            //
            // If the user is logged on and the forum type is either ALL or REG then the user has access
            //
            // If the type if ACL, MOD or ADMIN then we need to see if the user has specific permissions
            // to do whatever it is they want to do ... to do this we pull relevant information for the
            // user (and any groups they belong to)
            //
            // Now we compare the users access level against the forums. We assume here that a moderator
            // and admin automatically have access to an ACL forum, similarly we assume admins meet an
            // auth requirement of MOD
            //
            if ($forum_id !== AUTH_LIST_ALL) {
                $value = $f_access[$key];

                switch( $value) {
                    case AUTH_ALL:
                        $auth_user[$key] = true;
                        $auth_user[$key . '_type'] = $lang['Auth_Anonymous_Users'];
                        break;

                    case AUTH_REG:
                        $auth_user[$key] = $userdata['session_logged_in'] ? true : 0;
                        $auth_user[$key . '_type'] = $lang['Auth_Registered_Users'];
                        break;

                    case AUTH_ACL:
                        $auth_user[$key] = $userdata['session_logged_in'] ? self::auth_check_user(AUTH_ACL, $key,
                            $u_access, $is_admin) : 0;
                        $auth_user[$key . '_type'] = $lang['Auth_Users_granted_access'];
                        break;

                    case AUTH_MOD:
                        $auth_user[$key] = $userdata['session_logged_in'] ? self::auth_check_user(AUTH_MOD, 'auth_mod', $u_access, $is_admin) : 0;
                        $auth_user[$key . '_type'] = $lang['Auth_Moderators'];
                        break;

                    case AUTH_ADMIN:
                        $auth_user[$key] = $is_admin;
                        $auth_user[$key . '_type'] = $lang['Auth_Administrators'];
                        break;

                    default:
                        $auth_user[$key] = 0;
                        break;
                }
            } else {
                foreach ($f_access as $f_access_value) {
                    $value = $f_access_value[$key];
                    $f_forum_id = $f_access_value['forum_id'];
                    $u_access[$f_forum_id] = isset($u_access[$f_forum_id]) ? $u_access[$f_forum_id] : [];

                    switch ($value) {
                        case AUTH_ALL:
                            $auth_user[$f_forum_id][$key] = true;
                            $auth_user[$f_forum_id][$key . '_type'] = $lang['Auth_Anonymous_Users'];
                            break;

                        case AUTH_REG:
                            $auth_user[$f_forum_id][$key] = $userdata['session_logged_in'] ? true : 0;
                            $auth_user[$f_forum_id][$key . '_type'] = $lang['Auth_Registered_Users'];
                            break;

                        case AUTH_ACL:
                            $auth_user[$f_forum_id][$key] = $userdata['session_logged_in'] ? self::auth_check_user(AUTH_ACL, $key, $u_access[$f_forum_id], $is_admin) : 0;
                            $auth_user[$f_forum_id][$key . '_type'] = $lang['Auth_Users_granted_access'];
                            break;

                        case AUTH_MOD:
                            $auth_user[$f_forum_id][$key] = $userdata['session_logged_in'] ? self::auth_check_user(AUTH_MOD, 'auth_mod', $u_access[$f_forum_id], $is_admin) : 0;
                            $auth_user[$f_forum_id][$key . '_type'] = $lang['Auth_Moderators'];
                            break;

                        case AUTH_ADMIN:
                            $auth_user[$f_forum_id][$key] = $is_admin;
                            $auth_user[$f_forum_id][$key . '_type'] = $lang['Auth_Administrators'];
                            break;

                        default:
                            $auth_user[$f_forum_id][$key] = 0;
                            break;
                    }
                }
            }
        }

        //
        // Is user a moderator?
        //
        if ($forum_id !== AUTH_LIST_ALL) {
            $auth_user['auth_mod'] = $userdata['session_logged_in'] ? self::auth_check_user(AUTH_MOD, 'auth_mod', $u_access, $is_admin) : 0;
        } else {
            foreach ($f_access as $f_access_value) {
                $f_forum_id = $f_access_value['forum_id'];
                $u_access[$f_forum_id] = isset($u_access[$f_forum_id]) ? $u_access[$f_forum_id] : [];

                $auth_user[$f_forum_id]['auth_mod'] = $userdata['session_logged_in'] ? self::auth_check_user(AUTH_MOD, 'auth_mod', $u_access[$f_forum_id], $is_admin) : 0;
            }
        }

        return $auth_user;
    }

    /**
     * @param int $type
     * @param string $key
     * @param array $u_access
     * @param bool $is_admin
     *
     * @return bool|int
     */
    public static function auth_check_user($type, $key, $u_access, $is_admin)
    {
        $auth_user = 0;

        if (count($u_access)) {
            foreach ($u_access as $u_access_value) {
                $result = 0;

                if ($type === AUTH_ACL) {
                    $result = $u_access_value[$key];
                }

                if ($type === AUTH_MOD) {
                    $result = $result || $u_access_value['auth_mod'];
                }

                if ($type === AUTH_ADMIN) {
                    $result = $result || $is_admin;

                    // TODO CHECK THIS if its needed
                    break;
                }

                $auth_user = $auth_user || $result;
            }
        } else {
            $auth_user = $is_admin;
        }

        return $auth_user;
    }
}