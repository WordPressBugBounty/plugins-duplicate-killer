<?php
defined( 'ABSPATH' ) or die( 'You shall not pass!' );

add_action('wpforms_process', 'duplicateKiller_wpforms_before_send_email', 10, 3);
function duplicateKiller_wpforms_before_send_email($fields, $entry, $form_data){
	global $wpdb;

	$form_title = isset($form_data['settings']['form_title'])
		? (string) $form_data['settings']['form_title']
		: '';

	$request_debug_id = uniqid('duplicateKiller_wpforms_', true);
	$form_id_numeric  = isset($form_data['id']) ? (int) $form_data['id'] : 0;

	$table_name   = $wpdb->prefix . 'dk_forms_duplicate';
	$wpforms_page = get_option("WPForms_page");
	$wpforms_page = duplicateKiller_convert_option_architecture( $wpforms_page, 'wpforms_' );
	if (!is_array($wpforms_page)) {
		$wpforms_page = [];
	}

	// === Diagnostics flags
	$dk_enabled = class_exists('duplicateKiller_Diagnostics');
	$dk_has_errors = $dk_enabled && method_exists('duplicateKiller_Diagnostics', 'duplicateKiller_get_wpforms_errors_snapshot');
	$dk_has_normalize = $dk_enabled && method_exists('duplicateKiller_Diagnostics', 'duplicateKiller_normalize_debug_value');

	// === Logger wrapper
	$dk_log = static function(string $stage, array $payload = []) use ($dk_enabled) {
		if (!$dk_enabled) return;
		duplicateKiller_Diagnostics::log('wpforms', $stage, $payload);
	};

	$dk_log('process_start', [
		'request_debug_id' => $request_debug_id,
		'form_title'       => $form_title,
		'form_id'          => $form_id_numeric,
		'form_settings'    => isset($form_data['settings']) && is_array($form_data['settings']) ? $form_data['settings'] : [],
		'wpforms_page_has_form_config' => !empty($wpforms_page[$form_title]) ? 1 : 0,
		'wpforms_page_form_config'     => !empty($wpforms_page[$form_title]) ? $wpforms_page[$form_title] : [],
		'posted_fields_raw'            => is_array($fields) ? $fields : [],
		'entry_raw'                    => is_array($entry) ? $entry : [],
		'errors_before_processing'     => $dk_has_errors
			? duplicateKiller_Diagnostics::duplicateKiller_get_wpforms_errors_snapshot($form_id_numeric)
			: [],
	]);

	$cookie = duplicateKiller_get_form_cookie_simple(
		$wpforms_page,
		$form_title,
		'dk_form_cookie_wpforms_'
	);

	$form_cookie    = $cookie['form_cookie'];
	$checked_cookie = $cookie['checked_cookie'];

	$dk_log('cookie_state_resolved', [
		'request_debug_id' => $request_debug_id,
		'form_title'       => $form_title,
		'form_cookie'      => $form_cookie,
		'checked_cookie'   => $checked_cookie,
	]);

	$abort             = false;
	$storage_fields    = [];
	$current_values    = [];
	$has_enabled_field = false;

	$form_ip = isset($wpforms_page[$form_title]['user_ip']) && $wpforms_page[$form_title]['user_ip'] === "1"
		? true
		: 'NULL';

	// =========================
	// 1. IP CHECK
	// =========================
	$dk_log('ip_check_start', [
		'request_debug_id' => $request_debug_id,
		'form_title'       => $form_title,
	]);

	if (duplicateKiller_ip_limit_trigger("WPForms", $wpforms_page, $form_title)) {

		$message  = $wpforms_page[$form_title]['error_message_limit_ip_option'];
		$form_id  = (int) $form_data['id'];
		$field_id = 1;

		add_filter('wpforms_custom_form_invalid_form_message', function($invalid_form_message) use ($message) {
			return $message;
		}, 15);

		wpforms()->process->errors[$form_id][$field_id] = $message;
		wpforms()->process->errors[$form_id]['wpforms_global'] = $message;

		$dk_log('ip_limit_blocked', [
			'request_debug_id' => $request_debug_id,
			'message'          => $message,
		]);

		return;
	}

	// =========================
	// 2. DUPLICATE CHECK
	// =========================
	$dk_log('duplicate_check_start', [
		'request_debug_id' => $request_debug_id,
	]);

	if (!empty($wpforms_page[$form_title]) && is_array($fields)) {
		$enabled_fields = $wpforms_page[$form_title];

		foreach ($fields as $data) {

			if (!is_array($data) || !isset($data['name'])) {
				continue;
			}

			$field_name  = (string) $data['name'];
			$field_value = $data['value'] ?? '';

			$current_values[$field_name] = $field_value;

			$storage_fields[] = [
				'name'  => $field_name,
				'value' => $field_value
			];

			if (empty($enabled_fields[$field_name])) {
				continue;
			}

			$has_enabled_field = true;

			$result = duplicateKiller_check_duplicate_by_key_value(
				"WPForms",
				$form_title,
				$field_name,
				$field_value,
				$form_cookie,
				$checked_cookie
			);

			$dk_log('field_duplicate_check_result', [
				'request_debug_id' => $request_debug_id,
				'field_name'       => $field_name,
				'duplicate_result' => $result ? 1 : 0,
			]);

			if ($result) {
				$message = $wpforms_page[$form_title]['error_message'];

				if (is_array($field_value)) {
					foreach (['first-name', 'middle-name', 'last-name'] as $suffix) {
						wpforms()->process->errors[$form_data['id']][$field_name . '-' . $suffix] = $message;
					}
				} else {
					wpforms()->process->errors[$form_data['id']][$data['id']] = $message;
				}

				$dk_log('duplicate_found', [
					'request_debug_id' => $request_debug_id,
					'field_name'       => $field_name,
				]);

				$abort = true;
				break; // ✅ IMPORTANT FIX
			}
		}
	}

	// =========================
	// 3. SAVE
	// =========================
	if (!$abort && ($has_enabled_field || $form_ip === true)) {

		if ($form_ip === true) {
			$form_ip = duplicateKiller_get_user_ip();
		}

		$form_value = serialize($storage_fields);
		$form_date  = current_time('Y-m-d H:i:s');

		$insert_result = $wpdb->insert(
			$table_name,
			array(
				'form_plugin' => "WPForms",
				'form_name'   => $form_title,
				'form_value'  => $form_value,
				'form_cookie' => $form_cookie,
				'form_date'   => $form_date,
				'form_ip'     => $form_ip
			)
		);

		$dk_log('save_after_insert', [
			'request_debug_id' => $request_debug_id,
			'insert_ok'        => empty($wpdb->last_error) && false !== $insert_result ? 1 : 0,
			'wpdb_last_error'  => $wpdb->last_error,
		]);
	}
}
function duplicateKiller_wpforms_get_forms_ids() {
	$wpforms_posts = get_posts([
		'post_type' => 'wpforms',
		'order'     => 'DESC',
		'nopaging'  => true
	]);

	$forms = [];

	foreach ($wpforms_posts as $post) {
		if (!empty($post->post_title) && isset($post->ID)) {
			$forms[$post->post_title] = $post->ID;
		}
	}

	return $forms;
}
function duplicateKiller_wpforms_get_forms(){
	$wpforms_posts = get_posts([
		'post_type' => 'wpforms',
		'order' => 'DESC',
		'nopaging' => true
	]);

	$output = array();

	foreach($wpforms_posts as $form){
		$form_data = json_decode($form->post_content, true);

		$output[$form->post_title] = [
			'form_id'   => (int) $form->ID,
			'form_name' => (string) $form->post_title,
			'fields'    => [],
		];

		if (!empty($form_data['fields'])) {
			foreach ((array) $form_data['fields'] as $key => $field) {
				if (
					$field['type'] == "name" ||
					$field['type'] == "text" ||
					$field['type'] == "email" ||
					$field['type'] == "phone" ||
					$field['type'] == "number" ||
					$field['type'] == "textarea"
				) {
					$output[$form->post_title]['fields'][] = [
						'id'    => (string) $field['label'],
						'label' => (string) $field['label'],
						'type'  => (string) $field['type'],
					];
				}
			}
		}
	}

	return $output;
}
/*********************************
 * Callbacks
**********************************/
function duplicateKiller_wpforms_validate_input($input) {
    return duplicateKiller_sanitize_forms_option($input, 'WPForms_page', 'WPForms_page', [], 'WPForms');
}

