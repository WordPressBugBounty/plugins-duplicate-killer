<?php
defined( 'ABSPATH' ) or die( 'You shall not pass!' );

define( 'DK_NINJA_FORMS', 'NinjaForms' );
define( 'DK_NINJA_FORMS_LABEL', 'Ninja Forms' );

function duplicateKiller_shortcode_duplicatekiller_count($atts) {
    $atts = shortcode_atts([
        'plugin' => '',
        'form'   => '',
        'prefix' => '',
        'suffix'  => '',
		'amount'  => 0,
    ], $atts, 'duplicatekiller');

    $plugin = sanitize_text_field($atts['plugin']);
    $form   = sanitize_text_field($atts['form']);
    $prefix = sanitize_text_field($atts['prefix']);
    $suffix  = sanitize_text_field($atts['suffix']);
	$amount  = intval($atts['amount']);

    if (empty($plugin) || empty($form)) {
        return '<span style="color:red;">Invalid shortcode attributes.</span>';
    }

    global $wpdb;
    $table = $wpdb->prefix . 'dk_forms_duplicate';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Querying Formidable custom table (admin-only, no WP API).
	if ( $wpdb->get_var(
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Querying Formidable custom table (admin-only, no WP API).
		$wpdb->prepare(
			"SHOW TABLES LIKE %s",
			$table
		)
	) !== $table ) {
		return '<span style="color:orange;">No data available.</span>';
	}

    // Generate a unique transient key based on plugin & form
    $transient_key = 'dk_count_' . md5($plugin . '|' . $form);

    // Try to get cached count
    $count = get_transient($transient_key);

	if ( $count === false ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Querying Formidable custom table (admin-only, no WP API).
		$count = $wpdb->get_var(
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Querying Formidable custom table (admin-only, no WP API).
			$wpdb->prepare(
				"SELECT COUNT(*) FROM " . esc_sql( $table ) . " WHERE form_plugin = %s AND form_name = %s",
				$plugin,
				$form
			)
		);

		// Cache the result for 30 seconds
		set_transient( $transient_key, $count, 30 );
	}

    return '<span class="dk-submission-count">' . esc_html($prefix . ' ' . intval($count) + intval($amount) . ' ' . $suffix) . '</span>';
}
add_shortcode('duplicateKiller', 'duplicateKiller_shortcode_duplicatekiller_count');
function duplicateKiller_convert_option_architecture( $data, $prefix ) {

	$prefix = is_string( $prefix ) ? trim( $prefix ) : '';

	if ( '' === $prefix ) {
		return array();
	}

	// Accept serialized input.
	if ( is_string( $data ) ) {
		$data = maybe_unserialize( $data );
	}

	if ( ! is_array( $data ) ) {
		return array();
	}

	$result       = array();
	$global_map   = array();
	$form_entries = array();

	// Map FREE global keys to PRO keys.
	$global_key_map = array(
		$prefix . 'cookie_option'          => 'cookie_option',
		$prefix . 'error_message_limit_ip' => 'error_message_limit_ip_option',
		$prefix . 'error_message'          => 'error_message',
		$prefix . 'user_ip'                => 'user_ip',
		$prefix . 'user_ip_days'           => 'user_ip_days',
		$prefix . 'cookie_option_days'     => 'cookie_option_days',
	);

	// Separate globals vs forms.
	foreach ( $data as $key => $value ) {

		if ( ! is_string( $key ) ) {
			continue;
		}

		// Special case: CF7 save_image stays top-level only.
		if ( 'cf7_' === $prefix && 'cf7_save_image' === $key ) {
			$result[ $key ] = (string) $value;
			continue;
		}

		// Global setting.
		if ( array_key_exists( $key, $global_key_map ) ) {
			$mapped = $global_key_map[ $key ];
			$global_map[ $mapped ] = (string) $value;
			continue;
		}

		// Form candidate.
		if ( is_array( $value ) ) {
			$form_entries[ $key ] = $value;
		}
	}

	// Ensure CF7 save_image exists and is normalized.
	if ( 'cf7_' === $prefix ) {
		if ( ! array_key_exists( 'cf7_save_image', $result ) ) {
			$result['cf7_save_image'] = '1';
		} else {
			$result['cf7_save_image'] = ( '0' === (string) $result['cf7_save_image'] ) ? '0' : '1';
		}
	}

	// Safe defaults for PRO architecture.
	$defaults = array(
		'form_id'                       => '',
		'error_message'                 => '',
		'error_message_limit_ip_option' => '',
		'user_ip_days'                  => '7',
		'cookie_option_days'            => '1',
		'cookie_option'                 => '0',
		'user_ip'                       => '0',
		'cross_form_option'             => '0',
	);

	$base_form = array_merge( $defaults, $global_map );

	$form_ids_map = array();

	if ( 'cf7_' === $prefix && function_exists( 'duplicateKiller_get_cf7_forms_info' ) ) {
		$form_ids_map = duplicateKiller_get_cf7_forms_info();
	} elseif ( 'forminator_' === $prefix && function_exists( 'duplicateKiller_forminator_get_forms_ids' ) ) {
		$form_ids_map = duplicateKiller_forminator_get_forms_ids();
	} elseif ( 'wpforms_' === $prefix && function_exists( 'duplicateKiller_wpforms_get_forms_ids' ) ) {
		$form_ids_map = duplicateKiller_wpforms_get_forms_ids();
	} elseif ( 'breakdance_' === $prefix && function_exists( 'duplicateKiller_breakdance_get_forms' ) ) {
		$form_ids_map = duplicateKiller_breakdance_get_forms();
	} elseif ( 'elementor_' === $prefix && function_exists( 'duplicateKiller_elementor_get_form_map' ) ) {
		$form_ids_map = duplicateKiller_elementor_get_form_map();
	} elseif ( 'formidable_' === $prefix && function_exists( 'duplicateKiller_formidable_get_forms' ) ) {
		$form_ids_map = duplicateKiller_formidable_get_forms();
	} elseif ( 'ninjaforms_' === $prefix && function_exists( 'duplicateKiller_ninjaforms_get_forms' ) ) {
		$form_ids_map = duplicateKiller_ninjaforms_get_forms();
	}

	foreach ( $form_entries as $form_name => $form_config ) {

		if ( ! is_string( $form_name ) || ! is_array( $form_config ) ) {
			continue;
		}

		// Detect existing PRO structure.
		$is_pro = (
			isset( $form_config['error_message'] ) ||
			isset( $form_config['cookie_option'] ) ||
			isset( $form_config['user_ip'] ) ||
			isset( $form_config['cross_form_option'] )
		);

		$normalized = $is_pro ? $form_config : $base_form;

		foreach ( $form_config as $k => $v ) {

			// Preserve numeric field IDs (Formidable / Ninja Forms).
			if ( is_int( $k ) || ctype_digit( (string) $k ) ) {
				if ( is_scalar( $v ) ) {
					$normalized[ $k ] = (string) $v;
				}
				continue;
			}

			if ( ! is_string( $k ) ) {
				continue;
			}

			// Preserve labels map for Formidable / Ninja Forms.
			if ( 'labels' === $k && is_array( $v ) ) {
				$normalized[ $k ] = $v;
				continue;
			}

			// Keep PRO mapping keys untouched.
			if ( '_ck' === substr( $k, -3 ) ) {
				$normalized[ $k ] = $v;
				continue;
			}

			// Normalize scalar values safely.
			if ( is_scalar( $v ) ) {
				$normalized[ $k ] = (string) $v;
			}
		}

		$form_id_candidate = '';

		if ( empty( $normalized['form_id'] ) ) {

			if ( isset( $form_ids_map[ $form_name ] ) ) {
				if ( is_array( $form_ids_map[ $form_name ] ) && ! empty( $form_ids_map[ $form_name ]['form_id'] ) ) {
					$form_id_candidate = $form_ids_map[ $form_name ]['form_id'];
				} elseif ( ! is_array( $form_ids_map[ $form_name ] ) ) {
					$form_id_candidate = $form_ids_map[ $form_name ];
				}
			} elseif ( 'breakdance_' === $prefix ) {
				foreach ( $form_ids_map as $bundle ) {
					if (
						is_array( $bundle ) &&
						isset( $bundle['form_name'], $bundle['form_id'] ) &&
						(string) $bundle['form_name'] === $form_name &&
						! empty( $bundle['form_id'] )
					) {
						$form_id_candidate = $bundle['form_id'];
						break;
					}
				}
			} elseif ( 'elementor_' === $prefix ) {
				$last_dot_pos = strrpos( $form_name, '.' );

				if ( false !== $last_dot_pos ) {
					$elementor_base_name = substr( $form_name, 0, $last_dot_pos );

					if (
						'' !== $elementor_base_name &&
						isset( $form_ids_map[ $elementor_base_name ] ) &&
						! empty( $form_ids_map[ $elementor_base_name ] )
					) {
						$form_id_candidate = $form_ids_map[ $elementor_base_name ];
					}
				}
			}

			if ( '' !== $form_id_candidate ) {
				if ( in_array( $prefix, array( 'elementor_', 'formidable_', 'ninjaforms_' ), true ) ) {
					$normalized['form_id'] = (string) $form_id_candidate;
				} else {
					$normalized['form_id'] = (string) absint( $form_id_candidate );
				}
			}
		}

		$result[ $form_name ] = $normalized;
	}

	return $result;
}

