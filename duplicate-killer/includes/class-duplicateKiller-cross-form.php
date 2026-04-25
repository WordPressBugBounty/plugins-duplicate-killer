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
        ?>
        <div class="dk-cross-form-option">
            <fieldset class="dk-fieldset">
                <legend class="dk-legend-title">
					<?php esc_html_e('Cross-Form Duplicate Protection', 'duplicate-killer'); ?>
					<small style="font-weight:normal; margin-left:8px;">
						<a href="https://verselabwp.com/cross-form-duplicate-protection-in-wordpress-forms/" target="_blank" rel="noopener">
							<?php esc_html_e('How it works', 'duplicate-killer'); ?>
						</a>
					</small>
				</legend>

                <p>
                    <?php esc_html_e(
                        'Allow this form to participate in cross-form duplicate detection.',
                        'duplicate-killer'
                    ); ?>
                </p>

                <div class="dk-input-switch-ios">
                    <input
                        type="checkbox"
                        class="ios-switch-input"
                        id="<?php echo esc_attr('cross_form_' . $formSafe); ?>"
                        name=""
                        value="1"
                    />

                    <label
                        class="ios-switch-label"
                        for="<?php echo esc_attr('cross_form_' . $formSafe); ?>"
                    ></label>

                    <span class="ios-switch-text">
                        <?php esc_html_e('Enable Cross-Form Duplicate Protection for this form', 'duplicate-killer'); ?>
                    </span>
                </div>
            </fieldset>
        </div>
        <?php
    }
}