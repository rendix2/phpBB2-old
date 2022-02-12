<?php
/**
 *
 * Created by PhpStorm.
 * Filename: PermissionPresenter.php
 * User: Tomáš Babický
 * Date: 08.03.2021
 * Time: 14:05
 */

namespace phpBB2\App\AdminModule\UserModule\Presenters;

use phpBB2\App\AdminModule\Presenters\AdminBasePresenter;
use phpBB2\App\Helpers\TemplateHelper;

/**
 * Class PermissionPresenter
 *
 * @package phpBB2\App\AdminModule\UserModule\Presenters
 */
class PermissionPresenter extends AdminBasePresenter
{
    public function formatTemplateFiles()
    {
        return TemplateHelper::getPath($this);
    }

    public function renderDefault($id)
    {
    }
}