/**
 * Return duplicate-related config state for a form.
 *
 * @param array  $settings
 * @param string $form_name
 * @param string $ip_key
 * @return array
 */
function duplicateKiller_get_form_state( $settings, $form_name, $ip_key ) {
	$state = array(
		'form_exists'         => false,
		'has_duplicate_field' => false,
		'ip_enabled'          => false,
	);

	if ( ! is_array( $settings ) ) {
		return $state;
	}

	if ( empty( $form_name ) ) {
		return $state;
	}

	$state['ip_enabled'] = ! empty( $settings[ $ip_key ] ) && '1' === (string) $settings[ $ip_key ];

	if ( ! isset( $settings[ $form_name ] ) || ! is_array( $settings[ $form_name ] ) ) {
		return $state;
	}

	$state['form_exists'] = true;

	foreach ( $settings[ $form_name ] as $key => $value ) {
		if ( 'labels' === (string) $key ) {
			continue;
		}

		if ( '1' === (string) $value ) {
			$state['has_duplicate_field'] = true;
			break;
		}
	}

	return $state;
}
/**
 * Review milestone thresholds.
 *
 * @return int[]
 */
function duplicateKiller_review_milestones(): array {
	return array( 10, 50, 100, 1000, 3000, 5000, 10000 );
}

