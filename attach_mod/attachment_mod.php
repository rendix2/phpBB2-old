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

require_once($phpbb_root_path . 'attach_mod/includes/constants.php');
require_once($phpbb_root_path . 'attach_mod/includes/functions_includes.php');
require_once($phpbb_root_path . 'attach_mod/includes/functions_attach.php');
require_once($phpbb_root_path . 'attach_mod/includes/functions_delete.php');
require_once($phpbb_root_path . 'attach_mod/includes/functions_thumbs.php');
require_once($phpbb_root_path . 'attach_mod/includes/functions_filetypes.php');

if (defined('ATTACH_INSTALL')) {
    return;
}

/**
 * wrapper function for determining the correct language directory
 */
function attach_mod_get_lang($language_file)
{
    global $phpbb_root_path, $attach_config, $board_config;

    $language = $board_config['default_lang'];

    if (!file_exists($phpbb_root_path . 'language/lang_' . $language . '/' . $language_file . '.php')) {
        $language = $attach_config['board_lang'];

        if (!file_exists($phpbb_root_path . 'language/lang_' . $language . '/' . $language_file . '.php')) {
            message_die(GENERAL_MESSAGE, 'Attachment Mod language file does not exist: language/lang_' . $language . '/' . $language_file . '.php');
        } else {
            return $language;
        }
    } else {
        return $language;
    }
}

/**
 * Include attachment mod language entries
 */
function include_attach_lang()
{
    global $phpbb_root_path, $lang, $board_config, $attach_config;

    // Include Language
    $language = attach_mod_get_lang('lang_main_attach');
    require_once($phpbb_root_path . 'language/lang_' . $language . '/lang_main_attach.php');

    if (defined('IN_ADMIN')) {
        $language = attach_mod_get_lang('lang_admin_attach');
        require_once($phpbb_root_path . 'language/lang_' . $language . '/lang_admin_attach.php');
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
$key   = Tables::ATTACH_CONFIG_TABLE;

$attach_config = $cache->load($key);

if (!$attach_config) {
    $attach_config = get_config();
    $cache->save($key, $attach_config);
}

// Please do not change the include-order, it is valuable for proper execution.
// Functions for displaying Attachment Things
require_once($phpbb_root_path . 'attach_mod/displaying.php');

// Posting Attachments Class (HAVE TO BE BEFORE PM)
require_once($phpbb_root_path . 'attach_mod/posting_attachments.php');

// PM Attachments Class
require_once($phpbb_root_path . 'attach_mod/pm_attachments.php');

if (!(int)$attach_config['allow_ftp_upload']) {
    $upload_dir = $attach_config['upload_dir'];
} else {
    $upload_dir = $attach_config['download_path'];
}

if (!function_exists('attach_mod_sql_escape')) {
    message_die(GENERAL_MESSAGE, 'You haven\'t correctly updated/installed the Attachment Mod.<br />You seem to forgot uploading a new file. Please refer to the update instructions for help and make sure you have uploaded every file correctly.');
}


?>