<?php
defined( 'ABSPATH' ) or die( 'You shall not pass!' );

// Inject JS that sets the cookie AND set the valuea as hidden input field
add_action('wp_footer', 'dk_inject_wpforms_cookie_with_hidden_field', 20);
function dk_inject_wpforms_cookie_with_hidden_field() {
	$wpforms_page = get_option("WPForms_page");

	if (!empty($wpforms_page['wpforms_cookie_option']) && $wpforms_page['wpforms_cookie_option'] === "1") {
		$days = (int) $wpforms_page['wpforms_cookie_option_days'];
		$cookie_value = md5(microtime(true) . mt_rand());
		?>
		<script id="dk-wpforms-cookie-handler" type="text/javascript">
		document.addEventListener("DOMContentLoaded", function () {
			var cookieName = "dk_form_cookie";
			var cookieValue = getCookie(cookieName);
			if (!cookieValue) {
				var date = new Date();
				date.setDate(date.getDate() + <?php echo $days; ?>);
				var expires = "expires=" + date.toUTCString();
				document.cookie = cookieName + "=<?php echo esc_js($cookie_value); ?>; " + expires + "; path=/";
				cookieValue = "<?php echo esc_js($cookie_value); ?>";
			}

			// Add hidden input to each form
			document.querySelectorAll("form.wpforms-form").forEach(function(form) {
				if (!form.querySelector('input[name="dk_form_cookie"]')) {
					var input = document.createElement('input');
					input.type = 'hidden';
					input.name = 'dk_form_cookie';
					input.value = cookieValue;
					form.appendChild(input);
				}
			});

			function getCookie(name) {
				var cookieArr = document.cookie.split(";");
				for (var i = 0; i < cookieArr.length; i++) {
					var cookiePair = cookieArr[i].split("=");
					if (name === cookiePair[0].trim()) {
						return decodeURIComponent(cookiePair[1]);
					}
				}
				return null;
			}
		});
		</script>
		<?php
	}
}

