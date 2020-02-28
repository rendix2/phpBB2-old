<?php

/**
 * Class SearchHelper
 *
 * @author rendix2
 */
class SearchHelper
{
    /**
     * @param string $mode
     * @param string $entry
     * @param array  $stopWords
     * @param array  $synonyms
     *
     * @return string|string[]|null
     */
    public static function cleanWords($mode, &$entry, array &$stopWords, array &$synonyms)
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

        $entry = ' ' . strip_tags(mb_strtolower($entry)) . ' ';

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

        if (!empty($stopWords)) {
            foreach ($stopWords as &$stopWord) {
                $stopWord = trim($stopWord);

                if ($mode === 'post' || ( $stopWord !== 'not' && $stopWord !== 'and' && $stopWord !== 'or' )) {
                    $entry = str_replace(' ' . trim($stopWord) . ' ', ' ', $entry);
                }
            }
        }

        if (!empty($synonyms)) {
            foreach ($synonyms as &$synonym) {
                list($replace_synonym, $match_synonym) = explode(' ', mb_strtolower(trim($synonym)));

                if ($mode === 'post' || ( $match_synonym !== 'not' && $match_synonym !== 'and' && $match_synonym !== 'or' )) {
                    $entry =  str_replace(' ' . trim($match_synonym) . ' ', ' ' . trim($replace_synonym) . ' ', $entry);
                }
            }
        }

