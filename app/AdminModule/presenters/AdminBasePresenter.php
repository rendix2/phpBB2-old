<?php
/**
 *
 * Created by PhpStorm.
 * Filename: AdminBasePresenter.php
 * User: Tomáš Babický
 * Date: 05.03.2021
 * Time: 15:08
 */

namespace phpBB2\App\AdminModule\Presenters;

use Nette\Application\Helpers;
use Nette\Application\UI\Presenter;
use Nette\Localization\ITranslator;
use phpBB2\App\Services\ThemeService;

/**
 * Class AdminBasePresenter
 *
 * @package phpBB2\App\AdminModule\Presenters
 */
class AdminBasePresenter extends Presenter
{
    /**
     * @var ThemeService $themeService
     * @inject
     */
    public $themeService;

    /**
     * @var ITranslator $translator
     * @inject
     *
     */
    public $translator;

    public function formatTemplateFiles()
    {
        [, $presenter] = Helpers::splitName($this->getName());
        $dir = dirname(self::getReflection()->getFileName());
        $dir = is_dir("$dir/templates") ? $dir : dirname($dir);

        $themeName = $this->themeService->getThemeName();

        $explodedName = explode(':', $this->getName());
        $countExplodedName = count($explodedName);
        $modules = [];

        for ($i = 1; $i < $countExplodedName - 1; $i++) {
            $modules[] = $explodedName[$i];
        }

        $modulePath = DIRECTORY_SEPARATOR;

        foreach ($modules as $module) {
            $modulePath .= $module . 'Module' . DIRECTORY_SEPARATOR;
        }

        return [
            "$dir$modulePath/templates/$themeName/$presenter/$this->view.latte"
        ];
    }

    public function beforeRender()
    {
        parent::beforeRender();

        $this->template->theme = $this->themeService->getThemeData();
        $this->template->setTranslator($this->translator);
    }

    public function getTranslator()
    {
        return $this->translator;
    }
}