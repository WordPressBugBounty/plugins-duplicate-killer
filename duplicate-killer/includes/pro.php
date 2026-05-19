<?php
defined( 'ABSPATH' ) or die( 'You shall not pass!' );

function duplicateKiller_pro_plugin() {
	$upgrade_url = 'https://verselabwp.com/duplicate-killer/';
	?>
	<div class="dk-pro-wrap">

		<section class="dk-pro-hero">
			<div class="dk-pro-hero__content">
				<div class="dk-pro-brand">
					<span class="dk-pro-brand__icon" aria-hidden="true">
						<img
							src="<?php echo esc_url( DUPLICATEKILLER_PLUGIN_URL . 'assets/icon-256x256.png' ); ?>"
							alt=""
						/>
					</span>
					<span class="dk-pro-brand__name">Duplicate Killer PRO</span>
				</div>

				<h2 class="dk-pro-hero__title">
					<?php echo esc_html__( 'Stop duplicate submissions without CAPTCHAs.', 'duplicate-killer' ); ?>
				</h2>

				<p class="dk-pro-hero__text">
					<?php echo esc_html__( 'Protect forms, WooCommerce checkouts, and multi-form funnels — without annoying real visitors.', 'duplicate-killer' ); ?>
				</p>

				<div class="dk-pro-hero__actions">
					<a class="dk-pro-button dk-pro-button--primary" href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noopener noreferrer">
						<?php echo esc_html__( 'Upgrade to PRO', 'duplicate-killer' ); ?>
						<span aria-hidden="true">→</span>
					</a>

					<a class="dk-pro-button dk-pro-button--secondary" href="#dk-pro-features">
						<?php echo esc_html__( 'View features', 'duplicate-killer' ); ?>
					</a>
				</div>

				<div class="dk-pro-trust">
					<span><?php echo esc_html__( 'No puzzles', 'duplicate-killer' ); ?></span>
					<span><?php echo esc_html__( 'No traffic lights', 'duplicate-killer' ); ?></span>
					<span><?php echo esc_html__( 'Just unique entries', 'duplicate-killer' ); ?></span>
				</div>
			</div>

			<div class="dk-pro-hero__mockup" aria-hidden="true">
				<div class="dk-pro-mock-card">
					<div class="dk-pro-mock-card__head">
						<strong>Protected Forms</strong>
						
					</div>

					<div class="dk-pro-mock-row">
						<span>Contact Form</span>
						<em class="dk-status-pill dk-status-pill-active">
							Active
						</em>
					</div>
					<div class="dk-pro-mock-row">
						<span>Newsletter Form</span>
						<em class="dk-status-pill dk-status-pill-active">
							Active
						</em>
					</div>
					<div class="dk-pro-mock-row">
						<span>Booking Form</span>
						<em class="dk-status-pill dk-status-pill-active">
							Active
						</em>
					</div>
					<div class="dk-pro-mock-row">
						<span>Woo Checkout</span>
						<em class="dk-status-pill dk-status-pill-active">
							Active
						</em>
					</div>

					<div class="dk-pro-mock-stats">
						<div>
							<span>34</span>
							<small>Blocked attempts</small>
						</div>
						<div>
							<span>12</span>
							<small>Forms protected</small>
						</div>
					</div>
				</div>
			</div>
		</section>

		<div id="dk-pro-features" class="dk-pro-section-title">
			<h3><?php echo esc_html__( 'FREE vs PRO — what actually changes', 'duplicate-killer' ); ?></h3>
			<p><?php echo esc_html__( 'Both versions stop duplicate submissions. PRO gives you multi-form control, better messages, WooCommerce protection, and cleaner data.', 'duplicate-killer' ); ?></p>
		</div>

		<section class="dk-pro-compare">
			<div class="dk-pro-plan dk-pro-plan--free">
				<div class="dk-pro-plan__head">
					<span class="dk-pro-plan__icon">Free</span>
					<h4>FREE</h4>
				</div>

				<ul class="dk-pro-checklist dk-pro-checklist--muted">
					<li><?php echo esc_html__( 'Protect 1 form from duplicate submissions.', 'duplicate-killer' ); ?></li>
					<li><?php echo esc_html__( 'One global rule shared by protected entries.', 'duplicate-killer' ); ?></li>
					<li><?php echo esc_html__( 'Best for simple sites with a single main form.', 'duplicate-killer' ); ?></li>
					<li><?php echo esc_html__( 'Basic protection for Classic Checkout.', 'duplicate-killer' ); ?></li>
					<li><?php echo esc_html__( 'Fixed 60-second WooCommerce lock window.', 'duplicate-killer' ); ?></li>
				</ul>
			</div>

			<div class="dk-pro-plan dk-pro-plan--pro">
				<div class="dk-pro-plan__label"><?php echo esc_html__( 'Recommended', 'duplicate-killer' ); ?></div>

				<div class="dk-pro-plan__head">
					<span class="dk-pro-plan__icon">Pro</span>
					<h4>PRO</h4>
				</div>

				<ul class="dk-pro-checklist">
					<li><?php echo esc_html__( 'Protect all forms with individual rules per form.', 'duplicate-killer' ); ?></li>
					<li><?php echo esc_html__( 'Custom error message for each form.', 'duplicate-killer' ); ?></li>
					<li><?php echo esc_html__( 'Optional per-form limits for cleaner data.', 'duplicate-killer' ); ?></li>
					<li><?php echo esc_html__( 'Cross-Form Duplicate Protection.', 'duplicate-killer' ); ?></li>
					<li><?php echo esc_html__( 'WooCommerce Checkout Blocks support and analytics.', 'duplicate-killer' ); ?></li>
				</ul>
			</div>
		</section>

		<section class="dk-pro-panel dk-pro-panel--split">
			<div>
				<div class="dk-pro-panel__eyebrow">Cross-Form Protection</div>
				<h3><?php echo esc_html__( 'Stop users from bypassing protection by switching forms.', 'duplicate-killer' ); ?></h3>
				<p>
					<?php echo esc_html__( 'Prevent the same user from submitting the same data across multiple forms on your website. Useful for newsletters, lead forms, bookings, and multi-step funnels.', 'duplicate-killer' ); ?>
				</p>

				<ul class="dk-pro-checklist">
					<li><?php echo esc_html__( 'Detect duplicates across multiple forms.', 'duplicate-killer' ); ?></li>
					<li><?php echo esc_html__( 'Works with email, phone, number, and text fields.', 'duplicate-killer' ); ?></li>
					<li><?php echo esc_html__( 'Block repeated entries before your CRM gets polluted.', 'duplicate-killer' ); ?></li>
				</ul>
			</div>

			<div class="dk-pro-flow" aria-hidden="true">
				<div class="dk-pro-flow__card dk-pro-flow__card--ok">
					<strong>Newsletter Form</strong>
					<span>user@email.com</span>
					<em>Submitted</em>
				</div>

				<div class="dk-pro-flow__arrow">→</div>

				<div class="dk-pro-flow__card dk-pro-flow__card--blocked">
					<strong>Booking Form</strong>
					<span>user@email.com</span>
					<em>Duplicate blocked</em>
				</div>
			</div>
		</section>

		<section class="dk-pro-panel dk-pro-panel--split dk-pro-panel--woo">
			<div>
				<div class="dk-pro-panel__meta">
					<div class="dk-pro-panel__brand">
						<img
							src="<?php echo esc_url( plugins_url( 'assets/Woocommerce.png', DUPLICATEKILLER_PLUGIN ) ); ?>"
							alt="WooCommerce"
						/>
					</div>

					<div class="dk-pro-panel__eyebrow">
						<?php echo esc_html__( 'WooCommerce Protection', 'duplicate-killer' ); ?>
					</div>

				</div>
				<h3><?php echo esc_html__( 'Stop accidental duplicate orders before they become refunds.', 'duplicate-killer' ); ?></h3>
				<p>
					<?php echo esc_html__( 'WooCommerce already disables the “Place order” button, but duplicates still happen when checkout is slow, gateways lag, or customers submit from multiple tabs/devices.', 'duplicate-killer' ); ?>
				</p>

				<ul class="dk-pro-checklist">
					<li><?php echo esc_html__( 'Checkout Blocks support through Store API.', 'duplicate-killer' ); ?></li>
					<li><?php echo esc_html__( 'Configurable lock window and fingerprint controls.', 'duplicate-killer' ); ?></li>
					<li><?php echo esc_html__( 'Order linking, gateway breakdown, trends, and export.', 'duplicate-killer' ); ?></li>
				</ul>
			</div>

			<div class="dk-pro-analytics" aria-hidden="true">
				<div class="dk-pro-analytics__head">
					<strong>Last 7 days</strong>
					<span>WooCommerce Analytics</span>
				</div>

				<div class="dk-pro-analytics__stats">
					<div>
						<strong>34</strong>
						<span>Blocked attempts</span>
					</div>
					<div>
						<strong>21</strong>
						<span>Linked orders</span>
					</div>
					<div>
						<strong>1,240 USD</strong>
						<span>Refunds avoided</span>
					</div>
				</div>

				<div class="dk-pro-bars">
					<div class="dk-pro-bar">
						<span>Stripe</span>
						<i style="width:72%;"></i>
						<em>18</em>
					</div>
					<div class="dk-pro-bar">
						<span>PayPal</span>
						<i style="width:44%;"></i>
						<em>9</em>
					</div>
					<div class="dk-pro-bar">
						<span>Cash on Delivery</span>
						<i style="width:32%;"></i>
						<em>7</em>
					</div>
				</div>
			</div>
		</section>

		<section class="dk-pro-benefits">
			<div class="dk-pro-benefit">
				<span>🧩</span>
				<strong><?php echo esc_html__( 'Per-form rules', 'duplicate-killer' ); ?></strong>
				<p><?php echo esc_html__( 'Each form can have its own protection logic and error message.', 'duplicate-killer' ); ?></p>
			</div>

			<div class="dk-pro-benefit">
				<span>🔗</span>
				<strong><?php echo esc_html__( 'Cross-form detection', 'duplicate-killer' ); ?></strong>
				<p><?php echo esc_html__( 'Stop duplicate entries across multiple forms and funnels.', 'duplicate-killer' ); ?></p>
			</div>

			<div class="dk-pro-benefit">
				<span>🛒</span>
				<strong><?php echo esc_html__( 'WooCommerce safety', 'duplicate-killer' ); ?></strong>
				<p><?php echo esc_html__( 'Reduce accidental duplicate orders caused by retries and slow checkout.', 'duplicate-killer' ); ?></p>
			</div>

			<div class="dk-pro-benefit">
				<span>📊</span>
				<strong><?php echo esc_html__( 'Cleaner analytics', 'duplicate-killer' ); ?></strong>
				<p><?php echo esc_html__( 'See what was blocked, why it happened, and where it came from.', 'duplicate-killer' ); ?></p>
			</div>
		</section>

		<section class="dk-pro-final-cta">
			<div>
				<h3><?php echo esc_html__( 'Ready for cleaner forms and fewer duplicate orders?', 'duplicate-killer' ); ?></h3>
				<p><?php echo esc_html__( 'Upgrade to Duplicate Killer PRO and protect every form with more control.', 'duplicate-killer' ); ?></p>
			</div>

			<a class="dk-pro-button dk-pro-button--light" href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php echo esc_html__( 'Upgrade to Duplicate Killer PRO', 'duplicate-killer' ); ?>
				<span aria-hidden="true">→</span>
			</a>
		</section>

	</div>
	<?php
}