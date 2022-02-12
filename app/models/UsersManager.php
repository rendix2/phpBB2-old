<?php

namespace phpBB2\Models;

use dibi;
use Tables;

/**
 * Class UsersManager
 *
 * @author rendix2
 * @package phpBB2\Models
 */
class UsersManager extends CrudManager
{

    // User Levels <- Do not change the values of USER or ADMIN
    const DELETED = -1;
    const ANONYMOUS = -1;

    const USER = 0;
    const ADMIN = 1;
    const MOD = 2;

    /**
     * @return int
     */
    public function getAutoLoggedInUsersCount()
    {
        return dibi::select('COUNT(user_id)')
            ->from(
                dibi::select('user_id')
                    ->from(Tables::SESSIONS_AUTO_LOGIN_KEYS_TABLE)
                    ->groupBy('user_id')
            )
            ->as('x')
            ->fetchSingle();
    }

    public function getRegisteredUsersCount()
    {
        return dibi::select('COUNT(*)')
            ->from(Tables::SESSIONS_TABLE)
            ->where('[session_logged_in] = %i', 1)
            ->groupBy('session_user_id')
            ->fetchSingle();
    }

    public function getNotActiveUsers()
    {
        return  dibi::select('COUNT(*)')
            ->as('total')
            ->from(Tables::USERS_TABLE)
            ->where('[user_active] = %i', 0)
            ->where('[user_id] != %i', self::ANONYMOUS)
            ->fetchSingle();
    }

    public function getAdministratorsCount()
    {
        return dibi::select('COUNT(user_id)')
            ->as('total')
            ->from(Tables::USERS_TABLE)
            ->where('[user_level] = %i', self::ADMIN)
            ->where('[user_id] != %i', self::ANONYMOUS)
            ->fetchSingle();
    }

    public function getModeratorsCount()
    {
        return dibi::select('COUNT(user_id)')
            ->as('total')
            ->from(Tables::USERS_TABLE)
            ->where('[user_level] = %i', self::MOD)
            ->where('[user_id] != %i', self::ANONYMOUS)
            ->fetchSingle();
    }
}
