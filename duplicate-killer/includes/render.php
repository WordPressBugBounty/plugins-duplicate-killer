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

    echo '<h2 class="dk-form-header">' . esc_html($plugin_label) . '</h2>';
    if (!$licensed) {
        echo '<div class="notice notice-warning"><p>' .
            esc_html__('Some features are available in Duplicate Killer PRO.', 'duplicate-killer') .
        '</p></div>';
    }
	
	// Elementor Group Mode (only for Elementor)
	if ($db_plugin_key === 'elementor') {

		$group_mode = (int) get_option('duplicateKiller_elementor_group_mode', 0);

		echo '<div class="dk-elementor-group-mode" style="margin:15px 0;padding:12px 15px;background:#f8f9fa;border-left:4px solid #0073aa;">';

		echo '<label style="display:flex;align-items:center;gap:8px;font-weight:600;">';
		echo '<input type="checkbox" name="duplicateKiller_elementor_group_mode" value="1" ' . checked($group_mode, 1, false) . ' />';
		echo ' Enable Group Mode (Treat forms with the same Form Name as one form)';
		echo '</label>';

		echo '<p style="margin:6px 0 0 24px;font-size:13px;color:#555;">';
		echo 'When enabled, all Elementor forms that share the same Form Name will be treated as a single form. Recommended if you duplicated forms across multiple pages.';
		echo '</p>';

		echo '</div>';
	}

	$form_index = 0;

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

        $count = $counts[$form_key] ?? $counts[$form_name] ?? 0;

        ?>
        <div class="dk-single-form<?php echo $is_locked_form ? ' dk-form-locked' : ''; ?>">
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

            <h4 class="dk-form-header"><?php echo esc_html($form_key); ?></h4>

            <input type="hidden"
                name="<?php echo esc_attr($option_name . '[' . $form_key . '][form_id]'); ?>"
                value="<?php echo esc_attr((string)$form_id); ?>" />

            <h4><?php esc_html_e('Choose the unique fields', 'duplicate-killer'); ?></h4>

            <?php foreach ($fields as $field_index => $f):

                    $fid   = (string)($f['id'] ?? '');
                    if ($fid === '') continue;
                    $label = (string)($f['label'] ?? $fid);
                    $type  = (string)($f['type'] ?? '');
                    $is_checked = !empty($form_opts[$fid]);
                    $input_id = 'dk_' . $form_safe . '__' . sanitize_html_class($fid);
