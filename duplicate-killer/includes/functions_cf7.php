<?php
defined( 'ABSPATH' ) or die( 'You shall not pass!' );

add_action( 'wpcf7_before_send_mail', 'duplicateKiller_cf7_before_send_email', 1,3 );
function duplicateKiller_cf7_before_send_email($contact_form, &$abort, $object) {

    global $wpdb;
    $table_name = $wpdb->prefix.'dk_forms_duplicate';
	$cf7_page = get_option("CF7_page");
    $submission = WPCF7_Submission::get_instance();

	$request_debug_id = uniqid('duplicateKiller_cf7_free_', true);
	$dk_enabled       = class_exists('duplicateKiller_Diagnostics');

	//upload files if any
	$files = $submission->uploaded_files();
	$upload_dir = wp_upload_dir();

	// NEW location (WP.org compliant)
	$dkcf7_folder     = trailingslashit( $upload_dir['basedir'] ) . 'duplicate-killer';
	$dkcf7_folder_url = trailingslashit( $upload_dir['baseurl'] ) . 'duplicate-killer';

	// ensure dir exists
	if ( ! file_exists( $dkcf7_folder ) ) {
		wp_mkdir_p( $dkcf7_folder );
	}

	$form_name = $contact_form->title();

	$form_cookie = 'NULL';
	if ( isset( $_COOKIE['dk_form_cookie'] ) ) {
		$form_cookie = sanitize_text_field(
			wp_unslash( $_COOKIE['dk_form_cookie'] )
		);
	}

	$abort = false;
	$no_form = false;
	$data = $submission->get_posted_data();

	if ( $dk_enabled ) {
		duplicateKiller_Diagnostics::log('cf7', 'process_start', [
			'request_debug_id' => $request_debug_id,
			'form_name'        => $form_name,
			'form_cookie'      => $form_cookie,
			'posted_data'      => is_array($data) ? $data : [],
			'uploaded_files'   => is_array($files) ? $files : [],
			'cf7_page'         => is_array($cf7_page) ? $cf7_page : [],
		]);
	}

	$form_ip = "";

	//check if IP limit feature is active
	if($cf7_page['cf7_user_ip'] == "1"){

		$no_form = true;
		$form_ip = duplicateKiller_get_user_ip();

		if ( $dk_enabled ) {
			duplicateKiller_Diagnostics::log('cf7', 'ip_check_start', [
				'request_debug_id' => $request_debug_id,
				'form_name'        => $form_name,
				'form_ip'          => $form_ip,
				'ip_limit_enabled' => 1,
			]);
		}

		$duplicateKiller_check_ip_feature = duplicateKiller_check_ip_feature("CF7",$form_name,$form_ip);

		if ( $dk_enabled ) {
			duplicateKiller_Diagnostics::log('cf7', 'ip_check_result', [
				'request_debug_id' => $request_debug_id,
				'form_name'        => $form_name,
				'form_ip'          => $form_ip,
				'duplicate_ip'     => $duplicateKiller_check_ip_feature ? 1 : 0,
			]);
		}

		if($duplicateKiller_check_ip_feature){
			$message = $cf7_page['cf7_error_message_limit_ip'];

			//change the general error message with the dk_custom_error_message
			add_filter('cf7_custom_form_invalid_form_message',function($invalid_form_message, $contact_form) use($message){
				$invalid_form_message = $message;
				return $invalid_form_message = $message;
			},15,2);

			//stop form for submission if IP limit is triggered
			$abort = true;
			$object->set_response($message);

			if ( $dk_enabled ) {
				duplicateKiller_Diagnostics::log('cf7', 'ip_limit_blocked', [
					'request_debug_id' => $request_debug_id,
					'form_name'        => $form_name,
					'message'          => $message,
					'abort'            => 1,
				]);
			}

			// Increment blocked duplicates counter
			duplicateKiller_increment_duplicates_blocked_count();
		}

	}else{

		if ( $dk_enabled ) {
			duplicateKiller_Diagnostics::log('cf7', 'duplicate_check_start', [
				'request_debug_id' => $request_debug_id,
				'form_name'        => $form_name,
				'has_data'         => !empty($data) ? 1 : 0,
				'has_cf7_page'     => !empty($cf7_page) ? 1 : 0,
			]);
		}

		if($data AND $cf7_page){
			foreach ($data as $key => $d) {

				$tmpD = $d;
				if(!is_array($d)){
					$bl = array('\"',"\'",'/','\\','"',"'");
					$wl = array('&quot;','&#039;','&#047;', '&#092;','&quot;','&#039;');
					$tmpD = str_replace($bl, $wl, $tmpD);
				}

				if ( $dk_enabled ) {
					duplicateKiller_Diagnostics::log('cf7', 'field_inspected', [
						'request_debug_id' => $request_debug_id,
						'form_name'        => $form_name,
						'field_key'        => $key,
						'field_value'      => is_array($d) ? $d : (string) $d,
						'normalized_value' => is_array($tmpD) ? $tmpD : (string) $tmpD,
					]);
				}

				foreach($cf7_page as $cf7_form => $cf7_tag){

					if($form_name == $cf7_form){

						if(array_key_exists($key,$cf7_tag)){

							$no_form = true;

							if ( $dk_enabled ) {
								duplicateKiller_Diagnostics::log('cf7', 'field_enabled_in_config', [
									'request_debug_id' => $request_debug_id,
									'form_name'        => $form_name,
									'field_key'        => $key,
									'config_form'      => $cf7_form,
								]);
							}

							if($result = duplicateKiller_check_duplicate("CF7",$form_name)){

								if ( $dk_enabled ) {
									duplicateKiller_Diagnostics::log('cf7', 'duplicate_rows_found', [
										'request_debug_id' => $request_debug_id,
										'form_name'        => $form_name,
										'field_key'        => $key,
										'rows_count'       => is_array($result) ? count($result) : 0,
									]);
								}

								foreach($result as $row){
									$form_value = unserialize($row->form_value);

									if(isset($form_value[$key]) AND duplicateKiller_check_values_with_lowercase_filter($form_value[$key],$tmpD)){

										if ( $dk_enabled ) {
											duplicateKiller_Diagnostics::log('cf7', 'duplicate_match_found', [
												'request_debug_id' => $request_debug_id,
												'form_name'        => $form_name,
												'field_key'        => $key,
												'db_field_value'   => isset($form_value[$key]) ? $form_value[$key] : '',
												'current_value'    => $tmpD,
												'db_form_cookie'   => isset($row->form_cookie) ? $row->form_cookie : '',
											]);
										}

										if(function_exists('cfdb7_before_send_mail')){
											remove_action('wpcf7_before_send_mail', 'cfdb7_before_send_mail');
										}
										if(function_exists('vsz_cf7_before_send_email')){
											remove_action('wpcf7_before_send_mail', 'vsz_cf7_before_send_email');
										}

										$cookies_setup = [
											'plugin_name'   => "cf7_cookie_option",
											'get_option'    => $cf7_page,
											'cookie_stored' => $form_cookie,
											'cookie_db_set' => $row->form_cookie
										];

										$cookie_allowed = duplicateKiller_check_cookie($cookies_setup);

										if ( $dk_enabled ) {
											duplicateKiller_Diagnostics::log('cf7', 'cookie_check_result', [
												'request_debug_id' => $request_debug_id,
												'form_name'        => $form_name,
												'field_key'        => $key,
												'cookie_allowed'   => $cookie_allowed ? 1 : 0,
												'cookie_stored'    => $form_cookie,
												'cookie_db_set'    => $row->form_cookie,
											]);
										}

										if($cookie_allowed){
											$abort = true;
											$object->set_response($cf7_page['cf7_error_message']);

											if ( $dk_enabled ) {
												duplicateKiller_Diagnostics::log('cf7', 'duplicate_blocked', [
													'request_debug_id' => $request_debug_id,
													'form_name'        => $form_name,
													'field_key'        => $key,
													'message'          => $cf7_page['cf7_error_message'],
													'abort'            => 1,
												]);
											}

											// Increment blocked duplicates counter
											duplicateKiller_increment_duplicates_blocked_count();
										}
									}
								}
							}
						}
					}
				}
			}
		}
	}

	if ( $dk_enabled ) {
		duplicateKiller_Diagnostics::log('cf7', $abort ? 'save_skipped_abort' : 'save_start', [
			'request_debug_id' => $request_debug_id,
			'form_name'        => $form_name,
			'abort'            => $abort ? 1 : 0,
			'no_form'          => $no_form ? 1 : 0,
			'form_ip'          => $form_ip,
			'form_cookie'      => $form_cookie,
		]);
	}

	if(!$abort AND $no_form){

		//check if IP limit feature is active and store it
		if(!$form_ip){
			$duplicateKiller_check_ip_feature = duplicateKiller_get_setting("CF7_page","cf7_user_ip");
			$form_ip = ($duplicateKiller_check_ip_feature)? $form_ip=duplicateKiller_get_user_ip(): $form_ip='NULL';

			if ( $dk_enabled ) {
				duplicateKiller_Diagnostics::log('cf7', 'save_ip_resolved', [
					'request_debug_id' => $request_debug_id,
					'form_name'        => $form_name,
					'resolved_form_ip' => $form_ip,
				]);
			}
		}

		//check if user want to save the files locally
		if ( ! isset( $cf7_page['cf7_save_image'] ) || (string) $cf7_page['cf7_save_image'] === '1' ) {
			if ( $files ) {

				if ( $dk_enabled ) {
					duplicateKiller_Diagnostics::log('cf7', 'file_save_start', [
						'request_debug_id' => $request_debug_id,
						'form_name'        => $form_name,
						'files'            => $files,
					]);
				}

				// Init filesystem once
				global $wp_filesystem;
				if ( ! $wp_filesystem ) {
					require_once ABSPATH . 'wp-admin/includes/file.php';
					WP_Filesystem();
				}

				$random_number = uniqid( (string) time(), true );

				foreach ( $files as $file_key => $file ) {
					$file = is_array( $file ) ? reset( $file ) : $file;
					if ( empty( $file ) ) {
						continue;
					}

					$dest_name = $file_key . '-' . $random_number . '-' . basename( $file );
					$file_path = trailingslashit( $dkcf7_folder ) . $dest_name;
					$file_url  = trailingslashit( $dkcf7_folder_url ) . rawurlencode( $dest_name );

					if ( $wp_filesystem ) {
						$contents = file_get_contents( $file );
						if ( false !== $contents ) {
							$wp_filesystem->put_contents( $file_path, $contents, FS_CHMOD_FILE );

							if ( array_key_exists( $file_key, $data ) ) {
								$data[ $file_key ] = $file_url;
							}

							if ( $dk_enabled ) {
								duplicateKiller_Diagnostics::log('cf7', 'file_saved', [
									'request_debug_id' => $request_debug_id,
									'form_name'        => $form_name,
									'file_key'         => $file_key,
									'file_url'         => $file_url,
								]);
							}
						}
					}
				}
			}
		}

		$form_value = serialize($data);
		$form_date = current_time('Y-m-d H:i:s');

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Inserting into plugin-owned custom table.
		$insert_result = $wpdb->insert(
			$table_name,
			array(
				'form_plugin' => "CF7",
				'form_name'   => $form_name,
				'form_value'  => $form_value,
				'form_cookie' => $form_cookie,
				'form_date'   => $form_date,
				'form_ip'     => $form_ip
			)
		);

		if ( $dk_enabled ) {
			duplicateKiller_Diagnostics::log('cf7', 'save_after_insert', [
				'request_debug_id' => $request_debug_id,
				'form_name'        => $form_name,
				'insert_ok'        => empty($wpdb->last_error) && false !== $insert_result ? 1 : 0,
				'wpdb_last_error'  => $wpdb->last_error,
				'insert_id'        => $wpdb->insert_id,
				'table_name'       => $table_name,
			]);
		}
	}
}
/**
 * Retrieve CF7 forms and extract their text/email/tel fields.
 * Forms are ordered in descending order by ID (newest first).
 */
