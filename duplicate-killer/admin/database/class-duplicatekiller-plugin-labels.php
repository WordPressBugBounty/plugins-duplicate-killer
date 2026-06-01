<?php
defined( 'ABSPATH' ) || exit;

class DuplicateKiller_Plugin_Labels {

	public static function get_label( string $plugin ): string {
		$labels = array(
			'CF7'          => 'Contact Form 7',
			'elementor'    => 'Elementor Pro',
			'breakdance'   => 'Breakdance',
			'Forminator'   => 'Forminator',
			'WPForms'      => 'WPForms',
			'Formidable'   => 'Formidable Forms',
			'NinjaForms'   => 'Ninja Forms',
			'Ninja Forms'  => 'Ninja Forms',
			'WooCommerce'  => 'WooCommerce',
		);

		return $labels[ $plugin ] ?? $plugin;
	}
}