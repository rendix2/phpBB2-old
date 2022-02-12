<?php
/**
 *
 * Created by PhpStorm.
 * Filename: HomepagePresenter.php
 * User: Tomáš Babický
 * Date: 03.03.2021
 * Time: 13:50
 */

namespace phpBB2\App\AdminModule\Presenters;

use DateTime;
use DateTimeZone;
use Nette\DI\Container;
use Nette\Localization\ITranslator;
use phpBB2\App\Helpers\DateHelper;
use phpBB2\App\Services\ThemeService;
use phpBB2\Models\AvatarManager;
use phpBB2\Models\CategoriesManager;
use phpBB2\Models\ConfigManager;
use phpBB2\Models\DatabaseManager;
use phpBB2\Models\ForumsManager;
use phpBB2\Models\GroupsManager;
use phpBB2\Models\LanguagesManager;
use phpBB2\Models\PostsManager;
use phpBB2\Models\RanksManager;
use phpBB2\Models\SessionsManager;
use phpBB2\Models\ThanksManager;
use phpBB2\Models\ThemesManager;
use phpBB2\Models\TopicsManager;
use phpBB2\Models\TopicsWatchManager;
use phpBB2\Models\UsersManager;

class HomepagePresenter extends AdminBasePresenter
{
    /**
     * @var AvatarManager $avatarManager
     */
    private $avatarManager;

    private $categoriesManager;

    /**
     * @var ConfigManager $configManager
     */
    private $configManager;

    /**
     * @var Container $container
     */
    private $container;

    /**
     * @var DatabaseManager $databaseManager
     */
    private $databaseManager;

    private $forumsManager;

    private $groupsManager;

    /**
     * @var LanguagesManager $languagesManager
     */
    private $languagesManager;

    private $postsManager;

    private $ranksManager;

    /**
     * @var SessionsManager $sessionsManager
     */
    private $sessionsManager;

    /**
     * @var ThanksManager $thanksManager
     */
    private $thanksManager;

    private $themesManager;

    private $topicsManager;

    private $usersManager;

    /**
     * @var TopicsWatchManager $topicsWatchManager
     */
    private $topicsWatchManager;

    /**
     * HomepagePresenter constructor.
     *
     * @param ITranslator $translator
     * @param ThemeService $themeService
     * @param AvatarManager $avatarManager
     * @param CategoriesManager $categoriesManager
     * @param ConfigManager $configManager
     * @param Container $container
     * @param DatabaseManager $databaseManager
     * @param ForumsManager $forumsManager
     * @param GroupsManager $groupsManager
     * @param LanguagesManager $languagesManager
     * @param PostsManager $postsManager
     * @param RanksManager $ranksManager
     * @param SessionsManager $sessionsManager
     * @param ThanksManager $thanksManager
     * @param ThemesManager $themesManager
     * @param TopicsManager $topicsManager
     * @param TopicsWatchManager $topicsWatchManager
     * @param UsersManager $usersManager
     */
    public function __construct(
        ITranslator $translator,
        ThemeService $themeService,
        AvatarManager $avatarManager,
        CategoriesManager $categoriesManager,
        ConfigManager $configManager,
        Container $container,
        DatabaseManager $databaseManager,
        ForumsManager $forumsManager,
        GroupsManager $groupsManager,
        LanguagesManager $languagesManager,
        PostsManager $postsManager,
        RanksManager $ranksManager,
        SessionsManager $sessionsManager,
        ThanksManager $thanksManager,
        ThemesManager $themesManager,
        TopicsManager $topicsManager,
        TopicsWatchManager $topicsWatchManager,
        UsersManager $usersManager
    ) {
        parent::__construct($themeService, $translator);

        $this->avatarManager = $avatarManager;
        $this->categoriesManager = $categoriesManager;
        $this->configManager = $configManager;
        $this->container = $container;
        $this->databaseManager = $databaseManager;
        $this->forumsManager = $forumsManager;
        $this->groupsManager = $groupsManager;
        $this->languagesManager = $languagesManager;
        $this->postsManager = $postsManager;
        $this->ranksManager = $ranksManager;
        $this->sessionsManager = $sessionsManager;
        $this->thanksManager = $thanksManager;
        $this->themesManager = $themesManager;
        $this->topicsManager = $topicsManager;
        $this->topicsWatchManager = $topicsWatchManager;
        $this->usersManager = $usersManager;
    }