?>

                <div class="dk-input-checkbox-callback">
                    <input type="checkbox"
                        id="<?php echo esc_attr($input_id); ?>"
                        name="<?php echo esc_attr($option_name . '[' . $form_key . '][' . $fid . ']'); ?>"
                        value="1"
                        <?php checked($is_checked); ?>
						<?php echo $disabled_attr; ?>>

                    <label for="<?php echo esc_attr($input_id); ?>">
                        <?php echo esc_html($label); ?>
                        <?php if ($type !== ''): ?>
                            <small style="opacity:.7;">(<?php echo esc_html($type); ?>)</small>
                        <?php endif; ?>
                    </label>
					<?php
					 // Store labels for Formidable + Ninja Forms
					$store_labels = (
						$db_plugin_key === 'Formidable' ||
						$db_plugin_key === 'NinjaForms' ||
						$option_name === 'Formidable_page' ||
						$option_name === 'NinjaForms_page'
					);

					// Formidable uses numeric field IDs; normalize when possible
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

            <div class="<?php echo esc_attr(trim('dk-pro-wrap' . $pro_class)); ?>">

                <!-- Error message -->
                <div class="dk-set-error-message">
                    <fieldset class="dk-fieldset dk-error-fieldset">
                        <legend class="dk-legend-title">
							<?php esc_html_e('Error message when duplicate is found', 'duplicate-killer'); ?>
							<small style="font-weight:normal; margin-left:8px;">
								<a href="https://verselabwp.com/what-is-the-set-error-message-field-in-duplicate-killer/" target="_blank" rel="noopener">
									<?php esc_html_e('What is this?', 'duplicate-killer'); ?>
								</a>
							</small>
						</legend>
						<p class="dk-error-instruction">This message will be shown when the user submits a form with duplicate values.</p>
                        <input type="text"
                            class="dk-error-input"
                            name="<?php echo esc_attr($option_name . '[' . $form_key . '][error_message]'); ?>"
                            value="<?php echo esc_attr($err_msg); ?>"
							<?php echo $disabled_attr; ?> />
                    </fieldset>
                </div>

                <!-- IP limit -->
                <div class="dk-limit_submission_by_ip">
                    <fieldset class="dk-fieldset">
                        <legend class="dk-legend-title">
							<?php esc_html_e('Limit submissions by IP address', 'duplicate-killer'); ?>
							<small style="font-weight:normal; margin-left:8px;">
								<a href="https://verselabwp.com/limit-submissions-by-ip-address-in-wordpress-free-pro/" target="_blank" rel="noopener">
									<?php esc_html_e('How it works', 'duplicate-killer'); ?>
								</a>
							</small>
						</legend>
						<p><strong>This feature</strong> restrict form entries based on IP address for x days.</p>
                        <div class="dk-input-switch-ios">
                            <input type="checkbox"
                                class="ios-switch-input"
                                id="<?php echo esc_attr('user_ip_' . $form_safe); ?>"
                                name="<?php echo esc_attr($option_name . '[' . $form_key . '][user_ip]'); ?>"
                                value="1"
                                data-target="<?php echo esc_attr('#dk-limit-ip_' . $form_safe); ?>"
                                <?php checked($ip_enabled); ?>
								<?php echo $disabled_attr; ?>
                            />
                            <label class="ios-switch-label" for="<?php echo esc_attr('user_ip_' . $form_safe); ?>"></label>
                            <span class="ios-switch-text"><?php esc_html_e('Activate this function', 'duplicate-killer'); ?></span>
                        </div>

                        <div id="<?php echo esc_attr('dk-limit-ip_' . $form_safe); ?>"
                            class="dk-toggle-section<?php echo $ip_enabled ? ' is-active' : ''; ?>">
                            <label><?php esc_html_e('Set error message for this option:', 'duplicate-killer'); ?></label>
                            <input type="text"
                                class="dk-error-input"
                                name="<?php echo esc_attr($option_name . '[' . $form_key . '][error_message_limit_ip_option]'); ?>"
                                value="<?php echo esc_attr($ip_err_msg); ?>"
								<?php echo $disabled_attr; ?> />

                            <label><?php esc_html_e('IP block duration (in days):', 'duplicate-killer'); ?></label>
                            <input type="text"
                                class="dk-error-input"
                                name="<?php echo esc_attr($option_name . '[' . $form_key . '][user_ip_days]'); ?>"
                                value="<?php echo esc_attr($ip_days); ?>"
								<?php echo $disabled_attr; ?> />
                        </div>
                    </fieldset>
                </div>

                <!-- Cookie -->
                <div class="dk-set-unique-entries-per-user">
                    <fieldset class="dk-fieldset">
                       <legend class="dk-legend-title">
							<?php esc_html_e('Unique entries per user', 'duplicate-killer'); ?>
							<small style="font-weight:normal; margin-left:8px;">
								<a href="https://verselabwp.com/unique-entries-per-user-in-wordpress-how-to-use-it/" target="_blank" rel="noopener">
									<?php esc_html_e('How to use it?', 'duplicate-killer'); ?>
								</a>
							</small>
						</legend>
						<p><strong>This feature uses cookies.</strong> Multiple users can submit the same entry, but a single user cannot submit the same one twice.</p><p>Note: Cookies are set only for forms where this feature is enabled.</p>
                        <div class="dk-input-switch-ios">
                            <input type="checkbox"
                                class="ios-switch-input"
                                id="<?php echo esc_attr('cookie_' . $form_safe); ?>"
                                name="<?php echo esc_attr($option_name . '[' . $form_key . '][cookie_option]'); ?>"
                                value="1"
                                data-target="<?php echo esc_attr('#cookie_section_' . $form_safe); ?>"
                                <?php checked($cookie_enabled); ?>
								<?php echo $disabled_attr; ?>
                            />
                            <label class="ios-switch-label" for="<?php echo esc_attr('cookie_' . $form_safe); ?>"></label>
                            <span class="ios-switch-text"><?php esc_html_e('Activate this function', 'duplicate-killer'); ?></span>
                        </div>

                        <div id="<?php echo esc_attr('cookie_section_' . $form_safe); ?>"
                            class="dk-toggle-section<?php echo $cookie_enabled ? ' is-active' : ''; ?>">
                            <label><?php esc_html_e('Cookie persistence (days - max 365):', 'duplicate-killer'); ?></label>
                            <input type="text"
                                class="dk-error-input"
                                name="<?php echo esc_attr($option_name . '[' . $form_key . '][cookie_option_days]'); ?>"
                                value="<?php echo esc_attr($cookie_days); ?>"
								<?php echo $disabled_attr; ?> />
                        </div>
                    </fieldset>
                </div>

                <!-- Shortcode -->
                <div class="dk-shortcode-count-submission">
                    <fieldset class="dk-fieldset">
						<legend class="dk-legend-title">
							<?php esc_html_e('Display submission count', 'duplicate-killer'); ?>
							<small style="font-weight:normal; margin-left:8px;">
								<a href="https://verselabwp.com/what-is-duplicate-killer/#display-submission-count" target="_blank" rel="noopener">
									<?php esc_html_e('What is this?', 'duplicate-killer'); ?>
								</a>
							</small>
						</legend>
                        <?php
                            $shortcode = '[duplicateKiller plugin="' . esc_attr($db_plugin_key) . '" form="' . esc_attr($form_key) . '"]';
                            $short_id = 'dk_shortcode_' . $form_safe;
                        ?>
                        <div style="display:flex;gap:10px;align-items:center;">
                            <input type="text" id="<?php echo esc_attr($short_id); ?>" value="<?php echo esc_attr($shortcode); ?>" readonly style="flex: 1; padding: 8px 12px; font-size: 16px; border: 1px solid #ccc; border-radius: 5px; background: #fff; cursor: default;">
                            <button type="button" onclick="copyDKShortcode('<?php echo esc_js($short_id); ?>')" style="padding: 8px 16px; font-size: 14px; background-color: #0073aa; color: white; border: none; border-radius: 5px; cursor: pointer;"<?php echo $disabled_attr; ?>><?php esc_html_e('Copy', 'duplicate-killer'); ?></button>
                        </div>
                    </fieldset>
                </div>
            </div>
			
			<!-- Cross-posting -->
			<?php
			if (!$is_locked_form) {
				DuplicateKiller_CrossForm::render_per_form(
					$option_name,
					$form_key,
					$form_opts
				);
			}
			?>
			
            <!-- Delete records -->
            <div class="dk-box dk-delete-records">
                <p class="dk-record-count">
                    📦 <span class="dk-count-number"><?php echo esc_html((string)$count); ?></span>
                    <?php esc_html_e('saved submissions found for this form', 'duplicate-killer'); ?>
                </p>
                <?php if ($count > 0) : ?>
                    <label for="<?php echo esc_attr('delete_records_' . $form_safe); ?>" class="dk-delete-label">
                        <input type="checkbox"
                            id="<?php echo esc_attr('delete_records_' . $form_safe); ?>"
                            name="<?php echo esc_attr($option_name . '[' . $form_key . '][delete_records]'); ?>"
                            value="1"
                            class="dk-delete-checkbox"
							<?php echo $disabled_attr; ?>>
                        🗑️ <span><?php esc_html_e('Delete all saved entries for this form', 'duplicate-killer'); ?>
                            <small>(<?php esc_html_e('this action cannot be undone', 'duplicate-killer'); ?>)</small></span>
                    </label>
                <?php endif; ?>
            </div>
			
        </div>
        <?php
		$form_index++;
    }
}