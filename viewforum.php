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
$phpbb_root_path = './';

include $phpbb_root_path . 'common.php';

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
$start = ($start < 0) ? 0 : $start;

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

$forum_row = dibi::select('*')
    ->from(FORUMS_TABLE)
    ->where('forum_id = %i', $forum_id)
    ->fetch();

//
// If the query doesn't return any rows this isn't a valid forum. Inform
// the user.
//
if (!$forum_row) {
    message_die(GENERAL_MESSAGE, 'Forum_not_exist');
}

//
// Start session management
//
$userdata = session_pagestart($user_ip, $forum_id);
init_userprefs($userdata);
//
// End session management
//

// define cookie names
$topic_cookie_name = $board_config['cookie_name'] . '_t';
$forum_cookie_name = $board_config['cookie_name'] . '_f';
$forum_all_cookie_name = $board_config['cookie_name'] . '_f_all';

//
// Start auth check
//
$is_auth = [];
$is_auth = auth(AUTH_ALL, $forum_id, $userdata, $forum_row);

if (!$is_auth['auth_read'] || !$is_auth['auth_view']) {
    if (!$userdata['session_logged_in']) {
        $redirect = POST_FORUM_URL . "=$forum_id" . (isset($start) ? "&start=$start" : '');
        redirect(append_sid("login.php?redirect=viewforum.php&$redirect", true));
    }
	//
	// The user is not authed to read this forum ...
	//
	$message = ( !$is_auth['auth_view'] ) ? $lang['Forum_not_exist'] : sprintf($lang['Sorry_auth_read'], $is_auth['auth_read_type']);

	message_die(GENERAL_MESSAGE, $message);
}
//
// End of auth check
//

//
// Handle marking posts
//
if ($mark_read == 'topics') {
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

        $template->assign_vars(
            [
                'META' => '<meta http-equiv="refresh" content="3;url=' . append_sid("viewforum.php?" . POST_FORUM_URL . "=$forum_id") . '">'
            ]
        );
    }

    $message = $lang['Topics_marked_read'] . '<br /><br />' . sprintf($lang['Click_return_forum'], '<a href="' . append_sid("viewforum.php?" . POST_FORUM_URL . "=$forum_id") . '">', '</a> ');
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
if ($is_auth['auth_mod'] && $board_config['prune_enable']) {
    if ($forum_row['prune_next'] < time() && $forum_row['prune_enable']) {
        include $phpbb_root_path . 'includes/prune.php';
        require $phpbb_root_path . 'includes/functions_admin.php';
        auto_prune($forum_id);
    }
}
//
// End of forum prune
//

//
// Obtain list of moderators of each forum
// First users, then groups ... broken into two queries
//

$users_moderator_data = dibi::select('u.user_id, u.username ')
    ->from(AUTH_ACCESS_TABLE)
    ->as('aa')
    ->from(USER_GROUP_TABLE)
    ->as('ug')
    ->from(GROUPS_TABLE)
    ->as('g')
    ->from(USERS_TABLE)
    ->as('u')
    ->where('aa.forum_id = %i', $forum_id)
    ->where('aa.auth_mod = %i', 1)
    ->where('g.group_single_user = %i', 1)
    ->where('ug.group_id = aa.group_id')
    ->where('g.group_id = aa.group_id')
    ->where('u.user_id = ug.user_id')
    ->groupBy('u.user_id')
    ->groupBy('u.username')
    ->orderBy('u.user_id')
    ->fetchAll();

$moderators = [];

foreach ($users_moderator_data as $row) {
    $moderators[] = '<a href="' . append_sid("profile.php?mode=viewprofile&amp;" . POST_USERS_URL . "=" . $row->user_id) . '">' . $row->username . '</a>';
}

$group_moderator_data = dibi::select('g.group_id, g.group_name')
    ->from(AUTH_ACCESS_TABLE)
    ->as('aa')
    ->from(USER_GROUP_TABLE)
    ->as('ug')
    ->from(GROUPS_TABLE)
    ->as('g')
    ->where('aa.forum_id = %i', $forum_id)
    ->where('aa.auth_mod = %i', 1)
    ->where('g.group_single_user = %i', 0)
    ->where('g.group_type <> %i', GROUP_HIDDEN)
    ->where('ug.group_id = aa.group_id')
    ->where('g.group_id = aa.group_id')
    ->groupBy('g.group_id')
    ->groupBy('g.group_name')
    ->orderBy('g.group_id')
    ->fetchAll();

