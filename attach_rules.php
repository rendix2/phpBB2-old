<?php
/**
 *
 * @package attachment_mod
 * @version $Id: attach_rules.php,v 1.2 2005/11/05 12:23:33 acydburn Exp $
 * @copyright (c) 2002 Meik Sievertsen
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *
 */

$sep = DIRECTORY_SEPARATOR;
$phpbb_root_path = '.' . $sep;

require_once $phpbb_root_path . 'common.php';

$forum_id = get_var(POST_FORUM_URL, 0);
$privmsg = !$forum_id;

// Start Session Management
$userdata = init_userprefs(PAGE_RULES);

// Display the allowed Extension Groups and Upload Size
if ($privmsg) {
    $auth['auth_attachments'] = $userdata['user_level'] !== ADMIN ? (int)$attach_config['allow_pm_attach'] : true;
    $auth['auth_view'] = true;
    $_max_filesize = $attach_config['max_filesize_pm'];
} else {
    $auth = Auth::authorize(Auth::AUTH_ALL, $forum_id, $userdata);
    $_max_filesize = $attach_config['max_filesize'];
}

if (!($auth['auth_attachments'] && $auth['auth_view'])) {
    message_die(GENERAL_ERROR, 'You are not allowed to call this file (ID:2)');
}

$template->setFileNames(['body' => 'posting_attach_rules.tpl']);

$rows = dibi::select(['group_id', 'group_name', 'max_filesize', 'forum_permissions'])
    ->from(Tables::ATTACH_EXTENSION_GROUPS_TABLE)
    ->where('[allow_group] = %i', 1)
    ->orderBy('group_name', dibi::ASC)
    ->fetchAll();

$allowed_filesize = [];

// Ok, only process those Groups allowed within this forum
$nothing = true;
foreach ($rows as $row) {
    $auth_cache = trim($row->forum_permissions);

    $permit = $privmsg ? true : ((is_forum_authed($auth_cache, $forum_id)) || trim($row->forum_permissions) === '');

    if ($permit) {
        $nothing = false;
        $f_size = (int)trim($row->max_filesize);

        $det_filesize = $f_size ? $f_size : $_max_filesize;
        $max_filesize = $det_filesize === 0 ? $lang['Unlimited'] : get_formatted_filesize($det_filesize);

        $template->assignBlockVars('group_row',
            [
                'GROUP_RULE_HEADER' => sprintf($lang['Group_rule_header'], $row->group_name, $max_filesize)
            ]
        );

        $extensions = dibi::select('extension')
            ->from(Tables::ATTACH_EXTENSION_TABLE)
            ->where('[group_id] = %i', $row->group_id)
            ->orderBy('extension', dibi::ASC)
            ->fetchAll();

        foreach ($extensions as $extension) {
            $template->assignBlockVars('group_row.extension_row',
                [
                    'EXTENSION' => $extension->extension
                ]
            );
        }
    }
}

PageHelper::header($template, $userdata, $board_config, $lang, $images, $theme, $lang['Attach_rules_title'], true);

$template->assignVars(
    [
        'L_RULES_TITLE' => $lang['Attach_rules_title'],
        'L_CLOSE_WINDOW' => $lang['Close_window'],
        'L_EMPTY_GROUP_PERMS' => $lang['Note_user_empty_group_permissions']
    ]
);

if ($nothing) {
    $template->assignBlockVars('switch_nothing', []);
}

$template->pparse('body');

?>