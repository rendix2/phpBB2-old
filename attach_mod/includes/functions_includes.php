<?php
/**
 *
 * @package attachment_mod
 * @version $Id: functions_includes.php,v 1.4 2006/09/04 12:35:43 acydburn Exp $
 * @copyright (c) 2002 Meik Sievertsen
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *
 */

/**
 * These are functions called directly from phpBB2 Files
 */

/**
 * Include the FAQ-File (faq.php)
 *
 * @param $lang_file
 */
function attach_faq_include($lang_file)
{
    global $phpbb_root_path, $attach_config;

    if ((int)$attach_config['disable_mod']) {
        return;
    }

    $sep = DIRECTORY_SEPARATOR;

    if ($lang_file === 'lang_faq') {
        $language = attach_mod_get_lang('lang_faq_attach');
        require_once($phpbb_root_path . 'language ' . $sep . 'lang_' . $language . $sep . 'lang_faq_attach.php');
    }
}

/**
 * Setup Forum Authentication (admin/admin_forumauth.php)
 * @param $simple_auth_ary
 * @param $forum_auth_fields
 * @param $field_names
 */
function attach_setup_forum_auth(&$simple_auth_ary, &$forum_auth_fields, &$field_names)
{
    global $lang;

    // Add Attachment Auth
    //					Post Attachments
    $simple_auth_ary[0][] = Auth::AUTH_MOD;
    $simple_auth_ary[1][] = Auth::AUTH_MOD;
    $simple_auth_ary[2][] = Auth::AUTH_MOD;
    $simple_auth_ary[3][] = Auth::AUTH_MOD;
    $simple_auth_ary[4][] = Auth::AUTH_MOD;
    $simple_auth_ary[5][] = Auth::AUTH_MOD;
    $simple_auth_ary[6][] = Auth::AUTH_MOD;

    //					Download Attachments
    $simple_auth_ary[0][] = Auth::AUTH_ALL;
    $simple_auth_ary[1][] = Auth::AUTH_ALL;
    $simple_auth_ary[2][] = Auth::AUTH_REG;
    $simple_auth_ary[3][] = Auth::AUTH_ACL;
    $simple_auth_ary[4][] = Auth::AUTH_ACL;
    $simple_auth_ary[5][] = Auth::AUTH_MOD;
    $simple_auth_ary[6][] = Auth::AUTH_MOD;

    $forum_auth_fields[] = 'auth_attachments';
    $field_names['auth_attachments'] = $lang['Auth_attach'];

    $forum_auth_fields[] = 'auth_download';
    $field_names['auth_download'] = $lang['Auth_download'];
}

/**
 * Called from admin_users.php and admin_groups.php in order to process Quota Settings (admin/admin_users.php:admin/admin_groups.php)
 * @param      $admin_mode
 * @param bool $submit
 * @param      $mode
 */
