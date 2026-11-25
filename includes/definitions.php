<?php
if (!defined('ABSPATH')) exit;

// A form's definition (its fields and settings) is taken from the saved blocks when the form is rendered and kept
// server-side under a hash. The page only carries that hash, so a submission is validated against what the editor
// saved, never against what the browser sends, and the recipient address never appears in the HTML. Works for forms
// in pages, synced patterns and template parts alike, and with full-page caching.

const EVIVE_FORMS_FIELD_TYPES = ['text', 'email', 'tel', 'textarea', 'select', 'checkbox', 'consent', 'hidden'];
// 0.2.0: what a hidden field is filled with in the browser (first visit of the session; no cookies), or a fixed value.
const EVIVE_FORMS_SOURCES = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'referrer', 'landing_page', 'fixed'];

function evive_forms_field_source($attrs) {
    $s = (string) ($attrs['source'] ?? '');
    if (in_array($s, EVIVE_FORMS_SOURCES, true)) return $s;
    if (preg_match('/^query:[a-z0-9_-]{1,40}$/i', $s)) return strtolower($s);
    // A hidden field without a source but named like one (utm_source, referrer, ...) uses that name.
    $n = evive_forms_field_name($attrs);
    return in_array($n, EVIVE_FORMS_SOURCES, true) ? $n : ((string) ($attrs['value'] ?? '') !== '' ? 'fixed' : '');
}
const EVIVE_FORMS_MAX_DEFS    = 200;

// Field name as used in the HTML and in entries: the name set in the editor, else derived from label or type.
function evive_forms_field_name($attrs) {
    $name = sanitize_key($attrs['name'] ?? '');
    if ($name === '') $name = sanitize_key(str_replace(' ', '_', remove_accents(wp_strip_all_tags($attrs['label'] ?? ''))));
    if ($name === '') $name = sanitize_key($attrs['type'] ?? 'field');
    return substr($name, 0, 40);
}

function evive_forms_field_type($attrs) {
    $type = $attrs['type'] ?? 'text';
    return in_array($type, EVIVE_FORMS_FIELD_TYPES, true) ? $type : 'text';
}

// Field blocks may sit anywhere inside the form (also inside columns or groups).
function evive_forms_collect_fields($blocks, &$fields) {
    foreach ((array) $blocks as $b) {
        if (($b['blockName'] ?? '') === 'evive/field') {
            $a    = $b['attrs'] ?? [];
            $name = evive_forms_field_name($a);
            if (isset($fields[$name])) continue; // the editor keeps names unique; a duplicate is ignored
            $fields[$name] = [
                'type'     => evive_forms_field_type($a),
                'label'    => trim(wp_strip_all_tags($a['label'] ?? '')) ?: $name,
                'required' => !empty($a['required']) || evive_forms_field_type($a) === 'consent',
                'options'  => array_values(array_filter(array_map('trim', (array) ($a['options'] ?? [])), 'strlen')),
            ];
            if ($fields[$name]['type'] === 'hidden') {
                $fields[$name]['required'] = false;
                $fields[$name]['source']   = evive_forms_field_source($a);
                $fields[$name]['value']    = substr(sanitize_text_field($a['value'] ?? ''), 0, 200);
            }
        }
        if (!empty($b['innerBlocks'])) evive_forms_collect_fields($b['innerBlocks'], $fields);
    }
}

function evive_forms_build_definition($attrs, $inner_blocks) {
    $fields = [];
    evive_forms_collect_fields($inner_blocks, $fields);
    // 0.2.0: the small first-visit script (campaign source, landing page) runs site-wide once a form uses it.
    foreach ($fields as $f) {
        if ($f['type'] === 'hidden' && $f['source'] !== '' && $f['source'] !== 'fixed' && !get_option('evive_forms_capture')) {
            update_option('evive_forms_capture', 1);
        }
    }
    return [
        'title'                => trim(wp_strip_all_tags($attrs['formTitle'] ?? '')) ?: __('Contact form', 'evive-forms'),
        // 'recipient' is accepted as another name for notifyTo (0.2.0: forms written by evive used it).
        'notify_to'            => evive_forms_clean_emails(($attrs['notifyTo'] ?? '') !== '' ? $attrs['notifyTo'] : ($attrs['recipient'] ?? '')),
        'success'              => trim(wp_kses_post($attrs['successMessage'] ?? '')),
        'confirmation'         => !empty($attrs['confirmation']),
        'confirmation_subject' => trim(wp_strip_all_tags($attrs['confirmationSubject'] ?? '')),
        'confirmation_message' => trim(wp_strip_all_tags($attrs['confirmationMessage'] ?? '')),
        'fields'               => $fields,
    ];
}

// Stores the definition (once) and returns its hash.
function evive_forms_store_definition($def) {
    $hash = substr(hash_hmac('sha256', wp_json_encode($def), wp_salt('auth')), 0, 20);
    $defs = (array) get_option('evive_forms_defs', []);
    if (!isset($defs[$hash])) {
        $defs[$hash] = $def;
        if (count($defs) > EVIVE_FORMS_MAX_DEFS) $defs = array_slice($defs, -EVIVE_FORMS_MAX_DEFS, null, true);
        update_option('evive_forms_defs', $defs, false);
    }
    return $hash;
}

function evive_forms_get_definition($hash) {
    if (!is_string($hash) || !preg_match('/^[a-f0-9]{20}$/', $hash)) return null;
    $defs = (array) get_option('evive_forms_defs', []);
    return $defs[$hash] ?? null;
}
