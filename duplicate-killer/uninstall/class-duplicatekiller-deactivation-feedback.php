<?php
if (!defined('ABSPATH')) {
	exit;
}

class duplicateKiller_Deactivation_Feedback {

	private static $plugin_file;
	private static $plugin_basename;
	private static $plugin_slug = 'duplicate-killer';
	private static $plugin_name = 'Duplicate Killer';
	private static $ajax_action = 'duplicatekiller_deactivation_feedback';

	public static function init() {

		self::$plugin_file     = defined('DUPLICATEKILLER_PLUGIN') ? DUPLICATEKILLER_PLUGIN : '';
		self::$plugin_basename = self::$plugin_file ? plugin_basename(self::$plugin_file) : '';

		if (empty(self::$plugin_basename)) {
			return;
		}

		add_filter('plugin_action_links_' . self::$plugin_basename, array(__CLASS__, 'add_deactivation_feedback_attributes'), 10, 1);
		add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
		add_action('admin_footer-plugins.php', array(__CLASS__, 'render_modal'));
		add_action('wp_ajax_' . self::$ajax_action, array(__CLASS__, 'handle_ajax_feedback'));
	}

	public static function add_deactivation_feedback_attributes($actions) {
		if (!isset($actions['deactivate'])) {
			return $actions;
		}

		$actions['deactivate'] = str_replace(
			'<a ',
			'<a class="duplicatekiller-deactivate-link" data-duplicatekiller-feedback="1" ',
			$actions['deactivate']
		);

		return $actions;
	}

	public static function enqueue_assets($hook_suffix) {
		if ('plugins.php' !== $hook_suffix) {
			return;
		}

		$asset_base_url = plugin_dir_url(DUPLICATEKILLER_PLUGIN) . 'uninstall/assets/';

		wp_enqueue_style(
			'duplicatekiller-deactivation-feedback',
			$asset_base_url . 'deactivation-feedback.css',
			array(),
			defined('DUPLICATEKILLER_VERSION') ? DUPLICATEKILLER_VERSION : '1.0.0'
		);

		wp_enqueue_script(
			'duplicatekiller-deactivation-feedback',
			$asset_base_url . 'deactivation-feedback.js',
			array('jquery'),
			defined('DUPLICATEKILLER_VERSION') ? DUPLICATEKILLER_VERSION : '1.0.0',
			true
		);

		wp_localize_script(
			'duplicatekiller-deactivation-feedback',
			'duplicateKillerDeactivationFeedback',
			array(
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'action'  => self::$ajax_action,
				'nonce'   => wp_create_nonce(self::$ajax_action),
			)
		);
	}