function attachment_quota_settings($admin_mode, $mode, $submit = false)
{
    global $template, $lang, $lang, $phpbb_root_path, $attach_config;

    $sep = DIRECTORY_SEPARATOR;

    // Make sure constants got included
    require_once $phpbb_root_path . 'attach_mod' . $sep . 'includes' . $sep . 'constants.php';

    if ((int)$attach_config['allow_ftp_upload']) {
        $upload_dir = $attach_config['download_path'];
    } else {
        if ($attach_config['upload_dir'][0] === '/' || ($attach_config['upload_dir'][0] !== '/' && $attach_config['upload_dir'][1] === ':')) {
            $upload_dir = $attach_config['upload_dir'];
        } else {
            $upload_dir = $phpbb_root_path . $attach_config['upload_dir'];
        }
    }

    require_once $phpbb_root_path . 'attach_mod' . $sep . 'includes' . $sep . 'functions_selects.php';
    require_once $phpbb_root_path . 'attach_mod' . $sep . 'includes' . $sep . 'functions_admin.php';

    $user_id = 0;

    if ($admin_mode === 'user') {
        // We overwrite submit here... to be sure
        $submit = isset($_POST['submit']);

        if (!$submit && $mode !== 'save') {
            $user_id = get_var(POST_USERS_URL, 0);
            $u_name = get_var('username', '');

            if (!$user_id && !$u_name) {
                message_die(GENERAL_MESSAGE, $lang['No_user_id_specified']);
            }

            if ($user_id) {
                $this_userdata['user_id'] = $user_id;
            } else {
                // Get userdata is handling the sanitizing of username
                $this_userdata = get_userdata($_POST['username'], true);
            }

            $user_id = (int)$this_userdata['user_id'];
        } else {
            $user_id = get_var('id', 0);

            if (!$user_id) {
                message_die(GENERAL_MESSAGE, $lang['No_user_id_specified']);
            }
        }
    }

    if ($admin_mode === 'user' && !$submit && $mode !== 'save') {
        // Show the contents
        $rows = dibi::select(['quota_limit_id', 'quota_type'])
            ->from(Tables::ATTACH_QUOTA_TABLE)
            ->where('[user_id] = %i', $user_id)
            ->fetchAll();

        $pm_quota = $upload_quota = 0;

        if (count($rows)) {
            foreach ($rows as $row) {
                if ($row->quota_type === QUOTA_UPLOAD_LIMIT) {
                    $upload_quota = $row->quota_limit_id;
                } else if ($row->quota_type === QUOTA_PM_LIMIT) {
                    $pm_quota = $row->quota_limit_id;
                }
            }
        } else {
            // Set Default Quota Limit
            $upload_quota = $attach_config['default_upload_quota'];
            $pm_quota = $attach_config['default_pm_quota'];
        }

        $template->assignVars(
            [
                'S_SELECT_UPLOAD_QUOTA' => quota_limit_select('user_upload_quota', $upload_quota),
                'S_SELECT_PM_QUOTA' => quota_limit_select('user_pm_quota', $pm_quota),
                'L_UPLOAD_QUOTA' => $lang['Upload_quota'],
                'L_PM_QUOTA' => $lang['Pm_quota']
            ]
        );
    }

    if ($admin_mode === 'user' && $submit && $_POST['deleteuser']) {
        process_quota_settings($admin_mode, $user_id, QUOTA_UPLOAD_LIMIT, 0);
        process_quota_settings($admin_mode, $user_id, QUOTA_PM_LIMIT, 0);
    } else if ($admin_mode === 'user' && $submit && $mode === 'save') {
        // Get the contents
        $upload_quota = get_var('user_upload_quota', 0);
        $pm_quota = get_var('user_pm_quota', 0);

        process_quota_settings($admin_mode, $user_id, QUOTA_UPLOAD_LIMIT, $upload_quota);
        process_quota_settings($admin_mode, $user_id, QUOTA_PM_LIMIT, $pm_quota);
    }

    if ($admin_mode === 'group' && $mode === 'newgroup') {
        return;
    }

    if ($admin_mode === 'group' && !$submit && isset($_POST['edit'])) {
        // Get group id again, we do not trust phpBB here, Mods may be installed ;)
        $group_id = get_var(POST_GROUPS_URL, 0);

        // Show the contents
        $rows = dibi::select(['quota_limit_id', 'quota_type'])
            ->from(Tables::ATTACH_QUOTA_TABLE)
            ->where('[group_id] = %i', $group_id)
            ->fetchAll();

        $pm_quota = $upload_quota = 0;

        if (count($rows)) {
            foreach ($rows as $row) {
                if ($row->quota_type === QUOTA_UPLOAD_LIMIT) {
                    $upload_quota = $row->quota_limit_id;
                } else if ($row->quota_type === QUOTA_PM_LIMIT) {
                    $pm_quota = $row->quota_limit_id;
                }
            }
        } else {
            $upload_quota = $attach_config['default_upload_quota'];
            $pm_quota = $attach_config['default_pm_quota'];
        }

        $template->assignVars(
            [
                'S_SELECT_UPLOAD_QUOTA' => quota_limit_select('group_upload_quota', $upload_quota),
                'S_SELECT_PM_QUOTA' => quota_limit_select('group_pm_quota', $pm_quota),
                'L_UPLOAD_QUOTA' => $lang['Upload_quota'],
                'L_PM_QUOTA' => $lang['Pm_quota']
            ]
        );
    }

    if ($admin_mode === 'group' && $submit && isset($_POST['group_delete'])) {
        $group_id = get_var(POST_GROUPS_URL, 0);

        process_quota_settings($admin_mode, $group_id, QUOTA_UPLOAD_LIMIT, 0);
        process_quota_settings($admin_mode, $group_id, QUOTA_PM_LIMIT, 0);
    } else if ($admin_mode === 'group' && $submit) {
        $group_id = get_var(POST_GROUPS_URL, 0);

        // Get the contents
        $upload_quota = get_var('group_upload_quota', 0);
        $pm_quota = get_var('group_pm_quota', 0);

        process_quota_settings($admin_mode, $group_id, QUOTA_UPLOAD_LIMIT, $upload_quota);
        process_quota_settings($admin_mode, $group_id, QUOTA_PM_LIMIT, $pm_quota);
    }
}

