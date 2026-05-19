<?php
defined( 'ABSPATH' ) or die( 'You shall not pass!' );

add_action( 'forminator_custom_form_submit_errors', 'duplicateKiller_forminator_before_send_email', 10, 3 );
add_action( 'forminator_custom_form_submit_before_set_fields', 'duplicateKiller_forminator_save_fields', 10, 3 );

function duplicateKiller_forminator_before_send_email( $submit_errors, $form_id, $field_data_array ) {
	$forminator_page = get_option( 'Forminator_page' );
	$forminator_page = duplicateKiller_convert_option_architecture( $forminator_page, 'forminator_' );
	if ( ! is_array( $forminator_page ) ) {
		$forminator_page = array();
	}

	$request_debug_id = uniqid( 'duplicateKiller_forminator_validate_', true );
	$dk_enabled       = class_exists( 'duplicateKiller_Diagnostics' );

	$current_form = DuplicateKiller_Form_Normalizer::forminator( $form_id, $field_data_array );

	$resolved_form = DuplicateKiller_Form_Config_Resolver::resolve(
		$forminator_page,
		$current_form
	);

	if ( false === $resolved_form ) {
		if ( $dk_enabled ) {
			duplicateKiller_Diagnostics::log( 'forminator', 'form_config_not_found', array(
				'request_debug_id' => $request_debug_id,
				'current_form'     => $current_form,
				'submit_errors'    => $submit_errors,
			) );
		}

		return $submit_errors;
	}

	$form_name      = $resolved_form['form_name'];
	$form_config    = $resolved_form['form_config'];
	$enabled_fields = $resolved_form['enabled_fields'];

	$data = DuplicateKiller_Form_Normalizer::forminator_data( $field_data_array );

	$ip_limit_enabled    = ! empty( $form_config['user_ip'] ) && (string) $form_config['user_ip'] === '1';
	$field_check_enabled = ! empty( $enabled_fields );
	$cross_form_enabled  = ! empty( $form_config['cross_form_option'] ) && (string) $form_config['cross_form_option'] === '1';

	if ( ! $ip_limit_enabled && ! $field_check_enabled && ! $cross_form_enabled ) {
		if ( $dk_enabled ) {
			duplicateKiller_Diagnostics::log( 'forminator', 'no_duplicate_killer_feature_enabled', array(
				'request_debug_id' => $request_debug_id,
				'form_name'        => $form_name,
				'form_config'      => $form_config,
			) );
		}

		return $submit_errors;
	}

	if ( $dk_enabled ) {
		duplicateKiller_Diagnostics::log( 'forminator', 'process_start', array(
			'request_debug_id'    => $request_debug_id,
			'form_id'             => (int) $form_id,
			'form_name'           => $form_name,
			'form_config'         => $form_config,
			'enabled_fields'      => $enabled_fields,
			'ip_limit_enabled'    => $ip_limit_enabled ? 1 : 0,
			'field_check_enabled' => $field_check_enabled ? 1 : 0,
			'cross_form_enabled'  => $cross_form_enabled ? 1 : 0,
			'field_data_raw'      => is_array( $field_data_array ) ? $field_data_array : array(),
			'data'                => $data,
			'submit_errors_before'=> $submit_errors,
		) );
	}

	$cookie = duplicateKiller_get_form_cookie_simple(
		$forminator_page,
		$form_name,
		'dk_form_cookie_forminator_'
	);

	$form_cookie    = $cookie['form_cookie'];
	$checked_cookie = $cookie['checked_cookie'];

	if ( $dk_enabled ) {
		duplicateKiller_Diagnostics::log( 'forminator', 'cookie_state_resolved', array(
			'request_debug_id' => $request_debug_id,
			'form_name'        => $form_name,
			'form_cookie'      => $form_cookie,
			'checked_cookie'   => $checked_cookie ? 1 : 0,
		) );
	}

	// 1. IP limit check.
	if ( $ip_limit_enabled ) {
		$ip_limit_result = DuplicateKiller_IP_Limit_Checker::check(
			'Forminator',
			$form_name,
			$form_config,
			array(
				'request_debug_id' => $request_debug_id,
				'form_name'        => $form_name,
			)
		);

		if ( $ip_limit_result['blocked'] ) {
			add_filter(
				'forminator_custom_form_invalid_form_message',
				function( $invalid_form_message, $form_id ) use ( $ip_limit_result ) {
					return $ip_limit_result['message'];
				},
				15,
				2
			);

			$submit_errors[][] = $ip_limit_result['message'];

			if ( $dk_enabled ) {
				duplicateKiller_Diagnostics::log( 'forminator', 'ip_limit_blocked', array(
					'request_debug_id'    => $request_debug_id,
					'form_name'           => $form_name,
					'message'             => $ip_limit_result['message'],
					'submit_errors_after' => $submit_errors,
				) );
			}

			return $submit_errors;
		}
	}

	// 2. Duplicate field check.
	if ( $field_check_enabled ) {
		$result = DuplicateKiller_FieldDuplicate_Checker::check(
			'Forminator',
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

			if ( is_array( $field_value ) ) {
				foreach ( array( 'first-name', 'middle-name', 'last-name' ) as $suffix ) {
					$submit_errors[][ $field_key . '-' . $suffix ] = $result['message'];
				}
			} else {
				$submit_errors[][ $field_key ] = $result['message'];
			}

			if ( $dk_enabled ) {
				duplicateKiller_Diagnostics::log( 'forminator', 'duplicate_found', array(
					'request_debug_id'    => $request_debug_id,
					'form_name'           => $form_name,
					'field_key'           => $field_key,
					'message'             => $result['message'],
					'submit_errors_after' => $submit_errors,
				) );
			}

			return $submit_errors;
		}
	}

	// 3. Cross-form duplicate check.
	if (
		empty( $submit_errors )
		&& $cross_form_enabled
		&& class_exists( 'DuplicateKiller_CrossForm' )
	) {
		$cross_match = DuplicateKiller_CrossForm::checkForminatorCrossFormDuplicate(
			$forminator_page,
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
				$submit_errors[][ $field_key ] = $message;
			} else {
				$submit_errors[][] = $message;
			}

			if ( $dk_enabled ) {
				duplicateKiller_Diagnostics::log( 'forminator', 'cross_form_duplicate_found', array(
					'request_debug_id'    => $request_debug_id,
					'form_name'           => $form_name,
					'field_key'           => $field_key,
					'cross_match'         => $cross_match,
					'message'             => $message,
					'submit_errors_after' => $submit_errors,
				) );
			}

			return $submit_errors;
		}
	}

	if ( $dk_enabled ) {
		duplicateKiller_Diagnostics::log( 'forminator', 'process_end', array(
			'request_debug_id'     => $request_debug_id,
			'form_name'            => $form_name,
			'submit_errors_final'  => $submit_errors,
		) );
	}

	return $submit_errors;
}
function duplicateKiller_forminator_save_fields( $entry, $id, $field_data ) {
	$forminator_page = get_option( 'Forminator_page' );
	$forminator_page = duplicateKiller_convert_option_architecture( $forminator_page, 'forminator_' );
	if ( ! is_array( $forminator_page ) ) {
		$forminator_page = array();
	}

	$request_debug_id = uniqid( 'duplicateKiller_forminator_save_', true );
	$dk_enabled       = class_exists( 'duplicateKiller_Diagnostics' );

	$current_form = DuplicateKiller_Form_Normalizer::forminator( $id, $field_data );

	$resolved_form = DuplicateKiller_Form_Config_Resolver::resolve(
		$forminator_page,
		$current_form
	);

	if ( false === $resolved_form ) {
		if ( $dk_enabled ) {
			duplicateKiller_Diagnostics::log( 'forminator', 'save_fields_skipped_not_configured', array(
				'request_debug_id' => $request_debug_id,
				'form_id'          => (int) $id,
				'current_form'     => $current_form,
			) );
		}

		return;
	}

	$form_name      = $resolved_form['form_name'];
	$form_config    = $resolved_form['form_config'];
	$enabled_fields = $resolved_form['enabled_fields'];

	$ip_limit_enabled    = ! empty( $form_config['user_ip'] ) && (string) $form_config['user_ip'] === '1';
	$field_check_enabled = ! empty( $enabled_fields );
	$cross_form_enabled  = ! empty( $form_config['cross_form_option'] ) && (string) $form_config['cross_form_option'] === '1';

	$should_save_submission = (
		$ip_limit_enabled
		|| $field_check_enabled
		|| $cross_form_enabled
	);

	if ( ! $should_save_submission ) {
		if ( $dk_enabled ) {
			duplicateKiller_Diagnostics::log( 'forminator', 'save_fields_skipped_no_feature_enabled', array(
				'request_debug_id' => $request_debug_id,
				'form_id'          => (int) $id,
				'form_name'        => $form_name,
				'form_config'      => $form_config,
			) );
		}

		return;
	}

	$cookie = duplicateKiller_get_form_cookie_simple(
		$forminator_page,
		$form_name,
		'dk_form_cookie_forminator_'
	);

	$form_cookie = $cookie['form_cookie'];

	$storage_fields = DuplicateKiller_Form_Normalizer::forminator_storage_fields( $field_data );

	if ( $dk_enabled ) {
		duplicateKiller_Diagnostics::log( 'forminator', 'save_fields_start', array(
			'request_debug_id'        => $request_debug_id,
			'form_id'                 => (int) $id,
			'form_name'               => $form_name,
			'entry_raw'               => is_array( $entry ) ? $entry : array(),
			'field_data_raw'          => is_array( $field_data ) ? $field_data : array(),
			'form_config'             => $form_config,
			'enabled_fields'          => $enabled_fields,
			'ip_limit_enabled'        => $ip_limit_enabled ? 1 : 0,
			'field_check_enabled'     => $field_check_enabled ? 1 : 0,
			'cross_form_enabled'      => $cross_form_enabled ? 1 : 0,
			'should_save_submission'  => $should_save_submission ? 1 : 0,
			'storage_fields'          => $storage_fields,
		) );
	}

	DuplicateKiller_Submission_Storage::save(
		'Forminator',
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