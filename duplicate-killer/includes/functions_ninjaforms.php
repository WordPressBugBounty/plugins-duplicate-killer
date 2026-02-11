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

add_filter( 'ninja_forms_submit_data', 'duplicateKiller_ninjaforms_before_send_email', 10, 1 );

function duplicateKiller_ninjaforms_before_send_email( $form_data ) {
	global $wpdb;
	
	$table_name = $wpdb->prefix . 'dk_forms_duplicate';

	// Option "NinjaForms_page" contains BOTH:
	// - per-form configs keyed by "formkey.formid" (e.g. contactme.1)
	// - global settings keys (ninjaforms_cookie_option, ninjaforms_user_ip, messages, etc.)
	$ninja_page = get_option( 'NinjaForms_page' );
	if ( ! is_array( $ninja_page ) ) {
		return $form_data;
	}

	// Basic sanity checks
	if ( empty( $form_data ) || ! is_array( $form_data ) ) {
		return $form_data;
	}

	// Form ID
	$form_id = 0;
	if ( isset( $form_data['id'] ) ) {
		$form_id = (int) $form_data['id'];
	} elseif ( isset( $form_data['form_id'] ) ) {
		$form_id = (int) $form_data['form_id'];
	}
	if ( $form_id <= 0 ) {
		return $form_data;
	}

	// Form settings (title/key)
	$settings = ( isset( $form_data['settings'] ) && is_array( $form_data['settings'] ) ) ? $form_data['settings'] : array();

	// Prefer "Form Key" if present
	$form_key = '';
	if ( ! empty( $settings['key'] ) ) {
		$form_key = trim( (string) $settings['key'] );
	}

	// Fallback: build a stable key from title
	$title = '';
	if ( ! empty( $settings['title'] ) ) {
		$title = trim( (string) $settings['title'] );
	}

	if ( $form_key === '' ) {
		$form_key = sanitize_key( $title !== '' ? $title : ( 'form-' . $form_id ) );
	}

	if ( $form_key === '' ) {
		return $form_data;
	}

	// The per-form key inside NinjaForms_page is: "formkey.formid"
	$form_name = $form_key . '.' . $form_id; // e.g. "contactme.1"

	// Per-form config must exist under that exact key
	if ( empty( $ninja_page[ $form_name ] ) || ! is_array( $ninja_page[ $form_name ] ) ) {
		return $form_data;
	}
	$cfg = $ninja_page[ $form_name ];

	// Ensure errors array exists
	if ( ! isset( $form_data['errors'] ) || ! is_array( $form_data['errors'] ) ) {
		$form_data['errors'] = array();
	}
	if ( ! isset( $form_data['errors']['fields'] ) || ! is_array( $form_data['errors']['fields'] ) ) {
		$form_data['errors']['fields'] = array();
	}

	$fields = ( ! empty( $form_data['fields'] ) && is_array( $form_data['fields'] ) ) ? $form_data['fields'] : array();
	if ( empty( $fields ) ) {
		return $form_data;
	}

	// =========================
	// 1) IP check
	// =========================
	if ( duplicateKiller_ip_limit_trigger(DK_NINJA_FORMS, $ninja_page, $form_name ) ) {
		$message = ! empty( $ninja_page['ninjaforms_error_message_limit_ip'] )
			? (string) $ninja_page['ninjaforms_error_message_limit_ip']
			: 'This IP has been already submitted.';

		// REQUIRED: field error to actually stop submission
		$first_fid = duplicateKiller_nf_get_first_field_id( $fields );
		if ( $first_fid > 0 ) {
			$form_data['errors']['fields'][ $first_fid ] = $message;
		}

		return $form_data;
	}
	// =========================
	// 2) Duplicate field check (by FIELD_ID toggles in config)
	// =========================
	
	$cookie = duplicateKiller_get_form_cookie_simple($ninja_page, $form_name);

	$form_cookie = $cookie['form_cookie'];
	$checked_cookie = $cookie['checked_cookie'];
	
	// Storage for DB: field_id => value
	$storage_fields = array();

	$duplicate_message = ! empty( $ninja_page['ninjaforms_error_message'] )
		? (string) $ninja_page['ninjaforms_error_message']
		: 'Please check all fields! These values have been submitted already!';

	foreach ( $fields as $k => $field ) {

		// Field ID is usually the array key, but also keep a fallback to $field['id']
		$fid = 0;
		if ( is_numeric( $k ) ) {
			$fid = (int) $k;
		} elseif ( is_array( $field ) && isset( $field['id'] ) && is_numeric( $field['id'] ) ) {
			$fid = (int) $field['id'];
		}

		if ( $fid <= 0 ) {
			continue;
		}

		// Extract submitted value
		$submitted_value = '';
		if ( is_array( $field ) && array_key_exists( 'value', $field ) ) {
			$submitted_value = $field['value'];
		}

		// Normalize value (arrays -> first element)
		if ( is_array( $submitted_value ) ) {
			$submitted_value = reset( $submitted_value );
		}

		$submitted_value = is_string( $submitted_value ) ? wp_unslash( $submitted_value ) : $submitted_value;
		$submitted_value = is_scalar( $submitted_value ) ? sanitize_text_field( (string) $submitted_value ) : '';

		// Save for DB (always)
		$storage_fields[ $fid ] = $submitted_value;

		// Only check duplicates if this FIELD_ID is enabled in per-form config (e.g. 2 => "1")
		// NOTE: $cfg also contains 'labels' array; ignore that.
		if ( ! isset( $cfg[ $fid ] ) || (int) $cfg[ $fid ] !== 1 ) {
			continue;
		}
		$is_dup = duplicateKiller_check_duplicate_by_key_value(
			DK_NINJA_FORMS,
			$form_name, // e.g. "contactme.1"
			$fid,
			$submitted_value,
			$form_cookie,
			$checked_cookie
		);
			
		if ( $is_dup ) {
			// Block submission: form-level + field-level errors
			$form_data['errors']['form']           = $duplicate_message;
			$form_data['errors']['fields'][ $fid ] = $duplicate_message;

			return $form_data;
		}
	}

	// =========================
	// 3) Save to DB
	// =========================
	$ip_enabled = ( isset( $ninja_page['ninjaforms_user_ip'] ) && (string) $ninja_page['ninjaforms_user_ip'] === '1' );
	$form_ip    = $ip_enabled ? duplicateKiller_get_user_ip() : 'NULL';

	$form_value = serialize( $storage_fields );
	$form_date  = current_time( 'Y-m-d H:i:s' );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Inserting into plugin-owned custom table.
	$wpdb->insert(
		$table_name,
		array(
			'form_plugin' => DK_NINJA_FORMS,
			'form_name'   => $form_name,   // e.g. "contactme.1"
			'form_value'  => $form_value,  // field_id => value
			'form_cookie' => $form_cookie,
			'form_date'   => $form_date,
			'form_ip'     => $form_ip,
		)
	);

	return $form_data;
}

