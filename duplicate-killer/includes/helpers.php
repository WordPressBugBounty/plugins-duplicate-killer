<?php
defined( 'ABSPATH' ) or die( 'You shall not pass!' );

define( 'DK_NINJA_FORMS', 'NinjaForms' );
define( 'DK_NINJA_FORMS_LABEL', 'Ninja Forms' );

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

			<h4><?php esc_html_e( 'Choose the unique fields', 'duplicate-killer' ); ?></h4>

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

/**
 * Resolve cookie for a form (FREE global + PRO per-form).
 *
 * @param array  $options   get_option(...) array
 * @param string $form_name internal form key (ex: contactme.1)
 *
 * @return array {
 *   form_cookie    string  Cookie value or 'NULL'
 *   checked_cookie bool    True only if cookie exists AND is enabled for this form
 * }
 */
function duplicateKiller_get_form_cookie_simple( array $options, string $form_name ): array {

	$form_cookie    = 'NULL';
	$checked_cookie = false;

	// -------------------------
	// 1) PRO: per-form cookie
	// -------------------------
	if (
		isset( $options[ $form_name ]['cookie_option_days'] )
		&& is_numeric( $options[ $form_name ]['cookie_option_days'] )
		&& (int) $options[ $form_name ]['cookie_option_days'] > 0
	) {
		if ( ! empty( $_COOKIE['dk_form_cookie'] ) ) {
			$form_cookie    = sanitize_text_field( wp_unslash( $_COOKIE['dk_form_cookie'] ) );
			$checked_cookie = true;
		}
		return compact( 'form_cookie', 'checked_cookie' );
	}

	// -------------------------
	// 2) FREE: global cookie
	// -------------------------
	if (
		isset( $options['ninjaforms_cookie_option'] )
		&& (string) $options['ninjaforms_cookie_option'] === '1'
	) {
		if ( ! empty( $_COOKIE['dk_form_cookie'] ) ) {
			$form_cookie    = sanitize_text_field( wp_unslash( $_COOKIE['dk_form_cookie'] ) );
			$checked_cookie = true;
		}
	}

	return compact( 'form_cookie', 'checked_cookie' );
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
function duplicateKiller_ip_limit_trigger($plugin, $plugin_options, $form_name) {

    // Normalize plugin key (avoid casing bugs)
    $plugin = strtolower((string) $plugin);

    // Map plugin => global option key that enables IP limit
    $ip_option_key = array(
        'breakdance'  => 'breakdance_user_ip',
        'elementor'   => 'elementor_user_ip',
        'formidable'  => 'formidable_user_ip',
		'ninjaforms'  => 'ninjaforms_user_ip',
    );

    if (empty($ip_option_key[$plugin])) {
        return false;
    }

    $flag_key = $ip_option_key[$plugin];

    if (isset($plugin_options[$flag_key]) && (string) $plugin_options[$flag_key] === '1') {
        $form_ip = duplicateKiller_get_user_ip();
        if (duplicateKiller_check_ip_feature($plugin, $form_name, $form_ip)) {
            return true;
        }
    }

    return false;
}
function duplicateKiller_check_ip_feature($form_plugin,$form_name,$form_ip){
	$flag = false;
	global $wpdb;
	$table_name = esc_sql( $wpdb->prefix . 'dk_forms_duplicate' );
	
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reading from plugin-owned custom table (admin-only, request-scoped).
	$result = $wpdb->get_row( $wpdb->prepare(	
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is fixed and plugin-controlled.
			"SELECT form_ip, form_date FROM {$table_name} WHERE form_plugin = %s AND form_name = %s AND form_ip = %s ORDER BY form_id DESC",
			$form_plugin,
			$form_name,
			$form_ip
		)
	);
	
    //$sql = $wpdb->prepare( "SELECT form_ip FROM {$table_name} WHERE form_plugin = %s AND form_name = %s ORDER BY form_id DESC" , $form_plugin, $form_name );
    if($result){
		$created_at = new DateTime($result->form_date, new DateTimeZone('UTC'));

        // Current date minus 7 days
        $seven_days_ago = new DateTime('-7 days', new DateTimeZone('UTC'));

        if ($created_at > $seven_days_ago) {
			//The row is newer than 7 days.
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