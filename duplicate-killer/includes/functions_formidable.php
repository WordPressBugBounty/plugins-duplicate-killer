<?php
defined( 'ABSPATH' ) or die( 'You shall not pass!' );

add_filter('frm_validate_entry', 'duplicateKiller_formidable_before_send_email', 10, 2);
function duplicateKiller_formidable_before_send_email($errors, $values) {
	global $wpdb;

	$table_name = $wpdb->prefix . 'dk_forms_duplicate';

	$formidable_page = get_option('Formidable_page');
	if (!is_array($formidable_page)) {
		return $errors;
	}

	// Only handle real create submissions
	if (empty($values['frm_action']) || (string)$values['frm_action'] !== 'create') {
		return $errors;
	}

	$form_id  = !empty($values['form_id']) ? (int)$values['form_id'] : 0;
	$form_key = !empty($values['form_key']) ? trim((string)$values['form_key']) : '';
	if ($form_id <= 0 || $form_key === '') {
		return $errors;
	}

	// Form identifier (e.g. "contact-us.2")
	$wanted_form_id = $form_key . '.' . $form_id;

	// Config is keyed by wanted_form_id directly (e.g. $formidable_page['contact-us.2'])
	if (empty($formidable_page[$wanted_form_id]) || !is_array($formidable_page[$wanted_form_id])) {
		return $errors;
	}
	$cfg = $formidable_page[$wanted_form_id];

	// Use stored form identifier everywhere
	$form_name = $wanted_form_id; // "contact-us.2"

	// Cookie id (whatever your helper does)
	$form_cookie = duplicateKiller_get_formidable_cookie_if_enabled($formidable_page);

	// =========================
	// 1) IP check
	// =========================
	if (duplicateKiller_ip_limit_trigger('Formidable', $formidable_page, $form_name)) {
		$message = !empty($formidable_page['formidable_error_message_limit_ip'])
			? (string)$formidable_page['formidable_error_message_limit_ip']
			: 'This IP has been already submitted.';

		$errors['frm_error'] = $message;
		// Increment blocked duplicates counter
		duplicateKiller_increment_duplicates_blocked_count();
		return $errors;
	}

	// =========================
	// 2) Duplicate field check (by FIELD_ID toggles in config)
	// =========================
	$posted = (!empty($values['item_meta']) && is_array($values['item_meta'])) ? $values['item_meta'] : array();
	if (empty($posted)) {
		return $errors;
	}

	$checked_cookie = !empty($formidable_page['formidable_cookie_option']) && (string)$formidable_page['formidable_cookie_option'] === '1';

	// Storage for DB: field_id => value
	$storage_fields = array();

	$duplicate_message = !empty($formidable_page['formidable_error_message'])
		? (string)$formidable_page['formidable_error_message']
		: 'Please check all fields! These values have been submitted already!';

	foreach ($posted as $fid => $val) {
		if (!is_numeric($fid)) {
			continue;
		}
		$fid = (int)$fid;
		if ($fid <= 0) {
			continue;
		}

		// Normalize value (arrays -> first element)
		$submitted_value = $val;
		if (is_array($submitted_value)) {
			$submitted_value = reset($submitted_value);
		}
		$submitted_value = is_string($submitted_value) ? wp_unslash($submitted_value) : $submitted_value;
		$submitted_value = is_scalar($submitted_value) ? sanitize_text_field((string)$submitted_value) : '';

		// Save for DB (always)
		$storage_fields[$fid] = $submitted_value;

		// Only check duplicates if this FIELD_ID is enabled in config (e.g. 8 => "1")
		// Note: labels is stored under $cfg['labels'], we ignore it here.
		if (empty($cfg[$fid]) || (string)$cfg[$fid] !== '1') {
			continue;
		}

		$is_dup = duplicateKiller_check_duplicate_by_key_value(
			'Formidable',
			$form_name, // e.g. "contact-us.2"
			$fid,
			$submitted_value,
			$form_cookie,
			$checked_cookie
		);

		if ($is_dup) {
			$errors['field' . $fid] = $duplicate_message;
			if (empty($errors['frm_error'])) {
				$errors['frm_error'] = $duplicate_message;
				// Increment blocked duplicates counter
				duplicateKiller_increment_duplicates_blocked_count();
			}
			return $errors;
		}
	}

	// =========================
	// 3) Save to DB
	// =========================
	$form_ip = (!empty($formidable_page['formidable_user_ip']) && (string)$formidable_page['formidable_user_ip'] === '1')
		? duplicateKiller_get_user_ip()
		: 'NULL';

	$form_value = serialize($storage_fields);
	$form_date  = current_time('Y-m-d H:i:s');
	
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Inserting into plugin-owned custom table.
	$wpdb->insert(
		$table_name,
		array(
			'form_plugin' => 'Formidable',
			'form_name'   => $form_name,   // "contact-us.2"
			'form_value'  => $form_value,  // field_id => value
			'form_cookie' => $form_cookie,
			'form_date'   => $form_date,
			'form_ip'     => $form_ip,
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

    if ( empty($forms) || ! is_array($forms) ) {
        // Fallback to DB
        global $wpdb;

		$tbl_forms = esc_sql( $wpdb->prefix . 'frm_forms' );
		
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reading Formidable forms from core posts table (admin-only, request-scoped).
		$forms = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is fixed and plugin-controlled.
			"SELECT id, name, form_key FROM {$tbl_forms} WHERE is_template = 0 ORDER BY id DESC"
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
		if ( '' === $form_key ) {
			global $wpdb;

			$tbl_forms = esc_sql( $wpdb->prefix . 'frm_forms' );
			
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reading Formidable forms from core posts table (admin-only, request-scoped).
			$form_key = (string) $wpdb->get_var(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is fixed and plugin-controlled.
					"SELECT form_key FROM {$tbl_forms} WHERE id = %d LIMIT 1", $id
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
			$tbl_fields = esc_sql( $wpdb->prefix . 'frm_fields' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reading Formidable forms from core posts table (admin-only, request-scoped).
			$fields = $wpdb->get_results(
				$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is fixed and plugin-controlled.
					"SELECT id, field_key, name, type FROM {$tbl_fields} WHERE form_id = %d ORDER BY field_order DESC", $id
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
function duplicateKiller_formidable_validate_input($input){
	global $wpdb;
	$output = array();
	
	// Create our array for storing the validated options (keep numeric field keys, add labels)
	foreach ($input as $form_key => $value) {

		// Only per-form arrays (skip global settings like formidable_cookie_option)
		if (!is_array($value)) {
			continue;
		}

		if (!isset($output[$form_key]) || !is_array($output[$form_key])) {
			$output[$form_key] = array();
		}

		foreach ($value as $field_id => $field_value) {

			// Special: keep labels array for this form
			if ((string)$field_id === 'labels' && is_array($field_value)) {
				$output[$form_key]['labels'] = array();

				foreach ($field_value as $fid => $label) {
					// Keep numeric keys as-is (Formidable field IDs are often numeric)
					if ($fid === '' || $fid === null) {
						continue;
					}
					$output[$form_key]['labels'][$fid] = sanitize_text_field((string)$label);
				}

				continue;
			}

			// Normal checkbox fields: keep only "1"
			// (unchecked fields usually won't be present in $input anyway)
			if ((string)$field_value === "1") {
				$output[$form_key][$field_id] = "1";
			}
		}
	}
	//validate cookies feature
	if(!isset($input['formidable_cookie_option']) || $input['formidable_cookie_option'] !== "1"){
		$output['formidable_cookie_option'] = "0";
	}else{
		$output['formidable_cookie_option'] = "1";
	}
	if(filter_var($input['formidable_cookie_option_days'], FILTER_VALIDATE_INT) === false){
		$output['formidable_cookie_option_days'] = 365;
	}else{
		$output['formidable_cookie_option_days'] = sanitize_text_field($input['formidable_cookie_option_days']);
	}
	
	//validate ip limit feature
	if(!isset($input['formidable_user_ip']) || $input['formidable_user_ip'] !== "1"){
		$output['formidable_user_ip'] = "0";
	}else{
		$output['formidable_user_ip'] = "1";
	}
	if(empty($input['formidable_error_message_limit_ip'])){
		$output['formidable_error_message_limit_ip'] = "You already submitted this form!";
	}else{
		$output['formidable_error_message_limit_ip'] = sanitize_text_field($input['formidable_error_message_limit_ip']);
	}
	
	//validate standard error message
    if(empty($input['formidable_error_message'])){
		$output['formidable_error_message'] = "Please check all fields! These values has been submitted already!";
	}else{
		$output['formidable_error_message'] = sanitize_text_field($input['formidable_error_message']);
	}
    // Return the array processing any additional functions filtered by this action
      return apply_filters( 'duplicateKiller_formidable_error_message', $output, $input );
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

    $loaded = false;

    if ( did_action('frm_loaded') > 0 ) {
        $loaded = true;
    } elseif ( did_action('frm_after_load') > 0 ) {
        $loaded = true;
    } elseif ( did_action('plugins_loaded') > 0 ) {
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

    echo '<h3 style="color:green"><strong>' . esc_html__('Formidable Forms is activated!', 'duplicate-killer') . '</strong></h3>';

    $forms = duplicateKiller_formidable_get_forms();
    if (empty($forms)) {
        echo '<br/><span style="color:red"><strong>' . esc_html__('There is no contact form. Please create one!', 'duplicate-killer') . '</strong></span>';
		exit();
    }
}

function duplicateKiller_formidable_settings_callback($args){
	$options = get_option($args[0]);
	$checked_cookie = isset($options['formidable_cookie_option']) AND ($options['formidable_cookie_option'] == "1")?: $checked_cookie='';
	$stored_cookie_days = isset($options['formidable_cookie_option_days'])? $options['formidable_cookie_option_days']:"365";
	
	$checkbox_ip = isset($options['formidable_user_ip']) AND ($options['formidable_user_ip'] == "1")?: $checkbox_ip='';
	$stored_error_message_limit_ip = isset($options['formidable_error_message_limit_ip'])? $options['formidable_error_message_limit_ip']:"You already submitted this form!";
	
	$stored_error_message = isset($options['formidable_error_message'])? $options['formidable_error_message']:"Please check all fields! These values has been submitted already!";
	?>
	<h4 class="dk-form-header">Duplicate Killer settings</h4>
	<div class="dk-set-error-message">
		<fieldset class="dk-fieldset">
		<legend><strong>Set error message:</strong></legend>
		<span>Warn the user that the value inserted has been already submitted!</span>
		</br>
		<input type="text" size="70" name="<?php echo esc_attr($args[0].'[formidable_error_message]');?>" value="<?php echo esc_attr($stored_error_message);?>"></input>
		</fieldset>
	</div>
	</br>
	<div class="dk-set-unique-entries-per-user">
		<fieldset class="dk-fieldset">
		<legend><strong>Unique entries per user</strong></legend>
		<strong>This feature use cookies.</strong><span> Please note that multiple users <strong>can submit the same entry</strong>, but a single user cannot submit an entry they have already submitted before.</span>
		</br>
		</br>
		<div class="dk-input-checkbox-callback">
			<input type="checkbox" id="cookie" name="<?php echo esc_attr($args[0].'[formidable_cookie_option]');?>" value="1" <?php echo esc_attr($checked_cookie ? 'checked' : '');?>></input>
			<label for="cookie">Activate this function</label>
		</div>
		</br>
		<div id="dk-unique-entries-cookie" style="display:none">
		<span>Cookie persistence - Number of days </span><input type="text" name="<?php echo esc_attr($args[0].'[formidable_cookie_option_days]');?>" size="5" value="<?php echo esc_attr($stored_cookie_days);?>"></input>
		</br>
		</div>
		</fieldset>
	</div>
	<div class="dk-limit_submission_by_ip">
		<fieldset class="dk-fieldset">
		<legend><strong>Limit submissions by IP address for 7 days for all forms!</strong></legend>
		<strong>This feature </strong><span> restrict form entries based on IP address for 7 days</span>
		</br>
		</br>
		<div class="dk-input-checkbox-callback">
			<input type="checkbox" id="user_ip" name="<?php echo esc_attr($args[0].'[formidable_user_ip]');?>" value="1" <?php echo esc_attr($checkbox_ip ? 'checked' : '');?>></input>
			<label for="user_ip">Activate this function</label>
		</div>
		</br>
		<div id="dk-limit-ip" style="display:none">
		<span>Ser error message:</span><input type="text" size="70" name="<?php echo esc_attr($args[0].'[formidable_error_message_limit_ip]');?>" value="<?php echo esc_attr($stored_error_message_limit_ip);?>"></input>
		</br>
		</div>
		</fieldset>
	</div>
<?php
}
function duplicateKiller_formidable_select_form_tag_callback($args){
	$forms     = duplicateKiller_formidable_get_forms();      // [ 'Form name' => [ 'field1', 'field2' ] ]

	$forms_ids = ""; // [ 'Form name' => 123 ]

	duplicateKiller_render_forms_ui(
		'Formidable',
		'Formidable',
		$args,
		$forms,
		$forms_ids
	);
}