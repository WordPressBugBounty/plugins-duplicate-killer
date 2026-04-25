<?php
defined( 'ABSPATH' ) or die( 'You shall not pass!' );

add_action( 'wpcf7_before_send_mail', 'duplicateKiller_cf7_before_send_email', 1, 3 );
function duplicateKiller_cf7_before_send_email($contact_form, &$abort, $object) {

	global $wpdb;
	$table_name = $wpdb->prefix . 'dk_forms_duplicate';
	
	$cf7_page   = get_option("CF7_page");
	//error_log(print_r($cf7_page,true));
	$cf7_page = duplicateKiller_convert_option_architecture( $cf7_page, 'cf7_' );
	//error_log(print_r($cf7_page,true));
	
	if (!is_array($cf7_page)) {
		$cf7_page = [];
	}

	$request_debug_id = uniqid('duplicateKiller_cf7_', true);
	$dk_enabled       = class_exists('duplicateKiller_Diagnostics');

	$submission = WPCF7_Submission::get_instance();
	$data       = $submission ? $submission->get_posted_data() : [];
	$files      = $submission ? $submission->uploaded_files() : [];
	$upload_dir = wp_upload_dir();

	// NEW location (WP.org compliant)
	$dkcf7_folder     = trailingslashit($upload_dir['basedir']) . 'duplicate-killer';
	$dkcf7_folder_url = trailingslashit($upload_dir['baseurl']) . 'duplicate-killer';

	// ensure dir exists
	if (!file_exists($dkcf7_folder)) {
		wp_mkdir_p($dkcf7_folder);
	}

	$form_name = $contact_form->title();

	if ($dk_enabled) {
		duplicateKiller_Diagnostics::log('cf7', 'process_start', [
			'request_debug_id'      => $request_debug_id,
			'form_name'             => $form_name,
			'form_id'               => method_exists($contact_form, 'id') ? (int) $contact_form->id() : 0,
			'cf7_page_has_form_config' => !empty($cf7_page[$form_name]) ? 1 : 0,
			'cf7_page_form_config'     => !empty($cf7_page[$form_name]) ? $cf7_page[$form_name] : [],
			'posted_data_raw'          => is_array($data) ? $data : [],
			'uploaded_files_raw'       => is_array($files) ? $files : [],
			'abort_before_processing'  => $abort ? 1 : 0,
		]);
	}

	$cookie = duplicateKiller_get_form_cookie_simple(
		$cf7_page,
		$form_name,
		'dk_form_cookie_cf7_'
	);

	$form_cookie    = $cookie['form_cookie'];
	$checked_cookie = $cookie['checked_cookie'];

	if ($dk_enabled) {
		duplicateKiller_Diagnostics::log('cf7', 'cookie_state_resolved', [
			'request_debug_id' => $request_debug_id,
			'form_name'        => $form_name,
			'form_cookie'      => $form_cookie,
			'checked_cookie'   => $checked_cookie,
		]);
	}

	$abort   = false;
	$no_form = false;
	
	$ip_limit_enabled = ! empty( $cf7_page[ $form_name ]['user_ip'] ) && (string) $cf7_page[ $form_name ]['user_ip'] === '1';
	
	$form_ip = isset($cf7_page[$form_name]['user_ip']) && $cf7_page[$form_name]['user_ip'] === "1" ? true : 'NULL';

	if ($dk_enabled) {
		duplicateKiller_Diagnostics::log('cf7', 'ip_check_start', [
			'request_debug_id' => $request_debug_id,
			'form_name'        => $form_name,
			'user_ip_enabled'  => isset($cf7_page[$form_name]['user_ip']) && $cf7_page[$form_name]['user_ip'] === "1" ? 1 : 0,
			'user_ip_days'     => isset($cf7_page[$form_name]['user_ip_days']) ? $cf7_page[$form_name]['user_ip_days'] : '',
		]);
	}

	// 1. IP check
	if (duplicateKiller_ip_limit_trigger("CF7", $cf7_page, $form_name)) {
		$message = $cf7_page[$form_name]['error_message_limit_ip_option'];

		// change the general error message with the dk_custom_error_message
		add_filter('cf7_custom_form_invalid_form_message', function($invalid_form_message, $contact_form) use ($message) {
			$invalid_form_message = $message;
			return $invalid_form_message = $message;
		}, 15, 2);

		// stop form for submission if IP limit is triggered
		$abort = true;
		$object->set_response($message);

		if ($dk_enabled) {
			duplicateKiller_Diagnostics::log('cf7', 'ip_limit_blocked', [
				'request_debug_id' => $request_debug_id,
				'form_name'        => $form_name,
				'message'          => $message,
				'response_after_block' => $message,
			]);
		}

		return;
	}

	if ($dk_enabled) {
		duplicateKiller_Diagnostics::log('cf7', 'duplicate_check_start', [
			'request_debug_id' => $request_debug_id,
			'form_name'        => $form_name,
			'posted_keys'      => is_array($data) ? array_keys($data) : [],
			'config_keys'      => !empty($cf7_page[$form_name]) && is_array($cf7_page[$form_name]) ? array_keys($cf7_page[$form_name]) : [],
		]);
	}

	// 2. Duplicate field check
	if (!empty($data) && !empty($cf7_page)) {
		foreach ($cf7_page as $cf7_form => $fields_to_check) {
			if ($cf7_form !== $form_name) {
				continue;
			}

			if (!is_array($fields_to_check)) {
				continue;
			}

			foreach ($fields_to_check as $field_key => $enabled) {
				if (!$enabled || !isset($data[$field_key])) {
					continue;
				}

				$submitted_value = is_array($data[$field_key]) ? reset($data[$field_key]) : $data[$field_key];
				$submitted_value = sanitize_text_field($submitted_value);

				if ($dk_enabled) {
					duplicateKiller_Diagnostics::log('cf7', 'field_inspected', [
						'request_debug_id' => $request_debug_id,
						'form_name'        => $form_name,
						'field_key'        => $field_key,
						'field_value'      => $submitted_value,
						'is_enabled_in_config' => !empty($enabled) ? 1 : 0,
					]);
				}

				$result = duplicateKiller_check_duplicate_by_key_value(
					"CF7",
					$form_name,
					$field_key,
					$submitted_value,
					$form_cookie,
					$checked_cookie
				);

				if ($dk_enabled) {
					duplicateKiller_Diagnostics::log('cf7', 'field_duplicate_check_result', [
						'request_debug_id' => $request_debug_id,
						'form_name'        => $form_name,
						'field_key'        => $field_key,
						'field_value'      => $submitted_value,
						'duplicate_result' => $result ? 1 : 0,
						'form_cookie'      => $form_cookie,
						'checked_cookie'   => $checked_cookie,
					]);
				}

				if ($result = duplicateKiller_check_duplicate_by_key_value("CF7", $form_name, $field_key, $submitted_value, $form_cookie, $checked_cookie)) {

					if (!$result) {
						$no_form = true;
					}

					if ($no_form == false) {
						$abort = true;
						$object->set_response($cf7_page[$form_name]['error_message']);
						remove_action('wpcf7_before_send_mail', 'cfdb7_before_send_mail');
						remove_action('wpcf7_before_send_mail', 'vsz_cf7_before_send_email');

						if ($dk_enabled) {
							duplicateKiller_Diagnostics::log('cf7', 'duplicate_found', [
								'request_debug_id' => $request_debug_id,
								'form_name'        => $form_name,
								'field_key'        => $field_key,
								'message'          => $cf7_page[$form_name]['error_message'],
								'response_after_block' => $cf7_page[$form_name]['error_message'],
							]);
						}

						return;
					}
				}

				$no_form = true;
			}
		}
	}

	if ($dk_enabled) {
		duplicateKiller_Diagnostics::log('cf7', $abort ? 'save_skipped_abort' : 'save_start', [
			'request_debug_id'   => $request_debug_id,
			'form_name'          => $form_name,
			'abort'              => $abort ? 1 : 0,
			'no_form'            => $no_form ? 1 : 0,
			'form_ip_before_resolve' => $form_ip,
			'posted_data_before_save' => is_array($data) ? $data : [],
			'files_before_save'       => is_array($files) ? $files : [],
		]);
	}

	// 3. Save to DB
	if ( ! $abort && ( $no_form || $ip_limit_enabled ) ) {

		// check if IP limit feature is active and store it
		if ($form_ip === true) {
			$form_ip = duplicateKiller_get_user_ip();
		}

		if ($dk_enabled) {
			duplicateKiller_Diagnostics::log('cf7', 'save_ip_resolved', [
				'request_debug_id' => $request_debug_id,
				'form_name'        => $form_name,
				'resolved_form_ip' => $form_ip,
			]);
		}

		// Save files (if enabled)
		// check if user want to save the files locally
		if (!isset($cf7_page['cf7_save_image']) || (string) $cf7_page['cf7_save_image'] === '1') {
			if ($files) {
				// Init filesystem once
				global $wp_filesystem;
				if (!$wp_filesystem) {
					require_once ABSPATH . 'wp-admin/includes/file.php';
					WP_Filesystem();
				}

				$random_number = uniqid((string) time(), true);

				foreach ($files as $file_key => $file) {
					$file = is_array($file) ? reset($file) : $file;
					if (empty($file)) {
						continue;
					}

					$dest_name = $file_key . '-' . $random_number . '-' . basename($file);
					$file_path = trailingslashit($dkcf7_folder) . $dest_name;
					$file_url  = trailingslashit($dkcf7_folder_url) . rawurlencode($dest_name);

					if ($wp_filesystem) {
						$contents = file_get_contents($file);
						if (false !== $contents) {
							$wp_filesystem->put_contents($file_path, $contents, FS_CHMOD_FILE);

							if (array_key_exists($file_key, $data)) {
								$data[$file_key] = $file_url;
							}

							if ($dk_enabled) {
								duplicateKiller_Diagnostics::log('cf7', 'file_saved_locally', [
									'request_debug_id' => $request_debug_id,
									'form_name'        => $form_name,
									'file_key'         => $file_key,
									'file_path'        => $file_path,
									'file_url'         => $file_url,
								]);
							}
						}
					}
				}
			}
		}

		$form_value = serialize($data);
		$form_date  = current_time('Y-m-d H:i:s');

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Required for custom plugin table insert.
		$insert_result = $wpdb->insert(
			$table_name,
			array(
				'form_plugin' => "CF7",
				'form_name'   => $form_name,
				'form_value'  => $form_value,
				'form_cookie' => $form_cookie,
				'form_date'   => $form_date,
				'form_ip'     => $form_ip
			)
		);

		if ($dk_enabled) {
			duplicateKiller_Diagnostics::log('cf7', 'save_after_insert', [
				'request_debug_id' => $request_debug_id,
				'form_name'        => $form_name,
				'insert_ok'        => empty($wpdb->last_error) && false !== $insert_result ? 1 : 0,
				'wpdb_last_error'  => $wpdb->last_error,
				'insert_id'        => $wpdb->insert_id,
				'table_name'       => $table_name,
			]);
		}
	}
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
		
	<div class="dk-save-image-to-server">
	<fieldset class="dk-fieldset">
		<legend><strong>Store files on your server</strong></legend>
		<strong>Stores files submitted through the form.</strong>
		<span> Warning: This will use your server storage space.</span>
		<br><br>

		<div class="dk-input-checkbox-callback">
			<input type="checkbox" id="save_image" name="<?php echo esc_attr($args[0] . '[cf7_save_image]'); ?>" value="1" <?php echo esc_attr($checkbox_save_image ? 'checked' : ''); ?>>
			<label for="save_image">Enable files saving</label>
		</div>

		<div id="dk-save-image-path" style="display:none">
			<p><strong>Images will be saved in the default folder:</strong> <code>/wp-content/uploads/duplicate-killer</code></p>
			<p><em>This location will be used automatically. No additional configuration is needed.</em></p>
		</div>
	</fieldset>
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