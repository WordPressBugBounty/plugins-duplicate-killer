<?php
defined( 'ABSPATH' ) or die( 'You shall not pass!' );

add_action('elementor_pro/forms/validation', 'duplicateKiller_elementor_guard_only', 1, 2);
function duplicateKiller_elementor_guard_only($record, $ajax_handler) {

	$elementor_page = get_option('Elementor_page');
	$elementor_page = duplicateKiller_convert_option_architecture( $elementor_page, 'elementor_' );
	if (!is_array($elementor_page)) {
		$elementor_page = [];
	}

	$request_debug_id = uniqid('duplicateKiller_elementor_guard_', true);
	$dk_enabled       = class_exists('duplicateKiller_Diagnostics');

	// context
	$post_id   = (int)($record->get_form_settings('post_id') ?? 0);
	$form_name = trim((string)($record->get_form_settings('form_name') ?? ''));

	$node_id = (string)($record->get_form_settings('id') ?? '');
	if ($node_id === '') {
		$node_id = (string)($record->get_form_settings('form_id') ?? '');
	}

	if ($form_name === '' && $post_id) {
		$post = get_post($post_id);
		$form_name = $post ? $post->post_title : 'Elementor Form';
	}

	if ($dk_enabled) {
		duplicateKiller_Diagnostics::log('elementor', 'guard_start', [
			'request_debug_id' => $request_debug_id,
			'post_id'          => $post_id,
			'form_name'        => $form_name,
			'node_id'          => $node_id,
			'form_settings'    => method_exists($record, 'get_form_settings') ? [
				'post_id'   => $record->get_form_settings('post_id'),
				'form_name' => $record->get_form_settings('form_name'),
				'id'        => $record->get_form_settings('id'),
				'form_id'   => $record->get_form_settings('form_id'),
			] : [],
		]);
	}

	if ($form_name === '' || $node_id === '') {
		if ($dk_enabled) {
			duplicateKiller_Diagnostics::log('elementor', 'guard_exit_missing_context', [
				'request_debug_id' => $request_debug_id,
				'post_id'          => $post_id,
				'form_name'        => $form_name,
				'node_id'          => $node_id,
			]);
		}
		return;
	}

	$db_form_name = $form_name . '.' . $node_id;

	// ---------------------------------------------------------------------
	// Elementor Group Mode: use FormName.__group__ config if enabled & exists
	// ---------------------------------------------------------------------
	$group_mode = (int) get_option('duplicateKiller_elementor_group_mode', 0);
	$group_key  = $form_name . '.__group__';

	if ($group_mode === 1 && isset($elementor_page[$group_key]) && is_array($elementor_page[$group_key])) {
		$db_form_name = $group_key;
	}

	if ($dk_enabled) {
		duplicateKiller_Diagnostics::log('elementor', 'group_mode_resolved', [
			'request_debug_id' => $request_debug_id,
			'group_mode'       => $group_mode,
			'group_key'        => $group_key,
			'db_form_name'     => $db_form_name,
		]);
	}

	// cfg
	$cfg = [];
	if (!empty($elementor_page[$db_form_name]) && is_array($elementor_page[$db_form_name])) {
		$cfg = $elementor_page[$db_form_name];
	} else {
		foreach ($elementor_page as $k => $v) {
			if (!is_array($v) || empty($v['form_id'])) {
				continue;
			}
			if ((string)$v['form_id'] === (string)$node_id) {
				$cfg = $v;
				if (is_string($k) && $k !== '') {
					$db_form_name = $k;
				}
				break;
			}
		}
	}

	if ($dk_enabled) {
		duplicateKiller_Diagnostics::log('elementor', 'config_resolved', [
			'request_debug_id' => $request_debug_id,
			'db_form_name'     => $db_form_name,
			'config_found'     => !empty($cfg) ? 1 : 0,
			'config'           => $cfg,
		]);
	}

	if (empty($cfg)) {
		return;
	}

	// build data map
	$fields = $record->get('fields');
	if (!is_array($fields)) {
		$fields = [];
	}

	$data = [];
	foreach ($fields as $key => $field) {
		if (!is_array($field)) {
			continue;
		}
		$fid = (string)($field['id'] ?? $key);
		$val = $field['value'] ?? '';
		if (is_array($val)) {
			$val = implode(' ', array_map('strval', $val));
		}
		$data[$fid] = sanitize_text_field((string)$val);
	}

	if ($dk_enabled) {
		duplicateKiller_Diagnostics::log('elementor', 'fields_mapped', [
			'request_debug_id' => $request_debug_id,
			'db_form_name'     => $db_form_name,
			'fields_raw'       => $fields,
			'data_mapped'      => $data,
		]);
	}

	// 1) IP check
	if (function_exists('duplicateKiller_ip_limit_trigger') && duplicateKiller_ip_limit_trigger("elementor", $elementor_page, $db_form_name)) {
		$message = !empty($cfg['error_message_limit_ip_option'])
			? (string)$cfg['error_message_limit_ip_option']
			: __('This IP has been already submitted.', 'duplicate-killer');

		$ajax_handler->add_error_message($message);

		//stop elementor
		$first_field_id = '';
		if (is_array($fields) && $fields) {
			$first = reset($fields);
			if (is_array($first)) {
				$first_field_id = (string)($first['id'] ?? '');
			}
		}

		if ($first_field_id && method_exists($ajax_handler, 'add_error')) {
			//$ajax_handler->add_error($first_field_id, $message);
		}

		if ($dk_enabled) {
			duplicateKiller_Diagnostics::log('elementor', 'ip_limit_blocked', [
				'request_debug_id' => $request_debug_id,
				'db_form_name'     => $db_form_name,
				'message'          => $message,
				'first_field_id'   => $first_field_id,
			]);
		}

		return;
	}

	// 2) duplicate check

	//cookie check
	$cookie = duplicateKiller_get_form_cookie_simple(
		$elementor_page,
		$db_form_name,
		'dk_form_cookie_elementor_forms_'
	);

	$form_cookie    = $cookie['form_cookie'];
	$checked_cookie = $cookie['checked_cookie'];

	if ($dk_enabled) {
		duplicateKiller_Diagnostics::log('elementor', 'cookie_state_resolved', [
			'request_debug_id' => $request_debug_id,
			'db_form_name'     => $db_form_name,
			'form_cookie'      => $form_cookie,
			'checked_cookie'   => $checked_cookie,
		]);
	}

	foreach ($cfg as $field_key => $enabled) {
		if (in_array($field_key, [
			'form_id','error_message','error_message_limit_ip_option',
			'cookie_option','cookie_option_days','cross_form_option',
			'user_ip','user_ip_days'
		], true)) {
			continue;
		}

		if (!$enabled || !isset($data[$field_key])) {
			continue;
		}

		$submitted_value = $data[$field_key];
		if ($submitted_value === '') {
			continue;
		}

		if ($dk_enabled) {
			duplicateKiller_Diagnostics::log('elementor', 'field_duplicate_check_start', [
				'request_debug_id' => $request_debug_id,
				'db_form_name'     => $db_form_name,
				'field_key'        => $field_key,
				'field_value'      => $submitted_value,
				'enabled'          => $enabled ? 1 : 0,
			]);
		}

		$result = duplicateKiller_check_duplicate_by_key_value(
			"elementor",
			$db_form_name,
			$field_key,
			$submitted_value,
			$form_cookie,
			$checked_cookie
		);

		if ($dk_enabled) {
			duplicateKiller_Diagnostics::log('elementor', 'field_duplicate_check_result', [
				'request_debug_id' => $request_debug_id,
				'db_form_name'     => $db_form_name,
				'field_key'        => $field_key,
				'field_value'      => $submitted_value,
				'duplicate_result' => $result ? 1 : 0,
			]);
		}

		if ($result) {
			$msg = !empty($cfg['error_message'])
				? (string)$cfg['error_message']
				: __('Duplicate found.', 'duplicate-killer');

			$ajax_handler->add_error_message($msg);
			if (method_exists($ajax_handler, 'add_error')) {
				//$ajax_handler->add_error($field_key, $msg);
			}

			if ($dk_enabled) {
				duplicateKiller_Diagnostics::log('elementor', 'duplicate_found', [
					'request_debug_id' => $request_debug_id,
					'db_form_name'     => $db_form_name,
					'field_key'        => $field_key,
					'message'          => $msg,
				]);
			}

			return;
		}
	}

	if ($dk_enabled) {
		duplicateKiller_Diagnostics::log('elementor', 'guard_end_success', [
			'request_debug_id' => $request_debug_id,
			'db_form_name'     => $db_form_name,
		]);
	}

	// IMPORTANT: no DB insert here
}

