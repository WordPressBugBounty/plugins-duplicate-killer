<?php
defined( 'ABSPATH' ) or die( 'You shall not pass!' );

add_filter('frm_validate_entry', 'duplicateKiller_formidable_before_send_email', 10, 2);
function duplicateKiller_formidable_before_send_email( $errors, $values ) {
	$formidable_page = get_option( 'Formidable_page' );
	$formidable_page = duplicateKiller_convert_option_architecture( $formidable_page, 'formidable_' );
	if ( ! is_array( $formidable_page ) ) {
		return $errors;
	}

	if ( empty( $values['frm_action'] ) || (string) $values['frm_action'] !== 'create' ) {
		return $errors;
	}

	$request_debug_id = uniqid( 'duplicateKiller_formidable_', true );
	$dk_enabled       = class_exists( 'duplicateKiller_Diagnostics' );

	$current_form = DuplicateKiller_Form_Normalizer::formidable( $values );

	$resolved_form = DuplicateKiller_Form_Config_Resolver::resolve_formidable(
		$formidable_page,
		$current_form
	);

	if ( false === $resolved_form ) {
		if ( $dk_enabled ) {
			duplicateKiller_Diagnostics::log( 'formidable', 'form_config_not_found', array(
				'request_debug_id' => $request_debug_id,
				'current_form'     => $current_form,
				'values'           => $values,
			) );
		}
		return $errors;
	}

	$form_name      = $resolved_form['form_name'];
	$form_config    = $resolved_form['form_config'];
	$enabled_fields = $resolved_form['enabled_fields'];
	$data           = DuplicateKiller_Form_Normalizer::formidable_data( $values );

	$ip_limit_enabled    = ! empty( $form_config['user_ip'] ) && (string) $form_config['user_ip'] === '1';
	$field_check_enabled = ! empty( $enabled_fields );
	$cross_form_enabled  = ! empty( $form_config['cross_form_option'] ) && (string) $form_config['cross_form_option'] === '1';

	if ( ! $ip_limit_enabled && ! $field_check_enabled && ! $cross_form_enabled ) {
		if ( $dk_enabled ) {
			duplicateKiller_Diagnostics::log( 'formidable', 'no_duplicate_killer_feature_enabled', array(
				'request_debug_id' => $request_debug_id,
				'form_name'        => $form_name,
				'form_config'      => $form_config,
			) );
		}
		return $errors;
	}

	if ( $dk_enabled ) {
		duplicateKiller_Diagnostics::log( 'formidable', 'process_start', array(
			'request_debug_id'    => $request_debug_id,
			'current_form'        => $current_form,
			'form_name'           => $form_name,
			'form_config'         => $form_config,
			'enabled_fields'      => $enabled_fields,
			'ip_limit_enabled'    => $ip_limit_enabled ? 1 : 0,
			'field_check_enabled' => $field_check_enabled ? 1 : 0,
			'cross_form_enabled'  => $cross_form_enabled ? 1 : 0,
			'data'                => $data,
		) );
	}

	if ( $ip_limit_enabled ) {
		$ip_limit_result = DuplicateKiller_IP_Limit_Checker::check(
			'Formidable',
			$form_name,
			$form_config,
			array(
				'request_debug_id' => $request_debug_id,
				'form_name'        => $form_name,
			)
		);

		if ( $ip_limit_result['blocked'] ) {
			$errors['frm_error'] = $ip_limit_result['message'];
			return $errors;
		}
	}

	$cookie = duplicateKiller_get_form_cookie_simple(
		$formidable_page,
		$form_name,
		'dk_form_cookie_formidable_'
	);

	$form_cookie    = $cookie['form_cookie'];
	$checked_cookie = $cookie['checked_cookie'];

	if ( $field_check_enabled ) {
		$result = DuplicateKiller_FieldDuplicate_Checker::check(
			'Formidable',
			$form_name,
			$enabled_fields,
			$data,
			$form_cookie,
			$checked_cookie,
			$form_config,
			array(
				'request_debug_id' => $request_debug_id,
				'form_name'        => $form_name,
			)
		);

		if ( $result['blocked'] ) {
			$errors['form'] = $result['message'];
			$errors[$result['field_key'] ] = $result['message'];
			return $errors;
		}
	}

	if (
		$cross_form_enabled
		&& class_exists( 'DuplicateKiller_CrossForm' )
	) {
		$cross_match = DuplicateKiller_CrossForm::checkAssocCrossFormDuplicate(
			'Formidable',
			$formidable_page,
			$form_name,
			$form_config,
			$data
		);

		if ( $cross_match ) {
			$message = ! empty( $form_config['error_message'] )
				? (string) $form_config['error_message']
				: __( 'Please check all fields! These values have been submitted already!', 'duplicate-killer' );

			$matched_field_id = $cross_match['current_field_id'] ?? '';

			$errors['form'] = $message;

			if ( '' !== $matched_field_id ) {
				$errors[ (int) $matched_field_id ] = ' ';
			}

			return $errors;
		}
	}

	$should_save_submission = (
		$ip_limit_enabled
		|| $field_check_enabled
		|| $cross_form_enabled
	);

	DuplicateKiller_Submission_Storage::save(
		'Formidable',
		$form_name,
		$data,
		$form_cookie,
		$should_save_submission,
		$ip_limit_enabled,
		array(),
		array(),
		array(
			'request_debug_id' => $request_debug_id,
			'form_name'        => $form_name,
		)
	);

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