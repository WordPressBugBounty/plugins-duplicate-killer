<?php
defined( 'ABSPATH' ) || exit;

add_filter( 'srfm_form_submit_data', 'duplicateKiller_sureforms_before_send_email', 10, 1 );
add_filter( 'duplicateKiller_cookie_providers', 'duplicateKiller_sureforms_cookie_provider' );

// Check the mapped payment email before SureForms creates a Stripe intent.
add_action( 'wp_ajax_srfm_create_payment_intent', 'duplicateKiller_sureforms_check_payment_email', 5 );
add_action( 'wp_ajax_nopriv_srfm_create_payment_intent', 'duplicateKiller_sureforms_check_payment_email', 5 );
add_action( 'wp_ajax_srfm_create_subscription_intent', 'duplicateKiller_sureforms_check_payment_email', 5 );
add_action( 'wp_ajax_nopriv_srfm_create_subscription_intent', 'duplicateKiller_sureforms_check_payment_email', 5 );

/**
 * Read supported field definitions without rendering the form or updating meta.
 */
function duplicateKiller_sureforms_get_schema( int $form_id ): array {
	static $cache = array();

	if ( isset( $cache[ $form_id ] ) ) {
		return $cache[ $form_id ];
	}

	$schema = array(
		'supported'           => false,
		'fields'              => array(),
		'email_slugs'         => array(),
		'payment_email_slugs' => array(),
	);

	$post = get_post( $form_id );

	if (
		! $post instanceof WP_Post
		|| 'sureforms_form' !== $post->post_type
		|| ! function_exists( 'parse_blocks' )
	) {
		$cache[ $form_id ] = $schema;
		return $schema;
	}

	$types = array(
		'srfm/input'    => 'text',
		'srfm/email'    => 'email',
		'srfm/url'      => 'url',
		'srfm/textarea' => 'textarea',
		'srfm/number'   => 'number',
	);

	$stack = array(
		array(
			'blocks' => parse_blocks( $post->post_content ),
			'depth'  => 0,
			'refs'   => array(),
		),
	);

	$schema['supported'] = true;

	// Traverse nested blocks and synced blocks with a bounded depth.
	while ( ! empty( $stack ) ) {
		$item = array_pop( $stack );

		if ( $item['depth'] > 32 ) {
			$schema['supported'] = false;
			break;
		}

		foreach ( $item['blocks'] as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$name  = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
			$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] )
				? $block['attrs'] : array();

			if ( isset( $types[ $name ] ) && isset( $attrs['block_id'] ) ) {
				$block_id = is_string( $attrs['block_id'] ) ? $attrs['block_id'] : '';

				if ( preg_match( '/^[a-zA-Z0-9]+$/D', $block_id ) ) {
					$field_id = str_replace( 'srfm/', 'srfm-', $name ) . '-' . $block_id;
					$label    = isset( $attrs['label'] ) && is_scalar( $attrs['label'] )
						? sanitize_text_field( (string) $attrs['label'] ) : '';

					$schema['fields'][ $field_id ] = array(
						'id'    => $field_id,
						'label' => '' !== $label ? $label : $field_id,
						'type'  => $types[ $name ],
					);

					// Payment blocks identify their customer email by the Email block slug.
					if (
						'srfm/email' === $name
						&& isset( $attrs['slug'] )
						&& is_string( $attrs['slug'] )
						&& '' !== $attrs['slug']
					) {
						$schema['email_slugs'][ $field_id ] = $attrs['slug'];
					}
				}
			}

			// Read payment mapping without adding payment fields to DK's supported fields.
			if (
				'srfm/payment' === $name
				&& isset( $attrs['block_id'] )
				&& is_string( $attrs['block_id'] )
				&& preg_match( '/^[a-zA-Z0-9]+$/D', $attrs['block_id'] )
			) {
				$payment_email_slug = isset( $attrs['customerEmailField'] )
					&& is_string( $attrs['customerEmailField'] )
					? $attrs['customerEmailField'] : '';

				// Match SureForms' legacy subscription email mapping.
				if (
					'' === $payment_email_slug
					&& isset( $attrs['subscriptionPlan'] )
					&& is_array( $attrs['subscriptionPlan'] )
					&& isset( $attrs['subscriptionPlan']['customer_email'] )
					&& is_string( $attrs['subscriptionPlan']['customer_email'] )
				) {
					$payment_email_slug = $attrs['subscriptionPlan']['customer_email'];
				}

				$payment_block_id = $attrs['block_id'];

				// An ambiguous payment block ID must not select an arbitrary email field.
				if ( array_key_exists( $payment_block_id, $schema['payment_email_slugs'] ) ) {
					$schema['payment_email_slugs'][ $payment_block_id ] = '';
				} else {
					$schema['payment_email_slugs'][ $payment_block_id ] = $payment_email_slug;
				}
			}

			if ( 'core/block' === $name && ! empty( $attrs['ref'] ) ) {
				$ref_id = absint( $attrs['ref'] );

				if ( in_array( $ref_id, $item['refs'], true ) ) {
					$schema['supported'] = false;
					break 2;
				}

				$ref = get_post( $ref_id );

				if ( $ref instanceof WP_Post && 'wp_block' === $ref->post_type ) {
					$refs   = $item['refs'];
					$refs[] = $ref_id;

					$stack[] = array(
						'blocks' => parse_blocks( $ref->post_content ),
						'depth'  => $item['depth'] + 1,
						'refs'   => $refs,
					);
				}
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$stack[] = array(
					'blocks' => $block['innerBlocks'],
					'depth'  => $item['depth'] + 1,
					'refs'   => $item['refs'],
				);
			}
		}
	}

	$cache[ $form_id ] = $schema;

	return $schema;
}

