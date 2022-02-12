<?php
/**
 *
 * Created by PhpStorm.
 * Filename: CategoryPresenter.php
 * User: Tomáš Babický
 * Date: 22.03.2021
 * Time: 15:40
 */

namespace phpBB2\App\AdminModule\CategoryModule\Presenters;

use Nette\Forms\Form;
use phpBB2\App\AdminModule\Presenters\AdminBasePresenter;
use phpBB2\App\Helpers\TemplateHelper;
use phpBB2\App\PhpBBForm;
use phpBB2\Models\CategoriesManager;

/**
 * Class CategoryPresenter
 */
class CategoryPresenter extends AdminBasePresenter
{

    private CategoriesManager $categoriesManager;

    /**
     * @param CategoriesManager $categoriesManager
     */
    public function __construct(CategoriesManager $categoriesManager)
    {
        parent::__construct();

        $this->categoriesManager = $categoriesManager;
    }

    public function formatTemplateFiles()
    {
        return TemplateHelper::getPath($this);
    }

    public function actionEdit($id)
    {
        if ($id) {
            $category = $this->categoriesManager->getByPrimaryKey($id);

            if (!$category) {
                $this->error('');
            }

            $this['categoryForm']->setDefaults($category);
        }

    }

    public function renderEdit($id)
    {
    }


    public function actionDelete($id)
    {
    }

    public function actionMoveUp($id)
    {
    }

    public function actionMoveDown($id)
    {
    }

    public function createComponentCategoryForm()
    {
        $form = new PhpBBForm();
        $form->setTranslator($this->translator);

        $form->addText('cat_title', 'Category');

        $form->addSubmit('send', 'Submit');

        $form->onSuccess[] = [$this, 'categoryFormSuccess'];

        return $form;
    }

    public function categoryFormSuccess(Form $form)
    {
        $values = $form->getValues();
        $id = $this->getParameter('id');

        if ($id) {
            $this->categoriesManager->updateByPrimary($id, (array)$values);
        } else {
            $id = $this->categoriesManager->add((array)$values);
        }
    }

}