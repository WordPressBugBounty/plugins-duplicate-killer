<?php

/**
 * Plugin Name: Duplicate Killer
 * Version: 1.3.0
 * Description: Stop your duplicate entries  for Contact Form 7, Forminator and WPForms plugins. Pprevent duplicate entries from being created when users submit the form. The best example of its use is to limit one submission per Email address
 * Author: NIA
 * Author URI: https://profiles.wordpress.org/wpnia/
 * Text Domain: duplicate killer
 * Domain Path: /languages/
 *
 */

	defined('ABSPATH') or die('You shall not pass!');
	define('DuplicateKiller_PLUGIN',__FILE__);
	define ('DuplicateKiller_VERSION','1.3.0');
	define('DuplicateKiller_PLUGIN_DIR', untrailingslashit(dirname(DuplicateKiller_PLUGIN )));
	require_once DuplicateKiller_PLUGIN_DIR.'/includes/functions_cf7.php';
	require_once DuplicateKiller_PLUGIN_DIR.'/includes/functions_forminator.php';
	require_once DuplicateKiller_PLUGIN_DIR.'/includes/functions_wpforms.php';
	require_once DuplicateKiller_PLUGIN_DIR.'/includes/database.php';
	require_once DuplicateKiller_PLUGIN_DIR.'/includes/about.php';


/**
 * Create a new table in db
 */
function duplicateKiller_create_table(){
    global $wpdb;
    $table_name = $wpdb->prefix.'dk_forms_duplicate';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            form_id bigint(20) NOT NULL AUTO_INCREMENT,
			form_plugin varchar(50) NOT NULL,
			form_name varchar(50) NOT NULL,
            form_value longtext NOT NULL,
			form_cookie longtext NOT NULL,
			form_ip varchar(50) NOT NULL,
			form_date datetime NOT NULL,
            PRIMARY KEY  (form_id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
}

function dk_checked_defined_constants($name,$var){
	if (!defined($name)) {
		DEFINE($name,$var);
	}
}

/**
 * Activation function hook
 */
function duplicateKiller_on_activate(){
	duplicateKiller_create_table();
}
register_activation_hook( __FILE__, 'duplicateKiller_on_activate' );

//Fires when the upgrader process is complete
function duplicateKiller_upgrade_function( $upgrader_object, $options ) {
	dupplicateKiller_check_folder_and_database();
}
add_action( 'upgrader_process_complete', 'duplicateKiller_upgrade_function',10, 2);

/**
 * Delete custom tables
 */
function duplicateKiller_drop_table_uninstall(){
    global $wpdb;
    $table_name = $wpdb->prefix.'dk_forms_duplicate';
    $sql = "DROP TABLE IF EXISTS $table_name";
    $wpdb->query($sql);
    delete_option('CF7_page');
	delete_option('Forminator_page');
	delete_option('WPForms_page');
	delete_option('DuplicateKillerSettings');
}
register_uninstall_hook(__FILE__, 'duplicateKiller_drop_table_uninstall');


/**
 * Show settings link in wordpress installed plugins page
 */
function duplicateKiller_settings_link($links) {
    $forms_link = '<a href="'.admin_url('admin.php?page=duplicateKiller').'">'.esc_html__('Settings', 'duplicatekiller').'</a>';
    array_unshift($links, $forms_link);
    return $links;
}
$plugin = plugin_basename(__FILE__);
add_filter("plugin_action_links_$plugin", 'duplicateKiller_settings_link' );


/**
 * Get the table data
 */
function duplicateKiller_check_duplicate($form_plugin, $form_name){
	global $wpdb;
	$table_name = $wpdb->prefix.'dk_forms_duplicate';
    $sql = $wpdb->prepare( "SELECT form_value,form_cookie FROM {$table_name} WHERE form_plugin = %s AND form_name = %s ORDER BY form_id DESC" , $form_plugin, $form_name );
    return $wpdb->get_results($sql);
}
//inserted from 1.3.0
function dk_check_ip_feature($form_plugin,$form_name,$form_ip){
	$flag = false;
	global $wpdb;
	$table_name = $wpdb->prefix.'dk_forms_duplicate';
	$result = $wpdb->get_row($wpdb->prepare("SELECT form_ip,form_date FROM $table_name WHERE form_plugin = %s AND form_name = %s AND form_ip = %s ORDER by form_id DESC", $form_plugin, $form_name,$form_ip));
	
    //$sql = $wpdb->prepare( "SELECT form_ip FROM {$table_name} WHERE form_plugin = %s AND form_name = %s ORDER BY form_id DESC" , $form_plugin, $form_name );
    if($result){
		$created_at = new DateTime($result->form_date, new DateTimeZone('UTC'));

        // Current date minus 7 days
        $seven_days_ago = new DateTime('-7 days', new DateTimeZone('UTC'));

        if ($created_at > $seven_days_ago) {
			//The row is newer than 7 days.
            $flag = true;
        }
		
	}
	return $flag;
}

//inserted from 1.3.0
function dk_get_user_ip(){
	$ip_from_cloudflare = isCloudflare();
	if($ip_from_cloudflare){
		$ip_unvalided = sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP']));
			if(filter_var($ip_unvalided, FILTER_VALIDATE_IP)){
				$ip_valid = $ip_unvalided;
				return apply_filters( 'dk_get_user_ip', $ip_valid );
			}
		} else {
			$ip_unvalided = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
		}
	if(!empty($_SERVER['HTTP_CLIENT_IP'])){
	//check ip from share internet
		$ip_unvalided = sanitize_text_field(wp_unslash($_SERVER['HTTP_CLIENT_IP']));

	}elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
	//to check ip is pass from proxy
		$ip_unvalided = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
	}else{
		$ip_unvalided = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
	}
	if(filter_var( $ip_unvalided, FILTER_VALIDATE_IP)){
		$ip_valid = $ip_unvalided;
	}else{
		$ip_valid = "undefined";
	}
	return apply_filters( 'dk_get_user_ip', $ip_valid );
}

