<?php

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
        $this->attachment_id_list = get_var('attach_id_list', [0]);
        $this->attachment_comment_list = get_var('comment_list', [''], true);
        $this->attachment_filesize_list = get_var('filesize_list', [0]);
        $this->attachment_filetime_list = get_var('filetime_list', [0]);
        $this->attachment_filename_list = get_var('filename_list', ['']);
        $this->attachment_extension_list = get_var('extension_list', ['']);
        $this->attachment_mimetype_list = get_var('mimetype_list', ['']);

        $this->filename = (isset($_FILES['fileupload'], $_FILES['fileupload']['name']) && $_FILES['fileupload']['name'] !== 'none') ? trim(stripslashes($_FILES['fileupload']['name'])) : '';

        $this->attachment_list = get_var('attachment_list', ['']);
        $this->attachment_thumbnail_list = get_var('attach_thumbnail_list', [0]);
    }

    /**
     * Get Quota Limits
     *
     * @param     $userdata_quota
     * @param int $user_id
     */
    public function get_quota_limits($userdata_quota, $user_id = 0)
    {
        global $attach_config;

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
        $priorities = 'user;group';

        if ($userdata_quota['user_level'] === ADMIN) {
            $attach_config['pm_filesize_limit'] = 0; // Unlimited
            $attach_config['upload_filesize_limit'] = 0; // Unlimited
            return;
        }

        if ($this->page === PAGE_PRIVMSGS) {
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

        $priorities = explode(';', $priorities);
        $found = false;

        foreach ($priorities as $priority) {
            if (($priority === 'group') && (!$found)) {
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

            if ($priority === 'user' && !$found) {
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
            $quota_id = ($quota_type === QUOTA_UPLOAD_LIMIT) ? $attach_config['default_upload_quota'] : $attach_config['default_pm_quota'];

            if ($quota_id === 0) {
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
        if (($quota_type === QUOTA_UPLOAD_LIMIT) && $attach_config[$limit_type] > $attach_config[$default]) {
            $attach_config[$limit_type] = $attach_config[$default];
        }
    }

    /**
     * Handle all modes... (intern)
     * @private
     * @param $mode
     * @return bool
     * @throws \Dibi\Exception
     */
    public function handle_attachments($mode)
    {
        global $is_auth, $attach_config, $refresh, $post_id, $submit, $preview, $error, $error_msg, $lang, $template, $userdata;
        global $postId;

        //
        // ok, what shall we do ;)
        //

        // Some adjustments for PM's
        if ($this->page === PAGE_PRIVMSGS) {
            global $privmsg_id;

            $postId = $privmsg_id;

            if ($mode === 'post') {
                $mode = 'newtopic';
            } else if ($mode === 'edit') {
                $mode = 'editpost';
            }

            if ($userdata['user_level'] === ADMIN) {
                $is_auth['auth_attachments'] = 1;
                $max_attachments = ADMIN_MAX_ATTACHMENTS;
            } else {
                $is_auth['auth_attachments'] = (int)$attach_config['allow_pm_attach'];
                $max_attachments = (int)$attach_config['max_attachments_pm'];
            }

            $sql_id = 'privmsgs_id';
        } else {
            if ($userdata['user_level'] === ADMIN) {
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
        $allowed_attach_ids = [];
        if ($postId) {
            $allowed_attach_ids = dibi::select(['attach_id'])
                ->from(Tables::ATTACH_ATTACHMENT_TABLE)
                ->where('%n = %i', $sql_id, $postId)
                ->fetchPairs(null, 'attach_id');
        }

        // Check the submitted variables - do not allow wrong values
        $actual_id_list = get_var('attach_id_list', [0]);
        $actual_list = get_var('attachment_list', ['']);

        foreach ($actual_list as $i => $attachmentFileName) {
            if ($actual_id_list[$i] !== 0) {
                if (!in_array($actual_id_list[$i], $allowed_attach_ids, true)) {
                    message_die(CRITICAL_ERROR, 'You tried to change an attachment you do not have access to', '');
                }
            } else {
                // Really new attachment? If so, the filename should be unique...
                if (physical_filename_already_stored($attachmentFileName)) {
                    message_die(CRITICAL_ERROR, 'You tried to change an attachment you do not have access to', '');
                }
            }
        }

        // Init Vars
        $attachments = [];

        if (!$refresh) {
            $add = isset($_POST['add_attachment']);
            $delete = isset($_POST['del_attachment']);
            $edit = isset($_POST['edit_comment']);
            $update_attachment = isset($_POST['update_attachment']);
            $del_thumbnail = isset($_POST['del_thumbnail']);

            $add_attachment_box = !empty($_POST['add_attachment_box']);
            $posted_attachments_box = !empty($_POST['posted_attachments_box']);

            $refresh = $add || $delete || $edit || $del_thumbnail || $update_attachment || $add_attachment_box || $posted_attachments_box;
        }

        // Get Attachments
        if ($this->page === PAGE_PRIVMSGS) {
            $attachments = get_attachments_from_pm($postId);
        } else {
            $attachments = get_attachments_from_post($postId);
        }

        if ($this->page === PAGE_PRIVMSGS) {
            if ($userdata['user_level'] === ADMIN) {
                $auth = true;
            } else {
                $auth = ((int)$attach_config['allow_pm_attach']) ? true : false;
            }

            if (count($attachments) === 1) {
                $template->assignBlockVars('switch_attachments', []);

                $template->assignVars([
                        'L_DELETE_ATTACHMENTS' => $lang['Delete_attachment']]
                );
            } else if (count($attachments) > 0) {
                $template->assignBlockVars('switch_attachments', []);

                $template->assignVars([
                        'L_DELETE_ATTACHMENTS' => $lang['Delete_attachments']]
                );
            }
        } else {
            $auth = $is_auth['auth_edit'] || $is_auth['auth_mod'];
        }

        if (!$submit && $mode === 'editpost' && $auth && !$refresh && !$preview && !$error && !isset($_POST['del_poll_option'])) {
            foreach ($attachments as $attachment) {
                $this->attachment_list[] = $attachment->physical_filename;
                $this->attachment_comment_list[] = $attachment->comment;
                $this->attachment_filename_list[] = $attachment->real_filename;
                $this->attachment_extension_list[] = $attachment->extension;
                $this->attachment_mimetype_list[] = $attachment->mimetype;
                $this->attachment_filesize_list[] = $attachment->filesize;
                $this->attachment_filetime_list[] = $attachment->filetime;
                $this->attachment_id_list[] = $attachment->attach_id;
                $this->attachment_thumbnail_list[] = $attachment->thumbnail;
            }
        }

        $this->num_attachments = count($this->attachment_list);

        if ($submit && $mode !== 'vote') {
            if ($mode === 'newtopic' || $mode === 'reply' || $mode === 'editpost') {
                if ($this->filename !== '') {
                    if ($this->num_attachments < (int)$max_attachments) {
                        $this->upload_attachment();

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

                            // This Variable is set to false here, because the Attachment Mod enter Attachments into the
                            // Database in two modes, one if the id_list is 0 and the second one if post_attach is true
                            // Since post_attach is automatically switched to true if an Attachment got added to the filesystem,
                            // but we are assigning an id of 0 here, we have to reset the post_attach variable to false.
                            //
                            // This is very relevant, because it could happen that the post got not submitted, but we do not
                            // know this circumstance here. We could be at the posting page or we could be redirected to the entered
                            // post. :)
                            $this->post_attach = false;
                        }
                    } else {
                        $error = true;

                        if (!empty($error_msg)) {
                            $error_msg .= '<br />';
                        }

                        $error_msg .= sprintf($lang['Too_many_attachments'], (int)$max_attachments);
                    }
                }
            }
        }

        if ($preview || $refresh || $error) {
            $delete_attachment = isset($_POST['del_attachment']);
            $delete_thumbnail = isset($_POST['del_thumbnail']);

            $add_attachment = isset($_POST['add_attachment']);
            $edit_attachment = isset($_POST['edit_comment']);
            $update_attachment = isset($_POST['update_attachment']);

            // Perform actions on temporary attachments
            if ($delete_attachment || $delete_thumbnail) {
                // store old values
                $actual_id_list = get_var('attach_id_list', [0]);
                $actual_comment_list = get_var('comment_list', [''], true);
                $actual_filename_list = get_var('filename_list', ['']);
                $actual_extension_list = get_var('extension_list', ['']);
                $actual_mimetype_list = get_var('mimetype_list', ['']);
                $actual_filesize_list = get_var('filesize_list', [0]);
                $actual_filetime_list = get_var('filetime_list', [0]);

                $actual_list = get_var('attachment_list', ['']);
                $actual_thumbnail_list = get_var('attach_thumbnail_list', [0]);

                // clean values
                $this->attachment_list = [];
                $this->attachment_comment_list = [];
                $this->attachment_filename_list = [];
                $this->attachment_extension_list = [];
                $this->attachment_mimetype_list = [];
                $this->attachment_filesize_list = [];
                $this->attachment_filetime_list = [];
                $this->attachment_id_list = [];
                $this->attachment_thumbnail_list = [];

                // restore values :)
                if (isset($_POST['attachment_list'])) {
                    foreach ($actual_list as $i => $attachFileName) {
                        $restore = false;
                        $del_thumb = false;

                        if ($delete_thumbnail) {
                            if (isset($_POST['del_thumbnail'][$attachFileName])) {
                                $del_thumb = true;
                            } else {
                                $restore = true;
                            }
                        }

                        if ($delete_attachment && !isset($_POST['del_attachment'][$attachFileName])) {
                            $restore = true;
                        }

                        if ($restore) {
                            $this->attachment_list[] = $attachFileName;
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
                            if ($actual_id_list[$i] === '0') {
                                unlink_attach($attachFileName);

                                if ($actual_thumbnail_list[$i] === 1) {
                                    unlink_attach($attachFileName, MODE_THUMBNAIL);
                                }
                            } else {
                                delete_attachment([$postId], $actual_id_list[$i], $this->page);
                            }
                        } else if ($del_thumb) {
                            // delete selected thumbnail
                            $this->attachment_list[] = $attachFileName;
                            $this->attachment_comment_list[] = $actual_comment_list[$i];
                            $this->attachment_filename_list[] = $actual_filename_list[$i];
                            $this->attachment_extension_list[] = $actual_extension_list[$i];
                            $this->attachment_mimetype_list[] = $actual_mimetype_list[$i];
                            $this->attachment_filesize_list[] = $actual_filesize_list[$i];
                            $this->attachment_filetime_list[] = $actual_filetime_list[$i];
                            $this->attachment_id_list[] = $actual_id_list[$i];
                            $this->attachment_thumbnail_list[] = 0;

                            if ($actual_id_list[$i] === 0) {
                                unlink_attach($attachFileName, MODE_THUMBNAIL);
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
                    $actual_comment_list = get_var('comment_list', [''], true);

                    $this->attachment_comment_list = [];

                    foreach ($this->attachment_list as $i => $value) {
                        $this->attachment_comment_list[$i] = $actual_comment_list[$i];
                    }
                }

                if ($update_attachment) {
                    if ($this->filename === '') {
                        $error = true;

                        if (!empty($error_msg)) {
                            $error_msg .= '<br />';
                        }

                        $error_msg .= $lang['Error_empty_add_attachbox'];
                    }

                    $this->upload_attachment();

                    if (!$error) {
                        $actual_list = get_var('attachment_list', ['']);
                        $actual_id_list = get_var('attach_id_list', [0]);

                        $attachment_id = 0;
                        $actual_element = 0;

                        foreach ($actual_id_list as $i => $attachmentId) {
                            if (isset($_POST['update_attachment'][$attachmentId])) {
                                $attachment_id = (int)$attachmentId;
                                $actual_element = $i;
                            }
                        }

                        // Get current informations to delete the Old Attachment
                        $row = dibi::select(['physical_filename', 'comment', 'thumbnail'])
                            ->from(Tables::ATTACH_ATTACHMENTS_DESC_TABLE)
                            ->where('[attach_id] = %i', $attachment_id)
                            ->fetch();

                        if (!$row) {
                            $error = true;

                            if (!empty($error_msg)) {
                                $error_msg .= '<br />';
                            }

                            $error_msg .= $lang['Error_missing_old_entry'];
                        }

                        $comment = (trim($this->file_comment) === '') ? trim($row->comment) : trim($this->file_comment);

                        // Update Entry
                        $sql_ary = [
                            'physical_filename' => (string)basename($this->attach_filename),
                            'real_filename' => (string)basename($this->filename),
                            'comment' => (string)$comment,
                            'extension' => (string)mb_strtolower($this->extension),
                            'mimetype' => (string)mb_strtolower($this->type),
                            'filesize' => (int)$this->filesize,
                            'filetime' => (int)$this->filetime,
                            'thumbnail' => (int)$this->thumbnail
                        ];

                        dibi::update(Tables::ATTACH_ATTACHMENTS_DESC_TABLE, $sql_ary)
                            ->where('[attach_id] = %i', $attachment_id)
                            ->execute();

                        // Delete the Old Attachment
                        unlink_attach($row->physical_filename);

                        if ((int)$row->thumbnail === 1) {
                            unlink_attach($row->physical_filename, MODE_THUMBNAIL);
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

                if (($add_attachment || $preview) && $this->filename !== '') {
                    if ($this->num_attachments < (int)$max_attachments) {
                        $this->upload_attachment();

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
                        $error = true;

                        if (!empty($error_msg)) {
                            $error_msg .= '<br />';
                        }

                        $error_msg .= sprintf($lang['Too_many_attachments'], (int)$max_attachments);
                    }
                }
            }
        }

        return true;
    }

    /**
     * Basic Insert Attachment Handling for all Message Types
     * @param $mode
     * @param $message_type
     * @param $message_id
     * @return bool
     * @throws \Dibi\Exception
     */
    public function do_insert_attachment($mode, $message_type, $message_id)
    {
        if ((int)$message_id < 0) {
            return false;
        }

        if ($message_type === 'pm') {
            global $userdata, $to_userdata;

            $post_id = 0;
            $privmsgs_id = (int)$message_id;
            $user_id_1 = (int)$userdata['user_id'];
            $user_id_2 = (int)$to_userdata['user_id'];
            $sql_id = 'privmsgs_id';
        } else if ($message_type === 'post') {
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

        if ($mode === 'attach_list') {
            foreach ($this->attachment_list as $i => $attachmentId) {
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
                    $sql_ary = [
                        'physical_filename' => (string)basename($this->attachment_list[$i]),
                        'real_filename' => (string)basename($this->attachment_filename_list[$i]),
                        'comment' => (string)$this->attachment_comment_list[$i],
                        'extension' => (string)mb_strtolower($this->attachment_extension_list[$i]),
                        'mimetype' => (string)mb_strtolower($this->attachment_mimetype_list[$i]),
                        'filesize' => (int)$this->attachment_filesize_list[$i],
                        'filetime' => (int)$this->attachment_filetime_list[$i],
                        'thumbnail' => (int)$this->attachment_thumbnail_list[$i]
                    ];

                    $attach_id = dibi::insert(Tables::ATTACH_ATTACHMENTS_DESC_TABLE, $sql_ary)->execute(dibi::IDENTIFIER);

                    $sql_ary = [
                        'attach_id' => (int)$attach_id,
                        'post_id' => (int)$post_id,
                        'privmsgs_id' => (int)$privmsgs_id,
                        'user_id_1' => (int)$user_id_1,
                        'user_id_2' => (int)$user_id_2
                    ];

                    dibi::insert(Tables::ATTACH_ATTACHMENT_TABLE, $sql_ary)->execute();
                }
            }

            return true;
        }

        if (($mode === 'last_attachment') && $this->post_attach && !isset($_POST['update_attachment'])) {
            // insert attachment into db, here the user submited it directly
            $sql_ary = [
                'physical_filename' => (string)basename($this->attach_filename),
                'real_filename' => (string)basename($this->filename),
                'comment' => (string)$this->file_comment,
                'extension' => (string)mb_strtolower($this->extension),
                'mimetype' => (string)mb_strtolower($this->type),
                'filesize' => (int)$this->filesize,
                'filetime' => (int)$this->filetime,
                'thumbnail' => (int)$this->thumbnail
            ];

            $attach_id = dibi::insert(Tables::ATTACH_ATTACHMENTS_DESC_TABLE, $sql_ary)->execute(dibi::IDENTIFIER);

            $sql_ary = [
                'attach_id' => (int)$attach_id,
                'post_id' => (int)$post_id,
                'privmsgs_id' => (int)$privmsgs_id,
                'user_id_1' => (int)$user_id_1,
                'user_id_2' => (int)$user_id_2
            ];

            dibi::insert(Tables::ATTACH_ATTACHMENT_TABLE, $sql_ary)->execute();
        }
    }

    /**
     * Attachment Mod entry switch/output (intern)
     * @private
     */
    public function display_attachment_bodies()
    {
        global $attach_config, $is_auth, $lang, $mode, $template, $upload_dir, $userdata, $forum_id;
        global $phpbb_root_path;

        // Choose what to display
        $value_add = $value_posted = 0;

        if ((int)$attach_config['show_apcp']) {
            if (!empty($_POST['add_attachment_box'])) {
                $value_add = ($this->add_attachment_body === 0) ? 1 : 0;
                $this->add_attachment_body = $value_add;
            } else {
                $value_add = ($this->add_attachment_body === 0) ? 0 : 1;
            }

            if (!empty($_POST['posted_attachments_box'])) {
                $value_posted = ($this->posted_attachments_body === 0) ? 1 : 0;
                $this->posted_attachments_body = $value_posted;
            } else {
                $value_posted = ($this->posted_attachments_body === 0) ? 0 : 1;
            }
            $template->assignBlockVars('show_apcp', []);
        } else {
            $this->add_attachment_body = 1;
            $this->posted_attachments_body = 1;
        }

        $template->setFileNames(['attachbody' => 'posting_attach_body.tpl']);

        display_compile_cache_clear($template->getFiles()['attachbody'], 'attachbody');

        $s_hidden = '<input type="hidden" name="add_attachment_body" value="' . $value_add . '" />';
        $s_hidden .= '<input type="hidden" name="posted_attachments_body" value="' . $value_posted . '" />';

        if ($this->page === PAGE_PRIVMSGS) {
            $u_rules_id = 0;
        } else {
            $u_rules_id = $forum_id;
        }

        $template->assignVars(
            [
                'L_ATTACH_POSTING_CP' => $lang['Attach_posting_cp'],
                'L_ATTACH_POSTING_CP_EXPLAIN' => $lang['Attach_posting_cp_explain'],
                'L_OPTIONS' => $lang['Options'],
                'L_ADD_ATTACHMENT_TITLE' => $lang['Add_attachment_title'],
                'L_POSTED_ATTACHMENTS' => $lang['Posted_attachments'],
                'L_FILE_NAME' => $lang['File_name'],
                'L_FILE_COMMENT' => $lang['File_comment'],
                'RULES' => '<a href="' . Session::appendSid($phpbb_root_path . "attach_rules.php?f=$u_rules_id") . '" target="_blank">' . $lang['Allowed_extensions_and_sizes'] . '</a>',

                'S_HIDDEN' => $s_hidden
            ]
        );

        $attachments = [];

        if (count($this->attachment_list) > 0) {
            if ((int)$attach_config['show_apcp']) {
                $template->assignBlockVars('switch_posted_attachments', []);
            }

            foreach ($this->attachment_list as $i => $attachmentId) {
                $hidden = '<input type="hidden" name="attachment_list[]" value="' . $this->attachment_list[$i] . '" />';
                $hidden .= '<input type="hidden" name="filename_list[]" value="' . $this->attachment_filename_list[$i] . '" />';
                $hidden .= '<input type="hidden" name="extension_list[]" value="' . $this->attachment_extension_list[$i] . '" />';
                $hidden .= '<input type="hidden" name="mimetype_list[]" value="' . $this->attachment_mimetype_list[$i] . '" />';
                $hidden .= '<input type="hidden" name="filesize_list[]" value="' . $this->attachment_filesize_list[$i] . '" />';
                $hidden .= '<input type="hidden" name="filetime_list[]" value="' . $this->attachment_filetime_list[$i] . '" />';
                $hidden .= '<input type="hidden" name="attach_id_list[]" value="' . $this->attachment_id_list[$i] . '" />';
                $hidden .= '<input type="hidden" name="attach_thumbnail_list[]" value="' . $this->attachment_thumbnail_list[$i] . '" />';

                if (!$this->posted_attachments_body || count($this->attachment_list) === 0) {
                    $hidden .= '<input type="hidden" name="comment_list[]" value="' . $this->attachment_comment_list[$i] . '" />';
                }

                $template->assignBlockVars('hidden_row',
                    [
                        'S_HIDDEN' => $hidden
                    ]
                );
            }
        }

        if ($this->add_attachment_body) {
            init_display_template('attachbody', '{ADD_ATTACHMENT_BODY}', 'add_attachment_body.tpl');

            $form_enctype = 'enctype="multipart/form-data"';

            $template->assignVars(
                [
                    'L_ADD_ATTACH_TITLE' => $lang['Add_attachment_title'],
                    'L_ADD_ATTACH_EXPLAIN' => $lang['Add_attachment_explain'],
                    'L_ADD_ATTACHMENT' => $lang['Add_attachment'],

                    'FILE_COMMENT' => $this->file_comment,
                    'FILESIZE' => $attach_config['max_filesize'],
                    'FILENAME' => $this->filename,

                    'S_FORM_ENCTYPE' => $form_enctype]
            );
        }

        if ($this->posted_attachments_body && count($this->attachment_list) > 0) {
            init_display_template('attachbody', '{POSTED_ATTACHMENTS_BODY}', 'posted_attachments_body.tpl');

            $template->assignVars(
                [
                    'L_POSTED_ATTACHMENTS' => $lang['Posted_attachments'],
                    'L_UPDATE_COMMENT' => $lang['Update_comment'],
                    'L_UPLOAD_NEW_VERSION' => $lang['Upload_new_version'],
                    'L_DELETE_ATTACHMENT' => $lang['Delete_attachment'],
                    'L_DELETE_THUMBNAIL' => $lang['Delete_thumbnail'],
                    'L_OPTIONS' => $lang['Options']
                ]
            );

            foreach ($this->attachment_list as $i => $attachmentId) {
                if ($this->attachment_id_list[$i] === 0) {
                    $download_link = $upload_dir . '/' . basename($this->attachment_list[$i]);
                } else {
                    $download_link = Session::appendSid($phpbb_root_path . 'download.php?id=' . $this->attachment_id_list[$i]);
                }

                $template->assignBlockVars('attach_row', [
                        'FILE_NAME' => $this->attachment_filename_list[$i],
                        'ATTACH_FILENAME' => $this->attachment_list[$i],
                        'FILE_COMMENT' => $this->attachment_comment_list[$i],
                        'ATTACH_ID' => $this->attachment_id_list[$i],

                        'U_VIEW_ATTACHMENT' => $download_link]
                );

                // Thumbnail there ? And is the User Admin or Mod ? Then present the 'Delete Thumbnail' Button
                if ((int)$this->attachment_thumbnail_list[$i] === 1 && ((isset($is_auth['auth_mod']) && $is_auth['auth_mod']) || $userdata['user_level'] === ADMIN)) {
                    $template->assignBlockVars('attach_row.switch_thumbnail', []);
                }

                if ($this->attachment_id_list[$i]) {
                    $template->assignBlockVars('attach_row.switch_update_attachment', []);
                }
            }
        }

        $template->assignVarFromHandle('ATTACHBOX', 'attachbody');
    }

    /**
     * Upload an Attachment to Filespace (intern)
     */
    public function upload_attachment()
    {
        global $error, $error_msg, $lang, $attach_config, $userdata, $upload_dir, $forum_id;

        $this->post_attach = $this->filename !== '';

        if ($this->post_attach) {
            $r_file = trim(basename(htmlspecialchars($this->filename)));
            $file = $_FILES['fileupload']['tmp_name'];
            $this->type = $_FILES['fileupload']['type'];

            if (isset($_FILES['fileupload']['size']) && $_FILES['fileupload']['size'] === 0) {
                message_die(GENERAL_ERROR, 'Tried to upload empty file');
            }

            // Opera add the name to the mime type
            $this->type = (strstr($this->type, '; name')) ? str_replace(strstr($this->type, '; name'), '', $this->type) : $this->type;
            $this->type = mb_strtolower($this->type);
            $this->extension = mb_strtolower(get_extension($this->filename));

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

            $allowed_filesize = ($row->max_filesize) ? $row->max_filesize : $attach_config['max_filesize'];
            $cat_id = (int)$row->cat_id;
            $auth_cache = trim($row->forum_permissions);

            // check Filename
            if (preg_match("#[\\/:*?\"<>|]#i", $this->filename)) {
                $error = true;

                if (!empty($error_msg)) {
                    $error_msg .= '<br />';
                }

                $error_msg .= sprintf($lang['Invalid_filename'], htmlspecialchars($this->filename));
            }

            // check php upload-size
            if (!$error && $file === 'none') {
                $error = true;

                if (!empty($error_msg)) {
                    $error_msg .= '<br />';
                }

                $ini_val = (PHP_VERSION >= '4.0.0') ? 'ini_get' : 'get_cfg_var';

                $max_size = @$ini_val('upload_max_filesize');

                if ($max_size === '') {
                    $error_msg .= $lang['Attachment_php_size_na'];
                } else {
                    $error_msg .= sprintf($lang['Attachment_php_size_overrun'], $max_size);
                }
            }

            // Check Extension
            if (!$error && (int)$row->allow_group === 0) {
                $error = true;

                if (!empty($error_msg)) {
                    $error_msg .= '<br />';
                }

                $error_msg .= sprintf($lang['Disallowed_extension'], htmlspecialchars($this->extension));
            }

            // Check Forum Permissions
            if (!$error && $this->page !== PAGE_PRIVMSGS && $userdata['user_level'] !== ADMIN && !is_forum_authed($auth_cache, $forum_id) && trim($auth_cache) !== '') {
                $error = true;

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
                $this->attach_filename = mb_strtolower($this->filename);

                // To re-add cryptic filenames, change this variable to true
                $cryptic = false;

                if ($cryptic) {
                    $u_id = ((int)$userdata['user_id'] === ANONYMOUS) ? 0 : (int)$userdata['user_id'];
                    $this->attach_filename = $u_id . '_' . $this->filetime . '.' . $this->extension;
                } else {
                    $this->attach_filename = html_entity_decode(trim(stripslashes($this->attach_filename)));
                    $this->attach_filename = delete_extension($this->attach_filename);
                    $this->attach_filename = str_replace([' ', '-'], ['_', '_'], $this->attach_filename);
                    $this->attach_filename = str_replace('__', '_', $this->attach_filename);
                    $this->attach_filename = str_replace([',', '.', '!', '?', '�', '�', '�', '�', '�', '�', ';', ':', '@', "'", '"', '&'], ['', '', '', '', 'ue', 'ue', 'oe', 'oe', 'ae', 'ae', '', '', '', '', '', 'and'], $this->attach_filename);
                    $this->attach_filename = str_replace(['$', '�', '>', '<', '�', '%', '=', '/', '(', ')', '#', '*', '+', "\\", '{', '}', '[', ']'], ['dollar', 'ss', 'greater', 'lower', 'paragraph', 'percent', 'equal', '', '', '', '', '', '', '', '', '', '', ''], $this->attach_filename);
                    // Remove non-latin characters
                    $this->attach_filename = preg_replace("/([\xC2\xC3])([\x80-\xBF])/e", "chr(ord('\\1')<<6&0xC0|ord('\\2')&0x3F)", $this->attach_filename);
                    $this->attach_filename = rawurlencode($this->attach_filename);
                    $this->attach_filename = preg_replace('/(%[0-9A-F]{1,2})/i', '', $this->attach_filename);
                    $this->attach_filename = trim($this->attach_filename);

                    $new_filename = $this->attach_filename;

                    if (!$new_filename) {
                        $u_id = ((int)$userdata['user_id'] === ANONYMOUS) ? 0 : (int)$userdata['user_id'];
                        $new_filename = $u_id . '_' . $this->filetime . '.' . $this->extension;
                    }

                    do {
                        $this->attach_filename = $new_filename . '_' . mb_substr(rand(), 0, 3) . '.' . $this->extension;
                    } while (physical_filename_already_stored($this->attach_filename));

                    unset($new_filename);
                }

                // Do we have to create a thumbnail ?
                if ($cat_id === IMAGE_CAT && (int)$attach_config['img_create_thumbnail']) {
                    $this->thumbnail = 1;
                }
            }

            if ($error) {
                $this->post_attach = false;
                return;
            }

            // Upload Attachment
            if (!$error) {
                if (((int)$attach_config['allow_ftp_upload'])) {
                    $upload_mode = 'ftp';
                } else {
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
                }

                // Ok, upload the Attachment
                if (!$error) {
                    $this->move_uploaded_attachment($upload_mode, $file);
                }
            }

            // Now, check filesize parameters
            if (!$error && $upload_mode !== 'ftp' && !$this->filesize) {
                $this->filesize = (int)@filesize($upload_dir . '/' . $this->attach_filename);
            }

            // Check image type
            if ($cat_id === IMAGE_CAT || strpos($this->type, 'image/') === 0) {
                $img_info = @getimagesize($upload_dir . '/' . $this->attach_filename);

                // Do not display as image if we are not able to retrieve the info
                if ($img_info === false) {
                    $error = true;

                    if (!empty($error_msg)) {
                        $error_msg .= '<br />';
                    }

                    $error_msg .= sprintf($lang['General_upload_error'], './' . $upload_dir . '/' . $this->attach_filename);
                } else {
                    // check file type
                    $types = [
                        1 => ['gif'],
                        2 => ['jpg', 'jpeg'],
                        3 => ['png'],
                        4 => ['swf'],
                        5 => ['psd'],
                        6 => ['bmp'],
                        7 => ['tif', 'tiff'],
                        8 => ['tif', 'tiff'],
                        9 => ['jpg', 'jpeg'],
                        10 => ['jpg', 'jpeg'],
                        11 => ['jpg', 'jpeg'],
                        12 => ['jpg', 'jpeg'],
                        13 => ['swc'],
                        14 => ['iff'],
                        15 => ['wbmp'],
                        16 => ['xbm'],
                    ];

                    if (!isset($types[$img_info[2]])) {
                        $error = true;

                        if (!empty($error_msg)) {
                            $error_msg .= '<br />';
                        }

                        $error_msg .= sprintf($lang['General_upload_error'], './' . $upload_dir . '/' . $this->attach_filename);
                    } else if (!in_array($this->extension, $types[$img_info[2]], true)) {
                        $error = true;

                        if (!empty($error_msg)) {
                            $error_msg .= '<br />';
                        }

                        $error_msg .= sprintf($lang['General_upload_error'], './' . $upload_dir . '/' . $this->attach_filename);
                        $error_msg .= "<br />Filetype mismatch: expected {$types[$img_info[2]][0]} but {$this->extension} given.";
                    }
                }
            }

            // Check Image Size, if it's an image
            if (!$error && $userdata['user_level'] !== ADMIN && $cat_id === IMAGE_CAT) {
                list($width, $height) = image_getdimension($upload_dir . '/' . $this->attach_filename);

                if ($width !== 0 && $height !== 0 && (int)$attach_config['img_max_width'] !== 0 && (int)$attach_config['img_max_height'] !== 0) {
                    if ($width > (int)$attach_config['img_max_width'] || $height > (int)$attach_config['img_max_height']) {
                        $error = true;

                        if (!empty($error_msg)) {
                            $error_msg .= '<br />';
                        }

                        $error_msg .= sprintf($lang['Error_imagesize'], (int)$attach_config['img_max_width'], (int)$attach_config['img_max_height']);
                    }
                }
            }

            // check Filesize
            if (!$error && $allowed_filesize !== 0 && $this->filesize > $allowed_filesize && $userdata['user_level'] !== ADMIN) {
                $error = true;

                if (!empty($error_msg)) {
                    $error_msg .= '<br />';
                }

                $error_msg .= sprintf($lang['Attachment_too_big'], get_formatted_filesize($allowed_filesize));
            }

            // Check our complete quota
            if ($attach_config['attachment_quota']) {
                $total_filesize = dibi::select('SUM(filesize)')
                    ->as('total')
                    ->from(Tables::ATTACH_ATTACHMENTS_DESC_TABLE)
                    ->fetchSingle();

                if (($total_filesize + $this->filesize) > $attach_config['attachment_quota'] && $userdata['user_level'] !== ADMIN) {
                    $error = true;

                    if (!empty($error_msg)) {
                        $error_msg .= '<br />';
                    }

                    $error_msg .= $lang['Attach_quota_reached'];
                }
            }

            $this->get_quota_limits($userdata);

            // Check our user quota
            if (($this->page !== PAGE_PRIVMSGS) && $attach_config['upload_filesize_limit']) {
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
                    $error = true;

                    if (!empty($error_msg)) {
                        $error_msg .= '<br />';
                    }

                    $error_msg .= sprintf($lang['User_upload_quota_reached'], get_total_attach_filesize($attach_config['upload_filesize_limit']));
                }
            }

            // If we are at Private Messaging, check our PM Quota
            if ($this->page === PAGE_PRIVMSGS) {
                if ($attach_config['pm_filesize_limit']) {
                    $total_filesize = get_total_attach_pm_filesize('from_user', $userdata['user_id']);

                    if (($total_filesize + $this->filesize) > $attach_config['pm_filesize_limit']) {
                        $error = true;

                        if (!empty($error_msg)) {
                            $error_msg .= '<br />';
                        }

                        $error_msg .= $lang['Attach_quota_sender_pm_reached'];
                    }
                }

                $to_user = (isset($_POST['username'])) ? $_POST['username'] : '';

                // Check Receivers PM Quota
                if (!empty($to_user) && $userdata['user_level'] !== ADMIN) {
                    $u_data = get_userdata($to_user, true);

                    $user_id = (int)$u_data['user_id'];
                    $this->get_quota_limits($u_data, $user_id);

                    if ($attach_config['pm_filesize_limit']) {
                        $total_filesize = get_total_attach_pm_filesize('to_user', $user_id);

                        if (($total_filesize + $this->filesize) > $attach_config['pm_filesize_limit']) {
                            $error = true;

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
                $this->post_attach = false;
            }
        }
    }

    /**
     * Copy the temporary attachment to the right location (copy, move_uploaded_file or ftp)
     *
     * @param $upload_mode
     * @param $file
     */
    /**
     * @param $upload_mode
     * @param $file
     */
    public function move_uploaded_attachment($upload_mode, $file)
    {
        global $error, $error_msg, $lang, $upload_dir;

        if (!is_uploaded_file($file)) {
            message_die(GENERAL_ERROR, 'Unable to upload file. The given source has not been uploaded.', __LINE__, __FILE__);
        }

        switch ($upload_mode) {
            case 'copy':

                if (!@copy($file, $upload_dir . '/' . basename($this->attach_filename))) {
                    if (!@move_uploaded_file($file, $upload_dir . '/' . basename($this->attach_filename))) {
                        $error = true;

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
                        $error = true;

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

        if (!$error && $this->thumbnail === 1) {
            if ($upload_mode === 'ftp') {
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
