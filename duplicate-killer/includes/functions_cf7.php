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
	$form_name = $contact_form->title();
	$form_cookie = isset($_COOKIE['dk_form_cookie'])? $form_cookie=$_COOKIE['dk_form_cookie']: $form_cookie='NULL';
    if($submission){
		$abort = false;
		$no_form = false;
        $data = $submission->get_posted_data();
        $form_data = array();
        foreach ($data as $key => $d) {
            $tmpD = $d;
				if (!is_array($d)){
					$bl   = array('\"',"\'",'/','\\','"',"'");
					$wl   = array('&quot;','&#039;','&#047;', '&#092;','&quot;','&#039;');
                    $tmpD = str_replace($bl, $wl, $tmpD );
                }
                //$form_data[$key] = $tmpD;
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
									}else{
										if(!empty($tmpD))
										$form_data[$key] = $tmpD;
									}
								}
							}else{
								if(!empty($tmpD))
								$form_data[$key] = $tmpD;
							}
						}
					}
				}
        }
		if(!$abort AND $no_form){
			$form_value = serialize($form_data);
			$wpdb->insert(
			$table_name,
			array(
				'form_plugin' => "CF7",
				'form_name' => $form_name,
				'form_value'   => $form_value,
				'form_cookie' => $form_cookie
			) 
		);
		}
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
					$output[$form['post_title']][] = trim($tagsArray[$i+1],"]");
				}
				if(str_contains($tagsArray[$i],"[email")){
					$output[$form['post_title']][] = trim($tagsArray[$i+1],"]");
				}
				if(str_contains($tagsArray[$i],"[tel")){
					$output[$form['post_title']][] = trim($tagsArray[$i+1],"]");
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
	$stored_error_message = isset($options['cf7_error_message'])? $options['cf7_error_message']:"Please check all fields! These values has been submitted already!"; ?>
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
		<script>
			var checkbox = document.getElementById("cookie");
			checkbox.addEventListener('change', function() {
				if (this.checked) {
					document.getElementById("dk-unique-entries-cookie").style.display = "block";
				}else{
					document.getElementById("dk-unique-entries-cookie").style.display = "none";
				}
			});
			if (checkbox.checked == true) {
				document.getElementById("dk-unique-entries-cookie").style.display = "block";
			}
		</script>
	</div>
<?php
}
function duplicateKiller_cf7_select_form_tag_callback($args){
	$options = get_option($args[0]); ?>
	<h4 class="dk-form-header">CF7 forms list</h4>
<?php
	foreach(duplicate_killer_CF7_get_forms() as $form => $tag): ?>
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
		endfor; ?>
		</div>
<?php endforeach; ?>

<?php
}