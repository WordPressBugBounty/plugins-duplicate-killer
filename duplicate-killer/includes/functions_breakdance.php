<?php
defined( 'ABSPATH' ) or die( 'You shall not pass!' );

add_filter('breakdance_form_run_action_store_submission', 'duplicateKiller_breakdance_guard_action', 10, 5);
add_filter('breakdance_form_run_action_email',            'duplicateKiller_breakdance_guard_action', 10, 5);

function duplicateKiller_breakdance_guard_action($canExecute, $action, $extra, $form, $settings)
{
    if (is_wp_error($canExecute)) return $canExecute;

    static $dk_state = [
        'checked_once'    => false,
        'duplicate_found' => false,
        'error_sent'      => false,
        'saved_once'      => false,
        'error_message'   => '',
    ];

    if ($dk_state['checked_once']) {
        if ($dk_state['duplicate_found']) {
            if (!$dk_state['error_sent']) {
                $dk_state['error_sent'] = true;
                return new \WP_Error('dk_duplicate', $dk_state['error_message'] ?: __('Duplicate found.', 'duplicate-killer'));
            }
            return false;
        }
        return $canExecute;
    }
    $dk_state['checked_once'] = true;

    $post_id = isset($extra['postId']) ? $extra['postId'] : '';
	$node_id = isset($extra['formId']) ? $extra['formId'] : '';
    if (!$post_id || !$node_id) return $canExecute;

    $bd_form   = $settings['form'] ?? [];
    $base_name = trim((string)($bd_form['form_name'] ?? ''));
    if ($base_name === '') {
        $post = get_post($post_id);
        $base_name = $post ? $post->post_title : 'Breakdance Form';
    }

	$db_form_name = $base_name . '.' . (int)$post_id . '.' . (int)$node_id;

    $options = get_option('Breakdance_page');
    if (!is_array($options)) $options = [];

    $perForm = isset($options[$db_form_name]) && is_array($options[$db_form_name])
        ? $options[$db_form_name]
        : [];

    $global_cookie_enabled   = isset($options['breakdance_cookie_option']) && $options['breakdance_cookie_option'] === '1';
    $global_cookie_days_raw  = isset($options['breakdance_cookie_option_days']) ? $options['breakdance_cookie_option_days'] : '7';
    $global_ip_enabled       = isset($options['breakdance_user_ip']) && $options['breakdance_user_ip'] === '1';
    $global_err_msg_ip       = isset($options['breakdance_error_message_limit_ip']) ? (string)$options['breakdance_error_message_limit_ip'] : '';
    $global_err_msg_fields   = isset($options['breakdance_error_message']) ? (string)$options['breakdance_error_message'] : '';

    if (empty($perForm)) {
        foreach ($options as $opt_key => $opt_val) {
            if (!is_array($opt_val)) continue;
            if (!isset($opt_val['form_id'])) continue;
            if ($opt_val['form_id'] == $node_id) {
                $perForm = $opt_val;
                break;
            }
        }
    }

    if (empty($perForm)) return $canExecute;

	$form_cookie = isset( $_COOKIE['dk_form_cookie'] )
			? sanitize_text_field( wp_unslash( $_COOKIE['dk_form_cookie'] ) )
			: 'NULL';
    $default_msg_fields = __('Please check all fields! These values have been submitted already!', 'duplicate-killer');
    $default_msg_ip     = __('This IP has been already submitted.', 'duplicate-killer');

    $error_message_base = $global_err_msg_fields !== '' ? $global_err_msg_fields
                        : (isset($perForm['error_message']) ? (string)$perForm['error_message'] : $default_msg_fields);

    $error_message_ip   = $global_err_msg_ip !== '' ? $global_err_msg_ip
                        : (isset($perForm['error_message_limit_ip_option']) ? (string)$perForm['error_message_limit_ip_option'] : $default_msg_ip);

    $id_to_val   = [];
    $id_to_label = [];
    foreach ($form as $field) {
        $fid = \Breakdance\Forms\getIdFromField($field);
        $id_to_val[$fid]   = (string)($field['value'] ?? '');
        $id_to_label[$fid] = (string)($field['label'] ?? $fid);
    }

    /* 1) IP check */
    $ip_triggered = false;
    if (function_exists('duplicateKiller_ip_limit_trigger')) {
        $ip_triggered = duplicateKiller_ip_limit_trigger('breakdance', $options, $db_form_name);
	}
    if ($ip_triggered) {
        $dk_state['duplicate_found'] = true;
        $dk_state['error_sent']      = true;
        $dk_state['error_message']   = $error_message_ip;
		// Increment blocked duplicates counter
		duplicateKiller_increment_duplicates_blocked_count();
		return new \WP_Error('dk_duplicate_ip', $error_message_ip);
    }

    /* 2) check for duplicates
     */
    $unique_ids = [];
    foreach ($perForm as $k => $v) {
        if ($k === 'form_id') continue;
        if ($v === '1') $unique_ids[] = (string)$k;
    }

    if ($unique_ids) {
        $checked_cookie = $global_cookie_enabled;

        foreach ($unique_ids as $field_id) {
            $submitted_value = isset($id_to_val[$field_id]) ? $id_to_val[$field_id] : '';

            if ($submitted_value === '' || $submitted_value === null) continue;

            if (is_array($submitted_value)) {
                $submitted_value = implode(' ', array_map('strval', $submitted_value));
            } else {
                $submitted_value = (string)$submitted_value;
            }

            $exists = duplicateKiller_check_duplicate_by_key_value(
                'breakdance',          // $form_plugin
                $db_form_name,         // $form_name
                $field_id,             // $key
                $submitted_value,      // $value
                $form_cookie,          // $form_cookie
                $checked_cookie        // $checked_cookie
            );

            if ($exists) {
                $label  = $id_to_label[$field_id] ?? $field_id;
                $pretty = sprintf('%s: %s', $label, $error_message_base);
                $dk_state['duplicate_found'] = true;
                $dk_state['error_sent']      = true;
                $dk_state['error_message']   = $pretty;
				// Increment blocked duplicates counter
				duplicateKiller_increment_duplicates_blocked_count();
                return new \WP_Error('dk_duplicate', $pretty);
            }
        }
    }

    /* 3) save only one time (first action) */
    if (!$dk_state['saved_once']) {
        global $wpdb;

        $payload = serialize($extra['fields'] ?? []);

        $form_ip = $global_ip_enabled ? duplicateKiller_get_user_ip() : 'NULL';

        $now_gmt = current_time('Y-m-d H:i:s');
		
		$table_name = $wpdb->prefix . 'dk_forms_duplicate';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Inserting into plugin-owned custom table.
        $wpdb->insert($table_name, [
            'form_plugin' => 'breakdance',
            'form_name'   => $db_form_name,
            'form_value'  => $payload,
            'form_cookie' => $form_cookie,
            'form_date'   => $now_gmt,
            'form_ip'     => $form_ip,
        ], ['%s','%s','%s','%s','%s','%s']);

        $dk_state['saved_once'] = true;
    }

    return $canExecute;
}


