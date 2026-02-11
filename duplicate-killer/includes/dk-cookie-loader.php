<?php
defined( 'ABSPATH' ) || exit;

/**
 * Duplicate Killer - Cookie loader (CSP-safe)
 *
 * - Enqueues ONE external JS file (no inline script)
 * - Decides if cookie feature is enabled by reading your saved options
 * - JS sets cookie only when it detects any supported form plugin in the DOM
 *
 * Extend by adding new providers via filter: dk_cookie_providers
 */

add_action( 'wp_enqueue_scripts', 'duplicateKiller_cookie_enqueue_script' );

function duplicateKiller_cookie_enqueue_script() {

	if ( is_admin() ) {
		return;
	}

	// Fast path: cookie already exists -> nothing to do
	if ( ! empty( $_COOKIE['dk_form_cookie'] ) ) {
		return;
	}

	$config = duplicateKiller_cookie_build_config();
	if ( empty( $config['enabled'] ) ) {
		return;
	}

	$handle = 'dk-cookie';
	$ver = defined( 'DUPLICATEKILLER_VERSION' ) ? DUPLICATEKILLER_VERSION : '1.0.0';

	// IMPORTANT: DK_PLUGIN_FILE should point to the main plugin file
	$src = plugins_url( 'assets/dk-cookie.js', DUPLICATEKILLER_PLUGIN_FILE );

	wp_enqueue_script( $handle, $src, array(), $ver, true );

	// Pass settings safely to JS
	wp_localize_script( $handle, 'DK_COOKIE', array(
		'cookie_name' => 'dk_form_cookie',
		'days'        => (int) $config['days'],
		'selector'    => (string) $config['selector'], // combined selector for all enabled providers
		'max_wait_ms' => 3000, // retry window for late renders
		'interval_ms' => 100,  // retry interval
	) );
}

/**
 * Build cookie config from providers.
 * Returns:
 * - enabled: bool
 * - days: int (max days across enabled providers)
 * - selector: string (combined CSS selectors)
 */
function duplicateKiller_cookie_build_config() {

	$providers = duplicateKiller_cookie_default_providers();

	// Allow future integrations to add/override providers
	$providers = apply_filters( 'duplicateKiller_cookie_providers', $providers );

	$enabled   = false;
	$max_days  = 1;
	$selectors = array();

	foreach ( $providers as $provider ) {
		if ( empty( $provider['enabled'] ) ) {
			continue;
		}

		$enabled = true;

		$days = isset( $provider['days'] ) ? absint( $provider['days'] ) : 0;
		if ( $days < 1 ) {
			$days = 1;
		}
		if ( $days > $max_days ) {
			$max_days = $days;
		}

		if ( ! empty( $provider['selector'] ) && is_string( $provider['selector'] ) ) {
			$selectors[] = trim( $provider['selector'] );
		}
	}

	$selectors = array_filter( array_unique( $selectors ) );

	return array(
		'enabled'  => $enabled,
		'days'     => $max_days,
		'selector' => implode( ', ', $selectors ),
	);
}

/**
 * Default providers for cookie feature.
 * - Enable/Days should match YOUR saved settings for each form plugin integration.
 */
