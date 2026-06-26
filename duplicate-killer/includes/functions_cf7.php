<?php
defined( 'ABSPATH' ) or die( 'You shall not pass!' );

add_action( 'wpcf7_before_send_mail', 'duplicateKiller_cf7_before_send_email', 1, 3 );
add_action( 'wp_enqueue_scripts', 'duplicateKiller_cf7_popups_compat_enqueue', 30 );

function duplicateKiller_cf7_popups_compat_enqueue() {

	if ( is_admin() ) {
		return;
	}

	if ( ! defined( 'DUPLICATEKILLER_PLUGIN' ) || ! defined( 'DUPLICATEKILLER_VERSION' ) ) {
		return;
	}

	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	if ( ! is_plugin_active( 'cf7-popups/cf7-popups.php' ) ) {
		return;
	}

	wp_enqueue_script(
		'duplicatekiller-cf7-popups-compat',
		plugins_url( 'assets/dk-cf7-popups-compat.js', DUPLICATEKILLER_PLUGIN ),
		array( 'jquery', 'cf7-popups-frontend' ),
		DUPLICATEKILLER_VERSION,
		true
	);
}
function duplicateKiller_cf7_before_send_email( $contact_form, &$abort, $object ) {

	$cf7_page   = get_option( 'CF7_page' );
	if ( ! is_array( $cf7_page ) ) {
		$cf7_page = [];
	}
	$cf7_page = duplicateKiller_convert_option_architecture( $cf7_page, 'cf7_' );

	$request_debug_id = uniqid( 'duplicateKiller_cf7_', true );
	$dk_enabled       = class_exists( 'duplicateKiller_Diagnostics' );

	$submission = WPCF7_Submission::get_instance();
	$data       = $submission ? $submission->get_posted_data() : [];
	$files      = $submission ? $submission->uploaded_files() : [];
	
	if ( ! is_array( $data ) ) {
		$data = [];
	}
	if ( ! is_array( $files ) ) {
		$files = [];
	}

	$current_form = DuplicateKiller_Form_Normalizer::cf7( $contact_form );

	$resolved_form = DuplicateKiller_Form_Config_Resolver::resolve(
		$cf7_page,
		$current_form
	);

	if ( false === $resolved_form ) {
		if ( $dk_enabled ) {
			duplicateKiller_Diagnostics::log( 'cf7', 'form_config_not_found', [
				'request_debug_id' => $request_debug_id,
				'current_form'     => $current_form,
			] );
		}

		return;
	}

	$form_name      = $resolved_form['form_name'];
	$form_config    = $resolved_form['form_config'];
	$enabled_fields = $resolved_form['enabled_fields'];

	$ip_limit_enabled    = ! empty( $form_config['user_ip'] ) && (string) $form_config['user_ip'] === '1';
	$field_check_enabled = ! empty( $enabled_fields );
	$cross_form_enabled  = ! empty( $form_config['cross_form_option'] ) && (string) $form_config['cross_form_option'] === '1';

	if ( ! $ip_limit_enabled && ! $field_check_enabled && ! $cross_form_enabled ) {
		if ( $dk_enabled ) {
			duplicateKiller_Diagnostics::log( 'cf7', 'no_duplicate_killer_feature_enabled', [
				'request_debug_id' => $request_debug_id,
				'form_name'        => $form_name,
				'form_config'      => $form_config,
			] );
		}

		return;
	}

	if ( $dk_enabled ) {
		duplicateKiller_Diagnostics::log( 'cf7', 'process_start', [
			'request_debug_id'     => $request_debug_id,
			'form_name'            => $form_name,
			'form_id'              => $resolved_form['form_id'],
			'form_config'          => $form_config,
			'enabled_fields'       => $enabled_fields,
			'ip_limit_enabled'     => $ip_limit_enabled ? 1 : 0,
			'field_check_enabled'  => $field_check_enabled ? 1 : 0,
			'cross_form_enabled'   => $cross_form_enabled ? 1 : 0,
			'posted_data_raw'      => $data,
			'uploaded_files_raw'   => $files,
			'abort_before_process' => $abort ? 1 : 0,
		] );
	}

	$cookie = duplicateKiller_get_form_cookie_simple(
		$cf7_page,
		$form_name,
		'dk_form_cookie_cf7_'
	);

	$form_cookie    = $cookie['form_cookie'];
	$checked_cookie = $cookie['checked_cookie'];

	if ( $dk_enabled ) {
		duplicateKiller_Diagnostics::log( 'cf7', 'cookie_state_resolved', [
			'request_debug_id' => $request_debug_id,
			'form_name'        => $form_name,
			'form_cookie'      => $form_cookie,
			'checked_cookie'   => $checked_cookie ? 1 : 0,
		] );
	}

	$abort = false;

	// 1. IP limit check.
	if ( $ip_limit_enabled ) {
		$ip_limit_result = DuplicateKiller_IP_Limit_Checker::check(
			'CF7',
			$form_name,
			$form_config,
			array(
				'request_debug_id' => $request_debug_id,
				'form_name'        => $form_name,
			)
		);

		if ( $ip_limit_result['blocked'] ) {
			add_filter(
				'cf7_custom_form_invalid_form_message',
				function( $invalid_form_message, $contact_form ) use ( $ip_limit_result ) {
					return $ip_limit_result['message'];
				},
				15,
				2
			);

			$abort = true;

			if ( is_object( $object ) && method_exists( $object, 'set_response' ) ) {
				$object->set_response( $ip_limit_result['message'] );
			}
			
			remove_action( 'wpcf7_before_send_mail', 'cfdb7_before_send_mail' );
			remove_action( 'wpcf7_before_send_mail', 'vsz_cf7_before_send_email' );

			return;
		}
	}

	// 2. Duplicate field check: simple or cookie-based.
	if ( $field_check_enabled ) {
		$result = DuplicateKiller_FieldDuplicate_Checker::check(
			'CF7',
			$form_name,
			$enabled_fields,
			$data,
			$form_cookie,
			$checked_cookie,
			$form_config,
			array(
				'request_debug_id' => $request_debug_id,
			)
		);

		if ( $result['blocked'] ) {
			$abort = true;

			if ( is_object( $object ) && method_exists( $object, 'set_response' ) ) {
				$object->set_response( $result['message'] );
			}

			remove_action( 'wpcf7_before_send_mail', 'cfdb7_before_send_mail' );
			remove_action( 'wpcf7_before_send_mail', 'vsz_cf7_before_send_email' );

			return;
		}
	}

	// 3. Cross-form duplicate check.
	if (
		! $abort
		&& $cross_form_enabled
		&& class_exists( 'DuplicateKiller_CrossForm' )
	) {
		$cross_match = DuplicateKiller_CrossForm::checkAssocCrossFormDuplicate(
			'CF7',
			$cf7_page,
			$form_name,
			$form_config,
			$data
		);

		if ( $cross_match ) {
			$message = ! empty( $form_config['error_message'] )
				? $form_config['error_message']
				: __( 'Please check all fields!', 'duplicate-killer' );

			$abort = true;

			if ( is_object( $object ) && method_exists( $object, 'set_response' ) ) {
				$object->set_response( $message );
			}

			remove_action( 'wpcf7_before_send_mail', 'cfdb7_before_send_mail' );
			remove_action( 'wpcf7_before_send_mail', 'vsz_cf7_before_send_email' );

			if ( $dk_enabled ) {
				duplicateKiller_Diagnostics::log( 'cf7', 'cross_form_duplicate_found', [
					'request_debug_id' => $request_debug_id,
					'form_name'        => $form_name,
					'cross_match'      => $cross_match,
					'message'          => $message,
				] );
			}

			return;
		}
	}

	// 4. Save to DB only if at least one Duplicate Killer feature is active.
	$should_save_submission = (
		! $abort
		&& (
			$ip_limit_enabled
			|| $field_check_enabled
			|| $cross_form_enabled
		)
	);

	$storage_options = array(
		'save_files' => isset( $cf7_page['cf7_save_image'] ) ? (string) $cf7_page['cf7_save_image'] : '1',
	);

	DuplicateKiller_Submission_Storage::save(
		'CF7',
		$form_name,
		$data,
		$form_cookie,
		$should_save_submission,
		$ip_limit_enabled,
		$files,
		$storage_options,
		array(
			'request_debug_id' => $request_debug_id,
			'form_name'        => $form_name,
		)
	);
}
/**
 * Retrieve CF7 forms and extract their text/email/tel fields.
 * Forms are ordered in descending order by ID (newest first).
 */
