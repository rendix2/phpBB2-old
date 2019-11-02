<?php
/**
 *
 * @package attachment_mod
 * @version $Id: posting_attachments.php,v 1.12 2006/09/06 14:26:29 acydburn Exp $
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
 * Base Class for Attaching
 */
class attach_parent
{
    public $post_attach = false;
    public $attach_filename = '';
    public $filename = '';
    public $type = '';
    public $extension = '';
    public $file_comment = '';
    public $num_attachments = 0; // number of attachments in message
    public $filesize = 0;
    public $filetime = 0;
    public $thumbnail = 0;
    public $page = 0; // On which page we are on ? This should be filled by child classes.

    // Switches
    public $add_attachment_body = 0;
    public $posted_attachments_body = 0;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->add_attachment_body = get_var('add_attachment_body', 0);
        $this->posted_attachments_body = get_var('posted_attachments_body', 0);

        $this->file_comment = get_var('filecomment', '');
        $this->attachment_id_list = get_var('attach_id_list', array(0));
        $this->attachment_comment_list = get_var('comment_list', array(''), true);
        $this->attachment_filesize_list = get_var('filesize_list', array(0));
        $this->attachment_filetime_list = get_var('filetime_list', array(0));
        $this->attachment_filename_list = get_var('filename_list', array(''));
        $this->attachment_extension_list = get_var('extension_list', array(''));
        $this->attachment_mimetype_list = get_var('mimetype_list', array(''));

        $this->filename = (isset($_FILES['fileupload']) && isset($_FILES['fileupload']['name']) && $_FILES['fileupload']['name'] != 'none') ? trim(stripslashes($_FILES['fileupload']['name'])) : '';

