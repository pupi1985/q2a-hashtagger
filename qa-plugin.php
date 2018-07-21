<?php

/**
  Plugin Name: Hashtagger
  Plugin URI: https://dl.dropboxusercontent.com/u/13439369/q2a/hashtagger.zip
  Plugin Description: Automatically convert hashtags (#some_word) and mentions (@some_name) into HTML links
  Plugin Version: 1.1
  Plugin Date: 2014-02-12
  Plugin Author: Victor
  Plugin Author URI: http://www.question2answer.org/qa/user/Victor
  Plugin License: GPLv2
  Plugin Minimum Question2Answer Version: 1.6.3
  Plugin Update Check URI: https://dl.dropboxusercontent.com/u/13439369/q2a/hashtagger.txt
 */
if (!defined('QA_VERSION')) {
	header('Location: ../../');
	exit;
}

qa_register_plugin_phrases('qa-hashtagger-lang-*.php', 'plugin_hashtagger');
qa_register_plugin_module('filter', 'qa-hashtagger.php', 'qa_hashtagger', 'Hashtagger');
qa_register_plugin_module('event', 'qa-hashtagger.php', 'qa_hashtagger', 'Hashtagger');