add_action( 'wpforms_process', 'duplicateKiller_wpforms_before_send_email', 10, 3 );
function duplicateKiller_wpforms_before_send_email($fields, $entry, $form_data){
	$form_title = $form_data['settings']['form_title'];
	$result = duplicateKiller_check_duplicate("WPForms",$form_title);
	
	global $wpdb;
	$table_name = $wpdb->prefix.'dk_forms_duplicate';
	$wpforms_page = get_option("WPForms_page");
	$form_cookie = isset($_POST['dk_form_cookie']) ? sanitize_text_field($_POST['dk_form_cookie']) : 'NULL';
	$abort = false;
	$storage_fields = array();
	$form_ip="";
	$no_form = true;
	//check if IP limit feature is active
	if($wpforms_page['wpforms_user_ip'] == "1"){
		$form_ip = dk_get_user_ip();
		$dk_check_ip_feature = dk_check_ip_feature("WPForms",$form_title,$form_ip);
		if($dk_check_ip_feature){
			$message = $wpforms_page['wpforms_error_message_limit_ip'];
			//change the general error message with the dk_custom_error_message
			add_filter('wpforms_custom_form_invalid_form_message',function($invalid_form_message, $form_data) use($message){
				$invalid_form_message = $message;
				return $invalid_form_message = $message;
			},15,2);
				//stop form for submission if IP limit is triggered
				wpforms()->process->errors[ $form_data[ 'id' ]][0] = $message;
				$abort = true;
		}else{
			//store fields in custom table dk
				foreach($fields as $data){
					$storage_fields[] = [
						"name" => $data['name'],
						"value" => $data['value']
					];
				}
		}
	}else{
		foreach($fields as $data){
			$storage_fields[] = [
				"name" => $data['name'],
				"value" => $data['value']
			];
			foreach($wpforms_page as $form => $value){
				if($form_title == $form){
					if(isset($value[$data['name']]) and $value[$data['name']] == 1){
						if($result){
							foreach($result as $row){
								$form_value = unserialize($row->form_value);
								//inserted from v1.2.1
								$res = duplicateKiller_check_values($form_value,$data['name'],$data['value']);

									if($res && $form_cookie === $row->form_cookie){
										wpforms()->process->errors[$form_data['id']][$data['id']] = $wpforms_page['wpforms_error_message'];
										$abort = true;
										break; // stop search, already found
								}
								/* deprecated from 1.2.1else{
									if(!empty($data['value']))
									$data_for_insert[$data['name']] = $data['value'];
								}
								*/
							}
						}/* deprecated from 1.2.1
						else{
							if(!empty($data['value']))
							$data_for_insert[$data['name']] = $data['value'];
						}
						*/
					}
				}
			}
		}
	}
	if(!$abort AND $no_form){
		//check if IP limit feature is active and store it
		if(!$form_ip){
			$dk_check_ip_feature = getDuplicateKillerSetting("WPForms","wpforms_user_ip");
			$form_ip = ($dk_check_ip_feature)? $form_ip=dk_get_user_ip(): $form_ip='NULL';
		}
		
		$form_value = serialize($storage_fields);
		$form_date = current_time('Y-m-d H:i:s');
		$wpdb->insert(
			$table_name, 
			array(
				'form_plugin' => "WPForms",
				'form_name' => $form_title,
				'form_value'   => $form_value,
				'form_cookie' => $form_cookie,
				'form_date' => $form_date,
				'form_ip' => $form_ip
			) 
		);
	}
}
function duplicateKiller_wpforms_get_forms(){
	$wpforms_posts = get_posts([
		'post_type' => 'wpforms',
		'order' => 'ASC',
		'nopaging' => true
	]);
	$output = array();

	foreach($wpforms_posts as $form){
		$form_data = json_decode($form->post_content, true);
		if (!empty( $form_data['fields'])){
			foreach((array) $form_data['fields'] as $key => $field){
				if($field['type'] == "name" OR
					$field['type'] == "text" OR
					$field['type'] == "email"){
					$output[$form->post_title][] = $field['label'];
				}
			}
		}
	}
	
	return $output;
}
/*********************************
 * Callbacks
**********************************/
function duplicateKiller_wpforms_validate_input($input){
	global $wpdb;
	$output = array();
	
	// DELETE if checkbox is checked
	if (isset($_POST['WPForms_delete_records'])) {
		$table = $wpdb->prefix . 'dk_forms_duplicate';

		// If there is only one checkbox checked and it is NOT an array (ex: name="WPForms_delete_records[Some Form]")
		if (!is_array($_POST['WPForms_delete_records'])) {
			$form_name = sanitize_text_field($_POST['WPForms_delete_records']);
			$wpdb->delete($table, [
				'form_plugin' => 'WPForms',
				'form_name'   => $form_name,
			]);

		} else {
			// If there are multiple checkboxes checked
			foreach ($_POST['WPForms_delete_records'] as $raw_form_name => $delete_flag) {
				if ($delete_flag === "1") {
					$form_name = sanitize_text_field($raw_form_name);
					$wpdb->delete($table, [
						'form_plugin' => 'WPForms',
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
				if($asc !== "1"){
					$value[$arr] = "1";
					$output[$key] = $value;
				}else{
					$output[$key] = $value;
				}
			}
		}
	}
	//validate cookies
	if(!isset($input['wpforms_cookie_option']) || $input['wpforms_cookie_option'] !== "1"){
		$output['wpforms_cookie_option'] = "0";
	}else{
		$output['wpforms_cookie_option'] = "1";
	}
	if(filter_var($input['wpforms_cookie_option_days'], FILTER_VALIDATE_INT) === false){
		$output['wpforms_cookie_option_days'] = 365;
	}else{
		$output['wpforms_cookie_option_days'] = sanitize_text_field($input['wpforms_cookie_option_days']);
	}
	
	//validate ip limit feature
	if(!isset($input['wpforms_user_ip']) || $input['wpforms_user_ip'] !== "1"){
		$output['wpforms_user_ip'] = "0";
	}else{
		$output['wpforms_user_ip'] = "1";
	}
	if(empty($input['wpforms_error_message_limit_ip'])){
		$output['wpforms_error_message_limit_ip'] = "You already submitted this form!";
	}else{
		$output['wpforms_error_message_limit_ip'] = sanitize_text_field($input['wpforms_error_message_limit_ip']);
	}
	
	
    if(empty($input['wpforms_error_message'])){
		$output['wpforms_error_message'] = "Please check all fields! These values has been submitted already!";
	}else{
		$output['wpforms_error_message'] = sanitize_text_field($input['wpforms_error_message']);
	}
     
    // Return the array processing any additional functions filtered by this action
    return apply_filters('wpforms_error_message', $output, $input);
}

function duplicateKiller_WPForms_description() {
	if(class_exists('wpforms') OR is_plugin_active('wpforms-lite/wpforms.php')){?>
		<h3 style="color:green"><strong><?php esc_html_e('WPForms plugin is activated!','duplicatekiller');?></strong></h3>
<?php
	}else{ ?>
		<h3 style="color:red"><strong><?php esc_html_e('WPForms plugin is not activated! Please activate it in order to continue.','duplicatekiller');?></strong></h3>
<?php
		exit();
	}
	if(duplicateKiller_wpforms_get_forms() == NULL){ ?>
		</br><h3 style="color:red"><strong><?php esc_html_e('There is no contact forms. Please create one!','duplicatekiller');?></strong></h3>
<?php
		exit();
	}
}

function duplicateKiller_wpforms_settings_callback($args){
	$options = get_option($args[0]);
	$checked_cookie = isset($options['wpforms_cookie_option']) AND ($options['wpforms_cookie_option'] == "1")?: $checked_cookie='';
	$stored_cookie_days = isset($options['wpforms_cookie_option_days'])? $options['wpforms_cookie_option_days']:"365";
	$stored_error_message = isset($options['wpforms_error_message'])? $options['wpforms_error_message']:"Please check all fields! These values has been submitted already!"; 
	
	$checkbox_ip = isset($options['wpforms_user_ip']) AND ($options['wpforms_user_ip'] == "1")?: $checkbox_ip='';
	$stored_error_message_limit_ip = isset($options['wpforms_error_message_limit_ip'])? $options['wpforms_error_message_limit_ip']:"You already submitted this form!";
	
	?>
	<h4 class="dk-form-header">Duplicate Killer settings</h4>
	<div class="dk-set-error-message">
		<fieldset class="dk-fieldset">
		<legend><strong>Set error message:</strong></legend>
		<span>Warn the user that the value inserted has been already submitted!</span>
		</br>
		<input type="text" size="70" name="<?php echo esc_attr($args[0].'[wpforms_error_message]');?>" value="<?php echo esc_attr($stored_error_message);?>"></input>
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
			<input type="checkbox" id="cookie" name="<?php echo esc_attr($args[0].'[wpforms_cookie_option]');?>" value="1" <?php echo esc_attr($checked_cookie ? 'checked' : '');?>></input>
			<label for="cookie">Activate this function</label>
		</div>
		</br>
		<div id="dk-unique-entries-cookie" style="display:none">
		<span>Cookie persistence - Number of days </span><input type="text" name="<?php echo esc_attr($args[0].'[wpforms_cookie_option_days]');?>" size="5" value="<?php echo esc_attr($stored_cookie_days);?>"></input>
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
			<input type="checkbox" id="user_ip" name="<?php echo esc_attr($args[0].'[wpforms_user_ip]');?>" value="1" <?php echo esc_attr($checkbox_ip ? 'checked' : '');?>></input>
			<label for="user_ip">Activate this function</label>
		</div>
		</br>
		<div id="dk-limit-ip" style="display:none">
		<span>Ser error message:</span><input type="text" size="70" name="<?php echo esc_attr($args[0].'[wpforms_error_message_limit_ip]');?>" value="<?php echo esc_attr($stored_error_message_limit_ip);?>"></input>
		</br>
		</div>
		</fieldset>
	</div>
<?php
}
function duplicateKiller_wpforms_select_form_tag_callback($args){
	global $wpdb;

	$options = get_option($args[0]);
	$table   = $wpdb->prefix . 'dk_forms_duplicate';

	// Get all counts in a single query
	$counts = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT form_name, COUNT(*) as total FROM $table WHERE form_plugin = %s GROUP BY form_name",
			'WPForms'
		),
		OBJECT_K // => will return an array with key form_name
	);
?>
	<h4 class="dk-form-header">WPForms forms list</h4>
<?php
	$wp_forms = duplicateKiller_wpforms_get_forms();
	foreach($wp_forms as $form => $tag):
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
						name="<?php echo esc_attr('WPForms_delete_records[' . $form . ']'); ?>"
						value="1"
						class="dk-delete-checkbox">
					🗑️ <span>Delete all saved entries for this form <small>(this action cannot be undone)</small></span>
				</label>
				<?php endif; ?>
			</div>
		</div>
<?php endforeach;
}