        $this->attachment_list = get_var('attachment_list', array(''));
        $this->attachment_thumbnail_list = get_var('attach_thumbnail_list', array(0));
    }

    /**
     * Get Quota Limits
     */
    function get_quota_limits($userdata_quota, $user_id = 0)
    {
        global $attach_config, $db;

        //
        // Define Filesize Limits (Prepare Quota Settings)
        // Priority: User, Group, Management
        //
        // This method is somewhat query intensive, but i think because this one is only executed while attaching a file,
        // it does not make much sense to come up with an new db-entry.
        // Maybe i will change this in a future version, where you are able to disable the User Quota Feature at all (using
        // Default Limits for all Users/Groups)
        //

        // Change this to 'group;user' if you want to have first priority on group quota settings.
//		$priority = 'group;user';
        $priority = 'user;group';

        if ($userdata_quota['user_level'] == ADMIN) {
            $attach_config['pm_filesize_limit'] = 0; // Unlimited
            $attach_config['upload_filesize_limit'] = 0; // Unlimited
            return;
        }

        if ($this->page == PAGE_PRIVMSGS) {
            $quota_type = QUOTA_PM_LIMIT;
            $limit_type = 'pm_filesize_limit';
            $default = 'max_filesize_pm';
        } else {
            $quota_type = QUOTA_UPLOAD_LIMIT;
            $limit_type = 'upload_filesize_limit';
            $default = 'attachment_quota';
        }

        if (!$user_id) {
            $user_id = (int)$userdata_quota['user_id'];
        }

        $priority = explode(';', $priority);
        $found = false;

        for ($i = 0; $i < count($priority); $i++) {
            if (($priority[$i] == 'group') && (!$found)) {
                // Get Group Quota, if we find one, we have our quota
                $group_id = dibi::select('u.group_id')
                    ->from(Tables::USERS_GROUPS_TABLE)
                    ->as('ug')
                    ->innerJoin(Tables::GROUPS_TABLE)
                    ->as('g')
                    ->on('[ug.group_id] = [g.group_id]')
                    ->where('[ug.user_id] = %i', $user_id)
                    ->where('[ug.user_pending] = %i', 0)
                    ->where('[g.group_single_user] = %i', 0)
                    ->fetchPairs(null, 'group_id');

                if (count($group_id)) {
                    $quota_limit = dibi::select('l.quota_limit')
                        ->from(Tables::ATTACH_QUOTA_TABLE)
                        ->as('q')
                        ->innerJoin(Tables::ATTACH_QUOTA_LIMITS_TABLE)
                        ->as('l')
                        ->on('[q.quota_limit_id] = [l.quota_limit_id]')
                        ->where('[q.group_id] IN %in', $group_id)
                        ->where('[q.group_id] <> %i', 0)
                        ->where('[q.quota_type] = %i', $quota_type)
                        ->orderBy('l.quota_limit', dibi::DESC)
                        ->fetchSingle();

                    if ($quota_limit) {
                        $attach_config[$limit_type] = $quota_limit;
                        $found = true;
                    }
                }
            }

            if ($priority[$i] == 'user' && !$found) {
                // Get User Quota, if the user is not in a group or the group has no quotas
                $quota_limit = dibi::select('l.quota_limit')
                    ->from(Tables::ATTACH_QUOTA_TABLE)
                    ->as('q')
                    ->innerJoin(Tables::ATTACH_QUOTA_LIMITS_TABLE)
                    ->as('l')
                    ->on('[q.quota_limit_id] = [l.quota_limit_id]')
                    ->where('[q.user_id] = %i', $user_id)
                    ->where('[q.group_id] <> %i', 0)
                    ->where('[q.quota_type] = %i', $quota_type)
                    ->orderBy('l.quota_limit', dibi::DESC)
                    ->fetchSingle();

                if ($quota_limit) {
                    $attach_config[$limit_type] = $quota_limit;
                    $found = true;
                }
            }
        }

        if (!$found) {
            // Set Default Quota Limit
            $quota_id = ($quota_type == QUOTA_UPLOAD_LIMIT) ? $attach_config['default_upload_quota'] : $attach_config['default_pm_quota'];

            if ($quota_id == 0) {
                $attach_config[$limit_type] = $attach_config[$default];
            } else {
                $quota_limit = dibi::select('quota_limit')
                    ->from(Tables::ATTACH_QUOTA_LIMITS_TABLE)
                    ->where('[quota_limit_id] = %i', $quota_id)
                    ->fetch();

                if ($quota_limit) {
                    $attach_config[$limit_type] = $quota_limit;
                } else {
                    $attach_config[$limit_type] = $attach_config[$default];
                }
            }
        }

        // Never exceed the complete Attachment Upload Quota
        if ($quota_type == QUOTA_UPLOAD_LIMIT) {
            if ($attach_config[$limit_type] > $attach_config[$default]) {
                $attach_config[$limit_type] = $attach_config[$default];
            }
        }
    }

    /**
     * Handle all modes... (intern)
     * @private
     */
    function handle_attachments($mode)
    {
        global $is_auth, $attach_config, $refresh, $post_id, $submit, $preview, $error, $error_msg, $lang, $template, $userdata, $db;
        global $postId;

        //
        // ok, what shall we do ;)
        //

        // Some adjustments for PM's
        if ($this->page == PAGE_PRIVMSGS) {
            global $privmsg_id;

            $post_id = $privmsg_id;

            if ($mode == 'post') {
                $mode = 'newtopic';
            } else if ($mode == 'edit') {
                $mode = 'editpost';
            }

            if ($userdata['user_level'] == ADMIN) {
                $is_auth['auth_attachments'] = 1;
                $max_attachments = ADMIN_MAX_ATTACHMENTS;
            } else {
                $is_auth['auth_attachments'] = (int)$attach_config['allow_pm_attach'];
                $max_attachments = (int)$attach_config['max_attachments_pm'];
            }

            $sql_id = 'privmsgs_id';
        } else {
            if ($userdata['user_level'] == ADMIN) {
                $max_attachments = ADMIN_MAX_ATTACHMENTS;
            } else {
                $max_attachments = (int)$attach_config['max_attachments'];
            }

            $sql_id = 'post_id';
        }

        // nothing, if the user is not authorized or attachment mod disabled
        if ((int)$attach_config['disable_mod'] || !$is_auth['auth_attachments']) {
            return false;
        }

        // Get those attach_ids allowed for lists from the attachments table...
        $allowed_attach_ids = array();
        if ($post_id) {
            $allowed_attach_ids = dibi::select(['attach_id'])
                ->from(Tables::ATTACH_ATTACHMENT_TABLE)
                ->where('%n = %i', $sql_id, $post_id)
                ->fetchPairs(null, 'attach_id');
        }

        // Check the submitted variables - do not allow wrong values
        $actual_id_list = get_var('attach_id_list', array(0));
        $actual_list = get_var('attachment_list', array(''));

        for ($i = 0; $i < count($actual_list); $i++) {
            if ($actual_id_list[$i] != 0) {
                if (!in_array($actual_id_list[$i], $allowed_attach_ids)) {
                    message_die(CRITICAL_ERROR, 'You tried to change an attachment you do not have access to', '');
                }
            } else {
                // Really new attachment? If so, the filename should be unique...
                if (physical_filename_already_stored($actual_list[$i])) {
                    message_die(CRITICAL_ERROR, 'You tried to change an attachment you do not have access to', '');
                }
            }
        }

        // Init Vars
        $attachments = array();

        if (!$refresh) {
            $add = (isset($_POST['add_attachment'])) ? TRUE : FALSE;
            $delete = (isset($_POST['del_attachment'])) ? TRUE : FALSE;
            $edit = (isset($_POST['edit_comment'])) ? TRUE : FALSE;
            $update_attachment = (isset($_POST['update_attachment'])) ? TRUE : FALSE;
            $del_thumbnail = (isset($_POST['del_thumbnail'])) ? TRUE : FALSE;

            $add_attachment_box = (!empty($_POST['add_attachment_box'])) ? TRUE : FALSE;
            $posted_attachments_box = (!empty($_POST['posted_attachments_box'])) ? TRUE : FALSE;

            $refresh = $add || $delete || $edit || $del_thumbnail || $update_attachment || $add_attachment_box || $posted_attachments_box;
        }

        // Get Attachments
        if ($this->page == PAGE_PRIVMSGS) {
            $attachments = get_attachments_from_pm($post_id);
        } else {
            $attachments = get_attachments_from_post($post_id);
        }

        if ($this->page == PAGE_PRIVMSGS) {
            if ($userdata['user_level'] == ADMIN) {
                $auth = TRUE;
            } else {
                $auth = ((int)$attach_config['allow_pm_attach']) ? TRUE : FALSE;
            }

            if (count($attachments) == 1) {
                $template->assignBlockVars('switch_attachments', array());

                $template->assignVars(array(
                        'L_DELETE_ATTACHMENTS' => $lang['Delete_attachment'])
                );
            } else if (count($attachments) > 0) {
                $template->assignBlockVars('switch_attachments', array());

                $template->assignVars(array(
                        'L_DELETE_ATTACHMENTS' => $lang['Delete_attachments'])
                );
            }
        } else {
            $auth = ($is_auth['auth_edit'] || $is_auth['auth_mod']) ? TRUE : FALSE;
        }

        if (!$submit && $mode == 'editpost' && $auth) {
            if (!$refresh && !$preview && !$error && !isset($_POST['del_poll_option'])) {
                for ($i = 0; $i < count($attachments); $i++) {
                    $this->attachment_list[] = $attachments[$i]['physical_filename'];
                    $this->attachment_comment_list[] = $attachments[$i]['comment'];
                    $this->attachment_filename_list[] = $attachments[$i]['real_filename'];
                    $this->attachment_extension_list[] = $attachments[$i]['extension'];
                    $this->attachment_mimetype_list[] = $attachments[$i]['mimetype'];
                    $this->attachment_filesize_list[] = $attachments[$i]['filesize'];
                    $this->attachment_filetime_list[] = $attachments[$i]['filetime'];
                    $this->attachment_id_list[] = $attachments[$i]['attach_id'];
                    $this->attachment_thumbnail_list[] = $attachments[$i]['thumbnail'];
                }
            }
        }

        $this->num_attachments = count($this->attachment_list);

        if ($submit && $mode != 'vote') {
            if ($mode == 'newtopic' || $mode == 'reply' || $mode == 'editpost') {
                if ($this->filename != '') {
                    if ($this->num_attachments < (int)$max_attachments) {
                        $this->upload_attachment($this->page);

                        if (!$error && $this->post_attach) {
                            array_unshift($this->attachment_list, $this->attach_filename);
                            array_unshift($this->attachment_comment_list, $this->file_comment);
                            array_unshift($this->attachment_filename_list, $this->filename);
                            array_unshift($this->attachment_extension_list, $this->extension);
                            array_unshift($this->attachment_mimetype_list, $this->type);
                            array_unshift($this->attachment_filesize_list, $this->filesize);
                            array_unshift($this->attachment_filetime_list, $this->filetime);
                            array_unshift($this->attachment_id_list, '0');
                            array_unshift($this->attachment_thumbnail_list, $this->thumbnail);

                            $this->file_comment = '';

                            // This Variable is set to FALSE here, because the Attachment Mod enter Attachments into the
                            // Database in two modes, one if the id_list is 0 and the second one if post_attach is true
                            // Since post_attach is automatically switched to true if an Attachment got added to the filesystem,
                            // but we are assigning an id of 0 here, we have to reset the post_attach variable to FALSE.
                            //
                            // This is very relevant, because it could happen that the post got not submitted, but we do not
                            // know this circumstance here. We could be at the posting page or we could be redirected to the entered
                            // post. :)
                            $this->post_attach = FALSE;
                        }
                    } else {
                        $error = TRUE;
                        if (!empty($error_msg)) {
                            $error_msg .= '<br />';
                        }
                        $error_msg .= sprintf($lang['Too_many_attachments'], (int)$max_attachments);
                    }
                }
            }
        }

        if ($preview || $refresh || $error) {
            $delete_attachment = (isset($_POST['del_attachment'])) ? TRUE : FALSE;
            $delete_thumbnail = (isset($_POST['del_thumbnail'])) ? TRUE : FALSE;

            $add_attachment = (isset($_POST['add_attachment'])) ? TRUE : FALSE;
            $edit_attachment = (isset($_POST['edit_comment'])) ? TRUE : FALSE;
            $update_attachment = (isset($_POST['update_attachment'])) ? TRUE : FALSE;

            // Perform actions on temporary attachments
            if ($delete_attachment || $delete_thumbnail) {
                // store old values
                $actual_id_list = get_var('attach_id_list', array(0));
                $actual_comment_list = get_var('comment_list', array(''), true);
                $actual_filename_list = get_var('filename_list', array(''));
                $actual_extension_list = get_var('extension_list', array(''));
                $actual_mimetype_list = get_var('mimetype_list', array(''));
                $actual_filesize_list = get_var('filesize_list', array(0));
                $actual_filetime_list = get_var('filetime_list', array(0));

                $actual_list = get_var('attachment_list', array(''));
                $actual_thumbnail_list = get_var('attach_thumbnail_list', array(0));

                // clean values
                $this->attachment_list = array();
                $this->attachment_comment_list = array();
                $this->attachment_filename_list = array();
                $this->attachment_extension_list = array();
                $this->attachment_mimetype_list = array();
                $this->attachment_filesize_list = array();
                $this->attachment_filetime_list = array();
                $this->attachment_id_list = array();
                $this->attachment_thumbnail_list = array();

                // restore values :)
                if (isset($_POST['attachment_list'])) {
                    for ($i = 0; $i < count($actual_list); $i++) {
                        $restore = FALSE;
                        $del_thumb = FALSE;

                        if ($delete_thumbnail) {
                            if (!isset($_POST['del_thumbnail'][$actual_list[$i]])) {
                                $restore = TRUE;
                            } else {
                                $del_thumb = TRUE;
                            }
                        }

                        if ($delete_attachment) {
                            if (!isset($_POST['del_attachment'][$actual_list[$i]])) {
                                $restore = TRUE;
                            }
                        }

                        if ($restore) {
                            $this->attachment_list[] = $actual_list[$i];
                            $this->attachment_comment_list[] = $actual_comment_list[$i];
                            $this->attachment_filename_list[] = $actual_filename_list[$i];
                            $this->attachment_extension_list[] = $actual_extension_list[$i];
                            $this->attachment_mimetype_list[] = $actual_mimetype_list[$i];
                            $this->attachment_filesize_list[] = $actual_filesize_list[$i];
                            $this->attachment_filetime_list[] = $actual_filetime_list[$i];
                            $this->attachment_id_list[] = $actual_id_list[$i];
                            $this->attachment_thumbnail_list[] = $actual_thumbnail_list[$i];
                        } else if (!$del_thumb) {
                            // delete selected attachment
                            if ($actual_id_list[$i] == '0') {
                                unlink_attach($actual_list[$i]);

                                if ($actual_thumbnail_list[$i] == 1) {
                                    unlink_attach($actual_list[$i], MODE_THUMBNAIL);
                                }
                            } else {
                                delete_attachment($post_id, $actual_id_list[$i], $this->page);
                            }
                        } else if ($del_thumb) {
                            // delete selected thumbnail
                            $this->attachment_list[] = $actual_list[$i];
                            $this->attachment_comment_list[] = $actual_comment_list[$i];
                            $this->attachment_filename_list[] = $actual_filename_list[$i];
                            $this->attachment_extension_list[] = $actual_extension_list[$i];
                            $this->attachment_mimetype_list[] = $actual_mimetype_list[$i];
                            $this->attachment_filesize_list[] = $actual_filesize_list[$i];
                            $this->attachment_filetime_list[] = $actual_filetime_list[$i];
                            $this->attachment_id_list[] = $actual_id_list[$i];
                            $this->attachment_thumbnail_list[] = 0;

                            if ($actual_id_list[$i] == 0) {
                                unlink_attach($actual_list[$i], MODE_THUMBNAIL);
                            } else {
                                dibi::update(Tables::ATTACH_ATTACHMENTS_DESC_TABLE, ['thumbnail' => 0])
                                    ->where('[attach_id] = %i', $actual_id_list[$i])
                                    ->execute();
                            }
                        }
                    }
                }
            } else if ($edit_attachment || $update_attachment || $add_attachment || $preview) {
                if ($edit_attachment) {
                    $actual_comment_list = get_var('comment_list', array(''), true);

                    $this->attachment_comment_list = array();

                    for ($i = 0; $i < count($this->attachment_list); $i++) {
                        $this->attachment_comment_list[$i] = $actual_comment_list[$i];
                    }
                }

                if ($update_attachment) {
                    if ($this->filename == '') {
                        $error = TRUE;

                        if (!empty($error_msg)) {
                            $error_msg .= '<br />';
                        }

                        $error_msg .= $lang['Error_empty_add_attachbox'];
                    }

                    $this->upload_attachment($this->page);

                    if (!$error) {
                        $actual_list = get_var('attachment_list', array(''));
                        $actual_id_list = get_var('attach_id_list', array(0));

                        $attachment_id = 0;
                        $actual_element = 0;

                        for ($i = 0; $i < count($actual_id_list); $i++) {
                            if (isset($_POST['update_attachment'][$actual_id_list[$i]])) {
                                $attachment_id = (int)$actual_id_list[$i];
                                $actual_element = $i;
                            }
                        }

                        // Get current informations to delete the Old Attachment
                        $row = dibi::select(['physical_filename', 'comment', 'thumbnail'])
                            ->from(Tables::ATTACH_ATTACHMENTS_DESC_TABLE)
                            ->where('[atach_id] = %i', $attachment_id)
                            ->fetch();

                        if (!$row) {
                            $error = TRUE;
                            if (!empty($error_msg)) {
                                $error_msg .= '<br />';
                            }
                            $error_msg .= $lang['Error_missing_old_entry'];
                        }

                        $comment = (trim($this->file_comment) == '') ? trim($row->comment) : trim($this->file_comment);

                        // Update Entry
                        $sql_ary = array(
                            'physical_filename' => (string)basename($this->attach_filename),
                            'real_filename' => (string)basename($this->filename),
                            'comment' => (string)$comment,
                            'extension' => (string)strtolower($this->extension),
                            'mimetype' => (string)strtolower($this->type),
                            'filesize' => (int)$this->filesize,
                            'filetime' => (int)$this->filetime,
                            'thumbnail' => (int)$this->thumbnail
                        );

                        dibi::update(Tables::ATTACH_ATTACHMENTS_DESC_TABLE, $sql_ary)
                            ->where('[attach_id] = %i', $attachment_id)
                            ->execute();

                        // Delete the Old Attachment
                        unlink_attach($row['physical_filename']);

                        if ((int)$row['thumbnail'] == 1) {
                            unlink_attach($row['physical_filename'], MODE_THUMBNAIL);
                        }

                        // Make sure it is displayed
                        $this->attachment_list[$actual_element] = $this->attach_filename;
                        $this->attachment_comment_list[$actual_element] = $comment;
                        $this->attachment_filename_list[$actual_element] = $this->filename;
                        $this->attachment_extension_list[$actual_element] = $this->extension;
                        $this->attachment_mimetype_list[$actual_element] = $this->type;
                        $this->attachment_filesize_list[$actual_element] = $this->filesize;
                        $this->attachment_filetime_list[$actual_element] = $this->filetime;
                        $this->attachment_id_list[$actual_element] = $actual_id_list[$actual_element];
                        $this->attachment_thumbnail_list[$actual_element] = $this->thumbnail;
                        $this->file_comment = '';
                    }
                }

                if (($add_attachment || $preview) && $this->filename != '') {
                    if ($this->num_attachments < (int)$max_attachments) {
                        $this->upload_attachment($this->page);

                        if (!$error) {
                            array_unshift($this->attachment_list, $this->attach_filename);
                            array_unshift($this->attachment_comment_list, $this->file_comment);
                            array_unshift($this->attachment_filename_list, $this->filename);
                            array_unshift($this->attachment_extension_list, $this->extension);
                            array_unshift($this->attachment_mimetype_list, $this->type);
                            array_unshift($this->attachment_filesize_list, $this->filesize);
                            array_unshift($this->attachment_filetime_list, $this->filetime);
                            array_unshift($this->attachment_id_list, '0');
                            array_unshift($this->attachment_thumbnail_list, $this->thumbnail);

                            $this->file_comment = '';
                        }
                    } else {
                        $error = TRUE;
                        if (!empty($error_msg)) {
                            $error_msg .= '<br />';
                        }
                        $error_msg .= sprintf($lang['Too_many_attachments'], (int)$max_attachments);
                    }
                }
            }
        }

        return TRUE;
    }

    /**
     * Basic Insert Attachment Handling for all Message Types
     */
    function do_insert_attachment($mode, $message_type, $message_id)
    {
        if ((int)$message_id < 0) {
            return FALSE;
        }

        if ($message_type == 'pm') {
            global $userdata, $to_userdata;

            $post_id = 0;
            $privmsgs_id = (int)$message_id;
            $user_id_1 = (int)$userdata['user_id'];
            $user_id_2 = (int)$to_userdata['user_id'];
            $sql_id = 'privmsgs_id';
        } else if ($message_type = 'post') {
            global $post_info, $userdata;

            $post_id = (int)$message_id;
            $privmsgs_id = 0;
            $user_id_1 = (isset($post_info['poster_id'])) ? (int)$post_info['poster_id'] : 0;
            $user_id_2 = 0;
            $sql_id = 'post_id';

            if (!$user_id_1) {
                $user_id_1 = (int)$userdata['user_id'];
            }
        }

        if ($mode == 'attach_list') {
            for ($i = 0; $i < count($this->attachment_list); $i++) {
                if ($this->attachment_id_list[$i]) {
                    // Check if the attachment id is connected to the message
                    $row = dibi::select('attach_id')
                        ->from(Tables::ATTACH_ATTACHMENT_TABLE)
                        ->where('%n = %i', $sql_id, $$sql_id)
                        ->where('[attach_id] = %i', $this->attachment_id_list[$i])
                        ->fetch();

                    if (!$row) {
                        message_die(GENERAL_ERROR, 'Tried to update an attachment you are not allowed to access', '');
                    }

                    // update entry in db if attachment already stored in db and filespace
                    dibi::update(Tables::ATTACH_ATTACHMENTS_DESC_TABLE, ['comment' => $this->attachment_comment_list[$i]])
                        ->where('[attach_id] = %i', $this->attachment_id_list[$i])
                        ->execute();
                } else {
                    // insert attachment into db
                    $sql_ary = array(
                        'physical_filename' => (string)basename($this->attachment_list[$i]),
                        'real_filename' => (string)basename($this->attachment_filename_list[$i]),
                        'comment' => (string)$this->attachment_comment_list[$i],
                        'extension' => (string)strtolower($this->attachment_extension_list[$i]),
                        'mimetype' => (string)strtolower($this->attachment_mimetype_list[$i]),
                        'filesize' => (int)$this->attachment_filesize_list[$i],
                        'filetime' => (int)$this->attachment_filetime_list[$i],
                        'thumbnail' => (int)$this->attachment_thumbnail_list[$i]
                    );

                    $attach_id = dibi::insert(Tables::ATTACH_ATTACHMENTS_DESC_TABLE, $sql_ary)->execute(dibi::IDENTIFIER);

                    $sql_ary = array(
                        'attach_id' => (int)$attach_id,
                        'post_id' => (int)$post_id,
                        'privmsgs_id' => (int)$privmsgs_id,
                        'user_id_1' => (int)$user_id_1,
                        'user_id_2' => (int)$user_id_2
                    );

                    dibi::insert(Tables::ATTACH_ATTACHMENT_TABLE, $sql_ary)->execute();
                }
            }

            return TRUE;
        }

        if ($mode == 'last_attachment') {
            if ($this->post_attach && !isset($_POST['update_attachment'])) {
                // insert attachment into db, here the user submited it directly
                $sql_ary = array(
                    'physical_filename' => (string)basename($this->attach_filename),
                    'real_filename' => (string)basename($this->filename),
                    'comment' => (string)$this->file_comment,
                    'extension' => (string)strtolower($this->extension),
                    'mimetype' => (string)strtolower($this->type),
                    'filesize' => (int)$this->filesize,
                    'filetime' => (int)$this->filetime,
                    'thumbnail' => (int)$this->thumbnail
                );

                $attach_id = dibi::insert(Tables::ATTACH_ATTACHMENTS_DESC_TABLE, $sql_ary)->execute(dibi::IDENTIFIER);

                $sql_ary = array(
                    'attach_id' => (int)$attach_id,
                    'post_id' => (int)$post_id,
                    'privmsgs_id' => (int)$privmsgs_id,
                    'user_id_1' => (int)$user_id_1,
                    'user_id_2' => (int)$user_id_2
                );

                dibi::insert(Tables::ATTACH_ATTACHMENT_TABLE, $sql_ary)->execute();
            }
        }
    }

    /**
     * Attachment Mod entry switch/output (intern)
     * @private
     */
    function display_attachment_bodies()
    {
        global $attach_config, $is_auth, $lang, $mode, $template, $upload_dir, $userdata, $forum_id;
        global $phpbb_root_path;

        // Choose what to display
        $value_add = $value_posted = 0;

        if ((int)$attach_config['show_apcp']) {
            if (!empty($_POST['add_attachment_box'])) {
                $value_add = ($this->add_attachment_body == 0) ? 1 : 0;
                $this->add_attachment_body = $value_add;
            } else {
                $value_add = ($this->add_attachment_body == 0) ? 0 : 1;
            }

            if (!empty($_POST['posted_attachments_box'])) {
                $value_posted = ($this->posted_attachments_body == 0) ? 1 : 0;
                $this->posted_attachments_body = $value_posted;
            } else {
                $value_posted = ($this->posted_attachments_body == 0) ? 0 : 1;
            }
            $template->assignBlockVars('show_apcp', array());
        } else {
            $this->add_attachment_body = 1;
            $this->posted_attachments_body = 1;
        }

        $template->setFileNames(array(
                'attachbody' => 'posting_attach_body.tpl')
        );

        display_compile_cache_clear($template->getFiles()['attachbody'], 'attachbody');

        $s_hidden = '<input type="hidden" name="add_attachment_body" value="' . $value_add . '" />';
        $s_hidden .= '<input type="hidden" name="posted_attachments_body" value="' . $value_posted . '" />';

        if ($this->page == PAGE_PRIVMSGS) {
            $u_rules_id = 0;
        } else {
            $u_rules_id = $forum_id;
        }

        $template->assignVars(array(
                'L_ATTACH_POSTING_CP' => $lang['Attach_posting_cp'],
                'L_ATTACH_POSTING_CP_EXPLAIN' => $lang['Attach_posting_cp_explain'],
                'L_OPTIONS' => $lang['Options'],
                'L_ADD_ATTACHMENT_TITLE' => $lang['Add_attachment_title'],
                'L_POSTED_ATTACHMENTS' => $lang['Posted_attachments'],
                'L_FILE_NAME' => $lang['File_name'],
                'L_FILE_COMMENT' => $lang['File_comment'],
                'RULES' => '<a href="' . Session::appendSid($phpbb_root_path . "attach_rules.php?f=$u_rules_id") . '" target="_blank">' . $lang['Allowed_extensions_and_sizes'] . '</a>',

                'S_HIDDEN' => $s_hidden)
        );

        $attachments = array();

        if (count($this->attachment_list) > 0) {
            if ((int)$attach_config['show_apcp']) {
                $template->assignBlockVars('switch_posted_attachments', array());
            }

            for ($i = 0; $i < count($this->attachment_list); $i++) {
                $hidden = '<input type="hidden" name="attachment_list[]" value="' . $this->attachment_list[$i] . '" />';
                $hidden .= '<input type="hidden" name="filename_list[]" value="' . $this->attachment_filename_list[$i] . '" />';
                $hidden .= '<input type="hidden" name="extension_list[]" value="' . $this->attachment_extension_list[$i] . '" />';
                $hidden .= '<input type="hidden" name="mimetype_list[]" value="' . $this->attachment_mimetype_list[$i] . '" />';
                $hidden .= '<input type="hidden" name="filesize_list[]" value="' . $this->attachment_filesize_list[$i] . '" />';
                $hidden .= '<input type="hidden" name="filetime_list[]" value="' . $this->attachment_filetime_list[$i] . '" />';
                $hidden .= '<input type="hidden" name="attach_id_list[]" value="' . $this->attachment_id_list[$i] . '" />';
                $hidden .= '<input type="hidden" name="attach_thumbnail_list[]" value="' . $this->attachment_thumbnail_list[$i] . '" />';

                if (!$this->posted_attachments_body || count($this->attachment_list) == 0) {
                    $hidden .= '<input type="hidden" name="comment_list[]" value="' . $this->attachment_comment_list[$i] . '" />';
                }

                $template->assignBlockVars('hidden_row', array(
                        'S_HIDDEN' => $hidden)
                );
            }
        }

        if ($this->add_attachment_body) {
            init_display_template('attachbody', '{ADD_ATTACHMENT_BODY}', 'add_attachment_body.tpl');

            $form_enctype = 'enctype="multipart/form-data"';

            $template->assignVars(array(
                    'L_ADD_ATTACH_TITLE' => $lang['Add_attachment_title'],
                    'L_ADD_ATTACH_EXPLAIN' => $lang['Add_attachment_explain'],
                    'L_ADD_ATTACHMENT' => $lang['Add_attachment'],

                    'FILE_COMMENT' => $this->file_comment,
                    'FILESIZE' => $attach_config['max_filesize'],
                    'FILENAME' => $this->filename,

                    'S_FORM_ENCTYPE' => $form_enctype)
            );
        }

        if ($this->posted_attachments_body && count($this->attachment_list) > 0) {
            init_display_template('attachbody', '{POSTED_ATTACHMENTS_BODY}', 'posted_attachments_body.tpl');

            $template->assignVars(array(
                    'L_POSTED_ATTACHMENTS' => $lang['Posted_attachments'],
                    'L_UPDATE_COMMENT' => $lang['Update_comment'],
                    'L_UPLOAD_NEW_VERSION' => $lang['Upload_new_version'],
                    'L_DELETE_ATTACHMENT' => $lang['Delete_attachment'],
                    'L_DELETE_THUMBNAIL' => $lang['Delete_thumbnail'],
                    'L_OPTIONS' => $lang['Options'])
            );

            for ($i = 0; $i < count($this->attachment_list); $i++) {
                if ($this->attachment_id_list[$i] == 0) {
                    $download_link = $upload_dir . '/' . basename($this->attachment_list[$i]);
                } else {
                    $download_link = Session::appendSid($phpbb_root_path . 'download.php?id=' . $this->attachment_id_list[$i]);
                }

                $template->assignBlockVars('attach_row', array(
                        'FILE_NAME' => $this->attachment_filename_list[$i],
                        'ATTACH_FILENAME' => $this->attachment_list[$i],
                        'FILE_COMMENT' => $this->attachment_comment_list[$i],
                        'ATTACH_ID' => $this->attachment_id_list[$i],

                        'U_VIEW_ATTACHMENT' => $download_link)
                );

                // Thumbnail there ? And is the User Admin or Mod ? Then present the 'Delete Thumbnail' Button
                if ((int)$this->attachment_thumbnail_list[$i] == 1 && ((isset($is_auth['auth_mod']) && $is_auth['auth_mod']) || $userdata['user_level'] == ADMIN)) {
                    $template->assignBlockVars('attach_row.switch_thumbnail', array());
                }

                if ($this->attachment_id_list[$i]) {
                    $template->assignBlockVars('attach_row.switch_update_attachment', array());
                }
            }
        }

        $template->assignVarFromHandle('ATTACHBOX', 'attachbody');
    }

    /**
     * Upload an Attachment to Filespace (intern)
     */
    function upload_attachment()
    {
        global $error, $error_msg, $lang, $attach_config, $userdata, $upload_dir, $forum_id;

        $this->post_attach = ($this->filename != '') ? TRUE : FALSE;

        if ($this->post_attach) {
            $r_file = trim(basename(htmlspecialchars($this->filename)));
            $file = $_FILES['fileupload']['tmp_name'];
            $this->type = $_FILES['fileupload']['type'];

            if (isset($_FILES['fileupload']['size']) && $_FILES['fileupload']['size'] == 0) {
                message_die(GENERAL_ERROR, 'Tried to upload empty file');
            }

            // Opera add the name to the mime type
            $this->type = (strstr($this->type, '; name')) ? str_replace(strstr($this->type, '; name'), '', $this->type) : $this->type;
            $this->type = strtolower($this->type);
            $this->extension = strtolower(get_extension($this->filename));

            $this->filesize = @filesize($file);
            $this->filesize = (int)$this->filesize;

            $row = dibi::select(['g.allow_group', 'g.max_filesize', 'g.cat_id', 'g.forum_permissions'])
                ->from(Tables::ATTACH_EXTENSION_GROUPS_TABLE)
                ->as('g')
                ->innerJoin(Tables::ATTACH_EXTENSION_TABLE)
                ->as('e')
                ->on('[g.group_id] = [e.group_id]')
                ->where('[e.extension] = %s', $this->extension)
                ->fetch();

            $allowed_filesize = ($row['max_filesize']) ? $row['max_filesize'] : $attach_config['max_filesize'];
            $cat_id = (int)$row['cat_id'];
            $auth_cache = trim($row['forum_permissions']);

            // check Filename
            if (preg_match("#[\\/:*?\"<>|]#i", $this->filename)) {
                $error = TRUE;
                if (!empty($error_msg)) {
                    $error_msg .= '<br />';
                }
                $error_msg .= sprintf($lang['Invalid_filename'], htmlspecialchars($this->filename));
            }

            // check php upload-size
            if (!$error && $file == 'none') {
                $error = TRUE;
                if (!empty($error_msg)) {
                    $error_msg .= '<br />';
                }
                $ini_val = (PHP_VERSION >= '4.0.0') ? 'ini_get' : 'get_cfg_var';

                $max_size = @$ini_val('upload_max_filesize');

                if ($max_size == '') {
                    $error_msg .= $lang['Attachment_php_size_na'];
                } else {
                    $error_msg .= sprintf($lang['Attachment_php_size_overrun'], $max_size);
                }
            }

            // Check Extension
            if (!$error && (int)$row['allow_group'] == 0) {
                $error = TRUE;
                if (!empty($error_msg)) {
                    $error_msg .= '<br />';
                }
                $error_msg .= sprintf($lang['Disallowed_extension'], htmlspecialchars($this->extension));
            }

            // Check Forum Permissions
            if (!$error && $this->page != PAGE_PRIVMSGS && $userdata['user_level'] != ADMIN && !is_forum_authed($auth_cache, $forum_id) && trim($auth_cache) != '') {
                $error = TRUE;
                if (!empty($error_msg)) {
                    $error_msg .= '<br />';
                }
                $error_msg .= sprintf($lang['Disallowed_extension_within_forum'], htmlspecialchars($this->extension));
            }

            // Upload File
            $this->thumbnail = 0;

            if (!$error) {
                // Prepare Values
                $this->filetime = time();

                $this->filename = $r_file;

                // physical filename
                $this->attach_filename = strtolower($this->filename);

                // To re-add cryptic filenames, change this variable to true
                $cryptic = false;

                if (!$cryptic) {
                    $this->attach_filename = html_entity_decode(trim(stripslashes($this->attach_filename)));
                    $this->attach_filename = delete_extension($this->attach_filename);
                    $this->attach_filename = str_replace(array(' ', '-'), array('_', '_'), $this->attach_filename);
                    $this->attach_filename = str_replace('__', '_', $this->attach_filename);
                    $this->attach_filename = str_replace(array(',', '.', '!', '?', '�', '�', '�', '�', '�', '�', ';', ':', '@', "'", '"', '&'), array('', '', '', '', 'ue', 'ue', 'oe', 'oe', 'ae', 'ae', '', '', '', '', '', 'and'), $this->attach_filename);
                    $this->attach_filename = str_replace(array('$', '�', '>', '<', '�', '%', '=', '/', '(', ')', '#', '*', '+', "\\", '{', '}', '[', ']'), array('dollar', 'ss', 'greater', 'lower', 'paragraph', 'percent', 'equal', '', '', '', '', '', '', '', '', '', '', ''), $this->attach_filename);
                    // Remove non-latin characters
                    $this->attach_filename = preg_replace("/([\xC2\xC3])([\x80-\xBF])/e", "chr(ord('\\1')<<6&0xC0|ord('\\2')&0x3F)", $this->attach_filename);
                    $this->attach_filename = rawurlencode($this->attach_filename);
                    $this->attach_filename = preg_replace("/(%[0-9A-F]{1,2})/i", '', $this->attach_filename);
                    $this->attach_filename = trim($this->attach_filename);

                    $new_filename = $this->attach_filename;

                    if (!$new_filename) {
                        $u_id = ((int)$userdata['user_id'] == ANONYMOUS) ? 0 : (int)$userdata['user_id'];
                        $new_filename = $u_id . '_' . $this->filetime . '.' . $this->extension;
                    }

                    do {
                        $this->attach_filename = $new_filename . '_' . substr(rand(), 0, 3) . '.' . $this->extension;
                    } while (physical_filename_already_stored($this->attach_filename));

                    unset($new_filename);
                } else {
                    $u_id = ((int)$userdata['user_id'] == ANONYMOUS) ? 0 : (int)$userdata['user_id'];
                    $this->attach_filename = $u_id . '_' . $this->filetime . '.' . $this->extension;
                }

                // Do we have to create a thumbnail ?
                if ($cat_id == IMAGE_CAT && (int)$attach_config['img_create_thumbnail']) {
                    $this->thumbnail = 1;
                }
            }

            if ($error) {
                $this->post_attach = FALSE;
                return;
            }

            // Upload Attachment
            if (!$error) {
                if (!((int)$attach_config['allow_ftp_upload'])) {
                    // Descide the Upload method
                    $ini_val = (PHP_VERSION >= '4.0.0') ? 'ini_get' : 'get_cfg_var';

                    $safe_mode = @$ini_val('safe_mode');

                    if (@$ini_val('open_basedir')) {
                        if (@PHP_VERSION < '4.0.3') {
                            $upload_mode = 'copy';
                        } else {
                            $upload_mode = 'move';
                        }
                    } else if (@$ini_val('safe_mode')) {
                        $upload_mode = 'move';
                    } else {
                        $upload_mode = 'copy';
                    }
                } else {
                    $upload_mode = 'ftp';
                }

                // Ok, upload the Attachment
                if (!$error) {
                    $this->move_uploaded_attachment($upload_mode, $file);
                }
            }

            // Now, check filesize parameters
            if (!$error) {
                if ($upload_mode != 'ftp' && !$this->filesize) {
                    $this->filesize = (int)@filesize($upload_dir . '/' . $this->attach_filename);
                }
            }

            // Check image type
            if ($cat_id == IMAGE_CAT || strpos($this->type, 'image/') === 0) {
                $img_info = @getimagesize($upload_dir . '/' . $this->attach_filename);

                // Do not display as image if we are not able to retrieve the info
                if ($img_info === false) {
                    $error = TRUE;
                    if (!empty($error_msg)) {
                        $error_msg .= '<br />';
                    }
                    $error_msg .= sprintf($lang['General_upload_error'], './' . $upload_dir . '/' . $this->attach_filename);
                } else {
                    // check file type
                    $types = array(
                        1 => array('gif'),
                        2 => array('jpg', 'jpeg'),
                        3 => array('png'),
                        4 => array('swf'),
                        5 => array('psd'),
                        6 => array('bmp'),
                        7 => array('tif', 'tiff'),
                        8 => array('tif', 'tiff'),
                        9 => array('jpg', 'jpeg'),
                        10 => array('jpg', 'jpeg'),
                        11 => array('jpg', 'jpeg'),
                        12 => array('jpg', 'jpeg'),
                        13 => array('swc'),
                        14 => array('iff'),
                        15 => array('wbmp'),
                        16 => array('xbm'),
                    );

                    if (!isset($types[$img_info[2]])) {
                        $error = TRUE;
                        if (!empty($error_msg)) {
                            $error_msg .= '<br />';
                        }
                        $error_msg .= sprintf($lang['General_upload_error'], './' . $upload_dir . '/' . $this->attach_filename);
                    } else if (!in_array($this->extension, $types[$img_info[2]])) {
                        $error = TRUE;
                        if (!empty($error_msg)) {
                            $error_msg .= '<br />';
                        }
                        $error_msg .= sprintf($lang['General_upload_error'], './' . $upload_dir . '/' . $this->attach_filename);
                        $error_msg .= "<br />Filetype mismatch: expected {$types[$img_info[2]][0]} but {$this->extension} given.";
                    }
                }
            }

            // Check Image Size, if it's an image
            if (!$error && $userdata['user_level'] != ADMIN && $cat_id == IMAGE_CAT) {
                list($width, $height) = image_getdimension($upload_dir . '/' . $this->attach_filename);

                if ($width != 0 && $height != 0 && (int)$attach_config['img_max_width'] != 0 && (int)$attach_config['img_max_height'] != 0) {
                    if ($width > (int)$attach_config['img_max_width'] || $height > (int)$attach_config['img_max_height']) {
                        $error = TRUE;
                        if (!empty($error_msg)) {
                            $error_msg .= '<br />';
                        }
                        $error_msg .= sprintf($lang['Error_imagesize'], (int)$attach_config['img_max_width'], (int)$attach_config['img_max_height']);
                    }
                }
            }

            // check Filesize
            if (!$error && $allowed_filesize != 0 && $this->filesize > $allowed_filesize && $userdata['user_level'] != ADMIN) {
                $size_lang = ($allowed_filesize >= 1048576) ? $lang['MB'] : (($allowed_filesize >= 1024) ? $lang['KB'] : $lang['Bytes']);

                if ($allowed_filesize >= 1048576) {
                    $allowed_filesize = round($allowed_filesize / 1048576 * 100) / 100;
                } else if ($allowed_filesize >= 1024) {
                    $allowed_filesize = round($allowed_filesize / 1024 * 100) / 100;
                }

                $error = TRUE;
                if (!empty($error_msg)) {
                    $error_msg .= '<br />';
                }
                $error_msg .= sprintf($lang['Attachment_too_big'], $allowed_filesize, $size_lang);
            }

            // Check our complete quota
            if ($attach_config['attachment_quota']) {
                $total_filesize = dibi::select('SUM(filesize)')
                    ->as('total')
                    ->from(Tables::ATTACH_ATTACHMENTS_DESC_TABLE)
                    ->fetchSingle();

                if (($total_filesize + $this->filesize) > $attach_config['attachment_quota']) {
                    $error = TRUE;
                    if (!empty($error_msg)) {
                        $error_msg .= '<br />';
                    }
                    $error_msg .= $lang['Attach_quota_reached'];
                }
            }

            $this->get_quota_limits($userdata);

            // Check our user quota
            if ($this->page != PAGE_PRIVMSGS) {
                if ($attach_config['upload_filesize_limit']) {
                    $attach_ids = dibi::select('attach_id')
                        ->from(Tables::ATTACH_ATTACHMENT_TABLE)
                        ->where('[user_id_1] = %i', $userdata['user_id'])
                        ->where('[privmsgs_id] = %i', 0)
                        ->groupBy('attach_id')
                        ->fetchPairs(null, 'attach_id');

                    if (count($attach_ids)) {
                        // Now get the total filesize
                        $total_filesize = dibi::select('SUM(filesize)')
                            ->as('total')
                            ->from(Tables::ATTACH_ATTACHMENTS_DESC_TABLE)
                            ->where('[attach_id] IN %in', $attach_ids)
                            ->fetchSingle();
                    } else {
                        $total_filesize = 0;
                    }

                    if (($total_filesize + $this->filesize) > $attach_config['upload_filesize_limit']) {
                        $upload_filesize_limit = $attach_config['upload_filesize_limit'];
                        $size_lang = ($upload_filesize_limit >= 1048576) ? $lang['MB'] : (($upload_filesize_limit >= 1024) ? $lang['KB'] : $lang['Bytes']);

                        if ($upload_filesize_limit >= 1048576) {
                            $upload_filesize_limit = round($upload_filesize_limit / 1048576 * 100) / 100;
                        } else if ($upload_filesize_limit >= 1024) {
                            $upload_filesize_limit = round($upload_filesize_limit / 1024 * 100) / 100;
                        }

                        $error = TRUE;
                        if (!empty($error_msg)) {
                            $error_msg .= '<br />';
                        }
                        $error_msg .= sprintf($lang['User_upload_quota_reached'], $upload_filesize_limit, $size_lang);
                    }
                }
            }

            // If we are at Private Messaging, check our PM Quota
            if ($this->page == PAGE_PRIVMSGS) {
                if ($attach_config['pm_filesize_limit']) {
                    $total_filesize = get_total_attach_pm_filesize('from_user', $userdata['user_id']);

                    if (($total_filesize + $this->filesize) > $attach_config['pm_filesize_limit']) {
                        $error = TRUE;
                        if (!empty($error_msg)) {
                            $error_msg .= '<br />';
                        }
                        $error_msg .= $lang['Attach_quota_sender_pm_reached'];
                    }
                }

                $to_user = (isset($_POST['username'])) ? $_POST['username'] : '';

                // Check Receivers PM Quota
                if (!empty($to_user) && $userdata['user_level'] != ADMIN) {
                    $u_data = get_userdata($to_user, true);

                    $user_id = (int)$u_data['user_id'];
                    $this->get_quota_limits($u_data, $user_id);

                    if ($attach_config['pm_filesize_limit']) {
                        $total_filesize = get_total_attach_pm_filesize('to_user', $user_id);

                        if (($total_filesize + $this->filesize) > $attach_config['pm_filesize_limit']) {
                            $error = TRUE;
                            if (!empty($error_msg)) {
                                $error_msg .= '<br />';
                            }
                            $error_msg .= sprintf($lang['Attach_quota_receiver_pm_reached'], $to_user);
                        }
                    }
                }
            }

            if ($error) {
                unlink_attach($this->attach_filename);
                unlink_attach($this->attach_filename, MODE_THUMBNAIL);
                $this->post_attach = FALSE;
            }
        }
    }

    // Copy the temporary attachment to the right location (copy, move_uploaded_file or ftp)
    function move_uploaded_attachment($upload_mode, $file)
    {
        global $error, $error_msg, $lang, $upload_dir;

        if (!is_uploaded_file($file)) {
            message_die(GENERAL_ERROR, 'Unable to upload file. The given source has not been uploaded.', __LINE__, __FILE__);
        }

        switch ($upload_mode) {
            case 'copy':

                if (!@copy($file, $upload_dir . '/' . basename($this->attach_filename))) {
                    if (!@move_uploaded_file($file, $upload_dir . '/' . basename($this->attach_filename))) {
                        $error = TRUE;
                        if (!empty($error_msg)) {
                            $error_msg .= '<br />';
                        }
                        $error_msg .= sprintf($lang['General_upload_error'], './' . $upload_dir . '/' . $this->attach_filename);
                        return;
                    }
                }
                @chmod($upload_dir . '/' . basename($this->attach_filename), 0666);

                break;

            case 'move':

                if (!@move_uploaded_file($file, $upload_dir . '/' . basename($this->attach_filename))) {
                    if (!@copy($file, $upload_dir . '/' . basename($this->attach_filename))) {
                        $error = TRUE;
                        if (!empty($error_msg)) {
                            $error_msg .= '<br />';
                        }
                        $error_msg .= sprintf($lang['General_upload_error'], './' . $upload_dir . '/' . $this->attach_filename);
                        return;
                    }
                }
                @chmod($upload_dir . '/' . $this->attach_filename, 0666);

                break;

            case 'ftp':
                ftp_file($file, basename($this->attach_filename), $this->type);
                break;
        }

        if (!$error && $this->thumbnail == 1) {
            if ($upload_mode == 'ftp') {
                $source = $file;
                $dest_file = THUMB_DIR . '/t_' . basename($this->attach_filename);
            } else {
                $source = $upload_dir . '/' . basename($this->attach_filename);
                $dest_file = amod_realpath($upload_dir);
                $dest_file .= '/' . THUMB_DIR . '/t_' . basename($this->attach_filename);
            }

            if (!create_thumbnail($source, $dest_file, $this->type)) {
                if (!$file || !create_thumbnail($file, $dest_file, $this->type)) {
                    $this->thumbnail = 0;
                }
            }
        }
    }
}