function duplicateKiller_CF7_get_forms() {
    global $wpdb;

    // Get CF7 forms in descending order
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reading CF7 forms from core posts table (admin-only, request-scoped).
    $CF7Query = $wpdb->get_results(
        "SELECT ID, post_title, post_content 
         FROM {$wpdb->posts}
         WHERE post_type = 'wpcf7_contact_form'
           AND post_status NOT IN ('trash', 'auto-draft')
         ORDER BY ID DESC",
        ARRAY_A
    );

    if ( empty( $CF7Query ) ) {
        return [];
    }

    $output = [];

    foreach ( $CF7Query as $form ) {

        // Split content into tokens based on spaces
        $tagsArray = explode( " ", $form['post_content'] );

        for ( $i = 0; $i < count( $tagsArray ); $i++ ) {

            // Match [text ...]
            if ( str_contains( $tagsArray[$i], "[text" ) ) {
                $result = explode( "]", $tagsArray[ $i + 1 ] );
                $output[ $form['post_title'] ][] = sanitize_text_field( $result[0] );
            }

            // Match [email ...]
            if ( str_contains( $tagsArray[$i], "[email" ) ) {
                $result = explode( "]", $tagsArray[ $i + 1 ] );
                $output[ $form['post_title'] ][] = sanitize_text_field( $result[0] );
            }

            // Match [tel ...]
            if ( str_contains( $tagsArray[$i], "[tel" ) ) {
                $result = explode( "]", $tagsArray[ $i + 1 ] );
                $output[ $form['post_title'] ][] = sanitize_text_field( $result[0] );
            }

            // Stop scanning once we reach [submit]
            if ( str_contains( $tagsArray[$i], "[submit" ) ) {
                break;
            }
        }
    }

    return $output;
}

