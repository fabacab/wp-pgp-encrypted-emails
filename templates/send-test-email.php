<?php
/**
 * Template for the "send a test email" button.
 */

if ( ! defined( 'ABSPATH' ) ) { return; } // Disallow direct HTTP access.
?>
<p>
    <a class="button"
        href="<?php
            $return_url = ( is_ssl() ) ? 'https://' : 'http://';
            $return_url .= "{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
            print esc_attr(
                wp_nonce_url(
                    admin_url( 'admin-ajax.php?action=wp_pgp_encrypted_emails_send_test_email&return_url=' . rawurlencode( $return_url ) ),
                    'wp_pgp_encrypted_emails_send_test_email',
                    'wp_pgp_encrypted_emails_send_test_email'
                )
            );
        ?>"
    ><?php esc_html_e( 'Send me a test email', 'wp-pgp-encrypted-emails' ); ?></a>
</p>
<p class="description"><?php print sprintf(
    esc_html__( 'After you save the desired settings, return here and press the "Send me a test email" button to have %1$s send a test email to you. Make sure you can read and verify this email in your inbox to confirm that everything is working correctly.', 'wp-pgp-encrypted-emails' ),
    get_bloginfo( 'name' )
);?></p>
