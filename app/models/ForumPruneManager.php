<?php

namespace phpBB2\Models;

/**
 * Class ForumPruneManager
 *
 * @package phpBB2\Models
 * @author  rendix2
 */
class ForumPruneManager extends CrudManager
{
    public function getByForumId($forumId)
    {
        return $this->selectFluent()
            ->where('[forum_id] = %i', $forumId)
            ->fetch();
    }
}
