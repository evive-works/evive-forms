<?php
/**
 * Form field block. Only the markup: validation happens on the server against the saved definition.
 *
 * @package evive-forms
 */

if (!defined('ABSPATH')) exit;

$evive_type  = evive_forms_field_type($attributes);
$evive_name  = evive_forms_field_name($attributes);
$evive_id    = wp_unique_id('evive-field-');
$evive_req   = !empty($attributes['required']) || $evive_type === 'consent';
$evive_label = wp_kses($attributes['label'] ?? '', ['a' => ['href' => true, 'target' => true, 'rel' => true], 'strong' => [], 'em' => []]);
$evive_ph    = (string) ($attributes['placeholder'] ?? '');
$evive_help  = trim((string) ($attributes['help'] ?? ''));
$evive_extra = ['email' => 'autocomplete="email"', 'tel' => 'autocomplete="tel" inputmode="tel"'][$evive_type] ?? '';
$evive_attr  = sprintf('id="%s" name="%s"%s%s', esc_attr($evive_id), esc_attr($evive_name), $evive_req ? ' required' : '', $evive_help !== '' ? ' aria-describedby="' . esc_attr($evive_id) . '-help"' : '');
$evive_class = 'evive-field evive-field-' . $evive_type . (($attributes['width'] ?? '') === 'half' ? ' is-half' : '');
$evive_mark  = $evive_req ? ' <span class="evive-field-required" aria-hidden="true">*</span>' : '';
?>
<div <?php echo get_block_wrapper_attributes(['class' => $evive_class]); ?>>
<?php if ($evive_type === 'checkbox' || $evive_type === 'consent') : ?>
	<label class="evive-field-check"><input type="checkbox" value="1" <?php echo $evive_attr; ?>> <span><?php echo $evive_label . $evive_mark; ?></span></label>
<?php else : ?>
	<label for="<?php echo esc_attr($evive_id); ?>"><?php echo $evive_label . $evive_mark; ?></label>
	<?php if ($evive_type === 'textarea') : ?>
	<textarea rows="5" <?php echo $evive_attr; ?> placeholder="<?php echo esc_attr($evive_ph); ?>"></textarea>
	<?php elseif ($evive_type === 'select') : ?>
	<select <?php echo $evive_attr; ?>>
		<option value=""><?php echo esc_html($evive_ph !== '' ? $evive_ph : __('Choose…', 'evive-forms')); ?></option>
		<?php foreach ((array) ($attributes['options'] ?? []) as $evive_opt) : $evive_opt = trim((string) $evive_opt); if ($evive_opt === '') continue; ?>
		<option value="<?php echo esc_attr($evive_opt); ?>"><?php echo esc_html($evive_opt); ?></option>
		<?php endforeach; ?>
	</select>
	<?php else : ?>
	<input type="<?php echo esc_attr($evive_type); ?>" <?php echo $evive_attr; ?> <?php echo $evive_extra; ?> placeholder="<?php echo esc_attr($evive_ph); ?>">
	<?php endif; ?>
<?php endif; ?>
<?php if ($evive_help !== '') : ?>
	<p class="evive-field-help" id="<?php echo esc_attr($evive_id); ?>-help"><?php echo esc_html($evive_help); ?></p>
<?php endif; ?>
</div>
