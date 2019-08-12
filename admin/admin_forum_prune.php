<?php
/***************************************************************************
*                             admin_forum_prune.php
*                              -------------------
*     begin                : Mon Jul 31, 2001
*     copyright            : (C) 2001 The phpBB Group
*     email                : support@phpbb.com
*
*     $Id: admin_forum_prune.php 3207 2002-12-18 14:14:11Z psotfx $
*
****************************************************************************/

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 ***************************************************************************/

define('IN_PHPBB', true);

if (!empty($setmodules)) {
	$filename = basename(__FILE__);
	$module['Forums']['Prune'] = $filename;

	return;
}

//
// Load default header
//
$sep = DIRECTORY_SEPARATOR;
$phpbb_root_path = '.' . $sep . '..' . $sep;

require_once '.' . $sep . 'pagestart.php';
require_once $phpbb_root_path . 'includes' . $sep . 'prune.php';

//
// Get the forum ID for pruning
//
if (isset($_GET[POST_FORUM_URL]) || isset($_POST[POST_FORUM_URL])) {
    $forum_id = isset($_POST[POST_FORUM_URL]) ? $_POST[POST_FORUM_URL] : $_GET[POST_FORUM_URL];

    if ($forum_id === -1) {
        $forum_sql = false;
    } else {
        $forum_id = (int)$forum_id;
        $forum_sql = true;
    }
} else {
    $forum_id = '';
    $forum_sql = false;
}
//
// Get a list of forum's or the data for the forum that we are pruning.
//

$forums = dibi::select('f.*')
    ->from(FORUMS_TABLE)
    ->as('f')
    ->innerJoin(CATEGORIES_TABLE)
    ->as('c')
    ->on('c.cat_id = f.cat_id');

if ($forum_sql) {
    $forums->where('forum_id = %i', $forum_id);
}

$forums = $forums->orderBy('c.cat_order', dibi::ASC)
    ->orderBy('f.forum_order', dibi::ASC)
    ->fetchAll();

//
// Check for submit to be equal to Prune. If so then proceed with the pruning.
//
if (isset($_POST['doprune'])) {
	$prunedays = isset($_POST['prunedays']) ? (int)$_POST['prunedays'] : 0;

	// Convert days to seconds for timestamp functions...
    $user_timezone = isset($userdata['user_timezone']) ? $userdata['user_timezone'] : $board_config['board_timezone'];

    $prune_date = new DateTime();
    $prune_date->setTimezone(new DateTimeZone($user_timezone));
    $prune_date->sub(new DateInterval('P' . $prunedays . 'D'))
        ->getTimestamp();

    $template->setFileNames(['body' => 'admin/forum_prune_result_body.tpl']);

    foreach ($forums as $forum) {
        $prune_result = Prune::run($forum->forum_id, $prune_date->getTimestamp());

        sync('forum', $forum->forum_id);

        $row_color = !($i % 2) ? $theme['td_color1'] : $theme['td_color2'];
        $row_class = !($i % 2) ? $theme['td_class1'] : $theme['td_class2'];

        $template->assignBlockVars('prune_results',
            [
                'ROW_COLOR' => '#' . $row_color,
                'ROW_CLASS' => $row_class,

                'FORUM_NAME'   => htmlspecialchars($forum->forum_name, ENT_QUOTES),
                'FORUM_TOPICS' => $prune_result['topics'],
                'FORUM_POSTS'  => $prune_result['posts']
            ]
        );   
    }

    $template->assignVars(
        [
            'L_FORUM_PRUNE'   => $lang['Forum_Prune'],
            'L_FORUM'         => $lang['Forum'],
            'L_TOPICS_PRUNED' => $lang['Topics_pruned'],
            'L_POSTS_PRUNED'  => $lang['Posts_pruned'],
            'L_PRUNE_RESULT'  => $lang['Prune_success']
        ]
    );
} else {
	//
	// If they haven't selected a forum for pruning yet then
	// display a select box to use for pruning.
	//
	if (!isset($_POST[POST_FORUM_URL]) && !isset($_GET[POST_FORUM_URL])) {
		//
		// Output a selection table if no forum id has been specified.
		//
        $template->setFileNames(['body' => 'admin/forum_prune_select_body.tpl']);

        $select_list = '<select name="' . POST_FORUM_URL . '">';
		$select_list .= '<option value="-1">' . $lang['All_Forums'] . '</option>';

		foreach ($forums as $forum) {
            $select_list .= '<option value="' . $forum->forum_id . '">' . htmlspecialchars($forum->forum_name, ENT_QUOTES) . '</option>';
        }

		$select_list .= '</select>';

		//
		// Assign the template variables.
		//
        $template->assignVars(
            [
                'L_FORUM_PRUNE'  => $lang['Forum_Prune'],
                'L_SELECT_FORUM' => $lang['Select_a_Forum'],
                'L_LOOK_UP'      => $lang['Look_up_Forum'],

                'S_FORUMPRUNE_ACTION' => Session::appendSid('admin_forum_prune.php'),
                'S_FORUMS_SELECT'     => $select_list
            ]
        );
	} else {
	    if (isset($_POST[POST_FORUM_URL])) {
            $forum_id = (int)$_POST[POST_FORUM_URL];
        } elseif (isset($_GET[POST_FORUM_URL])) {
	        $forum_id = (int) $_GET[POST_FORUM_URL];
        }
		
		//
		// Output the form to retrieve Prune information.
		//
        $template->setFileNames(['body' => 'admin/forum_prune_body.tpl']);

        $forum_name = $forum_id === -1 ? $lang['All_Forums'] : $forums[0]->forum_name;

		$prune_data = $lang['Prune_topics_not_posted'] . ' ';
		$prune_data .= '<input class="post" type="text" name="prunedays" size="4"> ' . $lang['Days'];

		$hidden_input = '<input type="hidden" name="' . POST_FORUM_URL . '" value="' . $forum_id . '" />';

		//
		// Assign the template variables.
		//
		$template->assignVars(
		    [
                'FORUM_NAME' => htmlspecialchars($forum_name, ENT_QUOTES),
                'L_FORUM'    => $lang['Forum'],
                'L_DO_PRUNE' => $lang['Do_Prune'],

                'L_FORUM_PRUNE'         => $lang['Forum_Prune'],
                'L_FORUM_PRUNE_EXPLAIN' => $lang['Forum_Prune_explain'],

                'S_FORUMPRUNE_ACTION' => Session::appendSid('admin_forum_prune.php'),
                'S_PRUNE_DATA'        => $prune_data,
                'S_HIDDEN_VARS'       => $hidden_input
            ]
		);
	}
}
//
// Actually output the page here.
//
$template->pparse('body');

require_once '.' . $sep . 'page_footer_admin.php';

?>