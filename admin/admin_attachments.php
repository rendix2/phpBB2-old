<?php
/**
 *
 * @package attachment_mod
 * @version $Id: admin_attachments.php,v 1.3 2006/04/09 13:25:51 acydburn Exp $
 * @copyright (c) 2002 Meik Sievertsen
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *
 */

use Nette\Caching\Cache;
use phpBB2\Sync;

$sep = DIRECTORY_SEPARATOR;

// Let's set the root dir for phpBB
$phpbb_root_path = '..' . $sep;
require_once 'pagestart.php';

require_once $phpbb_root_path . 'attach_mod' . $sep . 'includes' . $sep . 'constants.php';
require_once $phpbb_root_path . 'includes' . $sep . 'functions_admin.php';
require_once $phpbb_root_path . 'attach_mod' . $sep . 'includes' . $sep . 'functions_attach.php';

if (!(int)$attach_config['allow_ftp_upload']) {
    if (($attach_config['upload_dir'][0] === '/') || (($attach_config['upload_dir'][0] !== '/') && ($attach_config['upload_dir'][1] === ':'))) {
        $upload_dir = $attach_config['upload_dir'];
    } else {
        $upload_dir = '../' . $attach_config['upload_dir'];
    }
} else {
    $upload_dir = $attach_config['download_path'];
}

require_once $phpbb_root_path . 'attach_mod' . $sep . 'includes' . $sep . 'functions_selects.php';
require_once $phpbb_root_path . 'attach_mod' . $sep . 'includes' . $sep . 'functions_admin.php';

// Check if the language got included
if (!isset($lang['Test_settings_successful'])) {
    // include_once is used within the function
    include_attach_lang();
}

// Init Vars
$mode = get_var('mode', '');
$e_mode = get_var('e_mode', '');
$size = get_var('size', '');
$quota_size = get_var('quota_size', '');
$pm_size = get_var('pm_size', '');

$submit = isset($_POST['submit']);
$check_upload = isset($_POST['settings']);
$check_image_cat = isset($_POST['cat_settings']);
$search_imagick = isset($_POST['search_imagick']);
$error = null;

// Re-evaluate the Attachment Configuration
$rows = dibi::select('*')
    ->from(Tables::ATTACH_CONFIG_TABLE)
    ->fetchAll();

foreach ($rows as $row) {
    $config_name = $row->config_name;
    $config_value = $row->config_value;

    $new_attach[$config_name] = get_var($config_name, trim($attach_config[$config_name]));

    if (!$size && !$submit && $config_name === 'max_filesize') {
        $size = ($attach_config[$config_name] >= 1048576) ? 'mb' : (($attach_config[$config_name] >= 1024) ? 'kb' : 'b');
    }

    if (!$quota_size && !$submit && $config_name === 'attachment_quota') {
        $quota_size = ($attach_config[$config_name] >= 1048576) ? 'mb' : (($attach_config[$config_name] >= 1024) ? 'kb' : 'b');
    }

    if (!$pm_size && !$submit && $config_name === 'max_filesize_pm') {
        $pm_size = ($attach_config[$config_name] >= 1048576) ? 'mb' : (($attach_config[$config_name] >= 1024) ? 'kb' : 'b');
    }

    if (!$submit && ($config_name === 'max_filesize' || $config_name === 'attachment_quota' || $config_name === 'max_filesize_pm')) {
        if ($new_attach[$config_name] >= 1048576) {
            $new_attach[$config_name] = round($new_attach[$config_name] / 1048576 * 100) / 100;
        } else if ($new_attach[$config_name] >= 1024) {
            $new_attach[$config_name] = round($new_attach[$config_name] / 1024 * 100) / 100;
        }
    }

    if ($submit && ($mode === 'manage' || $mode === 'cats')) {
        if ($config_name === 'max_filesize') {
            $old = $new_attach[$config_name];
            $new_attach[$config_name] = ($size === 'kb') ? round($new_attach[$config_name] * 1024) : (($size === 'mb') ? round($new_attach[$config_name] * 1048576) : $new_attach[$config_name]);
        }

        if ($config_name === 'attachment_quota') {
            $old = $new_attach[$config_name];
            $new_attach[$config_name] = ($quota_size === 'kb') ? round($new_attach[$config_name] * 1024) : (($quota_size === 'mb') ? round($new_attach[$config_name] * 1048576) : $new_attach[$config_name]);
        }

        if ($config_name === 'max_filesize_pm') {
            $old = $new_attach[$config_name];
            $new_attach[$config_name] = ($pm_size === 'kb') ? round($new_attach[$config_name] * 1024) : (($pm_size === 'mb') ? round($new_attach[$config_name] * 1048576) : $new_attach[$config_name]);
        }

        if ($config_name === 'ftp_server' || $config_name === 'ftp_path' || $config_name === 'download_path') {
            $value = trim($new_attach[$config_name]);

            if ($value[mb_strlen($value) - 1] === '/') {
                $value[mb_strlen($value) - 1] = ' ';
            }

            $new_attach[$config_name] = trim($value);
        }

        if ($config_name === 'max_filesize') {
            $old_size = $attach_config[$config_name];
            $new_size = $new_attach[$config_name];

            if ($old_size !== $new_size) {
                // See, if we have a similar value of old_size in Mime Groups. If so, update these values.
                dibi::update(Tables::ATTACH_EXTENSION_GROUPS_TABLE, ['max_filesize' => (int)$new_size])
                    ->where('[max_filesize] = %i', (int)$old_size)
                    ->execute();
            }

            dibi::update(Tables::ATTACH_CONFIG_TABLE, ['config_value' => $new_attach[$config_name]])
                ->where('[config_name] = %s', $config_name)
                ->execute();
        } else {
            dibi::update(Tables::ATTACH_CONFIG_TABLE, ['config_value' => $new_attach[$config_name]])
                ->where('[config_name] = %s', $config_name)
                ->execute();
        }

        if ($config_name === 'max_filesize' || $config_name === 'attachment_quota' || $config_name === 'max_filesize_pm') {
            $new_attach[$config_name] = $old;
        }
    }
}

$cache = new Cache($storage, Tables::ATTACH_CONFIG_TABLE);
$key = Tables::ATTACH_CONFIG_TABLE;

$cache->remove($key);

