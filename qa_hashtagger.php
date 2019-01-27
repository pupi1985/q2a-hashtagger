<?php

class qa_hashtagger
{
    const MENTION_TYPE_HASHTAGS = 'hashtags';
    const MENTION_TYPE_USERNAMES = 'usernames';

    /**
     * The list of hashtags found in contents of Questions, Comments or Answers
     *
     * @var array
     */
    private static $hashtags;

    /**
     * The list of users which was mentioned in Questions, Comments or Answers
     *
     * @var array
     */
    private static $userids;

    /**
     * Details about notification message
     *
     * @var array
     */
    private static $notification;

    /**
     * Filter question
     *
     * @param array $question
     * @param array $errors
     * @param array $oldquestion
     */
    public function filter_question(&$question, &$errors, $oldquestion)
    {
        if ($errors) {
            return;
        }

        $this->set_notification($question, $oldquestion, 'Q');
        $this->init_filter($question, 'questions');
        $question = $this->set_question_tags($question);
    }

    /**
     * Filter answer
     *
     * @param array $answer
     * @param array $errors
     * @param array $question
     * @param array $oldanswer
     */
    public function filter_answer(&$answer, &$errors, $question, $oldanswer)
    {
        if ($errors) {
            return;
        }

        $this->set_notification($question, $oldanswer, 'A');
        $this->filter_question_child($answer, $question, 'answers');
    }

    /**
     * Filter comment
     *
     * @param array $comment
     * @param array $errors
     * @param array $question
     * @param array $parent
     * @param array $oldcomment
     */
    public function filter_comment(&$comment, &$errors, $question, $parent, $oldcomment)
    {
        if ($errors) {
            return;
        }

        $this->set_notification($question, $oldcomment, 'C');
        $this->filter_question_child($comment, $question, 'comments');
    }

    /**
     * Notify users that they was mentioned
     * We need to process after
     *
     * @param string $event
     * @param int $userid
     * @param string|null $handle
     * @param int $cookieid
     * @param array $params
     */
    public function process_event($event, $userid, $handle, $cookieid, $params)
    {
        if (empty(self::$userids) && empty(self::$notification)) {
            return;
        }

        // For qa_send_notification()
        require_once QA_INCLUDE_DIR . 'app/emails.php';

        // For qa_db_event_create_not_entity()
        require_once QA_INCLUDE_DIR . 'db/events.php';

        if (self::$notification['question_id']) {
            if (!self::$notification['anchor_id']) {
                self::$notification['anchor_id'] = $params['postid'];
            }
        } else {
            self::$notification['question_id'] = isset($params['parentid']) ? $params['parentid'] : $params['postid'];
        }

        $subject = qa_lang('plugin_hashtagger/user_mentioned_title');
        $body = qa_lang('plugin_hashtagger/user_mentioned_body');

        $mentioned_url = qa_q_path(
            self::$notification['question_id'],
            self::$notification['question_title'],
            true,
            self::$notification['anchor_type'],
            self::$notification['anchor_id']
        );

        foreach (self::$userids as $userid) {
            /**
             * @todo - create an event for /updates/ page using qa_db_event_create_not_entity()
             *
             * At this moment we cannot do this, because qa_other_to_q_html_fields() does not accept
             * custom event types, thus on /updates/ page cannot shown suitable message for user
             *
             * The only thing to do this, is to override qa_other_to_q_html_fields() function
             * but it's too bad idea because it is necessary to copy the entire function
             */
            qa_send_notification($userid, null, null, $subject, $body, array(
                '^author_name' => self::$notification['auhtor_name'],
                '^question_title' => self::$notification['question_title'],
                '^mentioned_url' => $mentioned_url,
            ));
        }
    }

    /**
     * Configure notification variable
     *
     * @param array $question
     * @param array $oldrow
     * @param string $type
     */
    private function set_notification($question, $oldrow, $type)
    {
        if (qa_opt('plugin_hashtagger/notify_users')) {
            self::$notification = array(
                'auhtor_name' => empty($oldrow['handle']) ? qa_lang_html('main/anonymous') : $oldrow['handle'],
                'question_title' => $question['title'],
                'question_id' => isset($question['postid']) ? $question['postid'] : null,
                'anchor_type' => $type,
                'anchor_id' => isset($oldrow['postid']) ? $oldrow['postid'] : null,
            );
        }
    }

    /**
     * Set question tags
     *
     * @param array $question
     *
     * @return array
     */
    private function set_question_tags($question)
    {
        if (self::$hashtags) {
            if (isset($question['tags'])) {
                $new_tags = array_merge($question['tags'], self::$hashtags);
                $question['tags'] = array_unique($new_tags);
            } else {
                $question['tags'] = self::$hashtags;
            }
        }

        return $question;
    }

