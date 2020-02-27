<?php
/***************************************************************************
 *                                 modcp.php
 *                            -------------------
 *   begin                : July 4, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: modcp.php 6772 2006-12-16 13:11:28Z acydburn $
 *
 ***************************************************************************/

use phpBB2\Sync;

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 ***************************************************************************/

/**
 * Moderator Control Panel
 *
 * From this 'Control Panel' the moderator of a forum will be able to do
 * mass topic operations (locking/unlocking/moving/deleteing), and it will
 * provide an interface to do quick locking/unlocking/moving/deleting of
 * topics via the moderator operations buttons on all of the viewtopic pages.
 */

define('IN_PHPBB', true);

$sep = DIRECTORY_SEPARATOR;
$phpbb_root_path = '.' . $sep;

require_once $phpbb_root_path . 'common.php';
require_once $phpbb_root_path . 'includes' . $sep . 'bbcode.php';
require_once $phpbb_root_path . 'includes' . $sep . 'functions_admin.php';

//
// Obtain initial var settings
//
$forumId = '';

if (isset($_GET[POST_FORUM_URL]) || isset($_POST[POST_FORUM_URL])) {
    $forumId = isset($_POST[POST_FORUM_URL]) ? (int)$_POST[POST_FORUM_URL] : (int)$_GET[POST_FORUM_URL];
}

$postId = '';

if (isset($_GET[POST_POST_URL]) || isset($_POST[POST_POST_URL])) {
    $postId = isset($_POST[POST_POST_URL]) ? (int)$_POST[POST_POST_URL] : (int)$_GET[POST_POST_URL];
}

$topicId = '';

if (isset($_GET[POST_TOPIC_URL]) || isset($_POST[POST_TOPIC_URL])) {
    $topicId = isset($_POST[POST_TOPIC_URL]) ? (int)$_POST[POST_TOPIC_URL] : (int)$_GET[POST_TOPIC_URL];
}

//
// Continue var definitions
//
$start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
$start = $start < 0 ? 0 : $start;

$delete  = isset($_POST['delete']);
$move    = isset($_POST['move']);
$lock    = isset($_POST['lock']);
$unlock  = isset($_POST['unlock']);
$confirm = isset($_POST['confirm']);
$mode    = '';

if (isset($_POST[POST_MODE]) || isset($_GET[POST_MODE])) {
    $mode = isset($_POST[POST_MODE]) ? $_POST[POST_MODE] : $_GET[POST_MODE];
    $mode = htmlspecialchars($mode);
} else if ($delete) {
    $mode = 'delete';
} elseif ($move) {
    $mode = 'move';
} elseif ($lock) {
    $mode = 'lock';
} elseif ($unlock) {
    $mode = 'unlock';
}

$sid = '';

// session id check
if (!empty($_POST['sid']) || !empty($_GET['sid'])) {
    $sid = !empty($_POST['sid']) ? $_POST['sid'] : $_GET['sid'];
}

//
// Obtain relevant data
//
if (!empty($topicId)) {
    $topic_row = dibi::select(['f.forum_id','f.forum_name', 'f.forum_topics'])
        ->from(Tables::TOPICS_TABLE)
        ->as('t')
        ->innerJoin(Tables::FORUMS_TABLE)
        ->as('f')
        ->on('f.forum_id = t.forum_id')
        ->where('t.topic_id = %i', $topicId)
        ->fetch();

    if (!$topic_row) {
        message_die(GENERAL_MESSAGE, 'Topic_post_not_exist');
    }

    // todo oh why? :O if 0 than 1? :D
	$forumTopics = $topic_row->forum_topics === 0 ? 1 : $topic_row->forum_topics;
	$forumId     = $topic_row->forum_id;
	$forumName   = $topic_row->forum_name;
} elseif (!empty($forumId)) {
    $topic_row = dibi::select(['forum_name', 'forum_topics'])
        ->from(Tables::FORUMS_TABLE)
        ->where('forum_id = %i', $forumId)
        ->fetch();

    if (!$topic_row) {
        message_die(GENERAL_MESSAGE, 'Forum_not_exist');
    }

	$forumTopics = $topic_row->forum_topics === 0 ? 1 : $topic_row->forum_topics;
	$forumName   = $topic_row->forum_name;
} else {
	message_die(GENERAL_MESSAGE, 'Forum_not_exist');
}

//
// Start session management
//
$userdata = init_userprefs($forumId);
//
// End session management
//

// session id check
if ($sid === '' || $sid !== $userdata['session_id']) {
	message_die(GENERAL_ERROR, 'Invalid_session');
}

//
// Check if user did or did not confirm
// If they did not, forward them to the last page they were on
//
if (isset($_POST['cancel'])) {
    if ($topicId) {
        $redirect = 'viewtopic.php?' . POST_TOPIC_URL . "=$topicId";
    } elseif ($forumId) {
        $redirect = 'viewforum.php?' . POST_FORUM_URL . "=$forumId";
    } else {
        $redirect = 'index.php';
    }

    redirect(Session::appendSid($redirect, true));
}

//
// Start auth check
//
$is_auth = Auth::authorize(Auth::AUTH_ALL, $forumId, $userdata);

