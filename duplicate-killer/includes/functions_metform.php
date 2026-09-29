<?php
defined( 'ABSPATH' ) || exit;

add_filter( 'mf_after_validation_check', 'duplicateKiller_metform_validate', 10, 1 );
add_filter( 'rest_request_after_callbacks', 'duplicateKiller_metform_save_success', 10, 3 );
add_action( 'metform_after_store_form_data', 'duplicateKiller_metform_capture_uploads', 10, 4 );

function duplicateKiller_metform_get_context( $form_id, $form_data ) {
	if ( $form_id <= 0 || ! is_array( $form_data ) ) {
		return array();
	}

	$options = get_option( 'MetForm_page', array() );

	if ( ! is_array( $options ) || empty( $options ) ) {
		return array();
	}

	$data         = DuplicateKiller_Form_Normalizer::metform_data( $form_data );
	$current_form = DuplicateKiller_Form_Normalizer::metform( $form_id, $data );

	$resolved = DuplicateKiller_Form_Config_Resolver::resolve_by_form_id(
		$options,
		$current_form
	);

	if ( empty( $resolved ) || empty( $resolved['option_key'] ) ) {
		return array();
	}

	$form_name = (string) $resolved['option_key'];
	$cookie    = duplicateKiller_get_form_cookie_simple(
		$options,
		$form_name,
		'dk_form_cookie_metform_'
	);

	return array(
		'form_id'        => $form_id,
		'form_name'      => $form_name,
		'data'           => $data,
		'options'        => $options,
		'resolved'       => $resolved,
		'form_cookie'    => $cookie['form_cookie'],
		'checked_cookie' => $cookie['checked_cookie'],
	);
}

function duplicateKiller_metform_validate( $validation ) {
	if (
		! is_array( $validation ) ||
		empty( $validation['is_valid'] ) ||
		empty( $validation['form_data'] ) ||
		! is_array( $validation['form_data'] )
	) {
		return $validation;
	}
	
	$request_debug_id = uniqid( 'duplicateKiller_metform_validate_', true );
	$dk_enabled       = class_exists( 'duplicateKiller_Diagnostics' );

	$form_data = $validation['form_data'];
	$form_id   = isset( $form_data['id'] ) ? absint( $form_data['id'] ) : 0;
	$context   = duplicateKiller_metform_get_context( $form_id, $form_data );

	if ( empty( $context ) && $dk_enabled ) {
		duplicateKiller_Diagnostics::log(
			'metform',
			'form_config_not_found',
			array(
				'request_debug_id' => $request_debug_id,
				'form_id'          => $form_id,
			)
		);
	}
	if ( empty( $context ) ) {
		return $validation;
	}

	$config = $context['resolved']['form_config'];

	if ( $dk_enabled ) {
		duplicateKiller_Diagnostics::log(
			'metform',
			'process_start',
			array(
				'request_debug_id' => $request_debug_id,
				'form_id'          => $form_id,
				'form_name'        => $context['form_name'],
				'field_keys'       => array_keys( $context['data'] ),
				'enabled_fields'   => $context['resolved']['enabled_fields'],
				'message_mode'    => isset( $config['error_message_type'] )
					? sanitize_key( (string) $config['error_message_type'] )
					: 'classic',
				'ip_enabled'      => ! empty( $config['user_ip'] ) ? 1 : 0,
				'cookie_enabled'  => ! empty( $config['cookie_option'] ) ? 1 : 0,
				'cross_enabled'   => ! empty( $config['cross_form_option'] ) ? 1 : 0,
			)
		);
	}
	$ip_result = DuplicateKiller_IP_Limit_Checker::check(
		'MetForm',
		$context['form_name'],
		$config,
		array(
			'form_id'          => $form_id,
			'request_debug_id' => $request_debug_id,
		)
	);

	if ( ! empty( $ip_result['blocked'] ) ) {
		$validation['is_valid'] = false;
		$validation['message']  = sanitize_text_field( (string) $ip_result['message'] );

		return $validation;
	}

	$field_result = DuplicateKiller_FieldDuplicate_Checker::check(
		'MetForm',
		$context['form_name'],
		$context['resolved']['enabled_fields'],
		$context['data'],
		$context['form_cookie'],
		$context['checked_cookie'],
		$config,
		array(
			'form_id'          => $form_id,
			'request_debug_id' => $request_debug_id,
		)
	);

	if ( ! empty( $field_result['blocked'] ) ) {
		$message = (string) $field_result['message'];

		$validation['is_valid'] = false;
		$validation['message']  = duplicateKiller_modern_popup_maybe_add_marker(
			$message,
			'metform',
			$config,
			$form_id,
			$context['form_name']
		);

		return $validation;
	}

	if ( ! empty( $config['cross_form_option'] ) ) {
		$cross_result = DuplicateKiller_CrossForm::checkAssocCrossFormDuplicate(
			'MetForm',
			$context['options'],
			$context['form_name'],
			$config,
			$context['data']
		);

		if ( $cross_result ) {
			$defaults = duplicateKiller_get_form_defaults();
			$message  = ! empty( $config['error_message'] )
				? (string) $config['error_message']
				: (string) $defaults['error_message'];

			$validation['is_valid'] = false;
			$validation['message']  = duplicateKiller_modern_popup_maybe_add_marker(
				$message,
				'metform',
				$config,
				$form_id,
				$context['form_name']
			);
			
			if ( $dk_enabled ) {
				duplicateKiller_Diagnostics::log(
					'metform',
					'cross_form_duplicate_found',
					array(
						'request_debug_id' => $request_debug_id,
						'form_id'          => $form_id,
					)
				);
			}
		}
	}
	
	if ( $dk_enabled && ! empty( $validation['is_valid'] ) ) {
		duplicateKiller_Diagnostics::log(
			'metform',
			'validation_passed',
			array(
				'request_debug_id' => $request_debug_id,
				'form_id'          => $form_id,
			)
		);
	}

	return $validation;
}

