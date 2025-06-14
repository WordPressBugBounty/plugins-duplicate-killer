<?php
defined( 'ABSPATH' ) or die( 'You shall not pass!' );

/**
 * check if Forminator load the stylesheets and call the custom function (dk_forminator_is_cookie_set) if so
 */
add_filter( 'the_content', 'dk_check_forminator_enqueue' );
function dk_check_forminator_enqueue( $content ) {
	if(has_shortcode($content, 'forminator_form') OR function_exists('forminator_print_front_styles')){
		dk_forminator_is_cookie_set();
	}
	return $content;
}
function dk_forminator_is_cookie_set(){
	if($forminator_page = get_option("Forminator_page")){
	if(isset($forminator_page['forminator_cookie_option']) AND $forminator_page['forminator_cookie_option'] == "1"){
		dk_checked_defined_constants('dk_cookie_unique_time',md5(microtime(true).mt_Rand()));
		dk_checked_defined_constants('dk_cookie_days_persistence',$forminator_page['forminator_cookie_option_days']);
		add_action( 'wp_footer', function(){?>
		<script id="duplicate-killer-forminator-form" type="text/javascript">
			(function($){
			if($('button').hasClass('forminator-button-submit')){
				if(!getCookie('dk_form_cookie')){
					var date = new Date();
					date.setDate(date.getDate()+<?php echo esc_attr(dk_cookie_days_persistence);?>);
					var dk_forminator_form_cookie_days = date.toUTCString();
					document.cookie = "dk_form_cookie=<?php echo esc_attr(dk_cookie_unique_time);?>; expires="+dk_forminator_form_cookie_days+"; path=/";
				}
			}
			})(jQuery);
			function getCookie(ck_name) {
				var cookieArr = document.cookie.split(";");
				for(var i = 0; i < cookieArr.length; i++) {
					var cookiePair = cookieArr[i].split("=");
					if(ck_name == cookiePair[0].trim()) {
						return decodeURIComponent(cookiePair[1]);
					}
				}
				return null;
			}
		</script>
<?php
		}, 999 );
	}
	}
}
add_action( 'forminator_custom_form_submit_before_set_fields', 'duplicateKiller_forminator_save_fields', 10, 3 );
function duplicateKiller_forminator_save_fields($entry, $id, $field_data) {
	global $wpdb;
	$table_name = $wpdb->prefix.'dk_forms_duplicate';
	$form_title = get_the_title($id);
	$form_cookie = isset($_COOKIE['dk_form_cookie'])? $form_cookie=$_COOKIE['dk_form_cookie']: $form_cookie='NULL';
	$dk_check_ip_feature = getDuplicateKillerSetting("Forminator_page","forminator_user_ip");
	$form_ip = ($dk_check_ip_feature)? $form_ip=dk_get_user_ip(): $form_ip='NULL';

	foreach($field_data as $data){
		if(is_array($data['value']) AND isset($data['value']['file']) AND (!empty($data['value']['file']))){
			$storage_fields[] = [
				"name" => $data['name'],
				"value" => array(
					"file_name" => $data['value']['file']['file_name'],
					"file_url" => $data['value']['file']['file_url'])
			]; 
		}else{
			$storage_fields[] = [
				"name" => $data['name'],
				"value" => $data['value']
			];
		}
		
	}
	$form_value = serialize($storage_fields);
	$form_date = current_time('Y-m-d H:i:s');
		$wpdb->insert(
			$table_name, 
			array(
				'form_plugin' => "Forminator",
				'form_name' => $form_title,
				'form_value'   => $form_value,
				'form_cookie' => $form_cookie,
				'form_date' => $form_date,
				'form_ip' => $form_ip
			) 
		);
	//error_log( print_r( $field_data, true ) );
}

