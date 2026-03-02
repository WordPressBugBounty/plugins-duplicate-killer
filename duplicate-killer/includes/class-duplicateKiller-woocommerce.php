<?php
defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce integration: Prevent accidental duplicate orders.
 * Fully isolated from the rest of Duplicate Killer form integrations.
 */
final class duplicateKiller_WooCommerce {

	const OPTION_KEY = 'duplicateKillerWooCommerceSettings';

	// FREE defaults (fixed)
	const LOCK_WINDOW_SECONDS = 60;

	/**
	 * Boot.
	 */
	public static function init(): void {
		// Settings + UI
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );

		// Runtime hooks only if Woo is active and protection enabled
		add_action( 'init', array( __CLASS__, 'maybe_hook_runtime' ), 20 );
	}

	/**
	 * Basic WooCommerce active check (safe, no fatal if Woo not installed).
	 */
	public static function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Register WP Settings API for the Woo tab.
	 */
	public static function register_settings(): void {
		// Register option with sanitization callback
		register_setting(
			'WooCommerce_page',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => array(),
			)
		);

		// Section (content rendered via callback)
		add_settings_section(
			'WooCommerce_page_section',
			'',
			array( __CLASS__, 'render_section' ),
			'WooCommerce_page'
		);

		// Single field: our whole UI block
		add_settings_field(
			'dk_wc_settings',
			'',
			array( __CLASS__, 'render_settings_field' ),
			'WooCommerce_page',
			'WooCommerce_page_section'
		);
	}

	/**
	 * Sanitize settings saved from admin.
	 */
	public static function sanitize_settings( $input ): array {
		$input = is_array( $input ) ? $input : array();

		$message = '';
		if ( isset( $input['message'] ) ) {
			$message = sanitize_text_field( (string) $input['message'] );
			$message = trim( $message );
		}

		return array(
			'enabled' => ! empty( $input['enabled'] ) ? 1 : 0,
			'message' => $message,
		);
	}
	
	/**
	 * Helper to read a single setting.
	 */
	public static function get_setting( string $key, $default = null ) {
		$opts = get_option( self::OPTION_KEY, array() );
		if ( is_array( $opts ) && array_key_exists( $key, $opts ) ) {
			return $opts[ $key ];
		}
		return $default;
	}

	/**
	 * Admin section description.
	 */
	public static function render_section(): void {

		if ( ! self::is_woocommerce_active() ) {

			echo '<h3 style="color:red"><strong>';
			echo esc_html__( 'WooCommerce plugin is not activated! Please activate it in order to continue.', 'duplicate-killer' );
			echo '</strong></h3>';

			return;
		}

		echo '<h3 style="color:green"><strong>';
		echo esc_html__( 'WooCommerce plugin is activated!', 'duplicate-killer' );
		echo '</strong></h3>';

		echo '<p style="margin-top:8px;">';
		echo esc_html__( 'Protect your checkout from accidental duplicate orders caused by slow checkout, repeated clicks, or retries.', 'duplicate-killer' );
		echo '</p>';
		
		self::render_checkout_compat_notice();
		
		$others = self::get_other_checkout_shortcode_pages();
		if ( ! empty( $others ) ) {
			echo '<div style="margin:12px 0 10px;padding:10px 12px;border-radius:6px;border:1px solid #dcdcde;background:#fffbf0;border-left:4px solid #dba617;">';
			echo '<p style="margin:0;"><strong>' . esc_html__( 'Multiple Checkout pages detected', 'duplicate-killer' ) . '</strong><br>';
			echo esc_html__( 'FREE is designed around the default WooCommerce Checkout page. If you use multiple checkout pages/funnels, PRO can protect selected pages or all checkout pages.', 'duplicate-killer' );
			echo '</p></div>';
		}
		$checkout_id = wc_get_page_id( 'checkout' );
	}
	public static function get_db_notice_summary(): array {
		return self::get_stats();
	}
	/**
	 * Render the Woo settings UI.
	 */
	public static function render_settings_field(): void {
		
		// Do not render settings UI if WooCommerce is not active
		if ( ! self::is_woocommerce_active() ) {
			return;
		}
		$enabled = (int) self::get_setting( 'enabled', 0 );

		echo '<div class="dk-settings-form-wrapper">';

		echo '<label style="display:flex;align-items:center;gap:8px;font-weight:600;">';
		echo '<input type="checkbox" name="' . esc_attr( self::OPTION_KEY ) . '[enabled]" value="1" ' . checked( 1, $enabled, false ) . ' />';
		echo esc_html__( 'Activate WooCommerce duplicate order protection', 'duplicate-killer' );
		echo '</label>';

		echo '<p style="margin-top:8px;opacity:0.85;">';
		echo esc_html__( 'FREE: Prevents identical checkout submissions within 60 seconds (Classic Checkout).', 'duplicate-killer' );
		echo '</p>';

		echo '<ul style="margin:8px 0 0 18px; list-style:disc; opacity:0.85;">';
		echo '<li>' . esc_html__( 'Fingerprint: Billing Email + Cart Items + Order Total + Currency', 'duplicate-killer' ) . '</li>';
		echo '<li>' . esc_html__( 'Lock window: 60 seconds (fixed)', 'duplicate-killer' ) . '</li>';
		echo '<li>' . esc_html__( 'Blocks only accidental duplicates (server-side).', 'duplicate-killer' ) . '</li>';
		echo '</ul>';
		$message = (string) self::get_setting( 'message', '' );
		if ( $message === '' ) {
			$message = __( 'It looks like your order was already submitted. Please wait a moment and check your email for confirmation.', 'duplicate-killer' );
		}

		echo '<div style="margin-top:10px;max-width:720px;">';
		echo '<label style="display:block;font-weight:600;margin-bottom:6px;">' . esc_html__( 'Duplicate message', 'duplicate-killer' ) . '</label>';
		echo '<input type="text" class="regular-text" style="width:100%;" name="' . esc_attr( self::OPTION_KEY ) . '[message]" value="' . esc_attr( $message ) . '" />';
		echo '<p class="description" style="margin-top:6px;">' . esc_html__( 'Shown on checkout when an accidental duplicate order is detected.', 'duplicate-killer' ) . '</p>';
		echo '</div>';
		// PRO teaser (doesn't require PRO code)
		echo '<div class="dk-locked" style="margin-top:14px;padding:12px 15px;background:#f8f9fa;border-left:4px solid #2271b1;">';
		echo '<strong>' . esc_html__( 'PRO unlocks:', 'duplicate-killer' ) . '</strong>';
		echo '<ul style="margin:8px 0 0 18px; list-style:disc;">';
		echo '<li>' . esc_html__( 'Custom lock window (30–300s)', 'duplicate-killer' ) . '</li>';
		echo '<li>' . esc_html__( 'Advanced fingerprint controls (phone, shipping, IP, customer ID)', 'duplicate-killer' ) . '</li>';
		echo '<li>' . esc_html__( 'Checkout Blocks support', 'duplicate-killer' ) . '</li>';
		echo '<li>' . esc_html__( 'Logs, analytics, and smart redirect to existing order', 'duplicate-killer' ) . '</li>';
		echo '</ul>';
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Hook runtime only if enabled.
	 */
	public static function maybe_hook_runtime(): void {
		if ( ! self::is_woocommerce_active() ) {
			return;
		}

		if ( 1 !== (int) self::get_setting( 'enabled', 0 ) ) {
			return;
		}

		// Classic checkout validation stage
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'check_duplicate_checkout' ), 10, 2 );
		// Save order_id + received URL into the same lock transient so duplicates can link to the real order.
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'duplicateKiller_wc_record_successful_order' ), 10, 3 );
	}
	/**
	 * Store the created order details in the lock transient so subsequent duplicates
	 * can show/link the real order (used by PRO analytics later).
	 *
	 * @param int   $order_id
	 * @param array $posted_data
	 * @param WC_Order $order
	 */
	public static function duplicateKiller_wc_record_successful_order( $order_id, $posted_data, $order ): void {

		$order_id = absint( $order_id );
		if ( $order_id <= 0 ) {
			return;
		}

		if ( ! is_array( $posted_data ) ) {
			$posted_data = array();
		}

		// Rebuild the same fingerprint used at validation time.
		$fingerprint = self::build_fingerprint( $posted_data );
		if ( '' === $fingerprint ) {
			return;
		}

		$key = 'duplicateKiller_wc_' . $fingerprint;

		$existing = get_transient( $key );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		// Get order received URL if possible.
		$received_url = '';
		if ( is_object( $order ) && method_exists( $order, 'get_checkout_order_received_url' ) ) {
			$received_url = (string) $order->get_checkout_order_received_url();
		}

		// Payment method id (e.g. "cod", "stripe", etc.)
		$payment_method = '';
		if ( is_object( $order ) && method_exists( $order, 'get_payment_method' ) ) {
			$payment_method = (string) $order->get_payment_method();
		}

		$existing['order_id']        = $order_id;
		$existing['received']        = $received_url;
		$existing['payment_method']  = sanitize_key( $payment_method );
		$existing['customer_id']     = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		$existing['mode']            = self::get_checkout_mode();
		$existing['ip']              = duplicateKiller_get_user_ip();

		// Keep same expiration window (do NOT extend it unexpectedly).
		$window = (int) apply_filters( 'duplicateKiller_wc_lock_window_seconds', self::LOCK_WINDOW_SECONDS, $posted_data );
		set_transient( $key, $existing, max( 10, $window ) );
	}
	/**
	 * Main FREE checker: block accidental duplicate orders.
	 *
	 * @param array    $data   Posted checkout data (sanitized by Woo).
	 * @param WP_Error $errors Validation error object.
	 */
	public static function check_duplicate_checkout( $data, $errors ): void {
		// Safety: only if cart exists
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		$fingerprint = self::build_fingerprint( $data );

		if ( '' === $fingerprint ) {
			return;
		}
		//error_log('DK WC check_duplicate_checkout fired');
		/**
		 * Filter for PRO/extensions: allow overriding lock window.
		 * FREE uses 60 seconds but PRO can change it via this filter.
		 */
		$window = (int) apply_filters( 'duplicateKiller_wc_lock_window_seconds', self::LOCK_WINDOW_SECONDS, $data );

		$key = 'duplicateKiller_wc_' . $fingerprint;

		$existing = get_transient( $key );
		if ( ! empty( $existing ) ) {

			// Log duplicate attempt into wp_dk_forms_duplicate (minimal payload)
			self::log_duplicate_event( $fingerprint, $data );
			self::increment_blocked_counter();
			$custom_message = (string) self::get_setting( 'message', '' );
			if ( $custom_message === '' ) {
				$custom_message = __( 'It looks like your order was already submitted. Please wait a moment and check your email for confirmation.', 'duplicate-killer' );
			}

			wc_add_notice(
				apply_filters( 'duplicateKiller_wc_duplicate_message', $custom_message, $data ),
				'error'
			);

			return;
		}

		// Acquire lock
		set_transient(
			$key,
			array(
				'created_at' => time(),
				'order_id'   => 0,
				'received'   => '',
			),
			max( 10, $window )
		);
	}
	private static function increment_blocked_counter(): void {
		$key = 'duplicateKiller_duplicates_blocked_count';

		$current = (int) get_option( $key, 0 );
		$current++;

		update_option( $key, $current, false );
	}
	/**
	 * Build a stable fingerprint for the current checkout attempt.
	 */
	private static function build_fingerprint( array $data ): string {
		$email = '';
		if ( isset( $data['billing_email'] ) ) {
			$email = sanitize_email( (string) $data['billing_email'] );
			$email = strtolower( trim( $email ) );
		}

		if ( '' === $email ) {
			return '';
		}

		$currency = function_exists( 'get_woocommerce_currency' ) ? (string) get_woocommerce_currency() : '';
		$currency = sanitize_text_field( $currency );

		// Cart items hash: product_id:variation_id:qty
		$items = array();
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product_id   = isset( $cart_item['product_id'] ) ? (int) $cart_item['product_id'] : 0;
			$variation_id = isset( $cart_item['variation_id'] ) ? (int) $cart_item['variation_id'] : 0;
			$qty          = isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 0;

			if ( $product_id > 0 && $qty > 0 ) {
				$items[] = $product_id . ':' . $variation_id . ':' . $qty;
			}
		}
		sort( $items );
		
		if ( empty( $items ) ) {
			return '';
		}
		$items_hash = hash( 'sha256', implode( '|', $items ) );
		
		// Total (string normalized)
		$total = '';
		if ( WC()->cart ) {
			// get_total returns formatted HTML sometimes; use numeric total
			$total = (string) WC()->cart->get_total( 'edit' );
		}
		$total = preg_replace( '/\s+/', '', (string) $total );
		$total = sanitize_text_field( $total );

		$raw = $email . '|' . $items_hash . '|' . $total . '|' . $currency;

		// Final key hash (short)
		return substr( hash( 'sha256', $raw ), 0, 32 );
	}
	private static function log_duplicate_event( string $fingerprint, array $data ): void {
		global $wpdb;

		// Insert only on duplicates -> low volume, performance-safe.
		$table = $wpdb->prefix . 'dk_forms_duplicate';

		$ip = duplicateKiller_get_user_ip();

		$email = '';
		if ( isset( $data['billing_email'] ) ) {
			$email = strtolower( trim( sanitize_email( (string) $data['billing_email'] ) ) );
		}

		$email_masked = '';
		if ( isset( $data['billing_email'] ) ) {
			$email = strtolower( trim( sanitize_email( (string) $data['billing_email'] ) ) );

			if ( $email ) {
				$parts = explode( '@', $email );
				if ( count( $parts ) === 2 ) {
					$name   = $parts[0];
					$domain = $parts[1];
					$email_masked = substr( $name, 0, 1 ) . '***@' . $domain;
				}
			}
		}

		$total = '';
		if ( function_exists('WC') && WC()->cart ) {
			$total = WC()->cart->get_total( 'edit' );
		}

		$product_ids = array();
		if ( function_exists('WC') && WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				if ( ! empty( $cart_item['product_id'] ) ) {
					$product_ids[] = (int) $cart_item['product_id'];
				}
			}
		}
		// Read lock transient details (might include linked order info from successful checkout).
		$lock_key  = 'duplicateKiller_wc_' . $fingerprint;
		$existing  = get_transient( $lock_key );
		$existing  = is_array( $existing ) ? $existing : array();

		$order_id        = isset( $existing['order_id'] ) ? absint( $existing['order_id'] ) : 0;
		$order_received  = isset( $existing['received'] ) ? (string) $existing['received'] : '';
		$mode            = isset( $existing['mode'] ) ? sanitize_key( (string) $existing['mode'] ) : self::get_checkout_mode();
		$customer_id     = isset( $existing['customer_id'] ) ? absint( $existing['customer_id'] ) : ( function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0 );
		$payment_method  = isset( $existing['payment_method'] ) ? sanitize_key( (string) $existing['payment_method'] ) : '';

		if ( '' === $payment_method && isset( $data['payment_method'] ) ) {
			$payment_method = sanitize_key( (string) $data['payment_method'] );
		}
		$payload = array(
			'type'               => 'wc_checkout_duplicate',
			'fingerprint'         => $fingerprint,
			'order_id'            => $order_id,
			'email'               => $email_masked,
			'total'               => $total,
			'products'            => $product_ids,
			'currency'            => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
			'timestamp'           => time(),
			'mode'                => $mode,
			'customer_id'         => $customer_id,
			'payment_method'      => $payment_method,
			'order_received_url'  => $order_received,
			'ip'                  => $ip !== '' ? $ip : '',
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Logging into plugin-owned custom table.
		$wpdb->insert(
			$table,
			array(
				'form_plugin' => 'WooCommerce',
				'form_name'   => 'Checkout',
				'form_value'  => maybe_serialize( $payload ),
				'form_cookie' => 'NULL',
				'form_ip'     => $ip !== '' ? $ip : 'NULL',
				'form_date'   => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		// Invalidate cached stats if you add caching.
		delete_transient( 'duplicateKiller_wc_stats' );
	}
	private static function get_stats(): array {
		$cached = get_transient( 'duplicateKiller_wc_stats' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'dk_forms_duplicate' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Read from plugin-owned table.
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM {$table} WHERE form_plugin = %s AND form_name = %s",
				'WooCommerce',
				'Checkout'
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Read from plugin-owned table.
		$last_date = (string) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT MAX(form_date) FROM {$table} WHERE form_plugin = %s AND form_name = %s",
				'WooCommerce',
				'Checkout'
			)
		);

		$stats = array(
			'count'     => $count,
			'last_date' => $last_date,
		);

		set_transient( 'duplicateKiller_wc_stats', $stats, 60 );

		return $stats;
	}
	private static function get_checkout_mode(): string {

		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return 'unknown';
		}

		$checkout_id = (int) wc_get_page_id( 'checkout' );
		if ( $checkout_id <= 0 ) {
			return 'missing';
		}

		$post = get_post( $checkout_id );
		if ( ! $post || empty( $post->post_content ) ) {
			return 'unknown';
		}

		$content = (string) $post->post_content;

		// Classic shortcode
		if ( has_shortcode( $content, 'woocommerce_checkout' ) ) {
			return 'shortcode';
		}

		// Blocks (common markers)
		if ( strpos( $content, 'wp:woocommerce/checkout' ) !== false || strpos( $content, 'woocommerce/checkout' ) !== false ) {
			return 'blocks';
		}

		// Some themes build custom checkout pages; if it doesn't contain the shortcode, FREE can't guarantee.
		return 'custom';
	}

	private static function render_checkout_compat_notice(): void {

		$mode = self::get_checkout_mode();

		// Keep it visually similar to WP admin notices, but simple.
		$box_style = 'margin:12px 0 10px;padding:10px 12px;border-radius:6px;border:1px solid #dcdcde;background:#fff;';

		if ( 'shortcode' === $mode ) {
			echo '<div style="' . esc_attr( $box_style ) . 'border-left:4px solid #00a32a;">';
			echo '<p style="margin:0;"><strong>' . esc_html__( 'Compatibility: OK', 'duplicate-killer' ) . '</strong> — ';
			echo esc_html__( 'Your Checkout page uses the classic shortcode, so FREE protection works.', 'duplicate-killer' );
			echo '</p></div>';
			return;
		}

		if ( 'blocks' === $mode ) {

			$article_url = 'https://verselabwp.com/woocommerce-checkout-blocks-vs-classic-checkout-why-duplicate-killer-free-works-only-with-shortcodes/';

			echo '<div style="margin:12px 0 10px;padding:12px 14px;border-radius:6px;border:1px solid #dcdcde;background:#fff8f8;border-left:4px solid #d63638;">';

			echo '<p style="margin:0 0 6px 0;"><strong>';
			echo esc_html__( 'WooCommerce Checkout Blocks detected', 'duplicate-killer' );
			echo '</strong></p>';

			echo '<p style="margin:0 0 8px 0;line-height:1.5;">';
			echo esc_html__( 'Duplicate Killer FREE works only with the Classic Checkout shortcode. Checkout Blocks use a different system and require PRO support.', 'duplicate-killer' );
			echo '</p>';

			echo '<p style="margin:0;">';
			echo '<a href="' . esc_url( $article_url ) . '" target="_blank" style="font-weight:600;text-decoration:none;">';
			echo esc_html__( 'Learn why FREE does not support Checkout Blocks →', 'duplicate-killer' );
			echo '</a>';
			echo '</p>';

			echo '</div>';

			return;
		}

		if ( 'missing' === $mode ) {
			echo '<div style="' . esc_attr( $box_style ) . 'border-left:4px solid #d63638;background:#fff8f8;">';
			echo '<p style="margin:0;"><strong>' . esc_html__( 'Checkout page not configured', 'duplicate-killer' ) . '</strong><br>';
			echo esc_html__( 'WooCommerce Checkout page is missing. Configure it in WooCommerce settings to use this feature.', 'duplicate-killer' );
			echo '</p></div>';
			return;
		}

		// custom / unknown
		echo '<div style="' . esc_attr( $box_style ) . 'border-left:4px solid #dba617;background:#fffbf0;">';
		echo '<p style="margin:0;"><strong>' . esc_html__( 'Compatibility: uncertain', 'duplicate-killer' ) . '</strong><br>';
		echo esc_html__( 'FREE is designed for Classic Checkout shortcode. If your checkout is custom or block-based, results may vary. PRO supports Blocks.', 'duplicate-killer' );
		echo '</p></div>';
	}
	private static function get_other_checkout_shortcode_pages(): array {

		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return array();
		}

		$default_id = (int) wc_get_page_id( 'checkout' );
		if ( $default_id <= 0 ) {
			return array();
		}

		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'numberposts'    => 50, // safety limit
				's'              => 'woocommerce_checkout', // cheap filter
				'fields'         => 'ids',
			)
		);

		if ( empty( $pages ) || ! is_array( $pages ) ) {
			return array();
		}

		$others = array();

		foreach ( $pages as $pid ) {
			$pid = (int) $pid;
			if ( $pid <= 0 || $pid === $default_id ) {
				continue;
			}

			$content = (string) get_post_field( 'post_content', $pid );
			if ( $content !== '' && has_shortcode( $content, 'woocommerce_checkout' ) ) {
				$others[] = $pid;
			}
		}

		return array_values( array_unique( $others ) );
	}
}