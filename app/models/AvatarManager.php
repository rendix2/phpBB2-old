<?php
/**
 *
 * Created by PhpStorm.
 * Filename: AvatarManager.php
 * User: Tomáš Babický
 * Date: 03.03.2021
 * Time: 15:30
 */

namespace phpBB2\Models;

use Nette\DI\Container;
use Nette\Utils\Finder;
use SplFileInfo;

/**
 * Class AvatarManager
 *
 * @package phpBB2\Models
 */
class AvatarManager
{

    /**
     * @var Container $container
     */
    private $container;

    /**
     * @var ConfigManager $configManager
     */
    private $configManager;

    /**
     * AvatarManager constructor.
     * @param ConfigManager $configManager
     * @param Container $container
     */
    public function __construct(
        ConfigManager $configManager,
        Container $container
    ) {
        $this->configManager = $configManager;
        $this->container = $container;
    }

    /**
     * @return int
     */
    public function getAvatarDirSize()
    {
        $sep = DIRECTORY_SEPARATOR;

        $avatarsFolder = $this->container->getParameters()['wwwDir'] . $sep . '..' . $sep .  $this->configManager->getConfig()['avatar_path'];


        $avatarDirSize   = 0;
        $enabledExtensions = ['*.jpg', '*.jpeg', '*.pjpeg', '*.gif', '*.png'];

        $avatars = Finder::findFiles($enabledExtensions)->in($avatarsFolder);

        if (count($avatars)) {
            /**
             * @var SplFileInfo $avatar
             */
            foreach ($avatars as $avatar) {
                $avatarDirSize += $avatar->getSize();
            }

            $avatarDirSize = get_formatted_filesize($avatarDirSize);
        }

        return $avatarDirSize;
    }
}