add_action( 'forminator_custom_form_submit_errors','duplicateKiller_forminator_before_send_email',10,3);
function duplicateKiller_forminator_before_send_email($submit_errors, $form_id, $field_data_array){
	global $wpdb;
    $table_name = $wpdb->prefix.'dk_forms_duplicate';
	$forminator_page = get_option("forminator_page");
	
	$form_title = get_the_title( $form_id );
	
	//check if IP limit feature is active
	if($forminator_page['forminator_user_ip'] == "1"){
		$form_ip = dk_get_user_ip();
		$dk_check_ip_feature = dk_check_ip_feature("Forminator",$form_title,$form_ip);
		if($dk_check_ip_feature){
			$message = $forminator_page['forminator_error_message_limit_ip'];
			//change the general error message with the dk_custom_error_message
			add_filter('forminator_custom_form_invalid_form_message',function($invalid_form_message, $form_id) use($message){
				$invalid_form_message = $message;
				return $invalid_form_message = $message;
			},15,2);
			//stop form for submission if IP limit is triggered
				$submit_errors[] = $message;
				return $submit_errors;
		}
	}
	$abort = false;
	$form_cookie = isset($_COOKIE['dk_form_cookie'])? $form_cookie=$_COOKIE['dk_form_cookie']: $form_cookie='NULL';
	$storage_fields = array();
	$no_form = true;
	
	$result = duplicateKiller_check_duplicate("Forminator",$form_title);
	if($result AND $forminator_page){
	//$form_data = array(); deprecated from 1.2.1
	
	foreach($field_data_array as $data){
		//store form values
		$storage_fields[] = [
			"name" => $data['name'],
			"value" => $data['value']
		];
		foreach($forminator_page as $form => $value){
			if($form_title == $form){
				if(isset($value[$data['name']]) and $value[$data['name']] == 1){
						foreach($result as $row){
							$form_value = unserialize($row->form_value);
							//inserted from v1.2.1
							$res = duplicateKiller_check_values($form_value,$data['name'],$data['value']);
								if($res){
									$cookies_setup = [
										'plugin_name' => "forminator_cookie_option",
										'get_option' => $forminator_page,
										'cookie_stored' => $form_cookie,
										'cookie_db_set' => $row->form_cookie
									];
									if(dk_check_cookie($cookies_setup)){
										if(is_array($data['value'])){
											$submit_errors[][$data['name'].'-first-name'] = $forminator_page['forminator_error_message'];
											$submit_errors[][$data['name'].'-middle-name'] = $forminator_page['forminator_error_message'];
											$submit_errors[][$data['name'].'-last-name'] = $forminator_page['forminator_error_message'];
											$abort = true;
										}else{
											$submit_errors[][$data['name']] = $forminator_page['forminator_error_message'];
											$abort = true;
										}
									}
								}/* deprecated from 1.2.1
								else{
									if(!empty($data['value'])){
										$form_data[$data['name']] = $data['value'];
									}
									
								}
								*/
						}
					}/* deprecated from 1.2.1
					else{
						if(!empty($data['value'])){
							$form_data[$data['name']] = $data['value'];
						}
						
					}
					*/
				}
			}
		}
	}
	/*deprecated from 1.2.1
	
	if(!$abort and $no_form){
		$form_value = serialize($storage_fields);
		$wpdb->insert(
			$table_name, 
			array(
				'form_plugin' => "Forminator",
				'form_name' => $form_title,
				'form_value'   => $form_value,
				'form_cookie' => $form_cookie
			) 
		);							
	}
	*/
	return $submit_errors;
}