add_action('elementor_pro/forms/new_record', 'duplicateKiller_elementor_save_only', 10, 2);

function duplicateKiller_elementor_save_only($record, $handler) {
	if ( is_object( $handler ) ) {
		if ( isset( $handler->is_success ) && false === $handler->is_success ) {
			return;
		}

		if ( method_exists( $handler, 'has_errors' ) && $handler->has_errors() ) {
			return;
		}

		if ( isset( $handler->errors ) && ! empty( $handler->errors ) ) {
			return;
		}
	}
	global $wpdb;
	$table_name = $wpdb->prefix . 'dk_forms_duplicate';

	$elementor_page = get_option('Elementor_page');
	$elementor_page = duplicateKiller_convert_option_architecture( $elementor_page, 'elementor_' );
	if (!is_array($elementor_page)) {
		$elementor_page = [];
	}

	$request_debug_id = uniqid('duplicateKiller_elementor_save_', true);
	$dk_enabled       = class_exists('duplicateKiller_Diagnostics');

	// context
	$post_id   = (int)($record->get_form_settings('post_id') ?? 0);
	$form_name = trim((string)($record->get_form_settings('form_name') ?? ''));

	$node_id = (string)($record->get_form_settings('id') ?? '');
	if ($node_id === '') {
		$node_id = (string)($record->get_form_settings('form_id') ?? '');
	}

	if ($form_name === '' && $post_id) {
		$post = get_post($post_id);
		$form_name = $post ? $post->post_title : 'Elementor Form';
	}

	if ($dk_enabled) {
		duplicateKiller_Diagnostics::log('elementor', 'save_start', [
			'request_debug_id' => $request_debug_id,
			'post_id'          => $post_id,
			'form_name'        => $form_name,
			'node_id'          => $node_id,
		]);
	}

	if ($form_name === '' || $node_id === '') {
		if ($dk_enabled) {
			duplicateKiller_Diagnostics::log('elementor', 'save_exit_missing_context', [
				'request_debug_id' => $request_debug_id,
				'post_id'          => $post_id,
				'form_name'        => $form_name,
				'node_id'          => $node_id,
			]);
		}
		return;
	}

	$db_form_name = $form_name . '.' . $node_id;

	// ---------------------------------------------------------------------
	// Elementor Group Mode: store under FormName.__group__ so duplicates match across instances
	// ---------------------------------------------------------------------
	$group_mode = (int) get_option('duplicateKiller_elementor_group_mode', 0);
	$group_key  = $form_name . '.__group__';

	if ($group_mode === 1 && isset($elementor_page[$group_key]) && is_array($elementor_page[$group_key])) {
		$db_form_name = $group_key;
	}

	if ($dk_enabled) {
		duplicateKiller_Diagnostics::log('elementor', 'save_group_mode_resolved', [
			'request_debug_id' => $request_debug_id,
			'group_mode'       => $group_mode,
			'group_key'        => $group_key,
			'db_form_name'     => $db_form_name,
		]);
	}

	// cfg
	$cfg = [];
	if (!empty($elementor_page[$db_form_name]) && is_array($elementor_page[$db_form_name])) {
		$cfg = $elementor_page[$db_form_name];
	} else {
		foreach ($elementor_page as $k => $v) {
			if (!is_array($v) || empty($v['form_id'])) {
				continue;
			}
			if ((string)$v['form_id'] === (string)$node_id) {
				$cfg = $v;
				if (is_string($k) && $k !== '') {
					$db_form_name = $k;
				}
				break;
			}
		}
	}

	if ($dk_enabled) {
		duplicateKiller_Diagnostics::log('elementor', 'save_config_resolved', [
			'request_debug_id' => $request_debug_id,
			'db_form_name'     => $db_form_name,
			'config_found'     => !empty($cfg) ? 1 : 0,
			'config'           => $cfg,
		]);
	}

	if (empty($cfg)) {
		return;
	}

	//cookie check
	$cookie = duplicateKiller_get_form_cookie_simple(
		$elementor_page,
		$db_form_name,
		'dk_form_cookie_elementor_forms_'
	);

	$form_cookie    = $cookie['form_cookie'];
	$checked_cookie = $cookie['checked_cookie'];

	if ($dk_enabled) {
		duplicateKiller_Diagnostics::log('elementor', 'save_cookie_state_resolved', [
			'request_debug_id' => $request_debug_id,
			'db_form_name'     => $db_form_name,
			'form_cookie'      => $form_cookie,
			'checked_cookie'   => $checked_cookie,
		]);
	}

	// data
	$fields = $record->get('fields');
	if (!is_array($fields)) {
		$fields = [];
	}

	$data = [];
	foreach ($fields as $key => $field) {
		if (!is_array($field)) {
			continue;
		}
		$fid = (string)($field['id'] ?? $key);

		$val = $field['value'] ?? '';
		if (is_array($val)) {
			$val = implode(' ', array_map('strval', $val));
		}
		$data[$fid] = sanitize_text_field((string)$val);
	}

	if ($dk_enabled) {
		duplicateKiller_Diagnostics::log('elementor', 'save_fields_mapped', [
			'request_debug_id' => $request_debug_id,
			'db_form_name'     => $db_form_name,
			'fields_raw'       => $fields,
			'data_mapped'      => $data,
		]);
	}
	$meta_keys = [
		'form_id',
		'error_message',
		'error_message_limit_ip_option',
		'cookie_option',
		'cookie_option_days',
		'cross_form_option',
		'user_ip',
		'user_ip_days',
	];

	$enabled_duplicate_fields = array_diff(
		array_keys( $cfg ),
		$meta_keys
	);

	$submitted_duplicate_fields = array_intersect(
		$enabled_duplicate_fields,
		array_keys( $data )
	);

	if (
		empty( $submitted_duplicate_fields ) &&
		( ! isset( $cfg['user_ip'] ) || (string) $cfg['user_ip'] !== '1' )
	) {
		return;
	}
	// IP store flag
	$form_ip = (isset($cfg['user_ip']) && $cfg['user_ip'] === "1") ? duplicateKiller_get_user_ip() : 'NULL';

	if ($dk_enabled) {
		duplicateKiller_Diagnostics::log('elementor', 'save_ip_resolved', [
			'request_debug_id' => $request_debug_id,
			'db_form_name'     => $db_form_name,
			'form_ip'          => $form_ip,
		]);
	}
	
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Required for custom plugin table insert.
	$insert_result = $wpdb->insert(
		$table_name,
		[
			'form_plugin' => 'elementor',
			'form_name'   => $db_form_name,
			'form_value'  => serialize($data),
			'form_cookie' => $form_cookie,
			'form_date'   => current_time('Y-m-d H:i:s'),
			'form_ip'     => $form_ip,
		],
		['%s','%s','%s','%s','%s','%s']
	);

	if ($dk_enabled) {
		duplicateKiller_Diagnostics::log('elementor', 'save_after_insert', [
			'request_debug_id' => $request_debug_id,
			'db_form_name'     => $db_form_name,
			'insert_ok'        => empty($wpdb->last_error) && false !== $insert_result ? 1 : 0,
			'wpdb_last_error'  => $wpdb->last_error,
			'insert_id'        => $wpdb->insert_id,
			'table_name'       => $table_name,
		]);
	}
}