    public function renderDefault()
    {
        $boardConfig = $this->configManager->getConfig();

        $postCount = $this->postsManager->getAllCount();
        $topicCount = $this->topicsManager->getAllCount();
        $userCount = $this->usersManager->getAllCount();
        $thanksCount = $this->thanksManager->getAllCount();
        $topicWatchCount = $this->topicsWatchManager->getAllCount();
        $autoLoggedInUsersCount = $this->usersManager->getAutoLoggedInUsersCount();
        $onlineUsersCount = $this->sessionsManager->getAllCount();
        $notActiveUsersCount = $this->usersManager->getNotActiveUsers();


        $percentAutoLoggedUsers = $autoLoggedInUsersCount / $userCount;
        $activeUsersCount = $userCount - $notActiveUsersCount;

        $this->template->numberOfCategories = $this->categoriesManager->getAllCount();
        $this->template->numberOfForums = $this->forumsManager->getAllCount();
        $this->template->numberOfGroups = $this->groupsManager->getAllCount();
        $this->template->numberOfSingleUserGroups = $this->groupsManager->getSingleUserGroupsCount();
        $this->template->numberOfNotSingleUserGroups = $this->groupsManager->getNotSingleUserGroupsCount();
        $this->template->numberOfLanguages = $this->languagesManager->getAllCount();
        $this->template->numberOfRanks = $this->ranksManager->getAllCount();
        $this->template->numberofSpecialRanks = $this->ranksManager->getSpecialRanksCount();
        $this->template->numberofNotSpecialRanks = $this->ranksManager->getNotSpecialRanksCount();
        $this->template->numberOfThemes = $this->themesManager->getAllCount();
        $this->template->numberOfTopics = $topicCount;
        $this->template->numberOfPosts = $postCount;
        $this->template->numberOfTopicsWatch = $topicWatchCount;
        $this->template->numberOfUsers = $userCount;
        $this->template->numberOfOnlineUsers = $onlineUsersCount;
        $this->template->numberOfRegisteredOnlineUsers = $this->usersManager->getRegisteredUsersCount();
        $this->template->numberOfNotActiveUsers = $notActiveUsersCount;
        $this->template->numberOfActiveUsers = $activeUsersCount;
        $this->template->numberOfAdministrators = $this->usersManager->getAdministratorsCount();
        $this->template->numberOfModerators = $this->usersManager->getModeratorsCount();
        $this->template->numberOfAutoLoggedInUsers = $autoLoggedInUsersCount;

        $this->template->percentAutoLoggedIn = $percentAutoLoggedUsers;

        $this->template->dbSize = $this->databaseManager->getDatabaseSize();
        $this->template->avatarDirSize = $this->avatarManager->getAvatarDirSize();
        $this->template->startDay = DateHelper::createDate($boardConfig['default_dateformat'], $boardConfig['board_startdate'], $boardConfig['board_timezone']);
        $this->template->phpBBVersion = 2 . $boardConfig['version'];
        $this->template->phpVersion = PHP_VERSION;
        $this->template->mysqlVersion = $this->databaseManager->getVersion();

        $userTimeZone = isset($profileData['user_timezone']) ? $profileData['user_timezone'] : $boardConfig['board_timezone'];

        $zone = new DateTimeZone($userTimeZone);

        $boardStartDay = new DateTime();
        $boardStartDay->setTimezone($zone);
        $boardStartDay->setTimestamp($boardConfig['board_startdate']);

        $boardRunningDays = new DateTime();
        $boardRunningDays->setTimezone($zone);
        $boardRunningDays = $boardRunningDays->diff($boardStartDay)->days;

        $postsPerDay  = sprintf('%.2f', $postCount / $boardRunningDays);
        $topicsPerDay = sprintf('%.2f', $topicCount / $boardRunningDays);
        $usersPerDay  = sprintf('%.2f', $userCount / $boardRunningDays);
        $thanksPerDay  = sprintf('%.2f', $thanksCount / $boardRunningDays);
        $topicWatchPerDay  = sprintf('%.2f', $topicWatchCount / $boardRunningDays);

        $this->template->postsPerDay = $postsPerDay;
        $this->template->topicsPerDay = $topicsPerDay;
        $this->template->usersPerDay = $usersPerDay;
        $this->template->thanksPerDay = $thanksPerDay;
        $this->template->topicsWatchsPerDay = $topicWatchPerDay;
    }

}