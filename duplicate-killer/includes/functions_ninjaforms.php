<?php
defined( 'ABSPATH' ) or die( 'You shall not pass!' );

/**
 * DK: Ninja Forms hotfix for "Undefined array key payment_total_type"
 * Runs on action settings right before actions execute.
 */
add_action('ninja_forms_loaded', function () {
    add_filter('ninja_forms_run_action_settings', 'duplicateKiller_nf_hotfix_payment_total_type', 1, 3);
});

/**
 * @param array $settings  Action settings array.
 * @param int   $action_id Action ID.
 * @param int   $form_id   Form ID.
 * @return array
 */
function duplicateKiller_nf_hotfix_payment_total_type(array $settings, int $action_id, int $form_id): array
{
    // If Ninja Forms stores an empty payment_total but forgets payment_total_type,
    // it can trigger PHP warnings in MergeTags processing. Ensure the key exists.
    if (array_key_exists('payment_total', $settings) && !array_key_exists('payment_total_type', $settings)) {
        $settings['payment_total_type'] = '';
    }

    return $settings;
}

add_filter('ninja_forms_submit_data', 'duplicateKiller_ninjaforms_before_send_email', 10, 1);

function duplicateKiller_ninjaforms_before_send_email($form_data) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'dk_forms_duplicate';

    $ninja_page = get_option('NinjaForms_page');
	$ninja_page = duplicateKiller_convert_option_architecture( $ninja_page, 'ninjaforms_' );
    if (!is_array($ninja_page)) {
        return $form_data;
    }

    $request_debug_id = uniqid('duplicateKiller_ninja_', true);
    $dk_enabled       = class_exists('duplicateKiller_Diagnostics');

    if ($dk_enabled) {
        duplicateKiller_Diagnostics::log('ninja', 'process_start', [
            'request_debug_id' => $request_debug_id,
            'form_data'        => $form_data,
        ]);
    }

    if (empty($form_data) || !is_array($form_data)) {
        return $form_data;
    }

    // Form ID
    $form_id = 0;
    if (isset($form_data['id'])) {
        $form_id = (int)$form_data['id'];
    } elseif (isset($form_data['form_id'])) {
        $form_id = (int)$form_data['form_id'];
    }

    if ($form_id <= 0) {
        return $form_data;
    }

    $settings = (isset($form_data['settings']) && is_array($form_data['settings'])) ? $form_data['settings'] : [];

    $form_key = '';
    if (!empty($settings['key'])) {
        $form_key = trim((string)$settings['key']);
    }

    $title = '';
    if (!empty($settings['title'])) {
        $title = trim((string)$settings['title']);
    }

    if ($form_key === '') {
        $form_key = sanitize_key($title !== '' ? $title : ('form-' . $form_id));
    }

    if ($form_key === '') {
        return $form_data;
    }

    $wanted_form_id = $form_key . '.' . $form_id;

    if (empty($ninja_page[$wanted_form_id]) || !is_array($ninja_page[$wanted_form_id])) {
        return $form_data;
    }

    $cfg = $ninja_page[$wanted_form_id];

    if (empty($cfg['form_id'])) {
        return $form_data;
    }

    $form_name = (string)$cfg['form_id'];

    if ($dk_enabled) {
        duplicateKiller_Diagnostics::log('ninja', 'config_resolved', [
            'request_debug_id' => $request_debug_id,
            'form_name'        => $form_name,
            'config'           => $cfg,
        ]);
    }

    $cookie = duplicateKiller_get_form_cookie_simple(
        $ninja_page,
        $form_name,
        'dk_form_cookie_ninja_forms_'
    );

    $form_cookie    = $cookie['form_cookie'];
    $checked_cookie = $cookie['checked_cookie'];

    if ($dk_enabled) {
        duplicateKiller_Diagnostics::log('ninja', 'cookie_state_resolved', [
            'request_debug_id' => $request_debug_id,
            'form_cookie'      => $form_cookie,
            'checked_cookie'   => $checked_cookie,
        ]);
    }

    if (!isset($form_data['errors']) || !is_array($form_data['errors'])) {
        $form_data['errors'] = [];
    }
    if (!isset($form_data['errors']['fields']) || !is_array($form_data['errors']['fields'])) {
        $form_data['errors']['fields'] = [];
    }

    $fields = (!empty($form_data['fields']) && is_array($form_data['fields'])) ? $form_data['fields'] : [];
    if (empty($fields)) {
        return $form_data;
    }

    // =========================
    // 1) IP check
    // =========================
    if (duplicateKiller_ip_limit_trigger(DK_NINJA_FORMS, $ninja_page, $form_name)) {

        $message = !empty($cfg['error_message_limit_ip_option'])
            ? (string)$cfg['error_message_limit_ip_option']
            : 'This IP has been already submitted.';

        if ($dk_enabled) {
            duplicateKiller_Diagnostics::log('ninja', 'ip_limit_blocked', [
                'request_debug_id' => $request_debug_id,
                'message'          => $message,
            ]);
        }

        $first_fid = duplicateKiller_nf_get_first_field_id($fields);

        if ($first_fid > 0) {
            $form_data['errors']['fields'][$first_fid] = $message;
        }

        return $form_data;
    }

    // =========================
    // 2) Duplicate check
    // =========================
    $storage_fields = [];

    $duplicate_message = !empty($cfg['error_message'])
        ? (string)$cfg['error_message']
        : 'Please check all fields! These values have been submitted already!';
	$has_active_duplicate_field = false;
    foreach ($fields as $k => $field) {

        $fid = 0;
        if (is_numeric($k)) {
            $fid = (int)$k;
        } elseif (is_array($field) && isset($field['id']) && is_numeric($field['id'])) {
            $fid = (int)$field['id'];
        }

        if ($fid <= 0) continue;

        $submitted_value = '';
        if (is_array($field) && array_key_exists('value', $field)) {
            $submitted_value = $field['value'];
        }

        if (is_array($submitted_value)) {
            $submitted_value = reset($submitted_value);
        }

        $submitted_value = is_string($submitted_value) ? wp_unslash($submitted_value) : $submitted_value;
        $submitted_value = is_scalar($submitted_value) ? sanitize_text_field((string)$submitted_value) : '';

        $storage_fields[$fid] = $submitted_value;

        if ($dk_enabled) {
            duplicateKiller_Diagnostics::log('ninja', 'field_inspected', [
                'request_debug_id' => $request_debug_id,
                'field_id'         => $fid,
                'value'            => $submitted_value,
                'enabled'          => !empty($cfg[$fid]) ? 1 : 0,
            ]);
        }

        if (empty($cfg[$fid]) || (int)$cfg[$fid] !== 1) {
            continue;
        }
		$has_active_duplicate_field = true;

        $is_dup = duplicateKiller_check_duplicate_by_key_value(
            DK_NINJA_FORMS,
            $form_name,
            $fid,
            $submitted_value,
            $form_cookie,
            $checked_cookie
        );

        if ($dk_enabled) {
            duplicateKiller_Diagnostics::log('ninja', 'field_duplicate_check_result', [
                'request_debug_id' => $request_debug_id,
                'field_id'         => $fid,
                'duplicate'        => $is_dup ? 1 : 0,
            ]);
        }

        if ($is_dup) {

            if ($dk_enabled) {
                duplicateKiller_Diagnostics::log('ninja', 'duplicate_found', [
                    'request_debug_id' => $request_debug_id,
                    'field_id'         => $fid,
                ]);
            }

            $form_data['errors']['form'] = $duplicate_message;
            $form_data['errors']['fields'][$fid] = $duplicate_message;

            return $form_data;
        }
    }

    // =========================
    // 3) Pro feature Cross-form
    // =========================

    // =========================
    // 4) Save
    // =========================
	$should_save_submission = (
		$has_active_duplicate_field ||
		(!empty($cfg['user_ip']) && (int)$cfg['user_ip'] > 0)
	);

	if (!$should_save_submission) {
		if ($dk_enabled) {
			duplicateKiller_Diagnostics::log('ninja', 'save_skipped_no_active_rules', [
				'request_debug_id'            => $request_debug_id,
				'form_name'                   => $form_name,
				'has_active_duplicate_field'  => $has_active_duplicate_field ? 1 : 0,
				'user_ip_enabled'             => (!empty($cfg['user_ip']) && (int)$cfg['user_ip'] > 0) ? 1 : 0,
			]);
		}

		return $form_data;
	}
    $form_ip = (!empty($cfg['user_ip']) && (int)$cfg['user_ip'] > 0)
        ? duplicateKiller_get_user_ip()
        : 'NULL';

    $form_value = serialize($storage_fields);
    $form_date  = current_time('Y-m-d H:i:s');

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
    $insert_result = $wpdb->insert(
        $table_name,
        [
            'form_plugin' => DK_NINJA_FORMS,
            'form_name'   => $form_name,
            'form_value'  => $form_value,
            'form_cookie' => $form_cookie,
            'form_date'   => $form_date,
            'form_ip'     => $form_ip,
        ]
    );

    if ($dk_enabled) {
        duplicateKiller_Diagnostics::log('ninja', 'save_after_insert', [
            'request_debug_id' => $request_debug_id,
            'insert_ok'        => empty($wpdb->last_error) && false !== $insert_result ? 1 : 0,
            'wpdb_last_error'  => $wpdb->last_error,
        ]);
    }

    return $form_data;
}

