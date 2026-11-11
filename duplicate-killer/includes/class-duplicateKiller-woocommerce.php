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
		$dk_wc_view         = 'header';
		$woocommerce_active = self::is_woocommerce_active();
		$enabled            = $woocommerce_active && 1 === (int) self::get_setting( 'enabled', 0 );
		$others             = $woocommerce_active ? self::get_other_checkout_shortcode_pages() : array();

		require DUPLICATEKILLER_PLUGIN_DIR . '/admin/database/views/woocommerce-settings.php';
	}
	public static function get_db_notice_summary(): array {
		return self::get_stats();
	}
	/**
	 * Render the Woo settings UI.
	 */
	public static function render_settings_field(): void {
		if ( ! self::is_woocommerce_active() ) {
			return;
		}

		$dk_wc_view = 'fields';
		$enabled    = (int) self::get_setting( 'enabled', 0 );
		$message    = (string) self::get_setting( 'message', '' );

		if ( '' === $message ) {
			$message = __( 'It looks like your order was already submitted. Please wait a moment and check your email for confirmation.', 'duplicate-killer' );
		}

		$required_fields = array(
			__( 'Billing email - required', 'duplicate-killer' ),
			__( 'Cart items, variations and quantities - required', 'duplicate-killer' ),
			__( 'Currency - required', 'duplicate-killer' ),
		);

		require DUPLICATEKILLER_PLUGIN_DIR . '/admin/database/views/woocommerce-settings.php';
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

		// Classic checkout validation stage.
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'check_duplicate_checkout' ), 10, 2 );

		// Blocks checkout uses the Store API.
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( __CLASS__, 'check_duplicate_checkout_blocks' ), 10, 2 );

		// Save order_id + received URL into the same lock transient so duplicates can link to the real order.
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'duplicateKiller_wc_record_successful_order' ), 10, 3 );
	}
	
	/**
	 * Check Store API place-order requests using the PRO Blocks flow.
	 */
	public static function check_duplicate_checkout_blocks( $order, $request ): void {
		if (
			! $request instanceof \WP_REST_Request
			|| 'POST' !== $request->get_method()
			|| ! $order instanceof \WC_Order
			|| $order->get_id() <= 0
			|| ! class_exists( '\Automattic\WooCommerce\StoreApi\Exceptions\RouteException' )
		) {
			return;
		}

		self::duplicateKiller_debug_log(
			'blocks_check_start',
			array(
				'request_debug_id' => uniqid( 'duplicateKiller_wc_blocks_', true ),
				'order_id'         => $order->get_id(),
			)
		);

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		$data = self::extract_store_api_checkout_data( $request );
		$pm   = self::extract_store_api_payment_method( $request );

		if ( empty( $data['billing_email'] ) ) {
			$data['billing_email'] = (string) $order->get_billing_email();
		}

		if ( '' !== $pm ) {
			$data['payment_method'] = $pm;
		}

		if ( ! is_email( $data['billing_email'] ?? '' ) ) {
			return;
		}

		$fingerprint = self::build_fingerprint( $data );

		if ( '' === $fingerprint ) {
			return;
		}

		// FREE retains its fixed default and existing developer filter.
		$window = (int) apply_filters(
			'duplicateKiller_wc_lock_window_seconds',
			self::LOCK_WINDOW_SECONDS,
			$data
		);
		$window   = max( 10, $window );
		$key      = 'duplicateKiller_wc_' . $fingerprint;
		$existing = get_transient( $key );

		self::duplicateKiller_debug_log(
			'blocks_transient_loaded',
			array(
				'transient_key' => $key,
				'lock_exists'   => ! empty( $existing ),
				'lock_window'   => $window,
			)
		);

		if ( ! empty( $existing ) ) {
			self::log_duplicate_event( $fingerprint, $data );
			self::increment_blocked_counter();

			$custom_message = (string) self::get_setting( 'message', '' );

			if ( '' === $custom_message ) {
				$custom_message = __( 'It looks like your order was already submitted. Please wait a moment and check your email for confirmation.', 'duplicate-killer' );
			}

			$custom_message = apply_filters(
				'duplicateKiller_wc_duplicate_message',
				$custom_message,
				$data
			);

			self::duplicateKiller_debug_log(
				'blocks_duplicate_throw_route_exception',
				array( 'fingerprint' => $fingerprint )
			);

			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
				'duplicate_killer_duplicate_order',
				esc_html( wp_strip_all_tags( (string) $custom_message ) ),
				409
			);
		}

		set_transient(
			$key,
			array(
				'created_at' => time(),
				'order_id'   => 0,
				'received'   => '',
			),
			$window
		);

		// Preserve the existing FREE order-linking method and public signature.
		// A known order is not proof that payment has succeeded.
		self::duplicateKiller_wc_record_successful_order(
			$order->get_id(),
			$data,
			$order
		);

		self::duplicateKiller_debug_log(
			'blocks_lock_created',
			array(
				'transient_key' => $key,
				'fingerprint'   => $fingerprint,
				'lock_window'   => $window,
			)
		);
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

		$dk_enabled       = class_exists('duplicateKiller_Diagnostics');
		$request_debug_id = uniqid('dk_wc_record_order_', true);

		$order_id = absint( $order_id );
		if ( $order_id <= 0 ) {
			return;
		}

		if ( ! is_array( $posted_data ) ) {
			$posted_data = array();
		}

		if ( $dk_enabled ) {
			duplicateKiller_Diagnostics::log('woocommerce', 'record_order_start', array(
				'request_debug_id' => $request_debug_id,
				'order_id'         => $order_id,
				'posted_data'      => $posted_data,
			));
		}

		// Rebuild the same fingerprint used at validation time.
		$fingerprint = self::build_fingerprint( $posted_data );
		if ( '' === $fingerprint ) {
			if ( $dk_enabled ) {
				duplicateKiller_Diagnostics::log('woocommerce', 'record_order_no_fingerprint', array(
					'request_debug_id' => $request_debug_id,
					'order_id'         => $order_id,
				));
			}
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
		$existing['ip']              = self::get_user_ip();

		// Keep same expiration window (do NOT extend it unexpectedly).
		$window = (int) apply_filters( 'duplicateKiller_wc_lock_window_seconds', self::LOCK_WINDOW_SECONDS, $posted_data );
		set_transient( $key, $existing, max( 10, $window ) );

		if ( $dk_enabled ) {
			duplicateKiller_Diagnostics::log('woocommerce', 'record_order_saved_to_lock', array(
				'request_debug_id' => $request_debug_id,
				'order_id'         => $order_id,
				'fingerprint'      => $fingerprint,
				'transient_key'    => $key,
				'received_url'     => $received_url,
				'payment_method'   => $payment_method,
				'window'           => max( 10, $window ),
				'existing'         => $existing,
			));
		}
	}
	/**
	 * Main FREE checker: block accidental duplicate orders.
	 *
	 * @param array    $data   Posted checkout data (sanitized by Woo).
	 * @param WP_Error $errors Validation error object.
	 */
	public static function check_duplicate_checkout( $data, $errors ): void {
		$dk_enabled       = class_exists('duplicateKiller_Diagnostics');
		$request_debug_id = uniqid('dk_wc_validate_', true);

		// Safety: only if cart exists
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		if ( $dk_enabled ) {
			duplicateKiller_Diagnostics::log('woocommerce', 'validate_start', array(
				'request_debug_id' => $request_debug_id,
				'data'             => is_array( $data ) ? $data : array(),
			));
		}

		$fingerprint = self::build_fingerprint( $data );

		if ( $dk_enabled ) {
			duplicateKiller_Diagnostics::log('woocommerce', 'fingerprint_built', array(
				'request_debug_id' => $request_debug_id,
				'fingerprint'      => $fingerprint,
			));
		}

		if ( '' === $fingerprint ) {
			if ( $dk_enabled ) {
				duplicateKiller_Diagnostics::log('woocommerce', 'validate_no_fingerprint', array(
					'request_debug_id' => $request_debug_id,
				));
			}
			return;
		}

		$window = (int) apply_filters( 'duplicateKiller_wc_lock_window_seconds', self::LOCK_WINDOW_SECONDS, $data );
		$key    = 'duplicateKiller_wc_' . $fingerprint;

		$existing = get_transient( $key );

		if ( $dk_enabled ) {
			duplicateKiller_Diagnostics::log('woocommerce', 'lock_checked', array(
				'request_debug_id' => $request_debug_id,
				'transient_key'    => $key,
				'lock_exists'      => ! empty( $existing ) ? 1 : 0,
				'existing_lock'    => is_array( $existing ) ? $existing : $existing,
				'window'           => max( 10, $window ),
			));
		}

		if ( ! empty( $existing ) ) {

			// Log duplicate attempt into wp_dk_forms_duplicate (minimal payload)
			self::log_duplicate_event( $fingerprint, $data );
			self::increment_blocked_counter();

			$custom_message = (string) self::get_setting( 'message', '' );
			if ( $custom_message === '' ) {
				$custom_message = __( 'It looks like your order was already submitted. Please wait a moment and check your email for confirmation.', 'duplicate-killer' );
			}

			if ( $dk_enabled ) {
				duplicateKiller_Diagnostics::log('woocommerce', 'duplicate_blocked', array(
					'request_debug_id' => $request_debug_id,
					'fingerprint'      => $fingerprint,
					'message'          => $custom_message,
				));
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

		if ( $dk_enabled ) {
			duplicateKiller_Diagnostics::log('woocommerce', 'lock_created', array(
				'request_debug_id' => $request_debug_id,
				'fingerprint'      => $fingerprint,
				'transient_key'    => $key,
				'window'           => max( 10, $window ),
			));
		}
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
		$email = isset( $data['billing_email'] ) && is_scalar( $data['billing_email'] )
			? strtolower( trim( sanitize_email( (string) $data['billing_email'] ) ) )
			: '';

		if ( '' === $email || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return '';
		}

		$currency = function_exists( 'get_woocommerce_currency' )
			? sanitize_text_field( (string) get_woocommerce_currency() )
			: '';

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
		$raw        = $email . '|' . $items_hash . '|' . $currency;

		return substr( hash( 'sha256', $raw ), 0, 32 );
	}
	private static function log_duplicate_event( string $fingerprint, array $data ): void {
		global $wpdb;

		$dk_enabled       = class_exists('duplicateKiller_Diagnostics');
		$request_debug_id = uniqid('dk_wc_log_duplicate_', true);

		// Insert only on duplicates -> low volume, performance-safe.
		$table = $wpdb->prefix . 'dk_forms_duplicate';

		$ip = self::get_user_ip();

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
			'fingerprint'        => $fingerprint,
			'order_id'           => $order_id,
			'email'              => $email_masked,
			'total'              => $total,
			'products'           => $product_ids,
			'currency'           => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
			'timestamp'          => time(),
			'mode'               => $mode,
			'customer_id'        => $customer_id,
			'payment_method'     => $payment_method,
			'order_received_url' => $order_received,
			'ip'                 => $ip !== '' ? $ip : '',
		);

		if ( $dk_enabled ) {
			duplicateKiller_Diagnostics::log('woocommerce', 'duplicate_log_prepare', array(
				'request_debug_id' => $request_debug_id,
				'fingerprint'      => $fingerprint,
				'payload'          => $payload,
				'lock_key'         => $lock_key,
				'existing_lock'    => $existing,
			));
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Logging into plugin-owned custom table.
		$insert_result = $wpdb->insert(
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

		if ( $dk_enabled ) {
			duplicateKiller_Diagnostics::log('woocommerce', 'duplicate_log_saved', array(
				'request_debug_id' => $request_debug_id,
				'insert_ok'        => empty( $wpdb->last_error ) && false !== $insert_result ? 1 : 0,
				'wpdb_last_error'  => $wpdb->last_error,
				'insert_id'        => $wpdb->insert_id,
				'table'            => $table,
			));
		}

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

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Required WooCommerce checkout stats query on plugin-owned table.
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM {$table} WHERE form_plugin = %s AND form_name = %s",
				'WooCommerce',
				'Checkout'
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Required WooCommerce checkout stats query on plugin-owned table.
		$last_date = (string) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT MAX(form_date) FROM {$table} WHERE form_plugin = %s AND form_name = %s",
				'WooCommerce',
				'Checkout'
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

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
		$dk_wc_view = 'checkout';
		$mode       = self::get_checkout_mode();

		// No documentation link is configured for the FREE view.
		$article_url = '';

		switch ( $mode ) {
			case 'shortcode':
				$checkout_description = __( 'Your Checkout page uses Classic Checkout. FREE supports this checkout type.', 'duplicate-killer' );
				break;

			case 'blocks':
				$checkout_description = __( 'Your Checkout page uses Checkout Blocks. FREE supports this checkout type.', 'duplicate-killer' );
				break;

			case 'missing':
				$checkout_description = __( 'WooCommerce Checkout page is missing. Configure it in WooCommerce settings to use this feature.', 'duplicate-killer' );
				break;

			default:
				$checkout_description = __( 'FREE supports WooCommerce Classic Checkout and Checkout Blocks. Test custom checkout pages before going live.', 'duplicate-killer' );
		}

		require DUPLICATEKILLER_PLUGIN_DIR . '/admin/database/views/woocommerce-settings.php';
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
		/**
	 * Read the native Store API payment method without inspecting payment data.
	 */
	private static function extract_store_api_payment_method( $request ): string {
		$value = $request->get_param( 'payment_method' );

		return is_string( $value ) ? sanitize_key( $value ) : '';
	}

	/**
	 * Map native Store API addresses to the same keys used by PRO.
	 */
	private static function extract_store_api_checkout_data( $request ): array {
		$billing = $request->get_param( 'billing_address' );
		$billing = is_array( $billing ) ? $billing : array();
		$email   = $billing['email'] ?? $request->get_param( 'billing_email' );

		return is_string( $email )
			? array( 'billing_email' => sanitize_email( $email ) )
			: array();
	}

	private static function duplicateKiller_debug_log( string $stage, array $payload = array() ): void {
		if ( class_exists( 'duplicateKiller_Diagnostics' ) ) {
			duplicateKiller_Diagnostics::log( 'woocommerce', $stage, $payload );
		}
	}
	private static function get_user_ip(): string {

		if ( class_exists( 'DuplicateKiller_IP_Limit_Checker' ) ) {
			return DuplicateKiller_IP_Limit_Checker::get_user_ip();
		}

		if ( function_exists( 'duplicateKiller_get_user_ip' ) ) {
			return (string) duplicateKiller_get_user_ip();
		}

		return 'undefined';
	}
	public static function get_wc_analytics_summary(): array {
		return array(
			'total'    => 12,
			'last_24h' => 2,
			'last_7d'  => 8,
		);
	}

	public static function get_wc_analytics_top( int $limit = 5 ): array {
		return array(
			'top_products' => array(
				0 => 5,
				1 => 3,
			),
			'top_domains' => array(
				'gmail.com'   => 6,
				'yahoo.com'   => 3,
				'example.com' => 2,
			),
			'top_payments' => array(
				'cod'    => 5,
				'stripe' => 4,
				'paypal' => 2,
			),
			'top_modes' => array(
				'shortcode' => 7,
				'blocks'    => 4,
			),
			'top_ips' => array(
				'192.168.1.10' => 4,
				'192.168.1.22' => 3,
			),
			'top_emails' => array(),
			'unique_fingerprints' => 9,
			'orders_created'      => 7,
			'scanned_rows'        => 12,
		);
	}

	public static function get_wc_analytics_trends( int $days = 14 ): array {
		$days  = max( 2, min( 60, $days ) );
		$trend = array();

		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$date = date_i18n( 'Y-m-d', strtotime( '-' . $i . ' days' ) );
			$trend[ $date ] = (int) max( 0, ( $days - $i ) % 5 );
		}

		return $trend;
	}
}