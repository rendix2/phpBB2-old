<?php
/**
 *
 * Created by PhpStorm.
 * Filename: TemplateService.php
 * User: Tomáš Babický
 * Date: 05.03.2021
 * Time: 14:43
 */

namespace phpBB2\App\Services;

use Nette\Security\User;
use phpBB2\Models\ConfigManager;
use phpBB2\Models\ThemesManager;
use phpBB2\Models\UsersManager;

/**
 * Class ThemeService
 *
 * @package phpBB2\App\Services
 */
class ThemeService
{
    const DEFAULT_THEME = 'subsilver';

    /**
     * @var ConfigManager $configManager
     */
    private ConfigManager $configManager;

    /**
     * @var User $user
     */
    private $user;

    /**
     * @var ThemesManager $themesManager
     */
    private $themesManager;

    /**
     * @var UsersManager $userManager
     */
    private $usersManager;

    /**
     * ThemeService constructor.
     *
     * @param ConfigManager $configManager
     * @param ThemesManager $themesManager
     * @param UsersManager $usersManager
     * @param User $user
     */
    public function __construct(
        ConfigManager $configManager,
        ThemesManager $themesManager,
        UsersManager $usersManager,
        User $user
    ) {
        $this->configManager = $configManager;
        $this->themesManager = $themesManager;
        $this->user = $user;
        $this->usersManager = $usersManager;
    }

    public function getThemeName()
    {
        if ($this->user->loggedIn) {
            $userTheme = $this->user->identity->getData()['user_style'];
        } else {
            $userTheme = $this->configManager->getConfig()['default_style'];
        }

        $theme = $this->themesManager->getByPrimaryKey($userTheme);

        if ($theme) {
            return $theme->template_name;
        } else {
            return self::DEFAULT_THEME;
        }
    }

    public function getThemeData()
    {
        if ($this->user->loggedIn) {
            $userTheme = $this->user->identity->getData()['user_style'];
        } else {
            $userTheme = $this->configManager->getConfig()['default_style'];
        }

        $theme = $this->themesManager->getByPrimaryKey($userTheme);

        if ($theme) {
            return $theme;
        } else {
            $theme = $this->themesManager->getByPrimaryKey(self::DEFAULT_THEME);

            if ($theme) {
                return $theme;
            } else {
                throw new ServiceException;
            }
        }
    }
}

class ServiceException extends \Exception
{
}