<?php
/**
 * Plugin Name: Duplicate Killer
 * Version: 1.4.8
 * Description: Stop duplicate form entries for Contact Form 7, Forminator, WPForms, Elementor Forms, Formidable Forms, and Breakdance Page Builder. Prevent duplicate submissions when users submit your forms and limit one submission per email address or other selected fields.
 * Author: NIA
 * Author URI: https://profiles.wordpress.org/wpnia/
 * Text Domain: duplicate-killer
 * Domain Path: /languages/
 */

	defined('ABSPATH') or die('You shall not pass!');
	
	define('DuplicateKiller_PLUGIN',__FILE__);
	define('DuplicateKiller_VERSION','1.4.8');
	define('DuplicateKiller_PLUGIN_DIR', untrailingslashit(dirname(DuplicateKiller_PLUGIN )));
	
	require_once DuplicateKiller_PLUGIN_DIR.'/includes/helpers.php';
	require_once DuplicateKiller_PLUGIN_DIR.'/includes/dk-cookie-loader.php';
	require_once DuplicateKiller_PLUGIN_DIR.'/includes/functions_cf7.php';
	require_once DuplicateKiller_PLUGIN_DIR.'/includes/functions_forminator.php';
	require_once DuplicateKiller_PLUGIN_DIR.'/includes/functions_wpforms.php';
	require_once DuplicateKiller_PLUGIN_DIR.'/includes/functions_breakdance.php';
	require_once DuplicateKiller_PLUGIN_DIR.'/includes/functions_elementor.php';
	require_once DuplicateKiller_PLUGIN_DIR.'/includes/functions_formidable.php';
	require_once DuplicateKiller_PLUGIN_DIR.'/includes/functions_ninjaforms.php';
	require_once DuplicateKiller_PLUGIN_DIR.'/includes/database.php';
	require_once DuplicateKiller_PLUGIN_DIR.'/includes/pro.php';
	require_once DuplicateKiller_PLUGIN_DIR.'/includes/support.php';

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
	delete_option('Breakdance_page');
	delete_option('Elementor_page');
	delete_option('Formidable_page');
	delete_option('NinjaForms_page');
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

