<?php
if (!defined('ABSPATH')) exit;

// One option array, 'evive_forms'. A form block may override the recipient; everything else is site-wide.
function evive_forms_defaults() {
    return [
        'notify_to'      => '',  // empty: the site's admin email
        'retention_days' => 90,  // entries older than this are deleted daily; 0 = keep no entries at all
    ];
}

function evive_forms_option($key) {
    $o = wp_parse_args((array) get_option('evive_forms', []), evive_forms_defaults());
    return $o[$key] ?? null;
}

// Comma-separated list of valid addresses, invalid ones dropped.
function evive_forms_clean_emails($list) {
    $out = [];
    foreach (preg_split('/[,;\s]+/', (string) $list) as $e) {
        $e = sanitize_email($e);
        if ($e && is_email($e)) $out[] = $e;
    }
    return implode(', ', array_unique($out));
}

function evive_forms_sanitize($in) {
    $in = (array) $in;
    return [
        'notify_to'      => evive_forms_clean_emails($in['notify_to'] ?? ''),
        'retention_days' => min(3650, max(0, (int) ($in['retention_days'] ?? 90))),
    ];
}

add_action('admin_init', function () {
    register_setting('evive_forms', 'evive_forms', ['sanitize_callback' => 'evive_forms_sanitize']);
});

add_action('admin_menu', function () {
    add_options_page('evive Forms', 'evive Forms', 'manage_options', 'evive-forms', 'evive_forms_render_settings');
});

function evive_forms_render_settings() {
    if (!current_user_can('manage_options')) return;
    $o = wp_parse_args((array) get_option('evive_forms', []), evive_forms_defaults());
    ?>
    <div class="wrap">
        <h1>evive Forms</h1>
        <p><?php esc_html_e('Add a form with the "Form" block. These settings apply to every form; a form can set its own recipient in the block settings.', 'evive-forms'); ?></p>
        <form method="post" action="options.php">
            <?php settings_fields('evive_forms'); ?>
            <table class="form-table">
                <tr><th scope="row"><label for="evive-forms-notify"><?php esc_html_e('Send entries to', 'evive-forms'); ?></label></th><td>
                    <input id="evive-forms-notify" class="regular-text" type="text" name="evive_forms[notify_to]" value="<?php echo esc_attr($o['notify_to']); ?>" placeholder="<?php echo esc_attr(get_option('admin_email')); ?>">
                    <p class="description"><?php esc_html_e('One or more addresses, separated by commas. Empty: the site\'s admin email.', 'evive-forms'); ?></p>
                </td></tr>
                <tr><th scope="row"><label for="evive-forms-retention"><?php esc_html_e('Keep entries for', 'evive-forms'); ?></label></th><td>
                    <input id="evive-forms-retention" class="small-text" type="number" min="0" max="3650" name="evive_forms[retention_days]" value="<?php echo esc_attr($o['retention_days']); ?>"> <?php esc_html_e('days', 'evive-forms'); ?>
                    <p class="description"><?php esc_html_e('Older entries are deleted automatically (privacy). 0 = do not keep entries, only send the email.', 'evive-forms'); ?></p>
                </td></tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <p><?php
            /* translators: %s: link to the plugin FluentSMTP */
            printf(esc_html__('Mail is sent with wp_mail(). For reliable delivery use an SMTP plugin such as %s.', 'evive-forms'), '<a href="https://wordpress.org/plugins/fluent-smtp/">FluentSMTP</a>');
        ?></p>
    </div>
    <?php
}
