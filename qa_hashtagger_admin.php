<?php

class qa_hashtagger_admin
{
    /**
     * The list of options names (these options have boolean values)
     *
     * @var array
     */
    private static $bool_options = array(
        'plugin_hashtagger/filter_questions',
        'plugin_hashtagger/filter_comments',
        'plugin_hashtagger/filter_answers',
        'plugin_hashtagger/convert_hashtags',
        'plugin_hashtagger/keep_hash_symbol',
        'plugin_hashtagger/convert_usernames',
        'plugin_hashtagger/notify_users',
    );

    /**
     * Get the default option value
     *
     * @param string $option
     *
     * @return boolean
     */
    public function option_default($option)
    {
        return in_array($option, self::$bool_options);
    }

    /**
     * Configuration of plugin for admin form
     *
     * @param array $qa_content
     *
     * @return array
     */
    public function admin_form(&$qa_content)
    {
        // Check if we should save options
        $save_options = qa_clicked('plugin_hashtagger_save_button');

        // Define default form variables
        $config = array(
            'buttons' => array(
                array(
                    'label' => qa_lang('plugin_hashtagger/save_changes'),
                    'tags' => 'NAME="plugin_hashtagger_save_button"',
                ),
            ),
            'fields' => array(),
            'ok' => $save_options ? qa_lang('plugin_hashtagger/options_saved') : null,
        );

        // Build and save options
        foreach (self::$bool_options as $name) {
            if ($save_options) {
                qa_opt($name, (bool)qa_post_text($name));
            }

            $config['fields'][] = array(
                'label' => qa_lang($name),
                'type' => 'checkbox',
                'value' => qa_opt($name),
                'tags' => "NAME='{$name}'",
            );
        }

        return $config;
    }
}
