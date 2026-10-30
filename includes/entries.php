<?php
if (!defined('ABSPATH')) exit;

// Entries are a private post type: listed under "Form entries", readable only by administrators (filter
// 'evive_forms_capability'), never created by hand and deleted automatically after the retention period.
// Meta: _evive_form (form title), _evive_fields (list of [label, value]), _evive_email (sender), _evive_page.

function evive_forms_capability() {
    return apply_filters('evive_forms_capability', 'manage_options');
}

add_action('init', function () {
    $cap = evive_forms_capability();
    register_post_type('evive_form_entry', [
        'labels'       => [
            'name'          => __('Form entries', 'evive-forms'),
            'singular_name' => __('Form entry', 'evive-forms'),
            'menu_name'     => __('Form entries', 'evive-forms'),
            'all_items'     => __('Form entries', 'evive-forms'),
            'edit_item'     => __('Form entry', 'evive-forms'),
            'not_found'     => __('No entries yet.', 'evive-forms'),
            'search_items'  => __('Search entries', 'evive-forms'),
        ],
        'public'       => false,
        'show_ui'      => true,
        'show_in_rest' => false,
        'menu_icon'    => 'dashicons-feedback',
        'menu_position' => 26,
        'supports'     => ['title'],
        'map_meta_cap' => false,
        'capabilities' => [
            'edit_post' => $cap, 'read_post' => $cap, 'delete_post' => $cap, 'edit_posts' => $cap,
            'edit_others_posts' => $cap, 'edit_published_posts' => $cap, 'delete_posts' => $cap,
            'delete_others_posts' => $cap, 'delete_published_posts' => $cap, 'read_private_posts' => $cap,
            'publish_posts' => 'do_not_allow', 'create_posts' => 'do_not_allow',
        ],
    ]);
    if (!wp_next_scheduled('evive_forms_cleanup')) wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'evive_forms_cleanup');
});

register_deactivation_hook(EVIVE_FORMS_FILE, function () {
    wp_clear_scheduled_hook('evive_forms_cleanup');
});

function evive_forms_store_entry($def, $values, $page) {
    if ((int) evive_forms_option('retention_days') === 0) return 0;
    $fields = [];
    foreach ($def['fields'] as $name => $f) $fields[] = [$f['label'], $values[$name]];
    $email = evive_forms_sender_email($def, $values);
    $id = wp_insert_post([
        'post_type'   => 'evive_form_entry',
        'post_status' => 'publish',
        'post_title'  => $def['title'] . ($email ? ' – ' . $email : ''),
    ], false, false);
    if (!$id) return 0;
    update_post_meta($id, '_evive_form', $def['title']);
    update_post_meta($id, '_evive_fields', $fields);
    update_post_meta($id, '_evive_email', $email);
    update_post_meta($id, '_evive_page', $page);
    return $id;
}

add_action('evive_forms_cleanup', function () {
    $days = (int) evive_forms_option('retention_days');
    $args = ['post_type' => 'evive_form_entry', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 500, 'no_found_rows' => true];
    if ($days > 0) $args['date_query'] = [['before' => gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS), 'column' => 'post_date_gmt']];
    foreach (get_posts($args) as $id) wp_delete_post($id, true);
});

// List screen: date, form, sender, a short summary; filter by form; no "Add new", no quick edit.
add_filter('manage_evive_form_entry_posts_columns', function () {
    return [
        'cb'          => '<input type="checkbox">',
        'title'       => __('Entry', 'evive-forms'),
        'evive_form'  => __('Form', 'evive-forms'),
        'evive_sum'   => __('Message', 'evive-forms'),
        'date'        => __('Date', 'evive-forms'),
    ];
});

