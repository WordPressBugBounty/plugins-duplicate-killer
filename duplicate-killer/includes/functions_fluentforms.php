<?php
defined( 'ABSPATH' ) or die( 'You shall not pass!' );

add_filter( 'fluentform/validation_errors', 'duplicateKiller_fluentforms_validation_errors', 10, 4 );
add_action( 'fluentform/submission_inserted', 'duplicateKiller_fluentforms_submission_inserted', 20, 3 );

function duplicateKiller_fluentforms_validation_errors( $errors, $form_data, $form, $fields ) {
	$context = duplicateKiller_fluentforms_prepare_context( $form_data, $form );

	if ( empty( $context ) ) {
		return $errors;
	}

	$form_config = $context['resolved']['form_config'];
	if ( class_exists( 'duplicateKiller_Diagnostics' ) ) {
		duplicateKiller_Diagnostics::log(
			'fluentforms',
			'validation_start',
			array(
				'form_id'        => $context['form_id'],
				'form_name'      => $context['form_name'],
				'field_keys'     => array_keys( $context['data'] ),
				'enabled_fields' => $context['resolved']['enabled_fields'],
			)
		);
	}

	if ( class_exists( 'DuplicateKiller_IP_Limit_Checker' ) ) {
		$ip_result = DuplicateKiller_IP_Limit_Checker::check(
			'FluentForms',
			$context['form_name'],
			$form_config,
			array( 'form_id' => $context['form_id'] )
		);

		if ( ! empty( $ip_result['blocked'] ) ) {
			if ( class_exists( 'duplicateKiller_Diagnostics' ) ) {
				duplicateKiller_Diagnostics::log(
					'fluentforms',
					'ip_limit_blocked',
					array(
						'form_id'   => $context['form_id'],
						'form_name' => $context['form_name'],
						'message'   => $ip_result['message'],
					)
				);
			}

			return duplicateKiller_fluentforms_add_validation_error( $errors, '_duplicatekiller_ip', $ip_result['message'] );
		}
	}

	if ( class_exists( 'DuplicateKiller_FieldDuplicate_Checker' ) ) {
		$field_result = DuplicateKiller_FieldDuplicate_Checker::check(
			'FluentForms',
			$context['form_name'],
			$context['resolved']['enabled_fields'],
			$context['data'],
			$context['form_cookie'],
			$context['checked_cookie'],
			$form_config,
			array( 'form_id' => $context['form_id'] )
		);

		if ( ! empty( $field_result['blocked'] ) ) {
			$field_key = ! empty( $field_result['field_key'] ) ? $field_result['field_key'] : '_duplicatekiller';

			if ( class_exists( 'duplicateKiller_Diagnostics' ) ) {
				duplicateKiller_Diagnostics::log(
					'fluentforms',
					'duplicate_found',
					array(
						'form_id'   => $context['form_id'],
						'form_name' => $context['form_name'],
						'field_key' => $field_key,
						'message'   => $field_result['message'],
					)
				);
			}

			return duplicateKiller_fluentforms_add_validation_error( $errors, $field_key, $field_result['message'] );
		}
	}

	if ( ! empty( $form_config['cross_form_option'] ) && class_exists( 'DuplicateKiller_CrossForm' ) ) {
		$options = get_option( 'FluentForms_page', array() );
		$options = is_array( $options ) ? $options : array();

		$cross_result = DuplicateKiller_CrossForm::checkAssocCrossFormDuplicate(
			'FluentForms',
			$options,
			$context['form_name'],
			$form_config,
			$context['data']
		);

		if ( $cross_result ) {
			$message = ! empty( $form_config['error_message'] )
				? (string) $form_config['error_message']
				: __( 'Please check all fields! These values have been submitted already!', 'duplicate-killer' );

			$field_key = ! empty( $cross_result['current_field_id'] )
				? (string) $cross_result['current_field_id']
				: '_duplicatekiller_cross_form';

			$field_key = duplicateKiller_fluentforms_get_validation_field_key( $field_key, $form_data );
			if ( class_exists( 'duplicateKiller_Diagnostics' ) ) {
				duplicateKiller_Diagnostics::log(
					'fluentforms',
					'cross_form_duplicate_found',
					array(
						'form_id'      => $context['form_id'],
						'form_name'    => $context['form_name'],
						'field_key'    => $field_key,
						'cross_result' => array(
							'current_field_id' => isset( $cross_result['current_field_id'] ) ? $cross_result['current_field_id'] : '',
							'canonical_key'    => isset( $cross_result['canonical_key'] ) ? $cross_result['canonical_key'] : '',
							'matched_form'     => isset( $cross_result['matched_form'] ) ? $cross_result['matched_form'] : '',
							'matched_field_id' => isset( $cross_result['matched_field_id'] ) ? $cross_result['matched_field_id'] : '',
						),
					)
				);
			}
			return duplicateKiller_fluentforms_add_validation_error( $errors, $field_key, $message );
		}
	}

	return $errors;
}

