<?php
defined( 'ABSPATH' ) or die( 'You shall not pass!' );

add_filter('breakdance_form_run_action_store_submission', 'duplicateKiller_breakdance_guard_action', 10, 5);
add_filter('breakdance_form_run_action_email',            'duplicateKiller_breakdance_guard_action', 10, 5);
function duplicateKiller_breakdance_guard_action( $canExecute, $action, $extra, $form, $settings ) {

	if ( is_wp_error( $canExecute ) ) {
		return $canExecute;
	}

	static $dk_state = array(
		'checked_once'     => false,
		'duplicate_found'  => false,
		'error_sent'       => false,
		'saved_once'       => false,
		'error_message'    => '',
		'request_debug_id' => '',
	);

	if ( '' === $dk_state['request_debug_id'] ) {
		$dk_state['request_debug_id'] = uniqid( 'duplicateKiller_breakdance_', true );
	}

	$request_debug_id = $dk_state['request_debug_id'];
	$dk_enabled       = class_exists( 'duplicateKiller_Diagnostics' );

	if ( $dk_state['checked_once'] ) {
		if ( $dk_state['duplicate_found'] ) {
			if ( ! $dk_state['error_sent'] ) {
				$dk_state['error_sent'] = true;

				return new \WP_Error(
					'dk_duplicate',
					$dk_state['error_message'] ?: __( 'Duplicate found.', 'duplicate-killer' )
				);
			}

			return false;
		}

		return $canExecute;
	}

	$dk_state['checked_once'] = true;

	$options = get_option( 'Breakdance_page' );
	$options = duplicateKiller_convert_option_architecture( $options, 'breakdance_' );
	if ( ! is_array( $options ) ) {
		$options = array();
	}

	$current_form = DuplicateKiller_Form_Normalizer::breakdance(
		is_array( $extra ) ? $extra : array(),
		is_array( $form ) ? $form : array(),
		is_array( $settings ) ? $settings : array()
	);

	if ( empty( $current_form['post_id'] ) || empty( $current_form['form_id'] ) ) {
		return $canExecute;
	}

	$resolved_form = DuplicateKiller_Form_Config_Resolver::resolve_by_form_id(
		$options,
		$current_form
	);

	if ( false === $resolved_form ) {
		if ( $dk_enabled ) {
			duplicateKiller_Diagnostics::log( 'breakdance', 'form_config_not_found', array(
				'request_debug_id' => $request_debug_id,
				'current_form'     => $current_form,
			) );
		}

		return $canExecute;
	}

	$form_name           = $resolved_form['form_name'];
	$form_config         = $resolved_form['form_config'];
	$enabled_fields      = $resolved_form['enabled_fields'];
	$form_names_to_check = ! empty( $current_form['form_names_to_check'] ) && is_array( $current_form['form_names_to_check'] )
		? $current_form['form_names_to_check']
		: array( $form_name );

	$data         = ! empty( $current_form['field_values'] ) && is_array( $current_form['field_values'] ) ? $current_form['field_values'] : array();
	$field_labels = ! empty( $current_form['field_labels'] ) && is_array( $current_form['field_labels'] ) ? $current_form['field_labels'] : array();

	$ip_limit_enabled    = ! empty( $form_config['user_ip'] ) && (string) $form_config['user_ip'] === '1';
	$field_check_enabled = ! empty( $enabled_fields );
	$cross_form_enabled  = ! empty( $form_config['cross_form_option'] ) && (string) $form_config['cross_form_option'] === '1';

	if ( ! $ip_limit_enabled && ! $field_check_enabled && ! $cross_form_enabled ) {
		if ( $dk_enabled ) {
			duplicateKiller_Diagnostics::log( 'breakdance', 'no_duplicate_killer_feature_enabled', array(
				'request_debug_id' => $request_debug_id,
				'form_name'        => $form_name,
				'form_config'      => $form_config,
			) );
		}

		return $canExecute;
	}

	if ( $dk_enabled ) {
		duplicateKiller_Diagnostics::log( 'breakdance', 'process_start', array(
			'request_debug_id'    => $request_debug_id,
			'action'              => $action,
			'form_name'           => $form_name,
			'current_form'        => $current_form,
			'form_config'         => $form_config,
			'enabled_fields'      => $enabled_fields,
			'ip_limit_enabled'    => $ip_limit_enabled ? 1 : 0,
			'field_check_enabled' => $field_check_enabled ? 1 : 0,
			'cross_form_enabled'  => $cross_form_enabled ? 1 : 0,
			'data'                => $data,
			'dk_state_before'     => $dk_state,
		) );
	}

	$cookie = duplicateKiller_get_form_cookie_simple(
		$options,
		$form_name,
		'dk_form_cookie_breakdance_'
	);

	$form_cookie    = $cookie['form_cookie'];
	$checked_cookie = $cookie['checked_cookie'];

	// 1. IP limit check.
	if ( $ip_limit_enabled ) {
		$ip_blocked = false;
		$ip_message = '';

		foreach ( $form_names_to_check as $check_form_name ) {
			$ip_limit_result = DuplicateKiller_IP_Limit_Checker::check(
				'breakdance',
				$check_form_name,
				$form_config,
				array(
					'request_debug_id' => $request_debug_id,
					'form_name'        => $check_form_name,
				)
			);

			if ( ! empty( $ip_limit_result['blocked'] ) ) {
				$ip_blocked = true;
				$ip_message = $ip_limit_result['message'];
				break;
			}
		}

		if ( $ip_blocked ) {
			$dk_state['duplicate_found'] = true;
			$dk_state['error_sent']      = true;
			$dk_state['error_message']   = $ip_message;

			return new \WP_Error( 'dk_duplicate_ip', $ip_message );
		}
	}

	// 2. Duplicate field check.
	if ( $field_check_enabled ) {
		foreach ( $enabled_fields as $field_id ) {
			$submitted_value = array_key_exists( $field_id, $data ) ? $data[ $field_id ] : '';

			if ( is_array( $submitted_value ) ) {
				$submitted_value = implode( ' ', array_map( 'strval', $submitted_value ) );
			} else {
				$submitted_value = (string) $submitted_value;
			}

			$submitted_value = sanitize_text_field( $submitted_value );

			if ( '' === $submitted_value ) {
				continue;
			}

			$exists = false;

			foreach ( $form_names_to_check as $check_form_name ) {
				$exists = DuplicateKiller_FieldDuplicate_Checker::check_duplicate_by_key_value(
					'breakdance',
					$check_form_name,
					$field_id,
					$submitted_value,
					$form_cookie,
					$checked_cookie
				);

				if ( $exists ) {
					break;
				}
			}

			if ( $exists ) {
				$error_message_base = ! empty( $form_config['error_message'] )
					? (string) $form_config['error_message']
					: __( 'Please check all fields! These values have been submitted already!', 'duplicate-killer' );

				$label  = $field_labels[ $field_id ] ?? $field_id;
				$pretty = sprintf( '%s: %s', $label, $error_message_base );

				$dk_state['duplicate_found'] = true;
				$dk_state['error_sent']      = true;
				$dk_state['error_message']   = $pretty;

				if ( $dk_enabled ) {
					duplicateKiller_Diagnostics::log( 'breakdance', 'duplicate_found', array(
						'request_debug_id' => $request_debug_id,
						'form_name'        => $form_name,
						'field_id'         => $field_id,
						'field_label'      => $label,
						'message'          => $pretty,
					) );
				}

				return new \WP_Error( 'dk_duplicate', $pretty );
			}
		}
	}

	// 3. Cross-form duplicate check.
	if (
		$cross_form_enabled
		&& class_exists( 'DuplicateKiller_CrossForm' )
	) {
		$cross_match = DuplicateKiller_CrossForm::checkAssocCrossFormDuplicate(
			'breakdance',
			$options,
			$form_name,
			$form_config,
			$data
		);

		if ( $cross_match ) {
			$field_id = $cross_match['current_field_id'] ?? '';

			$error_message_base = ! empty( $form_config['error_message'] )
				? (string) $form_config['error_message']
				: __( 'Please check all fields! These values have been submitted already!', 'duplicate-killer' );

			$label  = $field_labels[ $field_id ] ?? $field_id;
			$pretty = sprintf( '%s: %s', $label, $error_message_base );

			$dk_state['duplicate_found'] = true;
			$dk_state['error_sent']      = true;
			$dk_state['error_message']   = $pretty;

			if ( $dk_enabled ) {
				duplicateKiller_Diagnostics::log( 'breakdance', 'cross_form_duplicate_found', array(
					'request_debug_id' => $request_debug_id,
					'form_name'        => $form_name,
					'field_id'         => $field_id,
					'field_label'      => $label,
					'cross_match'      => $cross_match,
					'message'          => $pretty,
				) );
			}

			return new \WP_Error( 'dk_cross_duplicate', $pretty );
		}
	}

	// 4. Save submission.
	$should_save_submission = (
		! $dk_state['saved_once']
		&& (
			$ip_limit_enabled
			|| $field_check_enabled
			|| $cross_form_enabled
		)
	);

	if ( $should_save_submission ) {
		DuplicateKiller_Submission_Storage::save(
			'breakdance',
			$form_name,
			! empty( $current_form['storage_payload'] ) && is_array( $current_form['storage_payload'] ) ? $current_form['storage_payload'] : array(),
			$form_cookie,
			true,
			$ip_limit_enabled,
			array(),
			array(),
			array(
				'request_debug_id' => $request_debug_id,
				'form_name'        => $form_name,
			)
		);

		$dk_state['saved_once'] = true;
	}

	if ( $dk_enabled ) {
		duplicateKiller_Diagnostics::log( 'breakdance', 'guard_end_success', array(
			'request_debug_id' => $request_debug_id,
			'dk_state'         => $dk_state,
		) );
	}

	return $canExecute;
}