/*********************************
 * Callbacks
**********************************/
function duplicateKiller_cf7_validate_input($input){
	global $wpdb;
	$output = array();

	// Create our array for storing the validated options
    foreach($input as $key =>$value){
		if(is_array($value)){
			foreach($value as $arr => $asc){
				//check if someone putting in ‘dog’ when the only valid values are numbers
				if($asc !== "1"){
					$value[$arr] = "1";
					$output[$key] = $value;
				}else{
					$output[$key] = $value;
				}
			}
		}
	}
	if(isset($input['cf7_cookie_option'])){
		if($input['cf7_cookie_option'] !== "1"){
			$output['cf7_cookie_option'] = "0";
		}else{
			$output['cf7_cookie_option'] = "1";
		}
	}else{
		$output['cf7_cookie_option'] = "0";
	}
	//validate ip limit feature
	if(!isset($input['cf7_user_ip']) || $input['cf7_user_ip'] !== "1"){
		$output['cf7_user_ip'] = "0";
	}else{
		$output['cf7_user_ip'] = "1";
	}
	if(empty($input['cf7_error_message_limit_ip'])){
		$output['cf7_error_message_limit_ip'] = "You already submitted this form!";
	}else{
		$output['cf7_error_message_limit_ip'] = sanitize_text_field($input['cf7_error_message_limit_ip']);
	}
	if(filter_var($input['cf7_cookie_option_days'], FILTER_VALIDATE_INT) === false){
		$output['cf7_cookie_option_days'] = 365;
	}else{
		$output['cf7_cookie_option_days'] = sanitize_text_field($input['cf7_cookie_option_days']);
	}
    if(empty($input['cf7_error_message'])){
		$output['cf7_error_message'] = "Please check all fields! These values has been submitted already!";
	}else{
		$output['cf7_error_message'] = sanitize_text_field($input['cf7_error_message']);
	}
	
	//validate save image option
	if(!isset($input['cf7_save_image']) || $input['cf7_save_image'] !== "1"){
		$output['cf7_save_image'] = "0";
	}else{
		$output['cf7_save_image'] = "1";
	}
    // Return the array processing any additional functions filtered by this action
    return apply_filters('duplicate_killer_cf7_validate_input', $output, $input);
}