function duplicateKiller_fluentforms_submission_inserted( $entry_id, $form_data, $form ) {
	$context = duplicateKiller_fluentforms_prepare_context( $form_data, $form );

	if ( empty( $context ) ) {
		return;
	}

	$form_config = $context['resolved']['form_config'];

	$should_save = (
		! empty( $context['resolved']['has_fields'] ) ||
		! empty( $form_config['user_ip'] ) ||
		! empty( $form_config['cookie_option'] ) ||
		! empty( $form_config['cross_form_option'] )
	);
	
	if ( class_exists( 'duplicateKiller_Diagnostics' ) ) {
		duplicateKiller_Diagnostics::log(
			'fluentforms',
			'submission_inserted',
			array(
				'form_id'     => $context['form_id'],
				'form_name'   => $context['form_name'],
				'entry_id'    => absint( $entry_id ),
				'should_save' => $should_save ? 'yes' : 'no',
			)
		);
	}
	if ( ! $should_save || ! class_exists( 'DuplicateKiller_Submission_Storage' ) ) {
		return;
	}

	DuplicateKiller_Submission_Storage::save(
		'FluentForms',
		$context['form_name'],
		$context['data'],
		$context['form_cookie'],
		true,
		! empty( $form_config['user_ip'] ) && (string) $form_config['user_ip'] === '1',
		array(),
		array(),
		array(
			'form_id'  => $context['form_id'],
			'entry_id' => absint( $entry_id ),
		)
	);
}

function duplicateKiller_fluentforms_prepare_context( $form_data, $form ): array {
	if ( ! is_array( $form_data ) ) {
		return array();
	}

	$form_id    = duplicateKiller_fluentforms_get_form_id( $form );
	$form_title = duplicateKiller_fluentforms_get_form_title( $form );
	$form_name  = duplicateKiller_fluentforms_get_option_key( $form_id, $form_title );

	if ( $form_id <= 0 || $form_name === '' ) {
		return array();
	}

	$data = duplicateKiller_fluentforms_sanitize_submission_data( $form_data );

	if ( empty( $data ) ) {
		return array();
	}

	$options = get_option( 'FluentForms_page', array() );
	$options = is_array( $options ) ? $options : array();

	if ( empty( $options ) ) {
		return array();
	}

	$current_form = array(
		'form_id'    => $form_id,
		'form_name'  => $form_name,
		'field_keys' => array_keys( $data ),
	);

	$resolved = DuplicateKiller_Form_Config_Resolver::resolve( $options, $current_form );

	if ( empty( $resolved ) || empty( $resolved['form_config'] ) ) {
		return array();
	}

	$cookie = duplicateKiller_get_form_cookie_simple(
		$options,
		$form_name,
		'dk_form_cookie_fluentforms_'
	);

	return array(
		'form_id'        => $form_id,
		'form_name'      => $form_name,
		'data'           => $data,
		'resolved'       => $resolved,
		'form_cookie'    => $cookie['form_cookie'],
		'checked_cookie' => $cookie['checked_cookie'],
	);
}

