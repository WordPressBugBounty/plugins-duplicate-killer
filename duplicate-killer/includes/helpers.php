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
/**
 * Render Duplicate Killer settings UI for a given forms plugin.
 *
 * @param string $plugin_key   Machine key used in DB / shortcode (e.g. 'CF7', 'GF').
 * @param string $plugin_label Human label used in headings (e.g. 'Contact Form 7').
 * @param array  $args         Args passed from add_settings_field callback.
 * @param array  $forms        [ 'Form Name' => [ 'field_tag_1', 'field_tag_2', ... ] ].
 * @param array  $forms_id     [ 'Form Name' => form_id ].
 */
function duplicateKiller_render_forms_ui( $plugin_key, $plugin_label, $args, $forms, $forms_id ) {
	global $wpdb;

	// Capability check – best practice in admin screens.
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Option name from settings API. $args[0] vine de la add_settings_field.
	$option_name = isset( $args[0] ) ? sanitize_key( $args[0] ) : '';

	if ( empty( $option_name ) ) {
		return;
	}

	$options = get_option( $option_name, array() );
	if ( ! is_array( $options ) ) {
		$options = array();
	}

	$counts = array();
	if ( ! empty( $forms ) ) {
		$table = $wpdb->prefix . 'dk_forms_duplicate';
		$table_safe = esc_sql( $table );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reading from plugin-owned custom table (admin-only, request-scoped).
		$results = $wpdb->get_results(
			$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT form_name, COUNT(*) AS total FROM {$table_safe} WHERE form_plugin = %s GROUP BY form_name",
				$plugin_key
			),
			OBJECT_K
		);
		if ( is_array( $results ) ) {
			$counts = $results;
		}
	}
	$upgrade_url = add_query_arg(
		array(
			'page' => 'duplicateKiller',
			'tab'  => 'pro',
		),
		admin_url( 'admin.php' )
	);

	$upgrade_url = esc_url( $upgrade_url );
	?>
	<h2 class="dk-form-header">
		<?php echo esc_html( sprintf( '%s Forms Overview', $plugin_label ) ); ?>
	</h2>
	<?php if ( $plugin_label === 'Elementor' ) : ?>

		<div class="dk-locked dk-elementor-group-mode" style="margin:15px 0;padding:12px 15px;background:#f8f9fa;border-left:4px solid #0073aa;">
			<label style="display:flex;align-items:center;gap:8px;font-weight:600;cursor:not-allowed;">
				<input type="checkbox" >
				Enable Group Mode (Treat forms with the same Form Name as one form)
				<span style="font-size:11px;font-weight:600;color:#fff;background:#2271b1;padding:2px 6px;border-radius:3px;">
					PRO
				</span>
			</label>

			<p style="margin:6px 0 0 24px;font-size:13px;color:#555;">
				When enabled, all Elementor forms that share the same Form Name will be treated as a single form.
				Recommended if you duplicated forms across multiple pages.
			</p>

		</div>

