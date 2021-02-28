<?php

use Nette\Caching\Cache;
use Nette\Caching\IStorage;
use Nette\Utils\Finder;

/**
 * Class LanguagePresenter
 */
class LanguagePresenter
{
    /**
     * @var array $lang
     */
    private $lang;

    /**
     * @var IStorage $storage
     */
    private $storage;

    /**
     * @var string $rootPath
     */
    private $rootPath;

    /**
     * @var array $userData
     */
    private $userData;

    /**
     * LanguagePresenter constructor.
     *
     * @param IStorage $storage
     * @param array $lang
     * @param $userData
     */
    public function __construct(IStorage $storage, array $lang, $userData)
    {
        $sep = DIRECTORY_SEPARATOR;

        $this->userData = $userData;
        $this->lang = $lang;
        $this->storage = $storage;
        $this->rootPath = '.' . $sep . '..' . $sep;
    }

    public function renderDefault()
    {
        global $SID;

        $databaseLanguages = dibi::select('*')
            ->from(Tables::LANGUAGES_TABLE)
            ->fetchAll();

        $sep = DIRECTORY_SEPARATOR;
        $resultLanguages = [];
        $foundLanguages = Finder::findDirectories('lang_*')->in($this->rootPath . 'app'. $sep. 'language');

        $databaseLanguagesNames = [];

        foreach ($databaseLanguages as $language) {
            $databaseLanguagesNames[] = $language->lang_name;
        }

        /**
         * @var SplFileInfo $language
         */
        foreach ($foundLanguages as $language) {
            $resultLanguages[] = trim(str_replace('lang_', '', $language->getFilename()));
        }

        $canInstall = array_diff($resultLanguages, $databaseLanguagesNames);

        $latte = new LatteFactory($this->storage, $this->userData);

        $parameters = [
            'C_LANG_ID' => POST_LANG_URL,
            'C_MODE' => POST_MODE,

            'languages' => $databaseLanguages,

            'S_CAN_BE_INSTALLED' => $canInstall,

            'L_LANG_ID' => $this->lang['Lang_id'],
            'L_LANG_NAME' => $this->lang['Lang_name'],
            'L_CAN_BE_INSTALLED' => $this->lang['Lang_can_be_isntalled'],
            'L_INSTALL' => $this->lang['Install'],

            'L_YES' => $this->lang['Yes'],
            'L_NO' => $this->lang['No'],
            'L_DELETE' => $this->lang['Delete'],
            'L_INFO' => $this->lang['Info'],

            'S_SID' => $SID,
        ];

        $latte->render('admin/Languages/default.latte', $parameters);
    }

