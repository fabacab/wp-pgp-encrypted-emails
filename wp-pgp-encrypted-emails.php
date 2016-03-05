<?php
/**
 * The WP PGP Encrypted Emails plugin for WordPress.
 *
 * WordPress plugin header information:
 *
 * * Plugin Name: WP PGP Encrypted Emails
 * * Plugin URI: https://github.com/meitar/wp-pgp-encrypted-emails
 * * Description: Encrypts all emails sent to a given user if that user adds a PGP public key to their profile. <strong>Like this plugin? Please <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&amp;business=TJLPJYXHSRBEE&amp;lc=US&amp;item_name=WP%20PGP%20Encrypted%20Emails&amp;item_number=wp-pgp-encrypted-emails&amp;currency_code=USD&amp;bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted" title="Send a donation to the developer of WP PGP Encrypted Emails">donate</a>. &hearts; Thank you!</strong>
 * * Version: 0.4.2
 * * Author: Maymay <bitetheappleback@gmail.com>
 * * Author URI: https://maymay.net/
 * * License: GPL-3
 * * License URI: https://www.gnu.org/licenses/gpl-3.0.en.html
 * * Text Domain: wp-pgp-encrypted-emails
 * * Domain Path: /languages
 *
 * @link https://developer.wordpress.org/plugins/the-basics/header-requirements/
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * @copyright Copyright (c) 2016 by Meitar "maymay" Moscovitz
 *
 * @package WordPress\Plugin\WP_PGP_Encrypted_Emails
 */

if (!defined('ABSPATH')) { exit; } // Disallow direct HTTP access.

if (!defined('WP_PGP_ENCRYPTED_EMAILS_MIN_PHP_VERSION')) {
    /**
     * The minimum version of PHP needed to run the plugin.
     *
     * This is explicit because WordPress supports even older versions
     * of PHP, so we check the running version on plugin activation.
     *
     * We need PHP 5.3.3 or later since the OpenPGP.php library we use
     * requires at least that version.
     *
     * @link https://secure.php.net/manual/en/language.oop5.late-static-bindings.php
     */
    define('WP_PGP_ENCRYPTED_EMAILS_MIN_PHP_VERSION', '5.3.3');
}

/**
 * Base class that WordPress uses to register and initialize plugin.
 */
class WP_PGP_Encrypted_Emails {

    /**
     * Meta key where PGP private/public keypair is stored.
     *
     * This is intended to be the PGP private key used by the plugin
     * for signing outgoing emails. It is *not* intended to store any
     * user's private key material nor is it intended to be used for
     * saving any key material for any other purpose other than this
     * plugin's own use. **Do not**, under any circumstances, copy a key
     * used in any other application to this field.
     *
     * @var string
     */
    private static $meta_keypair = 'pgp_keypair';

    /**
     * Meta key where PGP public key is stored.
     *
     * @var string
     */
    public static $meta_key = 'pgp_public_key';

    /**
     * Meta key where subject line toggle is stored.
     *
     * @var string
     */
    public static $meta_key_empty_subject_line = 'pgp_empty_subject_line';

    /**
     * Entry point for the WordPress framework into plugin code.
     *
     * This is the method called when WordPress loads the plugin file.
     * It is responsible for "registering" the plugin's main functions
     * with the {@see https://codex.wordpress.org/Plugin_API WordPress Plugin API}.
     *
     * @uses add_action()
     * @uses add_filter()
     * @uses remove_filter()
     * @uses register_activation_hook()
     * @uses register_deactivation_hook()
     *
     * @return void
     */
    public static function register () {
        add_action('plugins_loaded', array(__CLASS__, 'registerL10n'));
        add_action('init', array(__CLASS__, 'initialize'));

        add_action('wp_ajax_nopriv_download_pgp_signing_public_key', array(__CLASS__, 'downloadSigningPublicKey'));
        add_action('wp_ajax_download_pgp_signing_public_key', array(__CLASS__, 'downloadSigningPublicKey'));
        add_action('wp_ajax_openpgp_regen_keypair', array(__CLASS__, 'regenerateKeypair'));

        if (is_admin()) {
            add_action('admin_init', array(__CLASS__, 'registerAdminSettings'));
            add_action('admin_notices', array(__CLASS__, 'adminNoticeBadUserKey'));
            add_action('admin_notices', array(__CLASS__, 'adminNoticeBadAdminKey'));
            add_action('show_user_profile', array(__CLASS__, 'renderProfile'));
            add_action('personal_options_update', array(__CLASS__, 'saveProfile'));

            $kp = get_option(self::$meta_keypair);
            if (!$kp || empty($kp['privatekey'])) {
                add_action('admin_notices', array(__CLASS__, 'showMissingSigningKeyNotice'));
            }
        } else {
            remove_filter('comment_text', 'wptexturize'); // we do wptexturize() ourselves
            add_filter('comment_text', array(__CLASS__, 'commentText'));
            add_filter('comment_form_submit_field', array(__CLASS__, 'renderCommentFormFields'));
            add_filter('comment_class', array(__CLASS__, 'commentClass'), 10, 4);
            add_filter('preprocess_comment', array(__CLASS__, 'preprocessComment'));
        }

        add_filter('wp_mail', array(__CLASS__, 'wp_mail'));

        register_activation_hook(__FILE__, array(__CLASS__, 'activate'));
    }