/**
 * Get the highest milestone reached but not dismissed.
 *
 * @param int $count
 * @return int|null
 */
function duplicateKiller_get_active_milestone( int $count ): ?int {

	$dismissed = get_option( 'duplicateKiller_review_milestones_dismissed', array() );
	if ( ! is_array( $dismissed ) ) {
		$dismissed = array();
	}

	$active = null;

	foreach ( duplicateKiller_review_milestones() as $m ) {
		if ( $count >= $m && empty( $dismissed[ (string) $m ] ) ) {
			$active = (int) $m;
		}
	}

	return $active;
}

/**
 * Handle dismiss action for a milestone notice.
 */
function duplicateKiller_handle_dismiss_milestone_notice(): void {
	if ( ! is_admin() ) {
		return;
	}

	if ( empty( $_GET['duplicateKiller_dismiss_milestone'] ) ) {
		return;
	}

	$milestone = (int) $_GET['duplicateKiller_dismiss_milestone'];
	if ( $milestone <= 0 ) {
		return;
	}

	check_admin_referer( 'duplicateKiller_dismiss_milestone_' . $milestone );

	$dismissed = get_option( 'duplicateKiller_review_milestones_dismissed', array() );
	if ( ! is_array( $dismissed ) ) {
		$dismissed = array();
	}

	$dismissed[ (string) $milestone ] = time();
	update_option( 'duplicateKiller_review_milestones_dismissed', $dismissed, false );

	// Redirect to remove query args.
	wp_safe_redirect( remove_query_arg( array( 'duplicateKiller_dismiss_milestone', '_wpnonce' ) ) );
	exit;
}

