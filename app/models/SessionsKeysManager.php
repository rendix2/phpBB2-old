<?php

namespace phpBB2\Models;

use dibi;
use Tables;

/**
 * Class SessionsKeysManager
 *
 * @package phpBB2\Models
 * @author  rendix2
 */
class SessionsKeysManager extends CrudManager
{

    public function getAllSessionsKeysJoinedUsers()
    {
        return dibi::select('*')
            ->from(Tables::SESSIONS_AUTO_LOGIN_KEYS_TABLE)
            ->as('s')
            ->innerJoin(Tables::USERS_TABLE)
            ->as('u')
            ->on('[u.user_id] = [s.user_id]')
            ->fetchAll();
    }


}
