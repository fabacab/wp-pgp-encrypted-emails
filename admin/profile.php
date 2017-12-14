<h2><?php esc_html_e( 'Email Encryption', 'wp-pgp-encrypted-emails' ); ?></h2>
<table class="form-table">
    <tbody>
        <tr>
            <th>
                <label for="<?php print esc_attr( self::meta_key ); ?>">
                    <?php esc_html_e( 'Your PGP Public Key', 'wp-pgp-encrypted-emails' ); ?>
                </label>
            </th>
            <td>
                <textarea
                    id="<?php print esc_attr( self::meta_key ); ?>"
                    name="<?php print esc_attr( self::meta_key ); ?>"
                    class="large-text code"
                    rows="5"
                    ><?php print esc_textarea( $profileuser->{self::meta_key} ); ?></textarea>
                <p class="description">
                    <?php print sprintf(
                        esc_html__( 'Paste your PGP public key here to have %1$s encrypt emails it sends you. Leave this blank if you do not want to get or know how to decrypt encrypted emails.', 'wp-pgp-encrypted-emails' ),
                        get_bloginfo( 'name' )
                    ); ?>
                </p>
            </td>
        </tr>
<?php if ( function_exists( 'openssl_x509_read' ) ) { ?>
        <tr>
            <th>
                <label for="<?php print esc_attr( self::meta_smime_certificate ); ?>">
                    <?php esc_html_e( 'Your S/MIME Public Certificate', 'wp-pgp-encrypted-emails' ); ?>
                </label>
            </th>
            <td>
                <textarea
                    id="<?php print esc_attr( self::meta_smime_certificate ); ?>"
                    name="<?php print esc_attr( self::meta_smime_certificate ); ?>"
                    class="large-text code"
                    rows="5"
                    ><?php print esc_textarea( $profileuser->{self::meta_smime_certificate} ); ?></textarea>
                <p class="description">
                    <?php print sprintf(
                        esc_html__( 'Paste your S/MIME public certificate here to have %1$s encrypt emails it sends you. Leave this blank if you do not want to get or know how to decrypt encrypted emails.', 'wp-pgp-encrypted-emails' ),
                        get_bloginfo( 'name' )
                    ) ;?>
                </p>
            </td>
        </tr>
<?php
}

if ( self::getUserKey() && self::getUserCert() ) { ?>
        <tr>
            <th>
                <?php esc_html_e( 'Your encryption method preference', 'wp-pgp-encrypted-emails' ); ?>
            </th>
            <td>
                <select
                    id="<?php print esc_attr( self::meta_encryption_method ); ?>"
                    name="<?php print esc_attr( self::meta_encryption_method ); ?>">
                    <option
                        value="pgp"
                        <?php selected( $profileuser->{self::meta_encryption_method}, 'pgp' ); ?>
                    >
                        <?php esc_html_e( 'PGP/GPG', 'wp-pgp-encrypted-emails' ); ?>
                    </option>
                    <option
                        value="smime"
                        <?php selected( $profileuser->{self::meta_encryption_method}, 'smime' ); ?>
                    >
                        <?php esc_html_e( 'S/MIME', 'wp-pgp-encrypted-emails' ); ?>
                    </option>
                </select>
                <p class="description">
                    <?php esc_html_e( 'When both PGP and S/MIME encryption are available, this option instructs the plugin which method to attempt first. If the chosen method fails, the other method is attempted.', 'wp-pgp-encrypted-emails' ); ?>
                </p>
            </td>
        </tr>
<?php } ?>
        <tr>
            <th>
                <?php esc_html_e('Encrypted email subject lines', 'wp-pgp-encrypted-emails');?>
            </th>
            <td>
                <label for="<?php print esc_attr(self::meta_key_empty_subject_line);?>">
                    <input type="checkbox"
                        id="<?php print esc_attr(self::meta_key_empty_subject_line);?>"
                        name="<?php print esc_attr(self::meta_key_empty_subject_line);?>"
                        <?php checked($profileuser->{self::meta_key_empty_subject_line});?>
                        value="1"
                    />
                    <?php esc_html_e('Always empty subject lines for encrypted emails', 'wp-pgp-encrypted-emails');?>
                </label>
                <p class="description"><?php print sprintf(
                    esc_html__('Email encryption cannot encrypt envelope information (such as the subject) of an email, so if you want maximum privacy, make sure this option is enabled to always erase the subject line from encrypted emails you receive.', 'wp-pgp-encrypted-emails')
                );?></p>
            </td>
        </tr>
<?php
$kp = get_option( self::meta_keypair );
if ( ! empty( $kp['publickey'] ) ) {
?>
        <tr>
            <th>
                <?php print sprintf( esc_html__( 'PGP signing key for %s', 'wp-pgp-encrypted-emails' ), get_bloginfo( 'name' ) ); ?>
            </th>
            <td>
                <a class="button"
                    href="<?php print esc_attr( admin_url( 'admin-ajax.php?action=download_pgp_signing_public_key' ) ); ?>"
                ><?php esc_html_e( 'Download public key', 'wp-pgp-encrypted-emails' ); ?></a>
                <p class="description"><?php $lang = get_locale(); $lang = substr( $lang, 0, 2 ); print sprintf(
                    esc_html__( '%1$s sends digitally signed emails to help you verify that email you receive purporting to be from this website was in fact sent from this website. To authenticate the emails, download the PGP public key and import it to %2$san OpenPGP-compatible client%3$s.', 'wp-pgp-encrypted-emails' ),
                    get_bloginfo( 'name' ),
                    links_add_target( '<a href="https://prism-break.org/' . $lang . '/protocols/gpg/">' ), '</a>'
                );?></p>
            </td>
        </tr>
<?php } // endif ?>
        <tr id="wp-pgp-encrypted-emails-send-test-email">
            <th>
                <?php esc_html_e( 'Testing emails', 'wp-pgp-encrypted-emails' ); ?>
            </th>
            <td>
                <?php load_template( dirname( __FILE__ ) . '/../templates/send-test-email.php' ); ?>
            </td>
        </tr>
    </tbody>
</table>
