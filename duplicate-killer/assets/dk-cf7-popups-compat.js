(function () {
	'use strict';

	function getApiResponse(event) {
		if (!event || !event.detail || !event.detail.apiResponse) {
			return null;
		}

		return event.detail.apiResponse;
	}

	function getMessage(response) {
		if (!response || typeof response.message !== 'string') {
			return '';
		}

		return response.message.trim();
	}

	function getStatus(response, eventName) {
		if (response && typeof response.status === 'string' && response.status !== '') {
			return response.status;
		}

		if (eventName === 'wpcf7aborted') {
			return 'aborted';
		}

		return '';
	}

	function getSwal() {
		if (typeof window.swal === 'function') {
			return window.swal;
		}

		if (typeof window.sweetAlert === 'function') {
			return window.sweetAlert;
		}

		if (typeof window.Sweetalert2 === 'function') {
			return window.Sweetalert2;
		}

		return null;
	}

	function getPopupTitle(status) {
		if (typeof window.cf7_popups_val !== 'object' || window.cf7_popups_val === null) {
			return status === 'mail_sent' ? 'Email Sent' : 'Error';
		}

		if (status === 'mail_sent') {
			return window.cf7_popups_val.msg6 || 'Email Sent';
		}

		if (status === 'validation_failed') {
			return window.cf7_popups_val.msg1 || 'Validation Error';
		}

		return window.cf7_popups_val.msg3 || 'Error';
	}

	function getPopupType(status) {
		if (status === 'mail_sent') {
			return 'success';
		}

		if (status === 'spam') {
			return 'warning';
		}

		return 'error';
	}

	function closeExistingPopup(swalInstance) {
		if (!swalInstance) {
			return;
		}

		if (typeof swalInstance.close === 'function') {
			swalInstance.close();
			return;
		}

		if (typeof swalInstance.closeModal === 'function') {
			swalInstance.closeModal();
		}
	}

	function showPopup(status, message) {
		var swalInstance = getSwal();

		if (!swalInstance || !message) {
			return;
		}

		closeExistingPopup(swalInstance);

		window.setTimeout(function () {
			swalInstance({
				title: '<strong>' + getPopupTitle(status) + '</strong>',
				type: getPopupType(status),
				html: message,
				showCloseButton: true,
				showConfirmButton: false
			});
		}, 75);
	}

	function isGenericCf7PopupsMessage(message) {
		if (typeof window.cf7_popups_val !== 'object' || window.cf7_popups_val === null) {
			return false;
		}

		return [
			window.cf7_popups_val.msg2,
			window.cf7_popups_val.msg4,
			window.cf7_popups_val.msg5,
			window.cf7_popups_val.msg7
		].indexOf(message) !== -1;
	}

	function handleCf7Event(event) {
		var response = getApiResponse(event);
		var message = getMessage(response);
		var status = getStatus(response, event.type);

		if (!message || !status) {
			return;
		}

		// Duplicate Killer blocks CF7 by aborting the send. cf7-popups does not handle this event.
		if (status === 'aborted' || event.type === 'wpcf7aborted') {
			showPopup('aborted', message);
			return;
		}

		// For other CF7 statuses, only override cf7-popups when the response has a real custom message.
		if (isGenericCf7PopupsMessage(message)) {
			return;
		}

		showPopup(status, message);
	}

	document.addEventListener('wpcf7aborted', handleCf7Event, false);
	document.addEventListener('wpcf7submit', handleCf7Event, false);
	document.addEventListener('wpcf7invalid', handleCf7Event, false);
	document.addEventListener('wpcf7spam', handleCf7Event, false);
	document.addEventListener('wpcf7mailfailed', handleCf7Event, false);
	document.addEventListener('wpcf7mailsent', handleCf7Event, false);
})();