<?php

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
    public function preview_attachments()
    {
        global $attach_config, $is_auth, $userdata;

        if ((int)$attach_config['disable_mod'] || !$is_auth['auth_attachments']) {
            return false;
        }

        display_attachments_preview(
            $this->attachment_list,
            $this->attachment_filesize_list,
            $this->attachment_filename_list,
            $this->attachment_comment_list,
            $this->attachment_extension_list,
            $this->attachment_thumbnail_list
        );
    }

    /**
     * Insert an Attachment into a Post (this is the second function called from posting.php)
     * @param $post_id
     * @throws \Dibi\Exception
     */
    public function insert_attachment($post_id)
    {
        global $is_auth, $mode, $userdata, $error, $error_msg;

        // Insert Attachment ?
        if (!empty($post_id) && ($mode === 'newtopic' || $mode === 'reply' || $mode === 'editpost') && $is_auth['auth_attachments']) {
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
    public function posting_attachment_mod()
    {
        global $mode, $confirm, $is_auth, $postId, $add_attachment_box, $delete, $refresh;

        if (!$refresh) {
            $add_attachment_box = !empty($_POST['add_attachment_box']);
            $posted_attachments_box = !empty($_POST['posted_attachments_box']);

            $refresh = $add_attachment_box || $posted_attachments_box;
        }

        // Choose what to display
        $result = $this->handle_attachments($mode);

        if ($result === false) {
            return;
        }

        if ($confirm && ($delete || $mode === 'delete' || $mode === 'editpost') && ($is_auth['auth_delete'] || $is_auth['auth_mod'])) {
            if ($postId) {
                delete_attachment([$postId]);
            }
        }

        $this->display_attachment_bodies();
    }
}
