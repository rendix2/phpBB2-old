<?php
/**
 *
 * @package attachment_mod
 * @version $Id: functions_delete.php,v 1.1 2005/11/05 12:23:33 acydburn Exp $
 * @copyright (c) 2002 Meik Sievertsen
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *
 */

use phpBB2\Sync;

/**
 * All Attachment Functions processing the Deletion Process
 */

/**
 * Delete Attachment(s) from post(s) (intern)
 *
 * @param int $post_id_array
 * @param int $attach_id_array
 * @param int $page
 * @param int $user_id
 *
 * @throws \Dibi\Exception
 */
function delete_attachment($post_id_array = 0, $attach_id_array = 0, $page = 0, $user_id = 0)
{
    // Generate Array, if it's not an array
    if ($post_id_array === 0 && $attach_id_array === 0 && $page === 0) {
        return;
    }

    if ($post_id_array === 0 && $attach_id_array !== 0) {
        if (!is_array($attach_id_array)) {
            if (mb_strstr($attach_id_array, ', ')) {
                $attach_id_array = explode(', ', $attach_id_array);
            } else if (mb_strstr($attach_id_array, ',')) {
                $attach_id_array = explode(',', $attach_id_array);
            } else {
                $attach_id_array = [(int)$attach_id_array];
            }
        }

        // Get the post_ids to fill the array
        $p_id = $page === PAGE_PRIVMSGS ? 'privmsgs_id' : 'post_id';

        $post_id_array = dibi::select($p_id)
            ->from(Tables::ATTACH_ATTACHMENT_TABLE)
            ->where('[attach_id] IN %in', $attach_id_array)
            ->groupBy($p_id)
            ->fetchPairs(null, $p_id);
    }

    if (!is_array($post_id_array)) {
        if (trim($post_id_array) === '') {
            return;
        }

        if (mb_strstr($post_id_array, ', ')) {
            $post_id_array = explode(', ', $post_id_array);
        } elseif (mb_strstr($post_id_array, ',')) {
            $post_id_array = explode(',', $post_id_array);
        } else {
            $post_id_array = [(int) $post_id_array];
        }
    }

    if (!count($post_id_array)) {
        return;
    }

    // First of all, determine the post id and attach_id
    if ($attach_id_array === 0) {
        // Get the attach_ids to fill the array
        if ($page === PAGE_PRIVMSGS) {
            $attach_id_array = dibi::select('attach_id')
                ->from(Tables::ATTACH_ATTACHMENT_TABLE)
                ->where('[privmsgs_id] IN %in', $post_id_array)
                ->fetchPairs(null, 'attach_id');
        } else {
            $attach_id_array = dibi::select('attach_id')
                ->from(Tables::ATTACH_ATTACHMENT_TABLE)
                ->where('[post_id] IN %in', $post_id_array)
                ->fetchPairs(null, 'attach_id');
        }
    }

    if (!is_array($attach_id_array)) {
        if (mb_strstr($attach_id_array, ', ')) {
            $attach_id_array = explode(', ', $attach_id_array);
        } else if (mb_strstr($attach_id_array, ',')) {
            $attach_id_array = explode(',', $attach_id_array);
        } else {
            $attach_id_array = [(int)$attach_id_array];
        }
    }

    if (!count($attach_id_array)) {
        return;
    }

    if ($page === PAGE_PRIVMSGS) {
        $sql_id = 'privmsgs_id';
        if ($user_id) {
            $post_id_array_2 = [];

            $rows = dibi::select(['privmsgs_id', 'privmsgs_type', 'privmsgs_to_userid', 'privmsgs_from_userid'])
                ->from(Tables::PRIVATE_MESSAGE_TABLE)
                ->where('[privmsgs_id] IN %in', $post_id_array)
                ->fetchAll();

            foreach ($rows as $row) {
                $privmsgs_type = $row->privmsgs_type;

                if ($privmsgs_type === PRIVMSGS_READ_MAIL || $privmsgs_type === PRIVMSGS_NEW_MAIL || $privmsgs_type === PRIVMSGS_UNREAD_MAIL) {
                    if ($row->privmsgs_to_userid === $user_id) {
                        $post_id_array_2[] = $row->privmsgs_id;
                    }
                } else if ($privmsgs_type === PRIVMSGS_SENT_MAIL) {
                    if ($row->privmsgs_from_userid === $user_id) {
                        $post_id_array_2[] = $row->privmsgs_id;
                    }
                } else if ($privmsgs_type === PRIVMSGS_SAVED_OUT_MAIL) {
                    if ($row->privmsgs_from_userid === $user_id) {
                        $post_id_array_2[] = $row->privmsgs_id;
                    }
                } else if ($privmsgs_type === PRIVMSGS_SAVED_IN_MAIL) {
                    if ($row->privmsgs_to_userid === $user_id) {
                        $post_id_array_2[] = $row->privmsgs_id;
                    }
                }
            }
            $post_id_array = $post_id_array_2;
        }
    } else {
        $sql_id = 'post_id';
    }

    if (count($post_id_array) && count($attach_id_array)) {
        dibi::delete(Tables::ATTACH_ATTACHMENT_TABLE)
            ->where('[attach_id] IN %in', $attach_id_array)
            ->where('%n IN %in', $sql_id, $post_id_array)
            ->execute();

        foreach ($attach_id_array as $attachId) {
            $check = dibi::select('attach_id')
                ->from(Tables::ATTACH_ATTACHMENT_TABLE)
                ->where('[attach_id] = %i', $attachId)
                ->fetch();

            if (!$check) {
                $attachments = dibi::select(['attach_id', 'physical_filename', 'thumbnail'])
                    ->from(Tables::ATTACH_ATTACHMENTS_DESC_TABLE)
                    ->where('[attach_id] = %i', $attachId)
                    ->fetchAll();

                foreach ($attachments as $attachment) {
                    unlink_attach($attachment->physical_filename);

                    if ((int)$attachment->thumbnail === 1) {
                        unlink_attach($attachment->physical_filename, MODE_THUMBNAIL);
                    }

                    dibi::delete(Tables::ATTACH_ATTACHMENTS_DESC_TABLE)
                        ->where('[attach_id] = %i', $attachment->attach_id)
                        ->execute();
                }
            }
        }
    }

    // Now Sync the Topic/PM
    if ($page === PAGE_PRIVMSGS) {
        foreach ($post_id_array as $postId) {
            $res = dibi::select('attach_id')
                ->from(Tables::ATTACH_ATTACHMENT_TABLE)
                ->where('[privmsgs_id] = %i', $postId)
                ->fetch();

            if (!$res) {
                dibi::update(Tables::PRIVATE_MESSAGE_TABLE, ['privmsgs_attachment' => 0])
                    ->where('[privmsgs_id] = %i', $postId)
                    ->execute();
            }
        }
    } else {
        if (count($post_id_array)) {
            $topics = dibi::select('topic_id')
                ->from(Tables::POSTS_TABLE)
                ->where('[post_id] IN %in', $post_id_array)
                ->groupBy('topic_id')
                ->fetchPairs(null, 'topic_id');

            foreach ($topics as $topic) {
                Sync::attachTopic($topic);
            }
        }
    }
}

?>