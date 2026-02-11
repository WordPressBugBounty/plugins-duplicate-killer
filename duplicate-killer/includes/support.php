<?php
defined('ABSPATH') or die('You shall not pass!');

function duplicateKiller_support_plugin() {
    global $wpdb;

    ?>
    <div style="background:#fff;margin-top:25px;border:1px solid #ddd;padding:20px;border-radius:8px;max-width:960px;" >
        <h2>Why use Duplicate Killer?</h2>
        <p>Duplicate Killer prevents duplicate submissions for Contact Form 7, Forminator, WPForms Lite and Breakdance page builder forms.</p>
        <p>It ensures a designated field (like Email) contains unique data. Best example: limit one submission per email.</p>
        <ul>
			<li><span class="dashicons dashicons-yes" style="margin-right:6px;"></span>Supports Email, Phone, TextField as unique keys</li>
			<li><span class="dashicons dashicons-feedback" style="margin-right:6px;"></span>Custom error messages</li>
			<li><span class="dashicons dashicons-admin-users" style="margin-right:6px;"></span>Cookie-based “unique per user” option</li>
		</ul>
        <h3><span class="dashicons dashicons-admin-tools" style="margin-right:6px;"></span> How to use it:</h3>
        <ol>
            <li>Create a form with Contact Form 7, Forminator or WPForms Lite.</li>
            <li>Go to Duplicate Killer settings and select the right tab.</li>
            <li>Choose the unique fields (Name, Phone, Email, or TextField).</li>
            <li>Set your custom error message when duplicates are found.</li>
        </ol>
		<p><span class="dashicons dashicons-lock" style="margin-right:5px;"></span> Limit submissions from the same IP address for a set number of days — a useful method to block repeated spam or abuse.</p>
        <p><span class="dashicons dashicons-shield-alt" style="margin-right:5px;"></span> The cookie-based check prevents duplicate submissions per user — not globally — so multiple users can submit the same data, but a single user can’t re-submit their own.</p>
		<p><span class="dashicons dashicons-chart-bar" style="margin-right:5px;"></span> Use the shortcode to display the number of form submissions anywhere on your site — great for showing engagement, verifying participation, or triggering conditional content. Note: Updates every 30 seconds.</p>
    </div>

<?php
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	global $wpdb;

	/** ==== Site / Server ==== */
	$wp_version      = (string) get_bloginfo( 'version' );
	$php_version     = (string) phpversion();
	$mysql_version   = (string) $wpdb->db_version();
	$theme           = wp_get_theme();
	$site_url        = (string) get_site_url();
	$home_url        = (string) get_home_url();
	$server_software = isset( $_SERVER['SERVER_SOFTWARE'] )
		? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) )
		: 'Unknown';

	$memory_limit        = (string) ini_get( 'memory_limit' );
	$max_execution_time  = (string) ini_get( 'max_execution_time' );
	$upload_max_filesize = (string) ini_get( 'upload_max_filesize' );
	$post_max_size       = (string) ini_get( 'post_max_size' );

	/** ==== Plugin path / version ==== */
	$plugin_dir = trailingslashit( dirname( __FILE__, 1 ) ); // ajustează dacă e altă structură
	if ( ! function_exists( 'get_plugin_data' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$plugin_version = '';
	$main_file_path = wp_normalize_path( WP_PLUGIN_DIR . '/duplicate-killer/function.php' );
	if ( file_exists( $main_file_path ) ) {
		$plugin_data    = get_plugin_data( $main_file_path, false, false );
		$plugin_version = isset( $plugin_data['Version'] ) ? (string) $plugin_data['Version'] : '';
	}

	/** ==== Plugin lists (site + network) ==== */
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$active_site    = (array) get_option( 'active_plugins', [] );
	$active_network = is_multisite() ? array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) ) : [];
	$active_files   = array_values( array_unique( array_merge( $active_site, $active_network ) ) );
	$all_plugins    = get_plugins();

	$active_list = [];
	foreach ( $active_files as $plugin_file ) {
		if ( isset( $all_plugins[ $plugin_file ] ) ) {
			$p    = $all_plugins[ $plugin_file ];
			$name = ! empty( $p['Name'] ) ? $p['Name'] : $plugin_file;
			$ver  = ! empty( $p['Version'] ) ? $p['Version'] : '';
			$active_list[] = $ver ? sprintf( '%s v%s', $name, $ver ) : $name;
		} else {
			$active_list[] = $plugin_file;
		}
	}
	sort( $active_list, SORT_NATURAL | SORT_FLAG_CASE );
	$active_plugins_pretty = implode( ', ', $active_list );

	/** ==== Error log (ultimele 200 linii) via WP_Filesystem ==== */
	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	global $wp_filesystem;
	WP_Filesystem();

	$error_log_data = '';
	$paths = array_filter( [
		ABSPATH . 'error_log',
		trailingslashit( $plugin_dir ) . 'error_log',
		ini_get( 'error_log' ) ?: null,
	] );

	if ( isset( $wp_filesystem ) ) {
		foreach ( $paths as $path ) {
			$path = wp_normalize_path( $path );
			if ( ! $path || ! $wp_filesystem->exists( $path ) || $wp_filesystem->is_dir( $path ) ) {
				continue;
			}
			$content = $wp_filesystem->get_contents( $path );
			if ( false === $content ) {
				continue;
			}
			// limitează la ~1MB
			$max_bytes = 1024 * 1024;
			if ( strlen( $content ) > $max_bytes ) {
				$content = substr( $content, -$max_bytes );
			}
			$lines = preg_split( "/\r\n|\n|\r/", rtrim( (string) $content ) );
			$error_log_data = implode( "\n", array_slice( $lines, -200 ) );
			break;
		}
	}

	/** ==== Compose text block ==== */
	$tech_info  = "=== Site Info ===\n";
	$tech_info .= "Site URL: {$site_url}\n";
	$tech_info .= "Home URL: {$home_url}\n";
	$tech_info .= "WordPress Version: {$wp_version}\n";
	$tech_info .= "Active Theme: " . $theme->get( 'Name' ) . ' (v' . $theme->get( 'Version' ) . ")\n";
	$tech_info .= "\n=== Server Info ===\n";
	$tech_info .= "PHP Version: {$php_version}\n";
	$tech_info .= "MySQL Version: {$mysql_version}\n";
	$tech_info .= "Server Software: {$server_software}\n";
	$tech_info .= "Memory Limit: {$memory_limit}\n";
	$tech_info .= "Max Execution Time: {$max_execution_time}s\n";
	$tech_info .= "Upload Max Filesize: {$upload_max_filesize}\n";
	$tech_info .= "Post Max Size: {$post_max_size}\n";
	$tech_info .= "\n=== Plugin Info ===\n";
	$tech_info .= "Plugin: Duplicate Killer\n";
	$tech_info .= "Version: " . ( $plugin_version ?: 'Unknown' ) . "\n";
	$tech_info .= "Plugin Directory: {$plugin_dir}\n";
	$tech_info .= "Active Plugins: {$active_plugins_pretty}\n";
	if ( $error_log_data ) {
		$tech_info .= "\n=== Recent PHP Error Log (last 200 lines) ===\n";
		$tech_info .= $error_log_data . "\n";
	}
	?>

	<div style="background:#fff;margin-top:25px;border:1px solid #ddd;padding:20px;border-radius:8px;max-width:960px;">
		<h1>
			<span class="dashicons dashicons-editor-help" style="margin-right:6px;"></span>
			<?php echo esc_html__( 'Duplicate Killer — Technical Support Info', 'duplicate-killer' ); ?>
		</h1>
		<p style="font-size:14px;color:#444;margin-top:10px;">
			<?php echo esc_html__( 'If you need support, please copy the technical details below and send them to the developer.', 'duplicate-killer' ); ?>
		</p>

		<textarea id="dk-support-info" readonly style="width:100%;height:300px;"><?php echo esc_textarea( $tech_info ); ?></textarea>

		<div style="margin-top:15px;">
			<button type="button" class="button button-secondary" onclick="dkCopySupportInfo()" style="padding:8px 16px;cursor:pointer;">
				<span class="dashicons dashicons-clipboard" style="vertical-align:middle;margin-right:6px;"></span>
				<?php echo esc_html__( 'Copy Info', 'duplicate-killer' ); ?>
			</button>
			<a href="<?php echo esc_url( 'https://wordpress.org/support/plugin/duplicate-killer/' ); ?>" target="_blank" rel="noopener"
			   class="button button-primary" style="margin-left:8px;">
				<span class="dashicons dashicons-admin-site-alt3" style="vertical-align:middle;margin-right:6px;"></span>
				<?php echo esc_html__( 'Get Support Now', 'duplicate-killer' ); ?>
			</a>
			<a href="<?php echo esc_url( 'https://verselabwp.com/duplicate-killer-support/' ); ?>" target="_blank" rel="noopener"
			   class="button button-primary" style="margin-left:8px;">
				<span class="dashicons dashicons-paperclip" style="vertical-align:middle;margin-right:6px;"></span>
				<?php echo esc_html__( 'Request a feature', 'duplicate-killer' ); ?>
			</a>
		</div>

		<p id="dk-copy-status" style="color:green;display:none;margin-top:10px;">
			<span class="dashicons dashicons-yes-alt" style="vertical-align:middle;margin-right:6px;"></span>
			<?php echo esc_html__( 'Copied to clipboard!', 'duplicate-killer' ); ?>
		</p>
	</div>

	<script>
	function dkCopySupportInfo() {
	  const el = document.getElementById('dk-support-info');
	  const text = el ? (el.value || el.textContent || '') : '';

	  const ok = () => {
		const s = document.getElementById('dk-copy-status');
		if (s) { s.style.display = 'block'; setTimeout(()=> s.style.display='none', 2000); }
	  };

	  if (navigator.clipboard && window.isSecureContext) {
		navigator.clipboard.writeText(text).then(ok).catch(fallback);
	  } else {
		fallback();
	  }
	  function fallback() {
		const ta = document.createElement('textarea');
		ta.value = text;
		ta.setAttribute('readonly', '');
		ta.style.position = 'fixed';
		ta.style.opacity  = '0';
		document.body.appendChild(ta);
		ta.select();
		try { document.execCommand('copy'); } catch(e) {}
		document.body.removeChild(ta);
		ok();
	  }
	}
	</script>
<?php
}