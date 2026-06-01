<?php
defined( 'ABSPATH' ) || exit;
?>

<form method="get" action="" class="dk-db-search-form">
	<input type="hidden" name="page" value="<?php echo esc_attr( DuplicateKiller_Admin_Submissions_Request::get_page_slug() ); ?>">
	<input type="hidden" name="dk_view" value="<?php echo esc_attr( $view ); ?>">
	<?php if ( '' !== DuplicateKiller_Admin_Submissions_Request::get_form_plugin() ) : ?>
		<input type="hidden" name="dk_form_plugin" value="<?php echo esc_attr( DuplicateKiller_Admin_Submissions_Request::get_form_plugin() ); ?>">
		<input type="hidden" name="paged" value="1">
	<?php endif; ?>

	<?php if ( '' !== DuplicateKiller_Admin_Submissions_Request::get_form_name() ) : ?>
		<input type="hidden" name="dk_form_name" value="<?php echo esc_attr( DuplicateKiller_Admin_Submissions_Request::get_form_name() ); ?>">
		<input type="hidden" name="paged" value="1">
	<?php endif; ?>
	<?php if ( '' !== DuplicateKiller_Admin_Submissions_Request::get_tab() ) : ?>
		<input type="hidden" name="tab" value="<?php echo esc_attr( DuplicateKiller_Admin_Submissions_Request::get_tab() ); ?>">
	<?php endif; ?>

	<?php $ListTable->search_box( __( 'Search', 'duplicate-killer' ), 'search' ); ?>
</form>