/**
 * Safely get an Elementor document instance.
 *
 * @param int $post_id WordPress post/document ID.
 * @return object|null
 */
function duplicateKiller_elementor_get_document( int $post_id ) {
	if ( $post_id <= 0 ) {
		return null;
	}

	if ( ! did_action( 'elementor/loaded' ) ) {
		return null;
	}

	if ( ! class_exists( '\Elementor\Plugin' ) ) {
		return null;
	}

	try {
		$plugin = \Elementor\Plugin::$instance;

		if ( ! isset( $plugin->documents ) || ! method_exists( $plugin->documents, 'get' ) ) {
			return null;
		}

		$document = $plugin->documents->get( $post_id );

		return is_object( $document ) ? $document : null;
	} catch ( \Throwable $e ) {
		return null;
	}
}

/**
 * Safely get Elementor elements data for a document.
 *
 * @param int $post_id WordPress post/document ID.
 * @return array
 */
function duplicateKiller_elementor_get_document_elements_data( int $post_id ): array {
	$document = duplicateKiller_elementor_get_document( $post_id );

	if ( ! $document || ! method_exists( $document, 'get_elements_data' ) ) {
		return [];
	}

	try {
		$data = $document->get_elements_data();

		return is_array( $data ) ? $data : [];
	} catch ( \Throwable $e ) {
		return [];
	}
}

