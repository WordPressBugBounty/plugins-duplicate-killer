=== Duplicate Killer – Prevent Duplicate Form Submissions ===
Version: 1.5.0
Author: NIA
Author URI: https://profiles.wordpress.org/wpnia/
Contributors: wpnia
Tags: duplicates, forms, validation, elementor, cf7
Donate link: https://www.paypal.com/paypalme/wpnia
Requires at least: 5.2
Tested up to: 6.9
Stable tag: 1.5.0
Requires PHP: 5.6.20
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Block duplicate form submissions by validating unique email, phone and text fields — without CAPTCHA.

== Description ==

Duplicate Killer – Block Duplicate Form Submissions in WordPress.

If your forms receive the same email address multiple times, fake leads, or repeated submissions, this plugin blocks them instantly.

Choose which fields must be unique (email, phone, text) and block duplicate entries automatically — without changing your form design or user experience.

Duplicate Killer works silently in the background and integrates seamlessly with popular WordPress form plugins and page builders.

Free: In the free version, duplicate protection can be enabled for one form per supported plugin.

PRO: Duplicate Killer PRO enables multi-form protection with individual rules and messages per form.

== Supported Plugins ==

- Elementor Forms
- Contact Form 7
- Formidable Forms
- WPForms Lite
- Forminator
- Ninja Forms
- Breakdance Page Builder Forms


== Key Features ==

✔ Prevent duplicate form submissions without CAPTCHA
✔ Works with existing forms – no changes required
✔ Prevent duplicate submissions by Email, Phone or Text fields  
✔ Stop duplicate emails and repeated leads  
✔ One global error message for all forms (Free version)  
✔ Optional IP-based duplicate protection  
✔ Store unique entries securely in your WordPress database  
✔ Automatically store uploaded files from Contact Form 7  
✔ Lightweight, fast and easy to configure  

== Common use cases ==
- Prevent duplicate leads in contact and lead generation forms
- Keep CRM and email lists clean
- Block repeated event registrations with the same email

=== Ninja Forms ===
- Prevent duplicate submissions by Email, Phone or Text fields
- Clean validation messages
- No form design changes required

== NEW: Formidable Forms Support ==

Duplicate Killer now fully supports Formidable Forms and helps you stop duplicate form submissions in WordPress.

You can prevent duplicate entries in Formidable Forms contact forms and advanced forms by enforcing unique fields such as email address, phone number, or text fields.

This feature works with Formidable Forms native fields, requires no additional configuration.

Does not affect form design or user experience.

== NEW: Elementor Forms Support ==

Duplicate Killer now fully supports Elementor Forms.

You can prevent duplicate submissions in Elementor contact forms, lead forms and popups by enforcing unique values such as email or phone number.

This feature works with Elementor’s native form widget and requires no additional configuration.


== Plugin Integrations ==

=== Contact Form 7 ===
- Limit submissions by Email, Phone or Text fields
- Custom validation message for duplicate entries
- Automatically store uploaded files locally

=== Elementor Forms ===
- Prevent duplicate submissions on Elementor native forms
- Works with contact forms, lead forms and popups
- Seamless integration without modifying form structure

=== Forminator ===
- Select unique fields (Email, Phone, Text)
- Warn users when a value has already been submitted

=== WPForms Lite ===
- Prevent duplicate entries without changing form layout
- Clean and simple validation messages

=== Breakdance Page Builder Forms ===
- Prevent duplicate submissions on Breakdance native forms
- IP-based validation for cleaner data
- Fully compatible with Breakdance UI

== Free vs Pro ==

=== Free Version ===
- Protect one form (per supported plugin)
- Global duplicate protection rules (for the protected form)
- One global error message
- Global IP-based submission limits
- Unique entries per user (cookie-based)

=== Duplicate Killer PRO ===
- Protect multiple forms
- Per-form duplicate protection rules
- Custom error message for each form
- Different IP limits per form
- Unique entries per user, configurable per form
- Designed for sites with multiple forms and different submission needs
- Duplicate Killer PRO is ideal for sites with multiple forms and different audiences.


== Installation ==

1. Install Duplicate Killer from the WordPress Plugins screen or upload it to `/wp-content/plugins/`.
2. Activate the plugin from the Plugins menu.
3. Open Duplicate Killer from your WordPress admin dashboard.
4. Select your form plugin and choose which fields must be unique.

== Frequently Asked Questions ==

= Does this plugin block bots or spam? =
Duplicate Killer focuses on preventing duplicate form submissions and entries. It works alongside spam or CAPTCHA plugins and does not replace them.

= Will this slow down my site? =
No. Duplicate Killer performs lightweight database checks only on form submission and has no impact on frontend performance.

= Why should I use Duplicate Killer? =
Duplicate Killer prevents duplicate emails and repeated form submissions, helping you keep your leads and contact data clean.

