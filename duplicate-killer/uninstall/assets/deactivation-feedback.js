(function ($) {
	'use strict';

	let duplicateKillerDeactivateUrl = '';
	let duplicateKillerIsSubmitting = false;

	function duplicateKillerToggleSubmit() {
		const hasSelection = $('input[name="reason_key"]:checked').length > 0;

		$('#duplicatekiller-deactivation-form button[type="submit"]').prop('disabled', !hasSelection);
	}

	function duplicateKillerToggleFollowupInputs() {
		const selectedReason = $('input[name="reason_key"]:checked').val() || '';

		$('.duplicatekiller-feedback-option').removeClass('is-selected');
		$('.duplicatekiller-feedback-followup').hide();

		if (selectedReason) {
			$('input[name="reason_key"]:checked')
				.closest('.duplicatekiller-feedback-option')
				.addClass('is-selected');

			$('.duplicatekiller-feedback-followup[data-reason-input="' + selectedReason + '"]').show();
		}
	}

	function duplicateKillerResetForm() {
		const form = $('#duplicatekiller-deactivation-form')[0];

		if (form) {
			form.reset();
		}

		duplicateKillerIsSubmitting = false;

		$('.duplicatekiller-feedback-option').removeClass('is-selected');
		$('.duplicatekiller-feedback-followup').hide();

		$('#duplicatekiller-deactivation-form button[type="submit"]')
			.prop('disabled', true)
			.text('Submit & Deactivate');
	}

	function duplicateKillerRedirectToDeactivate() {
		if (duplicateKillerDeactivateUrl) {
			window.location.assign(duplicateKillerDeactivateUrl);
		}
	}

	$(document).on('click', '.duplicatekiller-deactivate-link', function (event) {
		event.preventDefault();

		duplicateKillerDeactivateUrl = $(this).attr('href') || '';

		duplicateKillerResetForm();

		$('#duplicatekiller-deactivation-modal').fadeIn(120);
	});

	$(document).on('change', 'input[name="reason_key"]', function () {
		duplicateKillerToggleSubmit();
		duplicateKillerToggleFollowupInputs();
	});

	$(document).on('click', '.duplicatekiller-feedback-close, .duplicatekiller-feedback-backdrop', function () {
		if (!duplicateKillerIsSubmitting) {
			$('#duplicatekiller-deactivation-modal').fadeOut(120);
		}
	});

	$(document).on('click', '.duplicatekiller-skip-deactivate', function () {
		duplicateKillerRedirectToDeactivate();
	});

	$(document).on('submit', '#duplicatekiller-deactivation-form', function (event) {
		event.preventDefault();

		if (duplicateKillerIsSubmitting) {
			return;
		}

		duplicateKillerIsSubmitting = true;

		const selectedReason = $('input[name="reason_key"]:checked').val() || '';
		const followupText = $('.duplicatekiller-feedback-followup[data-reason-input="' + selectedReason + '"]').val() || '';
		const generalText = $('#duplicatekiller-feedback-details').val() || '';
		const reasonText = $.trim([followupText, generalText].filter(Boolean).join("\n\n"));

		$('#duplicatekiller-deactivation-form button[type="submit"]')
			.prop('disabled', true)
			.text('Deactivating...');

		$.ajax({
			url: duplicateKillerDeactivationFeedback.ajaxUrl,
			type: 'POST',
			timeout: 2500,
			data: {
				action: duplicateKillerDeactivationFeedback.action,
				nonce: duplicateKillerDeactivationFeedback.nonce,
				reason_key: selectedReason,
				reason_text: reasonText
			}
		}).always(function () {
			duplicateKillerRedirectToDeactivate();
		});

		// Safety fallback: never block deactivation if AJAX is delayed or blocked.
		setTimeout(function () {
			duplicateKillerRedirectToDeactivate();
		}, 3000);
	});

	$(document).ready(function () {
		$('.duplicatekiller-feedback-followup').hide();
		duplicateKillerToggleSubmit();
	});
})(jQuery);