function duplicateKiller_breakdance_get_forms() {

	$cache_key = 'dk_breakdance_forms_v1';
	$cached    = get_transient( $cache_key );
	if ( false !== $cached && is_array( $cached ) ) {
		return $cached;
	}

	$out = array();

	// IMPORTANT: adjust post types to what you expect. Keeping 'any' can be heavy.
	$post_types = array( 'page', 'post' ); // add others if needed (e.g. 'breakdance_template')

	$per_page = 200;
	$paged    = 1;

	do {
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Admin-only scan for Breakdance forms (paged, IDs only).
		$post_ids = get_posts( array(
			'post_type'      => $post_types,
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'fields'         => 'ids',              // huge perf gain
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Admin-only scan for Breakdance forms (paged, IDs only).
			'meta_key'       => '_breakdance_data', // faster than meta_query EXISTS
			'orderby'        => 'ID',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		) );

		if ( empty( $post_ids ) ) {
			break;
		}

		foreach ( $post_ids as $post_id ) {
			$post_id = (int) $post_id;

			// Avoid loading full post objects unless needed.
			$post_title = get_the_title( $post_id );

			$all_meta = get_post_meta( $post_id, '_breakdance_data', false );
			if ( empty( $all_meta ) ) {
				continue;
			}

			foreach ( $all_meta as $meta_raw ) {

				$meta = $meta_raw;

				if ( is_string( $meta_raw ) ) {
					$decoded = json_decode( $meta_raw, true );
					if ( is_array( $decoded ) ) {
						$meta = $decoded;
					}
				}

				if ( ! is_array( $meta ) ) {
					$maybe = maybe_unserialize( $meta_raw );
					if ( is_array( $maybe ) ) {
						$meta = $maybe;
					}
				}

				if ( empty( $meta['tree_json_string'] ) || ! is_string( $meta['tree_json_string'] ) ) {
					continue;
				}

				$tree = json_decode( $meta['tree_json_string'], true );
				if ( ! is_array( $tree ) || empty( $tree['root'] ) ) {
					continue;
				}

				$stack = array( $tree['root'] );

				while ( ! empty( $stack ) ) {
					$node = array_pop( $stack );
					$type = isset( $node['data']['type'] ) ? (string) $node['data']['type'] : '';

					if ( 'EssentialElements\\FormBuilder' === $type ) {
						$props     = isset( $node['data']['properties'] ) ? (array) $node['data']['properties'] : array();
						$form      = isset( $props['content']['form'] ) ? (array) $props['content']['form'] : array();
						$fields    = isset( $form['fields'] ) ? (array) $form['fields'] : array();
						$form_name = trim( (string) ( $form['form_name'] ?? '' ) );

						if ( '' === $form_name ) {
							$form_name = (string) $post_title;
						}

						$node_id = (int) ( $node['id'] ?? 0 );

						// Ensure uniqueness across posts & nodes.
						$display_key = sprintf( '%s.%d.%d', $form_name, $post_id, $node_id );

						if ( ! isset( $out[ $display_key ] ) ) {
							$out[ $display_key ] = array(
								'form_id'   => $node_id,
								'post_id'   => $post_id,
								'form_name' => $form_name,
								'fields'    => array(),
							);
						}

						foreach ( $fields as $f ) {
							$f     = (array) $f;
							$ftype = strtolower( (string) ( $f['type'] ?? '' ) );

							if ( ! in_array( $ftype, array( 'text', 'email', 'tel', 'phone' ), true ) ) {
								continue;
							}

							$fid = '';

							if ( ! empty( $f['advanced']['id'] ) ) {
								$fid = (string) $f['advanced']['id'];
							} elseif ( ! empty( $f['label'] ) ) {
								$fid = sanitize_key( (string) $f['label'] );
							}

							if ( '' === $fid ) {
								continue;
							}

							$out[ $display_key ]['fields'][ $fid ] = array(
								'type'  => $ftype,
								'label' => (string) ( $f['label'] ?? '' ),
								'id'    => $fid,
							);
						}
					}

					if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
						foreach ( $node['children'] as $child ) {
							$stack[] = $child;
						}
					}
				}
			}
		}

		$paged++;
	} while ( count( $post_ids ) === $per_page );

	// Normalize fields to numeric array.
	foreach ( $out as $k => $bundle ) {
		if ( isset( $bundle['fields'] ) && is_array( $bundle['fields'] ) ) {
			$out[ $k ]['fields'] = array_values( $bundle['fields'] );
		}
	}

	// Cache 30 minutes (adjust).
	set_transient( $cache_key, $out, 30 * MINUTE_IN_SECONDS );

	return $out;
}

