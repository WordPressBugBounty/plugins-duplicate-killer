<?php
defined( 'ABSPATH' ) or die( 'You shall not pass!' );

/**
 * check if CF7 load the stylesheets and call the custom function (dk_cf7_is_cookie_set) if so
 */
add_filter( 'the_content', 'dk_check_cf7_enqueue' );
function dk_check_cf7_enqueue( $content ) {
	if(has_shortcode($content, 'contact-form-7') OR function_exists( 'wpcf7_enqueue_styles')){
		dk_cf7_is_cookie_set();
	}
	return $content;
}
function dk_cf7_is_cookie_set(){
	if($cf7_page = get_option("CF7_page")){
	if(isset($cf7_page['cf7_cookie_option']) AND $cf7_page['cf7_cookie_option'] == "1"){
		dk_checked_defined_constants('dk_cookie_unique_time',md5(microtime(true).mt_Rand()));
		dk_checked_defined_constants('dk_cookie_days_persistence',$cf7_page['cf7_cookie_option_days']);
		
		

		add_action( 'wp_footer', function(){?>
		<script id="duplicate-killer-wpcf7-form" type="text/javascript">
			(function($){
			if($('input').hasClass('wpcf7-submit')){
				if(!getCookie('dk_form_cookie')){
					var date = new Date();
					date.setDate(date.getDate()+<?php echo esc_attr(dk_cookie_days_persistence);?>);
					var dk_cf7_form_cookie_days = date.toUTCString();
					document.cookie = "dk_form_cookie=<?php echo esc_attr(dk_cookie_unique_time);?>; expires="+dk_cf7_form_cookie_days+"; path=/";
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
function duplicateKiller_cf7_before_send_email($contact_form, &$abort, $object) {

    global $wpdb;
    $table_name = $wpdb->prefix.'dk_forms_duplicate';
	$cf7_page = get_option("CF7_page");
    $submission = WPCF7_Submission::get_instance();
	//upload files if any
	$files = $submission->uploaded_files();
	$upload_dir = wp_upload_dir();
    $dkcf7_folder = $upload_dir['basedir'].'/dkcf7_uploads';
	$dkcf7_folder_url = $upload_dir['baseurl'].'/dkcf7_uploads';
	$form_name = $contact_form->title();
	
	$form_cookie = isset($_COOKIE['dk_form_cookie'])? $form_cookie=$_COOKIE['dk_form_cookie']: $form_cookie='NULL';
	$abort = false;
	$no_form = false;
	$data = $submission->get_posted_data();
	
	$form_ip = "";
	//check if IP limit feature is active
	if($cf7_page['cf7_user_ip'] == "1"){
		$no_form = true;
		$form_ip = dk_get_user_ip();
		$dk_check_ip_feature = dk_check_ip_feature("CF7",$form_name,$form_ip);
		if($dk_check_ip_feature){
			$message = $cf7_page['cf7_error_message_limit_ip'];
			//change the general error message with the dk_custom_error_message
			add_filter('cf7_custom_form_invalid_form_message',function($invalid_form_message, $contact_form) use($message){
				$invalid_form_message = $message;
				return $invalid_form_message = $message;
			},15,2);
			//stop form for submission if IP limit is triggered
				$abort = true;
				$object->set_response($message);
		}
	}else{
		if($data AND $cf7_page){
			foreach ($data as $key => $d) {
				$tmpD = $d;
					if(!is_array($d)){
						$bl = array('\"',"\'",'/','\\','"',"'");
						$wl = array('&quot;','&#039;','&#047;', '&#092;','&quot;','&#039;');
						$tmpD = str_replace($bl, $wl, $tmpD);
					}
					foreach($cf7_page as $cf7_form => $cf7_tag){
						if($form_name == $cf7_form){
							if(array_key_exists($key,$cf7_tag)){
								$no_form = true;
								if($result = duplicateKiller_check_duplicate("CF7",$form_name)){
									foreach($result as $row){
										$form_value = unserialize($row->form_value);
										if(isset($form_value[$key]) AND duplicateKiller_check_values_with_lowercase_filter($form_value[$key],$tmpD)){
											if(function_exists('cfdb7_before_send_mail')){
												remove_action('wpcf7_before_send_mail', 'cfdb7_before_send_mail');
											}
											if(function_exists('vsz_cf7_before_send_email')){
												remove_action('wpcf7_before_send_mail', 'vsz_cf7_before_send_email');
											}
											$cookies_setup = [
												'plugin_name' => "cf7_cookie_option",
												'get_option' => $cf7_page,
												'cookie_stored' => $form_cookie,
												'cookie_db_set' => $row->form_cookie
											];
											if(dk_check_cookie($cookies_setup)){
												$abort = true;
												$object->set_response($cf7_page['cf7_error_message']);
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
	if(!$abort AND $no_form){
		
		//check if IP limit feature is active and store it
		if(!$form_ip){
			$dk_check_ip_feature = getDuplicateKillerSetting("CF7","forminator_user_ip");
			$form_ip = ($dk_check_ip_feature)? $form_ip=dk_get_user_ip(): $form_ip='NULL';
		}
			//check if user want to save the files locally
			if (!isset($cf7_page['cf7_save_image']) || $cf7_page['cf7_save_image'] == 1) {
				//upload files if any
				if($files){
					$random_number = uniqid(time());
					foreach ($files as $file_key => $file) {
						$file = is_array( $file ) ? reset( $file ) : $file;
						if( empty($file) ) continue;
						$file_path = $dkcf7_folder.'/'.$file_key.'-'.$random_number.'-'.basename($file);
						$file_url = $dkcf7_folder_url.'/'.$file_key.'-'.$random_number.'-'.basename($file);
						copy($file, $file_path);
						if(array_key_exists($file_key, $data)){
							$data[$file_key] = $file_url;
						}
					}
				}
			}
			$form_value = serialize($data);
			$form_date = current_time('Y-m-d H:i:s');
			$wpdb->insert(
			$table_name,
			array(
				'form_plugin' => "CF7",
				'form_name' => $form_name,
				'form_value'   => $form_value,
				'form_cookie' => $form_cookie,
				'form_date' => $form_date,
				'form_ip' => $form_ip
			) 
		);
	}
}
add_action( 'wpcf7_before_send_mail', 'duplicateKiller_cf7_before_send_email', 1,3 );
function duplicate_killer_CF7_get_forms(){
	global $wpdb;	
	$CF7Query = $wpdb->get_results( "SELECT * FROM $wpdb->posts WHERE post_type = 'wpcf7_contact_form'", ARRAY_A );
	if($CF7Query == NULL){
		return false;
	}else{
		$output = array();
		foreach($CF7Query as $form){
			$tagsArray = explode(" ",$form['post_content']);
			
			
			for($i=0;$i<count($tagsArray);$i++){
				
				if(str_contains($tagsArray[$i],"[text")){
					//splits a string into an array based on a specified delimiter
					$result = explode(']', $tagsArray[$i+1]);
					
					//return the first element of the resulting array
					$output[$form['post_title']][] = $result[0];
				}
				if(str_contains($tagsArray[$i],"[email")){
					$result = explode(']', $tagsArray[$i+1]);
					$output[$form['post_title']][] = $result[0];
				}
				if(str_contains($tagsArray[$i],"[tel")){
					$result = explode(']', $tagsArray[$i+1]);
					$output[$form['post_title']][] = $result[0];
				}
				if(str_contains($tagsArray[$i],"[submit")){
					break;
				}
			}
		}
		return $output;
	}
}

/*********************************
 * Callbacks
**********************************/
function duplicateKiller_cf7_validate_input($input){
	global $wpdb;
	$output = array();
	
	
	// DELETE if checkbox is checked
	if (isset($_POST['CF7_delete_records'])) {
		$table = $wpdb->prefix . 'dk_forms_duplicate';

		// If there is only one checkbox checked and it is NOT an array (ex: name="CF7_delete_records[Some Form]")
		if (!is_array($_POST['CF7_delete_records'])) {

			$form_name = sanitize_text_field($_POST['CF7_delete_records']);
			$wpdb->delete($table, [
				'form_plugin' => 'CF7',
				'form_name'   => $form_name,
			]);

		} else {

			// If there are multiple checkboxes checked
			foreach ($_POST['CF7_delete_records'] as $raw_form_name => $delete_flag) {
				if ($delete_flag === "1") {
					$form_name = sanitize_text_field($raw_form_name);
					$wpdb->delete($table, [
						'form_plugin' => 'CF7',
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
		<h3 style="color:green"><strong><?php esc_html_e('Contact-form-7 plugin is activated!','duplicatekiller');?></strong></h3>
<?php
	}else{ ?>
		<h3 style="color:red"><strong><?php esc_html_e('Contact-form-7 plugin is not activated! Please activate it in order to continue.','duplicatekiller');?></strong></h3>
<?php
		exit();
	}
	if(duplicate_killer_CF7_get_forms() == NULL){ ?>
		</br><span style="color:red"><strong><?php esc_html_e('There is no contact forms. Please create one!','duplicatekiller');?></strong></span>
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
		<legend><strong>Set error message:</strong></legend>
		<span>Warn the user that the value inserted has been already submitted!</span>
		</br>
		<input type="text" size="70" name="<?php echo esc_attr($args[0].'[cf7_error_message]');?>" value="<?php echo esc_attr($stored_error_message);?>"></input>
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
		<legend><strong>Limit submissions by IP address for 7 days for all forms!</strong></legend>
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
			<p><strong>Images will be saved in the default folder:</strong> <code>/wp-content/uploads/dkcf7_uploads</code></p>
			<p><em>This location will be used automatically. No additional configuration is needed.</em></p>
		</div>
	</fieldset>
	</div>
<?php
}
function duplicateKiller_cf7_select_form_tag_callback($args){
	global $wpdb;

	$options = get_option($args[0]);
	$table   = $wpdb->prefix . 'dk_forms_duplicate';

	// Get all counts in a single query
	$counts = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT form_name, COUNT(*) as total FROM $table WHERE form_plugin = %s GROUP BY form_name",
			'CF7'
		),
		OBJECT_K // => will return an array with key form_name
	);
	?>
	<h4 class="dk-form-header">CF7 forms list</h4>
<?php
	foreach(duplicate_killer_CF7_get_forms() as $form => $tag):
		//get all counts for this form
		$count = isset($counts[$form]) ? (int)$counts[$form]->total : 0;
	?>
		<div class="dk-single-form"><h4 class="dk-form-header"><?php esc_html_e($form,'duplicatekiller');?></h4>
		<h4 style="text-align:center">Choose the unique fields</h4>
<?php
		for($i=0;$i<count($tag);$i++):
			$checked = isset($options[$form][$tag[$i]])?: $checked=''; ?>
			<div class="dk-input-checkbox-callback">
			<input type="checkbox" id="<?php echo esc_attr($form.'['.$tag[$i].']');?>" name="<?php echo esc_attr('CF7_page['.$form.']['.$tag[$i].']');?>" value="1" <?php echo esc_attr($checked ? 'checked' : '');?>>

			<label for="<?php echo esc_attr($form.'['.$tag[$i].']');?>"><?php echo esc_attr($tag[$i]);?></label></br>
			</div>

<?php
		endfor;
		?>
		<!-- New checkbox from v1.3.1: delete submissions -->
		<div class="dk-box dk-delete-records">
				<p class="dk-record-count">
					📦 <span class="dk-count-number"><?php echo esc_html($count); ?></span> saved submissions found for this form
				</p>
				<?php if ($count > 0) : ?>
					<label for="<?php echo esc_attr('delete_records_' . $form); ?>" class="dk-delete-label">
						<input type="checkbox"
							id="<?php echo esc_attr('delete_records_' . $form); ?>"
							name="<?php echo esc_attr('CF7_delete_records[' . $form . ']'); ?>"
							value="1"
							class="dk-delete-checkbox">
						🗑️ <span>Delete all saved entries for this form <small>(this action cannot be undone)</small></span>
					</label>
				<?php endif; ?>
			</div>
		</div>
<?php endforeach; ?>

<?php
}