function duplicateKiller_nf_get_first_field_id( array $fields ): int {
	foreach ( $fields as $k => $field ) {
		if ( is_numeric( $k ) ) {
			return (int) $k;
		}
		if ( is_array( $field ) && isset( $field['id'] ) && is_numeric( $field['id'] ) ) {
			return (int) $field['id'];
		}
	}
	return 0;
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

/*********************************
 * Callbacks
**********************************/
function duplicateKiller_ninjaforms_validate_input( $input ) {
	global $wpdb;

	$output = array();

	// Create our array for storing the validated options (keep numeric field keys, add labels)
	foreach ( $input as $form_key => $value ) {

		// Only per-form arrays (skip global settings like ninjaforms_cookie_option)
		if ( ! is_array( $value ) ) {
			continue;
		}

		if ( ! isset( $output[ $form_key ] ) || ! is_array( $output[ $form_key ] ) ) {
			$output[ $form_key ] = array();
		}

		foreach ( $value as $field_id => $field_value ) {

			// Special: keep labels array for this form
			if ( (string) $field_id === 'labels' && is_array( $field_value ) ) {
				$output[ $form_key ]['labels'] = array();

				foreach ( $field_value as $fid => $label ) {
					// Keep numeric keys as-is (Ninja Forms field IDs are often numeric)
					if ( $fid === '' || $fid === null ) {
						continue;
					}
					$output[ $form_key ]['labels'][ $fid ] = sanitize_text_field( (string) $label );
				}

				continue;
			}

			// Normal checkbox fields: keep only "1"
			// (unchecked fields usually won't be present in $input anyway)
			if ( (string) $field_value === '1' ) {
				$output[ $form_key ][ $field_id ] = '1';
			}
		}
	}

	// Validate cookies feature
	if ( ! isset( $input['ninjaforms_cookie_option'] ) || (string) $input['ninjaforms_cookie_option'] !== '1' ) {
		$output['ninjaforms_cookie_option'] = '0';
	} else {
		$output['ninjaforms_cookie_option'] = '1';
	}

	// Validate cookie days (default 365)
	if ( ! isset( $input['ninjaforms_cookie_option_days'] ) || filter_var( $input['ninjaforms_cookie_option_days'], FILTER_VALIDATE_INT ) === false ) {
		$output['ninjaforms_cookie_option_days'] = 365;
	} else {
		// Store as integer-ish string to keep existing storage style
		$output['ninjaforms_cookie_option_days'] = (string) absint( $input['ninjaforms_cookie_option_days'] );
	}

	// Validate IP limit feature
	if ( ! isset( $input['ninjaforms_user_ip'] ) || (string) $input['ninjaforms_user_ip'] !== '1' ) {
		$output['ninjaforms_user_ip'] = '0';
	} else {
		$output['ninjaforms_user_ip'] = '1';
	}

	// Validate IP limit error message
	if ( empty( $input['ninjaforms_error_message_limit_ip'] ) ) {
		$output['ninjaforms_error_message_limit_ip'] = 'You already submitted this form!';
	} else {
		$output['ninjaforms_error_message_limit_ip'] = sanitize_text_field( $input['ninjaforms_error_message_limit_ip'] );
	}

	// Validate standard error message
	if ( empty( $input['ninjaforms_error_message'] ) ) {
		$output['ninjaforms_error_message'] = 'Please check all fields! These values has been submitted already!';
	} else {
		$output['ninjaforms_error_message'] = sanitize_text_field( $input['ninjaforms_error_message'] );
	}

	// Return the array processing any additional functions filtered by this action
	return apply_filters( 'duplicateKiller_ninjaforms_error_message', $output, $input );
}

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

function duplicateKiller_ninjaforms_settings_callback($args){
	$options = get_option($args[0]);
	$checked_cookie = isset($options['ninjaforms_cookie_option']) AND ($options['ninjaforms_cookie_option'] == "1")?: $checked_cookie='';
	$stored_cookie_days = isset($options['ninjaforms_cookie_option_days'])? $options['ninjaforms_cookie_option_days']:"365";
	
	$checkbox_ip = isset($options['ninjaforms_user_ip']) AND ($options['ninjaforms_user_ip'] == "1")?: $checkbox_ip='';
	$stored_error_message_limit_ip = isset($options['ninjaforms_error_message_limit_ip'])? $options['ninjaforms_error_message_limit_ip']:"You already submitted this form!";
	
	$stored_error_message = isset($options['ninjaforms_error_message'])? $options['ninjaforms_error_message']:"Please check all fields! These values has been submitted already!";
	?>
	<h4 class="dk-form-header">Duplicate Killer settings</h4>
	<div class="dk-set-error-message">
		<fieldset class="dk-fieldset">
		<legend><strong>Set error message:</strong></legend>
		<span>Warn the user that the value inserted has been already submitted!</span>
		</br>
		<input type="text" size="70" name="<?php echo esc_attr($args[0].'[ninjaforms_error_message]');?>" value="<?php echo esc_attr($stored_error_message);?>"></input>
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
			<input type="checkbox" id="cookie" name="<?php echo esc_attr($args[0].'[ninjaforms_cookie_option]');?>" value="1" <?php echo esc_attr($checked_cookie ? 'checked' : '');?>></input>
			<label for="cookie">Activate this function</label>
		</div>
		</br>
		<div id="dk-unique-entries-cookie" style="display:none">
		<span>Cookie persistence - Number of days </span><input type="text" name="<?php echo esc_attr($args[0].'[ninjaforms_cookie_option_days]');?>" size="5" value="<?php echo esc_attr($stored_cookie_days);?>"></input>
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
			<input type="checkbox" id="user_ip" name="<?php echo esc_attr($args[0].'[ninjaforms_user_ip]');?>" value="1" <?php echo esc_attr($checkbox_ip ? 'checked' : '');?>></input>
			<label for="user_ip">Activate this function</label>
		</div>
		</br>
		<div id="dk-limit-ip" style="display:none">
		<span>Ser error message:</span><input type="text" size="70" name="<?php echo esc_attr($args[0].'[ninjaforms_error_message_limit_ip]');?>" value="<?php echo esc_attr($stored_error_message_limit_ip);?>"></input>
		</br>
		</div>
		</fieldset>
	</div>
<?php
}
function duplicateKiller_ninjaforms_select_form_tag_callback($args){
	$forms     = duplicateKiller_ninjaforms_get_forms();      // [ 'Form name' => [ 'field1', 'field2' ] ]

	$forms_ids = ""; // [ 'Form name' => 123 ]

	duplicateKiller_render_forms_ui(
		DK_NINJA_FORMS,
		DK_NINJA_FORMS_LABEL,
		$args,
		$forms,
		$forms_ids
	);
}