//Validates that the IP is from cloudflare
function ip_in_range($ip, $range) {
    if (strpos($range, '/') == false)
        $range .= '/32';

    // $range is in IP/CIDR format eg 127.0.0.1/24
    list($range, $netmask) = explode('/', $range, 2);
    $range_decimal = ip2long($range);
    $ip_decimal = ip2long($ip);
    $wildcard_decimal = pow(2, (32 - $netmask)) - 1;
    $netmask_decimal = ~ $wildcard_decimal;
    return (($ip_decimal & $netmask_decimal) == ($range_decimal & $netmask_decimal));
}

function _cloudflare_CheckIP($ip) {
    $cf_ips = array(
        '173.245.48.0/20',
		'103.21.244.0/22',
		'103.22.200.0/22',
		'103.31.4.0/22',
		'141.101.64.0/18',
		'108.162.192.0/18',
		'190.93.240.0/20',
		'188.114.96.0/20',
		'197.234.240.0/22',
		'198.41.128.0/17',
		'162.158.0.0/15',
		'104.16.0.0/13',
		'104.24.0.0/14',
		'172.64.0.0/13',
		'131.0.72.0/22'
    );
    $is_cf_ip = false;
    foreach ($cf_ips as $cf_ip) {
        if (ip_in_range($ip, $cf_ip)) {
            $is_cf_ip = true;
            break;
        }
    } return $is_cf_ip;
}

function _cloudflare_Requests_Check() {
    $flag = true;

    if(!isset($_SERVER['HTTP_CF_CONNECTING_IP']))   $flag = false;
    if(!isset($_SERVER['HTTP_CF_IPCOUNTRY']))       $flag = false;
    if(!isset($_SERVER['HTTP_CF_RAY']))             $flag = false;
    if(!isset($_SERVER['HTTP_CF_VISITOR']))         $flag = false;
    return $flag;
}

