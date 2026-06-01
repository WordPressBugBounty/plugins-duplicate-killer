<?php
if ( class_exists( 'duplicateKiller_WooCommerce' ) && class_exists( 'WooCommerce' ) ) {

	$wc = duplicateKiller_WooCommerce::get_db_notice_summary();

	if ( ! empty( $wc['count'] ) ) :
		?>
		<div class="notice notice-info" style="margin:10px 0 15px;padding:12px 14px;">
			<p style="margin:0;">
				<strong>
					<?php
					printf(
						/* translators: %s = duplicate count */
						esc_html__( '%s WooCommerce duplicate orders have been detected and blocked.', 'duplicate-killer' ),
						number_format_i18n( (int) $wc['count'] )
					);
					?>
				</strong>

				<?php if ( ! empty( $wc['last_date'] ) ) : ?>
					<br>
					<?php
					printf(
						/* translators: %s = last duplicate date */
						esc_html__( 'Last duplicate detected: %s', 'duplicate-killer' ),
						esc_html( (string) $wc['last_date'] )
					);
					?>
				<?php endif; ?>
			</p>
		</div>
		<?php
	endif;
}
?>

<div class="postbox" style="padding:15px;margin:15px 0;">
						<div class="postbox-header">
							<div class="duplicateKiller_wcAnalytics__topbar">
								<h2 class="hndle" style="margin:0;"><?php esc_html_e( 'WooCommerce Duplicate Analytics', 'duplicate-killer' ); ?></h2>
								<a class="button button-secondary" href="#">
									<?php esc_html_e( 'Export CSV', 'duplicate-killer' ); ?>
								</a>
							</div>
						</div>

						<div class="inside">
							<div class="duplicateKiller_wcAnalytics">
							<div class="duplicateKiller_wcAnalytics__locked">

								<div class="duplicateKiller_wcAnalytics__gridTop">

									<div class="duplicateKiller_wcAnalytics__trend">
										<p class="duplicateKiller_wcAnalytics__title"><?php esc_html_e( 'Trend (last 14 days)', 'duplicate-killer' ); ?></p>

										<?php if ( empty( $trend ) ) : ?>
											<p style="margin:0;" class="duplicateKiller_wcAnalytics__muted"><?php esc_html_e( 'Not enough data yet.', 'duplicate-killer' ); ?></p>
										<?php else : ?>
											<?php
												// Compute max for normalization (avoid division by zero).
												$duplicateKiller_trend_max = 0;
												foreach ( (array) $trend as $duplicateKiller_day => $duplicateKiller_count ) {
													$duplicateKiller_trend_max = max( $duplicateKiller_trend_max, (int) $duplicateKiller_count );
												}
												if ( $duplicateKiller_trend_max <= 0 ) {
													$duplicateKiller_trend_max = 1;
												}
											?>

											<div>
												<?php foreach ( $trend as $duplicateKiller_day => $duplicateKiller_count ) :
													$duplicateKiller_count = (int) $duplicateKiller_count;
													$duplicateKiller_pct   = (int) round( ( $duplicateKiller_count / $duplicateKiller_trend_max ) * 100 );
													$duplicateKiller_pct   = max( 0, min( 100, $duplicateKiller_pct ) );
												?>
													<div class="duplicateKiller_wcAnalytics__trendRow">
														<div class="duplicateKiller_wcAnalytics__trendLeft">
															<?php echo esc_html( $duplicateKiller_day ); ?>
														</div>

														<div class="duplicateKiller_wcAnalytics__trendRight">
															<div class="duplicateKiller_wcAnalytics__trendBarWrap" aria-hidden="true">
																<div class="duplicateKiller_wcAnalytics__trendBar" style="width: <?php echo esc_attr( (string) $duplicateKiller_pct ); ?>%;"></div>
															</div>

															<div class="duplicateKiller_wcAnalytics__trendCount duplicateKiller_wcAnalytics__muted">
																(<?php echo esc_html( number_format_i18n( $duplicateKiller_count ) ); ?>)
															</div>
														</div>
													</div>
												<?php endforeach; ?>
											</div>
										<?php endif; ?>
									</div>

									<div class="duplicateKiller_wcAnalytics__kpis">
										<div class="duplicateKiller_wcAnalytics__kpi">
											<div class="duplicateKiller_wcAnalytics__kpiNum"><?php echo esc_html( number_format_i18n( (int) $analytics['total'] ) ); ?></div>
											<div class="duplicateKiller_wcAnalytics__kpiLbl"><?php esc_html_e( 'Total duplicates logged', 'duplicate-killer' ); ?></div>
										</div>

										<div class="duplicateKiller_wcAnalytics__kpi">
											<div class="duplicateKiller_wcAnalytics__kpiNum"><?php echo esc_html( number_format_i18n( (int) $analytics['last_24h'] ) ); ?></div>
											<div class="duplicateKiller_wcAnalytics__kpiLbl"><?php esc_html_e( 'Last 24 hours', 'duplicate-killer' ); ?></div>
										</div>

										<div class="duplicateKiller_wcAnalytics__kpi">
											<div class="duplicateKiller_wcAnalytics__kpiNum"><?php echo esc_html( number_format_i18n( (int) $analytics['last_7d'] ) ); ?></div>
											<div class="duplicateKiller_wcAnalytics__kpiLbl"><?php esc_html_e( 'Last 7 days', 'duplicate-killer' ); ?></div>
										</div>
									</div>

								</div>

								<div class="duplicateKiller_wcAnalytics__kpis2">
									<div class="duplicateKiller_wcAnalytics__kpi">
										<div class="duplicateKiller_wcAnalytics__kpiNum"><?php echo esc_html( number_format_i18n( (int) ( $top['unique_fingerprints'] ?? 0 ) ) ); ?></div>
										<div class="duplicateKiller_wcAnalytics__kpiLbl"><?php esc_html_e( 'Unique fingerprints (sample)', 'duplicate-killer' ); ?></div>
									</div>

									<div class="duplicateKiller_wcAnalytics__kpi">
										<div class="duplicateKiller_wcAnalytics__kpiNum"><?php echo esc_html( number_format_i18n( (int) ( $top['orders_created'] ?? 0 ) ) ); ?></div>
										<div class="duplicateKiller_wcAnalytics__kpiLbl"><?php esc_html_e( 'Orders created (sample)', 'duplicate-killer' ); ?></div>
									</div>

									<div class="duplicateKiller_wcAnalytics__kpi">
										<div class="duplicateKiller_wcAnalytics__kpiNum"><?php echo esc_html( number_format_i18n( (int) ( $top['scanned_rows'] ?? 0 ) ) ); ?></div>
										<div class="duplicateKiller_wcAnalytics__kpiLbl"><?php esc_html_e( 'Rows scanned', 'duplicate-killer' ); ?></div>
									</div>
								</div>

								<div class="duplicateKiller_wcAnalytics__cards">

									<div class="duplicateKiller_wcAnalytics__card">
										<p class="duplicateKiller_wcAnalytics__title"><?php esc_html_e( 'Top affected products', 'duplicate-killer' ); ?></p>

										<?php if ( empty( $top['top_products'] ) ) : ?>
											<p style="margin:0;" class="duplicateKiller_wcAnalytics__muted"><?php esc_html_e( 'Not enough data yet.', 'duplicate-killer' ); ?></p>
										<?php else : ?>
											<ul>
												<?php foreach ( $top['top_products'] as $pid => $cnt ) :
													$title = get_the_title( (int) $pid );
													$title = $title ? $title : ( '#' . (int) $pid );
													$link  = admin_url( 'post.php?post=' . (int) $pid . '&action=edit' );
												?>
													<li>
														<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $title ); ?></a>
														<span class="duplicateKiller_wcAnalytics__muted">(<?php echo esc_html( number_format_i18n( (int) $cnt ) ); ?>)</span>
													</li>
												<?php endforeach; ?>
											</ul>
										<?php endif; ?>
									</div>

									<div class="duplicateKiller_wcAnalytics__card">
										<p class="duplicateKiller_wcAnalytics__title"><?php esc_html_e( 'Top email domains', 'duplicate-killer' ); ?></p>

										<?php if ( empty( $top['top_domains'] ) ) : ?>
											<p style="margin:0;" class="duplicateKiller_wcAnalytics__muted"><?php esc_html_e( 'Not enough data yet.', 'duplicate-killer' ); ?></p>
										<?php else : ?>
											<ul>
												<?php foreach ( $top['top_domains'] as $domain => $cnt ) : ?>
													<li>
														<?php echo esc_html( $domain ); ?>
														<span class="duplicateKiller_wcAnalytics__muted">(<?php echo esc_html( number_format_i18n( (int) $cnt ) ); ?>)</span>
													</li>
												<?php endforeach; ?>
											</ul>
										<?php endif; ?>
									</div>

									<div class="duplicateKiller_wcAnalytics__card">
										<p class="duplicateKiller_wcAnalytics__title"><?php esc_html_e( 'Top payment methods', 'duplicate-killer' ); ?></p>

										<?php if ( empty( $top['top_payments'] ) ) : ?>
											<p style="margin:0;" class="duplicateKiller_wcAnalytics__muted"><?php esc_html_e( 'Not enough data yet.', 'duplicate-killer' ); ?></p>
										<?php else : ?>
											<ul>
												<?php foreach ( $top['top_payments'] as $pm => $cnt ) :
													$pm_id    = is_string( $pm ) ? $pm : (string) $pm;
													$pm_label = $renderer->gateway_label( $pm_id );
													if ( $pm_label === '' ) {
														$pm_label = $pm_id;
													}
												?>
													<li>
														<?php echo esc_html( $pm_label ); ?>
														<span class="duplicateKiller_wcAnalytics__muted">(<?php echo esc_html( number_format_i18n( (int) $cnt ) ); ?>)</span>
													</li>
												<?php endforeach; ?>
											</ul>
										<?php endif; ?>
									</div>

									<div class="duplicateKiller_wcAnalytics__card">
										<p class="duplicateKiller_wcAnalytics__title"><?php esc_html_e( 'Top checkout types', 'duplicate-killer' ); ?></p>

										<?php if ( empty( $top['top_modes'] ) ) : ?>
											<p style="margin:0;" class="duplicateKiller_wcAnalytics__muted"><?php esc_html_e( 'Not enough data yet.', 'duplicate-killer' ); ?></p>
										<?php else : ?>
											<ul>
												<?php foreach ( $top['top_modes'] as $mode => $cnt ) : ?>
													<li>
														<?php echo esc_html( $renderer->label_mode( (string) $mode ) ); ?>
														<span class="duplicateKiller_wcAnalytics__muted">(<?php echo esc_html( number_format_i18n( (int) $cnt ) ); ?>)</span>
													</li>
												<?php endforeach; ?>
											</ul>
										<?php endif; ?>
									</div>

									<div class="duplicateKiller_wcAnalytics__card">
										<p class="duplicateKiller_wcAnalytics__title"><?php esc_html_e( 'Top IP addresses', 'duplicate-killer' ); ?></p>

										<?php if ( empty( $top['top_ips'] ) ) : ?>
											<p style="margin:0;" class="duplicateKiller_wcAnalytics__muted"><?php esc_html_e( 'Not enough data yet.', 'duplicate-killer' ); ?></p>
										<?php else : ?>
											<ul>
												<?php foreach ( $top['top_ips'] as $ip => $cnt ) : ?>
													<li>
														<?php echo esc_html( $ip ); ?>
														<span class="duplicateKiller_wcAnalytics__muted">(<?php echo esc_html( number_format_i18n( (int) $cnt ) ); ?>)</span>
													</li>
												<?php endforeach; ?>
											</ul>
										<?php endif; ?>
									</div>

								</div>
							
								<?php
								$upgrade_url = 'https://verselabwp.com/duplicate-killer/';
								?>
								<div class="duplicateKiller_wcAnalytics__overlay">
									<div class="duplicateKiller_wcAnalytics__overlayBox">

										<div class="duplicateKiller_wcAnalytics__overlayTitle">
											<?php esc_html_e( 'PRO Feature', 'duplicate-killer' ); ?>
										</div>

										<div class="duplicateKiller_wcAnalytics__overlayText">
											<?php esc_html_e(
												'Analytics, trends, breakdowns and CSV export are available in Duplicate Killer PRO.',
												'duplicate-killer'
											); ?>
										</div>

										<a
											class="button button-primary"
											href="<?php echo esc_url( $upgrade_url ); ?>"
										>
											<?php esc_html_e( 'Upgrade to PRO', 'duplicate-killer' ); ?>
										</a>

									</div>
								</div>

							</div><!-- .duplicateKiller_wcAnalytics__locked -->
							</div>
						</div>
					</div>