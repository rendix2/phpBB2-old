<?php
/**
 *
 * @package attachment_mod
 * @version $Id: functions_attach.php,v 1.5 2006/04/09 13:25:51 acydburn Exp $
 * @copyright (c) 2002 Meik Sievertsen
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *
 */

/**
 * All Attachment Functions needed everywhere
 */

/**
 * html_entity_decode replacement (from php manual)
 */
if (!function_exists('html_entity_decode')) {
    function html_entity_decode($given_html, $quote_style = ENT_QUOTES)
    {
        $trans_table = array_flip(get_html_translation_table(HTML_SPECIALCHARS, $quote_style));
        $trans_table['&#39;'] = "'";
        return (strtr($given_html, $trans_table));
    }
}

/**
 * A simple dectobase64 function
 */
function base64_pack($number)
{
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ+-';
    $base = strlen($chars);

    if ($number > 4096) {
        return;
    } else if ($number < $base) {
        return $chars[$number];
    }

    $hexval = '';

    while ($number > 0) {
        $remainder = $number % $base;

        if ($remainder < $base) {
            $hexval = $chars[$remainder] . $hexval;
        }

        $number = floor($number / $base);
    }

    return $hexval;
}

/**
 * base64todec function
 */
function base64_unpack($string)
{
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ+-';
    $base = strlen($chars);

    $length = strlen($string);
    $number = 0;

    for ($i = 1; $i <= $length; $i++) {
        $pos = $length - $i;
        $operand = strpos($chars, substr($string, $pos, 1));
        $exponent = pow($base, $i - 1);
        $decValue = $operand * $exponent;
        $number += $decValue;
    }

    return $number;
}

/**
 * Per Forum based Extension Group Permissions (Encode Number) -> Theoretically up to 158 Forums saveable. :)
 * We are using a base of 64, but splitting it to one-char and two-char numbers. :)
 */
function auth_pack($auth_array)
{
    $one_char_encoding = '#';
    $two_char_encoding = '.';
    $one_char = $two_char = false;
    $auth_cache = '';

    for ($i = 0; $i < count($auth_array); $i++) {
        $val = base64_pack((int)$auth_array[$i]);
        if (strlen($val) == 1 && !$one_char) {
            $auth_cache .= $one_char_encoding;
            $one_char = true;
        } else if (strlen($val) == 2 && !$two_char) {
            $auth_cache .= $two_char_encoding;
            $two_char = true;
        }

        $auth_cache .= $val;
    }

    return $auth_cache;
}

/**
 * Reverse the auth_pack process
 */
function auth_unpack($auth_cache)
{
    $one_char_encoding = '#';
    $two_char_encoding = '.';

    $auth = array();
    $auth_len = 1;

    for ($pos = 0; $pos < strlen($auth_cache); $pos += $auth_len) {
        $forum_auth = substr($auth_cache, $pos, 1);
        if ($forum_auth == $one_char_encoding) {
            $auth_len = 1;
            continue;
        } else if ($forum_auth == $two_char_encoding) {
            $auth_len = 2;
            $pos--;
            continue;
        }

        $forum_auth = substr($auth_cache, $pos, $auth_len);
        $forum_id = base64_unpack($forum_auth);
        $auth[] = (int)$forum_id;
    }
    return $auth;
}

/**
 * Used for determining if Forum ID is authed, please use this Function on all Posting Screens
 */
function is_forum_authed($auth_cache, $check_forum_id)
{
    $one_char_encoding = '#';
    $two_char_encoding = '.';

    if (trim($auth_cache) == '') {
        return true;
    }

    $auth = array();
    $auth_len = 1;

    for ($pos = 0; $pos < strlen($auth_cache); $pos += $auth_len) {
        $forum_auth = substr($auth_cache, $pos, 1);
        if ($forum_auth == $one_char_encoding) {
            $auth_len = 1;
            continue;
        } else if ($forum_auth == $two_char_encoding) {
            $auth_len = 2;
            $pos--;
            continue;
        }

        $forum_auth = substr($auth_cache, $pos, $auth_len);
        $forum_id = (int)base64_unpack($forum_auth);
        if ($forum_id == $check_forum_id) {
            return true;
        }
    }
    return false;
}

/**
 * Init FTP Session
 */