function isCloudflare(){
    $ipCheck = _cloudflare_CheckIP($_SERVER['REMOTE_ADDR']);
    $requestCheck = _cloudflare_Requests_Check();
    return ($ipCheck && $requestCheck);
}
function dk_check_cookie($data){
	$option = $data['get_option'];
	if(isset($option[$data['plugin_name']]) AND $option[$data['plugin_name']] == "1"){
		if($data['cookie_stored'] == $data['cookie_db_set']){
			return true;
		}else{
			return false;
		}
	}else{
		return true;
	}
}
//inserted from v1.2.1
function duplicateKiller_check_values($db_values, $form_name, $form_value){
	if(is_array($db_values)){
		foreach($db_values as $row){
			if(is_array($row)){
				if($row['name'] == $form_name){
					//check for forminator name field(Prefix,FirstName,MiddleName,LastName)
					if(is_array($row['value']) AND is_array($form_value)){
						$var1 = array_map('strtolower', $row['value']);
						$var2 = array_map('strtolower', $form_value);
							if($var1 == $var2){
								return true;
							}
					}
					elseif(strtolower($row['value']) == strtolower($form_value)){
						return true;
					}
				}
			}else{
				//for version under 1.2.1
				if($row == $form_value){
					if(strtolower($row) == strtolower($form_value)){
						return true;
					}
				}
			}
			
		}
	}
	return false;
}

function duplicateKiller_check_values_with_lowercase_filter($var1, $var2){
	if(is_array($var1) AND is_array($var2)){
		$var1 = array_map('strtolower', $var1);
		$var2 = array_map('strtolower', $var2);
		if($var1 == $var2){
			return true;
		}
	}elseif(!is_array($var1) AND !is_array($var2)){
		if(strtolower($var1) == strtolower($var2)){
			return true;
		}
	}
	return false;
}
/**
 * Include plugin style
 */
add_action('admin_enqueue_scripts', 'duplicateKiller_callback_for_setting_up_scripts');
function duplicateKiller_callback_for_setting_up_scripts() {
    wp_register_style( 'duplicateKillerStyle', plugins_url('/assets/style.css',DuplicateKiller_PLUGIN));
    wp_enqueue_style( 'duplicateKillerStyle' );
}
//for testing purpose
/*
$x = 11;
if($x==12){
	add_action( 'wp_footer', function(){?>
	<script type="text/javascript">
var wpcf7Elm = document.querySelector( '.wpcf7' );
 
wpcf7Elm.addEventListener( 'wpcf7mailsent', function( event ) {
  document.cookie = "username=John Smith; expires=Thu, 18 Dec 2024 12:00:00 UTC; path=/";
}, false );
	jQuery(document).on('forminator:form:submit:success', function (e, formData) {
document.cookie = "username=John Smith; expires=Thu, 18 Dec 2024 12:00:00 UTC; path=/";
});

	</script>

	<?php
}, 999 );
}
*/

/* Base Menu */
add_action('admin_menu', 'duplicateKiller_admin');
function duplicateKiller_admin(){
	add_menu_page(
		'DuplicateKiller', // the page title
		'DuplicateKiller', // menu title
		'manage_options', // capability
		'duplicateKiller', // //menu slug/handle
		'duplicateKiller_display_page', // callback function
		'dashicons-images-alt2');
	add_submenu_page(
        'duplicateKiller',
        'Database', //page title
        'Database', //menu title
        'manage_options', //capability,
        'duplicateKiller_database',//menu slug
        'duplicateKiller_db_display_page' //callback function
    );
}
//add or update plugin settings
function updateDuplicateKillerSettings($string,$value){
	$options = get_option('DuplicateKillerSettings');
	if($options){
		$options[$string] = $value;
		update_option("DuplicateKillerSettings",$options);
	}else{
		$options = [
			$string => $value
		];
		add_option("DuplicateKillerSettings",$options);
	}
}
function getDuplicateKillerSetting($page,$string = ""){
	if($page == "settings"){
		$options = get_option('DuplicateKillerSettings');
		if($options){
			if(empty($string)){
					return $options;
			}else{
				if(isset($options[$string])){
					return $options[$string];
				}
				
			}
		}
	}elseif($page == "Forminator_page"){
		$options = get_option('Forminator_page');
		if($options){
			if(empty($string)){
					return $options;
			}else{
				if(isset($options[$string])){
					return $options[$string];
				}
				
			}
		}
	}elseif($page == "CF7_page"){
		$options = get_option('CF7_page');
		if($options){
			if(empty($string)){
					return $options;
			}else{
				if(isset($options[$string])){
					return $options[$string];
				}
				
			}
		}
	}elseif($page == "WPForms_page"){
		$options = get_option('WPForms_page');
		if($options){
			if(empty($string)){
					return $options;
			}else{
				if(isset($options[$string])){
					return $options[$string];
				}
				
			}
		}
	}
	
	return false;
}
function createDuplicateKillerFolder(){
	$upload_dir = wp_upload_dir();
    $dkcf7_folder = $upload_dir['basedir'].'/dkcf7_uploads';
    if(!file_exists($dkcf7_folder)){
        wp_mkdir_p($dkcf7_folder);
        $file = fopen($dkcf7_folder.'/index.php', 'w');
        fwrite($file,"<?php \n\t // Silence is golden.");
        fclose($file);
    }
}

