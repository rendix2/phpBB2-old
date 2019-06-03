<?php
/***************************************************************************
 *                               viewtopic.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: viewtopic.php 6772 2006-12-16 13:11:28Z acydburn $
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

define('IN_PHPBB', true);
$phpbb_root_path = './';

include $phpbb_root_path . 'common.php';
include $phpbb_root_path . 'includes/bbcode.php';

//
// Start initial var setup
//
$topic_id = $post_id = 0;
if (isset($_GET[POST_TOPIC_URL])) {
    $topic_id = (int)$_GET[POST_TOPIC_URL];
} elseif (isset($_GET['topic'])) {
    $topic_id = (int)$_GET['topic'];
}

if (isset($_GET[POST_POST_URL])) {
    $post_id = (int)$_GET[POST_POST_URL];
}

$start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
$start = $start < 0 ? 0 : $start;

if (!$topic_id && !$post_id) {
	message_die(GENERAL_MESSAGE, 'Topic_post_not_exist');
}

//
// Find topic id if user requested a newer
// or older topic
//
$cookie_name_sid = $board_config['cookie_name'] . '_sid';
$cookie_name_topic = $board_config['cookie_name'] . '_t';
$cookie_name_forum = $board_config['cookie_name'] . '_f';

if (isset($_GET['view']) && empty($_GET[POST_POST_URL])) {
    if ($_GET['view'] == 'newest') {
		if ( isset($_COOKIE[$cookie_name_sid]) || isset($_GET['sid']) ) {
			$session_id = isset($_COOKIE[$cookie_name_sid]) ? $_COOKIE[$cookie_name_sid] : $_GET['sid'];

            if (!preg_match('/^[A-Za-z0-9]*$/', $session_id)) {
                $session_id = '';
            }

			if ( $session_id ) {
			    $session_post_id = dibi::select('p.post_id')
                    ->from(POSTS_TABLE)
                    ->as('p')
                    ->from(SESSIONS_TABLE)
                    ->as('s')
                    ->from(USERS_TABLE)
                    ->as('u')
                    ->where('s.session_id = %s', $session_id)
                    ->where('u.user_id = s.session_user_id')
                    ->where('p.topic_id = %i', $topic_id)
                    ->where('p.post_time >= u.user_lastvisit')
                    ->orderBy('p.post_time', dibi::ASC)
                    ->fetch();

                if (!$session_post_id) {
                    message_die(GENERAL_MESSAGE, 'No_new_posts_last_visit');
                }

				$post_id = $session_post_id->post_id;

                if (isset($_GET['sid'])) {
                    redirect("viewtopic.php?sid=$session_id&" . POST_POST_URL . "=$post_id#$post_id");
                } else {
                    redirect("viewtopic.php?" . POST_POST_URL . "=$post_id#$post_id");
                }
			}
		}

		redirect(append_sid("viewtopic.php?" . POST_TOPIC_URL . "=$topic_id", true));
	} elseif ( $_GET['view'] == 'next' || $_GET['view'] == 'previous' ) {
		$sql_condition = ( $_GET['view'] == 'next' ) ? '>' : '<';
		$sql_ordering = ( $_GET['view'] == 'next' ) ? 'ASC' : 'DESC';

		$row = dibi::select('t.topic_id')
            ->from(TOPICS_TABLE)
            ->as('t')
            ->from(TOPICS_TABLE)
            ->as('t2')
            ->where('t2.topic_id = %i', $topic_id)
            ->where('t.forum_id = t2.forum_id')
            ->where('t.topic_moved_id = %i', 0)
            ->where('t.topic_last_post_id '. $sql_condition . ' t2.topic_last_post_id')
            ->orderBy('t.topic_last_post_id', $sql_ordering)
            ->fetch();

        if ($row) {
			$topic_id = (int)$row->topic_id;
		} else {
			$message = ( $_GET['view'] == 'next' ) ? 'No_newer_topics' : 'No_older_topics';
			message_die(GENERAL_MESSAGE, $message);
		}
	}
}

//
// This rather complex gaggle of code handles querying for topics but
// also allows for direct linking to a post (and the calculation of which
// page the post is on and the correct display of viewtopic)
//

if (!$post_id) {
    $columns = ['t.topic_id', 't.topic_title', 't.topic_status', 't.topic_replies', 't.topic_time', 't.topic_type',
                't.topic_vote', 't.topic_last_post_id', 'f.forum_name', 'f.forum_status', 'f.forum_id', 'f.auth_view',
                'f.auth_read', 'f.auth_post', 'f.auth_reply', 'f.auth_edit', 'f.auth_delete', 'f.auth_sticky',
                'f.auth_announce', 'f.auth_pollcreate', 'f.auth_vote', 'f.auth_attachments'
                ];

    $forum_topic_data = dibi::select($columns)
        ->from(TOPICS_TABLE)
        ->as('t')
        ->from(FORUMS_TABLE)
        ->as('f')
        ->where('t.topic_id = %i', $topic_id)
        ->where('f.forum_id = t.forum_id')
        ->fetch();
} else {
    $columns = ['t.topic_id', 't.topic_title', 't.topic_status', 't.topic_replies', 't.topic_time', 't.topic_type',
                't.topic_vote', 't.topic_last_post_id', 'f.forum_name', 'f.forum_status', 'f.forum_id', 'f.auth_view',
                'f.auth_read', 'f.auth_post', 'f.auth_reply', 'f.auth_edit', 'f.auth_delete', 'f.auth_sticky',
                'f.auth_announce', 'f.auth_pollcreate', 'f.auth_vote', 'f.auth_attachments',
    ];

    $forum_topic_data = dibi::select($columns)
        ->select('COUNT(p2.post_id)')
        ->as('prev_posts')
        ->from(TOPICS_TABLE)
        ->as('t')
        ->from(FORUMS_TABLE)
        ->as('f')
        ->from(POSTS_TABLE)
        ->as('p')
        ->from(POSTS_TABLE)
        ->as('p2')
        ->where('p.post_id = %i', $post_id)
        ->where('t.topic_id = p.topic_id')
        ->where('p2.topic_id = p.topic_id')
        ->where('p2.post_id <= %i', $post_id)
        ->where('f.forum_id = t.forum_id')
        ->groupBy('p.post_id')
        ->groupBy('t.topic_id')
        ->groupBy('t.topic_title')
        ->groupBy('t.topic_status')
        ->groupBy('t.topic_replies')
        ->groupBy('t.topic_time')
        ->groupBy('t.topic_type')
        ->groupBy('t.topic_vote')
        ->groupBy('t.topic_last_post_id')
        ->groupBy('f.forum_name')
        ->groupBy('f.forum_status')
        ->groupBy('f.forum_id')
        ->groupBy('f.auth_view')
        ->groupBy('f.auth_read')
        ->groupBy('f.auth_post')
        ->groupBy('f.auth_reply')
        ->groupBy('f.auth_edit')
        ->groupBy('f.auth_delete')
        ->groupBy('f.auth_sticky')
        ->groupBy('f.auth_announce')
        ->groupBy('f.auth_pollcreate')
        ->groupBy('f.auth_vote')
        ->groupBy('f.auth_attachments')
        ->orderBy('p.post_id', dibi::ASC)
        ->fetch();
}

if (!$forum_topic_data) {
    message_die(GENERAL_MESSAGE, 'Topic_post_not_exist');
}

$forum_id = (int)$forum_topic_data->forum_id;

//
// Start session management
//
$userdata = session_pagestart($user_ip, $forum_id);
init_userprefs($userdata);
//
// End session management
//

//
// Start auth check
//
$is_auth = [];
$is_auth = auth(AUTH_ALL, $forum_id, $userdata, $forum_topic_data);

if (!$is_auth['auth_view'] || !$is_auth['auth_read'] ) {
	if ( !$userdata['session_logged_in'] ) {
		$redirect = $post_id ? POST_POST_URL . "=$post_id" : POST_TOPIC_URL . "=$topic_id";
		$redirect .= $start ? "&start=$start" : '';
		redirect(append_sid("login.php?redirect=viewtopic.php&$redirect", true));
	}

	$message = ( !$is_auth['auth_view'] ) ? $lang['Topic_post_not_exist'] : sprintf($lang['Sorry_auth_read'], $is_auth['auth_read_type']);

	message_die(GENERAL_MESSAGE, $message);
}
//
// End auth check
//

$forum_name = $forum_topic_data->forum_name;
$topic_title = $forum_topic_data->topic_title;
$topic_id = (int)$forum_topic_data->topic_id;
$topic_time = $forum_topic_data->topic_time;

if ($post_id) {
	$start = floor(($forum_topic_data->prev_posts - 1) / (int)$board_config['posts_per_page']) * (int)$board_config['posts_per_page'];
}

//
// Is user watching this thread?
//
if ($userdata['session_logged_in'] ) {
	$can_watch_topic = TRUE;

	$row = dibi::select('notify_status')
        ->from(TOPICS_WATCH_TABLE)
        ->where('topic_id = %i', $topic_id)
        ->where('user_id = %i', $userdata['user_id'])
        ->fetch();

    if ($row) {
        if (isset($_GET['unwatch'])) {
            if ($_GET['unwatch'] == 'topic') {
				$is_watching_topic = 0;

				$sql_priority = (SQL_LAYER == 'mysql') ? 'LOW_PRIORITY' : '';

				dibi::delete(TOPICS_WATCH_TABLE)
                    ->setFlag($sql_priority)
                    ->where('topic_id = %i', $topic_id)
                    ->where('user_id = %i', $userdata['user_id'])
                    ->execute();
			}

            $template->assign_vars(
                [
                    'META' => '<meta http-equiv="refresh" content="3;url=' . append_sid("viewtopic.php?" . POST_TOPIC_URL . "=$topic_id&amp;start=$start") . '">'
                ]
            );

            $message = $lang['No_longer_watching'] . '<br /><br />' . sprintf($lang['Click_return_topic'], '<a href="' . append_sid("viewtopic.php?" . POST_TOPIC_URL . "=$topic_id&amp;start=$start") . '">', '</a>');
			message_die(GENERAL_MESSAGE, $message);
		} else {
			$is_watching_topic = TRUE;

            if ($row->notify_status) {
                $sql_priority = (SQL_LAYER == 'mysql') ? 'LOW_PRIORITY' : '';

				dibi::update(TOPICS_WATCH_TABLE, ['notify_status' => 0])
                    ->setFlag($sql_priority)
                    ->where('topic_id = %i', $topic_id)
                    ->where('user_id = %i', $userdata['user_id'])
                    ->execute();
			}
		}
	} else {
        if (isset($_GET['watch'])) {
            if ($_GET['watch'] == 'topic') {
				$is_watching_topic = TRUE;

				$sql_priority = (SQL_LAYER == 'mysql') ? 'LOW_PRIORITY' : '';

                $insert_data = [
                    'user_id' => $userdata['user_id'],
                    'topic_id' => $topic_id,
                    'notify_status' => 0
                ];

				dibi::insert(TOPICS_WATCH_TABLE, $insert_data)
                    ->setFlag($sql_priority)
                    ->execute();
			}

            $template->assign_vars(
                [
                    'META' => '<meta http-equiv="refresh" content="3;url=' . append_sid("viewtopic.php?" . POST_TOPIC_URL . "=$topic_id&amp;start=$start") . '">'
                ]
            );

            $message = $lang['You_are_watching'] . '<br /><br />' . sprintf($lang['Click_return_topic'], '<a href="' . append_sid("viewtopic.php?" . POST_TOPIC_URL . "=$topic_id&amp;start=$start") . '">', '</a>');
			message_die(GENERAL_MESSAGE, $message);
		} else {
			$is_watching_topic = 0;
		}
	}
} else {
    if (isset($_GET['unwatch'])) {
        if ($_GET['unwatch'] == 'topic') {
			redirect(append_sid("login.php?redirect=viewtopic.php&" . POST_TOPIC_URL . "=$topic_id&unwatch=topic", true));
		}
	} else {
		$can_watch_topic = 0;
		$is_watching_topic = 0;
	}
}

//
// Generate a 'Show posts in previous x days' select box. If the postdays var is POSTed
// then get it's value, find the number of topics with dates newer than it (to properly
// handle pagination) and alter the main query
//
$previous_days = [
    0   => $lang['All_Posts'],
    1   => $lang['1_Day'],
    7   => $lang['7_Days'],
    14  => $lang['2_Weeks'],
    30  => $lang['1_Month'],
    90  => $lang['3_Months'],
    180 => $lang['6_Months'],
    364 => $lang['1_Year']
];

if (!empty($_POST['postdays']) || !empty($_GET['postdays'])) {
	$post_days =  !empty($_POST['postdays']) ? (int)$_POST['postdays'] : (int)$_GET['postdays'];
	$min_post_time = time() - ($post_days * 86400);

    $total_replies = dibi::select('COUNT(p.post_id)')
        ->as('num_posts')
        ->from(TOPICS_TABLE)
        ->as('t')
        ->from(POSTS_TABLE)
        ->as('p')
        ->where('t.topic_id = %i', $topic_id)
        ->where('p.topic_id = t.topic_id')
        ->where('p.post_time >= %i', $min_post_time)
        ->fetchSingle();

	$limit_posts_time = true;

    if (!empty($_POST['postdays'])) {
		$start = 0;
	}
} else {
	$total_replies = (int)$forum_topic_data->topic_replies + 1;

    $limit_posts_time = false;
	$post_days = 0;
}

$select_post_days = '<select name="postdays">';

foreach ($previous_days as $previous_day_key => $previous_days_value) {
	$selected = ($post_days == $previous_day_key) ? ' selected="selected"' : '';

	$select_post_days .= '<option value="' . $previous_day_key . '"' . $selected . '>' . $previous_days_value . '</option>';
}

$select_post_days .= '</select>';

//
// Decide how to order the post display
//
if (!empty($_POST['postorder']) || !empty($_GET['postorder'])) {
	$post_order = !empty($_POST['postorder']) ? htmlspecialchars($_POST['postorder']) : htmlspecialchars($_GET['postorder']);
	$post_time_order = ($post_order == "asc") ? "ASC" : "DESC";
} else {
	$post_order = 'asc';
	$post_time_order = 'ASC';
}

$select_post_order = '<select name="postorder">';

if ($post_time_order == 'ASC') {
	$select_post_order .= '<option value="asc" selected="selected">' . $lang['Oldest_First'] . '</option><option value="desc">' . $lang['Newest_First'] . '</option>';
} else {
	$select_post_order .= '<option value="asc">' . $lang['Oldest_First'] . '</option><option value="desc" selected="selected">' . $lang['Newest_First'] . '</option>';
}

$select_post_order .= '</select>';

//
// Go ahead and pull all data for this topic
//
$columns = [
    'u.username',
    'u.user_id',
    'u.user_posts',
    'u.user_from',
    'u.user_website',
    'u.user_email',
    'u.user_icq',
    'u.user_aim',
    'u.user_yim',
    'u.user_regdate',
    'u.user_msnm',
    'u.user_viewemail',
    'u.user_rank',
    'u.user_sig',
    'u.user_sig_bbcode_uid',
    'u.user_avatar',
    'u.user_avatar_type',
    'u.user_allowavatar',
    'u.user_allowsmile',
    'p.*',
    'pt.post_text',
    'pt.post_subject',
    'pt.bbcode_uid'
];

$posts = dibi::select($columns)
    ->from(POSTS_TABLE)
    ->as('p')
    ->leftJoin(USERS_TABLE)
    ->as('u')
    ->on('u.user_id = p.poster_id')
    ->leftJoin(POSTS_TEXT_TABLE)
    ->as('pt')
    ->on('pt.post_id = p.post_id')
    ->where('p.topic_id = %i', $topic_id);

if ($limit_posts_time) {
    $posts->where('p.post_time >= %i', $min_post_time);
}

$posts = $posts
    ->orderBy('p.post_id', $post_time_order)
    ->limit($board_config['posts_per_page'])
    ->offset((int)$start)
    ->fetchAll();

$total_posts = count($posts);

if (!$total_posts) {
    include $phpbb_root_path . 'includes/functions_admin.php';
    sync('topic', $topic_id);

    message_die(GENERAL_MESSAGE, $lang['No_posts_topic']);
}

$resync = false;

if ($forum_topic_data->topic_replies + 1 < $start + $total_posts)  {
   $resync = true; 
} elseif ($start + $board_config['posts_per_page'] > $forum_topic_data->topic_replies)  { 
   $row_id = (int)$forum_topic_data->topic_replies % (int)$board_config['posts_per_page'];
   
   if ($posts[$row_id]->post_id !== $forum_topic_data->topic_last_post_id || $start + $total_posts < $forum_topic_data->topic_replies) {
       $resync = true; 
   } 
}  elseif ($total_posts < $board_config['posts_per_page']) {
   $resync = true;
} 

if ($resync) { 
   include $phpbb_root_path . 'includes/functions_admin.php';
   sync('topic', $topic_id);

    $total_replies = dibi::select('COUNT(post_id)')
        ->as('total')
        ->from(POSTS_TABLE)
        ->where('topic_id = %i', $topic_id)
        ->fetchSingle();
}

$ranks = dibi::select('*')
    ->from(RANKS_TABLE)
    ->orderBy('rank_special')
    ->orderBy('rank_min')
    ->fetchAll();

//
// Define censored word matches
//
$orig_word = [];
$replacement_word = [];
obtain_word_list($orig_word, $replacement_word);

//
// Censor topic title
//
if ( count($orig_word) ) {
	$topic_title = preg_replace($orig_word, $replacement_word, $topic_title);
}

//
// Was a highlight request part of the URI?
//
$highlight_match = $highlight = '';
if (isset($_GET['highlight'])) {
	// Split words and phrases
	$words = explode(' ', trim(htmlspecialchars($_GET['highlight'])));

	foreach ($words as $word){
		if (trim($word) !== '') {
			$highlight_match .= (($highlight_match !== '') ? '|' : '') . str_replace('*', '\w*', preg_quote($word, '#'));
		}
	}
	
	unset($words);

	$highlight = urlencode($_GET['highlight']);
	$highlight_match = phpbb_rtrim($highlight_match, "\\");
}

//
// Post, reply and other URL generation for
// templating vars
//
$new_topic_url = append_sid("posting.php?mode=newtopic&amp;" . POST_FORUM_URL . "=$forum_id");
$reply_topic_url = append_sid("posting.php?mode=reply&amp;" . POST_TOPIC_URL . "=$topic_id");
$view_forum_url = append_sid("viewforum.php?" . POST_FORUM_URL . "=$forum_id");
$view_prev_topic_url = append_sid("viewtopic.php?" . POST_TOPIC_URL . "=$topic_id&amp;view=previous");
$view_next_topic_url = append_sid("viewtopic.php?" . POST_TOPIC_URL . "=$topic_id&amp;view=next");

//
// Mozilla navigation bar
//
$nav_links['prev'] = [
    'url'   => $view_prev_topic_url,
    'title' => $lang['View_previous_topic']
];
$nav_links['next'] = [
    'url'   => $view_next_topic_url,
    'title' => $lang['View_next_topic']
];
$nav_links['up']   = [
    'url'   => $view_forum_url,
    'title' => $forum_name
];

$reply_img = ( $forum_topic_data->forum_status === FORUM_LOCKED || $forum_topic_data->topic_status == TOPIC_LOCKED ) ? $images['reply_locked'] : $images['reply_new'];
$reply_alt = ( $forum_topic_data->forum_status === FORUM_LOCKED || $forum_topic_data->topic_status == TOPIC_LOCKED ) ? $lang['Topic_locked'] : $lang['Reply_to_topic'];
$post_img = ( $forum_topic_data->forum_status === FORUM_LOCKED ) ? $images['post_locked'] : $images['post_new'];
$post_alt = ( $forum_topic_data->forum_status === FORUM_LOCKED ) ? $lang['Forum_locked'] : $lang['Post_new_topic'];

//
// Set a cookie for this topic
//
if ($userdata['session_logged_in']) {
	$tracking_topics = isset($_COOKIE[$cookie_name_topic]) ? unserialize($_COOKIE[$cookie_name_topic]) : [];
	$tracking_forums = isset($_COOKIE[$cookie_name_forum]) ? unserialize($_COOKIE[$cookie_name_forum]) : [];

    if (!empty($tracking_topics[$topic_id]) && !empty($tracking_forums[$forum_id])) {
        $topic_last_read = ($tracking_topics[$topic_id] > $tracking_forums[$forum_id]) ? $tracking_topics[$topic_id] : $tracking_forums[$forum_id];
    } elseif (!empty($tracking_topics[$topic_id]) || !empty($tracking_forums[$forum_id])) {
        $topic_last_read = !empty($tracking_topics[$topic_id]) ? $tracking_topics[$topic_id] : $tracking_forums[$forum_id];
    } else {
        $topic_last_read = $userdata['user_lastvisit'];
    }

    if (count($tracking_topics) >= 150 && empty($tracking_topics[$topic_id])) {
		asort($tracking_topics);
		unset($tracking_topics[key($tracking_topics)]);
	}

	$tracking_topics[$topic_id] = time();

	setcookie($cookie_name_topic, serialize($tracking_topics), 0, $board_config['cookie_path'], $board_config['cookie_domain'], $board_config['cookie_secure']);
}

//
// Load templates
//
$template->set_filenames(['body' => 'viewtopic_body.tpl']);
make_jumpbox('viewforum.php', $forum_id);

//
// Output page header
//
$page_title = $lang['View_topic'] .' - ' . $topic_title;
include $phpbb_root_path . 'includes/page_header.php';

//
// User authorisation levels output
//
$s_auth_can = ( $is_auth['auth_post'] ? $lang['Rules_post_can'] : $lang['Rules_post_cannot'] ) . '<br />';
$s_auth_can .= ( $is_auth['auth_reply'] ? $lang['Rules_reply_can'] : $lang['Rules_reply_cannot'] ) . '<br />';
$s_auth_can .= ( $is_auth['auth_edit'] ? $lang['Rules_edit_can'] : $lang['Rules_edit_cannot'] ) . '<br />';
$s_auth_can .= ( $is_auth['auth_delete'] ? $lang['Rules_delete_can'] : $lang['Rules_delete_cannot'] ) . '<br />';
$s_auth_can .= ( $is_auth['auth_vote'] ? $lang['Rules_vote_can'] : $lang['Rules_vote_cannot'] ) . '<br />';

$topic_mod = '';

if ( $is_auth['auth_mod'] ) {
	$s_auth_can .= sprintf($lang['Rules_moderate'], "<a href=\"modcp.php?" . POST_FORUM_URL . "=$forum_id&amp;sid=" . $userdata['session_id'] . '">', '</a>');

	$topic_mod .= "<a href=\"modcp.php?" . POST_TOPIC_URL . "=$topic_id&amp;mode=delete&amp;sid=" . $userdata['session_id'] . '"><img src="' . $images['topic_mod_delete'] . '" alt="' . $lang['Delete_topic'] . '" title="' . $lang['Delete_topic'] . '" border="0" /></a>&nbsp;';

	$topic_mod .= "<a href=\"modcp.php?" . POST_TOPIC_URL . "=$topic_id&amp;mode=move&amp;sid=" . $userdata['session_id'] . '"><img src="' . $images['topic_mod_move'] . '" alt="' . $lang['Move_topic'] . '" title="' . $lang['Move_topic'] . '" border="0" /></a>&nbsp;';

	$topic_mod .= ( $forum_topic_data->topic_status == TOPIC_UNLOCKED ) ? "<a href=\"modcp.php?" . POST_TOPIC_URL . "=$topic_id&amp;mode=lock&amp;sid=" . $userdata['session_id'] . '"><img src="' . $images['topic_mod_lock'] . '" alt="' . $lang['Lock_topic'] . '" title="' . $lang['Lock_topic'] . '" border="0" /></a>&nbsp;' : "<a href=\"modcp.php?" . POST_TOPIC_URL . "=$topic_id&amp;mode=unlock&amp;sid=" . $userdata['session_id'] . '"><img src="' . $images['topic_mod_unlock'] . '" alt="' . $lang['Unlock_topic'] . '" title="' . $lang['Unlock_topic'] . '" border="0" /></a>&nbsp;';

	$topic_mod .= "<a href=\"modcp.php?" . POST_TOPIC_URL . "=$topic_id&amp;mode=split&amp;sid=" . $userdata['session_id'] . '"><img src="' . $images['topic_mod_split'] . '" alt="' . $lang['Split_topic'] . '" title="' . $lang['Split_topic'] . '" border="0" /></a>&nbsp;';
}

//
// Topic watch information
//
$s_watching_topic = '';
if ( $can_watch_topic ) {
	if ( $is_watching_topic ) {
		$s_watching_topic = "<a href=\"viewtopic.php?" . POST_TOPIC_URL . "=$topic_id&amp;unwatch=topic&amp;start=$start&amp;sid=" . $userdata['session_id'] . '">' . $lang['Stop_watching_topic'] . '</a>';
		$s_watching_topic_img = isset($images['topic_un_watch']) ? "<a href=\"viewtopic.php?" . POST_TOPIC_URL . "=$topic_id&amp;unwatch=topic&amp;start=$start&amp;sid=" . $userdata['session_id'] . '"><img src="' . $images['topic_un_watch'] . '" alt="' . $lang['Stop_watching_topic'] . '" title="' . $lang['Stop_watching_topic'] . '" border="0"></a>' : '';
	} else {
		$s_watching_topic = "<a href=\"viewtopic.php?" . POST_TOPIC_URL . "=$topic_id&amp;watch=topic&amp;start=$start&amp;sid=" . $userdata['session_id'] . '">' . $lang['Start_watching_topic'] . '</a>';
		$s_watching_topic_img = isset($images['Topic_watch']) ? "<a href=\"viewtopic.php?" . POST_TOPIC_URL . "=$topic_id&amp;watch=topic&amp;start=$start&amp;sid=" . $userdata['session_id'] . '"><img src="' . $images['Topic_watch'] . '" alt="' . $lang['Start_watching_topic'] . '" title="' . $lang['Start_watching_topic'] . '" border="0"></a>' : '';
	}
}

//
// If we've got a hightlight set pass it on to pagination,
// I get annoyed when I lose my highlight after the first page.
//
$pagination = ( $highlight !== '' ) ? generate_pagination("viewtopic.php?" . POST_TOPIC_URL . "=$topic_id&amp;postdays=$post_days&amp;postorder=$post_order&amp;highlight=$highlight", $total_replies, $board_config['posts_per_page'], $start) : generate_pagination("viewtopic.php?" . POST_TOPIC_URL . "=$topic_id&amp;postdays=$post_days&amp;postorder=$post_order", $total_replies, $board_config['posts_per_page'], $start);

//
// Send vars to template
//
$template->assign_vars(
    [
        'FORUM_ID'    => $forum_id,
        'FORUM_NAME'  => $forum_name,
        'TOPIC_ID'    => $topic_id,
        'TOPIC_TITLE' => $topic_title,
        'PAGINATION'  => $pagination,
        'PAGE_NUMBER' => sprintf($lang['Page_of'], floor($start / (int)$board_config['posts_per_page']) + 1, ceil($total_replies / (int)$board_config['posts_per_page'])),

        'POST_IMG'  => $post_img,
        'REPLY_IMG' => $reply_img,

        'L_AUTHOR'              => $lang['Author'],
        'L_MESSAGE'             => $lang['Message'],
        'L_POSTED'              => $lang['Posted'],
        'L_POST_SUBJECT'        => $lang['Post_subject'],
        'L_VIEW_NEXT_TOPIC'     => $lang['View_next_topic'],
        'L_VIEW_PREVIOUS_TOPIC' => $lang['View_previous_topic'],
        'L_POST_NEW_TOPIC'      => $post_alt,
        'L_POST_REPLY_TOPIC'    => $reply_alt,
        'L_BACK_TO_TOP'         => $lang['Back_to_top'],
        'L_DISPLAY_POSTS'       => $lang['Display_posts'],
        'L_LOCK_TOPIC'          => $lang['Lock_topic'],
        'L_UNLOCK_TOPIC'        => $lang['Unlock_topic'],
        'L_MOVE_TOPIC'          => $lang['Move_topic'],
        'L_SPLIT_TOPIC'         => $lang['Split_topic'],
        'L_DELETE_TOPIC'        => $lang['Delete_topic'],
        'L_GOTO_PAGE'           => $lang['Goto_page'],

        'S_TOPIC_LINK'        => POST_TOPIC_URL,
        'S_SELECT_POST_DAYS'  => $select_post_days,
        'S_SELECT_POST_ORDER' => $select_post_order,
        'S_POST_DAYS_ACTION'  => append_sid("viewtopic.php?" . POST_TOPIC_URL . '=' . $topic_id . "&amp;start=$start"),
        'S_AUTH_LIST'         => $s_auth_can,
        'S_TOPIC_ADMIN'       => $topic_mod,
        'S_WATCH_TOPIC'       => $s_watching_topic,
        'S_WATCH_TOPIC_IMG'   => $s_watching_topic_img,

        'U_VIEW_TOPIC'       => append_sid("viewtopic.php?" . POST_TOPIC_URL . "=$topic_id&amp;start=$start&amp;postdays=$post_days&amp;postorder=$post_order&amp;highlight=$highlight"),
        'U_VIEW_FORUM'       => $view_forum_url,
        'U_VIEW_OLDER_TOPIC' => $view_prev_topic_url,
        'U_VIEW_NEWER_TOPIC' => $view_next_topic_url,
        'U_POST_NEW_TOPIC'   => $new_topic_url,
        'U_POST_REPLY_TOPIC' => $reply_topic_url
    ]
);

//
// Does this topic contain a poll?
//
if ( !empty($forum_topic_data->topic_vote) ) {
	$s_hidden_fields = '';

    $columns = [
        'vd.vote_id',
        'vd.vote_text',
        'vd.vote_start',
        'vd.vote_length',
        'vr.vote_option_id',
        'vr.vote_option_text',
        'vr.vote_result'
    ];

    $vote_info = dibi::select($columns)
        ->from(VOTE_DESC_TABLE)
        ->as('vd')
        ->from(VOTE_RESULTS_TABLE)
        ->as('vr')
        ->where('vd.topic_id = %i', $topic_id)
        ->where('vr.vote_id = vd.vote_id')
        ->orderBy('vr.vote_option_id', dibi::ASC)
        ->fetchAll();

    if (count($vote_info)) {
		$vote_options = count($vote_info);

		$vote_id = $vote_info[0]->vote_id;
		$vote_title = $vote_info[0]->vote_text;

        /**
         * check if user voted
         */
        $user_voted = dibi::select('vote_id')
            ->from(VOTE_USERS_TABLE)
            ->where('vote_id = %i', $vote_id)
            ->where('vote_user_id = %i', (int)$userdata['user_id'])
            ->fetch();

        if (isset($_GET['vote']) || isset($_POST['vote'])) {
			$view_result = ( ( isset($_GET['vote']) ? $_GET['vote'] : $_POST['vote'] ) == 'viewresult' ) ? true : 0;
		} else {
			$view_result = 0;
		}

		$poll_expired = $vote_info[0]->vote_length ? ( ($vote_info[0]->vote_start + $vote_info[0]->vote_length < time() ) ? true : 0 ) : 0;

        if ($user_voted || $view_result || $poll_expired || !$is_auth['auth_vote'] || $forum_topic_data->topic_status == TOPIC_LOCKED) {
            $template->set_filenames(['pollbox' => 'viewtopic_poll_result.tpl']);

            $vote_results_sum = 0;

			foreach ($vote_info as $vote_info_value) {
				$vote_results_sum += $vote_info_value->vote_result;
			}

			$vote_graphic = 0;
			$vote_graphic_max = count($images['voting_graphic']);

            foreach ($vote_info as $vote_info_value) {
				$vote_percent = ( $vote_results_sum > 0 ) ? $vote_info_value->vote_result / $vote_results_sum : 0;
				$vote_graphic_length = round($vote_percent * $board_config['vote_graphic_length']);

				$vote_graphic_img = $images['voting_graphic'][$vote_graphic];
				$vote_graphic = ($vote_graphic < $vote_graphic_max - 1) ? $vote_graphic + 1 : 0;

				if (count($orig_word)) {
                    $vote_info_value->vote_option_text = preg_replace($orig_word, $replacement_word, $vote_info_value->vote_option_text);
				}

                $template->assign_block_vars("poll_option",
                    [
                        'POLL_OPTION_CAPTION' => $vote_info_value->vote_option_text,
                        'POLL_OPTION_RESULT'  => $vote_info_value->vote_result,
                        'POLL_OPTION_PERCENT' => sprintf("%.1d%%", $vote_percent * 100),

                        'POLL_OPTION_IMG'       => $vote_graphic_img,
                        'POLL_OPTION_IMG_WIDTH' => $vote_graphic_length
                    ]
                );
            }

            $template->assign_vars(
                [
                    'L_TOTAL_VOTES' => $lang['Total_votes'],
                    'TOTAL_VOTES'   => $vote_results_sum
                ]
            );
        } else {
            $template->set_filenames(['pollbox' => 'viewtopic_poll_ballot.tpl']);

            foreach ($vote_info as $vote_info_value) {
                if (count($orig_word)) {
                    $vote_info_value->vote_option_text = preg_replace($orig_word, $replacement_word, $vote_info_value->vote_option_text);
				}

                $template->assign_block_vars("poll_option",
                    [
                        'POLL_OPTION_ID'      => $vote_info_value->vote_option_id,
                        'POLL_OPTION_CAPTION' => $vote_info_value->vote_option_text
                    ]
                );
            }

            $template->assign_vars(
                [
                    'L_SUBMIT_VOTE'  => $lang['Submit_vote'],
                    'L_VIEW_RESULTS' => $lang['View_results'],

                    'U_VIEW_RESULTS' => append_sid("viewtopic.php?" . POST_TOPIC_URL . "=$topic_id&amp;postdays=$post_days&amp;postorder=$post_order&amp;vote=viewresult")
                ]
            );

            $s_hidden_fields = '<input type="hidden" name="topic_id" value="' . $topic_id . '" /><input type="hidden" name="mode" value="vote" />';
		}

        if (count($orig_word)) {
			$vote_title = preg_replace($orig_word, $replacement_word, $vote_title);
		}

		$s_hidden_fields .= '<input type="hidden" name="sid" value="' . $userdata['session_id'] . '" />';

        $template->assign_vars(
            [
                'POLL_QUESTION' => $vote_title,

                'S_HIDDEN_FIELDS' => $s_hidden_fields,
                'S_POLL_ACTION'   => append_sid("posting.php?mode=vote&amp;" . POST_TOPIC_URL . "=$topic_id")
            ]
        );

        $template->assign_var_from_handle('POLL_DISPLAY', 'pollbox');
	}
}