function duplicateKiller_cookie_default_providers() {

	$providers = array();

	// =========================
	// Ninja Forms (supports FREE global keys + PRO per-form keys)
	// =========================
	$ninja_page = get_option( 'NinjaForms_page', array() );

	$ninja_on   = false;
	$ninja_days = 7;

	if ( is_array( $ninja_page ) && ! empty( $ninja_page ) ) {

		// 1) FREE / legacy: global ON/OFF
		if ( ! empty( $ninja_page['ninjaforms_cookie_option'] ) && (string) $ninja_page['ninjaforms_cookie_option'] === '1' ) {
			$ninja_on = true;

			if ( ! empty( $ninja_page['ninjaforms_cookie_option_days'] ) && is_numeric( $ninja_page['ninjaforms_cookie_option_days'] ) ) {
				$ninja_days = absint( $ninja_page['ninjaforms_cookie_option_days'] );
			}
		}

		// 2) PRO: per-form config (e.g. "contactme.1" => [ 'cookie_option_days' => 123, ... ])
		if ( ! $ninja_on ) {
			$max_days = 0;

			foreach ( $ninja_page as $key => $cfg ) {
				if ( ! is_array( $cfg ) ) {
					continue;
				}

				// PRO stores days here
				if ( isset( $cfg['cookie_option_days'] ) && is_numeric( $cfg['cookie_option_days'] ) ) {
					$d = absint( $cfg['cookie_option_days'] );
					if ( $d > $max_days ) {
						$max_days = $d;
					}
				}

				// If you also store an explicit per-form on/off in PRO, support it here:
				// if ( isset($cfg['cookie_option']) && (string)$cfg['cookie_option'] === '1' ) { ... }
			}

			if ( $max_days > 0 ) {
				$ninja_on   = true;
				$ninja_days = $max_days;
			}
		}
	}

	if ( $ninja_days < 1 ) {
		$ninja_days = 1;
	}

	$providers['ninja_forms'] = array(
		'enabled'  => $ninja_on,
		'days'     => $ninja_days,
		'selector' => '.nf-form-content, .nf-form-wrap, .nf-form-cont, form[id^="nf-form-"], [data-nf-form-id]',
	);

	// =========================
	// Contact Form 7 (supports FREE global keys + PRO per-form keys)
	// =========================
	$cf7_page = get_option( 'CF7_page', array() );

	$cf7_on   = false;
	$cf7_days = 7;

	if ( is_array( $cf7_page ) && ! empty( $cf7_page ) ) {

		// 1) FREE: global ON/OFF (cf7_cookie_option + cf7_cookie_option_days)
		if ( ! empty( $cf7_page['cf7_cookie_option'] ) && (string) $cf7_page['cf7_cookie_option'] === '1' ) {
			$cf7_on = true;

			if ( ! empty( $cf7_page['cf7_cookie_option_days'] ) && is_numeric( $cf7_page['cf7_cookie_option_days'] ) ) {
				$cf7_days = absint( $cf7_page['cf7_cookie_option_days'] );
			}
		}

		// 2) PRO: per-form config (e.g. "Contact Form 2" => [ 'cookie_option_days' => 7, ... ])
		// In PRO you also have string keys like cf7_save_image => "1" - ignore those (not arrays)
		if ( ! $cf7_on ) {
			$max_days = 0;

			foreach ( $cf7_page as $key => $cfg ) {
				if ( ! is_array( $cfg ) ) {
					continue;
				}

				if ( isset( $cfg['cookie_option_days'] ) && is_numeric( $cfg['cookie_option_days'] ) ) {
					$d = absint( $cfg['cookie_option_days'] );
					if ( $d > $max_days ) {
						$max_days = $d;
					}
				}
			}

			if ( $max_days > 0 ) {
				$cf7_on   = true;
				$cf7_days = $max_days;
			}
		}
	}

	if ( $cf7_days < 1 ) {
		$cf7_days = 1;
	}

	$providers['cf7'] = array(
		'enabled'  => $cf7_on,
		'days'     => $cf7_days,
		'selector' => '.wpcf7 form, form.wpcf7-form, input.wpcf7-submit',
	);


	// =========================
	// Forminator (supports FREE global keys + PRO per-form keys)
	// =========================
	$forminator_page = get_option( 'Forminator_page', array() );

	$forminator_on   = false;
	$forminator_days = 7;

	if ( is_array( $forminator_page ) && ! empty( $forminator_page ) ) {

		// 1) FREE / legacy: global ON/OFF
		if ( ! empty( $forminator_page['forminator_cookie_option'] ) && (string) $forminator_page['forminator_cookie_option'] === '1' ) {
			$forminator_on = true;

			if ( ! empty( $forminator_page['forminator_cookie_option_days'] ) && is_numeric( $forminator_page['forminator_cookie_option_days'] ) ) {
				$forminator_days = absint( $forminator_page['forminator_cookie_option_days'] );
			}
		}

		// 2) PRO: per-form config (e.g. "quote-request" => [ 'cookie_option_days' => 7, ... ])
		if ( ! $forminator_on ) {
			$max_days = 0;

			foreach ( $forminator_page as $key => $cfg ) {
				if ( ! is_array( $cfg ) ) {
					continue; // skips global keys in FREE, but PRO has only arrays anyway
				}

				if ( isset( $cfg['cookie_option_days'] ) && is_numeric( $cfg['cookie_option_days'] ) ) {
					$d = absint( $cfg['cookie_option_days'] );
					if ( $d > $max_days ) {
						$max_days = $d;
					}
				}
			}

			if ( $max_days > 0 ) {
				$forminator_on   = true;
				$forminator_days = $max_days;
			}
		}
	}

	if ( $forminator_days < 1 ) {
		$forminator_days = 1;
	}

	$providers['forminator'] = array(
		'enabled'  => $forminator_on,
		'days'     => $forminator_days,
		// Common Forminator markup patterns
		'selector' => '.forminator-ui form, form.forminator-form, .forminator-custom-form, form[id^="forminator-module-"]',
	);

	// =========================
	// WPForms (supports FREE global keys + PRO per-form keys)
	// =========================
	$wpforms_page = get_option( 'WPForms_page', array() );

	$wpforms_on   = false;
	$wpforms_days = 7;

	if ( is_array( $wpforms_page ) && ! empty( $wpforms_page ) ) {

		// 1) FREE: global ON/OFF (wpforms_cookie_option + wpforms_cookie_option_days)
		if ( ! empty( $wpforms_page['wpforms_cookie_option'] ) && (string) $wpforms_page['wpforms_cookie_option'] === '1' ) {
			$wpforms_on = true;

			if ( ! empty( $wpforms_page['wpforms_cookie_option_days'] ) && is_numeric( $wpforms_page['wpforms_cookie_option_days'] ) ) {
				$wpforms_days = absint( $wpforms_page['wpforms_cookie_option_days'] );
			}
		}

		// 2) PRO: per-form config (e.g. "Simple Contact Form (ID #96)" => [ 'cookie_option_days' => 2, 'cookie_option' => 1, ... ])
		if ( ! $wpforms_on ) {
			$max_days = 0;

			foreach ( $wpforms_page as $key => $cfg ) {
				if ( ! is_array( $cfg ) ) {
					continue; // skips global keys in FREE
				}

				// If PRO has explicit on/off, respect it when present
				$per_form_on = true;
				if ( isset( $cfg['cookie_option'] ) ) {
					$per_form_on = (string) $cfg['cookie_option'] === '1';
				}

				if ( ! $per_form_on ) {
					continue;
				}

				if ( isset( $cfg['cookie_option_days'] ) && is_numeric( $cfg['cookie_option_days'] ) ) {
					$d = absint( $cfg['cookie_option_days'] );
					if ( $d > $max_days ) {
						$max_days = $d;
					}
				}
			}

			if ( $max_days > 0 ) {
				$wpforms_on   = true;
				$wpforms_days = $max_days;
			}
		}
	}

	if ( $wpforms_days < 1 ) {
		$wpforms_days = 1;
	}

	$providers['wpforms'] = array(
		'enabled'  => $wpforms_on,
		'days'     => $wpforms_days,
		// Common WPForms markup patterns
		'selector' => '.wpforms-container form, form.wpforms-form, form[id^="wpforms-form-"], .wpforms-form',
	);
	
	// =========================
	// Breakdance Forms (supports FREE global keys + PRO per-form keys)
	// =========================
	$breakdance_page = get_option( 'Breakdance_page', array() );

	$breakdance_on   = false;
	$breakdance_days = 7;

	if ( is_array( $breakdance_page ) && ! empty( $breakdance_page ) ) {

		// 1) FREE: global ON/OFF (breakdance_cookie_option + breakdance_cookie_option_days)
		if ( ! empty( $breakdance_page['breakdance_cookie_option'] ) && (string) $breakdance_page['breakdance_cookie_option'] === '1' ) {
			$breakdance_on = true;

			if ( ! empty( $breakdance_page['breakdance_cookie_option_days'] ) && is_numeric( $breakdance_page['breakdance_cookie_option_days'] ) ) {
				$breakdance_days = absint( $breakdance_page['breakdance_cookie_option_days'] );
			}
		}

		// 2) PRO: per-form config (e.g. "Contact Form.101" => [ 'cookie_option_days' => 7, ... ])
		if ( ! $breakdance_on ) {
			$max_days = 0;

			foreach ( $breakdance_page as $key => $cfg ) {
				if ( ! is_array( $cfg ) ) {
					continue; // skips global keys in FREE
				}

				if ( isset( $cfg['cookie_option_days'] ) && is_numeric( $cfg['cookie_option_days'] ) ) {
					$d = absint( $cfg['cookie_option_days'] );
					if ( $d > $max_days ) {
						$max_days = $d;
					}
				}
			}

			if ( $max_days > 0 ) {
				$breakdance_on   = true;
				$breakdance_days = $max_days;
			}
		}
	}

	if ( $breakdance_days < 1 ) {
		$breakdance_days = 1;
	}

	$providers['breakdance'] = array(
		'enabled'  => $breakdance_on,
		'days'     => $breakdance_days,

		/**
		 * Breakdance forms markup varies by version, so we use multiple safe selectors:
		 * - form[data-bde-form-id] often exists
		 * - .breakdance-form is common wrapper
		 * - generic fallback: form.bde-form (if present)
		 */
		'selector' => 'form[data-bde-form-id], .breakdance-form form, form.breakdance-form, form.bde-form',
	);

	// =========================
	// Elementor Forms (supports FREE global keys + PRO per-form keys)
	// =========================
	$elementor_page = get_option( 'Elementor_page', array() );

	$elementor_on   = false;
	$elementor_days = 7;

	if ( is_array( $elementor_page ) && ! empty( $elementor_page ) ) {

		// 1) FREE: global ON/OFF (elementor_cookie_option + elementor_cookie_option_days)
		if ( ! empty( $elementor_page['elementor_cookie_option'] ) && (string) $elementor_page['elementor_cookie_option'] === '1' ) {
			$elementor_on = true;

			if ( ! empty( $elementor_page['elementor_cookie_option_days'] ) && is_numeric( $elementor_page['elementor_cookie_option_days'] ) ) {
				$elementor_days = absint( $elementor_page['elementor_cookie_option_days'] );
			}
		}

		// 2) PRO: per-form config (e.g. "2nd FORM.1fc7fb0" => [ 'cookie_option_days' => 2, 'cookie_option' => 1, ... ])
		if ( ! $elementor_on ) {
			$max_days = 0;

			foreach ( $elementor_page as $key => $cfg ) {
				if ( ! is_array( $cfg ) ) {
					continue; // skips global keys in FREE
				}

				// If PRO has explicit on/off, respect it when present
				$per_form_on = true;
				if ( isset( $cfg['cookie_option'] ) ) {
					$per_form_on = (string) $cfg['cookie_option'] === '1';
				}
				if ( ! $per_form_on ) {
					continue;
				}

				if ( isset( $cfg['cookie_option_days'] ) && is_numeric( $cfg['cookie_option_days'] ) ) {
					$d = absint( $cfg['cookie_option_days'] );
					if ( $d > $max_days ) {
						$max_days = $d;
					}
				}
			}

			if ( $max_days > 0 ) {
				$elementor_on   = true;
				$elementor_days = $max_days;
			}
		}
	}

	if ( $elementor_days < 1 ) {
		$elementor_days = 1;
	}

	$providers['elementor_forms'] = array(
		'enabled'  => $elementor_on,
		'days'     => $elementor_days,
		// Common Elementor Forms markup patterns
		'selector' => 'form.elementor-form, .elementor-form form, .elementor-form, form[data-elementor-id]',
	);

	// =========================
	// Formidable Forms (supports FREE global keys + PRO per-form keys)
	// =========================
	$formidable_page = get_option( 'Formidable_page', array() );

	$formidable_on   = false;
	$formidable_days = 7;

	if ( is_array( $formidable_page ) && ! empty( $formidable_page ) ) {

		// 1) FREE: global ON/OFF (formidable_cookie_option + formidable_cookie_option_days)
		if ( ! empty( $formidable_page['formidable_cookie_option'] ) && (string) $formidable_page['formidable_cookie_option'] === '1' ) {
			$formidable_on = true;

			if ( ! empty( $formidable_page['formidable_cookie_option_days'] ) && is_numeric( $formidable_page['formidable_cookie_option_days'] ) ) {
				$formidable_days = absint( $formidable_page['formidable_cookie_option_days'] );
			}
		}

		// 2) PRO: per-form config (e.g. "contact-us.2" => [ 'cookie_option_days' => 12, ... ])
		if ( ! $formidable_on ) {
			$max_days = 0;

			foreach ( $formidable_page as $key => $cfg ) {
				if ( ! is_array( $cfg ) ) {
					continue; // in PRO should be arrays only, but still safe
				}

				if ( isset( $cfg['cookie_option_days'] ) && is_numeric( $cfg['cookie_option_days'] ) ) {
					$d = absint( $cfg['cookie_option_days'] );
					if ( $d > $max_days ) {
						$max_days = $d;
					}
				}
			}

			if ( $max_days > 0 ) {
				$formidable_on   = true;
				$formidable_days = $max_days;
			}
		}
	}

	if ( $formidable_days < 1 ) {
		$formidable_days = 1;
	}

	$providers['formidable'] = array(
		'enabled'  => $formidable_on,
		'days'     => $formidable_days,
		// Common Formidable frontend markup patterns
		'selector' => 'form.frm-show-form, .frm_forms form, form[id^="frm_form_"], .frm_form_fields',
	);

	/**
	 * Future providers (placeholders):
	 * Coming soon
	 */


	return $providers;
}