function duplicateKiller_breakdance_get_forms() {

	$q = new WP_Query( [
		'post_type'              => 'any',
		'posts_per_page'         => -1,
		'orderby'                => 'ID',
		'order'                  => 'DESC',
		'fields'                 => 'ids',      // performance: only IDs
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,      // we'll fetch only needed meta manually
		'update_post_term_cache' => false,
		'meta_key'               => '_breakdance_data', // avoids meta_query
		'meta_compare'           => 'EXISTS',
	] );

	$post_ids = $q->posts;
	if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
		return [];
	}

	$out = [];

	foreach ( $post_ids as $post_id ) {

		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			continue;
		}

		$all_meta = get_post_meta( $post_id, '_breakdance_data', false );
		if ( empty( $all_meta ) ) {
			continue;
		}

		$post_title = get_the_title( $post_id );

		foreach ( $all_meta as $meta_raw ) {

			$meta = is_string( $meta_raw ) ? json_decode( $meta_raw, true ) : $meta_raw;
			if ( ! is_array( $meta ) ) {
				$meta = maybe_unserialize( $meta_raw );
			}
			if ( empty( $meta['tree_json_string'] ) || ! is_string( $meta['tree_json_string'] ) ) {
				continue;
			}

			$tree = json_decode( $meta['tree_json_string'], true );
			if ( ! is_array( $tree ) || empty( $tree['root'] ) ) {
				continue;
			}

			// DFS over tree
			$stack = [ $tree['root'] ];
			while ( ! empty( $stack ) ) {

				$node = array_pop( $stack );
				$type = $node['data']['type'] ?? '';

				if ( $type === 'EssentialElements\\FormBuilder' ) {

					$props     = $node['data']['properties'] ?? [];
					$form      = $props['content']['form'] ?? [];
					$fields    = $form['fields'] ?? [];
					$form_name = trim( (string) ( $form['form_name'] ?? '' ) );
					if ( $form_name === '' ) {
						$form_name = $post_title;
					}

					$node_id    = (int) ( $node['id'] ?? 0 );
					$display_key = $form_name . '.' . $post_id . '.' . $node_id;

					if ( ! isset( $out[ $display_key ] ) ) {
						$out[ $display_key ] = [
							'form_id'   => $node_id,
							'post_id'   => $post_id,
							'form_name' => $form_name,
							'fields'    => [],
						];
					}

					foreach ( $fields as $f ) {
						$ftype = strtolower( (string) ( $f['type'] ?? '' ) );
						if ( ! in_array( $ftype, [ 'text', 'email', 'tel', 'phone', 'url' ], true ) ) {
							continue;
						}

						$fid = '';
						if ( ! empty( $f['advanced']['id'] ) ) {
							$fid = (string) $f['advanced']['id'];
						} elseif ( ! empty( $f['label'] ) ) {
							$fid = sanitize_key( (string) $f['label'] );
						}
						if ( $fid === '' ) {
							continue;
						}

						$out[ $display_key ]['fields'][] = [
							'type'  => $ftype,
							'label' => (string) ( $f['label'] ?? '' ),
							'id'    => $fid,
						];
					}
				}

				if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
					foreach ( $node['children'] as $child ) {
						$stack[] = $child;
					}
				}
			}
		}
	}

	// Deduplicate fields (by id) per form
	foreach ( $out as $key => $bundle ) {
		$seen  = [];
		$clean = [];

		foreach ( $bundle['fields'] as $f ) {
			if ( isset( $seen[ $f['id'] ] ) ) {
				continue;
			}
			$seen[ $f['id'] ] = true;
			$clean[]          = $f;
		}

		$out[ $key ]['fields'] = array_values( $clean );
	}

	return $out;
}