/**
 * Keep MetForm upload metadata only for the current PHP request.
 */
function duplicateKiller_metform_request_uploads( int $form_id, ?array $files = null ): array {
	static $uploads = array();

	if ( $form_id <= 0 ) {
		return array();
	}

	if ( null !== $files ) {
		$uploads[ $form_id ] = $files;
		return array();
	}

	$result = isset( $uploads[ $form_id ] ) ? $uploads[ $form_id ] : array();
	unset( $uploads[ $form_id ] );

	return $result;
}

/**
 * Capture only MetForm's completed upload references, never temporary files.
 */
function duplicateKiller_metform_capture_uploads( $form_id, $form_data, $settings, $attributes ): void {
	$form_id  = absint( $form_id );
	$uploaded = is_array( $attributes ) && isset( $attributes['file_upload_info'] )
		&& is_array( $attributes['file_upload_info'] )
			? $attributes['file_upload_info']
			: array();

	$files = array();

	foreach ( $uploaded as $field_key => $field_files ) {
		if ( ! is_string( $field_key ) || ! is_array( $field_files ) ) {
			continue;
		}

		$field_key = sanitize_key( $field_key );

		if ( '' === $field_key ) {
			continue;
		}

		foreach ( $field_files as $file ) {
			if (
				! is_array( $file ) ||
				! isset( $file['url'] ) ||
				! is_string( $file['url'] )
			) {
				continue;
			}

			$url    = esc_url_raw( $file['url'] );
			$scheme = wp_parse_url( $url, PHP_URL_SCHEME );

			if ( '' === $url || ! in_array( strtolower( (string) $scheme ), array( 'http', 'https' ), true ) ) {
				continue;
			}

			$name = isset( $file['name'] ) && is_string( $file['name'] )
				? sanitize_text_field( $file['name'] )
				: '';

			$entry = array();

			if ( '' !== $name ) {
				$entry['name'] = $name;
			}

			$entry['url'] = $url;
			$files[ $field_key ][] = $entry;
		}
	}

	duplicateKiller_metform_request_uploads( $form_id, $files );
	if ( class_exists( 'duplicateKiller_Diagnostics' ) ) {
		$integration_keys = array(
			'mf_mailster',
			'mf_mail_chimp',
			'mf_active_campaign',
			'mf_get_response',
			'mf_zapier',
			'mf_slack',
			'mf_convert_kit',
			'mf_mail_aweber',
			'mf_mail_poet',
			'mf_mailerlite',
			'mf_fluent',
			'mf_google_sheet',
			'mf_dropbox',
			'mf_google_drive',
			'mf_rest_api',
			'enable_user_notification',
			'enable_admin_notification',
		);

		$configured_integrations = array();

		foreach ( $integration_keys as $key ) {
			$configured_integrations[ $key ] = is_array( $settings ) &&
				isset( $settings[ $key ] ) &&
				is_scalar( $settings[ $key ] ) &&
				'1' === (string) $settings[ $key ]
					? 1
					: 0;
		}

		$file_count = 0;

		foreach ( $files as $field_files ) {
			$file_count += count( $field_files );
		}

		duplicateKiller_Diagnostics::log(
			'metform',
			'after_store_hook',
			array(
				'form_id'                 => $form_id,
				'metform_store_configured' => is_array( $settings ) &&
					! empty( $settings['store_entries'] ) ? 1 : 0,
				'upload_field_count'      => count( $files ),
				'upload_file_count'       => $file_count,
				'configured_integrations' => $configured_integrations,
			)
		);
	}
}