/**
 * Admin notice: show at milestones to encourage reviews.
 */
function duplicateKiller_admin_review_milestone_notice(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$count     = duplicateKiller_get_duplicates_blocked_count();
	$milestone = duplicateKiller_get_active_milestone( $count );

	if ( ! $milestone ) {
		return;
	}

	$dismiss_url = wp_nonce_url(
		add_query_arg( array( 'duplicateKiller_dismiss_milestone' => $milestone ) ),
		'duplicateKiller_dismiss_milestone_' . $milestone
	);

	// Replace with your real review URL(s).
	// WordPress.org reviews page is best for free plugin traction.
	$review_url_wporg   = 'https://wordpress.org/support/plugin/duplicate-killer/reviews/#new-post';
	$review_url_trustpilot = 'https://www.trustpilot.com/review/verselabwp.com';

	$message = sprintf(
		/* translators: 1: blocked count */
		__( '🎉 Duplicate Killer has blocked %1$s duplicate submissions on your site.', 'duplicate-killer' ),
		number_format_i18n( $count )
	);

	$sub_message = __( 'If it’s doing its job well, help others discover it with a quick review.', 'duplicate-killer' );

	echo '<div class="notice notice-success is-dismissible">';
	echo '<p><strong>' . esc_html( $message ) . '</strong></p>';
	echo '<p>' . esc_html( $sub_message ) . '</p>';
	echo '<p>';
	echo '<a class="button button-primary" href="' . esc_url( $review_url_wporg ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Leave a WordPress.org review', 'duplicate-killer' ) . '</a> ';
	echo '<a class="button" href="' . esc_url( $review_url_trustpilot ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Leave a Trustpilot review', 'duplicate-killer' ) . '</a> ';
	echo '<a class="button-link" href="' . esc_url( $dismiss_url ) . '">' . esc_html__( 'Dismiss', 'duplicate-killer' ) . '</a>';
	echo '</p>';
	echo '</div>';
}

/**
 * Atomically increments the "duplicates blocked" counter.
 * Uses a direct DB UPDATE to avoid lost increments under concurrent requests.
 *
 * @return int New total after increment.
 */
function duplicateKiller_increment_duplicates_blocked_count(): int {
	global $wpdb;

	$option_name = 'duplicateKiller_duplicates_blocked_count';

	// Try atomic UPDATE first (avoids race conditions).
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Plugin-owned option update; atomic increment.
	$updated = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$wpdb->options}
			 SET option_value = CAST(option_value AS UNSIGNED) + 1
			 WHERE option_name = %s",
			$option_name
		)
	);

	if ( $updated === 0 ) {
		// Option doesn't exist yet -> add it.
		// autoload = no (keeps wp_options autoload lean).
		add_option( $option_name, '1', '', 'no' );
		return 1;
	}

	// Read back the new value (cheap + correct).
	$new_value = (int) get_option( $option_name, 0 );
	// Optional: keep object cache coherent if any.
	wp_cache_delete( $option_name, 'options' );

	return $new_value;
}

/**
 * Returns total duplicates blocked (site-wide).
 *
 * @return int
 */
function duplicateKiller_get_duplicates_blocked_count(): int {
	return (int) get_option( 'duplicateKiller_duplicates_blocked_count', 0 );
}

/**
 * Verify admin capability + DK delete nonce. Dies on failure.
 *
 * @param string $action Nonce action string.
 * @param string $field  Nonce field name in POST.
 */
