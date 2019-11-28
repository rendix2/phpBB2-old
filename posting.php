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

use Nette\Utils\Random;

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
require_once $phpbb_root_path . 'includes' . $sep . 'bbcode.php';

//
// Check and set various parameters
//

if (!empty($_POST['post']) || !empty($_GET['post'])) {
    $submit = !empty($_POST['post']) ? htmlspecialchars($_POST['post']) : htmlspecialchars($_GET['post']);
} else {
    $submit = '';
}

if (!empty($_POST['preview']) || !empty($_GET['preview'])) {
    $preview = !empty($_POST['preview']) ? htmlspecialchars($_POST['preview']) : htmlspecialchars($_GET['preview']);
} else {
    $preview = '';
}

if (!empty($_POST['delete']) || !empty($_GET['delete'])) {
    $delete = !empty($_POST['delete']) ? htmlspecialchars($_POST['delete']) : htmlspecialchars($_GET['delete']);
} else {
    $delete = '';
}

if (!empty($_POST['poll_delete']) || !empty($_GET['poll_delete'])) {
    $pollDelete = !empty($_POST['poll_delete']) ? htmlspecialchars($_POST['poll_delete']) : htmlspecialchars($_GET['poll_delete']);
} else {
    $pollDelete = '';
}

if (!empty($_POST['add_poll_option']) || !empty($_GET['add_poll_option'])) {
    $pollAdd = !empty($_POST['add_poll_option']) ? htmlspecialchars($_POST['add_poll_option']) : htmlspecialchars($_GET['add_poll_option']);
} else {
    $pollAdd = '';
}

if (!empty($_POST['edit_poll_option']) || !empty($_GET['edit_poll_option'])) {
    $pollEdit = !empty($_POST['edit_poll_option']) ? htmlspecialchars($_POST['edit_poll_option']) : htmlspecialchars($_GET['edit_poll_option']);
} else {
    $pollEdit = '';
}

if (!empty($_POST[POST_MODE]) || !empty($_GET[POST_MODE])) {
    $mode = !empty($_POST[POST_MODE]) ? htmlspecialchars($_POST[POST_MODE]) : htmlspecialchars($_GET[POST_MODE]);
} else {
    $mode = '';
}

$confirm = isset($_POST['confirm']);
$sid = isset($_POST['sid']) ? $_POST['sid'] : 0;

if (!empty($_POST[POST_FORUM_URL]) || !empty($_GET[POST_FORUM_URL])) {
    $forumId = !empty($_POST[POST_FORUM_URL]) ? (int)$_POST[POST_FORUM_URL] : (int)$_GET[POST_FORUM_URL];
} else {
    $forumId = '';
}

if (!empty($_POST[POST_TOPIC_URL]) || !empty($_GET[POST_TOPIC_URL])) {
    $topicId = !empty($_POST[POST_TOPIC_URL]) ? (int)$_POST[POST_TOPIC_URL] : (int)$_GET[POST_TOPIC_URL];
} else {
    $topicId = '';
}

if (!empty($_POST[POST_POST_URL]) || !empty($_GET[POST_POST_URL])) {
    $postId = !empty($_POST[POST_POST_URL]) ? (int)$_POST[POST_POST_URL] : (int)$_GET[POST_POST_URL];
} else {
    $postId = '';
}

$refresh = $preview || $pollAdd || $pollEdit || $pollDelete;
$orig_word = $replacement_word = [];

//
// Set topic type
//
$topicType = !empty($_POST['topictype']) ? (int)$_POST['topictype'] : POST_NORMAL;
$topicType = in_array($topicType, [POST_NORMAL, POST_STICKY, POST_ANNOUNCE], true) ? $topicType : POST_NORMAL;

//
// If the mode is set to topic review then output
// that review ...
//
if ($mode === 'topicreview' && (int)$board_config['topic_review'] === 1) {
    require_once $phpbb_root_path . 'includes' . $sep . 'topic_review.php';

    topic_review($topicId, false);
    exit;
}

if ($mode === 'smilies') {
    PostHelper::generateSmileys('window', PAGE_POSTING);
    exit;
}

//
// Start session management
//
$userdata = init_userprefs(PAGE_POSTING);
//
// End session management
//

//
// Was cancel pressed? If so then redirect to the appropriate
// page, no point in continuing with any further checks
//
if (isset($_POST['cancel'])) {
    if ($postId) {
        $redirect   = 'viewtopic.php?' . POST_POST_URL . "=$postId";
        $postAppend = "#$postId";
    } elseif ($topicId) {
        $redirect   = 'viewtopic.php?' . POST_TOPIC_URL . "=$topicId";
        $postAppend = '';
    } elseif ($forumId) {
        $redirect   = 'viewforum.php?' . POST_FORUM_URL . "=$forumId";
        $postAppend = '';
    } else {
        $redirect   = 'index.php';
        $postAppend = '';
    }

    redirect(Session::appendSid($redirect, true) . $postAppend);
}