function attach_init_ftp($mode = false)
{
    global $lang, $attach_config;

    $server = (trim($attach_config['ftp_server']) == '') ? 'localhost' : trim($attach_config['ftp_server']);

    $ftp_path = ($mode == MODE_THUMBNAIL) ? trim($attach_config['ftp_path']) . '/' . THUMB_DIR : trim($attach_config['ftp_path']);

    $conn_id = @ftp_connect($server);

    if (!$conn_id) {
        message_die(GENERAL_ERROR, sprintf($lang['Ftp_error_connect'], $server));
    }

    $login_result = @ftp_login($conn_id, $attach_config['ftp_user'], $attach_config['ftp_pass']);

    if (!$login_result) {
        message_die(GENERAL_ERROR, sprintf($lang['Ftp_error_login'], $attach_config['ftp_user']));
    }

    if (!@ftp_pasv($conn_id, (int)$attach_config['ftp_pasv_mode'])) {
        message_die(GENERAL_ERROR, $lang['Ftp_error_pasv_mode']);
    }

    $result = @ftp_chdir($conn_id, $ftp_path);

    if (!$result) {
        message_die(GENERAL_ERROR, sprintf($lang['Ftp_error_path'], $ftp_path));
    }

    return $conn_id;
}

/**
 * Deletes an Attachment
 */
function unlink_attach($filename, $mode = false)
{
    global $upload_dir, $attach_config, $lang;

    $filename = basename($filename);

    if (!(int)$attach_config['allow_ftp_upload']) {
        if ($mode == MODE_THUMBNAIL) {
            $filename = $upload_dir . '/' . THUMB_DIR . '/t_' . $filename;
        } else {
            $filename = $upload_dir . '/' . $filename;
        }

        $deleted = @unlink($filename);
    } else {
        $conn_id = attach_init_ftp($mode);

        if ($mode == MODE_THUMBNAIL) {
            $filename = 't_' . $filename;
        }

        $res = @ftp_delete($conn_id, $filename);
        if (!$res) {
            if (ATTACH_DEBUG) {
                $add = ($mode == MODE_THUMBNAIL) ? '/' . THUMB_DIR : '';
                message_die(GENERAL_ERROR, sprintf($lang['Ftp_error_delete'], $attach_config['ftp_path'] . $add));
            }

            return $deleted;
        }

        @ftp_close($conn_id);

        $deleted = true;
    }

    return $deleted;
}

/**
 * FTP File to Location
 */
function ftp_file($source_file, $dest_file, $mimetype, $disable_error_mode = false)
{
    global $attach_config, $lang, $error, $error_msg;

    $conn_id = attach_init_ftp();

    // Binary or Ascii ?
    $mode = FTP_BINARY;
    if (preg_match("/text/i", $mimetype) || preg_match("/html/i", $mimetype)) {
        $mode = FTP_ASCII;
    }

    $res = @ftp_put($conn_id, $dest_file, $source_file, $mode);

    if (!$res && !$disable_error_mode) {
        $error = true;
        if (!empty($error_msg)) {
            $error_msg .= '<br />';
        }
        $error_msg = sprintf($lang['Ftp_error_upload'], $attach_config['ftp_path']) . '<br />';
        @ftp_close($conn_id);
        return false;
    }

    if (!$res) {
        return false;
    }

    @ftp_site($conn_id, 'CHMOD 0644 ' . $dest_file);
    @ftp_close($conn_id);
    return true;
}

/**
 * Check if Attachment exist
 */
function attachment_exists($filename)
{
    global $upload_dir, $attach_config;

    $filename = basename($filename);

    if (!(int)$attach_config['allow_ftp_upload']) {
        if (!@file_exists(@amod_realpath($upload_dir . '/' . $filename))) {
            return false;
        } else {
            return true;
        }
    } else {
        $found = false;

        $conn_id = attach_init_ftp();

        $file_listing = array();

        $file_listing = @ftp_rawlist($conn_id, $filename);

        for ($i = 0, $size = count($file_listing); $i < $size; $i++) {
            if (ereg("([-d])[rwxst-]{9}.* ([0-9]*) ([a-zA-Z]+[0-9: ]*[0-9]) ([0-9]{2}:[0-9]{2}) (.+)", $file_listing[$i], $regs)) {
                if ($regs[1] == 'd') {
                    $dirinfo[0] = 1;    // Directory == 1
                }
                $dirinfo[1] = $regs[2]; // Size
                $dirinfo[2] = $regs[3]; // Date
                $dirinfo[3] = $regs[4]; // Filename
                $dirinfo[4] = $regs[5]; // Time
            }

            if ($dirinfo[0] != 1 && $dirinfo[4] == $filename) {
                $found = true;
            }
        }

        @ftp_close($conn_id);

        return $found;
    }
}

/**
 * Check if Thumbnail exist
 */
