<?php
defined( 'ABSPATH' ) || exit;

if ( 'header' === $dk_wc_view ) :
	?>
	<header class="dk-wc-header">
		<span class="dk-wc-brand" aria-hidden="true">Woo</span>

		<div class="dk-wc-header-copy">
			<h2 class="dk-wc-title">
				<?php
				echo esc_html(
					$woocommerce_active
						? __( 'WooCommerce plugin is activated!', 'duplicate-killer' )
						: __( 'WooCommerce plugin is not activated!', 'duplicate-killer' )
				);
				?>
			</h2>

			<p class="dk-wc-description">
				<?php
				echo esc_html(
					$woocommerce_active
						? __( 'Protect your checkout from accidental duplicate orders caused by slow checkout, repeated clicks, or retries.', 'duplicate-killer' )
						: __( 'Please activate WooCommerce to configure duplicate order protection.', 'duplicate-killer' )
				);
				?>
			</p>
		</div>

		<span class="dk-wc-status <?php echo esc_attr( $enabled ? 'is-active' : 'is-inactive' ); ?>">
			<span class="dk-wc-status-dot" aria-hidden="true"></span>
			<?php
			echo esc_html(
				$enabled
					? __( 'Protection active', 'duplicate-killer' )
					: __( 'Protection inactive', 'duplicate-killer' )
			);
			?>
		</span>
	</header>

	<?php
	if ( $woocommerce_active ) :
		self::render_checkout_compat_notice();

		if ( ! empty( $others ) ) :
			?>
			<div class="dk-wc-warning">
				<strong><?php esc_html_e( 'Multiple Checkout pages detected', 'duplicate-killer' ); ?></strong>
				<p><?php esc_html_e( 'FREE is designed around the default WooCommerce Checkout page. If you use multiple checkout pages/funnels, PRO can protect selected pages or all checkout pages.', 'duplicate-killer' ); ?></p>
			</div>
			<?php
		endif;
	endif;

elseif ( 'checkout' === $dk_wc_view ) :
	?>
	<section class="dk-wc-detection">
		<span class="dk-wc-icon" aria-hidden="true">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false">
				<path d="M3 3h2l3 12h11l2-8H6"/>
				<path d="M8 15l-1 3h12"/>
				<circle cx="9" cy="21" r="1"/>
				<circle cx="18" cy="21" r="1"/>
			</svg>
		</span>

		<div class="dk-wc-detection-copy">
			<h3 class="dk-wc-heading">
				<?php esc_html_e( 'Checkout type detected', 'duplicate-killer' ); ?>
			</h3>

			<p class="dk-wc-description">
				<?php echo esc_html( $checkout_description ); ?>
			</p>

			<?php if ( '' !== $article_url ) : ?>
				<a class="dk-wc-link" href="<?php echo esc_url( $article_url ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Read the full explanation', 'duplicate-killer' ); ?>
					<span aria-hidden="true">&rarr;</span>
				</a>
			<?php endif; ?>
		</div>
	</section>

<?php elseif ( 'fields' === $dk_wc_view ) : ?>
	<div class="dk-wc-fields">
		<p class="dk-wc-summary">
			<?php esc_html_e( 'This feature blocks accidental duplicate checkout submissions (slow checkout, repeated clicks, gateway retries). It does not block intentional repeat purchases.', 'duplicate-killer' ); ?>
		</p>

		<label class="dk-wc-enable" for="dk-wc-enabled">
			<input
				id="dk-wc-enabled"
				type="checkbox"
				name="<?php echo esc_attr( self::OPTION_KEY . '[enabled]' ); ?>"
				value="1"
				<?php checked( 1, $enabled ); ?>
			/>
			<span><?php esc_html_e( 'Enable duplicate order protection', 'duplicate-killer' ); ?></span>
		</label>

		<div class="dk-wc-grid">
			<div class="dk-wc-field">
				<label class="dk-wc-label" for="dk-wc-lock-window">
					<?php esc_html_e( 'Lock window (seconds)', 'duplicate-killer' ); ?>
				</label>

				<!-- Display only. FREE does not save a configurable lock window. -->
				<input
					id="dk-wc-lock-window"
					class="small-text"
					type="number"
					value="<?php echo esc_attr( (string) self::LOCK_WINDOW_SECONDS ); ?>"
					disabled="disabled"
					aria-describedby="dk-wc-lock-help"
				/>

				<p id="dk-wc-lock-help" class="dk-wc-description">
					<?php esc_html_e( 'FREE uses a fixed 60-second window. Custom lock windows are available in PRO.', 'duplicate-killer' ); ?>
				</p>
			</div>

			<div class="dk-wc-field">
				<label class="dk-wc-label" for="dk-wc-message">
					<?php esc_html_e( 'Duplicate message', 'duplicate-killer' ); ?>
				</label>

				<input
					id="dk-wc-message"
					class="regular-text"
					type="text"
					name="<?php echo esc_attr( self::OPTION_KEY . '[message]' ); ?>"
					value="<?php echo esc_attr( $message ); ?>"
					aria-describedby="dk-wc-message-help"
				/>

				<p id="dk-wc-message-help" class="dk-wc-description">
					<?php esc_html_e( 'Shown on checkout when a duplicate attempt is detected.', 'duplicate-killer' ); ?>
				</p>
			</div>
		</div>

		<fieldset class="dk-wc-fingerprint">
			<legend class="screen-reader-text">
				<?php esc_html_e( 'Fingerprint controls', 'duplicate-killer' ); ?>
			</legend>

			<div class="dk-wc-fingerprint-header">
				<span class="dk-wc-fingerprint-icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" focusable="false">
						<path d="M4 11a8 8 0 0 1 16 0"/>
						<path d="M2.5 8a10 10 0 0 1 19 0"/>
						<path d="M7 13v-2a5 5 0 0 1 10 0v3c0 3-1 5-2 7"/>
						<path d="M10 21c3-3 4-6 4-10a2 2 0 0 0-4 0v3c0 3-1 5-3 7"/>
						<path d="M4 14c0 3-.5 4-1 5"/>
						<path d="M7 16c-.3 2-1 3-2 4"/>
						<path d="M20 14c0 3-.5 5-1.5 7"/>
						<path d="M12 11v3"/>
					</svg>
				</span>

				<div>
					<h3 class="dk-wc-heading">
						<?php esc_html_e( 'Fingerprint controls', 'duplicate-killer' ); ?>
					</h3>

					<p class="dk-wc-description">
						<?php esc_html_e( 'These fields are always used to identify identical checkout submissions.', 'duplicate-killer' ); ?>
					</p>
				</div>
			</div>

			<div class="dk-wc-checkboxes">
				<?php foreach ( $required_fields as $label ) : ?>
					<label class="dk-wc-check">
						<input type="checkbox" checked="checked" disabled="disabled" />
						<span><?php echo esc_html( $label ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</fieldset>

		<section class="dk-wc-fingerprint">
			<h3 class="dk-wc-heading">
				<?php esc_html_e( 'PRO unlocks:', 'duplicate-killer' ); ?>
			</h3>

			<p class="dk-wc-description">
				<?php esc_html_e( 'Custom lock window (30-300 seconds).', 'duplicate-killer' ); ?>
			</p>
			<p class="dk-wc-description">
				<?php esc_html_e( 'Advanced fingerprint controls (phone, shipping, IP, customer ID).', 'duplicate-killer' ); ?>
			</p>
			<p class="dk-wc-description">
				<?php esc_html_e( 'Logs, analytics, and smart redirect to existing order.', 'duplicate-killer' ); ?>
			</p>
		</section>
	</div>
<?php endif; ?>