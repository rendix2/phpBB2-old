<?php
/**
 *
 * Created by PhpStorm.
 * Filename: WordPresenter.php
 * User: Tomáš Babický
 * Date: 05.03.2021
 * Time: 16:23
 */

namespace phpBB2\App\AdminModule\Presenters;


use Nette\Application\UI\Form;
use Nette\Localization\ITranslator;
use Nette\Utils\ArrayHash;
use phpBB2\App\Helpers\TemplateHelper;
use phpBB2\App\PhpBBForm;
use phpBB2\App\Services\ThemeService;
use phpBB2\Models\WordsManager;

class WordPresenter extends AdminBasePresenter
{
    /**
     * @var WordsManager $wordsManager
     */
    private $wordsManager;

    /**
     * WordPresenter constructor.
     *
     * @param ThemeService $themeService
     * @param ITranslator $translator
     * @param WordsManager $wordsManager
     */
    public function __construct(
        ThemeService $themeService,
        ITranslator $translator,
        WordsManager $wordsManager
    ) {
        parent::__construct($themeService, $translator);

        $this->wordsManager = $wordsManager;
    }

    public function formatTemplateFiles()
    {
        return TemplateHelper::getPath($this);
    }

    public function renderDefault()
    {
        $words = $this->wordsManager->getAll();

        $this->template->words = $words;
    }

    public function actionDelete($id)
    {
        $this->wordsManager->deleteByPrimaryKey($id);
        $this->flashMessage('Word_removed');
        $this->redirect('Word:default');
    }

    public function actionEdit($id)
    {
        if ($id) {
            $word = $this->wordsManager->getByPrimaryKey($id);

            if (!$word) {
                $this->error('No_word_selected');
            }

            $this['wordForm']->setDefaults($word);
        }
    }

    public function renderEdit($id)
    {

    }

    public function createComponentWordForm()
    {
        $form = new PhpBBForm();

        $form->setTranslator($this->translator);

        $form->addText('word', 'Word');
        $form->addText('replacement', 'Replacement')
            ->setRequired('Must_enter_word');
        $form->addSubmit('send', 'Submit')
            ->setRequired('Must_enter_word');

        $form->onSuccess[] = [$this, 'wordFormSuccess'];

        return $form;
    }

    public function wordFormSuccess(Form $form, ArrayHash $values)
    {
        $id = $this->getParameter('id');

        if ($id) {
            $this->wordsManager->updateByPrimary($id, (array) $values);
            $this->flashMessage('Word_updated');
        } else {
            $id = $this->wordsManager->add((array) $values);
            $this->flashMessage('Word_added');
        }

        $this->redirect('Word:edit', $id);
    }
}
