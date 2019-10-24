<?php
/***************************************************************************
 *                                index.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: index.php 5502 2006-01-28 11:13:39Z acydburn $
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
// Start session management
//

$userdata = init_userprefs(PAGE_INDEX);
//
// End session management
//

$categoryId = isset($_GET[POST_CAT_URL]) ? $_GET[POST_CAT_URL] : -1;

if (isset($_GET['mark']) || isset($_POST['mark'])) {
    $markRead = isset($_POST['mark']) ? $_POST['mark'] : $_GET['mark'];
} else {
    $markRead = '';
}

// define cookie names
$topicCookieName    = $board_config['cookie_name'] . '_t';
$forumCookieName    = $board_config['cookie_name'] . '_f';
$forumAllCookieName = $board_config['cookie_name'] . '_f_all';

//
// Handle marking posts
//
if ($markRead === 'forums') {
    if ($userdata['session_logged_in']) {
        setcookie(
            $forumAllCookieName,
            time(),
            0,
            $board_config['cookie_path'],
            $board_config['cookie_domain'],
            isConnectionsSecure()
        );
    }

    $template->assignVars(
        [
            'META' => '<meta http-equiv="refresh" content="3;url=' . Session::appendSid('index.php') . '">'
        ]
    );

    $message = $lang['Forums_marked_read'] . '<br /><br />' . sprintf($lang['Click_return_index'], '<a href="' . Session::appendSid('index.php') . '">', '</a> ');

    message_die(GENERAL_MESSAGE, $message);
}
//
// End handle marking posts
//

$trackingTopics = isset($_COOKIE[$topicCookieName]) ? unserialize($_COOKIE[$topicCookieName]) : [];
$trackingForums = isset($_COOKIE[$forumCookieName]) ? unserialize($_COOKIE[$forumCookieName]) : [];

//
// If you don't use these stats on your index you may want to consider
// removing them
//
$totalPosts     = get_db_stat('postcount');
$totalUsers     = get_db_stat('usercount');
$newestUserData = get_db_stat('newestuser');
$newestUser     = $newestUserData->username;
$newestUserId   = $newestUserData->user_id;

if ($totalPosts === 0) {
    $l_total_post_s = $lang['Posted_articles_zero_total'];
} elseif ($totalPosts === 1) {
    $l_total_post_s = $lang['Posted_article_total'];
} else {
    $l_total_post_s = $lang['Posted_articles_total'];
}

if ($totalUsers === 0) {
    $l_total_user_s = $lang['Registered_users_zero_total'];
} elseif ($totalUsers === 1) {
    $l_total_user_s = $lang['Registered_user_total'];
} else {
    $l_total_user_s = $lang['Registered_users_total'];
}

//
// Start page proper
//

$categories = dibi::select(['cat_id', 'cat_title', 'cat_order'])
    ->from(CATEGORIES_TABLE)
    ->orderBy('cat_order')
    ->fetchAll();

if (!count($categories)) {
    message_die(GENERAL_MESSAGE, $lang['No_forums']);
}

//
// Define appropriate SQL
//
switch (Config::DBMS) {
    case 'postgresql':
        $sql = 'SELECT f.*, p.post_time, p.post_username, u.username, u.user_id 
				FROM ' . FORUMS_TABLE . ' f, ' . POSTS_TABLE . ' p, ' . USERS_TABLE . ' u
				WHERE p.post_id = f.forum_last_post_id 
					AND u.user_id = p.poster_id  
					UNION (
						SELECT f.*, NULL, NULL, NULL, NULL
						FROM ' . FORUMS_TABLE . ' f
						WHERE NOT EXISTS (
							SELECT p.post_time
							FROM ' . POSTS_TABLE . ' p
							WHERE p.post_id = f.forum_last_post_id  
						)
					)
					ORDER BY cat_id, forum_order';

        $forums = dibi::query($sql)->fetchAll();
        break;

    case 'oracle':
        $forums = dibi::select('f.*, p.post_time, p.post_username, u.username, u.user_id')
            ->from(FORUMS_TABLE)
            ->as('f')
            ->innerJoin(POSTS_TABLE)
            ->as('p')
            ->on('p.post_id = f.forum_last_post_id(+)')
            ->innerJoin(USERS_TABLE)
            ->as('u')
            ->on('u.user_id = p.poster_id(+)')
            ->orderBy('f.cat_id')
            ->orderBy('f.forum_order')
            ->fetchAll();
        break;

    default:
        // there was left join
        $forums = dibi::select('f.*, p.post_time, p.post_username, u.username, u.user_id')
            ->from(FORUMS_TABLE)
            ->as('f')
            ->leftJoin(POSTS_TABLE)
            ->as('p')
            ->on('p.post_id = f.forum_last_post_id')
            ->leftJoin(USERS_TABLE)
            ->as('u')
            ->on('u.user_id = p.poster_id')
            ->orderBy('f.cat_id')
            ->orderBy('f.forum_order')
            ->fetchAll();
        break;
}

$totalForums = count($forums);

if (!$totalForums) {
    message_die(GENERAL_MESSAGE, $lang['No_forums']);
}

//
// Obtain a list of topic ids which contain
// posts made since user last visited
//
if ($userdata['session_logged_in']) {
    // 60 days limit
    if ($userdata['user_lastvisit'] < (time() - 5184000)) {
        $userdata['user_lastvisit'] = time() - 5184000;
    }

    $new_topic_tmp_data = dibi::select('t.forum_id, t.topic_id, p.post_time')
        ->from(TOPICS_TABLE)
        ->as('t')
        ->innerJoin(POSTS_TABLE) // maybe there should be letft/inner join....
        ->as('p')
        ->on('p.post_id = t.topic_last_post_id')
        ->where('p.post_time > %i', $userdata['user_lastvisit'])
        ->where('t.topic_moved_id = %i', 0)
        ->fetchAll();

    $newTopicData = [];

    foreach ($new_topic_tmp_data as $topic) {
        $newTopicData[$topic->forum_id][$topic->topic_id] = $topic->post_time;
    }
}

//
// Obtain list of moderators of each forum
// First users, then groups ... broken into two queries
//

$forumModeratorsData = dibi::select('aa.forum_id, u.user_id, u.username')
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
    ->where('aa.auth_mod = %i', 1)
    ->where('g.group_single_user = %i', 1)
    ->groupBy('u.user_id')
    ->groupBy('u.username')
    ->groupBy('aa.forum_id')
    ->orderBy('aa.forum_id')
    ->orderBy('u.user_id')
    ->fetchAll();

$forumModerators = [];

foreach ($forumModeratorsData as $row) {
    $forumModerators[$row->forum_id][] = '<a href="' . Session::appendSid('profile.php?mode=viewprofile&amp;' . POST_USERS_URL . '=' . $row->user_id) . '">' . $row->username . '</a>';
}

$forumModeratorsData = dibi::select('aa.forum_id, g.group_id, g.group_name')
    ->from(AUTH_ACCESS_TABLE)
    ->as('aa')
    ->innerJoin(USER_GROUP_TABLE)
    ->as('ug')
    ->on('ug.group_id = aa.group_id')
    ->innerJoin(GROUPS_TABLE)
    ->as('g')
    ->on('g.group_id = aa.group_id')
    ->where('aa.auth_mod = %i', 1)
    ->where('g.group_single_user = %i', 0)
    ->where('g.group_type <> %i', GROUP_HIDDEN)
    ->groupBy('g.group_id')
    ->groupBy('g.group_name')
    ->groupBy('aa.forum_id')
    ->orderBy('aa.forum_id')
    ->orderBy('g.group_id')
    ->fetchAll();

foreach ($forumModeratorsData as $row) {
    $forumModerators[$row->forum_id][] = '<a href="' . Session::appendSid('groupcp.php?' . POST_GROUPS_URL . '=' . $row->group_id) . '">' . htmlspecialchars($row->group_name, ENT_QUOTES) . '</a>';
}

//
// Find which forums are visible for this user
//
$is_auth = Auth::authorize(AUTH_VIEW, AUTH_LIST_ALL, $userdata, $forums);

//
// Start output of page
//

$onlineUsersCount = dibi::select('COUNT(*)')
    ->from(SESSIONS_TABLE)
    ->where('session_logged_in = %i', 1)
    ->groupBy('session_user_id')
    ->fetchSingle();

if ($onlineUsersCount === 0) {
    $l_r_user_s = $lang['Online_users_zero_total'];
} elseif ($onlineUsersCount === 1) {
    $l_r_user_s = $lang['Online_user_total'];
} else {
    $l_r_user_s = $lang['Online_users_total'];
}

PageHelper::header($template, $userdata, $board_config, $lang, $images, $theme, $lang['Index'], $gen_simple_header);

$template->setFileNames(['body' => 'index_body.tpl']);

$template->assignVars(
    [
       'TOTAL_USERS_ONLINE'  => sprintf($l_r_user_s, $onlineUsersCount),

        'TOTAL_POSTS' => sprintf($l_total_post_s, $totalPosts),
        'TOTAL_USERS' => sprintf($l_total_user_s, $totalUsers),
        'NEWEST_USER' => sprintf($lang['Newest_user'], '<a href="' . Session::appendSid('profile.php?mode=viewprofile&amp;' . POST_USERS_URL . "=$newestUserId") . '">', $newestUser, '</a>'),

        'FORUM_IMG'        => $images['forum'],
        'FORUM_NEW_IMG'    => $images['forum_new'],
        'FORUM_LOCKED_IMG' => $images['forum_locked'],

        'F_LOGIN_FORM_TOKEN' => CSRF::getInputHtml(),

        'L_FORUM'               => $lang['Forum'],
        'L_TOPICS'              => $lang['Topics'],
        'L_REPLIES'             => $lang['Replies'],
        'L_VIEWS'               => $lang['Views'],
        'L_POSTS'               => $lang['Posts'],
        'L_LASTPOST'            => $lang['Last_Post'],
        'L_NO_NEW_POSTS'        => $lang['No_new_posts'],
        'L_NEW_POSTS'           => $lang['New_posts'],
        'L_NO_NEW_POSTS_LOCKED' => $lang['No_new_posts_locked'],
        'L_NEW_POSTS_LOCKED'    => $lang['New_posts_locked'],
        'L_ONLINE_EXPLAIN'      => $lang['Online_explain'],

        'L_MODERATOR'        => $lang['Moderators'],
        'L_FORUM_LOCKED'     => $lang['Forum_is_locked'],
        'L_MARK_FORUMS_READ' => $lang['Mark_all_forums'],

        'U_MARK_READ' => Session::appendSid('index.php?mark=forums')
    ]
);

//
// Let's decide which categories we should display
//
$displayCategories = [];

foreach ($forums as $forum) {
    if ($is_auth[$forum->forum_id]['auth_view']) {
        $displayCategories[$forum->cat_id] = true;
    }
}

//
// Okay, let's build the index
//

foreach ($categories as $i => $category) {
    $catId = $category->cat_id;

    //
    // Yes, we should, so first dump out the category
    // title, then, if appropriate the forum list
    //
    if (isset($displayCategories[$catId]) && $displayCategories[$catId]) {
        $template->assignBlockVars('catrow', [
                'CAT_ID'    => $catId,
                'CAT_DESC'  => htmlspecialchars($category->cat_title, ENT_QUOTES),
                'U_VIEWCAT' => Session::appendSid('index.php?' . POST_CAT_URL . "=$catId")
            ]
        );

        if ($categoryId === $catId || $categoryId === -1) {
            foreach ($forums as $forum) {
                if ($forum->cat_id === $catId) {
                    $forumId = $forum->forum_id;

                    if ($is_auth[$forumId]['auth_view']) {
                        if ($forum->forum_status === FORUM_LOCKED) {
                            $folder_image = $images['forum_locked'];
                            $folderAlt    = $lang['Forum_locked'];
                        } else {
                            $unreadTopics = false;

                            if ($userdata['session_logged_in'] && isset($newTopicData[$forumId])) {
                                $forumLastPostTime = 0;

                                foreach ($newTopicData[$forumId] as $check_topic_id => $check_post_time) {
                                    if (empty($trackingTopics[$check_topic_id])) {
                                        $unreadTopics      = true;
                                        $forumLastPostTime = max($check_post_time, $forumLastPostTime);
                                    } else {
                                        if ($trackingTopics[$check_topic_id] < $check_post_time) {
                                            $unreadTopics      = true;
                                            $forumLastPostTime = max($check_post_time, $forumLastPostTime);
                                        }
                                    }
                                }

                                if (isset($trackingForums[$forumId]) && $trackingForums[$forumId] > $forumLastPostTime) {
                                    $unreadTopics = false;
                                }

                                if (isset($_COOKIE[$forumAllCookieName]) && $_COOKIE[$forumAllCookieName] > $forumLastPostTime) {
                                    $unreadTopics = false;
                                }
                            }

                            $folder_image = $unreadTopics ? $images['forum_new'] : $images['forum'];
                            $folderAlt    = $unreadTopics ? $lang['New_posts']   : $lang['No_new_posts'];
                        }

                        $posts  = $forum->forum_posts;
                        $topics = $forum->forum_topics;

                        if ($forum->forum_last_post_id) {
                            $lastPostTime = create_date($board_config['default_dateformat'], $forum->post_time, $board_config['board_timezone']);

                            $lastPost = $lastPostTime . '<br />';

                            if ($forum->user_id === ANONYMOUS) {
                                if ($forum->post_username !== '') {
                                    $lastPost .= $forum->post_username . ' ';
                                } else {
                                    $lastPost .= $lang['Guest'];
                                }
                            } else {
                                $lastPost .= '<a href="' . Session::appendSid('profile.php?mode=viewprofile&amp;' . POST_USERS_URL . '=' . $forum->user_id) . '">' . $forum->username . '</a> ';
                            }

                            $lastPost .= '<a href="' . Session::appendSid('viewtopic.php?' . POST_POST_URL . '=' . $forum->forum_last_post_id) . '#' . $forum->forum_last_post_id . '"><img src="' . $images['icon_latest_reply'] . '" border="0" alt="' . $lang['View_latest_post'] . '" title="' . $lang['View_latest_post'] . '" /></a>';
                        } else {
                            $lastPost = $lang['No_Posts'];
                        }

                        $moderators_forum_count = isset($forumModerators[$forumId]) ? count($forumModerators[$forumId]) : 0;

                        if ($moderators_forum_count > 0) {
                            $l_moderators  = $moderators_forum_count === 1 ? $lang['Moderator'] : $lang['Moderators'];
                            $moderatorList = implode(', ', $forumModerators[$forumId]);
                        } else {
                            $l_moderators  = '&nbsp;';
                            $moderatorList = '&nbsp;';
                        }

                        $rowColor = !($i % 2) ? $theme['td_color1'] : $theme['td_color2'];
                        $rowClass = !($i % 2) ? $theme['td_class1'] : $theme['td_class2'];

                        $catRowData = [
                            'ROW_COLOR'        => '#' . $rowColor,
                            'ROW_CLASS'        => $rowClass,
                            'FORUM_FOLDER_IMG' => $folder_image,
                            'FORUM_NAME'       => htmlspecialchars($forum->forum_name, ENT_QUOTES),
                            'FORUM_DESC'       => htmlspecialchars($forum->forum_desc, ENT_QUOTES),
                            'POSTS'            => $forum->forum_posts,
                            'TOPICS'           => $forum->forum_topics,
                            'LAST_POST'        => $lastPost,
                            'MODERATORS'       => $moderatorList,

                            'L_MODERATOR'        => $l_moderators,
                            'L_FORUM_FOLDER_ALT' => $folderAlt,

                            'U_VIEWFORUM' => Session::appendSid('viewforum.php?' . POST_FORUM_URL . "=$forumId")
                        ];

                        $template->assignBlockVars('catrow.forumrow', $catRowData);
                    }
                }
            }
        }
    }
} // for ... categories

//
// Generate the page
//
$template->pparse('body');

PageHelper::footer($template, $userdata, $lang, $gen_simple_header);

?>