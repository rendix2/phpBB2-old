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

use Nette\Caching\Cache;

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 ***************************************************************************/

define('IN_PHPBB', true);

$sep = DIRECTORY_SEPARATOR;
$phpbb_root_path = '.' . DIRECTORY_SEPARATOR;

require_once $phpbb_root_path . 'common.php';
require_once $phpbb_root_path . 'includes' . $sep . 'bbcode.php';

//
// Start initial var setup
//
$topicId = $postId = 0;

// TODO no we will use just POST_TOPIC_URL - no 'topic'
if (isset($_GET[POST_TOPIC_URL])) {
    $topicId = (int)$_GET[POST_TOPIC_URL];
} elseif (isset($_GET['topic'])) {
    $topicId = (int)$_GET['topic'];
}

if (isset($_GET[POST_POST_URL])) {
    $postId = (int)$_GET[POST_POST_URL];
}

$start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
$start = $start < 0 ? 0 : $start;

if (!$topicId && !$postId) {
	message_die(GENERAL_MESSAGE, 'Topic_post_not_exist');
}

//
// Find topic id if user requested a newer
// or older topic
//
$cookieNameSid   = $board_config['cookie_name'] . '_sid';
$cookieNameTopic = $board_config['cookie_name'] . '_t';
$cookieNameForum = $board_config['cookie_name'] . '_f';

if (isset($_GET['view']) && empty($_GET[POST_POST_URL])) {
    if ($_GET['view'] === 'newest') {
		if (isset($_COOKIE[$cookieNameSid]) || isset($_GET['sid'])) {
			$sessionId = isset($_COOKIE[$cookieNameSid]) ? $_COOKIE[$cookieNameSid] : $_GET['sid'];

            if (!preg_match('/^[A-Za-z0-9]*$/', $sessionId)) {
                $sessionId = '';
            }

            if ($sessionId) {
			    $sessionPostId = dibi::select('p.post_id')
                    ->from(Tables::POSTS_TABLE)
                    ->as('p')
                    ->from(Tables::SESSIONS_TABLE)
                    ->as('s')
                    ->from(Tables::USERS_TABLE)
                    ->as('u')
                    ->where('s.session_id = %s', $sessionId)
                    ->where('u.user_id = s.session_user_id')
                    ->where('p.topic_id = %i', $topicId)
                    ->where('p.post_time >= u.user_lastvisit')
                    ->orderBy('p.post_time', dibi::ASC)
                    ->fetch();

                if (!$sessionPostId) {
                    message_die(GENERAL_MESSAGE, 'No_new_posts_last_visit');
                }

				$postId = $sessionPostId->post_id;

                if (isset($_GET['sid'])) {
                    redirect("viewtopic.php?sid=$sessionId&" . POST_POST_URL . "=$postId#$postId");
                } else {
                    redirect('viewtopic.php?' . POST_POST_URL . "=$postId#$postId");
                }
			}
		}

		redirect(Session::appendSid('viewtopic.php?' . POST_TOPIC_URL . "=$topicId", true));
	} elseif ($_GET['view'] === 'next' || $_GET['view'] === 'previous') {
		$sqlCondition = $_GET['view'] === 'next' ? '>'   : '<';
		$sqlOrdering  = $_GET['view'] === 'next' ? 'ASC' : 'DESC';

		$row = dibi::select('t.topic_id')
            ->from(Tables::TOPICS_TABLE)
            ->as('t')
            ->innerJoin(Tables::TOPICS_TABLE)
            ->as('t2')
            ->on('t.forum_id = t2.forum_id')
            ->where('t2.topic_id = %i', $topicId)
            ->where('t.topic_moved_id = %i', 0)
            ->where('t.topic_last_post_id '. $sqlCondition . ' t2.topic_last_post_id')
            ->orderBy('t.topic_last_post_id', $sqlOrdering)
            ->fetch();

        if ($row) {
			$topicId = (int)$row->topic_id;
		} else {
			$message = $_GET['view'] === 'next' ? 'No_newer_topics' : 'No_older_topics';
			message_die(GENERAL_MESSAGE, $message);
		}
	}
}

//
// This rather complex gaggle of code handles querying for topics but
// also allows for direct linking to a post (and the calculation of which
// page the post is on and the correct display of viewtopic)
//

if ($postId) {
    $columns = ['t.topic_id', 't.topic_title', 't.topic_status', 't.topic_replies', 't.topic_time', 't.topic_type',
                't.topic_vote', 't.topic_last_post_id', 'f.forum_name', 'f.forum_status', 'f.forum_id', 'f.auth_view',
                'f.auth_read', 'f.auth_post', 'f.auth_reply', 'f.auth_edit', 'f.auth_delete', 'f.auth_sticky',
                'f.auth_announce', 'f.auth_pollcreate', 'f.auth_vote', 'f.auth_attachments',
    ];

    $forum_topic_data = dibi::select($columns)
        ->select('COUNT(p2.post_id)')
        ->as('prev_posts')
        ->from(Tables::TOPICS_TABLE)
        ->as('t')
        ->innerJoin(Tables::FORUMS_TABLE)
        ->as('f')
        ->on('f.forum_id = t.forum_id')
        ->innerJoin(Tables::POSTS_TABLE)
        ->as('p')
        ->on('t.topic_id = p.topic_id')
        ->innerJoin(Tables::POSTS_TABLE)
        ->as('p2')
        ->on('p2.topic_id = p.topic_id')
        ->where('p.post_id = %i', $postId)
        ->where('p2.post_id <= %i', $postId)
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
} else {
    $columns = ['t.topic_id', 't.topic_title', 't.topic_status', 't.topic_replies', 't.topic_time', 't.topic_type',
                't.topic_vote', 't.topic_last_post_id', 'f.forum_name', 'f.forum_status', 'f.forum_id', 'f.auth_view',
                'f.auth_read', 'f.auth_post', 'f.auth_reply', 'f.auth_edit', 'f.auth_delete', 'f.auth_sticky',
                'f.auth_announce', 'f.auth_pollcreate', 'f.auth_vote', 'f.auth_attachments'
    ];

    $forum_topic_data = dibi::select($columns)
        ->from(Tables::TOPICS_TABLE)
        ->as('t')
        ->innerJoin(Tables::FORUMS_TABLE)
        ->as('f')
        ->on('f.forum_id = t.forum_id')
        ->where('t.topic_id = %i', $topicId)
        ->fetch();
}