/**
 * Build a stable field ID for a form field.
 *
 * Keeps backward compatibility with the current plugin storage format.
 *
 * @param array $field Elementor form field config.
 * @return string
 */
function duplicateKiller_elementor_build_field_id( array $field ): string {
	if ( ! empty( $field['custom_id'] ) ) {
		return (string) $field['custom_id'];
	}

	if ( ! empty( $field['_id'] ) ) {
		return 'field_' . (string) $field['_id'];
	}

	if ( ! empty( $field['field_label'] ) ) {
		return sanitize_key( (string) $field['field_label'] );
	}

	return '';
}

/**
 * Normalize Elementor widget settings into an array.
 *
 * @param mixed $settings Raw settings.
 * @return array
 */
function duplicateKiller_elementor_normalize_settings( $settings ): array {
	if ( is_array( $settings ) ) {
		return $settings;
	}

	if ( is_object( $settings ) ) {
		return (array) $settings;
	}

	return [];
}

/**
 * Extract supported Duplicate Killer fields from an Elementor form widget settings array.
 *
 * @param array $settings Elementor widget settings.
 * @return array
 */
function duplicateKiller_elementor_extract_form_fields_from_settings( array $settings ): array {
	$out    = [];
	$fields = $settings['form_fields'] ?? [];

	if ( ! is_array( $fields ) ) {
		return [];
	}

	foreach ( $fields as $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$label = (string) ( $field['field_label'] ?? '' );
		$type  = strtolower( (string) ( $field['field_type'] ?? '' ) );

		// Elementor default behavior for plain text fields.
		if ( '' === $type ) {
			$type = 'text';
		}

		// Keep current plugin intent / compatibility.
		if ( ! in_array( $type, [ 'text', 'textarea', 'email', 'tel', 'phone', 'url' ], true ) ) {
			continue;
		}

		$field_id = duplicateKiller_elementor_build_field_id( $field );

		if ( '' === $field_id ) {
			continue;
		}

		$out[] = [
			'type'  => $type,
			'label' => $label,
			'id'    => $field_id,
		];
	}

	return $out;
}

