<?php
/**
 *
 * @package attachment_mod
 * @version $Id: functions_admin.php,v 1.4 2006/04/22 16:21:09 acydburn Exp $
 * @copyright (c) 2002 Meik Sievertsen
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *
 */

use Nette\Utils\Finder;

/**
 * All Attachment Functions only needed in Admin
 */

/**
 * Set/Change Quotas
 *
 * @param     $mode
 * @param     $id
 * @param     $quota_type
 * @param int $quota_limit_id
 *
 * @throws \Dibi\Exception
 */
function process_quota_settings($mode, $id, $quota_type, $quota_limit_id = 0)
{
    $id = (int)$id;
    $quota_type = (int)$quota_type;
    $quota_limit_id = (int)$quota_limit_id;

    if ($mode === 'user') {
        if ($quota_limit_id) {
            // Check if user is already entered
            $checkQuota = dibi::select('user_id')
                ->from(Tables::ATTACH_QUOTA_TABLE)
                ->where('[user_id] = %i', $id)
                ->where('[quota_type] = %i', $quota_type)
                ->fetch();

            if ($checkQuota) {
                dibi::update(Tables::ATTACH_QUOTA_TABLE, ['quota_limit_id' => $quota_limit_id])
                    ->where('[user_id] = %i', $id)
                    ->where('[quota_type] = %i', $quota_type)
                    ->execute();
            } else {
                $sql_ary = [
                    'user_id' => (int)$id,
                    'group_id' => 0,
                    'quota_type' => (int)$quota_type,
                    'quota_limit_id' => (int)$quota_limit_id
                ];

                dibi::insert(Tables::ATTACH_QUOTA_TABLE, $sql_ary)->execute();
            }
        } else {
            dibi::delete(Tables::ATTACH_QUOTA_TABLE)
                ->where('[user_id] = %i', $id)
                ->where('[quota_type] = %i', $quota_type)
                ->execute();
        }
    } else if ($mode === 'group') {
        if ($quota_limit_id) {
            dibi::delete(Tables::ATTACH_QUOTA_TABLE)
                ->where('[group_id] = %i', $id)
                ->where('[quota_type] = %i', $quota_type)
                ->execute();
        } else {
            // Check if user is already entered
            $check = dibi::select('group_id')
                ->from(Tables::ATTACH_QUOTA_TABLE)
                ->where('[group_id] = %i', $id)
                ->where('[quota_type] = %i', $quota_type)
                ->fetch();

            if ($check) {
                dibi::update(Tables::ATTACH_QUOTA_TABLE, ['quota_limit_id' => $quota_limit_id])
                    ->where('[group_id] = %i', $id)
                    ->where('[quota_type] = %i', $quota_type)
                    ->execute();
            } else {
                $insertData = [
                    'user_id' => 0,
                    'group_id' => $id,
                    'quota_type' => $quota_type,
                    'quota_limit_id' => $quota_limit_id
                ];

                dibi::insert(Tables::ATTACH_QUOTA_TABLE, $insertData)->execute();
            }
        }
    }
}

/**
 * sort multi-dimensional Array
 * @param     $sort_array
 * @param     $key
 * @param     $sort_order
 * @param int $pre_string_sort
 * @return mixed
*/
function sort_multi_array($sort_array, $key, $sort_order, $pre_string_sort = 0)
{
    $last_element = count($sort_array) - 1;

    if ($pre_string_sort) {
        $string_sort = $pre_string_sort;
    } else {
        $string_sort = !is_numeric($sort_array[$last_element - 1][$key]);
    }

    for ($i = 0; $i < $last_element; $i++) {
        $num_iterations = $last_element - $i;

        for ($j = 0; $j < $num_iterations; $j++) {
            $next = 0;

            // do checks based on key
            $switch = false;
            if ($string_sort) {
                if (($sort_order === 'DESC' && strcasecmp($sort_array[$j][$key], $sort_array[$j + 1][$key]) < 0) || ($sort_order === 'ASC' && strcasecmp($sort_array[$j][$key], $sort_array[$j + 1][$key]) > 0)) {
                    $switch = true;
                }
            } else {
                if (($sort_order === 'DESC' && (int)$sort_array[$j][$key] < (int)$sort_array[$j + 1][$key]) || ($sort_order === 'ASC' && (int)$sort_array[$j][$key] > (int)$sort_array[$j + 1][$key])) {
                    $switch = true;
                }
            }

            if ($switch) {
                $temp = $sort_array[$j];
                $sort_array[$j] = $sort_array[$j + 1];
                $sort_array[$j + 1] = $temp;
            }
        }
    }

    return $sort_array;
}