	public static function render_modal() {
		?>
		<div id="duplicatekiller-deactivation-modal" class="duplicatekiller-feedback-modal" style="display:none;">
			<div class="duplicatekiller-feedback-backdrop"></div>

			<div class="duplicatekiller-feedback-box" role="dialog" aria-modal="true" aria-labelledby="duplicatekiller-feedback-title">
				<button type="button" class="duplicatekiller-feedback-close" aria-label="<?php echo esc_attr__('Close', 'duplicate-killer'); ?>">
					&times;
				</button>

				<h2 id="duplicatekiller-feedback-title">
					<?php echo esc_html(sprintf(
						/* translators: %s: Plugin name */
						__('Deactivate %s?', 'duplicate-killer'),
						self::$plugin_name
					)); ?>
				</h2>

				<p class="duplicatekiller-feedback-subtitle">
					<?php echo esc_html__('If you have a moment, please let us know why you are deactivating this plugin.', 'duplicate-killer'); ?>
				</p>

				<form id="duplicatekiller-deactivation-form">

					<div class="duplicatekiller-feedback-options">

						<label class="duplicatekiller-feedback-option">
							<span class="duplicatekiller-feedback-radio">
								<input type="radio" name="reason_key" value="temporary">
							</span>
							<span class="duplicatekiller-feedback-option-content">
								<span class="duplicatekiller-feedback-option-title">
									<?php echo esc_html__('It is only temporary', 'duplicate-killer'); ?>
								</span>
							</span>
						</label>

						<label class="duplicatekiller-feedback-option">
							<span class="duplicatekiller-feedback-radio">
								<input type="radio" name="reason_key" value="not_working">
							</span>
							<span class="duplicatekiller-feedback-option-content">
								<span class="duplicatekiller-feedback-option-title">
									<?php echo esc_html__('The plugin did not work as expected', 'duplicate-killer'); ?>
								</span>

								<input
									type="text"
									class="duplicatekiller-feedback-followup"
									data-reason-input="not_working"
									placeholder="<?php echo esc_attr__('What issue did you encounter? (optional)', 'duplicate-killer'); ?>"
								>
							</span>
						</label>

						<label class="duplicatekiller-feedback-option">
							<span class="duplicatekiller-feedback-radio">
								<input type="radio" name="reason_key" value="missing_feature">
							</span>
							<span class="duplicatekiller-feedback-option-content">
								<span class="duplicatekiller-feedback-option-title">
									<?php echo esc_html__('I need a feature that is missing', 'duplicate-killer'); ?>
								</span>

								<input
									type="text"
									class="duplicatekiller-feedback-followup"
									data-reason-input="missing_feature"
									placeholder="<?php echo esc_attr__('Which feature are you looking for? (optional)', 'duplicate-killer'); ?>"
								>
							</span>
						</label>

						<label class="duplicatekiller-feedback-option">
							<span class="duplicatekiller-feedback-radio">
								<input type="radio" name="reason_key" value="found_better_plugin">
							</span>
							<span class="duplicatekiller-feedback-option-content">
								<span class="duplicatekiller-feedback-option-title">
									<?php echo esc_html__('I found a better plugin', 'duplicate-killer'); ?>
								</span>

								<input
									type="text"
									class="duplicatekiller-feedback-followup"
									data-reason-input="found_better_plugin"
									placeholder="<?php echo esc_attr__('What plugin did you switch to and why? (optional)', 'duplicate-killer'); ?>"
								>
							</span>
						</label>

						<label class="duplicatekiller-feedback-option">
							<span class="duplicatekiller-feedback-radio">
								<input type="radio" name="reason_key" value="plugin_conflict">
							</span>
							<span class="duplicatekiller-feedback-option-content">
								<span class="duplicatekiller-feedback-option-title">
									<?php echo esc_html__('It conflicts with another plugin or theme', 'duplicate-killer'); ?>
								</span>
							</span>
						</label>

						<label class="duplicatekiller-feedback-option">
							<span class="duplicatekiller-feedback-radio">
								<input type="radio" name="reason_key" value="other">
							</span>
							<span class="duplicatekiller-feedback-option-content">
								<span class="duplicatekiller-feedback-option-title">
									<?php echo esc_html__('Other reason', 'duplicate-killer'); ?>
								</span>
							</span>
						</label>

					</div>

					<textarea
						id="duplicatekiller-feedback-details"
						name="reason_text"
						rows="4"
						placeholder="<?php echo esc_attr__('Tell us a little more... (optional)', 'duplicate-killer'); ?>"
					></textarea>

					<p class="duplicatekiller-feedback-privacy">
						<?php echo esc_html__('Your feedback helps us improve the plugin and build better features.', 'duplicate-killer'); ?>
					</p>

					<div class="duplicatekiller-feedback-actions">
						<button type="button" class="button duplicatekiller-skip-deactivate">
							<?php echo esc_html__('Skip & Deactivate', 'duplicate-killer'); ?>
						</button>

						<button type="submit" class="button button-primary" disabled>
							<?php echo esc_html__('Submit & Deactivate', 'duplicate-killer'); ?>
						</button>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	public static function handle_ajax_feedback() {
		check_ajax_referer(self::$ajax_action, 'nonce');

		if (!current_user_can('activate_plugins')) {
			wp_send_json_error(array(
				'message' => 'Permission denied.',
			), 403);
		}

		$reason_key  = isset($_POST['reason_key']) ? sanitize_key(wp_unslash($_POST['reason_key'])) : '';
		$reason_text = isset($_POST['reason_text']) ? sanitize_textarea_field(wp_unslash($_POST['reason_text'])) : '';

		$payload = self::get_feedback_payload($reason_key, $reason_text);

		self::send_feedback_to_api($payload);

		wp_send_json_success(array(
			'message' => 'Feedback received.',
		));
	}

	private static function get_feedback_payload($reason_key, $reason_text) {
		$site_url = home_url();
		$parsed   = wp_parse_url($site_url);

		$domain = '';

		if (is_array($parsed) && !empty($parsed['host'])) {
			$domain = strtolower($parsed['host']);
		}

		return array(
			'plugin_slug'      => self::$plugin_slug,
			'plugin_name'      => self::$plugin_name,
			'plugin_version'   => defined('DUPLICATEKILLER_VERSION') ? DUPLICATEKILLER_VERSION : '',
			'plugin_site'      => defined('DUPLICATEKILLER_SITE') ? DUPLICATEKILLER_SITE : '',

			'site_url_hash'    => hash('sha256', $site_url),
			'site_domain_hash' => $domain ? hash('sha256', $domain) : null,

			'wp_version'       => get_bloginfo('version'),
			'php_version'      => PHP_VERSION,
			'locale'           => get_locale(),
			'is_multisite'     => is_multisite() ? 1 : 0,

			'reason_key'       => $reason_key,
			'reason_text'      => $reason_text,
			'timestamp'        => current_time('mysql'),
		);
	}

	private static function send_feedback_to_api(array $payload) {
		$endpoint = 'https://api.verselabwp.com/api/feedback/v1/deactivation';

		// IMPORTANT: use the same secret as FEEDBACK_INGEST_SECRET from Laravel .env.
		$secret = '213c39507c329f0e48d0f8c79d167e74f9dcb009c14139d898bf65420aa3746b';

		if (empty($secret)) {
			return false;
		}

		$payload['website'] = '';

		$body = wp_json_encode($payload);

		if (empty($body)) {
			return false;
		}

		$timestamp = (string) time();

		$signature = hash_hmac(
			'sha256',
			$timestamp . '.' . $body,
			$secret
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout'     => 3,
				'redirection' => 0,
				'blocking'    => true,
				'sslverify'   => true,
				'headers'     => array(
					'Content-Type'             => 'application/json',
					'Accept'                   => 'application/json',
					'X-VerseLabWP-Timestamp'   => $timestamp,
					'X-VerseLabWP-Signature'   => $signature,
					'X-VerseLabWP-Plugin-Slug' => self::$plugin_slug,
				),
				'body'        => $body,
			)
		);

		if (is_wp_error($response)) {
			//error_log('DK API WP ERROR: ' . $response->get_error_message());
			return false;
		}

		$status_code   = wp_remote_retrieve_response_code($response);
		$response_body = wp_remote_retrieve_body($response);

		return $status_code >= 200 && $status_code < 300;
	}

