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

	// 0.2.0: hidden fields from the visit (see the footer script) and fields pre-filled from the link (?type=demo).
	function norm(s) { return String(s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim(); }
	function fill(form) {
		var visit = {}, q = new URLSearchParams(location.search);
		try { visit = JSON.parse(sessionStorage.getItem('evive_forms_visit') || '{}') || {}; } catch (e) {}
		form.querySelectorAll('input[data-evive-source]').forEach(function (input) {
			var s = input.dataset.eviveSource;
			if (!s || s === 'fixed') return;
			input.value = s.indexOf('query:') === 0 ? (q.get(s.slice(6)) || '').slice(0, 200) : (visit[s] || q.get(s) || '');
		});
		form.querySelectorAll('[data-evive-prefill]').forEach(function (field) {
			var v = q.get(field.dataset.evivePrefill);
			if (!v) return;
			if (field.tagName === 'SELECT') {
				var want = norm(v), opts = Array.prototype.slice.call(field.options).filter(function (o) { return o.value; });
				var hit = opts.find(function (o) { return norm(o.value) === want; }) || opts.find(function (o) { return norm(o.value).indexOf(want) === 0; }) ||
					opts.find(function (o) { return norm(o.value).indexOf(want) !== -1; });
				if (hit) field.value = hit.value;
			} else if (field.type !== 'checkbox') {
				field.value = v.slice(0, 200);
			}
		});
	}

	document.querySelectorAll('form[data-evive-form]').forEach(function (form) { fill(form); init(form); });
})();