function thumbnail_exists($filename)
{
    global $upload_dir, $attach_config;

    $filename = basename($filename);

    if (!(int)$attach_config['allow_ftp_upload']) {
        if (!@file_exists(@amod_realpath($upload_dir . '/' . THUMB_DIR . '/t_' . $filename))) {
            return false;
        } else {
            return true;
        }
    } else {
        $found = false;

        $conn_id = attach_init_ftp(MODE_THUMBNAIL);

        $file_listing = array();

        $filename = 't_' . $filename;
        $file_listing = @ftp_rawlist($conn_id, $filename);

        for ($i = 0, $size = count($file_listing); $i < $size; $i++) {
            if (ereg("([-d])[rwxst-]{9}.* ([0-9]*) ([a-zA-Z]+[0-9: ]*[0-9]) ([0-9]{2}:[0-9]{2}) (.+)", $file_listing[$i], $regs)) {
                if ($regs[1] == 'd') {
                    $dirinfo[0] = 1;    // Directory == 1
                }
                $dirinfo[1] = $regs[2]; // Size
                $dirinfo[2] = $regs[3]; // Date
                $dirinfo[3] = $regs[4]; // Filename
                $dirinfo[4] = $regs[5]; // Time
            }

            if ($dirinfo[0] != 1 && $dirinfo[4] == $filename) {
                $found = true;
            }
        }

        @ftp_close($conn_id);

        return $found;
    }
}

/**
 * Physical Filename stored already ?
 */
function physical_filename_already_stored($filename)
{
    if ($filename == '') {
        return false;
    }

    $filename = basename($filename);

    $res = dibi::select(['attach_id'])
        ->from(Tables::ATTACH_ATTACHMENTS_DESC_TABLE)
        ->where('[physical_filename] = %s', $filename)
        ->fetch();

    return $res !== false;
}

/**
 * Determine if an Attachment exist in a post/pm
 */
function attachment_exists_db($post_id, $page = 0)
{
    $post_id = (int)$post_id;

    if ($page == PAGE_PRIVMSGS) {
        $sql_id = 'privmsgs_id';
    } else {
        $sql_id = 'post_id';
    }

    $check = dibi::select(['attach_id'])
        ->from(Tables::ATTACH_ATTACHMENT_TABLE)
        ->where('%n = %i', $sql_id, $post_id)
        ->fetch();

    if ($check) {
        return true;
    } else {
        return false;
    }
}

/**
 * get all attachments from a post (could be an post array too)
 */
function get_attachments_from_post($post_id_array)
{
    global $db, $attach_config;

    $attachments = array();

    if (!is_array($post_id_array)) {
        if (empty($post_id_array)) {
            return $attachments;
        }

        $post_id = (int)$post_id_array;

        $post_id_array = [];
        $post_id_array[] = $post_id;
    }

    //$post_id_array = implode(', ', array_map('intval', $post_id_array));

    if ($post_id_array == '') {
        return $attachments;
    }

    $display_order = ((int)$attach_config['display_order'] == 0) ? 'DESC' : 'ASC';

    return $attachments = dibi::select(['a.post_id', 'd.*'])
        ->from(Tables::ATTACH_ATTACHMENT_TABLE)
        ->as('a')
        ->innerJoin(Tables::ATTACH_ATTACHMENTS_DESC_TABLE)
        ->as('d')
        ->on('[a.attach_id] = [d.attach_id]')
        ->where('[a.post_id] IN %in', $post_id_array)
        ->orderBy('d.filetime', $display_order)
        ->fetchAll();
}

/**
 * get all attachments from a pm
 */
function get_attachments_from_pm($privmsgs_id_array)
{
    global $attach_config;

    $attachments = array();

    if (!is_array($privmsgs_id_array)) {
        if (empty($privmsgs_id_array)) {
            return $attachments;
        }

        $privmsgs_id = (int)$privmsgs_id_array;

        $privmsgs_id_array = array();
        $privmsgs_id_array[] = $privmsgs_id;
    }

    //$privmsgs_id_array = implode(', ', array_map('intval', $privmsgs_id_array));

    if ($privmsgs_id_array == '') {
        return $attachments;
    }

    $display_order = ((int)$attach_config['display_order'] == 0) ? 'DESC' : 'ASC';

    return dibi::select(['a.privmsgs_id', 'd.*'])
        ->from(Tables::ATTACH_ATTACHMENT_TABLE)
        ->as('a')
        ->innerJoin(Tables::ATTACH_ATTACHMENTS_DESC_TABLE)
        ->as('d')
        ->on('[a.attach_id] = [d.attach_id]')
        ->where('[a.privmsgs_id] IN %in', $privmsgs_id_array)
        ->orderBy('d.filetime', $display_order)
        ->fetchAll();
}

