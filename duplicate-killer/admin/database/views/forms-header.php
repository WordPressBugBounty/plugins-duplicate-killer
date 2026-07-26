<?php
defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- View template variables are local to this included admin partial.
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
				printf(
					/* translators: 1: Form plugin name, 2: Form name. */
					esc_html__( 'Showing submissions from %1$s / %2$s.', 'duplicate-killer' ),
					esc_html( $form_plugin ),
					esc_html( $form_name )
				);
			} elseif ( '' !== $form_plugin ) {
				printf(
					/* translators: %s: Form plugin name. */
					esc_html__( 'Showing submissions from %s.', 'duplicate-killer' ),
					esc_html( $form_plugin )
				);
			} else {
				esc_html_e( 'Showing all stored form submissions.', 'duplicate-killer' );
			}
			?>
		</p>
	</div>

	<div class="dk-db-main-header__actions">
		<div class="dk-db-main-header__count">
			<?php
			printf(
				esc_html(
					/* translators: %s: Number of stored submissions. */
					_n(
						'%s submission',
						'%s submissions',
						$ListTable->get_total_items_count(),
						'duplicate-killer'
					)
				),
				esc_html( number_format_i18n( $ListTable->get_total_items_count() ) )
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
<?php // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound ?>