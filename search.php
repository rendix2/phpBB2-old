<?php
/***************************************************************************
 *                                search.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: search.php 6772 2006-12-16 13:11:28Z acydburn $
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
require_once $phpbb_root_path . 'includes' . $sep . 'bbcode.php';
require_once $phpbb_root_path . 'includes' . $sep . 'functions_search.php';

//
// Start session management
//
$userdata = Session::pageStart($user_ip, PAGE_SEARCH);
init_userprefs($userdata);
//
// End session management
//

//
// Define initial vars
//
if (isset($_POST[POST_MODE]) || isset($_GET[POST_MODE])) {
    $mode = isset($_POST[POST_MODE]) ? $_POST[POST_MODE] : $_GET[POST_MODE];
} else {
    $mode = '';
}

if (isset($_POST['search_keywords']) || isset($_GET['search_keywords'])) {
    $search_keywords = isset($_POST['search_keywords']) ? $_POST['search_keywords'] : $_GET['search_keywords'];
} else {
    $search_keywords = '';
}

if (isset($_POST['search_author']) || isset($_GET['search_author'])) {
    $search_author = isset($_POST['search_author']) ? $_POST['search_author'] : $_GET['search_author'];
    $search_author = phpbb_clean_username($search_author);
} else {
    $search_author = '';
}

$search_id = isset($_GET['search_id']) ? $_GET['search_id'] : '';

if (isset($_GET['show_results'])) {
    $show_results = $_GET['show_results'];
} elseif (isset($_POST['show_results'])) {
    $show_results = $_POST['show_results'];
} else {
    $show_results = 'posts';
}

if (isset($_POST['search_terms'])) {
    $search_terms = $_POST['search_terms'] === 'all' ? 1 : 0;
} else {
    $search_terms = 0;
}

if (isset($_POST['search_fields'])) {
    $search_fields = $_POST['search_fields'] === 'all' ? 1 : 0;
} else {
    $search_fields = 0;
}

$return_chars = isset($_POST['return_chars']) ? (int)$_POST['return_chars'] : 200;
$search_cat   = isset($_POST['search_cat'])   ? (int)$_POST['search_cat']   : -1;
$search_forum = isset($_POST['search_forum']) ? (int)$_POST['search_forum'] : -1;
$sort_by      = isset($_POST['sort_by'])      ? (int)$_POST['sort_by']      : 0;

if (isset($_POST['sort_dir'])) {
    $sort_dir = $_POST['sort_dir'] === 'DESC' ? 'DESC' : 'ASC';
} else {
    $sort_dir = 'DESC';
}

if (!empty($_POST['search_time']) || !empty($_GET['search_time'])) {
    $user_timezone = isset($_POST['timezone']) ? $_POST['timezone'] : $board_config['board_timezone'];

    $search_time = new DateTime();
    $search_time->setTimezone(new DateTimeZone($user_timezone));

    if (!empty($_POST['search_time'])) {
        $search_time->sub(new DateInterval('PT' . $_POST['search_time'] . 'D'));
    } else {
        $search_time->sub(new DateInterval('PT' . $_GET['search_time'] . 'D'));
    }

    $search_time = $search_time->getTimestamp();
	$topic_days = !empty($_POST['search_time']) ? (int)$_POST['search_time'] : (int)$_GET['search_time'];
} else {
	$search_time = 0;
	$topic_days = 0;
}

$start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
$start = $start < 0 ? 0 : $start;

//
// encoding match for workaround
//
$multibyte_charset = 'utf-8, big5, shift_jis, euc-kr, gb2312';

//
// Begin core code
//
if ($mode === 'searchuser') {
    //
    // This handles the simple windowed user search functions called from various other scripts
    //
    if (isset($_POST['search_username'])) {
        username_search($_POST['search_username']);
    } else {
        username_search('');
    }

    exit;
} elseif ($search_keywords !== '' || $search_author !== '' || $search_id) {
    $split_search = [];
    $search_results = '';

	//
	// Search ID Limiter, decrease this value if you experience further timeout problems with searching forums
	$limiter = 5000;
	$current_time = time();

	//
	// Cycle through options ...
	//
    if ($search_id === 'newposts' || $search_id === 'egosearch' || $search_id === 'unanswered' || $search_keywords !== '' || $search_author !== '') {
		//
		// Flood control
		//
		$result = dibi::select('MAX(sr.search_time)')
            ->as('last_search_time')
            ->from(SEARCH_TABLE)
            ->as('sr')
            ->innerJoin(SESSIONS_TABLE)
            ->as('se')
            ->on('sr.session_id = se.session_id');

		if ($userdata['user_id'] === ANONYMOUS) {
		    $result->where('se.session_ip = %s', $user_ip);
        } else {
		    $result->where('se.session_user_id = %s', $userdata['user_id']);
        }

		$result = $result->fetch();

        if ((int)$result->last_search_time > 0 && ($current_time - (int)$result->last_search_time) < (int)$board_config['search_flood_interval']) {
            message_die(GENERAL_MESSAGE, $lang['Search_Flood_Error']);
        }

        if ($search_id === 'newposts' || $search_id === 'egosearch' || ($search_author !== '' && $search_keywords === '')) {
            if ($search_id === 'newposts') {
                if ($userdata['session_logged_in']) {
                    $search_ids = dibi::select('post_id')
                        ->from(POSTS_TABLE)
                        ->where('post_time >= %i', $userdata['user_lastvisit'])
                        ->fetchPairs(null, 'post_id');
				} else {
					redirect(Session::appendSid('login.php?redirect=search.php&search_id=newposts', true));
				}

				$show_results = 'topics';
				$sort_by = 0;
				$sort_dir = 'DESC';
            } elseif ($search_id === 'egosearch') {
                if ($userdata['session_logged_in']) {
                    $search_ids = dibi::select('post_id')
                        ->from(POSTS_TABLE)
                        ->where('poster_id = %i', $userdata['user_id'])
                        ->fetchPairs(null, 'post_id');
                } else {
                    redirect(Session::appendSid('login.php?redirect=search.php&search_id=egosearch', true));
                }

				$show_results = 'topics';
				$sort_by = 0;
				$sort_dir = 'DESC';
			} else {
				$search_author = str_replace('*', '%', trim($search_author));

				if (( strpos($search_author, '%') !== false ) && ( strlen(str_replace('%', '', $search_author)) < $board_config['search_min_chars'] )) {
					$search_author = '';
				}

                $user_ids = dibi::select('user_id')
                    ->from(USERS_TABLE)
                    ->where('username LIKE %~like~', $search_author)
                    ->fetchPairs(null, 'user_id');

                if (!count($user_ids)) {
                    message_die(GENERAL_MESSAGE, $lang['No_search_match']);
                }

                $search_ids = dibi::select('post_id')
                    ->from(POSTS_TABLE)
                    ->where('poster_id IN %in', $user_ids);

                if ($search_time) {
                    $search_ids->where('post_time >= %i', $search_time);
                }

                $search_ids = $search_ids->fetchPairs(null, 'post_id');
			}

			$total_match_count = count($search_ids);

        } elseif ($search_keywords !== '') {
            $stopword_array = @file($phpbb_root_path . 'language' . $sep . 'lang_' . $board_config['default_lang'] . $sep . 'search_stopwords.txt');
            $synonym_array = @file($phpbb_root_path . 'language' . $sep . 'lang_' . $board_config['default_lang'] . $sep . 'search_synonyms.txt');

			$split_search = [];
			$stripped_keywords = stripslashes($search_keywords);
			$split_search = ( !strstr($multibyte_charset, $lang['ENCODING']) ) ?  split_words(clean_words('search', $stripped_keywords, $stopword_array, $synonym_array), 'search') : explode(' ', $search_keywords);
			unset($stripped_keywords);

			$word_count = 0;
			$current_match_type = 'or';

			$word_match = [];
			$result_list = [];

            foreach ($split_search as $split_search_value) {
                if (strlen(str_replace(['*', '%'], '', trim($split_search_value))) < $board_config['search_min_chars']) {
                    $split_search_value = '';
					continue;
				}

				switch ( $split_search_value) {
					case 'and':
						$current_match_type = 'and';
						break;

					case 'or':
						$current_match_type = 'or';
						break;

					case 'not':
						$current_match_type = 'not';
						break;

					default:
                        if (!empty($search_terms)) {
                            $current_match_type = 'and';
                        }

						if (false === strpos($multibyte_charset, $lang['ENCODING'])) {
							$match_word = str_replace('*', '%', $split_search_value);

							// TODO THERE WAS LIKE 'awwdawd' WITHOUT LIKE '%%'
							$post_ids = dibi::select('m.post_id')
                                ->from(SEARCH_WORD_TABLE)
                                ->as('w')
                                ->innerJoin(SEARCH_MATCH_TABLE)
                                ->as('m')
                                ->on('m.word_id = w.word_id')
                                ->where('w.word_text LIKE %~like~', $match_word)
                                ->where('w.word_common <> %i', 1);

                            if (!$search_fields) {
                                $post_ids->where('m.title_match = %i', 0);
                            }

                            $post_ids = $post_ids->fetchPairs(null, 'post_id');
						} else {
							$match_word =  addslashes('%' . str_replace('*', '', $split_search_value) . '%');

                            // TODO THERE WAS LIKE 'awwdawd' WITHOUT LIKE '%%'
                            if ($search_fields) {
                                $post_ids = dibi::select('post_id')
                                    ->from(POSTS_TEXT_TABLE)
                                    ->where('post_text LIKE ~%like~ OR post_subject LIKE %~like~', $match_word, $match_word);
                            } else {
                                $post_ids = dibi::select('post_id')
                                    ->from(POSTS_TEXT_TABLE)
                                    ->where('post_text LIKE %like', $match_word);
                            }

                            $post_ids = $post_ids->fetchPairs(null, 'post_id');
						}

                        $row = [];

						foreach ($post_ids as $post_id) {
                            $row[$post_id] = 1;

                            if (!$word_count) {
                                $result_list[$post_id] = 1;
                            } elseif ($current_match_type === 'or') {
                                $result_list[$post_id] = 1;
                            } elseif ($current_match_type === 'not') {
                                $result_list[$post_id] = 0;
                            }
                        }

                        if ($current_match_type === 'and' && $word_count) {
                            foreach ($result_list as $post_id => $match_count) {
                                if (!$row[$post_id]) {
                                    $result_list[$post_id] = 0;
                                }
                            }
                        }

						$word_count++;
                }
			}

			$search_ids = [];

			foreach ($result_list as $post_id => $matches) {
                if ($matches) {
                    $search_ids[] = $post_id;
                }
            }

			unset($result_list);
			$total_match_count = count($search_ids);
		}

		//
		// If user is logged in then we'll check to see which (if any) private
		// forums they are allowed to view and include them in the search.
		//
		// If not logged in we explicitly prevent searching of private forums
		//
		$auth_sql = '';
        if ($search_forum !== -1) {
            $is_auth = Auth::authorize(AUTH_READ, $search_forum, $userdata);

            if (!$is_auth['auth_read']) {
                message_die(GENERAL_MESSAGE, $lang['No_searchable_forums']);
            }

            $auth_sql = "f.forum_id = $search_forum";
        } else {
            $is_auth_ary = Auth::authorize(AUTH_READ, AUTH_LIST_ALL, $userdata);

            if ($search_cat !== -1) {
                $auth_sql = "f.cat_id = $search_cat";
            }

            $ignore_forum_sql = '';

            foreach ($is_auth_ary as $key => $value) {
                if (!$value['auth_read']) {
                    $ignore_forum_sql .= (($ignore_forum_sql !== '') ? ', ' : '') . $key;
                }
            }

            if ($ignore_forum_sql !== '') {
                $auth_sql .= $auth_sql !== '' ? " AND f.forum_id NOT IN ($ignore_forum_sql) " : "f.forum_id NOT IN ($ignore_forum_sql) ";
            }
        }

		//
		// Author name search
		//
        if ($search_author !== '') {
			$search_author = str_replace('*', '%', trim($search_author));

			if (( strpos($search_author, '%') !== false ) && ( strlen(str_replace('%', '', $search_author)) < $board_config['search_min_chars'] )) {
				$search_author = '';
			}
		}

        if ($total_match_count) {
            if ($show_results === 'topics') {
				//
				// This one is a beast, try to seperate it a bit (workaround for connection timeouts)
				//

                // todo maybe preserve keys?
				$search_id_chunks = array_chunk($search_ids, $limiter);
				$search_ids = [];

                foreach ($search_id_chunks as $search_id_chunk) {
                    if ($search_author === '' && $auth_sql === '') {
                        $search_ids = dibi::select('topic_id')
                            ->from(POSTS_TABLE)
                            ->where('post_id IN %in', $search_id_chunk);

                        if ($search_time) {
                            $search_ids->where('post_time >= %i', $search_time);
                        }

                        $search_ids = $search_ids->groupBy('topic_id')
                            ->fetchPairs(null, 'topic_id');
                    } else {
						$from_sql = POSTS_TABLE . ' p';

                        $search_ids = dibi::select('p.topic_id')
                            ->from(POSTS_TABLE)
                            ->as('p');

                        if ($search_author !== '') {
                            $search_ids->innerJoin(USERS_TABLE)
                                ->as('u')
                                ->on('u.user_id = p.poster_id')
                                ->where('u.user_id IN %in', $user_ids);

                            if ($search_time) {
                                $search_ids->where('p.post_time >= %i', $search_time);
                            }
                        }

                        if ($auth_sql !== '') {
                            $search_ids->from(FORUMS_TABLE)
                                ->as('f')
                                ->where('f.forum_id = p.forum_id');

                            if ($search_time) {
                                $search_ids->where('p.post_time >= %i', $search_time);
                            }

                            $search_ids->where($auth_sql);
                        }

                        $search_ids = $search_ids->where('p.post_id IN %in', $search_id_chunk)
                            ->groupBy('p.topic_id')
                        ->fetchPairs(null, 'topic_id');
					}
				}

				$total_match_count = count($search_ids);

			} elseif ($search_author !== '' || $search_time || $auth_sql !== '') {
				// TODO maybe use preserve keys?
                $search_id_chunks = array_chunk($search_ids, $limiter);
				$search_ids = [];

				foreach ($search_id_chunks as $search_id_chunk) {
                    if ($search_author === '' && $auth_sql === '') {
                        $search_ids = dibi::select('post_id')
                            ->from(POSTS_TABLE)
                            ->where('post_id IN %in', $search_id_chunk);

                        if ($search_time) {
                            $search_ids->where('post_time >= %i', $search_time);
                        }

                        $search_ids = $search_ids->fetchPairs(null, 'post_id');
                    } else {
                        $search_ids = dibi::select('p.post_id')
                            ->from(POSTS_TABLE)
                            ->as('p');

                        if ($auth_sql !== '') {
                            $search_ids->from(FORUMS_TABLE)
                                ->as('f')
                                ->where('f.forum_id = p.forum_id')
                                ->where($auth_sql);
                        }

                        // TODO this query we can optimize
                        if ($search_author !== '') {
                            $search_ids->from(USERS_TABLE)
                                ->as('u')
                                ->where('u.user_id = p.poster_id')
                                ->where('u.username LIKE %~like~', $search_author);
                        }

                        $search_ids->where('p.post_id IN %in', $search_id_chunk);

                        if ($search_time) {
                            $search_ids->where('p.post_time >= %i', $search_time);
                        }

                        $search_ids = $search_ids->fetchPairs(null, 'post_id');
                    }
				}

				$total_match_count = count($search_ids);
			}
		} elseif ($search_id === 'unanswered') {
			if ($auth_sql !== '') {
			    // TODO i guess we dont need f.forum
                $search_ids = dibi::select(['t.topic_id', 'f.forum_id'])
                    ->from(TOPICS_TABLE)
                    ->as('t')
                    ->innerJoin(FORUMS_TABLE)
                    ->as('f')
                    ->on('t.forum_id = f.forum_id')
                    ->where('t.topic_replies = %i', 0)
                    ->where('t.topic_moved_id = %i', 0)
                    ->where($auth_sql)
                    ->fetchPairs(null, 'topic_id');
			} else {
                $search_ids = dibi::select('topic_id')
                    ->from(TOPICS_TABLE)
                    ->where('topic_replies = %i', 0)
                    ->where('topic_moved_id = %i', 0)
                    ->fetchPairs(null, 'topic_id');
			}

			$total_match_count = count($search_ids);

			//
			// Basic requirements
			//
			$show_results = 'topics';
			$sort_by = 0;
			$sort_dir = 'DESC';
        } else {
            message_die(GENERAL_MESSAGE, $lang['No_search_match']);
        }

		//
		// Delete old data from the search result table
		//
        dibi::delete(SEARCH_TABLE)
            ->where('search_time < %i', $current_time - (int)$board_config['session_length'])
            ->execute();

		//
		// Store new result data
		//
		$search_results = implode(', ', $search_ids);
		$per_page = $show_results === 'posts' ? $board_config['posts_per_page'] : $board_config['topics_per_page'];

		//
		// Combine both results and search data (apart from original query)
		// so we can serialize it and place it in the DB
		//
		$store_search_data = [];

		//
		// Limit the character length (and with this the results displayed at all following pages) to prevent
		// truncated result arrays. Normally, search results above 12000 are affected.
		// - to include or not to include
		/*
		$max_result_length = 60000;
		if (strlen($search_results) > $max_result_length)
		{
			$search_results = substr($search_results, 0, $max_result_length);
			$search_results = substr($search_results, 0, strrpos($search_results, ','));
			$total_match_count = count(explode(', ', $search_results));
		}
		*/

        $store_search_data['search_results'] = $search_results;
        $store_search_data['total_match_count'] = $total_match_count;
        $store_search_data['split_search'] = $split_search;
        $store_search_data['sort_by'] = $sort_by;
        $store_search_data['sort_dir'] = $sort_dir;
        $store_search_data['show_results'] = $show_results;
        $store_search_data['return_chars'] = $return_chars;

		$result_array = serialize($store_search_data);
		unset($store_search_data);

		mt_srand ((double) microtime() * 1000000);
		$search_id = mt_rand();

        $update_data = [
            'search_id' => $search_id,
            'search_time' => $current_time,
            'search_array' => $result_array
        ];

		dibi::update(SEARCH_TABLE, $update_data)
            ->where('session_id = %s', $userdata['session_id'])
            ->execute();

        if (!dibi::getAffectedRows()) {
            $insert_data = [
                'search_id' => $search_id,
                'session_id' => $userdata['session_id'],
                'search_time' => $current_time,
                'search_array' => $result_array

            ];

            dibi::insert(SEARCH_TABLE, $insert_data)->execute();
		}
	} else {
		$search_id = (int)$search_id;

		if ($search_id) {
            $row = dibi::select('search_array')
                ->from(SEARCH_TABLE)
                ->where('search_id = %i', $search_id)
                ->where('session_id = %s', $userdata['session_id'])
                ->fetch();

            $search_data = unserialize($row->search_array);

            $search_results    = $search_data['search_results'];
            $total_match_count = $search_data['total_match_count'];
            $split_search      = $search_data['split_search'];
            $sort_by           = $search_data['sort_by'];
            $sort_dir          = $search_data['sort_dir'];
            $show_results      = $search_data['show_results'];
            $return_chars      = $search_data['return_chars'];
		}
	}

	//
	// Look up data ...
	//
    if ($search_results !== '') {
        if ($show_results === 'posts') {
            $columns = [
                'pt.post_text',
                'pt.bbcode_uid',
                'pt.post_subject',
                'p.*',
                'f.forum_id',
                'f.forum_name',
                't.*',
                'u.username',
                'u.user_id',
                'u.user_sig',
                'u.user_sig_bbcode_uid'
            ];

            $search_sets = dibi::select($columns)
                ->from(FORUMS_TABLE)
                ->as('f')
                ->innerJoin(POSTS_TABLE)
                ->as('p')
                ->on('f.forum_id = p.forum_id')
                ->innerJoin(TOPICS_TABLE)
                ->as('t')
                ->on('p.topic_id = t.topic_id')
                ->innerJoin(USERS_TABLE)
                ->as('u')
                ->on('p.poster_id = u.user_id')
                ->innerJoin(POSTS_TEXT_TABLE)
                ->as('pt')
                ->on('pt.post_id = p.post_id')
                ->where('p.post_id IN %in', $search_ids);
		} else {
            $columns = [
                't.*',
                'f.forum_id',
                'f.forum_name',
                'u.username',
                'u.user_id',
            ];

            $search_sets = dibi::select($columns)
                ->select('u2.username')
                ->as('user2')
                ->select('u2.user_id')
                ->as('id2')
                ->select('p.post_username')
                ->select('p2.post_username')
                ->as('post_username2')
                ->select('p2.post_time')
                ->select('p.post_id')
                ->select('pt.post_text')
                ->select('u.user_allowhtml')
                ->from(TOPICS_TABLE)
                ->as('t')
                ->innerJoin(FORUMS_TABLE)
                ->as('f')
                ->on('f.forum_id = t.forum_id')
                ->innerJoin(USERS_TABLE)
                ->as('u')
                ->on('t.topic_poster = u.user_id')
                ->innerJoin(POSTS_TABLE)
                ->as('p')
                ->on('p.post_id = t.topic_first_post_id')
                ->innerJoin(POSTS_TABLE)
                ->as('p2')
                ->on('p2.post_id = t.topic_last_post_id')
                ->innerJoin(USERS_TABLE)
                ->as('u2')
                ->on('u2.user_id = p2.poster_id')
                ->innerJoin(POSTS_TEXT_TABLE)
                ->as('pt')
                ->on('p.post_id = pt.post_id')
                ->where('t.topic_id IN %in', $search_ids);
		}

		$per_page = $show_results === 'posts' ? $board_config['posts_per_page'] : $board_config['topics_per_page'];

		switch ( $sort_by) {
			case 1:
                $order_by = $show_results === 'posts' ? 'pt.post_subject' : 't.topic_title';
                $search_sets->orderBy($order_by, $sort_dir);
				break;
			case 2:
                $search_sets->orderBy('t.topic_title', $sort_dir);
				break;
			case 3:
                $search_sets->orderBy('u.username', $sort_dir);
				break;
			case 4:
                $search_sets->orderBy('f.forum_id', $sort_dir);
				break;
			default:
                $order_by = $show_results === 'posts' ? 'p.post_time' : 'p2.post_time';
                $search_sets->orderBy($order_by, $sort_dir);
				break;
		}

        $search_sets = $search_sets->limit($per_page)
            ->offset($start)
            ->fetchAll();

		//
		// Define censored word matches
		//
		$orig_word = [];
		$replacement_word = [];
		obtain_word_list($orig_word, $replacement_word);
        $count_orig_word = count($orig_word);

		//
		// Output header
		//
		$page_title = $lang['Search'];

        require_once $phpbb_root_path . 'includes' . $sep . 'page_header.php';

        if ($show_results === 'posts') {
            $template->setFileNames(['body' => 'search_results_posts.tpl']);
        } else {
            $template->setFileNames(['body' => 'search_results_topics.tpl']);
        }

        make_jumpbox('viewforum.php');

		$l_search_matches = $total_match_count === 1 ? sprintf($lang['Found_search_match'], $total_match_count) : sprintf($lang['Found_search_matches'], $total_match_count);

        $template->assignVars(
            [
                'L_SEARCH_MATCHES' => $l_search_matches,
                'L_TOPIC'          => $lang['Topic']
            ]
        );

        $highlight_active = '';
		$highlight_match = [];

        foreach ($split_search as $split_word) {
			if ($split_word !== 'and' && $split_word !== 'or' && $split_word !== 'not') {
				$highlight_match[] = '#\b(' . str_replace('*', "([\w]+)?", $split_word) . ')\b#is';
				$highlight_active .= ' ' . $split_word;

				foreach ($synonym_array as $synonym) {
					list($replace_synonym, $match_synonym) = explode(' ', strtolower(trim($synonym)));

					if ($replace_synonym === $split_word) {
						$highlight_match[] = '#\b(' . str_replace('*', "([\w]+)?", $replace_synonym) . ')\b#is';
						$highlight_active .= ' ' . $match_synonym;
					}
				}
			}
		}

		$highlight_active = urlencode(trim($highlight_active));

        // define cookie names
        $topic_cookie_name = $board_config['cookie_name'] . '_t';
        $forum_cookie_name = $board_config['cookie_name'] . '_f';
        $forum_all_cookie_name = $board_config['cookie_name'] . '_f_all';

		foreach ($search_sets as $search_set) {
			$forum_url = Session::appendSid('viewforum.php?' . POST_FORUM_URL . '=' . $search_set->forum_id);
			$topic_url = Session::appendSid('viewtopic.php?' . POST_TOPIC_URL . '=' . $search_set->topic_id . "&amp;highlight=$highlight_active");
			$post_url  = Session::appendSid('viewtopic.php?' . POST_POST_URL . '=' . $search_set->post_id . "&amp;highlight=$highlight_active") . '#' . $search_set->post_id;

			$post_date = create_date($board_config['default_dateformat'], $search_set->post_time,
			$board_config['board_timezone']);

			$message = $search_set->post_text;
			$topic_title = $search_set->topic_title;

			$forum_id = $search_set->forum_id;
			$topic_id = $search_set->topic_id;

            if ($show_results === 'posts') {
                if (isset($return_chars)) {
					$bbcode_uid = $search_set->bbcode_uid;

					//
					// If the board has HTML off but the post has HTML
					// on then we process it, else leave it alone
					//
                    if ($return_chars !== -1) {
						$message = strip_tags($message);
						$message = preg_replace("/\[.*?:$bbcode_uid:?.*?\]/si", '', $message);
						$message = preg_replace('/\[url\]|\[\/url\]/si', '', $message);
						$message = mb_strlen($message) > $return_chars ? substr($message, 0, $return_chars) . ' ...' : $message;
					} else {
                        if (!$board_config['allow_html'] && $search_set->user_allowhtml) {
                            $message = preg_replace('#(<)([\/]?.*?)(>)#is', '&lt;\\2&gt;', $message);
                        }

                        if ($bbcode_uid !== '') {
							$message = $board_config['allow_bbcode'] ? bbencode_second_pass($message, $bbcode_uid) : preg_replace('/\:[0-9a-z\:]+\]/si', ']', $message);
						}

						$message = make_clickable($message);

                        if ($highlight_active) {
                            if (preg_match('/<.*>/', $message)) {
								$message = preg_replace($highlight_match, '<!-- #sh -->\1<!-- #eh -->', $message);

								$end_html = 0;
								$start_html = 1;
								$temp_message = '';
								$message = ' ' . $message . ' ';

                                while ($start_html = strpos($message, '<', $start_html)) {
									$grab_length = $start_html - $end_html - 1;
									$temp_message .= substr($message, $end_html + 1, $grab_length);

                                    if ($end_html = strpos($message, '>', $start_html)) {
										$length = $end_html - $start_html + 1;
										$hold_string = substr($message, $start_html, $length);

                                        if (strrpos(' ' . $hold_string, '<') !== 1) {
											$end_html = $start_html + 1;
											$end_counter = 1;

                                            while ($end_counter && $end_html < strlen($message)) {
                                                if (substr($message, $end_html, 1) === '>') {
                                                    $end_counter--;
                                                } elseif (substr($message, $end_html, 1) === '<') {
                                                    $end_counter++;
                                                }

                                                $end_html++;
                                            }

											$length = $end_html - $start_html + 1;
											$hold_string = substr($message, $start_html, $length);
											$hold_string = str_replace('<!-- #sh -->', '', $hold_string);
											$hold_string = str_replace('<!-- #eh -->', '', $hold_string);
										} elseif ($hold_string === '<!-- #sh -->') {
											$hold_string = str_replace('<!-- #sh -->', '<span style="color:#' . $theme['fontcolor3'] . '"><b>', $hold_string);
                                        } elseif ($hold_string === '<!-- #eh -->') {
                                            $hold_string = str_replace('<!-- #eh -->', '</b></span>', $hold_string);
                                        }

										$temp_message .= $hold_string;

										$start_html += $length;
                                    } else {
                                        $start_html = strlen($message);
                                    }
								}

								$grab_length = strlen($message) - $end_html - 1;
								$temp_message .= substr($message, $end_html + 1, $grab_length);

								$message = trim($temp_message);
							} else {
								$message = preg_replace($highlight_match, '<span style="color:#' . $theme['fontcolor3'] . '"><b>\1</b></span>', $message);
							}
						}
					}

                    if ($count_orig_word) {
						$topic_title = preg_replace($orig_word, $replacement_word, $topic_title);
						$post_subject = $search_set->post_subject !== '' ? preg_replace($orig_word, $replacement_word, $search_set->post_subject) : $topic_title;

						$message = preg_replace($orig_word, $replacement_word, $message);
					} else {
						$post_subject = $search_set->post_subject !== '' ? $search_set->post_subject : $topic_title;
					}

					if ($board_config['allow_smilies'] && $search_set->enable_smilies) {
						$message = smilies_pass($message);
					}

					$message = str_replace("\n", '<br />', $message);

				}

				$poster = $search_set->user_id !== ANONYMOUS ? '<a href="' . Session::appendSid('profile.php?mode=viewprofile&amp;' . POST_USERS_URL . '=' . $search_set->user_id) . '">' : '';

                if ($search_set->user_id !== ANONYMOUS) {
                    $poster .= $search_set->username;
                } else {
                    if ($search_set->post_username !== '') {
                        $poster .= $search_set->post_username;
                    } else {
                        $poster .= $lang['Guest'];
                    }
                }

				$poster .= $search_set->user_id !== ANONYMOUS ? '</a>' : '';

                if ($userdata['session_logged_in'] && $search_set->post_time > $userdata['user_lastvisit']) {
                    if (!empty($tracking_topics[$topic_id]) && !empty($tracking_forums[$forum_id])) {
                        $topic_last_read = $tracking_topics[$topic_id] > $tracking_forums[$forum_id] ? $tracking_topics[$topic_id] : $tracking_forums[$forum_id];
                    } elseif (!empty($tracking_topics[$topic_id]) || !empty($tracking_forums[$forum_id])) {
                        $topic_last_read = !empty($tracking_topics[$topic_id]) ? $tracking_topics[$topic_id] : $tracking_forums[$forum_id];
                    }

                    if ($search_set->post_time > $topic_last_read) {
                        $mini_post_img = $images['icon_minipost_new'];
                        $mini_post_alt = $lang['New_post'];
                    } else {
                        $mini_post_img = $images['icon_minipost'];
                        $mini_post_alt = $lang['Post'];
                    }
                } else {
                    $mini_post_img = $images['icon_minipost'];
                    $mini_post_alt = $lang['Post'];
                }

                $template->assignBlockVars('searchresults',
                    [
                        'TOPIC_TITLE'   => $topic_title,
                        'FORUM_NAME'    => htmlspecialchars($search_set->forum_name, ENT_QUOTES),
                        'POST_SUBJECT'  => $post_subject,
                        'POST_DATE'     => $post_date,
                        'POSTER_NAME'   => $poster,
                        'TOPIC_REPLIES' => $search_set->topic_replies,
                        'TOPIC_VIEWS'   => $search_set->topic_views,
                        'MESSAGE'       => $message,
                        'MINI_POST_IMG' => $mini_post_img,

                        'L_MINI_POST_ALT' => $mini_post_alt,

                        'U_POST'  => $post_url,
                        'U_TOPIC' => $topic_url,
                        'U_FORUM' => $forum_url
                    ]
                );
            } else {
				$message = '';

                if ($count_orig_word) {
                    $topic_title = preg_replace($orig_word, $replacement_word, $search_set->topic_title);
                }

                $topic_type = $search_set->topic_type;

                if ($topic_type === POST_ANNOUNCE) {
                    $topic_type = $lang['Topic_Announcement'] . ' ';
                } elseif ($topic_type === POST_STICKY) {
                    $topic_type = $lang['Topic_Sticky'] . ' ';
                } else {
                    $topic_type = '';
                }

                if ($search_set->topic_vote) {
                    $topic_type .= $lang['Topic_Poll'] . ' ';
                }

				$views = $search_set->topic_views;
				$replies = $search_set->topic_replies;

				// todo pagination!
                if (($replies + 1) > $board_config['posts_per_page']) {
					$total_pages = ceil( ( $replies + 1 ) / $board_config['posts_per_page'] );
					$goto_page = ' [ <img src="' . $images['icon_gotopost'] . '" alt="' . $lang['Goto_page'] . '" title="' . $lang['Goto_page'] . '" />' . $lang['Goto_page'] . ': ';

					$times = 1;

                    for ($j = 0; $j < $replies + 1; $j += $board_config['posts_per_page']) {
						$goto_page .= '<a href="' . Session::appendSid('viewtopic.php?' . POST_TOPIC_URL . '=' . $topic_id . "&amp;start=$j") . '">' . $times . '</a>';

                        if ($times === 1 && $total_pages > 4) {
                            $goto_page .= ' ... ';
                            $times = $total_pages - 3;
                            $j += ($total_pages - 4) * $board_config['posts_per_page'];
                        } elseif ($times < $total_pages) {
                            $goto_page .= ', ';
                        }

						$times++;
					}

					$goto_page .= ' ] ';
                } else {
                    $goto_page = '';
                }

                if ($search_set->topic_status === TOPIC_MOVED) {
					$topic_type = $lang['Topic_Moved'] . ' ';
					$topic_id = $search_set->topic_moved_id;

					$folder_image = '<img src="' . $images['folder'] . '" alt="' . $lang['No_new_posts'] . '" />';
					$newest_post_img = '';
				} else {
                    if ($search_set->topic_status === TOPIC_LOCKED) {
						$folder = $images['folder_locked'];
						$folder_new = $images['folder_locked_new'];
                    } elseif ($search_set->topic_type === POST_ANNOUNCE) {
						$folder = $images['folder_announce'];
						$folder_new = $images['folder_announce_new'];
                    } elseif ($search_set->topic_type === POST_STICKY) {
						$folder = $images['folder_sticky'];
						$folder_new = $images['folder_sticky_new'];
					} else {
						if ($replies >= $board_config['hot_threshold']) {
							$folder = $images['folder_hot'];
							$folder_new = $images['folder_hot_new'];
						} else {
							$folder = $images['folder'];
							$folder_new = $images['folder_new'];
						}
					}

                    if ($userdata['session_logged_in'] && $search_set->post_time > $userdata['user_lastvisit']) {
                        if (!empty($tracking_topics) || !empty($tracking_forums) || isset($_COOKIE[$forum_all_cookie_name])) {

                            $unread_topics = true;

                            if (!empty($tracking_topics[$topic_id]) && $tracking_topics[$topic_id] > $search_set->post_time) {
                                $unread_topics = false;
                            }

                            if (!empty($tracking_forums[$forum_id]) && $tracking_forums[$forum_id] > $search_set->post_time) {
                                $unread_topics = false;
                            }

                            if (isset($_COOKIE[$forum_all_cookie_name]) && $_COOKIE[$forum_all_cookie_name] > $search_set->post_time) {
                                $unread_topics = false;
                            }

                            if ($unread_topics) {
                                $folder_image = $folder_new;
                                $folder_alt = $lang['New_posts'];

                                $newest_post_img = '<a href="' . Session::appendSid('viewtopic.php?' . POST_TOPIC_URL . "=$topic_id&amp;view=newest") . '"><img src="' . $images['icon_newest_reply'] . '" alt="' . $lang['View_newest_post'] . '" title="' . $lang['View_newest_post'] . '" border="0" /></a> ';
                            } else {
                                $folder_alt = $search_set->topic_status === TOPIC_LOCKED ? $lang['Topic_locked'] : $lang['No_new_posts'];

                                $folder_image = $folder;
                                $newest_post_img = '';
                            }

                        } elseif ($search_set->post_time > $userdata['user_lastvisit']) {
                            $folder_image = $folder_new;
                            $folder_alt = $lang['New_posts'];

                            $newest_post_img = '<a href="' . Session::appendSid('viewtopic.php?' . POST_TOPIC_URL . "=$topic_id&amp;view=newest") . '"><img src="' . $images['icon_newest_reply'] . '" alt="' . $lang['View_newest_post'] . '" title="' . $lang['View_newest_post'] . '" border="0" /></a> ';
                        } else {
                            $folder_image = $folder;
                            $folder_alt = $search_set->topic_status === TOPIC_LOCKED ? $lang['Topic_locked'] : $lang['No_new_posts'];
                            $newest_post_img = '';
                        }
                    } else {
						$folder_image = $folder;
						$folder_alt = $search_set->topic_status === TOPIC_LOCKED ? $lang['Topic_locked'] : $lang['No_new_posts'];
						$newest_post_img = '';
					}
				}

				$topic_author = $search_set->user_id !== ANONYMOUS ? '<a href="' . Session::appendSid('profile.php?mode=viewprofile&amp;' . POST_USERS_URL . '=' . $search_set->user_id) . '">' : '';

                if ($search_set->user_id !== ANONYMOUS) {
                    $topic_author .= $search_set->username;
                } else {
                    if ($search_set->post_username !== '') {
                        $topic_author .= $search_set->post_username;
                    } else {
                        $topic_author .= $lang['Guest'];
                    }
                }

				$topic_author .= $search_set->user_id !== ANONYMOUS ? '</a>' : '';

				$first_post_time = create_date($board_config['default_dateformat'], $search_set->topic_time, $board_config['board_timezone']);
				$last_post_time  = create_date($board_config['default_dateformat'], $search_set->post_time, $board_config['board_timezone']);

                if ($search_set->id2 === ANONYMOUS) {
                    if ($search_set->post_username2 !== '') {
                        $last_post_author = $search_set->post_username2;
                    } else {
                        $last_post_author = $lang['Guest'];
                    }
                } else {
                    $last_post_author = '<a href="' . Session::appendSid('profile.php?mode=viewprofile&amp;' . POST_USERS_URL . '=' . $search_set->id2) . '">' . $search_set->user2 . '</a>';
                }

				$last_post_url = '<a href="' . Session::appendSid('viewtopic.php?' . POST_POST_URL . '=' . $search_set->topic_last_post_id) . '#' . $search_set->topic_last_post_id . '"><img src="' . $images['icon_latest_reply'] . '" alt="' . $lang['View_latest_post'] . '" title="' . $lang['View_latest_post'] . '" border="0" /></a>';

                $template->assignBlockVars('searchresults',
                    [
                        'FORUM_NAME'       => htmlspecialchars($search_set->forum_name, ENT_QUOTES),
                        'FORUM_ID'         => $forum_id,
                        'TOPIC_ID'         => $topic_id,
                        'FOLDER'           => $folder_image,
                        'NEWEST_POST_IMG'  => $newest_post_img,
                        'TOPIC_FOLDER_IMG' => $folder_image,
                        'GOTO_PAGE'        => $goto_page,
                        'REPLIES'          => $replies,
                        'TOPIC_TITLE'      => $topic_title,
                        'TOPIC_TYPE'       => $topic_type,
                        'VIEWS'            => $views,
                        'TOPIC_AUTHOR'     => $topic_author,
                        'FIRST_POST_TIME'  => $first_post_time,
                        'LAST_POST_TIME'   => $last_post_time,
                        'LAST_POST_AUTHOR' => $last_post_author,
                        'LAST_POST_IMG'    => $last_post_url,

                        'L_TOPIC_FOLDER_ALT' => $folder_alt,

                        'U_VIEW_FORUM' => $forum_url,
                        'U_VIEW_TOPIC' => $topic_url
                    ]
                );
            }
		}

		$base_url = "search.php?search_id=$search_id";

		// fixed bug for not working pagination for search author
		if ($search_author) {
            $base_url = 'search.php?search_author='.$search_author.'&show_results='. $show_results;
        }

        $template->assignVars(
            [
                'PAGINATION'  => generate_pagination($base_url, $total_match_count, $per_page, $start),
                'PAGE_NUMBER' => sprintf($lang['Page_of'], floor($start / $per_page) + 1, ceil($total_match_count / $per_page)),

                'L_AUTHOR'   => $lang['Author'],
                'L_MESSAGE'  => $lang['Message'],
                'L_FORUM'    => $lang['Forum'],
                'L_TOPICS'   => $lang['Topics'],
                'L_REPLIES'  => $lang['Replies'],
                'L_VIEWS'    => $lang['Views'],
                'L_POSTS'    => $lang['Posts'],
                'L_LASTPOST' => $lang['Last_Post'],
                'L_POSTED'   => $lang['Posted'],
                'L_SUBJECT'  => $lang['Subject'],

                'L_GOTO_PAGE' => $lang['Goto_page']
            ]
        );

        $template->pparse('body');

        require_once $phpbb_root_path . 'includes' . $sep . 'page_tail.php';
	} else {
		message_die(GENERAL_MESSAGE, $lang['No_search_match']);
	}
}

