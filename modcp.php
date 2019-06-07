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
$phpbb_root_path = './';

include $phpbb_root_path . 'common.php';
include $phpbb_root_path . 'includes/bbcode.php';
include $phpbb_root_path . 'includes/functions_admin.php';

//
// Obtain initial var settings
//
if (isset($_GET[POST_FORUM_URL]) || isset($_POST[POST_FORUM_URL])) {
    $forum_id = isset($_POST[POST_FORUM_URL]) ? (int)$_POST[POST_FORUM_URL] : (int)$_GET[POST_FORUM_URL];
} else {
    $forum_id = '';
}

if (isset($_GET[POST_POST_URL]) || isset($_POST[POST_POST_URL])) {
    $post_id = isset($_POST[POST_POST_URL]) ? (int)$_POST[POST_POST_URL] : (int)$_GET[POST_POST_URL];
} else {
    $post_id = '';
}

if (isset($_GET[POST_TOPIC_URL]) || isset($_POST[POST_TOPIC_URL])) {
    $topic_id = isset($_POST[POST_TOPIC_URL]) ? (int)$_POST[POST_TOPIC_URL] : (int)$_GET[POST_TOPIC_URL];
} else {
    $topic_id = '';
}

$confirm = $_POST['confirm'] ? true : 0;

//
// Continue var definitions
//
$start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
$start = $start < 0 ? 0 : $start;

$delete = isset($_POST['delete']);
$move   = isset($_POST['move']);
$lock   = isset($_POST['lock']);
$unlock = isset($_POST['unlock']);

if (isset($_POST['mode']) || isset($_GET['mode'])) {
    $mode = isset($_POST['mode']) ? $_POST['mode'] : $_GET['mode'];
    $mode = htmlspecialchars($mode);
} else {
    if ($delete) {
        $mode = 'delete';
    } elseif ($move) {
        $mode = 'move';
    } elseif ($lock) {
        $mode = 'lock';
    } elseif ($unlock) {
        $mode = 'unlock';
    } else {
        $mode = '';
    }
}

// session id check
if (!empty($_POST['sid']) || !empty($_GET['sid'])) {
    $sid = !empty($_POST['sid']) ? $_POST['sid'] : $_GET['sid'];
} else {
    $sid = '';
}

//
// Obtain relevant data
//
if ( !empty($topic_id) ) {
    $topic_row = dibi::select(['f.forum_id','f.forum_name', 'f.forum_topics'])
        ->from(TOPICS_TABLE)
        ->as('t')
        ->innerJoin(FORUMS_TABLE)
        ->as('f')
        ->on('f.forum_id = t.forum_id')
        ->where('t.topic_id = %i', $topic_id)
        ->fetch();

    if (!$topic_row) {
        message_die(GENERAL_MESSAGE, 'Topic_post_not_exist');
    }

	$forum_topics = ( $topic_row->forum_topics === 0 ) ? 1 : $topic_row->forum_topics;
	$forum_id = $topic_row->forum_id;
	$forum_name = $topic_row->forum_name;
} elseif ( !empty($forum_id) ) {
    $topic_row = dibi::select(['forum_name', 'forum_topics'])
        ->from(FORUMS_TABLE)
        ->where('forum_id = %i', $forum_id)
        ->fetch();

    if (!$topic_row) {
        message_die(GENERAL_MESSAGE, 'Forum_not_exist');
    }

	$forum_topics = ( $topic_row->forum_topics === 0 ) ? 1 : $topic_row->forum_topics;
	$forum_name = $topic_row->forum_name;
} else {
	message_die(GENERAL_MESSAGE, 'Forum_not_exist');
}

//
// Start session management
//
$userdata = Session::pageStart($user_ip, $forum_id);
init_userprefs($userdata);
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
    if ($topic_id) {
        $redirect = 'viewtopic.php?' . POST_TOPIC_URL . "=$topic_id";
    } elseif ($forum_id) {
        $redirect = 'viewforum.php?' . POST_FORUM_URL . "=$forum_id";
    } else {
        $redirect = 'index.php';
    }

    redirect(Session::appendSid($redirect, true));
}

//
// Start auth check
//
$is_auth = Auth::authorize(AUTH_ALL, $forum_id, $userdata);

