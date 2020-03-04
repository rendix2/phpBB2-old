<?php

use phpBB2\Sync;

/**
 * Class Prune
 *
 * @author rendix2
 */
class Prune
{
    /**
     * @param int  $forumId
     * @param int  $pruneDate timestamp
     * @param bool $pruneAll
     *
     * @return array
     * @throws \Dibi\Exception
     */
    public static function run($forumId, $pruneDate, $pruneAll = false)
    {
        $topics = dibi::select('topic_id')
            ->from(Tables::TOPICS_TABLE)
            ->where('topic_last_post_id = %i', 0)
            ->fetchAll();

        foreach ($topics as $topic) {
            Sync::oneTopic($topic->topic_id);
        }

        //
        // Those without polls and announcements ... unless told otherwise!
        //
        $topicQuery = dibi::select('t.topic_id')
            ->from(Tables::POSTS_TABLE)
            ->as('p')
            ->innerJoin(Tables::TOPICS_TABLE)
            ->as('t')
            ->on('p.post_id = t.topic_last_post_id')
            ->where('t.forum_id = %i', $forumId);

        if (!$pruneAll) {
            $topicQuery->where('[t.topic_vote] = %i', 0)
                ->where('t.topic_type <> %i', POST_ANNOUNCE);
        }

        if ($pruneDate) {
            $topicQuery->where('p.post_time < %i', $pruneDate);
        }

        $topicData = $topicQuery->fetchPairs(null, 'topic_id');

        if (count($topicData)) {
            $postIds = dibi::select('post_id')
                ->from(Tables::POSTS_TABLE)
                ->where('[forum_id] = %i', $forumId)
                ->where('[topic_id] IN %in', $topicData)
                ->fetchPairs(null, 'post_id');

            if (count($postIds)) {
                $userIds = dibi::select('poster_id')
                    ->from(Tables::POSTS_TABLE)
                    ->where('[post_id] IN %in', $postIds)
                    ->fetchPairs(null, 'user_id');

                $userCounts = [];

                foreach ($userIds as $userId) {
                    if (isset($userCounts[$userId])) {
                        $userCounts[$userId]++;
                    } else {
                        $userCounts[$userId] = 1;
                    }
                }

                foreach ($userCounts as $userId => $userCount) {
                    dibi::update(Tables::USERS_TABLE, ['user_posts%sql' => 'user_posts - ' . $userCount])
                        ->where('[user_id] = %i', $userId)
                        ->execute();
                }

                $topicAuthors = dibi::select('topic_poster')
                    ->select('COUNT(topic_id)')
                    ->as('topics')
                    ->from(Tables::TOPICS_TABLE)
                    ->where('[topic_id] IN %in', $topicData)
                    ->groupBy('topic_poster')
                    ->fetchAll();

                foreach ($topicAuthors as $author) {
                    dibi::update(Tables::USERS_TABLE, ['user_topics%sql' => 'user_topics - '. $author->topics])
                        ->where('[user_id] = %i', $author->topic_poster)
                        ->execute();
                }

                dibi::delete(Tables::TOPICS_WATCH_TABLE)
                    ->where('[topic_id] IN %in', $topicData)
                    ->execute();

                $prunedTopics = dibi::delete(Tables::TOPICS_TABLE)
                    ->where('[topic_id] IN %in', $topicData)
                    ->execute(dibi::AFFECTED_ROWS);

                $prunedPosts = dibi::delete(Tables::POSTS_TABLE)
                    ->where('[post_id] IN %in', $postIds)
                    ->execute(dibi::AFFECTED_ROWS);

                dibi::delete(Tables::POSTS_TEXT_TABLE)
                    ->where('[post_id] IN %in', $postIds)
                    ->execute(dibi::AFFECTED_ROWS);

                SearchHelper::removeSearchPost($postIds);

                delete_attachment($postIds);

                return ['topics' => $prunedTopics, 'posts' => $prunedPosts];
            }
        }

        return ['topics' => 0, 'posts' => 0];
    }

    /**
     * Function auto_prune(), this function will read the configuration data from
     * the auto_prune table and call the prune function with the necessary info.
     *
     * @param int $forumId
     *
     * @return bool
     * @throws \Dibi\Exception
     */
    public static function autoPrune($forumId = 0)
    {
        global $userdata;
        global $board_config;

        $prune = dibi::select('*')
            ->from(Tables::PRUNE_TABLE)
            ->where('[forum_id] = %i', $forumId)
            ->fetch();

        if (!$prune) {
            return false;
        }

        if ($prune->prune_freq && $prune->prune_days) {
            $userTimezone = isset($userdata['user_timezone']) ? $userdata['user_timezone'] : $board_config['board_timezone'];

            $timeZone = new DateTimeZone($userTimezone);

            $pruneDate = new DateTime();
            $pruneDate->setTimezone($timeZone);
            $pruneDate->sub(new DateInterval('P' . $prune->prune_days . 'D'))
                ->getTimestamp();

            $nextPrune = new DateTime();
            $nextPrune->setTimezone($timeZone);
            $nextPrune->add(new DateInterval('P' . $prune->prune_freq . 'D'))
                ->getTimestamp();

            self::run($forumId, $pruneDate);
            Sync::oneForum($forumId);

            dibi::update(Tables::FORUMS_TABLE, ['prune_next' => $nextPrune])
                ->where('[forum_id] = %i', $forumId)
                ->execute();
        }
    }

}