function duplicateKiller_verify_delete_nonce_or_die( $action = 'dk_delete_logs', $field = 'dk_nonce' ) {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You cannot do that!', 'duplicate-killer' ) );
	}

	if ( empty( $_POST[ $field ] ) ) {
		wp_die( esc_html__( 'Missing security nonce.', 'duplicate-killer' ) );
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );

	if ( ! wp_verify_nonce( $nonce, $action ) ) {
		wp_die( esc_html__( 'You cannot do that!', 'duplicate-killer' ) );
	}
}
/**
 * Process delete records requests for supported integrations.
 */
function duplicateKiller_handle_delete_records_request() {

	if ( empty( $_POST ) ) {
		return;
	}

	// PHPCS: verify nonce *before* reading any delete-related POST fields.
	if ( empty( $_POST['dk_nonce'] ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['dk_nonce'] ) );
	if ( ! wp_verify_nonce( $nonce, 'dk_delete_logs' ) ) {
		return;
	}

	// Capability check.
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$map = array(
		'CF7_delete_records'        => 'CF7',
		'Forminator_delete_records' => 'Forminator',
		'WPForms_delete_records'    => 'WPForms',
		'Breakdance_delete_records' => 'breakdance',
		'Elementor_delete_records'  => 'Elementor',
		'Formidable_delete_records' => 'Formidable',
		'NinjaForms_delete_records' => defined( 'DK_NINJA_FORMS' ) ? DK_NINJA_FORMS : 'NinjaForms',
	);

	$has_delete = false;

	foreach ( $map as $post_key => $plugin_slug ) {
		if ( isset( $_POST[ $post_key ] ) ) {
			$has_delete = true;
			break;
		}
	}

	if ( ! $has_delete ) {
		return;
	}

	// Nonce already verified above.
	duplicateKiller_verify_delete_nonce_or_die( 'dk_delete_logs', 'dk_nonce' );

	global $wpdb;
	$table = $wpdb->prefix . 'dk_forms_duplicate';

	foreach ( $map as $post_key => $plugin_slug ) {

		if ( ! isset( $_POST[ $post_key ] ) ) {
			continue;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized inside duplicateKiller_delete_selected_records_raw() (handles string/array).
		$raw_delete = wp_unslash($_POST[$post_key]);

		duplicateKiller_delete_selected_records_raw(
			$wpdb,
			$table,
			$raw_delete,
			$plugin_slug
		);
	}
}
/**
 * Delete selected records for a given form plugin from dk_forms_duplicate.
 * @param mixed $raw Raw value already extracted from request (string|array).
 */
function duplicateKiller_delete_selected_records_raw( $db, $table, $raw, $plugin_slug ) {
	if ( empty( $raw ) ) {
		return;
	}

	$to_delete = wp_unslash( $raw );

	if ( ! is_array( $to_delete ) ) {
		$form_name = sanitize_text_field( $to_delete );
		if ( '' !== $form_name ) {
			$db->delete( $table, array( 'form_plugin' => $plugin_slug, 'form_name' => $form_name ) );
		}
		return;
	}

	foreach ( $to_delete as $raw_form_name => $delete_flag ) {
		if ( '1' !== (string) $delete_flag ) {
			continue;
		}
		$form_name = sanitize_text_field( $raw_form_name );
		if ( '' !== $form_name ) {
			$db->delete( $table, array( 'form_plugin' => $plugin_slug, 'form_name' => $form_name ) );
		}
	}
}

function duplicateKiller_get_form_cookie_simple(
	array $options,
	string $form_name,
	string $cookie_prefix
): array {

	$form_cookie    = 'NULL';
	$checked_cookie = false;

	// Form must exist in options
	if ( ! isset( $options[ $form_name ] ) || ! is_array( $options[ $form_name ] ) ) {
		return compact( 'form_cookie', 'checked_cookie' );
	}

	$form = $options[ $form_name ];

	// PRO only: cookie must be explicitly enabled for this form
	if ( empty( $form['cookie_option'] ) || (int) $form['cookie_option'] !== 1 ) {
		return compact( 'form_cookie', 'checked_cookie' );
	}

	// =========================
	// GROUP MODE support: FormName.__group__
	// Cookie is named using "group_<safe_form_name>" suffix (set by JS)
	// Example: dk_form_cookie_elementor_forms_group_mainquote_form
	// =========================
	if ( str_ends_with( $form_name, '.__group__' ) ) {

		$raw_name = (string) $form_name;
		$raw_name = preg_replace( '/\.__group__$/', '', $raw_name );
		$raw_name = trim( $raw_name );

		// Normalize form name into cookie-safe suffix (must match JS safeId())
		$group_key = strtolower( $raw_name );
		$group_key = preg_replace( '/\s+/', ' ', $group_key );
		$group_key = preg_replace( '/[^a-z0-9_-]+/', '_', $group_key );
		$group_key = trim( $group_key, '_' );

		if ( $group_key !== '' ) {

			$group_cookie_name = $cookie_prefix . 'group_' . $group_key;

			if ( isset( $_COOKIE[ $group_cookie_name ] ) && $_COOKIE[ $group_cookie_name ] !== '' ) {
				$form_cookie    = sanitize_text_field( wp_unslash( $_COOKIE[ $group_cookie_name ] ) );
				$checked_cookie = true;

				return compact( 'form_cookie', 'checked_cookie' );
			}
		}
	}
	// Form must have a form_id (can be numeric OR string/hash, e.g. Elementor)
	if ( empty( $form['form_id'] ) ) {
		return compact( 'form_cookie', 'checked_cookie' );
	}

	// Normalize form_id into a cookie-safe suffix
	$form_id = strtolower( (string) $form['form_id'] );
	$form_id = preg_replace( '/[^a-z0-9_-]+/', '_', $form_id );
	$form_id = trim( $form_id, '_' );

	if ( $form_id === '' ) {
		return compact( 'form_cookie', 'checked_cookie' );
	}

	// Build cookie name using provided prefix (unchanged behavior)
	$cookie_name = $cookie_prefix . $form_id;

	// Check only this form's cookie
	if ( isset( $_COOKIE[ $cookie_name ] ) && $_COOKIE[ $cookie_name ] !== '' ) {
		$form_cookie    = sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ) );
		$checked_cookie = true;
	}
	
	//trigger only for Formidable
	if ( ! $checked_cookie ) {

		// Fallback for IDs like "contact-us.2" when JS sets numeric cookie suffix "2"
		$raw_id = (string) $form['form_id'];
		if ( preg_match( '/\.(\d+)$/', $raw_id, $m ) ) {
			$fallback_name = $cookie_prefix . (string) absint( $m[1] );

			if ( isset( $_COOKIE[ $fallback_name ] ) && $_COOKIE[ $fallback_name ] !== '' ) {
				$form_cookie    = sanitize_text_field( wp_unslash( $_COOKIE[ $fallback_name ] ) );
				$checked_cookie = true;
			}
		}
	}

	return compact( 'form_cookie', 'checked_cookie' );
}

