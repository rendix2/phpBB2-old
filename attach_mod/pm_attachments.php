<?php
/**
 *
 * @package attachment_mod
 * @version $Id: pm_attachments.php,v 1.2 2005/11/06 18:35:43 acydburn Exp $
 * @copyright (c) 2002 Meik Sievertsen
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *
 */

/**
 */
if (!defined('IN_PHPBB')) {
    die('Hacking attempt');
}

/**
 * @package attachment_mod
 * Class for Private Messaging
 */
class attach_pm extends attach_parent
{
    /**
     * @var bool $pm_delete_attachments
     */
    public $pm_delete_attachments = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->pm_delete_attachments = isset($_POST['pm_delete_attach']);
        $this->page = PAGE_PRIVMSGS;
    }

    /**
     * Preview Attachments in PM's
     */
    public function preview_attachments()
    {
        global $attach_config, $userdata;

        if (!(int)$attach_config['allow_pm_attach']) {
            return false;
        }

        display_attachments_preview($this->attachment_list, $this->attachment_filesize_list, $this->attachment_filename_list, $this->attachment_comment_list, $this->attachment_extension_list, $this->attachment_thumbnail_list);
    }

    /**
     * Insert an Attachment into a private message
     */
    public function insert_attachment_pm($a_privmsgs_id)
    {
        global $mode, $attach_config, $privmsg_sent_id, $userdata, $to_userdata;

        $a_privmsgs_id = (int)$a_privmsgs_id;

        // Insert Attachment ?
        if (!$a_privmsgs_id) {
            $a_privmsgs_id = (int)$privmsg_sent_id;
        }

        if ($a_privmsgs_id && ($mode == 'post' || $mode == 'reply' || $mode == 'edit') && (int)$attach_config['allow_pm_attach']) {
            $this->do_insert_attachment('attach_list', 'pm', $a_privmsgs_id);
            $this->do_insert_attachment('last_attachment', 'pm', $a_privmsgs_id);

            if ((count($this->attachment_list) > 0 || $this->post_attach) && !isset($_POST['update_attachment'])) {
                dibi::update(Tables::PRIVATE_MESSAGE_TABLE, ['privmsgs_attachment' => 1])
                    ->where('[privmsgs_id] = %i', (int)$a_privmsgs_id)
                    ->execute();
            }
        }
    }

    /**
     * Duplicate Attachment for sent PM
     */
    public function duplicate_attachment_pm($switch_attachment, $original_privmsg_id, $new_privmsg_id)
    {
        global $privmsg, $folder;

        if (($privmsg['privmsgs_type'] == PRIVMSGS_NEW_MAIL || $privmsg['privmsgs_type'] == PRIVMSGS_UNREAD_MAIL) && $folder == 'inbox' && (int)$switch_attachment == 1) {
            $rows = dibi::select(['*'])
                ->from(Tables::ATTACH_ATTACHMENT_TABLE)
                ->where('[privmsgs_id] = %i', (int)$original_privmsg_id)
                ->fetchAll();

            $num_rows = count($rows);

            if ($num_rows > 0) {
                foreach ($rows as $row) {
                    $sql_ary = [
                        'attach_id' => (int)$row->attach_id,
                        'post_id' => (int)$row->post_id,
                        'privmsgs_id' => (int)$new_privmsg_id,
                        'user_id_1' => (int)$row->user_id_1,
                        'user_id_2' => (int)$row->user_id_2,
                    ];

                    dibi::insert(Tables::ATTACH_ATTACHMENT_TABLE, $sql_ary)->execute();
                }

                dibi::update(Tables::PRIVATE_MESSAGE_TABLE, ['privmsgs_attachment' => 1])
                    ->where('[privmsgs_id] = %i', (int)$new_privmsg_id)
                    ->execute();
            }
        }
    }

    /**
     * Delete Attachments out of selected Private Message(s)
     */
    public function delete_all_pm_attachments($mark_list)
    {
        global $confirm, $delete_all;

        if (count($mark_list) && $delete_all && $confirm) {
            delete_attachment($mark_list, 0, PAGE_PRIVMSGS);
        }
    }

    /**
     * Display the Attach Limit Box (move it to displaying.php ?)
     */
    public function display_attach_box_limits()
    {
        global $folder, $attach_config, $board_config, $template, $lang, $userdata, $db;

        if (!$attach_config['allow_pm_attach'] && $userdata['user_level'] != ADMIN) {
            return;
        }

        $this->get_quota_limits($userdata);

        $pm_filesize_limit = (!$attach_config['pm_filesize_limit']) ? $attach_config['attachment_quota'] : $attach_config['pm_filesize_limit'];

        $pm_filesize_total = get_total_attach_pm_filesize('to_user', (int)$userdata['user_id']);

        $attach_limit_pct = ($pm_filesize_limit > 0) ? round(($pm_filesize_total / $pm_filesize_limit) * 100) : 0;
        $attach_limit_img_length = ($pm_filesize_limit > 0) ? round(($pm_filesize_total / $pm_filesize_limit) * $board_config['privmsg_graphic_length']) : 0;

        if ($attach_limit_pct > 100) {
            $attach_limit_img_length = $board_config['privmsg_graphic_length'];
        }
        $attach_limit_remain = ($pm_filesize_limit > 0) ? $pm_filesize_limit - $pm_filesize_total : 100;

        $l_box_size_status = sprintf($lang['Attachbox_limit'], $attach_limit_pct);

        $template->assignVars(
            [
                'ATTACHBOX_LIMIT_IMG_WIDTH' => $attach_limit_img_length,
                'ATTACHBOX_LIMIT_PERCENT' => $attach_limit_pct,

                'ATTACH_BOX_SIZE_STATUS' => $l_box_size_status
            ]
        );
    }

    /**
     * For Private Messaging
     */
    public function privmsgs_attachment_mod($mode)
    {
        global $attach_config, $template, $lang, $userdata, $phpbb_root_path, $db;
        global $confirm, $delete, $delete_all, $post_id, $privmsgs_id, $privmsg_id, $submit, $refresh, $mark_list, $folder;

        if ($folder != 'outbox') {
            $this->display_attach_box_limits();
        }

        if (!(int)$attach_config['allow_pm_attach']) {
            return;
        }

        if (!$refresh) {
            $add_attachment_box = !empty($_POST['add_attachment_box']);
            $posted_attachments_box = !empty($_POST['posted_attachments_box']);

            $refresh = $add_attachment_box || $posted_attachments_box;
        }

        $post_id = $privmsgs_id;

        $result = $this->handle_attachments($mode, PAGE_PRIVMSGS);

        if ($result === false) {
            return;
        }

        $mark_list = get_var('mark', [0]);

        if (($this->pm_delete_attachments || $delete) && count($mark_list)) {
            if (!$userdata['session_logged_in']) {
                $header_location = (@preg_match('/Microsoft|WebSTAR|Xitami/', getenv('SERVER_SOFTWARE'))) ? 'Refresh: 0; URL=' : 'Location: ';
                header($header_location . Session::appendSid($phpbb_root_path . 'login.php?redirect=privmsg.php&folder=inbox', true));
                exit;
            }

            if (count($mark_list)) {
                if (($this->pm_delete_attachments || $confirm) && !$delete_all) {
                    delete_attachment($mark_list, 0, PAGE_PRIVMSGS);
                }
            }
        }

        if ($submit || $refresh || $mode != '') {
            $this->display_attachment_bodies();
        }
    }
}

/**
 * Entry Point
 */
function execute_privmsgs_attachment_handling($mode)
{
    global $attachment_mod;

    $attachment_mod['pm'] = new attach_pm();

    if ($mode != 'read') {
        $attachment_mod['pm']->privmsgs_attachment_mod($mode);
    }
}