/**
 * @package attachment_mod
 * Attachment posting
 */
class attach_posting extends attach_parent
{
    /**
     * Constructor
     */
   public function __construct()
    {
        parent::__construct();
        $this->page = 0;
    }

    /**
     * Preview Attachments in Posts
     */
    function preview_attachments()
    {
        global $attach_config, $is_auth, $userdata;

        if ((int)$attach_config['disable_mod'] || !$is_auth['auth_attachments']) {
            return FALSE;
        }

        display_attachments_preview($this->attachment_list, $this->attachment_filesize_list, $this->attachment_filename_list, $this->attachment_comment_list, $this->attachment_extension_list, $this->attachment_thumbnail_list);
    }

    /**
     * Insert an Attachment into a Post (this is the second function called from posting.php)
     */
    function insert_attachment($post_id)
    {
        global $is_auth, $mode, $userdata, $error, $error_msg;

        // Insert Attachment ?
        if (!empty($post_id) && ($mode == 'newtopic' || $mode == 'reply' || $mode == 'editpost') && $is_auth['auth_attachments']) {
            $this->do_insert_attachment('attach_list', 'post', $post_id);
            $this->do_insert_attachment('last_attachment', 'post', $post_id);

            if ((count($this->attachment_list) > 0 || $this->post_attach) && !isset($_POST['update_attachment'])) {
                dibi::update(Tables::POSTS_TABLE, ['post_attachment' => 1])
                    ->where('[post_id] = %i', $post_id)
                    ->execute();

                $row = dibi::select('topic_id')
                    ->from(Tables::POSTS_TABLE)
                    ->where('[post_id] = %i', $post_id)
                    ->fetch();

                dibi::update(Tables::TOPICS_TABLE, ['topic_attachment' => 1])
                    ->where('[topic_id] = %i', $row->topic_id)
                    ->execute();
            }
        }
    }

    /**
     * Handle Attachments (Add/Delete/Edit/Show) - This is the first function called from every message handler
     */
    function posting_attachment_mod()
    {
        global $mode, $confirm, $is_auth, $post_id, $add_attachment_box, $delete, $refresh;

        if (!$refresh) {
            $add_attachment_box = (!empty($_POST['add_attachment_box'])) ? TRUE : FALSE;
            $posted_attachments_box = (!empty($_POST['posted_attachments_box'])) ? TRUE : FALSE;

            $refresh = $add_attachment_box || $posted_attachments_box;
        }

        // Choose what to display
        $result = $this->handle_attachments($mode);

        if ($result === false) {
            return;
        }

        if ($confirm && ($delete || $mode == 'delete' || $mode == 'editpost') && ($is_auth['auth_delete'] || $is_auth['auth_mod'])) {
            if ($post_id) {
                delete_attachment($post_id);
            }
        }

        $this->display_attachment_bodies();
    }

}

/**
 * Entry Point
 */
function execute_posting_attachment_handling()
{
    global $attachment_mod;

    $attachment_mod['posting'] = new attach_posting();
    $attachment_mod['posting']->posting_attachment_mod();
}

?>