function duplicateKiller_sanitize_id($string) {
	return preg_replace('/[^a-zA-Z0-9_-]/', '_', $string);
}
function duplicateKiller_get_form_defaults(): array {
    return [
        'error_message' => 'Please check all fields! These values have been submitted already!',
        'error_message_limit_ip_option' => 'This IP has been already submitted.',
        'user_ip_days' => '7',
        'cookie_option_days' => '7',
    ];
}
function duplicateKiller_delete_saved_entries(string $plugin_key, string $form_name): void {
	global $wpdb;

	$table = $wpdb->prefix . 'dk_forms_duplicate';

	$plugin_keys = array($plugin_key);

	if ($plugin_key === 'NinjaForms' || $plugin_key === 'Ninja Forms') {
		$plugin_keys = array('NinjaForms', 'Ninja Forms');
	}

	$placeholders = implode(',', array_fill(0, count($plugin_keys), '%s'));

	$params = array_merge($plugin_keys, array($form_name));

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Required for deleting entries from the plugin custom table.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM " . esc_sql($table) . "
			 WHERE form_plugin IN ($placeholders)
			 AND form_name = %s",
			$params
		)
	);
}
function duplicateKiller_get_field_icon_svg( string $type, string $label = '', string $fid = '' ): string {
	$type_key  = strtolower( trim( $type ) );
	$field_key = strtolower( $label . ' ' . $fid );

	$icons = [
		'email' => '<svg class="dk-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true" focusable="false"><path d="M4 6h16v12H4z" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 7l8 6 8-6" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',

		'text' => '<svg class="dk-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
			<path d="M6.5 7.5c0-.8.6-1.5 1.5-1.5h8c.8 0 1.5.6 1.5 1.5" stroke-width="1.5" stroke-linecap="round"/>
			<path d="M12 6v12" stroke-width="1.6" stroke-linecap="round"/>
			<path d="M9.5 18h5" stroke-width="1.6" stroke-linecap="round"/>
		</svg>',

		'phone' => '<svg class="dk-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true" focusable="false"><path d="M7 4l2.2 4.5-1.4 1.4c1.1 2.2 2.9 4 5.1 5.1l1.4-1.4L19 16v3c0 1.1-.9 2-2 2C9.3 21 3 14.7 3 7c0-1.1.9-2 2-2h2z" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',

		'number' => '<svg class="dk-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true" focusable="false"><path d="M5 9h14" stroke-width="1.6" stroke-linecap="round"/><path d="M5 15h14" stroke-width="1.6" stroke-linecap="round"/><path d="M10 4L8 20" stroke-width="1.6" stroke-linecap="round"/><path d="M16 4l-2 16" stroke-width="1.6" stroke-linecap="round"/></svg>',

		'textarea' => '<svg class="dk-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true" focusable="false"><rect x="4" y="5" width="16" height="14" rx="2" stroke-width="1.6"/><path d="M8 9h8M8 12h8M8 15h5" stroke-width="1.6" stroke-linecap="round"/></svg>',

		'url' => '<svg class="dk-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true" focusable="false"><path d="M10 13a5 5 0 0 0 7.1 0l1.4-1.4a5 5 0 0 0-7.1-7.1L10.5 5.4" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 11a5 5 0 0 0-7.1 0l-1.4 1.4a5 5 0 0 0 7.1 7.1l.9-.9" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',

		'date' => '<svg class="dk-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true" focusable="false"><rect x="4" y="5" width="16" height="15" rx="2" stroke-width="1.6"/><path d="M8 3v4M16 3v4M4 10h16" stroke-width="1.6" stroke-linecap="round"/></svg>',

		'choice' => '<svg class="dk-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true" focusable="false"><path d="M8 7h12M8 12h12M8 17h12" stroke-width="1.6" stroke-linecap="round"/><circle cx="4.5" cy="7" r="1" fill="currentColor"/><circle cx="4.5" cy="12" r="1" fill="currentColor"/><circle cx="4.5" cy="17" r="1" fill="currentColor"/></svg>',

		'default' => '<svg class="dk-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="8" stroke-width="1.6"/><path d="M9 12h6" stroke-width="1.6" stroke-linecap="round"/></svg>',
	];

	if ( $type_key === 'email' ) {
		return $icons['email'];
	}

	if ( $type_key === 'tel' || str_contains( $field_key, 'phone' ) || str_contains( $field_key, 'tel' ) ) {
		return $icons['phone'];
	}

	if ( $type_key === 'text' ) {
		return $icons['text'];
	}

	if ( $type_key === 'textarea' ) {
		return $icons['textarea'];
	}

	if ( $type_key === 'number' ) {
		return $icons['number'];
	}

	if ( $type_key === 'url' ) {
		return $icons['url'];
	}

	if ( $type_key === 'date' ) {
		return $icons['date'];
	}

	if ( in_array( $type_key, [ 'select', 'radio', 'checkbox' ], true ) ) {
		return $icons['choice'];
	}

	return $icons['default'];
}