/**
 * Check only the configured payment Email field before Stripe intent creation.
 *
 * This does not validate other fields, check IP/cross-form rules, save a
 * submission, or confirm payment success. Native final processing is unchanged.
 */
function duplicateKiller_sureforms_check_payment_email() {
	$started_at = microtime( true );
	$debug      = array(
		'request_debug_id'       => uniqid( 'duplicateKiller_sureforms_payment_email_', true ),
		'hook'                   => current_filter(),
		'scope'                  => 'mapped_payment_email_only',
		'native_payment_outcome' => 'not_observed',
	);

	// Keep diagnostic payloads explicit. Never log the raw payment request.
	$log = static function ( $stage, array $details = array() ) use ( &$debug, $started_at ) {
		if ( ! class_exists( 'duplicateKiller_Diagnostics' ) ) {
			return;
		}

		duplicateKiller_Diagnostics::log(
			'sureforms',
			$stage,
			array_merge(
				$debug,
				$details,
				array(
					'elapsed_ms' => round( ( microtime( true ) - $started_at ) * 1000, 2 ),
				)
			)
		);
	};

	if ( ! is_callable( array( 'SRFM\\Inc\\Submit_Token', 'verify' ) ) ) {
		$log(
			'payment_email_check_skipped',
			array( 'reason' => 'native_token_verifier_unavailable' )
		);
		return;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- The native SureForms submission token is verified below.
	foreach ( array( 'form_id', 'block_id', 'customer_email', 'token' ) as $key ) {
		if ( ! isset( $_POST[ $key ] ) || ! is_string( $_POST[ $key ] ) ) {
			$log(
				'payment_email_check_skipped',
				array(
					'reason'        => 'missing_or_invalid_request_parameter',
					'parameter_key' => $key,
				)
			);
			return;
		}
	}

	$form_id_value = wp_unslash( $_POST['form_id'] );
	$block_id      = wp_unslash( $_POST['block_id'] );
	$email         = sanitize_email( wp_unslash( $_POST['customer_email'] ) );
	$token         = wp_unslash( $_POST['token'] );
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	$form_id_valid  = 1 === preg_match( '/^[0-9]+$/D', $form_id_value );
	$block_id_valid = 1 === preg_match( '/^[a-zA-Z0-9]+$/D', $block_id );
	$email_valid    = false !== is_email( $email );

	if ( ! $form_id_valid || ! $block_id_valid || ! $email_valid ) {
		$log(
			'payment_email_check_skipped',
			array(
				'reason'         => 'invalid_request_values',
				'form_id_valid'  => $form_id_valid,
				'block_id_valid' => $block_id_valid,
				'email_valid'    => $email_valid,
			)
		);
		return;
	}

	$form_id                   = absint( $form_id_value );
	$debug['form_id']           = $form_id;
	$debug['payment_block_id']  = $block_id;

	// Authenticate before reading the form schema or checking stored submissions.
	if (
		$form_id <= 0
		|| ! \SRFM\Inc\Submit_Token::verify( $token, $form_id )
	) {
		$log(
			'payment_email_check_skipped',
			array( 'reason' => 'invalid_form_id_or_submission_token' )
		);
		return;
	}

	$debug['submission_token_verified'] = true;
	$options = get_option( 'SureForms_page', array() );

	if ( ! is_array( $options ) || empty( $options ) ) {
		$log(
			'payment_email_check_skipped',
			array( 'reason' => 'dk_options_missing_or_invalid' )
		);
		return;
	}

	$schema = duplicateKiller_sureforms_get_schema( $form_id );

	if ( empty( $schema['supported'] ) ) {
		$log(
			'payment_email_check_skipped',
			array( 'reason' => 'form_schema_unavailable_or_unsupported' )
		);
		return;
	}

	if ( empty( $schema['payment_email_slugs'][ $block_id ] ) ) {
		$log(
			'payment_email_check_skipped',
			array(
				'reason' => 'payment_block_or_email_mapping_unavailable',
				'payment_block_found' => array_key_exists(
					$block_id,
					$schema['payment_email_slugs']
				),
			)
		);
		return;
	}

	// Resolve the email field from saved blocks, not from a client-supplied field ID.
	$email_fields = array_keys(
		$schema['email_slugs'],
		$schema['payment_email_slugs'][ $block_id ],
		true
	);

	// Missing or ambiguous mappings retain native processing and final DK checks.
	if ( 1 !== count( $email_fields ) ) {
		$log(
			'payment_email_check_skipped',
			array(
				'reason'               => 'email_mapping_missing_or_ambiguous',
				'email_matching_count' => count( $email_fields ),
			)
		);
		return;
	}

	$field_id = $email_fields[0];
	$data     = array(
		$field_id => sanitize_text_field( $email ),
	);

	$debug['email_field_id'] = $field_id;

	$current  = DuplicateKiller_Form_Normalizer::sureforms( $form_id, $data );
	$resolved = DuplicateKiller_Form_Config_Resolver::resolve_by_form_id(
		$options,
		$current
	);

	if ( false === $resolved ) {
		$log(
			'payment_email_check_skipped',
			array( 'reason' => 'dk_form_configuration_not_resolved' )
		);
		return;
	}

	// The mapped Email field must explicitly be enabled for duplicates in DK.
	if ( ! in_array( $field_id, $resolved['enabled_fields'], true ) ) {
		$log(
			'payment_email_check_skipped',
			array( 'reason' => 'mapped_email_not_enabled_in_dk' )
		);
		return;
	}

	$form_name = (string) $resolved['option_key'];
	$config    = wp_parse_args(
		$resolved['form_config'],
		duplicateKiller_get_form_defaults()
	);
	$cookie = duplicateKiller_get_form_cookie_simple(
		$options,
		$form_name,
		'dk_form_cookie_sureforms_'
	);

	$debug['form_name'] = $form_name;

	$check_started_at = microtime( true );

	// Reuse existing duplicate duration and cookie rules for this email only.
	$result = DuplicateKiller_FieldDuplicate_Checker::check(
		'SureForms',
		$form_name,
		array( $field_id ),
		$data,
		$cookie['form_cookie'],
		$cookie['checked_cookie'],
		$config,
		$debug
	);

	$blocked = ! empty( $result['blocked'] );

	$log(
		'payment_email_check',
		array(
			'blocked'                     => $blocked,
			'decision'                    => $blocked ? 'block_intent_request' : 'continue_native_handler',
			'checker_ms'                  => round( ( microtime( true ) - $check_started_at ) * 1000, 2 ),
			'checked_cookie'              => $cookie['checked_cookie'],
			'field_duplicate_block_days'   => absint( $config['field_duplicate_block_days'] ?? 0 ),
			'field_duplicate_block_hours'  => absint( $config['field_duplicate_block_hours'] ?? 0 ),
			'field_duplicate_block_minutes'=> absint( $config['field_duplicate_block_minutes'] ?? 0 ),
			'modern_mode_selected'         => 'modern' === ( $config['error_message_type'] ?? 'classic' ),
		)
	);

	if ( ! $blocked ) {
		return;
	}

	$message = sanitize_text_field( (string) $result['message'] );

	if ( function_exists( 'duplicateKiller_modern_popup_maybe_add_marker' ) ) {
		$message = duplicateKiller_modern_popup_maybe_add_marker(
			$message,
			'sureforms',
			$config,
			$form_id,
			$form_name
		);
	}

	// Stop this AJAX request before the native Stripe intent handler executes.
	wp_send_json_error(
		array(
			'message' => $message,
		)
	);
}

/**
 * Resolve the configured form and retain only supported, defined fields.
 */
function duplicateKiller_sureforms_get_context( array $form_data ): array {
	$form_id = isset( $form_data['form-id'] ) && is_scalar( $form_data['form-id'] )
		? absint( $form_data['form-id'] ) : 0;

	$options = get_option( 'SureForms_page', array() );

	if ( $form_id <= 0 || ! is_array( $options ) || empty( $options ) ) {
		return array();
	}

	$schema = duplicateKiller_sureforms_get_schema( $form_id );

	if ( ! $schema['supported'] ) {
		return array();
	}

	$data = array_intersect_key(
		DuplicateKiller_Form_Normalizer::sureforms_data( $form_data ),
		$schema['fields']
	);

	$current  = DuplicateKiller_Form_Normalizer::sureforms( $form_id, $data );
	$resolved = DuplicateKiller_Form_Config_Resolver::resolve_by_form_id(
		$options,
		$current
	);

	if ( false === $resolved ) {
		return array();
	}

	$form_name = (string) $resolved['option_key'];
	$config    = wp_parse_args(
		$resolved['form_config'],
		duplicateKiller_get_form_defaults()
	);
	$cookie = duplicateKiller_get_form_cookie_simple(
		$options,
		$form_name,
		'dk_form_cookie_sureforms_'
	);

	return array(
		'form_id'        => $form_id,
		'form_name'      => $form_name,
		'options'        => $options,
		'config'         => $config,
		'data'           => $data,
		'enabled_fields' => $resolved['enabled_fields'],
		'form_cookie'    => $cookie['form_cookie'],
		'checked_cookie' => $cookie['checked_cookie'],
		'ip_enabled'     => isset( $config['user_ip'] ) && '1' === (string) $config['user_ip'],
		'cross_enabled'  => isset( $config['cross_form_option'] ) && '1' === (string) $config['cross_form_option'],
	);
}

/**
 * Block duplicates after native field and anti-spam validation.
 */
function duplicateKiller_sureforms_before_send_email( $form_data ) {
	if ( ! is_array( $form_data ) || isset( $form_data['error'] ) ) {
		return $form_data;
	}

	$context = duplicateKiller_sureforms_get_context( $form_data );
	$debug   = array(
		'request_debug_id' => uniqid( 'duplicateKiller_sureforms_validate_', true ),
	);

	if ( empty( $context ) ) {
		if ( class_exists( 'duplicateKiller_Diagnostics' ) ) {
			duplicateKiller_Diagnostics::log( 'sureforms', 'validation_skipped', $debug );
		}
		return $form_data;
	}

	$debug['form_id']   = $context['form_id'];
	$debug['form_name'] = $context['form_name'];

	if ( class_exists( 'duplicateKiller_Diagnostics' ) ) {
		duplicateKiller_Diagnostics::log(
			'sureforms',
			'process_start',
			array_merge(
				$debug,
				array(
					'data'           => $context['data'],
					'form_config'    => $context['config'],
					'enabled_fields' => $context['enabled_fields'],
					'ip_enabled'     => $context['ip_enabled'],
					'cross_enabled'  => $context['cross_enabled'],
					'checked_cookie' => $context['checked_cookie'],
				)
			)
		);
	}

	// 1. Keep IP-limit errors in their existing classic format.
	$ip_result = DuplicateKiller_IP_Limit_Checker::check(
		'SureForms',
		$context['form_name'],
		$context['config'],
		$debug
	);

	if ( ! empty( $ip_result['blocked'] ) ) {
		if ( class_exists( 'duplicateKiller_Diagnostics' ) ) {
			duplicateKiller_Diagnostics::log(
				'sureforms',
				'validation_blocked',
				array_merge(
					$debug,
					array( 'reason' => 'ip_limit' )
				)
			);
		}

		$form_data['error'] = sanitize_text_field( (string) $ip_result['message'] );
		return $form_data;
	}

	// 2. Reuse the existing field checker and duration/cookie rules.
	$result = DuplicateKiller_FieldDuplicate_Checker::check(
		'SureForms',
		$context['form_name'],
		$context['enabled_fields'],
		$context['data'],
		$context['form_cookie'],
		$context['checked_cookie'],
		$context['config'],
		$debug
	);

	$message = ! empty( $result['blocked'] ) ? (string) $result['message'] : '';
	$block_reason = ! empty( $result['blocked'] ) ? 'field_duplicate' : '';

	// 3. Reuse associative cross-form matching within SureForms.
	if (
		'' === $message
		&& $context['cross_enabled']
		&& class_exists( 'DuplicateKiller_CrossForm' )
	) {
		$match = DuplicateKiller_CrossForm::checkAssocCrossFormDuplicate(
			'SureForms',
			$context['options'],
			$context['form_name'],
			$context['config'],
			$context['data']
		);

		if ( $match ) {
			$message      = (string) $context['config']['error_message'];
			$block_reason = 'cross_form_duplicate';

			if ( class_exists( 'duplicateKiller_Diagnostics' ) ) {
				duplicateKiller_Diagnostics::log(
					'sureforms',
					'cross_form_duplicate_found',
					array_merge( $debug, array( 'cross_match' => $match ) )
				);
			}
		}
	}
	
	if ( class_exists( 'duplicateKiller_Diagnostics' ) ) {
		duplicateKiller_Diagnostics::log(
			'sureforms',
			'validation_result',
			array_merge(
				$debug,
				array(
					'blocked'              => '' !== $message,
					'reason'               => $block_reason,
					'decision'             => '' !== $message ? 'block_final_submission' : 'continue_native_submission',
					'modern_mode_selected' => 'modern' === ( $context['config']['error_message_type'] ?? 'classic' ),
				)
			)
		);
	}

	// 4. Only DK duplicate errors receive a Modern Popup marker.
	if ( '' !== $message ) {
		$message = sanitize_text_field( $message );

		if ( function_exists( 'duplicateKiller_modern_popup_maybe_add_marker' ) ) {
			$message = duplicateKiller_modern_popup_maybe_add_marker(
				$message,
				'sureforms',
				$context['config'],
				$context['form_id'],
				$context['form_name']
			);
		}

		$form_data['error'] = $message;
		return $form_data;
	}

	// Preserve this submission's canonical data until native success is confirmed.
	add_action(
		'srfm_form_submit',
		function ( $response ) use ( $form_data, $context ) {
			if (
				! is_array( $response )
				|| ! isset( $response['form_id'] )
				|| ! is_scalar( $response['form_id'] )
				|| absint( $response['form_id'] ) !== $context['form_id']
			) {
				return;
			}

			// Observe only explicit native submission metadata, not payment details.
			if ( class_exists( 'duplicateKiller_Diagnostics' ) ) {
				duplicateKiller_Diagnostics::log(
					'sureforms',
					'native_submission_result',
					array(
						'form_id'                    => $context['form_id'],
						'form_name'                  => $context['form_name'],
						'native_success'             => isset( $response['success'] ) && true === $response['success'],
						'entry_id'                   => isset( $response['entry_id'] ) && is_scalar( $response['entry_id'] )
							? absint( $response['entry_id'] ) : 0,
						'payment_settlement_verified'=> false,
						'external_delivery_verified' => false,
					)
				);
			}
			duplicateKiller_sureforms_save_fields(
				$response,
				$form_data,
				array()
			);
		},
		20,
		1
	);

	return $form_data;
}

/**
 * Save DK data after a native successful submission, with or without an entry.
 */
function duplicateKiller_sureforms_save_fields( $response, $form_data, $submission_data ) {
	static $saved_entries = array();

	if (
		! is_array( $response )
		|| ! isset( $response['success'] )
		|| true !== $response['success']
		|| ! is_array( $form_data )
		|| isset( $form_data['error'] )
		|| ! isset( $response['form_id'] )
		|| ! is_scalar( $response['form_id'] )
	) {
		return $response;
	}

	$context = duplicateKiller_sureforms_get_context( $form_data );

	if (
		empty( $context )
		|| absint( $response['form_id'] ) !== $context['form_id']
	) {
		return $response;
	}

	$entry_id = isset( $response['entry_id'] ) && is_scalar( $response['entry_id'] )
		? absint( $response['entry_id'] ) : 0;

	// Entry-less submissions are identified only within the current request.
	$save_key = $context['form_id'] . ':' . $entry_id;

	if ( isset( $saved_entries[ $save_key ] ) ) {
		if ( class_exists( 'duplicateKiller_Diagnostics' ) ) {
			duplicateKiller_Diagnostics::log(
				'sureforms',
				'submission_save_skipped',
				array(
					'form_id'   => $context['form_id'],
					'form_name' => $context['form_name'],
					'entry_id'  => $entry_id,
					'reason'    => 'already_processed_in_this_request',
				)
			);
		}

		return $response;
	}

	$should_save = (
		$context['ip_enabled']
		|| ! empty( $context['enabled_fields'] )
		|| $context['cross_enabled']
	);

	if ( ! $should_save ) {
		if ( class_exists( 'duplicateKiller_Diagnostics' ) ) {
			duplicateKiller_Diagnostics::log(
				'sureforms',
				'submission_save_skipped',
				array(
					'form_id'   => $context['form_id'],
					'form_name' => $context['form_name'],
					'entry_id'  => $entry_id,
					'reason'    => 'no_enabled_storage_rule',
				)
			);
		}

		return $response;
	}

	$saved_entries[ $save_key ] = true;

	DuplicateKiller_Submission_Storage::save(
		'SureForms',
		$context['form_name'],
		$context['data'],
		$context['form_cookie'],
		true,
		$context['ip_enabled'],
		array(),
		array(),
		array(
			'request_debug_id' => uniqid( 'duplicateKiller_sureforms_save_', true ),
			'form_id'          => $context['form_id'],
			'form_name'        => $context['form_name'],
			'entry_id'         => $entry_id,
		)
	);

	return $response;
}

/**
 * Discover forms using the same structure consumed by the shared renderer.
 */
function duplicateKiller_sureforms_get_forms(): array {
	if ( ! post_type_exists( 'sureforms_form' ) ) {
		return array();
	}

	$posts = get_posts(
		array(
			'post_type'      => 'sureforms_form',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'DESC',
		)
	);

	$forms = array();

	foreach ( $posts as $post ) {
		$schema = duplicateKiller_sureforms_get_schema( (int) $post->ID );

		if ( ! $schema['supported'] ) {
			continue;
		}

		$title = sanitize_text_field( $post->post_title );
		$key   = ( '' !== $title ? $title : 'SureForms' ) . '.' . $post->ID;

		$fields = array_values( $schema['fields'] );
		$type_names = array(
			'text'     => __( 'Text field', 'duplicate-killer' ),
			'email'    => __( 'Email field', 'duplicate-killer' ),
			'url'      => __( 'URL field', 'duplicate-killer' ),
			'textarea' => __( 'Message field', 'duplicate-killer' ),
			'number'   => __( 'Number field', 'duplicate-killer' ),
		);
		$type_counts  = array();
		$label_counts = array();
		$label_seen   = array();

		// Prepare display labels without changing canonical field identifiers.
		foreach ( $fields as $index => $field ) {
			$label = trim( (string) $field['label'] );
			$type  = (string) $field['type'];

			if ( '' === $label || $label === $field['id'] ) {
				$type_counts[ $type ] = ( $type_counts[ $type ] ?? 0 ) + 1;
				$base = $type_names[ $type ] ?? __( 'Field', 'duplicate-killer' );
				$label = $base . ' ' . $type_counts[ $type ];
			}

			$fields[ $index ]['label'] = $label;
			$label_counts[ $label ] = ( $label_counts[ $label ] ?? 0 ) + 1;
		}

		// Number identical labels so administrators can distinguish the fields.
		foreach ( $fields as $index => $field ) {
			$label = $field['label'];

			if ( $label_counts[ $label ] > 1 ) {
				$label_seen[ $label ] = ( $label_seen[ $label ] ?? 0 ) + 1;
				$fields[ $index ]['label'] = $label . ' - ' . $label_seen[ $label ];
			}
		}

		$forms[ $key ] = array(
			'form_id'   => (int) $post->ID,
			'form_name' => $key,
			'fields'    => $fields,
		);
	}

	return $forms;
}

/**
 * Extend the existing cookie loader without changing other providers.
 */
function duplicateKiller_sureforms_cookie_provider( $providers ) {
	if ( ! is_array( $providers ) ) {
		return $providers;
	}

	$options = get_option( 'SureForms_page', array() );
	$days    = array();

	foreach ( is_array( $options ) ? $options : array() as $config ) {
		if (
			! is_array( $config )
			|| empty( $config['cookie_option'] )
			|| '1' !== (string) $config['cookie_option']
			|| empty( $config['form_id'] )
			|| ! is_scalar( $config['form_id'] )
		) {
			continue;
		}

		$form_id = absint( $config['form_id'] );
		$schema  = duplicateKiller_sureforms_get_schema( $form_id );

		if ( ! $schema['supported'] ) {
			continue;
		}

		$defaults = duplicateKiller_get_form_defaults();
		$duration = isset( $config['cookie_option_days'] )
			? absint( $config['cookie_option_days'] )
			: absint( $defaults['cookie_option_days'] );

		$days[ $form_id ] = max( 1, $duration );
	}

	$providers['sureforms'] = array(
		'enabled'       => ! empty( $days ),
		'per_form_days' => $days,
		'cookie_prefix'=> 'dk_form_cookie_sureforms_',
		'selector'      => 'form.srfm-form',
	);

	return $providers;
}

/**
 * Settings callbacks use the existing sanitizer and renderer.
 */
function duplicateKiller_sureforms_validate_input( $input ) {
	return duplicateKiller_sanitize_forms_option(
		$input,
		'SureForms_page',
		'SureForms_page',
		array(),
		'SureForms'
	);
}

function duplicateKiller_sureforms_description() {
	if ( ! post_type_exists( 'sureforms_form' ) ) {
		echo '<h3><strong>' .
			esc_html__( 'SureForms plugin is not activated! Please activate it in order to continue.', 'duplicate-killer' ) .
			'</strong></h3>';
		return;
	}

	echo '<h3><strong>' .
		esc_html__( 'SureForms plugin is activated!', 'duplicate-killer' ) .
		'</strong></h3>';

	echo '<p class="description">' .
		esc_html__( 'Supports Text, Email, URL, Textarea, and Number fields. Duplicate Killer stores its own records independently of SureForms entry storage settings.', 'duplicate-killer' ) .
		' <a href="' .
		esc_url( 'https://verselabwp.com/duplicate-killer-for-sureforms-setup-features-and-limitations/' ) .
		'" target="_blank" rel="noopener noreferrer">' .
		esc_html__( 'Read the SureForms integration guide and limitations.', 'duplicate-killer' ) .
		'</a></p>';
}

function duplicateKiller_sureforms_select_form_tag_callback( $args ) {
	if ( ! post_type_exists( 'sureforms_form' ) ) {
		return;
	}

	duplicateKiller_render_forms_overview(
		array(
			'option_name'   => 'SureForms_page',
			'db_plugin_key' => 'SureForms',
			'plugin_label'  => 'SureForms',
			'forms'         => duplicateKiller_sureforms_get_forms(),
		)
	);
}