//
// What auth type do we need to check?
//
$is_auth = [];

switch ($mode) {
    case 'newtopic':
        if ($topicType === POST_ANNOUNCE) {
            $is_auth_type = 'auth_announce';
        } elseif ($topicType === POST_STICKY) {
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
    case 'thank':
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
$error_msg           = '';
$postData            = [];
switch ($mode) {
    case 'newtopic':
        if (empty($forumId)) {
            message_die(GENERAL_MESSAGE, $lang['Forum_not_exist']);
        }

        $post_info = dibi::select('*')
            ->from(Tables::FORUMS_TABLE)
            ->where('forum_id = %i', $forumId)
            ->fetch();
        break;

    case 'thank':
    case 'reply':
    case 'vote':
        if (empty($topicId)) {
            message_die(GENERAL_MESSAGE, $lang['No_topic_id']);
        }

    $post_info = dibi::select(['f.*', 't.topic_status', 't.topic_title', 't.topic_type'])
        ->from(Tables::FORUMS_TABLE)
        ->as('f')
        ->innerJoin(Tables::TOPICS_TABLE)
        ->as('t')
        ->on('f.forum_id = t.forum_id')
        ->where('t.topic_id = %i', $topicId)
        ->fetch();
		break;

	case 'quote':
	case 'editpost':
	case 'delete':
	case 'poll_delete':
        if (empty($postId)) {
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
                'p.post_time',
                'pt.post_subject',
                'pt.post_text',
                'pt.bbcode_uid',
                'u.username',
                'u.user_id',
                'u.user_sig',
                'u.user_sig_bbcode_uid'
            ];

            $post_info = dibi::select($columns)
                ->from(Tables::POSTS_TABLE)
                ->as('p')
                ->innerJoin(Tables::TOPICS_TABLE)
                ->as('t')
                ->on('t.topic_id = p.topic_id')
                ->innerJoin(Tables::FORUMS_TABLE)
                ->as('f')
                ->on('f.forum_id = p.forum_id')
                ->innerJoin(Tables::POSTS_TEXT_TABLE)
                ->as('pt')
                ->on('pt.post_id = p.post_id')
                ->innerJoin(Tables::USERS_TABLE)
                ->as('u')
                ->on('u.user_id = p.poster_id')
                ->where('p.post_id = %i', $postId)
                ->fetch();
        } else {
            $columns = [
                'f.*',
                't.topic_id',
                't.topic_status',
                't.topic_title',
                't.topic_type',
                't.topic_first_post_id',
                't.topic_last_post_id',
                't.topic_vote',
                'p.post_id',
                'p.poster_id',
                'p.post_time'
            ];

            $post_info = dibi::select($columns)
                ->from(Tables::POSTS_TABLE)
                ->as('p')
                ->innerJoin(Tables::TOPICS_TABLE)
                ->as('t')
                ->on('t.topic_id = p.topic_id')
                ->innerJoin(Tables::FORUMS_TABLE)
                ->as('f')
                ->on('f.forum_id = p.forum_id')
                ->where('p.post_id = %i', $postId)
                ->fetch();
        }

		break;

	default:
		message_die(GENERAL_MESSAGE, $lang['No_valid_mode']);
}

if ($post_info) {
	$forumId   = $post_info->forum_id;
	$forumName = $post_info->forum_name;

	$is_auth = Auth::authorize(Auth::AUTH_ALL, $forumId, $userdata, $post_info);

    if ($post_info->forum_status === FORUM_LOCKED && !$is_auth['auth_mod']) {
        message_die(GENERAL_MESSAGE, $lang['Forum_locked']);
    } elseif ($mode !== 'newtopic' && $mode !== 'thank' && $post_info->topic_status === TOPIC_LOCKED && !$is_auth['auth_mod']) {
        message_die(GENERAL_MESSAGE, $lang['Topic_locked']);
    }

    if ($mode === 'editpost' || $mode === 'delete' || $mode === 'poll_delete') {
		$topicId = $post_info->topic_id;

        $postData['poster_post'] = $post_info->poster_id === $userdata['user_id'];
        $postData['first_post']  = $post_info->topic_first_post_id === $postId;
        $postData['last_post']   = $post_info->topic_last_post_id === $postId;
        $postData['last_topic']  = $post_info->forum_last_post_id === $postId;
        $postData['has_poll']    = $post_info->topic_vote;
        $postData['topic_type']  = $post_info->topic_type;
        $postData['poster_id']   = $post_info->poster_id;

		if ($postData['first_post'] && $postData['has_poll']) {
		    $votes = dibi::select('*')
                ->from(Tables::VOTE_DESC_TABLE)
                ->as('vd')
                ->innerJoin(Tables::VOTE_RESULTS_TABLE)
                ->as('vr')
                ->on('vr.vote_id = vd.vote_id')
                ->where('vd.topic_id = %i', $topicId)
                ->orderBy('vr.vote_option_id')
                ->fetchAll();

			$pollOptions      = [];
			$poll_results_sum = 0;

			if (count($votes)) {
                $pollTitle  = $votes[0]->vote_text;
                $poll_id    = $votes[0]->vote_id;
                $pollLength = $votes[0]->vote_length / 86400;

                foreach ($votes as $vote) {
                    $pollOptions[$vote->vote_option_id] = $vote->vote_option_text;

                    $poll_results_sum += $vote->vote_result;
                }
            }

            $postData['edit_poll'] = ( !$poll_results_sum || $is_auth['auth_mod'] ) && $postData['first_post'];
		} else {
            $postData['edit_poll'] = $postData['first_post'] && $is_auth['auth_pollcreate'];
		}

		//
		// Can this user edit/delete the post/poll?
		//
        if ($post_info->poster_id !== $userdata['user_id'] && !$is_auth['auth_mod']) {
			$message = $delete || $mode === 'delete' ? $lang['Delete_own_posts'] : $lang['Edit_own_posts'];
			$message .= '<br /><br />' . sprintf($lang['Click_return_topic'], '<a href="' . Session::appendSid('viewtopic.php?' . POST_TOPIC_URL . "=$topicId") . '">', '</a>');

			message_die(GENERAL_MESSAGE, $message);
        } elseif (!$postData['last_post'] && !$is_auth['auth_mod'] && ($mode === 'delete' || $delete)) {
            message_die(GENERAL_MESSAGE, $lang['Cannot_delete_replied']);
        } elseif (!$postData['edit_poll'] && !$is_auth['auth_mod'] && ($mode === 'poll_delete' || $pollDelete)) {
            message_die(GENERAL_MESSAGE, $lang['Cannot_delete_poll']);
        }
    } else {
        if ($mode === 'quote') {
            $topicId = $post_info->topic_id;
        }

        if ($mode === 'newtopic') {
            $postData['topic_type'] = POST_NORMAL;
        }

        if ($mode === 'reply') {
            $postData['topic_type'] = $post_info->topic_type;
        }

        $postData['first_post'] = $mode === 'newtopic';
        $postData['last_post']  = false;
        $postData['has_poll']   = false;
        $postData['edit_poll']  = false;
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
        message_die(GENERAL_MESSAGE, sprintf($lang['Sorry_' . $is_auth_type], $is_auth[$is_auth_type . '_type']));
    }

    switch ($mode) {
        case 'newtopic':
            $redirect = 'mode=newtopic&' . POST_FORUM_URL . '=' . $forumId;
            break;
        case 'thank':
        case 'reply':
        case 'topicreview':
            $redirect = 'mode=reply&' . POST_TOPIC_URL . '=' . $topicId;
            break;
        case 'quote':
        case 'editpost':
            $redirect = 'mode=quote&' . POST_POST_URL . '=' . $postId;
            break;
    }

	redirect(Session::appendSid('login.php?redirect=posting.php&' . $redirect, true));
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
            ->from(Tables::TOPICS_WATCH_TABLE)
            ->where('topic_id = %i', $topicId)
            ->where('user_id = %i', $userdata['user_id'])
            ->fetchSingle();

        $notify_user = (bool)$notify_user;
    } else {
        $notify_user = $userdata['session_logged_in'] && $is_auth['auth_read'] ? $userdata['user_notify'] : 0;
    }
}