function duplicateKiller_WPForms_description() {
	if(class_exists('wpforms') OR is_plugin_active('wpforms-lite/wpforms.php')){?>
		<h3 style="color:green"><strong><?php esc_html_e('WPForms plugin is activated!','duplicate-killer');?></strong></h3>
<?php
	}else{ ?>
		<h3 style="color:red"><strong><?php esc_html_e('WPForms plugin is not activated! Please activate it in order to continue.','duplicate-killer');?></strong></h3>
<?php
		exit();
	}
	if(duplicateKiller_wpforms_get_forms() == NULL){ ?>
		</br><h3 style="color:red"><strong><?php esc_html_e('There is no contact forms. Please create one!','duplicate-killer');?></strong></h3>
<?php
		exit();
	}
}

function duplicateKiller_wpforms_settings_callback($args){
	$options = get_option($args[0]);
}
function duplicateKiller_wpforms_select_form_tag_callback($args){
    duplicateKiller_render_forms_overview([
        'option_name'   => (string)$args[0],   // WPForms_page
        'db_plugin_key' => 'WPForms',
        'plugin_label'  => 'WPForms',
        'forms'         => duplicateKiller_wpforms_get_forms(),
        'forms_id_map'  => duplicateKiller_wpforms_get_forms_ids(),
    ]);
}