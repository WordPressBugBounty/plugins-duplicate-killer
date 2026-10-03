<?php
defined( 'ABSPATH' ) or die( 'You shall not pass!' );

function duplicateKiller_sanitize_message($value, string $settings_group, string $error_code, string $error_text, int $min_len = 5): ?string {
    $msg = sanitize_text_field((string)$value);
    if (strlen($msg) < $min_len) {
        add_settings_error($settings_group, $error_code, $error_text, 'error');
        return null;
    }
    return $msg;
}

function duplicateKiller_sanitize_days($value, string $settings_group, string $error_code, string $error_text, int $min = 1, int $max = 365): ?int {
    $days = intval($value);
    if ($days < $min || $days > $max) {
        add_settings_error($settings_group, $error_code, $error_text, 'error');
        return null;
    }
    return $days;
}

function duplicateKiller_collect_checked_fields(array $values, array $skip_keys): array {
    $out = [];
    foreach ($values as $k => $v) {
        if (in_array($k, $skip_keys, true)) continue;
        if ((string)$v === '1') $out[$k] = '1';
    }
    return $out;
}

function duplicateKiller_sanitize_forms_option(
    $input,
    string $option_name,
    string $settings_group,
    array $global_keys = [],
    string $db_plugin_key = ''
): array {

    $previous = get_option( $option_name, array() );
    $previous = is_array( $previous ) ? $previous : array();

    if ( ! current_user_can( 'manage_options' ) || ! is_array( $input ) ) {
        return $previous;
    }

    $defaults   = duplicateKiller_get_form_defaults();
    $plugin_key = $db_plugin_key !== '' ? $db_plugin_key : duplicateKiller_option_to_db_plugin_key($option_name);

    // These keys are NOT "fields", they are per-form settings
    $known_scalar_keys = [
        'form_id',
        'error_message',
		'error_message_type',
		'modern_error_message',
		'field_duplicate_block_days',
		'field_duplicate_block_hours',
		'field_duplicate_block_minutes',
        'error_message_limit_ip_option',
        'user_ip_days',
		'user_ip_hours',
		'user_ip_minutes',
        'cookie_option_days',
        'cookie_option',
        'user_ip',
        'cross_form_option', //cross-posting from 5.0.9
        '__dk_field_type',   //cross-posting from 5.0.9
        '__dk_field_order',  //cross-posting from 5.0.9
        'delete_records',    // one-shot, do not persist
        'labels',            // Formidable-only meta, handled separately
    ];

    // Which scalar keys should fall back to defaults if emptied
    $defaultable_keys = [
        'error_message',
        'error_message_limit_ip_option',
        'user_ip_days',
        'cookie_option_days',
    ];

    // Resolve the submitted choice against real forms, once per settings save.
    $getters = array(
        'CF7_page'         => 'duplicateKiller_CF7_get_forms',
        'Forminator_page'  => 'duplicateKiller_forminator_get_forms',
        'WPForms_page'     => 'duplicateKiller_wpforms_get_forms',
        'Breakdance_page'  => 'duplicateKiller_breakdance_get_forms',
        'Elementor_page'   => 'duplicateKiller_elementor_get_forms',
        'Formidable_page'  => 'duplicateKiller_formidable_get_forms',
        'NinjaForms_page'  => 'duplicateKiller_ninjaforms_get_forms',
        'FluentForms_page' => 'duplicateKiller_fluentforms_get_forms',
        'MetForm_page'     => 'duplicateKiller_metform_get_forms',
    );

    if ( ! isset( $getters[ $option_name ] ) || ! function_exists( $getters[ $option_name ] ) ) {
        return $previous;
    }

    $raw_forms = call_user_func( $getters[ $option_name ] );
    $forms     = duplicateKiller_normalize_forms(
        is_array( $raw_forms ) ? $raw_forms : array()
    );

    $requested_value = isset( $input['__dk_free_form_selection'] )
        ? $input['__dk_free_form_selection']
        : '';

    if ( ! is_scalar( $requested_value ) ) {
        add_settings_error(
            $settings_group,
            'dk_invalid_form_selection',
            __( 'The selected form is invalid. No settings were changed.', 'duplicate-killer' ),
            'error'
        );
        return $previous;
    }

    // Exact allowlist matching is safer than transforming a provider's key.
    $requested_key = (string) $requested_value;
    $current_key   = duplicateKiller_free_active_form_key( $forms, $previous, $option_name );
    $has_saved_row = false;

    foreach ( $previous as $previous_value ) {
        if ( is_array( $previous_value ) ) {
            $has_saved_row = true;
            break;
        }
    }

    if ( '' !== $requested_key && ! array_key_exists( $requested_key, $forms ) ) {
        add_settings_error(
            $settings_group,
            'dk_unknown_form_selection',
            __( 'The selected form no longer exists. No settings were changed.', 'duplicate-killer' ),
            'error'
        );
        return $previous;
    }

    $target_key = '' !== $requested_key ? $requested_key : $current_key;

    if ( '' === $target_key && ! $has_saved_row && ! empty( $forms ) ) {
        reset( $forms );
        $target_key = (string) key( $forms );
    }

    if ( '' === $target_key || ! isset( $forms[ $target_key ]['form_id'] ) ) {
        add_settings_error(
            $settings_group,
            'dk_missing_form_selection',
            __( 'Choose an available form before saving.', 'duplicate-killer' ),
            'error'
        );
        return $previous;
    }

    $actual_form_id = $forms[ $target_key ]['form_id'];

    if ( ! is_scalar( $actual_form_id ) || '' === (string) $actual_form_id ) {
        return $previous;
    }

    $switching = '' !== $requested_key && $requested_key !== $current_key;

    if ( $switching ) {
        // A newly selected form starts with its ID only; old settings are discarded.
        $selected_form_values = array( 'form_id' => (string) $actual_form_id );
    } elseif ( isset( $input[ $target_key ] ) && is_array( $input[ $target_key ] ) ) {
        $selected_form_values = $input[ $target_key ];
        $selected_form_values['form_id'] = (string) $actual_form_id;
    } else {
        return $previous;
    }

    $selected_form_name = (string) $target_key;

    // ---------------------------------------------------------------------
    // (A) Run delete_records FIRST, but only for the selected form
    // ---------------------------------------------------------------------
    if (is_string($selected_form_name) && is_array($selected_form_values)) {
        if (!empty($selected_form_values['delete_records']) && (string)$selected_form_values['delete_records'] === '1') {
            if (current_user_can('manage_options')) {
                duplicateKiller_delete_saved_entries($plugin_key, $selected_form_name);

                add_settings_error(
                    $settings_group,
                    'deleted_' . sanitize_key($selected_form_name),
                    __('Deleted saved entries for this form.', 'duplicate-killer'),
                    'updated'
                );
            }
        }
    }

    // ---------------------------------------------------------------------
    // (B) If you block saving when unlicensed, do it after deletion
    // ---------------------------------------------------------------------
    if (function_exists('duplicateKiller_block_save_if_unlicensed')) {
        $blocked = duplicateKiller_block_save_if_unlicensed($option_name);
        if (is_array($blocked)) {
            return $blocked;
        }
    }

    // ---------------------------------------------------------------------
    // (C) Build cleaned option output
    // ---------------------------------------------------------------------
    $clean = [];

    // Global keys (top-level settings like cf7_save_image)
    foreach ($global_keys as $gk) {
        // Default missing checkboxes to 0
        if (!isset($input[$gk])) {
            $clean[$gk] = '0';
            continue;
        }

        $clean[$gk] = is_scalar( $input[ $gk ] )
            ? sanitize_text_field( (string) $input[ $gk ] )
            : '0';
        if ($clean[$gk] !== '1') {
            $clean[$gk] = '0';
        }
    }

    // No valid form row posted
    if (!is_string($selected_form_name) || !is_array($selected_form_values)) {
        return $clean;
    }

    $form_name = $selected_form_name;
    $values    = $selected_form_values;
    $row       = [];

    // 1) Sanitize scalar keys
    foreach ($known_scalar_keys as $k) {
        if (!array_key_exists($k, $values)) {
            continue;
        }

        // delete_records is one-shot: never store it
        if ($k === 'delete_records') {
            continue;
        }

        // labels is an array (Formidable/Ninja meta). Handle it later.
        if ($k === 'labels') {
            continue;
        }

        // Normalize boolean checkboxes
        if ( 'cross_form_option' === $k ) {
            $row[ $k ] = '0';
            continue;
        }

        if ( in_array( $k, array( 'cookie_option', 'user_ip' ), true ) ) {
            $row[ $k ] = is_scalar( $values[ $k ] ) && '1' === (string) $values[ $k ]
                ? '1'
                : '0';
            continue;
        }

        // posted field meta for cross-form generation
        if ($k === '__dk_field_type' || $k === '__dk_field_order') {
            continue;
        }
		
		if ( 'error_message_type' === $k ) {
				$val = sanitize_text_field( (string) $values[ $k ] );
				$row[ $k ] = 'modern' === $val ? 'modern' : 'classic';
				continue;
			}

		if ( 'modern_error_message' === $k ) {
			$val = wp_kses_post( (string) $values[ $k ] );

			if ( '' === trim( $val ) ) {
				continue;
			}
			$row[ $k ] = $val;
			continue;
		}

        $val = sanitize_text_field((string) $values[$k]);

        // If admin clears a "defaultable" field, remove it so UI falls back to default
        if (in_array($k, $defaultable_keys, true) && $val === '') {
            continue;
        }

		if ( 'field_duplicate_block_hours' === $k ) {
			$row[ $k ] = (string) max( 0, min( 23, (int) $val ) );
			continue;
		}

		if ( 'field_duplicate_block_minutes' === $k ) {
			$row[ $k ] = (string) max( 0, min( 59, (int) $val ) );
			continue;
		}
		if ( 'user_ip_hours' === $k ) {
			$row[ $k ] = (string) max( 0, min( 23, (int) $val ) );
			continue;
		}

		if ( 'user_ip_minutes' === $k ) {
			$row[ $k ] = (string) max( 0, min( 59, (int) $val ) );
			continue;
		}
        // Enforce numeric-only for days
        if (in_array($k, ['user_ip_days', 'cookie_option_days', 'field_duplicate_block_days'], true)) {
                $val = preg_replace('/[^0-9]/', '', $val);
                if ($val === '') {
                    // don't store empty; allow default fallback
                    continue;
                }
            }

        $row[$k] = $val;
    }

    $allowed_field_ids = array();

    foreach ( $forms[ $selected_form_name ]['fields'] as $field ) {
        if ( isset( $field['id'] ) && is_scalar( $field['id'] ) ) {
            $allowed_field_ids[] = (string) $field['id'];
        }
    }
    // (Formidable + Ninja Forms) Capture posted labels map (field_id => label)
    $supports_labels = (
        $plugin_key === 'Formidable' || $option_name === 'Formidable_page' ||
        $plugin_key === 'NinjaForms' || $option_name === 'NinjaForms_page'
    );

    $posted_labels = [];

    if ($supports_labels && !empty($values['labels']) && is_array($values['labels'])) {
        foreach ($values['labels'] as $fid => $lbl) {
            if (!is_numeric($fid)) {
                continue;
            }

            $fid = (int) $fid;
            if (
                $fid <= 0
                || ! in_array( (string) $fid, $allowed_field_ids, true )
                || ! is_scalar( $lbl )
            ) {
                continue;
            }

            $posted_labels[ $fid ] = sanitize_text_field( (string) $lbl );
        }
    }

    // 2) Treat remaining keys as "field checkboxes"
    foreach ($values as $k => $v) {
        if (in_array($k, $known_scalar_keys, true)) {
            continue;
        }

        if (
            in_array( (string) $k, $allowed_field_ids, true )
            && is_scalar( $v )
            && '1' === (string) $v
        ) {
            $row[ $k ] = 1;
        }
    }

    // (Formidable/Ninja) Store labels for ALL fields
    if ($supports_labels && !empty($posted_labels)) {
        $row['labels'] = $posted_labels;
    }

    $row['form_id'] = (string) $actual_form_id;
    $clean[ (string) $form_name ] = $row;

    return $clean;
}