<?php
/**
 * WP SMIME, a WordPress interface to S/MIME
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * @copyright Copyright (c) 2017 by Meitar "maymay" Moscovitz
 *
 * @package WordPress\Plugin\WP_PGP_Encrypted_Emails\WP_OpenPGP
 */

if ( ! defined( 'ABSPATH' ) ) { exit; } // Disallow direct HTTP access.

/**
 * Main class for S/MIME operations in WordPress.
 */
class WP_SMIME {

    /**
     * Registers WordPress plugin API hooks for other plugins.
     */
    public static function register () {
        add_filter( 'smime_certificate', array( __CLASS__, 'getCertificate' ) );
        add_filter( 'smime_certificate_pem_encode', array( __CLASS__, 'pemEncode' ) );
        add_filter( 'smime_encrypt', array( __CLASS__, 'encrypt'), 10, 4 );
    }

    /**
     * Gets an X.509 Certificate handle.
     *
     * @param mixed $cert The certificate.
     *
     * @see https://secure.php.net/manual/en/openssl.certparams.php
     *
     * @return resource|FALSE
     */
    public static function getCertificate ( $cert ) {
        $r = @openssl_x509_read( $cert );
        if ( is_resource( $r ) && 'OpenSSL X.509' === get_resource_type( $r ) ) {
            return $r;
        }
        return false;
    }

    /**
     * Encodes ("exports") a given X.509 certificate as PEM format.
     *
     * @param resource $cert
     *
     * @return string|FALSE
     */
    public static function pemEncode ( $cert ) {
        $r = null;
        return ( openssl_x509_export( $cert, $r ) )
            ? $r
            : false;
    }

    /**
     * Encrypts a message as an S/MIME email given a public certificate.
     *
     * @param string $message The message contents to encrypt.
     * @param string|string[] $headers The message headers for the encrypted part.
     * @param resource|array $certificates The recipient's certificate, or an array of recipient certificates.
     *
     * @return array|FALSE An array with two keys, `headers` and `message`, wherein the message is encrypted.
     */
    public static function encrypt ( $message, $headers, $certificates ) {
        if ( is_string( $headers ) ) {
            // PHP's openssl_pkcs7_encrypt expects headers as an array.
            $headers = explode( "\n", $headers );
        }

        $infile  = tempnam( '/tmp', 'wp_email_' );
        $outfile = $infile . '.enc';

        // Set headers in the encrypted part.
        $plaintext = $headers . "\n\n" . $message;

        // Write files for OpenSSL's encryption (which takes a file path).
        file_put_contents( $infile, $plaintext );

        // If we have it available, use a better cipher than the default.
        // This will be available in PHP 5.4 or later.
        // See https://secure.php.net/manual/en/openssl.ciphers.php
        $cipher_id = ( defined( 'OPENSSL_CIPHER_AES_256_CBC' ) ) ? OPENSSL_CIPHER_AES_256_CBC : OPENSSL_CIPHER_RC2_40;

        // Do the encryption.
        if ( openssl_pkcs7_encrypt( $infile, $outfile, $certificates, $headers, 0, $cipher_id ) ) {
            $smime = file_get_contents( $outfile );
        }

        // Immediately overwrite and delete the files written to disk.
        $fs = (int) filesize( $infile ); // cast to int to avoid FALSE
        file_put_contents( $infile, random_bytes( $fs + random_int( 0, $fs * 2 ) ) );
        unlink( $infile );
        $fs = (int) filesize( $outfile );
        file_put_contents( $outfile, random_bytes( $fs + random_int( 0, $fs * 2 ) ) );
        unlink( $outfile );

        if ( $smime ) {
            $parts   = explode( "\n\n", $smime, 2 );
            $r = array(
                'headers' => $parts[0],
                'message' => $parts[1],
            );
        } else {
            $r = false;
        }

        return $r;
    }
}
