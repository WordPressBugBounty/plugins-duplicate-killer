<?php
defined('ABSPATH') || exit;

/**
 * Duplicate Killer diagnostics and debug module.
 *
 * Usage:
 * 1. Save this file as:
 *    /includes/class-duplicateKiller-diagnostics.php
 *
 * 2. In main plugin file add only:
 *    require_once DUPLICATEKILLER_PLUGIN_DIR . '/includes/class-duplicateKiller-diagnostics.php';
 *    duplicateKiller_Diagnostics::init();
 *
 * 3. From any integration:
 *    if (class_exists('duplicateKiller_Diagnostics')) {
 *        duplicateKiller_Diagnostics::log('elementor', 'validation_start', ['form_name' => 'My Form']);
 *    }
 */
final class duplicateKiller_Diagnostics {

	const OPTION_SETTINGS = 'duplicateKiller_diagnostics_settings';
	const OPTION_LOGS     = 'duplicateKiller_diagnostics_logs';

	const MENU_SLUG       = 'duplicateKiller_diagnostics';
	const ACTION_DOWNLOAD = 'duplicateKiller_diagnostics_download';
	const ACTION_CLEAR    = 'duplicateKiller_diagnostics_clear';

	const DEFAULT_ENABLED    = 0;
	const DEFAULT_MAX_ENTRIES = 75;
	const DEFAULT_DEBUG_TAIL = 200;

	/**
	 * Bootstrap module.
	 */
	public static function init(): void {
		add_action('admin_menu', [__CLASS__, 'duplicateKiller_register_admin_menu'], 99);
		add_action('admin_init', [__CLASS__, 'duplicateKiller_register_settings']);
		add_action('admin_post_' . self::ACTION_DOWNLOAD, [__CLASS__, 'duplicateKiller_handle_download']);
		add_action('admin_post_' . self::ACTION_CLEAR, [__CLASS__, 'duplicateKiller_handle_clear']);
		add_action('admin_head', [__CLASS__, 'duplicateKiller_diagnostics_admin_menu_css']);
	}
	public static function duplicateKiller_diagnostics_admin_menu_css(): void {
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;

		if (!$screen || empty($screen->id)) {
			return;
		}

		
		?>
		<style>
			#adminmenu .wp-submenu a[href="admin.php?page=duplicateKiller_diagnostics"] {
				color: #f59e0b !important;
				font-weight: 600;
				position: relative;
			}