<?php endif; ?>
	
	<?php $dk_index = 0; ?>
	<?php foreach ( $forms as $form_name => $tags ) : ?>
		<?php
		$dk_index++;
		$is_locked_free = ( $dk_index > 1 );
		?>
		<?php
		$form_key  = (string) $form_name;
		
		$count     = isset( $counts[ $form_key ] ) ? (int) $counts[ $form_key ]->total : 0;
		$form_opts = isset( $options[ $form_key ] ) && is_array( $options[ $form_key ] )
			? $options[ $form_key ]
			: array();

		$form_id_safe = sanitize_title( $form_key );
		?>

		<div class="<?php echo esc_attr( 'dk-single-form' . ( $is_locked_free ? ' dk-locked' : '' ) ); ?>">
	<?php if ( $is_locked_free ) : ?>
		<div class="dk-pro-corner-badge">
			<?php echo esc_html__( 'PRO', 'duplicate-killer' ); ?>
		</div>

		<div class="dk-locked-overlay" aria-hidden="true"></div>

		<div class="dk-locked-overlay-content" role="note" aria-label="<?php echo esc_attr__( 'Pro feature', 'duplicate-killer' ); ?>">
			<p class="dk-locked-title">
				<?php echo esc_html__( 'Additional protection available', 'duplicate-killer' ); ?>
			</p>
			
			<div class="dk-locked-cta">
				<span class="dk-locked-mini">
					<?php echo esc_html__( 'Free includes protection for one form per plugin.', 'duplicate-killer' ); ?>
				</span>

				<a class="button button-primary" href="<?php echo esc_url( $upgrade_url ); ?>">
					<?php echo esc_html__( 'Upgrade to PRO', 'duplicate-killer' ); ?>
				</a>
			</div>
		</div>
	<?php endif; ?>
			<h4 class="dk-form-header">
				<?php echo esc_html( $form_key ); ?>
			</h4>

			<h4>
				<?php esc_html_e( 'Choose the unique fields', 'duplicate-killer' ); ?>
				<small style="font-weight:normal; margin-left:8px;">
					<a href="https://verselabwp.com/choose-the-unique-fields-in-wordpress-forms-how-it-works/" 
					   target="_blank" 
					   rel="noopener">
						What to choose?
					</a>
				</small>
			</h4>

			<?php
				/**
				 * Normalise $tags into a list of "fields".
				 *
				 * - For simple integrations (CF7-style): $tags is already an array of strings.
				 * - For bundle integrations (like your Breakdance structure): $tags contains
				 *   ['form_id', 'post_id', 'form_name', 'fields' => [ ...field arrays... ] ].
				 */
				$fields = array();

				if ( is_array( $tags ) && isset( $tags['fields'] ) && is_array( $tags['fields'] ) ) {
					// Bundle structure: use the "fields" child array
					$fields = $tags['fields'];
				} else {
					// Simple structure: treat $tags as the fields array itself
					$fields = $tags;
				}
			?>

			<?php if ( ! empty( $fields ) && is_array( $fields ) ) : ?>
				<?php foreach ( $fields as $field ) : ?>
					<?php
					/**
					 * Each $field can be:
					 * - string: "email", "name", ...
					 * - array:  [ 'id' => 'email', 'label' => 'Email', 'type' => 'text' ]
					 */
					$field_id    = '';
					$field_label = '';

					if ( is_array( $field ) ) {
						if ( isset( $field['id'] ) ) {
							$field_id = (string) $field['id'];
						} elseif ( isset( $field['name'] ) ) {
							$field_id = (string) $field['name'];
						}

						if ( isset( $field['label'] ) && $field['label'] !== '' ) {
							$field_label = (string) $field['label'];
						} else {
							$field_label = $field_id;
						}
					} else {
						// Simple string field
						$field_id    = (string) $field;
						$field_label = $field_id;
					}

					if ( $field_id === '' ) {
						continue;
					}
					
					$field_set = ! empty( $form_opts[ $field_id ] );

					// Safer DOM id
					$input_id = $form_id_safe . '__' . sanitize_html_class( $field_id );
					?>
					<div class="dk-input-checkbox-callback">
						<input type="checkbox"
							id="<?php echo esc_attr( $input_id ); ?>"
							name="<?php echo esc_attr( $is_locked_free ? '' : ( $plugin_key . '_page[' . $form_key . '][' . $field_id . ']' ) ); ?>"
							value="1"
							<?php checked( $field_set ); ?> 
							<?php //disabled( $is_locked_free ); ?>
							/>
							

						<label for="<?php echo esc_attr( $input_id ); ?>">
							<?php echo esc_html( $field_label ); ?>
						</label><br/>
					</div>
					<?php
					// Store label mapping in the same option payload (hidden field).
					?>
					<input type="hidden"
						name="<?php echo esc_attr( $is_locked_free ? '' : ( $plugin_key . '_page[' . $form_key . '][labels][' . $field_id . ']' ) ); ?>"
						value="<?php echo esc_attr( $field_label ); ?>" />
				<?php endforeach; ?>
			<?php endif; ?>
			
			<div class="dk-pro-rules-wrapper" id="dk-pro-rules-<?php echo esc_attr( $form_id_safe ); ?>">

				<button
					type="button"
					class="dk-pro-toggle"
					aria-expanded="false"
					aria-controls="dk-pro-content-<?php echo esc_attr( $form_id_safe ); ?>"
				>
					<span class="dk-pro-toggle-text">
						<?php esc_html_e( 'Individual Form Rules', 'duplicate-killer' ); ?>
					</span>
					<span class="dk-pro-toggle-icon" aria-hidden="true">➜</span>
				</button>

				<div
					class="pro-version dk-pro-rules-content"
					id="dk-pro-content-<?php echo esc_attr( $form_id_safe ); ?>"
				>
					<div class="dk-set-error-message">
						<fieldset class="dk-fieldset dk-error-fieldset">
							<legend class="dk-legend-title">
								<?php esc_html_e( 'Error message when duplicate is found', 'duplicate-killer' ); ?>
							</legend>
							<p class="dk-error-instruction">
								<?php esc_html_e( 'This message will be shown when the user submits a form with duplicate values.', 'duplicate-killer' ); ?>
							</p>
							<input type="text"
								class="dk-error-input"
								placeholder="<?php esc_attr_e( 'Please check all fields! These values have already been submitted.', 'duplicate-killer' ); ?>"
								name=""
								value="<?php esc_attr_e( 'Please check all fields! These values have already been submitted.', 'duplicate-killer' ); ?>" />
						</fieldset>
					</div>

					<div class="dk-limit_submission_by_ip">
						<fieldset class="dk-fieldset">
							<legend class="dk-legend-title">
								<?php esc_html_e( 'Limit submissions by IP address', 'duplicate-killer' ); ?>
							</legend>
							<p>
								<strong><?php esc_html_e( 'This feature', 'duplicate-killer' ); ?></strong>
								<?php esc_html_e( 'restricts form entries based on IP address for a given number of days.', 'duplicate-killer' ); ?>
							</p>

							<div class="dk-input-switch-ios">
								<input type="checkbox"
									class="ios-switch-input"
									id="user_ip_option_<?php echo esc_attr( $form_id_safe ); ?>"
									name=""
									value="1"
									/>

								<label class="ios-switch-label" for="user_ip_option_<?php echo esc_attr( $form_id_safe ); ?>"></label>
								<span class="ios-switch-text">
									<?php esc_html_e( 'Activate this function', 'duplicate-killer' ); ?>
								</span>
							</div>

							<div id="dk-limit-ip_<?php echo esc_attr( $form_id_safe ); ?>" class="dk-toggle-section">
								<label for="error_message_limit_ip_option_<?php echo esc_attr( $form_id_safe ); ?>">
									<?php esc_html_e( 'Set error message for this option:', 'duplicate-killer' ); ?>
								</label>
								<input type="text"
									id="error_message_limit_ip_option_<?php echo esc_attr( $form_id_safe ); ?>"
									name=""
									size="40"
									value=""
									class="dk-error-input"
									placeholder="<?php esc_attr_e( 'This IP has already submitted this form.', 'duplicate-killer' ); ?>" />

								<label for="user_ip_days_<?php echo esc_attr( $form_id_safe ); ?>">
									<?php esc_html_e( 'IP block duration (in days):', 'duplicate-killer' ); ?>
								</label>
								<input type="text"
									id="user_ip_days_<?php echo esc_attr( $form_id_safe ); ?>"
									name=""
									size="5"
									value=""
									class="dk-error-input"
									placeholder="<?php esc_attr_e( 'e.g. 7', 'duplicate-killer' ); ?>" />
							</div>
						</fieldset>
					</div>

					<div class="dk-set-unique-entries-per-user">
						<fieldset class="dk-fieldset">
							<legend class="dk-legend-title">
								<?php esc_html_e( 'Unique entries per user', 'duplicate-killer' ); ?>
							</legend>
							<p>
								<strong><?php esc_html_e( 'This feature uses cookies.', 'duplicate-killer' ); ?></strong>
								<?php esc_html_e( 'Multiple users can submit the same entry, but a single user cannot submit the same one twice.', 'duplicate-killer' ); ?>
							</p>

							<div class="dk-input-switch-ios">
								<input type="checkbox"
									class="ios-switch-input"
									id="cookie_<?php echo esc_attr( $form_id_safe ); ?>"
									name=""
									value="1"
									/>

								<label class="ios-switch-label" for="cookie_<?php echo esc_attr( $form_id_safe ); ?>"></label>
								<span class="ios-switch-text">
									<?php esc_html_e( 'Activate this function', 'duplicate-killer' ); ?>
								</span>
							</div>

							<div id="cookie_section_<?php echo esc_attr( $form_id_safe ); ?>" class="dk-toggle-section">
								<label for="cookie_days_<?php echo esc_attr( $form_id_safe ); ?>">
									<?php esc_html_e( 'Cookie persistence (days - max 365):', 'duplicate-killer' ); ?>
								</label>
								<input type="text"
									id="cookie_days_<?php echo esc_attr( $form_id_safe ); ?>"
									name=""
									size="5"
									value=""
									class="dk-error-input"
									placeholder="<?php esc_attr_e( 'e.g. 7', 'duplicate-killer' ); ?>" />
							</div>
						</fieldset>
					</div>
					<div class="dk-shortcode-count-submission">
						<fieldset class="dk-fieldset">
							<legend class="dk-legend-title">
								<?php esc_html_e( 'Display submission count', 'duplicate-killer' ); ?>
							</legend>
							<p>
								<?php esc_html_e( 'You can use this shortcode to display the submission count anywhere on your site. This is useful for showcasing engagement, verifying participation levels, or triggering conditional actions. Note: refresh every 30 seconds.', 'duplicate-killer' ); ?>
							</p>
							<?php
							$shortcode = sprintf(
								'[duplicateKiller plugin="%s" form="%s"]',
								$plugin_key,
								$form_key
							);
							$unique_id = uniqid( 'dk_shortcode_', true );
							?>
							<div style="display:flex; align-items:center; gap:10px;">
								<input type="text"
									id="<?php echo esc_attr( $unique_id ); ?>"
									value="<?php echo esc_attr( $shortcode ); ?>"
									readonly
									style="flex:1; padding:8px 12px; font-size:16px; border:1px solid #ccc; border-radius:5px; background:#fff; cursor:default;">
								<button type="button" 
									style="padding:8px 16px; font-size:14px; background-color:#0073aa; color:#fff; border:none; border-radius:5px; cursor:pointer;">
									<?php esc_html_e( 'Copy', 'duplicate-killer' ); ?>
								</button>
							</div>
						</fieldset>
					</div>
					<div class="dk-cross-form-option">
						<fieldset class="dk-fieldset">
							<legend class="dk-legend-title">
								Cross-Form Duplicate Protection (PRO)
							</legend>

							<p>
								Allow this form to participate in cross-form duplicate detection.
							</p>

							<div class="dk-input-switch-ios">
								<input
									type="checkbox"
									class="ios-switch-input"
									id="cross_form_demo"
									value="1"
									disabled
								/>

								<label
									class="ios-switch-label"
									for="cross_form_demo"
								></label>

								<span class="ios-switch-text">
									Enable Cross-Form Duplicate Protection for this form
								</span>
							</div>

							<p class="dk-pro-note">
								This feature is available in <strong>Duplicate Killer PRO</strong>.
							</p>
						</fieldset>
					</div>
				</div>
			</div>
			<div class="dk-box dk-delete-records">
				<p class="dk-record-count">
					📦
					<span class="dk-count-number">
						<?php echo esc_html( (string) $count ); ?>
					</span>
					<?php esc_html_e( 'saved submissions found for this form', 'duplicate-killer' ); ?>
				</p>

				<?php if ( $count > 0 ) : ?>
					<label for="<?php echo esc_attr( 'delete_records_' . $form_id_safe ); ?>" class="dk-delete-label">
						<input type="checkbox"
							id="<?php echo esc_attr( 'delete_records_' . $form_id_safe ); ?>"
							name="<?php echo esc_attr( $plugin_key . '_delete_records[' . $form_key . ']' ); ?>"
							value="1"
							class="dk-delete-checkbox" />
						🗑️
						<span>
							<?php esc_html_e( 'Delete all saved entries for this form', 'duplicate-killer' ); ?>
							<small>
								<?php esc_html_e( '(this action cannot be undone)', 'duplicate-killer' ); ?>
							</small>
						</span>
					</label>
				<?php endif; ?>
			</div>
		</div><!-- .dk-single-form -->
	<?php endforeach; ?>

	<div id="dk-toast" style="
	  display:none;
	  position:fixed;
	  top:50px;
	  left:50%;
	  transform:translateX(-50%);
	  background-color:#323232;
	  color:#fff;
	  padding:12px 20px;
	  border-radius:6px;
	  font-size:14px;
	  z-index:9999;
	  box-shadow:0 2px 6px rgba(0,0,0,0.3);
	">
	  <?php esc_html_e( 'Shortcode copied!', 'duplicate-killer' ); ?>
	</div>
	<?php
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
function duplicateKiller_check_duplicate_by_key_value($form_plugin, $form_name, $key, $value, $form_cookie = 'NULL', $checked_cookie = false) {
	global $wpdb;

	$table_name = $wpdb->prefix . 'dk_forms_duplicate';

	// Cache query results per request for the same plugin + form.
	// This avoids running the exact same SELECT multiple times
	// when several fields are checked during one submission.
	static $dk_results_cache = array();

	$cache_key = $form_plugin . '|' . $form_name;

	if ( ! isset( $dk_results_cache[ $cache_key ] ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required duplicate check query on plugin-owned table.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT form_value, form_cookie
				 FROM " . esc_sql( $table_name ) . "
				 WHERE form_plugin = %s
				 AND form_name = %s
				 ORDER BY form_id DESC",
				$form_plugin,
				$form_name
			)
		);

		$dk_results_cache[ $cache_key ] = is_array( $results ) ? $results : array();
	}

	$results = $dk_results_cache[ $cache_key ];

	foreach ( $results as $row ) {
		$form_data = maybe_unserialize( $row->form_value );

		// Associative array payloads: field_id => value
		if ( is_array( $form_data ) && isset( $form_data[ $key ] ) ) {
			if ( duplicateKiller_check_values_with_lowercase_filter( $form_data[ $key ], $value ) ) {

				// When cookie protection is enabled, duplicate detection becomes user-specific.
				// Multiple users can submit the same value, but the same user (same cookie)
				// cannot submit the same value more than once.
				if ( $checked_cookie == true ) {

					// If the cookie matches, it means the same user already submitted this value.
					if ( $row->form_cookie == $form_cookie ) {
						return true;
					} else {
						// Same value exists but belongs to another user (different cookie),
						// therefore it is allowed for the current user.
						return false;
					}
				}

				return true;
			}

		// Named value list payloads: array( array( 'name' => ..., 'value' => ... ) )
		} elseif ( is_array( $form_data ) && isset( $form_data[0]['name'] ) ) {
			foreach ( $form_data as $input ) {
				if ( isset( $input['name'] ) && $input['name'] === $key ) {
					if ( duplicateKiller_check_values_with_lowercase_filter( $input['value'], $value ) ) {

						// When cookie protection is enabled, duplicate detection becomes user-specific.
						// Multiple users can submit the same value, but the same user (same cookie)
						// cannot submit the same value more than once.
						if ( $checked_cookie == true ) {

							// If the cookie matches, it means the same user already submitted this value.
							if ( $row->form_cookie == $form_cookie ) {
								return true;
							} else {
								// Same value exists but belongs to another user (different cookie),
								// therefore it is allowed for the current user.
								return false;
							}
						}

						return true;
					}
				}
			}
		}
	}

	return false;
}
function duplicateKiller_check_values_with_lowercase_filter($var1, $var2){
	if(is_array($var1) AND is_array($var2)){
		$var1 = array_map('strtolower', $var1);
		$var2 = array_map('strtolower', $var2);
		if($var1 == $var2){
			return true;
		}
	}elseif(!is_array($var1) AND !is_array($var2)){
		if(strtolower($var1) == strtolower($var2)){
			return true;
		}
	}
	return false;
}
/**
 * Return the DK cookie value only if the Formidable cookie feature is enabled.
 *
 * @param array $formidable_page The full Formidable_page option array.
 * @return string Cookie value or 'NULL'
 */
