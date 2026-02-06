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

add_action( 'wp_enqueue_scripts', 'dk_cookie_enqueue_script' );

function dk_cookie_enqueue_script() {

	if ( is_admin() ) {
		return;
	}

	// Fast path: cookie already exists -> nothing to do
	if ( ! empty( $_COOKIE['dk_form_cookie'] ) ) {
		return;
	}

	$config = dk_cookie_build_config();
	if ( empty( $config['enabled'] ) ) {
		return;
	}

	$handle = 'dk-cookie';
	$ver = defined( 'DuplicateKiller_VERSION' ) ? DuplicateKiller_VERSION : '1.0.0';

	// IMPORTANT: DK_PLUGIN_FILE should point to the main plugin file
	$src = plugins_url( 'assets/dk-cookie.js', DuplicateKiller_PLUGIN );

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
function dk_cookie_build_config() {

	$providers = dk_cookie_default_providers();

	// Allow future integrations to add/override providers
	$providers = apply_filters( 'dk_cookie_providers', $providers );

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
function dk_cookie_default_providers() {

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
	// Contact Form 7 (your storage: CF7_page per form with cookie_option)
	// We enable if ANY form has cookie_option=1
	// =========================
	$cf7_page = get_option( 'CF7_page', array() );

	$cf7_on   = false;
	$cf7_days = 7;

	if ( is_array( $cf7_page ) ) {
		foreach ( $cf7_page as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( ! empty( $row['cookie_option'] ) && (string) $row['cookie_option'] === '1' ) {
				$cf7_on = true;

				if ( ! empty( $row['cookie_option_days'] ) && is_numeric( $row['cookie_option_days'] ) ) {
					$cf7_days = absint( $row['cookie_option_days'] );
				}
				break;
			}
		}
	}

	$providers['cf7'] = array(
		'enabled'  => $cf7_on,
		'days'     => max( 1, $cf7_days ),
		'selector' => '.wpcf7 form, form.wpcf7-form, input.wpcf7-submit',
	);

	/**
	 * Future providers (placeholders):
	 * Coming soon
	 */
	$providers['elementor_forms'] = array(
		'enabled'  => false,
		'days'     => 7,
		'selector' => 'form.elementor-form, .elementor-form',
	);

	$providers['forminator'] = array(
		'enabled'  => false,
		'days'     => 7,
		'selector' => '.forminator-ui form, form.forminator-form',
	);

	$providers['wpforms'] = array(
		'enabled'  => false,
		'days'     => 7,
		'selector' => 'form.wpforms-form, .wpforms-container form',
	);

	return $providers;
}