if ( !$is_auth['auth_mod'] ) {
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

		$page_title = $lang['Mod_CP'];
		include $phpbb_root_path . 'includes/page_header.php';

        if ($confirm) {
            if (empty($_POST['topic_id_list']) && empty($topic_id)) {
                message_die(GENERAL_MESSAGE, $lang['None_selected']);
            }

			include $phpbb_root_path . 'includes/functions_search.php';

            $topics = isset($_POST['topic_id_list']) ? $_POST['topic_id_list'] : [$topic_id];

			$topic_ids = dibi::select('topic_id')
                ->from(TOPICS_TABLE)
                ->where('topic_id IN %in', $topics)
                ->where('forum_id = %i', $forum_id)
                ->fetchPairs(null, 'topic_id');

			if (!count($topic_ids)) {
				message_die(GENERAL_MESSAGE, $lang['None_selected']);
			}

			$posters_of_topic = dibi::select('poster_id')
                ->select('COUNT(post_id)')
                ->as('posts')
                ->from(POSTS_TABLE)
                ->where('topic_id IN %in', $topic_ids)
                ->groupBy('poster_id')
                ->fetchAll();

			foreach ($posters_of_topic as $poster) {
                dibi::update(USERS_TABLE, ['user_posts%sql' => 'user_posts - ' . $poster['posts']])
                    ->where('user_id = %i', $poster->poster_id)
                    ->execute();
            }

            $post_ids = dibi::select('post_id')
                ->from(POSTS_TABLE)
                ->where('topic_id IN %in', $topic_ids)
                ->fetchPairs(null, 'post_id');

			$votes = dibi::select('vote_id')
                ->from(VOTE_DESC_TABLE)
                ->where('topic_id IN %in', $topic_ids)
                ->fetchPairs(null, 'vote_id');

			//
			// Got all required info so go ahead and start deleting everything
			//

            dibi::delete(TOPICS_TABLE)
                ->where('topic_id IN %in OR topic_moved_in', $topic_ids, $topic_ids)
                ->execute();

            if (count($post_ids)) {
                dibi::delete(POSTS_TABLE)
                    ->where('post_id IN %in', $post_ids)
                    ->execute();

                dibi::delete(POSTS_TEXT_TABLE)
                    ->where('post_id IN %in', $post_ids)
                    ->execute();

                remove_search_post($post_ids);
            }

            if (count($votes)) {
                dibi::delete(VOTE_DESC_TABLE)
                    ->where('vote_id IN %in', $votes)
                    ->execute();

                dibi::delete(VOTE_RESULTS_TABLE)
                    ->where('vote_id IN %in', $votes)
                    ->execute();

                dibi::delete(VOTE_USERS_TABLE)
                    ->where('vote_id IN %in', $votes)
                    ->execute();
            }

            dibi::delete(TOPICS_WATCH_TABLE)
                ->where('topic_id IN %in', $topic_ids)
                ->execute();

			sync('forum', $forum_id);

            if (!empty($topic_id)) {
                $redirect_page = 'viewforum.php?' . POST_FORUM_URL . "=$forum_id&amp;sid=" . $userdata['session_id'];
                $l_redirect    = sprintf($lang['Click_return_forum'], '<a href="' . $redirect_page . '">', '</a>');
            } else {
                $redirect_page = 'modcp.php?' . POST_FORUM_URL . "=$forum_id&amp;sid=" . $userdata['session_id'];
                $l_redirect    = sprintf($lang['Click_return_modcp'], '<a href="' . $redirect_page . '">', '</a>');
            }

            $template->assignVars(
                [
                    'META' => '<meta http-equiv="refresh" content="3;url=' . $redirect_page . '">'
                ]
            );

            message_die(GENERAL_MESSAGE, $lang['Topics_Removed'] . '<br /><br />' . $l_redirect);
		} else {
			// Not confirmed, show confirmation message
            if (empty($_POST['topic_id_list']) && empty($topic_id)) {
                message_die(GENERAL_MESSAGE, $lang['None_selected']);
			}

			$hidden_fields = '<input type="hidden" name="sid" value="' . $userdata['session_id'] . '" /><input type="hidden" name="mode" value="' . $mode . '" /><input type="hidden" name="' . POST_FORUM_URL . '" value="' . $forum_id . '" />';

            if (isset($_POST['topic_id_list'])) {
                $topics = $_POST['topic_id_list'];

				foreach ($topics as $topic) {
					$hidden_fields .= '<input type="hidden" name="topic_id_list[]" value="' . (int)$topic . '" />';
				}
			} else {
				$hidden_fields .= '<input type="hidden" name="' . POST_TOPIC_URL . '" value="' . $topic_id . '" />';
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

			include $phpbb_root_path . 'includes/page_tail.php';
		}
		break;

	case 'move':
		$page_title = $lang['Mod_CP'];
		include $phpbb_root_path . 'includes/page_header.php';

        if ($confirm) {
            if (empty($_POST['topic_id_list']) && empty($topic_id)) {
                message_die(GENERAL_MESSAGE, $lang['None_selected']);
            }

			$new_forum_id = (int)$_POST['new_forum'];
			$old_forum_id = $forum_id;

			$check_forum_id = dibi::select('forum_id')
                ->from(FORUMS_TABLE)
                ->where('forum_id = %i', $new_forum_id)
                ->fetchSingle();
			
			if (!$check_forum_id) {
				message_die(GENERAL_MESSAGE, 'New forum does not exist');
			}

            if ($new_forum_id !== $old_forum_id) {
                $topics = isset($_POST['topic_id_list']) ? $_POST['topic_id_list'] : [$topic_id];

				$topic_rows = dibi::select('*')
                    ->from(TOPICS_TABLE)
                    ->where('topic_id IN %in', $topics)
                    ->where('forum_id = %i', $old_forum_id)
                    ->where('topic_status <> %i', TOPIC_MOVED)
                    ->fetchAll();

                foreach ($topic_rows as $topic_row) {
					$topic_id = $row[$i]['topic_id'];

                    if (isset($_POST['move_leave_shadow'])) {
					    $insert_data = [
					        'forum_id' => $old_forum_id,
                            'topic_title' => $topic_row->topic_title,
                            'topic_poster' => $topic_row->topic_poster,
                            'topic_time' => $topic_row->topic_time,
                            'topic_status' => TOPIC_MOVED,
                            'topic_type' => POST_NORMAL,
                            'topic_vote' => $topic_row->topic_vote,
                            'topic_views' => $topic_row->topic_views,
                            'topic_replies' => $topic_row->topic_replies,
                            'topic_first_post_id' => $topic_row->topic_first_post_id,
                            'topic_last_post_id' => $topic_row->topic_last_post_id,
                            'topic_moved_id' => $topic_id,

                        ];

					    dibi::insert(TOPICS_TABLE, $insert_data)->execute();
					}

					dibi::update(TOPICS_TABLE, ['forum_id' => $new_forum_id])
                        ->where('topic_id = %i', $topic_id)
                        ->execute();

					dibi::update(POSTS_TABLE, ['forum_id' => $new_forum_id])
                        ->where('topic_id = %i', $topic_id)
                        ->execute();
				}

				// Sync the forum indexes
				sync('forum', $new_forum_id);
				sync('forum', $old_forum_id);

				$message = $lang['Topics_Moved'] . '<br /><br />';

			} else {
				$message = $lang['No_Topics_Moved'] . '<br /><br />';
			}

            if (!empty($topic_id)) {
				$redirect_page = 'viewtopic.php?' . POST_TOPIC_URL . "=$topic_id&amp;sid=" . $userdata['session_id'];
				$message .= sprintf($lang['Click_return_topic'], '<a href="' . $redirect_page . '">', '</a>');
			} else {
				$redirect_page = 'modcp.php?' . POST_FORUM_URL . "=$forum_id&amp;sid=" . $userdata['session_id'];
				$message .= sprintf($lang['Click_return_modcp'], '<a href="' . $redirect_page . '">', '</a>');
			}

			$message = $message . '<br \><br \>' . sprintf($lang['Click_return_forum'], '<a href="' . 'viewforum.php?' . POST_FORUM_URL . "=$old_forum_id&amp;sid=" . $userdata['session_id'] . '">', '</a>');

            $template->assignVars(['META' => '<meta http-equiv="refresh" content="3;url=' . $redirect_page . '">']);

            message_die(GENERAL_MESSAGE, $message);
		} else {
            if (empty($_POST['topic_id_list']) && empty($topic_id)) {
                message_die(GENERAL_MESSAGE, $lang['None_selected']);
            }

			$hidden_fields = '<input type="hidden" name="sid" value="' . $userdata['session_id'] . '" /><input type="hidden" name="mode" value="' . $mode . '" /><input type="hidden" name="' . POST_FORUM_URL . '" value="' . $forum_id . '" />';

            if (isset($_POST['topic_id_list'])) {
                $topics = $_POST['topic_id_list'];

				foreach ($topics as $topic) {
					$hidden_fields .= '<input type="hidden" name="topic_id_list[]" value="' . (int)$topic . '" />';
				}
			} else {
				$hidden_fields .= '<input type="hidden" name="' . POST_TOPIC_URL . '" value="' . $topic_id . '" />';
			}

			//
			// Set template files
			//
            $template->setFileNames(
                [
                    'movetopic' => 'modcp_move.tpl'
                ]
            );

            $template->assignVars(
                [
                    'MESSAGE_TITLE' => $lang['Confirm'],
                    'MESSAGE_TEXT'  => $lang['Confirm_move_topic'],

                    'L_MOVE_TO_FORUM' => $lang['Move_to_forum'],
                    'L_LEAVESHADOW'   => $lang['Leave_shadow_topic'],
                    'L_YES'           => $lang['Yes'],
                    'L_NO'            => $lang['No'],

                    'S_FORUM_SELECT'  => make_forum_select('new_forum', $forum_id),
                    'S_MODCP_ACTION'  => Session::appendSid('modcp.php'),
                    'S_HIDDEN_FIELDS' => $hidden_fields
                ]
            );

            $template->pparse('movetopic');

			include $phpbb_root_path . 'includes/page_tail.php';
		}
		break;

	case 'lock':
        if (empty($_POST['topic_id_list']) && empty($topic_id)) {
            message_die(GENERAL_MESSAGE, $lang['None_selected']);
        }

        $topics = isset($_POST['topic_id_list']) ? $_POST['topic_id_list'] : [$topic_id];

        // TODO there is no check if ids exists
		dibi::update(TOPICS_TABLE, ['topic_status' => TOPIC_LOCKED])
            ->where('topic_id IN %in', $topics)
            ->where('forum_id = %i', $forum_id)
            ->where('topic_moved_id = %i', 0)
            ->execute();

        if (!empty($topic_id)) {
			$redirect_page = 'viewtopic.php?' . POST_TOPIC_URL . "=$topic_id&amp;sid=" . $userdata['session_id'];
			$message = sprintf($lang['Click_return_topic'], '<a href="' . $redirect_page . '">', '</a>');
		} else {
			$redirect_page = 'modcp.php?' . POST_FORUM_URL . "=$forum_id&amp;sid=" . $userdata['session_id'];
			$message = sprintf($lang['Click_return_modcp'], '<a href="' . $redirect_page . '">', '</a>');
		}

		$message = $message . '<br \><br \>' . sprintf($lang['Click_return_forum'], '<a href="' . 'viewforum.php?' . POST_FORUM_URL . "=$forum_id&amp;sid=" . $userdata['session_id'] . '">', '</a>');

        $template->assignVars(['META' => '<meta http-equiv="refresh" content="3;url=' . $redirect_page . '">']);

        message_die(GENERAL_MESSAGE, $lang['Topics_Locked'] . '<br /><br />' . $message);

		break;

	case 'unlock':
        if (empty($_POST['topic_id_list']) && empty($topic_id)) {
			message_die(GENERAL_MESSAGE, $lang['None_selected']);
		}

        $topics = isset($_POST['topic_id_list']) ? $_POST['topic_id_list'] : [$topic_id];


		// TODO there is no check if ids exists
        dibi::update(TOPICS_TABLE, ['topic_status' => TOPIC_UNLOCKED])
            ->where('topic_id IN %in', $topics)
            ->where('forum_id = %i', $forum_id)
            ->where('topic_moved_id = %i', 0)
            ->execute();

        if (!empty($topic_id)) {
			$redirect_page = 'viewtopic.php?' . POST_TOPIC_URL . "=$topic_id&amp;sid=" . $userdata['session_id'];
			$message = sprintf($lang['Click_return_topic'], '<a href="' . $redirect_page . '">', '</a>');
		} else {
			$redirect_page = 'modcp.php?' . POST_FORUM_URL . "=$forum_id&amp;sid=" . $userdata['session_id'];
			$message = sprintf($lang['Click_return_modcp'], '<a href="' . $redirect_page . '">', '</a>');
		}

		$message = $message . '<br \><br \>' . sprintf($lang['Click_return_forum'], '<a href="' . 'viewforum.php?' . POST_FORUM_URL . "=$forum_id&amp;sid=" . $userdata['session_id'] . '">', '</a>');

        $template->assignVars(
            [
                'META' => '<meta http-equiv="refresh" content="3;url=' . $redirect_page . '">'
            ]
        );

        message_die(GENERAL_MESSAGE, $lang['Topics_Unlocked'] . '<br /><br />' . $message);

		break;

	case 'split':
		$page_title = $lang['Mod_CP'];
		include $phpbb_root_path . 'includes/page_header.php';

        $posts = [];

		if (isset($_POST['split_type_all']) || isset($_POST['split_type_beyond'])) {
			$posts = $_POST['post_id_list'];
		}

		if (count($posts)) {
		    $post_ids = dibi::select('post_id')
                ->from(POSTS_TABLE)
                ->where('post_id IN %in', $posts)
                ->where('forum_id = %i', $forum_id)
                ->fetchPairs(null, 'post_id');

			if (!count($post_ids)) {
				message_die(GENERAL_MESSAGE, $lang['None_selected']);
			}

			// TODO!!!!!!!!
			$posts_dibi = dibi::select(['post_id', 'poster_id', 'topic_id', 'post_time'])
                ->from(POSTS_TABLE)
                ->where('post_id IN %in', $post_ids)
                ->orderBy('post_time', dibi::ASC)
                ->fetchAll();

			if ($posts_dibi) {
				$first_poster = $posts_dibi[0]->poster_id;
				$topic_id = $posts_dibi[0]->topic_id;
				$post_time = $posts_dibi[0]->post_time;

				$user_ids_sql = [];
				$post_ids_sql = [];

				foreach ($posts_dibi as $post_dibi) {
				    $user_ids_sql[] = $post_dibi->post_id;
                    $post_ids_sql[] = $post_dibi->post_id;
                }

				$post_subject = trim(htmlspecialchars($_POST['subject']));

				if (empty($post_subject)) {
					message_die(GENERAL_MESSAGE, $lang['Empty_subject']);
				}

				$new_forum_id = (int)$_POST['new_forum_id'];
				$topic_time = time();

				$check_forum_id = dibi::select('forum_id')
                    ->from(FORUMS_TABLE)
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

                $new_topic_id = dibi::insert(TOPICS_TABLE, $insert_data)->execute(dibi::IDENTIFIER);

				// Update topic watch table, switch users whose posts
				// have moved, over to watching the new topic

                dibi::update(TOPICS_WATCH_TABLE, ['topic_id' => $new_topic_id])
                    ->where('topic_id = %i', $topic_id)
                    ->where('user_id IN %in', $user_ids_sql)
                    ->execute();

                if (!empty($_POST['split_type_beyond'])) {
                    dibi::update(POSTS_TABLE, ['topic_id' => $new_topic_id, 'forum_id' => $new_forum_id])
                        ->where('post_time >= %i', $post_time)
                        ->where('topic_id = %i', $topic_id)
                        ->execute();
                } else {
                    dibi::update(POSTS_TABLE, ['topic_id' => $new_topic_id, 'forum_id' => $new_forum_id])
                        ->where('post_id IN %in', $post_ids_sql)
                        ->execute();
                }

				sync('topic', $new_topic_id);
				sync('topic', $topic_id);
				sync('forum', $new_forum_id);
				sync('forum', $forum_id);

                $template->assignVars(
                    [
                        'META' => '<meta http-equiv="refresh" content="3;url=' . 'viewtopic.php?' . POST_TOPIC_URL . "=$topic_id&amp;sid=" . $userdata['session_id'] . '">'
                    ]
                );

                $message = $lang['Topic_split'] . '<br /><br />' . sprintf($lang['Click_return_topic'], '<a href="' . 'viewtopic.php?' . POST_TOPIC_URL . "=$topic_id&amp;sid=" . $userdata['session_id'] . '">', '</a>');
				message_die(GENERAL_MESSAGE, $message);
			}
		} else {
			//
			// Set template files
			//
            $template->setFileNames(['split_body' => 'modcp_split.tpl']);

            $posts = dibi::select(['u.username', 'p.*', 'pt.post_text', 'pt.bbcode_uid', 'pt.post_subject', 'p.post_username'])
                ->from(POSTS_TABLE)
                ->as('p')
                ->innerJoin(USERS_TABLE)
                ->as('u')
                ->on('p.poster_id = u.user_id')
                ->innerJoin(POSTS_TEXT_TABLE)
                ->as('pt')
                ->on('p.post_id = pt.post_id')
                ->where('p.topic_id = %i', $topic_id)
                ->orderBy('p.post_time', dibi::ASC)
                ->fetchAll();

            $total_posts = count($posts);

			$s_hidden_fields = '<input type="hidden" name="sid" value="' . $userdata['session_id'] . '" /><input type="hidden" name="' . POST_FORUM_URL . '" value="' . $forum_id . '" /><input type="hidden" name="' . POST_TOPIC_URL . '" value="' . $topic_id . '" /><input type="hidden" name="mode" value="split" />';

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

                        'FORUM_NAME' => $forum_name,

                        'U_VIEW_FORUM' => Session::appendSid('viewforum.php?' . POST_FORUM_URL . "=$forum_id"),

                        'S_SPLIT_ACTION'  => Session::appendSid('modcp.php'),
                        'S_HIDDEN_FIELDS' => $s_hidden_fields,
                        'S_FORUM_SELECT'  => make_forum_select('new_forum_id', false, $forum_id)
                    ]
                );

                //
				// Define censored word matches
				//
				$orig_word = [];
				$replacement_word = [];
				obtain_word_list($orig_word, $replacement_word);

				foreach ($posts as $post) {
					$post_id = $post->post_id;
					$poster_id = $post->poster_id;
					$poster = $post->username;

					$post_date = create_date($board_config['default_dateformat'], $post->post_time, $board_config['board_timezone']);

					$bbcode_uid = $post->bbcode_uid;
					$message = $post->post_text;
					$post_subject = ( $post->post_subject !== '' ) ? $post->post_subject : $topic_title;

					//
					// If the board has HTML off but the post has HTML
					// on then we process it, else leave it alone
					//
                    if (!$board_config['allow_html']) {
                        if ($post->enable_html) {
                            $message = preg_replace('#(<)([\/]?.*?)(>)#is', '&lt;\\2&gt;', $message);
                        }
                    }

                    if ($bbcode_uid !== '') {
						$message = $board_config['allow_bbcode'] ? bbencode_second_pass($message, $bbcode_uid) : preg_replace('/\:[0-9a-z\:]+\]/si', ']', $message);
					}

                    if (count($orig_word)) {
						$post_subject = preg_replace($orig_word, $replacement_word, $post_subject);
						$message = preg_replace($orig_word, $replacement_word, $message);
					}

					$message = make_clickable($message);

					if ( $board_config['allow_smilies'] && $post->enable_smilies ) {
						$message = smilies_pass($message);
					}

					$message = str_replace("\n", '<br />', $message);
					
					$row_color = ( !($i % 2) ) ? $theme['td_color1'] : $theme['td_color2'];
					$row_class = ( !($i % 2) ) ? $theme['td_class1'] : $theme['td_class2'];

					$checkbox = ( $i > 0 ) ? '<input type="checkbox" name="post_id_list[]" value="' . $post_id . '" />' : '&nbsp;';

                    $template->assignBlockVars('postrow',
                        [
                            'ROW_COLOR'    => '#' . $row_color,
                            'ROW_CLASS'    => $row_class,
                            'POSTER_NAME'  => $poster,
                            'POST_DATE'    => $post_date,
                            'POST_SUBJECT' => $post_subject,
                            'MESSAGE'      => $message,
                            'POST_ID'      => $post_id,

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
		include $phpbb_root_path . 'includes/page_header.php';

		$rdns_ip_num = isset($_GET['rdns']) ? $_GET['rdns'] : '';

        if (!$post_id) {
			message_die(GENERAL_MESSAGE, $lang['No_such_post']);
		}

		//
		// Set template files
		//
        $template->setFileNames(['viewip' => 'modcp_viewip.tpl']);

        $post_row = dibi::select(['poster_ip', 'poster_id'])
            ->from(POSTS_TABLE)
            ->where('post_id = %i', $post_id)
            ->where('forum_id = %i', $forum_id)
            ->fetch();

        if (!$post_row) {
            message_die(GENERAL_MESSAGE, $lang['No_such_post']);
        }

		$ip_this_post = decode_ip($post_row->poster_ip);
		$ip_this_post = ( $rdns_ip_num === $ip_this_post ) ? htmlspecialchars(gethostbyaddr($ip_this_post)) : $ip_this_post;

		$poster_id = $post_row->poster_id;

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

                'U_LOOKUP_IP' => 'modcp.php?mode=ip&amp;' . POST_POST_URL . "=$post_id&amp;" . POST_TOPIC_URL . "=$topic_id&amp;rdns=$ip_this_post&amp;sid=" . $userdata['session_id']
            ]
        );

        //
		// Get other IP's this user has posted under
		//
        $order_by = $dbms === 'msaccess' ? 'COUNT(*)' : 'postings';

        $rows = dibi::select('poster_ip')
            ->select('COUNT(*)')
            ->as('postings')
            ->from(POSTS_TABLE)
            ->where('poster_id = %i', $poster_id)
            ->groupBy('poster_ip')
            ->orderBy($order_by, dibi::DESC)
            ->fetchAll();

        $i = 0;

        foreach ($rows as $row) {
            if ($row->poster_ip === $post_row->poster_ip) {
                $template->assignVars(
                    [
                        'POSTS' => $row->postings . ' ' . (($row->postings === 1) ? $lang['Post'] : $lang['Posts'])
                    ]
                );
                continue;
            }

            $ip = decode_ip($row->poster_ip);
            $ip = ( $rdns_ip_num === $row->poster_ip || $rdns_ip_num === 'all') ? htmlspecialchars(gethostbyaddr($ip))
                : $ip;

            $row_color = ( !($i % 2) ) ? $theme['td_color1'] : $theme['td_color2'];
            $row_class = ( !($i % 2) ) ? $theme['td_class1'] : $theme['td_class2'];

            $template->assignBlockVars('iprow',
                [
                    'ROW_COLOR' => '#' . $row_color,
                    'ROW_CLASS' => $row_class,
                    'IP'        => $ip,
                    'POSTS'     => $row->postings . ' ' . (($row->postings === 1) ? $lang['Post'] : $lang['Posts']),

                    'U_LOOKUP_IP' => 'modcp.php?mode=ip&amp;' . POST_POST_URL . "=$post_id&amp;" . POST_TOPIC_URL . "=$topic_id&amp;rdns=" . $row->poster_ip . '&amp;sid=' . $userdata['session_id']
                ]
            );

            $i++;
        }

		//
		// Get other users who've posted under this IP
		//
        $order_by = $dbms === 'msaccess' ? 'COUNT(*)' : 'postings';

        $rows = dibi::select('u.user_id')
            ->select('u.username')
            ->select('COUNT(*)')
            ->as('postings')
            ->from(USERS_TABLE)
            ->as('u')
            ->innerJoin(POSTS_TABLE)
            ->as('p')
            ->on('p.poster_id = u.user_id')
            ->where('poster_ip = %s', $post_row->poster_ip)
            ->groupBy('u.user_id')
            ->groupBy('u.username')
            ->orderBy($order_by, dibi::DESC)
            ->fetchAll();

        $i = 0;

        foreach ($rows as $row) {
            $id = $row->user_id;
            $username = ($id === ANONYMOUS) ? $lang['Guest'] : $row->username;

            $row_color = ( !($i % 2) ) ? $theme['td_color1'] : $theme['td_color2'];
            $row_class = ( !($i % 2) ) ? $theme['td_class1'] : $theme['td_class2'];

            $template->assignBlockVars('userrow', [
                'ROW_COLOR'      => '#' . $row_color,
                'ROW_CLASS'      => $row_class,
                'USERNAME'       => $username,
                'POSTS'          => $row->postings . ' ' . (($row->postings === 1) ? $lang['Post'] : $lang['Posts']),
                'L_SEARCH_POSTS' => sprintf($lang['Search_user_posts'], $username),

                'U_PROFILE'     => ($id === ANONYMOUS) ? 'modcp.php?mode=ip&amp;' . POST_POST_URL . '=' . $post_id . '&amp;' . POST_TOPIC_URL . '=' . $topic_id . '&amp;sid=' . $userdata['session_id'] : Session::appendSid('profile.php?mode=viewprofile&amp;' . POST_USERS_URL . "=$id"),
                'U_SEARCHPOSTS' => Session::appendSid('search.php?search_author=' . (($id === ANONYMOUS) ? 'Anonymous' : urlencode($username)) . '&amp;showresults=topics')
            ]);

            $i++;
        }

		$template->pparse('viewip');

		break;

	default:
		$page_title = $lang['Mod_CP'];
		include $phpbb_root_path . 'includes/page_header.php';

        $template->assignVars(
            [
                'FORUM_NAME' => $forum_name,

                'L_MOD_CP'         => $lang['Mod_CP'],
                'L_MOD_CP_EXPLAIN' => $lang['Mod_CP_explain'],
                'L_SELECT'         => $lang['Select'],
                'L_DELETE'         => $lang['Delete'],
                'L_MOVE'           => $lang['Move'],
                'L_LOCK'           => $lang['Lock'],
                'L_UNLOCK'         => $lang['Unlock'],
                'L_TOPICS'         => $lang['Topics'],
                'L_REPLIES'        => $lang['Replies'],
                'L_LASTPOST'       => $lang['Last_Post'],

                'U_VIEW_FORUM'    => Session::appendSid('viewforum.php?' . POST_FORUM_URL . "=$forum_id"),
                'S_HIDDEN_FIELDS' => '<input type="hidden" name="sid" value="' . $userdata['session_id'] . '" /><input type="hidden" name="' . POST_FORUM_URL . '" value="' . $forum_id . '" />',
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

		$rows = dibi::select(['t.*', 'u.username', 'u.user_id', 'p.post_time'])
            ->from(TOPICS_TABLE)
            ->as('t')
            ->innerJoin(USERS_TABLE)
            ->as('u')
            ->on('t.topic_poster = u.user_id')
            ->innerJoin(POSTS_TABLE)
            ->as('p')
            ->on('p.post_id = t.topic_last_post_id')
            ->where('t.forum_id = %i', $forum_id)
            ->orderBy('t.topic_type', dibi::DESC)
            ->orderBy('p.post_time', dibi::DESC)
            ->limit($board_config['topics_per_page'])
            ->offset($start)
            ->fetchAll();

        foreach ($rows as $row) {
			$topic_title = '';

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

			$topic_id = $row->topic_id;
			$topic_type = $row->topic_type;
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

            if (count($orig_word)) {
                $topic_title = preg_replace($orig_word, $replacement_word, $topic_title);
            }

			$u_view_topic = 'modcp.php?mode=split&amp;' . POST_TOPIC_URL . "=$topic_id&amp;sid=" . $userdata['session_id'];
			$topic_replies = $row->topic_replies;

			$last_post_time = create_date($board_config['default_dateformat'], $row->post_time, $board_config['board_timezone']);

            $template->assignBlockVars('topicrow',
                [
                    'U_VIEW_TOPIC' => $u_view_topic,

                    'TOPIC_FOLDER_IMG' => $folder_img,
                    'TOPIC_TYPE'       => $topic_type,
                    'TOPIC_TITLE'      => $topic_title,
                    'REPLIES'          => $topic_replies,
                    'LAST_POST_TIME'   => $last_post_time,
                    'TOPIC_ID'         => $topic_id,

                    'L_TOPIC_FOLDER_ALT' => $folder_alt
                ]
            );
        }

        $template->assignVars([
            'PAGINATION'  => generate_pagination('modcp.php?' . POST_FORUM_URL . "=$forum_id&amp;sid=" . $userdata['session_id'], $forum_topics, $board_config['topics_per_page'], $start),
            'PAGE_NUMBER' => sprintf($lang['Page_of'], floor($start / $board_config['topics_per_page']) + 1, ceil($forum_topics / $board_config['topics_per_page'])),

            'L_GOTO_PAGE' => $lang['Goto_page']
            ]);

        $template->pparse('body');

		break;
}

include $phpbb_root_path . 'includes/page_tail.php';

?>