/**
 * Merge new fields into an existing form bundle without duplicates.
 *
 * @param array $bundle Existing form bundle.
 * @param array $fields New fields to merge.
 * @return array
 */
function duplicateKiller_elementor_merge_fields_into_bundle( array $bundle, array $fields ): array {
	if ( empty( $bundle['fields'] ) || ! is_array( $bundle['fields'] ) ) {
		$bundle['fields'] = [];
	}

	$bundle['fields'] = array_merge( $bundle['fields'], $fields );

	$seen  = [];
	$clean = [];

	foreach ( $bundle['fields'] as $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		$field_id = (string) ( $field['id'] ?? '' );

		if ( '' === $field_id || isset( $seen[ $field_id ] ) ) {
			continue;
		}

		$seen[ $field_id ] = true;
		$clean[]           = $field;
	}

	$bundle['fields'] = array_values( $clean );

	return $bundle;
}

/**
 * Register one found Elementor form into output list.
 *
 * @param array  $out              Output forms array (by reference).
 * @param string $effective_node_id Effective runtime/widget instance ID.
 * @param int    $post_id          Source document post ID.
 * @param string $form_name        Form name.
 * @param array  $fields           Parsed fields.
 * @return void
 */
function duplicateKiller_elementor_register_found_form( array &$out, string $effective_node_id, int $post_id, string $form_name, array $fields ): void {
	$effective_node_id = trim( $effective_node_id );
	$form_name         = trim( $form_name );

	if ( '' === $effective_node_id ) {
		return;
	}

	if ( '' === $form_name ) {
		$form_name = (string) get_the_title( $post_id );
	}

	if ( '' === $form_name ) {
		$form_name = 'Elementor Form';
	}

	$display_key = sprintf( '%s.%s', $form_name, $effective_node_id );

	if ( ! isset( $out[ $display_key ] ) || ! is_array( $out[ $display_key ] ) ) {
		$out[ $display_key ] = [
			'form_id'   => $effective_node_id,
			'post_id'   => $post_id,
			'form_name' => $form_name,
			'fields'    => [],
		];
	}

	$out[ $display_key ] = duplicateKiller_elementor_merge_fields_into_bundle( $out[ $display_key ], $fields );
}

