<?php
defined( 'ABSPATH' ) || exit;

/**
 * Modern Popup Mode
 *
 * Responsibilities:
 * - Detect whether a configured form uses Modern Popup Mode.
 * - Generate the hidden marker used to identify Duplicate Killer messages.
 * - Append the marker only to Duplicate Killer blocked responses.
 * - Pass the configured frontend popup content to JavaScript.
 */

/**
 * Check whether Modern Popup Mode is enabled for a form config.
 */
function duplicateKiller_modern_popup_is_enabled( array $form_config ): bool {
	$type = isset( $form_config['error_message_type'] ) ? (string) $form_config['error_message_type'] : 'classic';

	return 'modern' === $type;
}

/**
 * Build a deterministic marker for a specific provider/form/message.
 *
 * The marker is not a security token. It is a frontend identifier that helps
 * the JavaScript detect only Duplicate Killer messages.
 */
function duplicateKiller_modern_popup_get_marker(
	string $provider,
	$form_id,
	string $form_name,
	string $plain_message
): string {
	$payload = strtolower( $provider ) . '|' . (string) $form_id . '|' . $form_name . '|' . $plain_message;
	$hash    = hash_hmac( 'sha256', $payload, wp_salt( 'nonce' ) );

	return 'DKM' . strtoupper( substr( $hash, 0, 10 ) );
}

/**
 * Add the Modern Popup marker to a Duplicate Killer blocked message.
 *
 * This must only be called from integrations when Duplicate Killer actually
 * blocks a submission.
 */
function duplicateKiller_modern_popup_maybe_add_marker(
	string $message,
	string $provider,
	array $form_config,
	$form_id,
	string $form_name
): string {
	if ( ! duplicateKiller_modern_popup_is_enabled( $form_config ) ) {
		return $message;
	}

	$marker = duplicateKiller_modern_popup_get_marker( $provider, $form_id, $form_name, $message );

	if ( false !== strpos( $message, $marker ) ) {
		return $message;
	}

	return trim( $message . ' ' . $marker );
}

/**
 * Collect Modern Popup items for the frontend script.
 */
function duplicateKiller_modern_popup_get_frontend_items(): array {
	$providers = array(
		'CF7_page'         => 'cf7',
		'Forminator_page'  => 'forminator',
		'WPForms_page'     => 'wpforms',
		'Breakdance_page'  => 'breakdance',
		'Elementor_page'   => 'elementor',
		'Formidable_page'  => 'formidable',
		'NinjaForms_page'  => 'ninjaforms',
		'FluentForms_page' => 'fluentforms',
	);

	$defaults = duplicateKiller_get_form_defaults();
	$items    = array();

	foreach ( $providers as $option_name => $provider ) {
		$options = get_option( $option_name, array() );

		if ( ! is_array( $options ) ) {
			continue;
		}

		foreach ( $options as $form_name => $form_config ) {
			if ( ! is_array( $form_config ) ) {
				continue;
			}

			if ( ! duplicateKiller_modern_popup_is_enabled( $form_config ) ) {
				continue;
			}

			$form_name     = (string) $form_name;
			$form_id       = isset( $form_config['form_id'] ) ? (string) $form_config['form_id'] : '';
			$plain_message = ! empty( $form_config['error_message'] )
				? (string) $form_config['error_message']
				: (string) $defaults['error_message'];

			$modern_html = ! empty( $form_config['modern_error_message'] )
				? wp_kses_post( (string) $form_config['modern_error_message'] )
				: wp_kses_post( (string) $defaults['modern_error_message'] );

			if ( '' === trim( wp_strip_all_tags( $modern_html ) ) && false === strpos( $modern_html, '<' ) ) {
				continue;
			}

			$items[] = array(
				'provider' => $provider,
				'formId'   => $form_id,
				'formName' => $form_name,
				'plain'    => $plain_message,
				'marker'   => duplicateKiller_modern_popup_get_marker( $provider, $form_id, $form_name, $plain_message ),
				'html'     => $modern_html,
			);
		}
	}

	return $items;
}

add_action( 'wp_enqueue_scripts', 'duplicateKiller_modern_popup_enqueue_assets', 40 );

/**
 * Enqueue Modern Popup assets only when at least one supported form uses it.
 */
function duplicateKiller_modern_popup_enqueue_assets(): void {
	if ( is_admin() ) {
		return;
	}

	$items = duplicateKiller_modern_popup_get_frontend_items();

	if ( empty( $items ) ) {
		return;
	}

	$version = defined( 'DUPLICATEKILLER_VERSION' ) ? DUPLICATEKILLER_VERSION : '1.0.0';

	wp_enqueue_style(
		'duplicatekiller-modern-popup-mode',
		plugins_url( 'assets/dk-modern-popup-mode.css', DUPLICATEKILLER_PLUGIN ),
		array(),
		$version
	);

	wp_enqueue_script(
		'duplicatekiller-modern-popup-mode',
		plugins_url( 'assets/dk-modern-popup-mode.js', DUPLICATEKILLER_PLUGIN ),
		array(),
		$version,
		true
	);

	wp_localize_script(
		'duplicatekiller-modern-popup-mode',
		'DuplicateKillerModernPopup',
		array(
			'items' => $items,
		)
	);
}