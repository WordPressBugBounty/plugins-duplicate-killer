(function () {
	'use strict';

	/**
	 * Duplicate Killer Modern Popup Mode.
	 *
	 * Responsibilities:
	 * - Detect only Duplicate Killer blocked messages.
	 * - Show the Modern Popup UI when no supported third-party popup owns the UI.
	 * - Keep each form provider isolated and easy to maintain.
	 */

	// -------------------------------------------------------------------------
	// Config and shared state.
	// -------------------------------------------------------------------------

	var config = window.DuplicateKillerModernPopup || {};
	var items = Array.isArray(config.items) ? config.items : [];
	var shown = {};
	var closeTimer = null;

	if (!items.length) {
		return;
	}

	// -------------------------------------------------------------------------
	// Shared message helpers.
	// -------------------------------------------------------------------------

	function normalize(text) {
		return String(text || '').replace(/\s+/g, ' ').trim();
	}

	function getItemsByProvider(provider) {
		return items.filter(function (item) {
			return item && item.provider === provider;
		});
	}

	function findByMarker(text, provider, formId) {
		var normalizedText = normalize(text);
		var providerItems = getItemsByProvider(provider);

		for (var i = 0; i < providerItems.length; i++) {
			var item = providerItems[i];

			if (!item.marker || normalizedText.indexOf(item.marker) === -1) {
				continue;
			}

			if (formId && item.formId && String(item.formId) !== String(formId)) {
				continue;
			}

			return item;
		}

		return null;
	}

	function findByPlain(text, provider, formId) {
		var normalizedText = normalize(text);
		var providerItems = getItemsByProvider(provider).filter(function (item) {
			if (!item.plain || normalize(item.plain) !== normalizedText) {
				return false;
			}

			if (formId && item.formId && String(item.formId) !== String(formId)) {
				return false;
			}

			return true;
		});

		return providerItems.length === 1 ? providerItems[0] : null;
	}

	function findItem(text, provider, formId) {
		return findByMarker(text, provider, formId) || findByPlain(text, provider, formId);
	}

	function stripMarker(text, item) {
		if (!item || !item.marker) {
			return text;
		}

		return normalize(String(text || '').replace(item.marker, ''));
	}

	// -------------------------------------------------------------------------
	// Modal UI.
	// -------------------------------------------------------------------------

	function createModal() {
		var existing = document.querySelector('[data-duplicatekiller-modern-modal]');

		if (existing) {
			return existing;
		}

		var modal = document.createElement('div');
		modal.className = 'duplicatekiller-modern-modal';
		modal.setAttribute('data-duplicatekiller-modern-modal', '1');
		modal.setAttribute('hidden', 'hidden');

		modal.innerHTML = '' +
			'<div class="duplicatekiller-modern-modal__overlay" data-duplicatekiller-modern-close></div>' +
			'<div class="duplicatekiller-modern-modal__dialog" role="dialog" aria-modal="true" aria-live="polite">' +
				'<button type="button" class="duplicatekiller-modern-modal__close" data-duplicatekiller-modern-close aria-label="Close">&times;</button>' +
				'<div class="duplicatekiller-modern-modal__body"></div>' +
			'</div>';

		document.body.appendChild(modal);

		modal.addEventListener('click', function (event) {
			if (event.target.closest('[data-duplicatekiller-modern-close]')) {
				closeModal();
			}
		});

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && !modal.hasAttribute('hidden')) {
				closeModal();
			}
		});

		return modal;
	}

	function openModal(item) {
		if (!item || !item.html) {
			return;
		}

		var key = item.marker || item.provider + '|' + item.formId + '|' + item.plain;

		if (shown[key]) {
			return;
		}

		shown[key] = true;

		window.setTimeout(function () {
			shown[key] = false;
		}, 1500);

		var modal = createModal();
		var body = modal.querySelector('.duplicatekiller-modern-modal__body');

		if (!body) {
			return;
		}

		body.innerHTML = item.html;

		if (closeTimer) {
			window.clearTimeout(closeTimer);
			closeTimer = null;
		}

		modal.classList.remove('is-closing');
		modal.removeAttribute('hidden');

		window.requestAnimationFrame(function () {
			modal.classList.add('is-active');
		});

		var close = modal.querySelector('.duplicatekiller-modern-modal__close');

		if (close) {
			close.focus({ preventScroll: true });
		}
	}

	function closeModal() {
		var modal = document.querySelector('[data-duplicatekiller-modern-modal]');

		if (!modal || modal.hasAttribute('hidden')) {
			return;
		}

		modal.classList.remove('is-active');
		modal.classList.add('is-closing');

		if (closeTimer) {
			window.clearTimeout(closeTimer);
		}

		closeTimer = window.setTimeout(function () {
			modal.classList.remove('is-closing');
			modal.setAttribute('hidden', 'hidden');
			closeTimer = null;
		}, 180);
	}

	// -------------------------------------------------------------------------
	// Native message visibility helpers.
	// -------------------------------------------------------------------------

	function hideElement(element, item) {
		if (
			!element ||
			!item ||
			element === document.body ||
			element === document.documentElement
		) {
			return;
		}

		element.setAttribute('data-duplicatekiller-modern-hidden', '1');
		element.setAttribute('aria-hidden', 'true');
		element.style.display = 'none';
	}

	function restoreHiddenElement(element) {
		if (!element || element.getAttribute('data-duplicatekiller-modern-hidden') !== '1') {
			return;
		}

		element.removeAttribute('data-duplicatekiller-modern-hidden');
		element.removeAttribute('aria-hidden');
		element.style.display = '';
	}
	function restoreHiddenDescendants(root) {
		if (!root || !root.querySelectorAll) {
			return;
		}

		var hiddenElements = root.querySelectorAll('[data-duplicatekiller-modern-hidden="1"]');

		for (var i = 0; i < hiddenElements.length; i++) {
			restoreHiddenElement(hiddenElements[i]);
		}
	}

	// -------------------------------------------------------------------------
	// Third-party popup helpers.
	// -------------------------------------------------------------------------

	function closeKnownSweetAlert() {
		if (window.Swal && typeof window.Swal.close === 'function') {
			window.Swal.close();
		}

		if (window.swal && typeof window.swal.close === 'function') {
			window.swal.close();
		}

		if (window.sweetAlert && typeof window.sweetAlert.close === 'function') {
			window.sweetAlert.close();
		}
	}

	function hasCf7PopupsPopup() {
		return (
			typeof window.cf7_popups_val === 'object' &&
			window.cf7_popups_val !== null &&
			(
				(window.Swal && typeof window.Swal.fire === 'function') ||
				(typeof window.swal === 'function') ||
				(typeof window.sweetAlert === 'function') ||
				(typeof window.Sweetalert2 === 'function')
			)
		);
	}

	// -------------------------------------------------------------------------
	// Provider: Contact Form 7.
	// -------------------------------------------------------------------------

	function initCf7() {
		if (!getItemsByProvider('cf7').length) {
			return;
		}

		function restoreCf7Output(event) {
			var scope = event && event.target ? event.target : document;
			var responseOutput = scope.querySelector ? scope.querySelector('.wpcf7-response-output') : null;

			restoreHiddenElement(responseOutput);
		}

		function handle(event) {
			if (!event || event.type !== 'wpcf7aborted') {
				return;
			}

			var detail = event && event.detail ? event.detail : {};
			var response = detail.apiResponse || {};
			var message = typeof response.message === 'string' ? response.message : '';
			var formId = detail.contactFormId ? String(detail.contactFormId) : '';
			var item = findItem(message, 'cf7', formId);

			if (!item) {
				return;
			}

			if (hasCf7PopupsPopup()) {
				return;
			}

			var scope = event.target || document;
			var responseOutput = scope.querySelector ? scope.querySelector('.wpcf7-response-output') : null;

			if (responseOutput) {
				responseOutput.textContent = stripMarker(responseOutput.textContent, item);
				hideElement(responseOutput, item);
			}

			closeKnownSweetAlert();
			openModal(item);
		}

		// Duplicate Killer blocks CF7 by aborting the send.
		document.addEventListener('wpcf7aborted', handle, false);

		// Restore CF7 native output if it was hidden by a previous DK popup.
		document.addEventListener('wpcf7mailsent', restoreCf7Output, false);
		document.addEventListener('wpcf7invalid', restoreCf7Output, false);
		document.addEventListener('wpcf7spam', restoreCf7Output, false);
		document.addEventListener('wpcf7mailfailed', restoreCf7Output, false);
	}

	// -------------------------------------------------------------------------
	// Provider: Forminator.
	// -------------------------------------------------------------------------

	function getForminatorFormId(element) {
		var current = element;

		while (current && current !== document.body) {
			if (current.id && current.id.indexOf('forminator-module-') === 0) {
				return current.id.replace('forminator-module-', '');
			}

			if (current.getAttribute && current.getAttribute('data-form-id')) {
				return current.getAttribute('data-form-id');
			}

			current = current.parentNode;
		}

		return '';
	}

	function getForminatorErrorElement(element) {
		if (!element || !element.closest) {
			return null;
		}

		return element.closest(
			'.forminator-response-message, .forminator-error-message, .forminator-ui .forminator-error'
		);
	}

	function inspectForminatorNode(node) {
		if (!node || node.nodeType !== 1) {
			return;
		}

		var text = normalize(node.textContent);

		if (!text) {
			return;
		}

		var formId = getForminatorFormId(node);
		var item = findItem(text, 'forminator', formId);

		if (!item) {
			return;
		}

		hideElement(getForminatorErrorElement(node), item);
		openModal(item);
	}

	function initForminator() {
		if (!getItemsByProvider('forminator').length || typeof MutationObserver === 'undefined') {
			return;
		}

		var roots = document.querySelectorAll('.forminator-custom-form, form[id^="forminator-module-"]');

		if (!roots.length) {
			return;
		}

		for (var i = 0; i < roots.length; i++) {
			inspectForminatorNode(roots[i]);

			new MutationObserver(function (mutations) {
				for (var m = 0; m < mutations.length; m++) {
					for (var n = 0; n < mutations[m].addedNodes.length; n++) {
						inspectForminatorNode(mutations[m].addedNodes[n]);
					}

					if (mutations[m].target) {
						inspectForminatorNode(mutations[m].target);
					}
				}
			}).observe(roots[i], {
				childList: true,
				subtree: true,
				characterData: true
			});
		}
	}
	
		// -------------------------------------------------------------------------
	// Provider: WPForms Lite.
	// -------------------------------------------------------------------------

	function getWpformsFormId(element) {
		var current = element;

		while (current && current !== document.body) {
			if (current.getAttribute && current.getAttribute('data-formid')) {
				return current.getAttribute('data-formid');
			}

			if (current.id && current.id.indexOf('wpforms-form-') === 0) {
				return current.id.replace('wpforms-form-', '');
			}

			if (current.querySelector) {
				var hiddenId = current.querySelector('input[name="wpforms[id]"]');

				if (hiddenId && hiddenId.value) {
					return hiddenId.value;
				}
			}

			current = current.parentNode;
		}

		return '';
	}

	function inspectWpformsErrorElement(element) {
		if (!element || !element.textContent) {
			return;
		}

		var text = normalize(element.textContent);

		if (!text) {
			return;
		}

		var formId = getWpformsFormId(element);
		var item = findByMarker(text, 'wpforms', formId);

		if (!item) {
			return;
		}

		element.textContent = stripMarker(element.textContent, item);
		hideElement(element, item);
		openModal(item);
	}

	function inspectWpformsNode(node) {
		var element = node && node.nodeType === 3 ? node.parentNode : node;

		if (!element || element.nodeType !== 1) {
			return;
		}

		var errorSelector = '.wpforms-error-container, label.wpforms-error, em.wpforms-error, .wpforms-error';

		if (element.matches && element.matches(errorSelector)) {
			inspectWpformsErrorElement(element);
		}

		if (!element.querySelectorAll) {
			return;
		}

		var errors = element.querySelectorAll(errorSelector);

		for (var i = 0; i < errors.length; i++) {
			inspectWpformsErrorElement(errors[i]);
		}
	}

	function initWpforms() {
		if (!getItemsByProvider('wpforms').length || typeof MutationObserver === 'undefined') {
			return;
		}

		var roots = document.querySelectorAll('.wpforms-container, form.wpforms-form, form[id^="wpforms-form-"]');

		if (!roots.length) {
			return;
		}

		for (var i = 0; i < roots.length; i++) {
			inspectWpformsNode(roots[i]);

			roots[i].addEventListener('submit', function (event) {
				restoreHiddenDescendants(event.currentTarget || event.target);
			}, true);

			new MutationObserver(function (mutations) {
				for (var m = 0; m < mutations.length; m++) {
					for (var n = 0; n < mutations[m].addedNodes.length; n++) {
						inspectWpformsNode(mutations[m].addedNodes[n]);
					}

					if (mutations[m].target) {
						inspectWpformsNode(mutations[m].target);
					}
				}
			}).observe(roots[i], {
				childList: true,
				subtree: true,
				characterData: true
			});
		}
	}
	
		// -------------------------------------------------------------------------
	// Provider: Breakdance.
	// -------------------------------------------------------------------------

	function getBreakdanceFormId(element) {
		var current = element;

		while (current && current !== document.body) {
			if (current.querySelector) {
				var formId = current.querySelector('input[name="form_id"]');

				if (formId && formId.value) {
					return formId.value;
				}
			}

			current = current.parentNode;
		}

		return '';
	}

	function inspectBreakdanceErrorElement(element) {
		if (!element || !element.textContent) {
			return;
		}

		var text = normalize(element.textContent);

		if (!text) {
			return;
		}

		var formId = getBreakdanceFormId(element);
		var item = findByMarker(text, 'breakdance', formId);

		if (!item) {
			return;
		}

		element.textContent = stripMarker(element.textContent, item);
		hideElement(element, item);
		openModal(item);
	}

	function inspectBreakdanceNode(node) {
		var element = node && node.nodeType === 3 ? node.parentNode : node;

		if (!element || element.nodeType !== 1) {
			return;
		}

		var errorSelector = '.breakdance-form-message--error';

		if (element.matches && element.matches(errorSelector)) {
			inspectBreakdanceErrorElement(element);
		}

		if (!element.querySelectorAll) {
			return;
		}

		var errors = element.querySelectorAll(errorSelector);

		for (var i = 0; i < errors.length; i++) {
			inspectBreakdanceErrorElement(errors[i]);
		}
	}

	function initBreakdance() {
		if (!getItemsByProvider('breakdance').length || typeof MutationObserver === 'undefined') {
			return;
		}

		var roots = document.querySelectorAll('.bde-form-builder, form.breakdance-form');

		if (!roots.length) {
			return;
		}

		for (var i = 0; i < roots.length; i++) {
			inspectBreakdanceNode(roots[i]);

			roots[i].addEventListener('submit', function (event) {
				restoreHiddenDescendants(event.currentTarget || event.target);
			}, true);

			new MutationObserver(function (mutations) {
				for (var m = 0; m < mutations.length; m++) {
					for (var n = 0; n < mutations[m].addedNodes.length; n++) {
						inspectBreakdanceNode(mutations[m].addedNodes[n]);
					}

					if (mutations[m].target) {
						inspectBreakdanceNode(mutations[m].target);
					}
				}
			}).observe(roots[i], {
				childList: true,
				subtree: true,
				characterData: true
			});
		}
	}
	
		// -------------------------------------------------------------------------
	// Provider: Elementor Pro.
	// -------------------------------------------------------------------------

	function getElementorFormRoot(element) {
		if (!element || !element.closest) {
			return null;
		}

		return element.closest('form.elementor-form');
	}

	function hideElementorRelatedMessages(form, item) {
		if (!form || !form.querySelectorAll || !item) {
			return;
		}

		var messages = form.querySelectorAll('.elementor-message-danger, .elementor-error, .elementor-field-error');

		for (var i = 0; i < messages.length; i++) {
			var messageText = normalize(messages[i].textContent);
			var plainText = normalize(item.plain);
			var isFieldMessage = !!(messages[i].closest && messages[i].closest('.elementor-field-group'));

			if (!messageText) {
				continue;
			}

			if (
				!isFieldMessage ||
				(item.marker && messageText.indexOf(item.marker) !== -1) ||
				(plainText && messageText.indexOf(plainText) !== -1)
			) {
				messages[i].textContent = stripMarker(messages[i].textContent, item);
				hideElement(messages[i], item);
			}
		}
	}

	function inspectElementorErrorElement(element) {
		if (!element || !element.textContent) {
			return;
		}

		var text = normalize(element.textContent);

		if (!text) {
			return;
		}

		var item = findByMarker(text, 'elementor', '');

		if (!item) {
			return;
		}

		var form = getElementorFormRoot(element);

		element.textContent = stripMarker(element.textContent, item);
		hideElement(element, item);

		hideElementorRelatedMessages(form, item);
		openModal(item);
	}

	function inspectElementorNode(node) {
		var element = node && node.nodeType === 3 ? node.parentNode : node;

		if (!element || element.nodeType !== 1) {
			return;
		}

		var errorSelector = '.elementor-message-danger, .elementor-error, .elementor-field-error';

		if (element.matches && element.matches(errorSelector)) {
			inspectElementorErrorElement(element);
		}

		if (!element.querySelectorAll) {
			return;
		}

		var errors = element.querySelectorAll(errorSelector);

		for (var i = 0; i < errors.length; i++) {
			inspectElementorErrorElement(errors[i]);
		}
	}

	function initElementor() {
		if (!getItemsByProvider('elementor').length || typeof MutationObserver === 'undefined') {
			return;
		}

		var roots = document.querySelectorAll('form.elementor-form, .elementor-form form, form[data-elementor-id]');

		if (!roots.length) {
			return;
		}

		for (var i = 0; i < roots.length; i++) {
			inspectElementorNode(roots[i]);

			roots[i].addEventListener('submit', function (event) {
				restoreHiddenDescendants(event.currentTarget || event.target);
			}, true);

			new MutationObserver(function (mutations) {
				for (var m = 0; m < mutations.length; m++) {
					for (var n = 0; n < mutations[m].addedNodes.length; n++) {
						inspectElementorNode(mutations[m].addedNodes[n]);
					}

					if (mutations[m].target) {
						inspectElementorNode(mutations[m].target);
					}
				}
			}).observe(roots[i], {
				childList: true,
				subtree: true,
				characterData: true
			});
		}
	}
	
	// -------------------------------------------------------------------------
	// Provider: Formidable Forms.
	// -------------------------------------------------------------------------

	function getFormidableMessageSelector() {
		return 'div[id$="_error"], .frm_error, .frm_error_msg';
	}

	function hasVisibleFormidableMessages(container) {
		if (!container || !container.querySelectorAll) {
			return false;
		}

		var messages = container.querySelectorAll(getFormidableMessageSelector());

		for (var i = 0; i < messages.length; i++) {
			if (messages[i].getAttribute('data-duplicatekiller-modern-hidden') === '1') {
				continue;
			}

			if (normalize(messages[i].textContent)) {
				return true;
			}
		}

		return false;
	}

	function maybeHideFormidableContainer(element) {
		if (!element || !element.closest) {
			return;
		}

		var container = element.closest('.frm_error_style');

		if (!container) {
			return;
		}

		if (!hasVisibleFormidableMessages(container)) {
			hideElement(container, { marker: 'formidable' });
		}
	}
		function keepHiddenFormidableMessages(root) {
		if (!root || !root.querySelectorAll) {
			return;
		}

		var hiddenMessages = root.querySelectorAll('[data-duplicatekiller-modern-hidden="1"]');

		for (var i = 0; i < hiddenMessages.length; i++) {
			hiddenMessages[i].setAttribute('aria-hidden', 'true');
			hiddenMessages[i].style.display = 'none';
		}
	}

	function inspectFormidableErrorElement(element) {
		if (!element || !element.textContent) {
			return;
		}

		if (element.getAttribute('data-duplicatekiller-modern-hidden') === '1') {
			return;
		}

		var text = normalize(element.textContent);

		if (!text) {
			return;
		}

		var item = findByMarker(text, 'formidable', '');

		if (!item) {
			return;
		}

		element.textContent = stripMarker(element.textContent, item);
		hideElement(element, item);
		maybeHideFormidableContainer(element);
		openModal(item);
	}

	function inspectFormidableNode(node) {
		var element = node && node.nodeType === 3 ? node.parentNode : node;

		if (!element || element.nodeType !== 1) {
			return;
		}

		var errorSelector = getFormidableMessageSelector();

		if (element.matches && element.matches(errorSelector)) {
			inspectFormidableErrorElement(element);
		}

		if (!element.querySelectorAll) {
			return;
		}

		var errors = element.querySelectorAll(errorSelector);

		for (var i = 0; i < errors.length; i++) {
			inspectFormidableErrorElement(errors[i]);
		}
	}

	function initFormidable() {
		if (!getItemsByProvider('formidable').length || typeof MutationObserver === 'undefined') {
			return;
		}

		var roots = document.querySelectorAll('form.frm-show-form, .frm_forms form');

		if (!roots.length) {
			return;
		}

		for (var i = 0; i < roots.length; i++) {
			inspectFormidableNode(roots[i]);

			roots[i].addEventListener('submit', function (event) {
				keepHiddenFormidableMessages(event.currentTarget || event.target);
			}, true);

			new MutationObserver(function (mutations) {
				for (var m = 0; m < mutations.length; m++) {
					for (var n = 0; n < mutations[m].addedNodes.length; n++) {
						inspectFormidableNode(mutations[m].addedNodes[n]);
					}

					if (mutations[m].target) {
						inspectFormidableNode(mutations[m].target);
					}
				}
			}).observe(roots[i], {
				childList: true,
				subtree: true,
				characterData: true
			});
		}
	}
	
		// -------------------------------------------------------------------------
	// Provider: Ninja Forms.
	// -------------------------------------------------------------------------

	function getNinjaFormsErrorElement(element) {
		if (!element || !element.closest) {
			return null;
		}

		return element.closest('.nf-error-wrap, .nf-response-msg, .nf-form-errors');
	}

	function inspectNinjaFormsErrorElement(element) {
		if (!element || !element.textContent) {
			return;
		}

		if (element.getAttribute('data-duplicatekiller-modern-hidden') === '1') {
			return;
		}

		var text = normalize(element.textContent);

		if (!text) {
			return;
		}

		var item = findByMarker(text, 'ninjaforms', '');

		if (!item) {
			return;
		}

		element.textContent = stripMarker(element.textContent, item);

		var target = getNinjaFormsErrorElement(element) || element;

		hideElement(target, item);
		openModal(item);
	}

	function inspectNinjaFormsNode(node) {
		var element = node && node.nodeType === 3 ? node.parentNode : node;

		if (!element || element.nodeType !== 1) {
			return;
		}

		var errorSelector = '.nf-error-wrap, .nf-error-msg, .nf-response-msg, .nf-form-errors';

		if (element.matches && element.matches(errorSelector)) {
			inspectNinjaFormsErrorElement(element);
		}

		if (!element.querySelectorAll) {
			return;
		}

		var errors = element.querySelectorAll(errorSelector);

		for (var i = 0; i < errors.length; i++) {
			inspectNinjaFormsErrorElement(errors[i]);
		}
	}

	function initNinjaForms() {
		if (!getItemsByProvider('ninjaforms').length || typeof MutationObserver === 'undefined') {
			return;
		}

		var roots = document.querySelectorAll('.nf-form-cont');

		if (!roots.length) {
			roots = document.querySelectorAll('.ninja-forms-form-wrap');
		}

		if (!roots.length) {
			return;
		}

		for (var i = 0; i < roots.length; i++) {
			inspectNinjaFormsNode(roots[i]);

			new MutationObserver(function (mutations) {
				for (var m = 0; m < mutations.length; m++) {
					for (var n = 0; n < mutations[m].addedNodes.length; n++) {
						inspectNinjaFormsNode(mutations[m].addedNodes[n]);
					}

					if (mutations[m].target) {
						inspectNinjaFormsNode(mutations[m].target);
					}
				}
			}).observe(roots[i], {
				childList: true,
				subtree: true,
				characterData: true
			});
		}
	}
	
		// -------------------------------------------------------------------------
	// Provider: Fluent Forms.
	// -------------------------------------------------------------------------

	function getFluentFormsErrorElement(element) {
		if (!element || !element.closest) {
			return null;
		}

		return element.closest('.error.text-danger, .ff-errors-in-stack');
	}

	function inspectFluentFormsErrorElement(element) {
		if (!element || !element.textContent) {
			return;
		}

		if (element.getAttribute('data-duplicatekiller-modern-hidden') === '1') {
			return;
		}

		var text = normalize(element.textContent);

		if (!text) {
			return;
		}

		var item = findByMarker(text, 'fluentforms', '');

		if (!item) {
			return;
		}

		element.textContent = stripMarker(element.textContent, item);

		var target = getFluentFormsErrorElement(element) || element;

		hideElement(target, item);
		openModal(item);
	}

	function inspectFluentFormsNode(node) {
		var element = node && node.nodeType === 3 ? node.parentNode : node;

		if (!element || element.nodeType !== 1) {
			return;
		}

		var errorSelector = '.error.text-danger, .ff-errors-in-stack';

		if (element.matches && element.matches(errorSelector)) {
			inspectFluentFormsErrorElement(element);
		}

		if (!element.querySelectorAll) {
			return;
		}

		var errors = element.querySelectorAll(errorSelector);

		for (var i = 0; i < errors.length; i++) {
			inspectFluentFormsErrorElement(errors[i]);
		}
	}

	function initFluentForms() {
		if (!getItemsByProvider('fluentforms').length || typeof MutationObserver === 'undefined') {
			return;
		}

		var roots = document.querySelectorAll('.fluentform, form.frm-fluent-form, form[id^="fluentform_"]');

		if (!roots.length) {
			return;
		}

		for (var i = 0; i < roots.length; i++) {
			inspectFluentFormsNode(roots[i]);

			roots[i].addEventListener('submit', function (event) {
				restoreHiddenDescendants(event.currentTarget || event.target);
			}, true);

			new MutationObserver(function (mutations) {
				for (var m = 0; m < mutations.length; m++) {
					for (var n = 0; n < mutations[m].addedNodes.length; n++) {
						inspectFluentFormsNode(mutations[m].addedNodes[n]);
					}

					if (mutations[m].target) {
						inspectFluentFormsNode(mutations[m].target);
					}
				}
			}).observe(roots[i], {
				childList: true,
				subtree: true,
				characterData: true
			});
		}
	}

	// -------------------------------------------------------------------------
	// Boot.
	// -------------------------------------------------------------------------

	function init() {
		initCf7();
		initForminator();
		initWpforms();
		initBreakdance();
		initElementor();
		initFluentForms();
		initNinjaForms();
		initFormidable();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}());