/*********************************
 * Callbacks
**********************************/
function duplicateKiller_breakdance_validate_input($input){
    global $wpdb;

    $output = [];
    $table  = $wpdb->prefix . 'dk_forms_duplicate';

	if ( ! isset( $_POST['_wpnonce'] ) ) {
		return $input;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) );

	if ( ! wp_verify_nonce( $nonce, 'Breakdance_page-options' ) ) {
		return $input;
	}
    /* -------------------------
	   Fallback if $input null
	--------------------------*/
	if ( ! is_array( $input ) ) {

		// Verify Settings API nonce (options.php).
		if ( ! isset( $_POST['_wpnonce'] ) ) {
			return $input;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'Breakdance_page-options' ) ) {
			return $input;
		}

		if ( isset( $_POST['breakdance_page'] ) && is_array( $_POST['breakdance_page'] ) ) {
			$input = array_map(
				'sanitize_text_field',
				wp_unslash( $_POST['breakdance_page'] )
			);
		} elseif ( isset( $_POST['Breakdance_page'] ) && is_array( $_POST['Breakdance_page'] ) ) {
			$input = array_map(
				'sanitize_text_field',
				wp_unslash( $_POST['Breakdance_page'] )
			);
		} else {
			return apply_filters( 'duplicateKiller_breakdance_error_message', $output, $input );
		}
	}

    // known key
    $known_scalar_keys = [
        'form_id',
        'error_message',
        'error_message_limit_ip_option',
        'user_ip_days',
        'cookie_option_days',
    ];

    foreach ($input as $form_name => $values) {

        if (!is_array($values)) { continue; }

        $san_form = (string)$form_name;
        $form_out = [];

        /* --- form_id (int) --- */
        if (isset($values['form_id'])) {
            $form_out['form_id'] = intval($values['form_id']);
        }

        /* --- checkboxes (IDs) => "1" --- */
        foreach ($values as $k => $v) {
            if (in_array($k, $known_scalar_keys, true)) {
                continue; // already processed
            }
			// it is a field checkbox only if the value is exactly "1"
            // A field checkbox is "enabled" only when the value is exactly 1/"1".
			// Some builders send nested arrays, so we must handle both scalar and array shapes safely.
			$is_enabled = false;

			if (is_scalar($v)) {
				$is_enabled = ((string) $v === '1');
			} elseif (is_array($v)) {
				// Common patterns: ['enabled' => '1'] or ['value' => '1']
				if (isset($v['enabled']) && is_scalar($v['enabled']) && (string) $v['enabled'] === '1') {
					$is_enabled = true;
				} elseif (isset($v['value']) && is_scalar($v['value']) && (string) $v['value'] === '1') {
					$is_enabled = true;
				}
			}

			if ($is_enabled) {
				// $k is the field ID (ex: your-email / name / qqtaqi)
				$form_out[$k] = '1';
			}
        }

        if (!empty($form_out)) {
            $output[$san_form] = $form_out;
        }
    }
	//validate cookies feature
	if(!isset($input['breakdance_cookie_option']) || $input['breakdance_cookie_option'] !== "1"){
		$output['breakdance_cookie_option'] = "0";
	}else{
		$output['breakdance_cookie_option'] = "1";
	}
	if(filter_var($input['breakdance_cookie_option_days'], FILTER_VALIDATE_INT) === false){
		$output['breakdance_cookie_option_days'] = 365;
	}else{
		$output['breakdance_cookie_option_days'] = sanitize_text_field($input['breakdance_cookie_option_days']);
	}
	
	//validate ip limit feature
	if(!isset($input['breakdance_user_ip']) || $input['breakdance_user_ip'] !== "1"){
		$output['breakdance_user_ip'] = "0";
	}else{
		$output['breakdance_user_ip'] = "1";
	}
	if(empty($input['breakdance_error_message_limit_ip'])){
		$output['breakdance_error_message_limit_ip'] = "You already submitted this form!";
	}else{
		$output['breakdance_error_message_limit_ip'] = sanitize_text_field($input['breakdance_error_message_limit_ip']);
	}
	
	//validate standard error message
    if(empty($input['breakdance_error_message'])){
		$output['breakdance_error_message'] = "Please check all fields! These values has been submitted already!";
	}else{
		$output['breakdance_error_message'] = sanitize_text_field($input['breakdance_error_message']);
	}
    return apply_filters('duplicateKiller_breakdance_error_message', $output, $input);
}
/**
 * Helper: is Breakdance present & enabled for this request?
 */