/**
 * See if a post or pm really exist
 * @param $attach_id
 * @return bool
*/
function entry_exists($attach_id)
{
    $attach_id = (int)$attach_id;

    if (!$attach_id) {
        return false;
    }

    $ids = dibi::select(['post_id', 'privmsgs_id'])
        ->from(Tables::ATTACH_ATTACHMENT_TABLE)
        ->where('[attach_id] = %i', $attach_id)
        ->fetchAll();

    $exists = false;

    foreach ($ids as $id) {
        if ((int)$id->post_id !== 0) {
            $res = dibi::select(['post_id'])
                ->from(Tables::POSTS_TABLE)
                ->where('[post_id] = %i', $id->post_id)
                ->fetch();
        } else if ((int)$id->privmsgs_id !== 0) {
            $res = dibi::select(['post_id'])
                ->from(Tables::PRIVATE_MESSAGE_TABLE)
                ->where('[privmsgs_id] = %i', $id->privmsgs_id)
                ->fetch();
        }

        if ($res) {
            $exists = true;
            break;
        }
    }

    return $exists;
}

/**
 * Collect all Attachments in Filesystem
 */
function collect_attachments()
{
    global $upload_dir, $attach_config;

    $file_attachments = [];

    if ((int)$attach_config['allow_ftp_upload']) {
        $conn_id = attach_init_ftp();

        $file_listing = @ftp_rawlist($conn_id, '');

        if (!$file_listing) {
            message_die(GENERAL_ERROR, 'Unable to get Raw File Listing. Please be sure the LIST command is enabled at your FTP Server.');
        }

        for ($i = 0; $i < count($file_listing); $i++) {
            if (preg_match("#([-d])[rwxst-]{9}.* ([0-9]*) ([a-zA-Z]+[0-9: ]*[0-9]) ([0-9]{2}:[0-9]{2}) (.+)#", $file_listing[$i], $regs)) {
                if ($regs[1] === 'd') {
                    $dirinfo[0] = 1;    // Directory === 1
                }
                $dirinfo[1] = $regs[2]; // Size
                $dirinfo[2] = $regs[3]; // Date
                $dirinfo[3] = $regs[4]; // Filename
                $dirinfo[4] = $regs[5]; // Time
            }

            if ($dirinfo[0] !== 1 && $dirinfo[4] != 'index.php' && $dirinfo[4] != '.htaccess') {
                $file_attachments[] = trim($dirinfo[4]);
            }
        }

        @ftp_close($conn_id);
    } else {
        if ($dir = @opendir($upload_dir)) {
            while ($file = @readdir($dir)) {
                if ($file != 'index.php' && $file != '.htaccess' && !is_dir($upload_dir . '/' . $file) && !is_link($upload_dir . '/' . $file)) {
                    $file_attachments[] = trim($file);
                }
            }

            closedir($dir);
        } else {
            message_die(GENERAL_ERROR, 'Is Safe Mode Restriction in effect? The Attachment Mod seems to be unable to collect the Attachments within the upload Directory. Try to use FTP Upload to circumvent this error. Another reason could be that the directory ' . $upload_dir . ' does not exist.');
        }
    }

    return $file_attachments;
}

/**
 * Returns the filesize of the upload directory in human readable format
 */
function get_formatted_dirsize()
{
    global $attach_config, $upload_dir, $lang;

    $upload_dir_size = 0;

    if ((int)$attach_config['allow_ftp_upload']) {
        $conn_id = attach_init_ftp();
        $file_listing = @ftp_rawlist($conn_id, '');

        if (!$file_listing) {
            $upload_dir_size = $lang['Not_available'];
            return $upload_dir_size;
        }

        for ($i = 0; $i < count($file_listing); $i++) {
            if (preg_match("#([-d])[rwxst-]{9}.* ([0-9]*) ([a-zA-Z]+[0-9: ]*[0-9]) ([0-9]{2}:[0-9]{2}) (.+)#", $file_listing[$i], $regs)) {
                if ($regs[1] === 'd') {
                    $dirinfo[0] = 1;    // Directory === 1
                }
                $dirinfo[1] = $regs[2]; // Size
                $dirinfo[2] = $regs[3]; // Date
                $dirinfo[3] = $regs[4]; // Filename
                $dirinfo[4] = $regs[5]; // Time
            }

            if ($dirinfo[0] != 1 && $dirinfo[4] != 'index.php' && $dirinfo[4] != '.htaccess') {
                $upload_dir_size += $dirinfo[1];
            }
        }

        @ftp_close($conn_id);
    } else {
        $attachments = Finder::findFiles('*')
            ->in($upload_dir)
            ->exclude('index.php')
            ->exclude('.htaccess');

        if (count($attachments)) {
            $attachmentDirSize = 0;

            /**
             * @var SplFileInfo $attachment
             */
            foreach ($attachments as $attachment) {
                $attachmentDirSize += $attachment->getSize();
            }

            return get_formatted_filesize($attachmentDirSize);
        } else {
            // Couldn't open Avatar dir.
            return $lang['Not_available'];
        }
    }
}

