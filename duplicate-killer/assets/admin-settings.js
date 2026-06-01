// admin-settings.js
function toggleSectionOnCheckbox(checkboxId, targetId) {
	const checkbox = document.getElementById(checkboxId);
	const target = document.getElementById(targetId);

	if (!checkbox || !target) return;

	checkbox.addEventListener('change', () => {
		target.style.display = checkbox.checked ? 'block' : 'none';
	});

	target.style.display = checkbox.checked ? 'block' : 'none';
}
// New function for dynamic toggle per form
function initDuplicateKillerToggles() {
	const switches = document.querySelectorAll('.ios-switch-input[data-target]');

	switches.forEach(toggle => {
		const targetSelector = toggle.getAttribute('data-target');
		const target = document.querySelector(targetSelector);

		if (!target) return;

		if (toggle.checked) {
			target.classList.add('is-active');
		} else {
			target.classList.remove('is-active');
		}

		toggle.addEventListener('change', () => {
			target.classList.toggle('is-active', toggle.checked);
		});
	});
}
document.addEventListener('DOMContentLoaded', function () {
	toggleSectionOnCheckbox('user_ip', 'dk-limit-ip');
	toggleSectionOnCheckbox('save_image', 'dk-save-image-path');
		
	initDuplicateKillerToggles();

});
function copyDKShortcode(inputId) {
  var input = document.getElementById(inputId);
  input.select();
  input.setSelectionRange(0, 99999); // For mobile devices
  document.execCommand("copy");

  var toast = document.getElementById("dk-toast");
  toast.style.display = "block";

  setTimeout(function() {
    toast.style.display = "none";
  }, 2500);
}
document.addEventListener('click', function (event) {
	const button = event.target.closest('.dk-advanced-toggle');

	if (!button) {
		return;
	}

	const targetId = button.getAttribute('aria-controls');
	const target = document.getElementById(targetId);

	if (!target) {
		return;
	}

	const isOpen = target.classList.toggle('is-active');

	button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

	const icon = button.querySelector('.dk-advanced-toggle-icon');

	if (icon) {
		icon.textContent = isOpen ? '−' : '+';
	}
});
document.addEventListener('DOMContentLoaded', function () {
	const modal = document.getElementById('dk-db-submission-modal');
	const body = document.getElementById('dk-db-modal-body');
	const meta = document.getElementById('dk-db-modal-meta');
	const copyButton = document.getElementById('dk-db-copy-submission');
	const sidebarSearch = document.getElementById('dk-db-sidebar-search');

	if (copyButton) {
		copyButton.addEventListener('click', function () {
			const text = body ? body.innerText.trim() : '';

			if (!text) {
				return;
			}

			navigator.clipboard.writeText(text).then(function () {
				copyButton.textContent = 'Copied';

				setTimeout(function () {
					copyButton.textContent = 'Copy all data';
				}, 1400);
			});
		});
	}
	if (sidebarSearch) {
		sidebarSearch.addEventListener('input', function () {
			const query = sidebarSearch.value.toLowerCase().trim();
			const plugins = document.querySelectorAll('.dk-db-sidebar__plugin');

			plugins.forEach(function (plugin) {
				const text = plugin.textContent.toLowerCase();
				const matches = text.indexOf(query) !== -1;

				plugin.style.display = matches ? '' : 'none';
			});
		});
	}

	if (!modal || !body) {
		return;
	}

	function openModal(content, button) {
		if (meta && button) {
			meta.innerHTML = `
				<span><strong>Plugin</strong>${button.dataset.plugin || '-'}</span>
				<span><strong>Form</strong>${button.dataset.form || '-'}</span>
				<span><strong>Date</strong>${button.dataset.date || '-'}</span>
				<span><strong>IP</strong>${button.dataset.ip || '-'}</span>
			`;
		}
		body.innerHTML = content;
		modal.hidden = false;
		document.body.classList.add('dk-db-modal-open');
	}

	function closeModal() {
		modal.hidden = true;
		body.innerHTML = '';
		document.body.classList.remove('dk-db-modal-open');
	}

	document.addEventListener('click', function (event) {
		const button = event.target.closest('.dk-db-view-submission');

		if (button) {
			const id = button.getAttribute('data-submission-id');
			const content = document.getElementById('dk-db-submission-full-' + id);

			if (content) {
				openModal(content.innerHTML, button);
			}
		}
		const mobileRow = event.target.closest('.dk-db-table-card .wp-list-table tbody tr');

		if (
			mobileRow &&
			window.innerWidth <= 782 &&
			!event.target.closest('a') &&
			!event.target.closest('button') &&
			!event.target.closest('input') &&
			!event.target.closest('label')
		) {
			const mobileButton = mobileRow.querySelector('.dk-db-view-submission');

			if (mobileButton) {
				const id = mobileButton.getAttribute('data-submission-id');
				const content = document.getElementById('dk-db-submission-full-' + id);

				if (content) {
					openModal(content.innerHTML, mobileButton);
				}
			}
		}	
		if (event.target.closest('[data-dk-db-close]')) {
			closeModal();
		}
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape' && !modal.hidden) {
			closeModal();
		}
	});
});