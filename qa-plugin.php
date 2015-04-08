<?php
/*
	Plugin Name: Find Text in Posts
	Plugin URI: http://www.q2apro.com/plugins/find-text-posts
	Plugin Description: Plugin for admins to find text in posts
	Plugin Version: 0.1
	Plugin Date: 2015-04-08
	Plugin Author: q2apro.com
	Plugin Author URI: http://www.q2apro.com/
	Plugin License: GPLv3
	Plugin Minimum Question2Answer Version: 1.5
	Plugin Update Check URI: https://raw.github.com/q2apro/q2apro-find-text-posts/master/qa-plugin.php

	This program is free software. You can redistribute and modify it 
	under the terms of the GNU General Public License.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	More about this license: http://www.gnu.org/licenses/gpl.html
	
*/

	if ( !defined('QA_VERSION') ) { // don't allow this page to be requested directly from browser
		header('Location: ../../');
		exit;
	}

	// page
	qa_register_plugin_module('page', 'q2apro-find-text-posts-page.php', 'q2apro_find_text_posts_page', 'Q2APRO Find Text Posts Page');

	// language file
	qa_register_plugin_phrases('q2apro-find-text-posts-lang.php', 'q2apro_find_text_posts_lang');


/*
	Omit PHP closing tag to help avoid accidental output
*/