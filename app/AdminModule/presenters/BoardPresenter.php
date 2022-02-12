<?php
/**
 *
 * Created by PhpStorm.
 * Filename: BoardPresenter.php
 * User: Tomáš Babický
 * Date: 08.03.2021
 * Time: 1:28
 */

namespace phpBB2\App\AdminModule\Presenters;

use Nette\Application\UI\Form;
use Nette\Utils\ArrayHash;
use phpBB2\App\PhpBBForm;
use phpBB2\Models\ConfigManager;

/**
 * Class BoardPresenter
 *
 * @package phpBB2\App\AdminModule\Presenters
 */
class BoardPresenter extends AdminBasePresenter
{
    /**
     * @var ConfigManager $configManager
     */
    private $configManager;

    public function __construct(ConfigManager $configManager)
    {
        parent::__construct();

        $this->configManager = $configManager;
    }

    public function actionDefault()
    {
        $boardConfig = $this->configManager->getConfig();

        $this['boardForm']->setDefaults($boardConfig);
    }

    public function renderDefault()
    {

    }

    public function createComponentBoardForm()
    {
        $form = new PhpBBForm();
        $form->setTranslator($this->translator);

        $yesNo = [
            1 => 'Yes',
            0 => 'No'
        ];

        $form->addText('server_name');
        $form->addText('server_port');
        $form->addText('script_path');
        $form->addText('site_name');
        $form->addText('site_description');

        $form->addRadioList('disabled_board', '', $yesNo);

        $form->addRadioList('acct_activation');

        $form->addSubmit('send', 'Submit');

        $form->onSuccess[] = [$this, 'boardFormSuccess'];

        return $form;
    }

    public function boardFormSuccess(Form $form, ArrayHash $values)
    {

    }
}