function duplicateKiller_nf_get_first_field_id(array $fields): int {
    foreach ($fields as $k => $field) {
        if (is_numeric($k)) return (int)$k;
        if (is_array($field) && isset($field['id']) && is_numeric($field['id'])) {
            return (int)$field['id'];
        }
    }
    return 0;
}

/**
 * Helper: is Ninja Forms present & ready for this request?
 *
 * - Detects Ninja Forms core (Ninja_Forms() function or main class)
 * - Ensures we're past the plugin bootstrap stage (plugins_loaded)
 * - Uses a soft readiness check (Ninja Forms doesn't expose a single universal "loaded" action like some plugins)
 */
function duplicateKiller_ninjaforms_is_ready(): bool {

    // 1) Is Ninja Forms available (core)?
    // Prefer the public function Ninja_Forms(), but also allow class-based detection.
    if (
        ! function_exists('Ninja_Forms') &&
        ! class_exists('Ninja_Forms')
    ) {
        return false;
    }

    // 2) Are we past the plugin bootstrap?
    // If we're running too early, Ninja Forms might not be initialized yet.
    if ( did_action('plugins_loaded') <= 0 ) {
        return false;
    }

    // 3) If the function exists, ensure it returns a usable object.
    // This is a practical readiness gate used in many integrations.
    if ( function_exists('Ninja_Forms') ) {
        $nf = Ninja_Forms();
        if ( ! is_object($nf) ) {
            return false;
        }

        // Optional: check that the form API is available (guards against partial loads).
        // Avoid being overly strict; if a method changes, we don't want to hard-fail.
        if ( ! method_exists($nf, 'form') ) {
            return true;
        }

        $form_api = $nf->form();
        if ( ! is_object($form_api) || ! method_exists($form_api, 'get_forms') ) {
            // Soft-pass: NF is present, but the form API isn't accessible right now.
            // In most contexts this is still fine, so return true.
            return true;
        }
    }

    return true;
}
function duplicateKiller_ninjaforms_get_forms(): array {
    $out = [];

    // Ninja Forms not active
    if ( ! function_exists('Ninja_Forms') || ! is_object(Ninja_Forms()) ) {
        return [];
    }

    // Map Ninja Forms field types -> your internal types
    $allowed_types_map = [
        'textbox'  => 'text',
        'textarea' => 'textarea',
        'email'    => 'email',
        'phone'    => 'phone',
        'tel'      => 'phone',
        'url'      => 'url',
        'text'     => 'text',
    ];

    // Get forms (models)
    $forms = Ninja_Forms()->form()->get_forms(); // API pattern :contentReference[oaicite:1]{index=1}
    if ( empty($forms) || ! is_array($forms) ) {
        return [];
    }

    foreach ( $forms as $form_model ) {

        if ( ! is_object($form_model) || ! method_exists($form_model, 'get_id') ) {
            continue;
        }

        $form_id = (int) $form_model->get_id();
        if ( $form_id <= 0 ) continue;

        // Title/name from model setting (common approach) :contentReference[oaicite:2]{index=2}
        $name = '';
        if ( method_exists($form_model, 'get_setting') ) {
            $name = (string) $form_model->get_setting('title');
        }
        $name = trim($name);
        if ( $name === '' ) $name = 'Form #' . $form_id;

        // Build stable key + compound id: form_key.id
        $form_key = sanitize_key($name);
        if ( $form_key === '' ) $form_key = 'form-' . $form_id;

        $form_id_compound = $form_key . '.' . $form_id;
        $display_key      = $form_id_compound;

        $out[$display_key] = [
            'form_id'   => $form_id_compound,
            'form_name' => $name,
            'fields'    => [],
        ];

        /**
         * IMPORTANT:
         * get_forms() often returns "light" models (no fields hydrated).
         * So we fetch fields via the form factory for this specific form id.
         */
        $fields = [];

        // Preferred: load fields from the specific form model/factory (common in examples/docs) :contentReference[oaicite:3]{index=3}
        try {
            // Some installs expose get_fields() on the form model returned by Ninja_Forms()->form($id)
            $form_instance = Ninja_Forms()->form($form_id);

            if ( is_object($form_instance) && method_exists($form_instance, 'get_fields') ) {
                $fields = $form_instance->get_fields();
            } elseif ( is_object($form_instance) && method_exists($form_instance, 'get') ) {
                // Fallback: try to read fields from the form array
                $data = $form_instance->get();
                if ( is_array($data) && isset($data['fields']) && is_array($data['fields']) ) {
                    $fields = $data['fields'];
                }
            }
        } catch (Exception $e) {
            $fields = [];
        }

        if ( empty($fields) || ! is_array($fields) ) {
            continue;
        }

        foreach ( $fields as $field ) {

            // Case A: Field is a model object with settings :contentReference[oaicite:4]{index=4}
            if ( is_object($field) && method_exists($field, 'get_id') && method_exists($field, 'get_settings') ) {
                $fid = (int) $field->get_id();
                if ( $fid <= 0 ) continue;

                $settings = $field->get_settings(); // documented pattern :contentReference[oaicite:5]{index=5}
                if ( ! is_array($settings) ) continue;

                $nf_type = strtolower((string)($settings['type'] ?? ''));
                if ( $nf_type === '' || ! isset($allowed_types_map[$nf_type]) ) continue;

                $label = trim((string)($settings['label'] ?? ''));
                if ( $label === '' ) $label = ucfirst($allowed_types_map[$nf_type]) . ' #' . $fid;

                $out[$display_key]['fields'][] = [
                    'id'    => $fid, // numeric NF field id
                    'type'  => $allowed_types_map[$nf_type],
                    'label' => $label,
                ];

                continue;
            }

            // Case B: Field is an array (when loaded from form array)
            if ( is_array($field) ) {
                $fid = isset($field['id']) ? (int)$field['id'] : 0;
                if ( $fid <= 0 ) continue;

                $settings = $field['settings'] ?? [];
                if ( ! is_array($settings) ) continue;

                $nf_type = strtolower((string)($settings['type'] ?? ''));
                if ( $nf_type === '' || ! isset($allowed_types_map[$nf_type]) ) continue;

                $label = trim((string)($settings['label'] ?? ''));
                if ( $label === '' ) $label = ucfirst($allowed_types_map[$nf_type]) . ' #' . $fid;

                $out[$display_key]['fields'][] = [
                    'id'    => $fid,
                    'type'  => $allowed_types_map[$nf_type],
                    'label' => $label,
                ];
            }
        }

        // Deduplicate by field id
        if ( ! empty($out[$display_key]['fields']) ) {
            $seen  = [];
            $clean = [];
            foreach ( $out[$display_key]['fields'] as $ff ) {
                $fid = (int)($ff['id'] ?? 0);
                if ( $fid <= 0 ) continue;
                if ( isset($seen[$fid]) ) continue;
                $seen[$fid] = true;
                $clean[] = $ff;
            }
            $out[$display_key]['fields'] = array_values($clean);
        }
    }

    return $out;
}

