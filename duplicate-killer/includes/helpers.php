<?php
defined( 'ABSPATH' ) or die( 'You shall not pass!' );


define( 'DK_NINJA_FORMS', 'NinjaForms' );
define( 'DK_NINJA_FORMS_LABEL', 'Ninja Forms' );

/**
 * Render Duplicate Killer settings UI for a given forms plugin.
 *
 * @param string $plugin_key   Machine key used in DB / shortcode (e.g. 'CF7', 'GF').
 * @param string $plugin_label Human label used in headings (e.g. 'Contact Form 7').
 * @param array  $args         Args passed from add_settings_field callback.
 * @param array  $forms        [ 'Form Name' => [ 'field_tag_1', 'field_tag_2', ... ] ].
 * @param array  $forms_id     [ 'Form Name' => form_id ].
 */
function duplicate_killer_render_forms_ui( $plugin_key, $plugin_label, $args, $forms, $forms_id ) {
	global $wpdb;

	// Capability check – best practice in admin screens.
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Option name from settings API. $args[0] vine de la add_settings_field.
	$option_name = isset( $args[0] ) ? sanitize_key( $args[0] ) : '';

	if ( empty( $option_name ) ) {
		return;
	}

	$options = get_option( $option_name, array() );
	if ( ! is_array( $options ) ) {
		$options = array();
	}

	$table = $wpdb->prefix . 'dk_forms_duplicate';

	$counts = array();
	if ( ! empty( $forms ) ) {
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT form_name, COUNT(*) AS total 
				 FROM {$table} 
				 WHERE form_plugin = %s 
				 GROUP BY form_name",
				$plugin_key
			),
			OBJECT_K
		);

		if ( is_array( $results ) ) {
			$counts = $results;
		}
	}

	?>
	<h2 class="dk-form-header">
		<?php echo esc_html( sprintf( '%s Forms Overview', $plugin_label ) ); ?>
	</h2>
	
	<?php foreach ( $forms as $form_name => $tags ) : ?>
		<?php
		$form_key  = (string) $form_name;
		
		$count     = isset( $counts[ $form_key ] ) ? (int) $counts[ $form_key ]->total : 0;
		$form_opts = isset( $options[ $form_key ] ) && is_array( $options[ $form_key ] )
			? $options[ $form_key ]
			: array();

		$form_id_safe = sanitize_title( $form_key );
		?>

		<div class="dk-single-form">
			<h4 class="dk-form-header">
				<?php echo esc_html( $form_key ); ?>
			</h4>

			<h4><?php esc_html_e( 'Choose the unique fields', 'duplicatekiller' ); ?></h4>

			<?php
				/**
				 * Normalise $tags into a list of "fields".
				 *
				 * - For simple integrations (CF7-style): $tags is already an array of strings.
				 * - For bundle integrations (like your Breakdance structure): $tags contains
				 *   ['form_id', 'post_id', 'form_name', 'fields' => [ ...field arrays... ] ].
				 */
				$fields = array();

				if ( is_array( $tags ) && isset( $tags['fields'] ) && is_array( $tags['fields'] ) ) {
					// Bundle structure: use the "fields" child array
					$fields = $tags['fields'];
				} else {
					// Simple structure: treat $tags as the fields array itself
					$fields = $tags;
				}
			?>

			<?php if ( ! empty( $fields ) && is_array( $fields ) ) : ?>
				<?php foreach ( $fields as $field ) : ?>
					<?php
					/**
					 * Each $field can be:
					 * - string: "email", "name", ...
					 * - array:  [ 'id' => 'email', 'label' => 'Email', 'type' => 'text' ]
					 */
					$field_id    = '';
					$field_label = '';

					if ( is_array( $field ) ) {
						if ( isset( $field['id'] ) ) {
							$field_id = (string) $field['id'];
						} elseif ( isset( $field['name'] ) ) {
							$field_id = (string) $field['name'];
						}

						if ( isset( $field['label'] ) && $field['label'] !== '' ) {
							$field_label = (string) $field['label'];
						} else {
							$field_label = $field_id;
						}
					} else {
						// Simple string field
						$field_id    = (string) $field;
						$field_label = $field_id;
					}

					if ( $field_id === '' ) {
						continue;
					}
					
					$field_set = ! empty( $form_opts[ $field_id ] );

					// Safer DOM id
					$input_id = $form_id_safe . '__' . sanitize_html_class( $field_id );
					?>
					<div class="dk-input-checkbox-callback">
						<input type="checkbox"
							id="<?php echo esc_attr( $input_id ); ?>"
							name="<?php echo esc_attr( $plugin_key . '_page[' . $form_key . '][' . $field_id . ']' ); ?>"
							value="1"
							<?php checked( $field_set ); ?> />

						<label for="<?php echo esc_attr( $input_id ); ?>">
							<?php echo esc_html( $field_label ); ?>
						</label><br/>
					</div>
					<?php
					// Store label mapping in the same option payload (hidden field).
					?>
					<input type="hidden"
						name="<?php echo esc_attr( $plugin_key . '_page[' . $form_key . '][labels][' . $field_id . ']' ); ?>"
						value="<?php echo esc_attr( $field_label ); ?>" />
				<?php endforeach; ?>
			<?php endif; ?>
			
			<div class="pro-version">
			<div class="dk-set-error-message">
				<fieldset class="dk-fieldset dk-error-fieldset">
					<legend class="dk-legend-title">
						<?php esc_html_e( 'Error message when duplicate is found', 'duplicatekiller' ); ?>
					</legend>
					<p class="dk-error-instruction">
						<?php esc_html_e( 'This message will be shown when the user submits a form with duplicate values.', 'duplicatekiller' ); ?>
					</p>
					<input type="text"
						class="dk-error-input"
						placeholder="<?php esc_attr_e( 'Please check all fields! These values have already been submitted.', 'duplicatekiller' ); ?>"
						name=""
						value="<?php esc_attr_e( 'Please check all fields! These values have already been submitted.', 'duplicatekiller' ); ?>" />
				</fieldset>
			</div>

			<div class="dk-limit_submission_by_ip">
				<fieldset class="dk-fieldset">
					<legend class="dk-legend-title">
						<?php esc_html_e( 'Limit submissions by IP address', 'duplicatekiller' ); ?>
					</legend>
					<p>
						<strong><?php esc_html_e( 'This feature', 'duplicatekiller' ); ?></strong>
						<?php esc_html_e( 'restricts form entries based on IP address for a given number of days.', 'duplicatekiller' ); ?>
					</p>

					<div class="dk-input-switch-ios">
						<input type="checkbox"
							class="ios-switch-input"
							id="user_ip_option_<?php echo esc_attr( $form_id_safe ); ?>"
							name=""
							value="1"
							/>

						<label class="ios-switch-label" for="user_ip_option_<?php echo esc_attr( $form_id_safe ); ?>"></label>
						<span class="ios-switch-text">
							<?php esc_html_e( 'Activate this function', 'duplicatekiller' ); ?>
						</span>
					</div>

					<div id="dk-limit-ip_<?php echo esc_attr( $form_id_safe ); ?>" class="dk-toggle-section">
						<label for="error_message_limit_ip_option_<?php echo esc_attr( $form_id_safe ); ?>">
							<?php esc_html_e( 'Set error message for this option:', 'duplicatekiller' ); ?>
						</label>
						<input type="text"
							id="error_message_limit_ip_option_<?php echo esc_attr( $form_id_safe ); ?>"
							name=""
							size="40"
							value=""
							class="dk-error-input"
							placeholder="<?php esc_attr_e( 'This IP has already submitted this form.', 'duplicatekiller' ); ?>" />

						<label for="user_ip_days_<?php echo esc_attr( $form_id_safe ); ?>">
							<?php esc_html_e( 'IP block duration (in days):', 'duplicatekiller' ); ?>
						</label>
						<input type="text"
							id="user_ip_days_<?php echo esc_attr( $form_id_safe ); ?>"
							name=""
							size="5"
							value=""
							class="dk-error-input"
							placeholder="<?php esc_attr_e( 'e.g. 7', 'duplicatekiller' ); ?>" />
					</div>
				</fieldset>
			</div>

			<div class="dk-set-unique-entries-per-user">
				<fieldset class="dk-fieldset">
					<legend class="dk-legend-title">
						<?php esc_html_e( 'Unique entries per user', 'duplicatekiller' ); ?>
					</legend>
					<p>
						<strong><?php esc_html_e( 'This feature uses cookies.', 'duplicatekiller' ); ?></strong>
						<?php esc_html_e( 'Multiple users can submit the same entry, but a single user cannot submit the same one twice.', 'duplicatekiller' ); ?>
					</p>

					<div class="dk-input-switch-ios">
						<input type="checkbox"
							class="ios-switch-input"
							id="cookie_<?php echo esc_attr( $form_id_safe ); ?>"
							name=""
							value="1"
							/>

						<label class="ios-switch-label" for="cookie_<?php echo esc_attr( $form_id_safe ); ?>"></label>
						<span class="ios-switch-text">
							<?php esc_html_e( 'Activate this function', 'duplicatekiller' ); ?>
						</span>
					</div>

					<div id="cookie_section_<?php echo esc_attr( $form_id_safe ); ?>" class="dk-toggle-section">
						<label for="cookie_days_<?php echo esc_attr( $form_id_safe ); ?>">
							<?php esc_html_e( 'Cookie persistence (days - max 365):', 'duplicatekiller' ); ?>
						</label>
						<input type="text"
							id="cookie_days_<?php echo esc_attr( $form_id_safe ); ?>"
							name=""
							size="5"
							value=""
							class="dk-error-input"
							placeholder="<?php esc_attr_e( 'e.g. 7', 'duplicatekiller' ); ?>" />
					</div>
				</fieldset>
			</div>

			<div class="dk-shortcode-count-submission">
				<fieldset class="dk-fieldset">
					<legend class="dk-legend-title">
						<?php esc_html_e( 'Display submission count', 'duplicatekiller' ); ?>
					</legend>
					<p>
						<?php esc_html_e( 'You can use this shortcode to display the submission count anywhere on your site. This is useful for showcasing engagement, verifying participation levels, or triggering conditional actions. Note: refresh every 30 seconds.', 'duplicatekiller' ); ?>
					</p>
					<?php
					$shortcode = sprintf(
						'[duplicateKiller plugin="%s" form="%s"]',
						$plugin_key,
						$form_key
					);
					$unique_id = uniqid( 'dk_shortcode_', true );
					?>
					<div style="display:flex; align-items:center; gap:10px;">
						<input type="text"
							id="<?php echo esc_attr( $unique_id ); ?>"
							value="<?php echo esc_attr( $shortcode ); ?>"
							readonly
							style="flex:1; padding:8px 12px; font-size:16px; border:1px solid #ccc; border-radius:5px; background:#fff; cursor:default;">
						<button type="button" 
							style="padding:8px 16px; font-size:14px; background-color:#0073aa; color:#fff; border:none; border-radius:5px; cursor:pointer;">
							<?php esc_html_e( 'Copy', 'duplicatekiller' ); ?>
						</button>
					</div>
				</fieldset>
			</div>
			
			</div>
			<div class="dk-box dk-delete-records">
				<p class="dk-record-count">
					📦
					<span class="dk-count-number">
						<?php echo esc_html( (string) $count ); ?>
					</span>
					<?php esc_html_e( 'saved submissions found for this form', 'duplicatekiller' ); ?>
				</p>

				<?php if ( $count > 0 ) : ?>
					<label for="<?php echo esc_attr( 'delete_records_' . $form_id_safe ); ?>" class="dk-delete-label">
						<input type="checkbox"
							id="<?php echo esc_attr( 'delete_records_' . $form_id_safe ); ?>"
							name="<?php echo esc_attr( $plugin_key . '_delete_records[' . $form_key . ']' ); ?>"
							value="1"
							class="dk-delete-checkbox" />
						🗑️
						<span>
							<?php esc_html_e( 'Delete all saved entries for this form', 'duplicatekiller' ); ?>
							<small>
								<?php esc_html_e( '(this action cannot be undone)', 'duplicatekiller' ); ?>
							</small>
						</span>
					</label>
				<?php endif; ?>
			</div>
		</div><!-- .dk-single-form -->
	<?php endforeach; ?>

	<div id="dk-toast" style="
	  display:none;
	  position:fixed;
	  top:50px;
	  left:50%;
	  transform:translateX(-50%);
	  background-color:#323232;
	  color:#fff;
	  padding:12px 20px;
	  border-radius:6px;
	  font-size:14px;
	  z-index:9999;
	  box-shadow:0 2px 6px rgba(0,0,0,0.3);
	">
	  <?php esc_html_e( 'Shortcode copied!', 'duplicatekiller' ); ?>
	</div>
	<?php
}

