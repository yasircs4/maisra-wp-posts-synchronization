<?php
/**
 * @package maisra-sync-content
 */
/*
    Plugin Name: Maisra Posts Synchronization
    Description: This WordPress plugin synchronizes posts when -publish,edit,trash- between two website, it create and config channel vai wp-rest-api and `JWT` auth connection.
    Version: 1.1
    Author: Yasir Najeeb
    Author URI: https://maisra.net
    License: GPLv2 or later
    Text Domain: maisra-sync-content
*/

if (!function_exists('add_action')) {
    echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
    exit;
}



define('MAISRA_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once(MAISRA_PLUGIN_DIR . 'includes/MSC_init.php');
require_once(MAISRA_PLUGIN_DIR . 'includes/MSC_helper.php');
require_once(MAISRA_PLUGIN_DIR . 'includes/MSC_posts.php');
require_once(MAISRA_PLUGIN_DIR . 'includes/MSC_terms.php');

register_activation_hook(__FILE__, ['MSC_init', 'plugin_activation']);
register_deactivation_hook(__FILE__, ['MSC_init', 'plugin_deactivation']);