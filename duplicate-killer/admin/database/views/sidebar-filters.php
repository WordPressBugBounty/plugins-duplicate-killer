<?php
defined( 'ABSPATH' ) || exit;

$repository     = new DuplicateKiller_Submissions_Repository();
$sidebar_groups = $repository->get_sidebar_groups();

$current_plugin = DuplicateKiller_Admin_Submissions_Request::get_form_plugin();
$current_form   = DuplicateKiller_Admin_Submissions_Request::get_form_name();

if ( '' !== $current_plugin && '' !== $current_form ) {
	$form_is_valid = false;

	foreach ( $sidebar_groups as $plugin_key => $group ) {
		if ( $plugin_key !== $current_plugin || empty( $group['forms'] ) ) {
			continue;
		}

		foreach ( $group['forms'] as $form ) {
			if ( isset( $form['name'] ) && (string) $form['name'] === $current_form ) {
				$form_is_valid = true;
				break 2;
			}
		}
	}

	if ( ! $form_is_valid ) {
		$current_form = '';
	}
}

$base_url = DuplicateKiller_Admin_Submissions_Request::get_base_url();
$base_url = add_query_arg( 'dk_view', 'forms', $base_url );

$base_url = remove_query_arg(
	array( 'paged', 'dk_form_plugin', 'dk_form_name' ),
	$base_url
);

?>

<aside class="dk-db-sidebar">
	<div class="dk-db-sidebar__header">
		<h3><?php esc_html_e( 'Forms & Plugins', 'duplicate-killer' ); ?></h3>
	</div>
	<div class="dk-db-sidebar__actions">
		<a class="dk-db-sidebar__all <?php echo ( '' === $current_plugin && '' === $current_form ) ? 'is-active' : ''; ?>" 
		   <?php
			$all_url = remove_query_arg(
				array( 'dk_form_plugin', 'dk_form_name' ),
				$base_url
			);
			?>

			href="<?php echo esc_url( $all_url ); ?>"
		>
			<span><?php esc_html_e( 'All form submissions', 'duplicate-killer' ); ?></span>

			<?php
			$total_all = 0;
			foreach ( $sidebar_groups as $group ) {
				$total_all += isset( $group['total'] ) ? (int) $group['total'] : 0;
			}
			?>

			<strong><?php echo esc_html( number_format_i18n( $total_all ) ); ?></strong>
		</a>
	</div>
	<div class="dk-db-sidebar__search">
		<input type="search" id="dk-db-sidebar-search" placeholder="<?php esc_attr_e( 'Filter forms...', 'duplicate-killer' ); ?>">
	</div>
	<div class="dk-db-sidebar__list">
		<?php foreach ( $sidebar_groups as $plugin => $group ) : ?>
			<div class="dk-db-sidebar__plugin">
				<a class="dk-db-sidebar__plugin-link <?php echo ( $current_plugin === $plugin && '' === $current_form ) ? 'is-active' : ''; ?>"
					<?php
					$plugin_url = add_query_arg(
						array_filter(
							array(
								'dk_form_plugin' => $plugin,
								'dk_form_name'   => false,
								's'              => DuplicateKiller_Admin_Submissions_Request::get_search(),
							)
						),
						$base_url
					);
					?>

					href="<?php echo esc_url( $plugin_url ); ?>"
					>
					<span><?php echo esc_html( DuplicateKiller_Plugin_Labels::get_label( (string) $plugin ) ); ?></span>
					<strong><?php echo esc_html( number_format_i18n( (int) $group['total'] ) ); ?></strong>
				</a>

				<?php if ( ! empty( $group['forms'] ) ) : ?>
					<div class="dk-db-sidebar__forms">
						<?php foreach ( $group['forms'] as $form ) : ?>
							<?php
							$form_name = (string) $form['name'];
							$total     = (int) $form['total'];

							$url = add_query_arg(
								array(
									'dk_form_plugin' => $plugin,
									'dk_form_name'   => $form_name,
								),
								$base_url
							);

							$is_active = ( $current_plugin === $plugin && $current_form === $form_name );
							?>
							<a class="dk-db-sidebar__form <?php echo $is_active ? 'is-active' : ''; ?>"
							   href="<?php echo esc_url( $url ); ?>"
							   title="<?php echo esc_attr( $form_name ); ?>">
								<span><?php echo esc_html( $form_name ); ?></span>
								<em><?php echo esc_html( number_format_i18n( $total ) ); ?></em>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
</aside>