if (!$forum_topic_data) {
    message_die(GENERAL_MESSAGE, 'Topic_post_not_exist');
}

$forumId = (int)$forum_topic_data->forum_id;

//
// Start session management
//
$userdata = init_userprefs($forumId);
//
// End session management
//

//
// Start auth check
//
$is_auth = Auth::authorize(Auth::AUTH_ALL, $forumId, $userdata, $forum_topic_data);

if (!$is_auth['auth_view'] || !$is_auth['auth_read']) {
    if (!$userdata['session_logged_in']) {
        $redirect = $postId ? POST_POST_URL . "=$postId" : POST_TOPIC_URL . "=$topicId";
        $redirect .= $start ? "&start=$start" : '';
        redirect(Session::appendSid("login.php?redirect=viewtopic.php&$redirect", true));
    }

	$message = $is_auth['auth_view'] ? sprintf($lang['Sorry_auth_read'], $is_auth['auth_read_type']) : $lang['Topic_post_not_exist'];

	message_die(GENERAL_MESSAGE, $message);
}
//
// End auth check
//

$forumName  = $forum_topic_data->forum_name;
$topicTitle = $forum_topic_data->topic_title;
$topicId    = (int)$forum_topic_data->topic_id;
$topicTime  = $forum_topic_data->topic_time;

if ($postId) {
	$start = floor(($forum_topic_data->prev_posts - 1) / (int)$board_config['posts_per_page']) * (int)$board_config['posts_per_page'];
}

//
// Is user watching this thread?
//
if ($userdata['session_logged_in']) {
	$canWatchTopic = true;

	$row = dibi::select('notify_status')
        ->from(Tables::TOPICS_WATCH_TABLE)
        ->where('topic_id = %i', $topicId)
        ->where('user_id = %i', $userdata['user_id'])
        ->fetch();

    if ($row) {
        if (isset($_GET['unwatch'])) {
            if ($_GET['unwatch'] === 'topic') {
				$isWatchingTopic = 0;

				$sqlPriority = Config::DBMS === 'mysql' ? 'LOW_PRIORITY' : '';

				dibi::delete(Tables::TOPICS_WATCH_TABLE)
                    ->setFlag($sqlPriority)
                    ->where('topic_id = %i', $topicId)
                    ->where('user_id = %i', $userdata['user_id'])
                    ->execute();
			}

            $template->assignVars(
                [
                    'META' => '<meta http-equiv="refresh" content="3;url=' . Session::appendSid('viewtopic.php?' . POST_TOPIC_URL . "=$topicId&amp;start=$start") . '">'
                ]
            );

            $message = $lang['No_longer_watching'] . '<br /><br />' . sprintf($lang['Click_return_topic'], '<a href="' . Session::appendSid('viewtopic.php?' . POST_TOPIC_URL . "=$topicId&amp;start=$start") . '">', '</a>');
			message_die(GENERAL_MESSAGE, $message);
		} else {
			$isWatchingTopic = true;

            if ($row->notify_status) {
                $sqlPriority = Config::DBMS === 'mysql' ? 'LOW_PRIORITY' : '';

				dibi::update(Tables::TOPICS_WATCH_TABLE, ['notify_status' => 0])
                    ->setFlag($sqlPriority)
                    ->where('topic_id = %i', $topicId)
                    ->where('user_id = %i', $userdata['user_id'])
                    ->execute();
			}
		}
	} else {
        if (isset($_GET['watch'])) {
            if ($_GET['watch'] === 'topic') {
				$isWatchingTopic = true;

				$sqlPriority = Config::DBMS === 'mysql' ? 'LOW_PRIORITY' : '';

                $insertData = [
                    'user_id' => $userdata['user_id'],
                    'topic_id' => $topicId,
                    'notify_status' => 0
                ];

				dibi::insert(Tables::TOPICS_WATCH_TABLE, $insertData)
                    ->setFlag($sqlPriority)
                    ->execute();
			}

            $template->assignVars(
                [
                    'META' => '<meta http-equiv="refresh" content="3;url=' . Session::appendSid('viewtopic.php?' . POST_TOPIC_URL . "=$topicId&amp;start=$start") . '">'
                ]
            );

            $message = $lang['You_are_watching'] . '<br /><br />' . sprintf($lang['Click_return_topic'], '<a href="' . Session::appendSid('viewtopic.php?' . POST_TOPIC_URL . "=$topicId&amp;start=$start") . '">', '</a>');
			message_die(GENERAL_MESSAGE, $message);
		} else {
			$isWatchingTopic = 0;
		}
	}
} else {
    if (isset($_GET['unwatch'])) {
        if ($_GET['unwatch'] === 'topic') {
			redirect(Session::appendSid('login.php?redirect=viewtopic.php&' . POST_TOPIC_URL . "=$topicId&unwatch=topic", true));
		}
	} else {
		$canWatchTopic   = 0;
		$isWatchingTopic = 0;
	}
}

//
// Generate a 'Show posts in previous x days' select box. If the postdays var is POSTed
// then get it's value, find the number of topics with dates newer than it (to properly
// handle pagination) and alter the main query
//

$time = null;

if (!empty($_POST['postdays']) || !empty($_GET['postdays'])) {
	$postDays = !empty($_POST['postdays']) ? (int)$_POST['postdays'] : (int)$_GET['postdays'];

    $userTimeZone = isset($userdata['user_timezone']) ? $userdata['user_timezone'] : $board_config['board_timezone'];

    $time = new DateTime();
    $time->setTimezone(new DateTimeZone($userTimeZone));
    $time->sub(new DateInterval('P1D'));

    $totalReplies = dibi::select('COUNT(p.post_id)')
        ->as('num_posts')
        ->from(Tables::TOPICS_TABLE)
        ->as('t')
        ->innerJoin(Tables::POSTS_TABLE)
        ->as('p')
        ->on('p.topic_id = t.topic_id')
        ->where('t.topic_id = %i', $topicId)
        ->where('p.post_time >= %i', $time->getTimestamp())
        ->fetchSingle();

	$limitPostsTime = true;

    if (!empty($_POST['postdays'])) {
		$start = 0;
	}
} else {
	$totalReplies = (int)$forum_topic_data->topic_replies + 1;

    $limitPostsTime = false;
	$postDays       = 0;
}

