<?php

/**
 * Class Auth
 *
 * @author rendix2
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
     * @param int    $forumId
     * @param array  $userdata
     * @param string $f_access
     *
     * @return array
     */
    public static function authorize($type, $forumId, $userdata, $f_access = '')
    {
        global $lang;

        $authSql      = [];
        $authFields = [];

        switch ($type) {
            case AUTH_ALL:
                $authSql      = ['a.auth_view', 'a.auth_read', 'a.auth_post', 'a.auth_reply', 'a.auth_edit', 'a.auth_delete', 'a.auth_sticky', 'a.auth_announce', 'a.auth_vote', 'a.auth_pollcreate'];
                $authFields = ['auth_view', 'auth_read', 'auth_post', 'auth_reply', 'auth_edit', 'auth_delete', 'auth_sticky', 'auth_announce', 'auth_vote', 'auth_pollcreate'];
                break;

            case AUTH_VIEW:
                $authSql      = ['a.auth_view'];
                $authFields = ['auth_view'];
                break;

            case AUTH_READ:
                $authSql      = ['a.auth_read'];
                $authFields = ['auth_read'];
                break;

            case AUTH_POST:
                $authSql      = ['a.auth_post'];
                $authFields = ['auth_post'];
                break;

            case AUTH_REPLY:
                $authSql      = ['a.auth_reply'];
                $authFields = ['auth_reply'];
                break;

            case AUTH_EDIT:
                $authSql      = ['a.auth_edit'];
                $authFields = ['auth_edit'];
                break;

            case AUTH_DELETE:
                $authSql      = ['a.auth_delete'];
                $authFields = ['auth_delete'];
                break;

            case AUTH_ANNOUNCE:
                $authSql      = ['a.auth_announce'];
                $authFields = ['auth_announce'];
                break;
            case AUTH_STICKY:
                $authSql      = ['a.auth_sticky'];
                $authFields = ['auth_sticky'];
                break;

            case AUTH_POLLCREATE:
                $authSql      = ['a.auth_pollcreate'];
                $authFields = ['auth_pollcreate'];
                break;

            case AUTH_VOTE:
                $authSql      = ['a.auth_vote'];
                $authFields = ['auth_vote'];
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
            if ($forumId !== AUTH_LIST_ALL) {
                $f_access = dibi::select('a.forum_id')
                    ->select($authSql)
                    ->from(Tables::FORUMS_TABLE)
                    ->as('a')
                    ->where('a.forum_id = %i', $forumId)
                    ->fetch();
            } else {
                $f_access = dibi::select('a.forum_id')
                    ->select($authSql)
                    ->from(Tables::FORUMS_TABLE)
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
                ->select($authSql)
                ->select('a.auth_mod ')
                ->from(Tables::AUTH_ACCESS_TABLE)
                ->as('a')
                ->innerJoin(Tables::USERS_GROUPS_TABLE)
                ->as('ug')
                ->on('a.group_id = ug.group_id')
                ->where('ug.user_id = %i', $userdata['user_id'])
                ->where('ug.user_pending = %i', 0);

            if ($forumId !== AUTH_LIST_ALL) {
                $rows->where('a.forum_id = %i', $forumId);
            }

            $rows = $rows->fetchAll();

            foreach ($rows as $row) {
                if ($forumId !== AUTH_LIST_ALL) {
                    $u_access[] = $row;
                } else {
                    $u_access[$row->forum_id][] = $row;
                }
            }
        }

        $isAdmin = $userdata['user_level'] === ADMIN && $userdata['session_logged_in'] ? true : 0;

        $authUser = [];

        foreach ($authFields as $auth_field) {
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
            if ($forumId !== AUTH_LIST_ALL) {
                $value = $f_access[$key];

                switch( $value) {
                    case AUTH_ALL:
                        $authUser[$key] = true;
                        $authUser[$key . '_type'] = $lang['Auth_Anonymous_Users'];
                        break;

                    case AUTH_REG:
                        $authUser[$key] = $userdata['session_logged_in'] ? true : 0;
                        $authUser[$key . '_type'] = $lang['Auth_Registered_Users'];
                        break;

                    case AUTH_ACL:
                        $authUser[$key] = $userdata['session_logged_in'] ? self::auth_check_user(AUTH_ACL, $key, $u_access, $isAdmin) : 0;
                        $authUser[$key . '_type'] = $lang['Auth_Users_granted_access'];
                        break;

                    case AUTH_MOD:
                        $authUser[$key] = $userdata['session_logged_in'] ? self::auth_check_user(AUTH_MOD, 'auth_mod', $u_access, $isAdmin) : 0;
                        $authUser[$key . '_type'] = $lang['Auth_Moderators'];
                        break;

                    case AUTH_ADMIN:
                        $authUser[$key] = $isAdmin;
                        $authUser[$key . '_type'] = $lang['Auth_Administrators'];
                        break;

                    default:
                        $authUser[$key] = 0;
                        break;
                }
            } else {
                foreach ($f_access as $f_access_value) {
                    $value = $f_access_value[$key];
                    $f_forum_id = $f_access_value['forum_id'];
                    $u_access[$f_forum_id] = isset($u_access[$f_forum_id]) ? $u_access[$f_forum_id] : [];

                    switch ($value) {
                        case AUTH_ALL:
                            $authUser[$f_forum_id][$key] = true;
                            $authUser[$f_forum_id][$key . '_type'] = $lang['Auth_Anonymous_Users'];
                            break;

                        case AUTH_REG:
                            $authUser[$f_forum_id][$key] = $userdata['session_logged_in'] ? true : 0;
                            $authUser[$f_forum_id][$key . '_type'] = $lang['Auth_Registered_Users'];
                            break;

                        case AUTH_ACL:
                            $authUser[$f_forum_id][$key] = $userdata['session_logged_in'] ? self::auth_check_user(AUTH_ACL, $key, $u_access[$f_forum_id], $isAdmin) : 0;
                            $authUser[$f_forum_id][$key . '_type'] = $lang['Auth_Users_granted_access'];
                            break;

                        case AUTH_MOD:
                            $authUser[$f_forum_id][$key] = $userdata['session_logged_in'] ? self::auth_check_user(AUTH_MOD, 'auth_mod', $u_access[$f_forum_id], $isAdmin) : 0;
                            $authUser[$f_forum_id][$key . '_type'] = $lang['Auth_Moderators'];
                            break;

                        case AUTH_ADMIN:
                            $authUser[$f_forum_id][$key] = $isAdmin;
                            $authUser[$f_forum_id][$key . '_type'] = $lang['Auth_Administrators'];
                            break;

                        default:
                            $authUser[$f_forum_id][$key] = 0;
                            break;
                    }
                }
            }
        }

        //
        // Is user a moderator?
        //
        if ($forumId !== AUTH_LIST_ALL) {
            $authUser['auth_mod'] = $userdata['session_logged_in'] ? self::auth_check_user(AUTH_MOD, 'auth_mod', $u_access, $isAdmin) : 0;
        } else {
            foreach ($f_access as $f_access_value) {
                $f_forum_id = $f_access_value['forum_id'];
                $u_access[$f_forum_id] = isset($u_access[$f_forum_id]) ? $u_access[$f_forum_id] : [];

                $authUser[$f_forum_id]['auth_mod'] = $userdata['session_logged_in'] ? self::auth_check_user(AUTH_MOD, 'auth_mod', $u_access[$f_forum_id], $isAdmin) : 0;
            }
        }

        return $authUser;
    }

    /**
     * @param int    $type
     * @param string $key
     * @param array  $u_access
     * @param bool   $isAdmin
     *
     * @return bool|int
     */
    public static function auth_check_user($type, $key, $u_access, $isAdmin)
    {
        $authUser = 0;

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
                    $result = $result || $isAdmin;

                    // TODO CHECK THIS if its needed
                    break;
                }

                $authUser = $authUser || $result;
            }
        } else {
            $authUser = $isAdmin;
        }

        return $authUser;
    }
}