function duplicateKiller_metform_save_success( $response, $handler, $request ) {
	if (
		is_wp_error( $response ) ||
		! $request instanceof WP_REST_Request ||
		'POST' !== $request->get_method() ||
		! preg_match(
			'~^/metform/v1/entries/insert/([0-9]+)/?$~',
			$request->get_route(),
			$matches
		)
	) {
		return $response;
	}

	$request_debug_id = uniqid( 'duplicateKiller_metform_save_', true );
	$dk_enabled       = class_exists( 'duplicateKiller_Diagnostics' );
	
	$rest_response = rest_ensure_response( $response );
	$body          = $rest_response->get_data();

	if ( is_object( $body ) ) {
		$body = (array) $body;
	}
	
	$response_data = is_array( $body ) && isset( $body['data'] ) && is_array( $body['data'] )
		? $body['data']
		: array();

	$submitted_data = isset( $response_data['form_data'] ) && is_array( $response_data['form_data'] )
		? $response_data['form_data']
		: $request->get_params();
		
	if ( $dk_enabled ) {
		duplicateKiller_Diagnostics::log(
			'metform',
			'rest_response',
			array(
				'request_debug_id' => $request_debug_id,
				'form_id'          => absint( $matches[1] ),
				'status'           => is_array( $body ) && isset( $body['status'] )
					? sanitize_text_field( (string) $body['status'] )
					: '',
				'store_entries'    => is_array( $body ) && isset( $body['store_entries'] )
					? sanitize_text_field( (string) $body['store_entries'] )
					: '',
				'http_status'       => $rest_response->get_status(),
				'failure_type'      => is_array( $body ) &&
					isset( $body['failure_type'] ) &&
					is_scalar( $body['failure_type'] )
						? sanitize_key( (string) $body['failure_type'] )
						: '',
				'response_entry_id' => isset( $response_data['entry_id'] ) &&
					is_scalar( $response_data['entry_id'] )
						? absint( $response_data['entry_id'] )
						: 0,
				'payment_first'     => in_array(
					$submitted_data['mf_payment_first'] ?? null,
					array( 1, '1' ),
					true
				) ? 1 : 0,
				'payment_completed' => in_array(
					$submitted_data['mf_payment_completed'] ?? null,
					array( 1, '1' ),
					true
				) ? 1 : 0,
				'pro_loaded'        => class_exists( 'MetForm_Pro\Base\Package' ) ? 1 : 0,
			)
		);
	}

	if (
		! is_array( $body ) ||
		! isset( $body['status'] ) ||
		'1' !== (string) $body['status']
	) {
		return $response;
	}

	$form_id   = absint( $matches[1] );
	$form_data = isset( $body['data']['form_data'] ) && is_array( $body['data']['form_data'] )
		? $body['data']['form_data']
		: $request->get_params();

	if (
		! is_array( $form_data ) ||
		! isset( $form_data['id'] ) ||
		$form_id !== absint( $form_data['id'] )
	) {
		return $response;
	}

	$context = duplicateKiller_metform_get_context( $form_id, $form_data );

	if ( empty( $context ) ) {
		return $response;
	}

	$config = $context['resolved']['form_config'];

	$should_save = (
		! empty( $context['resolved']['has_fields'] ) ||
		! empty( $config['user_ip'] ) ||
		! empty( $config['cookie_option'] ) ||
		! empty( $config['cross_form_option'] )
	);

	if ( ! $should_save ) {
		return $response;
	}

	$uploaded_files = duplicateKiller_metform_request_uploads( $form_id );

	foreach ( $uploaded_files as $field_key => $files ) {
		if ( ! empty( $files ) ) {
			$context['data'][ $field_key ] = $files;
		}
	}

	DuplicateKiller_Submission_Storage::save(
		'MetForm',
		$context['form_name'],
		$context['data'],
		$context['form_cookie'],
		true,
		! empty( $config['user_ip'] ) && '1' === (string) $config['user_ip'],
		array(),
		array(),
		array(
			'form_id'          => $form_id,
			'request_debug_id' => $request_debug_id,
		)
	);

	return $response;
}

