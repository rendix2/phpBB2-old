<?php
/**
 *
 * Created by PhpStorm.
 * Filename: UserPresenter.php
 * User: Tomáš Babický
 * Date: 08.03.2021
 * Time: 13:12
 */

namespace phpBB2\App\AdminModule\GroupModule\Presenters;

use Nette\Application\UI\Form;
use Nette\Utils\ArrayHash;
use phpBB2\App\AdminModule\Presenters\AdminBasePresenter;
use phpBB2\App\Helpers\TemplateHelper;
use phpBB2\App\PhpBBForm;
use phpBB2\Models\GroupsManager;
use phpBB2\Models\UserGroupManager;
use phpBB2\Models\UsersManager;

/**
 * Class UserPresenter
 *
 * @package phpBB2\App\AdminModule\GroupModule\Presenters
 */
class UserPresenter extends AdminBasePresenter
{
    /**
     * @var GroupsManager $groupsManager
     */
    private $groupsManager;

    /**
     * @var UserGroupManager $userGroupManager
     */
    private $userGroupManager;

    /**
     * @var UsersManager $usersManager
     */
    private $usersManager;

    /**
     * UserPresenter constructor.
     *
     * @param GroupsManager $groupsManager
     * @param UserGroupManager $userGroupManager
     */
    public function __construct(
        GroupsManager $groupsManager,
        UserGroupManager $userGroupManager,
        UsersManager $usersManager
    ) {
        $this->groupsManager = $groupsManager;
        $this->userGroupManager = $userGroupManager;
        $this->usersManager = $usersManager;
    }

    public function formatTemplateFiles()
    {
        return TemplateHelper::getPath($this);
    }

    /**
     * @param int $id
     */
    public function renderDefault($id)
    {
        $group = $this->groupsManager->getByPrimaryKey($id);

        if (!$group) {
            $this->error($this->translator->translate('Group_not_exist'));
        }

        $users = $this->userGroupManager->getByRightJoined($id);

        $this->template->group = $group;
        $this->template->users = $users;
    }

    public function actionEdit($id)
    {
        $users = $this->usersManager->getAllPairs('username');

        bdump($users);

        $this['userForm-user_id']->setItems($users);
    }

    public function renderEdit($id)
    {

    }

    /**
     * @param int $id
     */
    public function actionDelete($userId, $groupId)
    {
        $this->userGroupManager->deleteByLeftAndRight($groupId, $userId);
        $this->flashMessage('Delete_group_member');
        $this->redirect('Group:edit', $groupId);
    }

    /**
     * @return PhpBBForm
     */
    public function createComponentUserForm()
    {
        $form = new PhpBBForm();
        $form->setTranslator($this->translator);

        $form->addSelect('user_id', 'Username')
            ->setTranslator(null);
        $form->addSubmit('send', 'Submit');

        $form->onSuccess[] = [$this, 'userFormSuccess'];

        return $form;
    }

    public function userFormSuccess(Form $form, ArrayHash $values)
    {
        $this->userGroupManager->add(
            [
                'user_id' => $values->user_id,
                'group_id' => $this->getParameter('id')
            ]
        );

        $this->redirect(':Admin:Group:Group:edit', $this->getParameter('id'));
    }
}
