<?php
defined( 'ABSPATH' ) or die( 'You shall not pass!' );

function duplicateKiller_pro_plugin() {
	?>
	<div class="dk-pro-wrap">

		<div class="dk-pro-panel dk-pro-panel--soft">
			<div class="dk-pro-head">
				<div class="dk-pro-icon">🧩</div>
				<div>
					<h4 class="dk-pro-title">Tired of CAPTCHAs? Same.</h4>
					<p class="dk-pro-text">
						<?php echo esc_html__( 'CAPTCHAs slow down real people and still don’t stop all spam. Duplicate Killer keeps your forms clean by blocking duplicate submissions instead — quietly.', 'duplicate-killer' ); ?>
					</p>
					<p class="dk-pro-kicker">
						<?php echo esc_html__( 'No puzzles. No traffic lights. Just unique entries.', 'duplicate-killer' ); ?>
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
						<?php echo esc_html__( 'Both versions stop duplicate submissions. The difference is how many forms you can protect and how much control you have.', 'duplicate-killer' ); ?>
					</p>
					<p class="dk-pro-kicker">
						<?php echo esc_html__( 'If your site has more than one form, global rules quickly feel limiting.', 'duplicate-killer' ); ?>
					</p>
				</div>
			</div>

			<div class="dk-pro-grid">
				<div class="dk-pro-box dk-pro-box--free">
					<div class="dk-pro-badge">FREE</div>
					<ul class="dk-pro-list">
						<li><?php echo esc_html__( 'Protect 1 form from duplicate submissions (email, phone or text).', 'duplicate-killer' ); ?></li>
						<li><?php echo esc_html__( 'One global rule shared by protected entries.', 'duplicate-killer' ); ?></li>
						<li><?php echo esc_html__( 'Best for simple sites with a single main form.', 'duplicate-killer' ); ?></li>
					</ul>
				</div>

				<div class="dk-pro-box dk-pro-box--pro">
					<div class="dk-pro-badge dk-pro-badge--pro">PRO</div>
					<ul class="dk-pro-list">
						<li><?php echo esc_html__( 'Protect all forms with individual rules per form.', 'duplicate-killer' ); ?></li>
						<li><?php echo esc_html__( 'Custom error message for each form.', 'duplicate-killer' ); ?></li>
						<li><?php echo esc_html__( 'Optional per-form limits (IP, user-level checks) for cleaner data.', 'duplicate-killer' ); ?></li>
					</ul>
				</div>
			</div>

			<p class="dk-pro-kicker">
				<?php echo esc_html__( 'Example: your newsletter can say “You’re already subscribed”, while your booking form can say “This email already has a reservation”.', 'duplicate-killer' ); ?>
			</p>
		</div>

		<div class="dk-pro-panel">
			<div class="dk-pro-head">
				<div class="dk-pro-icon">🛡️</div>
				<div>
					<h4 class="dk-pro-title">Why PRO feels better</h4>
					<p class="dk-pro-text">
						<?php echo esc_html__( 'PRO adds multi-form protection and per-form controls — so you get cleaner data without frustrating real visitors.', 'duplicate-killer' ); ?>
					</p>
				</div>
			</div>

			<div class="dk-pro-cta">
				<div class="dk-pro-mini">
					<?php
					echo wp_kses_post(
						sprintf(
							'<a href="%s"><strong>%s</strong></a>',
							esc_url( 'https://verselabwp.com/duplicate-killer/' ),
							esc_html__( 'Upgrade to Duplicate Killer PRO', 'duplicate-killer' )
						)
					);
					?>
				</div>
			</div>
		</div>

	</div>
	<?php
}
