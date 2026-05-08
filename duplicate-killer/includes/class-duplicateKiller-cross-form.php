<?php
defined('ABSPATH') or die('No script kiddies please!');

class DuplicateKiller_CrossForm {

    /**
     * Render per-form Cross-Form Duplicate Protection switch.
     *
     * @param string $optionName
     * @param string $formKey
     * @param array  $formOpts
     * @return void
     */
    public static function render_per_form($optionName, $formKey, $formOpts)
    {
        $formSafe = duplicateKiller_sanitize_id($formKey);
        $enabled  = !empty($formOpts['cross_form_option']) && (string) $formOpts['cross_form_option'] === '1';
        ?>
        <div class="dk-feature-row">
			<div class="dk-feature-info">
				<h4><?php esc_html_e('3. Cross-form protection - Pro feature', 'duplicate-killer'); ?></h4>

				<p>
					<?php esc_html_e(
						'Check for duplicates across all forms where cross-form protection is enabled.',
						'duplicate-killer'
					); ?>

					<a class="dk-feature-inline-link"
					   href="https://verselabwp.com/cross-form-duplicate-protection-in-wordpress-forms/"
					   target="_blank"
					   rel="noopener">
						<?php esc_html_e('How it works', 'duplicate-killer'); ?>
					</a>
				</p>
			</div>

			<div class="dk-feature-control">
				<div class="dk-input-switch-ios">
					<input
						type="checkbox"
						class="ios-switch-input"
						disabled
					/>

					<label
						class="ios-switch-label"
						for="<?php echo esc_attr('cross_form_' . $formSafe); ?>"
					></label>
				</div>
			</div>
		</div>
        <?php
    }
}