/* evive Forms: editor for the Form and Form field blocks. Plain JavaScript (no build step); both blocks are
   rendered on the server, so the editor only shows a preview and stores the settings. */
(function (wp) {
	'use strict';
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var be = wp.blockEditor;
	var c = wp.components;
	var useSelect = wp.data.useSelect;
	var useEffect = wp.element.useEffect;

	var TYPES = [
		{ value: 'text', label: __('Text', 'evive-forms') },
		{ value: 'email', label: __('Email', 'evive-forms') },
		{ value: 'tel', label: __('Phone', 'evive-forms') },
		{ value: 'textarea', label: __('Message (several lines)', 'evive-forms') },
		{ value: 'select', label: __('Choice (dropdown)', 'evive-forms') },
		{ value: 'checkbox', label: __('Checkbox', 'evive-forms') },
		{ value: 'consent', label: __('Consent (always required)', 'evive-forms') }
	];

	function slug(text) {
		return String(text || '').replace(/<[^>]*>/g, '').normalize('NFD').replace(/[̀-ͯ]/g, '')
			.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 40);
	}

	// ---- Form -------------------------------------------------------------------------------------------------
	var FORM_TEMPLATE = [
		['evive/field', { type: 'text', name: 'name', label: __('Name', 'evive-forms'), required: true, width: 'half' }],
		['evive/field', { type: 'email', name: 'email', label: __('Email', 'evive-forms'), required: true, width: 'half' }],
		['evive/field', { type: 'textarea', name: 'message', label: __('Message', 'evive-forms'), required: true }],
		['evive/field', { type: 'consent', name: 'consent', label: __('I agree that my details are used to answer my message.', 'evive-forms') }]
	];

	wp.blocks.registerBlockType('evive/form', {
		edit: function (props) {
			var a = props.attributes;
			var set = props.setAttributes;
			var blockProps = be.useBlockProps({ className: 'evive-form' });
			var inner = be.useInnerBlocksProps({ className: 'evive-form-fields' }, {
				template: FORM_TEMPLATE,
				allowedBlocks: ['evive/field', 'core/paragraph', 'core/heading', 'core/columns', 'core/group'],
				renderAppender: be.InnerBlocks.ButtonBlockAppender
			});
			return el(wp.element.Fragment, null,
				el(be.InspectorControls, null,
					el(c.PanelBody, { title: __('Form', 'evive-forms') },
						el(c.TextControl, { label: __('Form name', 'evive-forms'), help: __('Shown in the entries list and the email subject.', 'evive-forms'), value: a.formTitle, onChange: function (v) { set({ formTitle: v }); }, __nextHasNoMarginBottom: true }),
						el(c.TextControl, { label: __('Send entries to', 'evive-forms'), help: __('Email addresses, separated by commas. Empty: the address in Settings → evive Forms.', 'evive-forms'), value: a.notifyTo, onChange: function (v) { set({ notifyTo: v }); }, __nextHasNoMarginBottom: true }),
						el(c.TextControl, { label: __('Button text', 'evive-forms'), placeholder: __('Send', 'evive-forms'), value: a.submitLabel, onChange: function (v) { set({ submitLabel: v }); }, __nextHasNoMarginBottom: true }),
						el(c.TextareaControl, { label: __('Message after sending', 'evive-forms'), placeholder: __('Thank you! We received your message and will get back to you soon.', 'evive-forms'), value: a.successMessage, onChange: function (v) { set({ successMessage: v }); }, __nextHasNoMarginBottom: true })
					),
					el(c.PanelBody, { title: __('Confirmation to the sender', 'evive-forms'), initialOpen: false },
						el(c.ToggleControl, { label: __('Send a confirmation email', 'evive-forms'), help: __('Goes to the first email field, with a copy of the entry.', 'evive-forms'), checked: a.confirmation, onChange: function (v) { set({ confirmation: v }); }, __nextHasNoMarginBottom: true }),
						a.confirmation && el(c.TextControl, { label: __('Subject', 'evive-forms'), value: a.confirmationSubject, onChange: function (v) { set({ confirmationSubject: v }); }, __nextHasNoMarginBottom: true }),
						a.confirmation && el(c.TextareaControl, { label: __('Message', 'evive-forms'), value: a.confirmationMessage, onChange: function (v) { set({ confirmationMessage: v }); }, __nextHasNoMarginBottom: true })
					)
				),
				el('div', blockProps,
					el('div', inner),
					el('div', { className: 'wp-block-buttons evive-form-actions' },
						el('div', { className: 'wp-block-button' },
							el('span', { className: 'wp-block-button__link wp-element-button' }, a.submitLabel || __('Send', 'evive-forms'))
						)
					)
				)
			);
		},
		save: function () {
			return el(be.InnerBlocks.Content);
		}
	});

	// ---- Field ------------------------------------------------------------------------------------------------
	// Names of the other fields in the same form, to keep every field name unique.
	function useOtherNames(clientId) {
		return useSelect(function (select) {
			var store = select('core/block-editor');
			var formId = store.getBlockParentsByBlockName(clientId, 'evive/form').slice(-1)[0];
			var names = [];
			(function walk(blocks) {
				(blocks || []).forEach(function (b) {
					if (b.name === 'evive/field' && b.clientId !== clientId) names.push(b.attributes.name || slug(b.attributes.label) || b.attributes.type);
					walk(b.innerBlocks);
				});
			})(formId ? store.getBlock(formId).innerBlocks : []);
			return names;
		}, [clientId]);
	}

	function preview(a) {
		var ph = a.placeholder;
		switch (a.type) {
			case 'textarea': return el('textarea', { rows: 4, placeholder: ph, disabled: true });
			case 'select': return el('select', { disabled: true }, el('option', null, ph || __('Choose…', 'evive-forms')));
			case 'checkbox':
			case 'consent': return null;
			default: return el('input', { type: a.type, placeholder: ph, disabled: true });
		}
	}

	wp.blocks.registerBlockType('evive/field', {
		edit: function (props) {
			var a = props.attributes;
			var set = props.setAttributes;
			var others = useOtherNames(props.clientId);
			var check = a.type === 'checkbox' || a.type === 'consent';
			var required = a.required || a.type === 'consent';
			var blockProps = be.useBlockProps({ className: 'evive-field evive-field-' + a.type + (a.width === 'half' ? ' is-half' : '') });

			// A new field (or a copy) gets a unique name; once set, it stays, so entries and exports stay consistent.
			useEffect(function () {
				var base = a.name || slug(a.label) || a.type;
				var name = base, i = 2;
				while (others.indexOf(name) !== -1) name = base + '_' + i++;
				if (name !== a.name) set({ name: name });
			}, [a.name, others.join(',')]);

			var label = el(be.RichText, {
				tagName: 'span', value: a.label, allowedFormats: ['core/bold', 'core/italic', 'core/link'],
				placeholder: __('Label…', 'evive-forms'), onChange: function (v) { set({ label: v }); }
			});
			var mark = required ? el('span', { className: 'evive-field-required', 'aria-hidden': true }, ' *') : null;

			return el(wp.element.Fragment, null,
				el(be.InspectorControls, null,
					el(c.PanelBody, { title: __('Field', 'evive-forms') },
						el(c.SelectControl, { label: __('Type', 'evive-forms'), value: a.type, options: TYPES, onChange: function (v) { set({ type: v }); }, __nextHasNoMarginBottom: true }),
						a.type !== 'consent' && el(c.ToggleControl, { label: __('Required', 'evive-forms'), checked: a.required, onChange: function (v) { set({ required: v }); }, __nextHasNoMarginBottom: true }),
						a.type === 'select' && el(c.TextareaControl, { label: __('Choices', 'evive-forms'), help: __('One per line.', 'evive-forms'), value: (a.options || []).join('\n'), onChange: function (v) { set({ options: v.split('\n') }); }, __nextHasNoMarginBottom: true }),
						!check && el(c.TextControl, { label: __('Placeholder', 'evive-forms'), value: a.placeholder, onChange: function (v) { set({ placeholder: v }); }, __nextHasNoMarginBottom: true }),
						el(c.TextControl, { label: __('Help text', 'evive-forms'), value: a.help, onChange: function (v) { set({ help: v }); }, __nextHasNoMarginBottom: true }),
						el(c.SelectControl, { label: __('Width', 'evive-forms'), value: a.width, options: [{ value: 'full', label: __('Full width', 'evive-forms') }, { value: 'half', label: __('Half width', 'evive-forms') }], onChange: function (v) { set({ width: v }); }, __nextHasNoMarginBottom: true }),
						el(c.TextControl, { label: __('Field name', 'evive-forms'), help: __('Used in the entries and the CSV export. Letters, digits and _ only.', 'evive-forms'), value: a.name, onChange: function (v) { set({ name: slug(v) }); }, __nextHasNoMarginBottom: true })
					)
				),
				el('div', blockProps,
					check
						? el('label', { className: 'evive-field-check' }, el('input', { type: 'checkbox', disabled: true }), el('span', null, label, mark))
						: el(wp.element.Fragment, null, el('label', null, label, mark), preview(a)),
					a.help && el('p', { className: 'evive-field-help' }, a.help)
				)
			);
		},
		save: function () {
			return null;
		}
	});
})(window.wp);