$template->assignVars(['F_LOGIN_FORM_TOKEN' => CSRF::getInputHtml()]);

//
// Search forum
//
$result = dibi::select(['c.cat_title', 'c.cat_id', 'f.forum_name', 'f.forum_id'])
    ->from(CATEGORIES_TABLE)
    ->as('c')
    ->innerJoin(FORUMS_TABLE)
    ->as('f')
    ->on('f.cat_id = c.cat_id')
    ->orderBy('c.cat_order')
    ->orderBy('f.forum_order')
    ->fetchAll();

$is_auth_ary = Auth::authorize(AUTH_READ, AUTH_LIST_ALL, $userdata);

$s_forums = '';
$list_cat = [];


// TODO use Select.php class
// this is in some functions file now
foreach ($result as $row) {
    if ($is_auth_ary[$row->forum_id]['auth_read']) {
        $s_forums .= '<option value="' . $row->forum_id . '">' . htmlspecialchars($row->forum_name, ENT_QUOTES) . '</option>';

        if (empty($list_cat[$row->cat_id])) {
            $list_cat[$row->cat_id] = $row->cat_title;
        }
    }
}

if (count($list_cat)) {
	$s_forums = '<option value="-1">' . $lang['All_available'] . '</option>' . $s_forums;

	//
	// Category to search
	//
	$s_categories = '<option value="-1">' . $lang['All_available'] . '</option>';

	foreach ($list_cat as $cat_id => $cat_title) {
        $s_categories .= '<option value="' . $cat_id . '">' . htmlspecialchars($cat_title, ENT_QUOTES) . '</option>';
    }
} else {
	message_die(GENERAL_MESSAGE, $lang['No_searchable_forums']);
}

