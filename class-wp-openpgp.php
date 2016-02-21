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
            $encrypted = wordwrap(OpenPGP::enarmor($encrypted->to_bytes(), 'PGP MESSAGE'), 75, "\n", true);
        }
        return $encrypted;
    }

}
