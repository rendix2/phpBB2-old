<?php

use Nette\Caching\Cache;
use Nette\Utils\Random;

/**
 * Class PostHelper
 *
 * @author rendix2
 */
class PostHelper
{
    public static $htmlEntitiesMatch   = ['#&(?!(\#[0-9]+;))#', '#<#', '#>#', '#"#'];
    public static $htmlEntitiesReplace = ['&amp;', '&lt;', '&gt;', '&quot;'];

    public static $unHtmlSpecialCharsMatch   = ['#&gt;#', '#&lt;#', '#&quot;#', '#&amp;#'];
    public static $unHtmlSpecialCharsReplace = ['>', '<', '"', '&'];

    /**
    * This function will prepare a posted message for
    * entry into the database.
    **/
    public static function prepareMessage($message, $htmlOn, $bbcodeOn, $smileOn, $bbcode_uid = 0)
    {
        //
        // Clean up the message
        //
        $message = trim($message);

        if ($htmlOn) {
            // If HTML is on, we try to make it safe
            // This approach is quite agressive and anything that does not look like a valid tag
            // is going to get converted to HTML entities
            $message = stripslashes($message);
            $htmlMatch = '#<[^\w<]*(\w+)((?:"[^"]*"|\'[^\']*\'|[^<>\'"])+)?>#';
            $matches = [];

            $message_split = preg_split($htmlMatch, $message);
            preg_match_all($htmlMatch, $message, $matches);

            $message = '';

            foreach ($message_split as $part) {
                $tag = [array_shift($matches[0]), array_shift($matches[1]), array_shift($matches[2])];
                $message .= preg_replace(self::$htmlEntitiesMatch, self::$htmlEntitiesReplace, $part) . self::cleanHtml($tag);
            }

            $message = addslashes($message);
            $message = str_replace('&quot;', '\&quot;', $message);
        } else {
            $message = preg_replace(self::$htmlEntitiesMatch, self::$htmlEntitiesReplace, $message);
        }

        if ($bbcodeOn && $bbcode_uid !== '') {
            $message = bbencode_first_pass($message, $bbcode_uid);
        }

        return $message;
    }

    /**
     * @param string $message
     *
     * @return string|string[]|null
     */
    public static function unPrepareMessage($message)
    {
        return preg_replace(self::$unHtmlSpecialCharsMatch, self::$unHtmlSpecialCharsReplace, $message);
    }

    /**
     * Prepare a message for posting
     *
     * @param $mode
     * @param $postData
     * @param $bbcodeOn
     * @param $htmlOn
     * @param $smiliesOn
     * @param $errorMessage
     * @param $userName
     * @param $bbcode_uid
     * @param $subject
     * @param $message
     * @param $pollTitle
     * @param $pollOptions
     * @param $pollLength
     */
    public static function preparePost(
        &$mode,
        &$postData,
        &$bbcodeOn,
        &$htmlOn,
        &$smiliesOn,
        &$errorMessage,
        &$userName,
        &$bbcode_uid,
        &$subject,
        &$message,
        &$pollTitle,
        &$pollOptions,
        &$pollLength
    ) {
        global $board_config, $userdata, $lang;

        $pollOptionsCount = count($pollOptions);

        // Check username
        if (!empty($userName)) {
            $userName = phpbb_clean_username($userName);

            if (!$userdata['session_logged_in'] || ($userdata['session_logged_in'] && $userName !== $userdata['username'])) {
                $result = Validator::userName($userName, $lang, $userdata);

                if ($result['error']) {
                    $errorMessage .= !empty($errorMessage) ? '<br />' . $result['error_msg'] : $result['error_msg'];
                }
            } else {
                $userName = '';
            }
        }

        // Check subject
        if (!empty($subject)) {
            $subject = htmlspecialchars(trim($subject));
        } elseif ($mode === 'newtopic' || ($mode === 'editpost' && $postData['first_post'])) {
            $errorMessage .= !empty($errorMessage) ? '<br />' . $lang['Empty_subject'] : $lang['Empty_subject'];
        }

        // Check message
        if (!empty($message)) {
            $bbcode_uid = $bbcodeOn ? Random::generate(BBCODE_UID_LEN) : '';
            $message = self::prepareMessage(trim($message), $htmlOn, $bbcodeOn, $smiliesOn, $bbcode_uid);
        } elseif ($mode !== 'delete' && $mode !== 'poll_delete')  {
            $errorMessage .= !empty($errorMessage) ? '<br />' . $lang['Empty_message'] : $lang['Empty_message'];
        }

        //
        // Handle poll stuff
        //
        if ($mode === 'newtopic' || ($mode === 'editpost' && $postData['first_post'])) {
            $pollLength = isset($pollLength) ? max(0, (int)$pollLength) : 0;

            if (!empty($pollTitle)) {
                $pollTitle = htmlspecialchars(trim($pollTitle));
            }

            // TODO LOOK
            if (!empty($pollOptions)) {
                $temporaryOptionsText = [];

                foreach ($pollOptions as $optionId => $optionText) {
                    $optionText = trim($optionText);

                    if (!empty($optionText)) {
                        $temporaryOptionsText[(int)$optionId] = htmlspecialchars($optionText);
                    }
                }
                $optionText = $temporaryOptionsText;

                if ($pollOptionsCount < 1) {
                    $errorMessage .= !empty($errorMessage) ? '<br />' . $lang['To_few_poll_options'] : $lang['To_few_poll_options'];
                } elseif ($pollOptionsCount > $board_config['max_poll_options']) {
                    $errorMessage .= !empty($errorMessage) ? '<br />' . $lang['To_many_poll_options'] : $lang['To_many_poll_options'];
                } elseif ($pollTitle === '') {
                    $errorMessage .= !empty($errorMessage) ? '<br />' . $lang['Empty_poll_title'] : $lang['Empty_poll_title'];
                }
            }
        }
    }

