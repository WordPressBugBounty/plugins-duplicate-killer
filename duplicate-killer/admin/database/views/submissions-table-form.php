<?php
defined( 'ABSPATH' ) || exit;
?>

<form method="post" action="" class="dk-db-table-form">
	<input type="hidden" name="page" value="<?php echo esc_attr( DuplicateKiller_Admin_Submissions_Request::get_page_slug() ); ?>">
	<input type="hidden" name="dk_view" value="<?php echo esc_attr( $view ); ?>">

	<?php if ( '' !== $form_plugin ) : ?>
		<input type="hidden" name="dk_form_plugin" value="<?php echo esc_attr( $form_plugin ); ?>">
	<?php endif; ?>

	<?php if ( '' !== $form_name ) : ?>
		<input type="hidden" name="dk_form_name" value="<?php echo esc_attr( $form_name ); ?>">
	<?php endif; ?>

	<?php if ( '' !== DuplicateKiller_Admin_Submissions_Request::get_tab() ) : ?>
		<input type="hidden" name="tab" value="<?php echo esc_attr( DuplicateKiller_Admin_Submissions_Request::get_tab() ); ?>">
	<?php endif; ?>

	<?php if ( '' !== $search ) : ?>
		<input type="hidden" name="s" value="<?php echo esc_attr( $search ); ?>">
	<?php endif; ?>

	<div class="dk-db-table-card">
		<?php $ListTable->display(); ?>
	</div>
</form>