//
// Number of chars returned
//
$s_characters = '<option value="-1">' . $lang['All_available'] . '</option>';
$s_characters .= '<option value="0">0</option>';
$s_characters .= '<option value="25">25</option>';
$s_characters .= '<option value="50">50</option>';

for ($i = 100; $i < 1100; $i += 100) {
	$selected = $i === 200 ? ' selected="selected"' : '';
	$s_characters .= '<option value="' . $i . '"' . $selected . '>' . $i . '</option>';
}

//
// Sorting
//
$sort_by_types = [
    $lang['Sort_Time'],
    $lang['Sort_Post_Subject'],
    $lang['Sort_Topic_Title'],
    $lang['Sort_Author'],
    $lang['Sort_Forum']
];

$s_sort_by = '';

foreach ($sort_by_types as $i => $sort_by_type) {
	$s_sort_by .= '<option value="' . $i . '">' . $sort_by_type . '</option>';
}

//
// Search time
//
$previous_days = [
    0 => $lang['All_Posts'],
    1 => $lang['1_Day'],
    7 => $lang['7_Days'],
    14 => $lang['2_Weeks'],
    30 => $lang['1_Month'],
    90 => $lang['3_Months'],
    180 => $lang['6_Months'],
    364 => $lang['1_Year']
];

