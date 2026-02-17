<?php
defined( 'ABSPATH' ) or die( 'You shall not pass!' );

add_action('elementor_pro/forms/validation', 'duplicateKiller_elementor_guard_only', 1, 2);
function duplicateKiller_elementor_guard_only($record, $ajax_handler) {

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

    // key in option
    $db_form_name = $form_name . '.' . $node_id;

    // cookie
    $form_cookie = 'NULL';
	if ( isset( $_COOKIE['dk_form_cookie'] ) ) {
		$form_cookie = sanitize_text_field(
			wp_unslash( $_COOKIE['dk_form_cookie'] )
		);
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

    // 1) IP check (map globals to what duplicateKiller_ip_limit_trigger expects)
    if (function_exists('duplicateKiller_ip_limit_trigger')) {
        $mapped_for_ip = [
            // keep everything else if your function needs it
            // but provide the keys it expects:
            'user_ip'      => $g_user_ip_enabled ? "1" : "0",
            'user_ip_days' => isset($opt['elementor_user_ip_days']) ? (string)$opt['elementor_user_ip_days'] : '',
        ];

        // if duplicateKiller_ip_limit_trigger expects full option array, you can merge:
        $opt_for_ip = $opt + $mapped_for_ip;

        if (duplicateKiller_ip_limit_trigger("elementor", $opt_for_ip, $db_form_name)) {
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
			// Increment blocked duplicates counter
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
				// Increment blocked duplicates counter
				duplicateKiller_increment_duplicates_blocked_count();
            }
            return;
        }
    }

    // IMPORTANT: no DB insert here
}

add_action('elementor_pro/forms/new_record', 'duplicateKiller_elementor_save_only', 10, 2);
function duplicateKiller_elementor_save_only($record, $handler) {

    global $wpdb;

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
    if (empty($cfg)) return;

    // cookie
    $form_cookie = 'NULL';
	if ( isset( $_COOKIE['dk_form_cookie'] ) ) {
		$form_cookie = sanitize_text_field(
			wp_unslash( $_COOKIE['dk_form_cookie'] )
		);
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

    // IP store flag (global)
    $form_ip = $g_user_ip_enabled ? duplicateKiller_get_user_ip() : 'NULL';
	
	$table_name = $wpdb->prefix . 'dk_forms_duplicate';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Inserting into plugin-owned custom table.
    $wpdb->insert(
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
}

function duplicateKiller_elementor_get_forms(): array {

	$out = array();

	$per_page = 200;
	$paged    = 1;

	do {
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Admin-only scan for Elementor forms (paged, IDs only).
		$post_ids = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ), // Add more post types if needed (e.g. 'elementor_library').
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

			// Elementor stores JSON string in _elementor_data (sometimes already array).
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

			// DFS: Elementor tree is an array of root elements, each may contain "elements".
			$stack = $data;

			while ( ! empty( $stack ) ) {
				$node = array_pop( $stack );
				if ( ! is_array( $node ) ) {
					continue;
				}

				// Push children.
				if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
					foreach ( $node['elements'] as $child ) {
						$stack[] = $child;
					}
				}

				// Detect Elementor Form widget.
				$widgetType = (string) ( $node['widgetType'] ?? '' );
				$elType     = (string) ( $node['elType'] ?? '' );

				if ( 'widget' !== $elType || 'form' !== $widgetType ) {
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

				// Elementor element id (string like 'a1b2c3d').
				$node_id = (string) ( $node['id'] ?? '' );
				if ( '' === $node_id ) {
					continue;
				}

				$display_key = sprintf( '%s.%s', $form_name, $node_id );

				if ( ! isset( $out[ $display_key ] ) ) {
					$out[ $display_key ] = array(
						'form_id'   => $node_id,   // Elementor element id (string).
						'post_id'   => $post_id,
						'form_name' => $form_name,
						'fields'    => array(),
					);
				}

				$fields = $settings['form_fields'] ?? array();
				if ( ! is_array( $fields ) ) {
					$fields = array();
				}

				foreach ( $fields as $f ) {
					if ( ! is_array( $f ) ) {
						continue;
					}

					// Extra safety: skip items that don't look like real fields.
					$label = (string) ( $f['field_label'] ?? '' );
					$cid   = (string) ( $f['custom_id'] ?? '' );
					if ( '' === $label && '' === $cid ) {
						continue;
					}

					$ftype = strtolower( (string) ( $f['field_type'] ?? '' ) );

					// Elementor behavior: missing field_type => default Text field.
					if ( '' === $ftype ) {
						$ftype = 'text';
					}

					// Eligible types (align with your original intent).
					if ( ! in_array( $ftype, array( 'text', 'textarea', 'email', 'tel', 'phone', 'url' ), true ) ) {
						continue;
					}

					// Field id: prefer custom_id; fallback to _id; fallback to label.
					$fid = '';
					if ( ! empty( $f['custom_id'] ) ) {
						$fid = (string) $f['custom_id'];
					} elseif ( ! empty( $f['_id'] ) ) {
						$fid = 'field_' . (string) $f['_id'];
					} elseif ( ! empty( $f['field_label'] ) ) {
						$fid = sanitize_key( (string) $f['field_label'] );
					}

					if ( '' === $fid ) {
						continue;
					}

					$out[ $display_key ]['fields'][] = array(
						'type'  => $ftype,
						'label' => $label,
						'id'    => $fid,
					);
				}
			}
		}

		$paged++;
	} while ( count( $post_ids ) === $per_page );

	// Deduplicate fields (by id) per form.
	foreach ( $out as $key => $bundle ) {
		$seen  = array();
		$clean = array();

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
		<legend><strong>Set error message:</strong></legend>
		<span>Warn the user that the value inserted has been already submitted!</span>
		</br>
		<input type="text" size="70" name="<?php echo esc_attr($args[0].'[elementor_error_message]');?>" value="<?php echo esc_attr($stored_error_message);?>"></input>
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
		<legend><strong>Limit submissions by IP address for 7 days for all forms!</strong></legend>
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