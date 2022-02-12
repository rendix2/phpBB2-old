<?php
/**
 *
 * Created by PhpStorm.
 * Filename: GroupPresenter.php
 * User: Tomáš Babický
 * Date: 08.03.2021
 * Time: 1:17
 */

namespace phpBB2\App\AdminModule\GroupModule\Presenters;

use Nette\Application\UI\Form;
use Nette\Localization\ITranslator;
use Nette\Utils\ArrayHash;
use phpBB2\App\AdminModule\Presenters\AdminBasePresenter;
use phpBB2\App\Helpers\TemplateHelper;
use phpBB2\App\PhpBBForm;
use phpBB2\App\Services\ThemeService;
use phpBB2\Models\AuthAccessManager;
use phpBB2\Models\GroupsManager;
use phpBB2\Models\UserGroupManager;
use phpBB2\Models\UsersManager;

/**
 * Class GroupPresenter
 *
 * @package phpBB2\App\AdminModule\Presenters
 */
class GroupPresenter extends AdminBasePresenter
{
    /**
     * @var AuthAccessManager $authAccessManager
     */
    private $authAccessManager;

    /**
     * @var GroupsManager $groupsManager
     */
    private $groupsManager;

    /**
     * @var UsersManager $usersManager
     */
    private $usersManager;

    /**
     * @var UserGroupManager $userGroupManager
     */
    private UserGroupManager $userGroupManager;

    /**
     * GroupPresenter constructor.
     *
     * @param GroupsManager $groupsManager
     * @param UsersManager $usersManager
     */
    public function __construct(
        AuthAccessManager $authAccessManager,
        GroupsManager $groupsManager,
        UsersManager $usersManager,
        UserGroupManager $userGroupManager
    )
    {
        parent::__construct();

        $this->authAccessManager = $authAccessManager;
        $this->groupsManager = $groupsManager;
        $this->usersManager = $usersManager;
        $this->userGroupManager = $userGroupManager;
    }

    public function formatTemplateFiles()
    {
        return TemplateHelper::getPath($this);
    }

    public function renderDefault()
    {
        $groups = $this->groupsManager->getGroupsJoinedModerator();

        $this->template->groups = $groups;
        $this->template->manager = GroupsManager::class;
    }

    public function actionDelete($id)
    {
         $authMod = $this->authAccessManager->getAuthModByGroupId($id);

         if ($authMod === 1) {
             $users = $this->userGroupManager->getByLeft($id);

             foreach ($users as $user) {
                $groups =$this->groupsManager->getGroupsByModeratorCheckedPermissions($id, $user->user_id);

                if (count($groups)){
                    $this->usersManager->updateFluent(['user_level' => UsersManager::USER])
                        ->where('[user_level] = %i', UsersManager::MOD)
                        ->where('[user_id] = %i', (int)$user->user_id)
                        ->execute();
                }
             }
         }

         $this->groupsManager->deleteByPrimaryKey($id);
         $this->userGroupManager->deleteByLeft($id);
         $this->authAccessManager->deleteByGroup($id);

         $this->flashMessage('Deleted_group');
         $this->redirect('Group:default');
    }

    public function actionEdit($id)
    {
        $users = $this->usersManager->getAllPairs('username');

        $this['groupForm-group_moderator']->setItems($users);

        if ($id) {
            $group = $this->groupsManager->getByPrimaryKey($id);

            if (!$group) {
                $this->error($this->translator->translate('Group_not_exist'));
            }

            $this['groupForm']->setDefaults($group);
        }
    }

    public function renderEdit($id)
    {
        $group = $this->groupsManager->getByPrimaryKey($id);

        $this->template->group = $group;
    }

    public function createComponentGroupForm()
    {
        $form = new PhpBBForm();
        $form->setTranslator($this->translator);

        $form->addText('group_name', 'Group_name')
            ->setRequired('No_group_name');
        $form->addTextArea('group_description', 'Group_description');
        $form->addSelect('group_moderator', $this->translator->translate('Group_Moderator'))
            ->setTranslator(null)
            ->setRequired('No_group_moderator');

        $groupTypes = [
            GroupsManager::GROUP_CLOSED => 'Group_closed',
            GroupsManager::GROUP_OPEN => 'Group_open',
            GroupsManager::GROUP_HIDDEN => 'Group_hidden',
        ];

        $form->addRadioList('group_type', 'Group_type', $groupTypes)
            ->setRequired('');

        $form->addSubmit('send', 'Submit');

        $form->onSuccess[] = [$this, 'groupFormSuccess'];

        return $form;
    }

    public function groupFormSuccess(Form $form, ArrayHash $values)
    {
        $id = $this->getParameter('id');

        if ($id) {
            $group = $this->groupsManager->getByPrimaryKey($id);

            bdump($group->group_moderator, '$group->group_moderator');
            bdump($values->group_moderator, '$values->group_moderator');

            if ($group->group_moderator !== $values->group_moderator) {
                bdump('!% moderator');
                $this->userGroupManager->deleteByLeftAndRight($id, $group->group_moderator);

                $moderator = $this->userGroupManager->getByLeftAndRight($id, $group->group_moderator);

                if (!$moderator) {
                    $moderatorInsertData = [
                        'group_id'     => $id,
                        'user_id'      => $group->group_moderator,
                        'user_pending' => 0
                    ];

                    $this->userGroupManager->add($moderatorInsertData);
                }
            }

            $this->groupsManager->updateByPrimary($id, (array) $values);

            // TODO
            //attachment_quota_settings('group', $mode, $_POST['group_update']);

            $this->flashMessage('Updated_group');
        } else {
            $id = $this->groupsManager->add((array) $values);

            $userGroupInsertData = [
                'group_id' => $id,
                'user_id'  => $values->group_moderator,
                'user_pending' => 0
            ];

            // TODO
            //attachment_quota_settings('group', $mode, $_POST['group_update']);

            $this->userGroupManager->add($userGroupInsertData);
            $this->flashMessage('Added_new_group');
        }

        $this->redirect('Group:edit', $id);
    }
}