    /**
     * Loads localization files from plugin's languages directory.
     *
     * @uses load_plugin_textdomain()
     *
     * @return void
     */
    public static function registerL10n () {
        load_plugin_textdomain('wp-pgp-encrypted-emails', false, dirname(plugin_basename(__FILE__)).'/languages/');
    }

    /**
     * Loads plugin componentry. Called at the WordPress `init` hook.
     *
     * @return void
     */
    public static function initialize () {
        require_once plugin_dir_path(__FILE__).'class-wp-openpgp.php';
        WP_OpenPGP::register();
    }

    /**
     * Method to run when the plugin is activated by a user in the
     * WordPress Dashboard admin screen.
     *
     * @uses WP_PGP_Encrypted_Emails::checkPrereqs()
     *
     * @return void
     */
    public static function activate () {
        self::checkPrereqs();
    }

    /**
     * Checks system requirements and exits if they are not met.
     *
     * This first checks to ensure minimum WordPress and PHP versions
     * have been satisfied. If not, the plugin deactivates and exits.
     *
     * @global $wp_version
     *
     * @uses $wp_version
     * @uses WP_PGP_Encrypted_Emails::get_minimum_wordpress_version()
     * @uses deactivate_plugins()
     * @uses plugin_basename()
     *
     * @return void
     */
    public static function checkPrereqs () {
        global $wp_version;
        $min_wp_version = self::get_minimum_wordpress_version();

        if (version_compare(WP_PGP_ENCRYPTED_EMAILS_MIN_PHP_VERSION, PHP_VERSION) > 0) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(sprintf(
                __('WP PGP Encrypted Emails requires at least PHP version %1$s. You have PHP version %2$s.', 'wp-pgp-encrypted-emails'),
                WP_PGP_ENCRYPTED_EMAILS_MIN_PHP_VERSION, PHP_VERSION
            ));
        }
        if (version_compare($min_wp_version, $wp_version) > 0) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(sprintf(
                __('WP PGP Encrypted Emails requires at least WordPress version %1$s. You have WordPress version %2$s.', 'buoy'),
                $min_wp_version, $wp_version
            ));
        }
    }

    /**
     * Returns the "Requires at least" value from plugin's readme.txt.
     *
     * @link https://wordpress.org/plugins/about/readme.txt WordPress readme.txt standard
     *
     * @return string
     */
    public static function get_minimum_wordpress_version () {
        $lines = @file(plugin_dir_path(__FILE__).'readme.txt');
        foreach ($lines as $line) {
            preg_match('/^Requires at least: ([0-9.]+)$/', $line, $m);
            if ($m) {
                return $m[1];
            }
        }
    }

    /**
     * Shows a notice on the Plugins screen that a signing key is missing.
     *
     * @return void
     */
    public static function showMissingSigningKeyNotice () {
        $screen = get_current_screen();
        if ($screen->base === 'plugins') {
?>
<div class="updated">
    <p><a href="<?php print esc_attr(self::getKeypairRegenURL());?>" class="button"><?php esc_html_e('Generate PGP signing key', 'wp-pgp-encrypted-emails');?></a> &mdash; <?php print sprintf(esc_html__('Almost done! Generate an OpenPGP keypair for %s to sign outgoing emails.', 'wp-pgp-encrypted-emails'), get_bloginfo('name'));?></p>
</div>
<?php
        }
    }

    /**
     * Gets a user's PGP public key.
     *
     * @param WP_User|int|string $user
     *
     * @return OpenPGP_Message|false
     */
    public static function getUserKey ($user = null) {
        $wp_user = false;
        $ascii_key = false;

        if ($user instanceof WP_User) {
            $wp_user = $user;
        } else if (get_user_by('email', $user)) {
            $wp_user = get_user_by('email', $user);
        } else if (get_userdata($user)) {
            $wp_user = get_userdata($user);
        } else {
            $wp_user = wp_get_current_user();
        }

        if ($wp_user) {
            $ascii_key = $wp_user->{self::$meta_key};
        }

        return apply_filters('openpgp_key', $ascii_key);
    }

    /**
     * Gets the admin's PGP public key.
     *
     * @return OpenPGP_Message|false
     */
    public static function getAdminKey () {
        return apply_filters('openpgp_key', get_option(self::$meta_key));
    }

    /**
     * Registers the plugin settings with WordPress.
     *
     * @link https://codex.wordpress.org/Settings_API
     *
     * @uses add_settings_field()
     * @uses register_setting()
     *
     * @return void
     */
    public static function registerAdminSettings () {
        // PGP Public Key
        add_settings_field(
            self::$meta_key,
            __('Admin Email PGP Public Key', 'wp-pgp-encrypted-emails'),
            array(__CLASS__, 'renderAdminKeySetting'),
            'general',
            'default',
            array(
                'label_for' => self::$meta_key
            )
        );
        register_setting(
            'general',
            self::$meta_key,
            array(__CLASS__, 'sanitizeKeyASCII')
        );

        // PGP empty subject line?
        add_settings_field(
            self::$meta_key_empty_subject_line,
            __('Always empty subject lines for PGP-encrypted emails', 'wp-pgp-encrypted-emails'),
            array(__CLASS__, 'renderEmptySubjectLineSetting'),
            'general',
            'default',
            array(
                'label_for' => self::$meta_key_empty_subject_line
            )
        );
        register_setting(
            'general',
            self::$meta_key_empty_subject_line,
            array(__CLASS__, 'sanitizeCheckBox')
        );

        // PGP signing keypair
        add_settings_field(
            self::$meta_keypair,
            __('PGP Signing Keypair', 'wp-pgp-encrypted-emails'),
            array(__CLASS__, 'renderSigningKeypairSetting'),
            'general',
            'default',
            array(
                'label_for' => self::$meta_keypair.'_publickey'
            )
        );
        register_setting(
            'general',
            self::$meta_keypair,
            array(__CLASS__, 'sanitizeSigningKeypair')
        );
    }

    /**
     * Sanitizes the signing keypair.
     *
     * @param string[] $input
     *
     * @uses wp_parse_args()
     *
     * @return string[]
     */
    public static function sanitizeSigningKeypair ($input) {
        $old_keypair = get_option(self::$meta_keypair);
        return wp_parse_args(self::sanitizeKeypairASCII($input), $old_keypair);
    }

    /**
     * Sanitizes a PGP private/public keypair.
     *
     * @param string[] $keypair
     *
     * @return string[]
     */
    public static function sanitizeKeypairASCII ($keypair) {
        $safe_input = array();
        foreach ($keypair as $k => $v) {
            $safe_input[$k] = self::sanitizeKeyASCII($v);
        }
        return $safe_input;
    }

    /**
     * Sanitizes a PGP public key block.
     *
     * @param string $ascii_key
     *
     * @return string
     */
    public static function sanitizeKeyASCII ($ascii_key) {
        // TODO: Be a bit smarter about this being a PGP public key.
        return self::sanitizeTextArea($ascii_key);
    }

    /**
     * A helper function that sanitizes multi-line inputs.
     *
     * @param string $input
     *
     * @return string
     */
    public static function sanitizeTextArea ($input) {
        return implode("\n", array_map('sanitize_text_field', explode("\n", $input)));
    }

    /**
     * A helper function that sanitizes check boxes.
     *
     * @param mixed $input
     *
     * @return bool
     */
    public static function sanitizeCheckBox ($input) {
        return isset($input);
    }

    /**
     * Prints a warning to the user if their PGP public key can't be used.
     *
     * @uses WP_PGP_Encrypted_Emails::getUserKey()
     * @uses wp_get_current_user()
     * @uses admin_url()
     *
     * @return void
     */
    public static function adminNoticeBadUserKey () {
        $wp_user = wp_get_current_user();
        if (!empty($wp_user->{self::$meta_key}) && !self::getUserKey($wp_user)) {
?>
<div class="notice error is-dismissible">
    <p><strong><?php esc_html_e('There is a problem with your PGP public key.', 'wp-pgp-encrypted-emails');?></strong></p>
    <p class="description"><?php print sprintf(
        esc_html__('Your PGP public key is what WordPress uses to encrypt emails it sends to you so that only you can read them. Unfortunately, something is wrong or missing in %1$sthe public key saved in your profile%2$s.', 'wp-pgp-encrypted-emails'),
        '<a href="'.admin_url('profile.php#'.self::$meta_key).'">', '</a>'
    );?></p>
</div>
<?php
        }
    }

    /**
     * Prints a warning to the admin if their PGP public key can't be used.
     *
     * @uses get_option()
     * @uses WP_PGP_Encrypted_Emails::getAdminKey()
     * @uses admin_url()
     *
     * @return void
     */
    public static function adminNoticeBadAdminKey () {
        $options = get_option(self::$meta_key);
        if (current_user_can('manage_options')
            && !empty($options)
            && !self::getAdminKey())
        {
?>
<div class="notice error is-dismissible">
    <p><strong><?php esc_html_e('There is a problem with your admin email PGP public key.', 'wp-pgp-encrypted-emails');?></strong></p>
    <p class="description"><?php print sprintf(
        esc_html__('Your PGP public key is what WordPress uses to encrypt emails it sends to you so that only you can read them. Unfortunately, something is wrong or missing in %1$sthe admin email public key option%2$s.', 'wp-pgp-encrypted-emails'),
        '<a href="'.admin_url('options-general.php#'.self::$meta_key).'">', '</a>'
    );?></p>
</div>
<?php
        }
    }

    /**
     * Prints the HTML for the custom profile fields.
     *
     * @param WP_User $profileuser
     *
     * @return void
     */
    public static function renderProfile ($profileuser) {
        require_once 'pages/profile.php';
    }

    /**
     * Prints the HTML for the plugin's admin key setting.
     *
     * @return void
     */
    public static function renderAdminKeySetting () {
?>
<textarea
    id="<?php print esc_attr(self::$meta_key);?>"
    name="<?php print esc_attr(self::$meta_key);?>"
    class="large-text code"
    rows="5"
><?php print esc_textarea(get_option(self::$meta_key));?></textarea>
<p class="description">
    <?php print sprintf(
        esc_html__('Paste the PGP public key for the admin email here to have WordPress encrypt admin emails it sends. Leave this blank if you do not want to get or know how to decrypt encrypted emails.', 'wp-pgp-encrypted-emails')
    );?>
</p>
<?php
    }

    /**
     * Prints the HTML for the plugin's admin subject line setting.
     *
     * @return void
     */
    public static function renderEmptySubjectLineSetting () {
?>
<input type="checkbox"
    id="<?php print esc_attr(self::$meta_key_empty_subject_line);?>"
    name="<?php print esc_attr(self::$meta_key_empty_subject_line);?>"
    <?php checked(get_option(self::$meta_key_empty_subject_line));?>
    value="1"
/>
<span class="description">
    <?php print sprintf(
        esc_html__('PGP encryption cannot encrypt envelope information (such as the subject) of an email, so if you want maximum privacy, make sure this option is enabled to always erase the subject line from encrypted emails you receive.', 'wp-pgp-encrypted-emails')
    );?>
</span>
<?php
    }

    /**
     * Gets a URL for a valid keypair regen request.
     *
     * @return string
     */
    private static function getKeypairRegenURL () {
        return wp_nonce_url(
            admin_url('admin-ajax.php').'?action=openpgp_regen_keypair',
            'wp_pgp_regen_keypair', 'wp_pgp_nonce'
        );
    }

    /**
     * Prints the HTML for the plugin's signing keypair setting.
     *
     * @return void
     */
    public static function renderSigningKeypairSetting () {
        $kp = get_option(self::$meta_keypair);
        if (is_ssl()) {
?>
<p class="submit">
    <label for="<?php print esc_attr(self::$meta_keypair)?>_privatekey">Private key</label>
</p>
    <textarea
        id="<?php print esc_attr(self::$meta_keypair)?>_privatekey"
        name="<?php print esc_attr(self::$meta_keypair)?>[privatekey]"
        class="large-text code"
        rows="5"
    ><?php print esc_textarea($kp['privatekey']);?></textarea>
<?php
        } else {
            print '<p class="notice error" style="border-left: 4px solid red; padding: 6px 12px;">';
            print sprintf(
                esc_html__('Private key is not shown over an insecure (HTTP) connection. %1$sSwitch to HTTPS%2$s to manually modify private key.', 'wp-pgp-encrypted-emails'),
                '<a href="'.admin_url('options-general.php', 'https').'">', '</a>'
            );
            print '</p>';
        }
?>
<p class="submit">
    <label for="<?php print esc_attr(self::$meta_keypair)?>_publickey">Public key</label>
    <a class="button" href="<?php print esc_attr(
        admin_url('admin-ajax.php?action=download_pgp_signing_public_key')
    );?>">
        <?php esc_html_e('Download public key', 'wp-pgp-encrypted-emails');?>
    </a>
