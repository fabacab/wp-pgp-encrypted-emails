<h2><?php esc_html_e('PGP/GPG Encryption', 'wp-pgp-encrypted-emails');?></h2>
<table class="form-table">
    <tbody>
        <tr>
            <th>
                <label for="<?php print esc_attr(self::$meta_key);?>">
                    <?php esc_html_e('PGP Public Key', 'wp-pgp-encrypted-emails');?>
                </label>
            </th>
            <td>
                <textarea
                    id="<?php print esc_attr(self::$meta_key);?>"
                    name="<?php print esc_attr(self::$meta_key)?>"
                    ><?php print esc_textarea($profileuser->{self::$meta_key});?></textarea>
                <p class="description">
                    <?php print sprintf(
                        esc_html__('Paste your PGP public key here to have WordPress encrypt emails it sends you. Leave this blank if you do not want to get or know how to decrypt encrypted emails.', 'wp-pgp-encrypted-emails')
                    );?>
                </p>
            </td>
        </tr>
        <tr>
            <th>
                <?php esc_html_e('PGP email subject lines', 'wp-pgp-encrypted-emails');?>
            </th>
            <td>
                <label for="<?php print esc_attr(self::$meta_key_empty_subject_line);?>">
                    <input type="checkbox"
                        id="<?php print esc_attr(self::$meta_key_empty_subject_line);?>"
                        name="<?php print esc_attr(self::$meta_key_empty_subject_line);?>"
                        <?php checked($profileuser->{self::$meta_key_empty_subject_line});?>
                        value="1"
                    />
                    <?php esc_html_e('Always empty subject lines for PGP-encrypted emails', 'wp-pgp-encrypted-emails');?>
                </label>
                <p class="description"><?php print sprintf(
                    esc_html__('PGP encryption cannot encrypt envelope information (such as the subject) of an email, so if you want maximum privacy, make sure this option is enabled to always erase the subject line from encrypted emails you receive.', 'wp-pgp-encrypted-emails')
                );?></p>
            </td>
        </tr>
<?php
$kp = get_option(self::$meta_keypair);
if (!empty($kp['publickey'])) {
?>
        <tr>
            <th>
                <?php print sprintf(esc_html__('PGP signing key for %s', 'wp-pgp-encrypted-emails'), get_bloginfo('name'));?>
            </th>
            <td>
                <a class="button"
                    href="<?php print esc_attr(admin_url('admin-ajax.php?action=download_pgp_signing_public_key'));?>"
                ><?php esc_html_e('Download public key', 'wp-pgp-encrypted-emails');?></a>
                <p class="description"><?php $lang = get_locale(); $lang = substr($lang, 0, 2); print sprintf(
                    esc_html__('%1$s sends digitally signed emails to help you verify that email you receive purporting to be from this website was in fact sent from this website. To authenticate the emails, download the PGP public key and import it to %2$san OpenPGP-compatible client%3$s.', 'wp-pgp-encrypted-emails'),
                    get_bloginfo('name'),
                    '<a href="https://prism-break.org/'.$lang.'/protocols/gpg/" target="_blank">', '</a>'
                );?></p>
            </td>
        </tr>
<?php } // endif ?>
    </tbody>
</table>
