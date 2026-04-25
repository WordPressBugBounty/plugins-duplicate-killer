<?php
defined( 'ABSPATH' ) || exit;

/**
 * Duplicate Killer - Cookie loader (CSP-safe)
 *
 * - Enqueues ONE external JS file (no inline script)
 * - Decides if cookie feature is enabled by reading your saved options
 * - JS sets cookie only when it detects any supported form plugin in the DOM
 *
 */

add_action( 'wp_enqueue_scripts', 'duplicateKiller_cookie_enqueue_script' );

function duplicateKiller_cookie_enqueue_script() {

	if ( is_admin() ) {
		return;
	}

	/*
	// Fast path: any DK cookie exists -> nothing to do
	// DISABLE ONLY FOR PRO
	foreach ( array_keys( $_COOKIE ) as $k ) {
		if ( strpos( $k, 'dk_form_cookie_' ) === 0 ) {
			return;
		}
	}
	*/
	$config = duplicateKiller_cookie_build_config();
	if ( empty( $config['enabled'] ) ) {
		return;
	}

	$handle = 'dk-cookie';
	$ver = defined( 'DUPLICATEKILLER_VERSION' ) ? DUPLICATEKILLER_VERSION : '1.0.0';

	// IMPORTANT: DK_PLUGIN_FILE should point to the main plugin file
	$src = plugins_url( 'assets/dk-cookie.js', DUPLICATEKILLER_PLUGIN );

	wp_enqueue_script( $handle, $src, array(), $ver, true );

	// Pass settings safely to JS
	wp_localize_script( $handle, 'DK_COOKIE', array(
		'providers'   => $config['providers'],     // NEW
		'selector'    => (string) $config['selector'], // keep (optional safety)
		'max_wait_ms' => 3000,
		'interval_ms' => 100,
	) );
}

function duplicateKiller_cookie_build_config() {

	$providers = duplicateKiller_cookie_default_providers();

	// Allow future integrations to add/override providers
	$providers = apply_filters( 'duplicateKiller_cookie_providers', $providers );

	$enabled   = false;
	$selectors = array();
	$out       = array();

	foreach ( $providers as $key => $provider ) {
		if ( empty( $provider['enabled'] ) || empty( $provider['per_form_days'] ) ) {
			continue;
		}

		$enabled = true;

		if ( ! empty( $provider['selector'] ) && is_string( $provider['selector'] ) ) {
			$selectors[] = trim( $provider['selector'] );
		}

		$out[ $key ] = array(
			'selector'      => ! empty( $provider['selector'] ) ? (string) $provider['selector'] : '',
			'days'          => ! empty( $provider['days'] ) ? max( 1, absint( $provider['days'] ) ) : 1, // fallback days (FREE/global)
			'cookie_prefix' => ! empty( $provider['cookie_prefix'] ) ? (string) $provider['cookie_prefix'] : 'dk_form_cookie_' . $key . '_',
			'per_form_days' => ( ! empty( $provider['per_form_days'] ) && is_array( $provider['per_form_days'] ) ) ? $provider['per_form_days'] : array(),
		);
	}

	$selectors = array_filter( array_unique( $selectors ) );

	return array(
		'enabled'   => $enabled,
		'selector'  => implode( ', ', $selectors ), // still useful as "any form exists" shortcut
		'providers' => $out,                       // NEW
	);
}

/**
 * Default providers for cookie feature.
 * - Enable/Days should match YOUR saved settings for each form plugin integration.
 */
