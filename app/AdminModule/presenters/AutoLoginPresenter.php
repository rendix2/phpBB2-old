<?php
/**
 *
 * Created by PhpStorm.
 * Filename: AutoLoginPresenter.php
 * User: Tomáš Babický
 * Date: 07.03.2021
 * Time: 16:19
 */

namespace phpBB2\App\AdminModule\Presenters;

use Nette\Localization\ITranslator;
use phpBB2\App\Services\ThemeService;
use phpBB2\Models\SessionsKeysManager;

/**
 * Class AutoLoginPresenter
 *
 * @package phpBB2\App\AdminModule\Presenters
 */
class AutoLoginPresenter extends AdminBasePresenter
{

    /**
     * @var SessionsKeysManager $sessionsKeysManager
     */
    private $sessionsKeysManager;

    public function __construct(
        ThemeService $themeService,
        ITranslator $translator,
        SessionsKeysManager $sessionsKeysManager
    ) {
        parent::__construct($themeService, $translator);

        $this->sessionsKeysManager = $sessionsKeysManager;
    }

    public function renderDefault()
    {
        $sessions = $this->sessionsKeysManager->getAllSessionsKeysJoinedUsers();


        $this->template->sessions = $sessions;
        $this->template->addFilter('decodeIp', new \DecodeIpFilter());
    }

    public function actionDelete($id)
    {
        $this->sessionsKeysManager->deleteFluent()
            ->where('[key_id] = %s', $id)
            ->execute();

        $this->flashMessage('Delete_auto_login');
        $this->redirect('AutoLogin:default');
    }

}