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

document.addEventListener('DOMContentLoaded', function () {
	toggleSectionOnCheckbox('cookie', 'dk-unique-entries-cookie');
	toggleSectionOnCheckbox('user_ip', 'dk-limit-ip');
	toggleSectionOnCheckbox('save_image', 'dk-save-image-path');
});