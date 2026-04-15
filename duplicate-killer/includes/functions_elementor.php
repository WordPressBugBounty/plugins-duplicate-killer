<?php
defined( 'ABSPATH' ) or die( 'You shall not pass!' );

add_action('elementor_pro/forms/validation', 'duplicateKiller_elementor_guard_only', 1, 2);
function duplicateKiller_elementor_guard_only($record, $ajax_handler) {

    $request_debug_id = uniqid('dk_elementor_validate_', true);
    $dk_enabled       = class_exists('duplicateKiller_Diagnostics');

    $opt = get_option('Elementor_page');
    if (!is_array($opt)) $opt = [];

    // --- globals (new structure) ---
    $g_cookie_enabled = isset($opt['elementor_cookie_option']) && (string)$opt['elementor_cookie_option'] === '1';
    $g_cookie_days    = isset($opt['elementor_cookie_option_days']) ? (int)$opt['elementor_cookie_option_days'] : 0;

    $g_user_ip_enabled = isset($opt['elementor_user_ip']) && (string)$opt['elementor_user_ip'] === '1';

    $g_msg_ip = !empty($opt['elementor_error_message_limit_ip'])
        ? (string)$opt['elementor_error_message_limit_ip']
        : __('This IP has been already submitted.', 'duplicate-killer');

    $g_msg_dup = !empty($opt['elementor_error_message'])
        ? (string)$opt['elementor_error_message']
        : __('Duplicate found.', 'duplicate-killer');

    // --- context ---
    $post_id   = (int)($record->get_form_settings('post_id') ?? 0);
    $form_name = trim((string)($record->get_form_settings('form_name') ?? ''));

    $node_id = (string)($record->get_form_settings('id') ?? '');
    if ($node_id === '') $node_id = (string)($record->get_form_settings('form_id') ?? '');

    if ($form_name === '' && $post_id) {
        $post = get_post($post_id);
        $form_name = $post ? $post->post_title : 'Elementor Form';
    }
    if ($form_name === '' || $node_id === '') return;

    if ($dk_enabled) {
        duplicateKiller_Diagnostics::log('elementor', 'validate_start', [
            'request_debug_id' => $request_debug_id,
            'post_id'          => $post_id,
            'form_name'        => $form_name,
            'node_id'          => $node_id,
        ]);
    }

    // key in option
    $db_form_name = $form_name . '.' . $node_id;

    // cookie
    $form_cookie = 'NULL';
	if ( isset( $_COOKIE['dk_form_cookie'] ) ) {
		$form_cookie = sanitize_text_field(
			wp_unslash( $_COOKIE['dk_form_cookie'] )
		);
	}

    if ($dk_enabled) {
        duplicateKiller_Diagnostics::log('elementor', 'cookie_resolved', [
            'request_debug_id' => $request_debug_id,
            'form_cookie'      => $form_cookie,
        ]);
    }

    // --- per-form cfg (only fields in new structure) ---
    $cfg = [];
    if (!empty($opt[$db_form_name]) && is_array($opt[$db_form_name])) {
        $cfg = $opt[$db_form_name];
    } else {
        // fallback: match by suffix ".{node_id}" in option keys
        $suffix = '.' . $node_id;
        foreach ($opt as $k => $v) {
            if (!is_string($k) || !is_array($v)) continue; // skip globals + invalid
            if (substr($k, -strlen($suffix)) === $suffix) {
                $cfg = $v;
                $db_form_name = $k; // keep real key
                break;
            }
        }
    }
    if (empty($cfg) || !is_array($cfg)) return;

    if ($dk_enabled) {
        duplicateKiller_Diagnostics::log('elementor', 'config_resolved', [
            'request_debug_id' => $request_debug_id,
            'db_form_name'     => $db_form_name,
            'has_config'       => !empty($cfg) ? 1 : 0,
        ]);
    }

    // build data map
    $fields = $record->get('fields');
    if (!is_array($fields)) $fields = [];

    $data = [];
    foreach ($fields as $key => $field) {
        if (!is_array($field)) continue;
        $fid = (string)($field['id'] ?? $key);

        $val = $field['value'] ?? '';
        if (is_array($val)) $val = implode(' ', array_map('strval', $val));
        $data[$fid] = sanitize_text_field((string)$val);
    }

    if ($dk_enabled) {
        duplicateKiller_Diagnostics::log('elementor', 'fields_parsed', [
            'request_debug_id' => $request_debug_id,
            'data'             => $data,
        ]);
    }

    // 1) IP check (map globals to what duplicateKiller_ip_limit_trigger expects)
    if (function_exists('duplicateKiller_ip_limit_trigger')) {
        $mapped_for_ip = [
            'user_ip'      => $g_user_ip_enabled ? "1" : "0",
            'user_ip_days' => isset($opt['elementor_user_ip_days']) ? (string)$opt['elementor_user_ip_days'] : '',
        ];

        // if duplicateKiller_ip_limit_trigger expects full option array, you can merge:
        $opt_for_ip = $opt + $mapped_for_ip;
        $ip_triggered = duplicateKiller_ip_limit_trigger("elementor", $opt_for_ip, $db_form_name);

        if ($dk_enabled) {
            duplicateKiller_Diagnostics::log('elementor', 'ip_check', [
                'request_debug_id' => $request_debug_id,
                'triggered'        => $ip_triggered ? 1 : 0,
            ]);
        }

        if ($ip_triggered) {
            $ajax_handler->add_error_message($g_msg_ip);

            // stop elementor: attach error to first field if possible
            $first_field_id = '';
            if ($fields) {
                $first = reset($fields);
                if (is_array($first)) {
                    $first_field_id = (string)($first['id'] ?? '');
                }
            }
            if ($first_field_id && method_exists($ajax_handler, 'add_error')) {
                $ajax_handler->add_error($first_field_id, $g_msg_ip);
            }

            if ($dk_enabled) {
                duplicateKiller_Diagnostics::log('elementor', 'ip_blocked', [
                    'request_debug_id' => $request_debug_id,
                    'message'          => $g_msg_ip,
                    'first_field_id'   => $first_field_id,
                ]);
            }

			duplicateKiller_increment_duplicates_blocked_count();
            return;
        }
    }

    // 2) duplicate check
    $checked_cookie = $g_cookie_enabled;

    foreach ($cfg as $field_key => $enabled) {
        // $cfg = only fields, but extra safety:
        if (!is_string($field_key) || $field_key === '') continue;
        if ((string)$enabled !== "1") continue;
        if (!isset($data[$field_key])) continue;

        $submitted_value = $data[$field_key];
        if ($submitted_value === '') continue;

        if ($dk_enabled) {
            duplicateKiller_Diagnostics::log('elementor', 'field_check', [
                'request_debug_id' => $request_debug_id,
                'field_key'        => $field_key,
                'value'            => $submitted_value,
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

        if ($result) {
            $ajax_handler->add_error_message($g_msg_dup);
            if (method_exists($ajax_handler, 'add_error')) {
                $ajax_handler->add_error($field_key, $g_msg_dup);
            }

            if ($dk_enabled) {
                duplicateKiller_Diagnostics::log('elementor', 'duplicate_blocked', [
                    'request_debug_id' => $request_debug_id,
                    'field_key'        => $field_key,
                    'message'          => $g_msg_dup,
                ]);
            }

			duplicateKiller_increment_duplicates_blocked_count();
            return;
        }
    }

    // IMPORTANT: no DB insert here
}

add_action('elementor_pro/forms/new_record', 'duplicateKiller_elementor_save_only', 10, 2);
function duplicateKiller_elementor_save_only($record, $handler) {

    global $wpdb;

    $request_debug_id = uniqid('dk_elementor_save_', true);
    $dk_enabled       = class_exists('duplicateKiller_Diagnostics');

    $opt = get_option('Elementor_page');
    if (!is_array($opt)) $opt = [];

    // --- globals (new structure) ---
    $g_user_ip_enabled = isset($opt['elementor_user_ip']) && (string)$opt['elementor_user_ip'] === '1';

    // context
    $post_id   = (int)($record->get_form_settings('post_id') ?? 0);
    $form_name = trim((string)($record->get_form_settings('form_name') ?? ''));

    $node_id = (string)($record->get_form_settings('id') ?? '');
    if ($node_id === '') $node_id = (string)($record->get_form_settings('form_id') ?? '');

    if ($form_name === '' && $post_id) {
        $post = get_post($post_id);
        $form_name = $post ? $post->post_title : 'Elementor Form';
    }
    if ($form_name === '' || $node_id === '') return;

    $db_form_name = $form_name . '.' . $node_id;

    if ($dk_enabled) {
        duplicateKiller_Diagnostics::log('elementor', 'save_start', [
            'request_debug_id' => $request_debug_id,
            'post_id'          => $post_id,
            'form_name'        => $form_name,
            'node_id'          => $node_id,
            'db_form_name'     => $db_form_name,
        ]);
    }

    // ensure form exists in config (optional, but keeps behavior consistent)
    $cfg = [];
	if (!empty($opt[$db_form_name]) && is_array($opt[$db_form_name])) {
		$cfg = $opt[$db_form_name];
	} else {
		$suffix = '.' . $node_id;
		foreach ($opt as $k => $v) {
			if (!is_string($k) || !is_array($v)) continue;
			if (substr($k, -strlen($suffix)) === $suffix) {
				$cfg = $v;
				$db_form_name = $k;
				break;
			}
		}
	}

	if ( empty( $cfg ) || ! is_array( $cfg ) ) {
		return;
	}

	$has_duplicate_field = false;

	foreach ( $cfg as $field_key => $enabled ) {
		if ( 'labels' === (string) $field_key || 'form_id' === (string) $field_key ) {
			continue;
		}

		if ( '1' === (string) $enabled ) {
			$has_duplicate_field = true;
			break;
		}
	}

	if ( ! $has_duplicate_field && ! $g_user_ip_enabled ) {
		if ( $dk_enabled ) {
			duplicateKiller_Diagnostics::log('elementor', 'save_skipped', [
				'request_debug_id'    => $request_debug_id,
				'db_form_name'        => $db_form_name,
				'reason'              => 'no_duplicate_fields_and_ip_disabled',
				'has_duplicate_field' => 0,
				'ip_enabled'          => 0,
			]);
		}

		return;
	}
    // cookie
    $form_cookie = 'NULL';
	if ( isset( $_COOKIE['dk_form_cookie'] ) ) {
		$form_cookie = sanitize_text_field(
			wp_unslash( $_COOKIE['dk_form_cookie'] )
		);
	}

    if ($dk_enabled) {
        duplicateKiller_Diagnostics::log('elementor', 'cookie_resolved', [
            'request_debug_id' => $request_debug_id,
            'form_cookie'      => $form_cookie,
        ]);
    }

    // data
    $fields = $record->get('fields');
    if (!is_array($fields)) $fields = [];

    $data = [];
    foreach ($fields as $key => $field) {
        if (!is_array($field)) continue;
        $fid = (string)($field['id'] ?? $key);

        $val = $field['value'] ?? '';
        if (is_array($val)) $val = implode(' ', array_map('strval', $val));
        $data[$fid] = sanitize_text_field((string)$val);
    }

    if ($dk_enabled) {
        duplicateKiller_Diagnostics::log('elementor', 'fields_parsed', [
            'request_debug_id' => $request_debug_id,
            'data'             => $data,
        ]);
    }

    // IP store flag (global)
    $form_ip = $g_user_ip_enabled ? duplicateKiller_get_user_ip() : 'NULL';

    $table_name = $wpdb->prefix . 'dk_forms_duplicate';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Inserting into plugin-owned custom table.
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
            'insert_ok'        => empty($wpdb->last_error) && false !== $insert_result ? 1 : 0,
            'wpdb_last_error'  => $wpdb->last_error,
            'insert_id'        => $wpdb->insert_id,
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
function duplicateKiller_elementor_validate_input($input){
	global $wpdb;
	$output = array();
	
	// Create our array for storing the validated options
    foreach($input as $key =>$value){
		if(is_array($value)){
			foreach($value as $arr => $asc){
				//check if someone putting in ‘dog’ when the only valid values are numbers
				if($asc != "1"){
					$value[$arr] = "1";
					$output[$key] = $value;
				}else{
					$output[$key] = $value;
				}
			}	
		}
	}
	//validate cookies feature
	if(!isset($input['elementor_cookie_option']) || $input['elementor_cookie_option'] !== "1"){
		$output['elementor_cookie_option'] = "0";
	}else{
		$output['elementor_cookie_option'] = "1";
	}
	if(filter_var($input['elementor_cookie_option_days'], FILTER_VALIDATE_INT) === false){
		$output['elementor_cookie_option_days'] = 365;
	}else{
		$output['elementor_cookie_option_days'] = sanitize_text_field($input['elementor_cookie_option_days']);
	}
	
	//validate ip limit feature
	if(!isset($input['elementor_user_ip']) || $input['elementor_user_ip'] !== "1"){
		$output['elementor_user_ip'] = "0";
	}else{
		$output['elementor_user_ip'] = "1";
	}
	if(empty($input['elementor_error_message_limit_ip'])){
		$output['elementor_error_message_limit_ip'] = "You already submitted this form!";
	}else{
		$output['elementor_error_message_limit_ip'] = sanitize_text_field($input['elementor_error_message_limit_ip']);
	}
	
	//validate standard error message
    if(empty($input['elementor_error_message'])){
		$output['elementor_error_message'] = "Please check all fields! These values has been submitted already!";
	}else{
		$output['elementor_error_message'] = sanitize_text_field($input['elementor_error_message']);
	}
    // Return the array processing any additional functions filtered by this action
      return apply_filters( 'duplicateKiller_elementor_error_message', $output, $input );
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
function duplicateKiller_elementor_get_form_map(): array {

	$out = array();

	$per_page = 200;
	$paged    = 1;

	do {
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Admin-only scan for Elementor forms (paged, IDs only).
		$post_ids = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ), // Add more if needed (e.g. 'elementor_library').
				'posts_per_page' => $per_page,
				'paged'          => $paged,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Admin-only scan for Elementor forms (paged, IDs only).
				'meta_key'       => '_elementor_data',
				'orderby'        => 'ID',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		if ( empty( $post_ids ) ) {
			break;
		}

		foreach ( $post_ids as $post_id ) {
			$post_id = (int) $post_id;

			$meta_raw = get_post_meta( $post_id, '_elementor_data', true );
			if ( empty( $meta_raw ) ) {
				continue;
			}

			$data = $meta_raw;

			if ( is_string( $meta_raw ) ) {
				$decoded = json_decode( $meta_raw, true );
				if ( is_array( $decoded ) ) {
					$data = $decoded;
				} else {
					$maybe = maybe_unserialize( $meta_raw );
					if ( is_array( $maybe ) ) {
						$data = $maybe;
					}
				}
			}

			if ( ! is_array( $data ) || empty( $data ) ) {
				continue;
			}

			$stack = $data;

			while ( ! empty( $stack ) ) {
				$node = array_pop( $stack );
				if ( ! is_array( $node ) ) {
					continue;
				}

				if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
					foreach ( $node['elements'] as $child ) {
						$stack[] = $child;
					}
				}

				if (
					( $node['elType'] ?? '' ) !== 'widget' ||
					( $node['widgetType'] ?? '' ) !== 'form'
				) {
					continue;
				}

				$settings = $node['settings'] ?? array();
				if ( ! is_array( $settings ) ) {
					$settings = array();
				}

				$form_name = trim( (string) ( $settings['form_name'] ?? '' ) );
				if ( '' === $form_name ) {
					$form_name = (string) get_the_title( $post_id );
				}

				$form_id = (string) ( $node['id'] ?? '' );
				if ( '' === $form_id ) {
					continue;
				}

				// Keep first occurrence for a given form_name (same behavior as your original code).
				if ( ! isset( $out[ $form_name ] ) ) {
					$out[ $form_name ] = $form_id;
				}
			}
		}

		$paged++;
	} while ( count( $post_ids ) === $per_page );

	return $out;
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
	$checked_cookie = isset($options['elementor_cookie_option']) AND ($options['elementor_cookie_option'] == "1")?: $checked_cookie='';
	$stored_cookie_days = isset($options['elementor_cookie_option_days'])? $options['elementor_cookie_option_days']:"365";
	
	$checkbox_ip = isset($options['elementor_user_ip']) AND ($options['elementor_user_ip'] == "1")?: $checkbox_ip='';
	$stored_error_message_limit_ip = isset($options['elementor_error_message_limit_ip'])? $options['elementor_error_message_limit_ip']:"You already submitted this form!";
	
	$stored_error_message = isset($options['elementor_error_message'])? $options['elementor_error_message']:"Please check all fields! These values has been submitted already!";
	?>
	<h4 class="dk-form-header">Duplicate Killer settings</h4>
	<div class="dk-set-error-message">
		<fieldset class="dk-fieldset">
		<legend>
			<strong>Set error message:</strong>
			<small style="font-weight:normal; margin-left:8px;">
				<a href="https://verselabwp.com/what-is-the-set-error-message-field-in-duplicate-killer/" target="_blank" rel="noopener">
					What is this?
				</a>
			</small>
		</legend>
		<span>Warn the user that the value inserted has been already submitted!</span>
		</br>
		<input type="text" size="70" name="<?php echo esc_attr($args[0].'[elementor_error_message]');?>" value="<?php echo esc_attr($stored_error_message);?>"></input>
		</fieldset>
	</div>
	</br>
	<div class="dk-set-unique-entries-per-user">
		<fieldset class="dk-fieldset">
		<legend>
			<strong>Unique entries per user:</strong>
			<small style="font-weight:normal; margin-left:8px;">
				<a href="https://verselabwp.com/unique-entries-per-user-in-wordpress-how-to-use-it/" target="_blank" rel="noopener">
					How to use it?
				</a>
			</small>
		</legend>
		<strong>This feature use cookies.</strong><span> Please note that multiple users <strong>can submit the same entry</strong>, but a single user cannot submit an entry they have already submitted before.</span>
		</br>
		</br>
		<div class="dk-input-checkbox-callback">
			<input type="checkbox" id="cookie" name="<?php echo esc_attr($args[0].'[elementor_cookie_option]');?>" value="1" <?php echo esc_attr($checked_cookie ? 'checked' : '');?>></input>
			<label for="cookie">Activate this function</label>
		</div>
		</br>
		<div id="dk-unique-entries-cookie" style="display:none">
		<span>Cookie persistence - Number of days </span><input type="text" name="<?php echo esc_attr($args[0].'[elementor_cookie_option_days]');?>" size="5" value="<?php echo esc_attr($stored_cookie_days);?>"></input>
		</br>
		</div>
		</fieldset>
	</div>
	<div class="dk-limit_submission_by_ip">
		<fieldset class="dk-fieldset">
		<legend>
			<strong>Limit submissions by IP address</strong>
			<small style="font-weight:normal; margin-left:8px;">
				<a href="https://verselabwp.com/limit-submissions-by-ip-address-in-wordpress-free-pro/" target="_blank" rel="noopener">
					How it works
				</a>
			</small>
		</legend>
		<strong>This feature </strong><span> restrict form entries based on IP address for 7 days</span>
		</br>
		</br>
		<div class="dk-input-checkbox-callback">
			<input type="checkbox" id="user_ip" name="<?php echo esc_attr($args[0].'[elementor_user_ip]');?>" value="1" <?php echo esc_attr($checkbox_ip ? 'checked' : '');?>></input>
			<label for="user_ip">Activate this function</label>
		</div>
		</br>
		<div id="dk-limit-ip" style="display:none">
		<span>Ser error message:</span><input type="text" size="70" name="<?php echo esc_attr($args[0].'[elementor_error_message_limit_ip]');?>" value="<?php echo esc_attr($stored_error_message_limit_ip);?>"></input>
		</br>
		</div>
		</fieldset>
	</div>
<?php
}

function duplicateKiller_elementor_select_form_tag_callback($args){
	$forms     = duplicateKiller_elementor_get_forms();      // [ 'Form name' => [ 'field1', 'field2' ] ]

	$forms_ids = duplicateKiller_elementor_get_form_map(); // [ 'Form name' => 123 ]

	duplicateKiller_render_forms_ui(
		'Elementor',
		'Elementor',
		$args,
		$forms,
		$forms_ids
	);
}