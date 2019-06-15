<?php


use Dibi\Fluent;

class Manager
{

    /**\
     * @var string $tableName
     */
    private $tableName;

    /**
     * @var string $primaryKey
     */
    private $primaryKey;

    public function __construct()
    {
    }

    /**
     * @return string
     */
    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    /**
     * @return string
     */
    public function getTableName()
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
    public function getPairs($column)
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
}