$select_size_mode = size_select('size', $size);
$select_quota_size_mode = size_select('quota_size', $quota_size);
$select_pm_size_mode = size_select('pm_size', $pm_size);

// Search Imagick
if ($search_imagick) {
    $imagick = '';

    if (false !== strpos($imagick, "convert")) {
        return true;
    } else if ($imagick !== 'none') {
        if (false === strpos(PHP_OS, "WIN")) {
            $retval = @exec('whereis convert');
            $paths = explode(' ', $retval);

            if (is_array($paths)) {
                foreach ($paths as $path) {
                    $path = basename($path);

                    if ($path === 'convert') {
                        $imagick = $path;
                    }
                }
            }
        } else if (false !== strpos(PHP_OS, "WIN")) {
            $path = 'c:/imagemagick/convert.exe';

            if (@file_exists(@amod_realpath($path))) {
                $imagick = $path;
            }
        }
    }

    if (@file_exists(@amod_realpath(trim($imagick)))) {
        $new_attach['img_imagick'] = trim($imagick);
    } else {
        $new_attach['img_imagick'] = '';
    }
}

// Check Settings
if ($check_upload) {
    // Some tests...
    $attach_config = dibi::select('*')
        ->from(Tables::ATTACH_CONFIG_TABLE)
        ->fetchPairs('config_name', 'config_value');

    if ($attach_config['upload_dir'][0] === '/' || ($attach_config['upload_dir'][0] !== '/' && $attach_config['upload_dir'][1] === ':')) {
        $upload_dir = $attach_config['upload_dir'];
    } else {
        $upload_dir = $phpbb_root_path . $attach_config['upload_dir'];
    }

    $error = false;

    // Does the target directory exist, is it a directory and writeable. (only test if ftp upload is disabled)
    if ((int)$attach_config['allow_ftp_upload'] === 0) {
        if (!@file_exists(@amod_realpath($upload_dir))) {
            $error = true;
            $error_msg = sprintf($lang['Directory_does_not_exist'], $attach_config['upload_dir']) . '<br />';
        }

        if (!$error && !is_dir($upload_dir)) {
            $error = true;
            $error_msg = sprintf($lang['Directory_is_not_a_dir'], $attach_config['upload_dir']) . '<br />';
        }

        if (!$error) {
            if (!($fp = @fopen($upload_dir . '/0_000000.000', 'wb'))) {
                $error = true;
                $error_msg = sprintf($lang['Directory_not_writeable'], $attach_config['upload_dir']) . '<br />';
            } else {
                @fclose($fp);
                unlink_attach($upload_dir . '/0_000000.000');
            }
        }
    } else {
        // Check FTP Settings
        $server = (empty($attach_config['ftp_server'])) ? 'localhost' : $attach_config['ftp_server'];

        $conn_id = @ftp_connect($server);

        if (!$conn_id) {
            $error = true;
            $error_msg = sprintf($lang['Ftp_error_connect'], $server) . '<br />';
        }

        $login_result = @ftp_login($conn_id, $attach_config['ftp_user'], $attach_config['ftp_pass']);

        if ((!$login_result) && (!$error)) {
            $error = true;
            $error_msg = sprintf($lang['Ftp_error_login'], $attach_config['ftp_user']) . '<br />';
        }

        if (!@ftp_pasv($conn_id, (int)$attach_config['ftp_pasv_mode'])) {
            $error = true;
            $error_msg = $lang['Ftp_error_pasv_mode'];
        }

        if (!$error) {
            // Check Upload
            $tmpfname = @tempnam('/tmp', 't0000');

            @unlink($tmpfname); // unlink for safety on php4.0.3+

            $fp = @fopen($tmpfname, 'wb');

            @fwrite($fp, 'test');

            @fclose($fp);

            $result = @ftp_chdir($conn_id, $attach_config['ftp_path']);

            if (!$result) {
                $error = true;
                $error_msg = sprintf($lang['Ftp_error_path'], $attach_config['ftp_path']) . '<br />';
            } else {
                $res = @ftp_put($conn_id, 't0000', $tmpfname, FTP_ASCII);

                if (!$res) {
                    $error = true;
                    $error_msg = sprintf($lang['Ftp_error_upload'], $attach_config['ftp_path']) . '<br />';
                } else {
                    $res = @ftp_delete($conn_id, 't0000');

                    if (!$res) {
                        $error = true;
                        $error_msg = sprintf($lang['Ftp_error_delete'], $attach_config['ftp_path']) . '<br />';
                    }
                }
            }

            @ftp_close($conn_id);

            @unlink($tmpfname);
        }
    }

    if (!$error) {
        $message = $lang['Test_settings_successful'] . '<br /><br />';
        $message .= sprintf($lang['Click_return_attach_config'], '<a href="' . Session::appendSid('admin_attachments.php?mode=manage') . '">', '</a>') . '<br /><br />';
        $message .= sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

        message_die(GENERAL_MESSAGE, $message);
    }
}

// Management
if ($submit && $mode === 'manage' && !$error) {
    $message  = $lang['Attach_config_updated'] . '<br /><br />';
    $message .= sprintf($lang['Click_return_attach_config'], '<a href="' . Session::appendSid('admin_attachments.php?mode=manage') . '">', '</a>') . '<br /><br />';
    $message .= sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

    message_die(GENERAL_MESSAGE, $message);
}

