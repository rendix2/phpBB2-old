<?php
/**
 *
 * Created by PhpStorm.
 * Filename: AuthAccessManager.php
 * User: Tomáš Babický
 * Date: 09.03.2021
 * Time: 1:06
 */

namespace phpBB2\Models;

use dibi;
use Tables;

/**
 * Class AuthAccessManager
 *
 * @package phpBB2\Models
 */
class AuthAccessManager extends M2NManager
{
    public function __construct(GroupsManager $leftTable, ForumsManager $rightTable)
    {
        parent::__construct($leftTable, $rightTable);
    }


    public function getAuthModByGroupId($groupId)
    {
        return dibi::select('auth_mod')
            ->from(Tables::AUTH_ACCESS_TABLE)
            ->where('[group_id] = %i', $groupId)
            ->fetchSingle();
    }

    public function deleteByGroup($groupId)
    {
        return dibi::delete(Tables::AUTH_ACCESS_TABLE)
            ->where('[group_id] = %i', $groupId)
            ->execute();
    }
}
