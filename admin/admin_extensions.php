<?php
/**
 *
 * @package attachment_mod
 * @version $Id: admin_extensions.php,v 1.4 2006/04/09 13:25:51 acydburn Exp $
 * @copyright (c) 2002 Meik Sievertsen
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *
 */

use Nette\Caching\Cache;

/**
 */
define('IN_PHPBB', true);

// Let's set the root dir for phpBB

$sep = DIRECTORY_SEPARATOR;
$phpbb_root_path = '..' . $sep;

require_once 'pagestart.php';
require_once $phpbb_root_path . 'attach_mod' . $sep . 'includes' . $sep . 'constants.php';

if (!(int)$attach_config['allow_ftp_upload']) {
    if (($attach_config['upload_dir'][0] === '/') || (($attach_config['upload_dir'][0] !== '/') && ($attach_config['upload_dir'][1] === ':'))) {
        $upload_dir = $attach_config['upload_dir'];
    } else {
        $upload_dir = $phpbb_root_path . $attach_config['upload_dir'];
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
$types_download = [INLINE_LINK, PHYSICAL_LINK];
$modes_download = ['inline', 'physical'];

$types_category = [IMAGE_CAT, STREAM_CAT, SWF_CAT];
$modes_category = [$lang['Category_images'], $lang['Category_stream_files'], $lang['Category_swf_files']];

$size = get_var('size', '');
$mode = get_var('mode', '');
$e_mode = get_var('e_mode', '');

$submit = isset($_POST['submit']);

// Get Attachment Config
$cache = new Cache($storage, Tables::ATTACH_CONFIG_TABLE);
$key = Tables::ATTACH_CONFIG_TABLE;

$attach_config = $cache->load($key);

if (!$attach_config) {
    $attach_config = get_config();
    $cache->save($key, $attach_config);
}

// Extension Management
if ($submit && $mode === 'extensions') {
    // Change Extensions ?
    $extension_change_list = get_var('extension_change_list', [0]);
    $extension_explain_list = get_var('extension_explain_list', ['']);
    $group_select_list = get_var('group_select', [0]);

    // Generate correct Change List
    $extensions = [];

    for ($i = 0; $i < count($extension_change_list); $i++) {
        $extensions['_' . $extension_change_list[$i]]['comment'] = $extension_explain_list[$i];
        $extensions['_' . $extension_change_list[$i]]['group_id'] = (int)$group_select_list[$i];
    }

    $extension_rows = dibi::select('*')
        ->from(Tables::ATTACH_EXTENSION_TABLE)
        ->orderBy('ext_id')
        ->fetchAll();

    foreach ($extension_rows as $extension_row) {
        if ($extension_row->comment !== $extensions['_' . $extension_row->ext_id]['comment'] || (int)$extension_row->group_id !== (int)$extensions['_' . $extension_row->ext_id]['group_id']) {
            $sql_ary = [
                'comment' => (string)$extensions['_' . $extension_row->ext_id]['comment'],
                'group_id' => (int)$extensions['_' . $extension_row->ext_id]['group_id']
            ];

            dibi::update(Tables::ATTACH_EXTENSION_TABLE, $sql_ary)
                ->where('[ext_id] = %i', $extension_row->ext_id)
                ->execute();
        }
    }

    // Delete Extension?
    $extension_id_list = get_var('extension_id_list', [0]);

    if (count($extension_id_list)) {
        dibi::delete(Tables::ATTACH_EXTENSION_TABLE)
            ->where('[ext_id] IN %in', $extension_id_list)
            ->execute();
    }

    // Add Extension ?
    $extension = get_var('add_extension', '');
    $extension_explain = get_var('add_extension_explain', '');
    $extension_group = get_var('add_group_select', 0);

    $add = isset($_POST['add_extension_check']);

    if ($extension !== '' && $add) {
        $template->assignVars(
            [
                'ADD_EXTENSION' => $extension,
                'ADD_EXTENSION_EXPLAIN' => $extension_explain
            ]
        );

        if (!$error) {
            // check extension
            $rows = dibi::select('extension')
                ->from(Tables::ATTACH_EXTENSION_TABLE)
                ->fetchAll();

            foreach ($rows as $row) {
                if (strtolower(trim($row->extension)) === strtolower(trim($extension))) {
                    $error = true;

                    if (isset($error_msg)) {
                        $error_msg .= '<br />';
                    }

                    $error_msg .= sprintf($lang['Extension_exist'], strtolower(trim($extension)));
                }
            }

            // Extension Forbidden?
            if (!$error) {
                $rows = dibi::select(['extension'])
                    ->from(Tables::ATTACH_FORBIDEN_EXTENSIONS_TABLE)
                    ->fetchAll();

                foreach ($rows as $row) {
                    if (strtolower(trim($row->extension)) === strtolower(trim($extension))) {
                        $error = true;

                        if (isset($error_msg)) {
                            $error_msg .= '<br />';
                        }

                        $error_msg .= sprintf($lang['Unable_add_forbidden_extension'], strtolower(trim($extension)));
                    }
                }
            }

            if (!$error) {
                $sql_ary = [
                    'group_id' => (int)$extension_group,
                    'extension' => (string)strtolower($extension),
                    'comment' => (string)$extension_explain
                ];

                dibi::insert(Tables::ATTACH_EXTENSION_TABLE, $sql_ary)->execute();
            }
        }
    }

    if (!$error) {
        $message = $lang['Attach_config_updated'] . '<br /><br />' . sprintf($lang['Click_return_attach_config'], '<a href="' . Session::appendSid('admin_extensions.php?mode=extensions') . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid("index.php?pane=right") . '">', '</a>');

        message_die(GENERAL_MESSAGE, $message);
    }
}

if ($mode === 'extensions') {
    // Extensions
    $template->setFileNames(['body' => 'admin/attach_extensions.tpl']);

    $template->assignVars(
        [
            'L_EXTENSIONS_TITLE' => $lang['Manage_extensions'],
            'L_EXTENSIONS_EXPLAIN' => $lang['Manage_extensions_explain'],
            'L_SELECT' => $lang['Select'],
            'L_EXPLANATION' => $lang['Explanation'],
            'L_EXTENSION' => $lang['Extension'],
            'L_EXTENSION_GROUP' => $lang['Extension_group'],
            'L_ADD_NEW' => $lang['Add_new'],
            'L_DELETE' => $lang['Delete'],
            'L_CANCEL' => $lang['Cancel'],
            'L_SUBMIT' => $lang['Submit'],

            'S_CANCEL_ACTION' => Session::appendSid("admin_extensions.php?mode=extensions"),
            'S_ATTACH_ACTION' => Session::appendSid("admin_extensions.php?mode=extensions")
        ]
    );

    if ($submit) {
        $template->assignVars(
            [
                'S_ADD_GROUP_SELECT' => group_select('add_group_select', $extension_group)
            ]
        );
    } else {
        $template->assignVars(
            [
                'S_ADD_GROUP_SELECT' => group_select('add_group_select')
            ]
        );
    }

    $extension_rows = dibi::select('*')
        ->from(Tables::ATTACH_EXTENSION_TABLE)
        ->orderBy('group_id')
        ->fetchAll();

    $num_extension_row = count($extension_rows);

    if ($num_extension_row > 0) {
        $num_extension_row = sort_multi_array($num_extension_row, 'group_name', 'ASC');

        foreach ($extension_rows as $i => $extension_row) {
            if ($submit) {
                $template->assignBlockVars('extension_row',
                    [
                        'EXT_ID' => $extension_row->ext_id,
                        'EXTENSION' => $extension_row->extension,
                        'EXTENSION_EXPLAIN' => $extension_explain_list[$i],
                        'S_GROUP_SELECT' => group_select('group_select[]', $group_select_list[$i])
                    ]
                );
            } else {
                $template->assignBlockVars('extension_row',
                    [
                        'EXT_ID' => $extension_row->ext_id,
                        'EXTENSION' => $extension_row->extension,
                        'EXTENSION_EXPLAIN' => $extension_row->comment,
                        'S_GROUP_SELECT' => group_select('group_select[]', $extension_row->group_id)]
                );
            }
        }
    }

}

// Extension Groups
if ($submit && $mode === 'groups') {
    // Change Extension Groups ?
    $group_change_list = get_var('group_change_list', [0]);
    $extension_group_list = get_var('extension_group_list', ['']);
    $group_allowed_list = get_var('allowed_list', [0]);
    $download_mode_list = get_var('download_mode_list', [0]);
    $category_list = get_var('category_list', [0]);
    $upload_icon_list = get_var('upload_icon_list', ['']);
    $filesize_list = get_var('max_filesize_list', [0]);
    $size_select_list = get_var('size_select_list', ['']);

    $allowed_list = [];

    for ($i = 0; $i < count($group_allowed_list); $i++) {
        for ($j = 0; $j < count($group_change_list); $j++) {
            if ($group_allowed_list[$i] === $group_change_list[$j]) {
                $allowed_list[$j] = 1;
            }
        }
    }

    for ($i = 0; $i < count($group_change_list); $i++) {
        $allowed = isset($allowed_list[$i]);

        $filesize_list[$i] = ($size_select_list[$i] === 'kb') ? round($filesize_list[$i] * 1024) : (($size_select_list[$i] === 'mb') ? round($filesize_list[$i] * 1048576) : $filesize_list[$i]);

        $sql_ary = [
            'group_name' => (string)$extension_group_list[$i],
            'cat_id' => (int)$category_list[$i],
            'allow_group' => (int)$allowed,
            'download_mode' => (int)$download_mode_list[$i],
            'upload_icon' => (string)$upload_icon_list[$i],
            'max_filesize' => (int)$filesize_list[$i]
        ];

        dibi::update(Tables::ATTACH_EXTENSION_GROUPS_TABLE, $sql_ary)
            ->where('[group_id] = %i', $group_change_list[$i])
            ->execute();
    }

    // Delete Extension Groups
    $group_id_list = get_var('group_id_list', [0]);

    if (count($group_id_list)) {
        dibi::delete(Tables::ATTACH_EXTENSION_GROUPS_TABLE)
            ->where('[group_id] IN %in', $group_id_list)
            ->execute();

        // Set corresponding Extensions to a pending Group
        dibi::update(Tables::ATTACH_EXTENSION_TABLE, ['group_id' => 0])
            ->where('[group_id] IN %in', $group_id_list)
            ->execute();
    }

    // Add Extensions?
    $extension_group = get_var('add_extension_group', '');
    $download_mode = get_var('add_download_mode', 0);
    $cat_id = get_var('add_category', 0);
    $upload_icon = get_var('add_upload_icon', '');
    $filesize = get_var('add_max_filesize', 0);
    $size_select = get_var('add_size_select', '');

    $is_allowed = isset($_POST['add_allowed']);
    $add = isset($_POST['add_extension_group_check']);

    if ($extension_group !== '' && $add) {
        // check Extension Group
        $rows = dibi::select('group_name')
            ->from(Tables::ATTACH_EXTENSION_GROUPS_TABLE)
            ->fetchAll();

        foreach ($rows as $row) {
            if ($row->group_name === $extension_group) {
                $error = true;

                if (isset($error_msg)) {
                    $error_msg .= '<br />';
                }

                $error_msg .= sprintf($lang['Extension_group_exist'], $extension_group);
            }
        }

        if (!$error) {
            $filesize = ($size_select === 'kb') ? round($filesize * 1024) : (($size_select === 'mb') ? round($filesize * 1048576) : $filesize);

            $sql_ary = [
                'group_name' => (string)$extension_group,
                'cat_id' => (int)$cat_id,
                'allow_group' => (int)$is_allowed,
                'download_mode' => (int)$download_mode,
                'upload_icon' => (string)$upload_icon,
                'max_filesize' => (int)$filesize,
                'forum_permissions' => ''
            ];

            dibi::insert(Tables::ATTACH_EXTENSION_GROUPS_TABLE, $sql_ary)->execute();
        }
    }

    if (!$error) {
        $message = $lang['Attach_config_updated'] . '<br /><br />' . sprintf($lang['Click_return_attach_config'], '<a href="' . Session::appendSid("admin_extensions.php?mode=groups") . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid("index.php?pane=right") . '">', '</a>');

        message_die(GENERAL_MESSAGE, $message);
    }
}

if ($mode === 'groups') {
    // Extension Groups
    $template->setFileNames(['body' => 'admin/attach_extension_groups.tpl']);

    if (!$size && !$submit) {
        $max_add_filesize = $attach_config['max_filesize'];

        $size = ($max_add_filesize >= 1048576) ? 'mb' : (($max_add_filesize >= 1024) ? 'kb' : 'b');
    }

    if ($max_add_filesize >= 1048576) {
        $max_add_filesize = round($max_add_filesize / 1048576 * 100) / 100;
    } else if ($max_add_filesize >= 1024) {
        $max_add_filesize = round($max_add_filesize / 1024 * 100) / 100;
    }

    $viewgroup = get_var(POST_GROUPS_URL, 0);

    $template->assignVars(
        [
            'L_EXTENSION_GROUPS_TITLE' => $lang['Manage_extension_groups'],
            'L_EXTENSION_GROUPS_EXPLAIN' => $lang['Manage_extension_groups_explain'],
            'L_EXTENSION_GROUP' => $lang['Extension_group'],
            'L_ADD_NEW' => $lang['Add_new'],
            'L_ALLOWED' => $lang['Allowed'],
            'L_DELETE' => $lang['Delete'],
            'L_CANCEL' => $lang['Cancel'],
            'L_SUBMIT' => $lang['Submit'],
            'L_SPECIAL_CATEGORY' => $lang['Special_category'],
            'L_DOWNLOAD_MODE' => $lang['Download_mode'],
            'L_UPLOAD_ICON' => $lang['Upload_icon'],
            'L_MAX_FILESIZE' => $lang['Max_groups_filesize'],
            'L_ALLOWED_FORUMS' => $lang['Allowed_forums'],
            'L_FORUM_PERMISSIONS' => $lang['Ext_group_permissions'],

            'ADD_GROUP_NAME' => (isset($submit)) ? $extension_group : '',
            'MAX_FILESIZE' => $max_add_filesize,

            'S_FILESIZE' => size_select('add_size_select', $size),
            'S_ADD_DOWNLOAD_MODE' => download_select('add_download_mode'),
            'S_SELECT_CAT' => category_select('add_category'),
            'S_CANCEL_ACTION' => Session::appendSid("admin_extensions.php?mode=groups"),
            'S_ATTACH_ACTION' => Session::appendSid("admin_extensions.php?mode=groups")
        ]
    );

    $extension_groups = dibi::select('*')
        ->from(Tables::ATTACH_EXTENSION_GROUPS_TABLE)
        ->fetchAll();

    foreach ($extension_groups as $extension_group) {
        // Format the filesize
        if (!$extension_group->max_filesize) {
            $extension_group->max_filesize = $attach_config['max_filesize'];
        }

        $size_format = ($extension_group->max_filesize >= 1048576) ? 'mb' : (($extension_group->max_filesize >= 1024) ? 'kb' : 'b');

        if ($extension_group->max_filesize >= 1048576) {
            $extension_group->max_filesize = round($extension_group->max_filesize / 1048576 * 100) / 100;
        } else if ($extension_group->max_filesize >= 1024) {
            $extension_group->max_filesize = round($extension_group->max_filesize / 1024 * 100) / 100;
        }

        $s_allowed = ($extension_group->allow_group === 1) ? 'checked="checked"' : '';

        $template->assignBlockVars('grouprow',
            [
                'GROUP_ID' => $extension_group->group_id,
                'EXTENSION_GROUP' => $extension_group->group_name,
                'UPLOAD_ICON' => $extension_group->upload_icon,

                'S_ALLOW_SELECTED' => $s_allowed,
                'S_SELECT_CAT' => category_select('category_list[]', $extension_group->group_id),
                'S_DOWNLOAD_MODE' => download_select('download_mode_list[]', $extension_group->group_id),
                'S_FILESIZE' => size_select('size_select_list[]', $size_format),

                'MAX_FILESIZE' => $extension_group->max_filesize,
                'CAT_BOX' => ($viewgroup === $extension_group->group_id) ? $lang['Decollapse'] : $lang['Collapse'],
                'U_VIEWGROUP' => ($viewgroup === $extension_group->group_id) ? Session::appendSid("admin_extensions.php?mode=groups") : Session::appendSid("admin_extensions.php?mode=groups&" . POST_GROUPS_URL . "=" . $extension_group->group_id),
                'U_FORUM_PERMISSIONS' => Session::appendSid("admin_extensions.php?mode=$mode&amp;e_mode=perm&amp;e_group=" . $extension_group->group_id)
            ]
        );

        if ($viewgroup && $viewgroup === $extension_group->group_id) {
            $extensions = dibi::select(['comment', 'extension'])
                ->from(Tables::ATTACH_EXTENSION_TABLE)
                ->where('[group_id] = %i', $viewgroup)
                ->fetchAll();

            foreach ($extensions as $extension) {
                $template->assignBlockVars('grouprow.extensionrow',
                    [
                        'EXPLANATION' => $extension->comment,
                        'EXTENSION' => $extension->extension
                    ]
                );
            }
        }
    }
}

// Forbidden Extensions
if ($submit && $mode === 'forbidden') {
    // Store new forbidden extension or delete selected forbidden extensions
    $extension = get_var('extension_id_list', [0]);

    if (count($extension)) {
        dibi::delete(Tables::ATTACH_FORBIDEN_EXTENSIONS_TABLE)
            ->where('[ext_id] IN %in', $extension)
            ->execute();
    }

    $extension = get_var('add_extension', '');

    $add = isset($_POST['add_extension_check']);

    if ($extension !== '' && $add) {
        // Check Extension
        $rows = dibi::select('extension')
            ->from(Tables::ATTACH_FORBIDEN_EXTENSIONS_TABLE)
            ->fetchAll();

        foreach ($rows as $row) {
            if ($row->extension === $extension) {
                $error = true;

                if (isset($error_msg)) {
                    $error_msg .= '<br />';
                }

                $error_msg .= sprintf($lang['Forbidden_extension_exist'], $extension);
            }
        }

        // Check, if extension is allowed
        if (!$error) {
            $extensions = dibi::select('extension')
                ->from(Tables::ATTACH_EXTENSION_TABLE)
                ->fetchPairs(null, 'extension');

            foreach ($extensions as $extensionValue) {
                if (strtolower(trim($extensionValue)) === strtolower(trim($extension))) {
                    $error = true;

                    if (isset($error_msg)) {
                        $error_msg .= '<br />';
                    }

                    $error_msg .= sprintf($lang['Extension_exist_forbidden'], $extension);
                }
            }
        }

        if (!$error) {
            dibi::insert(Tables::ATTACH_FORBIDEN_EXTENSIONS_TABLE, ['extension' => $extension])->execute();
        }
    }

    if (!$error) {
        $message = $lang['Attach_config_updated'] . '<br /><br />' . sprintf($lang['Click_return_attach_config'], '<a href="' . Session::appendSid("admin_extensions.php?mode=forbidden") . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_admin_index'], '<a href="' . Session::appendSid("index.php?pane=right") . '">', '</a>');

        message_die(GENERAL_MESSAGE, $message);
    }

}

if ($mode === 'forbidden') {
    $template->setFileNames(['body' => 'admin/attach_forbidden_extensions.tpl']);

    $template->assignVars(
        [
            'S_ATTACH_ACTION' => Session::appendSid('admin_extensions.php?mode=forbidden'),

            'L_EXTENSIONS_TITLE' => $lang['Manage_forbidden_extensions'],
            'L_EXTENSIONS_EXPLAIN' => $lang['Manage_forbidden_extensions_explain'],
            'L_EXTENSION' => $lang['Extension'],
            'L_ADD_NEW' => $lang['Add_new'],
            'L_SUBMIT' => $lang['Submit'],
            'L_DELETE' => $lang['Delete']
        ]
    );

    $extensionRows = dibi::select('*')
        ->from(Tables::ATTACH_FORBIDEN_EXTENSIONS_TABLE)
        ->orderBy('extension')
        ->fetchAll();

    foreach ($extensionRows as $extensionRow) {
        $template->assignBlockVars('extensionrow',
            [
                'EXTENSION_ID' => $extensionRow->ext_id,
                'EXTENSION_NAME' => $extensionRow->extension
            ]
        );
    }
}

if ($e_mode === 'perm') {
    $group = get_var('e_group', 0);

    $add_forum = isset($_POST['add_forum']);
    $delete_forum = isset($_POST['del_forum']);

    if (isset($_POST['close_perm'])) {
        $e_mode = '';
    }
}

// Add Forums
if ($add_forum && $e_mode === 'perm' && $group) {
    $add_forums_list = get_var('entries', [0]);
    $add_all_forums = false;

    foreach ($add_forums_list as $value) {
        if ($value === GPERM_ALL) {
            $add_all_forums = true;

            break;
        }
    }

    // If we add ALL FORUMS, we are able to overwrite the Permissions
    if ($add_all_forums) {
        dibi::update(Tables::ATTACH_EXTENSION_GROUPS_TABLE, ['forum_permissions' => ''])
            ->where('[group_id] = %i', $group)
            ->execute();
    }

    // Else we have to add Permissions
    if (!$add_all_forums) {
        $row = dibi::select('forum_permissions')
            ->from(Tables::ATTACH_EXTENSION_GROUPS_TABLE)
            ->where('[group_id] = %i', $group)
            ->fetch();

        if (trim($row->forum_permissions) === '') {
            $auth_p = [];
        } else {
            $auth_p = auth_unpack($row->forum_permissions);
        }

        // Generate array for Auth_Pack, do not add doubled forums
        foreach ($add_forums_list as $value) {
            if (!in_array($value, $auth_p)) {
                $auth_p[] = $value;
            }
        }

        $auth_bitstream = auth_pack($auth_p);

        dibi::update(Tables::ATTACH_EXTENSION_GROUPS_TABLE, ['forum_permissions' => $auth_bitstream])
            ->where('[group_id] = %i', $group)
            ->execute();
    }
}

// Delete Forums
if ($delete_forum && $e_mode === 'perm' && $group) {
    $delete_forums_list = get_var('entries', [0]);

    // Get the current Forums
    $row = dibi::select('forum_permissions')
        ->from(Tables::ATTACH_EXTENSION_GROUPS_TABLE)
        ->where('[group_id] = %i', $group)
        ->fetch();

    $auth_p2 = auth_unpack(trim($row->forum_permissions));
    $auth_p = [];

    // Generate array for Auth_Pack, delete the chosen ones
    foreach ($auth_p2 as $value) {
        if (!in_array($value, $delete_forums_list)) {
            $auth_p[] = $value;
        }
    }

    $auth_bitstream = (count($auth_p) > 0) ? auth_pack($auth_p) : '';

    dibi::update(Tables::ATTACH_EXTENSION_GROUPS_TABLE, ['forum_permissions' => $auth_bitstream])
        ->where('[group_id] = %i', $group)
        ->execute();
}

// Display the Group Permissions Box for configuring it
if ($e_mode === 'perm' && $group) {
    $template->setFileNames(['perm_box' => 'admin/extension_groups_permissions.tpl']);

    $row = dibi::select(['group_name', 'forum_permissions'])
        ->from(Tables::ATTACH_EXTENSION_GROUPS_TABLE)
        ->where('[group_id] = %i', $group)
        ->fetch();

    $group_name = $row->group_name;
    $allowed_forums = trim($row->forum_permissions);

    $forum_perm = [];

    if ($allowed_forums === '') {
        $forum_perm[0]['forum_id'] = 0;
        $forum_perm[0]['forum_name'] = $lang['Perm_all_forums'];
    } else {
        $forum_p = [];
        $act_id = 0;
        $forum_p = auth_unpack($allowed_forums);

        $forum_perm = dibi::select(['forum_id', 'forum_name'])
            ->from(Tables::FORUMS_TABLE)
            ->where('[forum_id] IN %in', $forum_p)
            ->fetchAll();
    }

    foreach ($forum_perm as $forum) {
        $template->assignBlockVars('allow_option_values',
            [
                'VALUE' => $forum->forum_id,
                'OPTION' => $forum->forum_name
            ]
        );
    }

    $template->assignVars(
        [
            'L_GROUP_PERMISSIONS_TITLE' => sprintf($lang['Group_permissions_title'], trim($group_name)),
            'L_GROUP_PERMISSIONS_EXPLAIN' => $lang['Group_permissions_explain'],
            'L_REMOVE_SELECTED' => $lang['Remove_selected'],
            'L_CLOSE_WINDOW' => $lang['Close_window'],
            'L_ADD_FORUMS' => $lang['Add_forums'],
            'L_ADD_SELECTED' => $lang['Add_selected'],
            'L_RESET' => $lang['Reset'],
            'A_PERM_ACTION' => Session::appendSid("admin_extensions.php?mode=groups&amp;e_mode=perm&amp;e_group=$group")
        ]
    );

    $forum_option_values = [GPERM_ALL => $lang['Perm_all_forums']];

    $forum_option_database_values = dibi::select(['forum_id', 'forum_name'])
        ->from(Tables::FORUMS_TABLE)
        ->fetchPairs('forum_id', 'forum_name');

    $forum_option_values = array_merge($forum_option_values, $forum_option_database_values);

    foreach ($forum_option_values as $value => $option) {
        $template->assignBlockVars('forum_option_values',
            [
                'VALUE' => $value,
                'OPTION' => $option
            ]
        );
    }

    $template->assignVarFromHandle('GROUP_PERMISSIONS_BOX', 'perm_box');

    $empty_perm_forums = [];

    $rows = dibi::select(['forum_id', 'forum_name'])
        ->from(Tables::FORUMS_TABLE)
        ->where('[auth_attachments] < %i', Auth::AUTH_ADMIN)
        ->fetchAll();

    $rows2 = dibi::select('forum_permissions')
        ->from(Tables::ATTACH_EXTENSION_GROUPS_TABLE)
        ->where('[allow_group] = %i', 1)
        ->orderBy('group_name', dibi::ASC)
        ->fetchAll();

    foreach ($rows as $row) {
        $found_forum = false;

        foreach ($rows2 as $row2) {
            $allowed_forums = auth_unpack(trim($row2->forum_permissions));

            if (in_array($row->forum_id, $allowed_forums) || trim($row2->forum_permissions) === '') {
                $found_forum = true;
                break;
            }
        }

        if (!$found_forum) {
            $empty_perm_forums[$row->forum_id] = $row->forum_name;
        }
    }

    $message = '';

    foreach ($empty_perm_forums as $forum_id => $forum_name) {
        $message .= ($message === '') ? $forum_name : '<br />' . $forum_name;
    }

    if (count($empty_perm_forums) > 0) {
        $template->setFileNames(['perm_reg_header' => 'error_body.tpl']);
        $template->assignVars(['ERROR_MESSAGE' => $lang['Note_admin_empty_group_permissions'] . $message]);
        $template->assignVarFromHandle('PERM_ERROR_BOX', 'perm_reg_header');
    }
}

if ($error) {
    $template->setFileNames(['reg_header' => 'error_body.tpl']);
    $template->assignVars(['ERROR_MESSAGE' => $error_msg]);
    $template->assignVarFromHandle('ERROR_BOX', 'reg_header');
}

$template->assignVars(['ATTACH_VERSION' => sprintf($lang['Attachment_version'], $attach_config['attach_version'])]);

$template->pparse('body');

require_once 'page_footer_admin.php';

?>