/**
 * Try to detect a referenced Elementor template/global widget document ID.
 *
 * This is intentionally permissive because different Elementor versions/addons
 * may store reusable widget references under different keys.
 *
 * @param array $node Elementor node.
 * @return int
 */
function duplicateKiller_elementor_get_referenced_document_id_from_node( array $node ): int {
	$settings = duplicateKiller_elementor_normalize_settings( $node['settings'] ?? [] );

	$candidate_keys = [
		'template_id',
		'saved_widget',
		'global_widget_id',
		'global_template_id',
		'_template_id',
		'content_template_id',
	];

	foreach ( $candidate_keys as $key ) {
		if ( isset( $settings[ $key ] ) && '' !== (string) $settings[ $key ] ) {
			return absint( $settings[ $key ] );
		}
	}

	// Some structures may keep the reference directly on the node.
	$node_candidate_keys = [
		'templateID',
		'template_id',
		'global_widget_id',
		'saved_widget',
	];

	foreach ( $node_candidate_keys as $key ) {
		if ( isset( $node[ $key ] ) && '' !== (string) $node[ $key ] ) {
			return absint( $node[ $key ] );
		}
	}

	return 0;
}

/**
 * Recursively scan Elementor elements and collect form widgets.
 *
 * @param array $elements           Elements tree.
 * @param int   $source_post_id     Source document post ID.
 * @param array $out                Output forms array (by reference).
 * @param array $visited_docs       Visited document IDs (by reference) to avoid loops.
 * @param array $context            Scan context.
 * @return void
 */
function duplicateKiller_elementor_collect_forms_from_elements(
	array $elements,
	int $source_post_id,
	array &$out,
	array &$visited_docs,
	array $context = []
): void {
	foreach ( $elements as $node ) {
		if ( ! is_array( $node ) ) {
			continue;
		}

		$node_id     = isset( $node['id'] ) ? (string) $node['id'] : '';
		$el_type     = isset( $node['elType'] ) ? (string) $node['elType'] : '';
		$widget_type = isset( $node['widgetType'] ) ? (string) $node['widgetType'] : '';
		$settings    = duplicateKiller_elementor_normalize_settings( $node['settings'] ?? [] );

		// Direct Elementor Form widget.
		if ( 'widget' === $el_type && 'form' === $widget_type ) {
			$form_name = trim( (string) ( $settings['form_name'] ?? '' ) );
			$fields    = duplicateKiller_elementor_extract_form_fields_from_settings( $settings );

			duplicateKiller_elementor_register_found_form(
				$out,
				$node_id,
				$source_post_id,
				$form_name,
				$fields
			);
		}

		/*
		 * Reusable/global/template widgets:
		 * If the current node references another Elementor document/template,
		 * scan that referenced document too. When we discover form widgets inside
		 * that referenced document, we additionally register them under the current
		 * wrapper node ID, because frontend submit may use the instance ID from the
		 * page rather than the source template element ID.
		 */
		$referenced_doc_id = duplicateKiller_elementor_get_referenced_document_id_from_node( $node );

		if ( $referenced_doc_id > 0 && empty( $visited_docs[ $referenced_doc_id ] ) ) {
			$visited_docs[ $referenced_doc_id ] = true;

			$referenced_elements = duplicateKiller_elementor_get_document_elements_data( $referenced_doc_id );

			if ( ! empty( $referenced_elements ) ) {
				$temp_out = [];

				duplicateKiller_elementor_collect_forms_from_elements(
					$referenced_elements,
					$referenced_doc_id,
					$temp_out,
					$visited_docs,
					[
						'parent_wrapper_id' => $node_id,
						'parent_post_id'    => $source_post_id,
					]
				);

				foreach ( $temp_out as $bundle ) {
					if ( ! is_array( $bundle ) ) {
						continue;
					}

					$bundle_form_name = isset( $bundle['form_name'] ) ? (string) $bundle['form_name'] : '';
					$bundle_fields    = isset( $bundle['fields'] ) && is_array( $bundle['fields'] ) ? $bundle['fields'] : [];

					// Register original discovered bundle.
					if ( ! empty( $bundle['form_id'] ) ) {
						duplicateKiller_elementor_register_found_form(
							$out,
							(string) $bundle['form_id'],
							isset( $bundle['post_id'] ) ? (int) $bundle['post_id'] : $referenced_doc_id,
							$bundle_form_name,
							$bundle_fields
						);
					}

					// Also register the same form under the current wrapper/instance ID.
					if ( '' !== $node_id ) {
						duplicateKiller_elementor_register_found_form(
							$out,
							$node_id,
							$source_post_id,
							$bundle_form_name,
							$bundle_fields
						);
					}
				}
			}
		}

		// Recurse into child elements.
		if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
			duplicateKiller_elementor_collect_forms_from_elements(
				$node['elements'],
				$source_post_id,
				$out,
				$visited_docs,
				$context
			);
		}
	}
}

