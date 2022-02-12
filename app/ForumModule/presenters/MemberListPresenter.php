<?php
/**
 *
 * Created by PhpStorm.
 * Filename: MemberListPresenter.php
 * User: Tomáš Babický
 * Date: 02.03.2021
 * Time: 17:32
 */

namespace phpBB2\App\ForumModule\Presenters;

use Nette\Application\UI\Presenter;
use phpBB2\Models\UsersManager;

/**
 * Class MemberListPresenter
 *
 * @package phpBB2\App\ForumModule\Presenters
 */
class MemberListPresenter extends Presenter
{
    /**
     * @var UsersManager $usersManager
     */
    private $usersManager;

    /**
     * MemberListPresenter constructor.
     *
     * @param UsersManager $usersManager
     */
    public function __construct(UsersManager $usersManager)
    {
        parent::__construct();

        $this->usersManager = $usersManager;
    }

    public function renderDefault()
    {
    }
}