function duplicateKiller_get_formidable_cookie_if_enabled( array $formidable_page ) {

	if (
		empty( $formidable_page['formidable_cookie_option'] ) ||
		'1' !== (string) $formidable_page['formidable_cookie_option'] ||
		empty( $_COOKIE['dk_form_cookie'] )
	) {
		return 'NULL';
	}

	return sanitize_text_field(
		wp_unslash( $_COOKIE['dk_form_cookie'] )
	);
}
function duplicateKiller_ip_limit_trigger($plugin, $plugin_options, $form_name){
	//get option values -- ex: CF7_page[Form_Name][Option_saved]
	if (isset($plugin_options[$form_name]['user_ip']) && $plugin_options[$form_name]['user_ip'] == "1") {
		$form_ip = duplicateKiller_get_user_ip();
		$user_ip_days = (int)$plugin_options[$form_name]['user_ip_days'];
		if(duplicateKiller_check_ip_feature($plugin,$form_name,$form_ip,$user_ip_days)){
			//ip is already in db
			return true;
			
		}
	}
	return false;
}
function duplicateKiller_check_ip_feature($form_plugin,$form_name,$form_ip,$user_ip_days = 7){
	$flag = false;
	global $wpdb;
	$table_name = $wpdb->prefix . 'dk_forms_duplicate';
	
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Querying Formidable custom table (admin-only, no WP API).
	$result = $wpdb->get_row(
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Querying Formidable custom table (admin-only, no WP API).
		$wpdb->prepare(
			"SELECT form_ip, form_date
			 FROM " . esc_sql( $table_name ) . "
			 WHERE form_plugin = %s
			 AND form_name = %s
			 AND form_ip = %s
			 ORDER BY form_id DESC",
			$form_plugin,
			$form_name,
			$form_ip
		)
	);
	
    //$sql = $wpdb->prepare( "SELECT form_ip FROM {$table_name} WHERE form_plugin = %s AND form_name = %s ORDER BY form_id DESC" , $form_plugin, $form_name );
    if($result){
		$created_at = new DateTime($result->form_date, new DateTimeZone('UTC'));

        // Current date minus 7 days (default)
        $threshold  = new DateTime("-{$user_ip_days} days", new DateTimeZone('UTC'));

        if ($created_at > $threshold ) {
			//The row is newer than 7 days.(default)
            $flag = true;
        }
		
	}
	return $flag;
}

