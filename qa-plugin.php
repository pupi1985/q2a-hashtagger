<?php

if (!defined('QA_VERSION')) {
    header('Location: ../../');
    exit;
}

qa_register_plugin_phrases('qa-hashtagger-lang-*.php', 'plugin_hashtagger');
qa_register_plugin_module('filter', 'qa-hashtagger.php', 'qa_hashtagger', 'Hashtagger');
qa_register_plugin_module('event', 'qa-hashtagger.php', 'qa_hashtagger', 'Hashtagger');