foreach ($group_moderator_data as $row) {
    $moderators[] = '<a href="' . append_sid("groupcp.php?" . POST_GROUPS_URL . "=" . $row->group_id) . '">' . $row->group_name . '</a>';
}
	
$l_moderators = ( count($moderators) == 1 ) ? $lang['Moderator'] : $lang['Moderators'];
$forum_moderators = count($moderators) ? implode(', ', $moderators) : $lang['None'];
unset($moderators);

//
// Generate a 'Show topics in previous x days' select box. If the topicsdays var is sent
// then get it's value, find the number of topics with dates newer than it (to properly
// handle pagination) and alter the main query
//
$previous_days = [
    0 => $lang['All_Topics'],
    1 => $lang['1_Day'],
    7 => $lang['7_Days'],
    14 => $lang['2_Weeks'],
    30 => $lang['1_Month'],
    90 => $lang['3_Months'],
    180 => $lang['6_Months'],
    364 => $lang['1_Year']
];

if (!empty($_POST['topicdays']) || !empty($_GET['topicdays']) ) {
	$topic_days = !empty($_POST['topicdays']) ? (int)$_POST['topicdays'] : (int)$_GET['topicdays'];
	$min_topic_time = time() - ($topic_days * 86400);

	$forum_topics = dibi::select('COUNT(t.topic_id)')
        ->as('forum_topics')
        ->from(TOPICS_TABLE)
        ->as('t')
        ->from(POSTS_TABLE)
        ->as('p')
        ->where('t.forum_id = %i', $forum_id)
        ->where('p.post_id = t.topic_last_post_id')
        ->where('p.post_time >= %i', $min_topic_time)
        ->fetchSingle();

	$topics_count = $forum_topics ? $forum_topics : 1;
	$limit_topics_time = true;

    if (!empty($_POST['topicdays'])) {
		$start = 0;
	}
} else {
	$topics_count = $forum_row['forum_topics'] ? $forum_row['forum_topics'] : 1;

	$limit_topics_time = false;
	$topic_days = 0;
}

$select_topic_days = '<select name="topicdays">';

foreach ($previous_days as $previous_day_key => $previous_day_value) {
	$selected = ($topic_days == $previous_day_key) ? ' selected="selected"' : '';

	$select_topic_days .= '<option value="' . $previous_day_key . '"' . $selected . '>' . $previous_day_value . '</option>';
}

$select_topic_days .= '</select>';


//
// All announcement data, this keeps announcements
// on each viewforum page ...
//
$topic_rowset = dibi::select(['t.*', 'u.username', 'u.user_id'])
    ->select('u2.username')
    ->as('user2')
    ->select('u2.user_id')
    ->as('id2')
    ->select(['p.post_time', 'p.post_username'])
    ->from(TOPICS_TABLE)
    ->as('t')
    ->from(USERS_TABLE)
    ->as('u')
    ->from(POSTS_TABLE)
    ->as('p')
    ->from(USERS_TABLE)
    ->as('u2')
    ->where('t.forum_id = %i', $forum_id)
    ->where('t.topic_poster = u.user_id')
    ->where('p.post_id = t.topic_last_post_id')
    ->where('p.poster_id = u2.user_id')
    ->where('t.topic_type = %i', POST_ANNOUNCE)
    ->orderBy('t.topic_last_post_id', dibi::DESC)
    ->fetchAll();

$total_announcements = count($topic_rowset);

