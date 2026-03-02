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
				<div class="dk-pro-icon">🛒</div>
				<div>
					<h4 class="dk-pro-title"><?php echo esc_html__( 'WooCommerce — stop accidental duplicate orders', 'duplicate-killer' ); ?></h4>
					<p class="dk-pro-text">
						<?php echo esc_html__( 'WooCommerce already disables the “Place order” button, but duplicates still happen when checkout is slow, requests are retried, gateways lag, or customers submit from multiple tabs/devices. PRO adds server-side “idempotency” and analytics — so you can see what happens and why.', 'duplicate-killer' ); ?>
					</p>
					<p class="dk-pro-kicker">
						<?php echo esc_html__( 'Designed for real-world edge cases: slow hosting, flaky mobile networks, gateway delays, reverse proxies and retry behavior.', 'duplicate-killer' ); ?>
					</p>
				</div>
			</div>

			<div class="dk-pro-grid">
				<div class="dk-pro-box dk-pro-box--free">
					<div class="dk-pro-badge">FREE</div>
					<ul class="dk-pro-list">
						<li><?php echo esc_html__( 'Basic protection for Classic Checkout.', 'duplicate-killer' ); ?></li>
						<li><?php echo esc_html__( 'Fixed 60-second lock window.', 'duplicate-killer' ); ?></li>
						<li><?php echo esc_html__( 'Simple logging (summary only).', 'duplicate-killer' ); ?></li>
					</ul>
				</div>

				<div class="dk-pro-box dk-pro-box--pro">
					<div class="dk-pro-badge dk-pro-badge--pro">PRO</div>
					<ul class="dk-pro-list">
						<li><?php echo esc_html__( 'Checkout Blocks support (Store API).', 'duplicate-killer' ); ?></li>
						<li><?php echo esc_html__( 'Configurable lock window + advanced fingerprint controls.', 'duplicate-killer' ); ?></li>
						<li><?php echo esc_html__( 'Order linking + gateway breakdown + trends + export.', 'duplicate-killer' ); ?></li>
					</ul>
				</div>
			</div>

			<div class="dk-pro-panel dk-pro-panel--soft" style="margin-top:12px;">
				<div class="dk-pro-head">
					<div class="dk-pro-icon">📊</div>
					<div>
						<h4 class="dk-pro-title"><?php echo esc_html__( 'Example: WooCommerce Analytics', 'duplicate-killer' ); ?></h4>
						<p class="dk-pro-text">
							<?php echo esc_html__( 'A simple dashboard that helps store owners diagnose duplicates and measure impact.', 'duplicate-killer' ); ?>
						</p>
					</div>
				</div>

				<div class="dk-pro-grid">
					<div class="dk-pro-box">
						<div class="dk-pro-badge"><?php echo esc_html__( 'Last 7 days', 'duplicate-killer' ); ?></div>
						<ul class="dk-pro-list">
							<li><?php echo esc_html__( 'Blocked duplicate attempts: 34', 'duplicate-killer' ); ?></li>
							<li><?php echo esc_html__( 'Linked to real orders: 21', 'duplicate-killer' ); ?></li>
							<li><?php echo esc_html__( 'Estimated refunds avoided: 1,240 RON', 'duplicate-killer' ); ?></li>
						</ul>
					</div>

					<div class="dk-pro-box">
						<div class="dk-pro-badge"><?php echo esc_html__( 'Breakdown', 'duplicate-killer' ); ?></div>
						<ul class="dk-pro-list">
							<li><?php echo esc_html__( 'Stripe: 18', 'duplicate-killer' ); ?></li>
							<li><?php echo esc_html__( 'PayPal: 9', 'duplicate-killer' ); ?></li>
							<li><?php echo esc_html__( 'Cash on Delivery: 7', 'duplicate-killer' ); ?></li>
						</ul>
					</div>
				</div>

				<p class="dk-pro-kicker">
					<?php echo esc_html__( 'PRO can also show “Top repeating fingerprints”, peak hours, and direct links to the matching WooCommerce order — so support teams stop guessing.', 'duplicate-killer' ); ?>
				</p>
			</div>

			<p class="dk-pro-kicker">
				<?php echo esc_html__( 'Tip: this is especially useful on mobile traffic, high-latency checkouts, and stores using security proxies or aggressive caching layers.', 'duplicate-killer' ); ?>
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
