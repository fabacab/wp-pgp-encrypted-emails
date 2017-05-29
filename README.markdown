# WP PGP Encrypted Emails - OpenPGP and S/MIME encryption for WordPress

[![Download from WordPress.org](https://img.shields.io/wordpress/plugin/dt/wp-pgp-encrypted-emails.svg)](https://wordpress.org/plugins/wp-pgp-encrypted-emails/) [![Current release at WordPress.org](https://img.shields.io/wordpress/plugin/v/wp-pgp-encrypted-emails.svg)](https://wordpress.org/plugins/wp-pgp-encrypted-emails/) [![Required WordPress version](https://img.shields.io/wordpress/v/wp-pgp-encrypted-emails.svg)](https://wordpress.org/plugins/wp-pgp-encrypted-emails/developers/) [![WP PGP Encrypted Emails is licensed GPL-3.0](https://img.shields.io/github/license/meitar/wp-pgp-encrypted-emails.svg)](https://www.gnu.org/licenses/quick-guide-gplv3.en.html)

A pure PHP [WordPress plugin](https://developer.wordpress.org/plugins/) that adds a simple [OpenPGP](http://openpgp.org/about/) and [S/MIME](https://en.wikipedia.org/wiki/S/MIME) API using familiar [WordPress filter hooks](https://developer.wordpress.org/plugins/hooks/filters/).

OpenPGP implementation is based on the [OpenPGP.php](https://github.com/singpolyma/openpgp-php) project. S/MIME support uses the ubiquitous [OpenSSL extension](https://secure.php.net/manual/book.openssl.php) for PHP.

## Cryptographic implementation notes

Beyond merely processing WordPress-generated email automatically (i.e., any email sent via [WordPress's built-in `wp_mail()` function](https://developer.wordpress.org/reference/functions/wp_mail/)), this plugin also provides an easy-to-use API to cryptographically secure operations for encrypting arbitrary data to protect data-at-rest or data-in-motion intended to be familiar to WordPress plugin and theme developers. This API ensures WordPress developers have ready access to otherwise potentially difficult and obscure mechanisms for protecting user data. My hope is that developers can therefor build more secure, more private coordination and communication tools atop WordPress without needing to become security gurus, themselves.

That said, *I am not a cryptographer* and have *not* implemented my own cryptographic routines. Instead, I have taken some pain to find and properly use the best pre-existing, well-vetted, professionally audited, and widely available libraries, packaged them into this plugin, and wrapped them with the aforementioned API. See the `class-wp-*.php` files in the [`includes/`](includes/) directory to see the code itself, or read the rest of this document for an English explanation of the same.

The two encryption schemes provided by this plugin are accessible as the [OpenPGP API](#openpgp-api) and the [S/MIME API](#smime-api).

### OpenPGP API

The OpenPGP API consists of the following WordPress filter hooks:

* `openpgp_key` - Turns a ASCII-armored OpenPGP key into a PHP (`OpenPGP_Message`) object.
* `openpgp_enarmor` - Takes a binary OpenPGP datagram and ASCII-armors it.
* `openpgp_encrypt` - Encrypts arbitary data with a provided OpenPGP key.
* `openpgp_sign` - Signs arbitrary data with a provided OpenPGP key.
* `openpgp_sign_and_encrypt` - The equivalent of doing both `openpgp_encrypt` and `openpgp_sign`.

See the "Other Notes" section in the user-facing [`readme.txt`](readme.txt) for usage details.

These filters are registered with WordPress during [the `init` action hook](https://developer.wordpress.org/reference/hooks/init/). This means you cannot use them until *after* various WordPress start-up routines have completed, so that you have access to WordPress's security-related functions such as [`wp_salt()`](https://developer.wordpress.org/reference/functions/wp_salt/). You are encouraged to learn about and use these in conjunction with this API, if you so wish.

The WP PGP Encrypted Emails plugin uses this same hook system itself, which means *any third-party code running in your WordPress install can hijack these routines*. That's *useful* for plugin developers—for example, you can use this to inspect data as it is `openpgp_enarmor`ed if you call `openpgp_sign`—but means you are responsible for ensuring hooked functions do not molest your private data. Then again, if you have malicious PHP code running on your server, you are already totally pwned. :(

The OpenPGP implementation uses [this OpenPGP.php library](https://github.com/singpolyma/openpgp-php), which by itself does not enforce best practices. So, while you *can* use that library directly after requiring this plugin, you are *strongly* encouraged to use the above API instead. The reason is because this API wraps the OpenPGP.php library calls and aggressively checks for common mistakes or outdated practices, throwing errors and generally making a fuss if you do not pass sensible values to the library underneath.

For example, while you can *theoretically* generate an RSA keypair of any bitlength, contemporary wisdom generally holds that bitlengths fewer than 2048 are not secure. Therefore, the API throws an `UnexpectedValueException` and immediately errors out if you try to use this API to generate unacceptably weak keys.

Similarly, there are *many* pitfalls and "gotchas" when implementing your own encryption schemes. Using this plugin's API, you are protected from common mistakes such as encrypting signed data instead of signing encrypted data. (The order of operations is significant.) Using the API's `openpgp_sign_and_encrypt` filter hook alleviates this concern. Similarly, using `openpgp_enarmor` avoids some rather obscure (and annoying) compatibility problems as it is written to strictly follow the [OpenPGP Message RFC](https://tools.ietf.org/html/rfc4880).

### S/MIME API

The S/MIME API consists of the following WordPress filter hooks:

* `smime_certificate` - Retrieves a usable PHP `resource` of type `OpenSSL X.509` from some appropriately-formatted data.
* `smime_certificate_pem_encode` - Converts an `OpenSSL X.509` resource into a [PEM](https://en.wikipedia.org/wiki/Privacy-enhanced_Electronic_Mail)-encoded string.
* `smime_encrypt` - Performs the actual encryption given a message and an user's certificate.
* `smime_pem_to_der` - A convenience function to convert a PEM-encoded object to its [(X.690) DER](https://en.wikipedia.org/wiki/X.690#DER_encoding) equivalent.

Again, see the "Other Notes" section in the user-facing [`readme.txt`](readme.txt) for usage details.

As with OpenPGP, these filters are registered with WordPress during [the `init` action hook](https://developer.wordpress.org/reference/hooks/init/), but only if PHP has access to [the OpenSSL PHP extension](https://secure.php.net/manual/book.openssl.php), since this API provides a wrapper around its functions. The important operation here is, of course, `smime_encrypt`. You are strongly encouraged to use this filter hook instead of relying on the `openssl_*` functions directly because doing so enforces best practices and will automatically upgrade to the strongest available cipher modes based on your specific PHP execution environment.

This API uses the `openssl_pkcs7_encrypt()` function under the hood, but automatically detects and uses non-default options to further strengthen the encryption process. Specifically, it uses `OPENSSL_CIPHER_AES_256_CBC` if your PHP supports it. It also unconditionally and aggressively overwrites plaintext and even encrypted data storage locations (files) to help ensure no sensitive information remains on the system after encryption regardless of who the caller is.

These additional checks are not always considered by developers intending to perform security-sensitive operations and so, again, you are encouraged to make use of this API instead of rolling your own data encrypting routines.

## Handling key material

This plugin makes no additional attempt to protect key material from other running code because its *intent* is to provide cryptographic "primitives" to be used by other plugins or themes. As such, potentially sensitive key material is stored in easily-accessible places. This is generally fine, because the plugin's interface takes pains to prevent the storage and disclosure of a user's *private* key material and only accepts *public* key material. The exception to this is with the site's own "signing key," which is by definition private key material. Even this, however, has additional checks to enforce the use of TLS-secured connections (HTTPS), and the plugin will refuse to export private key material over unsecured (HTTP) connections, even at the expense of user-friendliness for administrative users. (Sorry, not sorry. Get your site using [LetsEncrypt](https://letsencrypt.org/) as soon as possible.)

> :construction: Note that some parts of this enforcement still need a better user interface. :(

A user's key material will be stored as part of their WordPress profile information and is therefore accessible to other running code. However, you are strongly encouraged to use the following WordPress filters provided by this plugin instead of directly accessing the user's metadata.

* `wp_openpgp_get_key` - To retrieve the user's OpenPGP public key.
* `wp_smime_get_certificate` - To retrieve the user's S/MIME public certificate.

Both these filters automatically invoke the `openpgp_key` or `smime_certificate` filters so that they `return` native PHP objects rather than raw strings. You can then immediately use the results in further operations. This radically simplifies the process from plaintext to successful encryption, as shown here using both schemes:

```php
// Get the key material.
$wp_user    = get_user_by( 'email', 'example.user@example.com' );     // `$wp_user` is now a `WP_User` object.
$public_key = apply_filters( 'wp_openpgp_user_key', $wp_user );       //< The OpenPGP public key for this user.
$smime_cert = apply_filters( 'wp_smime_user_certificate', $wp_user ); //< The S/MIME certificate for this user.

// Compose a message to encrypt.
$message = 'This is a test.';

// Do the encryption.
$pgp_encrypted_message   = apply_filters( 'openpgp_encrypt', $message, $public_key );
$smime_encrypted_message = apply_filters( 'smime_encrypt', $message, array(), $smime_cert ); //< Empty `array()` means no extra MIME-formatted headers.
```

This way, each WordPress user is able to indicate to you (and your plugin) that they wish to use one (or both) of the secure communication protocols widely deployed today. The API also makes implementing both schemes in your own code effectively identical. All of the differences between OpenPGP and S/MIME encryption are taken care of for you in as secure a manner as I know how.

If you want to support both OpenPGP and S/MIME *and* a given user has provided *both* an OpenPGP public key and an S/MIME certificate, you should additionally use the plugin's `wp_user_encryption_method` filter:

```php
$preferred_method = apply_filters( 'wp_user_encryption_method', $wp_user );
if ( 'pgp' === $preferred_method ) {
    print 'This user preferrs to use OpenPGP.';
} else if ( 'smime' === $preferred_method ) {
    print 'This user preferrs to use S/MIME.';
}
```

Obviously, if a given user only has an OpenPGP public key, or only has an S/MIME certificate, then use that method to communicate with them since you cannot use the other. ;)

## Disclaimer and bugs

Please [email me](mailto:meitarm@gmail.com) directly to report security bugs. I am not a professional (in any capacity; see also "[I quit, Because Capitalism](https://maymay.net/blog/2013/06/14/i-quit-because-capitalism/)"). I am just someone who cares about this shit and I'm doing my best, especially given the fact that I am not compensated financially for this work.

Patches, of course, are sincerely welcomed. :) (So are [donations](https://www.paypal.com/cgi-bin/webscr?cmd=_donations&amp;business=TJLPJYXHSRBEE&amp;lc=US&amp;item_name=WP%20PGP%20Encrypted%20Emails&amp;item_number=wp-pgp-encrypted-emails&amp;currency_code=USD&amp;bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted).)
