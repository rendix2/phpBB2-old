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
$userdata = Session::pageStart($user_ip, PAGE_INDEX);
init_userprefs($userdata);
//
// End session management
//

$view_category = isset($_GET[POST_CAT_URL]) ? $_GET[POST_CAT_URL] : -1;

if (isset($_GET['mark']) || isset($_POST['mark'])) {
    $mark_read = isset($_POST['mark']) ? $_POST['mark'] : $_GET['mark'];
} else {
    $mark_read = '';
}

// define cookie names
$topic_cookie_name = $board_config['cookie_name'] . '_t';
$forum_cookie_name = $board_config['cookie_name'] . '_f';
$forum_all_cookie_name = $board_config['cookie_name'] . '_f_all';

//
// Handle marking posts
//
if ($mark_read === 'forums') {
    if ($userdata['session_logged_in']) {
        setcookie(
            $forum_all_cookie_name,
            time(),
            0,
            $board_config['cookie_path'],
            $board_config['cookie_domain'],
            $board_config['cookie_secure']
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

$tracking_topics = isset($_COOKIE[$topic_cookie_name]) ? unserialize($_COOKIE[$topic_cookie_name]) : [];
$tracking_forums = isset($_COOKIE[$forum_cookie_name]) ? unserialize($_COOKIE[$forum_cookie_name]) : [];

//
// If you don't use these stats on your index you may want to consider
// removing them
//
$total_posts     = get_db_stat('postcount');
$total_users     = get_db_stat('usercount');
$newest_userdata = get_db_stat('newestuser');
$newest_user     = $newest_userdata->username;
$newest_uid      = $newest_userdata->user_id;

if ($total_posts === 0) {
    $l_total_post_s = $lang['Posted_articles_zero_total'];
} elseif ($total_posts === 1) {
    $l_total_post_s = $lang['Posted_article_total'];
} else {
    $l_total_post_s = $lang['Posted_articles_total'];
}

if ($total_users === 0) {
    $l_total_user_s = $lang['Registered_users_zero_total'];
} elseif ($total_users === 1) {
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
switch ($dbms) {
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

        $forum_data = dibi::query($sql)->fetchAll();
        break;

    case 'oracle':
        $forum_data = dibi::select('f.*, p.post_time, p.post_username, u.username, u.user_id')
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
        $forum_data = dibi::select('f.*, p.post_time, p.post_username, u.username, u.user_id')
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

$total_forums = count($forum_data);

if (!$total_forums) {
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

    $new_topic_data = [];

    foreach ($new_topic_tmp_data as $topic_data) {
        $new_topic_data[$topic_data->forum_id][$topic_data->topic_id] = $topic_data->post_time;
    }
}

//
// Obtain list of moderators of each forum
// First users, then groups ... broken into two queries
//

$forum_moderators_data = dibi::select('aa.forum_id, u.user_id, u.username')
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

$forum_moderators = [];

foreach ($forum_moderators_data as $row) {
    $forum_moderators[$row->forum_id][] = '<a href="' . Session::appendSid('profile.php?mode=viewprofile&amp;' . POST_USERS_URL . '=' . $row->user_id) . '">' . $row->username . '</a>';
}

$forum_moderators_data = dibi::select('aa.forum_id, g.group_id, g.group_name')
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

foreach ($forum_moderators_data as $row) {
    $forum_moderators[$row->forum_id][] = '<a href="' . Session::appendSid('groupcp.php?' . POST_GROUPS_URL . '=' . $row->group_id) . '">' . htmlspecialchars($row->group_name, ENT_QUOTES) . '</a>';
}

//
// Find which forums are visible for this user
//
$is_auth_array = Auth::authorize(AUTH_VIEW, AUTH_LIST_ALL, $userdata, $forum_data);

//
// Start output of page
//

showOnline(null, $userdata, $board_config, $theme, $lang, $storage, $template);

$page_title = $lang['Index'];

require_once $phpbb_root_path . 'includes' . $sep . 'page_header.php';

$template->setFileNames(['body' => 'index_body.tpl']);

$template->assignVars(
    [
        'TOTAL_POSTS' => sprintf($l_total_post_s, $total_posts),
        'TOTAL_USERS' => sprintf($l_total_user_s, $total_users),
        'NEWEST_USER' => sprintf($lang['Newest_user'], '<a href="' . Session::appendSid('profile.php?mode=viewprofile&amp;' . POST_USERS_URL . "=$newest_uid") . '">', $newest_user, '</a>'),

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
$display_categories = [];

foreach ($forum_data as $forum) {
    if ($is_auth_array[$forum->forum_id]['auth_view']) {
        $display_categories[$forum->cat_id] = true;
    }
}

//
// Okay, let's build the index
//

foreach ($categories as $i => $category) {
    $cat_id = $category->cat_id;

    //
    // Yes, we should, so first dump out the category
    // title, then, if appropriate the forum list
    //
    if (isset($display_categories[$cat_id]) && $display_categories[$cat_id]) {
        $template->assignBlockVars('catrow', [
                'CAT_ID'    => $cat_id,
                'CAT_DESC'  => htmlspecialchars($category->cat_title, ENT_QUOTES),
                'U_VIEWCAT' => Session::appendSid('index.php?' . POST_CAT_URL . "=$cat_id")
            ]);

        if ($view_category === $cat_id || $view_category === -1) {
            foreach ($forum_data as $forum) {
                if ($forum->cat_id === $cat_id) {
                    $forum_id = $forum->forum_id;

                    if ($is_auth_array[$forum_id]['auth_view']) {
                        if ($forum->forum_status === FORUM_LOCKED) {
                            $folder_image = $images['forum_locked'];
                            $folder_alt   = $lang['Forum_locked'];
                        } else {
                            $unread_topics = false;

                            if ($userdata['session_logged_in'] && isset($new_topic_data[$forum_id])) {
                                $forum_last_post_time = 0;

                                foreach ($new_topic_data[$forum_id] as $check_topic_id => $check_post_time) {
                                    if (empty($tracking_topics[$check_topic_id])) {
                                        $unread_topics        = true;
                                        $forum_last_post_time = max($check_post_time, $forum_last_post_time);
                                    } else {
                                        if ($tracking_topics[$check_topic_id] < $check_post_time) {
                                            $unread_topics        = true;
                                            $forum_last_post_time = max($check_post_time, $forum_last_post_time);
                                        }
                                    }
                                }

                                if (isset($tracking_forums[$forum_id]) && $tracking_forums[$forum_id] > $forum_last_post_time) {
                                    $unread_topics = false;
                                }

                                if (isset($_COOKIE[$forum_all_cookie_name]) && $_COOKIE[$forum_all_cookie_name] > $forum_last_post_time) {
                                    $unread_topics = false;
                                }
                            }

                            $folder_image = $unread_topics ? $images['forum_new'] : $images['forum'];
                            $folder_alt   = $unread_topics ? $lang['New_posts']   : $lang['No_new_posts'];
                        }

                        $posts  = $forum->forum_posts;
                        $topics = $forum->forum_topics;

                        if ($forum->forum_last_post_id) {
                            $last_post_time = create_date($board_config['default_dateformat'], $forum->post_time, $board_config['board_timezone']);

                            $last_post = $last_post_time . '<br />';

                            if ($forum->user_id === ANONYMOUS) {
                                if ($forum->post_username !== '') {
                                    $last_post .= $forum->post_username . ' ';
                                } else {
                                    $last_post .= $lang['Guest'];
                                }
                            } else {
                                $last_post .= '<a href="' . Session::appendSid('profile.php?mode=viewprofile&amp;' . POST_USERS_URL . '=' . $forum->user_id) . '">' . $forum->username . '</a> ';
                            }

                            $last_post .= '<a href="' . Session::appendSid('viewtopic.php?' . POST_POST_URL . '=' . $forum->forum_last_post_id) . '#' . $forum->forum_last_post_id . '"><img src="' . $images['icon_latest_reply'] . '" border="0" alt="' . $lang['View_latest_post'] . '" title="' . $lang['View_latest_post'] . '" /></a>';
                        } else {
                            $last_post = $lang['No_Posts'];
                        }

                        $moderators_forum_count = isset($forum_moderators[$forum_id]) ? count($forum_moderators[$forum_id]) : 0;

                        if ($moderators_forum_count > 0) {
                            $l_moderators   = $moderators_forum_count === 1 ? $lang['Moderator'] : $lang['Moderators'];
                            $moderator_list = implode(', ', $forum_moderators[$forum_id]);
                        } else {
                            $l_moderators   = '&nbsp;';
                            $moderator_list = '&nbsp;';
                        }

                        $row_color = !($i % 2) ? $theme['td_color1'] : $theme['td_color2'];
                        $row_class = !($i % 2) ? $theme['td_class1'] : $theme['td_class2'];

                        $catRowData = [
                            'ROW_COLOR'        => '#' . $row_color,
                            'ROW_CLASS'        => $row_class,
                            'FORUM_FOLDER_IMG' => $folder_image,
                            'FORUM_NAME'       => htmlspecialchars($forum->forum_name, ENT_QUOTES),
                            'FORUM_DESC'       => htmlspecialchars($forum->forum_desc, ENT_QUOTES),
                            'POSTS'            => $forum->forum_posts,
                            'TOPICS'           => $forum->forum_topics,
                            'LAST_POST'        => $last_post,
                            'MODERATORS'       => $moderator_list,

                            'L_MODERATOR'        => $l_moderators,
                            'L_FORUM_FOLDER_ALT' => $folder_alt,

                            'U_VIEWFORUM' => Session::appendSid('viewforum.php?' . POST_FORUM_URL . "=$forum_id")
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

require_once $phpbb_root_path . 'includes' . $sep . 'page_tail.php';

?>