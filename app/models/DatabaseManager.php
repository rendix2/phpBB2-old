<?php
/**
 *
 * Created by PhpStorm.
 * Filename: DatabaseManager.php
 * User: Tomáš Babický
 * Date: 03.03.2021
 * Time: 15:13
 */

namespace phpBB2\Models;

use Config;
use dibi;
use Dibi\Connection;

/**
 * Class DatabaseManager
 *
 * @package phpBB2\Models
 */
class DatabaseManager
{
    /**
     * Get database size
     * Currently only mysql and mssql are supported
     * copied from phpbb3
     */
    function getDatabaseSize()
    {
        global $lang;

        $databaseSize = false;

        // This code is heavily influenced by a similar routine in phpMyAdmin 2.2.0
        switch (Config::DBMS) {
            case 'mysql':
                $row = dibi::query('SELECT VERSION() AS mysql_version')->fetch();

                if ($row) {
                    $version = $row->mysql_version;

                    if (preg_match('#(3\.23|[45]\.|10\.[0-9]\.[0-9]{1,2}-+Maria)#', $version)) {
                        $tables = dibi::query('SHOW TABLE STATUS FROM %n', Config::DATABASE_NAME)->fetchAll();

                        $databaseSize = 0;

                        foreach ($tables as $table) {
                            if ((isset($table->Type) && $table->Type !== 'MRG_MyISAM') || (isset($table->Engine) && ($table->Engine === 'MyISAM' || $table->Engine === 'InnoDB' || $table->Engine === 'Aria'))) {
                                if (Config::TABLE_PREFIX !== '') {
                                    if (mb_strpos($table->Name, Config::TABLE_PREFIX) !== false) {
                                        $databaseSize += $table->Data_length + $table->Index_length;
                                    }
                                } else {
                                    $databaseSize += $table->Data_length + $table->Index_length;
                                }
                            }
                        }
                    }
                }
                break;

            case 'sqlite3':
                if (file_exists(Config::DATABASE_HOST)) {
                    $databaseSize = filesize(Config::DATABASE_HOST);
                }

                break;

            case 'mssql_odbc':
            case 'mssqlnative':
                $row = dibi::select('@@VERSION')->as('mssql_version')->fetch();

                if ($row) {
                    // Azure stats are stored elsewhere
                    if (mb_strpos($row->mssql_version, 'SQL Azure') !== false) {
                        $databaseSize = dibi::select('((SUM(size) * 8.0) * 1024.0)')->as('dbsize')
                            ->from('sys.dm_db_partition_stats')
                            ->fetchSingle();

                    } else {
                        $databaseSize = dibi::select('((SUM(size) * 8.0) * 1024.0)')->as('dbsize')
                            ->from('sysfiles')
                            ->fetchSingle();
                    }
                }

                break;

            case 'postgres':
                $row = dibi::select('proname')
                    ->from('pg_proc')
                    ->where('[proname] = %s', 'pg_database_size')
                    ->fetch();

                if ($row->proname === 'pg_database_size') {
                    $database = dibi::getDatabaseInfo()->getName();

                    if (mb_strpos($database, '.') !== false) {
                        [$database, ] = explode('.', $database);
                    }

                    $oid = dibi::select('oid')
                        ->from('pg_database')
                        ->where('[datname] = %s', $database)
                        ->fetchSingle();

                    $databaseSize = dibi::select( 'pg_database_size(%n)', $oid)->as('size')->fetchSingle();
                }
                break;

            case 'oracle':
                $databaseSize = dibi::select('SUM(bytes)')->as('dbsize')
                    ->from('user_segments')
                    ->fetchSingle();
                break;
        }

        return $databaseSize;

        $databaseSize = $databaseSize !== false ? get_formatted_filesize($databaseSize) : $lang['Not_available'];

        return $databaseSize;
    }

    public function getVersion()
    {
        return dibi::query('SELECT VERSION() AS mysql_version')->fetchSingle();
    }
}