</p>
<textarea
    id="<?php print esc_attr(self::$meta_keypair)?>_publickey"
    name="<?php print esc_attr(self::$meta_keypair)?>[publickey]"
    class="large-text code"
    rows="5"
><?php print esc_textarea($kp['publickey']);?></textarea>
<p class="description"><?php $lang = get_locale(); $lang = substr($lang, 0, 2); print sprintf(
    esc_html__('The PGP signing keypair is used to authenticate emails sent from this site. You should import its public key part into your OpenPGP-compatible email client. (%1$sFind an OpenPGP-compatible client for your platform%2$s.) You should never share the private key part with anyone; treat it like a password. If an attacker obtains a copy of the private key part, they can forge digital signatures belonging to this site.', 'wp-pgp-encrypted-emails'),
    '<a href="https://prism-break.org/'.$lang.'/protocols/gpg/" target="_blank">', '</a>'
);?></p>
<p class="submit">
    <a href="<?php print esc_attr(self::getKeypairRegenURL());?>" class="button">
        <?php esc_html_e('Regenerate keypair', 'wp-pgp-encrypted-emails');?>
    </a>
    <span class="description">
        <?php print sprintf(esc_html__('Careful, this will delete the current PGP signing keypair for %s.', 'wp-pgp-encrypted-emails'), get_bloginfo('name'));?>
    </span>
