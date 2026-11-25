<?php
/**
 * Plugin Name: evive Forms
 * Description: Light, solid forms of the evive family. A form is a block: place fields (text, email, phone,
 *              message, choice, checkbox, consent) in the editor like any other block. Server-side validation,
 *              spam protection without a captcha puzzle, entries kept in WordPress with automatic deletion,
 *              notification mail plus an optional confirmation to the sender. No scripts on pages without a form.
 * Version: 0.2.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: evive.works (StackPros B.V.)
 * Author URI: https://evive.works
 * License: GPL-2.0-or-later
 * Text Domain: evive-forms
 * Domain Path: /languages
 * Update URI: https://github.com/evive-works/evive-forms
 */

if (!defined('ABSPATH')) exit;

define('EVIVE_FORMS_VERSION', '0.2.0');
define('EVIVE_FORMS_FILE', __FILE__);
define('EVIVE_FORMS_DIR', plugin_dir_path(__FILE__));

// Updates: WordPress offers every GitHub Release of github.com/evive-works/evive-forms with the asset
// evive-forms.zip (Plugin Update Checker v5, MIT, same setup as the evive Cookie Banner).
require_once EVIVE_FORMS_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
$eviveFormsUpdates = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/evive-works/evive-forms/',
    __FILE__,
    'evive-forms'
);
$eviveFormsUpdates->setBranch('main');
$eviveFormsUpdates->getVcsApi()->enableReleaseAssets('/evive-forms\.zip$/');

require_once EVIVE_FORMS_DIR . 'includes/settings.php';
require_once EVIVE_FORMS_DIR . 'includes/definitions.php';
require_once EVIVE_FORMS_DIR . 'includes/submit.php';
require_once EVIVE_FORMS_DIR . 'includes/entries.php';

// 0.2.0: remember where a visit came from (campaign parameters, the external referrer, the first page) for the
// session only (sessionStorage, no cookie, nothing sent anywhere), so hidden form fields can carry it. Printed only
// once a form on the site has such a field. Visitors without JavaScript simply send these fields empty.
add_action('wp_footer', function () {
    if (!get_option('evive_forms_capture') || is_admin()) return;
    echo "<script>(function(){try{var K='evive_forms_visit',q=new URLSearchParams(location.search),k=['utm_source','utm_medium','utm_campaign','utm_term','utm_content','gclid'],a=JSON.parse(sessionStorage.getItem(K)||'null'),n=k.some(function(x){return q.get(x)});if(!a||n){var r='';try{r=document.referrer&&new URL(document.referrer).host!==location.host?document.referrer.slice(0,300):''}catch(e){}a={landing_page:(location.origin+location.pathname).slice(0,300),referrer:r};k.forEach(function(x){if(q.get(x))a[x]=q.get(x).slice(0,200)});sessionStorage.setItem(K,JSON.stringify(a))}}catch(e){}})();</script>\n";
}, 5);

add_action('init', function () {
    load_plugin_textdomain('evive-forms', false, dirname(plugin_basename(__FILE__)) . '/languages');

    // One editor script for both blocks, written without a build step (wp.element.createElement).
    $url = plugins_url('assets/', __FILE__);
    wp_register_script('evive-forms-editor', $url . 'editor.js',
        ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-data'], EVIVE_FORMS_VERSION, true);
    wp_set_script_translations('evive-forms-editor', 'evive-forms', EVIVE_FORMS_DIR . 'languages');
    wp_register_script('evive-forms', $url . 'form.js', [], EVIVE_FORMS_VERSION, ['strategy' => 'defer', 'in_footer' => true]);
    wp_register_style('evive-forms', $url . 'form.css', [], EVIVE_FORMS_VERSION);

    // block.json names these handles, so WordPress loads them only on pages that contain a form.
    register_block_type(EVIVE_FORMS_DIR . 'blocks/form');
    register_block_type(EVIVE_FORMS_DIR . 'blocks/field');
});