if ($mode === 'manage') {
    $template->setFileNames(['body' => 'admin/attach_manage_body.tpl']);

    $yes_no_switches = ['disable_mod', 'allow_pm_attach', 'allow_ftp_upload', 'attachment_topic_review', 'display_order', 'show_apcp', 'ftp_pasv_mode'];

    foreach ($yes_no_switches as $variable) {
        eval('$' . $variable . "_yes = \$new_attach['" . $variable . "'] !== '0' ? 'checked=\"checked\"' : '';");
        eval('$' . $variable . "_no = \$new_attach['" . $variable . "'] === '0' ? 'checked=\"checked\"' : '';");
    }

    if (function_exists('ftp_connect')) {
        $template->assignBlockVars('switch_ftp', []);
    } else {
        $template->assignBlockVars('switch_no_ftp', []);
    }

    $template->assignVars(
        [
            'L_MANAGE_TITLE' => $lang['Attach_settings'],
            'L_MANAGE_EXPLAIN' => $lang['Manage_attachments_explain'],
            'L_ATTACHMENT_SETTINGS' => $lang['Attach_settings'],
            'L_ATTACHMENT_FILESIZE_SETTINGS' => $lang['Attach_filesize_settings'],
            'L_ATTACHMENT_NUMBER_SETTINGS' => $lang['Attach_number_settings'],
            'L_ATTACHMENT_OPTIONS_SETTINGS' => $lang['Attach_options_settings'],
            'L_ATTACHMENT_FTP_SETTINGS' => $lang['ftp_info'],
            'L_NO_FTP_EXTENSIONS' => $lang['No_ftp_extensions_installed'],
            'L_UPLOAD_DIR' => $lang['Upload_directory'],
            'L_UPLOAD_DIR_EXPLAIN' => $lang['Upload_directory_explain'],
            'L_ATTACHMENT_IMG_PATH' => $lang['Attach_img_path'],
            'L_IMG_PATH_EXPLAIN' => $lang['Attach_img_path_explain'],
            'L_ATTACHMENT_TOPIC_ICON' => $lang['Attach_topic_icon'],
            'L_TOPIC_ICON_EXPLAIN' => $lang['Attach_topic_icon_explain'],
            'L_DISPLAY_ORDER' => $lang['Attach_display_order'],
            'L_DISPLAY_ORDER_EXPLAIN' => $lang['Attach_display_order_explain'],
            'L_YES' => $lang['Yes'],
            'L_NO' => $lang['No'],
            'L_DESC' => $lang['Sort_Descending'],
            'L_ASC' => $lang['Sort_Ascending'],
            'L_SUBMIT' => $lang['Submit'],
            'L_RESET' => $lang['Reset'],
            'L_MAX_FILESIZE' => $lang['Max_filesize_attach'],
            'L_MAX_FILESIZE_EXPLAIN' => $lang['Max_filesize_attach_explain'],
            'L_ATTACH_QUOTA' => $lang['Attach_quota'],
            'L_ATTACH_QUOTA_EXPLAIN' => $lang['Attach_quota_explain'],
            'L_DEFAULT_QUOTA_LIMIT' => $lang['Default_quota_limit'],
            'L_DEFAULT_QUOTA_LIMIT_EXPLAIN' => $lang['Default_quota_limit_explain'],
            'L_MAX_FILESIZE_PM' => $lang['Max_filesize_pm'],
            'L_MAX_FILESIZE_PM_EXPLAIN' => $lang['Max_filesize_pm_explain'],
            'L_MAX_ATTACHMENTS' => $lang['Max_attachments'],
            'L_MAX_ATTACHMENTS_EXPLAIN' => $lang['Max_attachments_explain'],
            'L_MAX_ATTACHMENTS_PM' => $lang['Max_attachments_pm'],
            'L_MAX_ATTACHMENTS_PM_EXPLAIN' => $lang['Max_attachments_pm_explain'],
            'L_DISABLE_MOD' => $lang['Disable_mod'],
            'L_DISABLE_MOD_EXPLAIN' => $lang['Disable_mod_explain'],
            'L_PM_ATTACH' => $lang['PM_Attachments'],
            'L_PM_ATTACH_EXPLAIN' => $lang['PM_Attachments_explain'],
            'L_FTP_UPLOAD' => $lang['Ftp_upload'],
            'L_FTP_UPLOAD_EXPLAIN' => $lang['Ftp_upload_explain'],
            'L_ATTACHMENT_TOPIC_REVIEW' => $lang['Attachment_topic_review'],
            'L_ATTACHMENT_TOPIC_REVIEW_EXPLAIN' => $lang['Attachment_topic_review_explain'],
            'L_ATTACHMENT_FTP_PATH' => $lang['Attach_ftp_path'],
            'L_ATTACHMENT_FTP_USER' => $lang['ftp_username'],
            'L_ATTACHMENT_FTP_PASS' => $lang['ftp_password'],
            'L_ATTACHMENT_FTP_PATH_EXPLAIN' => $lang['Attach_ftp_path_explain'],
            'L_ATTACHMENT_FTP_SERVER' => $lang['Ftp_server'],
            'L_ATTACHMENT_FTP_SERVER_EXPLAIN' => $lang['Ftp_server_explain'],
            'L_FTP_PASSIVE_MODE' => $lang['Ftp_passive_mode'],
            'L_FTP_PASSIVE_MODE_EXPLAIN' => $lang['Ftp_passive_mode_explain'],
            'L_DOWNLOAD_PATH' => $lang['Ftp_download_path'],
            'L_DOWNLOAD_PATH_EXPLAIN' => $lang['Ftp_download_path_explain'],
            'L_SHOW_APCP' => $lang['Show_apcp'],
            'L_SHOW_APCP_EXPLAIN' => $lang['Show_apcp_explain'],
            'L_TEST_SETTINGS' => $lang['Test_settings'],

            'S_ATTACH_ACTION' => Session::appendSid('admin_attachments.php?mode=manage'),
            'S_FILESIZE' => $select_size_mode,
            'S_FILESIZE_QUOTA' => $select_quota_size_mode,
            'S_FILESIZE_PM' => $select_pm_size_mode,
            'S_DEFAULT_UPLOAD_LIMIT' => default_quota_limit_select('default_upload_quota', (int)trim($new_attach['default_upload_quota'])),
            'S_DEFAULT_PM_LIMIT' => default_quota_limit_select('default_pm_quota', (int)trim($new_attach['default_pm_quota'])),
            'L_UPLOAD_QUOTA' => $lang['Upload_quota'],
            'L_PM_QUOTA' => $lang['Pm_quota'],

            'UPLOAD_DIR' => $new_attach['upload_dir'],
            'ATTACHMENT_IMG_PATH' => $new_attach['upload_img'],
            'TOPIC_ICON' => $new_attach['topic_icon'],
            'MAX_FILESIZE' => $new_attach['max_filesize'],
            'ATTACHMENT_QUOTA' => $new_attach['attachment_quota'],
            'MAX_FILESIZE_PM' => $new_attach['max_filesize_pm'],
            'MAX_ATTACHMENTS' => $new_attach['max_attachments'],
            'MAX_ATTACHMENTS_PM' => $new_attach['max_attachments_pm'],
            'FTP_SERVER' => $new_attach['ftp_server'],
            'FTP_PATH' => $new_attach['ftp_path'],
            'FTP_USER' => $new_attach['ftp_user'],
            'FTP_PASS' => $new_attach['ftp_pass'],
            'DOWNLOAD_PATH' => $new_attach['download_path'],
            'DISABLE_MOD_YES' => $disable_mod_yes,
            'DISABLE_MOD_NO' => $disable_mod_no,
            'PM_ATTACH_YES' => $allow_pm_attach_yes,
            'PM_ATTACH_NO' => $allow_pm_attach_no,
            'FTP_UPLOAD_YES' => $allow_ftp_upload_yes,
            'FTP_UPLOAD_NO' => $allow_ftp_upload_no,
            'FTP_PASV_MODE_YES' => $ftp_pasv_mode_yes,
            'FTP_PASV_MODE_NO' => $ftp_pasv_mode_no,
            'TOPIC_REVIEW_YES' => $attachment_topic_review_yes,
            'TOPIC_REVIEW_NO' => $attachment_topic_review_no,
            'DISPLAY_ORDER_ASC' => $display_order_yes,
            'DISPLAY_ORDER_DESC' => $display_order_no,
            'SHOW_APCP_YES' => $show_apcp_yes,
            'SHOW_APCP_NO' => $show_apcp_no
        ]
    );
}

