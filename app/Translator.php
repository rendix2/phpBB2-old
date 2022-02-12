<?php
/**
 *
 * Created by PhpStorm.
 * Filename: Translator.php
 * User: Tomáš Babický
 * Date: 03.03.2021
 * Time: 14:02
 */


namespace phpBB2\App;

use Nette\Localization\ITranslator;
use Nette\Utils\Finder;
use phpBB2\App\Services\LanguageService;

/**
 * Class Translator
 *
 * @package phpBB2\App
 */
class Translator implements ITranslator
{
    /**
     * @var array $translations
     */
    private $translations;

    /**
     * Translator constructor.
     *
     * @param LanguageService $languageService
     */
    public function __construct(LanguageService $languageService)
    {
        $sep = DIRECTORY_SEPARATOR;

        $path = __DIR__ . $sep . 'language' . $sep . 'lang_' . $languageService->getLanguage();

        $files = Finder::findFiles('*.php')->in($path);

        $allLang = [];

        include_once __DIR__ . $sep .'..'.$sep .'includes' . $sep . 'constants.php';

        foreach ($files as $file) {
            include_once $file->getPath() . $sep . $file->getFileName();

            $allLang += $lang;
        }

        $this->translations = $allLang;
    }

    function translate($message, $count = null)
    {
        return $this->translations[$message];
    }
}