function duplicateKiller_cookie_default_providers() {

	$providers = array();

	// =========================
	// Contact Form 7
	// =========================
	$cf7_page = get_option( 'CF7_page', array() );
	$cf7_page = duplicateKiller_convert_option_architecture( $cf7_page, 'cf7_' );
	$cf7_on           = false;
	$cf7_per_days     = array();

	if ( is_array( $cf7_page ) && ! empty( $cf7_page ) ) {

		foreach ( $cf7_page as $cfg ) {

			if ( ! is_array( $cfg ) ) {
				continue;
			}

			// STRICT: only if explicitly enabled
			if ( empty( $cfg['cookie_option'] ) || (string) $cfg['cookie_option'] !== '1' ) {
				continue;
			}

			if ( empty( $cfg['form_id'] ) || ! is_numeric( $cfg['form_id'] ) ) {
				continue;
			}

			$days = 1;
			if ( isset( $cfg['cookie_option_days'] ) && is_numeric( $cfg['cookie_option_days'] ) ) {
				$days = max( 1, absint( $cfg['cookie_option_days'] ) );
			}

			$cf7_per_days[ absint( $cfg['form_id'] ) ] = $days;
		}

		if ( ! empty( $cf7_per_days ) ) {
			$cf7_on = true;
		}
	}

	$providers['cf7'] = array(
		'enabled'        => $cf7_on,
		'per_form_days'  => $cf7_per_days,          // ONLY source of truth
		'cookie_prefix'  => 'dk_form_cookie_cf7_',
		'selector'       => '.wpcf7 form, form.wpcf7-form',
	);

	// =========================
	// Forminator — (cookie_option must be explicitly enabled)
	// =========================
	$forminator_page = get_option( 'Forminator_page', array() );
	$forminator_page = duplicateKiller_convert_option_architecture( $forminator_page, 'forminator_' );
	$forminator_on       = false;
	$forminator_per_days = array();

	if ( is_array( $forminator_page ) && ! empty( $forminator_page ) ) {

		foreach ( $forminator_page as $cfg ) {

			if ( ! is_array( $cfg ) ) {
				continue;
			}

			// PRO only: cookie must be explicitly enabled for this form
			if ( empty( $cfg['cookie_option'] ) || (string) $cfg['cookie_option'] !== '1' ) {
				continue;
			}

			// Form must have a valid numeric form_id
			if ( empty( $cfg['form_id'] ) || ! is_numeric( $cfg['form_id'] ) ) {
				continue;
			}

			$days = 1;
			if ( isset( $cfg['cookie_option_days'] ) && is_numeric( $cfg['cookie_option_days'] ) ) {
				$days = max( 1, absint( $cfg['cookie_option_days'] ) );
			}

			$forminator_per_days[ absint( $cfg['form_id'] ) ] = $days;
		}

		if ( ! empty( $forminator_per_days ) ) {
			$forminator_on = true;
		}
	}

	$providers['forminator'] = array(
		'enabled'        => $forminator_on,
		'per_form_days'  => $forminator_per_days, // ONLY source of truth
		'cookie_prefix'  => 'dk_form_cookie_forminator_',
		// Common Forminator markup patterns (prefer forms)
		'selector'       => '.forminator-ui form, form.forminator-form, form[id^="forminator-module-"]',
	);

	// =========================
	// WPForms — (cookie_option must be explicitly enabled)
	// =========================
	$wpforms_page = get_option( 'WPForms_page', array() );
	$wpforms_page = duplicateKiller_convert_option_architecture( $wpforms_page, 'wpforms_' );
	$wpforms_on       = false;
	$wpforms_per_days = array();

	if ( is_array( $wpforms_page ) && ! empty( $wpforms_page ) ) {

		foreach ( $wpforms_page as $cfg ) {

			if ( ! is_array( $cfg ) ) {
				continue;
			}

			// PRO only: cookie must be explicitly enabled for this form
			if ( empty( $cfg['cookie_option'] ) || (string) $cfg['cookie_option'] !== '1' ) {
				continue;
			}

			// Form must have a valid numeric form_id
			if ( empty( $cfg['form_id'] ) || ! is_numeric( $cfg['form_id'] ) ) {
				continue;
			}

			$days = 1;
			if ( isset( $cfg['cookie_option_days'] ) && is_numeric( $cfg['cookie_option_days'] ) ) {
				$days = max( 1, absint( $cfg['cookie_option_days'] ) );
			}

			$wpforms_per_days[ absint( $cfg['form_id'] ) ] = $days;
		}

		if ( ! empty( $wpforms_per_days ) ) {
			$wpforms_on = true;
		}
	}

	$providers['wpforms'] = array(
		'enabled'        => $wpforms_on,
		'per_form_days'  => $wpforms_per_days, // ONLY source of truth
		'cookie_prefix'  => 'dk_form_cookie_wpforms_',
		// Common WPForms markup patterns (prefer forms)
		'selector'       => '.wpforms-container form, form.wpforms-form, form[id^="wpforms-form-"], .wpforms-form',
	);
	
	// =========================
	// Breakdance Forms — (cookie_option must be explicitly enabled)
	// =========================
	$breakdance_page = get_option( 'Breakdance_page', array() );
	$breakdance_page = duplicateKiller_convert_option_architecture( $breakdance_page, 'breakdance_' );
	$breakdance_on       = false;
	$breakdance_per_days = array();

	if ( is_array( $breakdance_page ) && ! empty( $breakdance_page ) ) {

		foreach ( $breakdance_page as $cfg ) {

			if ( ! is_array( $cfg ) ) {
				continue;
			}

			// PRO only: cookie must be explicitly enabled for this form
			if ( empty( $cfg['cookie_option'] ) || (string) $cfg['cookie_option'] !== '1' ) {
				continue;
			}

			// Form must have a valid numeric form_id
			if ( empty( $cfg['form_id'] ) || ! is_numeric( $cfg['form_id'] ) ) {
				continue;
			}

			$days = 1;
			if ( isset( $cfg['cookie_option_days'] ) && is_numeric( $cfg['cookie_option_days'] ) ) {
				$days = max( 1, absint( $cfg['cookie_option_days'] ) );
			}

			$breakdance_per_days[ absint( $cfg['form_id'] ) ] = $days;
		}

		if ( ! empty( $breakdance_per_days ) ) {
			$breakdance_on = true;
		}
	}

	$providers['breakdance'] = array(
		'enabled'        => $breakdance_on,
		'per_form_days'  => $breakdance_per_days, // ONLY source of truth
		'cookie_prefix'  => 'dk_form_cookie_breakdance_',

		/**
		 * Breakdance forms markup varies by version, so we use multiple safe selectors:
		 * - form[data-bde-form-id] often exists
		 * - .breakdance-form is a common wrapper
		 * - fallback: form.breakdance-form / form.bde-form (if present)
		 */
		'selector'       => 'form[data-bde-form-id], .breakdance-form form, form.breakdance-form, form.bde-form',
	);

	// =========================
	// Elementor Forms —(cookie_option must be explicitly enabled)
	// =========================
	$elementor_page = get_option( 'Elementor_page', array() );
	$elementor_page = duplicateKiller_convert_option_architecture( $elementor_page, 'elementor_' );
	$elementor_on       = false;
	$elementor_per_days = array();

	if ( is_array( $elementor_page ) && ! empty( $elementor_page ) ) {

		foreach ( $elementor_page as $key => $cfg ) {

			if ( ! is_array( $cfg ) ) {
				continue;
			}
			// GROUP entry fallback (if option keys got altered): detect by form_id="__group__"
			if ( isset( $cfg['form_id'] ) && (string) $cfg['form_id'] === '__group__' ) {

				if ( empty( $cfg['cookie_option'] ) || (string) $cfg['cookie_option'] !== '1' ) {
					continue;
				}

				// We MUST have the group name. Prefer key-based extraction if possible.
				// If $key is not usable, we cannot build a per-form-name group key -> skip safely.
				if ( ! is_string( $key ) || $key === '' ) {
					continue;
				}

				$raw_name = (string) $key;
				$raw_name = preg_replace('/\.__group__$/', '', $raw_name);
				$raw_name = trim($raw_name);

				$base = strtolower( $raw_name );
				$base = preg_replace( '/[^a-z0-9_-]+/', '_', $base );
				$base = trim( $base, '_' );

				if ( $base === '' ) {
					continue;
				}

				$days = 1;
				if ( isset( $cfg['cookie_option_days'] ) && is_numeric( $cfg['cookie_option_days'] ) ) {
					$days = max( 1, absint( $cfg['cookie_option_days'] ) );
				}

				$elementor_per_days[ 'group_' . $base ] = $days;
				continue;
			}
			// PRO only: cookie must be explicitly enabled for this form
			if ( empty( $cfg['cookie_option'] ) || (string) $cfg['cookie_option'] !== '1' ) {
				continue;
			}

			// Elementor form_id is a string/hash (not necessarily numeric)
			if ( empty( $cfg['form_id'] ) ) {
				continue;
			}
			$form_id = strtolower( (string) $cfg['form_id'] );
			// Keep cookie suffix safe: [a-z0-9_-]
			$form_id = preg_replace( '/[^a-z0-9_-]+/', '_', $form_id );
			$form_id = trim( $form_id, '_' );

			if ( $form_id === '' ) {
				continue;
			}

			$days = 1;
			if ( isset( $cfg['cookie_option_days'] ) && is_numeric( $cfg['cookie_option_days'] ) ) {
				$days = max( 1, absint( $cfg['cookie_option_days'] ) );
			}

			$elementor_per_days[ $form_id ] = $days;
		}

		if ( ! empty( $elementor_per_days ) ) {
			$elementor_on = true;
		}
	}

	$providers['elementor_forms'] = array(
		'enabled'        => $elementor_on,
		'per_form_days'  => $elementor_per_days, // ONLY source of truth
		'cookie_prefix'  => 'dk_form_cookie_elementor_forms_',
		// Common Elementor Forms markup patterns (prefer forms)
		'selector'       => 'form.elementor-form, .elementor-form form, form[data-elementor-id]',
	);

	// =========================
	// Formidable Forms — (cookie_option must be explicitly enabled)
	// =========================
	$formidable_page = get_option( 'Formidable_page', array() );
	$formidable_page = duplicateKiller_convert_option_architecture( $formidable_page, 'formidable_' );
	$formidable_on       = false;
	$formidable_per_days = array();

	if ( is_array( $formidable_page ) && ! empty( $formidable_page ) ) {

		foreach ( $formidable_page as $cfg ) {

			if ( ! is_array( $cfg ) ) {
				continue;
			}

			// PRO only: cookie must be explicitly enabled for this form
			if ( empty( $cfg['cookie_option'] ) || (string) $cfg['cookie_option'] !== '1' ) {
				continue;
			}

			// Formidable form_id is often a string like "contact-us.2"
			if ( empty( $cfg['form_id'] ) ) {
				continue;
			}

			$raw_id = (string) $cfg['form_id'];

			// Keep cookie suffix safe: [a-z0-9_-]
			$safe_id = strtolower( $raw_id );
			$safe_id = preg_replace( '/[^a-z0-9_-]+/', '_', $safe_id );
			$safe_id = trim( $safe_id, '_' );

			if ( $safe_id === '' ) {
				continue;
			}

			$days = 1;
			if ( isset( $cfg['cookie_option_days'] ) && is_numeric( $cfg['cookie_option_days'] ) ) {
				$days = max( 1, absint( $cfg['cookie_option_days'] ) );
			}

			// Allowlist by safe string id (e.g. "contact-us_2")
			$formidable_per_days[ $safe_id ] = $days;

			// Also allowlist trailing numeric id (e.g. "2") for better DOM matching
			if ( preg_match( '/\.(\d+)$/', $raw_id, $m ) ) {
				$formidable_per_days[ (string) absint( $m[1] ) ] = $days;
			}
		}

		if ( ! empty( $formidable_per_days ) ) {
			$formidable_on = true;
		}
	}

	$providers['formidable'] = array(
		'enabled'        => $formidable_on,
		'per_form_days'  => $formidable_per_days, // ONLY source of truth
		'cookie_prefix'  => 'dk_form_cookie_formidable_',
		'selector'       => 'form.frm-show-form, .frm_forms form, form[id^="frm_form_"], .frm_form_fields',
	);

	// =========================
	// Ninja Forms — (cookie_option must be explicitly enabled)
	// =========================
	$ninja_page = get_option( 'NinjaForms_page', array() );
	$ninja_page = duplicateKiller_convert_option_architecture( $ninja_page, 'ninjaforms_' );
	$ninja_on       = false;
	$ninja_per_days = array();

	if ( is_array( $ninja_page ) && ! empty( $ninja_page ) ) {

		foreach ( $ninja_page as $cfg ) {

			if ( ! is_array( $cfg ) ) {
				continue;
			}

			// PRO only: cookie must be explicitly enabled for this form
			if ( empty( $cfg['cookie_option'] ) || (string) $cfg['cookie_option'] !== '1' ) {
				continue;
			}

			// Ninja form_id is often a string like "contactme.1"
			if ( empty( $cfg['form_id'] ) ) {
				continue;
			}

			$form_id = strtolower( (string) $cfg['form_id'] );
			
			// Keep cookie suffix safe: [a-z0-9_-]
			$form_id = preg_replace( '/[^a-z0-9_-]+/', '_', $form_id );
			$form_id = trim( $form_id, '_' );

			if ( $form_id === '' ) {
				continue;
			}

			$days = 1;
			if ( isset( $cfg['cookie_option_days'] ) && is_numeric( $cfg['cookie_option_days'] ) ) {
				$days = max( 1, absint( $cfg['cookie_option_days'] ) );
			}

			$ninja_per_days[ $form_id ] = $days;

			// Optional: also allowlist trailing numeric id (e.g. "contactme.1" -> "1")
			// This helps if your DOM identifier ends up being numeric.
			if ( preg_match( '/\.(\d+)$/', (string) $cfg['form_id'], $m ) ) {
				$ninja_per_days[ (string) absint( $m[1] ) ] = $days;
			}
		}

		if ( ! empty( $ninja_per_days ) ) {
			$ninja_on = true;
		}
	}

	$providers['ninja_forms'] = array(
		'enabled'        => $ninja_on,
		'per_form_days'  => $ninja_per_days, // ONLY source of truth
		'cookie_prefix'  => 'dk_form_cookie_ninja_forms_',
		// Keep it broad: Ninja often stores the form id on wrappers, not on <form>
		'selector'       => '.nf-form-content, .nf-form-wrap, .nf-form-cont, form[id^="nf-form-"], [data-nf-form-id]',
	);
	/**
	 * Future providers (placeholders):
	 * Coming soon
	 */

	return $providers;
}