$selectPostDays = Select::postDays($lang, $postDays);

//
// Decide how to order the post display
//
if (!empty($_POST['postorder']) || !empty($_GET['postorder'])) {
	$postOrder     = !empty($_POST['postorder']) ? htmlspecialchars($_POST['postorder']) : htmlspecialchars($_GET['postorder']);
	$postTimeOrder = $postOrder === 'asc' ? 'ASC' : 'DESC';
} else {
	$postOrder     = 'asc';
	$postTimeOrder = 'ASC';
}

$selectPostOrder = Select::postOrder($lang, $postTimeOrder);

//
// Go ahead and pull all data for this topic
//
$columns = [
    'u.username',
    'u.user_id',
    'u.user_posts',
    'u.user_topics',
    'u.user_from',
    'u.user_website',
    'u.user_email',
    'u.user_regdate',
    'u.user_viewemail',
    'u.user_rank',
    'u.user_sig',
    'u.user_sig_bbcode_uid',
    'u.user_avatar',
    'u.user_avatar_type',
    'u.user_allowavatar',
    'u.user_allowsmile',
    'u.user_allow_viewonline',
    'u.user_session_time',
    'p.*',
    'pt.post_text',
    'pt.post_subject',
    'pt.bbcode_uid'
];

$posts = dibi::select($columns)
    ->from(Tables::POSTS_TABLE)
    ->as('p')
    ->innerJoin(Tables::USERS_TABLE)
    ->as('u')
    ->on('u.user_id = p.poster_id')
    ->innerJoin(Tables::POSTS_TEXT_TABLE)
    ->as('pt')
    ->on('pt.post_id = p.post_id')
    ->where('p.topic_id = %i', $topicId);

// todo check if time is added correctly
if ($limitPostsTime) {
    $posts->where('p.post_time >= %i', $time->getTimestamp());
}

$posts = $posts
    ->orderBy('p.post_id', $postTimeOrder)
    ->limit($board_config['posts_per_page'])
    ->offset((int)$start)
    ->fetchAll();

$totalPosts = count($posts);

if (!$totalPosts) {
    require_once $phpbb_root_path . 'includes' . $sep . 'functions_admin.php';

    sync('topic', $topicId);

    message_die(GENERAL_MESSAGE, $lang['No_posts_topic']);
}

$resync = false;

if ($forum_topic_data->topic_replies + 1 < $start + $totalPosts)  {
   $resync = true; 
} elseif ($start + $board_config['posts_per_page'] > $forum_topic_data->topic_replies)  { 
   $row_id = (int)$forum_topic_data->topic_replies % (int)$board_config['posts_per_page'];
   
   if ($posts[$row_id]->post_id !== $forum_topic_data->topic_last_post_id || $start + $totalPosts < $forum_topic_data->topic_replies) {
       $resync = true; 
   } 
}  elseif ($totalPosts < $board_config['posts_per_page']) {
   $resync = true;
} 

if ($resync) {
    require_once $phpbb_root_path . 'includes' . $sep . 'functions_admin.php';

    sync('topic', $topicId);

    $totalReplies = dibi::select('COUNT(post_id)')
        ->as('total')
        ->from(Tables::POSTS_TABLE)
        ->where('topic_id = %i', $topicId)
        ->fetchSingle();
}

$cache = new Cache($storage, Tables::RANKS_TABLE);
$key   = Tables::RANKS_TABLE . '_ordered_by_rank_special_rank_min';

$cachedRanks = $cache->load($key);

if ($cachedRanks !== null) {
    $ranks = $cachedRanks;
} else {
    $ranks = dibi::select('*')
        ->from(Tables::RANKS_TABLE)
        ->orderBy('rank_special')
        ->orderBy('rank_min')
        ->fetchAll();

    $cache->save($key, $ranks);
}

//
// Define censored word matches
//
$orig_word        = [];
$replacement_word = [];

obtain_word_list($orig_word, $replacement_word);

$count_orig_word = count($orig_word);

//
// Censor topic title
//
if ($count_orig_word) {
    $topicTitle = preg_replace($orig_word, $replacement_word, $topicTitle);
}

//
// Was a highlight request part of the URI?
//
$highLightMatch = $highLight = '';
if (isset($_GET['highlight'])) {
	// Split words and phrases
	$words = explode(' ', trim(htmlspecialchars($_GET['highlight'])));

	foreach ($words as $word) {
		if (trim($word) !== '') {
			$highLightMatch .= (($highLightMatch !== '') ? '|' : '') . str_replace('*', '\w*', preg_quote($word, '#'));
		}
	}
	
	unset($words);

	$highLight      = urlencode($_GET['highlight']);
	$highLightMatch = rtrim($highLightMatch, "\\");
}

//
// Post, reply and other URL generation for
// templating vars
//
$newTopicUrl          = Session::appendSid('posting.php?mode=newtopic&amp;' . POST_FORUM_URL . "=$forumId");
$replyTopicUrl        = Session::appendSid('posting.php?mode=reply&amp;' . POST_TOPIC_URL . "=$topicId");
$viewForumUrl         = Session::appendSid('viewforum.php?' . POST_FORUM_URL . "=$forumId");
$viewPreviousTopicUrl = Session::appendSid('viewtopic.php?' . POST_TOPIC_URL . "=$topicId&amp;view=previous");
$viewNextTopicUrl     = Session::appendSid('viewtopic.php?' . POST_TOPIC_URL . "=$topicId&amp;view=next");

$replyImage = $forum_topic_data->forum_status === FORUM_LOCKED || $forum_topic_data->topic_status === TOPIC_LOCKED ? $images['reply_locked'] : $images['reply_new'];
$reply_alt  = $forum_topic_data->forum_status === FORUM_LOCKED || $forum_topic_data->topic_status === TOPIC_LOCKED ? $lang['Topic_locked'] : $lang['Reply_to_topic'];

$postImage = $forum_topic_data->forum_status === FORUM_LOCKED ? $images['post_locked'] : $images['post_new'];
$postAlt   = $forum_topic_data->forum_status === FORUM_LOCKED ? $lang['Forum_locked'] : $lang['Post_new_topic'];

