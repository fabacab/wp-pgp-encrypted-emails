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

        add_action( 'openpgp_sign_email', array( __CLASS__, 'maybeSignEmail' ), 10, 2 );
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
    <?php
    $kp = get_option( WP_PGP_Encrypted_Emails::meta_keypair );
    if ( ! empty( $kp['publickey'] ) ) {
    ?>
    <fieldset>
        <legend><?php print sprintf( esc_html__( 'PGP signing key for %s', 'wp-pgp-encrypted-emails' ), get_bloginfo( 'name' ) ); ?></legend>
        <p>
            <a class="button"
                href="<?php print esc_attr( admin_url( 'admin-ajax.php?action=download_pgp_signing_public_key' ) ); ?>"
            ><?php esc_html_e( 'Download public key', 'wp-pgp-encrypted-emails' ); ?></a>
        </p>
        <p>
            <label>
                <input type="checkbox"
                    id="<?php print esc_attr( WP_PGP_Encrypted_Emails::meta_key_receive_signed_email );?>"
                    name="<?php print esc_attr( WP_PGP_Encrypted_Emails::meta_key_receive_signed_email );?>"
                    <?php checked( $wp_user->{WP_PGP_Encrypted_Emails::meta_key_receive_signed_email} );?>
                    value="1"
                />
                <?php esc_html_e( 'Receive OpenPGP-signed emails', 'wp-pgp-encrypted-emails' ); ?>
            </label>
        </p>
        <p class="description"><?php $lang = get_locale(); $lang = substr( $lang, 0, 2 ); print sprintf(
            esc_html__( '%1$s sends digitally signed emails to help you verify that email you receive purporting to be from this website was in fact sent from this website. To authenticate the emails, download the PGP public key and import it to %2$san OpenPGP-compatible client%3$s.', 'wp-pgp-encrypted-emails' ),
            get_bloginfo( 'name' ),
            links_add_target( '<a href="https://prism-break.org/' . $lang . '/protocols/gpg/">' ), '</a>'
        );?></p>
    </fieldset>
    <?php } // endif ?>
    <p class="woocommerce-form-row">
        <label for="<?php print esc_attr( WP_PGP_Encrypted_Emails::meta_key ); ?>"><?php esc_html_e( 'Your PGP Public Key', 'wp-pgp-encrypted-emails' ); ?></label>
        <textarea
            id="<?php print esc_attr( WP_PGP_Encrypted_Emails::meta_key ); ?>"
            name="<?php print esc_attr( WP_PGP_Encrypted_Emails::meta_key ); ?>"
        ><?php
        print esc_html( $wp_user->{WP_PGP_Encrypted_Emails::meta_key} );
        ?></textarea>
    </p>
    <p class="description">
        <?php print sprintf(
            esc_html__( 'Paste your PGP public key here to have %1$s encrypt emails it sends you. Leave this blank if you do not want to get or know how to decrypt encrypted emails.', 'wp-pgp-encrypted-emails' ),
            get_bloginfo( 'name' )
        ) ;?>
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
    <p class="description">
        <?php print sprintf(
            esc_html__( 'Paste your S/MIME public certificate here to have %1$s encrypt emails it sends you. Leave this blank if you do not want to get or know how to decrypt encrypted emails.', 'wp-pgp-encrypted-emails' ),
            get_bloginfo( 'name' )
        ) ;?>
    </p>
<?php if ( WP_PGP_Encrypted_Emails::getUserKey() && WP_PGP_Encrypted_Emails::getUserCert() ) { ?>
    <p>
        <select
            id="<?php print esc_attr( WP_PGP_Encrypted_Emails::meta_encryption_method ); ?>"
            name="<?php print esc_attr( WP_PGP_Encrypted_Emails::meta_encryption_method ); ?>">
            <option
                value="pgp"
                <?php selected( $wp_user->{WP_PGP_Encrypted_Emails::meta_encryption_method}, 'pgp' ); ?>
            >
                <?php esc_html_e( 'PGP/GPG', 'wp-pgp-encrypted-emails' ); ?>
            </option>
            <option
                value="smime"
                <?php selected( $wp_user->{WP_PGP_Encrypted_Emails::meta_encryption_method}, 'smime' ); ?>
            >
                <?php esc_html_e( 'S/MIME', 'wp-pgp-encrypted-emails' ); ?>
            </option>
        </select>
        <label for="<?php print esc_attr( WP_PGP_Encrypted_Emails::meta_encryption_method ); ?>">
            <?php esc_html_e( 'Your encryption method preference', 'wp-pgp-encrypted-emails' ); ?>
        </label>
    </p>
    <p class="description">
        <?php esc_html_e( 'When both PGP and S/MIME encryption are available, this option instructs the plugin which method to attempt first. If the chosen method fails, the other method is attempted.', 'wp-pgp-encrypted-emails' ); ?>
    </p>
<?php } ?>
    <p>
        <label>
            <input type="checkbox"
                id="<?php print esc_attr( WP_PGP_Encrypted_Emails::meta_key_empty_subject_line );?>"
                name="<?php print esc_attr( WP_PGP_Encrypted_Emails::meta_key_empty_subject_line );?>"
                <?php checked( $wp_user->{WP_PGP_Encrypted_Emails::meta_key_empty_subject_line} );?>
                value="1"
            />
            <?php esc_html_e('Always empty subject lines for encrypted emails', 'wp-pgp-encrypted-emails');?>
        </label>
    </p>
    <p class="description"><?php print sprintf(
        esc_html__('Email encryption cannot encrypt envelope information (such as the subject) of an email, so if you want maximum privacy, make sure this option is enabled to always erase the subject line from encrypted emails you receive.', 'wp-pgp-encrypted-emails')
    );?></p>
</fieldset>
<?php
    }

    /**
     * Filters the `openpgp_sign_email` hook to determine whether or
     * not to add an OpenPGP signature for this particular recipient.
     *
     * @param bool $do_sign Whether or not to sign this message.
     * @param string $to The email address to which this message is addressed.
     *
     * @return bool
     */
    public static function maybeSignEmail ( $do_sign, $to ) {
        $wp_user = get_user_by( 'email', $to );
        if ( in_array( 'customer', $wp_user->roles ) ) {
            return (bool) get_user_meta( $wp_user->ID, WP_PGP_Encrypted_Emails::meta_key_receive_signed_email, true );
        }
        return $do_sign;
    }

}

WP_PGP_Encrypted_Emails_WooCommerce::register();
