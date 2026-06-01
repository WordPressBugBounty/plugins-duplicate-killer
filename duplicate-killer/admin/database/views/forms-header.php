<?php
defined( 'ABSPATH' ) || exit;

$clear_filter_url = DuplicateKiller_Admin_Submissions_Request::get_base_url();
$clear_filter_url = add_query_arg( 'dk_view', $view, $clear_filter_url );
$clear_filter_url = remove_query_arg(
	array( 'dk_form_plugin', 'dk_form_name', 'paged' ),
	$clear_filter_url
);
?>

<div class="dk-db-main-header">
	<div>
		<h2><?php esc_html_e( 'Stored submissions', 'duplicate-killer' ); ?></h2>

		<p>
			<?php
			if ( '' !== $form_plugin && '' !== $form_name ) {
				echo esc_html( sprintf( __( 'Showing submissions from %1$s / %2$s.', 'duplicate-killer' ), $form_plugin, $form_name ) );
			} elseif ( '' !== $form_plugin ) {
				echo esc_html( sprintf( __( 'Showing submissions from %s.', 'duplicate-killer' ), $form_plugin ) );
			} else {
				esc_html_e( 'Showing all stored form submissions.', 'duplicate-killer' );
			}
			?>
		</p>
	</div>

	<div class="dk-db-main-header__actions">
		<div class="dk-db-main-header__count">
			<?php
			echo esc_html(
				sprintf(
					_n(
						'%s submission',
						'%s submissions',
						$ListTable->get_total_items_count(),
						'duplicate-killer'
					),
					number_format_i18n( $ListTable->get_total_items_count() )
				)
			);
			?>
		</div>

		<?php if ( '' !== $form_plugin || '' !== $form_name ) : ?>
			<a class="dk-db-clear-filter" href="<?php echo esc_url( $clear_filter_url ); ?>">
				<?php esc_html_e( 'Clear filter', 'duplicate-killer' ); ?>
			</a>
		<?php endif; ?>
	</div>
</div>