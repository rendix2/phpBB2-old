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

/**
 * @param int  $topic_id
 * @param bool $is_inline_review
 *
 * @throws Exception
 */
function topic_review($topic_id, $is_inline_review)
{
    /**
     * @var BaseTemplate $template
     */
    global $template;

	global $board_config, $lang, $images, $theme;
	global $userdata;
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
            'f.auth_attachments',
            'f.auth_download',
            't.topic_attachment'
        ];

        //
        // Get topic info ...
        //
        $forum = dibi::select($columns)
            ->from(Tables::TOPICS_TABLE)
            ->as('t')
            ->innerJoin(Tables::FORUMS_TABLE)
            ->as('f')
            ->on('f.forum_id = t.forum_id')
            ->where('t.topic_id = %i', $topic_id)
            ->fetch();

        if (!$forum) {
            message_die(GENERAL_MESSAGE, 'Topic_post_not_exist');
        }
		
		//
		// Start session management
		//
        $userdata = init_userprefs($forum->forum_id);
		//
		// End session management
		//

		// TODO for now to arrary
		$is_auth = Auth::authorize(Auth::AUTH_ALL, $forum->forum_id, $userdata, $forum->toArray());

        if (!$is_auth['auth_read']) {
            message_die(GENERAL_MESSAGE, sprintf($lang['Sorry_auth_read'], $is_auth['auth_read_type']));
        }
	}

    $count_orig_word = 0;

	//
	// Define censored word matches
	//
    if (empty($orig_word) && empty($replacement_word)) {
		$orig_word = [];
		$replacement_word = [];

		obtain_word_list($orig_word, $replacement_word);

        $count_orig_word = count($orig_word);
	}

	//
	// Dump out the page header and load viewtopic body template
	//
    if (!$is_inline_review) {
		$gen_simple_header = true;

		$page_title = $lang['Topic_review'] . ' - ' . $forum->topic_title;

        PageHelper::header($template, $userdata, $board_config, $lang, $images,  $theme, $page_title, $gen_simple_header);

        $template->setFileNames(['reviewbody' => 'posting_topic_review.tpl']);
    }

    //
	// Go ahead and pull all data for this topic
	//
    $posts = dibi::select(['u.username', 'u.user_id', 'p.*', 'pt.post_text', 'pt.post_subject', 'pt.bbcode_uid'])
        ->from(Tables::POSTS_TABLE)
        ->as('p')
        ->innerJoin(Tables::USERS_TABLE)
        ->as('u')
        ->on('p.poster_id = u.user_id')
        ->innerJoin(Tables::POSTS_TEXT_TABLE)
        ->as('pt')
        ->on('p.post_id = pt.post_id')
        ->where('p.topic_id = %i', $topic_id)
        ->orderBy('p.post_time', dibi::DESC)
        ->limit($board_config['posts_per_page'])
        ->fetchAll();

    init_display_review_attachments($is_auth);

	//
	// Okay, let's do the loop, yeah come on baby let's do the loop
	// and it goes like this ...
	//
    if (count($posts)) {
		foreach ($posts as $i => $post) {
			$poster = $post->username;

			$post_date = create_date($board_config['default_dateformat'], $post->post_time, $board_config['board_timezone']);

			//
			// Handle anon users posting with usernames
			//

            if ($post->user_id === ANONYMOUS) {
                $poster = $lang['Guest'];

                if ($post->post_username !== '') {
                    $poster = $post->post_username;
                }
            }

			//
			// If the board has HTML off but the post has HTML
			// on then we process it, else leave it alone
			//
            if (!$board_config['allow_html'] && $post->enable_html) {
                $post->post_text = preg_replace('#(<)([\/]?.*?)(>)#is', '&lt;\2&gt;', $post->post_text);
            }

            if ($post->bbcode_uid !== '') {
                $post->post_text = $board_config['allow_bbcode'] ? bbencode_second_pass($post->post_text, $post->bbcode_uid) : preg_replace('/\:[0-9a-z\:]+\]/si', ']', $post->post_text);
            }

            $post->post_text = make_clickable($post->post_text);

            if ($count_orig_word) {
                $post->post_subject = preg_replace($orig_word, $replacement_word, $post->post_subject);
                $post->post_text    = preg_replace($orig_word, $replacement_word, $post->post_text);
            }

            if ($board_config['allow_smilies'] && $post->enable_smilies) {
                $post->post_text = smilies_pass($post->post_text);
            }

            $post->post_text = nl2br($post->post_text);

			//
			// Again this will be handled by the templating
			// code at some point
			//
			$rowColor = !($i % 2) ? $theme['td_color1'] : $theme['td_color2'];
			$rowClass = !($i % 2) ? $theme['td_class1'] : $theme['td_class2'];

            $template->assignBlockVars('postrow',
                [
                    'ROW_COLOR' => '#' . $rowColor,
                    'ROW_CLASS' => $rowClass,

                    'MINI_POST_IMG' => $images['icon_minipost'],
                    'POSTER_NAME'   => $poster,
                    'POST_DATE'     => $post_date,
                    'POST_SUBJECT'  => $post->post_subject,
                    'MESSAGE'       => $post->post_text,

                    'L_MINI_POST_ALT' => $lang['Post']
                ]
            );

            display_review_attachments($row['post_id'], $row['post_attachment'], $is_auth);
		}
	} else {
		message_die(GENERAL_MESSAGE, 'Topic_post_not_exist', '', __LINE__, __FILE__);
	}

    $template->assignVars(
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

        PageHelper::footer($template, $userdata, $lang, $gen_simple_header);
    }
}

?>