add_action('manage_evive_form_entry_posts_custom_column', function ($col, $id) {
    if ($col === 'evive_form') echo esc_html(get_post_meta($id, '_evive_form', true));
    if ($col === 'evive_sum') {
        $long = '';
        foreach ((array) get_post_meta($id, '_evive_fields', true) as $f) {
            if (strlen((string) ($f[1] ?? '')) > strlen($long)) $long = (string) $f[1];
        }
        echo esc_html(wp_trim_words($long, 16));
    }
}, 10, 2);

add_filter('post_row_actions', function ($actions, $post) {
    if ($post->post_type !== 'evive_form_entry') return $actions;
    unset($actions['inline hide-if-no-js']);
    if (isset($actions['edit'])) $actions['edit'] = '<a href="' . esc_url(get_edit_post_link($post->ID)) . '">' . esc_html__('View', 'evive-forms') . '</a>';
    return $actions;
}, 10, 2);

add_filter('bulk_actions-edit-evive_form_entry', function ($actions) {
    unset($actions['edit']);
    return $actions;
});

// Entries are deleted for good, not moved to the trash.
add_filter('pre_trash_post', function ($trash, $post) {
    if ($post->post_type !== 'evive_form_entry') return $trash;
    wp_delete_post($post->ID, true);
    return false;
}, 10, 2);

function evive_forms_titles() {
    global $wpdb;
    return $wpdb->get_col("SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_evive_form' ORDER BY meta_value");
}

add_action('restrict_manage_posts', function ($type) {
    if ($type !== 'evive_form_entry') return;
    $current = sanitize_text_field(wp_unslash($_GET['evive_form'] ?? ''));
    echo '<select name="evive_form"><option value="">' . esc_html__('All forms', 'evive-forms') . '</option>';
    foreach (evive_forms_titles() as $t) printf('<option value="%s"%s>%s</option>', esc_attr($t), selected($current, $t, false), esc_html($t));
    echo '</select>';
});

add_action('pre_get_posts', function ($q) {
    if (!is_admin() || !$q->is_main_query() || $q->get('post_type') !== 'evive_form_entry') return;
    $form = sanitize_text_field(wp_unslash($_GET['evive_form'] ?? ''));
    if ($form !== '') $q->set('meta_query', [['key' => '_evive_form', 'value' => $form]]);
});

add_action('manage_posts_extra_tablenav', function ($which) {
    if ($which !== 'top' || (get_current_screen()->post_type ?? '') !== 'evive_form_entry') return;
    $url = wp_nonce_url(add_query_arg(['action' => 'evive_forms_export', 'evive_form' => sanitize_text_field(wp_unslash($_GET['evive_form'] ?? ''))], admin_url('admin-post.php')), 'evive_forms_export');
    echo '<div class="alignleft actions"><a class="button" href="' . esc_url($url) . '">' . esc_html__('Export CSV', 'evive-forms') . '</a></div>';
});

// Detail screen: the entry as a read-only table instead of an editor.
add_action('add_meta_boxes_evive_form_entry', function ($post) {
    remove_meta_box('submitdiv', 'evive_form_entry', 'side');
    add_meta_box('evive-entry', __('Entry', 'evive-forms'), 'evive_forms_render_entry', 'evive_form_entry', 'normal', 'high');
});

add_action('admin_head', function () {
    if ((get_current_screen()->id ?? '') === 'evive_form_entry') echo '<style>#titlediv{display:none}</style>';
});

function evive_forms_render_entry($post) {
    echo '<p><strong>' . esc_html(get_post_meta($post->ID, '_evive_form', true)) . '</strong> · ' . esc_html(get_the_date('', $post) . ' ' . get_the_time('', $post)) . '</p>';
    echo '<table class="widefat striped"><tbody>';
    foreach ((array) get_post_meta($post->ID, '_evive_fields', true) as $f) {
        printf('<tr><th style="width:30%%">%s</th><td>%s</td></tr>', esc_html($f[0] ?? ''), nl2br(esc_html($f[1] ?? '')));
    }
    echo '</tbody></table>';
    $page = get_post_meta($post->ID, '_evive_page', true);
    if ($page) echo '<p>' . esc_html__('Sent from:', 'evive-forms') . ' <a href="' . esc_url($page) . '">' . esc_html($page) . '</a></p>';
    $email = get_post_meta($post->ID, '_evive_email', true);
    echo '<p>';
    if ($email) echo '<a class="button button-primary" href="mailto:' . esc_attr($email) . '">' . esc_html__('Reply by email', 'evive-forms') . '</a> ';
    echo '<a class="button-link-delete" href="' . esc_url(get_delete_post_link($post->ID, '', true)) . '">' . esc_html__('Delete entry', 'evive-forms') . '</a></p>';
}

