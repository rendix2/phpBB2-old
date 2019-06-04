<?php
/***************************************************************************
 *                                posting.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: posting.php 6772 2006-12-16 13:11:28Z acydburn $
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
include $phpbb_root_path . 'includes/functions_post.php';

//
// Check and set various parameters
//
$params = ['submit'      => 'post',
           'preview'     => 'preview',
           'delete'      => 'delete',
           'poll_delete' => 'poll_delete',
           'poll_add'    => 'add_poll_option',
           'poll_edit'   => 'edit_poll_option',
           'mode'        => 'mode'
];

foreach ($params as $var => $param) {
    if (!empty($_POST[$param]) || !empty($_GET[$param])) {
        $$var = !empty($_POST[$param]) ? htmlspecialchars($_POST[$param]) : htmlspecialchars($_GET[$param]);
    } else {
        $$var = '';
    }
}

$confirm = isset($_POST['confirm']);
$sid = isset($_POST['sid']) ? $_POST['sid'] : 0;

$params = ['forum_id' => POST_FORUM_URL, 'topic_id' => POST_TOPIC_URL, 'post_id' => POST_POST_URL];

foreach ($params as $var => $param) {
    if (!empty($_POST[$param]) || !empty($_GET[$param])) {
        $$var = !empty($_POST[$param]) ? (int)$_POST[$param] : (int)$_GET[$param];
    } else {
        $$var = '';
    }
}

$refresh = $preview || $poll_add || $poll_edit || $poll_delete;
$orig_word = $replacement_word = [];

//
// Set topic type
//
$topic_type = !empty($_POST['topictype']) ? (int)$_POST['topictype'] : POST_NORMAL;
$topic_type = in_array($topic_type, [POST_NORMAL, POST_STICKY, POST_ANNOUNCE], true) ? $topic_type : POST_NORMAL;

//
// If the mode is set to topic review then output
// that review ...
//
if ($mode === 'topicreview') {
    require $phpbb_root_path . 'includes/topic_review.php';

    topic_review($topic_id, false);
    exit;
} elseif ($mode === 'smilies') {
    generate_smilies('window', PAGE_POSTING);
    exit;
}

//
// Start session management
//
$userdata = session_pagestart($user_ip, PAGE_POSTING);
init_userprefs($userdata);
//
// End session management
//

//
// Was cancel pressed? If so then redirect to the appropriate
// page, no point in continuing with any further checks
//
if (isset($_POST['cancel'])) {
    if ($post_id) {
        $redirect    = "viewtopic.php?" . POST_POST_URL . "=$post_id";
        $post_append = "#$post_id";
    } elseif ($topic_id) {
        $redirect    = "viewtopic.php?" . POST_TOPIC_URL . "=$topic_id";
        $post_append = '';
    } elseif ($forum_id) {
        $redirect    = "viewforum.php?" . POST_FORUM_URL . "=$forum_id";
        $post_append = '';
    } else {
        $redirect    = "index.php";
        $post_append = '';
    }

    redirect(append_sid($redirect, true) . $post_append);
}

//
// What auth type do we need to check?
//
$is_auth = [];

switch ($mode) {
    case 'newtopic':
        if ($topic_type === POST_ANNOUNCE) {
            $is_auth_type = 'auth_announce';
        } elseif ($topic_type === POST_STICKY) {
            $is_auth_type = 'auth_sticky';
        } else {
            $is_auth_type = 'auth_post';
        }
        break;
    case 'reply':
    case 'quote':
        $is_auth_type = 'auth_reply';
        break;
    case 'editpost':
        $is_auth_type = 'auth_edit';
        break;
    case 'delete':
    case 'poll_delete':
        $is_auth_type = 'auth_delete';
        break;
    case 'vote':
        $is_auth_type = 'auth_vote';
        break;
    case 'topicreview':
        $is_auth_type = 'auth_read';
        break;
    default:
        message_die(GENERAL_MESSAGE, $lang['No_post_mode']);
        break;
}

//
// Here we do various lookups to find topic_id, forum_id, post_id etc.
// Doing it here prevents spoofing (eg. faking forum_id, topic_id or post_id
//
$error_msg = '';
$post_data = [];
switch ( $mode ) {
	case 'newtopic':
        if (empty($forum_id)) {
			message_die(GENERAL_MESSAGE, $lang['Forum_not_exist']);
		}

        $post_info = dibi::select('*')
            ->from(FORUMS_TABLE)
            ->where('forum_id = %i', $forum_id)
            ->fetch();
		break;

	case 'reply':
	case 'vote':
        if (empty($topic_id)) {
			message_die(GENERAL_MESSAGE, $lang['No_topic_id']);
		}

    $post_info = dibi::select(['f.*', 't.topic_status', 't.topic_title', 't.topic_type'])
            ->from(FORUMS_TABLE)
            ->as('f')
            ->innerJoin(TOPICS_TABLE)
            ->as('t')
            ->on('f.forum_id = t.forum_id')
            ->where('t.topic_id = %i', $topic_id)
            ->fetch();
		break;

	case 'quote':
	case 'editpost':
	case 'delete':
	case 'poll_delete':
		if ( empty($post_id) ) {
			message_die(GENERAL_MESSAGE, $lang['No_post_id']);
		}

		if (!$submit) {
		    $columns = [
		        'f.*',
                't.topic_id',
                't.topic_status',
                't.topic_type',
                't.topic_first_post_id',
                't.topic_last_post_id',
                't.topic_vote',
                'p.post_id',
                'p.poster_id',
                't.topic_title',
                'p.enable_bbcode',
                'p.enable_html',
                'p.enable_smilies',
                'p.enable_sig',
                'p.post_username',
                'pt.post_subject',
                'pt.post_text',
                'pt.bbcode_uid',
                'u.username',
                'u.user_id',
                'u.user_sig',
                'u.user_sig_bbcode_uid'
            ];

            $post_info = dibi::select($columns)
                ->from(POSTS_TABLE)
                ->as('p')
                ->innerJoin(TOPICS_TABLE)
                ->as('t')
                ->on('t.topic_id = p.topic_id')
                ->innerJoin(FORUMS_TABLE)
                ->as('f')
                ->on('f.forum_id = p.forum_id')
                ->innerJoin(POSTS_TEXT_TABLE)
                ->as('pt')
                ->on('pt.post_id = p.post_id')
                ->innerJoin(USERS_TABLE)
                ->as('u')
                ->on('u.user_id = p.poster_id')
                ->where('p.post_id = %i', $post_id)
                ->fetch();
        } else {
            $columns = [
                'f.*',
                't.topic_id',
                't.topic_status',
                't.topic_type',
                't.topic_first_post_id',
                't.topic_last_post_id',
                't.topic_vote',
                'p.post_id',
                'p.poster_id'
            ];

            $post_info = dibi::select($columns)
                ->from(POSTS_TABLE)
                ->as('p')
                ->innerJoin(TOPICS_TABLE)
                ->as('t')
                ->on('t.topic_id = p.topic_id')
                ->innerJoin(FORUMS_TABLE)
                ->as('f')
                ->on('f.forum_id = p.forum_id')
                ->where('p.post_id = %i', $post_id)
                ->fetch();
        }

		break;

	default:
		message_die(GENERAL_MESSAGE, $lang['No_valid_mode']);
}

if ($post_info) {
	$forum_id = $post_info->forum_id;
	$forum_name = $post_info->forum_name;

	$is_auth = auth(AUTH_ALL, $forum_id, $userdata, $post_info);

    if ($post_info->forum_status === FORUM_LOCKED && !$is_auth['auth_mod']) {
        message_die(GENERAL_MESSAGE, $lang['Forum_locked']);
    } elseif ($mode !== 'newtopic' && $post_info->topic_status === TOPIC_LOCKED && !$is_auth['auth_mod']) {
        message_die(GENERAL_MESSAGE, $lang['Topic_locked']);
    }

    if ( $mode === 'editpost' || $mode === 'delete' || $mode === 'poll_delete' ) {
		$topic_id = $post_info->topic_id;

		$post_data['poster_post'] = $post_info->poster_id === $userdata['user_id'];
		$post_data['first_post'] = $post_info->topic_first_post_id === $post_id;
		$post_data['last_post'] = $post_info->topic_last_post_id === $post_id;
		$post_data['last_topic'] = $post_info->forum_last_post_id === $post_id;
		$post_data['has_poll'] = $post_info->topic_vote ? true : false;
		$post_data['topic_type'] = $post_info->topic_type;
		$post_data['poster_id'] = $post_info->poster_id;

		if ( $post_data['first_post'] && $post_data['has_poll'] ) {
		    $votes = dibi::select('*')
                ->from(VOTE_DESC_TABLE)
                ->as('vd')
                ->innerJoin(VOTE_RESULTS_TABLE)
                ->as('vr')
                ->on('vr.vote_id = vd.vote_id')
                ->where('vd.topic_id = %i', $topic_id)
                ->orderBy('vr.vote_option_id')
                ->fetchAll();

			$poll_options = [];
			$poll_results_sum = 0;

			if (count($votes)) {
                $poll_title  = $votes[0]->vote_text;
                $poll_id     = $votes[0]->vote_id;
                $poll_length = $votes[0]->vote_length / 86400;

                foreach ($votes as $vote) {
                    $poll_options[$vote->vote_option_id] = $vote->vote_option_text;

                    $poll_results_sum += $vote->vote_result;
                }
            }

			$post_data['edit_poll'] = ( ( !$poll_results_sum || $is_auth['auth_mod'] ) && $post_data['first_post'] );
		} else {
			$post_data['edit_poll'] = $post_data['first_post'] && $is_auth['auth_pollcreate'];
		}

		//
		// Can this user edit/delete the post/poll?
		//
        if ($post_info->poster_id !== $userdata['user_id'] && !$is_auth['auth_mod']) {
			$message = ( $delete || $mode === 'delete' ) ? $lang['Delete_own_posts'] : $lang['Edit_own_posts'];
			$message .= '<br /><br />' . sprintf($lang['Click_return_topic'], '<a href="' . append_sid("viewtopic.php?" . POST_TOPIC_URL . "=$topic_id") . '">', '</a>');

			message_die(GENERAL_MESSAGE, $message);
        } elseif (!$post_data['last_post'] && !$is_auth['auth_mod'] && ($mode === 'delete' || $delete)) {
            message_die(GENERAL_MESSAGE, $lang['Cannot_delete_replied']);
        } elseif (!$post_data['edit_poll'] && !$is_auth['auth_mod'] && ($mode === 'poll_delete' || $poll_delete)) {
            message_die(GENERAL_MESSAGE, $lang['Cannot_delete_poll']);
        }
    } else {
        if ($mode === 'quote') {
            $topic_id = $post_info->topic_id;
        }
        if ($mode === 'newtopic') {
            $post_data['topic_type'] = POST_NORMAL;
        }

        if ($mode === 'reply') {
            $post_data['topic_type'] = $post_info->topic_type;
        }

		$post_data['first_post'] = ( $mode === 'newtopic' ) ? true : 0;
		$post_data['last_post'] = false;
		$post_data['has_poll'] = false;
		$post_data['edit_poll'] = false;
    }

    if ($mode === 'poll_delete' && !isset($poll_id)) {
        message_die(GENERAL_MESSAGE, $lang['No_such_post']);
    }
} else {
    message_die(GENERAL_MESSAGE, $lang['No_such_post']);
}

//
// The user is not authed, if they're not logged in then redirect
// them, else show them an error message
//
if (!$is_auth[$is_auth_type]) {
    if ($userdata['session_logged_in']) {
        message_die(GENERAL_MESSAGE, sprintf($lang['Sorry_' . $is_auth_type], $is_auth[$is_auth_type . "_type"]));
    }

	switch( $mode )
	{
		case 'newtopic':
			$redirect = "mode=newtopic&" . POST_FORUM_URL . "=" . $forum_id;
			break;
		case 'reply':
		case 'topicreview':
			$redirect = "mode=reply&" . POST_TOPIC_URL . "=" . $topic_id;
			break;
		case 'quote':
		case 'editpost':
			$redirect = "mode=quote&" . POST_POST_URL ."=" . $post_id;
			break;
	}

	redirect(append_sid("login.php?redirect=posting.php&" . $redirect, true));
}

//
// Set toggles for various options
//
if ($board_config['allow_html']) {
    if ($submit || $refresh) {
        $html_on = !$_POST['disable_html'];
    } else {
        if ($userdata['user_id'] === ANONYMOUS) {
            $html_on = $board_config['allow_html'];
        } else {
            $html_on = $userdata['user_allowhtml'];
        }
    }
} else {
    $html_on = 0;
}

if ($board_config['allow_bbcode']) {
    if ($submit || $refresh) {
        $bbcode_on = !isset($_POST['disable_bbcode']);
    } else {
        if ($userdata['user_id'] === ANONYMOUS) {
            $bbcode_on = $board_config['allow_bbcode'];
        } else {
            $bbcode_on = $userdata['user_allowbbcode'];
        }
    }
} else {
    $bbcode_on = 0;
}

if ($board_config['allow_smilies']) {
    if ($submit || $refresh) {
        $smilies_on = !isset($_POST['disable_smilies']);
    } else {
        if ($userdata['user_id'] === ANONYMOUS) {
            $smilies_on = $board_config['allow_smilies'];
        } else {
            $smilies_on = $userdata['user_allowsmile'];
        }
    }
} else {
    $smilies_on = 0;
}

if (($submit || $refresh) && $is_auth['auth_read']) {
    $notify_user = !empty($_POST['notify']) ? true : 0;
} else {
    if ($mode !== 'newtopic' && $userdata['session_logged_in'] && $is_auth['auth_read']) {
        $notify_user = dibi::select('topic_id')
            ->from(TOPICS_WATCH_TABLE)
            ->where('topic_id = %i', $topic_id)
            ->where('user_id = %i', $userdata['user_id'])
            ->fetchSingle();

		$notify_user = (bool)$notify_user;
    } else {
        $notify_user = ( $userdata['session_logged_in'] && $is_auth['auth_read'] ) ? $userdata['user_notify'] : 0;
	}
}

if ( $submit || $refresh ) {
    $attach_sig = isset($_POST['attach_sig']);
} else {
    if ($userdata['user_id'] === ANONYMOUS ) {
        $attach_sig = 0;
    } else {
        $attach_sig = $userdata['user_attachsig'];
    }
}

// --------------------
//  What shall we do?
//
if (($delete || $poll_delete || $mode === 'delete') && !$confirm) {
	//
	// Confirm deletion
	//
	$s_hidden_fields = '<input type="hidden" name="' . POST_POST_URL . '" value="' . $post_id . '" />';
	$s_hidden_fields .= ( $delete || $mode === "delete" ) ? '<input type="hidden" name="mode" value="delete" />' : '<input type="hidden" name="mode" value="poll_delete" />';
	$s_hidden_fields .= '<input type="hidden" name="sid" value="' . $userdata['session_id'] . '" />';

	$l_confirm = ( $delete || $mode === 'delete' ) ? $lang['Confirm_delete'] : $lang['Confirm_delete_poll'];

	//
	// Output confirmation page
	//
	include $phpbb_root_path . 'includes/page_header.php';

    $template->set_filenames(['confirm_body' => 'confirm_body.tpl']);

    $template->assign_vars(
        [
            'MESSAGE_TITLE' => $lang['Information'],
            'MESSAGE_TEXT'  => $l_confirm,

            'L_YES' => $lang['Yes'],
            'L_NO'  => $lang['No'],

            'S_CONFIRM_ACTION' => append_sid("posting.php"),
            'S_HIDDEN_FIELDS'  => $s_hidden_fields
        ]
    );

    $template->pparse('confirm_body');

	include $phpbb_root_path . 'includes/page_tail.php';
} elseif ($mode === 'vote') {
    //
    // Vote in a poll
    //
    if (empty($_POST['vote_id'])) {
        redirect(append_sid("viewtopic.php?" . POST_TOPIC_URL . "=$topic_id", true));
    }

    $vote_option_id = (int)$_POST['vote_id'];

    $vote_info = dibi::select('vd.vote_id')
        ->from(VOTE_DESC_TABLE)
        ->as('vd')
        ->innerJoin(VOTE_RESULTS_TABLE)
        ->as('vr')
        ->on('vr.vote_id = vd.vote_id')
        ->where('vd.topic_id = %i', $topic_id)
        ->where('vr.vote_option_id = %i', $vote_option_id)
        ->groupBy('vd.vote_id')
        ->fetch();

    if (!$vote_info) {
        message_die(GENERAL_MESSAGE, $lang['No_vote_option']);
    }

    $vote_id = $vote_info->vote_id;

    $row = dibi::select('*')
        ->from(VOTE_USERS_TABLE)
        ->where('vote_id = %i', $vote_id)
        ->where('vote_user_id = %i', $userdata['user_id'])
        ->fetch();

    if ($row) {
        message_die(GENERAL_MESSAGE, $lang['Already_voted']);
    }

    dibi::update(VOTE_RESULTS_TABLE, ['vote_result%sql' => 'vote_result + 1'])
        ->where('vote_id = %i', $vote_id)
        ->where('vote_option_id = %i', $vote_option_id)
        ->execute();

    $insert_data = [
        'vote_id' => $vote_id,
        'vote_user_id' => $userdata['user_id'],
        'vote_user_ip' => $user_ip
    ];

    dibi::insert(VOTE_USERS_TABLE, $insert_data)->execute();

    $template->assign_vars(
        [
            'META' => '<meta http-equiv="refresh" content="3;url=' . append_sid("viewtopic.php?" . POST_TOPIC_URL . "=$topic_id") . '">'
        ]
    );

    $message = $lang['Vote_cast'];
    $message .= '<br /><br />' . sprintf($lang['Click_view_message'], '<a href="' . append_sid("viewtopic.php?" . POST_TOPIC_URL . "=$topic_id") . '">', '</a>');

    message_die(GENERAL_MESSAGE, $message);

} elseif ($submit || $confirm) {
	//
	// Submit post/vote (newtopic, edit, reply, etc.)
	//
	$return_message = '';
	$return_meta = '';

	// session id check
    if ($sid === '' || $sid !== $userdata['session_id']) {
        $error_msg .= !empty($error_msg) ? '<br />' . $lang['Session_invalid'] : $lang['Session_invalid'];
    }

    switch ($mode) {
		case 'editpost':
		case 'newtopic':
		case 'reply':
			$username = !empty($_POST['username']) ? $_POST['username'] : '';
			$subject = !empty($_POST['subject']) ? trim($_POST['subject']) : '';
			$message = !empty($_POST['message']) ? $_POST['message'] : '';
			$poll_title = ( isset($_POST['poll_title']) && $is_auth['auth_pollcreate'] ) ? $_POST['poll_title'] : '';
			$poll_options = ( isset($_POST['poll_option_text']) && $is_auth['auth_pollcreate'] ) ? $_POST['poll_option_text'] : '';
			$poll_length = ( isset($_POST['poll_length']) && $is_auth['auth_pollcreate'] ) ? $_POST['poll_length'] : '';
			$bbcode_uid = '';

			prepare_post($mode, $post_data, $bbcode_on, $html_on, $smilies_on, $error_msg, $username, $bbcode_uid, $subject, $message, $poll_title, $poll_options, $poll_length);

			if ( $error_msg === '' )
			{
				$topic_type = ( $topic_type !== $post_data['topic_type'] && !$is_auth['auth_sticky'] && !$is_auth['auth_announce'] ) ? $post_data['topic_type'] : $topic_type;

				submit_post($mode, $post_data, $return_message, $return_meta, $forum_id, $topic_id, $post_id, $poll_id, $topic_type, $bbcode_on, $html_on, $smilies_on, $attach_sig, $bbcode_uid, str_replace("\'", "''", $username), str_replace("\'", "''", $subject), str_replace("\'", "''", $message), str_replace("\'", "''", $poll_title), $poll_options, $poll_length);
			}
			break;

		case 'delete':
		case 'poll_delete':
			if ($error_msg !== '') {
				message_die(GENERAL_MESSAGE, $error_msg);
			}

			delete_post($mode, $post_data, $return_message, $return_meta, $forum_id, $topic_id, $post_id, $poll_id);
			break;
	}

    if ($error_msg === '') {
        if ($mode !== 'editpost') {
            $user_id = ($mode === 'reply' || $mode === 'newtopic') ? $userdata['user_id'] : $post_data['poster_id'];
            update_post_stats($mode, $post_data, $forum_id, $topic_id, $post_id, $user_id);
        }

        // $mode !== 'newtopic' is because we dont have topic_title :)
        // AND we simply dont have who we should notify :D
		if ($error_msg === '' && $mode !== 'poll_delete' && $mode !== 'newtopic') {
			user_notification($mode, $post_data, $post_info->topic_title, $forum_id, $topic_id, $post_id, $notify_user);
		}

		if ( $mode === 'newtopic' || $mode === 'reply' ) {
            $topic_cookie_name = $board_config['cookie_name'] . '_t';
            $forum_cookie_name = $board_config['cookie_name'] . '_f';

			$tracking_topics = !empty($_COOKIE[$topic_cookie_name]) ? unserialize($_COOKIE[$topic_cookie_name]) : [];
			$tracking_forums = !empty($_COOKIE[$forum_cookie_name]) ? unserialize($_COOKIE[$forum_cookie_name]) : [];

            if (count($tracking_topics) + count($tracking_forums) === 100 && empty($tracking_topics[$topic_id])) {
				asort($tracking_topics);
				unset($tracking_topics[key($tracking_topics)]);
			}

			$tracking_topics[$topic_id] = time();

			setcookie($topic_cookie_name, serialize($tracking_topics), 0, $board_config['cookie_path'], $board_config['cookie_domain'], $board_config['cookie_secure']);
		}

        $template->assign_vars(
            [
                'META' => $return_meta
            ]
        );

        message_die(GENERAL_MESSAGE, $return_message);
	}
}

if ($refresh || isset($_POST['del_poll_option']) || $error_msg !== '' ) {
	$username = !empty($_POST['username']) ? htmlspecialchars(trim(stripslashes($_POST['username']))) : '';
	$subject  = !empty($_POST['subject'])  ? htmlspecialchars(trim(stripslashes($_POST['subject'])))  : '';
	$message  = !empty($_POST['message'])  ? htmlspecialchars(trim(stripslashes($_POST['message'])))  : '';

	$poll_title = !empty($_POST['poll_title']) ? htmlspecialchars(trim(stripslashes($_POST['poll_title']))) : '';
	$poll_length = isset($_POST['poll_length']) ? max(0, (int)$_POST['poll_length']) : 0;

	$poll_options = [];

    if (!empty($_POST['poll_option_text'])) {
        foreach ($_POST['poll_option_text'] as $option_id => $option_text) {
            if (isset($_POST['del_poll_option'][$option_id])) {
                unset($poll_options[$option_id]);
            } elseif (!empty($option_text)) {
                $poll_options[(int)$option_id] = htmlspecialchars(trim(stripslashes($option_text)));
            }
        }
    }

    if (isset($poll_add) && !empty($_POST['add_poll_option_text'])) {
        $poll_options[] = htmlspecialchars(trim(stripslashes($_POST['add_poll_option_text'])));
    }

	if ( $mode === 'newtopic' || $mode === 'reply') {
		$user_sig = ( $userdata['user_sig'] !== '' && $board_config['allow_sig'] ) ? $userdata['user_sig'] : '';
	} elseif ( $mode === 'editpost' ) {
		$user_sig = ( $post_info->user_sig !== '' && $board_config['allow_sig'] ) ? $post_info->user_sig : '';
		$userdata['user_sig_bbcode_uid'] = $post_info->user_sig_bbcode_uid;
	}

	if ($preview ) {
		$orig_word = [];
		$replacement_word = [];
		obtain_word_list($orig_word, $replacement_word);

		$bbcode_uid = $bbcode_on ? make_bbcode_uid() : '';
		$preview_message = stripslashes(prepare_message(addslashes(unprepare_message($message)), $html_on, $bbcode_on, $smilies_on, $bbcode_uid));
		$preview_subject = $subject;
		$preview_username = $username;

		//
		// Finalise processing as per viewtopic
		//
        if (!$html_on) {
            if ($user_sig !== '' || !$userdata['user_allowhtml']) {
                $user_sig = preg_replace('#(<)([\/]?.*?)(>)#is', '&lt;\2&gt;', $user_sig);
            }
        }

        if ($attach_sig && $user_sig !== '' && $userdata['user_sig_bbcode_uid']) {
            $user_sig = bbencode_second_pass($user_sig, $userdata['user_sig_bbcode_uid']);
        }

        if ($bbcode_on) {
            $preview_message = bbencode_second_pass($preview_message, $bbcode_uid);
        }

        if (!empty($orig_word)) {
            $preview_username = !empty($username) ? preg_replace($orig_word, $replacement_word, $preview_username) : '';
            $preview_subject  = !empty($subject) ? preg_replace($orig_word, $replacement_word, $preview_subject) : '';
            $preview_message  = !empty($preview_message) ? preg_replace($orig_word, $replacement_word,
                $preview_message) : '';
        }

        if ($user_sig !== '') {
            $user_sig = make_clickable($user_sig);
        }
        $preview_message = make_clickable($preview_message);

        if ($smilies_on) {
            if ($userdata['user_allowsmile'] && $user_sig !== '') {
                $user_sig = smilies_pass($user_sig);
            }

            $preview_message = smilies_pass($preview_message);
        }

        if ($attach_sig && $user_sig !== '') {
            $preview_message = $preview_message . '<br /><br />_________________<br />' . $user_sig;
        }

		$preview_message = str_replace("\n", '<br />', $preview_message);

        $template->set_filenames(['preview' => 'posting_preview.tpl']);

        $template->assign_vars(
            [
                'TOPIC_TITLE'  => $preview_subject,
                'POST_SUBJECT' => $preview_subject,
                'POSTER_NAME'  => $preview_username,
                'POST_DATE'    => create_date($board_config['default_dateformat'], time(), $board_config['board_timezone']),
                'MESSAGE'      => $preview_message,

                'L_POST_SUBJECT' => $lang['Post_subject'],
                'L_PREVIEW'      => $lang['Preview'],
                'L_POSTED'       => $lang['Posted'],
                'L_POST'         => $lang['Post']
            ]
        );
        $template->assign_var_from_handle('POST_PREVIEW_BOX', 'preview');
    } elseif ($error_msg !== '') {
        $template->set_filenames(['reg_header' => 'error_body.tpl']);
        $template->assign_vars(['ERROR_MESSAGE' => $error_msg]);
        $template->assign_var_from_handle('ERROR_BOX', 'reg_header');
    }
} else {
	//
	// User default entry point
	//
    if ($mode === 'newtopic') {
		$user_sig = $userdata['user_sig'];

		$username = $userdata['session_logged_in'] ? $userdata['username'] : '';
		$poll_title = '';
		$poll_length = '';
		$subject = '';
		$message = '';
    } elseif ($mode === 'reply') {
		$user_sig = $userdata['user_sig'];

		$username = $userdata['session_logged_in'] ? $userdata['username'] : '';
		$subject = '';
		$message = '';

    } elseif ($mode === 'quote' || $mode === 'editpost') {
        $poll_title = '';
        $poll_length = '';
		$subject = $post_data['first_post'] ? $post_info->topic_title : $post_info->post_subject;
		$message = $post_info->post_text;

        if ($mode === 'editpost') {
			$attach_sig = ( $post_info->enable_sig && $post_info->user_sig !== '' ) ? TRUE : 0;
			$user_sig = $post_info->user_sig;

			$html_on = $post_info->enable_html ? true : false;
			$bbcode_on = $post_info->enable_bbcode ? true : false;
			$smilies_on = $post_info->enable_smilies ? true : false;
		} else {
			$attach_sig = $userdata['user_attachsig'] ? TRUE : 0;
			$user_sig = $userdata['user_sig'];
		}

        if ($post_info->bbcode_uid !== '') {
			$message = preg_replace('/\:(([a-z0-9]:)?)' . $post_info->bbcode_uid . '/s', '', $message);
		}

		$message = str_replace('<', '&lt;', $message);
		$message = str_replace('>', '&gt;', $message);
		$message = str_replace('<br />', "\n", $message);

        if ($mode === 'quote') {
			$orig_word = [];
			$replacement_word = [];
			obtain_word_list($orig_word, $replace_word);

			$msg_date =  create_date($board_config['default_dateformat'], $postrow['post_time'], $board_config['board_timezone']);

			// Use trim to get rid of spaces placed there by MS-SQL 2000
			$quote_username = ( trim($post_info->post_username) !== '' ) ? $post_info->post_username : $post_info->username;
			$message = '[quote="' . $quote_username . '"]' . $message . '[/quote]';

            if (!empty($orig_word)) {
                $subject = !empty($subject) ? preg_replace($orig_word, $replace_word, $subject) : '';
                $message = !empty($message) ? preg_replace($orig_word, $replace_word, $message) : '';
            }

            if (!preg_match('/^Re:/', $subject) && strlen($subject) > 0) {
                $subject = 'Re: ' . $subject;
            }

			$mode = 'reply';
		} else {
			$username = ( $post_info->user_id === ANONYMOUS && !empty($post_info->post_username) ) ? $post_info->post_username : '';
		}
	}
}

//
// Signature toggle selection
//
if ($user_sig !== '' ) {
	$template->assign_block_vars('switch_signature_checkbox', []);
}

//
// HTML toggle selection
//
if ($board_config['allow_html']) {
    $html_status = $lang['HTML_is_ON'];
    $template->assign_block_vars('switch_html_checkbox', []);
} else {
    $html_status = $lang['HTML_is_OFF'];
}

//
// BBCode toggle selection
//
if ($board_config['allow_bbcode']) {
    $bbcode_status = $lang['BBCode_is_ON'];
    $template->assign_block_vars('switch_bbcode_checkbox', []);
} else {
    $bbcode_status = $lang['BBCode_is_OFF'];
}

//
// Smilies toggle selection
//
if ($board_config['allow_smilies']) {
    $smilies_status = $lang['Smilies_are_ON'];
    $template->assign_block_vars('switch_smilies_checkbox', []);
} else {
    $smilies_status = $lang['Smilies_are_OFF'];
}

if (!$userdata['session_logged_in'] || ($mode === 'editpost' && $post_info->poster_id === ANONYMOUS)) {
    $template->assign_block_vars('switch_username_select', []);
}

//
// Notify checkbox - only show if user is logged in
//
if ($userdata['session_logged_in'] && $is_auth['auth_read']) {
    if ($mode !== 'editpost' || ($mode === 'editpost' && $post_info->poster_id !== ANONYMOUS)) {
        $template->assign_block_vars('switch_notify_checkbox', []);
    }
}

//
// Delete selection
//
if ($mode === 'editpost' && (($is_auth['auth_delete'] && $post_data['last_post'] && (!$post_data['has_poll'] || $post_data['edit_poll'])) || $is_auth['auth_mod'])) {
    $template->assign_block_vars('switch_delete_checkbox', []);
}

//
// Topic type selection
//
$topic_type_toggle = '';
if ($mode === 'newtopic' || ($mode === 'editpost' && $post_data['first_post'])) {
	$template->assign_block_vars('switch_type_toggle', []);

	if ($is_auth['auth_sticky']) {
		$topic_type_toggle .= '<input type="radio" name="topictype" value="' . POST_STICKY . '"';

        if ($post_data['topic_type'] === POST_STICKY || $topic_type === POST_STICKY) {
            $topic_type_toggle .= ' checked="checked"';
        }

		$topic_type_toggle .= ' /> ' . $lang['Post_Sticky'] . '&nbsp;&nbsp;';
	}

    if ($is_auth['auth_announce']) {
		$topic_type_toggle .= '<input type="radio" name="topictype" value="' . POST_ANNOUNCE . '"';

        if ($post_data['topic_type'] === POST_ANNOUNCE || $topic_type === POST_ANNOUNCE) {
            $topic_type_toggle .= ' checked="checked"';
        }

		$topic_type_toggle .= ' /> ' . $lang['Post_Announcement'] . '&nbsp;&nbsp;';
	}

    if ($topic_type_toggle !== '') {
		$topic_type_toggle = $lang['Post_topic_as'] . ': <input type="radio" name="topictype" value="' . POST_NORMAL .'"' . ( ( $post_data['topic_type'] === POST_NORMAL || $topic_type === POST_NORMAL ) ? ' checked="checked"' : '' ) . ' /> ' . $lang['Post_Normal'] . '&nbsp;&nbsp;' . $topic_type_toggle;
	}
}

$hidden_form_fields = '<input type="hidden" name="mode" value="' . $mode . '" />';
$hidden_form_fields .= '<input type="hidden" name="sid" value="' . $userdata['session_id'] . '" />';

switch ($mode) {
	case 'newtopic':
		$page_title = $lang['Post_a_new_topic'];
		$hidden_form_fields .= '<input type="hidden" name="' . POST_FORUM_URL . '" value="' . $forum_id . '" />';
		break;

	case 'reply':
		$page_title = $lang['Post_a_reply'];
		$hidden_form_fields .= '<input type="hidden" name="' . POST_TOPIC_URL . '" value="' . $topic_id . '" />';
		break;

	case 'editpost':
		$page_title = $lang['Edit_Post'];
		$hidden_form_fields .= '<input type="hidden" name="' . POST_POST_URL . '" value="' . $post_id . '" />';
		break;
}

// Generate smilies listing for page output
generate_smilies('inline', PAGE_POSTING);

//
// Include page header
//
include $phpbb_root_path . 'includes/page_header.php';

$template->set_filenames(
    [
        'body'       => 'posting_body.tpl',
        'pollbody'   => 'posting_poll_body.tpl',
        'reviewbody' => 'posting_topic_review.tpl'
    ]
);
make_jumpbox('viewforum.php');

$template->assign_vars(
    [
        'FORUM_NAME'     => $forum_name,
        'L_POST_A'       => $page_title,
        'L_POST_SUBJECT' => $lang['Post_subject'],

        'U_VIEW_FORUM' => append_sid("viewforum.php?" . POST_FORUM_URL . "=$forum_id")
    ]
);

//
// This enables the forum/topic title to be output for posting
// but not for privmsg (where it makes no sense)
//
$template->assign_block_vars('switch_not_privmsg', []);

//
// Output the data to the template
//
$template->assign_vars([
        'USERNAME'       => $username,
        'SUBJECT'        => $subject,
        'MESSAGE'        => $message,
        'HTML_STATUS'    => $html_status,
        'BBCODE_STATUS'  => sprintf($bbcode_status, '<a href="' . append_sid("faq.php?mode=bbcode") . '" target="_phpbbcode">', '</a>'),
        'SMILIES_STATUS' => $smilies_status,

        'L_SUBJECT'          => $lang['Subject'],
        'L_MESSAGE_BODY'     => $lang['Message_body'],
        'L_OPTIONS'          => $lang['Options'],
        'L_PREVIEW'          => $lang['Preview'],
        'L_SPELLCHECK'       => $lang['Spellcheck'],
        'L_SUBMIT'           => $lang['Submit'],
        'L_CANCEL'           => $lang['Cancel'],
        'L_CONFIRM_DELETE'   => $lang['Confirm_delete'],
        'L_DISABLE_HTML'     => $lang['Disable_HTML_post'],
        'L_DISABLE_BBCODE'   => $lang['Disable_BBCode_post'],
        'L_DISABLE_SMILIES'  => $lang['Disable_Smilies_post'],
        'L_ATTACH_SIGNATURE' => $lang['Attach_signature'],
        'L_NOTIFY_ON_REPLY'  => $lang['Notify'],
        'L_DELETE_POST'      => $lang['Delete_post'],

        'L_BBCODE_B_HELP' => $lang['bbcode_b_help'],
        'L_BBCODE_I_HELP' => $lang['bbcode_i_help'],
        'L_BBCODE_U_HELP' => $lang['bbcode_u_help'],
        'L_BBCODE_Q_HELP' => $lang['bbcode_q_help'],
        'L_BBCODE_C_HELP' => $lang['bbcode_c_help'],
        'L_BBCODE_L_HELP' => $lang['bbcode_l_help'],
        'L_BBCODE_O_HELP' => $lang['bbcode_o_help'],
        'L_BBCODE_P_HELP' => $lang['bbcode_p_help'],
        'L_BBCODE_W_HELP' => $lang['bbcode_w_help'],
        'L_BBCODE_A_HELP' => $lang['bbcode_a_help'],
        'L_BBCODE_S_HELP' => $lang['bbcode_s_help'],
        'L_BBCODE_F_HELP' => $lang['bbcode_f_help'],
        'L_EMPTY_MESSAGE' => $lang['Empty_message'],

        'L_FONT_COLOR'      => $lang['Font_color'],
        'L_COLOR_DEFAULT'   => $lang['color_default'],
        'L_COLOR_DARK_RED'  => $lang['color_dark_red'],
        'L_COLOR_RED'       => $lang['color_red'],
        'L_COLOR_ORANGE'    => $lang['color_orange'],
        'L_COLOR_BROWN'     => $lang['color_brown'],
        'L_COLOR_YELLOW'    => $lang['color_yellow'],
        'L_COLOR_GREEN'     => $lang['color_green'],
        'L_COLOR_OLIVE'     => $lang['color_olive'],
        'L_COLOR_CYAN'      => $lang['color_cyan'],
        'L_COLOR_BLUE'      => $lang['color_blue'],
        'L_COLOR_DARK_BLUE' => $lang['color_dark_blue'],
        'L_COLOR_INDIGO'    => $lang['color_indigo'],
        'L_COLOR_VIOLET'    => $lang['color_violet'],
        'L_COLOR_WHITE'     => $lang['color_white'],
        'L_COLOR_BLACK'     => $lang['color_black'],

        'L_FONT_SIZE'   => $lang['Font_size'],
        'L_FONT_TINY'   => $lang['font_tiny'],
        'L_FONT_SMALL'  => $lang['font_small'],
        'L_FONT_NORMAL' => $lang['font_normal'],
        'L_FONT_LARGE'  => $lang['font_large'],
        'L_FONT_HUGE'   => $lang['font_huge'],

        'L_BBCODE_CLOSE_TAGS' => $lang['Close_Tags'],
        'L_STYLES_TIP'        => $lang['Styles_tip'],

        'U_VIEWTOPIC'    => $mode === 'reply' ? append_sid("viewtopic.php?" . POST_TOPIC_URL . "=$topic_id&amp;postorder=desc") : '',
        'U_REVIEW_TOPIC' => $mode === 'reply' ? append_sid("posting.php?mode=topicreview&amp;" . POST_TOPIC_URL . "=$topic_id") : '',

        'S_HTML_CHECKED'       => (!$html_on) ? 'checked="checked"' : '',
        'S_BBCODE_CHECKED'     => (!$bbcode_on) ? 'checked="checked"' : '',
        'S_SMILIES_CHECKED'    => (!$smilies_on) ? 'checked="checked"' : '',
        'S_SIGNATURE_CHECKED'  => $attach_sig ? 'checked="checked"' : '',
        'S_NOTIFY_CHECKED'     => $notify_user ? 'checked="checked"' : '',
        'S_TYPE_TOGGLE'        => $topic_type_toggle,
        'S_TOPIC_ID'           => $topic_id,
        'S_POST_ACTION'        => append_sid("posting.php"),
        'S_HIDDEN_FORM_FIELDS' => $hidden_form_fields
    ]);

//
// Poll entry switch/output
//
if (($mode === 'newtopic' || ($mode === 'editpost' && $post_data['edit_poll'])) && $is_auth['auth_pollcreate']) {
    $template->assign_vars(
        [
            'L_ADD_A_POLL'          => $lang['Add_poll'],
            'L_ADD_POLL_EXPLAIN'    => $lang['Add_poll_explain'],
            'L_POLL_QUESTION'       => $lang['Poll_question'],
            'L_POLL_OPTION'         => $lang['Poll_option'],
            'L_ADD_OPTION'          => $lang['Add_option'],
            'L_UPDATE_OPTION'       => $lang['Update'],
            'L_DELETE_OPTION'       => $lang['Delete'],
            'L_POLL_LENGTH'         => $lang['Poll_for'],
            'L_DAYS'                => $lang['Days'],
            'L_POLL_LENGTH_EXPLAIN' => $lang['Poll_for_explain'],
            'L_POLL_DELETE'         => $lang['Delete_poll'],

            'POLL_TITLE'  => $poll_title,
            'POLL_LENGTH' => $poll_length
        ]
    );

    if ($mode === 'editpost' && $post_data['edit_poll'] && $post_data['has_poll']) {
		$template->assign_block_vars('switch_poll_delete_toggle', []);
	}

	if (!empty($poll_options) ) {
	    foreach ($poll_options as $option_id => $option_text) {
            $template->assign_block_vars('poll_option_rows',
                [
                    'POLL_OPTION' => str_replace('"', '&quot;', $option_text),

                    'S_POLL_OPTION_NUM' => $option_id
                ]
            );
        }
    }

	$template->assign_var_from_handle('POLLBOX', 'pollbody');
}

//
// Topic review
//
if ($mode === 'reply' && $is_auth['auth_read'] ) {
	require $phpbb_root_path . 'includes/topic_review.php';
	topic_review($topic_id, true);

	$template->assign_block_vars('switch_inline_mode', []);
	$template->assign_var_from_handle('TOPIC_REVIEW_BOX', 'reviewbody');
}

$template->pparse('body');

include $phpbb_root_path . 'includes/page_tail.php';

?>