function dupplicateKiller_check_folder_and_database(){
	 $plugin_version = getDuplicateKillerSetting("settings","plugin_version");
	 if($plugin_version != DuplicateKiller_VERSION){
		 updateDuplicateKillerSettings("plugin_version",DuplicateKiller_VERSION);
		 duplicateKiller_create_table();
		 createDuplicateKillerFolder();
	 }
}
add_action('admin_init', 'duplicateKiller_options');
function duplicateKiller_options() {
	dupplicateKiller_check_folder_and_database();
	
	$settings = array(
		'CF7_page' => array(
		  'title'=>'Contact Form 7',
		  'page'=>'CF7_page',
		  'fields'=> array(
			array(
				'id'=> 'CF7_forms',
				'title'=>'',
				'callback'=> 'duplicateKiller_cf7_select_form_tag_callback'
			  ),
			array(
				'id' => 'CF7_error_message',
				'title' => '',
				'callback' =>'duplicateKiller_cf7_settings_callback'
				),
			),
		),
		'Forminator_page' => array(
			'title' => 'Forminator',
			'page' => 'Forminator_page',
			'fields' => array(
				array(
					'id' => 'Forminator_forms',
					'title'=>'',
					'callback' => 'duplicateKiller_forminator_select_form_tag_callback'
				),
				array(
					'id' => 'Forminator_error_message',
					'title' => '',
					'callback' =>'duplicateKiller_forminator_settings_callback'
				),
			),
		),
		'WPForms' => array(
			'title' => 'WPForms',
			'page' => 'WPForms_page',
			'fields' => array(
				array(
					'id' => 'WPForms_forms',
					'title'=>'',
					'callback' => 'duplicateKiller_wpforms_select_form_tag_callback'
				),
				array(
					'id' => 'WPForms_error_message',
					'title' =>'',
					'callback' =>'duplicateKiller_wpforms_settings_callback'
				),
			),
		),
		'About' => array(
			'title' => 'About',
			'page' => 'About_page',
			'fields' => array(),
		),
	);
	foreach($settings as $id => $values){
		if($values['page'] == "CF7_page"){
			add_settings_section(
	        $id,
	        '',
	        'duplicateKiller_CF7_description',
	        'CF7_page'
			);
			foreach ($values['fields'] as $field) {
				add_settings_field(  
					$field['id'],    
					$field['title'],
					$field['callback'],   
					$values['page'],
					$id,
					array(
						$values['page'],
						$field['title']
					) 
				);
			}
			register_setting($values['page'], $values['page'],'duplicateKiller_cf7_validate_input');
		}elseif($values['page'] == "Forminator_page"){
			add_settings_section(
	        $id,
	        '',
	        'duplicateKiller_forminator_description',
	        'Forminator_page'
			);
			foreach ($values['fields'] as $field) {
				add_settings_field(  
					$field['id'],    
					$field['title'],
					$field['callback'],   
					$values['page'],
					$id,
					array(
						$values['page'],
						$field['title']
					) 
				);
			}
			register_setting($values['page'], $values['page'],'duplicateKiller_forminator_validate_input');
		}elseif($values['page'] == "WPForms_page"){
			add_settings_section(
	        $id,
	        '',
	        'duplicateKiller_wpforms_description',
	        'WPForms_page'
			);
			foreach ($values['fields'] as $field) {
				add_settings_field(  
					$field['id'],
					$field['title'],
					$field['callback'],   
					$values['page'],
					$id,
					array(
						$values['page'],
						$field['title']
					) 
				);
			}
			register_setting($values['page'], $values['page'],'duplicateKiller_wpforms_validate_input');
		}elseif($values['page'] == "About_page"){
			add_settings_section(
	        'id_About_plugin',
	        '',
	        'duplicateKiller_about_plugin',
	        'About_page'
			);
		}
	}    
}

