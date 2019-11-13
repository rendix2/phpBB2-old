<?php
/**
 *
 * @package attachment_mod
 * @version $Id: attachment_mod.php,v 1.6 2005/11/06 18:35:43 acydburn Exp $
 * @copyright (c) 2002 Meik Sievertsen
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *
 * Minimum Requirement: PHP 4.2.0
 */

use Nette\Caching\Cache;

/**
 */
if (!defined('IN_PHPBB')) {
    die('Hacking attempt');
}

$sep = DIRECTORY_SEPARATOR;

require_once $phpbb_root_path . 'attach_mod' . $sep . 'includes' . $sep . 'constants.php';
require_once $phpbb_root_path . 'attach_mod' . $sep . 'includes' . $sep . 'functions_includes.php';
require_once $phpbb_root_path . 'attach_mod' . $sep . 'includes' . $sep . 'functions_attach.php';
require_once $phpbb_root_path . 'attach_mod' . $sep . 'includes' . $sep . 'functions_delete.php';
require_once $phpbb_root_path . 'attach_mod' . $sep . 'includes' . $sep . 'functions_thumbs.php';
require_once $phpbb_root_path . 'attach_mod' . $sep . 'includes' . $sep . 'functions_filetypes.php';

if (defined('ATTACH_INSTALL')) {
    return;
}

/**
 * wrapper function for determining the correct language directory
 *
 * @param string $language_file
 *
 * @return
 */
function attach_mod_get_lang($language_file)
{
    global $phpbb_root_path, $attach_config, $board_config;

    $language = $board_config['default_lang'];

    if (file_exists($phpbb_root_path . 'language/lang_' . $language . '/' . $language_file . '.php')) {
        return $language;
    } else {
        $language = $attach_config['board_lang'];

        if (file_exists($phpbb_root_path . 'language/lang_' . $language . '/' . $language_file . '.php')) {
            return $language;
        } else {
            message_die(GENERAL_MESSAGE, 'Attachment Mod language file does not exist: language/lang_' . $language . '/' . $language_file . '.php');
        }
    }
}

/**
 * Include attachment mod language entries
 */
function include_attach_lang()
{
    global $phpbb_root_path;

    // from some reason its important to make it work :O
    global $lang;

    $sep = DIRECTORY_SEPARATOR;

    // Include Language
    $language = attach_mod_get_lang('lang_main_attach');
    require_once($phpbb_root_path . 'language' . $sep . 'lang_' . $language . $sep . 'lang_main_attach.php');

    if (defined('IN_ADMIN')) {
        $language = attach_mod_get_lang('lang_admin_attach');
        require_once($phpbb_root_path . 'language' . $sep . 'lang_' . $language . $sep . 'lang_admin_attach.php');
    }
}

/**
 * Get attachment mod configuration
 */
function get_config()
{
    global $board_config;

    $attach_config = dibi::select('*')
        ->from(Tables::ATTACH_CONFIG_TABLE)
        ->fetchPairs('config_name', 'config_value');

    // We assign the original default board language here, because it gets overwritten later with the users default language
    $attach_config['board_lang'] = trim($board_config['default_lang']);

    return $attach_config;
}

// Get Attachment Config
$cache = new Cache($storage, Tables::ATTACH_CONFIG_TABLE);
$key = Tables::ATTACH_CONFIG_TABLE;

$attach_config = $cache->load($key);

if (!$attach_config) {
    $attach_config = get_config();
    $cache->save($key, $attach_config);
}

// Please do not change the include-order, it is valuable for proper execution.
// Functions for displaying Attachment Things
require_once $phpbb_root_path . 'attach_mod' . $sep . 'displaying.php';

/**
 * Entry Point
 *
 * Posting Attachments Class (HAVE TO BE BEFORE PM)
 */
function execute_posting_attachment_handling()
{
    global $attachment_mod;

    $attachment_mod['posting'] = new attach_posting();
    $attachment_mod['posting']->posting_attachment_mod();
}

/**
 * Entry Point
 *
 * PM Attachments Class
 *
 * @param string $mode
 */
function execute_privmsgs_attachment_handling($mode)
{
    global $attachment_mod;

    $attachment_mod['pm'] = new attach_pm();

    if ($mode !== 'read') {
        $attachment_mod['pm']->privmsgs_attachment_mod($mode);
    }
}

$upload_dir = (int)$attach_config['allow_ftp_upload'] ? $attach_config['download_path'] : $attach_config['upload_dir'];

?>