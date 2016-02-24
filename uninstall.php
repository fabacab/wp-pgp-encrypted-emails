<?php
/**
 * WP PGP Encrypted Emails uninstaller.
 *
 * @link https://developer.wordpress.org/plugins/the-basics/uninstall-methods/#uninstall-php
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * @copyright Copyright (c) 2016 by Meitar "maymay" Moscovitz
 *
 * @package WordPress\Plugin\WP_PGP_Encrypted_Emails\Uninstaller
 */

// Don't execute any uninstall code unless WordPress core requests it.
if (!defined('WP_UNINSTALL_PLUGIN')) { exit(); }

require_once plugin_dir_path(__FILE__) . 'wp-pgp-encrypted-emails.php';

$meta_keys = array(
    WP_PGP_Encrypted_Emails::$meta_key,
    WP_PGP_Encrypted_Emails::$meta_key_empty_subject_line
);
foreach ($meta_keys as $name) {
    delete_option($name);
    delete_metadata('user', null, $name, null, true);
}

// Also delete the private key, if told to do so.
// TODO: Implement these two options.
if (get_option('wp_openpgp_remove_private_key')) {
    delete_option(WP_PGP_Encrypted_Emails::$meta_private_key);
    delete_option('wp_openpgp_remove_private_key');
}