function duplicateKiller_fluentforms_add_validation_error( $errors, string $field_key, string $message ) {
	if ( ! is_array( $errors ) ) {
		$errors = array();
	}

	$field_key = sanitize_key( $field_key );

	if ( $field_key === '' ) {
		$field_key = '_duplicatekiller';
	}

	$errors[ $field_key ] = array( sanitize_text_field( $message ) );

	return $errors;
}
function duplicateKiller_fluentforms_get_validation_field_key( string $field_key, array $form_data ): string {
	if ( $field_key === '' ) {
		return '_duplicatekiller';
	}

	if ( array_key_exists( $field_key, $form_data ) ) {
		return $field_key;
	}

	foreach ( $form_data as $parent_key => $value ) {
		if ( ! is_array( $value ) ) {
			continue;
		}

		$parent_key = sanitize_key( (string) $parent_key );

		if ( $parent_key === '' ) {
			continue;
		}

		$prefix = $parent_key . '_';

		if ( strpos( $field_key, $prefix ) !== 0 ) {
			continue;
		}

		$child_key = substr( $field_key, strlen( $prefix ) );

		if ( $child_key !== '' && array_key_exists( $child_key, $value ) ) {
			return $parent_key . '[' . $child_key . ']';
		}
	}

	return $field_key;
}
function duplicateKiller_fluentforms_get_form_id( $form ): int {
	return is_object( $form ) && isset( $form->id ) ? absint( $form->id ) : 0;
}

function duplicateKiller_fluentforms_get_form_title( $form ): string {
	return is_object( $form ) && isset( $form->title )
		? sanitize_text_field( (string) $form->title )
		: '';
}

function duplicateKiller_fluentforms_get_option_key( int $form_id, string $title ): string {
	$title = trim( $title );

	if ( $title === '' ) {
		$title = 'Fluent Form';
	}

	return $title . '.' . $form_id;
}

function duplicateKiller_fluentforms_sanitize_submission_data( array $form_data ): array {
	$out = array();

	foreach ( $form_data as $key => $value ) {
		$key = (string) $key;

		if (
			$key === '_wp_http_referer' ||
			$key === '__fluent_form_embded_post_id' ||
			0 === strpos( $key, '_fluentform_' )
		) {
			continue;
		}

		$key = sanitize_key( $key );

		if ( $key === '' ) {
			continue;
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $child_key => $child_value ) {
				$child_key = sanitize_key( (string) $child_key );

				if ( $child_key === '' ) {
					continue;
				}

				$out[ $key . '_' . $child_key ] = is_scalar( $child_value )
					? sanitize_text_field( (string) $child_value )
					: '';
			}

			continue;
		}

		$out[ $key ] = sanitize_text_field( (string) $value );
	}

	return $out;
}

function duplicateKiller_fluentforms_is_ready(): bool {
	return defined( 'FLUENTFORM' ) || defined( 'FLUENTFORM_VERSION' ) || function_exists( 'wpFluent' );
}