// Shadow Attachments
if ($submit && $mode === 'shadow') {
    // Delete Attachments from file system...
    $attach_file_list = get_var('attach_file_list', ['']);

    foreach ($attach_file_list as $value) {
        unlink_attach($value);
        unlink_attach($value, MODE_THUMBNAIL);
    }

    // Delete Attachments from table...
    $attach_id_list = get_var('attach_id_list', [0]);

    dibi::delete(Tables::ATTACH_ATTACHMENTS_DESC_TABLE)
        ->where('[attach_id] IN %in', $attach_id_list)
        ->execute();

    dibi::delete(Tables::ATTACH_ATTACHMENT_TABLE)
        ->where('[attach_id] IN %in', $attach_id_list)
        ->execute();

    $message  = $lang['Attach_config_updated'] . '<br /><br />';
    $message .= sprintf($lang['Click_return_attach_config'], '<a href="' . Session::appendSid('admin_attachments.php?mode=shadow') . '">', '</a>') . '<br /><br />';
    $message .= sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

    message_die(GENERAL_MESSAGE, $message);
}

$shadow_attachments = [];
$shadow_row = [];

if ($mode === 'shadow') {
    @set_time_limit(0);

    // Shadow Attachments
    $template->setFileNames(['body' => 'admin/attach_shadow.tpl']);

    $shadow_attachments = [];
    $shadow_row = [];

    $template->assignVars(
        [
            'L_SHADOW_TITLE' => $lang['Shadow_attachments'],
            'L_SHADOW_EXPLAIN' => $lang['Shadow_attachments_explain'],
            'L_EXPLAIN_FILE' => $lang['Shadow_attachments_file_explain'],
            'L_EXPLAIN_ROW' => $lang['Shadow_attachments_row_explain'],
            'L_ATTACHMENT' => $lang['Attachment'],
            'L_COMMENT' => $lang['File_comment'],
            'L_DELETE' => $lang['Delete'],
            'L_DELETE_MARKED' => $lang['Delete_marked'],
            'L_MARK_ALL' => $lang['Mark_all'],
            'L_UNMARK_ALL' => $lang['Unmark_all'],

            'S_HIDDEN' => $hidden,
            'S_ATTACH_ACTION' => Session::appendSid('admin_attachments.php?mode=shadow')
        ]
    );

    $table_attachments = [];
    $assign_attachments = [];
    $file_attachments = [];

    // collect all attachments in attach-table
    $rows = dibi::select(['attach_id', 'physical_filename', 'comment'])
        ->from(Tables::ATTACH_ATTACHMENTS_DESC_TABLE)
        ->orderBy('attach_id')
        ->fetchAll();

    foreach ($rows as $row) {
        $table_attachments['attach_id'][] = (int)$row->attach_id;
        $table_attachments['physical_filename'][] = basename($row->physical_filename);
        $table_attachments['comment'][] = $row->comment;
    }

    $assign_attachments = dibi::select('attach_id')
        ->from(Tables::ATTACH_ATTACHMENT_TABLE)
        ->groupBy('attach_id')
        ->fetchPairs(null, 'attach_id');

    // collect all attachments on file-system
    $file_attachments = collect_attachments();

    $shadow_attachments = [];
    $shadow_row = [];

    // Now determine the needed Informations

    // Go through all Files on the filespace and see if all are stored within the DB
    foreach ($file_attachments as &$fileAttachment) {
        if (count($table_attachments['attach_id']) > 0) {
            if ($fileAttachment !== '' && !in_array(trim($fileAttachment), $table_attachments['physical_filename'], true)) {
                $shadow_attachments[] = trim($fileAttachment);// Delete this file from the file_attachments to not have double assignments in next steps
                $fileAttachment = '';
            }
        } elseif ($fileAttachment !== '') {
            $shadow_attachments[] = trim($fileAttachment);
            // Delete this file from the file_attachments to not have double assignments in next steps
            $fileAttachment = '';
        }
    }

    unset($fileAttachment);

    // Now look for Attachment ID's defined for posts or topics but not defined at the Attachments Description Table
    foreach ($assign_attachments as $assignAttachment) {
        if (!in_array($assignAttachment, $table_attachments['attach_id'], true)) {
            $shadow_row['attach_id'][] = $assignAttachment;
            $shadow_row['physical_filename'][] = $assignAttachment;
            $shadow_row['comment'][] = $lang['Empty_file_entry'];
        }
    }

    // Go through the Database and get those Files not stored at the Filespace
    foreach ($table_attachments['attach_id'] as $i => $tableAttachment) {
        $inArray = in_array(trim($table_attachments['physical_filename'][$i]), $file_attachments, true);

        if (($table_attachments['physical_filename'][$i] !== '') && !$inArray) {
            $shadow_row['attach_id'][] = $table_attachments['attach_id'][$i];
            $shadow_row['physical_filename'][] = trim($table_attachments['physical_filename'][$i]);
            $shadow_row['comment'][] = $table_attachments['comment'][$i];

            // Delete this entry from the table_attachments, to not interfere with the next step
            $table_attachments['attach_id'][$i] = 0;
            $table_attachments['physical_filename'][$i] = '';
            $table_attachments['comment'][$i] = '';
        }
    }

    // Now look at the missing posts and PM's
    foreach ($table_attachments['attach_id'] as $i => $tableAttachment) {
        if ($table_attachments['attach_id'][$i] && !entry_exists($table_attachments['attach_id'][$i])) {
            $shadow_row['attach_id'][] = $table_attachments['attach_id'][$i];
            $shadow_row['physical_filename'][] = trim($table_attachments['physical_filename'][$i]);
            $shadow_row['comment'][] = $table_attachments['comment'][$i];
        }
    }
}

