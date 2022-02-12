<?php
/**
 *
 * Created by PhpStorm.
 * Filename: UserPresenter.php
 * User: Tomáš Babický
 * Date: 05.03.2021
 * Time: 16:21
 */

namespace phpBB2\App\AdminModule\UserModule\Presenters;

use DateTimeZone;
use Nette\Application\UI\Form;
use Nette\Localization\ITranslator;
use Nette\Utils\ArrayHash;
use phpBB2\App\AdminModule\Presenters\AdminBasePresenter;
use phpBB2\App\Helpers\TemplateHelper;
use phpBB2\App\PhpBBForm;
use phpBB2\App\Services\ThemeService;
use phpBB2\Models\LanguagesManager;
use phpBB2\Models\RanksManager;
use phpBB2\Models\ThemesManager;
use phpBB2\Models\UsersManager;

/**
 * Class UserPresenter
 *
 * @package phpBB2\App\AdminModule\Presenters
 */
class UserPresenter extends AdminBasePresenter
{
    /**
     * @var UsersManager $usersManager
     */
    private $usersManager;

    /**
     * @var LanguagesManager $languagesManager
     */
    private $languagesManager;

    /**
     * @var RanksManager $ranksManager
     */
    private $ranksManager;

    /**
     * @var ThemesManager $themesManager
     */
    private $themesManager;

    /**
     * UserPresenter constructor.
     *
     * @param ThemeService $themeService
     * @param ITranslator $translator
     * @param UsersManager $usersManager
     */
    public function __construct(
        ThemeService $themeService,
        ITranslator $translator,
        UsersManager $usersManager,
        LanguagesManager $languagesManager,
        RanksManager $ranksManager,
        ThemesManager $themesManager
    ) {
        parent::__construct();

        $this->languagesManager = $languagesManager;
        $this->usersManager = $usersManager;
        $this->ranksManager = $ranksManager;
        $this->themesManager = $themesManager;
    }

    public function formatTemplateFiles()
    {
        return TemplateHelper::getPath($this);
    }

    public function renderDefault()
    {
        $users = $this->usersManager->getAll();

        $this->template->users = $users;
    }

    public function actionEdit($id)
    {
        $languages = $this->languagesManager
            ->selectFluent()
            ->fetchPairs('lang_name', 'lang_name');
        $themes = $this->themesManager
            ->selectFluent()
            ->orderBy('template_name')
            ->orderBy('themes_id')
            ->fetchPairs('themes_id', 'style_name');

        $ranks = $this->ranksManager->getPairsSpecialRanks();

        $timeZones = [];

        foreach (DateTimeZone::listIdentifiers() as $identifier) {
            $timeZones[$identifier] = $identifier;
        }

        $this['userForm-user_rank']->setItems($ranks);
        $this['userForm-user_style']->setItems($themes);
        $this['userForm-user_lang']->setItems($languages);
        $this['userForm-user_timezone']->setItems($timeZones);

        if ($id) {
            $userData = $this->usersManager->getByPrimaryKey($id);

            if (!$userData) {
                $this->error('No_user_id_specified');
            }

            $this['userForm']->setDefaults($userData);
        }
    }

    public function renderEdit($id)
    {
        if ($id) {
            $userData = $this->usersManager->getByPrimaryKey($id);
        } else {
            $userData = null;
        }


        $this->template->userData = $userData;
        $this->template->usersManager = UsersManager::class;
    }

    public function actionDelete($id)
    {

    }

    public function createComponentUserForm()
    {
        $form = new PhpBBForm();
        $form->setTranslator($this->translator);

        $form->addText('user_website', 'Website');
        $form->addText('user_location', 'Location');
        $form->addText('user_occupation', 'Occupation');
        $form->addText('user_interests', 'Interests');

        $yesNo = [
            1 => 'Yes',
            0 => 'No'
        ];

        $form->addTextArea('user_sig', 'Signature');

        $form->addRadioList('user_allow_view_online', 'Show_user', $yesNo);
        $form->addRadioList('user_notify', 'Always_notify', $yesNo);
        $form->addRadioList('user_notify_pm', 'Notify_on_privmsg', $yesNo);
        $form->addRadioList('user_popup_pm', 'Popup_on_privmsg', $yesNo);
        $form->addRadioList('user_attach_sig', 'Always_add_sig', $yesNo);
        $form->addRadioList('user_allow_bbcode', 'Always_bbcode', $yesNo);
        $form->addRadioList('user_allow_html', 'Always_html', $yesNo);
        $form->addRadioList('user_allow_smilie', 'Always_smile', $yesNo);

        $form->addSelect('user_lang', 'Board_lang')
            ->setTranslator();

        $form->addSelect('user_style', 'Board_style')
            ->setTranslator();

        $form->addSelect('user_timezone', 'Timezone')
            ->setTranslator();

        $form->addText('user_date_format','Date_format');
        $form->addRadioList('user_active', 'User_status', $yesNo);
        $form->addRadioList('user_allow_pm', 'User_allowpm', $yesNo);
        $form->addRadioList('user_allow_avatar', 'User_allowavatar', $yesNo);
        $form->addSelect('user_rank', 'Rank_title', $yesNo)
            ->setTranslator();

        $form->addSubmit('send', 'Submit');

        $form->onSuccess[] = [$this, 'userFormSuccess'];

        return $form;
    }

    public function userFormSuccess(Form $form, ArrayHash $values)
    {

    }

    public function createComponentUserChangePasswordForm()
    {
        $form = new PhpBBForm();
        $form->setTranslator($this->translator);

        $form->addPassword('user_password', 'New_password');
        $form->addPassword('user_password_confirm', 'Confirm_password');

        $form->addSubmit('send', 'Submit');

        $form->onSuccess = [$this, 'userChangePasswordFormSuccess'];

        return $form;
    }

    public function userChangePasswordFormSuccess(Form $form, ArrayHash $values)
    {

    }

    public function createComponentUserAdminChangePasswordForm()
    {
        $form = new PhpBBForm();
        $form->setTranslator($this->translator);

        $form->addPassword('user_password', 'ACP_password');
        $form->addPassword('user_password_confirm', 'ACP_password_confirm');

        $form->addSubmit('send', 'Submit');

        $form->onSuccess = [$this, 'userAdminChangePasswordFormSuccess'];

        return $form;
    }

    public function userAdminChangePasswordFormSuccess(Form $form, ArrayHash $values)
    {

    }
}