/**
 * Build SQL-Statement for the search feature
 *
 * @param string $order_by
 * @param int $total_rows
 *
 * @return array
 */
function search_attachments($order_by, &$total_rows)
{
    $search_keyword_fname = get_var('search_keyword_fname', '');
    $search_keyword_comment = get_var('search_keyword_comment', '');
    $search_author = get_var('search_author', '');
    $search_size_smaller = get_var('search_size_smaller', '');
    $search_size_greater = get_var('search_size_greater', '');
    $search_count_smaller = get_var('search_count_smaller', '');
    $search_count_greater = get_var('search_count_greater', '');
    $search_days_greater = get_var('search_days_greater', '');
    $search_forum = get_var('search_forum', '');
    $search_cat = get_var('search_cat', '');

    $attachments = dibi::select(['a.*', 't.post_id', 'p.post_time', 'p.topic_id'])
        ->from(Tables::ATTACH_ATTACHMENT_TABLE)
        ->as('t')
        ->innerJoin(Tables::ATTACH_ATTACHMENTS_DESC_TABLE)
        ->as('a')
        ->on('[a.attach_id] = [t.attach_id]')
        ->innerJoin(Tables::POSTS_TABLE)
        ->as('p')
        ->on('[t.post_id] = [p.post_id]');

    // Author name search
    if ($search_author !== '') {
        // Bring in line with 2.0.x expected username
        $search_author = addslashes(html_entity_decode($search_author));
        $search_author = stripslashes(phpbb_clean_username($search_author));

        // Prepare for directly going into sql query
        $search_author = str_replace('*', '%', attach_mod_sql_escape($search_author));

        // We need the post_id's, because we want to query the Attachment Table
        $rows = dibi::select('user_id')
            ->from(Tables::USERS_TABLE)
            ->where('[username] LIKE %~like~', $search_author)
            ->fetchPairs(null, 'user_id');

        $attachments->where('[t.user_id_1] IN %in', $rows);
    }

    // Search Keyword
    if ($search_keyword_fname !== '') {
        $match_word = str_replace('*', '%', $search_keyword_fname);

        $attachments->where('[a.real_filename] LIKE %~like~', $match_word);
    }

    if ($search_keyword_comment !== '') {
        $match_word = str_replace('*', '%', $search_keyword_comment);

        $attachments->where('[a.real_filename] LIKE %~like~', $match_word);
    }

    // Search Download Count
    if ($search_count_smaller !== '') {
        $attachments->where('[a.download_count] < %i', $search_count_smaller);
    } else if ($search_count_greater !== '') {
        $attachments->where('[a.download_count] > %i', $search_count_greater);
    }

    // Search Filesize
    if ($search_size_smaller !== '') {
        $attachments->where('[a.filesize] < %i', $search_size_smaller);
    } else if ($search_size_greater !== '') {
        $attachments->where('[a.filesize] > %i', $search_size_greater);
    }

    // Search Attachment Time
    if ($search_days_greater !== '') {
        $attachments->where('[a.filetime] < %i', (time() - ((int)$search_days_greater * 86400)));
    }

    // Search Forum
    if ($search_forum) {
        $attachments->where('[a.forum_id] < %i', $search_forum);
    }

    $total_rows_sql = clone $attachments;

    // DIRTY
    global $mode, $view, $start, $sort_order, $board_config;

    $total_rows_sql = getOrderBy($total_rows_sql, $mode, $view, $start, $sort_order, $board_config);

    /*
    $sql .= $order_by;

    if (!($result = $db->sql_query($sql))) {
        message_die(GENERAL_ERROR, 'Couldn\'t query attachments', '', __LINE__, __FILE__, $sql);
    }

    $attachments = $db->sql_fetchrowset($result);
    $num_attach = $db->sql_numrows($result);
    $db->sql_freeresult($result);

    if ($num_attach === 0) {
        message_die(GENERAL_MESSAGE, $lang['No_attach_search_match']);
    }

    if (!($result = $db->sql_query($total_rows_sql))) {
        message_die(GENERAL_ERROR, 'Could not query attachments', '', __LINE__, __FILE__, $sql);
    }

    $total_rows = $db->sql_numrows($result);
    $db->sql_freeresult($result);
    */

    return $attachments->fetchAll();
}

/**
 * perform LIMIT statement on arrays
 * @param array$array
 * @param int $start
 * @param int $pagelimit
 * @return array
*/
function limit_array($array, $start, $pagelimit)
{
    $count = count($array);

    // array from start - start+pagelimit
    $limit = ($count < ($start + $pagelimit)) ? $count : $start + $pagelimit;

    $limit_array = [];

    for ($i = $start; $i < $limit; $i++) {
        $limit_array[] = $array[$i];
    }

    return $limit_array;
}

?>