foreach ($shadow_attachments as $shadow_attachment) {
    $template->assignBlockVars('file_shadow_row',
        [
            'ATTACH_ID'       => $shadow_attachment,
            'ATTACH_FILENAME' => $shadow_attachment,
            'ATTACH_COMMENT'  => $lang['No_file_comment_available'],
            'U_ATTACHMENT'    => $upload_dir . '/' . basename($shadow_attachment)
        ]
    );
}

if (isset($shadow_row['attach_id'])) {
    foreach ($shadow_row['attach_id'] as $i => $shadowRow) {
        $template->assignBlockVars('table_shadow_row',
            [
                'ATTACH_ID'       => $shadow_row['attach_id'][$i],
                'ATTACH_FILENAME' => basename($shadow_row['physical_filename'][$i]),
                'ATTACH_COMMENT'  => trim($shadow_row['comment'][$i]) === '' ? $lang['No_file_comment_available'] : trim($shadow_row['comment'][$i])
            ]
        );
    }
}

if ($submit && $mode === 'cats' && !$error) {
    $message  = $lang['Attach_config_updated'] . '<br /><br />';
    $message .= sprintf($lang['Click_return_attach_config'], '<a href="' . Session::appendSid('admin_attachments.php?mode=cats') . '">', '</a>') . '<br /><br />';
    $message .= sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

    message_die(GENERAL_MESSAGE, $message);
}

if ($mode === 'cats') {
    $template->setFileNames(['body' => 'admin/attach_cat_body.tpl']);

    $s_assigned_group_images = $lang['None'];
    $s_assigned_group_streams = $lang['None'];
    $s_assigned_group_flash = $lang['None'];

    $s_assigned_group_images = [];
    $s_assigned_group_streams = [];
    $s_assigned_group_flash = [];

    $rows = dibi::select(['group_name', 'cat_id'])
        ->from(Tables::ATTACH_EXTENSION_GROUPS_TABLE)
        ->where('[cat_id] > %i', 0)
        ->orderBy('cat_id')
        ->fetchAll();

    foreach ($rows as $row) {
        if ($row->cat_id === IMAGE_CAT) {
            $s_assigned_group_images[] = $row->group_name;
        } else if ($row->cat_id === STREAM_CAT) {
            $s_assigned_group_streams[] = $row->group_name;
        } else if ($row->cat_id === SWF_CAT) {
            $s_assigned_group_flash[] = $row->group_name;
        }
    }

    $display_inlined_yes = $new_attach['img_display_inlined'] !== '0' ? 'checked="checked"' : '';
    $display_inlined_no = $new_attach['img_display_inlined'] === '0' ? 'checked="checked"' : '';

    $create_thumbnail_yes = $new_attach['img_create_thumbnail'] !== '0' ? 'checked="checked"' : '';
    $create_thumbnail_no = $new_attach['img_create_thumbnail'] === '0' ? 'checked="checked"' : '';

    $use_gd2_yes = $new_attach['use_gd2'] !== '0' ? 'checked="checked"' : '';
    $use_gd2_no = $new_attach['use_gd2'] === '0' ? 'checked="checked"' : '';

    // Check Thumbnail Support
    if (!is_imagick() && !@extension_loaded('gd')) {
        $new_attach['img_create_thumbnail'] = '0';
    } else {
        $template->assignBlockVars('switch_thumbnail_support', []);
    }

    $template->assignVars(
        [
            'L_MANAGE_CAT_TITLE' => $lang['Manage_categories'],
            'L_MANAGE_CAT_EXPLAIN' => $lang['Manage_categories_explain'],
            'L_SETTINGS_CAT_IMAGES' => $lang['Settings_cat_images'],
            'L_SETTINGS_CAT_STREAM' => $lang['Settings_cat_streams'],
            'L_SETTINGS_CAT_FLASH' => $lang['Settings_cat_flash'],
            'L_ASSIGNED_GROUP' => $lang['Assigned_group'],

            'L_DISPLAY_INLINED' => $lang['Display_inlined'],
            'L_DISPLAY_INLINED_EXPLAIN' => $lang['Display_inlined_explain'],
            'L_MAX_IMAGE_SIZE' => $lang['Max_image_size'],
            'L_MAX_IMAGE_SIZE_EXPLAIN' => $lang['Max_image_size_explain'],
            'L_IMAGE_LINK_SIZE' => $lang['Image_link_size'],
            'L_IMAGE_LINK_SIZE_EXPLAIN' => $lang['Image_link_size_explain'],
            'L_CREATE_THUMBNAIL' => $lang['Image_create_thumbnail'],
            'L_CREATE_THUMBNAIL_EXPLAIN' => $lang['Image_create_thumbnail_explain'],
            'L_MIN_THUMB_FILESIZE' => $lang['Image_min_thumb_filesize'],
            'L_MIN_THUMB_FILESIZE_EXPLAIN' => $lang['Image_min_thumb_filesize_explain'],
            'L_IMAGICK_PATH' => $lang['Image_imagick_path'],
            'L_IMAGICK_PATH_EXPLAIN' => $lang['Image_imagick_path_explain'],
            'L_SEARCH_IMAGICK' => $lang['Image_search_imagick'],
            'L_BYTES' => $lang['Bytes'],
            'L_TEST_SETTINGS' => $lang['Test_settings'],
            'L_YES' => $lang['Yes'],
            'L_NO' => $lang['No'],
            'L_SUBMIT' => $lang['Submit'],
            'L_RESET' => $lang['Reset'],
            'L_USE_GD2' => $lang['Use_gd2'],
            'L_USE_GD2_EXPLAIN' => $lang['Use_gd2_explain'],

            'IMAGE_MAX_HEIGHT' => $new_attach['img_max_height'],
            'IMAGE_MAX_WIDTH' => $new_attach['img_max_width'],

            'IMAGE_LINK_HEIGHT' => $new_attach['img_link_height'],
            'IMAGE_LINK_WIDTH' => $new_attach['img_link_width'],
            'IMAGE_MIN_THUMB_FILESIZE' => $new_attach['img_min_thumb_filesize'],
            'IMAGE_IMAGICK_PATH' => $new_attach['img_imagick'],

            'DISPLAY_INLINED_YES' => $display_inlined_yes,
            'DISPLAY_INLINED_NO' => $display_inlined_no,

            'CREATE_THUMBNAIL_YES' => $create_thumbnail_yes,
            'CREATE_THUMBNAIL_NO' => $create_thumbnail_no,

            'USE_GD2_YES' => $use_gd2_yes,
            'USE_GD2_NO' => $use_gd2_no,

            'S_ASSIGNED_GROUP_IMAGES' => implode(', ', $s_assigned_group_images),
            'S_ATTACH_ACTION' => Session::appendSid('admin_attachments.php?mode=cats')
        ]
    );
}

