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

use phpBB2\Models\ForumsManager;

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
$forumId = '';

if (isset($_GET[POST_FORUM_URL]) || isset($_POST[POST_FORUM_URL])) {
    $forumId = isset($_GET[POST_FORUM_URL]) ? (int)$_GET[POST_FORUM_URL] : (int)$_POST[POST_FORUM_URL];
} elseif (isset($_GET['forum'])) {
    $forumId = (int)$_GET['forum'];
}

$start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
$start = $start < 0 ? 0 : $start;

$markRead = '';

if (isset($_GET['mark']) || isset($_POST['mark'])) {
    $markRead = isset($_POST['mark']) ? $_POST['mark'] : $_GET['mark'];
}
//
// End initial var setup
//

//
// Check if the user has actually sent a forum ID with his/her request
// If not give them a nice error page.
//
if (!is_numeric($forumId)) {
    message_die(GENERAL_MESSAGE, 'Forum_not_exist');
}

/**
 * @var ForumsManager $forumsManager
 */
$forumsManager = $container->getService('ForumsManager');

$forum = $forumsManager->getByPrimaryKey($forumId);

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
$userdata = init_userprefs($forumId);
//
// End session management
//

// define cookie names
$topicCookieName    = $board_config['cookie_name'] . '_t';
$forumCookieName    = $board_config['cookie_name'] . '_f';
$forumAllCookieName = $board_config['cookie_name'] . '_f_all';

//
// Start auth check
//
$is_auth = Auth::authorize(Auth::AUTH_ALL, $forumId, $userdata, $forum);

