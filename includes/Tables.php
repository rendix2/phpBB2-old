<?php

/**
 * Class Tables
 */
class Tables
{
    /**
     * @var string
     */
    CONST PREFIX = Config::TABLE_PREFIX;

    /**
     * @var string
     */
    const AUTH_ACCESS_TABLE = self::PREFIX . 'auth_access';

    /**
     * @var string
     */
    const BAN_LIST_TABLE = self::PREFIX . 'banlist';

    /**
     * @var string
     */
    const CATEGORIES_TABLE = self::PREFIX . 'categories';

    /**
     * @var string
     */
    const CONFIG_TABLE = self::PREFIX . 'config';

    /**
     * @var string
     */
    const CONFIRM_TABLE = self::PREFIX . 'confirm';

    /**
     * @var string
     */
    const DISS_ALLOW_TABLE = self::PREFIX . 'disallow';

    /**
     * @var string
     */
    const FORUMS_TABLE = self::PREFIX . 'forums';

    /**
     * @var string
     */
    const GROUPS_TABLE = self::PREFIX . 'groups';

    /**
     * @var string
     */
    const POSTS_TABLE = self::PREFIX . 'posts';

    /**
     * @var string
     */
    const POSTS_TEXT_TABLE = self::PREFIX . 'posts_text';

    /**
     * @var string
     */
    const PRIVATE_MESSAGE_TABLE = self::PREFIX . 'privmsgs';

    /**
     * @var string
     */
    const PRIVATE_MESSAGE_TEXT_TABLE = self::PREFIX . 'privmsgs_text';

    /**
     * @var string
     */
    const LANGUAGES_TABLE = self::PREFIX . 'languages';

    /**
     * @var string
     */
    const PRUNE_TABLE = self::PREFIX . 'forum_prune';

    /**
     * @var string
     */
    const RANKS_TABLE = self::PREFIX . 'ranks';

    /**
     * @var string
     */
    const SEARCH_TABLE = self::PREFIX . 'search_results';

    /**
     * @var string
     */
    const SEARCH_WORD_TABLE = self::PREFIX . 'search_wordlist';

    /**
     * @var string
     */
    const SEARCH_MATCH_TABLE = self::PREFIX . 'search_wordmatch';

    /**
     * @var string
     */
    const SESSIONS_TABLE = self::PREFIX . 'sessions';

    /**
     * @var string
     */
    const SESSIONS_AUTO_LOGIN_KEYS_TABLE = self::PREFIX . 'sessions_keys';

    /**
     * @var string
     */
    const SMILEYS_TABLE = self::PREFIX . 'smilies';

    /**
     * @var string
     */
    const TEMPLATE_CACHE_TABLE = self::PREFIX . 'template_cache';

    /**
     * @var string
     */
    const THEMES_TABLE = self::PREFIX . 'themes';

    /**
     * @var string
     */
    const THEMES_NAME_TABLE = self::PREFIX . 'themes_name';

    /**
     * @var string
     */
    const TOPICS_TABLE = self::PREFIX . 'topics';

    /**
     * @var string
     */
    const TOPICS_WATCH_TABLE = self::PREFIX . 'topics_watch';

    /**
     * @var string
     */
    const USERS_TABLE = self::PREFIX . 'users';

    /**
     * @var string
     */
    const USERS_GROUPS_TABLE = self::PREFIX . 'user_group';

    /**
     * @var string
     */
    const WORDS_TABLE = self::PREFIX . 'words';

    /**
     * @var string
     */
    const VOTE_DESC_TABLE = self::PREFIX  . 'vote_desc';

    /**
     * @var string
     */
    const VOTE_RESULTS_TABLE = self::PREFIX . 'vote_results';

    /**
     * @var string
     */
    const VOTE_USERS_TABLE = self::PREFIX . 'vote_voters';
}