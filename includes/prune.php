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
	$topics = dibi::select('topic_id')
        ->from(TOPICS_TABLE)
        ->where('topic_last_post_id = %i', 0)
        ->fetchAll();

	foreach ($topics as $topic) {
	    sync('topic', $topic->topic_id);
    }

	//
	// Those without polls and announcements ... unless told otherwise!
	//
    $topic_query = dibi::select('t.topic_id')
        ->from(POSTS_TABLE)
        ->as('p')
        ->from(TOPICS_TABLE)
        ->as('t')
        ->where('t.forum_id = %i', $forum_id);

    if (!$prune_all) {
        $topic_query->where('t.topic_vote = %i', 0)
            ->where('t.topic_type <> %i', POST_ANNOUNCE);
    }

    $topic_query->where('p.post_id = t.topic_last_post_id');

	if ($prune_date) {
	    $topic_query->where('p.post_time < %i', $prune_date);
    }

	$topic_data = $topic_query->fetchPairs(null, 'topic_id');
		
	if (count($topic_data)) {
	    $post_ids = dibi::select('post_id')
            ->from(POSTS_TABLE)
            ->where('forum_id = %i', $forum_id)
            ->where('topic_id IN %in', $topic_data)
            ->fetchPairs(null, 'post_id');

		if ( count($post_ids) ) {
		    dibi::delete(TOPICS_WATCH_TABLE)
                ->where('topic_id IN %in', $topic_data)
                ->execute();

            $pruned_topics = dibi::delete(TOPICS_TABLE)
                ->where('topic_id IN %in', $topic_data)
                ->execute(dibi::AFFECTED_ROWS);

            $pruned_posts = dibi::delete(POSTS_TABLE)
                ->where('post_id IN %in', $post_ids)
                ->execute(dibi::AFFECTED_ROWS);

            dibi::delete(POSTS_TEXT_TABLE)
                ->where('post_id IN %in', $post_ids)
                ->execute(dibi::AFFECTED_ROWS);

			remove_search_post($post_ids);

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