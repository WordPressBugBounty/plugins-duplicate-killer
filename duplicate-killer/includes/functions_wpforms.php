<?php
defined( 'ABSPATH' ) or die( 'You shall not pass!' );

add_action('wpforms_process', 'duplicateKiller_wpforms_before_send_email', 10, 3);
function duplicateKiller_wpforms_before_send_email( $fields, $entry, $form_data ) {
	$wpforms_page = get_option( 'WPForms_page' );
	$wpforms_page = duplicateKiller_convert_option_architecture( $wpforms_page, 'wpforms_' );
	if ( ! is_array( $wpforms_page ) ) {
		$wpforms_page = array();
	}

	$request_debug_id = uniqid( 'duplicateKiller_wpforms_', true );
	$dk_enabled       = class_exists( 'duplicateKiller_Diagnostics' );

	$current_form = DuplicateKiller_Form_Normalizer::wpforms(
		is_array( $form_data ) ? $form_data : array(),
		is_array( $fields ) ? $fields : array()
	);

	$resolved_form = DuplicateKiller_Form_Config_Resolver::resolve(
		$wpforms_page,
		$current_form
	);

	if ( false === $resolved_form ) {
		if ( $dk_enabled ) {
			duplicateKiller_Diagnostics::log( 'wpforms', 'form_config_not_found', array(
				'request_debug_id' => $request_debug_id,
				'current_form'     => $current_form,
			) );
		}

		return;
	}

	$form_name      = $resolved_form['form_name'];
	$form_config    = $resolved_form['form_config'];
	$enabled_fields = $resolved_form['enabled_fields'];
	$form_id        = $resolved_form['form_id'];

	$data            = DuplicateKiller_Form_Normalizer::wpforms_data( is_array( $fields ) ? $fields : array() );
	$storage_fields  = DuplicateKiller_Form_Normalizer::wpforms_storage_fields( is_array( $fields ) ? $fields : array() );
	$field_id_map    = DuplicateKiller_Form_Normalizer::wpforms_field_id_map( is_array( $fields ) ? $fields : array() );

	$ip_limit_enabled    = ! empty( $form_config['user_ip'] ) && (string) $form_config['user_ip'] === '1';
	$field_check_enabled = ! empty( $enabled_fields );
	$cross_form_enabled  = ! empty( $form_config['cross_form_option'] ) && (string) $form_config['cross_form_option'] === '1';

	if ( ! $ip_limit_enabled && ! $field_check_enabled && ! $cross_form_enabled ) {
		if ( $dk_enabled ) {
			duplicateKiller_Diagnostics::log( 'wpforms', 'no_duplicate_killer_feature_enabled', array(
				'request_debug_id' => $request_debug_id,
				'form_name'        => $form_name,
				'form_config'      => $form_config,
			) );
		}

		return;
	}

	if ( $dk_enabled ) {
		duplicateKiller_Diagnostics::log( 'wpforms', 'process_start', array(
			'request_debug_id'    => $request_debug_id,
			'form_id'             => $form_id,
			'form_name'           => $form_name,
			'form_config'         => $form_config,
			'enabled_fields'      => $enabled_fields,
			'ip_limit_enabled'    => $ip_limit_enabled ? 1 : 0,
			'field_check_enabled' => $field_check_enabled ? 1 : 0,
			'cross_form_enabled'  => $cross_form_enabled ? 1 : 0,
			'posted_fields_raw'   => is_array( $fields ) ? $fields : array(),
			'entry_raw'           => is_array( $entry ) ? $entry : array(),
			'data'                => $data,
			'storage_fields'      => $storage_fields,
		) );
	}

	$cookie = duplicateKiller_get_form_cookie_simple(
		$wpforms_page,
		$form_name,
		'dk_form_cookie_wpforms_'
	);

	$form_cookie    = $cookie['form_cookie'];
	$checked_cookie = $cookie['checked_cookie'];

	if ( $dk_enabled ) {
		duplicateKiller_Diagnostics::log( 'wpforms', 'cookie_state_resolved', array(
			'request_debug_id' => $request_debug_id,
			'form_name'        => $form_name,
			'form_cookie'      => $form_cookie,
			'checked_cookie'   => $checked_cookie ? 1 : 0,
		) );
	}

	$abort = false;

	// 1. IP limit check.
	if ( $ip_limit_enabled ) {
		$ip_limit_result = DuplicateKiller_IP_Limit_Checker::check(
			'WPForms',
			$form_name,
			$form_config,
			array(
				'request_debug_id' => $request_debug_id,
				'form_name'        => $form_name,
			)
		);

		if ( $ip_limit_result['blocked'] ) {
			$message  = $ip_limit_result['message'];
			$field_id = ! empty( $field_id_map ) ? reset( $field_id_map ) : 1;

			add_filter(
				'wpforms_custom_form_invalid_form_message',
				function( $invalid_form_message ) use ( $message ) {
					return $message;
				},
				15
			);

			wpforms()->process->errors[ $form_id ][ $field_id ]            = $message;
			wpforms()->process->errors[ $form_id ]['wpforms_global']       = $message;

			if ( $dk_enabled ) {
				duplicateKiller_Diagnostics::log( 'wpforms', 'ip_limit_blocked', array(
					'request_debug_id' => $request_debug_id,
					'form_name'        => $form_name,
					'message'          => $message,
				) );
			}

			return;
		}
	}

	// 2. Duplicate field check.
	if ( $field_check_enabled ) {
		$result = DuplicateKiller_FieldDuplicate_Checker::check(
			'WPForms',
			$form_name,
			$enabled_fields,
			$data,
			$form_cookie,
			$checked_cookie,
			$form_config,
			array(
				'request_debug_id' => $request_debug_id,
				'form_name'        => $form_name,
			)
		);

		if ( $result['blocked'] ) {
			$field_key   = $result['field_key'];
			$field_value = array_key_exists( $field_key, $data ) ? $data[ $field_key ] : '';
			$message     = $result['message'];

			if ( is_array( $field_value ) ) {
				foreach ( array( 'first-name', 'middle-name', 'last-name' ) as $suffix ) {
					wpforms()->process->errors[ $form_id ][ $field_key . '-' . $suffix ] = $message;
				}
			} else {
				$wpforms_field_id = $field_id_map[ $field_key ] ?? $field_key;
				wpforms()->process->errors[ $form_id ][ $wpforms_field_id ] = $message;
			}

			wpforms()->process->errors[ $form_id ]['wpforms_global'] = $message;

			if ( $dk_enabled ) {
				duplicateKiller_Diagnostics::log( 'wpforms', 'duplicate_found', array(
					'request_debug_id' => $request_debug_id,
					'form_name'        => $form_name,
					'field_key'        => $field_key,
					'message'          => $message,
				) );
			}

			$abort = true;
			return;
		}
	}

	// 3. Cross-form duplicate check.
	if (
		! $abort
		&& $cross_form_enabled
		&& class_exists( 'DuplicateKiller_CrossForm' )
	) {
		$cross_match = DuplicateKiller_CrossForm::checkNamedValueListCrossFormDuplicate(
			'WPForms',
			$wpforms_page,
			$form_name,
			$form_config,
			$data
		);

		if ( $cross_match ) {
			$field_key = $cross_match['current_field_id'] ?? '';

			$message = ! empty( $form_config['error_message'] )
				? $form_config['error_message']
				: __( 'Please check all fields!', 'duplicate-killer' );

			if ( '' !== $field_key ) {
				$wpforms_field_id = $field_id_map[ $field_key ] ?? $field_key;
				wpforms()->process->errors[ $form_id ][ $wpforms_field_id ] = $message;
			}

			wpforms()->process->errors[ $form_id ]['wpforms_global'] = $message;

			if ( $dk_enabled ) {
				duplicateKiller_Diagnostics::log( 'wpforms', 'cross_form_duplicate_found', array(
					'request_debug_id' => $request_debug_id,
					'form_name'        => $form_name,
					'field_key'        => $field_key,
					'cross_match'      => $cross_match,
					'message'          => $message,
				) );
			}

			$abort = true;
			return;
		}
	}

	// 4. Save submission.
	$should_save_submission = (
		! $abort
		&& (
			$ip_limit_enabled
			|| $field_check_enabled
			|| $cross_form_enabled
		)
	);

	DuplicateKiller_Submission_Storage::save(
		'WPForms',
		$form_name,
		$storage_fields,
		$form_cookie,
		$should_save_submission,
		$ip_limit_enabled,
		array(),
		array(),
		array(
			'request_debug_id' => $request_debug_id,
			'form_name'        => $form_name,
		)
	);
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