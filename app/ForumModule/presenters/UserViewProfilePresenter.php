<?php
/***************************************************************************
 *                           UserViewProfilePresenter.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: UserViewProfilePresenter.php 5204 2005-09-14 18:14:30Z acydburn $
 *
 *
 ***************************************************************************/

namespace phpBB2\App\ForumModule\Presenters;

use DateTime;
use DateTimeZone;
use dibi;
use LatteFactory;
use Nette\Caching\Cache;
use Nette\Caching\IStorage;
use phpBB2\Models\ThanksManager;
use phpBB2\Models\UsersManager;
use Session;
use Tables;

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 *
 ***************************************************************************/

class UserViewProfilePresenter
{
    /**
     * @var UsersManager $usersManager
     */
    private $usersManager;

    /**
     * @var ThanksManager $thanksManager
     */
    private $thanksManager;

    /**
     * @var Cache $cache
     */
    private $cache;

    /**
     * @var LatteFactory $latte
     */
    private $latte;

    /**
     * @var array $lang
     */
    private $lang;

    private $userData;

    private $attachConfig;

    private $theme;

    /**
     * UserViewProfilePresenter constructor.
     *
     * @param UsersManager $usersManager
     * @param ThanksManager $thanksManager
     * @param IStorage $storage
     * @param $userData
     * @param $lang
     */
    public function __construct(
        UsersManager $usersManager,
        ThanksManager $thanksManager,
        IStorage $storage,
        $userData,
        $lang,
        $attachConfig,
        $boardConfig,
        $theme
    ) {
        $this->usersManager = $usersManager;
        $this->thanksManager = $thanksManager;
        $this->cache = new Cache($storage, Tables::RANKS_TABLE);
        $this->latte = new LatteFactory($storage, $userData);
        $this->userData = $userData;
        $this->lang = $lang;
        $this->attachConfig = $attachConfig;
        $this->boardConfig = $boardConfig;
        $this->theme = $theme;
    }

