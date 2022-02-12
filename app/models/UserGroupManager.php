<?php

namespace phpBB2\Models;

/**
 * Class UserGroupManager
 *
 * @package phpBB2\Models
 * @author  rendix2
 */
class UserGroupManager extends M2NManager
{
    public function __construct(UsersManager $leftTable, GroupsManager $rightTable)
    {
        parent::__construct($leftTable, $rightTable);
    }

}