function duplicateKiller_ninjaforms_description() {

    // Check if Ninja Forms is present and ready
    if ( ! duplicateKiller_ninjaforms_is_ready() ) {
        echo '<h3 style="color:red"><strong>' .
            esc_html__('Ninja Forms is not activated! Please activate it in order to continue.', 'duplicate-killer') .
        '</strong></h3>';
        exit();
    }

    // Success message (optional, same behavior as original)
    echo '<h3 style="color:green"><strong>' .
        esc_html__('Ninja Forms is activated!', 'duplicate-killer') .
    '</strong></h3>';

    // Fetch Ninja Forms
    $forms = duplicateKiller_ninjaforms_get_forms();

    if ( empty($forms) ) {
        echo '<br/><span style="color:red"><strong>' .
            esc_html__('There are no Ninja Forms available. Please create at least one form!', 'duplicate-killer') .
        '</strong></span>';
        exit();
    }
}

/*********************************
 * Callbacks
**********************************/
function duplicateKiller_ninjaforms_validate_input($input) {
    return duplicateKiller_sanitize_forms_option($input, 'NinjaForms_page', 'NinjaForms_page', [], 'Ninja Forms');
}

function duplicateKiller_ninjaforms_settings_callback($args){
	$options = get_option($args[0]);

}
function duplicateKiller_ninjaforms_select_form_tag_callback($args){
    duplicateKiller_render_forms_overview([
        'option_name'   => (string)$args[0],   // Formidable_page
        'db_plugin_key' => DK_NINJA_FORMS,
        'plugin_label'  => DK_NINJA_FORMS_LABEL,
        'forms'         => duplicateKiller_ninjaforms_get_forms(),
        'forms_id_map'  => [], // optional
    ]);
}