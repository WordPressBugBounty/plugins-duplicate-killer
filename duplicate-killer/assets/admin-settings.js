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