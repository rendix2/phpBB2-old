<?php

namespace phpBB2\Models;

/**
 * Class TopicsWatch
 *
 * @package phpBB2\Models
 * @author  rendix2
 */
class TopicsWatchManager extends M2NManager
{
    public function __construct(TopicsManager $leftTable, UsersManager $rightTable)
    {
        parent::__construct($leftTable, $rightTable);
    }

}