        return $entry;
    }

    /**
     * @param string $entry
     * @param string $mode
     *
     * @return array
     */
    public static function splitWords($entry, $mode = 'post')
    {
        // If you experience problems with the new method, uncomment this block.
        /*
            $rex = $mode === 'post' ? "/\b([\w��-�][\w��-�']*[\w��-�]+|[\w��-�]+?)\b/" : '/(\*?[a-z0-9��-�]+\*?)|\b([a-z0-9��-�]+)\b/';
            preg_match_all($rex, $entry, $split_entries);

            return $split_entries[1];
        */
        // Trim 1+ spaces to one space and split this trimmed string into words.
        return explode(' ', trim(preg_replace('#\s+#', ' ', $entry)));
    }

    /**
     * @param string $mode
     * @param int    $postId
     * @param string $post_text
     * @param string $post_title
     *
     * @throws \Dibi\Exception
     */
    public static function addSearchWords($mode, $postId, $post_text, $post_title = '')
    {
        global $phpbb_root_path, $board_config;

        $sep = DIRECTORY_SEPARATOR;

        $stopWords = @file($phpbb_root_path . 'language' . $sep . 'lang_' . $board_config['default_lang'] . $sep . 'search_stopwords.txt');
        $synonyms = @file($phpbb_root_path . 'language' . $sep . 'lang_' . $board_config['default_lang'] . $sep . 'search_synonyms.txt');

        $search_raw_words = [];
        $search_raw_words['text'] = self::splitWords(self::cleanWords('post', $post_text, $stopWords, $synonyms));
        $search_raw_words['title'] = self::splitWords(self::cleanWords('post', $post_title, $stopWords, $synonyms));

        @set_time_limit(0);

        $words = [];
        $word_insert_sql = [];

        foreach ($search_raw_words as $word_in => $search_matches) {
            $word_insert_sql[$word_in] = [];

            if (!empty($search_matches)) {
                foreach ($search_matches as $searchMatch) {
                    $searchMatch = trim($searchMatch);

                    if ($searchMatch !== '') {
                        $words[] = $searchMatch;

                        if (!in_array($searchMatch, $word_insert_sql[$word_in], true)) {
                            $word_insert_sql[$word_in][] = $searchMatch;
                        }
                    }
                }
            }
        }

        if (count($words)) {
            sort($words);

            $words = array_unique($words);
            $checkWords = [];

            switch (Config::DBMS) {
                case 'postgresql':
                case 'msaccess':
                case 'mssql-odbc':
                case 'oracle':
                case 'db2':
                    $checkWords = dibi::select(['word_id', 'word_text'])
                        ->from(Tables::SEARCH_WORD_TABLE)
                        ->where('word_text IN %in', $words)
                        ->fetchPairs('word_text', 'word_id');
                    break;
            }

            foreach ($words as $word) {
                if (!isset($checkWords[$word])) {
                    switch (Config::DBMS) {
                        case 'mysql':
                            $insertData = [
                                'word_text' => $word,
                                'word_common' => 0
                            ];

                            dibi::insert(Tables::SEARCH_WORD_TABLE, $insertData)->setFlag('IGNORE')->execute();
                            break;
                        case 'mssql':
                        case 'mssql-odbc':
                        default:
                            $insertData = [
                                'word_text' => $word,
                                'word_common' => 0
                            ];

                            dibi::insert(Tables::SEARCH_WORD_TABLE, $insertData)->execute();
                            break;
                    }
                }
            }
        }

        foreach ($word_insert_sql as $word_in => $matchQql) {
            $titleMatch = $word_in === 'title' ? 1 : 0;

            if (count($matchQql)) {
                dibi::query('INSERT INTO %n (post_id, word_id, title_match) 
                                SELECT %i, word_id, %i
                                FROM %n
                                WHERE word_text IN %in',
                    Tables::SEARCH_MATCH_TABLE,
                    $postId,
                    $titleMatch,
                    Tables::SEARCH_WORD_TABLE,
                    $matchQql
                );
            }
        }

        if ($mode === 'single') {
            self::removeCommon('single', 4/10, $words);
        }
    }

    /**
     * Check if specified words are too common now
     *
     * @param string $mode
     * @param float  $fraction
     * @param array  $word_id_list
     *
     * @throws \Dibi\Exception
     */
    public static function removeCommon($mode, $fraction, $word_id_list = [])
    {
        $totalPosts = dibi::select('COUNT(post_id)')
            ->as('total_posts')
            ->from(Tables::POSTS_TABLE)
            ->fetchSingle();

        if ($totalPosts === false) {
            message_die(GENERAL_ERROR, 'Could not obtain post count');
        }

        if ($totalPosts >= 100) {
            $common_threshold = floor($totalPosts * $fraction);

            if ($mode === 'single' && count($word_id_list)) {
                $wordIds = dibi::select('m.word_id')
                    ->from(Tables::SEARCH_MATCH_TABLE)
                    ->as('m')
                    ->innerJoin('SEARCH_WORD_TABLE')
                    ->as('w')
                    ->on('m.word_id = w.word_id')
                    ->where('w.word_text IN %in', $word_id_list)
                    ->groupBy('m.word_id')
                    ->having('COUNT(m.word_id) > %i', $common_threshold)
                    ->fetchPairs(null, 'word_id');
            } else {
                $wordIds = dibi::select('word_id')
                    ->from(Tables::SEARCH_MATCH_TABLE)
                    ->groupBy('word_id')
                    ->having('COUNT(word_id) > %i', $common_threshold)
                    ->fetchPairs(null, 'word_id');
            }

            if (count($wordIds)) {
                dibi::update(Tables::SEARCH_WORD_TABLE, ['word_common' => 1])
                    ->where('word_id IN %in', $wordIds)
                    ->execute();

                dibi::delete(Tables::SEARCH_MATCH_TABLE)
                    ->where('word_id IN %in', $wordIds)
                    ->execute();
            }
        }
    }

    /**
     * @param array $post_ids
     *
     * @return int
     */
    public static function removeSearchPost(array $post_ids)
    {
        $wordsRemoved = false;

        if (Config::DBMS === 'mysql') {
            $words = dibi::select('word_id')
                ->from(Tables::SEARCH_MATCH_TABLE)
                ->where('post_id IN %in', $post_ids)
                ->groupBy('word_id')
                ->fetchPairs(null, 'word_id');

            if (count($words)) {
                $wordsMatch = dibi::select('word_id')
                    ->from(Tables::SEARCH_MATCH_TABLE)
                    ->where('word_id IN %in', $words)
                    ->groupBy('word_id')
                    ->having('COUNT(word_id) = 1')
                    ->fetchPairs(null, 'word_id');

                if (count($wordsMatch)) {
                    $wordsRemoved = dibi::delete(Tables::SEARCH_WORD_TABLE)
                        ->where('word_id IN %in', $wordsMatch)
                        ->execute(dibi::AFFECTED_ROWS);
                }
            }
        } else {
            $wordsRemoved = dibi::delete(Tables::SEARCH_WORD_TABLE)
                ->where('word_id IN',

                    dibi::select('word_id')
                        ->from(Tables::SEARCH_MATCH_TABLE)
                        ->where('word_id IN',

                            dibi::select('word_id')
                                ->from(Tables::SEARCH_MATCH_TABLE)
                                ->where('post_id IN', $post_ids))
                        ->groupBy('word_id')
                        ->having('COUNT(word_id) = %i', 1))
                ->execute(dibi::AFFECTED_ROWS);
        }

        dibi::delete(Tables::SEARCH_MATCH_TABLE)
            ->where('post_id IN %in', $post_ids)
            ->execute();

        return $wordsRemoved;
    }

    /**
     * Username search
     *
     * @param string $search_match
     */
    public static function usernameSearch($search_match)
    {
        /**
         * @var BaseTemplate $template
         */
        global $template;

        global $board_config, $lang, $images, $theme, $phpbb_root_path;
        global $gen_simple_header;
        global $userdata;

        $gen_simple_header = true;
        $userNamesList = '';

        if (!empty($search_match)) {
            $username_search = preg_replace('/\*/', '%', phpbb_clean_username($search_match));

            $userNames = dibi::select('username')
                ->from(Tables::USERS_TABLE)
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

        PageHelper::header($template, $userdata, $board_config, $lang, $images,  $theme, $lang['Search'], $gen_simple_header);

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

        PageHelper::footer($template, $userdata, $lang, $gen_simple_header);
    }
}