function duplicateKiller_CF7_get_forms() {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reading CF7 forms from core posts table (admin-only, request-scoped).
	$CF7Query = $wpdb->get_results(
		"SELECT ID, post_title, post_content
		 FROM {$wpdb->posts}
		 WHERE post_type = 'wpcf7_contact_form'
		 ORDER BY ID DESC",
		ARRAY_A
	);

	if (empty($CF7Query)) {
		return [];
	}

	$output = [];

	foreach ($CF7Query as $form) {

		$form_id      = (int) $form['ID'];
		$form_name    = (string) $form['post_title'];
		$post_content = (string) $form['post_content'];

		$tagsArray = explode(' ', $post_content);

		$output[$form_name] = [
			'form_id'   => $form_id,
			'form_name' => $form_name,
			'fields'    => [],
		];

		for ($i = 0; $i < count($tagsArray); $i++) {

			$field_type = '';

			if (
				str_contains($tagsArray[$i], '[text') &&
				! str_contains($tagsArray[$i], '[textarea')
			) {
				$field_type = 'text';
			} elseif (str_contains($tagsArray[$i], '[email')) {
				$field_type = 'email';
			} elseif (str_contains($tagsArray[$i], '[tel')) {
				$field_type = 'tel';
			} elseif (str_contains($tagsArray[$i], '[number')) {
				$field_type = 'number';
			} elseif (str_contains($tagsArray[$i], '[textarea')) {
				$field_type = 'textarea';
			} elseif (str_contains($tagsArray[$i], '[submit')) {
				break;
			}

			if ($field_type === '') {
				continue;
			}

			if (!isset($tagsArray[$i + 1])) {
				continue;
			}

			// Split the next token by closing bracket and keep only the field name/id.
			$result = explode(']', $tagsArray[$i + 1]);
			$field_id = sanitize_text_field((string) ($result[0] ?? ''));

			if ($field_id === '') {
				continue;
			}

			$output[$form_name]['fields'][] = [
				'id'    => $field_id,
				'label' => $field_id,
				'type'  => $field_type,
			];
		}
	}

	return $output;
}

