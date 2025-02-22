<?php
defined( 'ABSPATH' ) or die( 'You shall not pass!' );

/**
 * check if WPForms load the stylesheets and call the custom function (dk_wpforms_is_cookie_set) if so
 */
 add_filter( 'the_content', 'dk_check_wpforms_enqueue' );
function dk_check_wpforms_enqueue( $content ) {
	if(has_shortcode($content, 'wpforms') OR ( function_exists( 'has_block' ) && has_block( 'wpforms/form-selector' ) )){
		dk_wpforms_is_cookie_set();
	}
	return $content;
}
function dk_wpforms_is_cookie_set(){
	if($forminator_page = get_option("WPForms_page")){
	if(isset($forminator_page['wpforms_cookie_option']) AND $forminator_page['wpforms_cookie_option'] == "1"){
		dk_checked_defined_constants('dk_cookie_unique_time',md5(microtime(true).mt_Rand()));
		dk_checked_defined_constants('dk_cookie_days_persistence',$forminator_page['wpforms_cookie_option_days']);
		add_action( 'wp_footer', function(){?>
		<script id="duplicate-killer-wpforms-form" type="text/javascript">
			(function($){
			if($('button').hasClass('wpforms-submit')){
				if(!getCookie('dk_form_cookie')){
					var date = new Date();
					date.setDate(date.getDate()+<?php echo esc_attr(dk_cookie_days_persistence);?>);
					var dk_wpforms_form_cookie_days = date.toUTCString();
					document.cookie = "dk_form_cookie=<?php echo esc_attr(dk_cookie_unique_time);?>; expires="+dk_wpforms_form_cookie_days+"; path=/";
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

add_action( 'wpforms_process', 'duplicateKiller_wpforms_before_send_email', 10, 3 );
function duplicateKiller_wpforms_before_send_email($fields, $entry, $form_data){
	$form_title = $form_data['settings']['form_title'];
	$result = duplicateKiller_check_duplicate("WPForms",$form_title);
	
	global $wpdb;
	$table_name = $wpdb->prefix.'dk_forms_duplicate';
	$wpforms_page = get_option("WPForms_page");
	$form_cookie = isset($_COOKIE['dk_form_cookie'])? $form_cookie=$_COOKIE['dk_form_cookie']: $form_cookie='NULL';
	$abort = false;
	$storage_fields = array();
	
	$no_form = true;
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

								if($res){
								$cookies_setup = [
										'plugin_name' => "wpforms_cookie_option",
										'get_option' => $wpforms_page,
										'cookie_stored' => $form_cookie,
										'cookie_db_set' => $row->form_cookie
									];
								if(dk_check_cookie($cookies_setup)){
									wpforms()->process->errors[ $form_data[ 'id' ]][$data[ 'id' ]] = $wpforms_page['wpforms_error_message'];
									$abort = true;
								}
							}/* deprecated from 1.2.1else{
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
	if(!$abort and $no_form){
		$form_value = serialize($storage_fields);
		$form_date = current_time('Y-m-d H:i:s');
		$wpdb->insert(
			$table_name, 
			array(
				'form_plugin' => "WPForms",
				'form_name' => $form_title,
				'form_value'   => $form_value,
				'form_cookie' => $form_cookie,
				'form_date' => $form_date
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
	$output = array();
	// Create our array for storing the validated options
    foreach($input as $key =>$value){
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
	if($input['wpforms_cookie_option'] !== "1"){
		$output['wpforms_cookie_option'] = "0";
	}else{
		$output['wpforms_cookie_option'] = "1";
	}
	if(filter_var($input['wpforms_cookie_option_days'], FILTER_VALIDATE_INT) === false){
		$output['wpforms_cookie_option_days'] = 365;
	}else{
		$output['wpforms_cookie_option_days'] = sanitize_text_field($input['wpforms_cookie_option_days']);
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
	$stored_error_message = isset($options['wpforms_error_message'])? $options['wpforms_error_message']:"Please check all fields! These values has been submitted already!"; ?>
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
function duplicateKiller_wpforms_select_form_tag_callback($args){
	$options = get_option($args[0]);
?>
	<h4 class="dk-form-header">WPForms forms list</h4>
<?php
	$wp_forms = duplicateKiller_wpforms_get_forms();
	foreach($wp_forms as $form => $tag):
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
		</div>
<?php endforeach;
}