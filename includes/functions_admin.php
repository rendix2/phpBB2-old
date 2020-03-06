<?php
/***************************************************************************
 *                            functions_admin.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: functions_admin.php 6981 2007-02-10 12:14:24Z acydburn $
 *
 *
 ***************************************************************************/

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 *
 ***************************************************************************/

/**
 * Simple version of jumpbox, just lists authed forums
 *
 * @param string $boxName
 * @param bool   $ignoreForum
 * @param string $selectForum
 *
 * @return string
 */
function make_forum_select($boxName, $ignoreForum = false, $selectForum = '')
{
	global $userdata, $lang;

	$isAuth = Auth::authorize(Auth::AUTH_READ, Auth::AUTH_ALL, $userdata);

	$forums = dibi::select(['f.forum_id', 'f.forum_name'])
        ->from(Tables::CATEGORIES_TABLE)
        ->as('c')
        ->innerJoin(Tables::FORUMS_TABLE)
        ->as('f')
        ->on('[f.cat_id] = [c.cat_id]')
        ->orderBy('c.cat_order')
        ->orderBy(' f.forum_order')
        ->fetchAll();

	$forumList = '';

    foreach ($forums as $forum) {
        if ($isAuth[$forum->forum_id]['auth_read'] && $ignoreForum !== $forum->forum_id) {
            $selected = $selectForum === $forum->forum_id ? ' selected="selected"' : '';

            $forumList .= '<option value="' . $forum->forum_id . '"' . $selected . '>' . htmlspecialchars($forum->forum_name, ENT_QUOTES) . '</option>';
        }
    }

	$forumList = $forumList === '' ? $lang['No_forums'] : '<select name="' . $boxName . '">' . $forumList . '</select>';

	return $forumList;
}

/**
 * Get database size
 * Currently only mysql and mssql are supported
 * copied from phpbb3
 */
function get_database_size()
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
                    list($database, ) = explode('.', $database);
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

    $databaseSize = $databaseSize !== false ? get_formatted_filesize($databaseSize) : $lang['Not_available'];

    return $databaseSize;
}

/**
 * its says when user is and what he does
 *
 * @param int $sessionPage
 * @param array $lang
 *
 * @return string
 */
function getForumLocation($sessionPage, array $lang)
{
    switch ($sessionPage) {
        case PAGE_ADMIN_INDEX:
            return $lang['Admin_index'];
        case PAGE_POSTING:
            return $lang['Posting_message'];
        case PAGE_LOGIN:
            return $lang['Logging_on'];
        case PAGE_SEARCH:
            return $lang['Searching_forums'];
        case PAGE_PROFILE:
            return $lang['Viewing_profile'];
        case PAGE_VIEW_ONLINE:
            return $lang['Viewing_online'];
        case PAGE_VIEW_MEMBERS:
            return $lang['Viewing_member_list'];
        case PAGE_PRIVMSGS:
            return $lang['Viewing_priv_msgs'];
        case PAGE_FAQ:
            return $lang['Viewing_FAQ'];
        case PAGE_RANKS:
            return $lang['Viewing_ranks'];
        case PAGE_UACP:
            return $lang['User_acp_title'];
        case PAGE_RULES:
            return $lang['Rules_page'];
        case PAGE_INDEX:
        default:
            return $lang['Forum_index'];
    }
}

?>