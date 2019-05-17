<?php
/***************************************************************************
*                                 prune.php
*                            -------------------
*   begin                : Thursday, June 14, 2001
*   copyright            : (C) 2001 The phpBB Group
*   email                : support@phpbb.com
*
*   $Id: prune.php 5508 2006-01-29 17:31:16Z grahamje $
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
 ***************************************************************************/

if ( !defined('IN_PHPBB') ) {
   die("Hacking attempt");
}

require $phpbb_root_path . 'includes/functions_search.php';

function prune($forum_id, $prune_date, $prune_all = false)
{
	global $db, $lang;

	$topics = dibi::select('topic_id')
        ->from(TOPICS_TABLE)
        ->where('topic_last_post_id = %i', 0)
        ->fetchAll();

	foreach ($topics as $topic) {
	    sync('topic', $topic->topic_id);
    }

	$prune_all = $prune_all ? '' : 'AND t.topic_vote = 0 AND t.topic_type <> ' . POST_ANNOUNCE;
	//
	// Those without polls and announcements ... unless told otherwise!
	//
	$sql = "SELECT t.topic_id 
		FROM " . POSTS_TABLE . " p, " . TOPICS_TABLE . " t
		WHERE t.forum_id = $forum_id
			$prune_all 
			AND p.post_id = t.topic_last_post_id";
	
	if ( $prune_date != '' ) {
		$sql .= " AND p.post_time < $prune_date";
	}

	if ( !($result = $db->sql_query($sql)) ) {
		message_die(GENERAL_ERROR, 'Could not obtain lists of topics to prune', '', __LINE__, __FILE__, $sql);
	}

	$sql_topics = '';
	
	while ($row = $db->sql_fetchrow($result) ) {
		$sql_topics .= ( ( $sql_topics != '' ) ? ', ' : '' ) . $row['topic_id'];
	}
	
	$db->sql_freeresult($result);
		
	if ($sql_topics != '' ) {
		$sql = "SELECT post_id
			FROM " . POSTS_TABLE . " 
			WHERE forum_id = $forum_id 
				AND topic_id IN ($sql_topics)";
		
		if ( !($result = $db->sql_query($sql)) ) {
			message_die(GENERAL_ERROR, 'Could not obtain list of posts to prune', '', __LINE__, __FILE__, $sql);
		}

		$sql_post = '';
		
		while ( $row = $db->sql_fetchrow($result) ) {
			$sql_post .= ( ( $sql_post != '' ) ? ', ' : '' ) . $row['post_id'];
		}
		
		$db->sql_freeresult($result);

		if ( $sql_post != '' ) {
			$sql = "DELETE FROM " . TOPICS_WATCH_TABLE . " 
				WHERE topic_id IN ($sql_topics)";
			
			if ( !$db->sql_query($sql, BEGIN_TRANSACTION) ) {
				message_die(GENERAL_ERROR, 'Could not delete watched topics during prune', '', __LINE__, __FILE__, $sql);
			}

			$sql = "DELETE FROM " . TOPICS_TABLE . " 
				WHERE topic_id IN ($sql_topics)";
			
			if ( !$db->sql_query($sql) ) {
				message_die(GENERAL_ERROR, 'Could not delete topics during prune', '', __LINE__, __FILE__, $sql);
			}

			$pruned_topics = $db->sql_affectedrows();

			$sql = "DELETE FROM " . POSTS_TABLE . " 
				WHERE post_id IN ($sql_post)";
			
			if ( !$db->sql_query($sql) ) {
				message_die(GENERAL_ERROR, 'Could not delete post_text during prune', '', __LINE__, __FILE__, $sql);
			}

			$pruned_posts = $db->sql_affectedrows();

			$sql = "DELETE FROM " . POSTS_TEXT_TABLE . " 
				WHERE post_id IN ($sql_post)";
			
			if ( !$db->sql_query($sql) ) {
				message_die(GENERAL_ERROR, 'Could not delete post during prune', '', __LINE__, __FILE__, $sql);
			}

			remove_search_post($sql_post);

            return ['topics' => $pruned_topics, 'posts' => $pruned_posts];
        }
    }

    return ['topics' => 0, 'posts' => 0];
}

//
// Function auto_prune(), this function will read the configuration data from
// the auto_prune table and call the prune function with the necessary info.
//
function auto_prune($forum_id = 0)
{
	$prune = dibi::select('*')
        ->from(PRUNE_TABLE)
        ->where('forum_id = %i', $forum_id)
        ->fetch();

    if (!$prune) {
        return;
    }

    if ($prune->prune_freq && $prune->prune_days) {
        $prune_date = time() - ($prune->prune_days * 86400);
        $next_prune = time() + ($prune->prune_freq * 86400);

        prune($forum_id, $prune_date);
        sync('forum', $forum_id);

        dibi::update(FORUMS_TABLE, ['prune_next' => $next_prune])
            ->where('forum_id = %i', $forum_id)
            ->execute();
    }
}

?>