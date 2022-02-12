<?php
/**
 *
 * Created by PhpStorm.
 * Filename: LanguageService.php
 * User: Tomáš Babický
 * Date: 05.03.2021
 * Time: 15:28
 */

namespace phpBB2\App\Services;

use Nette\Security\User;
use phpBB2\Models\ConfigManager;

/**
 * Class LanguageService
 *
 * @package phpBB2\App\Services
 */
class LanguageService
{
    const DEFAULT_LANGUAGE = 'english';

    /**
     * @var ConfigManager $configManager
     */
    private $configManager;

    /**
     * @var User $user
     */
    private $user;

    /**
     * LanguageService constructor.
     *
     * @param ConfigManager $configManager
     * @param User $user
     */
    public function __construct(
        ConfigManager $configManager,
        User $user
    ) {
        $this->configManager = $configManager;
        $this->user = $user;
    }

    public function getLanguage()
    {
        if ($this->user->loggedIn) {
            $language = $this->user->identity->getData()['user_lang'];
        } else {
            $language = $this->configManager->getConfig()['default_lang'];

            if ($language === null) {
                $language = self::DEFAULT_LANGUAGE;
            }
        }

        return $language;
    }
}
