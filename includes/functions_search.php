<?php
/***************************************************************************
*                              functions_search.php
*                              -------------------
*     begin                : Wed Sep 05 2001
*     copyright            : (C) 2002 The phpBB Group
*     email                : support@phpbb.com
*
*     $Id: functions_search.php 5204 2005-09-14 18:14:30Z acydburn $
*
****************************************************************************/

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 ***************************************************************************/

function clean_words($mode, &$entry, array &$stopwords, array &$synonyms)
{
    static $drop_char_matches = [
        '^'  => ' ',
        '$'  => ' ',
        '&'  => ' ',
        '('  => ' ',
        ')'  => ' ',
        '<'  => ' ',
        '>'  => ' ',
        '`'  => '',
        '\'' => '',
        '"'  => ' ',
        '|'  => ' ',
        ','  => ' ',
        '@'  => ' ',
        '_'  => '',
        '?'  => ' ',
        '%'  => ' ',
        '-'  => '',
        '~'  => ' ',
        '+'  => ' ',
        '.'  => ' ',
        '['  => ' ',
        ']'  => ' ',
        '{'  => ' ',
        '}'  => ' ',
        ':'  => ' ',
        '\\' => ' ',
        '/'  => ' ',
        '='  => ' ',
        '#'  => ' ',
        '\'' => ' ',
        ';'  => ' ',
        '!'  => ' '
    ];

	$entry = ' ' . strip_tags(strtolower($entry)) . ' ';

	if ($mode === 'post') {
		// Replace line endings by a space
		$entry = preg_replace('/[\n\r]/is', ' ', $entry);
		// HTML entities like &nbsp;
		$entry = preg_replace('/\b&[a-z]+;\b/', ' ', $entry);
		// Remove URL's
		$entry = preg_replace('/\b[a-z0-9]+:\/\/[a-z0-9\.\-]+(\/[a-z0-9\?\.%_\-\+=&\/]+)?/', ' ', $entry);
		// Quickly remove BBcode.
		$entry = preg_replace('/\[img:[a-z0-9]{10,}\].*?\[\/img:[a-z0-9]{10,}\]/', ' ', $entry);
		$entry = preg_replace('/\[\/?url(=.*?)?\]/', ' ', $entry);
		$entry = preg_replace('/\[\/?[a-z\*=\+\-]+(\:?[0-9a-z]+)?:[a-z0-9]{10,}(\:[a-z0-9]+)?=?.*?\]/', ' ', $entry);
	} elseif ($mode === 'search') {
		$entry = str_replace(' +', ' and ', $entry);
		$entry = str_replace(' -', ' not ', $entry);
	}

	//
	// Filter out strange characters like ^, $, &, change "it's" to "its"
	//
	foreach ($drop_char_matches as $key => $value) {
		$entry = str_replace($key, $value, $entry);
	}

    if ($mode === 'post') {
		$entry = str_replace('*', ' ', $entry);

		// 'words' that consist of <3 or >20 characters are removed.
		$entry = preg_replace('/[ ]([\S]{1,2}|[\S]{21,})[ ]/',' ', $entry);
	}

    if (!empty($stopwords)) {
		foreach ($stopwords as &$stopword) {
			$stopword = trim($stopword);

			if ($mode === 'post' || ( $stopword !== 'not' && $stopword !== 'and' && $stopword !== 'or' )) {
				$entry = str_replace(' ' . trim($stopword) . ' ', ' ', $entry);
			}
		}
	}

    if (!empty($synonyms)) {
		foreach ($synonyms as &$synonym) {
			list($replace_synonym, $match_synonym) = explode(' ', strtolower(trim($synonym)));

			if ($mode === 'post' || ( $match_synonym !== 'not' && $match_synonym !== 'and' && $match_synonym !== 'or' )) {
				$entry =  str_replace(' ' . trim($match_synonym) . ' ', ' ' . trim($replace_synonym) . ' ', $entry);
			}
		}
	}

	return $entry;
}

function split_words($entry, $mode = 'post')
{
	// If you experience problems with the new method, uncomment this block.
/*
	$rex = ( $mode == 'post' ) ? "/\b([\w��-�][\w��-�']*[\w��-�]+|[\w��-�]+?)\b/" : '/(\*?[a-z0-9��-�]+\*?)|\b([a-z0-9��-�]+)\b/';
	preg_match_all($rex, $entry, $split_entries);

	return $split_entries[1];
*/
	// Trim 1+ spaces to one space and split this trimmed string into words.
	return explode(' ', trim(preg_replace('#\s+#', ' ', $entry)));
}

