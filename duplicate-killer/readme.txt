=== Duplicate Killer – Prevent Duplicate Form Submissions ===
Version: 1.7.7
Author: NIA
Author URI: https://profiles.wordpress.org/wpnia/
Contributors: wpnia
Tags: woocommerce, duplicate orders, forminator, elementor forms, contact form 7
Donate link: https://www.paypal.com/paypalme/wpnia
Requires at least: 5.3
Tested up to: 7.1.3
Stable tag: 1.7.7
Requires PHP: 5.6.20
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Prevent duplicate form submissions by email, phone or text. Supports Contact Form 7, Elementor Forms, Forminator, WPForms Lite and more.

== Description ==
Duplicate Killer prevents repeated submissions in WordPress forms. Choose which supported fields must be unique, such as an email address, phone number or text value. When a matching value has already been saved by Duplicate Killer, the plugin blocks the repeated submission and displays your message.

Use it to reduce duplicate leads, repeated contact requests and multiple registrations with the same email address. No CAPTCHA is required for duplicate checks.

= Reduce repeated form spam without adding CAPTCHA =
Some unwanted submissions repeat the same email address, phone number or other field value. Duplicate Killer can block these repeated values in supported WordPress forms. Optional IP-based limits can also restrict further submissions from the same IP address during your chosen protection period.

Duplicate Killer adds duplicate submission protection, not general bot detection. Spam using new values and different IP addresses may still pass these checks.

= When is Duplicate Killer useful? =
* Contact forms: reduce repeated requests using the same email address or phone number.
* Registration forms: block another registration with an email address already saved by Duplicate Killer.
* Lead forms: reduce repeated entries before your team follows up.
* Time-based restrictions: allow the same value again after a protection period in minutes, hours or days.
* Multiple forms with PRO: check selected forms within the same supported form plugin.

Duplicate Killer checks submitted values. It does not verify that an email address belongs to the visitor or guarantee one entry per person.

= Supported form plugins =
Duplicate Killer adds duplicate submission protection to existing forms. It does not create forms. Available fields depend on the integration. The Free version allows configuration of the most recently created form in each supported form plugin.

= Contact Form 7: prevent duplicate entries =
Block repeated Contact Form 7 submissions using selected fields, such as email, phone or text. Show your duplicate message through Classic Message or Modern Popup Mode.

= Elementor Forms: prevent duplicate submissions =
Add duplicate checks to forms built with the Elementor Pro Form widget. Duplicate Killer PRO also includes Group Mode for forms that share the same Form Name.

= Forminator: control repeated submissions =
Choose supported Forminator fields to check for repeated values. Set a protection period when the same value should be allowed again after a certain time.

= WPForms Lite: add duplicate-field checks =
Use Duplicate Killer with WPForms Lite to check selected fields against entries saved by Duplicate Killer. Visitors receive your configured message when a duplicate is blocked.

= Formidable Forms: require unique field values =
Apply duplicate checks to supported Formidable Forms fields. For example, check an email field to reduce repeated registrations with the same address.

= Ninja Forms: block repeated form values =
Add duplicate protection to supported Ninja Forms fields. Choose the values to check and explain blocked submissions with your own duplicate message.

= Breakdance Forms: protect your existing forms =
Check supported fields in forms built with the Breakdance Form Builder. Configure duplicate protection without replacing your existing form.

= Fluent Forms: reduce duplicate leads =
Check selected Fluent Forms fields for values already saved by Duplicate Killer. Use this to reduce repeated contact requests or lead submissions.

= MetForm: duplicate protection in beta =
Add duplicate checks and Modern Popup Mode to supported MetForm forms. Submission details can include links to uploaded files. Test MetForm Pro payment forms and external integrations on a staging site before use.

= WordPress form submission limits and IP protection =
* Select the fields to check for duplicate values.
* Set duplicate protection periods in days, hours and minutes.
* Configure IP-based submission limits separately.
* Use optional browser cookie protection.
* Review saved submissions in your WordPress dashboard.
* Display the saved submission count with a shortcode.

Field checks and IP limits serve different purposes. Field checks look for repeated values, such as an email address. IP limits restrict submissions from the same IP address, even when the submitted values differ. Configure them separately for your form.

People on a shared network may use the same public IP address. Choose IP restrictions carefully when several visitors may submit from an office, school or household.

= Example: block the same email for 24 hours =
Select the email field in your protected form and set the duplicate protection period to 1 day. Save your settings, then submit a new email address. Another submission with that email will be blocked during the protection period.

Other enabled rules, such as IP limits, are checked separately.

= Classic Message or Modern Popup Mode =
Keep a standard duplicate error message or display a custom popup. Modern Popup Mode supports formatted text, links and colors. Preview your popup in the settings before saving. Existing forms keep Classic Message by default.

= Free and PRO =
The Free version lets you configure the most recently created form in each supported form plugin, with custom protection settings and messages.

Duplicate Killer PRO lets you protect multiple forms with separate settings. Cross-Form Duplicate Protection checks selected forms within the same supported form plugin. Elementor Group Mode groups forms that share the same Form Name.

