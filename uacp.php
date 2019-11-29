<?php
/** 
*
* @package attachment_mod
* @version $Id: uacp.php,v 1.4 2006/09/06 14:26:29 acydburn Exp $
* @copyright (c) 2002 Meik Sievertsen
* @license http://opensource.org/licenses/gpl-license.php GNU Public License 
*
*/

$sep = DIRECTORY_SEPARATOR;

define('IN_PHPBB', true);
$phpbb_root_path = '.' . $sep;

require_once $phpbb_root_path . 'common.php';

// session id check
$sid = get_var('sid', '');

// Start session management
$userdata = init_userprefs(PAGE_UACP);

// session id check
if ($sid === '' || $sid !== $userdata['session_id']) {
	message_die(GENERAL_ERROR, 'Invalid_session');
}

// Obtain initial var settings
$user_id = get_var(POST_USERS_URL, 0);

if (!$user_id) {
	message_die(GENERAL_MESSAGE, $lang['No_user_id_specified']);
}

$profiledata = get_userdata($user_id);

if ($profiledata['user_id'] !== $userdata['user_id'] && $userdata['user_level'] !== ADMIN) {
	message_die(GENERAL_MESSAGE, $lang['Not_Authorised']);
}

PageHelper::header($template, $userdata, $board_config, $lang, $images, $theme, $lang['User_acp_title'], false);

$language = $board_config['default_lang'];

if (!file_exists($phpbb_root_path . 'language'. $sep .'lang_' . $language . $sep . 'lang_admin_attach.php')) {
	$language = $attach_config['board_lang'];
}

require_once $phpbb_root_path . 'language' . $sep . 'lang_' . $language . $sep . 'lang_admin_attach.php';

$start = get_var('start', 0);
$sort_order = get_var('order', 'ASC');
$sort_order = ($sort_order === 'ASC') ? 'ASC' : 'DESC';
$mode = get_var('mode', '');

$mode_types = [
    'real_filename' => $lang['Sort_Filename'],
    'comment' => $lang['Sort_Comment'],
    'extension' => $lang['Sort_Extension'],
    'filesize' => $lang['Sort_Size'],
    'downloads' => $lang['Sort_Downloads'],
    'post_time' => $lang['Sort_Posttime']
];

if (!$mode) {
	$mode = 'real_filename';
	$sort_order = 'ASC';
}

// Pagination?
$do_pagination = true;

// Set select fields
$select_sort_mode = $select_sort_order = '';

