<?php
/**
 *
 * @package attachment_mod
 * @version $Id: admin_attach_cp.php,v 1.1 2005/11/07 18:55:05 acydburn Exp $
 * @copyright (c) 2002 Meik Sievertsen
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *
 */

/**
 */
define('IN_PHPBB', true);

$sep = DIRECTORY_SEPARATOR;

// Let's set the root dir for phpBB
$phpbb_root_path = '.' . $sep . '..' . $sep;

require_once 'pagestart.php';

require_once $phpbb_root_path . 'attach_mod' . $sep . 'includes' . $sep . 'constants.php';

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

// Init Variables
$start = get_var('start', 0);
$sort_order = get_var('order', 'ASC');
$sort_order = ($sort_order === 'ASC') ? 'ASC' : 'DESC';
$mode = get_var('mode', '');
$view = get_var('view', '');
$uid = (isset($_POST['u_id'])) ? get_var('u_id', 0) : get_var('uid', 0);

$view = (isset($_POST['search']) && $_POST['search']) ? 'attachments' : $view;

// process modes based on view
if ($view === 'username') {
    $mode_types = [
        'username' => $lang['Sort_Username'],
        'attachments' => $lang['Sort_Attachments'],
        'filesize' => $lang['Sort_Size']
    ];

    if (!$mode) {
        $mode = 'attachments';
        $sort_order = 'DESC';
    }
} else if ($view === 'attachments') {
    $mode_types = [
        'real_filename' => $lang['Sort_Filename'],
        'comment' => $lang['Sort_Comment'],
        'extension' => $lang['Sort_Extension'],
        'filesize' => $lang['Sort_Size'],
        'downloads' => $lang['Sort_Downloads'],
        'post_time' => $lang['Sort_Posttime']
        /*, 'posts' => $lang['Sort_Posts']*/
    ];

    if (!$mode) {
        $mode = 'real_filename';
        $sort_order = 'ASC';
    }
} else if ($view === 'search') {
    $mode_types = [
        'real_filename' => $lang['Sort_Filename'],
        'comment' => $lang['Sort_Comment'],
        'extension' => $lang['Sort_Extension'],
        'filesize' => $lang['Sort_Size'],
        'downloads' => $lang['Sort_Downloads'],
        'post_time' => $lang['Sort_Posttime']
        /*, 'posts' => $lang['Sort_Posts'] */
    ];

    $sort_order = 'DESC';
} else {
    $view = 'stats';
    $mode_types_text = [];
    $sort_order = 'ASC';
    $mode_types = [];
}


// Pagination ?
$do_pagination = $view !== 'stats' && $view !== 'search';

// Set select fields
$view_types = [
    'stats' => $lang['View_Statistic'],
    'search' => $lang['View_Search'],
    'username' => $lang['View_Username'],
    'attachments' => $lang['View_Attachments']
];

$select_view = '<select name="view">';

foreach ($view_types as $value => $text) {
    $selected = ($view === $value) ? ' selected="selected"' : '';
    $select_view .= '<option value="' . $value . '"' . $selected . '>' . $text . '</option>';
}

$select_view .= '</select>';

if (count($mode_types) > 0) {
    $select_sort_mode = '<select name="mode">';

    foreach ($mode_types as $value => $text) {
        $selected = ($mode === $value) ? ' selected="selected"' : '';
        $select_sort_mode .= '<option value="' . $value . '"' . $selected . '>' . $text . '</option>';
    }

    $select_sort_mode .= '</select>';
}

$select_sort_order = '<select name="order">';
if ($sort_order === 'ASC') {
    $select_sort_order .= '<option value="ASC" selected="selected">' . $lang['Sort_Ascending'] . '</option><option value="DESC">' . $lang['Sort_Descending'] . '</option>';
} else {
    $select_sort_order .= '<option value="ASC">' . $lang['Sort_Ascending'] . '</option><option value="DESC" selected="selected">' . $lang['Sort_Descending'] . '</option>';
}
$select_sort_order .= '</select>';

$submit_change = isset($_POST['submit_change']);
$delete = isset($_POST['delete']);
$delete_id_list = get_var('delete_id_list', array(0));

$confirm = isset($_POST['confirm']);

