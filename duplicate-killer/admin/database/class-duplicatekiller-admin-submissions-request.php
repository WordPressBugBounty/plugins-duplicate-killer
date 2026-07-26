<?php
defined( 'ABSPATH' ) || exit;

class DuplicateKiller_Admin_Submissions_Request {

	public static function get_search(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin search filter.
		$search = isset( $_REQUEST['s'] )
			? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return $search;
	}

	public static function get_form_plugin(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin plugin filter.
		$form_plugin = isset( $_REQUEST['dk_form_plugin'] )
			? sanitize_text_field( wp_unslash( $_REQUEST['dk_form_plugin'] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return $form_plugin;
	}

	public static function get_form_name(): string {
		$form_plugin = self::get_form_plugin();

		if ( '' === $form_plugin ) {
			return '';
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin form-name filter.
		$form_name = isset( $_REQUEST['dk_form_name'] )
			? sanitize_text_field( wp_unslash( $_REQUEST['dk_form_name'] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return $form_name;
	}

	public static function get_view(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin view filter.
		$view = isset( $_REQUEST['dk_view'] )
			? sanitize_key( wp_unslash( $_REQUEST['dk_view'] ) )
			: 'forms';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return in_array( $view, array( 'forms', 'wc' ), true ) ? $view : 'forms';
	}

	public static function get_page_slug(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin page slug used for URLs.
		$page_slug = isset( $_REQUEST['page'] )
			? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return $page_slug;
	}

	public static function get_tab(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin tab used for URLs.
		$tab = isset( $_REQUEST['tab'] )
			? sanitize_key( wp_unslash( $_REQUEST['tab'] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return $tab;
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