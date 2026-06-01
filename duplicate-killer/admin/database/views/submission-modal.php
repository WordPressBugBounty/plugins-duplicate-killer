<?php
defined( 'ABSPATH' ) || exit;
?>

<div class="dk-db-modal" id="dk-db-submission-modal" hidden>
	<div class="dk-db-modal__overlay" data-dk-db-close></div>

	<div class="dk-db-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="dk-db-modal-title">
		<div class="dk-db-modal__header">
			<div>
				<h2 id="dk-db-modal-title"><?php esc_html_e( 'Submission details', 'duplicate-killer' ); ?></h2>
				<p><?php esc_html_e( 'Full stored values for this submission.', 'duplicate-killer' ); ?></p>
			</div>

			<div class="dk-db-modal__header-actions">
				<button type="button" class="dk-db-modal__copy" id="dk-db-copy-submission">
					<?php esc_html_e( 'Copy all data', 'duplicate-killer' ); ?>
				</button>

				<button
					type="button"
					class="dk-db-modal__close"
					data-dk-db-close
					aria-label="<?php esc_attr_e( 'Close modal', 'duplicate-killer' ); ?>">
					<span></span>
				</button>
			</div>
		</div>

		<div class="dk-db-modal__meta" id="dk-db-modal-meta"></div>
		<div class="dk-db-modal__body" id="dk-db-modal-body"></div>
	</div>
</div>