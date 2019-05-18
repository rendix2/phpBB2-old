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

//
// Simple version of jumpbox, just lists authed forums
//
function make_forum_select($box_name, $ignore_forum = false, $select_forum = '')
{
	global $db, $userdata, $lang;

	$is_auth_ary = auth(AUTH_READ, AUTH_LIST_ALL, $userdata);

	$sql = 'SELECT f.forum_id, f.forum_name
		FROM ' . CATEGORIES_TABLE . ' c, ' . FORUMS_TABLE . ' f
		WHERE f.cat_id = c.cat_id 
		ORDER BY c.cat_order, f.forum_order';

	if ( !($result = $db->sql_query($sql)) ) {
		message_die(GENERAL_ERROR, 'Couldn not obtain forums information', '', __LINE__, __FILE__, $sql);
	}

	$forum_list = '';

	while ($row = $db->sql_fetchrow($result) ) {
		if ( $is_auth_ary[$row['forum_id']]['auth_read'] && $ignore_forum != $row['forum_id'] ) {
			$selected = ( $select_forum == $row['forum_id'] ) ? ' selected="selected"' : '';
			$forum_list .= '<option value="' . $row['forum_id'] . '"' . $selected .'>' . $row['forum_name'] . '</option>';
		}
	}

	$forum_list = ( $forum_list == '' ) ? $lang['No_forums'] : '<select name="' . $box_name . '">' . $forum_list . '</select>';

	return $forum_list;
}

//
// Synchronise functions for forums/topics
//
function sync($type, $id = false)
{
	global $db;

	switch($type) {
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

            if ( $total_topics === false ) {
                message_die(GENERAL_ERROR, 'Could not get topic count', '', __LINE__, __FILE__, $sql);
            }

			$forums_update_data = [
			    'forum_last_post_id' => $last_post,
                'forum_posts'        => $total_posts,
                'forum_topics'       => $total_topics
            ];

			dibi::update(FORUMS_TABLE, $forums_update_data)->where('forum_id = %i', $id)->execute();

			break;

		case 'topic':
			$sql = "SELECT MAX(post_id) AS last_post, MIN(post_id) AS first_post, COUNT(post_id) AS total_posts
				FROM " . POSTS_TABLE . "
				WHERE topic_id = $id";

			if ( !($result = $db->sql_query($sql)) ) {
				message_die(GENERAL_ERROR, 'Could not get post ID', '', __LINE__, __FILE__, $sql);
			}

			if ( $row = $db->sql_fetchrow($result) ) {
				if ($row['total_posts']) {
					// Correct the details of this topic
                    $update_data = [
                        'topic_replies' => $row['total_posts'] - 1,
                        'topic_first_post_id' => $row['first_post'],
                        'topic_last_post_id' => $row['last_post']
                    ];

                    dibi::update(TOPICS_TABLE, $update_data)
                        ->where('topic_id = %i', $id)
                        ->execute();
				} else {
					// There are no replies to this topic
					// Check if it is a move stub
					$sql = 'SELECT topic_moved_id 
						FROM ' . TOPICS_TABLE . " 
						WHERE topic_id = $id";

					if (!($result = $db->sql_query($sql))) {
						message_die(GENERAL_ERROR, 'Could not get topic ID', '', __LINE__, __FILE__, $sql);
					}

					if ($row = $db->sql_fetchrow($result)) {
						if (!$row['topic_moved_id']) {
						    dibi::delete(TOPICS_TABLE)->where('topic_id = %i', $id)->execute();
						}
					}

					$db->sql_freeresult($result);
				}
			}
			break;
	}
	
	return true;
}

?>