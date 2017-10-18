<?php
/**
 * WooCommerce integration for the WP PGP Encrypted Emails plugin.
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * @copyright Copyright (c) 2017 by Meitar "maymay" Moscovitz
 *
 * @package WordPress\Plugin\WP_PGP_Encrypted_Emails\WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) { exit; } // Disallow direct HTTP access.

/**
 * Main class for WooCommerce integration.
 */
class WP_PGP_Encrypted_Emails_WooCommerce {

    /**
     * Register hooks.
     */
    public static function register () {
        add_action( 'woocommerce_edit_account_form', array( __CLASS__, 'renderEditAccountForm' ) );
        add_action( 'woocommerce_save_account_details', array( 'WP_PGP_Encrypted_Emails', 'saveProfile' ) );
    }

    /**
     * Prints the relevant fields as part of the WooCommerce-provided
     * "Account Details" front-end profile screen.
     */
    public static function renderEditAccountForm () {
        $wp_user = get_userdata( get_current_user_id() );
?>
<fieldset>
    <legend><?php esc_html_e( 'Email security', 'wp-pgp-encrypted-emails' ); ?></legend>
    <p class="woocommerce-form-row">
        <label for="<?php print esc_attr( WP_PGP_Encrypted_Emails::meta_key ); ?>"><?php esc_html_e( 'Your PGP Public Key', 'wp-pgp-encrypted-emails' ); ?></label>
        <textarea
            id="<?php print esc_attr( WP_PGP_Encrypted_Emails::meta_key ); ?>"
            name="<?php print esc_attr( WP_PGP_Encrypted_Emails::meta_key ); ?>"
        ><?php
        print esc_html( $wp_user->{WP_PGP_Encrypted_Emails::meta_key} );
        ?></textarea>
    </p>
    <p class="woocommerce-form-row">
        <label for="<?php print esc_attr( WP_PGP_Encrypted_Emails::meta_smime_certificate ); ?>"><?php esc_html_e( 'Your S/MIME Public Certificate', 'wp-pgp-encrypted-emails' ); ?></label>
        <textarea
            id="<?php print esc_attr( WP_PGP_Encrypted_Emails::meta_smime_certificate ); ?>"
            name="<?php print esc_attr( WP_PGP_Encrypted_Emails::meta_smime_certificate ); ?>"
        ><?php
        print esc_html( $wp_user->{WP_PGP_Encrypted_Emails::meta_smime_certificate} );
        ?></textarea>
    </p>
</fieldset>
<?php
    }

}

WP_PGP_Encrypted_Emails_WooCommerce::register();