function duplicateKiller_fluentforms_get_forms(): array {
	global $wpdb;

	$table = $wpdb->prefix . 'fluentform_forms';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$table_exists = $wpdb->get_var(
		$wpdb->prepare(
			'SHOW TABLES LIKE %s',
			$table
		)
	);

	if ( $table_exists !== $table ) {
		return array();
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$rows = $wpdb->get_results(
		"SELECT id, title, form_fields FROM " . esc_sql( $table ) . ' ORDER BY id DESC',
		ARRAY_A
	);

	$forms = array();

	foreach ( (array) $rows as $row ) {
		$form_id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
		$title   = isset( $row['title'] ) ? sanitize_text_field( (string) $row['title'] ) : '';

		if ( $form_id <= 0 ) {
			continue;
		}

		$form_key = duplicateKiller_fluentforms_get_option_key( $form_id, $title );
		$decoded  = json_decode( (string) $row['form_fields'], true );

		$forms[ $form_key ] = array(
			'form_id'   => $form_id,
			'form_name' => $form_key,
			'fields'    => duplicateKiller_fluentforms_extract_fields(
				is_array( $decoded )
					? ( isset( $decoded['fields'] ) && is_array( $decoded['fields'] ) ? $decoded['fields'] : $decoded )
					: array()
			),
		);
	}

	return $forms;
}
function duplicateKiller_fluentforms_normalize_field_type( string $element ): string {
	$element = strtolower( trim( $element ) );

	$map = array(
		'input_email'    => 'email',
		'input_text'     => 'text',
		'input_number'   => 'number',
		'input_url'      => 'url',
		'input_date'     => 'date',
		'input_radio'    => 'radio',
		'input_checkbox' => 'checkbox',
		'select'         => 'select',
		'select_country' => 'select',
		'textarea'       => 'textarea',
	);

	return isset( $map[ $element ] ) ? $map[ $element ] : $element;
}
function duplicateKiller_fluentforms_extract_fields( array $nodes ): array {
	$fields = array();

	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) ) {
			continue;
		}

		$element = isset( $node['element'] ) ? (string) $node['element'] : '';

		if ( $element === 'input_name' ) {
			$fields = array_merge(
				$fields,
				duplicateKiller_fluentforms_extract_name_fields( $node )
			);
			continue;
		}

		$attributes = ! empty( $node['attributes'] ) && is_array( $node['attributes'] ) ? $node['attributes'] : array();
		$settings   = ! empty( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array();

		$name = isset( $attributes['name'] ) ? sanitize_key( (string) $attributes['name'] ) : '';

		if ( $name !== '' ) {
			$label = $settings['label'] ?? $attributes['placeholder'] ?? $name;

			$fields[ $name ] = array(
				'id'    => $name,
				'label' => sanitize_text_field( (string) $label ),
				'type'  => duplicateKiller_fluentforms_normalize_field_type( $element ),
			);
		}

		foreach ( array( 'fields', 'columns', 'children' ) as $child_key ) {
			if ( ! empty( $node[ $child_key ] ) && is_array( $node[ $child_key ] ) ) {
				$fields = array_merge(
					$fields,
					duplicateKiller_fluentforms_extract_fields( $node[ $child_key ] )
				);
			}
		}
	}

	return array_values( $fields );
}
function duplicateKiller_fluentforms_extract_name_fields( array $node ): array {
	$out = array();

	$parent_attributes = ! empty( $node['attributes'] ) && is_array( $node['attributes'] ) ? $node['attributes'] : array();
	$parent_name       = isset( $parent_attributes['name'] ) ? sanitize_key( (string) $parent_attributes['name'] ) : '';

	if ( $parent_name === '' ) {
		return array();
	}

	$fields = ! empty( $node['fields'] ) && is_array( $node['fields'] )
		? $node['fields']
		: array();

	foreach ( $fields as $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$attributes = ! empty( $field['attributes'] ) && is_array( $field['attributes'] ) ? $field['attributes'] : array();
		$settings   = ! empty( $field['settings'] ) && is_array( $field['settings'] ) ? $field['settings'] : array();

		$name = isset( $attributes['name'] ) ? sanitize_key( (string) $attributes['name'] ) : '';

		if ( $name === '' ) {
			continue;
		}

		$visible = true;

		if ( isset( $settings['visible'] ) && ! $settings['visible'] ) {
			$visible = false;
		}

		if ( isset( $settings['admin_field_label'] ) && $settings['admin_field_label'] === false ) {
			$visible = false;
		}

		if ( isset( $field['visible'] ) && ! $field['visible'] ) {
			$visible = false;
		}

		if ( ! $visible ) {
			continue;
		}

		$label = $settings['label'] ?? $attributes['placeholder'] ?? $name;
		$field_key = $parent_name . '_' . $name;

		$type = isset( $field['element'] ) ? (string) $field['element'] : 'input_text';
		$out[ $field_key ] = array(
			'id'    => $field_key,
			'label' => sanitize_text_field( (string) $label ),
			'type'  => duplicateKiller_fluentforms_normalize_field_type( $type ),
		);
	}

	return array_values( $out );
}
function duplicateKiller_fluentforms_validate_input( $input ) {
	return duplicateKiller_sanitize_forms_option(
		$input,
		'FluentForms_page',
		'FluentForms_page',
		array(),
		'FluentForms'
	);
}

function duplicateKiller_fluentforms_description() {
	if ( duplicateKiller_fluentforms_is_ready() ) {
		echo '<h3 style="color:green"><strong>' . esc_html__( 'Fluent Forms plugin is activated!', 'duplicate-killer' ) . '</strong></h3>';
		return;
	}

	echo '<h3 style="color:red"><strong>' . esc_html__( 'Fluent Forms plugin is not activated! Please activate it in order to continue.', 'duplicate-killer' ) . '</strong></h3>';
	exit();
}

function duplicateKiller_fluentforms_settings_callback( $args ) {
	$options = get_option( $args[0] );
}

function duplicateKiller_fluentforms_select_form_tag_callback( $args ) {
	duplicateKiller_render_forms_overview(
		array(
			'option_name'   => 'FluentForms_page',
			'db_plugin_key' => 'FluentForms',
			'plugin_label'  => 'Fluent Forms',
			'forms'         => duplicateKiller_fluentforms_get_forms(),
		)
	);
}