function duplicateKiller_get_user_ip() {
	$ip_valid = 'undefined';

	// Cloudflare
	if (
		function_exists( 'duplicateKiller_isCloudflare' ) &&
		duplicateKiller_isCloudflare() &&
		isset( $_SERVER['HTTP_CF_CONNECTING_IP'] )
	) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated via filter_var(FILTER_VALIDATE_IP).
		$raw = wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] );
		$raw = trim( $raw );

		if ( filter_var( $raw, FILTER_VALIDATE_IP ) ) {
			$ip_valid = $raw;
			return apply_filters( 'duplicateKiller_get_user_ip', $ip_valid );
		}
	}

	// Client IP
	if ( isset( $_SERVER['HTTP_CLIENT_IP'] ) ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated via filter_var(FILTER_VALIDATE_IP).
		$raw = wp_unslash( $_SERVER['HTTP_CLIENT_IP'] );
		$raw = trim( $raw );

		if ( filter_var( $raw, FILTER_VALIDATE_IP ) ) {
			$ip_valid = $raw;
			return apply_filters( 'duplicateKiller_get_user_ip', $ip_valid );
		}
	}

	// X-Forwarded-For
	if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated via filter_var(FILTER_VALIDATE_IP).
		$raw = wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] );
		$raw = trim( $raw );

		$parts    = array_map( 'trim', explode( ',', $raw ) );
		$first_ip = $parts[0] ?? '';

		if ( $first_ip && filter_var( $first_ip, FILTER_VALIDATE_IP ) ) {
			$ip_valid = $first_ip;
			return apply_filters( 'duplicateKiller_get_user_ip', $ip_valid );
		}
	}

	// Fallback
	if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated via filter_var(FILTER_VALIDATE_IP).
		$raw = wp_unslash( $_SERVER['REMOTE_ADDR'] );
		$raw = trim( $raw );

		if ( filter_var( $raw, FILTER_VALIDATE_IP ) ) {
			$ip_valid = $raw;
		}
	}

	return apply_filters( 'duplicateKiller_get_user_ip', $ip_valid );
}