function duplicateKiller_bd_is_ready(): bool {
    // Present?
    if (!defined('__BREAKDANCE_VERSION') && !class_exists('\Breakdance\Forms\Actions\Action')) {
        return false;
    }
    // Migration mode can disable BD per request
    if (function_exists('\Breakdance\MigrationMode\isBreakdanceEnabledForRequest')
        && !\Breakdance\MigrationMode\isBreakdanceEnabledForRequest()) {
        return false;
    }
    // Loader fired?
    return did_action('before_breakdance_loaded') > 0;
}
function duplicateKiller_breakdance_description(){
	if (!duplicateKiller_bd_is_ready()) {
        echo '<h3 style="color:red"><strong>' . esc_html__('Breakdance plugin is not activated! Please activate it in order to continue.', 'duplicate-killer') . '</strong></h3>';
		exit();
    }

    // If you need a success message, keep this. If not, remove it.
    echo '<h3 style="color:green"><strong>' . esc_html__('Breakdance plugin is activated!', 'duplicate-killer') . '</strong></h3>';

    $forms = duplicateKiller_breakdance_get_forms();
    if (empty($forms)) {
        echo '<br/><span style="color:red"><strong>' . esc_html__('There is no contact form. Please create one!', 'duplicate-killer') . '</strong></span>';
		exit();
    }
}

