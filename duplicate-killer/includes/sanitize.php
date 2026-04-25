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

    if (!is_array($input)) {
        return [];
    }

    $defaults   = duplicateKiller_get_form_defaults();
    $plugin_key = $db_plugin_key !== '' ? $db_plugin_key : duplicateKiller_option_to_db_plugin_key($option_name);

    // These keys are NOT "fields", they are per-form settings
    $known_scalar_keys = [
        'form_id',
        'error_message',
        'error_message_limit_ip_option',
        'user_ip_days',
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

    // ---------------------------------------------------------------------
    // Pick only the first valid form row from posted input
    // ---------------------------------------------------------------------
    $selected_form_name   = null;
    $selected_form_values = null;

    foreach ($input as $form_name => $values) {
        if (!is_array($values)) {
            continue;
        }

        $selected_form_name   = (string) $form_name;
        $selected_form_values = $values;
        break;
    }

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

        $clean[$gk] = sanitize_text_field((string) $input[$gk]);
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
        if (in_array($k, ['cookie_option', 'user_ip', 'cross_form_option'], true)) {
            $row[$k] = !empty($values[$k]) ? '1' : '0';
            continue;
        }

        // posted field meta for cross-form generation
        if ($k === '__dk_field_type' || $k === '__dk_field_order') {
            continue;
        }

        $val = sanitize_text_field((string) $values[$k]);

        // If admin clears a "defaultable" field, remove it so UI falls back to default
        if (in_array($k, $defaultable_keys, true) && $val === '') {
            continue;
        }

        // Enforce numeric-only for days
        if (in_array($k, ['user_ip_days', 'cookie_option_days'], true)) {
            $val = preg_replace('/[^0-9]/', '', $val);
            if ($val === '') {
                continue;
            }
        }

        $row[$k] = $val;
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
            if ($fid <= 0) {
                continue;
            }

            $posted_labels[$fid] = sanitize_text_field((string) $lbl);
        }
    }

    // 2) Treat remaining keys as "field checkboxes"
    foreach ($values as $k => $v) {
        if (in_array($k, $known_scalar_keys, true)) {
            continue;
        }

        if (!empty($v) && (string) $v === '1') {
            $row[$k] = 1;
        }
    }

    // (Formidable/Ninja) Store labels for ALL fields
    if ($supports_labels && !empty($posted_labels)) {
        $row['labels'] = $posted_labels;
    }

    $clean[(string) $form_name] = $row;

    return $clean;
}