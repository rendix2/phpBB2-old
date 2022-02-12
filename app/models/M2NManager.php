<?php
/**
 *
 * Created by PhpStorm.
 * Filename: M2NManager.php
 * User: Tomáš Babický
 * Date: 03.03.2021
 * Time: 14:45
 */

namespace phpBB2\Models;

use Config;
use dibi;
use Dibi\Fluent;

/**
 * Class M2NManager
 *
 * @package phpBB2\Models
 */
abstract class M2NManager extends Manager
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
     * @var string $leftKey
     */
    private $leftKey;

    /**
     * @var string $rightKey
     */
    private $rightKey;

    private $leftTable;

    private $rightTable;

    /**
     * Manager constructor.
     */
    public function __construct(CrudManager $leftTable, CrudManager $rightTable)
    {
        $origClassName = str_replace('Manager', '', get_class($this));
        $explodedName  = explode('\\', $origClassName);
        $count         = count($explodedName);
        $className     = $explodedName[$count - 1];

        $this->tablePrefix = Config::TABLE_PREFIX;
        $this->tableName   = mb_strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));
        $this->tableName   = $this->tablePrefix . $this->tableName;

        $keys = dibi::getDatabaseInfo()->getTable($this->tableName)->primaryKey;

        $this->leftKey = $keys->columns[0]->getName();
        $this->rightKey = $keys->columns[1]->getName();
        $this->leftTable = $leftTable;
        $this->rightTable = $rightTable;
    }

    /**
     * @return Fluent;
     */
    public function selectCountFluent()
    {
        return dibi::select('COUNT(%n)', '*')
            ->from($this->tableName);
    }

    /**
     * @return mixed
     */
    public function getAllCount()
    {
        return $this->selectCountFluent()->fetchSingle();
    }

    public function add(array $data)
    {
        return dibi::insert($this->tableName,$data)
            ->execute();
    }

    public function getByLeft($id)
    {
        return dibi::select('*')
            ->from($this->tableName)
            ->where('%n = %i', $this->leftKey, $id)
            ->fetchAll();
    }

    public function getByLeftAndRight($leftId,$rightId)
    {
        return dibi::select('*')
            ->from($this->tableName)
            ->where('%n = %i', $this->leftKey, $leftId)
            ->where('%n = %i', $this->rightKey, $rightId)
            ->fetch();
    }

    public function getByLeftJoined($id)
    {
        return dibi::select('*')
            ->from($this->tableName)
            ->as('join_table')
            ->innerJoin($this->rightTable->getTableName())
            ->on('%n = %n', $this->rightTable->getTableName() . '.' . $this->rightTable->getPrimaryKey(), 'join_table.' . $this->leftKey)
            ->where('%n = %i', 'join_table.' . $this->rightKey, $id)
            ->fetchAll();
    }

    public function getByRight($id)
    {
        return dibi::select('*')
            ->from($this->tableName)
            ->where('%n = %i', $this->rightKey, $id)
            ->fetchAll();
    }

    public function getByRightJoined($id)
    {
        return dibi::select('*')
            ->from($this->tableName)
            ->as('join_table')
            ->innerJoin($this->leftTable->getTableName())
            ->on('%n = %n', $this->leftTable->getTableName() . '.' . $this->leftTable->getPrimaryKey(), 'join_table.' . $this->rightKey)
            ->where('%n = %i', 'join_table.' . $this->leftKey, $id)
            ->fetchAll();
    }

    public function deleteByLeft($leftId)
    {
        return dibi::delete($this->tableName)
            ->where('%n = %i', $this->leftKey, $leftId)
            ->execute();
    }

    public function deleteRight($rightId)
    {
        return dibi::delete($this->tableName)
            ->where('%n = %i', $this->rightKey, $rightId)
            ->execute();
    }

    public function deleteByLeftAndRight($leftId, $rightId)
    {
        return dibi::delete($this->tableName)
            ->where('%n = %i', $this->leftKey, $leftId)
            ->where('%n = %i', $this->rightKey, $rightId)
            ->execute();
    }
}