//
// Set a cookie for this topic
//
if ($userdata['session_logged_in']) {
	$trackingTopics = isset($_COOKIE[$cookieNameTopic]) ? unserialize($_COOKIE[$cookieNameTopic]) : [];
	$trackingForums = isset($_COOKIE[$cookieNameForum]) ? unserialize($_COOKIE[$cookieNameForum]) : [];

    if (!empty($trackingTopics[$topicId]) && !empty($trackingForums[$forumId])) {
        $topicLastRead = $trackingTopics[$topicId] > $trackingForums[$forumId] ? $trackingTopics[$topicId] : $trackingForums[$forumId];
    } elseif (!empty($trackingTopics[$topicId]) || !empty($trackingForums[$forumId])) {
        $topicLastRead = !empty($trackingTopics[$topicId]) ? $trackingTopics[$topicId] : $trackingForums[$forumId];
    } else {
        $topicLastRead = $userdata['user_lastvisit'];
    }

    if (count($trackingTopics) >= 150 && empty($trackingTopics[$topicId])) {
		asort($trackingTopics);
		unset($trackingTopics[key($trackingTopics)]);
	}

    $trackingTopics[$topicId] = time();

	setcookie(
	    $cookieNameTopic,
        serialize($trackingTopics),
        0,
        $board_config['cookie_path'],
        $board_config['cookie_domain'],
        isConnectionsSecure()
    );
}

//
// Load templates
//
$template->setFileNames(['body' => 'viewtopic_body.tpl']);
make_jumpbox('viewforum.php', $forumId);

//
// Output page header
//
$page_title = $lang['View_topic'] .' - ' . $topicTitle;

PageHelper::header($template, $userdata, $board_config, $lang, $images, $theme, $page_title, $gen_simple_header);

//
// User authorisation levels output
//
$s_auth_can  = ( $is_auth['auth_post']   ? $lang['Rules_post_can']   : $lang['Rules_post_cannot'] )   . '<br />';
$s_auth_can .= ( $is_auth['auth_reply']  ? $lang['Rules_reply_can']  : $lang['Rules_reply_cannot'] )  . '<br />';
$s_auth_can .= ( $is_auth['auth_edit']   ? $lang['Rules_edit_can']   : $lang['Rules_edit_cannot'] )   . '<br />';
$s_auth_can .= ( $is_auth['auth_delete'] ? $lang['Rules_delete_can'] : $lang['Rules_delete_cannot'] ) . '<br />';
$s_auth_can .= ( $is_auth['auth_vote']   ? $lang['Rules_vote_can']   : $lang['Rules_vote_cannot'] )   . '<br />';

$topic_mod = '';

if ($is_auth['auth_mod']) {
	$s_auth_can .= sprintf($lang['Rules_moderate'], '<a href="modcp.php?' . POST_FORUM_URL . "=$forumId&amp;sid=" . $userdata['session_id'] . '">', '</a>');

	$topic_mod .= '<a href="modcp.php?' . POST_TOPIC_URL . "=$topicId&amp;mode=delete&amp;sid=" . $userdata['session_id'] . '"><img src="' . $images['topic_mod_delete'] . '" alt="' . $lang['Delete_topic'] . '" title="' . $lang['Delete_topic'] . '" border="0" /></a>&nbsp;';

	$topic_mod .= '<a href="modcp.php?' . POST_TOPIC_URL . "=$topicId&amp;mode=move&amp;sid=" . $userdata['session_id'] . '"><img src="' . $images['topic_mod_move'] . '" alt="' . $lang['Move_topic'] . '" title="' . $lang['Move_topic'] . '" border="0" /></a>&nbsp;';

	$topic_mod .= ( $forum_topic_data->topic_status === TOPIC_UNLOCKED ) ? '<a href="modcp.php?' . POST_TOPIC_URL . "=$topicId&amp;mode=lock&amp;sid=" . $userdata['session_id'] . '"><img src="' . $images['topic_mod_lock'] . '" alt="' . $lang['Lock_topic'] . '" title="' . $lang['Lock_topic'] . '" border="0" /></a>&nbsp;' : '<a href="modcp.php?' . POST_TOPIC_URL . "=$topicId&amp;mode=unlock&amp;sid=" . $userdata['session_id'] . '"><img src="' . $images['topic_mod_unlock'] . '" alt="' . $lang['Unlock_topic'] . '" title="' . $lang['Unlock_topic'] . '" border="0" /></a>&nbsp;';

	$topic_mod .= '<a href="modcp.php?' . POST_TOPIC_URL . "=$topicId&amp;mode=split&amp;sid=" . $userdata['session_id'] . '"><img src="' . $images['topic_mod_split'] . '" alt="' . $lang['Split_topic'] . '" title="' . $lang['Split_topic'] . '" border="0" /></a>&nbsp;';
}

//
// Topic watch information
//
$s_watching_topic = '';
$s_watching_topic_img = '';
if ($canWatchTopic) {
	if ($isWatchingTopic) {
		$s_watching_topic = '<a href="viewtopic.php?' . POST_TOPIC_URL . "=$topicId&amp;unwatch=topic&amp;start=$start&amp;sid=" . $userdata['session_id'] . '">' . $lang['Stop_watching_topic'] . '</a>';
		$s_watching_topic_img = isset($images['topic_un_watch']) ? '<a href="viewtopic.php?' . POST_TOPIC_URL . "=$topicId&amp;unwatch=topic&amp;start=$start&amp;sid=" . $userdata['session_id'] . '"><img src="' . $images['topic_un_watch'] . '" alt="' . $lang['Stop_watching_topic'] . '" title="' . $lang['Stop_watching_topic'] . '" border="0"></a>' : '';
	} else {
		$s_watching_topic = '<a href="viewtopic.php?' . POST_TOPIC_URL . "=$topicId&amp;watch=topic&amp;start=$start&amp;sid=" . $userdata['session_id'] . '">' . $lang['Start_watching_topic'] . '</a>';
		$s_watching_topic_img = isset($images['Topic_watch']) ? '<a href="viewtopic.php?' . POST_TOPIC_URL . "=$topicId&amp;watch=topic&amp;start=$start&amp;sid=" . $userdata['session_id'] . '"><img src="' . $images['Topic_watch'] . '" alt="' . $lang['Start_watching_topic'] . '" title="' . $lang['Start_watching_topic'] . '" border="0"></a>' : '';
	}
}