= How does Duplicate Killer work? =
When a form is submitted, selected field values are stored in the database. If the same value is submitted again, the submission is blocked and a validation message is shown.

= Can multiple users submit the same value? =
Yes. In the PRO version, you can enable “Unique entries per user”, allowing multiple users to submit the same value while blocking repeat submissions from the same user.

= Does it affect form design or styling? =
No. Duplicate Killer works in the background and does not change your form appearance.

= Does it work with Elementor Forms? =
Yes. Duplicate Killer fully supports Elementor Forms.

= Does this plugin work with existing spam protection plugins? =
Yes. Duplicate Killer works alongside spam and CAPTCHA plugins and focuses only on preventing duplicate form submissions.

== When to use Duplicate Killer ==
Use this plugin if you receive repeated submissions with the same email, phone number or text values.
If you only need basic spam protection, a CAPTCHA plugin may be enough.

== When should I use Duplicate Killer PRO? ==
Use the PRO version if your site has multiple forms with different purposes — for example a contact form, a registration form and a newsletter signup — and each one needs different duplicate submission rules.

== Screenshots ==

1. Custom error message shown when a duplicate submission is detected
2. Plugin settings – prevent duplicate form submissions in WordPress
3. Block repeat submissions from the same user using browser cookies
4. Display total form entries on the frontend using a shortcode
5. Works with popular WordPress form plugins

== Upgrade Notice ==
= 1.5.0 =
Track how many duplicate submissions your site has been protected from and unlock milestone insights inside the admin.

== Changelog ==

= 1.5.0 =
* New milestone system: track total duplicates blocked.
* Admin insights: see how many duplicates Duplicate Killer stopped.
* Smart review prompts at key protection milestones.
* Internal improvements and stability fixes.

= 1.4.9 =
* Improved security and data validation.
* Better compatibility with latest WordPress versions.
* Cleaner and more stable file handling for uploaded files.
* Improved admin performance and script loading.
* Translation system aligned with WordPress standards.
* General code cleanup and stability improvements.

= 1.4.8 =
* Updated: Free vs PRO structure updated to reflect the long-term direction of the plugin.
* Added: Visual guidance in the admin area for multi-form protection.
* Improved: Minor improvements to cookie-based duplicate detection.

= 1.4.7 =
* New: Full Ninja Forms support – block duplicate submissions before entries are saved.
* Major upgrade: Cookie engine fully rewritten for better performance and reliability.
* Improved compatibility with cache plugins and strict Content Security Policies (CSP).
* Smarter cookie-based uniqueness logic, applied only when enabled per form.
* Internal optimizations preparing support for additional form plugins.

= 1.4.6 =
* New: Formidable Forms support – stop duplicate form submissions by email or other fields.
* Prevent duplicate entries by email, phone, or text fields in Formidable Forms.
* Improved compatibility and stability across supported form plugins.

= 1.4.5 =
* New feature: Duplicate protection for Elementor Forms

= 1.4.4 =
* Bug fix: Undefined array key Forminator

= 1.4.3 =
* Bug: Problem with table creation

= 1.4.2 =
* Support Number field on Forminator

= 1.4.1 =
* Tested up to WordPress 6.9

= 1.4.0 =
* Feature: Added support for forms built with Breakdance Page Builder.

= 1.3.1 =
* Feature: Automatically store uploaded files from the form (CF7) on your server

= 1.3.0 =
* Tested up to 6.8.1
* Feature: Restrict form entries based on IP address

= 1.2.3 =
* Feature: Store CF7 files submitted

= 1.2.2 =
* Bug: Problem with table creation
* Feature: Add form date submission

= 1.2.1 =
* Feature: Store CF7/Forminator/WPForms submissions to your WordPress database
* Tested up to 6.7.2 Wordpress

= 1.2.0 =
* Bug: Fixed - Custom HTML in CF7 form – issue with detection

= 1.1.9 =
* Bug: Fixed only  first 3 forms are showing in the “WPForms forms list”

= 1.1.7 =
* Bug: Fixed style sheet.

= 1.1.6 =
* Bug: Prevent empty values from being detected as duplicate entries.

= 1.1.5 =
* Interface to manage the saved values in your WordPress database.

= 1.1.4 =
* Duplicate Killer will prevent the entries from being added into CFDB7(Contact Form 7 Database Addon) plugin.
* Tested up to 6.4.1 Wordpress'

= 1.1.3 =
* Fixed PHP Warning Undefined array key at CF7 function
* Tested up to 6.3.1 Wordpress'

= 1.1.2 =
* Tested up to 6.1.1 Wordpress'

= 1.1.1 =
* Fix bug at feature 'Unique entries per user'

= 1.1.0 =
* New feature - Unique entries per user
* New style navigation for better UX

= 1.0 =
* First public release