//
// Update the topic view counter
//
dibi::update(TOPICS_TABLE, ['topic_views%sql' => 'topic_views + 1'])
    ->where('topic_id = %i', $topic_id)
    ->execute();

//
// Okay, let's do the loop, yeah come on baby let's do the loop
// and it goes like this ...
//
foreach ($posts as $i => $post) {
	$poster_id = $post->user_id;
	$poster = ( $poster_id == ANONYMOUS ) ? $lang['Guest'] : $post->username;

	$post_date = create_date($board_config['default_dateformat'], $post->post_time, $board_config['board_timezone']);

	$poster_posts = ( $post->user_id !== ANONYMOUS ) ? $lang['Posts'] . ': ' . $post->user_posts : '';

	$poster_from = ( $post->user_from && $post->user_id !== ANONYMOUS ) ? $lang['Location'] . ': ' . $post->user_from : '';

	$poster_joined = ( $post->user_id !== ANONYMOUS ) ? $lang['Joined'] . ': ' . create_date($lang['DATE_FORMAT'], $post->user_regdate, $board_config['board_timezone']) : '';

	$poster_avatar = '';

    if ($post->user_avatar_type && $poster_id !== ANONYMOUS && $post->user_allowavatar) {
		switch( $post->user_avatar_type ) {
			case USER_AVATAR_UPLOAD:
				$poster_avatar = $board_config['allow_avatar_upload'] ? '<img src="' . $board_config['avatar_path'] . '/' . $post->user_avatar . '" alt="" border="0" />' : '';
				break;
			case USER_AVATAR_REMOTE:
				$poster_avatar = $board_config['allow_avatar_remote'] ? '<img src="' . $post->user_avatar . '" alt="" border="0" />' : '';
				break;
			case USER_AVATAR_GALLERY:
				$poster_avatar = $board_config['allow_avatar_local'] ? '<img src="' . $board_config['avatar_gallery_path'] . '/' . $post->user_avatar . '" alt="" border="0" />' : '';
				break;
		}
	}

	//
	// Define the little post icon
	//
    if ($userdata['session_logged_in'] && $post->post_time > $userdata['user_lastvisit'] && $post->post_time > $topic_last_read) {
        $mini_post_img = $images['icon_minipost_new'];
        $mini_post_alt = $lang['New_post'];
    } else {
        $mini_post_img = $images['icon_minipost'];
        $mini_post_alt = $lang['Post'];
    }

	$mini_post_url = append_sid("viewtopic.php?" . POST_POST_URL . '=' . $post->post_id) . '#' . # $post->post_id;

	//
	// Generate ranks, set them to empty string initially.
	//
	$poster_rank = '';
	$rank_image = '';
    if ($post->user_id == ANONYMOUS) {
	    // WHAT WAS THERE???????
	}
	elseif ( $post->user_rank ) {
	    foreach ($ranks as $rank) {
	        if ($post->user_rank === $rank->rank_id && $rank->rank_special) {
	            $poster_rank = $rank->rank_title;
	            $rank_image = $rank->rank_image ? '<img src="' . $rank->rank_image . '" alt="' . $poster_rank . '" title="' . $poster_rank . '" border="0" /><br />' : '';
            }
        }
	} else {
		foreach ($ranks as $rank) {
		    if ( $post->user_posts >= $rank->rank_min && !$rank->rank_special) {
		        $poster_rank = $rank->rank_title;
		        $rank_image = $rank->rank_image ? '<img src="' . $rank->rank_image . '" alt="' . $poster_rank . '" title="' . $poster_rank . '" border="0" /><br />' : '';
            }
        }
	}

	//
	// Handle anon users posting with usernames
	//
    if ($poster_id === ANONYMOUS && $post->post_username !== '') {
		$poster = $post->post_username;
		$poster_rank = $lang['Guest'];
	}

	$temp_url = '';

    if ($poster_id !== ANONYMOUS) {
		$temp_url = append_sid("profile.php?mode=viewprofile&amp;" . POST_USERS_URL . "=$poster_id");
		$profile_img = '<a href="' . $temp_url . '"><img src="' . $images['icon_profile'] . '" alt="' . $lang['Read_profile'] . '" title="' . $lang['Read_profile'] . '" border="0" /></a>';
		$profile = '<a href="' . $temp_url . '">' . $lang['Read_profile'] . '</a>';

		$temp_url = append_sid("privmsg.php?mode=post&amp;" . POST_USERS_URL . "=$poster_id");
		$pm_img = '<a href="' . $temp_url . '"><img src="' . $images['icon_pm'] . '" alt="' . $lang['Send_private_message'] . '" title="' . $lang['Send_private_message'] . '" border="0" /></a>';
		$pm = '<a href="' . $temp_url . '">' . $lang['Send_private_message'] . '</a>';

        if (!empty($post->user_viewemail) || $is_auth['auth_mod']) {
			$email_uri = $board_config['board_email_form'] ? append_sid("profile.php?mode=email&amp;" . POST_USERS_URL .'=' . $poster_id) : 'mailto:' . $post->user_email;

			$email_img = '<a href="' . $email_uri . '"><img src="' . $images['icon_email'] . '" alt="' . $lang['Send_email'] . '" title="' . $lang['Send_email'] . '" border="0" /></a>';
			$email = '<a href="' . $email_uri . '">' . $lang['Send_email'] . '</a>';
		} else {
			$email_img = '';
			$email = '';
		}

		$www_img = $post->user_website ? '<a href="' . $post->user_website . '" target="_userwww"><img src="' . $images['icon_www'] . '" alt="' . $lang['Visit_website'] . '" title="' . $lang['Visit_website'] . '" border="0" /></a>' : '';
		$www = $post->user_website ? '<a href="' . $post->user_website . '" target="_userwww">' . $lang['Visit_website'] . '</a>' : '';

        if (!empty($post->user_icq)) {
			$icq_status_img = '<a href="http://wwp.icq.com/' . $post->user_icq . '#pager"><img src="http://web.icq.com/whitepages/online?icq=' . $post->user_icq . '&img=5" width="18" height="18" border="0" /></a>';
			$icq_img = '<a href="http://wwp.icq.com/scripts/search.dll?to=' . $post->user_icq . '"><img src="' . $images['icon_icq'] . '" alt="' . $lang['ICQ'] . '" title="' . $lang['ICQ'] . '" border="0" /></a>';
			$icq =  '<a href="http://wwp.icq.com/scripts/search.dll?to=' . $post->user_icq . '">' . $lang['ICQ'] . '</a>';
		} else {
			$icq_status_img = '';
			$icq_img = '';
			$icq = '';
		}

		$aim_img = $post->user_aim ? '<a href="aim:goim?screenname=' . $post->user_aim . '&amp;message=Hello+Are+you+there?"><img src="' . $images['icon_aim'] . '" alt="' . $lang['AIM'] . '" title="' . $lang['AIM'] . '" border="0" /></a>' : '';
		$aim = $post->user_aim ? '<a href="aim:goim?screenname=' . $post->user_aim . '&amp;message=Hello+Are+you+there?">' . $lang['AIM'] . '</a>' : '';

		$temp_url = append_sid("profile.php?mode=viewprofile&amp;" . POST_USERS_URL . "=$poster_id");
		$msn_img = $post->user_msnm ? '<a href="' . $temp_url . '"><img src="' . $images['icon_msnm'] . '" alt="' . $lang['MSNM'] . '" title="' . $lang['MSNM'] . '" border="0" /></a>' : '';
		$msn = $post->user_msnm ? '<a href="' . $temp_url . '">' . $lang['MSNM'] . '</a>' : '';

		$yim_img = $post->user_yim ? '<a href="http://edit.yahoo.com/config/send_webmesg?.target=' . $post->user_yim . '&amp;.src=pg"><img src="' . $images['icon_yim'] . '" alt="' . $lang['YIM'] . '" title="' . $lang['YIM'] . '" border="0" /></a>' : '';
		$yim = $post->user_yim ? '<a href="http://edit.yahoo.com/config/send_webmesg?.target=' . $post->user_yim . '&amp;.src=pg">' . $lang['YIM'] . '</a>' : '';
	} else {
		$profile_img = '';
		$profile = '';
		$pm_img = '';
		$pm = '';
		$email_img = '';
		$email = '';
		$www_img = '';
		$www = '';
		$icq_status_img = '';
		$icq_img = '';
		$icq = '';
		$aim_img = '';
		$aim = '';
		$msn_img = '';
		$msn = '';
		$yim_img = '';
		$yim = '';
	}

	$temp_url = append_sid("posting.php?mode=quote&amp;" . POST_POST_URL . "=" . $post->post_id);
	$quote_img = '<a href="' . $temp_url . '"><img src="' . $images['icon_quote'] . '" alt="' . $lang['Reply_with_quote'] . '" title="' . $lang['Reply_with_quote'] . '" border="0" /></a>';
	$quote = '<a href="' . $temp_url . '">' . $lang['Reply_with_quote'] . '</a>';

	$temp_url = append_sid("search.php?search_author=" . urlencode($post->username) . "&amp;showresults=posts");
	$search_img = '<a href="' . $temp_url . '"><img src="' . $images['icon_search'] . '" alt="' . sprintf($lang['Search_user_posts'], $post->username) . '" title="' . sprintf($lang['Search_user_posts'], $post->username) . '" border="0" /></a>';
	$search = '<a href="' . $temp_url . '">' . sprintf($lang['Search_user_posts'], $post->username) . '</a>';

    if (($userdata['user_id'] == $poster_id && $is_auth['auth_edit']) || $is_auth['auth_mod']) {
		$temp_url = append_sid("posting.php?mode=editpost&amp;" . POST_POST_URL . "=" . $post->post_id);
		$edit_img = '<a href="' . $temp_url . '"><img src="' . $images['icon_edit'] . '" alt="' . $lang['Edit_delete_post'] . '" title="' . $lang['Edit_delete_post'] . '" border="0" /></a>';
		$edit = '<a href="' . $temp_url . '">' . $lang['Edit_delete_post'] . '</a>';
	} else {
		$edit_img = '';
		$edit = '';
	}

    if ($is_auth['auth_mod']) {
		$temp_url = "modcp.php?mode=ip&amp;" . POST_POST_URL . "=" . $post->post_id . "&amp;" . POST_TOPIC_URL . "=" . $topic_id . "&amp;sid=" . $userdata['session_id'];
		$ip_img = '<a href="' . $temp_url . '"><img src="' . $images['icon_ip'] . '" alt="' . $lang['View_IP'] . '" title="' . $lang['View_IP'] . '" border="0" /></a>';
		$ip = '<a href="' . $temp_url . '">' . $lang['View_IP'] . '</a>';

		$temp_url        = "posting.php?mode=delete&amp;" . POST_POST_URL . "=" . $post->post_id . "&amp;sid=" . $userdata['session_id'];
		$delete_post_img = '<a href="' . $temp_url . '"><img src="' . $images['icon_delpost'] . '" alt="' . $lang['Delete_post'] . '" title="' . $lang['Delete_post'] . '" border="0" /></a>';
		$delete_post     = '<a href="' . $temp_url . '">' . $lang['Delete_post'] . '</a>';
	} else {
		$ip_img = '';
		$ip = '';

        if ($userdata['user_id'] == $poster_id && $is_auth['auth_delete'] && $forum_topic_data->topic_last_post_id == $post->post_id) {
			$temp_url        = "posting.php?mode=delete&amp;" . POST_POST_URL . "=" . $post->post_id . "&amp;sid=" . $userdata['session_id'];
			$delete_post_img = '<a href="' . $temp_url . '"><img src="' . $images['icon_delpost'] . '" alt="' . $lang['Delete_post'] . '" title="' . $lang['Delete_post'] . '" border="0" /></a>';
			$delete_post     = '<a href="' . $temp_url . '">' . $lang['Delete_post'] . '</a>';
		} else {
			$delete_post_img = '';
			$delete_post     = '';
		}
	}

	$post_subject = ( $post->post_subject !== '' ) ? $post->post_subject : '';

	$message = $post->post_text;
	$bbcode_uid = $post->bbcode_uid;

	$user_sig = ( $post->enable_sig && $post->user_sig !== '' && $board_config['allow_sig'] ) ? $post->user_sig : '';
	$user_sig_bbcode_uid = $post->user_sig_bbcode_uid;

	//
	// Note! The order used for parsing the message _is_ important, moving things around could break any
	// output
	//

	//
	// If the board has HTML off but the post has HTML
	// on then we process it, else leave it alone
	//
    if (!$board_config['allow_html'] || !$userdata['user_allowhtml']) {
        if ($user_sig !== '') {
            $user_sig = preg_replace('#(<)([\/]?.*?)(>)#is', "&lt;\\2&gt;", $user_sig);
        }

        if ($post->enable_html) {
            $message = preg_replace('#(<)([\/]?.*?)(>)#is', "&lt;\\2&gt;", $message);
        }
    }

	//
	// Parse message and/or sig for BBCode if reqd
	//
	if ($user_sig !== '' && $user_sig_bbcode_uid !== '') {
		$user_sig = $board_config['allow_bbcode'] ? bbencode_second_pass($user_sig, $user_sig_bbcode_uid) : preg_replace("/\:$user_sig_bbcode_uid/si", '', $user_sig);
	}

	if ($bbcode_uid !== '') {
		$message = $board_config['allow_bbcode'] ? bbencode_second_pass($message, $bbcode_uid) : preg_replace("/\:$bbcode_uid/si", '', $message);
	}

    if ($user_sig !== '') {
		$user_sig = make_clickable($user_sig);
	}
	
	$message = make_clickable($message);

	//
	// Parse smilies
	//
    if ($board_config['allow_smilies']) {
        if ($post->user_allowsmile && $user_sig !== '') {
            $user_sig = smilies_pass($user_sig);
        }

        if ($post->enable_smilies) {
            $message = smilies_pass($message);
        }
    }

	//
	// Highlight active words (primarily for search)
	//
	if ($highlight_match) {
		// This has been back-ported from 3.0 CVS
		$message = preg_replace('#(?!<.*)(?<!\w)(' . $highlight_match . ')(?!\w|[^<>]*>)#i', '<b style="color:#'.$theme['fontcolor3'].'">\1</b>', $message);
	}

	//
	// Replace naughty words
	//
	if (count($orig_word)) {
		$post_subject = preg_replace($orig_word, $replacement_word, $post_subject);

		if ($user_sig !== '') {
			$user_sig = str_replace('\"', '"', substr(@preg_replace('#(\>(((?>([^><]+|(?R)))*)\<))#se', "@preg_replace(\$orig_word, \$replacement_word, '\\0')", '>' . $user_sig . '<'), 1, -1));
		}

		$message = str_replace('\"', '"', substr(@preg_replace('#(\>(((?>([^><]+|(?R)))*)\<))#se', "@preg_replace(\$orig_word, \$replacement_word, '\\0')", '>' . $message . '<'), 1, -1));
	}

	//
	// Replace newlines (we use this rather than nl2br because
	// till recently it wasn't XHTML compliant)
	//
    if ($user_sig !== '') {
		$user_sig = '<br />_________________<br />' . str_replace("\n", "\n<br />\n", $user_sig);
	}

	$message = str_replace("\n", "\n<br />\n", $message);

	//
	// Editing information
	//
    if ($post->post_edit_count) {
		$l_edit_time_total = ( $post->post_edit_count == 1 ) ? $lang['Edited_time_total'] : $lang['Edited_times_total'];

		$l_edited_by = '<br /><br />' . sprintf($l_edit_time_total, $poster, create_date($board_config['default_dateformat'], $post->post_edit_time, $board_config['board_timezone']), $post->post_edit_count);
	} else {
		$l_edited_by = '';
	}

	//
	// Again this will be handled by the templating
	// code at some point
	//
	$row_color = ( !($i % 2) ) ? $theme['td_color1'] : $theme['td_color2'];
	$row_class = ( !($i % 2) ) ? $theme['td_class1'] : $theme['td_class2'];

    $template->assign_block_vars('postrow',
        [
            'ROW_COLOR'      => '#' . $row_color,
            'ROW_CLASS'      => $row_class,

            'RANK_IMAGE'     => $rank_image,

            'POSTER_NAME'    => $poster,
            'POSTER_RANK'    => $poster_rank,
            'POSTER_JOINED'  => $poster_joined,
            'POSTER_POSTS'   => $poster_posts,
            'POSTER_FROM'    => $poster_from,
            'POSTER_AVATAR'  => $poster_avatar,

            'POST_DATE'      => $post_date,
            'POST_SUBJECT'   => $post_subject,

            'MESSAGE'        => $message,
            'SIGNATURE'      => $user_sig,
            'EDITED_MESSAGE' => $l_edited_by,

            'MINI_POST_IMG' => $mini_post_img,

            'PROFILE_IMG' => $profile_img,
            'PROFILE'     => $profile,

            'SEARCH_IMG' => $search_img,
            'SEARCH'     => $search,

            'PM_IMG' => $pm_img,
            'PM'     => $pm,

            'EMAIL_IMG' => $email_img,
            'EMAIL'     => $email,

            'WWW_IMG' => $www_img,
            'WWW'     => $www,

            'ICQ_STATUS_IMG' => $icq_status_img,
            'ICQ_IMG'        => $icq_img,
            'ICQ'            => $icq,

            'AIM_IMG' => $aim_img,
            'AIM'     => $aim,

            'MSN_IMG' => $msn_img,
            'MSN'     => $msn,

            'YIM_IMG' => $yim_img,
            'YIM'     => $yim,

            'EDIT_IMG' => $edit_img,
            'EDIT'     => $edit,

            'QUOTE_IMG' => $quote_img,
            'QUOTE'     => $quote,

            'IP_IMG' => $ip_img,
            'IP'     => $ip,

            'DELETE_IMG' => $delete_post_img,
            'DELETE'     => $delete_post,

            'L_MINI_POST_ALT' => $mini_post_alt,

            'U_MINI_POST' => $mini_post_url,
            'U_POST_ID'   => $post->post_id
        ]
    );
}

$template->pparse('body');

include $phpbb_root_path . 'includes/page_tail.php';

?>