if (count($mode_types) > 0) {
	$select_sort_mode = '<select name="mode">';

	foreach ($mode_types as $value => $text ) {
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

$delete = isset($_POST['delete']);
$delete_id_list = (isset($_POST['delete_id_list'])) ? array_map('intval', $_POST['delete_id_list']) : [];

$confirm = isset($_POST['confirm']) && $_POST['confirm'];

if ($confirm && count($delete_id_list)) {
	$attachments = [];

	foreach ($delete_id_list as $attachId) {
	    $row = dibi::select(['post_id', 'privmsgs_id'])
            ->from(Tables::ATTACH_ATTACHMENT_TABLE)
            ->where('[attach_id] = %i', $attachId)
            ->where('(user_id_1 = %i OR user_id_2 = %i)', $profiledata['user_id'], $profiledata['user_id'])
            ->fetch();

        if ($row) {
            if ($row->post_id !== 0) {
                delete_attachment(0, (int)$attachId);
            } else {
                delete_attachment(0, (int)$attachId, PAGE_PRIVMSGS, (int)$profiledata['user_id']);
            }
        }
	}
} else if ($delete && count($delete_id_list) > 0) {
	// Not confirmed, show confirmation message
	$hidden_fields = '<input type="hidden" name="view" value="' . $view . '" />';
	$hidden_fields .= '<input type="hidden" name="mode" value="' . $mode . '" />';
	$hidden_fields .= '<input type="hidden" name="order" value="' . $sort_order . '" />';
	$hidden_fields .= '<input type="hidden" name="' . POST_USERS_URL . '" value="' . (int)$profiledata['user_id'] . '" />';
	$hidden_fields .= '<input type="hidden" name="start" value="' . $start . '" />';
	$hidden_fields .= '<input type="hidden" name="sid" value="' . $userdata['session_id'] . '" />';

	foreach ($delete_id_list as $attachId) {
		$hidden_fields .= '<input type="hidden" name="delete_id_list[]" value="' . (int)$attachId . '" />';
	}

	$template->setFileNames(['confirm' => 'confirm_body.tpl']);

    $template->assignVars(
        [
            'MESSAGE_TITLE' => $lang['Confirm'],
            'MESSAGE_TEXT' => $lang['Confirm_delete_attachments'],

            'L_YES' => $lang['Yes'],
            'L_NO' => $lang['No'],

            'S_CONFIRM_ACTION' => Session::appendSid($phpbb_root_path . 'uacp.php'),
            'S_HIDDEN_FIELDS' => $hidden_fields
        ]
    );

	$template->pparse('confirm');

	PageHelper::footer($template, $userdata, $lang, false);

	exit;
}

$hidden_fields = '';
	
$template->setFileNames(['body' => 'uacp_body.tpl']);

$total_rows = 0;

$username = $profiledata['username'];

$s_hidden = '<input type="hidden" name="' . POST_USERS_URL . '" value="' . (int)$profiledata['user_id'] . '" />';
$s_hidden .= '<input type="hidden" name="sid" value="' . $userdata['session_id'] . '" />';

// Assign Template Vars
$template->assignVars(
    [
        'L_SUBMIT' => $lang['Submit'],
        'L_UACP' => $lang['UACP'],
        'L_SELECT_SORT_METHOD' => $lang['Select_sort_method'],
        'L_ORDER' => $lang['Order'],
        'L_FILENAME' => $lang['File_name'],
        'L_FILECOMMENT' => $lang['File_comment_cp'],
        'L_EXTENSION' => $lang['Extension'],
        'L_SIZE' => $lang['Sort_Size'],
        'L_DOWNLOADS' => $lang['Downloads'],
        'L_POST_TIME' => $lang['Post_time'],
        'L_POSTED_IN_TOPIC' => $lang['Posted_in_topic'],
        'L_DELETE' => $lang['Delete'],
        'L_DELETE_MARKED' => $lang['Delete_marked'],
        'L_MARK_ALL' => $lang['Mark_all'],
        'L_UNMARK_ALL' => $lang['Unmark_all'],

        'USERNAME' => $profiledata['username'],

        'S_USER_HIDDEN' => $s_hidden,
        'S_MODE_ACTION' => Session::appendSid($phpbb_root_path . 'uacp.php'),
        'S_MODE_SELECT' => $select_sort_mode,
        'S_ORDER_SELECT' => $select_sort_order
    ]
);

$attach_ids = dibi::select('attach_id')
    ->from(Tables::ATTACH_ATTACHMENT_TABLE)
    ->where('[user_id_1] = %i OR [user_id_2] = %i', $profiledata['user_id'], $profiledata['user_id'])
    ->groupBy('attach_id')
    ->fetchPairs(null, 'attach_id');

$num_attach_ids = count($attach_ids);
$total_rows = $num_attach_ids;
$attachments = [];

if ($num_attach_ids > 0) {
    $attachments = dibi::select('a.*')
        ->from(Tables::ATTACH_ATTACHMENTS_DESC_TABLE)
        ->as('a')
        ->where('[a.attach_id] IN %in', $attach_ids);

    // Set Order
    switch ($mode) {
        case 'filename':
            $attachments->orderBy('a.real_filename', $sort_order);
            break;

        case 'comment':
            $attachments->orderBy('a.comment', $sort_order);
            break;

        case 'extension':
            $attachments->orderBy('a.extension', $sort_order);
            break;

        case 'filesize':
            $attachments->orderBy('a.filesize', $sort_order);
            break;

        case 'downloads':
            $attachments->orderBy('a.download_count', $sort_order);
            break;

        case 'post_time':
            $attachments->orderBy('a.filetime', $sort_order);
            break;

        default:
            $attachments->orderBy('a.real_filename', dibi::ASC);
            break;
    }

    $attachments = $attachments->limit($board_config['topics_per_page'])
        ->offset($start)
        ->fetchAll();

	$num_attach = count($attachments);
}

if (count($attachments) > 0) {
    foreach ($attachments as $i => $attachment) {
        $row_color = (!($i % 2)) ? $theme['td_color1'] : $theme['td_color2'];
        $row_class = (!($i % 2)) ? $theme['td_class1'] : $theme['td_class2'];

		// Is the Attachment assigned to more than one post?
		// If it's not assigned to any post, it's an private message thingy. ;)
		$post_titles = [];

		$ids = dibi::select('*')
            ->from(Tables::ATTACH_ATTACHMENT_TABLE)
            ->where('[attach_id] = %i', $attachment->attach_id)
            ->fetchAll();

		$num_ids = count($ids);

        for ($j = 0; $j < $num_ids; $j++) {
            if ($ids[$j]['post_id'] !== 0) {
                $row = dibi::select('t.topic_title')
                    ->from(Tables::TOPICS_TABLE)
                    ->as('t')
                    ->innerJoin(Tables::POSTS_TABLE)
                    ->as('p')
                    ->on('[p.topic_id] = [t.topic_id]')
                    ->where('[p.post_id] = %i', $ids[$j]['post_id'])
                    ->groupBy('t.topic_id')
                    ->groupBy('t.topic_title')
                    ->fetch();

                $post_title = $row->topic_title;

                if (strlen($post_title) > 32) {
                    $post_title = mb_substr($post_title, 0, 30) . '...';
                }

                $view_topic = Session::appendSid($phpbb_root_path . 'viewtopic.php?' . POST_POST_URL . '=' . $ids[$j]['post_id'] . '#' . $ids[$j]['post_id']);

                $post_titles[] = '<a href="' . $view_topic . '" class="gen" target="_blank">' . $post_title . '</a>';
            } else {
                $desc = '';

                $row = dibi::select(['privmsgs_type', 'privmsgs_to_userid', 'privmsgs_from_userid'])
                    ->from(Tables::PRIVATE_MESSAGE_TABLE)
                    ->where('[privmsgs_id] = %i', $ids[$j]['privmsgs_id'])
                    ->fetch();

                if ($row) {
                    $privmsgs_type = $row->privmsgs_type;

                    if ($privmsgs_type === PRIVMSGS_READ_MAIL || $privmsgs_type === PRIVMSGS_NEW_MAIL || $privmsgs_type === PRIVMSGS_UNREAD_MAIL) {
                        if ($row->privmsgs_to_userid === $profiledata['user_id']) {
                            $desc = $lang['Private_Message'] . ' (' . $lang['Inbox'] . ')';
                        }
                    } else if ($privmsgs_type === PRIVMSGS_SENT_MAIL) {
                        if ($row->privmsgs_from_userid === $profiledata['user_id']) {
                            $desc = $lang['Private_Message'] . ' (' . $lang['Sentbox'] . ')';
                        }
                    } else if ($privmsgs_type === PRIVMSGS_SAVED_OUT_MAIL) {
                        if ($row->privmsgs_from_userid === $profiledata['user_id']) {
                            $desc = $lang['Private_Message'] . ' (' . $lang['Savebox'] . ')';
                        }
                    } else if ($privmsgs_type === PRIVMSGS_SAVED_IN_MAIL) {
                        if ($row->privmsgs_to_userid === $profiledata['user_id']) {
                            $desc = $lang['Private_Message'] . ' (' . $lang['Savebox'] . ')';
                        }
                    }

                    if ($desc !== '') {
                        $post_titles[] = $desc;
                    }
				}
			}
		}

        // Iron out those Attachments assigned to us, but not more controlled by us. ;) (PM's)
        if (count($post_titles) > 0) {
            $delete_box = '<input type="checkbox" name="delete_id_list[]" value="' . (int)$attachment->attach_id . '" />';

            foreach ($delete_id_list as $attachId) {
                if ($attachId === $attachment->attach_id) {
                    $delete_box = '<input type="checkbox" name="delete_id_list[]" value="' . (int)$attachment->attach_id . '" checked="checked" />';
                    break;
                }
            }

			$post_titles = implode('<br />', $post_titles);

			$hidden_field = '<input type="hidden" name="attach_id_list[]" value="' . (int) $attachment->attach_id . '" />';
			$hidden_field .= '<input type="hidden" name="sid" value="' . $userdata['session_id'] . '" />';

            $template->assignBlockVars('attachrow',
                [
                    'ROW_NUMBER' => $i + ($start + 1),
                    'ROW_COLOR' => '#' . $row_color,
                    'ROW_CLASS' => $row_class,

                    'FILENAME' => $attachment->real_filename,
                    'COMMENT' => nl2br($attachment->comment),
                    'EXTENSION' => $attachment->extension,
                    'SIZE' => get_formatted_filesize($attachment->filesize),
                    'DOWNLOAD_COUNT' => $attachment->download_count,
                    'POST_TIME' => create_date($board_config['default_dateformat'], $attachment->filetime, $board_config['board_timezone']),
                    'POST_TITLE' => $post_titles,

                    'S_DELETE_BOX' => $delete_box,
                    'S_HIDDEN' => $hidden_field,
                    'U_VIEW_ATTACHMENT' => Session::appendSid($phpbb_root_path . 'download.php?id=' . $attachment->attach_id)
                ]
            //			'U_VIEW_POST' => ($attachments[$i]['post_id'] != 0) ? append_sid("../viewtopic." . $phpEx . "?" . POST_POST_URL . "=" . $attachments[$i]['post_id'] . "#" . $attachments[$i]['post_id']) : '')
            );
		}
	}
}

// Generate Pagination
if ($do_pagination && $total_rows > $board_config['topics_per_page']) {
	$pagination = generate_pagination($phpbb_root_path . 'uacp.php?mode=' . $mode . '&amp;order=' . $sort_order . '&amp;' . POST_USERS_URL . '=' . $profiledata['user_id'] . '&amp;sid=' . $userdata['session_id'], $total_rows, $board_config['topics_per_page'], $start).'&nbsp;';

    $template->assignVars(
        [
            'PAGINATION' => $pagination,
            'PAGE_NUMBER' => sprintf($lang['Page_of'], (floor($start / $board_config['topics_per_page']) + 1), ceil($total_rows / $board_config['topics_per_page'])),

            'L_GOTO_PAGE' => $lang['Goto_page']
        ]
    );
}

$template->pparse('body');

PageHelper::footer($template, $userdata, $lang, false);

?>