/**
 * Resolve cookie for a form (FREE global + PRO per-form).
 *
 * @param array  $options   get_option(...) array
 * @param string $form_name internal form key (ex: contactme.1)
 *
 * @return array {
 *   form_cookie    string  Cookie value or 'NULL'
 *   checked_cookie bool    True only if cookie exists AND is enabled for this form
 * }
 */
function dk_get_form_cookie_simple( array $options, string $form_name ): array {

	$form_cookie    = 'NULL';
	$checked_cookie = false;

	// -------------------------
	// 1) PRO: per-form cookie
	// -------------------------
	if (
		isset( $options[ $form_name ]['cookie_option_days'] )
		&& is_numeric( $options[ $form_name ]['cookie_option_days'] )
		&& (int) $options[ $form_name ]['cookie_option_days'] > 0
	) {
		if ( ! empty( $_COOKIE['dk_form_cookie'] ) ) {
			$form_cookie    = sanitize_text_field( wp_unslash( $_COOKIE['dk_form_cookie'] ) );
			$checked_cookie = true;
		}
		return compact( 'form_cookie', 'checked_cookie' );
	}

	// -------------------------
	// 2) FREE: global cookie
	// -------------------------
	if (
		isset( $options['ninjaforms_cookie_option'] )
		&& (string) $options['ninjaforms_cookie_option'] === '1'
	) {
		if ( ! empty( $_COOKIE['dk_form_cookie'] ) ) {
			$form_cookie    = sanitize_text_field( wp_unslash( $_COOKIE['dk_form_cookie'] ) );
			$checked_cookie = true;
		}
	}

	return compact( 'form_cookie', 'checked_cookie' );
}
/**
 * Return the DK cookie value only if the Formidable cookie feature is enabled.
 *
 * New structure: global option in Formidable_page:
 * - formidable_cookie_option = "1"
 * Cookie name: dk_form_cookie
 *
 * @param array $formidable_page The full Formidable_page option array.
 * @return string Cookie value or 'NULL'
 */