function duplicateKiller_CF7_description() {
	if(class_exists('WPCF7_ContactForm') OR is_plugin_active('contact-form-7/wp-contact-form-7.php')){ ?>
		<h3 style="color:green"><strong><?php esc_html_e('Contact-form-7 plugin is activated!','duplicate-killer');?></strong></h3>
<?php
	}else{ ?>
		<h3 style="color:red"><strong><?php esc_html_e('Contact-form-7 plugin is not activated! Please activate it in order to continue.','duplicate-killer');?></strong></h3>
<?php
		exit();
	}
	if(duplicateKiller_CF7_get_forms() == NULL){ ?>
		</br><span style="color:red"><strong><?php esc_html_e('There is no contact forms. Please create one!','duplicate-killer');?></strong></span>
<?php
		exit();
	}
}

function duplicateKiller_cf7_settings_callback($args){
	$options = get_option($args[0]);
	$checked_cookie = isset($options['cf7_cookie_option']) AND ($options['cf7_cookie_option'] == "1")?: $checked_cookie='';
	$stored_cookie_days = isset($options['cf7_cookie_option_days'])? $options['cf7_cookie_option_days']:"365";
	$stored_error_message = isset($options['cf7_error_message'])? $options['cf7_error_message']:"Please check all fields! These values has been submitted already!"; 
	$checkbox_ip = isset($options['cf7_user_ip']) AND ($options['cf7_user_ip'] == "1")?: $checkbox_ip='';
	$stored_error_message_limit_ip = isset($options['cf7_error_message_limit_ip'])? $options['cf7_error_message_limit_ip']:"You already submitted this form!";
	
	$checkbox_save_image = (!isset($options['cf7_save_image']) || $options['cf7_save_image'] == "1") ? 1 : 0;
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
		<input type="text" size="70" name="<?php echo esc_attr($args[0].'[cf7_error_message]');?>" value="<?php echo esc_attr($stored_error_message);?>"></input>
		</fieldset>
	</div>
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
			<input type="checkbox" id="cookie" name="<?php echo esc_attr($args[0].'[cf7_cookie_option]');?>" value="1" <?php echo esc_attr($checked_cookie ? 'checked' : '');?>></input>
			<label for="cookie">Activate this function</label>
		</div>
		</br>
		<div id="dk-unique-entries-cookie" style="display:none">
		<span>Cookie persistence - Number of days </span><input type="text" name="<?php echo esc_attr($args[0].'[cf7_cookie_option_days]');?>" size="5" value="<?php echo esc_attr($stored_cookie_days);?>"></input>
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
			<input type="checkbox" id="user_ip" name="<?php echo esc_attr($args[0].'[cf7_user_ip]');?>" value="1" <?php echo esc_attr($checkbox_ip ? 'checked' : '');?>></input>
			<label for="user_ip">Activate this function</label>
		</div>
		</br>
		<div id="dk-limit-ip" style="display:none">
		<span>Ser error message:</span><input type="text" size="70" name="<?php echo esc_attr($args[0].'[cf7_error_message_limit_ip]');?>" value="<?php echo esc_attr($stored_error_message_limit_ip);?>"></input>
		</br>
		</div>
		</fieldset>
	</div>
	<div class="dk-save-image-to-server">
	<fieldset class="dk-fieldset">
		<legend><strong>Save images on server</strong></legend>
		<strong>Stores images submitted through the form.</strong>
		<span> Warning: This will use your server storage space.</span>
		<br><br>

		<div class="dk-input-checkbox-callback">
			<input type="checkbox" id="save_image" name="<?php echo esc_attr($args[0] . '[cf7_save_image]'); ?>" value="1" <?php echo esc_attr($checkbox_save_image ? 'checked' : ''); ?>>
			<label for="save_image">Enable image saving</label>
		</div>

		<div id="dk-save-image-path" style="display:none">
			<p><strong>Images will be saved in the default folder:</strong> <code>/wp-content/uploads/duplicate-killer</code></p>
			<p><em>This location will be used automatically. No additional configuration is needed.</em></p>
		</div>
	</fieldset>
	</div>
<?php
}
function duplicateKiller_get_cf7_forms_info() {
    global $wpdb;
	
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reading CF7 forms from core posts table (admin-only, request-scoped).
    $results = $wpdb->get_results(
        "SELECT post_title, ID 
         FROM {$wpdb->posts}
         WHERE post_type = 'wpcf7_contact_form'
           AND post_status NOT IN ('trash','auto-draft')
         ORDER BY ID DESC",
        ARRAY_A
    );

    if ( empty( $results ) ) {
        return [];
    }

    $forms = [];

    foreach ( $results as $row ) {
        $title = sanitize_text_field( $row['post_title'] );
        $id    = (int) $row['ID'];

        if ( $id > 0 && $title !== '' ) {
            $forms[ $title ] = $id;
        }
    }

    return $forms;
}
function duplicateKiller_cf7_select_form_tag_callback($args){
	$forms     = duplicateKiller_CF7_get_forms();      // [ 'Form name' => [ 'field1', 'field2' ] ]

	$forms_ids = duplicateKiller_get_cf7_forms_info(); // [ 'Form name' => 123 ]

	duplicateKiller_render_forms_ui(
		'CF7',
		'Contact Form 7',
		$args,
		$forms,
		$forms_ids
	);
}