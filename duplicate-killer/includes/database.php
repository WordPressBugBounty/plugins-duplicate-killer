<?php
defined( 'ABSPATH' ) or die( 'You shall not pass!' );

require_once DUPLICATEKILLER_PLUGIN_DIR . '/admin/database/class-duplicatekiller-admin-submissions-request.php';
require_once DUPLICATEKILLER_PLUGIN_DIR . '/admin/database/class-duplicatekiller-submissions-repository.php';
require_once DUPLICATEKILLER_PLUGIN_DIR . '/admin/database/class-duplicatekiller-submission-value-renderer.php';
require_once DUPLICATEKILLER_PLUGIN_DIR . '/admin/database/class-duplicatekiller-wc-analytics-renderer.php';
require_once DUPLICATEKILLER_PLUGIN_DIR . '/admin/database/class-duplicatekiller-plugin-labels.php';

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

require_once DUPLICATEKILLER_PLUGIN_DIR . '/admin/database/class-duplicatekiller-database-main-list-table.php';

function duplicateKiller_database_display_page(){
	return new duplicateKiller_DisplayDatabase();
}

class duplicateKiller_DisplayDatabase{
    public function __construct(){
        $this->list_table_page();
    }
    public function list_table_page() {
		$ListTable = new duplicateKiller_DatabaseMainListTable();
		$ListTable->prepare_items();

		$form_plugin = DuplicateKiller_Admin_Submissions_Request::get_form_plugin();
		$form_name   = DuplicateKiller_Admin_Submissions_Request::get_form_name();
		$search      = DuplicateKiller_Admin_Submissions_Request::get_search();
		$view        = DuplicateKiller_Admin_Submissions_Request::get_view();
		$base_url    = DuplicateKiller_Admin_Submissions_Request::get_base_url();
		?>

		<div class="wrap dk-db-page">
			<?php include DUPLICATEKILLER_PLUGIN_DIR . '/admin/database/views/page-header.php'; ?>

			<?php include DUPLICATEKILLER_PLUGIN_DIR . '/admin/database/views/tabs.php'; ?>

			<div class="dk-db-layout <?php echo ( 'wc' === $view ) ? 'dk-db-layout--full' : ''; ?>">
				<?php if ( 'forms' === $view ) : ?>
					<?php include DUPLICATEKILLER_PLUGIN_DIR . '/admin/database/views/sidebar-filters.php'; ?>
				<?php endif; ?>

				<main class="dk-db-main">
					<?php if ( 'wc' === $view ) : ?>
						<?php
						$wc_analytics_renderer = new DuplicateKiller_WC_Analytics_Renderer();
						$wc_analytics_renderer->render();
						?>
					<?php endif; ?>

					<?php if ( 'forms' === $view ) : ?>
						<?php include DUPLICATEKILLER_PLUGIN_DIR . '/admin/database/views/forms-header.php'; ?>
						<?php include DUPLICATEKILLER_PLUGIN_DIR . '/admin/database/views/active-filters.php'; ?>
						<?php include DUPLICATEKILLER_PLUGIN_DIR . '/admin/database/views/search-form.php'; ?>
						<?php include DUPLICATEKILLER_PLUGIN_DIR . '/admin/database/views/submissions-table-form.php'; ?>
					<?php endif; ?>
				</main>
			</div>

			<?php include DUPLICATEKILLER_PLUGIN_DIR . '/admin/database/views/submission-modal.php'; ?>
		</div>
		<?php
	}

}