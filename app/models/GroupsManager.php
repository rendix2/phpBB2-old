<?php

namespace phpBB2\Models;

use dibi;
use Tables;

/**
 * Class GroupsManager
 *
 * @author  rendix2
 * @package phpBB2\Models
 */
class GroupsManager extends CrudManager
{

    const GROUP_OPEN = 0;
    const GROUP_CLOSED = 1;
    const GROUP_HIDDEN = 2;

    public function getSingleUserGroupsCount()
    {
        return dibi::select('COUNT(group_id)')
            ->from(Tables::GROUPS_TABLE)
            ->where('[group_single_user] = %i', 1)
            ->fetchSingle();
    }

    public function getNotSingleUserGroupsCount()
    {
        return dibi::select('COUNT(group_id)')
            ->from(Tables::GROUPS_TABLE)
            ->where('[group_single_user] = %i', 0)
            ->fetchSingle();
    }

    public function getGroupsJoinedModerator()
    {
        return dibi::select('*')
            ->from(Tables::GROUPS_TABLE)
            ->as('g')
            ->leftJoin(Tables::USERS_TABLE)
            ->as('u')
            ->on('[g.group_moderator] = [u.user_id]')
            ->where('[g.group_single_user] <> %i', 1)
            ->fetchAll();
    }

    public function getGroupsByModeratorCheckedPermissions($groupId, $userId)
    {
        return dibi::select('g.group_id')
            ->from(Tables::AUTH_ACCESS_TABLE)
            ->as('a')
            ->from(Tables::GROUPS_TABLE)
            ->as('g')
            ->from(Tables::USERS_GROUPS_TABLE)
            ->as('ug')
            ->where('[a.auth_mod] = %i', 1)
            ->where('[g.group_id] = [a.group_id]')
            ->where('[a.group_id] = [ug.group_id]')
            ->where('[g.group_id] = [ug.group_id]')
            ->where('[ug.user_id] = %i',(int)$userId)
            ->where('[ug.group_id] <> %i', $groupId)
            ->fetchAll();
    }
}