/**
 * Display Page
*/

function dk_do_settings_sections( $page ) {
	global $wp_settings_sections, $wp_settings_fields;

	if ( ! isset( $wp_settings_sections[ $page ] ) ) {
		return;
	}

	foreach ( (array) $wp_settings_sections[ $page ] as $section ) {
		if ( $section['title'] ) {
			echo "<h3>{$section['title']}</h3>\n";
		}

		if ( $section['callback'] ) {
			call_user_func( $section['callback'], $section );
		}

		if ( ! isset( $wp_settings_fields )         ||
			! isset( $wp_settings_fields[ $page ] ) ||
			! isset( $wp_settings_fields[ $page ][ $section['id'] ] )
		) {
			continue;
		}
		echo '<div class="settings-form-wrapper">';
		dk_do_settings_fields( $page, $section['id'] );
		echo '</div>';
	}
}

function dk_do_settings_fields( $page, $section ) {
	global $wp_settings_fields;

	if ( ! isset( $wp_settings_fields[ $page ][ $section ] ) ) {
		return;
	}

	foreach ( (array) $wp_settings_fields[ $page ][ $section ] as $field ) {
		echo '<div class="dk-settings-form-row flex-tab">';

		echo '<p>';
		if ( ! empty( $field['args']['label_for'] ) ) {
			echo '<label for="' . esc_attr( $field['args']['label_for'] ) . '">' . $field['title'] . '</label>';
		} else {
			echo $field['title'];
		}

		call_user_func( $field['callback'], $field['args'] );
		echo '</p>';

		echo '</div>';
	}
}
function duplicateKiller_display_page() {
?>
    <div class="wrap">
        <h2><?php esc_html_e('DuplicateKiller','duplicatekiller');?></h2>  
        <?php settings_errors();   
            $active_tab = isset($_GET[ 'tab' ]) ? sanitize_text_field($_GET['tab']) : 'first';  
        ?>  
        <h2 class="nav-tab-wrapper">  
            <a href="?page=duplicateKiller&tab=first" class="nav-tab <?php echo esc_attr($active_tab == 'first' ? 'nav-tab-active' : ''); ?>"><?php esc_html_e('Contact Form 7','duplicatekiller');?>
			</a>  
			<a href="?page=duplicateKiller&tab=second" class="nav-tab <?php echo esc_attr($active_tab == 'second' ? 'nav-tab-active' : ''); ?>"><?php esc_html_e('Forminator','duplicatekiller');?>
			</a>
			<a href="?page=duplicateKiller&tab=third" class="nav-tab <?php echo esc_attr($active_tab == 'third' ? 'nav-tab-active' : ''); ?>"><?php esc_html_e('WPForms Free','duplicatekiller');?></a>
			<a href="?page=duplicateKiller&tab=about" class="nav-tab <?php echo esc_attr($active_tab == 'about' ? 'nav-tab-active' : ''); ?>"><?php esc_html_e('About','duplicatekiller');?></a>
        </h2>  
        <form method="post" action="options.php">  
            <?php 
            if($active_tab == 'first') {  
                settings_fields('CF7_page');
				dk_do_settings_sections('CF7_page');
				submit_button();
            }elseif($active_tab == 'second') {
                settings_fields('Forminator_page');
				dk_do_settings_sections('Forminator_page');
				submit_button();
            }elseif($active_tab == 'third') {
                settings_fields('WPForms_page');
				dk_do_settings_sections('WPForms_page');
				submit_button();
            }elseif($active_tab == 'about') {
                settings_fields('About_page');
				dk_do_settings_sections('About_page');
            }
            ?>
        </form> 
    </div> 
<?php
}