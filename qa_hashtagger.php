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
     * The list of tags to set to the question
     *
     * @var array
     */
    private static $questionTags;

    /** @var bool */
    private static $newTagsPresent = false;

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
        $this->init_filter($answer, 'answers');
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
        $this->init_filter($comment, 'comments');
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

        foreach (self::$userids as $userid => $link) {
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
        if (!empty(self::$questionTags)) {
            if (isset($question['tags'])) {
                $new_tags = array_merge($question['tags'], self::$questionTags);
                $question['tags'] = array_unique($new_tags);
            } else {
                $question['tags'] = self::$questionTags;
            }
        }

        return $question;
    }

    /**
     * We should hide HTML links to prevent "double links"
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
     * @param array $matches
     * @param boolean $htmlFormat
     *
     * @return string
     */
    private static function build_tag_link(array $matches, $htmlFormat)
    {
        require_once QA_INCLUDE_DIR . 'util/string.php';
        require_once QA_INCLUDE_DIR . 'db/post-create.php';

        $tag = qa_strtolower($matches['word']);

        if ($htmlFormat) {
            $tag = html_entity_decode($tag);
        }

        $linkText = qa_opt('plugin_hashtagger/keep_hash_symbol') ? "#" : '';
        $linkText .= $tag;

        $tagWord = qa_db_single_select(qa_db_tag_word_selectspec($tag));

        self::$questionTags[] = $tag;

        if (is_null($tagWord)) {
            $tagWord = qa_db_word_mapto_ids_add(array($tagWord));
        }

        $url = qa_html(qa_path_absolute('tag/' . $tag));

        if (!in_array($tag, self::$hashtags)) {
            self::$hashtags[$tagWord['wordid']] = $url;
        }

        return sprintf('${hashtagger-open-link-tag-%s}%s${hashtagger-close-link}', $tagWord['wordid'], $linkText);
    }

    /**
     * Build link to user profile
     *
     * @param array $match
     * @param boolean $htmlFormat
     *
     * @return string
     */
    private static function build_user_link(array $match, $htmlFormat)
    {
        $handle = $match['name'];

        if ($htmlFormat) {
            $handle = html_entity_decode($handle);
        }

        $userid = qa_handle_to_userid($handle);
        if ($userid) {
            $url = qa_html(qa_path_absolute('user/' . $handle));
            self::$userids[$userid] = $url;

            return sprintf('${hashtagger-open-link-user-%s}%s${hashtagger-close-link}', $userid, $handle);
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
        self::$questionTags = array();

        $htmlContent = $row['content'];
        $isInHtmlFormat = $row['format'] === 'html';

        // Hide links
        if (stripos($htmlContent, '</a>') !== false) {
            $htmlContent = $this->preg_call('%(<a.*?</a>)%i', 'hide_html_links', $htmlContent);
        }

        // Convert hashtags
        if ($convert_hashtags) {
            $htmlContent = $this->parseTags($isInHtmlFormat, $htmlContent);
        }

        // Convert usernames
        if ($convert_usernames) {
            $htmlContent = $this->parseUsers($isInHtmlFormat, $htmlContent);
        }

        // Unhide links
        if (strpos($htmlContent, '</b64>') !== false) {
            $htmlContent = $this->preg_call('%<b64>(.*?)</b64>%', 'show_html_links', $htmlContent);
        }

        if (empty(self::$hashtags) && empty(self::$userids)) {
            return;
        }

        if (!$isInHtmlFormat) {
            $htmlContent = qa_html($htmlContent, true);
        }

        $htmlContent = $this->updateUsers($htmlContent);
        $htmlContent = $this->updateTags($htmlContent);
        $htmlContent = $this->updateCloseTags($htmlContent);

        // Let's force the object to use HTML format if it has created links
        if (!$isInHtmlFormat) {
            $row['format'] = 'html';
        }
        $row['content'] = $htmlContent;
    }

    /**
     * @param bool $isInHtmlFormat
     * @param string $htmlContent
     *
     * @return string
     */
    private function parseTags($isInHtmlFormat, $htmlContent)
    {
        $htmlContent = preg_replace_callback(
            '%#(?P<word>[\w\-]+?)#%u',
            function ($matches) use ($isInHtmlFormat) {
                return self::build_tag_link($matches, $isInHtmlFormat);
            },
            $htmlContent
        );

        return $htmlContent;
    }

    /**
     * @param bool $isInHtmlFormat
     * @param string $htmlContent
     *
     * @return string
     */
    private function parseUsers($isInHtmlFormat, $htmlContent)
    {
        $htmlContent = preg_replace_callback(
            '%@(?P<name>.+?)[@/+]%u',
            function ($matches) use ($isInHtmlFormat) {
                return self::build_user_link($matches, $isInHtmlFormat);
            },
            $htmlContent
        );

        return $htmlContent;
    }

    /**
     * @param string $htmlContent
     *
     * @return string
     */
    private function updateUsers($htmlContent)
    {
        foreach (self::$userids as $userid => $url) {
            $openLinkSearch = sprintf('${hashtagger-open-link-user-%s}', $userid);
            $replaceWith = sprintf('<a href="%s">', $url);
            $htmlContent = str_replace($openLinkSearch, $replaceWith, $htmlContent);
        }

        return $htmlContent;
    }

    /**
     * @param string $htmlContent
     *
     * @return string
     */
    public function updateTags($htmlContent)
    {
        foreach (self::$hashtags as $tagId => $url) {
            $openLinkSearch = sprintf('${hashtagger-open-link-tag-%s}', $tagId);
            $replaceWith = sprintf('<a href="%s">', $url);
            $htmlContent = str_replace($openLinkSearch, $replaceWith, $htmlContent);
        }

        return $htmlContent;
    }

    /**
     * @param $htmlContent
     *
     * @return string
     */
    public function updateCloseTags($htmlContent)
    {
        return str_replace('${hashtagger-close-link}', '</a>', $htmlContent);
    }
}
