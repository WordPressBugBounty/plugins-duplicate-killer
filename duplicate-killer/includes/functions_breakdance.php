<?php
defined( 'ABSPATH' ) or die( 'You shall not pass!' );

add_filter('breakdance_form_run_action_store_submission', 'duplicateKiller_breakdance_guard_action', 10, 5);
add_filter('breakdance_form_run_action_email',            'duplicateKiller_breakdance_guard_action', 10, 5);

function duplicateKiller_breakdance_guard_action($canExecute, $action, $extra, $form, $settings)
{

	if (is_wp_error($canExecute)) {
		return $canExecute;
	}

	static $dk_state = [
		'checked_once'     => false,
		'duplicate_found'  => false,
		'error_sent'       => false,
		'saved_once'       => false,
		'error_message'    => '',
		'request_debug_id' => '',
	];

	if ($dk_state['request_debug_id'] === '') {
		$dk_state['request_debug_id'] = uniqid('duplicateKiller_breakdance_', true);
	}

	$request_debug_id = $dk_state['request_debug_id'];
	$dk_enabled       = class_exists('duplicateKiller_Diagnostics');

	if ($dk_enabled) {
		duplicateKiller_Diagnostics::log('breakdance', 'guard_start', [
			'request_debug_id' => $request_debug_id,
			'action'           => $action,
			'can_execute_type' => is_object($canExecute) ? get_class($canExecute) : gettype($canExecute),
			'is_wp_error'      => is_wp_error($canExecute) ? 1 : 0,
			'extra_raw'        => is_array($extra) ? $extra : [],
			'form_raw'         => is_array($form) ? $form : [],
			'settings_raw'     => is_array($settings) ? $settings : [],
			'dk_state_before'  => $dk_state,
		]);
	}

	if ($dk_state['checked_once']) {
		if ($dk_enabled) {
			duplicateKiller_Diagnostics::log('breakdance', 'guard_already_checked', [
				'request_debug_id' => $request_debug_id,
				'dk_state'         => $dk_state,
			]);
		}

		if ($dk_state['duplicate_found']) {
			if (!$dk_state['error_sent']) {
				$dk_state['error_sent'] = true;

				if ($dk_enabled) {
					duplicateKiller_Diagnostics::log('breakdance', 'duplicate_block_return_wp_error', [
						'request_debug_id' => $request_debug_id,
						'message'          => $dk_state['error_message'] ?: __('Duplicate found.', 'duplicate-killer'),
					]);
				}

				return new \WP_Error('dk_duplicate', $dk_state['error_message'] ?: __('Duplicate found.', 'duplicate-killer'));
			}

			if ($dk_enabled) {
				duplicateKiller_Diagnostics::log('breakdance', 'duplicate_block_return_false', [
					'request_debug_id' => $request_debug_id,
					'dk_state'         => $dk_state,
				]);
			}

			return false;
		}

		return $canExecute;
	}

	$dk_state['checked_once'] = true;

	$post_id = (int)($extra['postId'] ?? 0);
	$node_id = (int)($extra['formId'] ?? 0);

	if ($dk_enabled) {
		duplicateKiller_Diagnostics::log('breakdance', 'ids_resolved', [
			'request_debug_id' => $request_debug_id,
			'post_id'          => $post_id,
			'node_id'          => $node_id,
		]);
	}

	if (!$post_id || !$node_id) {
		if ($dk_enabled) {
			duplicateKiller_Diagnostics::log('breakdance', 'guard_exit_missing_ids', [
				'request_debug_id' => $request_debug_id,
				'post_id'          => $post_id,
				'node_id'          => $node_id,
			]);
		}
		return $canExecute;
	}

	$bd_form   = $settings['form'] ?? [];
	$base_name = trim((string)($bd_form['form_name'] ?? ''));
	if ($base_name === '') {
		$post = get_post($post_id);
		$base_name = $post ? $post->post_title : 'Breakdance Form';
	}

	$options = get_option('Breakdance_page');
	$options = duplicateKiller_convert_option_architecture( $options, 'breakdance_' );
	if (!is_array($options)) {
		$options = [];
	}

	$db_form_name = $base_name . '.' . $post_id . '.' . $node_id;
	$cfg          = array();

	if ( isset( $options[ $db_form_name ] ) && is_array( $options[ $db_form_name ] ) ) {
		$cfg = $options[ $db_form_name ];
	}

	if ($dk_enabled) {
		duplicateKiller_Diagnostics::log('breakdance', 'config_resolved', [
			'request_debug_id' => $request_debug_id,
			'base_name'        => $base_name,
			'config_found'     => !empty($cfg) ? 1 : 0,
			'config'           => $cfg,
		]);
	}
	
	if (empty($cfg)) {
		if ($dk_enabled) {
			duplicateKiller_Diagnostics::log('breakdance', 'no_form_name_found', [
				'request_debug_id' => $request_debug_id,
				'db_form_name'     => $db_form_name,
				'post_id'          => $post_id,
				'node_id'          => $node_id,
				'base_name'        => $base_name,
				'available_keys'   => is_array($options) ? array_keys($options) : [],
			]);
		}
		return $canExecute;
	}


	$default_msg        = __('Please check all fields! These values have been submitted already!', 'duplicate-killer');
	$error_message_base = isset($cfg['error_message']) ? (string)$cfg['error_message'] : $default_msg;

	$id_to_val   = [];
	$id_to_label = [];
	foreach ($form as $field) {
		$fid = \Breakdance\Forms\getIdFromField($field);
		$id_to_val[$fid]   = (string)($field['value'] ?? '');
		$id_to_label[$fid] = (string)($field['label'] ?? $fid);
	}

	if ($dk_enabled) {
		duplicateKiller_Diagnostics::log('breakdance', 'form_map_built', [
			'request_debug_id' => $request_debug_id,
			'db_form_name'     => $db_form_name,
			'id_to_val'        => $id_to_val,
			'id_to_label'      => $id_to_label,
		]);
	}

	if (function_exists('duplicateKiller_ip_limit_trigger')) {
		$triggered = duplicateKiller_ip_limit_trigger('breakdance', $options, $db_form_name);

		$msg = !empty($cfg['error_message_limit_ip_option'])
			? (string)$cfg['error_message_limit_ip_option']
			: __('This IP has been already submitted.', 'duplicate-killer');

		if ($dk_enabled) {
			duplicateKiller_Diagnostics::log('breakdance', 'ip_check_result', [
				'request_debug_id' => $request_debug_id,
				'db_form_name'     => $db_form_name,
				'triggered'        => $triggered ? 1 : 0,
				'message'          => $msg,
			]);
		}

		if ($triggered) {
			$dk_state['duplicate_found'] = true;
			$dk_state['error_sent']      = true;
			$dk_state['error_message']   = $msg;

			if ($dk_enabled) {
				duplicateKiller_Diagnostics::log('breakdance', 'ip_limit_blocked', [
					'request_debug_id' => $request_debug_id,
					'message'          => $msg,
					'dk_state'         => $dk_state,
				]);
			}

			return new \WP_Error('dk_duplicate_ip', $msg);
		}
	}

	$unique_ids = [];
	foreach ($cfg as $k => $v) {
		if (
			in_array($k, ['error_message','cookie_option','cookie_option_days','user_ip','error_message_limit_ip_option','user_ip_days','form_id','cross_form_option'], true)
			|| substr((string)$k, -3) === '_ck'
		) {
			continue;
		}
		if ($v == '1') {
			$unique_ids[] = $k;
		}
	}
	//error_log(print_r($unique_ids, true));

	if ($dk_enabled) {
		duplicateKiller_Diagnostics::log('breakdance', 'unique_ids_resolved', [
			'request_debug_id' => $request_debug_id,
			'db_form_name'     => $db_form_name,
			'unique_ids'       => $unique_ids,
		]);
	}

	$cookie = duplicateKiller_get_form_cookie_simple(
			$options,
			$db_form_name,
			'dk_form_cookie_breakdance_'
	);

	$form_cookie    = $cookie['form_cookie'];
	$checked_cookie = $cookie['checked_cookie'];
	
	if ($unique_ids) {
		if ($dk_enabled) {
			duplicateKiller_Diagnostics::log('breakdance', 'cookie_state_resolved', [
				'request_debug_id' => $request_debug_id,
				'db_form_name'     => $db_form_name,
				'form_cookie'      => $form_cookie,
				'checked_cookie'   => $checked_cookie,
			]);
		}

		foreach ($unique_ids as $field_id) {
			$submitted_value = isset($id_to_val[$field_id]) ? $id_to_val[$field_id] : '';

			if ($submitted_value === '' || $submitted_value === null) {
				if ($dk_enabled) {
					duplicateKiller_Diagnostics::log('breakdance', 'field_skipped_empty', [
						'request_debug_id' => $request_debug_id,
						'field_id'         => $field_id,
					]);
				}
				continue;
			}

			if (is_array($submitted_value)) {
				$submitted_value = implode(' ', array_map('strval', $submitted_value));
			} else {
				$submitted_value = (string)$submitted_value;
			}

			if ($dk_enabled) {
				duplicateKiller_Diagnostics::log('breakdance', 'field_duplicate_check_start', [
					'request_debug_id' => $request_debug_id,
					'field_id'         => $field_id,
					'field_label'      => $id_to_label[$field_id] ?? $field_id,
					'field_value'      => $submitted_value,
				]);
			}

			$exists = duplicateKiller_check_duplicate_by_key_value(
				'breakdance',          // $form_plugin
				$db_form_name,         // $form_name
				$field_id,             // $key
				$submitted_value,      // $value
				$form_cookie,          // $form_cookie
				$checked_cookie        // $checked_cookie
			);

			if ($dk_enabled) {
				duplicateKiller_Diagnostics::log('breakdance', 'field_duplicate_check_result', [
					'request_debug_id' => $request_debug_id,
					'field_id'         => $field_id,
					'field_label'      => $id_to_label[$field_id] ?? $field_id,
					'field_value'      => $submitted_value,
					'duplicate_result' => $exists ? 1 : 0,
				]);
			}

			if ($exists) {
				$label  = $id_to_label[$field_id] ?? $field_id;
				$pretty = sprintf('%s: %s', $label, $error_message_base);

				$dk_state['duplicate_found'] = true;
				$dk_state['error_sent']      = true;
				$dk_state['error_message']   = $pretty;

				if ($dk_enabled) {
					duplicateKiller_Diagnostics::log('breakdance', 'duplicate_found', [
						'request_debug_id' => $request_debug_id,
						'field_id'         => $field_id,
						'field_label'      => $label,
						'message'          => $pretty,
						'dk_state'         => $dk_state,
					]);
				}

				return new \WP_Error('dk_duplicate', $pretty);
			}
		}
	}
	
	$should_save_submission = (
		! empty( $unique_ids ) ||
		( isset( $cfg['user_ip'] ) && (string) $cfg['user_ip'] === '1' )
	);
	if (!$dk_state['saved_once'] && $should_save_submission) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'dk_forms_duplicate';

		$payload = serialize($extra['fields'] ?? []);

		$form_ip = (isset($cfg['user_ip']) && $cfg['user_ip'] === "1") ? (duplicateKiller_get_user_ip()) : 'NULL';

		$now_gmt = current_time('Y-m-d H:i:s');

		if ($dk_enabled) {
			duplicateKiller_Diagnostics::log('breakdance', 'save_start', [
				'request_debug_id' => $request_debug_id,
				'db_form_name'     => $db_form_name,
				'payload'          => $extra['fields'] ?? [],
				'form_ip'          => $form_ip,
				'table_name'       => $table_name,
			]);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Required for custom plugin table insert.
		$insert_result = $wpdb->insert($table_name, [
			'form_plugin' => 'breakdance',
			'form_name'   => $db_form_name,
			'form_value'  => $payload,
			'form_cookie' => $form_cookie,
			'form_date'   => $now_gmt,
			'form_ip'     => $form_ip,
		], ['%s','%s','%s','%s','%s','%s']);

		if ($dk_enabled) {
			duplicateKiller_Diagnostics::log('breakdance', 'save_after_insert', [
				'request_debug_id' => $request_debug_id,
				'db_form_name'     => $db_form_name,
				'insert_ok'        => empty($wpdb->last_error) && false !== $insert_result ? 1 : 0,
				'wpdb_last_error'  => $wpdb->last_error,
				'insert_id'        => $wpdb->insert_id,
				'table_name'       => $table_name,
			]);
		}

		$dk_state['saved_once'] = true;
	}

	if ($dk_enabled) {
		duplicateKiller_Diagnostics::log('breakdance', 'guard_end_success', [
			'request_debug_id' => $request_debug_id,
			'dk_state'         => $dk_state,
		]);
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