// Check Cat Settings
if ($check_image_cat) {
    // Some tests...
    $attach_config = dibi::select('*')
        ->from(Tables::ATTACH_CONFIG_TABLE)
        ->fetchPairs('config_name', 'config_value');

    if ($attach_config['upload_dir'][0] === '/' || ($attach_config['upload_dir'][0] !== '/' && $attach_config['upload_dir'][1] === ':')) {
        $upload_dir = $attach_config['upload_dir'];
    } else {
        $upload_dir = $phpbb_root_path . $attach_config['upload_dir'];
    }

    $upload_dir .= '/' . THUMB_DIR;

    $error = false;

    // Does the target directory exist, is it a directory and writeable. (only test if ftp upload is disabled)
    if ((int)$attach_config['allow_ftp_upload'] === 0 && (int)$attach_config['img_create_thumbnail'] === 1) {
        if (!@file_exists(@amod_realpath($upload_dir))) {
            @mkdir($upload_dir, 0755);
            @chmod($upload_dir, 0777);

            if (!@file_exists(@amod_realpath($upload_dir))) {
                $error = true;
                $error_msg = sprintf($lang['Directory_does_not_exist'], $upload_dir) . '<br />';
            }
        }

        if (!$error && !is_dir($upload_dir)) {
            $error = true;
            $error_msg = sprintf($lang['Directory_is_not_a_dir'], $upload_dir) . '<br />';
        }

        if (!$error) {
            if (!($fp = @fopen($upload_dir . '/0_000000.000', 'wb'))) {
                $error = true;
                $error_msg = sprintf($lang['Directory_not_writeable'], $upload_dir) . '<br />';
            } else {
                @fclose($fp);
                @unlink($upload_dir . '/0_000000.000');
            }
        }
    } else if ((int)$attach_config['allow_ftp_upload'] && (int)$attach_config['img_create_thumbnail']) {
        // Check FTP Settings
        $server = empty($attach_config['ftp_server']) ? 'localhost' : $attach_config['ftp_server'];

        $conn_id = @ftp_connect($server);

        if (!$conn_id) {
            $error = true;
            $error_msg = sprintf($lang['Ftp_error_connect'], $server) . '<br />';
        }

        $login_result = @ftp_login($conn_id, $attach_config['ftp_user'], $attach_config['ftp_pass']);

        if (!$login_result && !$error) {
            $error = true;
            $error_msg = sprintf($lang['Ftp_error_login'], $attach_config['ftp_user']) . '<br />';
        }

        if (!@ftp_pasv($conn_id, (int)$attach_config['ftp_pasv_mode'])) {
            $error = true;
            $error_msg = $lang['Ftp_error_pasv_mode'];
        }

        if (!$error) {
            // Check Upload
            $tmpfname = @tempnam('/tmp', 't0000');

            @unlink($tmpfname); // unlink for safety on php4.0.3+

            $fp = @fopen($tmpfname, 'wb');

            @fwrite($fp, 'test');

            @fclose($fp);

            $result = @ftp_chdir($conn_id, $attach_config['ftp_path'] . '/' . THUMB_DIR);

            if (!$result) {
                @ftp_mkdir($conn_id, $attach_config['ftp_path'] . '/' . THUMB_DIR);
            }

            $result = @ftp_chdir($conn_id, $attach_config['ftp_path'] . '/' . THUMB_DIR);

            if (!$result) {
                $error = true;
                $error_msg = sprintf($lang['Ftp_error_path'], $attach_config['ftp_path'] . '/' . THUMB_DIR) . '<br />';
            } else {
                $res = @ftp_put($conn_id, 't0000', $tmpfname, FTP_ASCII);

                if (!$res) {
                    $error = true;
                    $error_msg = sprintf($lang['Ftp_error_upload'], $attach_config['ftp_path'] . '/' . THUMB_DIR) . '<br />';
                } else {
                    $res = @ftp_delete($conn_id, 't0000');

                    if (!$res) {
                        $error = true;
                        $error_msg = sprintf($lang['Ftp_error_delete'], $attach_config['ftp_path'] . '/' . THUMB_DIR) . '<br />';
                    }
                }
            }

            @ftp_close($conn_id);

            @unlink($tmpfname);
        }
    }

    if (!$error) {
        $message  = $lang['Test_settings_successful'] . '<br /><br />';
        $message .= sprintf($lang['Click_return_attach_config'], '<a href="' . Session::appendSid('admin_attachments.php?mode=cats') . '">', '</a>') . '<br /><br />';
        $message .= sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

        message_die(GENERAL_MESSAGE, $message);
    }
}

