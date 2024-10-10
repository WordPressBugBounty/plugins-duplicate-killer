<?php
defined( 'ABSPATH' ) or die( 'You shall not pass!' );

function duplicateKiller_about_plugin(){
?>
	<div class="dk-single-form">
	<h4 class="dk-form-header">About plugin</h4>
	<p><?php printf( __( 'This plugin was developed by <a href="%s"><strong>NIA</strong></a>.', 'duplicatekiller' ), 'https://profiles.wordpress.org/wpnia/' );?>
	It is licensed as Free Software under GNU General Public License 2 (GPL 2).</p>
	<p><?php printf( __( 'To support this plugin and future improvements you can <a href="%s"><strong>leave a review</strong></a> or <a href="%s"><strong>give a donation</strong></a>. You can <a href="%s"><strong>buy me a coffee</strong></a> if the plugin was useful.', 'd' ), 'https://wordpress.org/plugins/duplicate-killer/', 'https://www.paypal.com/paypalme/wpnia','https://www.buymeacoffee.com/nicaialexav' ); ?></p>
	<p>
	<?php printf( __( 'If you need support with this plugin you can <a href="%s"><strong>contact me</strong></a> on the forum.', 'd' ), 'https://wordpress.org/support/plugin/duplicate-killer/'); ?>
	</div>
	<div class="dk-single-form">
	<h4 class="dk-form-header">Why should I use this plugin?</h4>
	<p>Duplicate Killer will <u>prevent double entry submissions</u> for Contact Form 7, Forminator and WPForms Lite plugins.</p>
	<p>This plugin can be used to ensure that a designated field has unique data entered into it, the best example of its use is to limit one submission per Email address.</p>
	<p>Choose the unique fields of your forms from Email, Phone, TextField.</p>
	</div>
	

	<div class="dk-single-form">
	<h4 class="dk-form-header">How to use Duplicate Killer?</h4>
	<p>1. Create your form with Contact Form 7, Forminator or WPForms plugins.</p>
	<p><?php printf( __( '2. Find the <a href="%s"><strong>Duplicate Killer</strong></a> and click on the tab which suits you (Contact Form 7, Forminator or WPForms tab', 'd' ), 'admin.php?page=duplicateKiller' );?>
	</p>
	<p>3. Choose your unique fields based on your form configuration (Name, Phone, Email and TextField are supported.</p>
	<p>4. Set your custom error message for users when value has been already submitted.</p>
	</div>
	
	<div class="dk-single-form">
	<h4 class="dk-form-header">Unique entries per user</h4>
	<p>This feature use cookies and prevent duplicates at the user level, not global.
	<p>Multiple users <strong>can submit the same entry</strong>, but a single user cannot submit values they have already submitted before.</p>
	</div>
	<div class="dk-single-form">
	<h4 class="dk-form-header">Roadmap</h4>
	<p>Improvements:
	<ul>
		<li>1. More contact forms plugins supported! <u>Coming soon...</u></li>
		<li>2. Dedicated error message for every single form.</li>
		<li>3. Further customization and functionality integration options</li>
	</ul>
	</p>
	<p>Design:
	<ul>
		<li>1. UI adjustments to make the plugin easier to use.</li>
		<li>2. New style navigation for better UX</li>
	</ul>
	</p>
<?php
}