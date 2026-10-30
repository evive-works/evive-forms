<?php
/**
 * Form block. $attributes, $content (the rendered fields) and $block come from WordPress.
 *
 * @package evive-forms
 */

if (!defined('ABSPATH')) exit;

$evive_def  = evive_forms_build_definition($attributes, $block->parsed_block['innerBlocks'] ?? []);
$evive_hash = evive_forms_store_definition($evive_def);
$evive_page = remove_query_arg(['evive_form', 'evive_status'], home_url(wp_unslash($_SERVER['REQUEST_URI'] ?? '/')));
$evive_done = isset($_GET['evive_form'], $_GET['evive_status']) && $_GET['evive_form'] === $evive_hash;
$evive_ok   = $evive_def['success'] !== '' ? $evive_def['success'] : esc_html__('Thank you! We received your message and will get back to you soon.', 'evive-forms');
$evive_wrap = get_block_wrapper_attributes(['class' => 'evive-form', 'id' => 'evive-form-' . $evive_hash, 'data-evive-form' => $evive_hash]);

// After a submission without JavaScript the visitor comes back here with the result in the URL.
if ($evive_done && $_GET['evive_status'] === 'sent') : ?>
<div <?php echo $evive_wrap; ?>><div class="evive-form-message is-success" role="status"><?php echo wp_kses_post($evive_ok); ?></div></div>
<?php return; endif; ?>
<form <?php echo $evive_wrap; ?> method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-success="<?php echo esc_attr(wp_kses_post($evive_ok)); ?>" data-error="<?php esc_attr_e('Sending failed. Please try again in a moment.', 'evive-forms'); ?>">
	<input type="hidden" name="action" value="evive_forms_submit">
	<input type="hidden" name="evive_form" value="<?php echo esc_attr($evive_hash); ?>">
	<input type="hidden" name="evive_page" value="<?php echo esc_url($evive_page); ?>">
	<input type="hidden" name="evive_t" value="">
	<div class="evive-form-trap" aria-hidden="true"><label>Website <input type="text" name="evive_website" value="" tabindex="-1" autocomplete="off"></label></div>
	<div class="evive-form-fields"><?php echo $content; ?></div>
	<div class="evive-form-message<?php echo $evive_done ? ' is-error' : ''; ?>" role="alert"<?php echo $evive_done ? '' : ' hidden'; ?>><?php if ($evive_done) esc_html_e('Something went wrong while sending. Please check the fields and try again.', 'evive-forms'); ?></div>
	<div class="wp-block-buttons evive-form-actions"><div class="wp-block-button"><button type="submit" class="wp-block-button__link wp-element-button"><?php echo esc_html(trim(wp_strip_all_tags($attributes['submitLabel'] ?? '')) ?: __('Send', 'evive-forms')); ?></button></div></div>
</form>
