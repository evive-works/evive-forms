<?php
if (!defined('ABSPATH')) exit;

// Submissions go to admin-post.php, for visitors with and without JavaScript. The script sends the header
// "X-Evive-Forms: 1" and gets JSON back; a plain form post is redirected back to the page with the result in the URL.
// No nonce: pages with forms are usually cached, and the definition hash plus the spam checks below do that job.

const EVIVE_FORMS_RATE_LIMIT  = 5;   // submissions per IP per window
const EVIVE_FORMS_RATE_WINDOW = 600; // seconds
const EVIVE_FORMS_MIN_SECONDS = 3;   // a human needs at least this long to fill in a form

add_action('admin_post_nopriv_evive_forms_submit', 'evive_forms_handle_submit');
add_action('admin_post_evive_forms_submit', 'evive_forms_handle_submit');

function evive_forms_handle_submit() {
    $hash = sanitize_text_field(wp_unslash($_POST['evive_form'] ?? ''));
    $page = esc_url_raw(wp_unslash($_POST['evive_page'] ?? ''));
    $def  = evive_forms_get_definition($hash);
    if (!$def) evive_forms_respond(false, ['_form' => __('This form is no longer available. Please reload the page.', 'evive-forms')], $hash, $page);

    // Spam checks answer like a success, so bots learn nothing; the entry is simply dropped.
    if (evive_forms_is_spam()) evive_forms_respond(true, [], $hash, $page);
    if (!evive_forms_rate_ok()) evive_forms_respond(false, ['_form' => __('Too many messages in a short time. Please try again later.', 'evive-forms')], $hash, $page);

    [$values, $errors] = evive_forms_validate($def, wp_unslash($_POST));
    if ($errors) evive_forms_respond(false, $errors, $hash, $page);

    $entry_id = evive_forms_store_entry($def, $values, $page);
    evive_forms_send_mails($def, $values, $page);

    /**
     * After a valid submission (entry stored, mails sent). For integrations such as a newsletter service or a webhook.
     *
     * @param int   $entry_id 0 when entries are not kept.
     * @param array $values   name => value, as validated.
     * @param array $def      the form definition (title, fields, ...).
     * @param string $page    the page the form was sent from.
     */
    do_action('evive_forms_submitted', $entry_id, $values, $def, $page);
    evive_forms_respond(true, [], $hash, $page);
}

function evive_forms_is_spam() {
    if (trim((string) ($_POST['evive_website'] ?? '')) !== '') return true;
    // evive_t is the fill time in seconds, set by the script. Without JavaScript it is empty and not held against anyone.
    $t = (string) ($_POST['evive_t'] ?? '');
    return $t !== '' && (int) $t < EVIVE_FORMS_MIN_SECONDS;
}

function evive_forms_rate_ok() {
    $ip  = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $key = 'evive_forms_rl_' . substr(hash_hmac('sha256', $ip, wp_salt('nonce')), 0, 16); // no IP stored
    $n   = (int) get_transient($key);
    if ($n >= EVIVE_FORMS_RATE_LIMIT) return false;
    set_transient($key, $n + 1, EVIVE_FORMS_RATE_WINDOW);
    return true;
}

// Only fields in the saved definition are read; anything else in the post is ignored.
function evive_forms_validate($def, $post) {
    $values = [];
    $errors = [];
    foreach ($def['fields'] as $name => $f) {
        $raw = $post[$name] ?? '';
        if (is_array($raw)) $raw = '';
        switch ($f['type']) {
            case 'checkbox':
            case 'consent':
                $v = $raw !== '' ? __('Yes', 'evive-forms') : '';
                break;
            case 'textarea':
                $v = substr(sanitize_textarea_field($raw), 0, 5000);
                break;
            case 'email':
                $v = substr(sanitize_email($raw), 0, 200);
                if ($v !== '' && !is_email($v)) $errors[$name] = __('Please enter a valid email address.', 'evive-forms');
                if ($v === '' && trim($raw) !== '') $errors[$name] = __('Please enter a valid email address.', 'evive-forms');
                break;
            case 'tel':
                $v = substr(sanitize_text_field($raw), 0, 40);
                if ($v !== '' && !preg_match('/^[0-9 +().\-\/]{6,40}$/', $v)) $errors[$name] = __('Please enter a valid phone number.', 'evive-forms');
                break;
            case 'select':
                $v = sanitize_text_field($raw);
                if ($v !== '' && !in_array($v, $f['options'], true)) $v = '';
                break;
            default:
                $v = substr(sanitize_text_field($raw), 0, 300);
        }
        if ($f['required'] && $v === '' && !isset($errors[$name])) {
            $errors[$name] = $f['type'] === 'consent' ? __('Please give your consent to send this form.', 'evive-forms') : __('This field is required.', 'evive-forms');
        }
        $values[$name] = $v;
    }
    return [$values, $errors];
}

// The first email field is the sender: used for Reply-To and the confirmation mail.
function evive_forms_sender_email($def, $values) {
    foreach ($def['fields'] as $name => $f) {
        if ($f['type'] === 'email' && !empty($values[$name])) return $values[$name];
    }
    return '';
}

function evive_forms_mail_body($def, $values, $page) {
    $lines = [];
    foreach ($def['fields'] as $name => $f) {
        $lines[] = $f['label'] . ': ' . ($values[$name] !== '' ? $values[$name] : '-');
    }
    $lines[] = '';
    /* translators: %s: URL of the page with the form */
    $lines[] = sprintf(__('Sent from: %s', 'evive-forms'), $page ?: home_url('/'));
    return implode("\n", $lines);
}

function evive_forms_send_mails($def, $values, $page) {
    $site   = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
    $to     = $def['notify_to'] ?: (evive_forms_option('notify_to') ?: get_option('admin_email'));
    $sender = evive_forms_sender_email($def, $values);
    $headers = $sender ? ['Reply-To: ' . $sender] : [];
    /* translators: 1: site name, 2: form title */
    wp_mail($to, sprintf(__('[%1$s] New entry: %2$s', 'evive-forms'), $site, $def['title']), evive_forms_mail_body($def, $values, $page), $headers);

    if ($def['confirmation'] && $sender) {
        /* translators: %s: site name */
        $subject = $def['confirmation_subject'] ?: sprintf(__('Thank you for your message to %s', 'evive-forms'), $site);
        $message = $def['confirmation_message'] ?: __('Thank you for your message. We will get back to you soon.', 'evive-forms');
        wp_mail($sender, $subject, $message . "\n\n" . evive_forms_mail_body($def, $values, ''));
    }
}

function evive_forms_respond($ok, $errors, $hash, $page) {
    if (!empty($_SERVER['HTTP_X_EVIVE_FORMS'])) {
        wp_send_json(['ok' => $ok, 'errors' => (object) $errors], $ok ? 200 : 422);
    }
    $back = wp_validate_redirect($page, home_url('/'));
    $back = add_query_arg(['evive_form' => $hash, 'evive_status' => $ok ? 'sent' : 'error'], $back);
    wp_safe_redirect($back . '#' . rawurlencode('evive-form-' . $hash), 303);
    exit;
}