//
// If we've got a hightlight set pass it on to pagination,
// I get annoyed when I lose my highlight after the first page.
//
$pagination = $highLight !== '' ? generate_pagination('viewtopic.php?' . POST_TOPIC_URL . "=$topicId&amp;postdays=$postDays&amp;postorder=$postOrder&amp;highlight=$highLight", $totalReplies, $board_config['posts_per_page'], $start) : generate_pagination('viewtopic.php?' . POST_TOPIC_URL . "=$topicId&amp;postdays=$postDays&amp;postorder=$postOrder", $totalReplies, $board_config['posts_per_page'], $start);

//
// Send vars to template
//
$template->assignVars(
    [
        'FORUM_ID'    => $forumId,
        'FORUM_NAME'  => htmlspecialchars($forumName, ENT_QUOTES),
        'TOPIC_ID'    => $topicId,
        'TOPIC_TITLE' => $topicTitle,
        'PAGINATION'  => $pagination,
        'PAGE_NUMBER' => sprintf($lang['Page_of'], floor($start / (int)$board_config['posts_per_page']) + 1, ceil($totalReplies / (int)$board_config['posts_per_page'])),

        'POST_IMG'  => $postImage,
        'REPLY_IMG' => $replyImage,

        'L_AUTHOR'              => $lang['Author'],
        'L_MESSAGE'             => $lang['Message'],
        'L_POSTED'              => $lang['Posted'],
        'L_POST_SUBJECT'        => $lang['Post_subject'],
        'L_VIEW_NEXT_TOPIC'     => $lang['View_next_topic'],
        'L_VIEW_PREVIOUS_TOPIC' => $lang['View_previous_topic'],
        'L_POST_NEW_TOPIC'      => $postAlt,
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
        'S_SELECT_POST_DAYS'  => $selectPostDays,
        'S_SELECT_POST_ORDER' => $selectPostOrder,
        'S_POST_DAYS_ACTION'  => Session::appendSid('viewtopic.php?' . POST_TOPIC_URL . '=' . $topicId . "&amp;start=$start"),
        'S_AUTH_LIST'         => $s_auth_can,
        'S_TOPIC_ADMIN'       => $topic_mod,
        'S_WATCH_TOPIC'       => $s_watching_topic,
        'S_WATCH_TOPIC_IMG'   => $s_watching_topic_img,

        'U_VIEW_TOPIC'       => Session::appendSid('viewtopic.php?' . POST_TOPIC_URL . "=$topicId&amp;start=$start&amp;postdays=$postDays&amp;postorder=$postOrder&amp;highlight=$highLight"),
        'U_VIEW_FORUM'       => $viewForumUrl,
        'U_VIEW_OLDER_TOPIC' => $viewPreviousTopicUrl,
        'U_VIEW_NEWER_TOPIC' => $viewNextTopicUrl,
        'U_POST_NEW_TOPIC'   => $newTopicUrl,
        'U_POST_REPLY_TOPIC' => $replyTopicUrl
    ]
);

//
// Does this topic contain a poll?
//
if (!empty($forum_topic_data->topic_vote)) {
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

    $votes = dibi::select($columns)
        ->from(Tables::VOTE_DESC_TABLE)
        ->as('vd')
        ->innerJoin(Tables::VOTE_RESULTS_TABLE)
        ->as('vr')
        ->on('vr.vote_id = vd.vote_id')
        ->where('vd.topic_id = %i', $topicId)
        ->orderBy('vr.vote_option_id', dibi::ASC)
        ->fetchAll();

    $totalVoteOptions = count($votes);

    if ($totalVoteOptions) {
		$voteId    = $votes[0]->vote_id;
		$voteTitle = $votes[0]->vote_text;

        /**
         * check if user voted
         */
        $userVoted = dibi::select('vote_id')
            ->from(Tables::VOTE_USERS_TABLE)
            ->where('vote_id = %i', $voteId)
            ->where('vote_user_id = %i', (int)$userdata['user_id'])
            ->fetch();

        if (isset($_GET['vote']) || isset($_POST['vote'])) {
            $viewResult = isset($_GET['vote']) ? $_GET['vote'] : $_POST['vote'];
            $viewResult = $viewResult === 'viewresult';
		} else {
			$viewResult = 0;
		}

        $pollExpired = $votes[0]->vote_length && ($votes[0]->vote_start + $votes[0]->vote_length < time());

        if ($userVoted || $viewResult || $pollExpired || !$is_auth['auth_vote'] || $forum_topic_data->topic_status === TOPIC_LOCKED) {
            $template->setFileNames(['pollbox' => 'viewtopic_poll_result.tpl']);

            $voteResultsSum = 0;

			foreach ($votes as $vote) {
				$voteResultsSum += $vote->vote_result;
			}

			$vote_graphic = 0;
			$vote_graphic_max = count($images['voting_graphic']);

            foreach ($votes as $vote) {
				$votePercent       = $voteResultsSum > 0 ? $vote->vote_result / $voteResultsSum : 0;
				$voteGraphicLength = round($votePercent * $board_config['vote_graphic_length']);

				$vote_graphic_img = $images['voting_graphic'][$vote_graphic];
				$vote_graphic = ($vote_graphic < $vote_graphic_max - 1) ? $vote_graphic + 1 : 0;

				if ($count_orig_word) {
                    $vote->vote_option_text = preg_replace($orig_word, $replacement_word, $vote->vote_option_text);
				}

                $template->assignBlockVars('poll_option',
                    [
                        'POLL_OPTION_CAPTION' => $vote->vote_option_text,
                        'POLL_OPTION_RESULT'  => $vote->vote_result,
                        'POLL_OPTION_PERCENT' => sprintf('%.1d%%', $votePercent * 100),

                        'POLL_OPTION_IMG'       => $vote_graphic_img,
                        'POLL_OPTION_IMG_WIDTH' => $voteGraphicLength
                    ]
                );
            }

            $template->assignVars(
                [
                    'L_TOTAL_VOTES' => $lang['Total_votes'],
                    'TOTAL_VOTES'   => $voteResultsSum
                ]
            );
        } else {
            $template->setFileNames(['pollbox' => 'viewtopic_poll_ballot.tpl']);

            foreach ($votes as $vote) {
                if ($count_orig_word) {
                    $vote->vote_option_text = preg_replace($orig_word, $replacement_word, $vote->vote_option_text);
				}

                $template->assignBlockVars('poll_option',
                    [
                        'POLL_OPTION_ID'      => $vote->vote_option_id,
                        'POLL_OPTION_CAPTION' => $vote->vote_option_text
                    ]
                );
            }

            $template->assignVars(
                [
                    'L_SUBMIT_VOTE'  => $lang['Submit_vote'],
                    'L_VIEW_RESULTS' => $lang['View_results'],

                    'U_VIEW_RESULTS' => Session::appendSid('viewtopic.php?' . POST_TOPIC_URL . "=$topicId&amp;postdays=$postDays&amp;postorder=$postOrder&amp;vote=viewresult")
                ]
            );

            $s_hidden_fields = '<input type="hidden" name="topic_id" value="' . $topicId . '" /><input type="hidden" name="mode" value="vote" />';
		}

        if ($count_orig_word) {
			$voteTitle = preg_replace($orig_word, $replacement_word, $voteTitle);
		}

		$s_hidden_fields .= '<input type="hidden" name="sid" value="' . $userdata['session_id'] . '" />';

        $template->assignVars(
            [
                'POLL_QUESTION' => $voteTitle,

                'S_HIDDEN_FIELDS' => $s_hidden_fields,
                'S_POLL_ACTION'   => Session::appendSid('posting.php?mode=vote&amp;' . POST_TOPIC_URL . "=$topicId")
            ]
        );

        $template->assignVarFromHandle('POLL_DISPLAY', 'pollbox');
	}
}

