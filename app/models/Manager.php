<?php

namespace phpBB2\Models;

use Config;
use dibi;
use Dibi\Fluent;

/**
 * Class Manager
 *
 * @author rendix2
 * @package phpBB2\Models
 */
class Manager
{

    /**
     * @var string $tableName
     */
    private $tableName;

    /**
     * @var string $primaryKey
     */
    private $primaryKey;

    /**
     * @var string $tablePrefix
     */
    private $tablePrefix;

    /**
     * Manager constructor.
     */
    public function __construct()
    {
        $origClassName = str_replace('Manager', '', get_class($this));
        $explodedName  = explode('\\', $origClassName);
        $count         = count($explodedName);
        $className     = $explodedName[$count - 1];

        $this->tablePrefix = Config::TABLE_PREFIX;
        $this->tableName   = mb_strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));
        $this->tableName   = $this->tablePrefix . $this->tableName;
        $this->primaryKey  = dibi::getDatabaseInfo()->getTable($this->tableName)->primaryKey->columns[0]->getName();
    }

    /**
     * @return string
     */
    protected function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    /**
     * @return string
     */
    protected function getTableName()
    {
        return $this->tableName;
    }

    /**
     * @param array $data
     *
     * @return int
     */
    public function add(array $data)
    {
        return dibi::insert($this->tableName, $data)->execute(dibi::IDENTIFIER);
    }

    /**
     * @param array $data
     *
     * @return Fluent
     */
    public function updateFluent(array $data)
    {
        return dibi::update($this->tableName, $data);
    }

    /**
     * @return Fluent
     *
     */
    public function deleteFluent()
    {
        return dibi::delete($this->tableName);
    }

    /**
     * @return Fluent
     */
    public function selectFluent()
    {
        return dibi::select('*')
            ->from($this->tableName);
    }

    /**
     * @return array
     */
    public function getAll()
    {
        return $this->selectFluent()
            ->fetchAll();
    }

    /**
     * @param string $column
     * @return array
     */
    public function getAllPairs($column)
    {
        return $this->selectFluent()->fetchPairs($this->primaryKey, $column);
    }

    /**
     * @return Fluent;
     */
    public function selectCountFluent()
    {
        return dibi::select('COUNT(%n)', $this->primaryKey)
        ->from($this->tableName);
    }

    /**
     * @return mixed
     */
    public function getAllCount()
    {
        return $this->selectCountFluent()->fetchSingle();
    }
}