if ($submit || $refresh) {
    $attachSignature = isset($_POST['attach_sig']);
} else {
    if ($userdata['user_id'] === ANONYMOUS) {
        $attachSignature = 0;
    } else {
        $attachSignature = $userdata['user_attachsig'];
    }
}

execute_posting_attachment_handling();

// --------------------
//  What shall we do?
//
if (($delete || $pollDelete || $mode === 'delete') && !$confirm) {
	//
	// Confirm deletion
	//
	$s_hidden_fields  = '<input type="hidden" name="' . POST_POST_URL . '" value="' . $postId . '" />';
	$s_hidden_fields .= $delete || $mode === 'delete' ? '<input type="hidden" name="mode" value="delete" />' : '<input type="hidden" name="mode" value="poll_delete" />';
	$s_hidden_fields .= '<input type="hidden" name="sid" value="' . $userdata['session_id'] . '" />';

	$l_confirm = ( $delete || $mode === 'delete' ) ? $lang['Confirm_delete'] : $lang['Confirm_delete_poll'];

	//
	// Output confirmation page
	//
    PageHelper::header($template, $userdata, $board_config, $lang, $images, $theme, $page_title, $gen_simple_header);

    $template->setFileNames(['confirm_body' => 'confirm_body.tpl']);

    $template->assignVars(
        [
            'MESSAGE_TITLE' => $lang['Information'],
            'MESSAGE_TEXT'  => $l_confirm,

            'L_YES' => $lang['Yes'],
            'L_NO'  => $lang['No'],

            'S_CONFIRM_ACTION' => Session::appendSid('posting.php'),
            'S_HIDDEN_FIELDS'  => $s_hidden_fields
        ]
    );

    $template->pparse('confirm_body');

    PageHelper::footer($template, $userdata, $lang, $gen_simple_header);
} else if ($mode === 'thank') {
    $topicId = (int)$_GET[POST_TOPIC_URL];

    if (empty($topicId)) {
        message_die(GENERAL_MESSAGE, 'No topic Selected');
    }

    if (!($userdata['session_logged_in'])) {
        $message = $lang['thanks_not_logged'];
        $message .= '<br /><br />' . sprintf($lang['Click_return_topic'], '<a href="' . Session::appendSid('viewtopic.php?' . POST_TOPIC_URL . "=$topicId") . '">', '</a>');
        message_die(GENERAL_MESSAGE, $message);
    }

    // Check if user is the topic starter
    $topic_starter_check = dibi::select('topic_poster')
        ->from(Tables::TOPICS_TABLE)
        ->where('[topic_id] = %i', $topicId)
        ->where('[topic_poster] = %i', $userdata['user_id'])
        ->fetch();

    if ($topic_starter_check) {
        $message = $lang['t_starter'];
        $message .= '<br /><br />' . sprintf($lang['Click_return_topic'], '<a href="' . Session::appendSid('viewtopic.php?' . POST_TOPIC_URL . "=$topicId") . '">', '</a>');
        message_die(GENERAL_MESSAGE, $message);
    }

    // Check if user had thanked before
    $thankfull_check = dibi::select('topic_id')
        ->from(Tables::THANKS_TABLE)
        ->where('[topic_id] = %i', $topicId)
        ->where('[user_id] = %i', $userdata['user_id'])
        ->fetch();

    if ($thankfull_check) {
        $message = $lang['thanked_before'];
    } else {
        // Insert thanks if he/she hasn't
        $thanksData = [
            'topic_id' => $topicId,
            'user_id' => $userdata['user_id'],
            'thanks_time' => time()
        ];

        dibi::insert(Tables::THANKS_TABLE, $thanksData)->execute();

        $usersManager = $container->getService('UsersManager');
        $usersManager->updateByPrimary($userdata['user_id'], ['user_thanks%sql' => 'user_thanks + 1']);

        $forumsManager = $container->getService('ForumsManager');
        $forumsManager->updateByPrimary($forumId, ['forum_thanks%sql' => 'forum_thanks + 1']);

        $topicsManager = $container->getService('TopicsManager');
        $topicsManager->updateByPrimary($topicId, ['topic_thanks%sql' => 'topic_thanks + 1']);

        $message = $lang['thanks_add'];
    }

    $template->assignVars([
            'META' => '<meta http-equiv="refresh" content="3;url=' . Session::appendSid('viewtopic.php?' . POST_TOPIC_URL . "=$topicId") . '">']
    );

    $message .= '<br /><br />' . sprintf($lang['Click_return_topic'], '<a href="' . Session::appendSid('viewtopic.php?' . POST_TOPIC_URL . "=$topicId") . '">', '</a>');

    message_die(GENERAL_MESSAGE, $message);
} elseif ($mode === 'vote') {
    //
    // Vote in a poll
    //
    if (empty($_POST['vote_id'])) {
        redirect(Session::appendSid('viewtopic.php?' . POST_TOPIC_URL . "=$topicId", true));
    }

    $vote_option_id = (int)$_POST['vote_id'];

    $vote_info = dibi::select('vd.vote_id')
        ->from(Tables::VOTE_DESC_TABLE)
        ->as('vd')
        ->innerJoin(Tables::VOTE_RESULTS_TABLE)
        ->as('vr')
        ->on('vr.vote_id = vd.vote_id')
        ->where('vd.topic_id = %i', $topicId)
        ->where('vr.vote_option_id = %i', $vote_option_id)
        ->groupBy('vd.vote_id')
        ->fetch();

    if (!$vote_info) {
        message_die(GENERAL_MESSAGE, $lang['No_vote_option']);
    }

    $vote_id = $vote_info->vote_id;

    $row = dibi::select('*')
        ->from(Tables::VOTE_USERS_TABLE)
        ->where('vote_id = %i', $vote_id)
        ->where('vote_user_id = %i', $userdata['user_id'])
        ->fetch();

    if ($row) {
        message_die(GENERAL_MESSAGE, $lang['Already_voted']);
    }

    dibi::update(Tables::VOTE_RESULTS_TABLE, ['vote_result%sql' => 'vote_result + 1'])
        ->where('vote_id = %i', $vote_id)
        ->where('vote_option_id = %i', $vote_option_id)
        ->execute();

    $insert_data = [
        'vote_id'      => $vote_id,
        'vote_user_id' => $userdata['user_id'],
        'vote_user_ip' => $user_ip
    ];

    dibi::insert(Tables::VOTE_USERS_TABLE, $insert_data)->execute();

    $template->assignVars(
        [
            'META' => '<meta http-equiv="refresh" content="3;url=' . Session::appendSid('viewtopic.php?' . POST_TOPIC_URL . "=$topicId") . '">'
        ]
    );

    $message = $lang['Vote_cast'];
    $message .= '<br /><br />' . sprintf($lang['Click_view_message'], '<a href="' . Session::appendSid('viewtopic.php?' . POST_TOPIC_URL . "=$topicId") . '">', '</a>');

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
			$username = !empty($_POST['username']) ? $_POST['username']      : '';
			$subject = !empty($_POST['subject'])  ? trim($_POST['subject']) : '';
			$message = !empty($_POST['message'])  ? $_POST['message']       : '';

			$pollTitle   = isset($_POST['poll_title']) && $is_auth['auth_pollcreate']       ? $_POST['poll_title'] : '';
			$pollOptions = isset($_POST['poll_option_text']) && $is_auth['auth_pollcreate'] ? $_POST['poll_option_text'] : '';
			$pollLength  = isset($_POST['poll_length']) && $is_auth['auth_pollcreate']      ? $_POST['poll_length'] : '';

			$bbcode_uid = '';

			PostHelper::preparePost($mode, $postData, $bbcode_on, $html_on, $smilies_on, $error_msg, $username, $bbcode_uid, $subject, $message, $pollTitle, $pollOptions, $pollLength);

			if ($error_msg === '') {
				$topicType = ( $topicType !== $postData['topic_type'] && !$is_auth['auth_sticky'] && !$is_auth['auth_announce'] ) ? $postData['topic_type'] : $topicType;

				PostHelper::submitPost($mode, $postData, $return_message, $return_meta, $forumId, $topicId, $postId, $poll_id, $topicType, $bbcode_on, $html_on, $smilies_on, $attachSignature, $bbcode_uid, str_replace("\'", "''", $username), str_replace("\'", "''", $subject), str_replace("\'", "''", $message), str_replace("\'", "''", $pollTitle), $pollOptions, $pollLength);
			}
			break;

		case 'delete':
		case 'poll_delete':
			if ($error_msg !== '') {
				message_die(GENERAL_MESSAGE, $error_msg);
			}

			PostHelper::deletePost($mode, $postData, $return_message, $return_meta, $forumId, $topicId, $postId, $poll_id);
			break;
	}

    if ($error_msg === '') {
        if ($mode !== 'editpost') {
            $user_id = ($mode === 'reply' || $mode === 'newtopic') ? $userdata['user_id'] : $postData['poster_id'];

            $attachment_mod['posting']->insert_attachment($postId);
            PostHelper::updatePostStats($mode, $postData, $forumId, $topicId, $postId, $user_id);
        }

        // $mode !== 'newtopic' is because we dont have topic_title :)
        // AND we simply dont have who we should notify :D
		if ($error_msg === '' && $mode !== 'poll_delete' && $mode !== 'newtopic') {
			PostHelper::userNotification($mode, $postData, $post_info->topic_title, $forumId, $topicId, $postId, $notify_user);
		}

        if ($mode === 'newtopic' || $mode === 'reply') {
            $topicCookieName = $board_config['cookie_name'] . '_t';
            $forumCookieName = $board_config['cookie_name'] . '_f';

			$trackingTopics = !empty($_COOKIE[$topicCookieName]) ? unserialize($_COOKIE[$topicCookieName]) : [];
			$trackingForums = !empty($_COOKIE[$forumCookieName]) ? unserialize($_COOKIE[$forumCookieName]) : [];

            if (count($trackingTopics) + count($trackingForums) === 100 && empty($trackingTopics[$topicId])) {
				asort($trackingTopics);
				unset($trackingTopics[key($trackingTopics)]);
			}

            $trackingTopics[$topicId] = time();

			setcookie(
			    $topicCookieName,
                serialize($trackingTopics),
                0,
                $board_config['cookie_path'],
                $board_config['cookie_domain'],
                isConnectionsSecure()
            );
		}

        /*
        $template->assignVars(
            [
                'META' => $return_meta
            ]
        );
        */

        message_die(GENERAL_MESSAGE, $return_message);
	}
}