//
// Update the topic view counter
//
dibi::update(Tables::TOPICS_TABLE, ['topic_views%sql' => 'topic_views + 1'])
    ->where('topic_id = %i', $topicId)
    ->execute();

//
// Okay, let's do the loop, yeah come on baby let's do the loop
// and it goes like this ...
//
foreach ($posts as $i => $post) {
	$posterId       = $post->user_id;
	$posterUserName = $posterId === ANONYMOUS ? $lang['Guest'] : $post->username;

	$postDate = create_date($board_config['default_dateformat'], $post->post_time, $board_config['board_timezone']);

    $posterPosts  = $post->user_id !== ANONYMOUS ? $lang['Posts'] . ': ' . $post->user_posts   : '';
    $posterTopics = $post->user_id !== ANONYMOUS ? $lang['Topics'] . ': ' . $post->user_topics : '';

	$posterFrom = $post->user_from && $post->user_id !== ANONYMOUS ? $lang['Location'] . ': ' . htmlspecialchars($post->user_from, ENT_QUOTES) : '';

	$posterJoined = $post->user_id !== ANONYMOUS ? $lang['Joined'] . ': ' . create_date($lang['DATE_FORMAT'], $post->user_regdate, $board_config['board_timezone']) : '';

	$posterAvatar = '';

    if ($post->user_avatar_type && $posterId !== ANONYMOUS && $post->user_allowavatar) {
		switch( $post->user_avatar_type) {
			case USER_AVATAR_UPLOAD:
				$posterAvatar = $board_config['allow_avatar_upload'] ? '<img src="' . $board_config['avatar_path'] . '/' . $post->user_avatar . '" alt="" border="0" />' : '';
				break;
			case USER_AVATAR_REMOTE:
				$posterAvatar = $board_config['allow_avatar_remote'] ? '<img src="' . $post->user_avatar . '" alt="" border="0" />' : '';
				break;
			case USER_AVATAR_GALLERY:
				$posterAvatar = $board_config['allow_avatar_local'] ? '<img src="' . $board_config['avatar_gallery_path'] . '/' . $post->user_avatar . '" alt="" border="0" />' : '';
				break;
		}
	}

    // <!-- BEGIN Another Online/Offline indicator -->
    if (!$post->user_allow_viewonline && $userdata['user_level'] === ADMIN || $post->user_allow_viewonline) {
        $expiry_time = time() - ONLINE_TIME_DIFF;

        if ($post->user_session_time >= $expiry_time) {
            $user_onlinestatus = '<img src="' . $images['Online'] . '" alt="' . $lang['Online'] . '" title="' . $lang['Online'] . '" border="0" align="middle" />';

            if (!$post->user_allow_viewonline && $userdata['user_level'] === ADMIN) {
                $user_onlinestatus = '<img src="' . $images['Hidden_Admin'] . '" alt="' . $lang['Hidden'] . '" title="' . $lang['Hidden'] . '" border="0" align="middle" />';
            }
        } else {
            $user_onlinestatus = '<img src="' . $images['Offline'] . '" alt="' . $lang['Offline'] . '" title="' . $lang['Offline'] . '" border="0" />';

            if (!$post->user_allow_viewonline && $userdata['user_level'] === ADMIN) {
                $user_onlinestatus = '<img src="' . $images['Offline'] . '" alt="' . $lang['Hidden'] . '" title="' . $lang['Hidden'] . '" border="0" />';
            }
        }
    } else {
        $user_onlinestatus = '<img src="' . $images['Offline'] . '" alt="' . $lang['Offline'] . '" title="' . $lang['Offline'] . '" border="0" />';
    }

    if ($post->user_id === ANONYMOUS) {
        $user_onlinestatus = '';
    }
    // <!-- END Another Online/Offline indicator -->

	//
	// Define the little post icon
	//
    if ($userdata['session_logged_in'] && $post->post_time > $userdata['user_lastvisit'] && $post->post_time > $topicLastRead) {
        $miniPostImage = $images['icon_minipost_new'];
        $miniPostAlt   = $lang['New_post'];
    } else {
        $miniPostImage = $images['icon_minipost'];
        $miniPostAlt   = $lang['Post'];
    }

	$mini_post_url = Session::appendSid('viewtopic.php?' . POST_POST_URL . '=' . $post->post_id) . '#' . # $post->post_id;

	//
	// Generate ranks, set them to empty string initially.
	//
	$posterRank = '';
	$rankImage     = '';
    if ($post->user_id === ANONYMOUS) {
	    // WHAT WAS THERE???????
	} elseif ($post->user_rank) {
	    foreach ($ranks as $rank) {
	        if ($post->user_rank === $rank->rank_id && $rank->rank_special) {
	            $posterRank = htmlspecialchars($rank->rank_title, ENT_QUOTES);
	            $rankImage  = $rank->rank_image ? '<img src="' . $rank->rank_image . '" alt="' . $posterRank . '" title="' . $posterRank . '" border="0" /><br />' : '';
            }
        }
	} else {
		foreach ($ranks as $rank) {
		    if ($post->user_posts >= $rank->rank_min && !$rank->rank_special) {
                $posterRank = htmlspecialchars($rank->rank_title, ENT_QUOTES);
		        $rankImage  = $rank->rank_image ? '<img src="' . $rank->rank_image . '" alt="' . $posterRank . '" title="' . $posterRank . '" border="0" /><br />' : '';
            }
        }
	}

	//
	// Handle anon users posting with usernames
	//
    if ($posterId === ANONYMOUS && $post->post_username !== '') {
		$posterUserName = $post->post_username;
		$posterRank     = $lang['Guest'];
	}

	$temporaryUrl = '';

    if ($posterId !== ANONYMOUS) {
		$temporaryUrl = Session::appendSid('profile.php?mode=viewprofile&amp;' . POST_USERS_URL . "=$posterId");
		$profileImage = '<a href="' . $temporaryUrl . '"><img src="' . $images['icon_profile'] . '" alt="' . $lang['Read_profile'] . '" title="' . $lang['Read_profile'] . '" border="0" /></a>';
		$profile      = '<a href="' . $temporaryUrl . '">' . $lang['Read_profile'] . '</a>';

		$temporaryUrl = Session::appendSid('privmsg.php?mode=post&amp;' . POST_USERS_URL . "=$posterId");
		$pmImage      = '<a href="' . $temporaryUrl . '"><img src="' . $images['icon_pm'] . '" alt="' . $lang['Send_private_message'] . '" title="' . $lang['Send_private_message'] . '" border="0" /></a>';
		$pm           = '<a href="' . $temporaryUrl . '">' . $lang['Send_private_message'] . '</a>';

        if (!empty($post->user_viewemail) || $is_auth['auth_mod']) {
			$email_uri = $board_config['board_email_form'] ? Session::appendSid('profile.php?mode=email&amp;' . POST_USERS_URL .'=' . $posterId) : 'mailto:' . $post->user_email;

			$emailImage = '<a href="' . $email_uri . '"><img src="' . $images['icon_email'] . '" alt="' . $lang['Send_email'] . '" title="' . $lang['Send_email'] . '" border="0" /></a>';
			$email      = '<a href="' . $email_uri . '">' . $lang['Send_email'] . '</a>';
		} else {
			$emailImage = '';
			$email      = '';
		}

		$www_img = $post->user_website ? '<a href="' . $post->user_website . '" target="_userwww"><img src="' . $images['icon_www'] . '" alt="' . $lang['Visit_website'] . '" title="' . $lang['Visit_website'] . '" border="0" /></a>' : '';
		$www     = $post->user_website ? '<a href="' . $post->user_website . '" target="_userwww">' . $lang['Visit_website'] . '</a>' : '';
	} else {
		$profileImage = '';
		$profile      = '';
		$pmImage      = '';
		$pm           = '';
		$emailImage   = '';
		$email        = '';
		$www_img      = '';
		$www          = '';
	}

	$temporaryUrl = Session::appendSid('posting.php?mode=quote&amp;' . POST_POST_URL . '=' . $post->post_id);
	$quoteImage   = '<a href="' . $temporaryUrl . '"><img src="' . $images['icon_quote'] . '" alt="' . $lang['Reply_with_quote'] . '" title="' . $lang['Reply_with_quote'] . '" border="0" /></a>';
	$quote        = '<a href="' . $temporaryUrl . '">' . $lang['Reply_with_quote'] . '</a>';

	$temporaryUrl = Session::appendSid('search.php?search_author=' . urlencode($post->username) . '&amp;show_results=posts');
	$searchImage  = '<a href="' . $temporaryUrl . '"><img src="' . $images['icon_search'] . '" alt="' . sprintf($lang['Search_user_posts'], $post->username) . '" title="' . sprintf($lang['Search_user_posts'], $post->username) . '" border="0" /></a>';
	$search       = '<a href="' . $temporaryUrl . '">' . sprintf($lang['Search_user_posts'], $post->username) . '</a>';

    if (($userdata['user_id'] === $posterId && $is_auth['auth_edit']) || $is_auth['auth_mod']) {
		$temporaryUrl = Session::appendSid('posting.php?mode=editpost&amp;' . POST_POST_URL . '=' . $post->post_id);
		$editImage    = '<a href="' . $temporaryUrl . '"><img src="' . $images['icon_edit'] . '" alt="' . $lang['Edit_delete_post'] . '" title="' . $lang['Edit_delete_post'] . '" border="0" /></a>';
		$edit         = '<a href="' . $temporaryUrl . '">' . $lang['Edit_delete_post'] . '</a>';
	} else {
		$editImage = '';
		$edit      = '';
	}

    if ($is_auth['auth_mod']) {
		$temporaryUrl = 'modcp.php?mode=ip&amp;' . POST_POST_URL . '=' . $post->post_id . '&amp;' . POST_TOPIC_URL . '=' . $topicId . '&amp;sid=' . $userdata['session_id'];
		$ip_img       = '<a href="' . $temporaryUrl . '"><img src="' . $images['icon_ip'] . '" alt="' . $lang['View_IP'] . '" title="' . $lang['View_IP'] . '" border="0" /></a>';
		$ip           = '<a href="' . $temporaryUrl . '">' . $lang['View_IP'] . '</a>';

		$temporaryUrl    = 'posting.php?mode=delete&amp;' . POST_POST_URL . '=' . $post->post_id . '&amp;sid=' . $userdata['session_id'];
		$deletePostImage = '<a href="' . $temporaryUrl . '"><img src="' . $images['icon_delpost'] . '" alt="' . $lang['Delete_post'] . '" title="' . $lang['Delete_post'] . '" border="0" /></a>';
		$deletePost      = '<a href="' . $temporaryUrl . '">' . $lang['Delete_post'] . '</a>';
	} else {
		$ip_img = '';
		$ip = '';

        if ($userdata['user_id'] === $posterId && $is_auth['auth_delete'] && $forum_topic_data->topic_last_post_id === $post->post_id) {
			$temporaryUrl    = 'posting.php?mode=delete&amp;' . POST_POST_URL . '=' . $post->post_id . '&amp;sid=' . $userdata['session_id'];
			$deletePostImage = '<a href="' . $temporaryUrl . '"><img src="' . $images['icon_delpost'] . '" alt="' . $lang['Delete_post'] . '" title="' . $lang['Delete_post'] . '" border="0" /></a>';
			$deletePost      = '<a href="' . $temporaryUrl . '">' . $lang['Delete_post'] . '</a>';
		} else {
			$deletePostImage = '';
			$deletePost      = '';
		}
	}

	$postSubject = $post->post_subject;

	$message = $post->post_text;
	$bbcode_uid = $post->bbcode_uid;

	$userSignature       = $post->enable_sig && $post->user_sig !== '' && $board_config['allow_sig'] ? $post->user_sig : '';
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
        if ($userSignature !== '') {
            $userSignature = preg_replace('#(<)([\/]?.*?)(>)#is', "&lt;\\2&gt;", $userSignature);
        }

        if ($post->enable_html) {
            $message = preg_replace('#(<)([\/]?.*?)(>)#is', "&lt;\\2&gt;", $message);
        }
    }

	//
	// Parse message and/or sig for BBCode if reqd
	//
	if ($userSignature !== '' && $user_sig_bbcode_uid !== '') {
		$userSignature = $board_config['allow_bbcode'] ? bbencode_second_pass($userSignature, $user_sig_bbcode_uid) : preg_replace("/\:$user_sig_bbcode_uid/si", '', $userSignature);
	}

	if ($bbcode_uid !== '') {
		$message = $board_config['allow_bbcode'] ? bbencode_second_pass($message, $bbcode_uid) : preg_replace("/\:$bbcode_uid/si", '', $message);
	}

    if ($userSignature !== '') {
		$userSignature = make_clickable($userSignature);
	}
	
	$message = make_clickable($message);

	//
	// Parse smilies
	//
    if ($board_config['allow_smilies']) {
        if ($post->user_allowsmile && $userSignature !== '') {
            $userSignature = smilies_pass($userSignature);
        }

        if ($post->enable_smilies) {
            $message = smilies_pass($message);
        }
    }

	//
	// Highlight active words (primarily for search)
	//
	if ($highLightMatch) {
		// This has been back-ported from 3.0 CVS
		$message = preg_replace('#(?!<.*)(?<!\w)(' . $highLightMatch . ')(?!\w|[^<>]*>)#i', '<b style="color:#'. $theme['fontcolor3'].'">\1</b>', $message);
	}

	//
	// Replace naughty words
	//
	if ($count_orig_word) {
		$postSubject = preg_replace($orig_word, $replacement_word, $postSubject);

		if ($userSignature !== '') {
			$userSignature = str_replace('\"', '"', substr(@preg_replace('#(\>(((?>([^><]+|(?R)))*)\<))#se', "@preg_replace(\$orig_word, \$replacement_word, '\\0')", '>' . $userSignature . '<'), 1, -1));
		}

		$message = str_replace('\"', '"', substr(@preg_replace('#(\>(((?>([^><]+|(?R)))*)\<))#se', "@preg_replace(\$orig_word, \$replacement_word, '\\0')", '>' . $message . '<'), 1, -1));
	}

    if ($userSignature !== '') {
		$userSignature = $board_config['signature_delimiter'] . nl2br($userSignature);
	}

	$message = nl2br($message);

	//
	// Editing information
	//
    if ($post->post_edit_count) {
		$l_edit_time_total = $post->post_edit_count === 1 ? $lang['Edited_time_total'] : $lang['Edited_times_total'];

		$l_edited_by = '<br /><br />' . sprintf($l_edit_time_total, $posterUserName, create_date($board_config['default_dateformat'], $post->post_edit_time, $board_config['board_timezone']), $post->post_edit_count);
	} else {
		$l_edited_by = '';
	}

	//
	// Again this will be handled by the templating
	// code at some point
	//
	$rowColor = !($i % 2) ? $theme['td_color1'] : $theme['td_color2'];
	$rowClass     = !($i % 2) ? $theme['td_class1'] : $theme['td_class2'];

    $template->assignBlockVars('postrow',
        [
            'ROW_COLOR'      => '#' . $rowColor,
            'ROW_CLASS'      => $rowClass,

            'RANK_IMAGE'     => $rankImage,

            'POSTER_NAME'    => $posterUserName,
            'POSTER_RANK'    => $posterRank,
            'POSTER_JOINED'  => $posterJoined,
            'POSTER_POSTS'   => $posterPosts,
            'POSTER_TOPICS'  => $posterTopics,
            'POSTER_FROM'    => $posterFrom,
            'POSTER_AVATAR'  => $posterAvatar,

            // <!-- BEGIN Another Online/Offline indicator -->
            'POSTER_ONLINE' => $user_onlinestatus,
            // <!-- END Another Online/Offline indicator -->

            'POST_DATE'      => $postDate,
            'POST_SUBJECT'   => $postSubject,

            'MESSAGE'        => $message,
            'SIGNATURE'      => $userSignature,
            'EDITED_MESSAGE' => $l_edited_by,

            'MINI_POST_IMG' => $miniPostImage,

            'PROFILE_IMG' => $profileImage,
            'PROFILE'     => $profile,

            'SEARCH_IMG' => $searchImage,
            'SEARCH'     => $search,

            'PM_IMG' => $pmImage,
            'PM'     => $pm,

            'EMAIL_IMG' => $emailImage,
            'EMAIL'     => $email,

            'WWW_IMG' => $www_img,
            'WWW'     => $www,

            'EDIT_IMG' => $editImage,
            'EDIT'     => $edit,

            'QUOTE_IMG' => $quoteImage,
            'QUOTE'     => $quote,

            'IP_IMG' => $ip_img,
            'IP'     => $ip,

            'DELETE_IMG' => $deletePostImage,
            'DELETE'     => $deletePost,

            'L_MINI_POST_ALT' => $miniPostAlt,

            'U_MINI_POST' => $mini_post_url,
            'U_POST_ID'   => $post->post_id
        ]
    );
}

$template->pparse('body');

PageHelper::footer($template, $userdata, $lang, $gen_simple_header);

?>