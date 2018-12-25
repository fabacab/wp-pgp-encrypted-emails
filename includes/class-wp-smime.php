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
     * S/MIME-specific MIME parameters for the Content-Type header
     * added by PHP's `openssl_pkcs7_encrypt()` function.
     *
     * This is used to store the current mail message's overrides to
     * WordPress's default `Content-Type` header processing, which is
     * sadly rather naive.
     *
     * @var string
     *
     * @see wp_mail()
     * @see self::encrypt()
     * @see self::filterContentType()
     */
    private static $media_type_parameters;

    /**
     * Registers WordPress plugin API hooks for other plugins.
     */
    public static function register () {
        add_filter( 'smime_certificate', array( __CLASS__, 'getCertificate' ) );
        add_filter( 'smime_certificate_pem_encode', array( __CLASS__, 'pemEncode' ) );
        add_filter( 'smime_pem_to_der', array( __CLASS__, 'pemToDer' ) );
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
     * Encodes a PEM-encoded (RFC 7468) string to its DER equivalent.
     *
     * PEM is two things: a header/footer labeling and a Base 64
     * encoding. Therefore, to go from a valid PEM format back to DER
     * (raw binary) representation of the same data, one need merely
     * strip the labels and base-64 decode the data. The process does
     * not verify the data is actually valid DER data, just that the
     * representation of it is correct.
     *
     * This means that if your input PEM data is a string containing
     * multiple objects (i.e., it has more than one pair of labels),
     * then this method may not actually work for your use case. For
     * safety, you should call this function only on a single object,
     * like one (and only one) certificate, or key, at a time.
     *
     * @see https://tools.ietf.org/html/rfc7468
     * @see https://en.wikipedia.org/wiki/X.690#DER_encoding
     *
     * @param string $pem_str Data that is PEM-encoded.
     *
     * @return string The same data, but in DER format.
     */
    public static function pemToDer ( $pem_str ) {
        $pem_lines = array_map( 'trim', explode( "\n", $pem_str ) );
        $der_lines = array();

        // Remove any lines that begin with five dashes.
        // (These are labels.)
        foreach ( $pem_lines as $pem_line ) {
            if ( 0 === strpos( $pem_line, '-----' ) ) {
                continue;
            } else {
                $der_lines[] = $pem_line;
            }
        }

        return base64_decode( implode( '', $der_lines ) );
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
        $infile  = tempnam( sys_get_temp_dir(), 'wp_email_' );
        $outfile = tempnam( sys_get_temp_dir(), 'wp_email_' );

        if ( false === $infile || false === $outfile ) {
            error_log( "Could not create temporary files!" );
            return false;
        }

        $plaintext  = ( is_array( $headers ) ) ? implode( "\n", $headers ) : $headers;
        $plaintext .= "\n\n" . $message;

        // If we have it available, use a better cipher than the default.
        // This will be available in PHP 5.4 or later.
        // See https://secure.php.net/manual/en/openssl.ciphers.php
        $cipher_id = ( defined( 'OPENSSL_CIPHER_AES_256_CBC' ) ) ? OPENSSL_CIPHER_AES_256_CBC : OPENSSL_CIPHER_RC2_40;

        if ( is_string( $headers ) ) {
            // PHP's openssl_pkcs7_encrypt expects headers as an array.
            $headers = explode( "\n", $headers );
        }

        // Write files for OpenSSL's encryption (which takes a file path).
        $written = file_put_contents( $infile, $plaintext );

        if ( false === $written ) {
            error_log( "Could not write plaintext!" );

            // try to delete before returning as some data may have been written
            self::safeDelete( $infile, $outfile );
            return false;
        }

        $smime = false;

        // Do the encryption.
        $encrypted = openssl_pkcs7_encrypt(
            $infile,
            $outfile,
            $certificates,
            // Remove any existing 'Content-Type: text/html' headers,
            // since `openssl_pkcs7_encrypt() generates its own and we
            // do not want to prepend these; they are intended for the
            // encrypted body, not the envelope.
            array_filter( $headers, array( __CLASS__, 'filterMailHeader' ) ),
            0,
            $cipher_id
        );
        if ( $encrypted ) {
            $smime = file_get_contents( $outfile );
        }

        // Immediately overwrite and delete the files written to disk.
        self::safeDelete( $infile, $outfile );

        if ( $smime ) {
            $parts = explode( "\n\n", $smime, 2 );
            $r = array(
                'headers' => $parts[0],
                'message' => $parts[1],
            );
            // WordPress doesn't like MIME headers that have complex
            // or unrecognized media type parameters, so we utilize
            // its `wp_mail_content_type` filter hook to stuff the
            // complete Content-Type header, with parameters, there.
            $m = array();
            if ( preg_match( '/Content-Type: application\/(?:x-)?pkcs7-mime(.*)/i', $r['headers'], $m ) ) {
                if ( isset( $m[1] ) ) {
                    self::$media_type_parameters = $m[1];
                    add_filter( 'wp_mail_content_type', array( __CLASS__, 'filterContentType' ) );
                }
            }
        } else {
            $r = false;
        }

        return $r;
    }

    /**
     * Filters an array of email headers
     *
     * When used with `array_filter()`, this function will remove
     * headers that contain the string `Content-Type: text/html`,
     * Empty elements (blank lines) are also removed.
     *
     * @param $h string The header line to filter.
     *
     * @return bool true if line is not filtered out, false otherwise.
     */
    private static function filterMailHeader( $h ) {
        return $h && false === stripos( $h, 'Content-Type: text/html' );
    }

    /**
     * Ensures S/MIME emails contain the correct Content-Type MIME
     * header as supplied by the underlying `openssl_pkcs7_encrypt()`
     * function call result.
     *
     * @param string $content_type
     *
     * @see https://developer.wordpress.org/reference/hooks/wp_mail_content_type/
     *
     * @uses self::$media_type_parameters
     */
    public static function filterContentType ( $content_type ) {
        // Retrieve the last `encrypt()`ion's media type result.
        $parameters = self::$media_type_parameters;

        // Don't retain this information for future invocations.
        self::$media_type_parameters = null;

        // Unhook ourselves.
        remove_filter( 'wp_mail_content_type', array( __CLASS__, 'filterContentType' ) );

        return $content_type . $parameters;
    }

    /**
     * Securely deletes one or more files by overwriting them.
     * Tries to handle possible errors gracefully to avoid content disclosure.
     *
     * This is by no means foolproof or highly secure, especially when used in a shared hosting scenario.
     * It's rather an attempt to make the best out of a very sub-optimal situation.
     *
     * @param array $files
     */
    private static function safeDelete( ...$files ) {

        foreach ( $files as $f ) {

            // clear stat cache to avoid falsely reported file status
            clearstatcache();

            if ( ! file_exists( $f ) || ! is_file( $f ) ) {
                // nothing we can do about this
                error_log( "File path is invalid!" );
                continue;
            }
            if ( ! is_writable( $f ) ) {
                error_log( "File is not writable!" );

                // cant overwrite, try to delete it
                if ( ! unlink( $f ) ) {
                    error_log( "Could not delete file!" );
                }
                continue;
            }

            // try to delegate the work to coreutils 'shred' for better performance
            // exists on almost all linux systems
            // https://www.gnu.org/software/coreutils/manual/html_node/shred-invocation.html#shred-invocation
            $handle = popen( 'shred --force --remove --zero ' . escapeshellarg( $f ), 'r' );

            if ( -1 !== pclose( $handle ) ) {
                clearstatcache();

                // unfortunately we can't get the original exit code of 'shred'
                if ( ! file_exists( $f ) ) {

                    // assume shredding was successful,
                    // continue with next file
                    continue;
                } else {
                    error_log( "Shredding was not successful!" );
                }
            } else {
                error_log( "Error while running 'shred'!" );
            }

            // determine file size
            $size = filesize( $f );

            // if FALSE, size must not be 0!
            if ( false === $size || $size < 0 ) {
                // set default size to 1 MiB
                $size = 1 * 1024 * 1024;
            }

            // try to overwrite 3 times
            for ( $i = 0; $i < 3; ++ $i ) {

                // randomly increase size
                try {
                    if ( $size > 0 ) {
                        $size += random_int( 1, $size * 2 );

                    } else if ( 0 === $size ) {
                        // file should be empty, no need to overwrite,
                        // but random_bytes() expects non-zero value
                        $size = 1;
                    }
                } catch ( Exception $e ) {
                    error_log( "Could not generate secure integer!" );

                    // fallback to insecure rand()
                    $size += rand( 1, $size * 2 );
                }

                $bytes = array();

                try {
                    $bytes = random_bytes( $size );

                } catch ( Exception $e ) {
                    error_log( "Could not generate random bytes!" );

                    // fallback, overwrite using zeroes
                    for ( $i = 0; $i < $size; ++$i ) {
                        $bytes[] = "0";
                    }
                }
                if ( empty( $bytes ) ) {
                    error_log( "Nothing to overwrite with!" );
                }
                if ( false === file_put_contents( $f, $bytes ) ) {
                    error_log( "Could not overwrite file! Try: {$i}" );
                }
            }

            // delete file
            if ( ! unlink( $f ) ) {
                error_log( "Could not delete file!" );
            }
        }

        // double check if all files were deleted
        foreach ( scandir( sys_get_temp_dir() ) as $f ) {
            if ( false !== stripos( $f, 'wp_email' ) ) {

                $path = sys_get_temp_dir() . '/' . $f;
                error_log( "Email file was not deleted: '{$path}' !" );
            }
        }
    }
}