/*********************************
 * Callbacks
**********************************/
function duplicateKiller_breakdance_validate_input($input) {
    return duplicateKiller_sanitize_forms_option($input, 'Breakdance_page', 'Breakdance_page', [], 'breakdance');
}
/**
 * Helper: is Breakdance present & enabled for this request?
 */
function duplicateKiller_bd_is_ready(): bool {
    // Present?
    if (!defined('__BREAKDANCE_VERSION') && !class_exists('\Breakdance\Forms\Actions\Action')) {
        return false;
    }
    // Migration mode can disable BD per request
    if (function_exists('\Breakdance\MigrationMode\isBreakdanceEnabledForRequest')
        && !\Breakdance\MigrationMode\isBreakdanceEnabledForRequest()) {
        return false;
    }
    // Loader fired?
    return did_action('before_breakdance_loaded') > 0;
}
function duplicateKiller_breakdance_description(){
	if (!duplicateKiller_bd_is_ready()) {
        echo '<h3 style="color:red"><strong>' . esc_html__('Breakdance plugin is not activated! Please activate it in order to continue.', 'duplicate-killer') . '</strong></h3>';
		exit();
    }

    // If you need a success message, keep this. If not, remove it.
    echo '<h3 style="color:green"><strong>' . esc_html__('Breakdance plugin is activated!', 'duplicate-killer') . '</strong></h3>';

    $forms = duplicateKiller_breakdance_get_forms();
    if (empty($forms)) {
        echo '<br/><span style="color:red"><strong>' . esc_html__('There is no contact form. Please create one!', 'duplicate-killer') . '</strong></span>';
		exit();
    }
}

function duplicateKiller_breakdance_settings_callback($args){
	$options = get_option($args[0]);

}
function duplicateKiller_breakdance_select_form_tag_callback($args){

    duplicateKiller_render_forms_overview([
        'option_name'   => (string)$args[0],   // Breakdance_page
        'db_plugin_key' => 'breakdance',
        'plugin_label'  => 'Breakdance',
        'forms'         => duplicateKiller_breakdance_get_forms(),
        'forms_id_map'  => [], // optional
    ]);
}