    /**
     * Filter objects submited to question (like answers or comments)
     *
     * @param array $row
     * @param array $old_question
     * @param string $type
     */
    private function filter_question_child(&$row, $old_question, $type)
    {
        $this->init_filter($row, $type);

        if (empty(self::$hashtags)) {
            return;
        }

        // For qa_tagstring_to_tags()
        require_once QA_INCLUDE_DIR . 'util/string.php';

        // For qa_post_set_content()
        require_once QA_INCLUDE_DIR . 'app/posts.php';

        $old_question['tags'] = qa_tagstring_to_tags($old_question['tags']);
        $new_question = $this->set_question_tags($old_question);

        if ($new_question['tags'] != $old_question['tags']) {
            qa_post_set_content(
                $new_question['postid'],
                $new_question['title'],
                $new_question['content'],
                $new_question['format'],
                $new_question['tags']
            );
        }
    }

    /**
     * We should to hide HTML links to prevent "double links"
     *
     * @param array $match
     *
     * @return string
     */
    private function hide_html_links($match)
    {
        $hash = base64_encode($match[1]);

        return "<b64>{$hash}</b64>";
    }

    /**
     * Unhide hidden HTML links
     *
     * @param array $match
     *
     * @return string
     */
    private function show_html_links($match)
    {
        return base64_decode($match[1]);
    }

    /**
     * Build tag link and set it for global variable
     *
     * @param array $match
     *
     * @return string
     */
    private static function build_tag_link($match)
    {
        require_once QA_INCLUDE_DIR . 'util/string.php';

        $tag = qa_strtolower($match['word']);

        $linkText = qa_opt('plugin_hashtagger/keep_hash_symbol') ? "#" : '';
        $linkText .= $tag;

        if (!in_array($tag, self::$hashtags)) {
            self::$hashtags[] = $tag;
        }

        $url = qa_path_html('tag/' . $tag);

        return sprintf('<a href="%s">%s</a>', $url, $linkText);
    }

    /**
     * Build link to user profile
     *
     * @param array $match
     *
     * @return string
     */
    private static function build_user_link($match)
    {
        $userid = qa_handle_to_userid($match['name']);
        if ($userid) {
            self::$userids[] = $userid;
            $url = qa_path_html("user/{$match['name']}");

            return "<a href='{$url}'>@{$match['name']}</a>";
        } else {
            // If user does not exists in DB the string is returned as is
            return $match[0];
        }
    }

    /**
     * Check if option is enabled and contains specified character
     *
     * @param string $mentionType
     * @param string $char
     * @param string $str
     *
     * @return bool
     */
    private function is_convertable($mentionType, $char, $str)
    {
        return qa_opt("plugin_hashtagger/convert_{$mentionType}") && strpos($str, $char) !== false;
    }

    /**
     * A minified version of preg_replace_callback() function
     *
     * @param string $rgxp
     * @param string $fn
     * @param string $str
     *
     * @return string
     */
    private function preg_call($rgxp, $fn, $str)
    {
        return preg_replace_callback($rgxp, array($this, $fn), $str);
    }

    /**
     * Filter content and tags of object
     *
     * @param array $row
     * @param string $type
     */
    private function init_filter(&$row, $type)
    {
        // We need to process only non-empty content
        if (empty($row['content']) || !qa_opt("plugin_hashtagger/filter_{$type}")) {
            return;
        }

        $convert_hashtags = $this->is_convertable(self::MENTION_TYPE_HASHTAGS, '#', $row['content']);
        $convert_usernames = $this->is_convertable(self::MENTION_TYPE_USERNAMES, '@', $row['content']);

        // Skip message if does not have any convertable options
        if (!$convert_hashtags && !$convert_usernames) {
            return;
        }

        // Redefine variables
        self::$hashtags = array();
        self::$userids = array();

        // Hide links
        if (stripos($row['content'], '</a>') !== false) {
            $row['content'] = $this->preg_call('%(<a.*?</a>)%i', 'hide_html_links', $row['content']);
        }

        // Convert hashtags
        if ($convert_hashtags) {
            $row['content'] = $this->preg_call('%#(?P<word>[\w\-]*?)#%u', 'build_tag_link', $row['content']);
        }

        // Convert usernames
        if ($convert_usernames) {
            $row['content'] = $this->preg_call('%@(?P<name>[\w\-]+)%u', 'build_user_link', $row['content']);
            $row['content'] = $this->preg_call('%@"(?P<name>.*?)"%u', 'build_user_link', $row['content']);
        }

        // Unhide links
        if (strpos($row['content'], '</b64>') !== false) {
            $row['content'] = $this->preg_call('%<b64>(.*?)</b64>%', 'show_html_links', $row['content']);
        }

        // Let's force the object to use HTML format if it has created links
        if (!empty(self::$hashtags) || !empty(self::$userids)) {
            $row['format'] = 'html';
        }
    }
}