//
// Grab all the basic data (all topics except announcements)
// for this forum
//
$temp_topics = dibi::select(['t.*', 'u.username', 'u.user_id'])
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
    ->from(USERS_TABLE)
    ->as('u')
    ->from(POSTS_TABLE)
    ->as('p')
    ->from(POSTS_TABLE)
    ->as('p2')
    ->from(USERS_TABLE)
    ->as('u2')
    ->where('t.forum_id = %i', $forum_id)
    ->where('t.topic_poster = u.user_id')
    ->where('p.post_id = t.topic_first_post_id')
    ->where('p2.post_id = t.topic_last_post_id')
    ->where('u2.user_id = p2.poster_id')
    ->where('t.topic_type <> %i', POST_ANNOUNCE);

    if ($limit_topics_time) {
        $temp_topics->where('p.post_time >= %i', $min_topic_time);
    }

    $temp_topics = $temp_topics->orderBy('t.topic_type', dibi::DESC)
        ->orderBy('t.topic_last_post_id', dibi::DESC)
        ->limit($board_config['topics_per_page'])
        ->offset($start)
        ->fetchAll();

$total_topics = count($temp_topics);

$topic_rowset = array_merge($topic_rowset, $temp_topics);

//
// Total topics ...
//
$total_topics += $total_announcements;

//
// Define censored word matches
//
$orig_word = [];
$replacement_word = [];
obtain_word_list($orig_word, $replacement_word);

//
// Post URL generation for templating vars
//
$template->assign_vars([
        'L_DISPLAY_TOPICS' => $lang['Display_topics'],

        'U_POST_NEW_TOPIC' => append_sid("posting.php?mode=newtopic&amp;" . POST_FORUM_URL . "=$forum_id"),

        'S_SELECT_TOPIC_DAYS' => $select_topic_days,
        'S_POST_DAYS_ACTION'  => append_sid("viewforum.php?" . POST_FORUM_URL . "=" . $forum_id . "&amp;start=$start")
    ]);

//
// User authorisation levels output
//
$s_auth_can = ( $is_auth['auth_post'] ? $lang['Rules_post_can'] : $lang['Rules_post_cannot'] ) . '<br />';
$s_auth_can .= ( $is_auth['auth_reply'] ? $lang['Rules_reply_can'] : $lang['Rules_reply_cannot'] ) . '<br />';
$s_auth_can .= ( $is_auth['auth_edit'] ? $lang['Rules_edit_can'] : $lang['Rules_edit_cannot'] ) . '<br />';
$s_auth_can .= ( $is_auth['auth_delete'] ? $lang['Rules_delete_can'] : $lang['Rules_delete_cannot'] ) . '<br />';
$s_auth_can .= ( $is_auth['auth_vote'] ? $lang['Rules_vote_can'] : $lang['Rules_vote_cannot'] ) . '<br />';

if ($is_auth['auth_mod']) {
	$s_auth_can .= sprintf($lang['Rules_moderate'], "<a href=\"modcp.php?" . POST_FORUM_URL . "=$forum_id&amp;start=" . $start . "&amp;sid=" . $userdata['session_id'] . '">', '</a>');
}

//
// Mozilla navigation bar
//
$nav_links['up'] = [
    'url'   => append_sid('index.php'),
    'title' => sprintf($lang['Forum_Index'], $board_config['sitename'])
];

//
// Dump out the page header and load viewforum template
//
define('SHOW_ONLINE', true);
$page_title = $lang['View_forum'] . ' - ' . $forum_row['forum_name'];
include $phpbb_root_path . 'includes/page_header.php';

$template->set_filenames(['body' => 'viewforum_body.tpl']);
make_jumpbox('viewforum.php');

$template->assign_vars(
    [
        'FORUM_ID'   => $forum_id,
        'FORUM_NAME' => $forum_row['forum_name'],
        'MODERATORS' => $forum_moderators,
        'POST_IMG'   => ($forum_row['forum_status'] == FORUM_LOCKED) ? $images['post_locked'] : $images['post_new'],

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
        'L_POST_NEW_TOPIC'      => ($forum_row['forum_status'] == FORUM_LOCKED) ? $lang['Forum_locked'] : $lang['Post_new_topic'],
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

        'U_VIEW_FORUM' => append_sid("viewforum.php?" . POST_FORUM_URL . "=$forum_id"),

        'U_MARK_READ' => append_sid("viewforum.php?" . POST_FORUM_URL . "=$forum_id&amp;mark=topics")
    ]
);
//
// End header
//

