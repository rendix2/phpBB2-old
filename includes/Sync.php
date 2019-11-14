<?php

namespace phpBB2;

use dibi;
use Dibi\Exception;
use Tables;

/**
 * Class Sync
 *
 * @author rendix2
 */
class Sync
{
    /**
     * sync all forums
     */
    public static function allForums()
    {
        $forums = dibi::select('forum_id')
            ->from(Tables::FORUMS_TABLE)
            ->fetchPairs(null, 'forum_id');

        foreach ($forums as $forum) {
            self::oneForum($forum);
        }
    }

    /**
     * @param int $forumId
     *
     * @throws Exception
     */
    public static function oneForum($forumId)
    {
        $row = dibi::select('MAX(post_id)')
            ->as('last_post')
            ->select('COUNT(post_id)')
            ->as('total')
            ->from(Tables::POSTS_TABLE)
            ->where('[forum_id] = %i', $forumId)
            ->fetch();

        if (!$row) {
            message_die(GENERAL_ERROR, 'Could not get posts by forum ID');
        }

        $lastPost = $row->last_post;
        $totalPosts = $row->total;

        $totalTopics = dibi::select('COUNT(topic_id)')
            ->as('total')
            ->from(Tables::TOPICS_TABLE)
            ->where('[forum_id] = %i', $forumId)
            ->fetchSingle();

        if ($totalTopics === false) {
            message_die(GENERAL_ERROR, 'Could not get topic count');
        }

        $forumsUpdateData = [
            'forum_last_post_id' => $lastPost,
            'forum_posts'        => $totalPosts,
            'forum_topics'       => $totalTopics
        ];

        dibi::update(Tables::FORUMS_TABLE, $forumsUpdateData)
            ->where('[forum_id] = %i', $forumId)
            ->execute();
    }

    /**
     *
     */
    public static function allTopics()
    {
        $topics = dibi::select('topic_id')
            ->from(Tables::TOPICS_TABLE)
            ->fetchPairs(null, 'topic_id');

        foreach ($topics as $topic) {
            self::oneTopic($topic);
        }
    }

    /**
     * @param int $topicId
     *
     * @throws Exception
     */
    public static function oneTopic($topicId)
    {
        $row = dibi::select('MAX(post_id)')
            ->as('last_post')
            ->select('MIN(post_id)')
            ->as('first_post')
            ->select('COUNT(post_id)')
            ->as('total_posts')
            ->from(Tables::POSTS_TABLE)
            ->where('[topic_id] = %i', $topicId)
            ->fetch();

        if ($row) {
            if ($row->total_posts) {
                // Correct the details of this topic
                $updateData = [
                    'topic_replies'       => $row->total_posts - 1,
                    'topic_first_post_id' => $row->first_post,
                    'topic_last_post_id'  => $row->last_post
                ];

                dibi::update(Tables::TOPICS_TABLE, $updateData)
                    ->where('[topic_id] = %i', $topicId)
                    ->execute();
            } else {
                // There are no replies to this topic
                // Check if it is a move stub
                $topicMovedId = dibi::select('topic_moved_id')
                    ->from(Tables::TOPICS_TABLE)
                    ->where('[topic_id] = %i', $topicId)
                    ->fetch();

                if ($topicMovedId && !$topicMovedId->topic_moved_id) {
                    dibi::delete(Tables::TOPICS_TABLE)
                        ->where('[topic_id] = %i', $topicId)
                        ->execute();

                }
            }

            self::attachTopic($topicId);
        }
    }

    /**
     * @param $topicId
     *
     * @throws Exception
     */
    public static function attachTopic($topicId)
    {
        if (!$topicId) {
            return;
        }

        $topicId = (int)$topicId;

        $post_ids = dibi::select('post_id')
            ->from(Tables::POSTS_TABLE)
            ->where('[topic_id] = %i', $topicId)
            ->groupBy('post_id')
            ->fetchPairs(null, 'post_id');

        if (!count($post_ids)) {
            return;
        }

        $checkAttach = dibi::select('attach_id')
            ->from(Tables::ATTACH_ATTACHMENT_TABLE)
            ->where('[post_id] IN %in', $post_ids)
            ->fetch();

        $set_id = $checkAttach === false ? 0 : 1;

        dibi::update(Tables::TOPICS_TABLE, ['topic_attachment' => $set_id])
            ->where('[topic_id] = %i', $topicId)
            ->execute();

        foreach ($post_ids as $post_id) {
            $checkAttach = dibi::select('attach_id')
                ->from(Tables::ATTACH_ATTACHMENT_TABLE)
                ->where('[post_id] = %i', $post_id)
                ->fetch();

            $set_id = $checkAttach === false ? 0 : 1;

            dibi::update(Tables::POSTS_TABLE, ['post_attachment' => $set_id])
                ->where('[post_id] = %i', $post_id)
                ->execute();
        }
    }
}
