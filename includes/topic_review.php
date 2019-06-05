<?php
/***************************************************************************
 *                              topic_review.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: topic_review.php 5142 2005-05-06 20:50:13Z acydburn $
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
 *
 ***************************************************************************/

function topic_review($topic_id, $is_inline_review)
{
	global $board_config, $template, $lang, $images, $theme, $phpbb_root_path;
	global $userdata, $user_ip;
	global $orig_word, $replacement_word;

    if (!$is_inline_review) {
        if (!isset($topic_id) || !$topic_id) {
            message_die(GENERAL_MESSAGE, 'Topic_post_not_exist');
        }

		$columns = [
		    't.topic_title',
            'f.forum_id',
            'f.auth_view',
            'f.auth_read',
            'f.auth_post',
            'f.auth_reply',
            'f.auth_edit',
            'f.auth_delete',
            'f.auth_sticky',
            'f.auth_announce',
            'f.auth_pollcreate',
            'f.auth_vote',
            'f.auth_attachments'
        ];

        //
        // Get topic info ...
        //
        $forum_row = dibi::select($columns)
            ->from(TOPICS_TABLE)
            ->as('t')
            ->innerJoin(FORUMS_TABLE)
            ->as('f')
            ->on('f.forum_id = t.forum_id')
            ->where('t.topic_id = %i', $topic_id)
            ->fetch();

        if (!$forum_row) {
            message_die(GENERAL_MESSAGE, 'Topic_post_not_exist');
        }


		$forum_id = $forum_row->forum_id;
		$topic_title = $forum_row->topic_title;
		
		//
		// Start session management
		//
		$userdata = session_pagestart($user_ip, $forum_id);
		init_userprefs($userdata);
		//
		// End session management
		//

		$is_auth = [];
		// TODO for now to arrary
		$is_auth = auth(AUTH_ALL, $forum_id, $userdata, $forum_row->toArray());

        if (!$is_auth['auth_read']) {
            message_die(GENERAL_MESSAGE, sprintf($lang['Sorry_auth_read'], $is_auth['auth_read_type']));
        }
	}

	//
	// Define censored word matches
	//
    if (empty($orig_word) && empty($replacement_word)) {
		$orig_word = [];
		$replacement_word = [];

		obtain_word_list($orig_word, $replacement_word);
	}

	//
	// Dump out the page header and load viewtopic body template
	//
    if (!$is_inline_review) {
		$gen_simple_header = true;

		$page_title = $lang['Topic_review'] . ' - ' . $topic_title;
		include $phpbb_root_path . 'includes/page_header.php';

        $template->set_filenames(['reviewbody' => 'posting_topic_review.tpl']);
    }

    //
	// Go ahead and pull all data for this topic
	//
    $rows = dibi::select(['u.username', 'u.user_id', 'p.*', 'pt.post_text', 'pt.post_subject', 'pt.bbcode_uid'])
        ->from(POSTS_TABLE)
        ->as('p')
        ->innerJoin(USERS_TABLE)
        ->as('u')
        ->on('p.poster_id = u.user_id')
        ->innerJoin(POSTS_TEXT_TABLE)
        ->as('pt')
        ->on('p.post_id = pt.post_id')
        ->where('p.topic_id = %i', $topic_id)
        ->orderBy('p.post_time', dibi::DESC)
        ->limit($board_config['posts_per_page'])
        ->fetchAll();

	//
	// Okay, let's do the loop, yeah come on baby let's do the loop
	// and it goes like this ...
	//
    if (count($rows)) {
		$mini_post_img = $images['icon_minipost'];
		$mini_post_alt = $lang['Post'];

		$i = 0;
		
		foreach ($rows as $row) {
			$poster_id = $row->user_id;
			$poster = $row->username;

			$post_date = create_date($board_config['default_dateformat'], $row->post_time,
			$board_config['board_timezone']);

			//
			// Handle anon users posting with usernames
			//
            if ($poster_id === ANONYMOUS && $row->post_username !== '') {
				$poster = $row->post_username;
				$poster_rank = $lang['Guest'];
            } elseif ($poster_id === ANONYMOUS) {
				$poster = $lang['Guest'];
				$poster_rank = '';
			}

			$post_subject = $row->post_subject;

			$message = $row->post_text;
			$bbcode_uid = $row->bbcode_uid;

			//
			// If the board has HTML off but the post has HTML
			// on then we process it, else leave it alone
			//
            if (!$board_config['allow_html'] && $row->enable_html) {
                $message = preg_replace('#(<)([\/]?.*?)(>)#is', '&lt;\2&gt;', $message);
            }

            if ($bbcode_uid !== '') {
                $message = $board_config['allow_bbcode'] ? bbencode_second_pass($message, $bbcode_uid) : preg_replace('/\:[0-9a-z\:]+\]/si', ']', $message);
            }

			$message = make_clickable($message);

            if (count($orig_word)) {
                $post_subject = preg_replace($orig_word, $replacement_word, $post_subject);
                $message      = preg_replace($orig_word, $replacement_word, $message);
            }

            if ($board_config['allow_smilies'] && $row->enable_smilies) {
                $message = smilies_pass($message);
            }

			$message = str_replace("\n", '<br />', $message);

			//
			// Again this will be handled by the templating
			// code at some point
			//
			$row_color = ( !($i % 2) ) ? $theme['td_color1'] : $theme['td_color2'];
			$row_class = ( !($i % 2) ) ? $theme['td_class1'] : $theme['td_class2'];

            $template->assign_block_vars('postrow',
                [
                    'ROW_COLOR' => '#' . $row_color,
                    'ROW_CLASS' => $row_class,

                    'MINI_POST_IMG' => $mini_post_img,
                    'POSTER_NAME'   => $poster,
                    'POST_DATE'     => $post_date,
                    'POST_SUBJECT'  => $post_subject,
                    'MESSAGE'       => $message,

                    'L_MINI_POST_ALT' => $mini_post_alt
                ]
            );

            $i++;
		}
	} else {
		message_die(GENERAL_MESSAGE, 'Topic_post_not_exist', '', __LINE__, __FILE__);
	}

    $template->assign_vars(
        [
            'L_AUTHOR'       => $lang['Author'],
            'L_MESSAGE'      => $lang['Message'],
            'L_POSTED'       => $lang['Posted'],
            'L_POST_SUBJECT' => $lang['Post_subject'],
            'L_TOPIC_REVIEW' => $lang['Topic_review']
        ]
    );

    if (!$is_inline_review) {
        $template->pparse('reviewbody');
        include $phpbb_root_path . 'includes/page_tail.php';
    }
}

?>