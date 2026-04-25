<?php
defined( 'ABSPATH' ) or die( 'You shall not pass!' );

add_filter('frm_validate_entry', 'duplicateKiller_formidable_before_send_email', 10, 2);
function duplicateKiller_formidable_before_send_email($errors, $values) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'dk_forms_duplicate';

    $formidable_page = get_option('Formidable_page');
	$formidable_page = duplicateKiller_convert_option_architecture( $formidable_page, 'formidable_' );
    if (!is_array($formidable_page)) {
        return $errors;
    }

    $request_debug_id = uniqid('duplicateKiller_formidable_', true);
    $dk_enabled       = class_exists('duplicateKiller_Diagnostics');

    // Only handle real create submissions
    if (empty($values['frm_action']) || (string)$values['frm_action'] !== 'create') {
        return $errors;
    }

    $form_id  = !empty($values['form_id']) ? (int)$values['form_id'] : 0;
    $form_key = !empty($values['form_key']) ? trim((string)$values['form_key']) : '';

    if ($dk_enabled) {
        duplicateKiller_Diagnostics::log('formidable', 'process_start', [
            'request_debug_id' => $request_debug_id,
            'form_id'          => $form_id,
            'form_key'         => $form_key,
            'values'           => $values,
        ]);
    }

    if ($form_id <= 0 || $form_key === '') {
        return $errors;
    }

    $wanted_form_id = $form_key . '.' . $form_id;

    if (empty($formidable_page[$wanted_form_id]) || !is_array($formidable_page[$wanted_form_id])) {
        return $errors;
    }

    $cfg = $formidable_page[$wanted_form_id];

    if (empty($cfg['form_id'])) {
        return $errors;
    }

    $form_name = (string)$cfg['form_id'];

    if ($dk_enabled) {
        duplicateKiller_Diagnostics::log('formidable', 'config_resolved', [
            'request_debug_id' => $request_debug_id,
            'form_name'        => $form_name,
            'config'           => $cfg,
        ]);
    }

    // =========================
    // 1) IP check
    // =========================
    if (duplicateKiller_ip_limit_trigger('Formidable', $formidable_page, $form_name)) {

        $message = !empty($cfg['error_message_limit_ip_option'])
            ? (string)$cfg['error_message_limit_ip_option']
            : 'This IP has been already submitted.';

        if ($dk_enabled) {
            duplicateKiller_Diagnostics::log('formidable', 'ip_limit_blocked', [
                'request_debug_id' => $request_debug_id,
                'form_name'        => $form_name,
                'message'          => $message,
            ]);
        }

        $errors['frm_error'] = $message;
        return $errors;
    }

    // =========================
    // 2) Duplicate field check
    // =========================
    $posted = (!empty($values['item_meta']) && is_array($values['item_meta'])) ? $values['item_meta'] : [];

    if (empty($posted)) {
        return $errors;
    }

    $cookie = duplicateKiller_get_form_cookie_simple(
        $formidable_page,
        $form_name,
        'dk_form_cookie_formidable_'
    );

    $form_cookie    = $cookie['form_cookie'];
    $checked_cookie = $cookie['checked_cookie'];

    if ($dk_enabled) {
        duplicateKiller_Diagnostics::log('formidable', 'cookie_state_resolved', [
            'request_debug_id' => $request_debug_id,
            'form_name'        => $form_name,
            'form_cookie'      => $form_cookie,
            'checked_cookie'   => $checked_cookie,
        ]);
    }

    $storage_fields = [];

    $duplicate_message = !empty($cfg['error_message'])
        ? (string)$cfg['error_message']
        : 'Please check all fields! These values have been submitted already!';
		
	$has_active_duplicate_field = false;
    foreach ($posted as $fid => $val) {

        if (!is_numeric($fid)) continue;
        $fid = (int)$fid;
        if ($fid <= 0) continue;

        $submitted_value = $val;

        if (is_array($submitted_value)) {
            $submitted_value = reset($submitted_value);
        }

        $submitted_value = is_string($submitted_value) ? wp_unslash($submitted_value) : $submitted_value;
        $submitted_value = is_scalar($submitted_value) ? sanitize_text_field((string)$submitted_value) : '';

        $storage_fields[$fid] = $submitted_value;

        if ($dk_enabled) {
            duplicateKiller_Diagnostics::log('formidable', 'field_inspected', [
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
            'Formidable',
            $form_name,
            $fid,
            $submitted_value,
            $form_cookie,
            $checked_cookie
        );

        if ($dk_enabled) {
            duplicateKiller_Diagnostics::log('formidable', 'field_duplicate_check_result', [
                'request_debug_id' => $request_debug_id,
                'field_id'         => $fid,
                'value'            => $submitted_value,
                'duplicate'        => $is_dup ? 1 : 0,
            ]);
        }

        if ($is_dup) {

            if ($dk_enabled) {
                duplicateKiller_Diagnostics::log('formidable', 'duplicate_found', [
                    'request_debug_id' => $request_debug_id,
                    'field_id'         => $fid,
                    'message'          => $duplicate_message,
                ]);
            }

            $errors['form'] = $duplicate_message;
            $errors[$fid]   = $duplicate_message;
            return $errors;
        }
    }

    // =========================
    // 3) Pro feature - Cross-form duplicate
    // =========================
	
	$should_save_submission = (
		$has_active_duplicate_field ||
		(!empty($cfg['user_ip']) && (int)$cfg['user_ip'] > 0)
	);

	if (!$should_save_submission) {
		if ($dk_enabled) {
			duplicateKiller_Diagnostics::log('formidable', 'save_skipped_no_active_rules', [
				'request_debug_id'         => $request_debug_id,
				'form_name'                => $form_name,
				'has_active_duplicate_field' => $has_active_duplicate_field ? 1 : 0,
				'user_ip_enabled'          => (!empty($cfg['user_ip']) && (int)$cfg['user_ip'] > 0) ? 1 : 0,
			]);
		}

		return $errors;
	}
    // =========================
    // 4) Save to DB
    // =========================
    $form_ip = (!empty($cfg['user_ip']) && (int)$cfg['user_ip'] > 0)
        ? duplicateKiller_get_user_ip()
        : 'NULL';

    $form_value = serialize($storage_fields);
    $form_date  = current_time('Y-m-d H:i:s');

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
    $insert_result = $wpdb->insert(
        $table_name,
        [
            'form_plugin' => 'Formidable',
            'form_name'   => $form_name,
            'form_value'  => $form_value,
            'form_cookie' => $form_cookie,
            'form_date'   => $form_date,
            'form_ip'     => $form_ip,
        ]
    );

    if ($dk_enabled) {
        duplicateKiller_Diagnostics::log('formidable', 'save_after_insert', [
            'request_debug_id' => $request_debug_id,
            'insert_ok'        => empty($wpdb->last_error) && false !== $insert_result ? 1 : 0,
            'wpdb_last_error'  => $wpdb->last_error,
            'insert_id'        => $wpdb->insert_id,
        ]);
    }

    return $errors;
}

function duplicateKiller_formidable_get_forms(): array {
    $out = [];

    if ( ! class_exists('FrmForm') ) {
        return [];
    }

    $forms = [];

    if ( method_exists('FrmForm', 'get_published_forms') ) {
        $forms = FrmForm::get_published_forms();
    } elseif ( method_exists('FrmForm', 'getAll') ) {
        $forms = FrmForm::getAll(['is_template' => 0], ' ORDER BY id DESC');
    }

    if ( empty( $forms ) || ! is_array( $forms ) ) {

		global $wpdb;

		$tbl_forms = $wpdb->prefix . 'frm_forms';
		
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Querying Formidable custom table (admin-only, no WP API).
		$forms = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, name, form_key
				 FROM " . esc_sql( $tbl_forms ) . "
				 WHERE is_template = %d
				 ORDER BY id DESC",
				0
			)
		);
	}
    if ( empty($forms) ) return [];

    $allowed_types = ['text', 'textarea', 'email', 'phone', 'url', 'tel'];

    foreach ( $forms as $form ) {

        $id       = 0;      // Numeric Formidable ID
        $name     = '';
        $form_key = '';

        // Extract id/name/form_key from object/array
        if ( is_object($form) ) {
            $id       = isset($form->id) ? (int) $form->id : 0;
            $name     = isset($form->name) ? (string) $form->name : '';
            $form_key = isset($form->form_key) ? (string) $form->form_key : '';
        } elseif ( is_array($form) ) {
            $id       = isset($form['id']) ? (int) $form['id'] : 0;
            $name     = isset($form['name']) ? (string) $form['name'] : '';
            $form_key = isset($form['form_key']) ? (string) $form['form_key'] : '';
        }

        if ( $id <= 0 ) continue;

        $name = trim($name);
        if ( $name === '' ) $name = 'Form #' . $id;

        // Ensure form_key exists (fallback to DB if missing)
		$form_key = trim( $form_key );

		if ( $form_key === '' ) {

			global $wpdb;

			$tbl_forms = $wpdb->prefix . 'frm_forms';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Querying Formidable custom table (admin-only, no WP API).
			$form_key = (string) $wpdb->get_var(
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Querying Formidable custom table (admin-only, no WP API).
				$wpdb->prepare(
					"SELECT form_key FROM " . esc_sql( $tbl_forms ) . " WHERE id = %d LIMIT 1",
					$id
				)
			);

			$form_key = trim( $form_key );
		}

        // Build the "form_id" string requested: form_key.id
        // If form_key is still empty, fallback to "form-{id}.{id}"
        $form_id_compound = ($form_key !== '') ? ($form_key . '.' . $id) : ('form-' . $id . '.' . $id);

        $display_key = $form_id_compound;

        if ( ! isset($out[$display_key]) ) {
            $out[$display_key] = [
                'form_id'   => $form_id_compound, // IMPORTANT: string "form_key.id"
                'form_name' => $name,
                'fields'    => [],
            ];
        }

        // Fetch fields for this form
        $fields = [];

        if ( class_exists('FrmField') && method_exists('FrmField', 'get_all_for_form') ) {
            $fields = FrmField::get_all_for_form($id, '', 'include');
        } else {
            global $wpdb;
            $tbl_fields = $wpdb->prefix . 'frm_fields';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Querying Formidable custom table (admin-only context).
			$fields = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, field_key, name, type
					 FROM " . esc_sql( $tbl_fields ) . "
					 WHERE form_id = %d
					 ORDER BY field_order DESC",
					$id
				)
			);
        }

        if ( empty($fields) ) continue;

        foreach ( $fields as $f ) {
            $type  = '';
            $label = '';
            $key   = '';
            $fid   = 0;

            if ( is_object($f) ) {
                $type  = isset($f->type) ? strtolower((string)$f->type) : '';
                $label = isset($f->name) ? (string)$f->name : '';
                $key   = isset($f->field_key) ? (string)$f->field_key : '';
                $fid   = isset($f->id) ? (int)$f->id : 0;
            } elseif ( is_array($f) ) {
                $type  = isset($f['type']) ? strtolower((string)$f['type']) : '';
                $label = isset($f['name']) ? (string)$f['name'] : '';
                $key   = isset($f['field_key']) ? (string)$f['field_key'] : '';
                $fid   = isset($f['id']) ? (int)$f['id'] : 0;
            }

            if ( $type === '' ) continue;

            // Normalize tel => phone
            if ( $type === 'tel' ) $type = 'phone';

            // Only include allowed types
            if ( ! in_array($type, $allowed_types, true) ) {
                continue;
            }

            $label = trim($label);

            // Field id: prefer field_key
            $field_id = '';
            if ( $key !== '' ) {
                $field_id = sanitize_key($key);
            } elseif ( $fid > 0 ) {
                $field_id = 'field_' . $fid;
            } else {
                $field_id = sanitize_key($label);
            }

            if ( $field_id === '' ) continue;

           $out[$display_key]['fields'][] = [
				// field_key (e.g. 29yf4d2)
				// numeric frm_fields.id (IMPORTANT)
				//29yf4d2.id
				'id'    => $fid,
				'type'  => $type,
				'label' => $label,
			];
        }
        // Deduplicate fields by id
        if ( ! empty($out[$display_key]['fields']) ) {
            $seen  = [];
            $clean = [];
            foreach ( $out[$display_key]['fields'] as $ff ) {
                if ( isset($seen[$ff['id']]) ) continue;
                $seen[$ff['id']] = true;
                $clean[] = $ff;
            }
            $out[$display_key]['fields'] = array_values($clean);
        }
    }
	/*
	echo '<pre>';
	print_r($out);
	echo '</pre>';
	die();
	*/
    return $out;
}