/**
 * Count Filesize of Attachments in Database based on the attachment id
 */
function get_total_attach_filesize($attach_ids)
{
    if (!is_array($attach_ids) || !count($attach_ids)) {
        return 0;
    }

    return dibi::select('SUM(filesize)')
        ->as('total')
        ->from(Tables::ATTACH_ATTACHMENTS_DESC_TABLE)
        ->where('[attach_id] IN %in', $attach_ids)
        ->fetchSingle();
}

/**
 * Count Filesize for Attachments in Users PM Boxes (Do not count the SENT Box)
 */
function get_total_attach_pm_filesize($direction, $user_id)
{
    if ($direction != 'from_user' && $direction != 'to_user') {
        return 0;
    } else {
        $user_sql = ($direction == 'from_user') ? '(a.user_id_1 = ' . (int)$user_id . ')' : '(a.user_id_2 = ' . (int)$user_id . ')';
    }

    $attach_id = dibi::select(['a.attach_id'])
        ->from(Tables::ATTACH_ATTACHMENT_TABLE)
        ->as('a')
        ->innerJoin(Tables::PRIVATE_MESSAGE_TABLE)
        ->as('p')
        ->on('[a.privmsgs_id] = [p.privmsgs_id]')
        ->where('[a.privmsgs_id] <> %i', 0)
        ->where('[p.privmsgs_type] <> %i', PRIVMSGS_SENT_MAIL)
        ->fetchPairs(null, 'attach_id');

    /*
    $sql = 'SELECT a.attach_id
        FROM ' . ATTACHMENTS_TABLE . ' a, ' . PRIVMSGS_TABLE . " p
        WHERE $user_sql
            AND a.privmsgs_id <> 0 AND a.privmsgs_id = p.privmsgs_id
            AND p.privmsgs_type <> " . PRIVMSGS_SENT_MAIL;
    */

    $num_rows = count($attach_id);

    $pm_filesize_total = 0;

    if ($num_rows == 0) {
        return 0;
    }

    $pm_filesize_total = get_total_attach_filesize($attach_id);
    return $pm_filesize_total;
}

/**
 * Get allowed Extensions and their respective Values
 */
function get_extension_informations()
{
    return dibi::select(['e.extension', 'g.cat_id', 'g.download_mode', 'g.upload_icon'])
        ->from(Tables::ATTACH_EXTENSION_TABLE)
        ->as('e')
        ->innerJoin(Tables::ATTACH_EXTENSION_GROUPS_TABLE)
        ->as('g')
        ->on('[e.group_id] = [g.group_id]')
        ->where('[g.allow_group] = %i', 1)
        ->fetchAll();
}

/**
 * Sync Topic (includes/functions_admin.php)
 */
function attachment_sync_topic($topic_id)
{
    if (!$topic_id) {
        return;
    }

    $topic_id = (int)$topic_id;

    $post_ids = dibi::select('post_id')
        ->from(Tables::POSTS_TABLE)
        ->where('[topic_id] = %i', $topic_id)
        ->groupBy('post_id')
        ->fetchPairs(null, 'post_id');

    if (!count($post_ids)) {
        return;
    }

    $checkAttach = dibi::select('attach_id')
        ->from(Tables::ATTACH_ATTACHMENT_TABLE)
        ->where('[post_id] IN %in', $post_ids)
        ->fetch();

    $set_id = $checkAttach === false ? 0 : 1;

    dibi::update(Tables::TOPICS_TABLE, ['topic_attachment' => $set_id])
        ->where('[topic_id] = %i', $topic_id)
        ->execute();

    foreach ($post_ids as $post_id) {
        $checkAttach = dibi::select('attach_id')
            ->from(Tables::ATTACH_ATTACHMENT_TABLE)
            ->where('[post_id] = %i', $post_id)
            ->fetch();

        $set_id = $checkAttach === false ? 0 : 1;

        dibi::update(Tables::POSTS_TABLE, ['post_attachment' => $set_id])
            ->where('[post_id] = %i', $post_id)
            ->execute();
    }
}

/**
 * Get Extension
 */
function get_extension($filename)
{
    if (!stristr($filename, '.')) {
        return '';
    }

    $extension = strrchr(strtolower($filename), '.');
    $extension[0] = ' ';
    $extension = strtolower(trim($extension));

    if (is_array($extension)) {
        return '';
    } else {
        return $extension;
    }
}

/**
 * Delete Extension
 */
function delete_extension($filename)
{
    return substr($filename, 0, strrpos(strtolower(trim($filename)), '.'));
}

/**
 * Check if a user is within Group
 */
