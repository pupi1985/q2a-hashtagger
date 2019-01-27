<?php

if (!defined('QA_VERSION')) {
    header('Location: ../../');
    exit;
}

qa_register_plugin_module('filter', 'qa_hashtagger.php', 'qa_hashtagger', 'Hashtagger Filter');
qa_register_plugin_module('event', 'qa_hashtagger.php', 'qa_hashtagger', 'Hashtagger Event');
qa_register_plugin_module('process', 'qa_hashtagger_admin.php', 'qa_hashtagger_admin', 'Hashtagger Admin');

qa_register_plugin_phrases('qa_hashtagger_lang_*.php', 'plugin_hashtagger');
