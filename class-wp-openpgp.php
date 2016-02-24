<?php
/**
 * WP OpenPGP, a WordPress interface to OpenPGP
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * @copyright Copyright (c) 2016 by Meitar "maymay" Moscovitz
 *
 * @package WordPress\Plugin\WP_PGP_Encrypted_Emails\WP_OpenPGP
 */

if (!defined('ABSPATH')) { exit; } // Disallow direct HTTP access.

// Load dependencies.
if (!class_exists('OpenPGP')) {
    require_once plugin_dir_path(__FILE__).'vendor/openpgp-php/vendor/autoload.php';
    require_once plugin_dir_path(__FILE__).'vendor/openpgp-php/openpgp.php';
    require_once plugin_dir_path(__FILE__).'vendor/openpgp-php/openpgp_crypt_rsa.php';
    require_once plugin_dir_path(__FILE__).'vendor/openpgp-php/openpgp_crypt_symmetric.php';
}

/**
 * Main class for OpenPGP operations in WordPress.
 */
class WP_OpenPGP {

    /**
     * Registers WordPress plugin API hooks for other plugins.
     */
    public static function register () {
        add_filter('openpgp_enarmor', array(__CLASS__, 'enarmor'), 10, 3);
        add_filter('openpgp_encrypt', array(__CLASS__, 'encrypt'), 10, 3);
        add_filter('openpgp_key', array(__CLASS__, 'getKey'));
    }

    /**
     * Gets an OpenPGP public key.
     *
     * @param bool $ascii Whether or not the key is ASCII-armored.
     *
     * @return OpenPGP_Message|false
     */
    public static function getKey ($key, $ascii = true) {
        if ($ascii) {
            $key = OpenPGP::unarmor($key, 'PGP PUBLIC KEY BLOCK');
        }
        $openpgp_msg = OpenPGP_Message::parse($key);
        return (is_null($openpgp_msg)) ? false : $openpgp_msg;
    }

    /**
     * Encrypts data to a PGP public key, passphrase, or set of passphrases or keys.
     *
     * @param string $data
     * @param string|array|OpenPGP_Message $keys A passphrase (as a `string`), a PGP public key, or an array of these.
     * @param bool $armor
     *
     * @return string
     */
    public static function encrypt ($data, $keys, $armor = true) {
        $plain_data = new OpenPGP_LiteralDataPacket($data, array(
            'format' => 'u', 'filename' => 'encrypted.gpg'
        ));
        $encrypted = OpenPGP_Crypt_Symmetric::encrypt($keys, new OpenPGP_Message(array($plain_data)));
        if ($armor) {
            $encrypted = apply_filters('openpgp_enarmor', $encrypted->to_bytes(), 'PGP MESSAGE');
        }
        return $encrypted;
    }

    /**
     * ASCII-armors a value.
     *
     * This function wraps the `OpenPGP::enarmor()` method and offers
     * a WordPress filter hook (`openpgp_enarmor`) to plugin API calls.
     *
     * @param string $data
     * @param string $marker
     * @param array $headers
     *
     * @link https://singpolyma.github.io/openpgp-php/classOpenPGP.html#aa9d90195277e4c9d435ea70488f89c83
     *
     * @return string
     */
    public static function enarmor ($data, $marker = 'MESSAGE', $headers = array()) {
        return wordwrap(OpenPGP::enarmor($data, $marker, $headers), 75, "\n", true);
    }

    /**
     * Generates a new private/public PGP keypair.
     *
     * @param string $identity The identity to associate with the key, in `Name <name@example.com>` form.
     * @param int $bits
     *
     * @uses Crypt_RSA::createKey()
     * @uses Crypt_RSA::loadKey()
     * @uses OpenPGP_SecretKeyPacket::__construct()
     * @uses OpenPGP_UserIDPacket::__construct()
     * @uses OpenPGP_Crypt_RSA::__construct()
     * @uses OpenPGP_Crypt_RSA::sign_key_userid()
     * @uses OpenPGP_Crypt_RSA::to_bytes()
     * @uses OpenPGP_PublicKeyPacket::__construct()
     * @uses OpenPGP_PublicKeyPacket::to_bytes()
     *
     * @return OpenPGP_Message[]
     */
    public static function generateKeypair ($identity, $bits = 4096) {
        if (2048 > $bits) {
            $error_msg = 'RSA keys with less than 2048 bits are unacceptable.';
            throw new UnexpectedValueException($error_msg);
        }

        $keypair = array();

        // FYI, I'm (mostly) following the example at
        // https://github.com/singpolyma/openpgp-php/blob/master/examples/keygen.php
        // but would LOOOOOVE to have someone more knowledgeable than
        // I am about this stuff double-check me here. Patches welcome!

        $rsa = new Crypt_RSA(); // This is the line that causes the error.
        $k = $rsa->createKey($bits);
        $rsa->loadKey($k['privatekey']);

        $nkey = new OpenPGP_SecretKeyPacket(array(
            'n' => $rsa->modulus->toBytes(),
            'e' => $rsa->publicExponent->toBytes(),
            'd' => $rsa->exponent->toBytes(),
            'p' => $rsa->primes[1]->toBytes(),
            'q' => $rsa->primes[2]->toBytes(),
            'u' => $rsa->coefficients[2]->toBytes()
        ));
        $uid = new OpenPGP_UserIDPacket($identity);

        $wkey = new OpenPGP_Crypt_RSA($nkey);
        $m = $wkey->sign_key_userid(array($nkey, $uid));

        $keypair['privatekey'] = $m->to_bytes();

        $pubm = clone($m);
        $pubm[0] = new OpenPGP_PublicKeyPacket($pubm[0]);

        $keypair['publickey'] = $pubm->to_bytes();

        return $keypair;
    }

}
