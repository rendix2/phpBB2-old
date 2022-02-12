<?php
/**
 *
 * Created by PhpStorm.
 * Filename: RankPresenter.php
 * User: Tomáš Babický
 * Date: 05.03.2021
 * Time: 16:20
 */

namespace phpBB2\App\AdminModule\Presenters;

use Nette\Application\UI\Form;
use Nette\Localization\ITranslator;
use Nette\Utils\ArrayHash;
use phpBB2\App\PhpBBForm;
use phpBB2\App\Services\ThemeService;
use phpBB2\Models\RanksManager;
use phpBB2\Models\UsersManager;

/**
 * Class RankPresenter
 *
 * @package phpBB2\App\AdminModule\Presenters
 */
class RankPresenter extends AdminBasePresenter
{
    /**
     * @var RanksManager $ranksManager
     */
    private $ranksManager;

    /**
     * @var UsersManager $usersManager
     */
    private $usersManager;

    /**
     * RankPresenter constructor.
     *
     * @param ThemeService $themeService
     * @param ITranslator $translator
     * @param RanksManager $ranksManager
     * @param UsersManager $usersManager
     */
    public function __construct(
        RanksManager $ranksManager,
        UsersManager $usersManager
    ) {
        parent::__construct();

        $this->ranksManager = $ranksManager;
        $this->usersManager = $usersManager;
    }

    public function renderDefault()
    {
        $ranks = $this->ranksManager->getAll();

        $this->template->ranks = $ranks;
    }

    public function actionEdit($id)
    {
        if ($id) {
            $rank = $this->ranksManager->getByPrimaryKey($id);

            if (!$rank) {
                $this->error($this->translator->translate('Must_select_rank'));
            }

            $this['rankForm']->setDefaults($rank);
        }
    }

    public function renderEdit($id)
    {
        $rank = $this->ranksManager->getByPrimaryKey($id);

        $this->template->rank = $rank;
    }

    public function actionDelete($id)
    {
        $rank = $this->ranksManager->getByPrimaryKey($id);

        if ($rank) {
            $this->ranksManager->deleteByPrimaryKey($id);
            $this->usersManager->updateFluent(['user_rank' => 0])
                ->where('[user_rank] = %i', $id)
                ->execute();
        }

        $this->flashMessage('Rank_removed');
        $this->redirect('Rank:default');
    }

    public function createComponentRankForm()
    {
        $form = new PhpBBForm();
        $form->setTranslator($this->getTranslator());

        $specials = [
          1 => 'Yes',
          0 => 'No'
        ];

        $form->addText('rank_title', 'Rank_title')
            ->setRequired('Must_select_rank');
        $form->addText('rank_desc', 'Ranks_desc');
        $form->addRadioList('rank_special', 'Rank_special', $specials);
        $form->addInteger('rank_min', 'Rank_minimum')
            ->setNullable(0);
        $form->addText('rank_image', 'Rank_image');
        $form->addSubmit('send', 'Submit');

        $form->onSuccess[] = [$this, 'rankFormSuccess'];

        return $form;
    }

    public function rankFormSuccess(Form $form, ArrayHash $values)
    {
        $id = $this->getParameter('id');

        if ($id) {
            $this->ranksManager->updateByPrimary($id, (array) $values);

            if ($values->rank_special) {
                $this->usersManager->updateFluent(['user_rank' => 0])
                    ->where('[user_rank] = %i', $id)
                    ->execute();
            }

            $this->flashMessage('Rank_updated');
        } else {
            $id = $this->ranksManager->add((array) $values);

            $this->flashMessage('Rank_added');
        }

        $this->redirect('Rank:edit', $id);
    }
}