if ($mode === 'sync') {
    $info = '';
    @set_time_limit(0);

    echo isset($lang['Sync_topics']) ? $lang['Sync_topics'] : 'Sync Topics';

    $topics = dibi::select('topic_id')
        ->from(Tables::TOPICS_TABLE)
        ->fetchPairs(null, 'topic_id');

    echo '<br />';

    $i = 0;
    foreach ($topics as $topic) {
        @flush();
        echo '.';

        if ($i % 50 === 0) {
            echo '<br />';
        }

        Sync::attachTopic($topic);
        $i++;
    }

    echo '<br /><br />';
    echo isset($lang['Sync_posts']) ? $lang['Sync_posts'] : 'Sync Posts';

    // Reassign Attachments to the Poster ID
    $rows = dibi::select(['a.attach_id', 'a.post_id', 'a.user_id_1', 'p.poster_id'])
        ->from(Tables::ATTACH_ATTACHMENT_TABLE)
        ->as('a')
        ->innerJoin(Tables::POSTS_TABLE)
        ->as('p')
        ->on('[p.post_id] = [a.post_id]')
        ->where('[a.user_id_1] <> [p.poster_id]')
        ->where('[a.user_id_2] = %i', 0)
        ->fetchAll();

    echo '<br />';

    foreach ($rows as $row) {
        dibi::update(Tables::ATTACH_ATTACHMENT_TABLE, ['user_id_1' => $row->poster_id])
            ->where('[attach_id] = %i', $row->attach_id)
            ->where('[post_id] = %i', $row->post_id)
            ->execute();

        @flush();
        echo '.';

        if ($i % 50 === 0) {
            echo '<br />';
        }
    }

    echo '<br /><br />';
    echo isset($lang['Sync_thumbnails']) ? $lang['Sync_thumbnails'] : 'Sync Thumbnails';

    // Sync Thumbnails (if a thumbnail is no longer there, delete it)
    // Get all Posts/PM's with the Thumbnail Flag set
    // Go through all of them and make sure the Thumbnail exist. If it does not exist, unset the Thumbnail Flag
    $rows = dibi::select(['attach_id', 'physical_filename', 'thumbnail'])
        ->from(Tables::ATTACH_ATTACHMENTS_DESC_TABLE)
        ->where('[thumbnail] = %i', 1)
        ->fetchAll();

    echo '<br />';

    $i = 0;
    foreach ($rows as $row) {
        @flush();
        echo '.';

        if ($i % 50 === 0) {
            echo '<br />';
        }

        if (!thumbnail_exists(basename($row->physical_filename))) {
            $info .= sprintf($lang['Sync_thumbnail_resetted'], $row->physical_filename) . '<br />';

            dibi::update(Tables::ATTACH_ATTACHMENTS_DESC_TABLE, ['thumbnail' => 0])
                ->where('[attach_id] = %i', $row->attach_id)
                ->execute();
        }
        $i++;
    }

    // Sync Thumbnails (make sure all non-existent thumbnails are deleted) - the other way around
    // Get all Posts/PM's with the Thumbnail Flag NOT set
    // Go through all of them and make sure the Thumbnail does NOT exist. If it does exist, delete it
    $rows = dibi::select(['attach_id', 'physical_filename', 'thumbnail'])
        ->from(Tables::ATTACH_ATTACHMENTS_DESC_TABLE)
        ->where('[thumbnail] = %i', 0);

    echo '<br />';

    $i = 0;
    foreach ($rows as $row) {
        @flush();
        echo '.';
        if ($i % 50 === 0) {
            echo '<br />';
        }

        if (thumbnail_exists(basename($row->physical_filename))) {
            $info .= sprintf($lang['Sync_thumbnail_resetted'], $row->physical_filename) . '<br />';
            unlink_attach(basename($row->physical_filename), MODE_THUMBNAIL);
        }
        $i++;
    }

    @flush();
    die('<br /><br /><br />' . $lang['Attach_sync_finished'] . '<br /><br />' . $info);
}

// Quota Limit Settings
if ($submit && $mode === 'quota') {
    // Change Quota Limit
    $quota_change_list = get_var('quota_change_list', [0]);
    $quota_desc_list = get_var('quota_desc_list', ['']);
    $filesize_list = get_var('max_filesize_list', [0]);
    $size_select_list = get_var('size_select_list', ['']);

    $allowed_list = [];

    for ($i = 0; $i < count($quota_change_list); $i++) {
        $filesize_list[$i] = ($size_select_list[$i] === 'kb') ? round($filesize_list[$i] * 1024) : (($size_select_list[$i] === 'mb') ? round($filesize_list[$i] * 1048576) : $filesize_list[$i]);

        dibi::update(Tables::ATTACH_QUOTA_LIMITS_TABLE, ['quota_desc' => $quota_desc_list[$i], 'quota_limit' => $filesize_list[$i]])
            ->where('[quota_limit_id] = %i', $quota_change_list[$i])
            ->execute();
    }

    // Delete Quota Limits
    $quota_id_list = get_var('quota_id_list', [0]);

    if (count($quota_id_list)) {
        dibi::delete(Tables::ATTACH_QUOTA_LIMITS_TABLE)
            ->where('[quota_limit_id] IN %in', $quota_id_list)
            ->execute();

        // Delete Quotas linked to this setting
        dibi::delete(Tables::ATTACH_QUOTA_TABLE)
            ->where('[quota_limit_id] IN %in', $quota_id_list)
            ->execute();
    }

    // Add Quota Limit ?
    $quota_desc = get_var('quota_description', '');
    $filesize = get_var('add_max_filesize', 0);
    $size_select = get_var('add_size_select', '');

    $add = isset($_POST['add_quota_check']);

    if ($quota_desc !== '' && $add) {
        // check Quota Description
        $rows = dibi::select(['quota_desc'])
            ->from(Tables::ATTACH_QUOTA_LIMITS_TABLE)
            ->fetchAll();

        foreach ($rows as $row) {
            if ($row->quota_desc === $quota_desc) {
                $error = true;

                if (isset($error_msg)) {
                    $error_msg .= '<br />';
                }

                $error_msg .= sprintf($lang['Quota_limit_exist'], $extension_group);
            }
        }

        if (!$error) {
            $filesize = ($size_select === 'kb') ? round($filesize * 1024) : (($size_select === 'mb') ? round($filesize * 1048576) : $filesize);

            dibi::insert(Tables::ATTACH_QUOTA_LIMITS_TABLE, ['quota_desc' => $quota_desc, 'quota_limit' => $filesize])->execute();
        }
    }

    if (!$error) {
        $message  = $lang['Attach_config_updated'] . '<br /><br />';
        $message .= sprintf($lang['Click_return_attach_config'], '<a href="' . Session::appendSid('admin_attachments.php?mode=quota') . '">', '</a>') . '<br /><br />';
        $message .= sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

        message_die(GENERAL_MESSAGE, $message);
    }
}