function duplicateKiller_metform_get_forms() {
	if (
		! class_exists( '\MetForm\Core\Entries\Map_El' ) ||
		! class_exists( '\MetForm\Widgets\Manifest' )
	) {
		return array();
	}

	$posts = get_posts(
		array(
			'post_type'      => 'metform-form',
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'DESC',
		)
	);

	$allowed_types = array(
		'mf-text'      => 'text',
		'mf-email'     => 'email',
		'mf-number'    => 'number',
		'mf-telephone' => 'tel',
		'mf-date'      => 'date',
		'mf-time'      => 'time',
		'mf-textarea'  => 'textarea',
		'mf-url'       => 'url',
	);

	$forms = array();

	foreach ( $posts as $post ) {
		$definition = get_post_meta( $post->ID, '_elementor_data', true );
		$elements   = is_string( $definition ) ? json_decode( $definition ) : $definition;

		if ( ! is_array( $elements ) ) {
			continue;
		}

		$mapped = \MetForm\Core\Entries\Map_El::data(
			$elements,
			\MetForm\Widgets\Manifest::instance()->get_input_widgets()
		)->get_el();

		$fields = array();

		foreach ( (array) $mapped as $field_name => $settings ) {
			if ( ! is_object( $settings ) ) {
				continue;
			}

			$type = isset( $settings->widgetType ) ? (string) $settings->widgetType : '';

			if ( ! isset( $allowed_types[ $type ] ) ) {
				continue;
			}

			$field_name = sanitize_key( (string) $field_name );

			if ( '' === $field_name ) {
				continue;
			}

			$label = isset( $settings->mf_input_label )
				? sanitize_text_field( (string) $settings->mf_input_label )
				: '';

			$fields[] = array(
				'id'    => $field_name,
				'label' => '' !== $label ? $label : $field_name,
				'type'  => $allowed_types[ $type ],
			);
		}

		$title    = sanitize_text_field( (string) $post->post_title );
		$form_key = ( '' !== $title ? $title : 'MetForm' ) . '.' . $post->ID;

		$forms[ $form_key ] = array(
			'form_id'   => $post->ID,
			'form_name' => $form_key,
			'fields'    => $fields,
		);
	}

	return $forms;
}

function duplicateKiller_metform_validate_input( $input ) {
	return duplicateKiller_sanitize_forms_option(
		$input,
		'MetForm_page',
		'MetForm_page',
		array(),
		'MetForm'
	);
}

function duplicateKiller_metform_description() {
	if ( post_type_exists( 'metform-form' ) ) {
		echo '<h3 style="color:green"><strong>' .
			esc_html__( 'MetForm plugin is activated!', 'duplicate-killer' ) .
			'</strong></h3>';
		echo '<p class="description">' .
			esc_html__( 'MetForm integration is in beta. For MetForm Pro, verify form submissions, connected integrations, and payment flows on a staging site before going live.', 'duplicate-killer' ) .
			'</p>';
		return;
	}

	echo '<h3 style="color:red"><strong>' .
		esc_html__( 'MetForm plugin is not activated! Please activate it in order to continue.', 'duplicate-killer' ) .
		'</strong></h3>';
}

function duplicateKiller_metform_settings_callback( $args ) {
	$options = get_option( $args[0] );
}

function duplicateKiller_metform_select_form_tag_callback( $args ) {
	duplicateKiller_render_forms_overview(
		array(
			'option_name'   => 'MetForm_page',
			'db_plugin_key' => 'MetForm',
			'plugin_label'  => 'MetForm',
			'forms'         => duplicateKiller_metform_get_forms(),
		)
	);
}