/*********************************
 * Callbacks
**********************************/
function duplicateKiller_cf7_validate_input($input) {
    $global_keys = ['cf7_save_image'];
    return duplicateKiller_sanitize_forms_option($input, 'CF7_page', 'CF7_page', $global_keys, 'CF7');
}

function duplicateKiller_CF7_description() {

    // Include plugin.php if necessary
    if ( ! function_exists('is_plugin_active') ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    if ( class_exists('WPCF7_ContactForm') || is_plugin_active('contact-form-7/wp-contact-form-7.php') ) {

        echo '<h3 style="color:green"><strong>' .
            esc_html__('Contact Form 7 plugin is activated!', 'duplicate-killer') .
        '</strong></h3>';

    } else {

        echo '<h3 style="color:red"><strong>' .
            esc_html__('Contact Form 7 plugin is not activated! Please activate it in order to continue.', 'duplicate-killer') .
        '</strong></h3>';

        exit; // stop further execution cleanly
    }

    $forms = duplicateKiller_CF7_get_forms();

    if ( empty($forms) ) {

        echo '<br><span style="color:red"><strong>' .
            esc_html__('There are no contact forms. Please create one!', 'duplicate-killer') .
        '</strong></span>';

        exit; // stop further execution cleanly
    }
}

function duplicateKiller_cf7_settings_callback($args){
	$options = get_option($args[0]);

	$checkbox_save_image = (!isset($options['cf7_save_image']) || $options['cf7_save_image'] == "1") ? 1 : 0;
	
	
	?>
	<h4 class="dk-form-header">General settings</h4>
	<div class="dk-settings-card dk-card-width-1">
		<div class="dk-card-section">

			<div class="dk-feature-row">
				<div class="dk-feature-info">
					<h4><?php esc_html_e('Store uploaded files for Contact Form 7', 'duplicate-killer'); ?></h4>

					<p>
						<?php esc_html_e(
							'Save files submitted through the form directly on your server. This feature uses your server storage space.',
							'duplicate-killer'
						); ?>
					</p>
				</div>

				<div class="dk-feature-control">
					<div class="dk-input-switch-ios">
						<input
							type="checkbox"
							class="ios-switch-input"
							id="save_image"
							name="<?php echo esc_attr($args[0] . '[cf7_save_image]'); ?>"
							value="1"
							<?php checked($checkbox_save_image, 1); ?>
						/>

						<label class="ios-switch-label" for="save_image"></label>
					</div>
				</div>
			</div>

			<div id="dk-save-image-path"
				class="dk-feature-fields is-active"
				style="<?php echo $checkbox_save_image ? '' : 'display:none;'; ?>">

				<div class="dk-feature-field dk-feature-field--full">
					<label>
						<?php esc_html_e('Storage location', 'duplicate-killer'); ?>
					</label>

					<p>
						<?php esc_html_e(
							'Uploaded files will be stored automatically in the following WordPress uploads directory.',
							'duplicate-killer'
						); ?>
					</p>

					<input
						type="text"
						class="dk-error-input"
						readonly
						value="/wp-content/uploads/duplicate-killer">
				</div>

			</div>
		</div>
	</div>
<?php
}
function duplicateKiller_get_cf7_forms_info() {
    global $wpdb;
	
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reading CF7 forms from core posts table (admin-only, request-scoped).
    $results = $wpdb->get_results(
        "SELECT post_title, ID 
         FROM {$wpdb->posts}
         WHERE post_type = 'wpcf7_contact_form'
           AND post_status NOT IN ('trash','auto-draft')
         ORDER BY ID DESC",
        ARRAY_A
    );

    if ( empty( $results ) ) {
        return [];
    }

    $forms = [];

    foreach ( $results as $row ) {
        $title = sanitize_text_field( $row['post_title'] );
        $id    = (int) $row['ID'];

        if ( $id > 0 && $title !== '' ) {
            $forms[ $title ] = $id;
        }
    }

    return $forms;
}
function duplicateKiller_cf7_select_form_tag_callback($args){
    duplicateKiller_render_forms_overview([
        'option_name'   => (string)$args[0],   // CF7_page
        'db_plugin_key' => 'CF7',
        'plugin_label'  => 'Contact Form 7',
        'forms'         => duplicateKiller_CF7_get_forms(),
        'forms_id_map'  => duplicateKiller_get_cf7_forms_info(),
    ]);
}