//
// Okay, lets dump out the page ...
//
if ($total_topics) {
    foreach ($topic_rowset as $i => $topic) {
		$topic_id = $topic->topic_id;

		$topic_title = count($orig_word) ? preg_replace($orig_word, $replacement_word, $topic->topic_title) : $topic->topic_title;
		$replies = $topic->topic_replies;
		$topic_type = $topic->topic_type;

        if ($topic_type == POST_ANNOUNCE) {
            $topic_type = $lang['Topic_Announcement'] . ' ';
        } elseif ($topic_type == POST_STICKY) {
            $topic_type = $lang['Topic_Sticky'] . ' ';
        } else {
            $topic_type = '';
        }

        if ($topic->topic_vote) {
            $topic_type .= $lang['Topic_Poll'] . ' ';
        }

        if ($topic->topic_status == TOPIC_MOVED) {
			$topic_type = $lang['Topic_Moved'] . ' ';
			$topic_id = $topic->topic_moved_id;

			$folder_image =  $images['folder'];
			$folder_alt = $lang['Topics_Moved'];
			$newest_post_img = '';
		} else {
            if ($topic->topic_type == POST_ANNOUNCE) {
				$folder = $images['folder_announce'];
				$folder_new = $images['folder_announce_new'];
            } elseif ($topic->topic_type == POST_STICKY) {
				$folder = $images['folder_sticky'];
				$folder_new = $images['folder_sticky_new'];
            } elseif ($topic->topic_status == TOPIC_LOCKED) {
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
			if ($userdata['session_logged_in'] ) {
				if ($topic->post_time > $userdata['user_lastvisit'] )  {
					if (!empty($tracking_topics) || !empty($tracking_forums) || isset($_COOKIE[$forum_all_cookie_name]) ) {
						$unread_topics = true;

						if (!empty($tracking_topics[$topic_id]) ) {
							if ($tracking_topics[$topic_id] >= $topic->post_time ) {
								$unread_topics = false;
							}
						}

						if (!empty($tracking_forums[$forum_id]) ) {
							if ($tracking_forums[$forum_id] >= $topic->post_time ) {
								$unread_topics = false;
							}
						}

						if (isset($_COOKIE[$forum_all_cookie_name]) ) {
							if ($_COOKIE[$forum_all_cookie_name] >= $topic->post_time ) {
								$unread_topics = false;
							}
						}

						if ($unread_topics ) {
							$folder_image = $folder_new;
							$folder_alt = $lang['New_posts'];

							$newest_post_img = '<a href="' . append_sid("viewtopic.php?" . POST_TOPIC_URL . "=$topic_id&amp;view=newest") . '"><img src="' . $images['icon_newest_reply'] . '" alt="' . $lang['View_newest_post'] . '" title="' . $lang['View_newest_post'] . '" border="0" /></a> ';
						} else {
							$folder_image = $folder;
							$folder_alt = $topic->topic_status == TOPIC_LOCKED ? $lang['Topic_locked'] : $lang['No_new_posts'];

							$newest_post_img = '';
						}
					} else {
						$folder_image = $folder_new;
						$folder_alt = $topic->topic_status == TOPIC_LOCKED ? $lang['Topic_locked'] : $lang['New_posts'];

						$newest_post_img = '<a href="' . append_sid("viewtopic.php?" . POST_TOPIC_URL . "=$topic_id&amp;view=newest") . '"><img src="' . $images['icon_newest_reply'] . '" alt="' . $lang['View_newest_post'] . '" title="' . $lang['View_newest_post'] . '" border="0" /></a> ';
					}
				} else {
					$folder_image = $folder;
					$folder_alt = $topic->topic_status == TOPIC_LOCKED ? $lang['Topic_locked'] : $lang['No_new_posts'];

					$newest_post_img = '';
				}
			} else {
				$folder_image = $folder;
				$folder_alt = $topic->topic_status == TOPIC_LOCKED ? $lang['Topic_locked'] : $lang['No_new_posts'];

				$newest_post_img = '';
			}
		}

        if (($replies + 1) > $board_config['posts_per_page']) {
			$total_pages = ceil( ( $replies + 1 ) / $board_config['posts_per_page'] );
			$goto_page = ' [ <img src="' . $images['icon_gotopost'] . '" alt="' . $lang['Goto_page'] . '" title="' . $lang['Goto_page'] . '" />' . $lang['Goto_page'] . ': ';

			$times = 1;
			
			for ($j = 0; $j < $replies + 1; $j += $board_config['posts_per_page']) {
				$goto_page .= '<a href="' . append_sid("viewtopic.php?" . POST_TOPIC_URL . "=" . $topic_id . "&amp;start=$j") . '">' . $times . '</a>';
				
				if ($times == 1 && $total_pages > 4 ) {
					$goto_page .= ' ... ';
					$times = $total_pages - 3;
					$j += ( $total_pages - 4 ) * $board_config['posts_per_page'];
				} elseif ( $times < $total_pages ) {
					$goto_page .= ', ';
				}
				
				$times++;
			}
			
			$goto_page .= ' ] ';
		} else {
			$goto_page = '';
		}
		
		$view_topic_url = append_sid("viewtopic.php?" . POST_TOPIC_URL . "=$topic_id");

		$topic_author = ( $topic->user_id != ANONYMOUS ) ? '<a href="' . append_sid("profile.php?mode=viewprofile&amp;" . POST_USERS_URL . '=' . $topic->user_id) . '">' : '';
		$topic_author .= ( $topic->user_id != ANONYMOUS ) ? $topic->username : ( ( $topic->post_username != '' ) ? $topic->post_username : $lang['Guest'] );

		$topic_author .= ( $topic->user_id != ANONYMOUS ) ? '</a>' : '';

		$first_post_time = create_date($board_config['default_dateformat'], $topic->topic_time, $board_config['board_timezone']);

		$last_post_time = create_date($board_config['default_dateformat'], $topic->post_time, $board_config['board_timezone']);

		$last_post_author = ( $topic->id2 == ANONYMOUS ) ? ( ($topic->post_username2 != '' ) ? $topic->post_username2 . ' ' : $lang['Guest'] . ' ' ) : '<a href="' . append_sid("profile.php?mode=viewprofile&amp;" . POST_USERS_URL . '='  . $topic->id2) . '">' . $topic->user2 . '</a>';

		$last_post_url = '<a href="' . append_sid("viewtopic.php?"  . POST_POST_URL . '=' . $topic->topic_last_post_id) . '#' . $topic->topic_last_post_id . '"><img src="' . $images['icon_latest_reply'] . '" alt="' . $lang['View_latest_post'] . '" title="' . $lang['View_latest_post'] . '" border="0" /></a>';

		$views = $topic->topic_views;
		
		$row_color = ( !($i % 2) ) ? $theme['td_color1'] : $theme['td_color2'];
		$row_class = ( !($i % 2) ) ? $theme['td_class1'] : $theme['td_class2'];

        $template->assign_block_vars('topicrow',
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
                'VIEWS'            => $views,
                'FIRST_POST_TIME'  => $first_post_time,
                'LAST_POST_TIME'   => $last_post_time,
                'LAST_POST_AUTHOR' => $last_post_author,
                'LAST_POST_IMG'    => $last_post_url,

                'L_TOPIC_FOLDER_ALT' => $folder_alt,

                'U_VIEW_TOPIC' => $view_topic_url
            ]
        );
    }

    $topics_count -= $total_announcements;

    $template->assign_vars(
        [
            'PAGINATION'  => generate_pagination("viewforum.php?" . POST_FORUM_URL . "=$forum_id&amp;topicdays=$topic_days", $topics_count, $board_config['topics_per_page'], $start),
            'PAGE_NUMBER' => sprintf($lang['Page_of'], floor($start / $board_config['topics_per_page']) + 1, ceil($topics_count / $board_config['topics_per_page'])),

            'L_GOTO_PAGE' => $lang['Goto_page']
        ]
    );
} else {
	//
	// No topics
	//
	$no_topics_msg = $forum_row['forum_status'] == FORUM_LOCKED ? $lang['Forum_locked'] : $lang['No_topics_post_one'];
    $template->assign_vars(['L_NO_TOPICS' => $no_topics_msg]);

    $template->assign_block_vars('switch_no_topics', []);

}

//
// Parse the page and print
//
$template->pparse('body');

//
// Page footer
//
include $phpbb_root_path . 'includes/page_tail.php';

?>