	public static function uninstall() {
		$settings = get_option('DuplicateKillerSettings');

		if (isset($settings['delete_on_uninstall']) && (int) $settings['delete_on_uninstall'] === 1) {
			self::delete_database_table();
			self::delete_plugin_options();
		}

		self::clear_update_caches();
	}

	private static function delete_database_table() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin uninstall: dropping plugin-owned table.
		$wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}dk_forms_duplicate`");
	}

	private static function delete_plugin_options() {
		delete_option('Breakdance_page');
		delete_option('CF7_page');
		delete_option('Forminator_page');
		delete_option('Elementor_page');
		delete_option('WPForms_page');
		delete_option('Formidable_page');
		delete_option('NinjaForms_page');
		delete_option('DuplicateKillerSettings');
		delete_option('duplicatekillerpro_license_options');
		delete_option('duplicate_killer_elementor_debug_logs');
		
		// New: review milestones + blocked duplicates counter
		delete_option( 'duplicateKiller_duplicates_blocked_count' );
		delete_option( 'duplicateKiller_review_milestones_dismissed' );
		
		delete_option( 'duplicateKillerWooCommerceSettings' );
		
		delete_option( 'duplicateKiller_diagnostics_settings' );
		delete_option( 'duplicateKiller_diagnostics_logs' );
	}

	private static function clear_update_caches() {
		delete_site_transient('update_plugins');
		delete_site_transient('plugins');
		delete_transient('update_plugins');
		delete_transient('plugins');
	}
}