function dk_get_formidable_cookie_if_enabled(array $formidable_page) {
    if (
        empty($formidable_page['formidable_cookie_option']) ||
        (string) $formidable_page['formidable_cookie_option'] !== '1' ||
        empty($_COOKIE['dk_form_cookie'])
    ) {
        return 'NULL';
    }

    return sanitize_text_field((string) $_COOKIE['dk_form_cookie']);
}
function dk_ip_limit_trigger($plugin, $plugin_options, $form_name) {

    // Normalize plugin key (avoid casing bugs)
    $plugin = strtolower((string) $plugin);

    // Map plugin => global option key that enables IP limit
    $ip_option_key = array(
        'breakdance'  => 'breakdance_user_ip',
        'elementor'   => 'elementor_user_ip',
        'formidable'  => 'formidable_user_ip',
		'ninjaforms'  => 'ninjaforms_user_ip',
    );

    if (empty($ip_option_key[$plugin])) {
        return false;
    }

    $flag_key = $ip_option_key[$plugin];

    if (isset($plugin_options[$flag_key]) && (string) $plugin_options[$flag_key] === '1') {
        $form_ip = dk_get_user_ip();
        if (dk_check_ip_feature($plugin, $form_name, $form_ip)) {
            return true;
        }
    }

    return false;
}
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