Use separate rules when your contact form and registration form have different requirements. Use Cross-Form Duplicate Protection when several forms collect entries for the same campaign and should share duplicate checks.

For Elementor sites that reuse forms with the same Form Name, Group Mode helps apply protection across those forms.

[Compare Free and PRO](https://verselabwp.com/duplicate-killer/)

= WooCommerce duplicate order protection =
The Free version also includes duplicate order protection for WooCommerce Classic Checkout, with a 60-second protection window. PRO adds Checkout Blocks support, a configurable protection window and additional reporting tools.

== Installation ==

1. Install Duplicate Killer from the WordPress Plugins screen or upload it to `/wp-content/plugins/`.
2. Activate the plugin from the Plugins menu.
3. Open Duplicate Killer from your WordPress admin dashboard.
4. Select your form plugin and choose which fields must be unique.
5. Save your settings. Submit a test entry, then try the same protected value again.

== Frequently Asked Questions ==

= Does this plugin block bots or spam? =
Duplicate Killer focuses on preventing duplicate form submissions and entries. It works alongside spam or CAPTCHA plugins and does not replace them.

= Will this slow down my site? =
Duplicate Killer checks submitted values against saved entries. Performance depends on your hosting, settings and submission history. Modern Popup Mode also uses frontend assets when enabled.

= Why should I use Duplicate Killer? =
Duplicate Killer helps reduce duplicate leads and contact requests by checking selected email, phone or text fields. Set a protection period and explain blocked submissions with your own message.

= Does Duplicate Killer support WooCommerce? =
Yes. The FREE version protects WooCommerce Classic Checkout from accidental duplicate submissions within a 60-second window.

= How does Duplicate Killer work? =
Duplicate Killer compares selected field values with entries it has previously saved. A matching value can block the submission according to your protection settings and duration. Earlier entries stored only by your form plugin are not automatically imported.
For WooCommerce, a unique order fingerprint is generated from billing email, cart items, order total and currency.

= How does cookie-based protection identify visitors? =
Cookie-based protection uses the visitor's browser. It does not verify a person's identity. Clearing cookies or using another browser can change how the visitor is recognized.

= Does it affect form design or styling? =
Classic Message uses your form plugin's error display. Modern Popup Mode shows a custom popup for Duplicate Killer duplicate-field messages. It does not redesign your form fields.

= Does it work with Elementor Forms? =
Yes. Duplicate Killer supports forms created with the Elementor Pro Form widget. Duplicate Killer PRO also includes Group Mode for Elementor forms that share the same Form Name.

= Do I need CAPTCHA to prevent duplicate submissions? =
No. Duplicate Killer checks repeated values without requiring a CAPTCHA challenge. CAPTCHA and anti-spam tools address different problems and may still be useful alongside duplicate protection. Test the combined setup with your form plugin and extensions.

= Can I block repeated form submissions from the same IP address? =
Yes. Enable IP-based protection for your protected form and set a duration in days, hours and minutes. This limits form submissions from that IP address; it does not block access to your whole WordPress website.

= Why is a new email address being blocked? =
Check whether IP-based or cookie-based protection is enabled. These rules are separate from duplicate-field checks and may restrict a submission even when the email address is new.

= Does FREE support WooCommerce Checkout Blocks? =
No. Checkout Blocks use a different system (Store API) and require the PRO version.

= What happens if I upgrade to PRO later? =
All WooCommerce duplicate logs stored in the FREE version are automatically used by PRO analytics. No database migration is required.

= Does WooCommerce protection prevent every duplicate payment? =
No. Duplicate order protection and payment processing are different. Duplicate Killer checks checkout submissions, but payment gateway retries and callbacks also depend on WooCommerce and the gateway integration.

== Screenshots ==

1. Custom validation message displayed when a duplicate WordPress form submission is detected.
2. Plugin settings for selecting which WordPress form fields should be protected from duplicate submissions.
3. Browser-based protection prevents the same visitor from submitting the same value more than once per form.
4. Display the total number of saved form submissions on the WordPress frontend using a generated shortcode.
5. IP-based protection blocks repeat form submissions from the same address for a configurable number of days.
6. WooCommerce duplicate order protection blocks accidental checkout retries, repeated clicks, and duplicate submissions.

== Upgrade Notice ==
= 1.7.7 =
Improved Formidable Forms payment handling and submission field labels. Failed payments no longer block retries.

== Changelog ==

= 1.7.7 =
* Fixed duplicate protection for Formidable Forms payments.
* Failed payments and invalid submissions no longer create Duplicate Killer records.
* Improved Product, Quantity, and Total field labels in submission details.

= 1.7.6 =
* Fixed duplicate protection for WPForms Stripe payments.
* Failed payments no longer create Duplicate Killer records.
* Fixed duplicate errors when returning from 3D Secure authentication.

= 1.7.5 =
* Fixed duplicate submission handling for Contact Form 7 native Stripe payments.
* Payment forms now save Duplicate Killer records only after payment is confirmed.
* Prevented Stripe checkout from replacing duplicate protection messages.

= 1.7.4 =
* New: Added SureForms support for duplicate submission protection.
* New: Added early duplicate email checks for SureForms Stripe payments.
* Fix: Improved support for longer form names.

= 1.7.3 =
* You’re in control! Choose the exact form you want to protect with Duplicate Killer.
* Switch forms easily from the plugin settings.

= 1.7.2 =
* Added MetForm support for duplicate submission protection.
* Added Modern Popup Mode for MetForm.
* Display uploaded file links in submission details.
* Added MetForm diagnostics and deactivation feedback.

= 1.7.1 =
* Added hours and minutes to duplicate submission block duration.
* Added hours and minutes to IP block duration.
* Fixed IP block expiry when the site uses a local time zone.
* Highlighted active duration values in the settings.

= 1.7.0 =
* New: Modern Popup Mode displays duplicate submission messages in a customizable popup.
* Customize messages with links, colors, and formatted text.
* Preview your popup directly in the plugin settings.
* Choose Classic Message or Modern Popup Mode for each form.
* Existing forms keep Classic Message by default.

= 1.6.8 =
* Improved WordPress.org Plugin Check compliance across admin, diagnostics, and database handling.
* Refined escaping, internationalization comments, and compatibility safeguards.
* Added clearer inline documentation for intentional custom-table queries.

= 1.6.7 =
* Added Fluent Forms support for duplicate submission protection.
*Improved admin UI compatibility for Fluent Forms fields.

= 1.6.6 =
* Improved compatibility with popup-based Contact Form 7 message plugins.
* Duplicate protection messages now display correctly when Contact Form 7 responses are shown in popups.
* Improved handling for blocked Contact Form 7 submissions without changing duplicate detection behavior.

= 1.6.5 =
* Tweaks: Improved Elementor duplicate submission blocking.
* Tweaks: Added safer Elementor field-level validation handling.
* Tweaks: Major update to the frontend display of the submissions page.
* Fix: Duplicate error messages appearing multiple times on Elementor forms.
* Fix: Woocommerce call to undefined function

= 1.6.4 =
* Minor stability and duplicate detection improvements for Breakdance Page Builder and Ninja Forms.

= 1.6.3 =
* Added custom field duplicate block duration setting per form.
* Minor stability and duplicate detection improvements.

= 1.6.2 =
* Major internal architecture refactor for duplicate validation system
* Improved duplicate validation consistency across all supported form plugins.
* Improved maintainability for future form plugin integrations.
* General code cleanup and internal optimization.

= 1.6.1 =
* This update includes a major admin UI and CSS structure cleanup, improving layout consistency, responsiveness, and overall maintainability across the plugin interface.

= 1.6.0 =
* New: Deactivation feedback modal to collect user insights when disabling the plugin.
* New: Secure API integration for anonymous feedback tracking (privacy-friendly).
* New: Advanced analytics foundation for better product decisions.
* Improved: Overall stability and internal architecture.

= 1.5.9 =
* Major update: improved database structure for better consistency and scalability
* Fixed form identification inconsistencies (especially for builders like Breakdance)
* Improved duplicate detection accuracy across all integrations
* Enhanced cookie handling and per-form tracking reliability
* Better compatibility with legacy stored data (automatic fallback support)
* Performance optimizations for submission queries and lookups
* UI improvements and added contextual help links in settings
* Minor bug fixes and stability improvements

= 1.5.8 =
* Improved duplicate protection: unconfigured forms are now ignored and IP-only protection works correctly.

= 1.5.7 =
* Improved compatibility with Breakdance Page Builder forms
* Enhanced form detection logic for more accurate results across all Breakdance layouts

= 1.5.6 =
* Performance improvement: Added optimized database index for faster duplicate checks.
* Improved query efficiency for duplicate validation (reduced database load).
* Optimized duplicate detection logic for large datasets (50k+ submissions).
* Minor internal performance and stability improvements.

= 1.5.5 =
* Improved integration of the Diagnostics module within the admin interface.
* Replaced legacy support access with direct Diagnostics navigation.
* Minor UI refinements and overall stability improvements.

= 1.5.4 =
* Improved Elementor Forms detection to correctly identify real form instances (including Global Widgets and Popups).
* Fixed issue where duplicate submissions were not detected due to mismatched form IDs.

= 1.5.3 =
* New PRO feature: Cross-Form Duplicate Protection.
* Allows duplicate detection across multiple forms.
* Compatible with all supported form plugins.
* Internal improvements preparing cross-form validation engine.

= 1.5.2 =
* New: WooCommerce Classic Checkout duplicate order protection (FREE).
* Prevent accidental duplicate orders within a 60-second lock window.
* Structured duplicate logging added for seamless upgrade to PRO analytics.
* Compatibility notice for Checkout Blocks (PRO required).
* Internal improvements for WooCommerce validation flow.
* Minor admin UI refinements.

= 1.5.1 =
* Minor fix for Elementor “Show Forms” integration.
* Added new PRO feature: Enable Group Mode (Elementor).
* Group Mode allows treating Elementor forms with the same Form Name as a single form across multiple pages.
* Improved Elementor compatibility and stability.

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