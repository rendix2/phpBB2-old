<?php
/**
 *
 * Created by PhpStorm.
 * Filename: ForumPresenter.php
 * User: Tomáš Babický
 * Date: 08.03.2021
 * Time: 1:18
 */

namespace phpBB2\App\AdminModule\ForumModule\Presenters;

use Nette\Application\UI\Form;
use Nette\Utils\ArrayHash;
use phpBB2\App\AdminModule\Presenters\AdminBasePresenter;
use phpBB2\App\Helpers\TemplateHelper;
use phpBB2\App\Models\Facades\CategoryFacade;
use phpBB2\App\PhpBBForm;
use phpBB2\Models\CategoriesManager;
use phpBB2\Models\ForumPruneManager;
use phpBB2\Models\ForumsManager;
use phpBB2\Sync;

/**
 * Class ForumPresenter
 *
 * @package phpBB2\App\AdminModule\Presenters
 */
class ForumPresenter extends AdminBasePresenter
{
    /**
     * @var CategoryFacade $categoryFacade
     */
    private $categoryFacade;

    /**
     * @var CategoriesManager $categoriesManager
     */
    private $categoriesManager;

    /**
     * @var ForumsManager $forumsManager
     */
    private $forumsManager;

    /**
     * @var ForumPruneManager $pruneManager
     */
    private $pruneManager;

    public function __construct(
        CategoryFacade $categoryFacade,
        CategoriesManager $categoriesManager,
        ForumsManager $forumsManager,
        ForumPruneManager $forumPruneManager
    ) {
        parent::__construct();

        $this->categoryFacade = $categoryFacade;
        $this->categoriesManager = $categoriesManager;
        $this->forumsManager = $forumsManager;
        $this->pruneManager = $forumPruneManager;
    }

    public function formatTemplateFiles()
    {
        return TemplateHelper::getPath($this);
    }

    public function renderDefault()
    {
        $categories = $this->categoryFacade->getAll();

        bdump($categories);

        $this->template->categories = $categories;
    }

    public function actionResync($id)
    {
        Sync::oneForum($id);

        $this->redirect(':Admin:Forum:Forum:default');
    }

    public function actionDelete($id)
    {
        $forums = $this->forumsManager->getAllPairs('forum_name');

        $this['deleteForumForm-to_forum']->setItems($forums);
    }

    public function actionEdit($id)
    {
        $categories = $this->categoriesManager->getAllPairs('cat_title');
        $forumPrune = $this->pruneManager->getByForumId($id);

        $this['forumForm-cat_id']->setItems($categories);

        if ($id) {
            $forum = $this->forumsManager->getByPrimaryKey($id);

            if ($forum) {
                $this['forumForm']->setDefaults($forum);

                if ($forumPrune) {
                    $this['forumForm-prune_days']->setDefaultValue($forumPrune->prune_days);
                    $this['forumForm-prune_freq']->setDefaultValue($forumPrune->prune_freq);
                } else {
                    $this['forumForm-prune_days']->setDefaultValue(7);
                    $this['forumForm-prune_freq']->setDefaultValue(1);
                }
            }
        }
    }

    public function renderEdit($id)
    {
    }

    public function actionMoveUp($id)
    {
    }

    public function actionMoveDown($id)
    {
    }

    public function createComponentForumForm()
    {
        $form = new PhpBBForm();
        $form->setTranslator($this->translator);

        $yesNo = [
            0 => 'No',
            1 => 'Yes',
        ];

        $lockedUnlocked = [
            0 => 'Status_unlocked',
            1 => 'Status_locked'
        ];

        $form->addGroup('Forum_settings');

        $form->addText('forum_name', 'Forum_name');
        $form->addTextArea('forum_desc', 'Forum_desc');
        $form->addSelect('cat_id', 'Category')
            ->setTranslator();
        $form->addRadioList('forum_status', 'Forum_status', $lockedUnlocked);
        $form->addRadioList('forum_thank_enable', 'use_thank', $yesNo);

        $form->addGroup('Forum_pruning');

        $form->addRadioList('prune_enable', 'Forum_pruning', $yesNo);
        $form->addText('prune_days', 'prune_days');
        $form->addText('prune_freq', 'prune_freq');

        $form->addSubmit('send', 'Submit');

        $form->onSuccess[] = [$this, 'forumFormSuccess'];

        return $form;
    }

    public function forumFormSuccess(Form $form)
    {
        $values = clone $form->getValues();
        $id = $this->getParameter('id');

        $forumValues = clone $values;
        unset($forumValues->prune_days);
        unset($forumValues->prune_freq);

        if ($id) {
            $this->forumsManager->updateByPrimary($id, (array) $forumValues);
        } else {
            $id = $this->forumsManager->add((array) $forumValues);
        }

        if ($values->prune_enable) {
            $pruneCount = $this->pruneManager->selectCountFluent()
                ->where('[forum_id] = %i', $id)
                ->fetchSingle();

            if ($pruneCount > 0) {
                $update_data = [
                    'prune_days' => (int)$values->prune_days,
                    'prune_freq' => (int)$values->prune_freq
                ];

                $this->pruneManager->updateFluent($update_data)
                    ->where('[forum_id] = %i', $id)
                    ->execute();
            } else {
                $insert_data = [
                    'forum_id'   => $id,
                    'prune_days' => (int)$values->prune_days,
                    'prune_freq' => (int)$values->prune_freq
                ];

                $this->pruneManager->add($insert_data);
            }
        }
    }

    public function createComponentDeleteForumForm()
    {
        $form = new PhpBBForm();
        $form->setTranslator($this->translator);

        $form->addSelect('to_forum', 'Forum_name')
        ->setTranslator();

        $form->addSubmit('send', 'Submit');

        $form->onSuccess[] = [$this, 'deleteForumFormSuccess'];

        return $form;
    }

    public function deleteForumFormSuccess(Form $form, ArrayHash $values)
    {
        $values = $form->getValues();
        $id = $this->getParameter('id');
    }
}