if ($confirm && count($delete_id_list) > 0) {
    $attachments = [];

    delete_attachment(0, $delete_id_list);
} else if ($delete && count($delete_id_list) > 0) {
    // Not confirmed, show confirmation message
    $hidden_fields = '<input type="hidden" name="view" value="' . $view . '" />';
    $hidden_fields .= '<input type="hidden" name="mode" value="' . $mode . '" />';
    $hidden_fields .= '<input type="hidden" name="order" value="' . $sort_order . '" />';
    $hidden_fields .= '<input type="hidden" name="u_id" value="' . $uid . '" />';
    $hidden_fields .= '<input type="hidden" name="start" value="' . $start . '" />';

    foreach ($delete_id_list as $attachId) {
        $hidden_fields .= '<input type="hidden" name="delete_id_list[]" value="' . $attachId . '" />';
    }

    $template->setFileNames(['confirm' => 'confirm_body.tpl']);
    $template->assignVars(
        [
            'MESSAGE_TITLE' => $lang['Confirm'],
            'MESSAGE_TEXT' => $lang['Confirm_delete_attachments'],

            'L_YES' => $lang['Yes'],
            'L_NO' => $lang['No'],

            'S_CONFIRM_ACTION' => Session::appendSid('admin_attach_cp.php'),
            'S_HIDDEN_FIELDS' => $hidden_fields
        ]
    );

    $template->pparse('confirm');

    require_once 'page_footer_admin.php';

    exit;
}

// Assign Default Template Vars
$template->assignVars(
    [
        'L_VIEW' => $lang['View'],
        'L_SUBMIT' => $lang['Submit'],
        'L_CONTROL_PANEL_TITLE' => $lang['Control_panel_title'],
        'L_CONTROL_PANEL_EXPLAIN' => $lang['Control_panel_explain'],

        'S_VIEW_SELECT' => $select_view,
        'S_MODE_ACTION' => Session::appendSid('admin_attach_cp.php')
    ]
);

if ($submit_change && $view === 'attachments') {
    $attach_change_list = get_var('attach_id_list', array(0));
    $attach_comment_list = get_var('attach_comment_list', array(''));
    $attach_download_count_list = get_var('attach_count_list', array(0));

    // Generate correct Change List
    $attachments = [];

    for ($i = 0; $i < count($attach_change_list); $i++) {
        $attachments['_' . $attach_change_list[$i]]['comment'] = $attach_comment_list[$i];
        $attachments['_' . $attach_change_list[$i]]['download_count'] = $attach_download_count_list[$i];
    }

    $attachrows = dibi::select('*')
        ->from(Tables::ATTACH_ATTACHMENTS_DESC_TABLE)
        ->orderBy('attach_id')
        ->fetchAll();

    foreach ($attachrows as $attachrow) {
        if (isset($attachments['_' . $attachrow['attach_id']])) {
            if ($attachrow['comment'] !== $attachments['_' . $attachrow['attach_id']]['comment'] || $attachrow['download_count'] !== $attachments['_' . $attachrow['attach_id']]['download_count']) {
                $updateDate = [
                    'comment' => $attachments['_' . $attachrow['attach_id']]['comment'],
                    'download_count' => $attachments['_' . $attachrow['attach_id']]['download_count']
                ];

                dibi::update(Tables::ATTACH_ATTACHMENTS_DESC_TABLE, $updateDate)
                    ->where('[attach_id] = %i', $attachrow['attach_id'])
                    ->execute();
            }
        }
    }
}