if ($mode === 'quota') {
    $template->setFileNames(['body' => 'admin/attach_quota_body.tpl']);

    $max_add_filesize = $attach_config['max_filesize'];
    $size = ($max_add_filesize >= 1048576) ? 'mb' : (($max_add_filesize >= 1024) ? 'kb' : 'b');

    if ($max_add_filesize >= 1048576) {
        $max_add_filesize = round($max_add_filesize / 1048576 * 100) / 100;
    } else if ($max_add_filesize >= 1024) {
        $max_add_filesize = round($max_add_filesize / 1024 * 100) / 100;
    }

    $template->assignVars(
        [
            'L_MANAGE_QUOTAS_TITLE' => $lang['Manage_quotas'],
            'L_MANAGE_QUOTAS_EXPLAIN' => $lang['Manage_quotas_explain'],
            'L_SUBMIT' => $lang['Submit'],
            'L_RESET' => $lang['Reset'],
            'L_EDIT' => $lang['Edit'],
            'L_VIEW' => $lang['View'],
            'L_DESCRIPTION' => $lang['Description'],
            'L_SIZE' => $lang['Max_filesize_attach'],
            'L_ADD_NEW' => $lang['Add_new'],
            'L_DELETE' => $lang['Delete'],
            'MAX_FILESIZE' => $max_add_filesize,

            'S_FILESIZE' => size_select('add_size_select', $size),
            'L_REMOVE_SELECTED' => $lang['Remove_selected'],

            'S_ATTACH_ACTION' => Session::appendSid('admin_attachments.php?mode=quota')
        ]
    );

    $rows = dibi::select('*')
        ->from(Tables::ATTACH_QUOTA_LIMITS_TABLE)
        ->orderBy('quota_limit', dibi::DESC)
        ->fetchAll();

    foreach ($rows as $row) {
        $size_format = ($row->quota_limit >= 1048576) ? 'mb' : (($row->quota_limit >= 1024) ? 'kb' : 'b');

        if ($row->quota_limit >= 1048576) {
            $row->quota_limit = round($row->quota_limit / 1048576 * 100) / 100;
        } else if ($row->quota_limit >= 1024) {
            $row->quota_limit = round($row->quota_limit / 1024 * 100) / 100;
        }

        $template->assignBlockVars('limit_row',
            [
                'QUOTA_NAME' => $row->quota_desc,
                'QUOTA_ID' => $row->quota_limit_id,
                'S_FILESIZE' => size_select('size_select_list[]', $size_format),
                'U_VIEW' => Session::appendSid("admin_attachments.php?mode=$mode&amp;e_mode=view_quota&amp;quota_id=" . $row->quota_limit_id),
                'MAX_FILESIZE' => $row->quota_limit
            ]
        );
    }
}

if ($mode === 'quota' && $e_mode === 'view_quota') {
    $quota_id = get_var('quota_id', 0);

    if (!$quota_id) {
        message_die(GENERAL_MESSAGE, 'Invalid Call');
    }

    $template->assignBlockVars('switch_quota_limit_desc', []);

    $row = dibi::select('*')
        ->from(Tables::ATTACH_QUOTA_LIMITS_TABLE)
        ->where('[quota_limit_id] = %i', $quota_id)
        ->fetch();

    $template->assignVars(
        [
            'L_QUOTA_LIMIT_DESC' => $row->quota_desc,
            'L_ASSIGNED_USERS' => $lang['Assigned_users'],
            'L_ASSIGNED_GROUPS' => $lang['Assigned_groups'],
            'L_UPLOAD_QUOTA' => $lang['Upload_quota'],
            'L_PM_QUOTA' => $lang['Pm_quota']
        ]
    );

    $rows = dibi::select(['q.user_id', 'u.username', 'q.quota_type'])
        ->from(Tables::ATTACH_QUOTA_TABLE)
        ->as('q')
        ->innerJoin(Tables::USERS_TABLE)
        ->as('u')
        ->on('[q.user_id] = [u.user_id]')
        ->where('[quota_limit_id] = %i', $quota_id)
        ->where('[q.user_id] <> %i', 0)
        ->fetchAll();

    foreach ($rows as $row) {
        if ($row->quota_type === QUOTA_UPLOAD_LIMIT) {
            $template->assignBlockVars('users_upload_row',
                [
                    'USER_ID' => $row->user_id,
                    'USERNAME' => $row->username
                ]
            );
        } else if ($row->quota_type === QUOTA_PM_LIMIT) {
            $template->assignBlockVars('users_pm_row',
                [
                    'USER_ID' => $row->user_id,
                    'USERNAME' => $row->username
                ]
            );
        }
    }

    $rows = dibi::select(['q.group_id', 'g.group_name', 'q.quota_type'])
        ->from(Tables::ATTACH_QUOTA_TABLE)
        ->as('q')
        ->innerJoin(Tables::GROUPS_TABLE)
        ->as('g')
        ->on('[q.group_id] = [g.group_id]')
        ->where('[q.quota_limit_id] = %i', $quota_id)
        ->where('[q.group_id] <> %i', 0)
        ->fetchAll();

    foreach ($rows as $row) {
        if ($row->quota_type === QUOTA_UPLOAD_LIMIT) {
            $template->assignBlockVars('groups_upload_row',
                [
                    'GROUP_ID' => $row->group_id,
                    'GROUPNAME' => $row->group_name
                ]
            );
        } else if ($row->quota_type === QUOTA_PM_LIMIT) {
            $template->assignBlockVars('groups_pm_row',
                [
                    'GROUP_ID' => $row->group_id,
                    'GROUPNAME' => $row->group_name
                ]
            );
        }
    }
}

if ($error) {
    $template->setFileNames(['reg_header' => 'error_body.tpl']);
    $template->assignVars(['ERROR_MESSAGE' => $error_msg]);
    $template->assignVarFromHandle('ERROR_BOX', 'reg_header');
}

$template->assignVars(
    [
        'ATTACH_VERSION' => sprintf($lang['Attachment_version'], $attach_config['attach_version'])
    ]
);

$template->pparse('body');

require_once 'page_footer_admin.php';

?>