/**
 * Get all Elementor forms from all relevant Elementor documents.
 *
 * Uses Elementor Documents API instead of reading raw _elementor_data directly.
 * Preserves the current Duplicate Killer return structure.
 *
 * @return array
 */
function duplicateKiller_elementor_get_forms(): array {
	$out = [];

	// Fail safe if Elementor is not ready.
	if ( ! did_action( 'elementor/loaded' ) || ! class_exists( '\Elementor\Plugin' ) ) {
		return [];
	}

	/*
	 * We intentionally scan all public/document-like post types that may contain
	 * Elementor data, including elementor_library.
	 *
	 * Using 'fields' => 'ids' keeps memory usage lower and is WP-friendly.
	 */
	$post_types = [ 'post', 'page' ];

	if ( post_type_exists( 'elementor_library' ) ) {
		$post_types[] = 'elementor_library';
	}

	$post_types = array_values( array_unique( array_filter( $post_types ) ) );

	$per_page = 200;
	$paged    = 1;

	do {
		$query_args = [
			'post_type'              => $post_types,
			'post_status'            => [ 'publish', 'private', 'draft', 'pending', 'future' ],
			'posts_per_page'         => $per_page,
			'paged'                  => $paged,
			'fields'                 => 'ids',
			'orderby'                => 'ID',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => [
				[
					'key'     => '_elementor_data',
					'compare' => 'EXISTS',
				],
			],
		];

		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin-only scan for Elementor documents.
		$post_ids = get_posts( $query_args );

		if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
			break;
		}

		foreach ( $post_ids as $post_id ) {
			$post_id = (int) $post_id;

			if ( $post_id <= 0 ) {
				continue;
			}

			$elements_data = duplicateKiller_elementor_get_document_elements_data( $post_id );

			// Fallback only if Documents API returned nothing.
			if ( empty( $elements_data ) ) {
				$meta_raw = get_post_meta( $post_id, '_elementor_data', true );

				if ( is_string( $meta_raw ) && '' !== $meta_raw ) {
					$decoded = json_decode( wp_unslash( $meta_raw ), true );

					if ( is_array( $decoded ) ) {
						/*
						 * Some stored structures may be:
						 * - pure numeric array of elements
						 * - full document array with ['content'] key
						 */
						if ( isset( $decoded['content'] ) && is_array( $decoded['content'] ) ) {
							$elements_data = $decoded['content'];
						} else {
							$elements_data = $decoded;
						}
					}
				}
			}

			if ( empty( $elements_data ) || ! is_array( $elements_data ) ) {
				continue;
			}

			$visited_docs           = [];
			$visited_docs[ $post_id ] = true;

			duplicateKiller_elementor_collect_forms_from_elements(
				$elements_data,
				$post_id,
				$out,
				$visited_docs
			);
		}

		$paged++;
	} while ( count( $post_ids ) === $per_page );

	// Final dedupe safety per form bundle.
	foreach ( $out as $key => $bundle ) {
		if ( ! is_array( $bundle ) ) {
			unset( $out[ $key ] );
			continue;
		}

		$bundle = duplicateKiller_elementor_merge_fields_into_bundle( $bundle, [] );

		$out[ $key ] = $bundle;
	}

	/*
	 * Preserve existing group mode behavior.
	 */
	$group_mode = (int) get_option( 'duplicateKiller_elementor_group_mode', 0 );

	if ( 1 !== $group_mode ) {
		return $out;
	}

	$form_counts = [];

	foreach ( $out as $bundle ) {
		$name = isset( $bundle['form_name'] ) ? (string) $bundle['form_name'] : '';

		if ( '' === $name ) {
			continue;
		}

		if ( ! isset( $form_counts[ $name ] ) ) {
			$form_counts[ $name ] = 0;
		}

		$form_counts[ $name ]++;
	}

	$grouped = [];

	foreach ( $out as $bundle ) {
		$form_name = isset( $bundle['form_name'] ) ? (string) $bundle['form_name'] : '';

		if ( '' === $form_name ) {
			continue;
		}

		if ( (int) ( $form_counts[ $form_name ] ?? 0 ) < 2 ) {
			continue;
		}

		if ( ! isset( $grouped[ $form_name ] ) ) {
			$grouped[ $form_name ] = [
				'form_id'   => '__group__',
				'post_id'   => isset( $bundle['post_id'] ) ? (int) $bundle['post_id'] : 0,
				'form_name' => $form_name,
				'fields'    => isset( $bundle['fields'] ) && is_array( $bundle['fields'] ) ? $bundle['fields'] : [],
			];
		} else {
			$grouped[ $form_name ] = duplicateKiller_elementor_merge_fields_into_bundle(
				$grouped[ $form_name ],
				isset( $bundle['fields'] ) && is_array( $bundle['fields'] ) ? $bundle['fields'] : []
			);
		}
	}

	$final = [];

	foreach ( $out as $key => $bundle ) {
		$form_name = isset( $bundle['form_name'] ) ? (string) $bundle['form_name'] : '';

		if ( '' === $form_name ) {
			$final[ $key ] = $bundle;
			continue;
		}

		if ( (int) ( $form_counts[ $form_name ] ?? 0 ) >= 2 ) {
			continue;
		}

		$final[ $key ] = $bundle;
	}

	foreach ( $grouped as $name => $bundle ) {
		$final[ $name . '.__group__' ] = $bundle;
	}

	return $final;
}