if (!$is_auth['auth_mod']) {
	message_die(GENERAL_MESSAGE, $lang['Not_Moderator'], $lang['Not_Authorised']);
}
//
// End Auth Check
//

//
// Do major work ...
//
switch ($mode) {
	case 'delete':
        if (!$is_auth['auth_delete']) {
            message_die(GENERAL_MESSAGE, sprintf($lang['Sorry_auth_delete'], $is_auth['auth_delete_type']));
        }

        PageHelper::header($template, $userdata, $board_config, $lang, $images, $theme, $lang['Mod_CP'], $gen_simple_header);

        if ($confirm) {
            if (empty($_POST['topic_id_list']) && empty($topicId)) {
                message_die(GENERAL_MESSAGE, $lang['None_selected']);
            }

            $topics = isset($_POST['topic_id_list']) ? $_POST['topic_id_list'] : [$topicId];

			$topicIds = dibi::select('topic_id')
                ->from(Tables::TOPICS_TABLE)
                ->where('topic_id IN %in', $topics)
                ->where('forum_id = %i', $forumId)
                ->fetchPairs(null, 'topic_id');

			if (!count($topicIds)) {
				message_die(GENERAL_MESSAGE, $lang['None_selected']);
			}

			$usersManager = $container->getService('UsersManager');

            $topicAuthors = dibi::select('topic_poster')
                ->select('COUNT(topic_id)')
                ->as('topics')
                ->from(Tables::TOPICS_TABLE)
                ->where('topic_id IN %in', $topicIds)
                ->where('forum_id = %i', $forumId)
                ->groupBy('topic_poster')
                ->fetchAll();

			foreach ($topicAuthors as $author) {
                dibi::update(Tables::USERS_TABLE, ['user_topics%sql' => 'user_topics - '. $author->topics])
                    ->where('user_id = %i', $author->topic_poster)
                    ->execute();
            }

			$postsAuthors = dibi::select('poster_id')
                ->select('COUNT(post_id)')
                ->as('posts')
                ->from(Tables::POSTS_TABLE)
                ->where('topic_id IN %in', $topicIds)
                ->groupBy('poster_id')
                ->fetchAll();

			foreach ($postsAuthors as $poster) {
                dibi::update(Tables::USERS_TABLE, ['user_posts%sql' => 'user_posts - ' . $poster->posts])
                    ->where('user_id = %i', $poster->poster_id)
                    ->execute();
            }

			$thankers = dibi::select('user_id')
                ->select('COUNT(user_id)')
                ->as('thanks')
                ->from(Tables::THANKS_TABLE)
                ->where('[topic_id] IN %in', $topicIds)
                ->groupBy('user_id')
                ->fetchAll();

			foreach ($thankers as $thanker) {
			    $usersManager->updateByPrimary($thanker->user_id, ['user_thanks%sql' => 'user_thanks - '. $thanker->thanks]);
            }

            $postIds = dibi::select('post_id')
                ->from(Tables::POSTS_TABLE)
                ->where('topic_id IN %in', $topicIds)
                ->fetchPairs(null, 'post_id');

			//
			// Got all required info so go ahead and start deleting everything
			//

            dibi::delete(Tables::THANKS_TABLE)
                ->where('[topic_id] IN %in', $topicIds)
                ->execute();

            dibi::delete(Tables::TOPICS_TABLE)
                ->where('topic_id IN %in OR topic_moved_id IN %in', $topicIds, $topicIds)
                ->execute();

            if (count($postIds)) {
                dibi::delete(Tables::POSTS_TABLE)
                    ->where('post_id IN %in', $postIds)
                    ->execute();

                dibi::delete(Tables::POSTS_TEXT_TABLE)
                    ->where('post_id IN %in', $postIds)
                    ->execute();

                SearchHelper::removeSearchPost($postIds);

                delete_attachment($postIds);
            }

            $votes = dibi::select('vote_id')
                ->from(Tables::VOTE_DESC_TABLE)
                ->where('topic_id IN %in', $topicIds)
                ->fetchPairs(null, 'vote_id');

            if (count($votes)) {
                dibi::delete(Tables::VOTE_DESC_TABLE)
                    ->where('vote_id IN %in', $votes)
                    ->execute();

                dibi::delete(Tables::VOTE_RESULTS_TABLE)
                    ->where('vote_id IN %in', $votes)
                    ->execute();

                dibi::delete(Tables::VOTE_USERS_TABLE)
                    ->where('vote_id IN %in', $votes)
                    ->execute();
            }

            dibi::delete(Tables::TOPICS_WATCH_TABLE)
                ->where('topic_id IN %in', $topicIds)
                ->execute();

			Sync::oneForum($forumId);

            if (!empty($topicId)) {
                $redirectPage = 'viewforum.php?' . POST_FORUM_URL . "=$forumId&amp;sid=" . $userdata['session_id'];
                $l_redirect   = sprintf($lang['Click_return_forum'], '<a href="' . $redirectPage . '">', '</a>');
            } else {
                $redirectPage = 'modcp.php?' . POST_FORUM_URL . "=$forumId&amp;sid=" . $userdata['session_id'];
                $l_redirect   = sprintf($lang['Click_return_modcp'], '<a href="' . $redirectPage . '">', '</a>');
            }

            $template->assignVars(
                [
                    'META' => '<meta http-equiv="refresh" content="3;url=' . $redirectPage . '">'
                ]
            );

            message_die(GENERAL_MESSAGE, $lang['Topics_Removed'] . '<br /><br />' . $l_redirect);
		} else {
			// Not confirmed, show confirmation message
            if (empty($_POST['topic_id_list']) && empty($topicId)) {
                message_die(GENERAL_MESSAGE, $lang['None_selected']);
			}

			$hidden_fields = '<input type="hidden" name="sid" value="' . $userdata['session_id'] . '" /><input type="hidden" name="mode" value="' . $mode . '" /><input type="hidden" name="' . POST_FORUM_URL . '" value="' . $forumId . '" />';

            if (isset($_POST['topic_id_list'])) {
				foreach ($_POST['topic_id_list'] as $topic) {
					$hidden_fields .= '<input type="hidden" name="topic_id_list[]" value="' . (int)$topic . '" />';
				}
			} else {
				$hidden_fields .= '<input type="hidden" name="' . POST_TOPIC_URL . '" value="' . $topicId . '" />';
			}

			//
			// Set template files
			//
            $template->setFileNames(['confirm' => 'confirm_body.tpl']);

            $template->assignVars(
                [
                    'MESSAGE_TITLE' => $lang['Confirm'],
                    'MESSAGE_TEXT'  => $lang['Confirm_delete_topic'],

                    'L_YES' => $lang['Yes'],
                    'L_NO'  => $lang['No'],

                    'S_CONFIRM_ACTION' => Session::appendSid('modcp.php'),
                    'S_HIDDEN_FIELDS'  => $hidden_fields
                ]
            );

            $template->pparse('confirm');

            PageHelper::footer($template, $userdata, $lang, $gen_simple_header);
		}
		break;

	case 'move':
        PageHelper::header($template, $userdata, $board_config, $lang, $images, $theme, $lang['Mod_CP'], $gen_simple_header);

        if ($confirm) {
            if (empty($_POST['topic_id_list']) && empty($topicId)) {
                message_die(GENERAL_MESSAGE, $lang['None_selected']);
            }

			$new_forum_id = (int)$_POST['new_forum'];
			$old_forum_id = $forumId;

			$check_forum_id = dibi::select('forum_id')
                ->from(Tables::FORUMS_TABLE)
                ->where('forum_id = %i', $new_forum_id)
                ->fetchSingle();

			if (!$check_forum_id) {
				message_die(GENERAL_MESSAGE, 'New forum does not exist');
			}

            if ($new_forum_id !== $old_forum_id) {
                $topics_ids = isset($_POST['topic_id_list']) ? $_POST['topic_id_list'] : [$topicId];

				$topics = dibi::select('*')
                    ->from(Tables::TOPICS_TABLE)
                    ->where('topic_id IN %in', $topics_ids)
                    ->where('forum_id = %i', $old_forum_id)
                    ->where('topic_status <> %i', TOPIC_MOVED)
                    ->fetchAll();

                foreach ($topics as $topic) {
                    if (isset($_POST['move_leave_shadow'])) {
					    $insert_data = [
					        'forum_id' => $old_forum_id,
                            'topic_title' => $topic->topic_title,
                            'topic_poster' => $topic->topic_poster,
                            'topic_time' => $topic->topic_time,
                            'topic_status' => TOPIC_MOVED,
                            'topic_type' => POST_NORMAL,
                            'topic_vote' => $topic->topic_vote,
                            'topic_views' => $topic->topic_views,
                            'topic_replies' => $topic->topic_replies,
                            'topic_thanks' => $topic->topic_thanks,
                            'topic_first_post_id' => $topic->topic_first_post_id,
                            'topic_last_post_id' => $topic->topic_last_post_id,
                            'topic_moved_id' => $topic->topic_id,

                        ];

					    dibi::insert(Tables::TOPICS_TABLE, $insert_data)->execute();
					}

					dibi::update(Tables::TOPICS_TABLE, ['forum_id' => $new_forum_id])
                        ->where('topic_id = %i', $topic->topic_id)
                        ->execute();

					dibi::update(Tables::POSTS_TABLE, ['forum_id' => $new_forum_id])
                        ->where('topic_id = %i', $topic->topic_id)
                        ->execute();
				}

				// Sync the forum indexes
				Sync::oneForum($new_forum_id);
				Sync::oneForum($old_forum_id);

				$message = $lang['Topics_Moved'] . '<br /><br />';

			} else {
				$message = $lang['No_Topics_Moved'] . '<br /><br />';
			}

            if (!empty($topicId)) {
				$redirectPage = 'viewtopic.php?' . POST_TOPIC_URL . "=$topicId&amp;sid=" . $userdata['session_id'];
				$message      .= sprintf($lang['Click_return_topic'], '<a href="' . $redirectPage . '">', '</a>');
			} else {
				$redirectPage = 'modcp.php?' . POST_FORUM_URL . "=$forumId&amp;sid=" . $userdata['session_id'];
				$message      .= sprintf($lang['Click_return_modcp'], '<a href="' . $redirectPage . '">', '</a>');
			}

			$message .= '<br><br>' . sprintf($lang['Click_return_forum'], '<a href="' . 'viewforum.php?' . POST_FORUM_URL . "=$old_forum_id&amp;sid=" . $userdata['session_id'] . '">', '</a>') . '<br /><br />';

            $template->assignVars(['META' => '<meta http-equiv="refresh" content="3;url=' . $redirectPage . '">']);

            message_die(GENERAL_MESSAGE, $message);
		} else {
            if (empty($_POST['topic_id_list']) && empty($topicId)) {
                message_die(GENERAL_MESSAGE, $lang['None_selected']);
            }

			$hidden_fields = '<input type="hidden" name="sid" value="' . $userdata['session_id'] . '" /><input type="hidden" name="mode" value="' . $mode . '" /><input type="hidden" name="' . POST_FORUM_URL . '" value="' . $forumId . '" />';

            if (isset($_POST['topic_id_list'])) {
				foreach ($_POST['topic_id_list'] as $topic) {
					$hidden_fields .= '<input type="hidden" name="topic_id_list[]" value="' . (int)$topic . '" />';
				}
			} else {
				$hidden_fields .= '<input type="hidden" name="' . POST_TOPIC_URL . '" value="' . $topicId . '" />';
			}

			//
			// Set template files
			//
            $template->setFileNames(['movetopic' => 'modcp_move.tpl']);
            $template->assignVars(
                [
                    'MESSAGE_TITLE' => $lang['Confirm'],
                    'MESSAGE_TEXT'  => $lang['Confirm_move_topic'],

                    'L_MOVE_TO_FORUM' => $lang['Move_to_forum'],
                    'L_LEAVESHADOW'   => $lang['Leave_shadow_topic'],
                    'L_YES'           => $lang['Yes'],
                    'L_NO'            => $lang['No'],

                    'S_FORUM_SELECT'  => make_forum_select('new_forum', $forumId),
                    'S_MODCP_ACTION'  => Session::appendSid('modcp.php'),
                    'S_HIDDEN_FIELDS' => $hidden_fields
                ]
            );

            $template->pparse('movetopic');

            PageHelper::footer($template, $userdata, $lang, $gen_simple_header);
		}
		break;

	case 'lock':
        if (empty($_POST['topic_id_list']) && empty($topicId)) {
            message_die(GENERAL_MESSAGE, $lang['None_selected']);
        }

        $topics = isset($_POST['topic_id_list']) ? $_POST['topic_id_list'] : [$topicId];

        // TODO there is no check if ids exists
		dibi::update(Tables::TOPICS_TABLE, ['topic_status' => TOPIC_LOCKED])
            ->where('topic_id IN %in', $topics)
            ->where('forum_id = %i', $forumId)
            ->where('topic_moved_id = %i', 0)
            ->execute();

        if (!empty($topicId)) {
			$redirectPage = 'viewtopic.php?' . POST_TOPIC_URL . "=$topicId&amp;sid=" . $userdata['session_id'];
			$message      = sprintf($lang['Click_return_topic'], '<a href="' . $redirectPage . '">', '</a>');
		} else {
			$redirectPage = 'modcp.php?' . POST_FORUM_URL . "=$forumId&amp;sid=" . $userdata['session_id'];
			$message      = sprintf($lang['Click_return_modcp'], '<a href="' . $redirectPage . '">', '</a>');
		}

		$message .= '<br><br>' . sprintf($lang['Click_return_forum'], '<a href="' . 'viewforum.php?' . POST_FORUM_URL . "=$forumId&amp;sid=" . $userdata['session_id'] . '">', '</a>') . '<br /><br />';

        $template->assignVars(['META' => '<meta http-equiv="refresh" content="3;url=' . $redirectPage . '">']);

        message_die(GENERAL_MESSAGE, $lang['Topics_Locked'] . '<br /><br />' . $message);

		break;

	case 'unlock':
        if (empty($_POST['topic_id_list']) && empty($topicId)) {
			message_die(GENERAL_MESSAGE, $lang['None_selected']);
		}

        $topics = isset($_POST['topic_id_list']) ? $_POST['topic_id_list'] : [$topicId];

        dibi::update(Tables::TOPICS_TABLE, ['topic_status' => TOPIC_UNLOCKED])
            ->where('topic_id IN %in', $topics)
            ->where('forum_id = %i', $forumId)
            ->where('topic_moved_id = %i', 0)
            ->execute();

        if (!empty($topicId)) {
			$redirectPage = 'viewtopic.php?' . POST_TOPIC_URL . "=$topicId&amp;sid=" . $userdata['session_id'];
			$message      = sprintf($lang['Click_return_topic'], '<a href="' . $redirectPage . '">', '</a>');
		} else {
			$redirectPage = 'modcp.php?' . POST_FORUM_URL . "=$forumId&amp;sid=" . $userdata['session_id'];
			$message      = sprintf($lang['Click_return_modcp'], '<a href="' . $redirectPage . '">', '</a>');
		}

		$message .= '<br><br>' . sprintf($lang['Click_return_forum'], '<a href="' . 'viewforum.php?' . POST_FORUM_URL . "=$forumId&amp;sid=" . $userdata['session_id'] . '">', '</a>') . '<br /><br />';

        $template->assignVars(
            [
                'META' => '<meta http-equiv="refresh" content="3;url=' . $redirectPage . '">'
            ]
        );

        message_die(GENERAL_MESSAGE, $lang['Topics_Unlocked'] . '<br /><br />' . $message);

		break;

	case 'split':
		$page_title = $lang['Mod_CP'];

        PageHelper::header($template, $userdata, $board_config, $lang, $images,  $theme, $page_title, $gen_simple_header);

        $posts = [];

        /**
         * TODO
         */
		if (isset($_POST['split_type_all']) || isset($_POST['split_type_beyond'])) {
		    if (!isset($_POST['post_id_list'])) {
                message_die(GENERAL_MESSAGE, $lang['None_selected']);
            }

			$posts = $_POST['post_id_list'];
		}

		if (count($posts)) {
		    $postIds = dibi::select('post_id')
                ->from(Tables::POSTS_TABLE)
                ->where('post_id IN %in', $posts)
                ->where('forum_id = %i', $forumId)
                ->fetchPairs(null, 'post_id');

			if (!count($postIds)) {
				message_die(GENERAL_MESSAGE, $lang['None_selected']);
			}

			// TODO!!!!!!!!
			$posts = dibi::select(['post_id', 'poster_id', 'topic_id', 'post_time'])
                ->from(Tables::POSTS_TABLE)
                ->where('post_id IN %in', $postIds)
                ->orderBy('post_time', dibi::ASC)
                ->fetchAll();

			if ($posts) {
				$first_poster = $posts[0]->poster_id;
				$topicId      = $posts[0]->topic_id;
				$post_time    = $posts[0]->post_time;

				$user_ids_sql = [];
				$post_ids_sql = [];

				foreach ($posts as $post) {
				    $user_ids_sql[] = $post->poster_id;
                    $post_ids_sql[] = $post->post_id;
                }

				$post_subject = trim(htmlspecialchars($_POST['subject']));

				if (empty($post_subject)) {
					message_die(GENERAL_MESSAGE, $lang['Empty_subject']);
				}

				$new_forum_id = (int)$_POST['new_forum_id'];
				$topic_time = time();

				$check_forum_id = dibi::select('forum_id')
                    ->from(Tables::FORUMS_TABLE)
                    ->where('forum_id = %i', $new_forum_id)
                    ->fetchSingle();

				if (!$check_forum_id) {
					message_die(GENERAL_MESSAGE, 'New forum does not exist');
				}

				$insert_data = [
				    'topic_title'  => $post_subject,
                    'topic_poster' => $first_poster,
                    'topic_time'   => $topic_time,
                    'forum_id'     => $new_forum_id,
                    'topic_status' => TOPIC_UNLOCKED,
                    'topic_type'   => POST_NORMAL
                ];

                $new_topic_id = dibi::insert(Tables::TOPICS_TABLE, $insert_data)->execute(dibi::IDENTIFIER);

				// Update topic watch table, switch users whose posts
				// have moved, over to watching the new topic

                dibi::update(Tables::TOPICS_WATCH_TABLE, ['topic_id' => $new_topic_id])
                    ->where('topic_id = %i', $topicId)
                    ->where('user_id IN %in', $user_ids_sql)
                    ->execute();

                if (!empty($_POST['split_type_beyond'])) {
                    dibi::update(Tables::POSTS_TABLE, ['topic_id' => $new_topic_id, 'forum_id' => $new_forum_id])
                        ->where('post_time >= %i', $post_time)
                        ->where('topic_id = %i', $topicId)
                        ->execute();
                } else {
                    dibi::update(Tables::POSTS_TABLE, ['topic_id' => $new_topic_id, 'forum_id' => $new_forum_id])
                        ->where('post_id IN %in', $post_ids_sql)
                        ->execute();
                }

                Sync::oneTopic($new_topic_id);
                Sync::oneTopic($topicId);

                Sync::oneForum($new_forum_id);
                Sync::oneForum($forumId);

                $template->assignVars(
                    [
                        'META' => '<meta http-equiv="refresh" content="3;url=' . 'viewtopic.php?' . POST_TOPIC_URL . "=$topicId&amp;sid=" . $userdata['session_id'] . '">'
                    ]
                );

                $message = $lang['Topic_split'] . '<br /><br />' . sprintf($lang['Click_return_topic'], '<a href="' . 'viewtopic.php?' . POST_TOPIC_URL . "=$topicId&amp;sid=" . $userdata['session_id'] . '">', '</a>');
				message_die(GENERAL_MESSAGE, $message);
			}
		} else {
			//
			// Set template files
			//
            $template->setFileNames(['split_body' => 'modcp_split.tpl']);

            $posts = dibi::select(['u.username', 'p.*', 'pt.post_text', 'pt.bbcode_uid', 'pt.post_subject', 'p.post_username'])
                ->from(Tables::POSTS_TABLE)
                ->as('p')
                ->innerJoin(Tables::USERS_TABLE)
                ->as('u')
                ->on('p.poster_id = u.user_id')
                ->innerJoin(Tables::POSTS_TEXT_TABLE)
                ->as('pt')
                ->on('p.post_id = pt.post_id')
                ->where('p.topic_id = %i', $topicId)
                ->orderBy('p.post_time', dibi::ASC)
                ->fetchAll();

            $total_posts = count($posts);

			$s_hidden_fields = '<input type="hidden" name="sid" value="' . $userdata['session_id'] . '" /><input type="hidden" name="' . POST_FORUM_URL . '" value="' . $forumId . '" /><input type="hidden" name="' . POST_TOPIC_URL . '" value="' . $topicId . '" /><input type="hidden" name="mode" value="split" />';

			if ($total_posts) {
                $template->assignVars(
                    [
                        'L_SPLIT_TOPIC'         => $lang['Split_Topic'],
                        'L_SPLIT_TOPIC_EXPLAIN' => $lang['Split_Topic_explain'],
                        'L_AUTHOR'              => $lang['Author'],
                        'L_MESSAGE'             => $lang['Message'],
                        'L_SELECT'              => $lang['Select'],
                        'L_SPLIT_SUBJECT'       => $lang['Split_title'],
                        'L_SPLIT_FORUM'         => $lang['Split_forum'],
                        'L_POSTED'              => $lang['Posted'],
                        'L_SPLIT_POSTS'         => $lang['Split_posts'],
                        'L_SUBMIT'              => $lang['Submit'],
                        'L_SPLIT_AFTER'         => $lang['Split_after'],
                        'L_POST_SUBJECT'        => $lang['Post_subject'],
                        'L_MARK_ALL'            => $lang['Mark_all'],
                        'L_UNMARK_ALL'          => $lang['Unmark_all'],
                        'L_POST'                => $lang['Post'],

                        'FORUM_NAME' => htmlspecialchars($forumName, ENT_QUOTES),

                        'U_VIEW_FORUM' => Session::appendSid('viewforum.php?' . POST_FORUM_URL . "=$forumId"),

                        'S_SPLIT_ACTION'  => Session::appendSid('modcp.php'),
                        'S_HIDDEN_FIELDS' => $s_hidden_fields,
                        'S_FORUM_SELECT'  => make_forum_select('new_forum_id', false, $forumId)
                    ]
                );

                //
				// Define censored word matches
				//
				$orig_word = [];
				$replacement_word = [];
				obtain_word_list($orig_word, $replacement_word);
				$count_orig_word = count($orig_word);

				foreach ($posts as $i => $post) {
					$message = $post->post_text;
					$post_subject = $post->post_subject !== '' ? $post->post_subject : $topic_title;

					//
					// If the board has HTML off but the post has HTML
					// on then we process it, else leave it alone
					//
                    if (!$board_config['allow_html'] && $post->enable_html) {
                        $message = preg_replace('#(<)([\/]?.*?)(>)#is', '&lt;\\2&gt;', $message);
                    }

                    if ($post->bbcode_uid !== '') {
						$message = $board_config['allow_bbcode'] ? bbencode_second_pass($message, $post->bbcode_uid) : preg_replace('/\:[0-9a-z\:]+\]/si', ']', $message);
					}

                    if ($count_orig_word) {
						$post_subject = preg_replace($orig_word, $replacement_word, $post_subject);
						$message      = preg_replace($orig_word, $replacement_word, $message);
					}

					$message = make_clickable($message);

					if ($board_config['allow_smilies'] && $post->enable_smilies) {
						$message = smilies_pass($message);
					}

					$message = nl2br($message);

					$row_color = !($i % 2) ? $theme['td_color1'] : $theme['td_color2'];
					$row_class = !($i % 2) ? $theme['td_class1'] : $theme['td_class2'];

					$checkbox = $i > 0 ? '<input type="checkbox" name="post_id_list[]" value="' . $post->post_id . '" />' : '&nbsp;';

                    $template->assignBlockVars('postrow',
                        [
                            'ROW_COLOR'    => '#' . $row_color,
                            'ROW_CLASS'    => $row_class,
                            'POSTER_NAME'  => $post->username,
                            'POST_DATE'    => create_date($board_config['default_dateformat'], $post->post_time, $board_config['board_timezone']),
                            'POST_SUBJECT' => $post_subject,
                            'MESSAGE'      => $message,
                            'POST_ID'      => $post->post_id,

                            'S_SPLIT_CHECKBOX' => $checkbox
                        ]
                    );
                }

				$template->pparse('split_body');
			}
		}
		break;

	case 'ip':
		$page_title = $lang['Mod_CP'];

        PageHelper::header($template, $userdata, $board_config, $lang, $images,  $theme, $page_title, $gen_simple_header);

		$rdns_ip_num = isset($_GET['rdns']) ? $_GET['rdns'] : '';

        if (!$postId) {
			message_die(GENERAL_MESSAGE, $lang['No_such_post']);
		}

		//
		// Set template files
		//
        $template->setFileNames(['viewip' => 'modcp_viewip.tpl']);

        $post = dibi::select(['poster_ip', 'poster_id'])
            ->from(Tables::POSTS_TABLE)
            ->where('post_id = %i', $postId)
            ->where('forum_id = %i', $forumId)
            ->fetch();

        if (!$post) {
            message_die(GENERAL_MESSAGE, $lang['No_such_post']);
        }

		$ip_this_post = decode_ip($post->poster_ip);
		$ip_this_post = $rdns_ip_num === $ip_this_post ? htmlspecialchars(gethostbyaddr($ip_this_post)) : $ip_this_post;

		$poster_id = $post->poster_id;

        $template->assignVars(
            [
                'L_IP_INFO'      => $lang['IP_info'],
                'L_THIS_POST_IP' => $lang['This_posts_IP'],
                'L_OTHER_IPS'    => $lang['Other_IP_this_user'],
                'L_OTHER_USERS'  => $lang['Users_this_IP'],
                'L_LOOKUP_IP'    => $lang['Lookup_IP'],
                'L_SEARCH'       => $lang['Search'],

                'SEARCH_IMG' => $images['icon_search'],

                'IP' => $ip_this_post,

                'U_LOOKUP_IP' => 'modcp.php?mode=ip&amp;' . POST_POST_URL . "=$postId&amp;" . POST_TOPIC_URL . "=$topicId&amp;rdns=$ip_this_post&amp;sid=" . $userdata['session_id']
            ]
        );

        //
		// Get other IP's this user has posted under
		//
        $order_by = Config::DBMS === 'msaccess' ? 'COUNT(*)' : 'postings';

        $rows = dibi::select('poster_ip')
            ->select('COUNT(*)')
            ->as('postings')
            ->from(Tables::POSTS_TABLE)
            ->where('poster_id = %i', $poster_id)
            ->groupBy('poster_ip')
            ->orderBy($order_by, dibi::DESC)
            ->fetchAll();

        foreach ($rows as $i => $row) {
            if ($row->poster_ip === $post->poster_ip) {
                $template->assignVars(
                    [
                        'POSTS' => $row->postings . ' ' . (($row->postings === 1) ? $lang['Post'] : $lang['Posts'])
                    ]
                );
                continue;
            }

            $ip = decode_ip($row->poster_ip);
            $ip = $rdns_ip_num === $row->poster_ip || $rdns_ip_num === 'all' ? htmlspecialchars(gethostbyaddr($ip)) : $ip;

            $row_color = !($i % 2) ? $theme['td_color1'] : $theme['td_color2'];
            $row_class = !($i % 2) ? $theme['td_class1'] : $theme['td_class2'];

            $template->assignBlockVars('iprow',
                [
                    'ROW_COLOR' => '#' . $row_color,
                    'ROW_CLASS' => $row_class,
                    'IP'        => $ip,
                    'POSTS'     => $row->postings . ' ' . $row->postings === 1 ? $lang['Post'] : $lang['Posts'],

                    'U_LOOKUP_IP' => 'modcp.php?mode=ip&amp;' . POST_POST_URL . "=$postId&amp;" . POST_TOPIC_URL . "=$topicId&amp;rdns=" . $row->poster_ip . '&amp;sid=' . $userdata['session_id']
                ]
            );
        }

		//
		// Get other users who've posted under this IP
		//
        $order_by = Config::DBMS === 'msaccess' ? 'COUNT(*)' : 'postings';

        $rows = dibi::select('u.user_id')
            ->select('u.username')
            ->select('COUNT(*)')
            ->as('postings')
            ->from(Tables::USERS_TABLE)
            ->as('u')
            ->innerJoin(Tables::POSTS_TABLE)
            ->as('p')
            ->on('p.poster_id = u.user_id')
            ->where('poster_ip = %s', $post->poster_ip)
            ->groupBy('u.user_id')
            ->groupBy('u.username')
            ->orderBy($order_by, dibi::DESC)
            ->fetchAll();

        foreach ($rows as $i => $row) {
            $id       = $row->user_id;
            $username = $id === ANONYMOUS ? $lang['Guest'] : $row->username;

            $row_color = !($i % 2) ? $theme['td_color1'] : $theme['td_color2'];
            $row_class = !($i % 2) ? $theme['td_class1'] : $theme['td_class2'];

            if ($id === ANONYMOUS) {
                $profile = 'modcp.php?mode=ip&amp;' . POST_POST_URL . '=' . $postId . '&amp;' . POST_TOPIC_URL . '=' . $topicId . '&amp;sid=' . $userdata['session_id'];
            } else {
                $profile = Session::appendSid('profile.php?mode=viewprofile&amp;' . POST_USERS_URL . "=$id");
            }

            $template->assignBlockVars('userrow',
                [
                    'ROW_COLOR'      => '#' . $row_color,
                    'ROW_CLASS'      => $row_class,
                    'USERNAME'       => $username,
                    'POSTS'          => $row->postings . ' ' . (($row->postings === 1) ? $lang['Post'] : $lang['Posts']),
                    'L_SEARCH_POSTS' => sprintf($lang['Search_user_posts'], $username),

                    'U_PROFILE'     => $profile,
                    'U_SEARCHPOSTS' => Session::appendSid('search.php?search_author=' . (($id === ANONYMOUS) ? 'Anonymous' : urlencode($username)) . '&amp;show_results=topics')
                ]
            );
        }

		$template->pparse('viewip');

		break;

	default:
		$page_title = $lang['Mod_CP'];

        PageHelper::header($template, $userdata, $board_config, $lang, $images,  $theme, $page_title, $gen_simple_header);

        $template->assignVars(
            [
                'FORUM_NAME' => htmlspecialchars($forumName, ENT_QUOTES),

                'L_MOD_CP'         => $lang['Mod_CP'],
                'L_MOD_CP_EXPLAIN' => $lang['Mod_CP_explain'],
                'L_SELECT'         => $lang['Select'],
                'L_DELETE'         => $lang['Delete'],
                'L_MOVE'           => $lang['Move'],
                'L_LOCK'           => $lang['Lock'],
                'L_UNLOCK'         => $lang['Unlock'],
                'L_TOPICS'         => $lang['Topics'],
                'L_REPLIES'        => $lang['Replies'],
                'L_THANKS'         => $lang['Thanks'],
                'L_AUTHOR'         => $lang['Author'],
                'L_LASTPOST'       => $lang['Last_Post'],

                'U_VIEW_FORUM'    => Session::appendSid('viewforum.php?' . POST_FORUM_URL . "=$forumId"),
                'S_HIDDEN_FIELDS' => '<input type="hidden" name="sid" value="' . $userdata['session_id'] . '" /><input type="hidden" name="' . POST_FORUM_URL . '" value="' . $forumId . '" />',
                'S_MODCP_ACTION'  => Session::appendSid('modcp.php')
            ]
        );

        $template->setFileNames(['body' => 'modcp_body.tpl']);
        make_jumpbox('modcp.php');

		//
		// Define censored word matches
		//
		$orig_word = [];
		$replacement_word = [];
		obtain_word_list($orig_word, $replacement_word);

		$count_orig_word = count($orig_word);

		$rows = dibi::select(['t.*', 'u.username', 'u.user_id', 'p.post_time'])
            ->from(Tables::TOPICS_TABLE)
            ->as('t')
            ->innerJoin(Tables::USERS_TABLE)
            ->as('u')
            ->on('t.topic_poster = u.user_id')
            ->innerJoin(Tables::POSTS_TABLE)
            ->as('p')
            ->on('p.post_id = t.topic_last_post_id')
            ->where('t.forum_id = %i', $forumId)
            ->orderBy('t.topic_type', dibi::DESC)
            ->orderBy('p.post_time', dibi::DESC)
            ->limit($board_config['topics_per_page'])
            ->offset($start)
            ->fetchAll();

        $topic_title = '';

        foreach ($rows as $row) {
			$topic_title = $row->topic_title;

            if ($row->topic_status === TOPIC_LOCKED) {
                $folder_img = $images['folder_locked'];
                $folder_alt = $lang['Topic_locked'];
            } else {
                if ($row->topic_type === POST_ANNOUNCE) {
                    $folder_img = $images['folder_announce'];
                    $folder_alt = $lang['Topic_Announcement'];
                } elseif ($row->topic_type === POST_STICKY) {
                    $folder_img = $images['folder_sticky'];
                    $folder_alt = $lang['Topic_Sticky'];
                } else {
                    $folder_img = $images['folder'];
                    $folder_alt = $lang['No_new_posts'];
                }
            }

			$topicId      = $row->topic_id;
			$topic_type   = $row->topic_type;
			$topic_status = $row->topic_status;

            if ($topic_type === POST_ANNOUNCE) {
                $topic_type = $lang['Topic_Announcement'] . ' ';
            } elseif ($topic_type === POST_STICKY) {
                $topic_type = $lang['Topic_Sticky'] . ' ';
            } elseif ($topic_status === TOPIC_MOVED) {
                $topic_type = $lang['Topic_Moved'] . ' ';
            } else {
                $topic_type = '';
            }

            if ($row->topic_vote) {
                $topic_type .= $lang['Topic_Poll'] . ' ';
            }

            $topic_title = $row->topic_title;

            if ($count_orig_word) {
                $topic_title = preg_replace($orig_word, $replacement_word, $topic_title);
            }

			$u_view_topic  = 'modcp.php?mode=split&amp;' . POST_TOPIC_URL . "=$topicId&amp;sid=" . $userdata['session_id'];

			$last_post_time = create_date($board_config['default_dateformat'], $row->post_time, $board_config['board_timezone']);

            $template->assignBlockVars('topicrow',
                [
                    'U_VIEW_TOPIC' => $u_view_topic,

                    'TOPIC_FOLDER_IMG' => $folder_img,
                    'TOPIC_TYPE'       => $topic_type,
                    'TOPIC_TITLE'      => $topic_title,
                    'REPLIES'          => $row->topic_replies,
                    'THANKS'           => $row->topic_thanks,
                    'AUTHOR'           => $row->username,
                    'LAST_POST_TIME'   => $last_post_time,
                    'TOPIC_ID'         => $topicId,
                    'TOPIC_ATTACHMENT_IMG' => topic_attachment_image($row->topic_attachment),

                    'L_TOPIC_FOLDER_ALT' => $folder_alt
                ]
            );
        }

        $template->assignVars([
            'PAGINATION'  => generate_pagination('modcp.php?' . POST_FORUM_URL . "=$forumId&amp;sid=" . $userdata['session_id'], $forumTopics, $board_config['topics_per_page'], $start),
            'PAGE_NUMBER' => sprintf($lang['Page_of'], floor($start / $board_config['topics_per_page']) + 1, ceil($forumTopics / $board_config['topics_per_page'])),

            'L_GOTO_PAGE' => $lang['Goto_page']
            ]);

        $template->pparse('body');

		break;
}

PageHelper::footer($template, $userdata, $lang, $gen_simple_header);

?>