    public function renderInstall($id)
    {
        $neededFiles = [
            'lang_admin.php',
            'lang_bbcode.php',
            'lang_faq.php',
            'lang_main.php',
            'search_stopwords.txt',
            'search_synonyms.txt',
        ];

        $sep = DIRECTORY_SEPARATOR;
        $foundFiles = Finder::findFiles($neededFiles)->in($this->rootPath . 'app' . $sep . 'language' . $sep . 'lang_' . $id);
        $files = [];

        /**
         * @var SplFileInfo $file
         */
        foreach ($foundFiles as $file) {
            $files[] = $file->getFilename();
        }

        $common = array_intersect($files, $neededFiles);

        if (count(array_diff($neededFiles, $common))) {
            $message  = $this->lang['Installed_language_missing_files'] . '<br /><br />';
            $message .= sprintf($this->lang['Click_return_language'], '<a href="' . Session::appendSid('admin_languages.php') . '">', '</a>') . '<br /><br />';
            $message .= sprintf($this->lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

            message_die(GENERAL_MESSAGE, $message);
        }

        dibi::insert(Tables::LANGUAGES_TABLE,  ['lang_name' => $id,])->execute();

        $message  = $this->lang['Installed_language'] . '<br /><br />';
        $message .= sprintf($this->lang['Click_return_language'], '<a href="' . Session::appendSid('admin_languages.php') . '">', '</a>') . '<br /><br />';
        $message .= sprintf($this->lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

        message_die(GENERAL_MESSAGE, $message);
    }

    public function renderEdit($id)
    {
        $language = dibi::select('*')
            ->from(Tables::LANGUAGES_TABLE)
            ->where('[lang_id] = %i', $id)
            ->fetch();

        if (!$language) {
            $message  = $this->lang['Language_not_found'] . '<br /><br />';
            $message .= sprintf($this->lang['Click_return_language'], '<a href="' . Session::appendSid('admin_languages.php') . '">', '</a>') . '<br /><br />';
            $message .= sprintf($this->lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

            message_die(GENERAL_MESSAGE, $message);
        }

        $sep = DIRECTORY_SEPARATOR;

        $foundFiles = Finder::findFiles('*')->in($this->rootPath . 'app' . $sep . 'language' . $sep . 'lang_' . $language->lang_name);

        $latte = new LatteFactory($this->storage, $this->userData);

        $parameters = [
            'C_MODE' => POST_MODE,

            'L_LANG_FILE_NAME' => $this->lang['Language_file_name'],
            'L_LANG_FILE_SIZE' => $this->lang['Language_file_size'],
            'S_FILES' => $foundFiles
        ];

        $latte->render('admin/Languages/edit.latte', $parameters);
    }

    public function actionDelete($id)
    {
        $language = dibi::select('*')
            ->from(Tables::LANGUAGES_TABLE)
            ->where('[lang_id] = %i', $id)
            ->fetch();

        $replaceLang = dibi::select('*')
            ->from(Tables::LANGUAGES_TABLE)
            ->where('[lang_name] = %s', 'english')
            ->fetch();

        if ($replaceLang && $language->lang_id === $replaceLang->lang_id) {
            $replaceLang = dibi::select('*')
                ->from(Tables::LANGUAGES_TABLE)
                ->where('[lang_name] != %s', 'english')
                ->orderBy('lang_id', dibi::DESC)
                ->fetch();

            if (!$replaceLang) {
                dibi::update(Tables::USERS_TABLE, ['user_lang' => $language->lang_name])
                    ->where('[user_lang] = %s', $language->lang_name)
                    ->execute();

                dibi::update(Tables::CONFIG_TABLE, ['config_value' => $language->lang_name])
                    ->where('[config_name] = %s', 'default_lang')
                    ->execute();

                $cache = new Cache($this->storage, Tables::CONFIG_TABLE);
                $cache->remove(Tables::CONFIG_TABLE);

                $message  = $this->lang['language_no_replacement'] . '<br /><br />';
                $message .= sprintf($this->lang['Click_return_language'], '<a href="' . Session::appendSid('admin_languages.php') . '">', '</a>') . '<br /><br />';
                $message .= sprintf($this->lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

                message_die(GENERAL_MESSAGE, $message);
            }
        }

        if (!$replaceLang) {
            $replaceLang = dibi::select('*')
                ->from(Tables::LANGUAGES_TABLE)
                ->where('[lang_id] != %i', $language->lang_id)
                ->orderBy('lang_id', dibi::DESC)
                ->fetch();

            if (!$replaceLang) {
                dibi::update(Tables::USERS_TABLE, ['user_lang' => $language->lang_name])
                    ->where('[user_id] != %s', ANONYMOUS)
                    ->execute();

                dibi::update(Tables::CONFIG_TABLE, ['config_value' => $language->lang_name])
                    ->where('[config_name] = %s', 'default_lang')
                    ->execute();

                $cache = new Cache($this->storage, Tables::CONFIG_TABLE);
                $cache->remove(Tables::CONFIG_TABLE);

                $message  = $this->lang['language_no_replacement'] . '<br /><br />';
                $message .= sprintf($this->lang['Click_return_language'], '<a href="' . Session::appendSid('admin_languages.php') . '">', '</a>') . '<br /><br />';
                $message .= sprintf($this->lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

                message_die(GENERAL_MESSAGE, $message);
            }
        }

        dibi::delete(Tables::LANGUAGES_TABLE)
            ->where('[lang_id] = %i', $id)
            ->execute();

        dibi::update(Tables::USERS_TABLE, ['user_lang' => $replaceLang->lang_name])
            ->where('[user_lang] = %s', $language->lang_name)
            ->execute();

        $cache = new Cache($this->storage, Tables::CONFIG_TABLE);
        $cache->remove(Tables::CONFIG_TABLE);

        $message  = $this->lang['Delete_language'] . '<br /><br />';
        $message .= sprintf($this->lang['Click_return_language'], '<a href="' . Session::appendSid('admin_languages.php') . '">', '</a>') . '<br /><br />';
        $message .= sprintf($this->lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

        message_die(GENERAL_MESSAGE, $message);
    }
}

define('IN_PHPBB', 1);

//
// Load default header
//
$sep = DIRECTORY_SEPARATOR;
$phpbb_root_path = '.' . $sep . '..' . $sep;

require_once '.' . $sep . 'pagestart.php';

$mode = '';

if (isset($_POST[POST_MODE]) || isset($_GET[POST_MODE])) {
    $mode = isset($_POST[POST_MODE]) ? $_POST[POST_MODE] : $_GET[POST_MODE];
}

$languagePresenter = new LanguagePresenter($storage, $lang, $userdata);

switch ($mode) {
    case 'install':
        $languagePresenter->renderInstall($_GET[POST_LANG_URL]);

        break;
    case 'edit':
        $languagePresenter->renderEdit($_GET[POST_LANG_URL]);

        break;

    case 'delete':
        $languagePresenter->actionDelete($_GET[POST_LANG_URL]);
        break;

    case '':
    default:
        $languagePresenter->renderDefault();

    break;
}
