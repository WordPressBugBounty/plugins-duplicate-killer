<?php
defined( 'ABSPATH' ) || exit;

						$active_search      = DuplicateKiller_Admin_Submissions_Request::get_search();
						$active_form_plugin = DuplicateKiller_Admin_Submissions_Request::get_form_plugin();
						$active_form_name   = DuplicateKiller_Admin_Submissions_Request::get_form_name();
						?>

						<?php if ( '' !== $active_search || '' !== $active_form_plugin || '' !== $active_form_name ) : ?>
							<div class="dk-db-active-filters">
								<?php
								$filters_base_url = DuplicateKiller_Admin_Submissions_Request::get_base_url();

								$filters_current_url = add_query_arg(
									array_filter(
										array(
											'dk_view'        => $view,
											'dk_form_plugin' => $active_form_plugin,
											'dk_form_name'   => $active_form_name,
											's'              => $active_search,
										)
									),
									$filters_base_url
								);

								$remove_search_url = remove_query_arg( array( 's', 'paged' ), $filters_current_url );

								$remove_form_url = remove_query_arg(
									array( 'dk_form_plugin', 'dk_form_name', 'paged' ),
									$filters_current_url
								);

								$remove_form_name_url = remove_query_arg(
									array( 'dk_form_name', 'paged' ),
									$filters_current_url
								);
								?>
								<span><?php esc_html_e( 'Active filters:', 'duplicate-killer' ); ?></span>

								<?php if ( '' !== $active_form_plugin ) : ?>
									<a href="<?php echo esc_url( $remove_form_url ); ?>">
										<?php echo esc_html( DuplicateKiller_Plugin_Labels::get_label( $active_form_plugin ) ); ?> ×
									</a>
								<?php endif; ?>

								<?php if ( '' !== $active_form_name ) : ?>
									<a href="<?php echo esc_url( $remove_form_name_url ); ?>">
										<?php echo esc_html( $active_form_name ); ?> ×
									</a>
								<?php endif; ?>

								<?php if ( '' !== $active_search ) : ?>
									<a href="<?php echo esc_url( $remove_search_url ); ?>">
										<?php echo esc_html( sprintf( __( 'Search: %s', 'duplicate-killer' ), $active_search ) ); ?> ×
									</a>
								<?php endif; ?>
							</div>
						<?php endif; ?>