if ($refresh || isset($_POST['del_poll_option']) || $error_msg !== '') {
	$username = !empty($_POST['username']) ? htmlspecialchars(trim(stripslashes($_POST['username']))) : '';
	$subject  = !empty($_POST['subject'])  ? htmlspecialchars(trim(stripslashes($_POST['subject'])))  : '';
	$message  = !empty($_POST['message'])  ? htmlspecialchars(trim(stripslashes($_POST['message'])))  : '';

	$pollTitle  = !empty($_POST['poll_title']) ? htmlspecialchars(trim(stripslashes($_POST['poll_title']))) : '';
	$pollLength = isset($_POST['poll_length']) ? max(0, (int)$_POST['poll_length']) : 0;

	$pollOptions = [];

    if (!empty($_POST['poll_option_text'])) {
        foreach ($_POST['poll_option_text'] as $option_id => $option_text) {
            if (isset($_POST['del_poll_option'][$option_id])) {
                unset($pollOptions[$option_id]);
            } elseif (!empty($option_text)) {
                $pollOptions[(int)$option_id] = htmlspecialchars(trim(stripslashes($option_text)));
            }
        }
    }

    if (isset($pollAdd) && !empty($_POST['add_poll_option_text'])) {
        $pollOptions[] = htmlspecialchars(trim(stripslashes($_POST['add_poll_option_text'])));
    }

	if ($mode === 'newtopic' || $mode === 'reply') {
		$userSignature = ($userdata['user_sig'] !== '' && $board_config['allow_sig'] ) ? $userdata['user_sig'] : '';
	} elseif ($mode === 'editpost') {
		$userSignature = ( $post_info->user_sig !== '' && $board_config['allow_sig'] ) ? $post_info->user_sig : '';
		$userdata['user_sig_bbcode_uid'] = $post_info->user_sig_bbcode_uid;
	}

	if ($preview) {
		$orig_word = [];
		$replacement_word = [];
		obtain_word_list($orig_word, $replacement_word);

		$bbcode_uid = $bbcode_on ? Random::generate(BBCODE_UID_LEN) : '';
        $previewMessage = stripslashes(PostHelper::prepareMessage(addslashes(PostHelper::unPrepareMessage($message)), $html_on, $bbcode_on, $smilies_on, $bbcode_uid));
        $previewSubject = $subject;
        $previewUserName = $username;

		//
		// Finalise processing as per viewtopic
		//
        if (!$html_on) {
            if ($userSignature !== '' || !$userdata['user_allowhtml']) {
                $userSignature = preg_replace('#(<)([\/]?.*?)(>)#is', '&lt;\2&gt;', $userSignature);
            }
        }

        if ($attachSignature && $userSignature !== '' && $userdata['user_sig_bbcode_uid']) {
            $userSignature = bbencode_second_pass($userSignature, $userdata['user_sig_bbcode_uid']);
        }

        if ($bbcode_on) {
            $previewMessage = bbencode_second_pass($previewMessage, $bbcode_uid);
        }

        if (!empty($orig_word)) {
            $previewUserName = !empty($username)        ? preg_replace($orig_word, $replacement_word, $previewUserName) : '';
            $previewSubject  = !empty($subject)         ? preg_replace($orig_word, $replacement_word, $previewSubject)  : '';
            $previewMessage  = !empty($previewMessage) ? preg_replace($orig_word, $replacement_word, $previewMessage)  : '';
        }

        if ($userSignature !== '') {
            $userSignature = make_clickable($userSignature);
        }
        $previewMessage = make_clickable($previewMessage);

        if ($smilies_on) {
            if ($userdata['user_allowsmile'] && $userSignature !== '') {
                $userSignature = smilies_pass($userSignature);
            }

            $previewMessage = smilies_pass($previewMessage);
        }

        if ($attachSignature && $userSignature !== '') {
            $previewMessage .= $userSignature . $board_config['signature_delimiter'];
        }

		$previewMessage = nl2br($previewMessage);

        $template->setFileNames(['preview' => 'posting_preview.tpl']);

        $attachment_mod['posting']->preview_attachments();

        $template->assignVars(
            [
                'TOPIC_TITLE'  => $previewSubject,
                'POST_SUBJECT' => $previewSubject,
                'POSTER_NAME'  => $previewUserName,
                'POST_DATE'    => create_date($board_config['default_dateformat'], time(), $board_config['board_timezone']),
                'MESSAGE'      => $previewMessage,

                'L_POST_SUBJECT' => $lang['Post_subject'],
                'L_PREVIEW'      => $lang['Preview'],
                'L_POSTED'       => $lang['Posted'],
                'L_POST'         => $lang['Post']
            ]
        );
        $template->assignVarFromHandle('POST_PREVIEW_BOX', 'preview');
    } elseif ($error_msg !== '') {
        $template->setFileNames(['reg_header' => 'error_body.tpl']);
        $template->assignVars(['ERROR_MESSAGE' => $error_msg]);
        $template->assignVarFromHandle('ERROR_BOX', 'reg_header');
    }
} else {
	//
	// User default entry point
	//
    if ($mode === 'newtopic') {
		$userSignature = $userdata['user_sig'];

		$username   = $userdata['session_logged_in'] ? $userdata['username'] : '';
		$pollTitle  = '';
		$pollLength = '';
		$subject    = '';
		$message    = '';
    } elseif ($mode === 'reply') {
		$userSignature = $userdata['user_sig'];

		$username = $userdata['session_logged_in'] ? $userdata['username'] : '';
		$subject = '';
		$message = '';

    } elseif ($mode === 'quote' || $mode === 'editpost') {
        $pollTitle  = '';
        $pollLength = '';
		$subject    = $postData['first_post'] ? $post_info->topic_title : $post_info->post_subject;
		$message    = $post_info->post_text;

        $username = $userdata['session_logged_in'] ? $userdata['username'] : '';

        if ($mode === 'editpost') {
			$attachSignature = $post_info->enable_sig && $post_info->user_sig !== '';
			$userSignature   = $post_info->user_sig;

            $html_on    = (bool)$post_info->enable_html;
            $bbcode_on  = (bool)$post_info->enable_bbcode;
            $smilies_on = (bool)$post_info->enable_smilies;
		} else {
			$attachSignature = (bool)$userdata['user_attachsig'];
			$userSignature   = $userdata['user_sig'];
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

			$msg_date =  create_date($board_config['default_dateformat'], $post_info->post_time, $board_config['board_timezone']);

			// Use trim to get rid of spaces placed there by MS-SQL 2000
			$quoteUserName = trim($post_info->post_username) !== '' ? $post_info->post_username : $post_info->username;
			$message = '[quote="' . $quoteUserName . '"]' . $message . '[/quote]';

            if (!empty($orig_word)) {
                $subject = !empty($subject) ? preg_replace($orig_word, $replace_word, $subject) : '';
                $message = !empty($message) ? preg_replace($orig_word, $replace_word, $message) : '';
            }

            if (!preg_match('/^Re:/', $subject) && $subject !== '') {
                $subject = 'Re: ' . $subject;
            }

			$mode = 'reply';
		} else {
			$username = $post_info->user_id === ANONYMOUS && !empty($post_info->post_username) ? $post_info->post_username : '';
		}
	}
}

