<?php
/**
 *
 * Created by PhpStorm.
 * Filename: StartService.php
 * User: Tomáš Babický
 * Date: 05.03.2021
 * Time: 14:38
 */

namespace phpBB2\App\Services;

use Exception;

/**
 * Class StartService
 *
 * @package phpBB2\App\Services
 */
class StartService
{

    /**
     * @var \phpBB2\Models\ConfigManager
     */
    private $configManager;

    /**
     * StartService constructor.
     *
     * @param \phpBB2\Models\ConfigManager $configManager
     */
    public function __construct(
        \phpBB2\Models\ConfigManager $configManager
    ) {
        $this->configManager = $configManager;
    }

    public function checkBoardDisabled()
    {
        $boardDisabled = $this->configManager->getConfig()['board_disable'];

        if ($boardDisabled) {
            throw new BoardDisabledException();
        }
    }

    public function checkBanned()
    {

    }

    public function run()
    {
        $this->checkBanned();
        $this->checkBoardDisabled();
    }
}

class BoardDisabledException extends Exception
{
}

class BannedException extends Exception
{
}