if (!$is_auth['auth_read'] || !$is_auth['auth_view']) {
    if (!$userdata['session_logged_in']) {
        $redirect = POST_FORUM_URL . "=$forumId" . (isset($start) ? "&start=$start" : '');
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
if ($markRead === 'topics') {
    if ($userdata['session_logged_in']) {
        $lastPost = dibi::select('MAX(post_time)')
            ->as('last_post')
            ->from(Tables::POSTS_TABLE)
            ->where('[forum_id] = %i', $forumId)
            ->fetchSingle();

		if ($lastPost) {
			$trackingForums = isset($_COOKIE[$forumCookieName]) ? unserialize($_COOKIE[$forumCookieName]) : [];
			$trackingTopics = isset($_COOKIE[$topicCookieName]) ? unserialize($_COOKIE[$topicCookieName]) : [];

            if ((count($trackingForums) + count($trackingTopics)) >= 150 && empty($trackingForums[$forumId])) {
                asort($trackingForums);
                unset($trackingForums[key($trackingForums)]);
            }

            if ($lastPost > $userdata['user_last_visit']) {
                $trackingForums[$forumId] = time();

				setcookie(
                    $forumCookieName,
                    serialize($trackingForums),
                    0,
                    $board_config['cookie_path'],
                    $board_config['cookie_domain'],
                    isConnectionsSecure()
                );
			}
		}

        $template->assignVars(
            [
                'META' => '<meta http-equiv="refresh" content="3;url=' . Session::appendSid('viewforum.php?' . POST_FORUM_URL . "=$forumId") . '">'
            ]
        );
    }

    $message  = $lang['Topics_marked_read'] . '<br /><br />';
    $message .= sprintf($lang['Click_return_forum'], '<a href="' . Session::appendSid('viewforum.php?' . POST_FORUM_URL . "=$forumId") . '">', '</a> ');

	message_die(GENERAL_MESSAGE, $message);
}
//
// End handle marking posts
//

$trackingTopics = isset($_COOKIE[$topicCookieName]) ? unserialize($_COOKIE[$topicCookieName]) : '';
$trackingForums = isset($_COOKIE[$forumCookieName]) ? unserialize($_COOKIE[$forumCookieName]) : '';

//
// Do the forum Prune
//
if ($is_auth['auth_mod'] && $board_config['prune_enable'] && $forum->prune_next < time() && $forum->prune_enable) {
    require_once $phpbb_root_path . 'includes' . $sep . 'functions_admin.php';

    Prune::autoPrune($forumId);
}
//
// End of forum prune
//

//
// Obtain list of moderators of each forum
// First users, then groups ... broken into two queries
//

$userModerators = dibi::select('u.user_id, u.username')
    ->from(Tables::AUTH_ACCESS_TABLE)
    ->as('aa')
    ->innerJoin(Tables::USERS_GROUPS_TABLE)
    ->as('ug')
    ->on('[ug.group_id] = [aa.group_id]')
    ->innerJoin(Tables::GROUPS_TABLE)
    ->as('g')
    ->on('[g.group_id] = [aa.group_id]')
    ->innerJoin(Tables::USERS_TABLE)
    ->as('u')
    ->on('[u.user_id] = [ug.user_id]')
    ->where('[aa.forum_id] = %i', $forumId)
    ->where('[aa.auth_mod] = %i', 1)
    ->where('[g.group_single_user] = %i', 1)
    ->groupBy('u.user_id')
    ->groupBy('u.username')
    ->orderBy('u.user_id')
    ->fetchAll();

$moderators = [];

foreach ($userModerators as $moderator) {
    $moderators[] = '<a href="' . Session::appendSid('profile.php?mode=viewprofile&amp;' . POST_USERS_URL . '=' . $moderator->user_id) . '">' . htmlspecialchars($moderator->username, ENT_QUOTES) . '</a>';
}

$groupModerators = dibi::select('g.group_id, g.group_name')
    ->from(Tables::AUTH_ACCESS_TABLE)
    ->as('aa')
    ->innerJoin(Tables::USERS_GROUPS_TABLE)
    ->as('ug')
    ->on('[ug.group_id] = [aa.group_id]')
    ->innerJoin(Tables::GROUPS_TABLE)
    ->as('g')
    ->on('[g.group_id] = [aa.group_id]')
    ->where('[aa.forum_id] = %i', $forumId)
    ->where('[aa.auth_mod] = %i', 1)
    ->where('[g.group_single_user] = %i', 0)
    ->where('[g.group_type] <> %i', GROUP_HIDDEN)
    ->groupBy('g.group_id')
    ->groupBy('g.group_name')
    ->orderBy('g.group_id')
    ->fetchAll();

foreach ($groupModerators as $moderator) {
    $moderators[] = '<a href="' . Session::appendSid('groupcp.php?' . POST_GROUPS_URL . '=' . $moderator->group_id) . '">' . htmlspecialchars($moderator->group_name, ENT_QUOTES) . '</a>';
}

$moderatorsCount = count($moderators);
	
$l_moderators    = $moderatorsCount === 1 ? $lang['Moderator'] : $lang['Moderators'];
$forumModerators = $moderatorsCount ? implode(', ', $moderators) : $lang['None'];
unset($moderators);

//
// Generate a 'Show topics in previous x days' select box. If the topicsdays var is sent
// then get it's value, find the number of topics with dates newer than it (to properly
// handle pagination) and alter the main query
//
if (!empty($_POST['topicdays']) || !empty($_GET['topicdays'])) {
	$topicDays     = !empty($_POST['topicdays'])       ? (int)$_POST['topicdays']   : (int)$_GET['topicdays'];
    $user_timezone = isset($userdata['user_timezone']) ? $userdata['user_timezone'] : $board_config['board_timezone'];

    $minTopicTime = new DateTime();
    $minTopicTime->setTimezone(new DateTimeZone($user_timezone));
    $minTopicTime->sub(new DateInterval('P'.$topicDays.'D'));
    $minTopicTime = $minTopicTime->getTimestamp();

	$forumTopics = dibi::select('COUNT(t.topic_id)')
        ->as('forum_topics')
        ->from(Tables::TOPICS_TABLE)
        ->as('t')
        ->innerJoin(Tables::POSTS_TABLE)
        ->as('p')
        ->on('[p.post_id] = [t.topic_last_post_id]')
        ->where('[t.forum_id] = %i', $forumId)
        ->where('[p.post_time] >= %i', $minTopicTime)
        ->fetchSingle();

	$topicsCount     = $forumTopics ? $forumTopics : 1;
	$limitTopicsTime = true;

    if (!empty($_POST['topicdays'])) {
		$start = 0;
	}
} else {
	$topicsCount = $forum->forum_topics ? $forum->forum_topics : 1;

	$limitTopicsTime = false;
	$topicDays       = 0;
}

$select_topic_days = Select::topicDays($lang, $topicDays);

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
    ->from(Tables::TOPICS_TABLE)
    ->as('t')
    ->innerJoin(Tables::USERS_TABLE)
    ->as('u')
    ->on('[t.topic_poster] = [u.user_id]')
    ->innerJoin(Tables::POSTS_TABLE)
    ->as('p')
    ->on('[p.post_id] = [t.topic_last_post_id]')
    ->innerJoin(Tables::USERS_TABLE)
    ->as('u2')
    ->on('[p.poster_id] = [u2.user_id]')
    ->where('[t.forum_id] = %i', $forumId)
    ->where('[t.topic_type] = %i', POST_ANNOUNCE)
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
    ->from(Tables::TOPICS_TABLE)
    ->as('t')
    ->innerJoin(Tables::USERS_TABLE)
    ->as('u')
    ->on('[t.topic_poster] = [u.user_id]')
    ->innerJoin(Tables::POSTS_TABLE)
    ->as('p')
    ->on('[p.post_id] = [t.topic_first_post_id]')
    ->innerJoin(Tables::POSTS_TABLE)
    ->as('p2')
    ->on('[p2.post_id] = [t.topic_last_post_id]')
    ->innerJoin(Tables::USERS_TABLE)
    ->as('u2')
    ->on('[u2.user_id] = [p2.poster_id]')
    ->where('[t.forum_id] = %i', $forumId)
    ->where('[t.topic_type] <> %i', POST_ANNOUNCE);

    if ($limitTopicsTime) {
        $basicTopics->where('[p.post_time] >= %i', $minTopicTime);
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

        'U_POST_NEW_TOPIC' => Session::appendSid('posting.php?mode=newtopic&amp;' . POST_FORUM_URL . "=$forumId"),

        'S_SELECT_TOPIC_DAYS' => $select_topic_days,
        'S_POST_DAYS_ACTION'  => Session::appendSid('viewforum.php?' . POST_FORUM_URL . '=' . $forumId . "&amp;start=$start")
    ]
);

//
// User authorisation levels output
//
$s_auth_can  = $is_auth['auth_post']   ? $lang['Rules_post_can']   : $lang['Rules_post_cannot']   . '<br />';
$s_auth_can .= $is_auth['auth_reply']  ? $lang['Rules_reply_can']  : $lang['Rules_reply_cannot']  . '<br />';
$s_auth_can .= $is_auth['auth_edit']   ? $lang['Rules_edit_can']   : $lang['Rules_edit_cannot']   . '<br />';
$s_auth_can .= $is_auth['auth_delete'] ? $lang['Rules_delete_can'] : $lang['Rules_delete_cannot'] . '<br />';
$s_auth_can .= $is_auth['auth_vote']   ? $lang['Rules_vote_can']   : $lang['Rules_vote_cannot']   . '<br />';

if (!(bool)$attach_config['disable_mod']) {
    // If you want to have the rules window link within the forum view too, comment out the two lines, and comment the third line
    //	$rules_link = '(<a href="' . $phpbb_root_path . 'attach_rules.' . $phpEx . '?f=' . $forum_id . '" target="_blank">Rules</a>)';
    //	$s_auth_can .= ( ( $is_auth['auth_attachments'] ) ? $rules_link . ' ' . $lang['Rules_attach_can'] : $lang['Rules_attach_cannot'] ) . '<br />';
    $s_auth_can .= $is_auth['auth_attachments']  ? $lang['Rules_attach_can']   : $lang['Rules_attach_cannot']   . '<br />';
    $s_auth_can .= $is_auth['auth_download']     ? $lang['Rules_download_can'] : $lang['Rules_download_cannot'] . '<br />';
}

if ($is_auth['auth_mod']) {
	$s_auth_can .= sprintf($lang['Rules_moderate'], '<a href="modcp.php?' . POST_FORUM_URL . "=$forumId&amp;start=" . $start . '&amp;sid=' . $userdata['session_id'] . '">', '</a>');
}

//
// Dump out the page header and load viewforum template
//

$page_title = $lang['View_forum'] . ' - ' . $forum->forum_name;

PageHelper::header($template, $userdata, $board_config, $lang, $images, $theme, $page_title, $gen_simple_header);

$template->setFileNames(['body' => 'viewforum_body.tpl']);
make_jumpbox('viewforum.php');

$template->assignVars(
    [
        'FORUM_ID'   => $forumId,
        'FORUM_NAME' => htmlspecialchars($forum->forum_name, ENT_QUOTES),
        'MODERATORS' => $forumModerators,
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
        'L_THANKS'              => $lang['Thanks'],
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

        'U_VIEW_FORUM' => Session::appendSid('viewforum.php?' . POST_FORUM_URL . "=$forumId"),

        'U_MARK_READ' => Session::appendSid('viewforum.php?' . POST_FORUM_URL . "=$forumId&amp;mark=topics")
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
		$topicId   = $topic->topic_id;
		$replies   = $topic->topic_replies;
		$topicType = $topic->topic_type;

        $topicTitle = $count_orig_word ? preg_replace($orig_word, $replacement_word, $topic->topic_title) : $topic->topic_title;

        if ($topicType === POST_ANNOUNCE) {
            $topicType = $lang['Topic_Announcement'] . ' ';
        } elseif ($topicType === POST_STICKY) {
            $topicType = $lang['Topic_Sticky'] . ' ';
        } else {
            $topicType = '';
        }

        if ($topic->topic_vote) {
            $topicType .= $lang['Topic_Poll'] . ' ';
        }

        if ($topic->topic_status === TOPIC_MOVED) {
			$topicType = $lang['Topic_Moved'] . ' ';
			$topicId   = $topic->topic_moved_id;

			$folderImage     = $images['folder'];
			$folderAlt       = $lang['Topics_Moved'];
			$newest_post_img = '';
		} else {
            if ($topic->topic_type === POST_ANNOUNCE) {
				$folder    = $images['folder_announce'];
				$folderNew = $images['folder_announce_new'];
            } elseif ($topic->topic_type === POST_STICKY) {
				$folder    = $images['folder_sticky'];
				$folderNew = $images['folder_sticky_new'];
            } elseif ($topic->topic_status === TOPIC_LOCKED) {
				$folder    = $images['folder_locked'];
				$folderNew = $images['folder_locked_new'];
			} else {
				if ($replies >= $board_config['hot_threshold']) {
					$folder    = $images['folder_hot'];
					$folderNew = $images['folder_hot_new'];
				} else {
					$folder    = $images['folder'];
					$folderNew = $images['folder_new'];
				}
			}

			$newest_post_img = '';

			if ($userdata['session_logged_in'] && $topic->post_time > $userdata['user_last_visit']) {
                if (!empty($trackingTopics) || !empty($trackingForums) || isset($_COOKIE[$forumAllCookieName])) {
                    $unreadTopics = true;

                    if (isset($trackingTopics[$topicId]) && $trackingTopics[$topicId] >= $topic->post_time) {
                        $unreadTopics = false;
                    }

                    if (isset($trackingForums[$forumId]) && $trackingForums[$forumId] >= $topic->post_time) {
                        $unreadTopics = false;
                    }

                    if (isset($_COOKIE[$forumAllCookieName]) && $_COOKIE[$forumAllCookieName] >= $topic->post_time) {
                        $unreadTopics = false;
                    }

                    if ($unreadTopics) {
                        $folderImage = $folderNew;
                        $folderAlt   = $lang['New_posts'];

                        $newest_post_img = '<a href="' . Session::appendSid('viewtopic.php?' . POST_TOPIC_URL . "=$topicId&amp;view=newest") . '"><img src="' . $images['icon_newest_reply'] . '" alt="' . $lang['View_newest_post'] . '" title="' . $lang['View_newest_post'] . '" border="0" /></a> ';
                    } else {
                        $folderImage = $folder;
                        $folderAlt   = $topic->topic_status === TOPIC_LOCKED ? $lang['Topic_locked'] : $lang['No_new_posts'];

                        $newest_post_img = '';
                    }
                } else {
                    $folderImage = $folderNew;
                    $folderAlt   = $topic->topic_status === TOPIC_LOCKED ? $lang['Topic_locked'] : $lang['New_posts'];

                    $newest_post_img = '<a href="' . Session::appendSid('viewtopic.php?' . POST_TOPIC_URL . "=$topicId&amp;view=newest") . '"><img src="' . $images['icon_newest_reply'] . '" alt="' . $lang['View_newest_post'] . '" title="' . $lang['View_newest_post'] . '" border="0" /></a> ';
                }
            } else {
				$folderImage = $folder;
				$folderAlt   = $topic->topic_status === TOPIC_LOCKED ? $lang['Topic_locked'] : $lang['No_new_posts'];

				$newest_post_img = '';
			}
		}

        // use pagination !!
        if (($replies + 1) > $board_config['posts_per_page']) {
			$totalPages = ceil( ( $replies + 1 ) / $board_config['posts_per_page'] );
			$gotoPage   = ' [ <img src="' . $images['icon_gotopost'] . '" alt="' . $lang['Goto_page'] . '" title="' . $lang['Goto_page'] . '" />' . $lang['Goto_page'] . ': ';

			$times = 1;
			
			for ($j = 0; $j < $replies + 1; $j += $board_config['posts_per_page']) {
				$gotoPage .= '<a href="' . Session::appendSid('viewtopic.php?' . POST_TOPIC_URL . '=' . $topicId . "&amp;start=$j") . '">' . $times . '</a>';
				
				if ($times === 1 && $totalPages > 4) {
					$gotoPage .= ' ... ';
					$times    = $totalPages - 3;
					$j        += ( $totalPages - 4 ) * $board_config['posts_per_page'];
				} elseif ($times < $totalPages) {
					$gotoPage .= ', ';
				}
				
				$times++;
			}
			
			$gotoPage .= ' ] ';
		} else {
			$gotoPage = '';
		}
		
		$view_topic_url = Session::appendSid('viewtopic.php?' . POST_TOPIC_URL . "=$topicId");

		$topicAuthor = $topic->user_id !== ANONYMOUS ? '<a href="' . Session::appendSid('profile.php?mode=viewprofile&amp;' . POST_USERS_URL . '=' . $topic->user_id) . '">' : '';

		if ($topic->user_id !== ANONYMOUS) {
            $topicAuthor .=  $topic->username;
        } else {
		    if ($topic->post_username !== '') {
                $topicAuthor .= $topic->post_username;
            } else {
                $topicAuthor .= $lang['Guest'];
            }
        }

		$topicAuthor .= $topic->user_id !== ANONYMOUS ? '</a>' : '';

		$firstPostTime = create_date($board_config['default_dateformat'], $topic->topic_time, $board_config['board_timezone']);
		$lastPostTime  = create_date($board_config['default_dateformat'], $topic->post_time, $board_config['board_timezone']);

        if ($topic->id2 === ANONYMOUS) {
            $lastPostAuthor = $topic->post_username2 !== '' ? $topic->post_username2 . ' ' : $lang['Guest'];
        } else {
            $lastPostAuthor = '<a href="' . Session::appendSid('profile.php?mode=viewprofile&amp;' . POST_USERS_URL . '='  . $topic->id2) . '">' . $topic->user2 . '</a>';
        }

		$last_post_url = '<a href="' . Session::appendSid('viewtopic.php?' . POST_POST_URL . '=' . $topic->topic_last_post_id) . '#' . $topic->topic_last_post_id . '"><img src="' . $images['icon_latest_reply'] . '" alt="' . $lang['View_latest_post'] . '" title="' . $lang['View_latest_post'] . '" border="0" /></a>';
		
		$rowColor = ($i % 2) ? $theme['td_color1'] : $theme['td_color2'];
		$rowClass = ($i % 2) ? $theme['td_class1'] : $theme['td_class2'];

        $template->assignBlockVars('topicrow',
            [
                'ROW_COLOR'        => $rowColor,
                'ROW_CLASS'        => $rowClass,
                'FORUM_ID'         => $forumId,
                'TOPIC_ID'         => $topicId,
                'TOPIC_FOLDER_IMG' => $folderImage,
                'TOPIC_AUTHOR'     => $topicAuthor,
                'GOTO_PAGE'        => $gotoPage,
                'REPLIES'          => $replies,
                'THANKS'           => $topic->topic_thanks,
                'NEWEST_POST_IMG'  => $newest_post_img,
                'TOPIC_ATTACHMENT_IMG' => topic_attachment_image($topic->topic_attachment),
                'TOPIC_TITLE'      => $topicTitle,
                'TOPIC_TYPE'       => $topicType,
                'VIEWS'            => $topic->topic_views,
                'FIRST_POST_TIME'  => $firstPostTime,
                'LAST_POST_TIME'   => $lastPostTime,
                'LAST_POST_AUTHOR' => $lastPostAuthor,
                'LAST_POST_IMG'    => $last_post_url,

                'L_TOPIC_FOLDER_ALT' => $folderAlt,

                'U_VIEW_TOPIC' => $view_topic_url
            ]
        );
    }

    $topicsCount -= $totalAnnouncements;

    $template->assignVars(
        [
            'PAGINATION'  => generate_pagination('viewforum.php?' . POST_FORUM_URL . "=$forumId&amp;topicdays=$topicDays", $topicsCount, $board_config['topics_per_page'], $start),
            'PAGE_NUMBER' => sprintf($lang['Page_of'], floor($start / $board_config['topics_per_page']) + 1, ceil($topicsCount / $board_config['topics_per_page'])),

            'L_GOTO_PAGE' => $lang['Goto_page']
        ]
    );
} else {
	//
	// No topics
	//
	$noTopicsMessage = $forum->forum_status === FORUM_LOCKED ? $lang['Forum_locked'] : $lang['No_topics_post_one'];
    $template->assignVars(['L_NO_TOPICS' => $noTopicsMessage]);

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