//
// Signature toggle selection
//
if ($userSignature !== '') {
	$template->assignBlockVars('switch_signature_checkbox', []);
}

//
// HTML toggle selection
//
if ($board_config['allow_html']) {
    $html_status = $lang['HTML_is_ON'];
    $template->assignBlockVars('switch_html_checkbox', []);
} else {
    $html_status = $lang['HTML_is_OFF'];
}

//
// BBCode toggle selection
//
if ($board_config['allow_bbcode']) {
    $bbcode_status = $lang['BBCode_is_ON'];
    $template->assignBlockVars('switch_bbcode_checkbox', []);
} else {
    $bbcode_status = $lang['BBCode_is_OFF'];
}

//
// Smilies toggle selection
//
if ($board_config['allow_smilies']) {
    $smilies_status = $lang['Smilies_are_ON'];
    $template->assignBlockVars('switch_smilies_checkbox', []);
} else {
    $smilies_status = $lang['Smilies_are_OFF'];
}

if (!$userdata['session_logged_in'] || ($mode === 'editpost' && $post_info->poster_id === ANONYMOUS)) {
    $template->assignBlockVars('switch_username_select', []);
}

//
// Notify checkbox - only show if user is logged in
//
if ($userdata['session_logged_in'] && $is_auth['auth_read']) {
    if ($mode !== 'editpost' || ($mode === 'editpost' && $post_info->poster_id !== ANONYMOUS)) {
        $template->assignBlockVars('switch_notify_checkbox', []);
    }
}