function add_search_words($mode, $post_id, $post_text, $post_title = '')
{
	global $phpbb_root_path, $board_config, $lang, $dbms;

	$stopword_array = @file($phpbb_root_path . 'language/lang_' . $board_config['default_lang'] . '/search_stopwords.txt');
	$synonym_array = @file($phpbb_root_path . 'language/lang_' . $board_config['default_lang'] . '/search_synonyms.txt');

	$search_raw_words = [];
	$search_raw_words['text'] = split_words(clean_words('post', $post_text, $stopword_array, $synonym_array));
	$search_raw_words['title'] = split_words(clean_words('post', $post_title, $stopword_array, $synonym_array));

	@set_time_limit(0);

	$words = [];
	$word_insert_sql = [];

    foreach ($search_raw_words as $word_in => $search_matches) {
		$word_insert_sql[$word_in] = [];

        if (!empty($search_matches)) {
            foreach ($search_matches as $search_match) {
                $search_match = trim($search_match);

				if ($search_match !== '') {
					$words[] = $search_match;

                    if (!in_array($search_match, $word_insert_sql[$word_in], true)) {
						$word_insert_sql[$word_in][] = $search_match;
					}
				}
			}
		}
	}

    if (count($words)) {
		sort($words);

		$words = array_unique($words);
		$check_words = [];

        switch ($dbms) {
            case 'postgresql':
            case 'msaccess':
            case 'mssql-odbc':
            case 'oracle':
            case 'db2':
                $check_words = dibi::select(['word_id', 'word_text'])
                    ->from(SEARCH_WORD_TABLE)
                    ->where('word_text IN %in', $words)
                    ->fetchPairs('word_text', 'word_id');
                break;
        }

        foreach ($words as $word) {
            if (!isset($check_words[$word])) {
                switch ($dbms) {
                    case 'mysql':
                        $insert_data = [
                            'word_text' => $word,
                            'word_common' => 0
                        ];

                        dibi::insert(SEARCH_WORD_TABLE, $insert_data)->setFlag('IGNORE')->execute();
                        break;
                    case 'mssql':
                    case 'mssql-odbc':
                    default:
                        $insert_data = [
                            'word_text' => $word,
                            'word_common' => 0
                        ];

                        dibi::insert(SEARCH_WORD_TABLE, $insert_data)->execute();
                        break;
                }
            }
		}
	}

	foreach ($word_insert_sql as $word_in => $match_sql) {
		$title_match = $word_in === 'title' ? 1 : 0;

        if (count($match_sql)) {
		    dibi::query('INSERT INTO %n (post_id, word_id, title_match) 
                                SELECT %i, word_id, %i
                                FROM %n
                                WHERE word_text IN %in',
                SEARCH_MATCH_TABLE,
                $post_id,
                $title_match,
                SEARCH_WORD_TABLE,
                $match_sql
            );
		}
	}

	if ($mode === 'single') {
		remove_common('single', 4/10, $words);
	}
}

//
// Check if specified words are too common now
//
function remove_common($mode, $fraction, $word_id_list = [])
{
	$total_posts = dibi::select('COUNT(post_id)')
        ->as('total_posts')
        ->from(POSTS_TABLE)
        ->fetchSingle();

    if ($total_posts === false) {
        message_die(GENERAL_ERROR, 'Could not obtain post count');
    }

    if ($total_posts >= 100) {
		$common_threshold = floor($total_posts * $fraction);

        if ($mode === 'single' && count($word_id_list)) {
            $word_ids = dibi::select('m.word_id')
                ->from(SEARCH_MATCH_TABLE)
                ->as('m')
                ->innerJoin('SEARCH_WORD_TABLE')
                ->as('w')
                ->on('m.word_id = w.word_id')
                ->where('w.word_text IN %in', $word_id_list)
                ->groupBy('m.word_id')
                ->having('COUNT(m.word_id) > %i', $common_threshold)
                ->fetchPairs(null, 'word_id');
		} else {
		    $word_ids = dibi::select('word_id')
                ->from(SEARCH_MATCH_TABLE)
                ->groupBy('word_id')
                ->having('COUNT(word_id) > %i', $common_threshold)
                ->fetchPairs(null, 'word_id');
		}

        if (count($word_ids)) {
		    dibi::update(SEARCH_WORD_TABLE, ['word_common' => 1])
                ->where('word_id IN %in', $word_ids)
                ->execute();

		    dibi::delete(SEARCH_MATCH_TABLE)
                ->where('word_id IN %in', $word_ids)
                ->execute();
		}
	}
}