$s_time = '';

// todo use Select
foreach ($previous_days as $previous_day_key => $previous_days_value) {
	$selected = $topic_days === $previous_day_key ? ' selected="selected"' : '';

	$s_time .= '<option value="' . $previous_day_key . '"' . $selected . '>' . $previous_days_value . '</option>';
}

//
// Output the basic page
//
$page_title = $lang['Search'];

require_once $phpbb_root_path . 'includes' . $sep . 'page_header.php';

$template->setFileNames(['body' => 'search_body.tpl']);
make_jumpbox('viewforum.php');

$template->assignVars(
    [
        'L_SEARCH_QUERY'            => $lang['Search_query'],
        'L_SEARCH_OPTIONS'          => $lang['Search_options'],
        'L_SEARCH_KEYWORDS'         => $lang['Search_keywords'],
        'L_SEARCH_KEYWORDS_EXPLAIN' => $lang['Search_keywords_explain'],
        'L_SEARCH_AUTHOR'           => $lang['Search_author'],
        'L_SEARCH_AUTHOR_EXPLAIN'   => $lang['Search_author_explain'],
        'L_SEARCH_ANY_TERMS'        => $lang['Search_for_any'],
        'L_SEARCH_ALL_TERMS'        => $lang['Search_for_all'],
        'L_SEARCH_MESSAGE_ONLY'     => $lang['Search_msg_only'],
        'L_SEARCH_MESSAGE_TITLE'    => $lang['Search_title_msg'],
        'L_CATEGORY'                => $lang['Category'],
        'L_RETURN_FIRST'            => $lang['Return_first'],
        'L_CHARACTERS'              => $lang['characters_posts'],
        'L_SORT_BY'                 => $lang['Sort_by'],
        'L_SORT_ASCENDING'          => $lang['Sort_Ascending'],
        'L_SORT_DESCENDING'         => $lang['Sort_Descending'],
        'L_SEARCH_PREVIOUS'         => $lang['Search_previous'],
        'L_DISPLAY_RESULTS'         => $lang['Display_results'],
        'L_FORUM'                   => $lang['Forum'],
        'L_TOPICS'                  => $lang['Topics'],
        'L_POSTS'                   => $lang['Posts'],

        'S_SEARCH_ACTION'     => Session::appendSid('search.php?mode=results'),
        'S_CHARACTER_OPTIONS' => $s_characters,
        'S_FORUM_OPTIONS'     => $s_forums,
        'S_CATEGORY_OPTIONS'  => $s_categories,
        'S_TIME_OPTIONS'      => $s_time,
        'S_SORT_OPTIONS'      => $s_sort_by,
        'S_HIDDEN_FIELDS'     => ''
    ]
);

$template->pparse('body');

require_once $phpbb_root_path . 'includes' . $sep . 'page_tail.php';

?>