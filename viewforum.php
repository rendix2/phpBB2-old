<?php
/***************************************************************************
 *                               viewforum.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: viewforum.php 6772 2006-12-16 13:11:28Z acydburn $
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

$sep = DIRECTORY_SEPARATOR;
$phpbb_root_path = '.' . $sep;

require_once $phpbb_root_path . 'common.php';

//
// Start initial var setup
//
if (isset($_GET[POST_FORUM_URL]) || isset($_POST[POST_FORUM_URL])) {
    $forum_id = isset($_GET[POST_FORUM_URL]) ? (int)$_GET[POST_FORUM_URL] : (int)$_POST[POST_FORUM_URL];
} elseif (isset($_GET['forum'])) {
    $forum_id = (int)$_GET['forum'];
} else {
    $forum_id = '';
}

$start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
$start = $start < 0 ? 0 : $start;

if (isset($_GET['mark']) || isset($_POST['mark'])) {
    $mark_read = isset($_POST['mark']) ? $_POST['mark'] : $_GET['mark'];
} else {
    $mark_read = '';
}
//
// End initial var setup
//

//
// Check if the user has actually sent a forum ID with his/her request
// If not give them a nice error page.
//
if (!is_numeric($forum_id)) {
    message_die(GENERAL_MESSAGE, 'Forum_not_exist');
}

$forum = dibi::select('*')
    ->from(FORUMS_TABLE)
    ->where('forum_id = %i', $forum_id)
    ->fetch();

//
// If the query doesn't return any rows this isn't a valid forum. Inform
// the user.
//
if (!$forum) {
    message_die(GENERAL_MESSAGE, 'Forum_not_exist');
}

//
// Start session management
//
$userdata = init_userprefs($forum_id);
//
// End session management
//

// define cookie names
$topic_cookie_name     = $board_config['cookie_name'] . '_t';
$forum_cookie_name     = $board_config['cookie_name'] . '_f';
$forum_all_cookie_name = $board_config['cookie_name'] . '_f_all';

//
// Start auth check
//
$is_auth = Auth::authorize(AUTH_ALL, $forum_id, $userdata, $forum);

if (!$is_auth['auth_read'] || !$is_auth['auth_view']) {
    if (!$userdata['session_logged_in']) {
        $redirect = POST_FORUM_URL . "=$forum_id" . (isset($start) ? "&start=$start" : '');
        redirect(Session::appendSid("login.php?redirect=viewforum.php&$redirect", true));
    }
	//
	// The user is not authed to read this forum ...
	//
	$message = !$is_auth['auth_view'] ? $lang['Forum_not_exist'] : sprintf($lang['Sorry_auth_read'], $is_auth['auth_read_type']);

	message_die(GENERAL_MESSAGE, $message);
}
//
// End of auth check
//

//
// Handle marking posts
//
if ($mark_read === 'topics') {
    if ($userdata['session_logged_in']) {
        $last_post = dibi::select('MAX(post_time)')
            ->as('last_post')
            ->from(POSTS_TABLE)
            ->where('forum_id = %i', $forum_id)
            ->fetchSingle();

		if ($last_post) {
			$tracking_forums = isset($_COOKIE[$forum_cookie_name]) ? unserialize($_COOKIE[$forum_cookie_name]) : [];
			$tracking_topics = isset($_COOKIE[$topic_cookie_name]) ? unserialize($_COOKIE[$topic_cookie_name]) : [];

            if ((count($tracking_forums) + count($tracking_topics)) >= 150 && empty($tracking_forums[$forum_id])) {
                asort($tracking_forums);
                unset($tracking_forums[key($tracking_forums)]);
            }

            if ($last_post > $userdata['user_lastvisit']) {
				$tracking_forums[$forum_id] = time();

				setcookie(
                    $forum_cookie_name,
                    serialize($tracking_forums),
                    0,
                    $board_config['cookie_path'],
                    $board_config['cookie_domain'],
                    $board_config['cookie_secure']
                );
			}
		}

        $template->assignVars(
            [
                'META' => '<meta http-equiv="refresh" content="3;url=' . Session::appendSid('viewforum.php?' . POST_FORUM_URL . "=$forum_id") . '">'
            ]
        );
    }

    $message = $lang['Topics_marked_read'] . '<br /><br />' . sprintf($lang['Click_return_forum'], '<a href="' . Session::appendSid('viewforum.php?' . POST_FORUM_URL . "=$forum_id") . '">', '</a> ');
	message_die(GENERAL_MESSAGE, $message);
}
//
// End handle marking posts
//

$tracking_topics = isset($_COOKIE[$topic_cookie_name]) ? unserialize($_COOKIE[$topic_cookie_name]) : '';
$tracking_forums = isset($_COOKIE[$forum_cookie_name]) ? unserialize($_COOKIE[$forum_cookie_name]) : '';

//
// Do the forum Prune
//
if ($is_auth['auth_mod'] && $board_config['prune_enable'] && $forum->prune_next < time() && $forum->prune_enable) {
    require_once $phpbb_root_path . 'includes' . $sep . 'prune.php';
    require_once $phpbb_root_path . 'includes' . $sep . 'functions_admin.php';

    PruneClass::auto_prune($forum_id);
}
//
// End of forum prune
//

//
// Obtain list of moderators of each forum
// First users, then groups ... broken into two queries
//

$userModerators = dibi::select('u.user_id, u.username')
    ->from(AUTH_ACCESS_TABLE)
    ->as('aa')
    ->innerJoin(USER_GROUP_TABLE)
    ->as('ug')
    ->on('ug.group_id = aa.group_id')
    ->innerJoin(GROUPS_TABLE)
    ->as('g')
    ->on('g.group_id = aa.group_id')
    ->innerJoin(USERS_TABLE)
    ->as('u')
    ->on('u.user_id = ug.user_id')
    ->where('aa.forum_id = %i', $forum_id)
    ->where('aa.auth_mod = %i', 1)
    ->where('g.group_single_user = %i', 1)
    ->groupBy('u.user_id')
    ->groupBy('u.username')
    ->orderBy('u.user_id')
    ->fetchAll();

$moderators = [];

foreach ($userModerators as $moderator) {
    $moderators[] = '<a href="' . Session::appendSid('profile.php?mode=viewprofile&amp;' . POST_USERS_URL . '=' . $moderator->user_id) . '">' . htmlspecialchars($moderator->username, ENT_QUOTES) . '</a>';
}

$groupModerators = dibi::select('g.group_id, g.group_name')
    ->from(AUTH_ACCESS_TABLE)
    ->as('aa')
    ->innerJoin(USER_GROUP_TABLE)
    ->as('ug')
    ->on('ug.group_id = aa.group_id')
    ->innerJoin(GROUPS_TABLE)
    ->as('g')
    ->on('g.group_id = aa.group_id')
    ->where('aa.forum_id = %i', $forum_id)
    ->where('aa.auth_mod = %i', 1)
    ->where('g.group_single_user = %i', 0)
    ->where('g.group_type <> %i', GROUP_HIDDEN)
    ->groupBy('g.group_id')
    ->groupBy('g.group_name')
    ->orderBy('g.group_id')
    ->fetchAll();

foreach ($groupModerators as $moderator) {
    $moderators[] = '<a href="' . Session::appendSid('groupcp.php?' . POST_GROUPS_URL . '=' . $moderator->group_id) . '">' . htmlspecialchars($moderator->group_name, ENT_QUOTES) . '</a>';
}

$moderators_count = count($moderators);
	
$l_moderators = $moderators_count === 1 ? $lang['Moderator'] : $lang['Moderators'];
$forum_moderators = $moderators_count ? implode(', ', $moderators) : $lang['None'];
unset($moderators);

//
// Generate a 'Show topics in previous x days' select box. If the topicsdays var is sent
// then get it's value, find the number of topics with dates newer than it (to properly
// handle pagination) and alter the main query
//

if (!empty($_POST['topicdays']) || !empty($_GET['topicdays'])) {
	$topic_days    = !empty($_POST['topicdays'])       ? (int)$_POST['topicdays']   : (int)$_GET['topicdays'];
    $user_timezone = isset($userdata['user_timezone']) ? $userdata['user_timezone'] : $board_config['board_timezone'];

    $min_topic_time = new DateTime();
    $min_topic_time->setTimezone(new DateTimeZone($user_timezone));
    $min_topic_time->sub(new DateInterval('P'.$topic_days.'D'));
    $min_topic_time = $min_topic_time->getTimestamp();

	$forumTopics = dibi::select('COUNT(t.topic_id)')
        ->as('forum_topics')
        ->from(TOPICS_TABLE)
        ->as('t')
        ->innerJoin(POSTS_TABLE)
        ->as('p')
        ->on('p.post_id = t.topic_last_post_id')
        ->where('t.forum_id = %i', $forum_id)
        ->where('p.post_time >= %i', $min_topic_time)
        ->fetchSingle();

	$topicsCount       = $forumTopics ? $forumTopics : 1;
	$limit_topics_time = true;

    if (!empty($_POST['topicdays'])) {
		$start = 0;
	}
} else {
	$topicsCount = $forum->forum_topics ? $forum->forum_topics : 1;

	$limit_topics_time = false;
	$topic_days = 0;
}

$select_topic_days = Select::topicDays($lang, $topic_days);

//
// All announcement data, this keeps announcements
// on each viewforum page ...
//
$announcementTopics = dibi::select(['t.*', 'u.username', 'u.user_id'])
    ->select('u2.username')
    ->as('user2')
    ->select('u2.user_id')
    ->as('id2')
    ->select(['p.post_time', 'p.post_username'])
    ->from(TOPICS_TABLE)
    ->as('t')
    ->innerJoin(USERS_TABLE)
    ->as('u')
    ->on('t.topic_poster = u.user_id')
    ->innerJoin(POSTS_TABLE)
    ->as('p')
    ->on('p.post_id = t.topic_last_post_id')
    ->innerJoin(USERS_TABLE)
    ->as('u2')
    ->on('p.poster_id = u2.user_id')
    ->where('t.forum_id = %i', $forum_id)
    ->where('t.topic_type = %i', POST_ANNOUNCE)
    ->orderBy('t.topic_last_post_id', dibi::DESC)
    ->fetchAll();

$totalAnnouncements = count($announcementTopics);

//
// Grab all the basic data (all topics except announcements)
// for this forum
//
$basicTopics = dibi::select(['t.*', 'u.username', 'u.user_id'])
    ->select('u2.username')
    ->as('user2')
    ->select('u2.user_id')
    ->as('id2')
    ->select('p.post_username')
    ->select('p2.post_username')
    ->as('post_username2')
    ->select('p2.post_time ')
    ->from(TOPICS_TABLE)
    ->as('t')
    ->innerJoin(USERS_TABLE)
    ->as('u')
    ->on('t.topic_poster = u.user_id')

    ->innerJoin(POSTS_TABLE)
    ->as('p')
    ->on('p.post_id = t.topic_first_post_id')

    ->innerJoin(POSTS_TABLE)
    ->as('p2')
    ->on('p2.post_id = t.topic_last_post_id')

    ->innerJoin(USERS_TABLE)
    ->as('u2')
    ->on('u2.user_id = p2.poster_id')

    ->where('t.forum_id = %i', $forum_id)
    ->where('t.topic_type <> %i', POST_ANNOUNCE);

    if ($limit_topics_time) {
        $basicTopics->where('p.post_time >= %i', $min_topic_time);
    }

    $basicTopics = $basicTopics->orderBy('t.topic_type', dibi::DESC)
        ->orderBy('t.topic_last_post_id', dibi::DESC)
        ->limit($board_config['topics_per_page'])
        ->offset($start)
        ->fetchAll();

$totalBaseTopics = count($basicTopics);

$topics = array_merge($announcementTopics, $basicTopics);

//
// Total topics ...
//
$totalTopics = $totalBaseTopics + $totalAnnouncements;

//
// Define censored word matches
//
$orig_word = [];
$replacement_word = [];
obtain_word_list($orig_word, $replacement_word);
$count_orig_word = count($orig_word);

//
// Post URL generation for templating vars
//
$template->assignVars(
    [
        'L_DISPLAY_TOPICS' => $lang['Display_topics'],

        'U_POST_NEW_TOPIC' => Session::appendSid('posting.php?mode=newtopic&amp;' . POST_FORUM_URL . "=$forum_id"),

        'S_SELECT_TOPIC_DAYS' => $select_topic_days,
        'S_POST_DAYS_ACTION'  => Session::appendSid('viewforum.php?' . POST_FORUM_URL . '=' . $forum_id . "&amp;start=$start")
    ]
);

//
// User authorisation levels output
//
$s_auth_can  = ( $is_auth['auth_post']   ? $lang['Rules_post_can']   : $lang['Rules_post_cannot'] )   . '<br />';
$s_auth_can .= ( $is_auth['auth_reply']  ? $lang['Rules_reply_can']  : $lang['Rules_reply_cannot'] )  . '<br />';
$s_auth_can .= ( $is_auth['auth_edit']   ? $lang['Rules_edit_can']   : $lang['Rules_edit_cannot'] )   . '<br />';
$s_auth_can .= ( $is_auth['auth_delete'] ? $lang['Rules_delete_can'] : $lang['Rules_delete_cannot'] ) . '<br />';
$s_auth_can .= ( $is_auth['auth_vote']   ? $lang['Rules_vote_can']   : $lang['Rules_vote_cannot'] )   . '<br />';

if ($is_auth['auth_mod']) {
	$s_auth_can .= sprintf($lang['Rules_moderate'], '<a href="modcp.php?' . POST_FORUM_URL . "=$forum_id&amp;start=" . $start . '&amp;sid=' . $userdata['session_id'] . '">', '</a>');
}

//
// Dump out the page header and load viewforum template
//

if ($board_config['show_online']) {
    showOnline($forum_id, $userdata, $board_config, $theme, $lang, $storage, $template);
}

$page_title = $lang['View_forum'] . ' - ' . $forum->forum_name;

PageHelper::header($template, $userdata, $board_config, $lang, $images,  $theme, $page_title, $gen_simple_header);

$template->setFileNames(['body' => 'viewforum_body.tpl']);
make_jumpbox('viewforum.php');

$template->assignVars(
    [
        'FORUM_ID'   => $forum_id,
        'FORUM_NAME' => htmlspecialchars($forum->forum_name, ENT_QUOTES),
        'MODERATORS' => $forum_moderators,
        'POST_IMG'   => $forum->forum_status === FORUM_LOCKED ? $images['post_locked'] : $images['post_new'],

        'FOLDER_IMG'              => $images['folder'],
        'FOLDER_NEW_IMG'          => $images['folder_new'],
        'FOLDER_HOT_IMG'          => $images['folder_hot'],
        'FOLDER_HOT_NEW_IMG'      => $images['folder_hot_new'],
        'FOLDER_LOCKED_IMG'       => $images['folder_locked'],
        'FOLDER_LOCKED_NEW_IMG'   => $images['folder_locked_new'],
        'FOLDER_STICKY_IMG'       => $images['folder_sticky'],
        'FOLDER_STICKY_NEW_IMG'   => $images['folder_sticky_new'],
        'FOLDER_ANNOUNCE_IMG'     => $images['folder_announce'],
        'FOLDER_ANNOUNCE_NEW_IMG' => $images['folder_announce_new'],

        'L_TOPICS'              => $lang['Topics'],
        'L_REPLIES'             => $lang['Replies'],
        'L_VIEWS'               => $lang['Views'],
        'L_POSTS'               => $lang['Posts'],
        'L_LASTPOST'            => $lang['Last_Post'],
        'L_MODERATOR'           => $l_moderators,
        'L_MARK_TOPICS_READ'    => $lang['Mark_all_topics'],
        'L_POST_NEW_TOPIC'      => $forum->forum_status === FORUM_LOCKED ? $lang['Forum_locked'] : $lang['Post_new_topic'],
        'L_NO_NEW_POSTS'        => $lang['No_new_posts'],
        'L_NEW_POSTS'           => $lang['New_posts'],
        'L_NO_NEW_POSTS_LOCKED' => $lang['No_new_posts_locked'],
        'L_NEW_POSTS_LOCKED'    => $lang['New_posts_locked'],
        'L_NO_NEW_POSTS_HOT'    => $lang['No_new_posts_hot'],
        'L_NEW_POSTS_HOT'       => $lang['New_posts_hot'],
        'L_ANNOUNCEMENT'        => $lang['Post_Announcement'],
        'L_STICKY'              => $lang['Post_Sticky'],
        'L_POSTED'              => $lang['Posted'],
        'L_JOINED'              => $lang['Joined'],
        'L_AUTHOR'              => $lang['Author'],

        'S_AUTH_LIST' => $s_auth_can,

        'U_VIEW_FORUM' => Session::appendSid('viewforum.php?' . POST_FORUM_URL . "=$forum_id"),

        'U_MARK_READ' => Session::appendSid('viewforum.php?' . POST_FORUM_URL . "=$forum_id&amp;mark=topics")
    ]
);
//
// End header
//

//
// Okay, lets dump out the page ...
//
if ($totalBaseTopics) {
    foreach ($topics as $i => $topic) {
		$topic_id   = $topic->topic_id;
		$replies    = $topic->topic_replies;
		$topic_type = $topic->topic_type;

        $topic_title = $count_orig_word ? preg_replace($orig_word, $replacement_word, $topic->topic_title) : $topic->topic_title;

        if ($topic_type === POST_ANNOUNCE) {
            $topic_type = $lang['Topic_Announcement'] . ' ';
        } elseif ($topic_type === POST_STICKY) {
            $topic_type = $lang['Topic_Sticky'] . ' ';
        } else {
            $topic_type = '';
        }

        if ($topic->topic_vote) {
            $topic_type .= $lang['Topic_Poll'] . ' ';
        }

        if ($topic->topic_status === TOPIC_MOVED) {
			$topic_type = $lang['Topic_Moved'] . ' ';
			$topic_id = $topic->topic_moved_id;

			$folder_image =  $images['folder'];
			$folder_alt = $lang['Topics_Moved'];
			$newest_post_img = '';
		} else {
            if ($topic->topic_type === POST_ANNOUNCE) {
				$folder = $images['folder_announce'];
				$folder_new = $images['folder_announce_new'];
            } elseif ($topic->topic_type === POST_STICKY) {
				$folder = $images['folder_sticky'];
				$folder_new = $images['folder_sticky_new'];
            } elseif ($topic->topic_status === TOPIC_LOCKED) {
				$folder = $images['folder_locked'];
				$folder_new = $images['folder_locked_new'];
			} else {
				if ($replies >= $board_config['hot_threshold']) {
					$folder = $images['folder_hot'];
					$folder_new = $images['folder_hot_new'];
				} else {
					$folder = $images['folder'];
					$folder_new = $images['folder_new'];
				}
			}

			$newest_post_img = '';

			if ($userdata['session_logged_in'] && $topic->post_time > $userdata['user_lastvisit']) {
                if (!empty($tracking_topics) || !empty($tracking_forums) || isset($_COOKIE[$forum_all_cookie_name])) {
                    $unread_topics = true;

                    if (isset($tracking_topics[$topic_id]) && $tracking_topics[$topic_id] >= $topic->post_time) {
                        $unread_topics = false;
                    }

                    if (isset($tracking_forums[$forum_id]) && $tracking_forums[$forum_id] >= $topic->post_time) {
                        $unread_topics = false;
                    }

                    if (isset($_COOKIE[$forum_all_cookie_name]) && $_COOKIE[$forum_all_cookie_name] >= $topic->post_time) {
                        $unread_topics = false;
                    }

                    if ($unread_topics) {
                        $folder_image = $folder_new;
                        $folder_alt = $lang['New_posts'];

                        $newest_post_img = '<a href="' . Session::appendSid('viewtopic.php?' . POST_TOPIC_URL . "=$topic_id&amp;view=newest") . '"><img src="' . $images['icon_newest_reply'] . '" alt="' . $lang['View_newest_post'] . '" title="' . $lang['View_newest_post'] . '" border="0" /></a> ';
                    } else {
                        $folder_image = $folder;
                        $folder_alt = $topic->topic_status === TOPIC_LOCKED ? $lang['Topic_locked'] : $lang['No_new_posts'];

                        $newest_post_img = '';
                    }
                } else {
                    $folder_image = $folder_new;
                    $folder_alt = $topic->topic_status === TOPIC_LOCKED ? $lang['Topic_locked'] : $lang['New_posts'];

                    $newest_post_img = '<a href="' . Session::appendSid('viewtopic.php?' . POST_TOPIC_URL . "=$topic_id&amp;view=newest") . '"><img src="' . $images['icon_newest_reply'] . '" alt="' . $lang['View_newest_post'] . '" title="' . $lang['View_newest_post'] . '" border="0" /></a> ';
                }
            } else {
				$folder_image = $folder;
				$folder_alt = $topic->topic_status === TOPIC_LOCKED ? $lang['Topic_locked'] : $lang['No_new_posts'];

				$newest_post_img = '';
			}
		}

        // use pagination !!
        if (($replies + 1) > $board_config['posts_per_page']) {
			$total_pages = ceil( ( $replies + 1 ) / $board_config['posts_per_page'] );
			$goto_page = ' [ <img src="' . $images['icon_gotopost'] . '" alt="' . $lang['Goto_page'] . '" title="' . $lang['Goto_page'] . '" />' . $lang['Goto_page'] . ': ';

			$times = 1;
			
			for ($j = 0; $j < $replies + 1; $j += $board_config['posts_per_page']) {
				$goto_page .= '<a href="' . Session::appendSid('viewtopic.php?' . POST_TOPIC_URL . '=' . $topic_id . "&amp;start=$j") . '">' . $times . '</a>';
				
				if ($times === 1 && $total_pages > 4) {
					$goto_page .= ' ... ';
					$times = $total_pages - 3;
					$j += ( $total_pages - 4 ) * $board_config['posts_per_page'];
				} elseif ($times < $total_pages) {
					$goto_page .= ', ';
				}
				
				$times++;
			}
			
			$goto_page .= ' ] ';
		} else {
			$goto_page = '';
		}
		
		$view_topic_url = Session::appendSid('viewtopic.php?' . POST_TOPIC_URL . "=$topic_id");

		$topic_author = $topic->user_id !== ANONYMOUS ? '<a href="' . Session::appendSid('profile.php?mode=viewprofile&amp;' . POST_USERS_URL . '=' . $topic->user_id) . '">' : '';

		if ($topic->user_id !== ANONYMOUS) {
            $topic_author .=  $topic->username;
        } else {
		    if ($topic->post_username !== '') {
                $topic_author .= $topic->post_username;
            } else {
                $topic_author .= $lang['Guest'];
            }
        }

		$topic_author .= $topic->user_id !== ANONYMOUS ? '</a>' : '';

		$first_post_time = create_date($board_config['default_dateformat'], $topic->topic_time, $board_config['board_timezone']);
		$last_post_time  = create_date($board_config['default_dateformat'], $topic->post_time, $board_config['board_timezone']);

        if ($topic->id2 === ANONYMOUS) {
            $last_post_author = $topic->post_username2 !== '' ? $topic->post_username2 . ' ' : $lang['Guest'];
        } else {
            $last_post_author = '<a href="' . Session::appendSid('profile.php?mode=viewprofile&amp;' . POST_USERS_URL . '='  . $topic->id2) . '">' . $topic->user2 . '</a>';
        }

		$last_post_url = '<a href="' . Session::appendSid('viewtopic.php?' . POST_POST_URL . '=' . $topic->topic_last_post_id) . '#' . $topic->topic_last_post_id . '"><img src="' . $images['icon_latest_reply'] . '" alt="' . $lang['View_latest_post'] . '" title="' . $lang['View_latest_post'] . '" border="0" /></a>';
		
		$row_color = !($i % 2) ? $theme['td_color1'] : $theme['td_color2'];
		$row_class = !($i % 2) ? $theme['td_class1'] : $theme['td_class2'];

        $template->assignBlockVars('topicrow',
            [
                'ROW_COLOR'        => $row_color,
                'ROW_CLASS'        => $row_class,
                'FORUM_ID'         => $forum_id,
                'TOPIC_ID'         => $topic_id,
                'TOPIC_FOLDER_IMG' => $folder_image,
                'TOPIC_AUTHOR'     => $topic_author,
                'GOTO_PAGE'        => $goto_page,
                'REPLIES'          => $replies,
                'NEWEST_POST_IMG'  => $newest_post_img,
                'TOPIC_TITLE'      => $topic_title,
                'TOPIC_TYPE'       => $topic_type,
                'VIEWS'            => $topic->topic_views,
                'FIRST_POST_TIME'  => $first_post_time,
                'LAST_POST_TIME'   => $last_post_time,
                'LAST_POST_AUTHOR' => $last_post_author,
                'LAST_POST_IMG'    => $last_post_url,

                'L_TOPIC_FOLDER_ALT' => $folder_alt,

                'U_VIEW_TOPIC' => $view_topic_url
            ]
        );
    }

    $topicsCount -= $totalAnnouncements;

    $template->assignVars(
        [
            'PAGINATION'  => generate_pagination('viewforum.php?' . POST_FORUM_URL . "=$forum_id&amp;topicdays=$topic_days", $topicsCount, $board_config['topics_per_page'], $start),
            'PAGE_NUMBER' => sprintf($lang['Page_of'], floor($start / $board_config['topics_per_page']) + 1, ceil($topicsCount / $board_config['topics_per_page'])),

            'L_GOTO_PAGE' => $lang['Goto_page']
        ]
    );
} else {
	//
	// No topics
	//
	$no_topics_msg = $forum->forum_status === FORUM_LOCKED ? $lang['Forum_locked'] : $lang['No_topics_post_one'];
    $template->assignVars(['L_NO_TOPICS' => $no_topics_msg]);

    $template->assignBlockVars('switch_no_topics', []);

}

//
// Parse the page and print
//
$template->pparse('body');

//
// Page footer
//
PageHelper::footer($template, $userdata, $lang, $gen_simple_header);

?>