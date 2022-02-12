<?php
/**
 *
 * Created by PhpStorm.
 * Filename: PrunePresenter.php
 * User: Tomáš Babický
 * Date: 22.03.2021
 * Time: 16:04
 */

namespace phpBB2\App\AdminModule\ForumModule\Presenters;

use phpBB2\App\AdminModule\Presenters\AdminBasePresenter;
use phpBB2\App\Helpers\TemplateHelper;

/**
 * Class PrunePresenter
 *
 * @package phpBB2\App\AdminModule\ForumModule\Presenters
 */
class PrunePresenter extends AdminBasePresenter
{
    public function formatTemplateFiles()
    {
        return TemplateHelper::getPath($this);
    }

    public function renderDefault($id)
    {
    }
}
