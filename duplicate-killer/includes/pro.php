<?php
defined( 'ABSPATH' ) or die( 'You shall not pass!' );

function duplicateKiller_pro_plugin(){
?>
<div class="dk-pro-wrap">

	<div class="dk-pro-panel dk-pro-panel--soft">
		<div class="dk-pro-head">
			<div class="dk-pro-icon">🧩</div>
			<div>
				<h4 class="dk-pro-title">Tired of CAPTCHAs? Same.</h4>
				<p class="dk-pro-text">
					<?php echo esc_html__( 'CAPTCHAs slow down real people and still don’t stop all spam. Duplicate Killer keeps your forms clean by blocking duplicate submissions instead — quietly.', 'duplicatekiller' ); ?>
				</p>
				<p class="dk-pro-kicker">
					<?php echo esc_html__( 'No puzzles. No traffic lights. Just unique entries.', 'duplicatekiller' ); ?>
				</p>
			</div>
		</div>
	</div>

	<div class="dk-pro-panel">
		<div class="dk-pro-head">
			<div class="dk-pro-icon">⚖️</div>
			<div>
				<h4 class="dk-pro-title">FREE vs PRO — what actually changes</h4>
				<p class="dk-pro-text">
					<?php echo esc_html__( 'Both versions stop duplicate submissions. The difference is how much control you have.', 'duplicatekiller' ); ?>
				</p>
				<p class="dk-pro-kicker">
					<?php echo esc_html__( 'If your site has more than one form, global rules start to feel limiting.', 'duplicatekiller' ); ?>
				</p>
			</div>
		</div>

		<div class="dk-pro-grid">
			<div class="dk-pro-box dk-pro-box--free">
				<div class="dk-pro-badge">FREE</div>
				<ul class="dk-pro-list">
					<li><?php echo esc_html__( 'Block duplicate submissions (email, phone or text).', 'duplicatekiller' ); ?></li>
					<li><?php echo esc_html__( 'One global rule shared by all forms.', 'duplicatekiller' ); ?></li>
					<li><?php echo esc_html__( 'Best for simple sites with a single main form.', 'duplicatekiller' ); ?></li>
				</ul>
			</div>

			<div class="dk-pro-box dk-pro-box--pro">
				<div class="dk-pro-badge dk-pro-badge--pro">PRO</div>
				<ul class="dk-pro-list">
					<li><?php echo esc_html__( 'Different rules for different forms.', 'duplicatekiller' ); ?></li>
					<li><?php echo esc_html__( 'Custom error message for each form.', 'duplicatekiller' ); ?></li>
					<li><?php echo esc_html__( 'Clear explanations that match each form’s context.', 'duplicatekiller' ); ?></li>
				</ul>
			</div>
		</div>

		<p class="dk-pro-kicker">
			<?php echo esc_html__( 'Example: your newsletter can say “You’re already subscribed”, while your booking form can say “This email already has a reservation”.', 'duplicatekiller' ); ?>
		</p>
	</div>

	<div class="dk-pro-panel">
		<div class="dk-pro-head">
			<div class="dk-pro-icon">🛡️</div>
			<div>
				<h4 class="dk-pro-title">Why PRO feels better</h4>
				<p class="dk-pro-text">
					<?php echo esc_html__( 'PRO adds smarter rules like per-form logic, user-level checks and IP limits — so you get cleaner data without frustrating real visitors.', 'duplicatekiller' ); ?>
				</p>
			</div>
		</div>

		<div class="dk-pro-cta">
			<div class="dk-pro-mini">
				<?php echo esc_html__( 'Set it once. Let it run. Keep your forms clean.', 'duplicatekiller' ); ?>
				<?php printf(
					wp_kses_post( __( ' <a href="%s"><strong>Upgrade to Duplicate Killer PRO</strong></a>', 'duplicatekiller' ) ),
					esc_url( 'https://verselabwp.com/duplicate-killer/' )
				); ?>
			</div>
		</div>
	</div>

</div>
<?php
}
