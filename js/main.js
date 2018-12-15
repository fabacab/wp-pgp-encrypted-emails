/**
 * Client-side JavaScript for WP PGP Encrypted Emails.
 */
(function () {
    var el_pubkey = document.getElementById('pgp_public_key');
    if ( '' === el_pubkey.value ) {
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
                userIds: [
                    {
                        name: document.getElementById('display_name').value,
                        email: document.getElementById('email').value
                    }
                ],
                numBits: 4096,
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
                var ascii_pubkey = key.publicKeyArmored;
                el_pubkey.value = ascii_pubkey.trim();
                localStorage.setItem('openpgp_privateKeyArmored', key.privateKeyArmored);
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
})();
