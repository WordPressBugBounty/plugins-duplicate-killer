<?php
defined( 'ABSPATH' ) || exit;

class DuplicateKiller_Admin_Submissions_Request {

	public static function get_search(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Search query for UI filtering only.
		return isset( $_REQUEST['s'] )
			? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) )
			: '';
	}
	public static function get_form_plugin(): string {
		return isset( $_REQUEST['dk_form_plugin'] )
			? sanitize_text_field( wp_unslash( $_REQUEST['dk_form_plugin'] ) )
			: '';
	}

	public static function get_form_name(): string {
		$form_plugin = self::get_form_plugin();

		if ( '' === $form_plugin ) {
			return '';
		}

		return isset( $_REQUEST['dk_form_name'] )
			? sanitize_text_field( wp_unslash( $_REQUEST['dk_form_name'] ) )
			: '';
	}
	public static function get_view(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- View filter for UI tabs only.
		$view = isset( $_REQUEST['dk_view'] )
			? sanitize_key( wp_unslash( $_REQUEST['dk_view'] ) )
			: 'forms';

		return in_array( $view, array( 'forms', 'wc' ), true ) ? $view : 'forms';
	}

	public static function get_page_slug(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page slug used for URLs only.
		return isset( $_REQUEST['page'] )
			? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) )
			: '';
	}

	public static function get_tab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin tab used for URLs only.
		return isset( $_REQUEST['tab'] )
			? sanitize_key( wp_unslash( $_REQUEST['tab'] ) )
			: '';
	}

	public static function get_base_url(): string {
		$page_slug = self::get_page_slug();

		$base_url = admin_url( 'admin.php?page=' . rawurlencode( $page_slug ) );

		$tab = self::get_tab();
		if ( '' !== $tab ) {
			$base_url = add_query_arg( 'tab', $tab, $base_url );
		}

		return $base_url;
	}
}