add_action('admin_post_evive_forms_export', function () {
    if (!current_user_can(evive_forms_capability())) wp_die('', 403);
    check_admin_referer('evive_forms_export');
    $form = sanitize_text_field(wp_unslash($_GET['evive_form'] ?? ''));
    $args = ['post_type' => 'evive_form_entry', 'post_status' => 'any', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'ASC'];
    if ($form !== '') $args['meta_query'] = [['key' => '_evive_form', 'value' => $form]];
    $rows = [];
    $cols = [];
    foreach (get_posts($args) as $p) {
        $row = ['date' => get_post_time('Y-m-d H:i', false, $p), 'form' => get_post_meta($p->ID, '_evive_form', true)];
        foreach ((array) get_post_meta($p->ID, '_evive_fields', true) as $f) {
            $row[$f[0]] = $f[1];
            $cols[$f[0]] = true;
        }
        $row['page'] = get_post_meta($p->ID, '_evive_page', true);
        $rows[] = $row;
    }
    $head = array_merge(['date', 'form'], array_keys($cols), ['page']);
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="form-entries-' . gmdate('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM, so Excel reads UTF-8
    fputcsv($out, $head);
    foreach ($rows as $r) {
        // A leading = + - @ would be run as a formula by spreadsheet apps.
        fputcsv($out, array_map(function ($k) use ($r) {
            $v = (string) ($r[$k] ?? '');
            return preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v;
        }, $head));
    }
    exit;
});

// Privacy tools (Tools → Export/Erase Personal Data) find entries by the sender's email.
function evive_forms_entries_for($email) {
    return get_posts(['post_type' => 'evive_form_entry', 'post_status' => 'any', 'posts_per_page' => -1,
        'meta_query' => [['key' => '_evive_email', 'value' => $email]]]);
}

add_filter('wp_privacy_personal_data_exporters', function ($exporters) {
    $exporters['evive-forms'] = [
        'exporter_friendly_name' => __('Form entries', 'evive-forms'),
        'callback' => function ($email) {
            $data = [];
            foreach (evive_forms_entries_for($email) as $p) {
                $items = [['name' => __('Form', 'evive-forms'), 'value' => get_post_meta($p->ID, '_evive_form', true)],
                          ['name' => __('Date', 'evive-forms'), 'value' => $p->post_date]];
                foreach ((array) get_post_meta($p->ID, '_evive_fields', true) as $f) $items[] = ['name' => $f[0], 'value' => $f[1]];
                $data[] = ['group_id' => 'evive-forms', 'group_label' => __('Form entries', 'evive-forms'), 'item_id' => 'entry-' . $p->ID, 'data' => $items];
            }
            return ['data' => $data, 'done' => true];
        },
    ];
    return $exporters;
});

add_filter('wp_privacy_personal_data_erasers', function ($erasers) {
    $erasers['evive-forms'] = [
        'eraser_friendly_name' => __('Form entries', 'evive-forms'),
        'callback' => function ($email) {
            $n = 0;
            foreach (evive_forms_entries_for($email) as $p) $n += wp_delete_post($p->ID, true) ? 1 : 0;
            return ['items_removed' => $n > 0, 'items_retained' => false, 'messages' => [], 'done' => true];
        },
    ];
    return $erasers;
});
