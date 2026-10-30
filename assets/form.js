/* evive Forms: sends the form without a page reload and shows errors next to the fields.
   Without this script the form still works as a normal post (the server redirects back with the result). */
(function () {
	'use strict';
	var loaded = Date.now();

	function clearErrors(form) {
		form.querySelectorAll('.evive-field-error').forEach(function (el) { el.remove(); });
		form.querySelectorAll('[aria-invalid]').forEach(function (el) { el.removeAttribute('aria-invalid'); });
	}

	function showMessage(form, text) {
		var box = form.querySelector('.evive-form-message');
		box.textContent = text;
		box.className = 'evive-form-message is-error';
		box.hidden = false;
	}

	function showErrors(form, errors) {
		var first = null;
		Object.keys(errors).forEach(function (name) {
			var input = form.elements[name];
			if (!input || name === '_form') return showMessage(form, errors[name]);
			input.setAttribute('aria-invalid', 'true');
			var p = document.createElement('p');
			p.className = 'evive-field-error';
			p.id = input.id + '-error';
			p.textContent = errors[name];
			input.closest('.evive-field').appendChild(p);
			input.setAttribute('aria-errormessage', p.id);
			first = first || input;
		});
		if (first) first.focus();
	}

	function init(form) {
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var button = form.querySelector('button[type="submit"]');
			if (button.disabled) return;
			clearErrors(form);
			form.querySelector('.evive-form-message').hidden = true;
			form.elements.evive_t.value = Math.round((Date.now() - loaded) / 1000);
			button.disabled = true;
			form.setAttribute('aria-busy', 'true');
			// form.action would be the hidden input named "action", so read the attribute.
			fetch(form.getAttribute('action'), { method: 'POST', body: new FormData(form), headers: { 'X-Evive-Forms': '1' }, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (res.ok) {
						var done = document.createElement('div');
						done.className = 'evive-form-message is-success';
						done.setAttribute('role', 'status');
						done.tabIndex = -1;
						done.innerHTML = form.dataset.success;
						form.replaceChildren(done);
						done.focus();
						return;
					}
					showErrors(form, res.errors || {});
				})
				.catch(function () { showMessage(form, form.dataset.error); })
				.finally(function () { button.disabled = false; form.removeAttribute('aria-busy'); });
		});
	}

	document.querySelectorAll('form[data-evive-form]').forEach(init);
})();