    /**
     * Post a new topic/reply/poll or edit existing post/poll
     *
     * @param $mode
     * @param $postData
     * @param $message
     * @param $meta
     * @param $forumId
     * @param $topicId
     * @param $postId
     * @param $pollId
     * @param $topicType
     * @param $bbcodeOn
     * @param $htmlOn
     * @param $smiliesOn
     * @param $attachSignature
     * @param $bbcode_uid
     * @param $postUsername
     * @param $postSubject
     * @param $postMessage
     * @param $pollTitle
     * @param $pollOptions
     * @param $pollLength
     *
     * @return bool
     * @throws \Dibi\Exception
     */
    public static function submitPost(
        $mode,
        &$postData,
        &$message,
        &$meta,
        &$forumId,
        &$topicId,
        &$postId,
        &$pollId,
        &$topicType,
        &$bbcodeOn,
        &$htmlOn,
        &$smiliesOn,
        &$attachSignature,
        &$bbcode_uid,
        $postUsername,
        $postSubject,
        $postMessage,
        $pollTitle,
        &$pollOptions,
        &$pollLength
    ) {
        global $board_config, $lang;
        global $userdata, $user_ip;

        $pollOptionsCount = count($pollOptions);

        $currentTime = time();

        if ($mode === 'newtopic' || $mode === 'reply' || $mode === 'editpost') {
            //
            // Flood control
            //
            $maxPostTime = dibi::select('MAX(post_time)')
                ->as('last_post_time')
                ->from(Tables::POSTS_TABLE);

            if ($userdata['user_id'] === ANONYMOUS) {
                $maxPostTime->where('[poster_ip] = %s', $user_ip);
            } else {
                $maxPostTime->where('[poster_id] = %i', $userdata['user_id']);
            }

            $maxPostTime = $maxPostTime->fetchSingle();

            if ($maxPostTime && (int)$maxPostTime > 0 && ($currentTime - $maxPostTime) < (int)$board_config['flood_interval']) {
                message_die(GENERAL_MESSAGE, $lang['Flood_Error']);
            }
        }

        if ($mode === 'editpost') {
            SearchHelper::removeSearchPost([$postId]);
        }

        if ($mode === 'newtopic' || ($mode === 'editpost' && $postData['first_post'])) {
            $topicVote = !empty($pollTitle) && $pollOptionsCount >= 2 ? 1 : 0;

            if ($mode !== 'editpost') {
                $insertData = [
                    'topic_title'  => $postSubject,
                    'topic_poster' => $userdata['user_id'],
                    'topic_time'   => $currentTime,
                    'forum_id'     => $forumId,
                    'topic_status' => TOPIC_UNLOCKED,
                    'topic_type'   => $topicType,
                    'topic_vote'   => $topicVote
                ];

                $topicId = dibi::insert(Tables::TOPICS_TABLE, $insertData)->execute(dibi::IDENTIFIER);
            } else {
                $updateData = [
                    'topic_title' => $postSubject,
                    'topic_type' => $topicType
                ];

                if ($postData['edit_vote'] || !empty($pollTitle)) {
                    $updateData['topic_vote'] = $topicVote;
                }

                dibi::update(Tables::TOPICS_TABLE, $updateData)
                    ->where('[topic_id] = %i', $topicId)
                    ->execute();
            }
        }

        if ($mode === 'editpost') {
            $updateData = [
                'post_username'  => $postUsername,
                'enable_bbcode'  => $bbcodeOn,
                'enable_html'    => $htmlOn,
                'enable_smilies' => $smiliesOn,
                'enable_sig'     => $attachSignature
            ];

            if ($mode === 'editpost' && !$postData['last_post'] && $postData['poster_post']) {
                $updateData['post_edit_time'] = $currentTime;
                $updateData['post_edit_count%sql'] = 'post_edit_count + 1';
            }

            dibi::update(Tables::POSTS_TABLE, $updateData)
                ->where('[post_id] = %i', $postId)
                ->execute();

            $updateData = [
                'post_text'    => $postMessage,
                'bbcode_uid'   => $bbcode_uid,
                'post_subject' => $postSubject
            ];

            dibi::update(Tables::POSTS_TEXT_TABLE, $updateData)
                ->where('[post_id] = %i', $postId)
                ->execute();
        } else {
            $insertData = [
                'topic_id'       => $topicId,
                'forum_id'       => $forumId,
                'poster_id'      => $userdata['user_id'],
                'post_username'  => $postUsername,
                'post_time'      => $currentTime,
                'poster_ip'      => $user_ip,
                'enable_bbcode'  => $bbcodeOn,
                'enable_html'    => $htmlOn,
                'enable_smilies' => $smiliesOn,
                'enable_sig'     => $attachSignature
            ];

            $postId = dibi::insert(Tables::POSTS_TABLE, $insertData)->execute(dibi::IDENTIFIER);

            $insertData = [
                'post_id'      => $postId,
                'post_subject' => $postSubject,
                'bbcode_uid'   => $bbcode_uid,
                'post_text'    => $postMessage
            ];

            dibi::insert(Tables::POSTS_TEXT_TABLE, $insertData)->execute();
        }

        SearchHelper::addSearchWords('single', $postId, stripslashes($postMessage), stripslashes($postSubject));

        //
        // Add poll
        //
        if (($mode === 'newtopic' || ($mode === 'editpost' && $postData['edit_poll'])) && !empty($pollTitle) && $pollOptionsCount >= 2) {
            if ($postData['has_poll']) {
                $updateData = [
                    'vote_text' => $pollTitle,
                    'vote_length' => $pollLength * 86400,
                ];

                dibi::update(Tables::VOTE_DESC_TABLE, $updateData)
                    ->where('[topic_id] = %i', $topicId)
                    ->execute();
            } else {
                $insertData = [
                    'topic_id' => $topicId,
                    'vote_text' => $pollTitle,
                    'vote_start' => $currentTime,
                    'vote_length' => $pollLength * 86400
                ];

                $pollId = dibi::insert(Tables::VOTE_DESC_TABLE, $insertData)->execute(dibi::IDENTIFIER);
            }

            $deleteOptionSql = [];
            $oldPollResult = [];

            if ($mode === 'editpost' && $postData['has_poll']) {
                $votes = dibi::select(['vote_option_id', 'vote_result'])
                    ->from(Tables::VOTE_RESULTS_TABLE)
                    ->where('[vote_id] = %i', $pollId)
                    ->orderBy('vote_option_id', dibi::ASC)
                    ->fetchPairs('vote_option_id', 'vote_result');

                foreach ($votes as $voteOptionId => $voteResult) {
                    $oldPollResult[$voteOptionId] = $voteResult;

                    if (!isset($pollOptions[$voteOptionId])) {
                        $deleteOptionSql[] = $voteOptionId;
                    }
                }
            }

            $pollOptionId = 1;

            foreach ($pollOptions as $optionId => $optionText) {
                if (!empty($optionText)) {
                    $optionText = str_replace("\'", "''", htmlspecialchars($optionText));
                    $pollResult = $mode === 'editpost' && isset($oldPollResult[$optionId]) ? $oldPollResult[$optionId] : 0;

                    if ($mode !== 'editpost' || !isset($oldPollResult[$optionId])) {
                        $insertData = [
                            'vote_id'          => $pollId,
                            'vote_option_id'   => $pollOptionId,
                            'vote_option_text' => $optionText,
                            'vote_result'      => $pollResult
                        ];

                        dibi::insert(Tables::VOTE_RESULTS_TABLE, $insertData)->execute();
                    } else {
                        $updateData = [
                            'vote_option_text' => $optionText,
                            'vote_result'      => $pollResult
                        ];

                        dibi::update(Tables::VOTE_RESULTS_TABLE, $updateData)
                            ->where('[vote_option_id] = %i', $optionId)
                            ->where('[vote_id] = %i', $pollId)
                            ->execute();
                    }

                    $pollOptionId++;
                }
            }

            if (count($deleteOptionSql)) {
                dibi::delete(Tables::VOTE_RESULTS_TABLE)
                    ->where('[vote_option_id] IN %in', $deleteOptionSql)
                    ->where('[vote_id] = %i', $pollId)
                    ->execute();
            }
        }

        $meta = '<meta http-equiv="refresh" content="3;url=' . Session::appendSid('viewtopic.php?' . POST_POST_URL . '=' . $postId) . '#' . $postId . '">';
        $message = $lang['Stored'] . '<br /><br />' . sprintf($lang['Click_view_message'], '<a href="' . Session::appendSid('viewtopic.php?' . POST_POST_URL . '=' . $postId) . '#' . $postId . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_forum'], '<a href="' . Session::appendSid('viewforum.php?' . POST_FORUM_URL . "=$forumId") . '">', '</a>');

        return false;
    }

    /**
     * Update post stats and details
     *
     * @param string $mode
     * @param array  $postData
     * @param int    $forumId
     * @param int    $topicId
     * @param int    $postId
     * @param int    $userId
     *
     * @throws \Dibi\Exception
     */
    public static function updatePostStats(&$mode, array &$postData, &$forumId, &$topicId, &$postId, &$userId)
    {
        $sign = $mode === 'delete' ? '- 1' : '+ 1';

        $forumUpdateSql = ['forum_posts%sql' => 'forum_posts ' . $sign];
        $topicUpdateSql = [];

        if ($mode === 'delete') {
            if ($postData['last_post']) {
                if ($postData['first_post']) {
                    $forumUpdateSql['forum_topics%sql'] = 'forum_topics - 1';
                } else {
                    $topicUpdateSql['topic_replies%sql'] = 'topic_replies - 1';

                    $lastPostId = dibi::select('MAX(post_id)')
                        ->as('last_post_id')
                        ->from(Tables::POSTS_TABLE)
                        ->where('[topic_id] = %i', $topicId)
                        ->fetchSingle();

                    if ($lastPostId === false) {
                        message_die(GENERAL_ERROR, 'Error in deleting post');
                    }

                    $topicUpdateSql['topic_last_post_id'] = $lastPostId;
                }

                if ($postData['last_topic']) {
                    $lastPostId = dibi::select('MAX(post_id)')
                        ->as('last_post_id')
                        ->from(Tables::POSTS_TABLE)
                        ->where('[forum_id] = %i', $forumId)
                        ->fetchSingle();

                    if ($lastPostId) {
                        $forumUpdateSql['forum_last_post_id'] = $lastPostId;
                    } else {
                        $forumUpdateSql['forum_last_post_id'] = 0;
                    }
                }
            } elseif ($postData['first_post']) {
                $firstPostId = dibi::select('MIN(post_id)')
                    ->as('first_post_id')
                    ->from(Tables::POSTS_TABLE)
                    ->where('[topic_id] = %i', $topicId)
                    ->fetchSingle();

                if ($firstPostId) {
                    $topicUpdateSql['topic_replies%sql'] = 'topic_replies - 1';
                    $topicUpdateSql['topic_first_post_id%sql'] = $firstPostId;
                }
            } else {
                $topicUpdateSql['topic_replies%sql'] = 'topic_replies - 1';
            }
        } elseif ($mode !== 'poll_delete') {
            $forumUpdateSql['forum_last_post_id'] = $postId;

            if ($mode === 'newtopic') {
                $forumUpdateSql['forum_topics%sql'] = 'forum_topics ' . $sign;
            }

            $topicUpdateSql['topic_last_post_id'] = $postId;

            if ($mode === 'reply') {
                $topicUpdateSql['topic_replies%sql'] = 'topic_replies ' . $sign;
            } else {
                $topicUpdateSql['topic_first_post_id'] = $postId;
            }
        } else {
            $topicUpdateSql['topic_vote'] = 0;
        }

        if ($mode !== 'poll_delete') {
            dibi::update(Tables::FORUMS_TABLE, $forumUpdateSql)
                ->where('[forum_id] = %i', $forumId)
                ->execute();
        }

        if (count($topicUpdateSql)) {
            dibi::update(Tables::TOPICS_TABLE, $topicUpdateSql)
                ->where('[topic_id] = %i', $topicId)
                ->execute();
        }

        if ($mode !== 'poll_delete') {
            dibi::update(Tables::USERS_TABLE, ['user_posts%sql' => 'user_posts ' . $sign])
                ->where('[user_id] = %i', $userId)
                ->execute();
        }

        if ($mode === 'newtopic') {
            dibi::update(Tables::USERS_TABLE, ['user_topics%sql' => 'user_topics + 1'])
                ->where('[user_id] = %i', $userId)
                ->execute();
        }
    }

    /**
     * Delete a post/poll
     *
     * @param string $mode
     * @param array  $postData
     * @param string $message
     * @param string $meta
     * @param int    $forumId
     * @param int    $topicId
     * @param int    $postId
     * @param int    $pollId
     *
     * @throws \Dibi\Exception
     */
    public static function deletePost(
        $mode,
        array &$postData,
        &$message,
        &$meta,
        &$forumId,
        &$topicId,
        &$postId,
        &$pollId
    ) {
        global $lang;
        global $container;

        if ($mode !== 'poll_delete') {
            dibi::delete(Tables::POSTS_TABLE)
                ->where('[post_id] = %i', $postId)
                ->execute();

            dibi::delete(Tables::POSTS_TEXT_TABLE)
                ->where('[post_id] = %i', $postId)
                ->execute();

            if ($postData['last_post'] && $postData['first_post']) {
                dibi::delete(Tables::TOPICS_TABLE)
                    ->where('[topic_id] = %i OR [topic_moved_id] = %i', $topicId, $topicId)
                    ->execute();

                dibi::delete(Tables::TOPICS_WATCH_TABLE)
                    ->where('[topic_id] = %i', $topicId)
                    ->execute();

                dibi::update(Tables::USERS_TABLE, ['user_topics%sql' => 'user_topics - 1'])
                    ->where('[user_id] = %i', $postData['poster_id'])
                    ->execute();

                $usersManager = $container->getService('UsersManager');
                $thanksManager = $container->getService('ThanksManager');
                $forumsManager = $container->getService('ForumsManager');

                $thanks = $thanksManager->getByTopicId($topicId);
                $usersToUpdate = [];

                foreach ($thanks as $thank) {
                    $usersToUpdate[] = $thank->user_id;
                }

                $usersManager->updateByPrimarys($usersToUpdate, ['user_thanks%sql' => 'user_thanks - 1']);
                $forumsManager->updateByPrimary($forumId, ['forum_thanks%sql' => 'forum_thanks - ' . count($usersToUpdate)]);
                $thanksManager->deleteByTopicId($topicId);
            }

            SearchHelper::removeSearchPost([$postId]);
        }

        if ($mode === 'poll_delete' || ($mode === 'delete' && $postData['first_post'] && $postData['last_post']) && $postData['has_poll'] && $postData['edit_poll']) {
            dibi::delete(Tables::VOTE_DESC_TABLE)
                ->where('[topic_id] = %i', $topicId)
                ->execute();

            dibi::delete(Tables::VOTE_RESULTS_TABLE)
                ->where('[vote_id] = %i', $pollId)
                ->execute();

            dibi::delete(Tables::VOTE_USERS_TABLE)
                ->where('[vote_id] = %i', $pollId)
                ->execute();
        }

        if ($mode === 'delete' && $postData['first_post'] && $postData['last_post']) {
            $meta = '<meta http-equiv="refresh" content="3;url=' . Session::appendSid('viewforum.php?' . POST_FORUM_URL . '=' . $forumId) . '">';
            $message = $lang['Deleted'];
        } else {
            $meta = '<meta http-equiv="refresh" content="3;url=' . Session::appendSid('viewtopic.php?' . POST_TOPIC_URL . '=' . $topicId) . '">';
            $message = (($mode === 'poll_delete') ? $lang['Poll_delete'] : $lang['Deleted']) . '<br /><br />' . sprintf($lang['Click_return_topic'], '<a href="' . Session::appendSid('viewtopic.php?' . POST_TOPIC_URL . "=$topicId") . '">', '</a>');
        }

        $message .=  '<br /><br />' . sprintf($lang['Click_return_forum'], '<a href="' . Session::appendSid('viewforum.php?' . POST_FORUM_URL . "=$forumId") . '">', '</a>');
    }

    /**
     * Handle user notification on new post
     *
     * @param string $mode
     * @param        $postData
     * @param string $topicTitle
     * @param int    $forumId
     * @param int    $topicId
     * @param int    $postId
     * @param bool   $notifyUser
     *
     * @throws \Dibi\Exception
     */
    public static function userNotification(
        $mode,
        &$postData,
        &$topicTitle,
        &$forumId,
        &$topicId,
        &$postId,
        &$notifyUser
    ) {
        global $board_config, $lang;
        global $userdata;

        if ($mode === 'delete') {
            return;
        }

        if ($mode === 'reply') {
            $userIds = dibi::select('ban_user_id')
                ->from(Tables::BAN_LIST_TABLE)
                ->fetchPairs(null, 'ban_user_id');

            $userNotId = array_merge([$userdata['user_id']], [ANONYMOUS], $userIds);

            $users = dibi::select(['u.user_id', 'u.user_email', 'u.user_lang'])
                ->from(Tables::TOPICS_WATCH_TABLE)
                ->as('tw')
                ->innerJoin(Tables::USERS_TABLE)
                ->as('u')
                ->on('[u.user_id] = [tw.user_id]')
                ->where('[tw.topic_id] = %i', $topicId)
                ->where('[tw.user_id] NOT IN %in', $userNotId)
                ->where('[tw.notify_status] = %i', TOPIC_WATCH_UN_NOTIFIED)
                ->fetchAll();

            $updateWatchedSql = [];
            $bcc_list_ary = [];

            if (count($users)) {
                // Sixty second limit
                @set_time_limit(60);

                foreach ($users as $user) {
                    if ($user->user_email !== '') {
                        $bcc_list_ary[$user->user_lang][] = $user->user_email;
                    }

                    $updateWatchedSql[] = $user->user_id;
                }

                //
                // Let's do some checking to make sure that mass mail functions
                // are working in win32 versions of php.
                //
                if (preg_match('/[c-z]:\\\.*/i', getenv('PATH')) && !$board_config['smtp_delivery']) {
                    // We are running on windows, force delivery to use our smtp functions
                    // since php's are broken by default
                    $board_config['smtp_delivery'] = 1;
                    $board_config['smtp_host'] = @ini_get('SMTP');
                }

                if (count($bcc_list_ary)) {
                    $emailer = new Emailer($board_config['smtp_delivery']);

                    $scriptName = preg_replace('/^\/?(.*?)\/?$/', '\1', trim($board_config['script_path']));
                    $scriptName = $scriptName !== '' ? $scriptName . '/viewtopic.php' : 'viewtopic.php';
                    $serverName = trim($board_config['server_name']);
                    $serverProtocol = $board_config['cookie_secure'] ? 'https://' : 'http://';
                    $serverPort = $board_config['server_port'] !== 80 ? ':' . trim($board_config['server_port']) . '/' : '/';

                    $origWords = [];
                    $replacementWords = [];
                    obtain_word_list($origWords, $replacementWords);

                    $emailer->setFrom($board_config['board_email']);
                    $emailer->setReplyTo($board_config['board_email']);

                    $topicTitle = count($origWords) ? preg_replace($origWords, $replacementWords, self::unPrepareMessage($topicTitle)) : self::unPrepareMessage($topicTitle);

                    foreach ($bcc_list_ary as $userLang => $bccList) {
                        $emailer->useTemplate('topic_notify', $userLang);

                        foreach ($bccList as $bccValue) {
                            $emailer->addBcc($bccValue);
                        }

                        // The Topic_reply_notification lang string below will be used
                        // if for some reason the mail template subject cannot be read
                        // ... note it will not necessarily be in the posters own language!
                        $emailer->setSubject($lang['Topic_reply_notification']);

                        // This is a nasty kludge to remove the username var ... till (if?)
                        // translators update their templates
                        $msg = preg_replace('#[ ]?{USERNAME}#', '', $emailer->getMsg());

                        $emailer->setMsg($msg);

                        $emailer->assignVars(
                            [
                                'EMAIL_SIG'   => !empty($board_config['board_email_sig']) ? str_replace('<br />', "\n", "-- \n" . $board_config['board_email_sig']) : '',
                                'SITENAME'    => $board_config['sitename'],
                                'TOPIC_TITLE' => $topicTitle,

                                'U_TOPIC'               => $serverProtocol . $serverName . $serverPort . $scriptName . '?' . POST_POST_URL . "=$postId#$postId",
                                'U_STOP_WATCHING_TOPIC' => $serverProtocol . $serverName . $serverPort . $scriptName . '?' . POST_TOPIC_URL . "=$topicId&unwatch=topic"
                            ]
                        );

                        $emailer->send();
                        $emailer->reset();
                    }
                }
            }

            if (count($updateWatchedSql)) {
                dibi::update(Tables::TOPICS_WATCH_TABLE, ['notify_status' => TOPIC_WATCH_NOTIFIED])
                    ->where('[topic_id] = %i', $topicId)
                    ->where('[user_id] IN %in', $updateWatchedSql)
                    ->execute();
            }
        }

        $topicWatch = dibi::select('topic_id')
            ->from(Tables::TOPICS_WATCH_TABLE)
            ->where('[topic_id] = %i', $topicId)
            ->where('[user_id] = %i', $userdata['user_id'])
            ->fetchSingle();

        if (!$notifyUser && $topicWatch) {
            dibi::delete(Tables::TOPICS_WATCH_TABLE)
                ->where('[topic_id] = %i', $topicId)
                ->where('[user_id] = %i', $userdata['user_id'])
                ->execute();
        } elseif ($notifyUser && $topicWatch === false) {
            $insertData = [
                'user_id'       => $userdata['user_id'],
                'topic_id'      => $topicId,
                'notify_status' => 0
            ];

            dibi::insert(Tables::TOPICS_WATCH_TABLE, $insertData)
                ->execute();
        }
    }

    /**
     * Fill smiley templates (or just the variables) with smileys
     * Either in a window or inline
     *
     * @param string $mode
     * @param int    $pageId
     *
     * @throws Throwable
     */
    public static function generateSmileys($mode, $pageId)
    {
        /**
         * @var BaseTemplate $template
         */
        global $template;

        global $board_config, $lang, $images, $theme;
        global $userdata;
        global $storage;

        $inlineColumns = 4;
        $inlineRows = 5;
        $windowColumns = 8;
        $sep = DIRECTORY_SEPARATOR;

        if ($mode === 'window') {
            $userdata = init_userprefs($pageId);

            $gen_simple_header = true;

            PageHelper::header($template, $userdata, $board_config, $lang, $images,  $theme, $lang['Emoticons'], $gen_simple_header);

            $template->setFileNames(['smiliesbody' => 'posting_smilies.tpl']);
        }

        $cache = new Cache($storage, Tables::SMILEYS_TABLE);

        $key = Tables::SMILEYS_TABLE . '_ordered_by_smilies_id';

        $smileysCaches = $cache->load($key);

        if ($smileysCaches !== null) {
            $smilies = $smileysCaches;
        } else {
            $smilies = dibi::select(['emoticon', 'code', 'smile_url'])
                ->from(Tables::SMILEYS_TABLE)
                ->orderBy('smilies_id')
                ->fetchAll();

            $cache->save($key, $smilies);
        }

        if (count($smilies)) {
            $numSmilies = 0;
            $rowSet = [];

            foreach ($smilies as $smiley) {
                if (empty($rowSet[$smiley->smile_url])) {
                    $rowSet[$smiley->smile_url]['code'] = str_replace("'", "\\'", str_replace('\\', '\\\\', $smiley->code));
                    $rowSet[$smiley->smile_url]['emoticon'] = $smiley->emoticon;
                    $numSmilies++;
                }
            }

            if ($numSmilies) {
                $isModInline = $mode === 'inline';

                $smileysCount = $isModInline ? min(19, $numSmilies) : $numSmilies;
                $smileysSplitRow = $isModInline ? $inlineColumns - 1 : $windowColumns - 1;

                $s_colspan = 0;
                $row = 0;
                $col = 0;

                foreach ($rowSet as $smile_url => $data) {
                    if (!$col) {
                        $template->assignBlockVars('smilies_row', []);
                    }

                    $template->assignBlockVars('smilies_row.smilies_col',
                        [
                            'SMILEY_CODE' => $data['code'],
                            'SMILEY_IMG'  => $board_config['smilies_path'] . $sep . $smile_url,
                            'SMILEY_DESC' => $data['emoticon']
                        ]
                    );

                    $s_colspan = max($s_colspan, $col + 1);

                    if ($col === $smileysSplitRow) {
                        if ($isModInline && $row === $inlineRows - 1) {
                            break;
                        }

                        $col = 0;
                        $row++;
                    } else {
                        $col++;
                    }
                }

                if ($isModInline && $numSmilies > $inlineRows * $inlineColumns) {
                    $template->assignBlockVars('switch_smilies_extra', []);

                    $template->assignVars(
                        [
                            'L_MORE_SMILIES' => $lang['More_emoticons'],
                            'U_MORE_SMILIES' => Session::appendSid('posting.php?mode=smilies')
                        ]
                    );
                }

                $template->assignVars(
                    [
                        'L_EMOTICONS'       => $lang['Emoticons'],
                        'L_CLOSE_WINDOW'    => $lang['Close_window'],
                        'S_SMILIES_COLSPAN' => $s_colspan
                    ]
                );
            }
        }

        if ($mode === 'window') {
            $template->pparse('smiliesbody');

            PageHelper::footer($template, $userdata, $lang, $gen_simple_header);
        }
    }

    /**
     * Called from within prepare_message to clean included HTML tags if HTML is
     * turned on for that post
     *
     * @param array $tag Matching text from the message to parse
     *
     * @return string
     */
    public static function cleanHtml($tag)
    {
        global $board_config;

        if (empty($tag[0])) {
            return '';
        }

        $allowedHtmlTags = preg_split('/, */', mb_strtolower($board_config['allow_html_tags']));
        $disallowedAttributes = '/^(?:style|on)/i';

        // Check if this is an end tag
        preg_match('/<[^\w\/]*\/[\W]*(\w+)/', $tag[0], $matches);
        if (count($matches)) {
            if (in_array(mb_strtolower($matches[1]), $allowedHtmlTags, true)) {
                return '</' . $matches[1] . '>';
            } else {
                return htmlspecialchars('</' . $matches[1] . '>');
            }
        }

        // Check if this is an allowed tag
        if (in_array(mb_strtolower($tag[1]), $allowedHtmlTags, true)) {
            $attributes = '';

            if (!empty($tag[2])) {
                preg_match_all('/[\W]*?(\w+)[\W]*?=[\W]*?(["\'])((?:(?!\2).)*)\2/', $tag[2], $test);
                $countTestZero = count($test[0]);

                for ($i = 0; $i < $countTestZero; $i++) {
                    if (preg_match($disallowedAttributes, $test[1][$i])) {
                        continue;
                    }
                    $attributes .= ' ' . $test[1][$i] . '=' . $test[2][$i] . str_replace(['[', ']'], ['&#91;', '&#93;'], htmlspecialchars($test[3][$i])) . $test[2][$i];
                }
            }

            return '<' . $tag[1] . $attributes . '>';
        }
        // Finally, this is not an allowed tag so strip all the attibutes and escape it
        else {
            return htmlspecialchars('<' .   $tag[1] . '>');
        }
    }
}
