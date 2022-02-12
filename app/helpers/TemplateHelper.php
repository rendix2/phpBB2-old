<?php
/**
 *
 * Created by PhpStorm.
 * Filename: TemplateHelper.php
 * User: Tomáš Babický
 * Date: 08.03.2021
 * Time: 14:08
 */

namespace phpBB2\App\Helpers;

use Nette\Application\Helpers;
use Nette\Application\IPresenter;

/**
 * Class TemplateHelper
 *
 * @package phpBB2\App\Helpers
 */
class TemplateHelper
{

    /**
     * @param IPresenter $IPresenter
     *
     * @return string[]
     */
    public static function getPath(IPresenter $IPresenter)
    {
        [, $presenter] = Helpers::splitName($IPresenter->getName());
        $dir = dirname($IPresenter::getReflection()->getFileName());
        $dir = is_dir("$dir/templates") ? $dir : dirname($dir);

        $themeName = $IPresenter->themeService->getThemeName();

        return [
            "$dir/templates/$themeName/$presenter/$IPresenter->view.latte"
        ];
    }
}
