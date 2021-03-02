<?php

use Nette\Caching\IStorage;

class OnlinePresenter
{
    /**
     * @var IStorage $storage
     */
    private $storage;

    private $userData;

    /**
     * @var array $lang
     */
    private array $lang;

    /**
     * @var array $boardConfig
     */
    private array $boardConfig;

    private $theme;

    /**
     * OnlinePresenter constructor.
     *
     * @param IStorage $storage
     * @param $userData
     * @param array $lang
     * @param array $boardConfig
     * @param $theme
     */
    public function __construct(IStorage $storage, $userData, array $lang, array $boardConfig, $theme)
    {
        $this->storage = $storage;
        $this->userData = $userData;
        $this->lang = $lang;
        $this->boardConfig = $boardConfig;
        $this->theme = $theme;
    }

    public function renderDefault()
    {
        $latte = new LatteFactory($this->storage, $this->userData);

        //
        // Get users online information.
        //
        $user_timezone = isset($this->userData['user_timezone']) ? $this->userData['user_timezone'] : $this->boardConfig['board_timezone'];

        $time = new DateTime();
        $time->setTimezone(new DateTimeZone($user_timezone));
        $time->sub(new DateInterval('PT' . ONLINE_TIME_DIFF . 'S'));

        $columns = [
            'u.user_id',
            'u.username',
            'u.user_session_time',
            'u.user_session_page',
            'u.user_allow_view_online',
            's.session_logged_in',
            's.session_ip',
            's.session_start',
            'session_page'
        ];

        $registeredUsers = dibi::select($columns)
            ->from(Tables::USERS_TABLE)
            ->as('u')
            ->innerJoin(Tables::SESSIONS_TABLE)
            ->as('s')
            ->on('[u.user_id] = [s.session_user_id]')
            ->where('[s.session_logged_in] = %i', 1)
            ->where('[u.user_id] <> %i', ANONYMOUS)
            ->where('[s.session_time] >= %i', $time->getTimestamp())
            ->orderBy('u.user_id', dibi::DESC)
            ->groupBy('u.user_id')
            ->fetchAll();

        $guestUsers = dibi::select(['session_page', 'session_logged_in', 'session_time', 'session_ip', 'session_start'])
            ->from(Tables::SESSIONS_TABLE)
            ->where('[session_logged_in] = %i', 0)
            ->where('[session_time] >= %i', $time->getTimestamp())
            ->orderBy('session_time', 'DESC')
            ->fetchAll();

        $forums = dibi::select(['forum_name', 'forum_id'])
            ->from(Tables::FORUMS_TABLE)
            ->fetchPairs('forum_id', 'forum_name');

        if (count($registeredUsers)) {
            foreach ($registeredUsers as $i => $registeredUser) {
                // check if user does some special action
                // if he is in some forum
                if ($registeredUser->session_page < 1) {
                    $locationUrl = 'index.php?pane=right';

                    $location = getForumLocation($registeredUser->user_session_page, $this->lang);
                } else {
                    $locationUrl = Session::appendSid('admin_forums.php?mode=editforum&amp;' . POST_FORUM_URL . '=' . $registeredUser->user_session_page);
                    $location = $forums[$registeredUser->user_session_page];
                }

                $registeredUser->started = create_date($this->boardConfig['default_dateformat'], $registeredUser->session_start, $this->boardConfig['board_timezone']);
                $registeredUser->ipAddress = decode_ip($registeredUser->session_ip);
                $registeredUser->lastupdate = create_date($this->boardConfig['default_dateformat'], $registeredUser->user_session_time, $this->boardConfig['board_timezone']);
                $registeredUser->forumLocation = $location;
                $registeredUser->forumLocationLink = Session::appendSid($locationUrl);
                $registeredUser->userProfileLink = Session::appendSid('admin_users.php?mode=edit&amp;' . POST_USERS_URL . '=' . $registeredUser->user_id);
            }
        }

        if (count($guestUsers)) {
            foreach ($guestUsers as $i => $guestUser) {
                // check if user does some special action
                // if he is in some forum
                if ($guestUser->session_page < 1) {
                    $locationUrl = 'index.php?pane=right';

                    $location = getForumLocation($guestUser->session_page, $this->lang);
                } else {
                    $locationUrl = Session::appendSid('admin_forums.php?mode=editforum&amp;' . POST_FORUM_URL . '=' . $guestUser->session_page);

                    $location = $forums[$guestUser->session_page];
                }

                $guestUser->ipAddress = decode_ip($guestUser->session_ip);
                $guestUser->started = create_date($this->boardConfig['default_dateformat'], $guestUser->session_start, $this->boardConfig['board_timezone']);
                $guestUser->lastupdate = create_date($this->boardConfig['default_dateformat'], $guestUser->session_time, $this->boardConfig['board_timezone']);
                $guestUser->username = $this->lang['Guest'];
                $guestUser->forumLocation = $location;
                $guestUser->forumLocationLink = Session::appendSid($locationUrl);
            }
        }

        $parameters = [
            'guestUsers' => $guestUsers,
            'registeredUsers' => $registeredUsers,
            'lang' => $this->lang,
            'theme' => $this->theme
        ];

        $latte->render('admin/Online/default.latte', $parameters);
    }
}

define('IN_PHPBB', 1);

//
// Let's set the root dir for phpBB
//
$sep = DIRECTORY_SEPARATOR;
$phpbb_root_path = '.' . $sep . '..' . $sep;

require_once '.' . $sep . 'pagestart.php';

$onlinePresenter = new OnlinePresenter($storage, $userdata, $lang, $board_config, $theme);

$onlinePresenter->renderDefault();

require_once '.' . $sep . 'page_footer_admin.php';