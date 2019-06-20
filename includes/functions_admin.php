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

	$isAuth = Auth::authorize(AUTH_READ, AUTH_LIST_ALL, $userdata);

	$forums = dibi::select(['f.forum_id', 'f.forum_name'])
        ->from(CATEGORIES_TABLE)
        ->as('c')
        ->innerJoin(FORUMS_TABLE)
        ->as('f')
        ->on('f.cat_id = c.cat_id')
        ->orderBy('c.cat_order')
        ->orderBy(' f.forum_order')
        ->fetchAll();

	$forumList = '';

    foreach ($forums as $forum) {
        if ($isAuth[$forum->forum_id]['auth_read'] && $ignoreForum !== $forum->forum_id) {
            $selected = $selectForum === $forum->forum_id ? ' selected="selected"' : '';

            $forumList .= '<option value="' . $forum->forum_id . '"' . $selected . '>' . $forum->forum_name . '</option>';
        }
    }

	$forumList = $forumList === '' ? $lang['No_forums'] : '<select name="' . $boxName . '">' . $forumList . '</select>';

	return $forumList;
}

//
// Synchronise functions for forums/topics
//
function sync($type, $id = false)
{
    switch ($type) {
        case 'all forums':
            $forums = dibi::select('forum_id')
                ->from(FORUMS_TABLE)
                ->fetchAll();

            foreach ($forums as $forum) {
                sync('forum', $forum->forum_id);
            }
            break;

        case 'all topics':
            $topics = dibi::select('topic_id')
                ->from(TOPICS_TABLE)
                ->fetchAll();

            foreach ($topics as $topic) {
                sync('topic', $topic->topic_id);
            }
            break;

        case 'forum':
            $row = dibi::select('MAX(post_id)')
                ->as('last_post')
                ->select('COUNT(post_id)')
                ->as('total')
                ->from(POSTS_TABLE)
                ->where('forum_id = %i', $id)
                ->fetch();

            if (!$row) {
                message_die(GENERAL_ERROR, 'Could not get posts by forum ID');
            }

            $last_post = $row->last_post;
            $total_posts = $row->total;

            $total_topics = dibi::select('COUNT(topic_id)')
                ->as('total')
                ->from(TOPICS_TABLE)
                ->where('forum_id = %i', $id)
                ->fetchSingle();

            if ($total_topics === false) {
                message_die(GENERAL_ERROR, 'Could not get topic count');
            }

			$forums_update_data = [
			    'forum_last_post_id' => $last_post,
                'forum_posts'        => $total_posts,
                'forum_topics'       => $total_topics
            ];

            dibi::update(FORUMS_TABLE, $forums_update_data)->where('forum_id = %i', $id)->execute();

            break;

        case 'topic':
            $row = dibi::select('MAX(post_id)')
                ->as('last_post')
                ->select('MIN(post_id)')
                ->as('first_post')
                ->select('COUNT(post_id)')
                ->as('total_posts')
                ->from(POSTS_TABLE)
                ->where('topic_id = %i', $id)
                ->fetch();

            if ($row) {
                if ($row->total_posts) {
                    // Correct the details of this topic
                    $update_data = [
                        'topic_replies'       => $row->total_posts - 1,
                        'topic_first_post_id' => $row->first_post,
                        'topic_last_post_id'  => $row->last_post
                    ];

                    dibi::update(TOPICS_TABLE, $update_data)
                        ->where('topic_id = %i', $id)
                        ->execute();
                } else {
                    // There are no replies to this topic
                    // Check if it is a move stub
                    $topic_moved_id = dibi::select('topic_moved_id')
                        ->from(TOPICS_TABLE)
                        ->where('topic_id = %i', $id)
                        ->fetch();

                    if ($topic_moved_id) {
                        if (!$topic_moved_id->topic_moved_id) {
                            dibi::delete(TOPICS_TABLE)
                                ->where('topic_id = %i', $id)
                                ->execute();
                        }
                    }
                }
            }
            break;
    }

    return true;
}

/**
 * Get database size
 * Currently only mysql and mssql are supported
 * copied from phpbb3
 */
function get_database_size()
{
    global $dbms, $table_prefix, $lang, $dbname;

    $database_size = false;

    // This code is heavily influenced by a similar routine in phpMyAdmin 2.2.0
    switch ($dbms) {
        case 'mysql':
            $row = dibi::query('SELECT VERSION() AS mysql_version')->fetch();

            if ($row) {
                $version = $row->mysql_version;

                if (preg_match('#(3\.23|[45]\.|10\.[0-9]\.[0-9]{1,2}-+Maria)#', $version)) {
                    $tables = dibi::query('SHOW TABLE STATUS FROM %n', $dbname)->fetchAll();

                    $database_size = 0;

                    foreach ($tables as $table) {
                        if ((isset($table->Type) && $table->Type != 'MRG_MyISAM') || (isset($table->Engine) && ($table->Engine == 'MyISAM' || $table->Engine == 'InnoDB' || $table->Engine == 'Aria'))) {
                            if ($table_prefix != '') {
                                if (strpos($table->Name, $table_prefix) !== false) {
                                    $database_size += $table->Data_length + $table->Index_length;
                                }
                            } else {
                                $database_size += $table->Data_length + $table->Index_length;
                            }
                        }
                    }
                }
            }
            break;

        case 'sqlite3':
            global $dbhost;

            if (file_exists($dbhost)) {
                $database_size = filesize($dbhost);
            }

            break;

        case 'mssql_odbc':
        case 'mssqlnative':
            $row = dibi::select('@@VERSION')->as('mssql_version')->fetch();

            if ($row) {
                // Azure stats are stored elsewhere
                if (strpos($row['mssql_version'], 'SQL Azure') !== false) {
                    $database_size = dibi::select('((SUM(size) * 8.0) * 1024.0)')->as('dbsize')
                        ->from('sys.dm_db_partition_stats')
                        ->fetchSingle();

                } else {
                    $database_size = dibi::select('((SUM(size) * 8.0) * 1024.0)')->as('dbsize')
                        ->from('sysfiles')
                        ->fetchSingle();
                }
            }

            break;

        case 'postgres':
            $row = dibi::select('proname')
                ->from('pg_proc')
                ->where('proname = %s', 'pg_database_size')
                ->fetch();

            if ($row['proname'] == 'pg_database_size') {
                $database = dibi::getDatabaseInfo()->getName();

                if (strpos($database, '.') !== false) {
                    list($database, ) = explode('.', $database);
                }

                $oid = dibi::select('oid')
                    ->from('pg_database')
                    ->where('datname = %s', $database)
                    ->fetchSingle();

                $database_size = dibi::select( 'pg_database_size(%n)', $oid)->as('size')->fetchSingle();
            }
            break;

        case 'oracle':
            $database_size = dibi::select('SUM(bytes)')->as('dbsize')
                ->from('user_segments')
                ->fetchSingle();
            break;
    }

    $database_size = ($database_size !== false) ? get_formatted_filesize($database_size) : $lang['Not_available'];

    return $database_size;
}

?>