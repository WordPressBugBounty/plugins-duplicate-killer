/* global DK_ELEMENTOR_UI */
(function () {
	"use strict";

	if (!DK_ELEMENTOR_UI || !Array.isArray(DK_ELEMENTOR_UI.messages)) {
		return;
	}

	var messages = DK_ELEMENTOR_UI.messages.map(normalize).filter(Boolean);

	if (!messages.length) {
		return;
	}

	function normalize(text) {
		return String(text || "").replace(/\s+/g, " ").trim();
	}

	function getDuplicateKillerMessage(text) {
		var normalizedText = normalize(text);

		for (var i = 0; i < messages.length; i++) {
			if (normalizedText === messages[i] || normalizedText.indexOf(messages[i]) !== -1) {
				return messages[i];
			}
		}

		return "";
	}

	function cleanupInlineErrors(form) {
		var foundMessage = "";
		var inlineErrors = form.querySelectorAll(
			".elementor-field-group .elementor-message-danger.elementor-help-inline, " +
			".elementor-field-group .elementor-message-danger.elementor-form-help-inline"
		);

		for (var i = 0; i < inlineErrors.length; i++) {
			var message = getDuplicateKillerMessage(inlineErrors[i].textContent);

			if (!message) {
				continue;
			}

			foundMessage = foundMessage || message;
			inlineErrors[i].style.display = "none";

			var fieldGroup = inlineErrors[i].closest ? inlineErrors[i].closest(".elementor-field-group") : null;

			if (fieldGroup) {
				fieldGroup.classList.remove("elementor-error");
			}
		}

		return foundMessage;
	}

	function cleanupSummaryErrors(form, duplicateKillerMessage) {
		var summaryErrors = form.querySelectorAll(".elementor-message-danger");

		for (var i = 0; i < summaryErrors.length; i++) {
			if (summaryErrors[i].closest(".elementor-field-group")) {
				continue;
			}

			var message = duplicateKillerMessage || getDuplicateKillerMessage(summaryErrors[i].textContent);

			if (!message) {
				continue;
			}

			if (normalize(summaryErrors[i].textContent) !== message) {
				summaryErrors[i].textContent = message;
			}
		}
	}

	function cleanupForm(form) {
		var duplicateKillerMessage = cleanupInlineErrors(form);

		cleanupSummaryErrors(form, duplicateKillerMessage);
	}

	function cleanupAll() {
		var forms = document.querySelectorAll("form.elementor-form");

		for (var i = 0; i < forms.length; i++) {
			cleanupForm(forms[i]);
		}
	}

	function observe() {
		if (!document.body || typeof MutationObserver === "undefined") {
			return;
		}

		var observer = new MutationObserver(function () {
			cleanupAll();
		});

		observer.observe(document.body, {
			childList: true,
			subtree: true,
			characterData: true
		});
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", function () {
			cleanupAll();
			observe();
		});
	} else {
		cleanupAll();
		observe();
	}
})();