function duplicateKiller_breakdance_settings_callback($args){
	$options = get_option($args[0]);
	$checked_cookie = isset($options['breakdance_cookie_option']) AND ($options['breakdance_cookie_option'] == "1")?: $checked_cookie='';
	$stored_cookie_days = isset($options['breakdance_cookie_option_days'])? $options['breakdance_cookie_option_days']:"365";
	
	$checkbox_ip = isset($options['breakdance_user_ip']) AND ($options['breakdance_user_ip'] == "1")?: $checkbox_ip='';
	$stored_error_message_limit_ip = isset($options['breakdance_error_message_limit_ip'])? $options['breakdance_error_message_limit_ip']:"You already submitted this form!";
	
	$stored_error_message = isset($options['breakdance_error_message'])? $options['breakdance_error_message']:"Please check all fields! These values has been submitted already!";
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
		<input type="text" size="70" name="<?php echo esc_attr($args[0].'[breakdance_error_message]');?>" value="<?php echo esc_attr($stored_error_message);?>"></input>
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
			<input type="checkbox" id="cookie" name="<?php echo esc_attr($args[0].'[breakdance_cookie_option]');?>" value="1" <?php echo esc_attr($checked_cookie ? 'checked' : '');?>></input>
			<label for="cookie">Activate this function</label>
		</div>
		</br>
		<div id="dk-unique-entries-cookie" style="display:none">
		<span>Cookie persistence - Number of days </span><input type="text" name="<?php echo esc_attr($args[0].'[breakdance_cookie_option_days]');?>" size="5" value="<?php echo esc_attr($stored_cookie_days);?>"></input>
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

		<strong>This feature </strong>
		<span>restrict form entries based on IP address for 7 days</span>
		</br>
		</br>
		<div class="dk-input-checkbox-callback">
			<input type="checkbox" id="user_ip" name="<?php echo esc_attr($args[0].'[breakdance_user_ip]');?>" value="1" <?php echo esc_attr($checkbox_ip ? 'checked' : '');?>></input>
			<label for="user_ip">Activate this function</label>
		</div>
		</br>
		<div id="dk-limit-ip" style="display:none">
		<span>Ser error message:</span><input type="text" size="70" name="<?php echo esc_attr($args[0].'[breakdance_error_message_limit_ip]');?>" value="<?php echo esc_attr($stored_error_message_limit_ip);?>"></input>
		</br>
		</div>
		</fieldset>
	</div>
<?php
}
function duplicateKiller_breakdance_select_form_tag_callback($args){
	$forms     = duplicateKiller_breakdance_get_forms();      // [ 'Form name' => [ 'field1', 'field2' ] ]

	$forms_ids = ""; // not available

	duplicateKiller_render_forms_ui(
		'Breakdance',
		'Breakdance',
		$args,
		$forms,
		$forms_ids
	);
}