</p>
<?php
    }

    /**
     * Prompts the browser to download the signing public key.
     *
     * This method should be run in the context of `admin-ajax.php`.
     *
     * @return void
     */
    public static function downloadSigningPublicKey () {
        $kp = get_option(self::$meta_keypair);
        $k  = $kp['publickey'];
        $filename = sanitize_title_with_dashes(get_bloginfo('name')).'.pubkey.asc';
        header('Content-Type: application/octet-stream');
        header("Content-Disposition: attachment; filename=$filename");
        header('Content-Length: '.strlen($k));
        if (function_exists('gzencode')
            && isset($_SERVER['HTTP_ACCEPT_ENCODING'])
            && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false) {
            header('Content-Encoding: gzip');
            $k = gzencode($k);
        }
        print $k;
        exit();
    }

    /**
     * Resets the signing keypair.
     *
     * This method should be run in the context of `admin-ajax.php`.
     *
     * This will save a serialized array in the `pgp_keypair` option
     * in the WordPress database. The array has two elements, which
     * correspond to the private and public key material of the pair,
     * respectively. They are indexed as `privatekey` and `publickey`.
     *
     * Other plugins can access and use the keypair like this:
     *
     *     // Get the keypair as an associative array
     *     $keypair = get_option('pgp_keypair');
     *
     * From here, you can use the various `openpgp_*` filters to use
     * the keys for signing, encryption, or other operations.
     *
     * @return void
     */
    public static function regenerateKeypair () {
        if (empty($_GET['wp_pgp_nonce']) || !wp_verify_nonce($_GET['wp_pgp_nonce'], 'wp_pgp_regen_keypair')) {
            add_settings_error('general', 'settings_updated', __('Invalid keygen request.', 'wp-pgp-encrypted-emails'));
            set_transient('settings_errors', get_settings_errors(), 30);
            wp_safe_redirect(admin_url('options-general.php?settings-updated=true'));
            exit(1); // error exit code
        }
        // Make up an email address to use as the site's key identity.
        // This is also what WordPress core's wp_mail() function does.
        // See: https://core.trac.wordpress.org/browser/tags/4.4.2/src/wp-includes/pluggable.php#L371
        $sitename = strtolower( $_SERVER['SERVER_NAME'] );
        if (substr($sitename, 0, 4) == 'www.') {
            $sitename = substr($sitename, 4);
        }
        $from_email = 'wordpress@'.$sitename;

        // Key generation could take some time, so try raising the limit.
        $old_time_limit = ini_get('max_execution_time');
        set_time_limit(0);

        // If that doesn't work, make sure we can gracefully fail.
        add_action('shutdown', array(__CLASS__, 'keygenTimeoutError'));

        // Now try generating a new keypair.
        $keypair = WP_OpenPGP::generateKeypair("WordPress <$from_email>");

        // If we're still running, restore the old settings.
        set_time_limit($old_time_limit);

        $ascii_keypair = array();
        $ascii_keypair['privatekey'] = apply_filters('openpgp_enarmor', $keypair['privatekey'], 'PGP PRIVATE KEY BLOCK');
        $ascii_keypair['publickey']  = apply_filters('openpgp_enarmor', $keypair['publickey'], 'PGP PUBLIC KEY BLOCK');
        update_option(self::$meta_keypair, $ascii_keypair);

        add_settings_error('general', 'settings_updated', __('OpenPGP signing keypair successfully regenerated.', 'wp-pgp-encrypted-emails'), 'updated');
        set_transient('settings_errors', get_settings_errors(), 30);
        wp_safe_redirect(admin_url('options-general.php?settings-updated=true'));
        exit(0); // success exit code
    }

    /**
     * Runs when we cannot generate a keypair within PHP's time limit.
     *
     * @return void
     */
    public static function keygenTimeoutError () {
        // TODO: How can we recover from this more gracefully?
        error_log(__('RSA keypair generation exceeded maximum PHP execution timeout.', 'wp-pgp-encrypted-emails'));
    }

    /**
     * Adds a "Private" checkbox to the comment form.
     *
     * @link https://developer.wordpress.org/reference/hooks/comment_form_submit_field/
     *
     * @param string $submit_field
     *
     * @return string
     */
    public static function renderCommentFormFields ($submit_field) {
        $post = get_post();
        $html = '';
        if (self::getUserKey($post->post_author)) {
            $author = get_userdata($post->post_author);
            $html .= '<p class="comment-form-openpgp-encryption">';
            $html .= '<label for="openpgp-encryption">'.esc_html__('Private', 'wp-pgp-encrypted-emails').'</label>';
            $html .= '<input type="checkbox" id="openpgp-encryption" name="openpgp-encryption" value="1" />';
            $html .= ' <span class="description">'.sprintf(esc_html__('You can encrypt your comment so that only %s can read it.', 'wp-pgp-encrypted-emails'), $author->display_name).'</span>';
            $html .= '</p>';
        }
        return $html.$submit_field;
    }

    /**
     * Adds an "openpgp-encryption" class to encrypted comments.
     *
     * @link https://developer.wordpress.org/reference/hooks/comment_class/
     *
     * @param array       $classes    An array of comment classes.
     * @param string      $class      A comma-separated list of additional classes added to the list.
     * @param int         $comment_id The comment id.
     * @param WP_Comment  $comment    The comment object.
     *
     * @return array
     */
    public static function commentClass ($classes, $class, $comment_id, $comment) {
        if (self::isEncrypted($comment->comment_content)) {
            $classes[] = 'openpgp-encryption';
        }
        return $classes;
    }

    /**
     * Whether the given text is an encrypted PGP MESSAGE block.
     *
     * @param string $text
     *
     * @return bool
     */
    public static function isEncrypted ($text) {
        $lines = explode("\n", $text);
        $first_line = trim(array_shift($lines));
        return (0 === strpos($first_line, '-----BEGIN PGP MESSAGE')) ? true : false;
    }

    /**
     * Texturizes comment text if it is not encrypted.
     *
     * @param string $text
     *
     * @return string
     */
    public static function commentText ($text) {
        return (self::isEncrypted($text)) ? $text : wptexturize($text);
    }

    /**
     * Saves profile field values to the database on profile update.
     *
     * @global $_POST Used to access values submitted by profile form.
     *
     * @param int $user_id
     *
     * @uses WP_PGP_Encrypted_Emails::$meta_key
     * @uses WP_PGP_Encrypted_Emails::sanitizeTextArea()
     * @uses update_user_meta()
     *
     * @return void
     */
    public static function saveProfile ($user_id) {
        update_user_meta(
            $user_id,
            self::$meta_key,
            self::sanitizeTextArea($_POST[self::$meta_key])
        );
        update_user_meta(
            $user_id,
            self::$meta_key_empty_subject_line,
            isset($_POST[self::$meta_key_empty_subject_line])
        );
    }

    /**
     * Encrypts messages that WordPress sends when it sends email.
     *
     * This method completely hijacks a call to `wp_mail()` function,
     * accepting its arguments and sending 1 email for each recipient
     * it is passed. The last (or only) recipient will be returned to
     * the original call to `wp_mail()` for normal handling.
     *
     * @link https://developer.wordpress.org/reference/hooks/wp_mail/
     *
     * @param array $args
     *
     * @return array
     */
    public static function wp_mail ($args) {
        if (!is_array($args['to'])) {
            $args['to'] = explode(',', $args['to']);
        }
        $args['headers'] = (isset($args['headers'])) ? $args['headers'] : '';
        $args['attachments'] = (isset($args['attachments'])) ? $args['attachments'] : array();

        // First sign the message, if we can.
        $kp = get_option(self::$meta_keypair);
        if ($kp && !empty($kp['privatekey']) && $sec_key = apply_filters('openpgp_key', $kp['privatekey'])) {
            $args['message'] = apply_filters('openpgp_sign', $args['message'], $sec_key);
        }

        while ($to = array_pop($args['to'])) {
            $mail = self::prepareMail(
                $to,
                $args['subject'],
                $args['message'],
                $args['headers'],
                $args['attachments']
            );
            if (0 === count($args['to'])) {
                return $mail;
            } else {
                // Now that we've re-configured the message, we run this
                // back through wp_mail() without calling this same
                // function again.
                remove_filter('wp_mail', array(__CLASS__, __FUNCTION__));
                wp_mail($mail['to'], $mail['subject'], $mail['message'], $mail['headers'], $mail['attachments']);
                add_filter('wp_mail', array(__CLASS__, __FUNCTION__));
            }
        }
    }

    /**
     * Encrypts an email to a single recipient.
     *
     * @param string $to
     * @param string $subject
     * @param string $message
     * @param string|string[] $headers
     * @param string[] $attachments
     *
     * @return array
     */
    private static function prepareMail ($to, $subject, $message, $headers, $attachments) {
        $pub_key       = false;
        $erase_subject = false;

        if (get_option('admin_email') === $to) {
            $pub_key = self::getAdminKey();
            $erase_subject = get_option(self::$meta_key_empty_subject_line);
        } else if ($wp_user = get_user_by('email', $to)) {
            $pub_key = self::getUserKey($wp_user);
            $erase_subject = $wp_user->{self::$meta_key_empty_subject_line};
        }

        if ($erase_subject) {
            $subject = '';
        }

        if ($pub_key instanceof OpenPGP_Message) {
            try {
                $message = apply_filters('openpgp_encrypt', $message, $pub_key);
            } catch (Exception $e) {
                error_log(sprintf(
                    __('Cannot send encrypted email to %1$s', 'wp-pgp-encrypted-emails'),
                    $to
                ));
            }
        }

        return array(
            'to' => $to,
            'subject' => $subject,
            'message' => $message,
            'headers' => $headers,
            'attachments' => $attachments
        );
    }

    /**
     * Encrypts comment content with the post author's public key.
     *
     * @link https://developer.wordpress.org/reference/hooks/preprocess_comment/
     *
     * @param array $comment_data
     *
     * @return array
     */
    public static function preprocessComment ($comment_data) {
        $post = get_post($comment_data['comment_post_ID']);
        $key = self::getUserKey($post->post_author);
        if (!empty($_POST['openpgp-encryption']) && !self::isEncrypted($comment_data['comment_content']) && $key) {
            $comment_data['comment_content'] = apply_filters('openpgp_encrypt', wp_unslash($comment_data['comment_content']), $key);
        }
        return $comment_data;
    }

}

WP_PGP_Encrypted_Emails::register();
