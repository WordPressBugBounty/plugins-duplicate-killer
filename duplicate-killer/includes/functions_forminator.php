<?php
defined( 'ABSPATH' ) or die( 'You shall not pass!' );

add_action( 'forminator_custom_form_submit_errors', 'duplicateKiller_forminator_before_send_email', 10, 3 );
function duplicateKiller_forminator_before_send_email($submit_errors, $form_id, $field_data_array){
	global $wpdb;
	$table_name = $wpdb->prefix . 'dk_forms_duplicate';

	$forminator_page = get_option("Forminator_page");
	$forminator_page = duplicateKiller_convert_option_architecture( $forminator_page, 'forminator_' );
	if (!is_array($forminator_page)) {
		$forminator_page = [];
	}

	$form_title       = get_the_title($form_id);
	$request_debug_id = uniqid('duplicateKiller_forminator_validate_', true);
	$dk_enabled       = class_exists('duplicateKiller_Diagnostics');

	if ($dk_enabled) {
		duplicateKiller_Diagnostics::log('forminator', 'process_start', [
			'request_debug_id' => $request_debug_id,
			'form_id'          => (int) $form_id,
			'form_title'       => $form_title,
			'field_data_raw'   => is_array($field_data_array) ? $field_data_array : [],
			'submit_errors_before' => $submit_errors,
			'form_config'      => !empty($forminator_page[$form_title]) ? $forminator_page[$form_title] : [],
			'table_name'       => $table_name,
		]);
	}

	if ($dk_enabled) {
		duplicateKiller_Diagnostics::log('forminator', 'ip_check_start', [
			'request_debug_id' => $request_debug_id,
			'form_title'       => $form_title,
			'user_ip_enabled'  => isset($forminator_page[$form_title]['user_ip']) && $forminator_page[$form_title]['user_ip'] === "1" ? 1 : 0,
			'user_ip_days'     => isset($forminator_page[$form_title]['user_ip_days']) ? $forminator_page[$form_title]['user_ip_days'] : '',
		]);
	}

	// 1. IP check
	if (duplicateKiller_ip_limit_trigger("Forminator", $forminator_page, $form_title)) {
		$message = $forminator_page[$form_title]['error_message_limit_ip_option'];

		//change the general error message with the dk_custom_error_message
		add_filter('forminator_custom_form_invalid_form_message', function($invalid_form_message, $form_id) use($message){
			$invalid_form_message = $message;
			return $invalid_form_message = $message;
		}, 15, 2);

		//stop form for submission if IP limit is triggered
		$submit_errors[][] = $message;

		if ($dk_enabled) {
			duplicateKiller_Diagnostics::log('forminator', 'ip_limit_blocked', [
				'request_debug_id' => $request_debug_id,
				'form_title'       => $form_title,
				'message'          => $message,
				'submit_errors_after' => $submit_errors,
			]);
		}

		return $submit_errors;
	}

	// 2. Duplicate field check
	$cookie = duplicateKiller_get_form_cookie_simple(
		$forminator_page,
		$form_title,
		'dk_form_cookie_forminator_'
	);

	$form_cookie    = $cookie['form_cookie'];
	$checked_cookie = $cookie['checked_cookie'];

	if ($dk_enabled) {
		duplicateKiller_Diagnostics::log('forminator', 'cookie_state_resolved', [
			'request_debug_id' => $request_debug_id,
			'form_title'       => $form_title,
			'form_cookie'      => $form_cookie,
			'checked_cookie'   => $checked_cookie,
		]);
	}

	$storage_fields = [];
	$abort          = false;

	if ($dk_enabled) {
		duplicateKiller_Diagnostics::log('forminator', 'duplicate_check_start', [
			'request_debug_id' => $request_debug_id,
			'form_title'       => $form_title,
			'field_count'      => is_array($field_data_array) ? count($field_data_array) : 0,
			'enabled_config'   => !empty($forminator_page[$form_title]) ? $forminator_page[$form_title] : [],
		]);
	}

	if (!empty($forminator_page[$form_title]) && is_array($field_data_array)) {
		$enabled_fields = $forminator_page[$form_title];

		foreach ($field_data_array as $data) {
			$field_name  = $data['name'];
			$field_value = $data['value'];

			// save values
			$storage_fields[] = [
				'name'  => $field_name,
				'value' => $field_value
			];

			if ($dk_enabled) {
				duplicateKiller_Diagnostics::log('forminator', 'field_inspected', [
					'request_debug_id' => $request_debug_id,
					'form_title'       => $form_title,
					'field_name'       => $field_name,
					'field_value'      => $field_value,
					'is_enabled_in_config' => empty($enabled_fields[$field_name]) ? 0 : 1,
				]);
			}

			if (empty($enabled_fields[$field_name])) {
				continue;
			}

			$result = duplicateKiller_check_duplicate_by_key_value("Forminator", $form_title, $field_name, $field_value, $form_cookie, $checked_cookie);

			if ($dk_enabled) {
				duplicateKiller_Diagnostics::log('forminator', 'field_duplicate_check_result', [
					'request_debug_id' => $request_debug_id,
					'form_title'       => $form_title,
					'field_name'       => $field_name,
					'field_value'      => $field_value,
					'duplicate_result' => $result ? 1 : 0,
					'form_cookie'      => $form_cookie,
					'checked_cookie'   => $checked_cookie,
				]);
			}

			if ($result) {
				$message = $forminator_page[$form_title]['error_message'];

				//3. Cookie field check


				if (is_array($field_value)) {
					foreach (['first-name', 'middle-name', 'last-name'] as $suffix) {
						$submit_errors[][$field_name . '-' . $suffix] = $message;
					}
				} else {
					$submit_errors[][$field_name] = $message;
				}

				$abort = true;

				if ($dk_enabled) {
					duplicateKiller_Diagnostics::log('forminator', 'duplicate_found', [
						'request_debug_id'   => $request_debug_id,
						'form_title'         => $form_title,
						'field_name'         => $field_name,
						'message'            => $message,
						'submit_errors_after'=> $submit_errors,
					]);
				}
			}
		}
	}

	if ($dk_enabled) {
		duplicateKiller_Diagnostics::log('forminator', 'process_end', [
			'request_debug_id' => $request_debug_id,
			'form_title'       => $form_title,
			'abort'            => $abort ? 1 : 0,
			'storage_fields'   => $storage_fields,
			'submit_errors_final' => $submit_errors,
		]);
	}

	return $submit_errors;
}