function user_in_group($user_id, $group_id)
{
    $user_id = (int)$user_id;
    $group_id = (int)$group_id;

    if (!$user_id || !$group_id) {
        return false;
    }

    $res = dibi::select('u.group_id')
        ->from(Tables::USERS_GROUPS_TABLE)
        ->as('ug')
        ->innerJoin(Tables::GROUPS_TABLE)
        ->as('g')
        ->on('[u.group_id] = [g.group_id]')
        ->where('[ug.user_id] = %i', $user_id)
        ->where('[g.group_id] = %i', $group_id)
        ->where('[ug.user_pending] = %i', 0)
        ->fetch();

    return $res !== false;
}

/**
 * Realpath replacement for attachment mod
 */
function amod_realpath($path)
{
    return (function_exists('realpath')) ? realpath($path) : $path;
}

/**
 * _set_var
 *
 * Set variable, used by {@link get_var the get_var function}
 *
 * @private
 */
function _set_var(&$result, $var, $type, $multibyte = false)
{
    settype($var, $type);
    $result = $var;

    if ($type == 'string') {
        $result = trim(htmlspecialchars(str_replace(array("\r\n", "\r", '\xFF'), array("\n", "\n", ' '), $result)));
        // 2.0.x is doing addslashes on all variables
        $result = stripslashes($result);
        if ($multibyte) {
            $result = preg_replace('#&amp;(\#[0-9]+;)#', '&\1', $result);
        }
    }
}

/**
 * get_var
 *
 * Used to get passed variable
 */
function get_var($var_name, $default, $multibyte = false)
{
    $request_var = (isset($_POST[$var_name])) ? $_POST : $_GET;

    if (!isset($request_var[$var_name]) || (is_array($request_var[$var_name]) && !is_array($default)) || (is_array($default) && !is_array($request_var[$var_name]))) {
        return (is_array($default)) ? array() : $default;
    }

    $var = $request_var[$var_name];

    if (!is_array($default)) {
        $type = gettype($default);
    } else {
        list($key_type, $type) = each($default);
        $type = gettype($type);
        $key_type = gettype($key_type);
    }

    if (is_array($var)) {
        $_var = $var;
        $var = array();

        foreach ($_var as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $_k => $_v) {
                    _set_var($k, $k, $key_type);
                    _set_var($_k, $_k, $key_type);
                    _set_var($var[$k][$_k], $_v, $type, $multibyte);
                }
            } else {
                _set_var($k, $k, $key_type);
                _set_var($var[$k], $v, $type, $multibyte);
            }
        }
    } else {
        _set_var($var, $var, $type, $multibyte);
    }

    return $var;
}

/**
 * Escaping SQL
 */
function attach_mod_sql_escape($text)
{
    switch (SQL_LAYER) {
        case 'postgresql':
            return pg_escape_string($text);
            break;

        case 'mysql':
        case 'mysql4':
            if (function_exists('mysql_escape_string')) {
                return mysql_escape_string($text);
            } else {
                return str_replace("'", "''", str_replace('\\', '\\\\', $text));
            }
            break;

        default:
            return str_replace("'", "''", str_replace('\\', '\\\\', $text));
            break;
    }
}

function getOrderBy(\Dibi\Fluent $fluent, $mode, $view, $start, $sort_order, $board_config)
{
    // Set Order
    if ($view == 'username') {
        switch ($mode) {
            case 'username':
                $fluent->orderBy('u.username', $sort_order);
                break;

            case 'attachments':
                $fluent->orderBy('total_attachments', $sort_order);
                break;

            case 'filesize':
                $fluent->orderBy('total_size', $sort_order);
                break;

            default:
                $fluent->orderBy('u.total_attachments', $sort_order);
                $mode = 'attachments';
                $sort_order = 'DESC';
                break;
        }
    } else if ($view == 'attachments') {
        switch ($mode) {
            case 'filename':
                $fluent->orderBy('a.real_filename', $sort_order);
                break;

            case 'comment':
                $fluent->orderBy('a.comment', $sort_order);
                break;

            case 'extension':
                $fluent->orderBy('a.extension', $sort_order);
                break;

            case 'filesize':
                $fluent->orderBy('a.filesize', $sort_order);
                break;

            case 'downloads':
                $fluent->orderBy('a.download_count', $sort_order);
                break;

            case 'post_time':
                $fluent->orderBy('a.filetime', $sort_order);
                break;

            default:
                $fluent->orderBy('a.real_filename', dibi::ASC);
                $mode = 'a.real_filename';
                $sort_order = 'ASC';
                break;
        }
    }

    $fluent->limit($board_config['topics_per_page'])
        ->offset($start);

    return $fluent;
}

?>