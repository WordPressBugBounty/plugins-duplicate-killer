<?php
defined( 'ABSPATH' ) || exit;

class DuplicateKiller_WC_Analytics_Renderer {

	public function render(): void {
		if ( ! class_exists( 'duplicateKiller_WooCommerce' ) || ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$analytics = duplicateKiller_WooCommerce::get_wc_analytics_summary();
		$top       = duplicateKiller_WooCommerce::get_wc_analytics_top( 5 );
		$trend     = duplicateKiller_WooCommerce::get_wc_analytics_trends( 14 );

		$renderer = $this;

		include DUPLICATEKILLER_PLUGIN_DIR . '/admin/database/views/wc-analytics.php';
	}

	public function label_mode( string $mode ): string {
		$mode = sanitize_key( $mode );

		if ( 'shortcode' === $mode ) {
			return 'Classic (Shortcode)';
		}

		if ( 'blocks' === $mode ) {
			return 'Blocks';
		}

		if ( 'custom' === $mode ) {
			return 'Custom';
		}

		if ( 'missing' === $mode ) {
			return 'Missing';
		}

		if ( 'unknown' === $mode ) {
			return 'Unknown';
		}

		return ucfirst( $mode );
	}

	public function gateway_label( string $gateway_id ): string {
		$gateway_id = sanitize_key( $gateway_id );

		if ( '' === $gateway_id ) {
			return '';
		}

		if ( function_exists( 'WC' ) && WC()->payment_gateways() ) {
			$gateways = WC()->payment_gateways()->payment_gateways();

			if ( is_array( $gateways ) && isset( $gateways[ $gateway_id ] ) && is_object( $gateways[ $gateway_id ] ) ) {
				$title = method_exists( $gateways[ $gateway_id ], 'get_title' )
					? (string) $gateways[ $gateway_id ]->get_title()
					: '';

				$title = trim( $title );

				if ( '' !== $title ) {
					return $title;
				}
			}
		}

		return $gateway_id;
	}
}