    /**
     * @param int $id
     */
    public function renderDefault($id)
    {
        global $template;
        global $board_config;
        global $theme;
        global $images;
        global $gen_simple_header;

        if (!isset($_GET[POST_USERS_URL]) || !is_numeric($_GET[POST_USERS_URL]) || $_GET[POST_USERS_URL] === ANONYMOUS) {
            message_die(GENERAL_MESSAGE, $this->lang['No_user_id_specified']);
        }

        $profileData = $this->usersManager->getByPrimaryKey($_GET[POST_USERS_URL]);

        if (!$profileData) {
            message_die(GENERAL_MESSAGE, $this->lang['No_user_id_specified']);
        }

        $key   = Tables::RANKS_TABLE . '_ordered_by_rank_special_rank_min';

        $cachedRanks = $this->cache->load($key);

        if ($cachedRanks !== null) {
            $ranks = $cachedRanks;
        } else {
            $ranks = dibi::select('*')
                ->from(Tables::RANKS_TABLE)
                ->orderBy('rank_special')
                ->orderBy('rank_min')
                ->fetchAll();

            $this->cache->save($key, $ranks);
        }


        //
        // Calculate the number of days this user has been a member ($memberdays)
        // Then calculate their posts per day
        //
        $userTimezone = isset($profileData->user_timezone) ? $profileData->user_timezone : $board_config['board_timezone'];

        $zone = new DateTimeZone($userTimezone);

        $regdate = new DateTime();
        $regdate->setTimezone($zone);
        $regdate->setTimestamp($profileData->user_reg_date);

        $memberdays = new DateTime('now', $zone);
        $memberdays = $memberdays->diff($regdate)->days;

        $postsPerDay  = $profileData->user_posts / $memberdays;
        $topicsPerDay = $profileData->user_topics / $memberdays;
        $thanksPerDay = $profileData->user_thanks / $memberdays;
        $topicWatchesPerDay = $profileData->user_topic_watches / $memberdays;

        // Get the users percentage of total posts
        if ($profileData->user_posts !== 0) {
            $totalPosts      = get_db_stat('postcount');
            $percentagePosts = $totalPosts ? min(100, ($profileData->user_posts / $totalPosts) * 100) : 0;
        } else {
            $percentagePosts = 0;
        }

        // Get the users percentage of total topics
        if ($profileData->user_topics !== 0) {
            $totalTopics = get_db_stat('topiccount');
            $percentageTopics= $totalTopics ? min(100, ($profileData->user_topics / $totalTopics) * 100) : 0;
        } else {
            $percentageTopics = 0;
        }

        // Get the users percentage of total thanks
        if ($profileData->user_thanks !== 0) {
            $thanksCount = $this->thanksManager->getAllCount();

            $percentageThanks = $thanksCount ? min(100, ($profileData->user_thanks / $thanksCount) * 100) : 0;
        } else {
            $percentageThanks = 0;
        }

        // Get the users percentage of total thanks
        if ($profileData->user_topic_watches !== 0) {
            $topicsWatchesCount = dibi::select('COUNT(*)')
                ->as('count')
                ->from(Tables::TOPICS_WATCH_TABLE)
                ->fetchSingle();

            $percentageTopicsWatches = $topicsWatchesCount ? min(100, ($profileData->user_topic_watches / $topicsWatchesCount) * 100) : 0;
        } else {
            $percentageTopicsWatches = 0;
        }

        $avatarImage = '';

        if ($profileData->user_avatar_type && $profileData->user_allow_avatar) {
            switch ($profileData->user_avatar_type) {
                case USER_AVATAR_UPLOAD:
                    $avatarImage = $board_config['allow_avatar_upload'] ? '<img src="' . $board_config['avatar_path'] . '/' . $profileData->user_avatar . '" alt="" border="0" />' : '';
                    break;
                case USER_AVATAR_REMOTE:
                    $avatarImage = $board_config['allow_avatar_remote'] ? '<img src="' . $profileData->user_avatar . '" alt="" border="0" />' : '';
                    break;
                case USER_AVATAR_GALLERY:
                    $avatarImage = $board_config['allow_avatar_local'] ? '<img src="' . $board_config['avatar_gallery_path'] . '/' . $profileData->user_avatar . '" alt="" border="0" />' : '';
                    break;
            }
        }

        $posterRank = '';
        $rankImage  = '';

        if ($profileData->user_rank) {
            foreach ($ranks as $rank) {
                if ($profileData->user_rank === $rank->rank_id && $rank->rank_special) {
                    $posterRank = $rank->rank_title;
                    $rankImage  = $rank->rank_image ? '<img src="' . $rank->rank_image . '" alt="' . $posterRank . '" title="' . $posterRank . '" border="0" /><br />' : '';
                }
            }
        } else {
            foreach ($ranks as $rank) {
                if ($profileData->user_posts >= $rank->rank_min && !$rank->rank_special) {
                    $posterRank = $rank->rank_title;
                    $rankImage  = $rank->rank_image ? '<img src="' . $rank->rank_image . '" alt="' . $posterRank . '" title="' . $posterRank . '" border="0" /><br />' : '';
                }
            }
        }

        $temp_url = Session::appendSid('privmsg.php?mode=post&amp;' . POST_USERS_URL . '=' . $profileData->user_id);
        $pm_img = '<a href="' . $temp_url . '"><img src="' . $images['icon_pm'] . '" alt="' . $this->lang['Send_private_message'] . '" title="' . $this->lang['Send_private_message'] . '" border="0" /></a>';
        $pm = '<a href="' . $temp_url . '">' . $this->lang['Send_private_message'] . '</a>';

        if ($board_config['board_email_form'] || $userdata['user_level'] === ADMIN) {
            $email_uri = Session::appendSid('profile.php?mode=email&amp;' . POST_USERS_URL .'=' . $profileData->user_id);

            $emailImage = '<a href="' . $email_uri . '"><img src="' . $images['icon_email'] . '" alt="' . $this->lang['Send_email'] . '" title="' . $this->lang['Send_email'] . '" border="0" /></a>';
            $email      = '<a href="' . $email_uri . '">' . $this->lang['Send_email'] . '</a>';
        } else {
            $emailImage = '&nbsp;';
            $email      = '&nbsp;';
        }

        $www_img = $profileData->user_website ? '<a href="' . $profileData->user_website . '" target="_userwww"><img src="' . $images['icon_www'] . '" alt="' . $this->lang['Visit_website'] . '" title="' . $this->lang['Visit_website'] . '" border="0" /></a>' : '&nbsp;';
        $www     = $profileData->user_website ? '<a href="' . $profileData->user_website . '" target="_userwww">' . $profileData->user_website . '</a>' : '&nbsp;';

        $temp_url    = Session::appendSid('search.php?search_author=' . urlencode($profileData->username) . '&amp;show_results=posts');
        $searchImage = '<a href="' . $temp_url . '"><img src="' . $images['icon_search'] . '" alt="' . sprintf($this->lang['Search_user_posts'], $profileData->username) . '" title="' . sprintf($this->lang['Search_user_posts'], $profileData->username) . '" border="0" /></a>';
        $search      = '<a href="' . $temp_url . '">' . sprintf($this->lang['Search_user_posts'], $profileData->username) . '</a>';

        // <!-- BEGIN Another Online/Offline indicator -->
        if ((!$profileData->user_allow_view_online && $userdata['user_level'] === ADMIN) || $profileData->user_allow_view_online) {
            $expiry_time = time() - ONLINE_TIME_DIFF;

            if ($profileData->user_session_time >= $expiry_time) {
                $user_onlinestatus = '<img src="' . $images['Online'] . '" alt="' . $this->lang['Online'] . '" title="' . $this->lang['Online'] . '" border="0" />';

                if (!$profileData->user_allow_view_online && $userdata['user_level'] === ADMIN) {
                    $user_onlinestatus = '<img src="' . $images['Hidden_Admin'] . '" alt="' . $this->lang['Hidden'] . '" title="' . $this->lang['Hidden'] . '" border="0" align="middle" />';
                }
            } else {
                $user_onlinestatus = '<img src="' . $images['Offline'] . '" alt="' . $this->lang['Offline'] . '" title="' . $this->lang['Offline'] . '" border="0" />';

                if (!$profileData->user_allow_view_online && $userdata['user_level'] === ADMIN) {
                    $user_onlinestatus = '<img src="' . $images['Offline'] . '" alt="' . $this->lang['Hidden'] . '" title="' . $this->lang['Hidden'] . '" border="0" />';
                }
            }
        } else {
            $user_onlinestatus = '<img src="' . $images['Offline'] . '" alt="' . $this->lang['Offline'] . '" title="' . $this->lang['Offline'] . '" border="0" />';
        }
        // <!-- END Another Online/Offline indicator -->

        $attachParameters = display_upload_attach_box_limits($profileData->user_id);

        if (function_exists('get_html_translation_table')) {
            $u_search_author = urlencode(strtr($profileData->username, array_flip(get_html_translation_table(HTML_ENTITIES))));
        } else {
            $u_search_author = urlencode(str_replace(['&amp;', '&#039;', '&quot;', '&lt;', '&gt;'], ['&', "'", '"', '<', '>'], $profileData->username));
        }

        $profileData->joined = create_date($this->lang['DATE_FORMAT'], $profileData->user_reg_date, $board_config['board_timezone']);
        $profileData->lastVisit = create_date($this->lang['DATE_FORMAT'], $profileData->user_session_time, $board_config['board_timezone']);
        $profileData->posterRank = $posterRank;
        $profileData->rankImage = $rankImage;
        $profileData->avatarImg = $avatarImage;
        $profileData->onlineStatus = $user_onlinestatus;
        $profileData->www = $www;
        $profileData->email = $email;
        $profileData->pm = $pm;
        $profileData->search = $search;

        $img = [
            'pm' => $pm_img,
            'avatar' => $avatarImage,
            'email' => $emailImage,
            'www' => $www_img,
            'search' => $searchImage
        ];

        $parameters = [
            'profileData' => $profileData,
            'indexLink' => Session::appendSid('../index.php'),
            'lang' => $this->lang,
            'img' => (object) $img,

            'jumpBox' => jumpBoxLatte('viewforum.php'),

            'l_index' => sprintf($this->lang['Forum_Index'], $this->boardConfig['sitename']),

            'l_post_day_stats' => sprintf($this->lang['User_post_day_stats'], $postsPerDay),
            'l_post_percent_stats' => sprintf($this->lang['User_post_pct_stats'], $percentagePosts),

            'l_topic_day_stats' => sprintf($this->lang['User_topic_day_stats'], $topicsPerDay),
            'l_topic_percent_stats' => sprintf($this->lang['User_post_pct_stats'], $percentageTopics),

            'l_thank_day_stats' => sprintf($this->lang['User_thank_day_stats'], $thanksPerDay),
            'l_thank_percent_stats' => sprintf($this->lang['User_post_pct_stats'], $percentageThanks),
            
            'l_topics_watch_day_stats' => sprintf($this->lang['User_topic_watch_day_stats'], $topicWatchesPerDay),
            'l_topics_watch_percent_stats' => sprintf($this->lang['User_post_pct_stats'], $percentageTopicsWatches),

            'l_viewing_profile' => sprintf($this->lang['Viewing_user_profile'], $profileData->username),
            'l_about_user' => sprintf($this->lang['About_user'], $profileData->username),

            'l_search_user_posts' => sprintf($this->lang['Search_user_posts'], $profileData->username),
            'l_search_user_topics' => sprintf($this->lang['Search_user_topics'], $profileData->username),
            'l_search_user_thanks' => sprintf($this->lang['Search_user_thanks'], $profileData->username),
            'l_search_user_topics_watches' => sprintf($this->lang['Search_user_topics_watches'], $profileData->username),

            'tdColor2Theme' => $this->theme['td_color2'],

            'searchUserPostsLink' => Session::appendSid('search.php?search_author=' . $u_search_author . '&amp;show_results=posts'),
            'searchUserTopicsLink' => Session::appendSid('search.php?search_author=' . $u_search_author . '&amp;show_results=topics'),
            'searchUserThanksLink' => Session::appendSid('search.php?search_author=' . $u_search_author . '&amp;show_results=thanks'),
            'searchUserTopicsWatchesLink' => Session::appendSid('search.php?search_author=' . $u_search_author . '&amp;show_results=topics_watches'),
        ];

        $parameters = array_merge($parameters, $attachParameters);

        $this->latte->render('UserViewProfile/default.latte', $parameters);
    }
}

?>