//
// Delete selection
//
if ($mode === 'editpost' && (($is_auth['auth_delete'] && $postData['last_post'] && (!$postData['has_poll'] || $postData['edit_poll'])) || $is_auth['auth_mod'])) {
    $template->assignBlockVars('switch_delete_checkbox', []);
}

//
// Topic type selection
//
$topic_type_toggle = '';
if ($mode === 'newtopic' || ($mode === 'editpost' && $postData['first_post'])) {
	$template->assignBlockVars('switch_type_toggle', []);

	if ($is_auth['auth_sticky']) {
		$topic_type_toggle .= '<input type="radio" name="topictype" value="' . POST_STICKY . '"';

        if ($postData['topic_type'] === POST_STICKY || $topicType === POST_STICKY) {
            $topic_type_toggle .= ' checked="checked"';
        }

		$topic_type_toggle .= ' /> ' . $lang['Post_Sticky'] . '&nbsp;&nbsp;';
	}

    if ($is_auth['auth_announce']) {
		$topic_type_toggle .= '<input type="radio" name="topictype" value="' . POST_ANNOUNCE . '"';

        if ($postData['topic_type'] === POST_ANNOUNCE || $topicType === POST_ANNOUNCE) {
            $topic_type_toggle .= ' checked="checked"';
        }

		$topic_type_toggle .= ' /> ' . $lang['Post_Announcement'] . '&nbsp;&nbsp;';
	}

    if ($topic_type_toggle !== '') {
		$topic_type_toggle = $lang['Post_topic_as'] . ': <input type="radio" name="topictype" value="' . POST_NORMAL .'"' . ( ($postData['topic_type'] === POST_NORMAL || $topicType === POST_NORMAL ) ? ' checked="checked"' : '' ) . ' /> ' . $lang['Post_Normal'] . '&nbsp;&nbsp;' . $topic_type_toggle;
	}
}