function duplicateKiller_check_duplicate_by_key_value($form_plugin, $form_name, $key, $value, $form_cookie = 'NULL', $checked_cookie = false) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dk_forms_duplicate';

    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT form_value,form_cookie FROM {$table_name} WHERE form_plugin = %s AND form_name = %s ORDER BY form_id DESC",
            $form_plugin, $form_name
        )
    );

    foreach ($results as $row) {
        $form_data = maybe_unserialize($row->form_value);
		
         // CF7: associative array (key => value)
        if (is_array($form_data) && isset($form_data[$key])) {
            if (duplicateKiller_check_values_with_lowercase_filter($form_data[$key], $value)) {

				//3 checked cookie
				if($checked_cookie == true){
					if($row->form_cookie == $form_cookie){
						return true;
					}else{
						return false;
					}
				}
                return true;
            }

        // Forminator: arrays of array (with key "name" and "value")
        }elseif (is_array($form_data) && isset($form_data[0]['name'])) {
            foreach ($form_data as $input) {
                if (isset($input['name']) && $input['name'] === $key) {
                    if (duplicateKiller_check_values_with_lowercase_filter($input['value'], $value)) {
                        //3 checked cookie
						if($checked_cookie == true){
							if($row->form_cookie == $form_cookie){
								return true;
							}else{
								return false;
							}
						}
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
    if (!isset($_GET['page']) || $_GET['page'] !== 'duplicateKiller') {
        return;
    }
		wp_register_style(
		'duplicateKillerStyle',
		plugins_url('assets/style.css', DuplicateKiller_PLUGIN),
		[],
		DuplicateKiller_VERSION
	);
	wp_enqueue_style('duplicateKillerStyle');

	wp_enqueue_script(
		'duplicateKiller-admin',
		plugins_url('assets/admin-settings.js', DuplicateKiller_PLUGIN),
		[],
		DuplicateKiller_VERSION,
		true
	);
}


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
			'title' => 'Contact Form 7',
			'description_cb' => 'duplicateKiller_CF7_description',
			'validate_cb' => 'duplicateKiller_cf7_validate_input',
			'fields' => array(
				array('id' => 'CF7_forms', 'title' => '', 'callback' => 'duplicateKiller_cf7_select_form_tag_callback'),
				array('id' => 'CF7_error_message', 'title' => '', 'callback' => 'duplicateKiller_cf7_settings_callback'),
			),
		),
		'Forminator_page' => array(
			'title' => 'Forminator',
			'description_cb' => 'duplicateKiller_forminator_description',
			'validate_cb' => 'duplicateKiller_forminator_validate_input',
			'fields' => array(
				array('id' => 'Forminator_forms', 'title' => '', 'callback' => 'duplicateKiller_forminator_select_form_tag_callback'),
				array('id' => 'Forminator_error_message', 'title' => '', 'callback' => 'duplicateKiller_forminator_settings_callback'),
			),
		),
		'WPForms_page' => array(
			'title' => 'WPForms',
			'description_cb' => 'duplicateKiller_wpforms_description',
			'validate_cb' => 'duplicateKiller_wpforms_validate_input',
			'fields' => array(
				array('id' => 'WPForms_forms', 'title' => '', 'callback' => 'duplicateKiller_wpforms_select_form_tag_callback'),
				array('id' => 'WPForms_error_message', 'title' => '', 'callback' => 'duplicateKiller_wpforms_settings_callback'),
			),
		),
		'Breakdance_page' => array(
			'title' => 'Breakdance',
			'description_cb' => 'duplicateKiller_breakdance_description',
			'validate_cb' => 'duplicateKiller_breakdance_validate_input',
			'fields' => array(
				array('id' => 'Breakdance_forms', 'title' => '', 'callback' => 'duplicateKiller_breakdance_select_form_tag_callback'),
				array('id' => 'Breakdance_error_message', 'title' => '', 'callback' => 'duplicateKiller_breakdance_settings_callback'),
			),
		),
		'Elementor_page' => array(
			'title' => 'Elementor',
			'description_cb' => 'duplicateKiller_elementor_description',
			'validate_cb' => 'duplicateKiller_elementor_validate_input',
			'fields' => array(
				array('id' => 'Elementor_forms', 'title' => '', 'callback' => 'duplicateKiller_elementor_select_form_tag_callback'),
				array('id' => 'Elementor_error_message', 'title' => '', 'callback' => 'duplicateKiller_Elementor_settings_callback'),
			),
		),
		'Formidable_page' => array(
			'title' => 'Formidable',
			'description_cb' => 'duplicateKiller_formidable_description',
			'validate_cb' => 'duplicateKiller_formidable_validate_input',
			'fields' => array(
				array('id' => 'Formidable_forms', 'title' => '', 'callback' => 'duplicateKiller_formidable_select_form_tag_callback'),
				array('id' => 'Formidable_error_message', 'title' => '', 'callback' => 'duplicateKiller_Formidable_settings_callback'),
			),
		),
		'NinjaForms_page' => array(
			'title' => 'Formidable',
			'description_cb' => 'duplicateKiller_NinjaForms_description',
			'validate_cb' => 'duplicateKiller_NinjaForms_validate_input',
			'fields' => array(
				array('id' => 'NinjaForms_forms', 'title' => '', 'callback' => 'duplicateKiller_NinjaForms_select_form_tag_callback'),
				array('id' => 'NinjaForms_error_message', 'title' => '', 'callback' => 'duplicateKiller_NinjaForms_settings_callback'),
			),
		),
		'Pro_page' => array(
			'title' => 'Pro',
			'description_cb' => 'duplicateKiller_pro_plugin',
			'fields' => array(),
		),
		'Get_support' => array(
			'title' => 'Get Support',
			'description_cb' => 'duplicateKiller_support_plugin', // Definește această funcție
			'fields' => array(),
		),
	);

	foreach ($settings as $page => $data) {
		add_settings_section(
			$page . '_section',
			'',
			$data['description_cb'],
			$page
		);

		foreach ($data['fields'] as $field) {
			add_settings_field(
				$field['id'],
				$field['title'],
				$field['callback'],
				$page,
				$page . '_section',
				array($page, $field['title'])
			);
		}

		// Înregistrăm doar dacă există funcție de validare
		if (!empty($data['validate_cb'])) {
			register_setting($page, $page, $data['validate_cb']);
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
			<a href="?page=duplicateKiller&tab=fourth" class="nav-tab <?php echo esc_attr($active_tab == 'fourth' ? 'nav-tab-active' : ''); ?>"><?php esc_html_e('Breakdance','duplicatekiller');?></a>
			<a href="?page=duplicateKiller&tab=fifth" class="nav-tab <?php echo esc_attr($active_tab == 'fifth' ? 'nav-tab-active' : ''); ?>"><?php esc_html_e('Elementor','duplicatekiller');?></a>
			
			<a href="?page=duplicateKiller&tab=sixth" class="nav-tab <?php echo esc_attr($active_tab == 'sixth' ? 'nav-tab-active' : ''); ?>"><?php esc_html_e('Formidable','duplicatekiller');?></a>
			
			<a href="?page=duplicateKiller&tab=seventh" class="nav-tab <?php echo esc_attr($active_tab == 'seventh' ? 'nav-tab-active' : ''); ?>"><?php esc_html_e('Ninja Forms','duplicatekiller');?></a>
			
			<a href="?page=duplicateKiller&tab=pro"
			   class="nav-tab dk-pro-tab <?php echo esc_attr($active_tab == 'pro' ? 'nav-tab-active' : ''); ?>">
			   <?php esc_html_e('PRO','duplicatekiller');?>
			</a>
			<a href="?page=duplicateKiller&tab=support" class="nav-tab <?php echo esc_attr($active_tab == 'support' ? 'nav-tab-active' : ''); ?>"><?php esc_html_e('Get support','duplicatekiller');?></a>
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
            }elseif($active_tab == 'fourth') {
                settings_fields('Breakdance_page');
				dk_do_settings_sections('Breakdance_page');
				submit_button();	
            }elseif($active_tab == 'fifth') {
                settings_fields('Elementor_page');
				dk_do_settings_sections('Elementor_page');
				submit_button();
			}elseif($active_tab == 'sixth') {
                settings_fields('Formidable_page');
				dk_do_settings_sections('Formidable_page');
				submit_button();
			}elseif($active_tab == 'seventh') {
                settings_fields('NinjaForms_page');
				dk_do_settings_sections('NinjaForms_page');
				submit_button();				
            }elseif($active_tab == 'pro') {
                settings_fields('Pro_page');
				dk_do_settings_sections('Pro_page');
            }elseif ($active_tab == 'support') {
				settings_fields('Get_support'); 
				dk_do_settings_sections('Get_support');
			}
            ?>
        </form> 
    </div> 
<?php
}