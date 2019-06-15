<?php


use Dibi\Result;
use Dibi\Row;

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
            ->execute();
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
            ->where('%n = %i', $this->getTableName(), $primaryKey)
            ->fetch();
    }

}