// Statistics
if ($view === 'stats') {
    $template->setFileNames(['body' => 'admin/attach_cp_body.tpl']);

    $number_of_attachments = dibi::select('COUNT(*)')
        ->as('total')
        ->from(Tables::ATTACH_ATTACHMENTS_DESC_TABLE)
        ->fetchSingle();

    $number_of_posts = dibi::select('COUNT(post_id)')
        ->as('total')
        ->from(Tables::ATTACH_ATTACHMENT_TABLE)
        ->where('[post_id] <> %i', 0)
        ->groupBy('post_id')
        ->fetchSingle();

    $number_of_pms = dibi::select('COUNT(privmsgs_id)')
        ->as('total')
        ->from(Tables::ATTACH_ATTACHMENT_TABLE)
        ->where('[privmsgs_id] <> %i', 0)
        ->groupBy('privmsgs_id')
        ->fetchSingle();

    $number_of_topics = dibi::select('COUNT(p.topic_id)')
        ->as('total')
        ->from(Tables::ATTACH_ATTACHMENT_TABLE)
        ->as('a')
        ->innerJoin(Tables::POSTS_TABLE)
        ->as('p')
        ->on('[a.post_id] = [p.post_id]')
        ->groupBy('p.topic_id')
        ->fetchSingle();

    $number_of_users = dibi::select('COUNT(user_id_1)')
        ->as('total')
        ->from(Tables::ATTACH_ATTACHMENT_TABLE)
        ->where('[post_id] <> %i', 0)
        ->groupBy('user_id_1')
        ->fetchSingle();

    $template->assignVars(
        [
            'L_STATISTIC' => $lang['Statistic'],
            'L_VALUE' => $lang['Value'],
            'L_NUMBER_OF_ATTACHMENTS' => $lang['Number_of_attachments'],
            'L_TOTAL_FILESIZE' => $lang['Total_filesize'],
            'L_ATTACH_QUOTA' => $lang['Attach_quota'],
            'L_NUMBER_OF_POSTS' => $lang['Number_posts_attach'],
            'L_NUMBER_OF_PMS' => $lang['Number_pms_attach'],
            'L_NUMBER_OF_TOPICS' => $lang['Number_topics_attach'],
            'L_NUMBER_OF_USERS' => $lang['Number_users_attach'],

            'TOTAL_FILESIZE' => get_formatted_dirsize(),
            'ATTACH_QUOTA' => get_formatted_filesize($attach_config['attachment_quota']),
            'NUMBER_OF_ATTACHMENTS' => $number_of_attachments,
            'NUMBER_OF_POSTS' => $number_of_posts,
            'NUMBER_OF_PMS' => $number_of_pms,
            'NUMBER_OF_TOPICS' => $number_of_topics,
            'NUMBER_OF_USERS' => $number_of_users
        ]
    );
}

// Search
if ($view === 'search') {
    // Get Forums and Categories
    $rows = dibi::select(['c.cat_title', 'c.cat_id', 'f.forum_name', 'f.forum_id'])
        ->from(Tables::CATEGORIES_TABLE)
        ->as('c')
        ->innerJoin(Tables::FORUMS_TABLE)
        ->as('f')
        ->on('[f.cat_id] = [c.cat_id]')
        ->orderBy('c.cat_id')
        ->orderBy('f.forum_order')
        ->fetchAll();

    $s_forums = '';
    foreach ($rows as $row) {
        $s_forums .= '<option value="' . $row->forum_id . '">' . $row->forum_name . '</option>';

        if (empty($list_cat[$row->cat_id])) {
            $list_cat[$row->cat_id] = $row->cat_title;
        }
    }

    if ($s_forums !== '') {
        $s_forums = '<option value="0">' . $lang['All_available'] . '</option>' . $s_forums;

        // Category to search
        $s_categories = '<option value="0">' . $lang['All_available'] . '</option>';

        foreach ($list_cat as $cat_id => $cat_title) {
            $s_categories .= '<option value="' . $cat_id . '">' . $cat_title . '</option>';
        }
    } else {
        message_die(GENERAL_MESSAGE, $lang['No_searchable_forums']);
    }

    $template->setFileNames(['body' => 'admin/attach_cp_search.tpl']);
    $template->assignVars(
        [
            'L_ATTACH_SEARCH_QUERY' => $lang['Attach_search_query'],
            'L_FILENAME' => $lang['File_name'],
            'L_COMMENT' => $lang['File_comment'],
            'L_SEARCH_OPTIONS' => $lang['Search_options'],
            'L_SEARCH_AUTHOR' => $lang['Search_author'],
            'L_WILDCARD_EXPLAIN' => $lang['Search_wildcard_explain'],
            'L_SIZE_SMALLER_THAN' => $lang['Size_smaller_than'],
            'L_SIZE_GREATER_THAN' => $lang['Size_greater_than'],
            'L_COUNT_SMALLER_THAN' => $lang['Count_smaller_than'],
            'L_COUNT_GREATER_THAN' => $lang['Count_greater_than'],
            'L_MORE_DAYS_OLD' => $lang['More_days_old'],
            'L_CATEGORY' => $lang['Category'],
            'L_ORDER' => $lang['Order'],
            'L_SORT_BY' => $lang['Select_sort_method'],
            'L_FORUM' => $lang['Forum'],
            'L_SEARCH' => $lang['Search'],

            'S_FORUM_OPTIONS' => $s_forums,
            'S_CATEGORY_OPTIONS' => $s_categories,
            'S_SORT_OPTIONS' => $select_sort_mode,
            'S_SORT_ORDER' => $select_sort_order
        ]
    );
}


