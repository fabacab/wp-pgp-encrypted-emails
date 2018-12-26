/**
 * Client-side JavaScript for WP PGP Encrypted Emails.
 */
(function () {
    // Key generation routines.
    var el_pubkey = document.getElementById('pgp_public_key');
    if ( el_pubkey && '' === el_pubkey.value ) {
        var msg = 'We noticed you do not have an OpenPGP public key saved in your profile. Without this, we cannot send you private (encrypted) emails.';
        msg += ' We can generate a digital lock (called a "public key"),';
        msg += ' and a password-protected key for you to use (called a "private key").';
        msg += "\n\n";
        msg += 'Click "OK" to continue. Click "Cancel" to abort.';
        msg += "\n\n";
        msg += 'You will have a chance to export your private key at the end of this procedure.';
        msg += ' If you are using "Private Browsing" or "Incognito Browsing" mode, you MUST';
        msg += ' export a copy of your private key, or you will lose it when you close this tab.';
        if ( confirm(msg) ) {
            // Prepare OpenPGP.js options.
            var options = {
                'userIds': [
                    {
                        'name': document.getElementById('display_name').value,
                        'email': document.getElementById('email').value
                    }
                ],
                'numBits': 2048,
            };

            // Prompt for a passphrase to protect the private key.
            msg = 'Preparing to generate OpenPGP 4096-bit RSA keypair with the following options:';
            msg += "\n\n";
            msg += 'Name: ' + options.userIds[0].name;
            msg += "\n\n";
            msg += 'Email: ' + options.userIds[0].email;
            msg += "\n\n";
            msg += 'Passphrase:';
            var passphrase = prompt(msg);
            options.passphrase = passphrase.trim();

            // Generate the OpenPGP keypair.
            msg = 'Generating key.';
            msg += "\n\n";
            msg += 'This may take a minute, and your Web browser may appear frozen during this process.';
            alert(msg);
            openpgp.generateKey(options).then(function (key) {
                el_pubkey.value = key.publicKeyArmored;
                localStorage.setItem('openpgp_privateKeyArmored', key.privateKeyArmored);
                localStorage.setItem('openpgp_publicKeyArmored', key.publicKeyArmored);
                localStorage.setItem('openpgp_revocationCertificate', key.revocationCertificate);

                // Offer to download ("export") the generated private key.
                var b = new Blob([
                    localStorage.getItem('openpgp_privateKeyArmored')
                ], {'type': 'application/pgp-keys'});
                if ( confirm('Export private key now?') ) {
                    window.location = URL.createObjectURL(b);
                }

                alert('Key generation complete. Be sure to save your updated profile!');
            });
        }
    }

    // Decryption routine.
    var el_ciphertext  = document.getElementById('ciphertext');
    var el_decrypt_btn = document.getElementById('openpgpjs-decrypt');
    var el_verify_btn  = document.getElementById('openpgpjs-verify');

    // Decrypt when "Decrypt" button is pressed.
    if ( el_decrypt_btn ) {
        el_decrypt_btn.addEventListener('click', async function () {
            var ciphertext = el_ciphertext.value;
            var passphrase = prompt('Enter your private key passphrase.').trim();
            const privKeyObj = (await openpgp.key.readArmored(localStorage.getItem('openpgp_privateKeyArmored'))).keys[0];
            await privKeyObj.decrypt(passphrase);
            var options = {
                'message': await openpgp.message.readArmored(ciphertext),
                'privateKeys': [privKeyObj]
            };
            openpgp.decrypt(options).then(function (plaintext) {
                el_ciphertext.value = plaintext.data
            });
        });
    }

    // Verify when "Verify" button is pressed.
    if ( el_verify_btn ) {
        el_verify_btn.addEventListener('click', async function () {
            var ciphertext = el_ciphertext.value;
            var options = {
                'message': await openpgp.cleartext.readArmored(ciphertext),
                'publicKeys': (await openpgp.key.readArmored(document.getElementById('verification-public-key').value)).keys
            };
            console.log(options);
            openpgp.verify(options).then(function (verified) {
                console.log(verified);
                validity = verified.signatures[0].valid;
                if ( validity ) {
                    alert('Message is valid. Signed by Key ID: ' + verified.signatures[0].keyid.toHex());
                } else {
                    alert('Message could not be verified.');
                }
            });
        });
    }
})();
