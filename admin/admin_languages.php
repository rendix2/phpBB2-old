<?php

use Nette\Caching\Cache;
use Nette\Utils\Finder;

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

switch ($mode) {
    case 'install':
        $neededFiles = [
          'lang_admin.php',
          'lang_bbcode.php',
          'lang_faq.php',
          'lang_main.php',
          'search_stopwords.txt',
          'search_synonyms.txt',
        ];

        $sep = DIRECTORY_SEPARATOR;
        $foundFiles = Finder::findFiles($neededFiles)->in($phpbb_root_path . 'language' . $sep . 'lang_' . $_GET[POST_LANG_URL]);
        $files = [];

        /**
         * @var SplFileInfo $file
         */
        foreach ($foundFiles as $file) {
            $files[] = $file->getFilename();
        }

        $common = array_intersect($files, $neededFiles);

        if (count(array_diff($neededFiles, $common))) {
            $message = $lang['Installed_language_missing_files'] . '<br /><br />' . sprintf($lang['Click_return_language'], '<a href="' . Session::appendSid('admin_languages.php') . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

            message_die(GENERAL_MESSAGE, $message);
        }

        dibi::insert(Tables::LANGUAGES_TABLE,  ['lang_name' => $_GET[POST_LANG_URL],])->execute();

        $message = $lang['Installed_language'] . '<br /><br />' . sprintf($lang['Click_return_language'], '<a href="' . Session::appendSid('admin_languages.php') . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

        message_die(GENERAL_MESSAGE, $message);

        break;
    case 'edit':
        $language = dibi::select('*')
            ->from(Tables::LANGUAGES_TABLE)
            ->where('[lang_id] = %i', $_GET[POST_LANG_URL])
            ->fetch();

        if (!$language) {
            $message = $lang['Language_not_found'] . '<br /><br />' . sprintf($lang['Click_return_language'], '<a href="' . Session::appendSid('admin_languages.php') . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

            message_die(GENERAL_MESSAGE, $message);
        }

        $foundFiles = Finder::findFiles('*')->in($phpbb_root_path . 'language' . $sep . 'lang_' . $language->lang_name);

        $latte = new LatteFactory($storage, $userdata);

        $parameters = [
            'C_MODE' => POST_MODE,

            'L_LANG_FILE_NAME' => $lang['Language_file_name'],
            'L_LANG_FILE_SIZE' => $lang['Language_file_size'],
            'S_FILES' => $foundFiles
        ];

        $latte->render('admin/language_edit.latte', $parameters);

        break;

    case 'delete':
        $language = dibi::select('*')
            ->from(Tables::LANGUAGES_TABLE)
            ->where('[lang_id] = %i', $_GET[POST_LANG_URL])
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

                $cache = new Cache($storage, Tables::CONFIG_TABLE);
                $cache->remove(Tables::CONFIG_TABLE);

                $message = $lang['language_no_replacement'] . '<br /><br />' . sprintf($lang['Click_return_language'], '<a href="' . Session::appendSid('admin_languages.php') . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

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

                $cache = new Cache($storage, Tables::CONFIG_TABLE);
                $cache->remove(Tables::CONFIG_TABLE);

                $message = $lang['language_no_replacement'] . '<br /><br />' . sprintf($lang['Click_return_language'], '<a href="' . Session::appendSid('admin_languages.php') . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

                message_die(GENERAL_MESSAGE, $message);
            }
        }

        dibi::delete(Tables::LANGUAGES_TABLE)
            ->where('[lang_id] = %i', $_GET[POST_LANG_URL])
            ->execute();

        dibi::update(Tables::USERS_TABLE, ['user_lang' => $replaceLang->lang_name])
            ->where('[user_lang] = %s', $language->lang_name)
            ->execute();

        $cache = new Cache($storage, Tables::CONFIG_TABLE);
        $cache->remove(Tables::CONFIG_TABLEBLE);

        $message = $lang['Delete_language'] . '<br /><br />' . sprintf($lang['Click_return_language'], '<a href="' . Session::appendSid('admin_languages.php') . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

	    message_die(GENERAL_MESSAGE, $message);
        break;

    case '':
    default:
        $databaseLanguages = dibi::select('*')
        ->from(Tables::LANGUAGES_TABLE)
        ->fetchAll();

        $resultLanguages = [];
        $foundLanguages = Finder::findDirectories('lang_*')->in($phpbb_root_path . 'language');

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

        $latte = new LatteFactory($storage, $userdata);

    $parameters = [
        'C_LANG_ID' => POST_LANG_URL,
        'C_MODE' => POST_MODE,

        'D_LANGUAGES' => $databaseLanguages,

        'S_CAN_BE_INSTALLED' => $canInstall,

        'L_LANG_ID' => $lang['Lang_id'],
        'L_LANG_NAME' => $lang['Lang_name'],
        'L_CAN_BE_INSTALLED' => $lang['Lang_can_be_isntalled'],
        'L_INSTALL' => $lang['Install'],

        'L_YES' => $lang['Yes'],
        'L_NO' => $lang['No'],
        'L_DELETE' => $lang['Delete'],
        'L_INFO' => $lang['Info'],

        'S_SID' => $SID,
    ];

    $latte->render('admin/languages_default.latte', $parameters);

    break;
}