// Username
if ($view === 'username') {
    $template->setFileNames(['body' => 'admin/attach_cp_user.tpl']);

    $template->assignVars(
        [
            'L_SELECT_SORT_METHOD' => $lang['Select_sort_method'],
            'L_ORDER' => $lang['Order'],
            'L_USERNAME' => $lang['Username'],
            'L_TOTAL_SIZE' => $lang['Size'],
            'L_ATTACHMENTS' => $lang['Attachments'],

            'S_MODE_SELECT' => $select_sort_mode,
            'S_ORDER_SELECT' => $select_sort_order
        ]
    );


    // Get all Users with their respective total attachments amount
    $members = dibi::select(['u.username'])
        ->select('a.user_id_1')
        ->as('user_id')
        ->select('count(*)')
        ->as('total_attachments')
        ->from(Tables::ATTACH_ATTACHMENT_TABLE)
        ->as('a')
        ->innerJoin(Tables::USERS_TABLE)
        ->as('u')
        ->on('[a.user_id_1] = [u.user_id]')
        ->groupBy('a.user_id_1')
        ->groupBy('u.username');

    if ($mode !== 'filesize') {
        $members = getOrderBy($members, $mode, $view, $start, $sort_order, $board_config);
    }

    $members = $members->fetchAll();
    $num_members = count($members);

    if ($num_members > 0) {
        foreach ($members as $member) {
            // Get all attach_id's the specific user posted
            $attach_ids = dibi::select('attach_id')
                ->from(Tables::ATTACH_ATTACHMENT_TABLE)
                ->where('[user_id_1] = %i', $member->user_id)
                ->groupBy('attach_id')
                ->fetchPairs(null, 'attach_id');

            if (count($attach_ids)) {
                // Now get the total filesize
                $totalFileSize = dibi::select('SUM(filesize)')
                    ->as('total_size')
                    ->from(Tables::ATTACH_ATTACHMENTS_DESC_TABLE)
                    ->where('[attach_id] IN %in', $attach_ids)
                    ->fetchSingle();

                $member->total_size = (int)$totalFileSize;
            }
        }

        if ($mode === 'filesize') {
            $members = sort_multi_array($members, 'total_size', $sort_order, false);
            $members = limit_array($members, $start, $board_config['topics_per_page']);
        }

        foreach ($members as $i => $member) {
            $row_color = (!($i % 2)) ? $theme['td_color1'] : $theme['td_color2'];
            $row_class = (!($i % 2)) ? $theme['td_class1'] : $theme['td_class2'];

            $template->assignBlockVars('memberrow',
                [
                    'ROW_NUMBER' => $i + ($_GET['start'] + 1),
                    'ROW_COLOR' => '#' . $row_color,
                    'ROW_CLASS' => $row_class,
                    'USERNAME' => $member->username,
                    'TOTAL_ATTACHMENTS' => $member->total_attachments,
                    'TOTAL_SIZE' => get_formatted_filesize($member->total_size),
                    'U_VIEW_MEMBER' => Session::appendSid('admin_attach_cp.php?view=attachments&amp;uid=' . $member->user_id)
                ]
            );
        }
    }

    $rows = dibi::select('user_id_1')
        ->from(Tables::ATTACH_ATTACHMENT_TABLE)
        ->groupBy('user_id_1')
        ->fetchAll();

    $total_rows = count($rows);
}