			#adminmenu .wp-submenu a[href="admin.php?page=duplicateKiller_diagnostics"]::before {
				content: "↳";
				display: inline-block;
				margin-right: 6px;
				color: #f59e0b;
				font-weight: 700;
			}

			#adminmenu .wp-submenu a[href="admin.php?page=duplicateKiller_diagnostics"]:hover,
			#adminmenu .wp-submenu a[href="admin.php?page=duplicateKiller_diagnostics"]:focus {
				color: #ea580c !important;
			}

			#adminmenu .wp-submenu a[href="admin.php?page=duplicateKiller_diagnostics"]:hover::before,
			#adminmenu .wp-submenu a[href="admin.php?page=duplicateKiller_diagnostics"]:focus::before {
				color: #ea580c;
			}
		</style>
		<?php
	}
	/**
	 * Public logger for all integrations.
	 *
	 * Example:
	 * duplicateKiller_Diagnostics::log('elementor', 'validation_start', ['form_id' => 'abc123']);
	 */
	public static function log(string $plugin, string $stage, array $payload = []): void {
		if (!self::duplicateKiller_is_enabled()) {
			return;
		}

		try {
			$logs = get_option(self::OPTION_LOGS, []);
			if (!is_array($logs)) {
				$logs = [];
			}

			$entry = [
				'time_local'     => current_time('mysql'),
				'time_utc'       => gmdate('Y-m-d H:i:s'),
				'plugin'         => sanitize_key($plugin),
				'stage'          => sanitize_key($stage),
				'request_id'     => self::duplicateKiller_get_request_id(),
				'request_method' => isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '',
				'current_url'    => self::duplicateKiller_get_current_url(),
				'http_referer'   => isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : '',
				'user_agent'     => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
				'remote_ip'      => self::duplicateKiller_get_user_ip_safe(),
				'payload'        => self::duplicateKiller_sanitize_recursive($payload),
			];

			$logs[] = $entry;

			$max_entries = self::duplicateKiller_get_max_entries();
			if (count($logs) > $max_entries) {
				$logs = array_slice($logs, -$max_entries);
			}

			update_option(self::OPTION_LOGS, $logs, false);
		} catch (\Throwable $e) {
			// Diagnostics must never break form submission.
		}
	}

	/**
	 * Add submenu under Duplicate Killer.
	 */
	public static function duplicateKiller_register_admin_menu(): void {
		add_submenu_page(
			'duplicateKiller',
			__('Diagnostics', 'duplicate-killer'),
			__('Diagnostics', 'duplicate-killer'),
			'manage_options',
			self::MENU_SLUG,
			[__CLASS__, 'duplicateKiller_render_page']
		);
	}

	/**
	 * Register diagnostics settings.
	 */
	public static function duplicateKiller_register_settings(): void {
		register_setting(
			self::MENU_SLUG,
			self::OPTION_SETTINGS,
			[__CLASS__, 'duplicateKiller_validate_settings']
		);

		add_settings_section(
			'duplicateKiller_diagnostics_main_section',
			'',
			'__return_false',
			self::MENU_SLUG
		);

		add_settings_field(
			'duplicateKiller_diagnostics_enabled',
			__('Enable diagnostics logging', 'duplicate-killer'),
			[__CLASS__, 'duplicateKiller_render_enabled_field'],
			self::MENU_SLUG,
			'duplicateKiller_diagnostics_main_section'
		);

		add_settings_field(
			'duplicateKiller_diagnostics_max_entries',
			__('Maximum stored entries', 'duplicate-killer'),
			[__CLASS__, 'duplicateKiller_render_max_entries_field'],
			self::MENU_SLUG,
			'duplicateKiller_diagnostics_main_section'
		);
	}

	/**
	 * Validate admin settings.
	 */
	public static function duplicateKiller_validate_settings($input): array {
		$input = is_array($input) ? $input : [];

		$output = [];
		$output['enabled'] = !empty($input['enabled']) ? 1 : 0;
		$output['max_entries'] = self::DEFAULT_MAX_ENTRIES;

		add_settings_error(
			self::OPTION_SETTINGS,
			'duplicateKiller_diagnostics_saved',
			__('Diagnostics settings saved.', 'duplicate-killer'),
			'updated'
		);

		return $output;
	}

	/**
	 * Render enabled checkbox.
	 */
	public static function duplicateKiller_render_enabled_field(): void {
		$settings = self::duplicateKiller_get_settings();
		?>
		<label for="duplicateKiller_diagnostics_enabled">
			<input
				type="checkbox"
				id="duplicateKiller_diagnostics_enabled"
				name="<?php echo esc_attr(self::OPTION_SETTINGS); ?>[enabled]"
				value="1"
				<?php checked(!empty($settings['enabled'])); ?>
			/>
			<?php echo esc_html__('When enabled, Duplicate Killer will record diagnostic entries for supported integrations.', 'duplicate-killer'); ?>
		</label>
		<?php
	}

	/**
	 * Render max entries field.
	 */
	public static function duplicateKiller_render_max_entries_field(): void {
		$settings = self::duplicateKiller_get_settings();
		$value    = isset($settings['max_entries']) ? (int) $settings['max_entries'] : self::DEFAULT_MAX_ENTRIES;
		?>
		<input
			type="number"
			min="1"
			max="500"
			step="1"
			name="<?php echo esc_attr(self::OPTION_SETTINGS); ?>[max_entries]"
			value="<?php echo esc_attr((string) $value); ?>"
			class="small-text"
		/>
		<p class="description">
			<?php echo esc_html__('Only the most recent entries are kept. Older entries are removed automatically.', 'duplicate-killer'); ?>
		</p>
		<?php
	}

	/**
	 * Render diagnostics admin page.
	 */
	public static function duplicateKiller_render_page(): void {
		if (!current_user_can('manage_options')) {
			return;
		}

		$settings       = self::duplicateKiller_get_settings();
		$logs           = self::duplicateKiller_get_logs();
		$dk_settings    = get_option('DuplicateKillerSettings', []);
		$plugin_version = is_array($dk_settings) && !empty($dk_settings['plugin_version'])
			? (string) $dk_settings['plugin_version']
			: '';
		$table_snapshot = self::duplicateKiller_get_duplicate_killer_table_snapshot();
		$table_exists   = !empty($table_snapshot['exists']) && $table_snapshot['exists'] === 'yes';

		$download_url = wp_nonce_url(
			admin_url('admin-post.php?action=' . self::ACTION_DOWNLOAD),
			'duplicateKiller_diagnostics_download'
		);

		$clear_url = wp_nonce_url(
			admin_url('admin-post.php?action=' . self::ACTION_CLEAR),
			'duplicateKiller_diagnostics_clear'
		);

		$support_url = 'https://verselabwp.com/duplicate-killer-support/';

		$enabled_badge = !empty($settings['enabled'])
			? '<span class="dk-badge-ok">Enabled</span>'
			: '<span class="dk-badge-off">Disabled</span>';

		$table_badge = $table_exists
			? '<span class="dk-badge-ok">Found</span>'
			: '<span class="dk-badge-warn">Missing</span>';

		settings_errors();
		?>
		<div class="duplicate-killer-support duplicateKiller-diagnostics-page">

			<div class="dk-diagnostics-hero">
				<div class="dk-diagnostics-hero__content">
					<h1><?php echo esc_html__('Duplicate Killer Diagnostics', 'duplicate-killer'); ?></h1>
					<p class="dk-diagnostics-note">
						<?php echo esc_html__('If you have a problem with a form, please follow the steps below exactly. This will help us identify the issue much faster.', 'duplicate-killer'); ?>
					</p>
				</div>
			</div>

			<form method="post" action="options.php" class="dk-diagnostics-steps-form">
				<?php settings_fields(self::MENU_SLUG); ?>

				<div class="dk-settings-form-row">
					<div class="dk-diagnostics-steps-grid">

						<div class="dk-diagnostics-step-card dk-diagnostics-step-card--primary">
							<div class="dk-diagnostics-step-card__number">1</div>
							<h3><?php echo esc_html__('Enable Diagnostics', 'duplicate-killer'); ?></h3>
							<p>
								<?php echo esc_html__('Turn on diagnostics below, then save the settings. Without this, no troubleshooting data will be recorded.', 'duplicate-killer'); ?>
							</p>

							<div class="dk-diagnostics-step-card__content">
								<div class="dk-input-switch-ios">
									<input
										type="hidden"
										name="<?php echo esc_attr(self::OPTION_SETTINGS); ?>[enabled]"
										value="0"
									/>

									<input
										type="checkbox"
										class="ios-switch-input"
										id="duplicateKiller_diagnostics_enabled"
										name="<?php echo esc_attr(self::OPTION_SETTINGS); ?>[enabled]"
										value="1"
										<?php checked(!empty($settings['enabled'])); ?>
									/>

									<label class="ios-switch-label" for="duplicateKiller_diagnostics_enabled"></label>

									<span class="ios-switch-text">
										<?php echo esc_html__('Enable diagnostics logging', 'duplicate-killer'); ?>
									</span>
								</div>

								<p class="dk-error-instruction" style="margin-top:10px;">
									<?php echo esc_html__('This setting is saved automatically when switched on or off.', 'duplicate-killer'); ?>
								</p>
							</div>
						</div>

						<div class="dk-diagnostics-step-card">
							<div class="dk-diagnostics-step-card__number">2</div>
							<h3><?php echo esc_html__('Reproduce the Problem', 'duplicate-killer'); ?></h3>
							<p>
								<?php echo esc_html__('Open the affected page and test the exact form that has the issue. Submit it 2–3 times with the same values, or repeat the exact failing action.', 'duplicate-killer'); ?>
							</p>

							<div class="dk-diagnostics-step-card__content">
								<ul class="dk-diagnostics-checklist">
									<li><?php echo esc_html__('Use the same form that is causing the issue.', 'duplicate-killer'); ?></li>
									<li><?php echo esc_html__('Repeat the issue exactly as it happens.', 'duplicate-killer'); ?></li>
									<li><?php echo esc_html__('For duplicate issues, submit the same values again.', 'duplicate-killer'); ?></li>
								</ul>
							</div>
						</div>

						<div class="dk-diagnostics-step-card">
							<div class="dk-diagnostics-step-card__number">3</div>
							<h3><?php echo esc_html__('Download the TXT Report', 'duplicate-killer'); ?></h3>
							<p>
								<?php echo esc_html__('After testing, come back here and download the diagnostics report. You can also clear stored logs after downloading.', 'duplicate-killer'); ?>
							</p>

							<div class="dk-diagnostics-step-card__content">
								<a href="<?php echo esc_url($download_url); ?>" class="button button-primary dk-diagnostics-btn-full">
									<?php echo esc_html__('Download TXT Report', 'duplicate-killer'); ?>
								</a>
							</div>
						</div>

						<div class="dk-diagnostics-step-card">
							<div class="dk-diagnostics-step-card__number">4</div>
							<h3><?php echo esc_html__('Send It to Support', 'duplicate-killer'); ?></h3>
							<p>
								<?php echo esc_html__('Open the support form, attach the TXT report, and describe the problem briefly.', 'duplicate-killer'); ?>
							</p>

							<div class="dk-diagnostics-step-card__content">
								<a href="<?php echo esc_url($support_url); ?>" class="button dk-diagnostics-btn-full" target="_blank" rel="noopener noreferrer">
									<?php echo esc_html__('Open Support Form', 'duplicate-killer'); ?>
								</a>
							</div>
						</div>

					</div>
				</div>

				<div class="dk-settings-form-row">
					<h2 class="dk-diagnostics-section-title"><?php echo esc_html__('Current Status', 'duplicate-killer'); ?></h2>

					<div class="dk-diagnostics-status-grid">
						<div class="dk-diagnostics-stat">
							<div class="dk-diagnostics-stat-label"><?php echo esc_html__('Diagnostics', 'duplicate-killer'); ?></div>
							<div class="dk-diagnostics-stat-value"><?php echo $enabled_badge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
						</div>

						<div class="dk-diagnostics-stat">
							<div class="dk-diagnostics-stat-label"><?php echo esc_html__('Stored Entries', 'duplicate-killer'); ?></div>
							<div class="dk-diagnostics-stat-value"><?php echo esc_html((string) count($logs)); ?></div>
						</div>

						<div class="dk-diagnostics-stat">
							<div class="dk-diagnostics-stat-label"><?php echo esc_html__('Maximum Entries', 'duplicate-killer'); ?></div>
							<div class="dk-diagnostics-stat-value"><?php echo esc_html((string) self::duplicateKiller_get_max_entries()); ?></div>
						</div>

						<div class="dk-diagnostics-stat">
							<div class="dk-diagnostics-stat-label"><?php echo esc_html__('Plugin Version', 'duplicate-killer'); ?></div>
							<div class="dk-diagnostics-stat-value"><?php echo esc_html($plugin_version !== '' ? $plugin_version : '—'); ?></div>
						</div>

						<div class="dk-diagnostics-stat">
							<div class="dk-diagnostics-stat-label"><?php echo esc_html__('Duplicate Killer Table', 'duplicate-killer'); ?></div>
							<div class="dk-diagnostics-stat-value"><?php echo $table_badge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
						</div>
					</div>

					<div class="dk-diagnostics-bottombar">

						<?php submit_button(__('Save Settings', 'duplicate-killer'), 'primary dk-diagnostics-save-button', 'submit', false); ?>

						<a
							href="<?php echo esc_url($clear_url); ?>"
							class="button dk-btn-danger dk-btn-small"
							onclick="return confirm('<?php echo esc_js(__('Are you sure you want to clear all diagnostics logs? This cannot be undone.', 'duplicate-killer')); ?>');"
						>
							<?php echo esc_html__('Clear Logs', 'duplicate-killer'); ?>
						</a>

					</div>
				</div>

			</form>

		</div>

		<script>
		document.addEventListener('DOMContentLoaded', function () {
			var form = document.querySelector('.dk-diagnostics-steps-form');
			var toggle = document.getElementById('duplicateKiller_diagnostics_enabled');

			if (!form || !toggle) {
				return;
			}

			toggle.addEventListener('change', function () {
				HTMLFormElement.prototype.submit.call(form);
			});
		});
		</script>
		<?php
	}

	private static function duplicateKiller_render_max_entries_field_inline(): void {
		$settings = self::duplicateKiller_get_settings();
		$value    = isset($settings['max_entries']) ? (int) $settings['max_entries'] : self::DEFAULT_MAX_ENTRIES;
		?>
		<input
			type="number"
			min="1"
			max="500"
			step="1"
			id="duplicateKiller_diagnostics_max_entries"
			name="<?php echo esc_attr(self::OPTION_SETTINGS); ?>[max_entries]"
			value="<?php echo esc_attr((string) $value); ?>"
			class="small-text dk-diagnostics-number-input"
		/>
		<p class="description">
			<?php echo esc_html__('Older entries are removed automatically when the limit is reached.', 'duplicate-killer'); ?>
		</p>
		<?php
	}
	/**
	 * Handle TXT download.
	 */
	public static function duplicateKiller_handle_download(): void {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access diagnostics.', 'duplicate-killer'));
		}

		check_admin_referer('duplicateKiller_diagnostics_download');

		$report   = self::duplicateKiller_build_report();
		$filename = 'duplicate-killer-diagnostics-' . gmdate('Y-m-d-H-i-s') . '.txt';

		nocache_headers();
		header('Content-Type: text/plain; charset=utf-8');
		header('Content-Disposition: attachment; filename=' . $filename);
		header('Content-Length: ' . strlen($report));

		echo $report; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Handle log clear.
	 */
	public static function duplicateKiller_handle_clear(): void {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to clear diagnostics.', 'duplicate-killer'));
		}

		check_admin_referer('duplicateKiller_diagnostics_clear');

		delete_option(self::OPTION_LOGS);

		wp_safe_redirect(
			add_query_arg(
				[
					'page' => self::MENU_SLUG,
					'cleared' => '1',
				],
				admin_url('admin.php')
			)
		);
		exit;
	}

	/**
	 * Build full TXT report.
	 */
	public static function duplicateKiller_build_report(): string {
		$lines   = [];
		$divider = str_repeat('=', 80);

		$lines[] = 'Duplicate Killer Diagnostics Report';
		$lines[] = 'Generated: ' . current_time('mysql');
		$lines[] = 'Site: ' . site_url();
		$lines[] = $divider;
		$lines[] = '';

		$sections = [
			'Diagnostics Settings'              => self::duplicateKiller_get_settings(),
			'Duplicate Killer Settings'         => self::duplicateKiller_get_duplicate_killer_settings_snapshot(),
			'Environment'                       => self::duplicateKiller_get_environment_snapshot(),
			'WordPress'                         => self::duplicateKiller_get_wordpress_snapshot(),
			'Theme'                             => self::duplicateKiller_get_theme_snapshot(),
			'Plugins'                           => self::duplicateKiller_get_plugins_snapshot(),
			'MU Plugins'                        => self::duplicateKiller_get_mu_plugins_snapshot(),
			'Drop-ins'                          => self::duplicateKiller_get_dropins_snapshot(),
			'WordPress Constants'               => self::duplicateKiller_get_constants_snapshot(),
			'Request Snapshot'                  => self::duplicateKiller_get_request_snapshot(),
			'Hook / Action Context'             => self::duplicateKiller_get_hook_context_snapshot(),
			'Duplicate Killer Core Availability'=> self::duplicateKiller_get_core_availability_snapshot(),
			'Supported Integrations Status'     => self::duplicateKiller_get_integrations_status_snapshot(),
			'Duplicate Killer Table'            => self::duplicateKiller_get_duplicate_killer_table_snapshot(),
			'Database Indexes / Collation'      => self::duplicateKiller_get_database_indexes_snapshot(),
			'Stored Diagnostics Logs'           => self::duplicateKiller_get_logs(),
			'wp-content/debug.log tail'         => self::duplicateKiller_get_wp_debug_log_tail(self::DEFAULT_DEBUG_TAIL),
			'PHP error_log tail'                => self::duplicateKiller_get_php_error_log_tail(self::DEFAULT_DEBUG_TAIL),
		];

		foreach ($sections as $title => $data) {
			$lines[] = $title;
			$lines[] = str_repeat('-', 80);
			$lines[] = self::duplicateKiller_stringify($data);
			$lines[] = '';
		}

		return implode("\n", $lines);
	}

	/**
	 * Return module settings with defaults.
	 */
	private static function duplicateKiller_get_settings(): array {
		$settings = get_option(self::OPTION_SETTINGS, []);
		if (!is_array($settings)) {
			$settings = [];
		}

		return [
			'enabled'     => !empty($settings['enabled']) ? 1 : self::DEFAULT_ENABLED,
			'max_entries' => self::DEFAULT_MAX_ENTRIES,
		];
	}

	/**
	 * Return if diagnostics are enabled.
	 */
	private static function duplicateKiller_is_enabled(): bool {
		$settings = self::duplicateKiller_get_settings();
		return !empty($settings['enabled']);
	}

	/**
	 * Return maximum stored entries.
	 */
	private static function duplicateKiller_get_max_entries(): int {
		$settings = self::duplicateKiller_get_settings();
		return (int) $settings['max_entries'];
	}

	/**
	 * Return stored logs.
	 */
	private static function duplicateKiller_get_logs(): array {
		$logs = get_option(self::OPTION_LOGS, []);
		return is_array($logs) ? $logs : [];
	}

	/**
	 * Build a stable per-request ID.
	 */
	private static function duplicateKiller_get_request_id(): string {
		static $request_id = null;

		if ($request_id !== null) {
			return $request_id;
		}

		$request_id = uniqid('duplicateKiller_', true);
		return $request_id;
	}

	/**
	 * Get current absolute URL.
	 */
	private static function duplicateKiller_get_current_url(): string {
		$scheme = is_ssl() ? 'https' : 'http';
		$host   = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
		$uri    = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';

		if ($host === '') {
			return '';
		}

		return $scheme . '://' . $host . $uri;
	}

	/**
	 * Safe IP getter.
	 */
	private static function duplicateKiller_get_user_ip_safe(): string {
		if (function_exists('duplicateKiller_get_user_ip')) {
			try {
				return sanitize_text_field((string) duplicateKiller_get_user_ip());
			} catch (\Throwable $e) {
				return '';
			}
		}

		return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
	}

	/**
	 * Sanitize recursively for storage.
	 */
	private static function duplicateKiller_sanitize_recursive($data) {
		if (is_array($data)) {
			$out = [];
			foreach ($data as $key => $value) {
				$sanitized_key = is_string($key) ? sanitize_text_field($key) : $key;
				$out[$sanitized_key] = self::duplicateKiller_sanitize_recursive($value);
			}
			return $out;
		}

		if (is_object($data)) {
			return self::duplicateKiller_sanitize_recursive((array) $data);
		}

		if (is_bool($data) || is_null($data) || is_int($data) || is_float($data)) {
			return $data;
		}

		return sanitize_text_field((string) $data);
	}

	/**
	 * Convert any data into text.
	 */
	private static function duplicateKiller_stringify($data): string {
		if (is_string($data)) {
			return $data;
		}

		return print_r($data, true);
	}

	/**
	 * Collect Duplicate Killer settings snapshot.
	 */
	private static function duplicateKiller_get_duplicate_killer_settings_snapshot(): array {
		$out = [
			'DuplicateKillerSettings'          => get_option('DuplicateKillerSettings', []),
			'duplicateKiller_elementor_group_mode' => get_option('duplicateKiller_elementor_group_mode', null),
			'CF7_page'                         => get_option('CF7_page', []),
			'Forminator_page'                  => get_option('Forminator_page', []),
			'WPForms_page'                     => get_option('WPForms_page', []),
			'Breakdance_page'                  => get_option('Breakdance_page', []),
			'Elementor_page'                   => get_option('Elementor_page', []),
			'Formidable_page'                  => get_option('Formidable_page', []),
			'NinjaForms_page'                  => get_option('NinjaForms_page', []),
			'WooCommerce_page'                 => get_option('WooCommerce_page', []),
		];

		return $out;
	}

	/**
	 * Collect environment data.
	 */
	private static function duplicateKiller_get_environment_snapshot(): array {
		global $wpdb;

		return [
			'site_url'                => site_url(),
			'home_url'                => home_url(),
			'admin_url'               => admin_url(),
			'wp_version'              => get_bloginfo('version'),
			'php_version'             => PHP_VERSION,
			'mysql_version'           => method_exists($wpdb, 'db_version') ? $wpdb->db_version() : '',
			'server_software'         => isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : '',
			'server_name'             => isset($_SERVER['SERVER_NAME']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_NAME'])) : '',
			'https'                   => is_ssl() ? 'yes' : 'no',
			'locale'                  => get_locale(),
			'timezone_string'         => wp_timezone_string(),
			'memory_limit'            => ini_get('memory_limit'),
			'max_execution_time'      => ini_get('max_execution_time'),
			'max_input_vars'          => ini_get('max_input_vars'),
			'post_max_size'           => ini_get('post_max_size'),
			'upload_max_filesize'     => ini_get('upload_max_filesize'),
			'wp_debug'                => defined('WP_DEBUG') ? (WP_DEBUG ? 'true' : 'false') : 'not defined',
			'wp_debug_log'            => defined('WP_DEBUG_LOG') ? (WP_DEBUG_LOG ? 'true' : 'false') : 'not defined',
			'wp_debug_display'        => defined('WP_DEBUG_DISPLAY') ? (WP_DEBUG_DISPLAY ? 'true' : 'false') : 'not defined',
			'script_debug'            => defined('SCRIPT_DEBUG') ? (SCRIPT_DEBUG ? 'true' : 'false') : 'not defined',
			'doing_ajax'              => wp_doing_ajax() ? 'true' : 'false',
			'current_user_can_manage' => current_user_can('manage_options') ? 'true' : 'false',
		];
	}

	/**
	 * Collect WordPress snapshot.
	 */
	private static function duplicateKiller_get_wordpress_snapshot(): array {
		return [
			'name'              => get_bloginfo('name'),
			'description'       => get_bloginfo('description'),
			'version'           => get_bloginfo('version'),
			'language'          => get_bloginfo('language'),
			'charset'           => get_bloginfo('charset'),
			'permalink_structure' => (string) get_option('permalink_structure'),
			'active_plugins_count' => is_array((array) get_option('active_plugins', [])) ? count((array) get_option('active_plugins', [])) : 0,
			'multisite'         => is_multisite() ? 'yes' : 'no',
		];
	}

	/**
	 * Collect active theme snapshot.
	 */
	private static function duplicateKiller_get_theme_snapshot(): array {
		$theme = wp_get_theme();

		return [
			'name'       => $theme->get('Name'),
			'version'    => $theme->get('Version'),
			'template'   => $theme->get_template(),
			'stylesheet' => $theme->get_stylesheet(),
			'parent'     => $theme->parent() ? $theme->parent()->get('Name') : '',
		];
	}

	/**
	 * Collect plugins snapshot.
	 */
	private static function duplicateKiller_get_plugins_snapshot(): array {
		if (!function_exists('get_plugins')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins   = get_plugins();
		$active_plugins = (array) get_option('active_plugins', []);
		$out = [];

		foreach ($all_plugins as $plugin_file => $plugin_data) {
			$out[$plugin_file] = [
				'name'    => isset($plugin_data['Name']) ? $plugin_data['Name'] : '',
				'version' => isset($plugin_data['Version']) ? $plugin_data['Version'] : '',
				'active'  => in_array($plugin_file, $active_plugins, true) ? 'yes' : 'no',
			];
		}

		return $out;
	}
	/**
	 * Mask sensitive request values before exporting diagnostics.
	 */
	private static function duplicateKiller_mask_sensitive_recursive($data) {
		$sensitive_keys = [
			'password',
			'pass',
			'pwd',
			'token',
			'nonce',
			'_wpnonce',
			'authorization',
			'license_key',
			'api_key',
			'secret',
			'client_secret',
			'access_token',
			'refresh_token',
		];

		if (is_array($data)) {
			$out = [];

			foreach ($data as $key => $value) {
				$normalized_key = is_string($key) ? strtolower((string) $key) : '';

				if ($normalized_key !== '' && in_array($normalized_key, $sensitive_keys, true)) {
					$out[$key] = '***masked***';
					continue;
				}

				$out[$key] = self::duplicateKiller_mask_sensitive_recursive($value);
			}

			return $out;
		}

		if (is_object($data)) {
			return self::duplicateKiller_mask_sensitive_recursive((array) $data);
		}

		if (is_string($data)) {
			if (strlen($data) > 1000) {
				return substr($data, 0, 1000) . '...';
			}
			return sanitize_text_field($data);
		}

		if (is_bool($data) || is_null($data) || is_int($data) || is_float($data)) {
			return $data;
		}

		return self::duplicateKiller_sanitize_recursive($data);
	}
	/**
	 * Collect MU plugins snapshot.
	 */
	private static function duplicateKiller_get_mu_plugins_snapshot(): array {
		if (!function_exists('get_mu_plugins')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$mu_plugins = get_mu_plugins();
		$out = [];

		foreach ($mu_plugins as $plugin_file => $plugin_data) {
			$out[$plugin_file] = [
				'name'    => isset($plugin_data['Name']) ? $plugin_data['Name'] : '',
				'version' => isset($plugin_data['Version']) ? $plugin_data['Version'] : '',
			];
		}

		return $out;
	}

	/**
	 * Collect drop-ins snapshot.
	 */
	private static function duplicateKiller_get_dropins_snapshot(): array {
		if (!function_exists('get_dropins')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$dropins = get_dropins();
		$out = [];

		foreach ($dropins as $file => $data) {
			$out[$file] = [
				'name'        => isset($data['Name']) ? $data['Name'] : '',
				'description' => isset($data['Description']) ? $data['Description'] : '',
			];
		}

		return $out;
	}
	/**
	 * Collect database indexes / collation for Duplicate Killer table.
	 */
	private static function duplicateKiller_get_database_indexes_snapshot(): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'dk_forms_duplicate';

		if (!self::duplicateKiller_table_exists($table_name)) {
			return [
				'table_name' => $table_name,
				'exists'     => 'no',
			];
		}

		$charset_collate = $wpdb->get_charset_collate();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Diagnostics only.
		$indexes = $wpdb->get_results("SHOW INDEX FROM {$table_name}", ARRAY_A);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Diagnostics only.
		$table_status = $wpdb->get_row(
			$wpdb->prepare('SHOW TABLE STATUS LIKE %s', $table_name),
			ARRAY_A
		);

		return [
			'table_name'       => $table_name,
			'exists'           => 'yes',
			'wpdb_charset_collate' => $charset_collate,
			'table_collation'  => isset($table_status['Collation']) ? $table_status['Collation'] : '',
			'engine'           => isset($table_status['Engine']) ? $table_status['Engine'] : '',
			'indexes'          => is_array($indexes) ? $indexes : [],
		];
	}
	/**
	 * Collect Duplicate Killer core availability.
	 */
	private static function duplicateKiller_get_core_availability_snapshot(): array {
		$functions = [
			'duplicateKiller_get_user_ip',
			'duplicateKiller_get_form_cookie_simple',
			'duplicateKiller_check_duplicate_by_key_value',
			'duplicateKiller_ip_limit_trigger',
			'duplicateKiller_sanitize_forms_option',
			'duplicateKiller_render_forms_overview',
		];

		$classes = [
			'DuplicateKiller_CrossForm',
			'duplicateKiller_Diagnostics',
		];

		$out = [
			'functions' => [],
			'classes'   => [],
		];

		foreach ($functions as $function_name) {
			$out['functions'][$function_name] = function_exists($function_name) ? 'yes' : 'no';
		}

		foreach ($classes as $class_name) {
			$out['classes'][$class_name] = class_exists($class_name) ? 'yes' : 'no';
		}

		return $out;
	}
	/**
	 * Collect current hook / action context.
	 */
	private static function duplicateKiller_get_hook_context_snapshot(): array {
		global $wp_filter;

		$current_filter = current_filter();

		return [
			'current_filter'            => $current_filter,
			'doing_action_init'         => did_action('init'),
			'doing_action_wp_loaded'    => did_action('wp_loaded'),
			'doing_action_admin_init'   => did_action('admin_init'),
			'doing_action_elementor_loaded' => did_action('elementor/loaded'),
			'doing_action_elementor_pro_init' => did_action('elementor_pro/init'),
			'registered_hook_count'     => is_array($wp_filter) ? count($wp_filter) : 0,
		];
	}
	/**
	 * Collect important constants snapshot.
	 */
	private static function duplicateKiller_get_constants_snapshot(): array {
		$constants = [
			'ABSPATH',
			'WP_CONTENT_DIR',
			'WP_PLUGIN_DIR',
			'WPMU_PLUGIN_DIR',
			'WP_MEMORY_LIMIT',
			'WP_MAX_MEMORY_LIMIT',
			'WP_DEBUG',
			'WP_DEBUG_LOG',
			'WP_DEBUG_DISPLAY',
			'SCRIPT_DEBUG',
			'SAVEQUERIES',
		];

		$out = [];

		foreach ($constants as $constant) {
			$out[$constant] = defined($constant) ? constant($constant) : 'not defined';
		}

		return $out;
	}
	/**
	 * Collect supported integrations status.
	 */
	private static function duplicateKiller_get_integrations_status_snapshot(): array {
		return [
			'contact_form_7' => [
				'plugin_active' => defined('WPCF7_VERSION') ? 'yes' : 'no',
				'option_exists' => get_option('CF7_page', null) !== null ? 'yes' : 'no',
			],
			'forminator' => [
				'plugin_active' => class_exists('Forminator') ? 'yes' : 'no',
				'option_exists' => get_option('Forminator_page', null) !== null ? 'yes' : 'no',
			],
			'wpforms' => [
				'plugin_active' => function_exists('wpforms') || defined('WPFORMS_VERSION') ? 'yes' : 'no',
				'option_exists' => get_option('WPForms_page', null) !== null ? 'yes' : 'no',
			],
			'breakdance' => [
				'plugin_active' => defined('BREAKDANCE_VERSION') ? 'yes' : 'no',
				'option_exists' => get_option('Breakdance_page', null) !== null ? 'yes' : 'no',
			],
			'elementor' => [
				'plugin_active' => defined('ELEMENTOR_VERSION') || class_exists('\Elementor\Plugin') ? 'yes' : 'no',
				'pro_active'    => defined('ELEMENTOR_PRO_VERSION') || class_exists('\ElementorPro\Plugin') ? 'yes' : 'no',
				'option_exists' => get_option('Elementor_page', null) !== null ? 'yes' : 'no',
			],
			'formidable' => [
				'plugin_active' => class_exists('FrmAppHelper') ? 'yes' : 'no',
				'option_exists' => get_option('Formidable_page', null) !== null ? 'yes' : 'no',
			],
			'ninja_forms' => [
				'plugin_active' => class_exists('Ninja_Forms') ? 'yes' : 'no',
				'option_exists' => get_option('NinjaForms_page', null) !== null ? 'yes' : 'no',
			],
			'woocommerce' => [
				'plugin_active' => class_exists('WooCommerce') ? 'yes' : 'no',
				'option_exists' => get_option('WooCommerce_page', null) !== null ? 'yes' : 'no',
			],
		];
	}
	/**
	 * Collect current request snapshot.
	 */
	private static function duplicateKiller_get_request_snapshot(): array {
		$get_raw  = isset($_GET) && is_array($_GET) ? wp_unslash($_GET) : [];
		$post_raw = isset($_POST) && is_array($_POST) ? wp_unslash($_POST) : [];

		return [
			'request_method' => isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '',
			'doing_ajax'     => wp_doing_ajax() ? 'yes' : 'no',
			'is_admin'       => is_admin() ? 'yes' : 'no',
			'current_url'    => self::duplicateKiller_get_current_url(),
			'http_referer'   => isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : '',
			'remote_ip'      => self::duplicateKiller_get_user_ip_safe(),
			'get'            => self::duplicateKiller_mask_sensitive_recursive($get_raw),
			'post'           => self::duplicateKiller_mask_sensitive_recursive($post_raw),
			'get_keys'       => is_array($get_raw) ? array_keys($get_raw) : [],
			'post_keys'      => is_array($post_raw) ? array_keys($post_raw) : [],
		];
	}
	/**
	 * Collect Duplicate Killer table details.
	 */
	private static function duplicateKiller_get_duplicate_killer_table_snapshot(): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'dk_forms_duplicate';
		$exists     = self::duplicateKiller_table_exists($table_name);

		$out = [
			'table_name' => $table_name,
			'exists'     => $exists ? 'yes' : 'no',
		];

		if (!$exists) {
			return $out;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Diagnostics only.
		$count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Diagnostics only.
		$schema = $wpdb->get_results("DESCRIBE {$table_name}", ARRAY_A);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Diagnostics only.
		$latest_rows = $wpdb->get_results(
			"SELECT form_id, form_plugin, form_name, form_cookie, form_ip, form_date
			 FROM {$table_name}
			 ORDER BY form_id DESC
			 LIMIT 20",
			ARRAY_A
		);

		$out['row_count']   = (int) $count;
		$out['schema']      = is_array($schema) ? $schema : [];
		$out['latest_rows'] = is_array($latest_rows) ? $latest_rows : [];

		return $out;
	}

	/**
	 * Check if a custom table exists.
	 */
	private static function duplicateKiller_table_exists(string $table_name): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Diagnostics only.
		$found = $wpdb->get_var(
			$wpdb->prepare('SHOW TABLES LIKE %s', $table_name)
		);

		return $found === $table_name;
	}

	/**
	 * Tail wp-content/debug.log.
	 */
	private static function duplicateKiller_get_wp_debug_log_tail(int $max_lines = 200): array {
		$path = WP_CONTENT_DIR . '/debug.log';
		return self::duplicateKiller_get_file_tail_snapshot($path, $max_lines);
	}

	/**
	 * Tail PHP error_log if configured and readable.
	 */
	private static function duplicateKiller_get_php_error_log_tail(int $max_lines = 200): array {
		$path = ini_get('error_log');

		if (!is_string($path) || $path === '') {
			return [
				'path'   => '',
				'exists' => 'no',
				'lines'  => [],
				'note'   => 'PHP error_log is not configured.',
			];
		}

		return self::duplicateKiller_get_file_tail_snapshot($path, $max_lines);
	}

	/**
	 * Read the last lines of a file.
	 */
	private static function duplicateKiller_get_file_tail_snapshot(string $path, int $max_lines = 200): array {
		$path = wp_normalize_path($path);

		if ($path === '') {
			return [
				'path'   => '',
				'exists' => 'no',
				'lines'  => [],
			];
		}

		if (!file_exists($path)) {
			return [
				'path'   => $path,
				'exists' => 'no',
				'lines'  => [],
			];
		}

		if (!is_readable($path)) {
			return [
				'path'   => $path,
				'exists' => 'yes',
				'lines'  => [],
				'note'   => 'File exists but is not readable.',
			];
		}

		$contents = @file($path, FILE_IGNORE_NEW_LINES); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if (!is_array($contents)) {
			return [
				'path'   => $path,
				'exists' => 'yes',
				'lines'  => [],
				'note'   => 'Unable to read file contents.',
			];
		}

		$max_lines = max(1, absint($max_lines));

		return [
			'path'        => $path,
			'exists'      => 'yes',
			'total_lines' => count($contents),
			'lines'       => array_slice($contents, -$max_lines),
		];
	}
	/**
	 * Normalize a value for diagnostics comparison preview.
	 */
	public static function duplicateKiller_normalize_debug_value($value): string {
		if (is_array($value)) {
			$value = implode(' ', array_map('strval', $value));
		}

		$value = sanitize_text_field((string) $value);
		$value = trim($value);

		return $value;
	}

	/**
	 * Safe snapshot of current WPForms process errors.
	 */
	public static function duplicateKiller_get_wpforms_errors_snapshot($form_id = null): array {
		if (!function_exists('wpforms')) {
			return [];
		}

		try {
			$process = wpforms()->process ?? null;
			if (!$process || !isset($process->errors) || !is_array($process->errors)) {
				return [];
			}

			if ($form_id !== null) {
				$form_id = (int) $form_id;
				return isset($process->errors[$form_id]) && is_array($process->errors[$form_id])
					? $process->errors[$form_id]
					: [];
			}

			return $process->errors;
		} catch (\Throwable $e) {
			return [
				'debug_error' => $e->getMessage(),
			];
		}
	}
}