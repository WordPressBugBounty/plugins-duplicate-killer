<?php
defined( 'ABSPATH' ) or die( 'You shall not pass!' );

/**
 * Normalize forms to a standard structure used by the renderer.
 *
 * Output format:
 * [
 *   'Form Key' => [
 *      'form_id'   => 123,
 *      'form_name' => 'My Form',
 *      'fields'    => [
 *          ['id'=>'email', 'label'=>'Email', 'type'=>'email'],
 *          ...
 *      ],
 *   ],
 * ]
 */

function duplicateKiller_normalize_forms(array $forms_raw, array $fallback_form_ids = []): array {
    $out = [];

    foreach ($forms_raw as $form_key => $payload) {
        $form_key  = (string) $form_key;
        $form_id   = $fallback_form_ids[$form_key] ?? '';
        $form_name = $form_key;
        $fields    = [];

        // Breakdance-like: array with form_id/form_name/fields[]
        if (is_array($payload) && (isset($payload['fields']) || isset($payload['form_id']) || isset($payload['form_name']))) {
            if (isset($payload['form_id']))   $form_id = $payload['form_id'];
            if (!empty($payload['form_name'])) $form_name = (string) $payload['form_name'];

            if (!empty($payload['fields']) && is_array($payload['fields'])) {
                foreach ($payload['fields'] as $f) {
                    if (!is_array($f)) continue;
                    $fid = (string)($f['id'] ?? '');
                    if ($fid === '') continue;

                    $fields[] = [
                        'id'    => $fid,
                        'label' => (string)($f['label'] ?? $fid),
                        'type'  => (string)($f['type'] ?? ''),
                    ];
                }
            }

            $out[$form_key] = [
                'form_id'   => $form_id,
                'form_name' => $form_name,
                'fields'    => $fields,
            ];
            continue;
        }

        // Simple list: ['email', 'name', ...]
        if (is_array($payload)) {
            foreach ($payload as $fid) {
                $fid = is_scalar($fid) ? (string)$fid : '';
                if ($fid === '') continue;
                $fields[] = ['id' => $fid, 'label' => $fid, 'type' => ''];
            }
        }

        $out[$form_key] = [
            'form_id'   => $form_id,
            'form_name' => $form_name,
            'fields'    => $fields,
        ];
    }

    return $out;
}
function duplicateKiller_get_submission_counts(string $db_plugin_key): array {
    static $cache = [];
    if (isset($cache[$db_plugin_key])) return $cache[$db_plugin_key];
	
	$plugin_keys = array( $db_plugin_key );

	if ( $db_plugin_key === 'NinjaForms' || $db_plugin_key === 'Ninja Forms' ) {
		$plugin_keys = array( 'NinjaForms', 'Ninja Forms' );
	}

	$placeholders = implode( ',', array_fill( 0, count( $plugin_keys ), '%s' ) );
    global $wpdb;
    $table = $wpdb->prefix . 'dk_forms_duplicate';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Querying Formidable custom table (admin-only, no WP API).
	$rows = $wpdb->get_results(
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Querying Formidable custom table (admin-only, no WP API).
		$wpdb->prepare(
			"SELECT form_name, COUNT(*) AS total
			 FROM " . esc_sql( $table ) . "
			 WHERE form_plugin IN ($placeholders)
			 GROUP BY form_name",
			$plugin_keys
		),
		ARRAY_A
	);

    $out = [];
    foreach ((array)$rows as $r) {
        $out[(string)$r['form_name']] = (int)$r['total'];
    }

    return $cache[$db_plugin_key] = $out;
}
function duplicateKiller_render_forms_overview(array $config) {
	//error_log(print_r($config,true));
    // Required
    $option_name   = (string)($config['option_name'] ?? '');
    $db_plugin_key = (string)($config['db_plugin_key'] ?? '');
    $plugin_label  = (string)($config['plugin_label'] ?? $db_plugin_key);
    $forms_raw     = (array)($config['forms'] ?? []);
    $forms_id_map  = (array)($config['forms_id_map'] ?? []);

    if ($option_name === '' || $db_plugin_key === '') {
        echo '<p>Missing renderer configuration.</p>';
        return;
    }

    $options = get_option($option_name, []);
    if (!is_array($options)) $options = [];

	$licensed = true;
    $pro_class = $licensed ? '' : ' pro-version';

    $counts = duplicateKiller_get_submission_counts($db_plugin_key);
    $forms  = duplicateKiller_normalize_forms($forms_raw, $forms_id_map);

    //echo '<h2 class="dk-form-header">' . esc_html($plugin_label) . '</h2>';
    if (!$licensed) {
        echo '<div class="notice notice-warning"><p>' .
            esc_html__('Some features are available in Duplicate Killer PRO.', 'duplicate-killer') .
        '</p></div>';
    }
	
	// Elementor Group Mode (only for Elementor)
	if ($db_plugin_key === 'elementor') {
		?>

		<div class="dk-settings-card dk-card-width-1">
			<div class="dk-card-section">
				<div class="dk-feature-row">
					<div class="dk-feature-info">
						<h4>
							<?php esc_html_e('Elementor group mode - Pro feature', 'duplicate-killer'); ?>
						</h4>

						<p>
							<?php esc_html_e(
								'Treat Elementor forms with the same Form Name as a single form across multiple pages.',
								'duplicate-killer'
							); ?>
						</p>
					</div>

					<div class="dk-feature-control">
						<div class="dk-input-switch-ios">
							<input
								type="checkbox"
								class="ios-switch-input"
								value="1"
								disabled
							/>
							<label
								class="ios-switch-label"
								for="duplicateKiller_elementor_group_mode">
							</label>
						</div>
					</div>
				</div>

				<div class="dk-settings-warning">
					<span class="dashicons dashicons-info-outline"></span>

					<span>
						<?php esc_html_e(
							'Recommended if you duplicated the same Elementor form across multiple pages.',
							'duplicate-killer'
						); ?>
					</span>
				</div>
			</div>
		</div>
		<?php
	}

	$form_index = 0;
	?>
	<div class="dk-forms-wrapper">
	<?php
    foreach ($forms as $form_key => $form) {
        $form_key   = (string)$form_key;
        $form_safe  = duplicateKiller_sanitize_id($form_key);
        $form_name  = (string)($form['form_name'] ?? $form_key);
        $form_id    = $form['form_id'] ?? '';
        $fields     = is_array($form['fields'] ?? null) ? $form['fields'] : [];

        $form_opts = (isset($options[$form_key]) && is_array($options[$form_key])) ? $options[$form_key] : [];

		$is_locked_form = ($form_index > 0);
		$disabled_attr  = $is_locked_form ? ' disabled="disabled"' : '';

        // Defaults
        $defaults = duplicateKiller_get_form_defaults();

		$err_msg = !empty($form_opts['error_message'])
			? (string)$form_opts['error_message']
			: $defaults['error_message'];

		$ip_err_msg = !empty($form_opts['error_message_limit_ip_option'])
			? (string)$form_opts['error_message_limit_ip_option']
			: $defaults['error_message_limit_ip_option'];

		$ip_days = !empty($form_opts['user_ip_days'])
			? (string)$form_opts['user_ip_days']
			: $defaults['user_ip_days'];

		$cookie_days = !empty($form_opts['cookie_option_days'])
			? (string)$form_opts['cookie_option_days']
			: $defaults['cookie_option_days'];

        $cookie_enabled = !empty($form_opts['cookie_option']) && (string)$form_opts['cookie_option'] === '1';
        $ip_enabled = !empty($form_opts['user_ip']) && (string)$form_opts['user_ip'] === '1';
		$cross_form_enabled = !empty($form_opts['cross_form_option']) && (string) $form_opts['cross_form_option'] === '1';

		$advanced_open = $ip_enabled || $cookie_enabled || $cross_form_enabled;
		$advanced_id   = 'dk_advanced_settings_' . $form_safe;

		$selected_duplicate_fields = 0;

		foreach ( $fields as $f ) {
			$fid = (string) ( $f['id'] ?? '' );

			if ( $fid !== '' && ! empty( $form_opts[$fid] ) ) {
				$selected_duplicate_fields++;
			}
		}

		$duplicate_protection_enabled = $selected_duplicate_fields > 0;
        $count = $counts[$form_key] ?? $counts[$form_name] ?? 0;
		if ( 'breakdance' === $db_plugin_key ) {

		$legacy_form_key = $form_name . '.' . $form_id;

		if ( $legacy_form_key !== $form_key && isset( $counts[$legacy_form_key] ) ) {
			$count += (int) $counts[$legacy_form_key];
		}
	}
        ?>
        <div class="dk-single-form dk-form-card<?php echo $is_locked_form ? ' dk-form-locked' : ''; ?>">
		<!-- Card header -->
		<div class="dk-card-header">
			<div>
				<h3 class="dk-card-title"><?php echo esc_html($form_key); ?></h3>
				<p class="dk-card-description">
					<?php esc_html_e('Protect this form from duplicate submissions.', 'duplicate-killer'); ?>
				</p>
			</div>

			<?php if ($duplicate_protection_enabled) : ?>
				<span class="dk-status-pill dk-status-pill-active">
					<?php esc_html_e('Protection active', 'duplicate-killer'); ?>
				</span>
			<?php else : ?>
				<span class="dk-status-pill dk-status-pill-inactive">
					<?php esc_html_e('No protected fields', 'duplicate-killer'); ?>
				</span>
			<?php endif; ?>
		</div>
			<?php if ($is_locked_form): ?>
				<div class="dk-pro-ribbon">PRO</div>
				<div class="dk-form-lock-overlay">
					<div class="dk-form-lock-message">
						<strong>Upgrade to PRO</strong>
						<span>Unlock access to this form and manage all your forms without limits.</span>

						<div class="dk-form-lock-cta">
							<span class="dk-form-lock-mini">Available in Duplicate Killer PRO</span>
							<a href="<?php echo esc_url(admin_url('admin.php?page=duplicateKiller&tab=pro')); ?>" class="button button-primary">
								Upgrade to PRO
							</a>
						</div>
					</div>
				</div>
			<?php endif; ?>

            <input type="hidden"
                name="<?php echo esc_attr($option_name . '[' . $form_key . '][form_id]'); ?>"
                value="<?php echo esc_attr((string)$form_id); ?>" />

			<!-- Protected fields -->
			<div class="dk-card-section">
				<div class="dk-section-header">
					<h4><?php esc_html_e('Protected fields', 'duplicate-killer'); ?></h4>
					<p>
						<?php esc_html_e('Choose which fields from this form should be checked for duplicates.', 'duplicate-killer'); ?>
					</p>
				</div>
            <?php foreach ($fields as $field_index => $f):

                    $fid   = (string)($f['id'] ?? '');
                    if ($fid === '') continue;
                    $label = (string)($f['label'] ?? $fid);
                    $type  = (string)($f['type'] ?? '');
                    $is_checked = !empty($form_opts[$fid]);
                    $input_id = 'dk_' . $form_safe . '__' . sanitize_html_class($fid);
					$field_type_key = strtolower(trim($type));
					$field_label_key = strtolower($label . ' ' . $fid);

					$field_desc = __('Check this field for duplicate values.', 'duplicate-killer');

					$field_icon_svg = duplicateKiller_get_field_icon_svg( $type, $label, $fid );

					if ($field_type_key === 'email') {

						$field_desc = __('Ensure email address is unique.', 'duplicate-killer');

					} elseif (
						$field_type_key === 'tel' ||
						strpos($field_label_key, 'phone') !== false ||
						strpos($field_label_key, 'tel') !== false
					) {

						$field_desc = __('Ensure phone number is unique.', 'duplicate-killer');

					} elseif ($field_type_key === 'text') {

						$field_desc = __('Check for duplicate text values.', 'duplicate-killer');

					} elseif ($field_type_key === 'textarea') {

						$field_desc = __('Check for duplicate message values.', 'duplicate-killer');

					} elseif ($field_type_key === 'number') {

						$field_desc = __('Ensure number value is unique.', 'duplicate-killer');

					} elseif ($field_type_key === 'url') {

						$field_desc = __('Ensure URL value is unique.', 'duplicate-killer');

					} elseif ($field_type_key === 'date') {

						$field_desc = __('Ensure date value is unique.', 'duplicate-killer');

					} elseif (
						in_array(
							$field_type_key,
							array('select', 'radio', 'checkbox'),
							true
						)
					) {

						$field_desc = __('Check selected option values for duplicates.', 'duplicate-killer');

					}
					?>
					<div class="dk-protected-field-row<?php echo $is_checked ? ' is-active' : ''; ?>">
						<label class="dk-protected-field-label" for="<?php echo esc_attr($input_id); ?>">
							<input type="checkbox"
								id="<?php echo esc_attr($input_id); ?>"
								name="<?php echo esc_attr($option_name . '[' . $form_key . '][' . $fid . ']'); ?>"
								value="1"
								class="dk-protected-field-checkbox"
								<?php checked($is_checked); ?>
								<?php echo $disabled_attr; ?>>

							<span class="dk-checkbox-ui" aria-hidden="true"></span>
							<?php echo $field_icon_svg; ?>
							<span class="dk-protected-field-main">
								<span class="dk-protected-field-name">
									<?php echo esc_html($label); ?>

									<?php if ($type !== '') : ?>
										<small class="dk-field-type">(<?php echo esc_html($type); ?>)</small>
									<?php endif; ?>
								</span>
							</span>
							<span class="dk-protected-field-desc">
								<?php echo esc_html($field_desc); ?>
							</span>
						</label>

						<?php
						$store_labels = (
							$db_plugin_key === 'Formidable' ||
							$db_plugin_key === 'NinjaForms' ||
							$option_name === 'Formidable_page' ||
							$option_name === 'NinjaForms_page'
						);

						$fid_for_label = is_numeric($fid) ? (int) $fid : $fid;

						if ($store_labels): ?>
							<input type="hidden"
								name="<?php echo esc_attr($option_name . '[' . $form_key . '][labels][' . $fid_for_label . ']'); ?>"
								value="<?php echo esc_attr($label); ?>" />
						<?php endif; ?>

						<input type="hidden"
							name="<?php echo esc_attr($option_name . '[' . $form_key . '][__dk_field_type][' . $fid . ']'); ?>"
							value="<?php echo esc_attr($type); ?>" />

						<input type="hidden"
							name="<?php echo esc_attr($option_name . '[' . $form_key . '][__dk_field_order][' . $fid . ']'); ?>"
							value="<?php echo esc_attr((string)$field_index); ?>" />
					</div>
            <?php endforeach; ?>
			</div>
            <div class="<?php echo esc_attr(trim('dk-pro-wrap' . $pro_class)); ?>">

                <!-- Error message -->
                <div class="dk-card-section">
					<div class="dk-section-header">
						<h4 class="dk-section-title-with-link">
							<span>
								<?php esc_html_e('Duplicate message', 'duplicate-killer'); ?>
							</span>

							<a class="dk-section-title-link"
								href="https://verselabwp.com/what-is-the-set-error-message-field-in-duplicate-killer/"
								target="_blank"
								rel="noopener">
								<?php esc_html_e('What is this?', 'duplicate-killer'); ?>
							</a>
						</h4>

						<p>
							<?php esc_html_e('This message appears when Duplicate Killer blocks a repeated submission.', 'duplicate-killer'); ?>
						</p>
					</div>

					<div class="dk-card-section-inner">
						<input type="text"
							class="dk-error-input"
							name="<?php echo esc_attr($option_name . '[' . $form_key . '][error_message]'); ?>"
							value="<?php echo esc_attr($err_msg); ?>"
							<?php echo $disabled_attr; ?> />
					</div>
				</div>
				<!-- Advanced settings -->
				<div class="dk-card-section dk-card-section-advanced">
					<button type="button"
						class="dk-advanced-toggle"
						aria-expanded="<?php echo $advanced_open ? 'true' : 'false'; ?>"
						aria-controls="<?php echo esc_attr($advanced_id); ?>">
						<span>
							<?php esc_html_e('Advanced Features', 'duplicate-killer'); ?>
						</span>
						<span class="dk-advanced-toggle-icon" aria-hidden="true">
							<?php echo $advanced_open ? '−' : '+'; ?>
						</span>
					</button>

					<div id="<?php echo esc_attr($advanced_id); ?>"
						class="dk-advanced-content<?php echo $advanced_open ? ' is-active' : ''; ?>">

						<div class="dk-advanced-content-inner">
							<!-- IP limit -->
							<div class="dk-feature-row dk-feature-row-has-fields">
								<div class="dk-feature-info">
									<h4><?php esc_html_e('1. IP limit', 'duplicate-killer'); ?></h4>

									<p>
										<?php esc_html_e('Block repeated submissions from the same IP address.', 'duplicate-killer'); ?>

										<a class="dk-feature-inline-link"
										   href="https://verselabwp.com/limit-submissions-by-ip-address-in-wordpress-free-pro/"
										   target="_blank"
										   rel="noopener">
											<?php esc_html_e('How it works?', 'duplicate-killer'); ?>
										</a>
									</p>
								</div>

								<div class="dk-feature-control">
									<div class="dk-input-switch-ios">
										<input type="checkbox"
											class="ios-switch-input"
											id="<?php echo esc_attr('user_ip_' . $form_safe); ?>"
											name="<?php echo esc_attr($option_name . '[' . $form_key . '][user_ip]'); ?>"
											value="1"
											data-target="<?php echo esc_attr('#dk-limit-ip_' . $form_safe); ?>"
											<?php checked($ip_enabled); ?>
											<?php echo $disabled_attr; ?> />

										<label class="ios-switch-label" for="<?php echo esc_attr('user_ip_' . $form_safe); ?>"></label>
									</div>
								</div>

								<div id="<?php echo esc_attr('dk-limit-ip_' . $form_safe); ?>"
									class="dk-feature-fields dk-feature-fields--align-descriptions dk-toggle-section<?php echo $ip_enabled ? ' is-active' : ''; ?>">

									<div class="dk-feature-field dk-feature-field--wide">
										<label><?php esc_html_e('IP warning message', 'duplicate-killer'); ?></label>
										<p><?php esc_html_e('This message appears when a visitor is blocked by the IP limit.', 'duplicate-killer'); ?></p>

										<input type="text"
											class="dk-error-input"
											name="<?php echo esc_attr($option_name . '[' . $form_key . '][error_message_limit_ip_option]'); ?>"
											value="<?php echo esc_attr($ip_err_msg); ?>"
											<?php echo $disabled_attr; ?> />
									</div>

									<div class="dk-feature-field dk-feature-field--small">
										<label><?php esc_html_e('Block duration', 'duplicate-killer'); ?></label>
										<p><?php esc_html_e('Number of days this IP address should be blocked.', 'duplicate-killer'); ?></p>

										<div class="dk-input-prefix-group">
											<span class="dk-input-prefix-label">
												<?php esc_html_e('Days', 'duplicate-killer'); ?>
											</span>

											<input type="text"
												class="dk-input-prefix-field"
												name="<?php echo esc_attr($option_name . '[' . $form_key . '][user_ip_days]'); ?>"
												value="<?php echo esc_attr($ip_days); ?>"
												<?php echo $disabled_attr; ?> />
										</div>
									</div>
								</div>
							</div>

							<!-- Cookie -->
							<div class="dk-feature-row dk-feature-row-has-fields">
								<div class="dk-feature-info">
									<h4><?php esc_html_e('2. Browser protection', 'duplicate-killer'); ?></h4>

									<p>
										<?php esc_html_e('Use cookies to prevent the same visitor from submitting the same values again.', 'duplicate-killer'); ?>

										<a class="dk-feature-inline-link"
										   href="https://verselabwp.com/unique-entries-per-user-in-wordpress-how-to-use-it/"
										   target="_blank"
										   rel="noopener">
											<?php esc_html_e('How to use it?', 'duplicate-killer'); ?>
										</a>
									</p>
								</div>

								<div class="dk-feature-control">
									<div class="dk-input-switch-ios">
										<input type="checkbox"
											class="ios-switch-input"
											id="<?php echo esc_attr('cookie_' . $form_safe); ?>"
											name="<?php echo esc_attr($option_name . '[' . $form_key . '][cookie_option]'); ?>"
											value="1"
											data-target="<?php echo esc_attr('#cookie_section_' . $form_safe); ?>"
											<?php checked($cookie_enabled); ?>
											<?php echo $disabled_attr; ?> />

										<label class="ios-switch-label" for="<?php echo esc_attr('cookie_' . $form_safe); ?>"></label>
									</div>
								</div>

								<div id="<?php echo esc_attr('cookie_section_' . $form_safe); ?>"
									class="dk-feature-fields dk-toggle-section<?php echo $cookie_enabled ? ' is-active' : ''; ?>">

									<div class="dk-feature-field dk-feature-field--full">
										<label><?php esc_html_e('Cookie duration', 'duplicate-killer'); ?></label>
										<p><?php esc_html_e('Number of days this browser should be remembered.', 'duplicate-killer'); ?></p>

										<div class="dk-input-prefix-group">
											<span class="dk-input-prefix-label">
												<?php esc_html_e('Days', 'duplicate-killer'); ?>
											</span>

											<input type="text"
												class="dk-input-prefix-field"
												name="<?php echo esc_attr($option_name . '[' . $form_key . '][cookie_option_days]'); ?>"
												value="<?php echo esc_attr($cookie_days); ?>"
												<?php echo $disabled_attr; ?> />
										</div>
									</div>
								</div>
							</div>
							<?php
							if (!$is_locked_form) {
								DuplicateKiller_CrossForm::render_per_form(
									$option_name,
									$form_key,
									$form_opts
								);
							}
							?>
							
							<!-- Shortcode -->
							<div class="dk-feature-row dk-feature-row-has-fields">
								<div class="dk-feature-info">
									<h4><?php esc_html_e('4. Submission count display', 'duplicate-killer'); ?></h4>

									<p>
										<?php esc_html_e('Use this shortcode to display the number of saved submissions for this form.', 'duplicate-killer'); ?>

										<a class="dk-feature-inline-link"
										   href="https://verselabwp.com/what-is-duplicate-killer/#display-submission-count"
										   target="_blank"
										   rel="noopener">
											<?php esc_html_e('What is this?', 'duplicate-killer'); ?>
										</a>
									</p>
								</div>

								<div class="dk-feature-control"></div>

								<div class="dk-feature-fields is-active">
									<div class="dk-feature-field dk-feature-field--full">
										<label><?php esc_html_e('Shortcode', 'duplicate-killer'); ?></label>
										<p><?php esc_html_e('Copy and paste this shortcode into a page, post, or widget.', 'duplicate-killer'); ?></p>

										<?php
										$shortcode = '[duplicateKiller plugin="' . esc_attr($db_plugin_key) . '" form="' . esc_attr($form_key) . '"]';
										$short_id = 'dk_shortcode_' . $form_safe;
										?>

										<div class="dk-shortcode-control">
											<input type="text"
												id="<?php echo esc_attr($short_id); ?>"
												value="<?php echo esc_attr($shortcode); ?>"
												readonly
												class="dk-error-input">

											<button type="button"
												onclick="copyDKShortcode('<?php echo esc_js($short_id); ?>')"
												class="button button-primary"
												<?php echo $disabled_attr; ?>>
												<?php esc_html_e('Copy', 'duplicate-killer'); ?>
											</button>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
            </div>
			
            <!-- Stored submissions -->
			<div class="dk-card-section dk-stored-submissions<?php echo $count > 0 ? ' has-records' : ' is-empty'; ?>">

				<div class="dk-stored-submissions-card">

					<div class="dk-stored-submissions-icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
							<ellipse cx="12" cy="5" rx="7" ry="3" stroke-width="1.7"/>
							<path d="M5 5v5c0 1.7 3.1 3 7 3s7-1.3 7-3V5" stroke-width="1.7"/>
							<path d="M5 10v5c0 1.7 3.1 3 7 3s7-1.3 7-3v-5" stroke-width="1.7"/>
							<path d="M5 15v4c0 1.7 3.1 3 7 3s7-1.3 7-3v-4" stroke-width="1.7"/>
						</svg>
					</div>

					<div class="dk-stored-submissions-content">

						<?php if ( $count > 0 ) : ?>

							<label for="<?php echo esc_attr( 'delete_records_' . $form_safe ); ?>" class="dk-stored-submissions-label">
								<input type="checkbox"
									id="<?php echo esc_attr( 'delete_records_' . $form_safe ); ?>"
									name="<?php echo esc_attr( $option_name . '[' . $form_key . '][delete_records]' ); ?>"
									value="1"
									class="dk-delete-checkbox"
									<?php echo $disabled_attr; ?>>

								<span>
									<strong>
										<?php
										printf(
											esc_html(
												_n(
													'%s saved submission for this form.',
													'%s saved submissions for this form.',
													(int) $count,
													'duplicate-killer'
												)
											),
											esc_html( (string) $count )
										);
										?>
									</strong>

									<small>
										<?php esc_html_e( 'Check to delete all saved submissions.', 'duplicate-killer' ); ?>
									</small>
								</span>
							</label>

						<?php else : ?>

							<strong>
								<?php esc_html_e( 'No submissions stored yet.', 'duplicate-killer' ); ?>
							</strong>

							<small>
								<?php esc_html_e( 'Saved submissions for this form can be deleted from here when available.', 'duplicate-killer' ); ?>
							</small>

						<?php endif; ?>

					</div>

					<div class="dk-stored-submissions-action">
						<?php if ( $count > 0 ) : ?>
							<a class="dk-stored-submissions-button"
							   href="<?php echo esc_url(
									admin_url(
										'admin.php?page=duplicateKiller_database&dk_view=forms&s=' . rawurlencode( $form_key )
									)
							   ); ?>">
								<?php esc_html_e( 'View submissions', 'duplicate-killer' ); ?>
							</a>
						<?php else : ?>
							<a class="dk-stored-submissions-button"
							   href="https://verselabwp.com/what-is-duplicate-killer"
							   target="_blank"
							   rel="noopener">
								<?php esc_html_e( 'Learn more', 'duplicate-killer' ); ?>
								<span aria-hidden="true">›</span>
							</a>
						<?php endif; ?>
					</div>

				</div>

			</div>
			
        </div>
        <?php
		$form_index++;
    }
	?>
	</div>
	<?php
}