function duplicateKiller_forminator_get_forms(){
	$forminator_posts = get_posts([
		'post_type' => 'forminator_forms',
		'order' => 'ASC',
		'nopaging' => true
	]);
	$output = array();
	foreach($forminator_posts as $form => $value){
		if($result = get_post_meta($value->ID,'',true)){
			$result = maybe_unserialize($result['forminator_form_meta'][0]);
			foreach($result as $res => $var){
				if($var){
					foreach($var as $arr){
						if(isset($arr['id'])){
							if($arr['type'] == "name" OR
								$arr['type'] == "text" OR
								$arr['type'] == "email" OR
								$arr['type'] == "phone"
							)
							$output[$value->post_title][] = $arr['id'];
						}	
					}
				}
			}
		}
	}
	return $output;
}
/*********************************
 * Callbacks
**********************************/
function duplicateKiller_forminator_validate_input($input){
	global $wpdb;
	$output = array();
	
	// DELETE if checkbox is checked
	if (isset($_POST['Forminator_delete_records'])) {
		$table = $wpdb->prefix . 'dk_forms_duplicate';

		// If there is only one checkbox checked and it is NOT an array (ex: name="Forminator_delete_records[Some Form]")
		if (!is_array($_POST['Forminator_delete_records'])) {

			$form_name = sanitize_text_field($_POST['Forminator_delete_records']);
			$wpdb->delete($table, [
				'form_plugin' => 'Forminator',
				'form_name'   => $form_name,
			]);

		} else {

			// If there are multiple checkboxes checked
			foreach ($_POST['Forminator_delete_records'] as $raw_form_name => $delete_flag) {
				if ($delete_flag === "1") {
					$form_name = sanitize_text_field($raw_form_name);
					$wpdb->delete($table, [
						'form_plugin' => 'Forminator',
						'form_name'   => $form_name,
					]);
				}
			}
		}
	}
	
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
	if(!isset($input['forminator_cookie_option']) || $input['forminator_cookie_option'] !== "1"){
		$output['forminator_cookie_option'] = "0";
	}else{
		$output['forminator_cookie_option'] = "1";
	}
	if(filter_var($input['forminator_cookie_option_days'], FILTER_VALIDATE_INT) === false){
		$output['forminator_cookie_option_days'] = 365;
	}else{
		$output['forminator_cookie_option_days'] = sanitize_text_field($input['forminator_cookie_option_days']);
	}
	
	//validate ip limit feature
	if(!isset($input['forminator_user_ip']) || $input['forminator_user_ip'] !== "1"){
		$output['forminator_user_ip'] = "0";
	}else{
		$output['forminator_user_ip'] = "1";
	}
	if(empty($input['forminator_error_message_limit_ip'])){
		$output['forminator_error_message_limit_ip'] = "You already submitted this form!";
	}else{
		$output['forminator_error_message_limit_ip'] = sanitize_text_field($input['forminator_error_message_limit_ip']);
	}
	
	//validate standard error message
    if(empty($input['forminator_error_message'])){
		$output['forminator_error_message'] = "Please check all fields! These values has been submitted already!";
	}else{
		$output['forminator_error_message'] = sanitize_text_field($input['forminator_error_message']);
	}
    // Return the array processing any additional functions filtered by this action
      return apply_filters( 'forminator_error_message', $output, $input );
}

function duplicateKiller_forminator_description(){
	if(class_exists('Forminator') OR is_plugin_active('forminator/forminator.php')){ ?>
		<h3 style="color:green"><strong><?php esc_html_e('Forminator plugin is activated!','duplicatekiller');?></strong></h3>
<?php
	}else{ ?>
		<h3 style="color:red"><strong><?php esc_html_e('Forminator plugin is not activated! Please activate it in order to continue.','duplicatekiller');?></strong></h3>
<?php
		exit();
	}
	if(duplicateKiller_forminator_get_forms() == NULL){ ?>
		</br><span style="color:red"><strong><?php esc_html_e('There is no contact forms. Please create one!','duplicatekiller');?></strong></span>
<?php
		exit();
	}
}