/*********************************
 * Callbacks
**********************************/
function duplicateKiller_formidable_validate_input($input) {
    return duplicateKiller_sanitize_forms_option($input, 'Formidable_page', 'Formidable_page', [], 'formidable');
}
/**
 * Helper: is Formidable Forms present & loaded for this request?
 *
 * - Detectează Formidable Lite (FrmAppHelper / FrmForm)
 * - Detectează Pro (FrmProAppHelper) dacă vrei features Pro
 * - Verifică hook-urile de load/init când există
 */
function duplicateKiller_formidable_is_ready(): bool {

    // 1) Plugin încărcat? (Lite / Core)
    if (
        ! class_exists('FrmAppHelper') &&
        ! class_exists('FrmForm') &&
        ! defined('FRM_VERSION')
    ) {
        return false;
    }

    /**
     * 2) "Loaded" hook (dacă există în build-ul tău)
     * Unele versiuni folosesc 'frm_loaded' / 'frm_after_load' etc.
     * Ca să nu blocăm aiurea, facem check "soft": dacă nu există, nu picăm.
     */
    $loaded = false;

    // cele mai întâlnite în ecosistem
    if ( did_action('frm_loaded') > 0 ) {
        $loaded = true;
    } elseif ( did_action('frm_after_load') > 0 ) {
        $loaded = true;
    } elseif ( did_action('plugins_loaded') > 0 ) {
        // fallback: dacă ajungem aici și clasele există, în practică e ok
        $loaded = true;
    }

    if ( ! $loaded ) {
        return false;
    }

    return true;
}
function duplicateKiller_formidable_description(){
	if (!duplicateKiller_formidable_is_ready()) {
        echo '<h3 style="color:red"><strong>' . esc_html__('Formidable Forms is not activated! Please activate it in order to continue.', 'duplicate-killer') . '</strong></h3>';
		exit();
    }

    // If you need a success message, keep this. If not, remove it.
    echo '<h3 style="color:green"><strong>' . esc_html__('Formidable Forms is activated!', 'duplicate-killer') . '</strong></h3>';

    $forms = duplicateKiller_formidable_get_forms();
    if (empty($forms)) {
        echo '<br/><span style="color:red"><strong>' . esc_html__('There is no contact form. Please create one!', 'duplicate-killer') . '</strong></span>';
		exit();
    }
}

function duplicateKiller_formidable_settings_callback($args){
	$options = get_option($args[0]);

}
function duplicateKiller_formidable_select_form_tag_callback($args){
    duplicateKiller_render_forms_overview([
        'option_name'   => (string)$args[0],   // Formidable_page
        'db_plugin_key' => 'formidable',
        'plugin_label'  => 'Formidable',
        'forms'         => duplicateKiller_formidable_get_forms(),
        'forms_id_map'  => [], // optional
    ]);
}