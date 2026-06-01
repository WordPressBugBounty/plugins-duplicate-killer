<?php
defined( 'ABSPATH' ) || exit;
?>

<div class="dk-db-tabs">
	<a class="dk-db-tab <?php echo ( 'forms' === $view ) ? 'dk-db-tab--active' : ''; ?>"
	   href="<?php echo esc_url( add_query_arg( 'dk_view', 'forms', $base_url ) ); ?>">
		<?php esc_html_e( 'Forms', 'duplicate-killer' ); ?>
	</a>

	<a class="dk-db-tab <?php echo ( 'wc' === $view ) ? 'dk-db-tab--active' : ''; ?>"
	   href="<?php echo esc_url( add_query_arg( 'dk_view', 'wc', $base_url ) ); ?>">
		<?php esc_html_e( 'WooCommerce', 'duplicate-killer' ); ?>
	</a>
</div>