//Validates that the IP is from cloudflare
function duplicateKiller_ip_in_range($ip, $range) {
    if (strpos($range, '/') == false)
        $range .= '/32';

    // $range is in IP/CIDR format eg 127.0.0.1/24
    list($range, $netmask) = explode('/', $range, 2);
    $range_decimal = ip2long($range);
    $ip_decimal = ip2long($ip);
    $wildcard_decimal = pow(2, (32 - $netmask)) - 1;
    $netmask_decimal = ~ $wildcard_decimal;
    return (($ip_decimal & $netmask_decimal) == ($range_decimal & $netmask_decimal));
}

function duplicateKiller_cloudflare_CheckIP($ip) {
    $cf_ips = array(
        '173.245.48.0/20',
		'103.21.244.0/22',
		'103.22.200.0/22',
		'103.31.4.0/22',
		'141.101.64.0/18',
		'108.162.192.0/18',
		'190.93.240.0/20',
		'188.114.96.0/20',
		'197.234.240.0/22',
		'198.41.128.0/17',
		'162.158.0.0/15',
		'104.16.0.0/13',
		'104.24.0.0/14',
		'172.64.0.0/13',
		'131.0.72.0/22'
    );
    $is_cf_ip = false;
    foreach ($cf_ips as $cf_ip) {
        if (duplicateKiller_ip_in_range($ip, $cf_ip)) {
            $is_cf_ip = true;
            break;
        }
    } return $is_cf_ip;
}

function duplicateKiller_cloudflare_Requests_Check() {
    $flag = true;

    if(!isset($_SERVER['HTTP_CF_CONNECTING_IP']))   $flag = false;
    if(!isset($_SERVER['HTTP_CF_IPCOUNTRY']))       $flag = false;
    if(!isset($_SERVER['HTTP_CF_RAY']))             $flag = false;
    if(!isset($_SERVER['HTTP_CF_VISITOR']))         $flag = false;
    return $flag;
}

function duplicateKiller_isCloudflare(){
	if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- IP validated inside duplicateKiller_cloudflare_CheckIP().
	$remote_ip = wp_unslash( $_SERVER['REMOTE_ADDR'] );
}
    $ipCheck = duplicateKiller_cloudflare_CheckIP($remote_ip);
    $requestCheck = duplicateKiller_cloudflare_Requests_Check();
    return ($ipCheck && $requestCheck);
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