$hidden_form_fields = '<input type="hidden" name="mode" value="' . $mode . '" />';
$hidden_form_fields .= '<input type="hidden" name="sid" value="' . $userdata['session_id'] . '" />';

switch ($mode) {
	case 'newtopic':
		$page_title = $lang['Post_a_new_topic'];
		$hidden_form_fields .= '<input type="hidden" name="' . POST_FORUM_URL . '" value="' . $forumId . '" />';
		break;

	case 'reply':
		$page_title = $lang['Post_a_reply'];
		$hidden_form_fields .= '<input type="hidden" name="' . POST_TOPIC_URL . '" value="' . $topicId . '" />';
		break;

	case 'editpost':
		$page_title = $lang['Edit_Post'];
		$hidden_form_fields .= '<input type="hidden" name="' . POST_POST_URL . '" value="' . $postId . '" />';
		break;
}

// Generate smilies listing for page output
PostHelper::generateSmileys('inline', PAGE_POSTING);

//
// Include page header
//
PageHelper::header($template, $userdata, $board_config, $lang, $images,  $theme, $page_title, $gen_simple_header);

$template->setFileNames(
    [
        'body'       => 'posting_body.tpl',
        'pollbody'   => 'posting_poll_body.tpl',
        'reviewbody' => 'posting_topic_review.tpl'
    ]
);
make_jumpbox('viewforum.php');