/**
 * Called from usercp_viewprofile, displays the User Upload Quota Box, Upload Stats and a Link to the User Attachment Control Panel
 * Groups are able to be grabbed, but it's not used within the Attachment Mod. ;)
 * (includes/UserViewProfilePresenter.php)
 * @param     $user_id
 * @param int $group_id
 */
function display_upload_attach_box_limits($user_id, $group_id = 0)
{
    global $attach_config, $board_config, $phpbb_root_path, $lang, $template, $userdata, $profiledata;

    if ((int)$attach_config['disable_mod']) {
        return;
    }

    if ($userdata['user_level'] !== ADMIN && $userdata['user_id'] !== $user_id) {
        return;
    }

    if (!$user_id) {
        return;
    }

    // Return if the user is not within the to be listed Group
    if ($group_id) {
        if (!user_in_group($user_id, $group_id)) {
            return;
        }
    }

    $user_id = (int)$user_id;
    $group_id = (int)$group_id;

    $attachments = new attach_posting();
    $attachments->page = PAGE_INDEX;

    // Get the assigned Quota Limit. For Groups, we are directly getting the value, because this Quota can change from user to user.
    if ($group_id) {
        $row = dibi::select('l.quota_limit')
            ->from(Tables::ATTACH_QUOTA_TABLE)
            ->as('q')
            ->innerJoin(Tables::ATTACH_QUOTA_LIMITS_TABLE)
            ->as('l')
            ->on('[q.quota_limit_id] = [l.quota_limit_id]')
            ->where('[q.group_id] = %i', $group_id)
            ->where('[quota_type]', QUOTA_UPLOAD_LIMIT)
            ->fetch();

        if ($row) {
            $attach_config['upload_filesize_limit'] = (int)$row->quota_limit;
        } else {
            // Set Default Quota Limit
            $quota_id = (int)$attach_config['default_upload_quota'];

            if ($quota_id === 0) {
                $attach_config['upload_filesize_limit'] = $attach_config['attachment_quota'];
            } else {
                $row = dibi::select('quota_limit')
                    ->from(Tables::ATTACH_QUOTA_LIMITS_TABLE)
                    ->where('[quota_limit_id] = %i', $quota_id)
                    ->fetch();

                if ($row) {
                    $attach_config['upload_filesize_limit'] = $row->quota_limit;
                } else {
                    $attach_config['upload_filesize_limit'] = $attach_config['attachment_quota'];
                }
            }
        }
    } else {
        if (is_array($profiledata)) {
            $attachments->get_quota_limits($profiledata, $user_id);
        } else {
            $attachments->get_quota_limits($userdata, $user_id);
        }
    }

    if ($attach_config['upload_filesize_limit']) {
        $upload_filesize_limit = $attach_config['upload_filesize_limit'];
    } else {
        $upload_filesize_limit = $attach_config['attachment_quota'];
    }

    $user_quota = $upload_filesize_limit === 0 ? $lang['Unlimited'] : get_formatted_filesize($upload_filesize_limit);

    // Get all attach_id's the specific user posted, but only uploads to the board and not Private Messages
    $attach_ids = dibi::select('attach_id')
        ->from(Tables::ATTACH_ATTACHMENT_TABLE)
        ->where('[user_id_1] = %i', $user_id)
        ->where('[privmsgs_id] = %i', 0)
        ->groupBy('attach_id')
        ->fetchPairs(null, 'attach_id');

    $upload_filesize = count($attach_ids) > 0 ? get_total_attach_filesize($attach_ids) : 0;

    $user_uploaded = get_formatted_filesize($upload_filesize);

    $upload_limit_pct = $upload_filesize_limit > 0 ? round(($upload_filesize / $upload_filesize_limit) * 100) : 0;
    $upload_limit_img_length = $upload_filesize_limit > 0 ? round(($upload_filesize / $upload_filesize_limit) * $board_config['privmsg_graphic_length']) : 0;

    if ($upload_limit_pct > 100) {
        $upload_limit_img_length = $board_config['privmsg_graphic_length'];
    }

    $l_box_size_status = sprintf($lang['Upload_percent_profile'], $upload_limit_pct);

    $template->assignBlockVars('switch_upload_limits', []);

    return [
        'UACPLink' => $phpbb_root_path . 'uacp.php?' . POST_USERS_URL . '=' . $user_id . '&amp;sid=' . $userdata['session_id'],

        'l_uploaded' => sprintf($lang['User_uploaded_profile'], $user_uploaded),
        'l_quota' => sprintf($lang['User_quota_profile'], $user_quota),
        'l_percent_full' => sprintf($lang['Upload_percent_profile'], $upload_limit_pct),

        'upload_limit_img_width' => $upload_limit_img_length,
        'upload_limit_percent' => $upload_limit_pct,
    ];
}


?>