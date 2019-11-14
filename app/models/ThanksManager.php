<?php

namespace phpBB2\Models;

use dibi;
use Dibi\Row;
use Tables;

/**
 * Class ThanksManager
 *
 * @author rendix2
 * @package phpBB2\Models
 */
class ThanksManager extends CrudManager
{
    /**
     * @param int $topicId
     *
     * @return mixed
     */
    public function getCountByTopicId($topicId)
    {
        return $this->selectCountFluent()
            ->where('[topic_id] = %i', $topicId)
            ->fetchSingle();
    }

    /**
     * @param int $topicId
     *
     * @return Row[]
     */
    public function getByTopicId($topicId)
    {
        return $this->selectFluent()
            ->where('[topic_id] = %i', $topicId)
            ->fetchAll();
    }

    /**
     * @param int $userId
     *
     * @return Row[]
     */
    public function getByUserId($userId)
    {
        return $this->selectFluent()
            ->where('[user_id] = %i', $userId)
            ->fetchAll();
    }

    /**
     * @param int $topicId
     *
     * @return \Dibi\Result|int
     * @throws \Dibi\Exception
     */
    public function deleteByTopicId($topicId)
    {
        return dibi::delete(Tables::THANKS_TABLE)
            ->where('[topic_id] = %i', $topicId)
            ->execute();
    }
}