// Attachments
if ($view === 'attachments') {
    $user_based = ($uid) ? true : false;
    $search_based = isset($_POST['search']) && $_POST['search'];

    $hidden_fields = '';

    $template->setFileNames(['body' => 'admin/attach_cp_attachments.tpl']);

    $template->assignVars(
        [
            'L_SELECT_SORT_METHOD' => $lang['Select_sort_method'],
            'L_ORDER' => $lang['Order'],

            'L_FILENAME' => $lang['File_name'],
            'L_FILECOMMENT' => $lang['File_comment_cp'],
            'L_EXTENSION' => $lang['Extension'],
            'L_SIZE' => $lang['Size'],
            'L_DOWNLOADS' => $lang['Downloads'],
            'L_POST_TIME' => $lang['Post_time'],
            'L_POSTED_IN_TOPIC' => $lang['Posted_in_topic'],
            'L_DELETE' => $lang['Delete'],
            'L_DELETE_MARKED' => $lang['Delete_marked'],
            'L_SUBMIT_CHANGES' => $lang['Submit_changes'],
            'L_MARK_ALL' => $lang['Mark_all'],
            'L_UNMARK_ALL' => $lang['Unmark_all'],

            'S_MODE_SELECT' => $select_sort_mode,
            'S_ORDER_SELECT' => $select_sort_order
        ]
    );

    $total_rows = 0;

    // Are we called from Username ?
    if ($user_based) {
        $username = dibi::select('username')
            ->from(Tables::USERS_TABLE)
            ->where('[user_id] = %i', $uid)
            ->fetchSingle();

        $s_hidden = '<input type="hidden" name="u_id" value="' . (int)$uid . '" />';

        $template->assignBlockVars('switch_user_based', []);
        $template->assignVars(
            [
                'S_USER_HIDDEN' => $s_hidden,
                'L_STATISTICS_FOR_USER' => sprintf($lang['Statistics_for_user'], $username)
            ]
        );

        $attach_ids = dibi::select('attach_id')
            ->from(Tables::ATTACH_ATTACHMENT_TABLE)
            ->where('[user_id_1] = %i', $uid)
            ->groupBy('attach_id')
            ->fetchPairs(null, 'attach_id');

        $num_attach_ids = count($attach_ids);

        if ($num_attach_ids === 0) {
            message_die(GENERAL_MESSAGE, 'For some reason no Attachments are assigned to the User "' . $username . '".');
        }

        $total_rows = $num_attach_ids;

        $attachments = dibi::select('a.*')
            ->from(Tables::ATTACH_ATTACHMENTS_DESC_TABLE)
            ->as('a')
            ->where('[a.attach_id] IN %in', $attach_ids);

        $attachments = getOrderBy($attachments, $mode, $view, $start, $sort_order, $board_config);
        $attachments = $attachments->fetchAll();
    } else if ($search_based) {
        // we are called from search
        // TODO
        $attachments = search_attachments($order_by, $total_rows);
    } else {
        $attachments = dibi::select('a.*')
            ->from(Tables::ATTACH_ATTACHMENTS_DESC_TABLE)
            ->as('a');

        $attachments = getOrderBy($attachments, $mode, $view, $start, $sort_order, $board_config);
        $attachments = $attachments->fetchAll();
    }
    $num_attach = count($attachments);


    /*
    if (!$search_based) {
        if (!($result = $db->sql_query($sql))) {
            message_die(GENERAL_ERROR, 'Couldn\'t query attachments', '', __LINE__, __FILE__, $sql);
        }

        $attachments = $db->sql_fetchrowset($result);
        $num_attach = $db->sql_numrows($result);
        $db->sql_freeresult($result);
    }
    */

    if (count($attachments) > 0) {
        foreach ($attachments as $i => $attachment) {
            $delete_box = '<input type="checkbox" name="delete_id_list[]" value="' . (int)$attachment->attach_id . '" />';

            foreach ($delete_id_list as $attachId) {
                if ($attachId === $attachment->attach_id) {
                    $delete_box = '<input type="checkbox" name="delete_id_list[]" value="' . (int)$attachment->attach_id . '" checked="checked" />';
                    break;
                }
            }

            $row_color = (!($i % 2)) ? $theme['td_color1'] : $theme['td_color2'];
            $row_class = (!($i % 2)) ? $theme['td_class1'] : $theme['td_class2'];

            // Is the Attachment assigned to more than one post ?
            // If it's not assigned to any post, it's an private message thingy. ;)
            $post_titles = [];

            $ids = dibi::select('*')
                ->from(Tables::ATTACH_ATTACHMENT_TABLE)
                ->where('[attach_id] = %i', $attachments[$i]['attach_id'])
                ->fetchAll();

            $num_ids = count($ids);

            for ($j = 0; $j < $num_ids; $j++) {
                if ($ids[$j]['post_id'] !== 0) {
                    $post_title = dibi::select('t.topic_title')
                        ->from(Tables::TOPICS_TABLE)
                        ->as('t')
                        ->innerJoin(Tables::POSTS_TABLE)
                        ->as('p')
                        ->on('[p.topic_id] = [t.topic_id]')
                        ->where('[p.post_id] = %i', $ids[$j]['post_id'])
                        ->groupBy('t.topic_id')
                        ->groupBy('t.topic_title')
                        ->fetchSingle();

                    if (strlen($post_title) > 32) {
                        $post_title = substr($post_title, 0, 30) . '...';
                    }

                    $view_topic = Session::appendSid($phpbb_root_path . 'viewtopic.php?' . POST_POST_URL . '=' . $ids[$j]['post_id'] . '#' . $ids[$j]['post_id']);

                    $post_titles[] = '<a href="' . $view_topic . '" class="gen" target="_blank">' . $post_title . '</a>';
                } else {
                    $post_titles[] = $lang['Private_Message'];
                }
            }

            $post_titles = implode('<br />', $post_titles);

            $hidden_field = '<input type="hidden" name="attach_id_list[]" value="' . (int)$attachment->attach_id . '" />';

            $template->assignBlockVars('attachrow',
                [
                    'ROW_NUMBER' => $i + ($_GET['start'] + 1),
                    'ROW_COLOR' => '#' . $row_color,
                    'ROW_CLASS' => $row_class,

                    'FILENAME' => $attachment->real_filename,
                    'COMMENT' => $attachment->comment,
                    'EXTENSION' => $attachment->extension,
                    'SIZE' => get_formatted_filesize($attachment->filesize),
                    'DOWNLOAD_COUNT' => $attachment->download_count,
                    'POST_TIME' => create_date($board_config['default_dateformat'], $attachment->filetime, $board_config['board_timezone']),
                    'POST_TITLE' => $post_titles,

                    'S_DELETE_BOX' => $delete_box,
                    'S_HIDDEN' => $hidden_field,
                    'U_VIEW_ATTACHMENT' => Session::appendSid($phpbb_root_path . 'download.php?id=' . $attachments[$i]['attach_id'])
                ]
//				'U_VIEW_POST' => ($attachments[$i]['post_id'] != 0) ? Session::appendSid("../viewtopic." . $phpEx . "?" . POST_POST_URL . "=" . $attachments[$i]['post_id'] . "#" . $attachments[$i]['post_id']) : '')
            );

        }
    }

    if (!$search_based && !$user_based && $total_attachments === 0) {
        $total_rows = dibi::select('COUNT(attach_id)')
            ->from(Tables::VOTE_DESC_TABLE)
            ->fetchSingle();
    }
}

// Generate Pagination
if ($do_pagination && $total_rows > $board_config['topics_per_page']) {
    $pagination = generate_pagination(
        'admin_attach_cp.php?view=' . $view . '&amp;mode=' . $mode . '&amp;order=' . $sort_order . '&amp;uid=' . $uid,
        $total_rows,
        $board_config['topics_per_page'],
        $start
    ) . '&nbsp;';

    $template->assignVars(
        [
            'PAGINATION' => $pagination,
            'PAGE_NUMBER' => sprintf($lang['Page_of'], (floor($start / $board_config['topics_per_page']) + 1), ceil($total_rows / $board_config['topics_per_page'])),

            'L_GOTO_PAGE' => $lang['Goto_page']
        ]
    );
}

$template->assignVars(['ATTACH_VERSION' => sprintf($lang['Attachment_version'], $attach_config['attach_version'])]);
$template->pparse('body');

require_once 'page_footer_admin.php';

?>