/**
 * @param array $post_ids
 *
 * @return int
 */
function remove_search_post(array $post_ids)
{
    global $dbms;

	$words_removed = false;

    if ($dbms === 'mysql') {
        $words = dibi::select('word_id')
            ->from(SEARCH_MATCH_TABLE)
            ->where('post_id IN %in', $post_ids)
            ->groupBy('word_id')
            ->fetchPairs(null, 'word_id');

        if (count($words)) {
            $words_match = dibi::select('word_id')
                ->from(SEARCH_MATCH_TABLE)
                ->where('word_id IN %in', $words)
                ->groupBy('word_id')
                ->having('COUNT(word_id) = 1')
                ->fetchPairs(null, 'word_id');

            if (count($words_match)) {
                $words_removed = dibi::delete(SEARCH_WORD_TABLE)
                    ->where('word_id IN %in', $words_match)
                    ->execute(dibi::AFFECTED_ROWS);
            }
        }
    } else {
        $words_removed = dibi::delete(SEARCH_WORD_TABLE)
            ->where('word_id IN',

                dibi::select('word_id')
                    ->from(SEARCH_MATCH_TABLE)
                    ->where('word_id IN',

                        dibi::select('word_id')
                            ->from(SEARCH_MATCH_TABLE)
                            ->where('post_id IN', $post_ids))
                    ->groupBy('word_id')
                    ->having('COUNT(word_id) = %i', 1))
            ->execute(dibi::AFFECTED_ROWS);
    }

	dibi::delete(SEARCH_MATCH_TABLE)
        ->where('post_id IN %in', $post_ids)
        ->execute();

	return $words_removed;
}

//
// Username search
//
function username_search($search_match)
{
    /**
     * @var Template $template
     */
    global $template;

	global $board_config, $lang, $images, $theme, $phpbb_root_path;
	global $gen_simple_header;

	$gen_simple_header = true;

	$userNamesList = '';

    if (!empty($search_match)) {
		$username_search = preg_replace('/\*/', '%', phpbb_clean_username($search_match));

		$userNames = dibi::select('username')
            ->from(USERS_TABLE)
            ->where('username LIKE %~like~', $username_search)
            ->where('user_id <> %i', ANONYMOUS)
            ->orderBy('username')
            ->fetchPairs(null, 'username');

		if (count($userNames)) {
		    foreach ($userNames as $userName) {
                $userNamesList .= '<option value="' . $userName . '">' . htmlspecialchars($userName, ENT_QUOTES) . '</option>';
            }
        } else {
            $userNamesList .= '<option value="-1">' . $lang['No_match']. '</option>';
        }
	}

	$page_title = $lang['Search'];

    require_once $phpbb_root_path . 'includes/page_header.php';

    $template->setFileNames(['search_user_body' => 'search_username.tpl']);

    $template->assignVars(
        [
            'USERNAME' => !empty($search_match) ? phpbb_clean_username($search_match) : '',

            'F_LOGIN_FORM_TOKEN' => CSRF::getInputHtml(),

            'L_CLOSE_WINDOW'    => $lang['Close_window'],
            'L_SEARCH_USERNAME' => $lang['Find_username'],
            'L_UPDATE_USERNAME' => $lang['Select_username'],
            'L_SELECT'          => $lang['Select'],
            'L_SEARCH'          => $lang['Search'],
            'L_SEARCH_EXPLAIN'  => $lang['Search_author_explain'],

            'S_USERNAME_OPTIONS' => $userNamesList,
            'S_SEARCH_ACTION'    => Session::appendSid('search.php?mode=searchuser')
        ]
    );

    if ($userNamesList !== '') {
		$template->assignBlockVars('switch_select_name', []);
	}

	$template->pparse('search_user_body');

    require_once $phpbb_root_path . 'includes/page_tail.php';
}

?>