function duplicateKiller_forminator_settings_callback($args){
	$options = get_option($args[0]);
	$checked_cookie = isset($options['forminator_cookie_option']) AND ($options['forminator_cookie_option'] == "1")?: $checked_cookie='';
	$stored_cookie_days = isset($options['forminator_cookie_option_days'])? $options['forminator_cookie_option_days']:"365";
	
	$checkbox_ip = isset($options['forminator_user_ip']) AND ($options['forminator_user_ip'] == "1")?: $checkbox_ip='';
	$stored_error_message_limit_ip = isset($options['forminator_error_message_limit_ip'])? $options['forminator_error_message_limit_ip']:"You already submitted this form!";
	
	$stored_error_message = isset($options['forminator_error_message'])? $options['forminator_error_message']:"Please check all fields! These values has been submitted already!";
	?>
	<h4 class="dk-form-header">Duplicate Killer settings</h4>
	<div class="dk-set-error-message">
		<fieldset class="dk-fieldset">
		<legend><strong>Set error message:</strong></legend>
		<span>Warn the user that the value inserted has been already submitted!</span>
		</br>
		<input type="text" size="70" name="<?php echo esc_attr($args[0].'[forminator_error_message]');?>" value="<?php echo esc_attr($stored_error_message);?>"></input>
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
			<input type="checkbox" id="cookie" name="<?php echo esc_attr($args[0].'[forminator_cookie_option]');?>" value="1" <?php echo esc_attr($checked_cookie ? 'checked' : '');?>></input>
			<label for="cookie">Activate this function</label>
		</div>
		</br>
		<div id="dk-unique-entries-cookie" style="display:none">
		<span>Cookie persistence - Number of days </span><input type="text" name="<?php echo esc_attr($args[0].'[forminator_cookie_option_days]');?>" size="5" value="<?php echo esc_attr($stored_cookie_days);?>"></input>
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
			<input type="checkbox" id="user_ip" name="<?php echo esc_attr($args[0].'[forminator_user_ip]');?>" value="1" <?php echo esc_attr($checkbox_ip ? 'checked' : '');?>></input>
			<label for="user_ip">Activate this function</label>
		</div>
		</br>
		<div id="dk-limit-ip" style="display:none">
		<span>Ser error message:</span><input type="text" size="70" name="<?php echo esc_attr($args[0].'[forminator_error_message_limit_ip]');?>" value="<?php echo esc_attr($stored_error_message_limit_ip);?>"></input>
		</br>
		</div>
		</fieldset>
	</div>
<?php
}
function duplicateKiller_forminator_select_form_tag_callback($args){
	global $wpdb;

	$options = get_option($args[0]);
	$table   = $wpdb->prefix . 'dk_forms_duplicate';

	// Get all counts in a single query
	$counts = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT form_name, COUNT(*) as total FROM $table WHERE form_plugin = %s GROUP BY form_name",
			'Forminator'
		),
		OBJECT_K // => will return an array with key form_name
	);
	?>
	<h4 class="dk-form-header">Forminator forms list</h4>
<?php
	$forminator_forms = duplicateKiller_forminator_get_forms();
	foreach($forminator_forms as $form => $tag):
	//get all counts for this form
		$count = isset($counts[$form]) ? (int)$counts[$form]->total : 0;
?>
		<div class="dk-single-form"><h4 class="dk-form-header"><?php esc_html_e($form,'duplicatekiller');?></h4>
		<h4 style="text-align:center">Choose the unique fields</h4>
<?php
		for($i=0;$i<count($tag);$i++):
			$checked = isset($options[$form][$tag[$i]])?: $checked='';?>
			<div class="dk-input-checkbox-callback">
			<input type="checkbox" id="<?php echo esc_attr($form.'['.$tag[$i].']');?>" name="<?php echo esc_attr($args[0].'['.$form.']['.$tag[$i].']');?>" value="1" <?php echo esc_attr($checked ? 'checked' : '');?>>
			<label for="<?php echo esc_attr($form.'['.$tag[$i].']');?>"><?php echo esc_attr($tag[$i]);?></label></br>
			</div>
<?php
		endfor; ?>
		<!-- New checkbox from v1.3.1: delete submissions -->
		<div class="dk-box dk-delete-records">
				<p class="dk-record-count">
					📦 <span class="dk-count-number"><?php echo esc_html($count); ?></span> saved submissions found for this form
				</p>
				<?php if ($count > 0) : ?>
				<label for="<?php echo esc_attr('delete_records_' . $form); ?>" class="dk-delete-label">
					<input type="checkbox"
						id="<?php echo esc_attr('delete_records_' . $form); ?>"
						name="<?php echo esc_attr('Forminator_delete_records[' . $form . ']'); ?>"
						value="1"
						class="dk-delete-checkbox">
					🗑️ <span>Delete all saved entries for this form <small>(this action cannot be undone)</small></span>
				</label>
				<?php endif; ?>
			</div>
		</div>
<?php endforeach;
}