$template->assignVars(
    [
        'FORUM_NAME'     => htmlspecialchars($forumName, ENT_QUOTES),
        'L_POST_A'       => $page_title,
        'L_POST_SUBJECT' => $lang['Post_subject'],

        'U_VIEW_FORUM' => Session::appendSid('viewforum.php?' . POST_FORUM_URL . "=$forumId")
    ]
);

//
// This enables the forum/topic title to be output for posting
// but not for privmsg (where it makes no sense)
//
$template->assignBlockVars('switch_not_privmsg', []);

//
// Output the data to the template
//
$template->assignVars([
    'USERNAME'       => $username,
    'SUBJECT'        => $subject,
    'MESSAGE'        => $message,
    'HTML_STATUS'    => $html_status,
    'BBCODE_STATUS'  => sprintf($bbcode_status, '<a href="' . Session::appendSid('faq.php?mode=bbcode') . '" target="_phpbbcode">', '</a>'),
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

    'U_VIEWTOPIC'    => $mode === 'reply' ? Session::appendSid('viewtopic.php?' . POST_TOPIC_URL . "=$topicId&amp;postorder=desc") : '',
    'U_REVIEW_TOPIC' => $mode === 'reply' ? Session::appendSid('posting.php?mode=topicreview&amp;' . POST_TOPIC_URL . "=$topicId") : '',

    'S_HTML_CHECKED'       => !$html_on    ? 'checked="checked"'    : '',
    'S_BBCODE_CHECKED'     => !$bbcode_on  ? 'checked="checked"'  : '',
    'S_SMILIES_CHECKED'    => !$smilies_on ? 'checked="checked"' : '',
    'S_SIGNATURE_CHECKED'  => $attachSignature  ? 'checked="checked"'  : '',
    'S_NOTIFY_CHECKED'     => $notify_user ? 'checked="checked"' : '',
    'S_TYPE_TOGGLE'        => $topic_type_toggle,
    'S_TOPIC_ID'           => $topicId,
    'S_POST_ACTION'        => Session::appendSid('posting.php'),
    'S_HIDDEN_FORM_FIELDS' => $hidden_form_fields
    ]);

//
// Poll entry switch/output
//
if (($mode === 'newtopic' || ($mode === 'editpost' && $postData['edit_poll'])) && $is_auth['auth_pollcreate']) {
    $template->assignVars(
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

            'POLL_TITLE'  => $pollTitle,
            'POLL_LENGTH' => $pollLength
        ]
    );

    if ($mode === 'editpost' && $postData['edit_poll'] && $postData['has_poll']) {
		$template->assignBlockVars('switch_poll_delete_toggle', []);
	}

	if (!empty($pollOptions)) {
	    foreach ($pollOptions as $option_id => $option_text) {
            $template->assignBlockVars('poll_option_rows',
                [
                    'POLL_OPTION' => str_replace('"', '&quot;', $option_text),

                    'S_POLL_OPTION_NUM' => $option_id
                ]
            );
        }
    }

	$template->assignVarFromHandle('POLLBOX', 'pollbody');
}

//
// Topic review
//
if ($mode === 'reply' && $is_auth['auth_read'] && (int)$board_config['topic_review'] === 1) {
    require_once $phpbb_root_path . 'includes' . $sep . 'topic_review.php';

    topic_review($topicId, true);

    $template->assignBlockVars('switch_inline_mode', []);
    $template->assignVarFromHandle('TOPIC_REVIEW_BOX', 'reviewbody');
}

$template->pparse('body');

PageHelper::footer($template, $userdata, $lang, $gen_simple_header);

?>