/*********************************
 * Callbacks
**********************************/
function duplicateKiller_elementor_validate_input($input) {
    return duplicateKiller_sanitize_forms_option($input, 'Elementor_page', 'Elementor_page', [], 'elementor');
}
/**
 * Helper: is Elementor present & enabled for this request?
 */
function duplicateKiller_elementor_pro_is_ready(): bool {
    // Elementor (free) must be loaded first
    if (
        !defined('ELEMENTOR_VERSION') &&
        !class_exists('\Elementor\Plugin')
    ) {
        return false;
    }

    // Elementor loader fired?
    if (did_action('elementor/loaded') <= 0) {
        return false;
    }

    // Elementor Pro present?
    if (
        !defined('ELEMENTOR_PRO_VERSION') &&
        !class_exists('\ElementorPro\Plugin')
    ) {
        return false;
    }

    // Pro initialized?
    if (did_action('elementor_pro/init') <= 0) {
        // fallback: sometimes Pro plugin class exists before init hook
        if (!class_exists('\ElementorPro\Plugin')) {
            return false;
        }
    }

    return true;
}
function duplicateKiller_elementor_description(){
	if (!duplicateKiller_elementor_pro_is_ready()) {
        echo '<h3 style="color:red"><strong>' . esc_html__('Elementor PRO plugin is not activated! Please activate it in order to continue.', 'duplicate-killer') . '</strong></h3>';
		exit();
    }

    // If you need a success message, keep this. If not, remove it.
    echo '<h3 style="color:green"><strong>' . esc_html__('Elementor PRO plugin is activated!', 'duplicate-killer') . '</strong></h3>';

    $forms = duplicateKiller_elementor_get_forms();
    if (empty($forms)) {
        echo '<br/><span style="color:red"><strong>' . esc_html__('There is no contact form. Please create one!', 'duplicate-killer') . '</strong></span>';
		exit();
    }
}

function duplicateKiller_elementor_settings_callback($args){
	$options = get_option($args[0]);

}
function duplicateKiller_elementor_select_form_tag_callback($args){
    duplicateKiller_render_forms_overview([
        'option_name'   => (string)$args[0],   // Elementor_page
        'db_plugin_key' => 'elementor',
        'plugin_label'  => 'Elementor',
        'forms'         => duplicateKiller_elementor_get_forms(),
        'forms_id_map'  => [], // optional
    ]);
}