add_action( 'forminator_custom_form_submit_before_set_fields', 'duplicateKiller_forminator_save_fields', 10, 3 );
function duplicateKiller_forminator_save_fields($entry, $id, $field_data) {
	global $wpdb;

	$forminator_page = get_option('Forminator_page');
	$forminator_page = duplicateKiller_convert_option_architecture( $forminator_page, 'forminator_' );
	if (!is_array($forminator_page)) {
		$forminator_page = [];
	}

	$table_name       = $wpdb->prefix . 'dk_forms_duplicate';
	$form_title       = get_the_title($id);
	$request_debug_id = uniqid('duplicateKiller_forminator_save_', true);
	$dk_enabled       = class_exists('duplicateKiller_Diagnostics');

	// Stop if this form is not configured in Forminator_page
	if ( empty( $form_title ) || empty( $forminator_page[ $form_title ] ) || ! is_array( $forminator_page[ $form_title ] ) ) {
		if ( $dk_enabled ) {
			duplicateKiller_Diagnostics::log('forminator', 'save_fields_skipped_not_configured', [
				'request_debug_id' => $request_debug_id,
				'form_id'          => (int) $id,
				'form_title'       => $form_title,
				'has_config'       => ! empty( $forminator_page[ $form_title ] ) ? 1 : 0,
			]);
		}
		return;
	}

	if ($dk_enabled) {
		duplicateKiller_Diagnostics::log('forminator', 'save_fields_start', [
			'request_debug_id' => $request_debug_id,
			'form_id'          => (int) $id,
			'form_title'       => $form_title,
			'entry_raw'        => is_array($entry) ? $entry : [],
			'field_data_raw'   => is_array($field_data) ? $field_data : [],
			'form_config'      => !empty($forminator_page[$form_title]) ? $forminator_page[$form_title] : [],
		]);
	}

	$cookie = duplicateKiller_get_form_cookie_simple(
		$forminator_page,
		$form_title,
		'dk_form_cookie_forminator_'
	);

	$form_cookie    = $cookie['form_cookie'];
	$checked_cookie = $cookie['checked_cookie'];

	if ($dk_enabled) {
		duplicateKiller_Diagnostics::log('forminator', 'save_fields_cookie_state_resolved', [
			'request_debug_id' => $request_debug_id,
			'form_title'       => $form_title,
			'form_cookie'      => $form_cookie,
			'checked_cookie'   => $checked_cookie,
		]);
	}

	$form_ip = (isset($forminator_page[$form_title]['user_ip']) && $forminator_page[$form_title]['user_ip'] === "1") ? true : 'NULL';
	if ($form_ip === true) {
		$form_ip = duplicateKiller_get_user_ip();
	}
	
	if ($dk_enabled) {
		duplicateKiller_Diagnostics::log('forminator', 'save_fields_ip_resolved', [
			'request_debug_id' => $request_debug_id,
			'form_title'       => $form_title,
			'resolved_form_ip' => $form_ip,
		]);
	}

	$storage_fields = [];
	$has_enabled_field = false;
	if (is_array($field_data)) {
		foreach ($field_data as $data) {

			// Extract field name + value from Forminator field data
			$name  = isset($data['name']) ? $data['name'] : '';
			$value = $data['value'] ?? null;
			
			if ($name !== '' && !empty($forminator_page[$form_title][$name])) {
				$has_enabled_field = true;
			}
			if ($dk_enabled) {
				duplicateKiller_Diagnostics::log('forminator', 'save_fields_field_inspected', [
					'request_debug_id'    => $request_debug_id,
					'form_title'          => $form_title,
					'field_name'          => $name,
					'field_value'         => $value,
					'is_enabled_in_config'=> ($name !== '' && !empty($forminator_page[$form_title][$name])) ? 1 : 0,
					'is_file_like'        => is_array($value) && isset($value['file']) && is_array($value['file']) && !empty($value['file']) ? 1 : 0,
				]);
			}

			// Handle file upload field (single or multiple)
			if (is_array($value) && isset($value['file']) && is_array($value['file']) && !empty($value['file'])) {

				$file = $value['file'];

				// Try to extract file name (may not exist for multiple uploads)
				$file_name = $file['file_name'] ?? $file['filename'] ?? $file['name'] ?? '';

				// Extract file URL (can be string or array in multiple upload)
				$file_url_raw = $file['file_url'] ?? $file['url'] ?? '';

				// If multiple upload, file_url can be an array of URLs
				if (is_array($file_url_raw)) {
					// Clean and reindex URLs
					$file_url = array_values(array_filter($file_url_raw));
				} else {
					// Single upload (string URL)
					$file_url = $file_url_raw;
				}

				$storage_fields[] = [
					"name"  => $name,
					"value" => [
						// Ensure consistent type (avoid NULL in DB)
						"file_name" => is_string($file_name) ? $file_name : '',
						"file_url"  => $file_url,
					],
				];

			} else {

				$storage_fields[] = [
					"name"  => $name,
					"value" => $value,
				];

			}
		}
	}
	if (!$has_enabled_field && $form_ip === 'NULL') {
		if ($dk_enabled) {
			duplicateKiller_Diagnostics::log('forminator', 'save_fields_skipped_no_enabled_field_and_no_ip_limit', [
				'request_debug_id'   => $request_debug_id,
				'form_title'         => $form_title,
				'has_enabled_field'  => 0,
				'form_ip'            => $form_ip,
				'storage_fields'     => $storage_fields,
			]);
		}
		return;
	}
	
	$form_value = serialize($storage_fields);
	$form_date  = current_time('Y-m-d H:i:s');
	

	if ($dk_enabled) {
		duplicateKiller_Diagnostics::log('forminator', 'save_fields_before_insert', [
			'request_debug_id' => $request_debug_id,
			'form_title'       => $form_title,
			'has_enabled_field'=> $has_enabled_field ? 1 : 0,
			'form_ip'          => $form_ip,
			'storage_fields'   => $storage_fields,
			'table_name'       => $table_name,
		]);
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Required for custom plugin table insert.
	$insert_result = $wpdb->insert(
		$table_name,
		array(
			'form_plugin' => "Forminator",
			'form_name'   => $form_title,
			'form_value'  => $form_value,
			'form_cookie' => $form_cookie,
			'form_date'   => $form_date,
			'form_ip'     => $form_ip,
		)
	);

	if ($dk_enabled) {
		duplicateKiller_Diagnostics::log('forminator', 'save_fields_after_insert', [
			'request_debug_id' => $request_debug_id,
			'form_title'       => $form_title,
			'insert_ok'        => empty($wpdb->last_error) && false !== $insert_result ? 1 : 0,
			'wpdb_last_error'  => $wpdb->last_error,
			'insert_id'        => $wpdb->insert_id,
			'table_name'       => $table_name,
		]);
	}

	// error_log(print_r($field_data, true));
}

function duplicateKiller_forminator_get_forms_ids() {
    $forminator_posts = get_posts([
        'post_type' => 'forminator_forms',
        'order'     => 'DESC',
        'nopaging'  => true
    ]);

    $forms = [];

    foreach ($forminator_posts as $post) {
        
        if (!empty($post->post_title) && isset($post->ID)) {
            $forms[$post->post_title] = $post->ID;
        }
    }

    return $forms;
}
function duplicateKiller_forminator_get_forms(){
	$forminator_posts = get_posts([
		'post_type' => 'forminator_forms',
		'order' => 'DESC',
		'nopaging' => true
	]);

	$output = array();

	foreach($forminator_posts as $form => $value){

		// Initialize form structure once
		$output[$value->post_title] = [
			'form_id'   => (int) $value->ID,
			'form_name' => (string) $value->post_title,
			'fields'    => [],
		];

		if($result = get_post_meta($value->ID,'',true)){
			$result = maybe_unserialize($result['forminator_form_meta'][0]);

			foreach($result as $res => $var){
				if($var){
					foreach($var as $arr){
						if(isset($arr['id'])){
							if(
								$arr['type'] == "name" ||
								$arr['type'] == "text" ||
								$arr['type'] == "email" ||
								$arr['type'] == "phone" ||
								$arr['type'] == "number"
							){
								$output[$value->post_title]['fields'][] = [
									'id'    => (string) $arr['id'],
									'label' => (string) $arr['id'],
									'type'  => (string) $arr['type'],
								];
							}
						}
					}
				}
			}
		}
	}

	return $output;
}
/*********************************
 * Callbacks
**********************************/
function duplicateKiller_forminator_validate_input($input) {
    return duplicateKiller_sanitize_forms_option($input, 'Forminator_page', 'Forminator_page', [], 'Forminator');
}

function duplicateKiller_forminator_description(){
	if(class_exists('Forminator') OR is_plugin_active('forminator/forminator.php')){ ?>
		<h3 style="color:green"><strong><?php esc_html_e('Forminator plugin is activated!','duplicate-killer');?></strong></h3>
<?php
	}else{ ?>
		<h3 style="color:red"><strong><?php esc_html_e('Forminator plugin is not activated! Please activate it in order to continue.','duplicate-killer');?></strong></h3>
<?php
		exit();
	}
	if(duplicateKiller_forminator_get_forms() == NULL){ ?>
		</br><span style="color:red"><strong><?php esc_html_e('There is no contact forms. Please create one!','duplicate-killer');?></strong></span>
<?php
		exit();
	}
}

function duplicateKiller_forminator_settings_callback($args){
	$options = get_option($args[0]);
}
function duplicateKiller_forminator_select_form_tag_callback($args){
    duplicateKiller_render_forms_overview([
        'option_name'   => (string)$args[0],   // Forminator_page
        'db_plugin_key' => 'Forminator',
        'plugin_label'  => 'Forminator',
        'forms'         => duplicateKiller_forminator_get_forms(),
        'forms_id_map'  => duplicateKiller_forminator_get_forms_ids(),
    ]);
}