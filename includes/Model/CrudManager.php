<?php

namespace phpBB2\Models;

use Dibi\Result;
use Dibi\Row;

/**
 * Class CrudManager
 *
 * @author rendix2
 */
class CrudManager extends Manager
{

    /**
     * @param $primaryKey
     * @param array $data
     *
     * @return Result|int
     */
    public function updateByPrimary($primaryKey, array $data)
    {
        return $this->updateFluent($data)
            ->where('%n = %i', $this->getPrimaryKey(), $primaryKey)
            ->execute(dibi::AFFECTED_ROWS);
    }

    /**
     * @param array $primaryKeys
     * @param array $data
     * @return Result|int
     */
    public function updateByPrimarys(array $primaryKeys, array  $data)
    {
        return $this->updateFluent($data)
            ->where('%n IN %in', $this->getPrimaryKey(), $primaryKeys)
            ->execute(dibi::AFFECTED_ROWS);
    }

    /**
     * @param $primaryKey
     *
     * @return Result|int
     */
    public function deleteByPrimaryKey($primaryKey)
    {
        return $this->deleteFluent()
            ->where('%n = %i', $this->getPrimaryKey(), $primaryKey)
            ->execute();
    }

    /**
     * @param $primaryKey
     *
     * @return Row|false
     */
    public function getByPrimaryKey($primaryKey)
    {
        return $this->selectFluent()
            ->where('%n = %i', $this->getPrimaryKey(), $primaryKey)
            ->fetch();
    }

    /**
     * @return mixed
     */
    public function getAllCount()
    {
        return $this->selectCountFluent()->fetchSingle();
    }
}