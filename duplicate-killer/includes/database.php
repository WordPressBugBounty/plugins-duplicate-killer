<?php
defined( 'ABSPATH' ) or die( 'You shall not pass!' );

function duplicateKiller_database_display_page(){
	return new duplicateKiller_DisplayDatabase();
}

class duplicateKiller_DisplayDatabase{
    public function __construct(){
        $this->list_table_page();
    }
    public function list_table_page(){
		$ListTable = new duplicateKiller_DatabaseMainListTable();
		$ListTable->prepare_items();
		
		$upgrade_url = add_query_arg(
			array(
				'page' => 'duplicateKiller',
				'tab'  => 'pro',
			),
			admin_url( 'admin.php' )
		);

		$upgrade_url = esc_url( $upgrade_url );
		$total_blocked = (int) get_option( 'duplicateKiller_duplicates_blocked_count', 0 );
		?>
			<div class="wrap">
				<div id="icon-users" class="icon32"></div>
				<h2>Duplicate Killer Database</h2>

				<?php if ( $total_blocked > 0 ) : ?>
					<div class="notice notice-info" style="margin: 10px 0 15px; padding: 10px 12px;">
						<p style="margin:0;">
							<strong><?php esc_html_e( 'Total forms duplicates blocked:', 'duplicate-killer' ); ?></strong>
							<?php echo esc_html( number_format_i18n( $total_blocked ) ); ?>
						</p>
					</div>
				<?php endif;
				if ( class_exists( 'duplicateKiller_WooCommerce' ) && class_exists( 'WooCommerce' ) ) {
					$wc = duplicateKiller_WooCommerce::get_db_notice_summary();

					if ( ! empty( $wc['count'] ) ) : ?>
						<div class="notice notice-info" style="margin: 10px 0 15px; padding: 10px 12px;">
							<p style="margin:0;">
								<strong><?php esc_html_e( 'WooCommerce duplicates logged:', 'duplicate-killer' ); ?></strong>
								<?php echo esc_html( number_format_i18n( (int) $wc['count'] ) ); ?>

								<?php if ( ! empty( $wc['last_date'] ) ) : ?>
									<br>
									<strong><?php esc_html_e( 'Last WooCommerce duplicate:', 'duplicate-killer' ); ?></strong>
									<?php echo esc_html( (string) $wc['last_date'] ); ?>
								<?php endif; ?>
							</p>
						</div>
						<div class="duplicateKiller_analytics_wrap duplicateKiller_analytics_wrap--locked">

						  <!-- Header -->
						  <div class="duplicateKiller_analytics_header">
							<div class="duplicateKiller_analytics_title">WooCommerce Duplicate Analytics</div>
							<button class="duplicateKiller_btn duplicateKiller_btn--secondary" type="button" disabled>
							  Export CSV
							</button>
						  </div>

						  <div class="duplicateKiller_analytics_grid">

							<!-- Trend -->
							<div class="duplicateKiller_card duplicateKiller_card--trend duplicateKiller_blur">
							  <div class="duplicateKiller_card_title">Trend (last 14 days)</div>

							  <div class="duplicateKiller_trend_row">
								<div class="duplicateKiller_trend_date">2026-02-26</div>
								<div class="duplicateKiller_trend_bar"><div class="duplicateKiller_trend_fill" style="width: 28%;"></div></div>
								<div class="duplicateKiller_trend_count">(3)</div>
							  </div>

							  <div class="duplicateKiller_trend_row">
								<div class="duplicateKiller_trend_date">2026-02-28</div>
								<div class="duplicateKiller_trend_bar"><div class="duplicateKiller_trend_fill" style="width: 55%;"></div></div>
								<div class="duplicateKiller_trend_count">(9)</div>
							  </div>

							  <div class="duplicateKiller_trend_row">
								<div class="duplicateKiller_trend_date">2026-03-01</div>
								<div class="duplicateKiller_trend_bar"><div class="duplicateKiller_trend_fill" style="width: 95%;"></div></div>
								<div class="duplicateKiller_trend_count">(28)</div>
							  </div>

							  <div class="duplicateKiller_trend_row">
								<div class="duplicateKiller_trend_date">2026-03-02</div>
								<div class="duplicateKiller_trend_bar"><div class="duplicateKiller_trend_fill" style="width: 40%;"></div></div>
								<div class="duplicateKiller_trend_count">(9)</div>
							  </div>
							</div>

							<!-- Top KPI cards -->
							<div class="duplicateKiller_card duplicateKiller_card--kpi duplicateKiller_blur">
							  <div class="duplicateKiller_kpi_value">49</div>
							  <div class="duplicateKiller_kpi_label">Total duplicates logged</div>
							</div>

							<div class="duplicateKiller_card duplicateKiller_card--kpi duplicateKiller_blur">
							  <div class="duplicateKiller_kpi_value">37</div>
							  <div class="duplicateKiller_kpi_label">Last 24 hours</div>
							</div>

							<div class="duplicateKiller_card duplicateKiller_card--kpi duplicateKiller_blur">
							  <div class="duplicateKiller_kpi_value">49</div>
							  <div class="duplicateKiller_kpi_label">Last 7 days</div>
							</div>

							<!-- Second row KPI cards -->
							<div class="duplicateKiller_card duplicateKiller_card--kpi duplicateKiller_blur">
							  <div class="duplicateKiller_kpi_value">4</div>
							  <div class="duplicateKiller_kpi_label">Unique fingerprints (sample)</div>
							</div>

							<div class="duplicateKiller_card duplicateKiller_card--kpi duplicateKiller_blur">
							  <div class="duplicateKiller_kpi_value">32</div>
							  <div class="duplicateKiller_kpi_label">Orders created (sample)</div>
							</div>

							<div class="duplicateKiller_card duplicateKiller_card--kpi duplicateKiller_blur">
							  <div class="duplicateKiller_kpi_value">800</div>
							  <div class="duplicateKiller_kpi_label">Rows scanned</div>
							</div>

							<!-- Bottom cards -->
							<div class="duplicateKiller_card duplicateKiller_card--list duplicateKiller_blur">
							  <div class="duplicateKiller_card_title">Top affected products</div>
							  <ul class="duplicateKiller_list">
								<li><a href="#" onclick="return false;">Produsul unu</a> <span class="duplicateKiller_muted">(49)</span></li>
								<li><a href="#" onclick="return false;">Produsul nou 2</a> <span class="duplicateKiller_muted">(7)</span></li>
							  </ul>
							</div>

							<div class="duplicateKiller_card duplicateKiller_card--list duplicateKiller_blur">
							  <div class="duplicateKiller_card_title">Top email domains</div>
							  <ul class="duplicateKiller_list">
								<li>protonmail.com <span class="duplicateKiller_muted">(37)</span></li>
								<li>yahoo.com <span class="duplicateKiller_muted">(12)</span></li>
							  </ul>
							</div>

							<div class="duplicateKiller_card duplicateKiller_card--list duplicateKiller_blur">
							  <div class="duplicateKiller_card_title">Top payment methods</div>
							  <ul class="duplicateKiller_list">
								<li>Cash on delivery <span class="duplicateKiller_muted">(9)</span></li>
							  </ul>
							</div>

							<div class="duplicateKiller_card duplicateKiller_card--list duplicateKiller_blur">
							  <div class="duplicateKiller_card_title">Top checkout types</div>
							  <ul class="duplicateKiller_list">
								<li>Blocks <span class="duplicateKiller_muted">(37)</span></li>
							  </ul>
							</div>

							<div class="duplicateKiller_card duplicateKiller_card--list duplicateKiller_blur">
							  <div class="duplicateKiller_card_title">Top IP addresses</div>
							  <ul class="duplicateKiller_list">
								<li>::1 <span class="duplicateKiller_muted">(37)</span></li>
							  </ul>
							</div>

						  </div>

						  <!-- Overlay lock -->
						  <div class="duplicateKiller_analytics_lock" aria-hidden="false">
							<div class="duplicateKiller_analytics_lock_box">
							  <div class="duplicateKiller_analytics_lock_title">PRO Feature</div>
							  <div class="duplicateKiller_analytics_lock_text">
								Analytics, trends, breakdowns and export are available in Duplicate Killer PRO.
							  </div>
							  <a
								class="duplicateKiller_btn duplicateKiller_btn--primary"
								href="<?php echo esc_url( $upgrade_url ); ?>"
							  >
								Upgrade to PRO
							  </a>
							</div>
						  </div>

						</div>
					<?php endif;
				}

				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading search query for UI filtering (non-destructive).
				$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
				?>

				<!-- SEARCH: GET (ca in WP core, ca sa ramana s in URL si la paginare) -->
				<form method="get" action="">
					<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- UI navigation param only. ?>
					<input type="hidden" name="page" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_REQUEST['page'] ?? '' ) ) ); ?>">
					<?php if ( isset( $_REQUEST['tab'] ) ) : ?>
						<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- UI navigation param only. ?>
						<input type="hidden" name="tab" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_REQUEST['tab'] ) ) ); ?>">
					<?php endif; ?>

					<?php $ListTable->search_box( __( 'Search', 'duplicate-killer' ), 'search' ); ?>
				</form>

				<!-- TABLE + BULK: POST (delete ramane POST + nonce) -->
				<form method="post" action="">
					<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- UI navigation param only. ?>
					<input type="hidden" name="page" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_REQUEST['page'] ?? '' ) ) ); ?>">
					<?php if ( isset( $_REQUEST['tab'] ) ) : ?>
						<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- UI navigation param only. ?>
						<input type="hidden" name="tab" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_REQUEST['tab'] ) ) ); ?>">
					<?php endif; ?>

					<?php if ( '' !== $search ) : ?>
						<input type="hidden" name="s" value="<?php echo esc_attr( $search ); ?>">
					<?php endif; ?>

					<?php $ListTable->display(); ?>
				</form>
				
			</div>
		<?php
	}

}

if( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class duplicateKiller_DatabaseMainListTable extends WP_List_Table{
	public function __construct() {
        parent::__construct(
            array(
                'singular' => 'dk_contact_form',
                'plural'   => 'dk_contact_forms',
                'ajax'     => false
            )
        );
    }

    public function prepare_items() {

		$search = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading search query for UI filtering (non-destructive).
		if ( isset( $_REQUEST['s'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading search query for UI filtering (non-destructive).
			$search = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );
		}

		global $wpdb;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified inside process_bulk_action().
		$this->process_bulk_action();

		$dkdb       = apply_filters( 'duplicate_killer_database', $wpdb );
		$table_name = $dkdb->prefix . 'dk_forms_duplicate';

		$columns     = $this->get_columns();
		$hidden      = $this->get_hidden_columns();
		$data        = $this->table_data($search ); // dacă folosești $search, pasează-l: table_data( $search )
		$perPage     = 20;
		$currentPage = $this->get_pagenum();
		$totalItems  = $this->countDKFormsDB( $search );

		$this->set_pagination_args(
			array(
				'total_items' => $totalItems,
				'per_page'    => $perPage,
			)
		);

		$this->_column_headers = array( $columns, $hidden );
		$this->items           = $data;
	}
    
    //Override the parent columns method. Defines the columns to use in your listing table
    public function get_columns(){

        $columns = array(
			'cb' => '<input type="checkbox" />',
			'form_plugin'=> __( 'Plugin', 'duplicate-killer' ),
            'form_name' => __( 'Name', 'duplicate-killer' ),
            'form_value'=> __( 'Value', 'duplicate-killer' ),
			'form_date'=> __( 'Date', 'duplicate-killer' ),
			'form_ip'=> __( 'IP', 'duplicate-killer' )
        );

        return $columns;
    }

    //Define which columns are hidden
    public function get_hidden_columns(){
        return array('form_id');
    }
	
	//Define check box for bulk action (each row)
    public function column_cb($item){
        return sprintf(
             '<input type="checkbox" name="%1$s[]" value="%2$s" />',
             $this->_args['singular'],
             $item['form_id']
        );
    }
	public function get_bulk_actions(){
        return array(
            'delete' => __( 'Delete', 'duplicate-killer' )
        );
    }
	
	private function table_data( string $search = '' ) {
		global $wpdb;
		$data = array();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading search query for UI filtering (non-destructive).
		if ( '' === $search && isset( $_REQUEST['s'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading search query for UI filtering (non-destructive).
			$search = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );
		}
		
		$table_name = $wpdb->prefix . 'dk_forms_duplicate';
		$per_page = 20;
		$page     = max( 1, (int) $this->get_pagenum() );
		$offset   = ( $page - 1 ) * $per_page;

		if ( '' !== $search ) {
			$like   = '%' . $wpdb->esc_like( $search ) . '%';
			
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$result = $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT * FROM {$table_name}  WHERE form_value  LIKE %s OR form_plugin LIKE %s OR form_name   LIKE %s ORDER BY form_id DESC LIMIT %d OFFSET %d",
			$like, $like, $like, $per_page, $offset
				),
				OBJECT
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$result = $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT * FROM {$table_name} ORDER BY form_id DESC LIMIT %d OFFSET %d", $per_page, $offset
				),
				OBJECT
			);
		}

		// Cache options once (cheap + clean).
		$formidable_page = get_option( 'Formidable_page', array() );
		if ( ! is_array( $formidable_page ) ) {
			$formidable_page = array();
		}

		$ninjaforms_page = get_option( 'NinjaForms_page', array() );
		if ( ! is_array( $ninjaforms_page ) ) {
			$ninjaforms_page = array();
		}

		$allowed_html = array(
			'div'    => array( 'class' => true ),
			'p'      => array(),
			'br'     => array(),
			'strong' => array(),
			'small'  => array( 'style' => true ),
			'em'     => array(),
			'a'      => array(
				'href'   => true,
				'target' => true,
				'rel'    => true,
			),
			'img'    => array(
				'src'   => true,
				'alt'   => true,
				'style' => true,
			),
		);

		$allowed_protocols = array_merge( wp_allowed_protocols(), array( 'data' ) );

		foreach ( (array) $result as $row ) {

			// Reset per row (prevents leaking values across iterations).
			$data_value = array();
			$store      = '';

			$data_value['form_id']     = absint( $row->form_id );
			$data_value['form_plugin'] = sanitize_text_field( (string) $row->form_plugin );
			$data_value['form_name']   = sanitize_text_field( (string) $row->form_name );

			$form_value = maybe_unserialize( $row->form_value );

			if ( 'CF7' === $data_value['form_plugin'] ) {
				$store = $this->organize_array_cf7( $form_value );

			} elseif ( 'Forminator' === $data_value['form_plugin'] ) {
				$store = $this->organize_array_forminator( $form_value );

			} elseif ( 'WPForms' === $data_value['form_plugin'] ) {
				$store = $this->organize_array_wpforms( $form_value );

			} elseif ( 'breakdance' === $data_value['form_plugin'] ) {
				$store = $this->duplicateKiller_organize_array_breakdance( $form_value );

			} elseif ( 'Formidable' === $data_value['form_plugin'] ) {
				$store = $this->organize_array_formidable( $form_value, $data_value['form_name'], $formidable_page );

			} elseif ( 'NinjaForms' === $data_value['form_plugin'] ) {
				$store = $this->organize_array_ninjaforms( $form_value, $data_value['form_name'], $ninjaforms_page );

			} elseif ( 'elementor' === $data_value['form_plugin'] ) {
				$store = $this->organize_array_cf7( $form_value );
			
			} elseif ( 'WooCommerce' === $data_value['form_plugin'] ) {
				$store = $this->duplicateKiller_organize_array_woocommerce( $form_value );
				
			}else {
				// Fallback for unknown plugins.
				$store = is_string( $form_value )
					? $form_value
					: wp_json_encode( $form_value, JSON_PRETTY_PRINT );
			}

			$data_value['form_value'] = wp_kses( (string) $store, $allowed_html, $allowed_protocols );
			$data_value['form_date']  = sanitize_text_field( (string) $row->form_date );
			$data_value['form_ip']    = sanitize_text_field( (string) $row->form_ip );

			$data[] = $data_value;
		}

		return $data;
	}
	
	/**
	 * WooCommerce (FREE): show a simple readable summary (fingerprint/email/total),
	 * plus a blurred PRO teaser.
	 *
	 * @param mixed $form_value Unserialized form_value payload
	 * @return string HTML (sanitized later by wp_kses in table_data()).
	 */
	private function duplicateKiller_organize_array_woocommerce( $form_value ): string {

		$form_value = maybe_unserialize( $form_value );

		if ( ! is_array( $form_value ) ) {
			return '';
		}

		$type = isset( $form_value['type'] ) ? (string) $form_value['type'] : '';
		if ( 'wc_checkout_duplicate' !== $type ) {
			return '<p><em>' . esc_html__( 'WooCommerce duplicate entry.', 'duplicate-killer' ) . '</em></p>';
		}

		$fingerprint = isset( $form_value['fingerprint'] ) ? (string) $form_value['fingerprint'] : '';
		$email       = isset( $form_value['email'] ) ? (string) $form_value['email'] : '';
		$total       = isset( $form_value['total'] ) ? (string) $form_value['total'] : '';
		$currency    = isset( $form_value['currency'] ) ? (string) $form_value['currency'] : '';
		$products    = isset( $form_value['products'] ) && is_array( $form_value['products'] ) ? $form_value['products'] : array();

		$fp_short = '';
		if ( $fingerprint !== '' ) {
			$fp_short = substr( preg_replace( '/[^a-f0-9]/i', '', $fingerprint ), 0, 12 );
		}

		$products = array_map( 'absint', $products );
		$products = array_filter( $products );

		$pro_url = admin_url( 'admin.php?page=duplicateKiller&tab=pro' );

		// Unique wrapper id (so multiple rows can toggle independently)
		$wrap_id = 'dk-pro-wc-' . wp_rand( 10000, 99999 );

		$out  = '<div class="dk-wc-dup">';

		if ( $fp_short !== '' ) {
			$out .= '<p><strong>' . esc_html__( 'Fingerprint:', 'duplicate-killer' ) . '</strong> ' . esc_html( $fp_short ) . '</p>';
		}

		if ( $email !== '' ) {
			$out .= '<p><strong>' . esc_html__( 'Email:', 'duplicate-killer' ) . '</strong> ' . esc_html( $email ) . '</p>';
		}

		if ( $total !== '' ) {
			$line = $total . ( $currency !== '' ? ' ' . $currency : '' );
			$out .= '<p><strong>' . esc_html__( 'Total:', 'duplicate-killer' ) . '</strong> ' . esc_html( $line ) . '</p>';
		}

		if ( ! empty( $products ) ) {
			$out .= '<p><strong>' . esc_html__( 'Products:', 'duplicate-killer' ) . '</strong> #' . esc_html( implode( ', #', $products ) ) . '</p>';
		}

		// PRO teaser toggle (uses your CSS)
		$out .= '<div class="dk-pro-rules-wrapper" id="' . esc_attr( $wrap_id ) . '">';
		$out .= '<div class="dk-pro-toggle" role="button" tabindex="0" onclick="...">';

		$out .= '<div class="dk-pro-cta">';
		$out .= '<span class="dk-pro-mini">' . esc_html__( 'Unlock full details for this entry and more.', 'duplicate-killer' ) . '</span>';
		$out .= '<a href="' . esc_url( $pro_url ) . '">' . esc_html__( 'Upgrade to PRO →', 'duplicate-killer' ) . '</a>';
		$out .= '</div>';

		$out .= '</div>'; // panel
		$out .= '</div>'; // content
		$out .= '</div>'; // wrapper

		$out .= '</div>';

		return $out;
	}
	/**
	 * Breakdance: render fields as "label - value" lines.
	 * Supports multiple uploaded files (comma-separated signed URLs) and converts them
	 * to direct file URLs under uploads/breakdance/submissions to avoid 401 on signed endpoints.
	 *
	 * @param mixed $array Unserialized form_value (usually associative array).
	 * @return string HTML (will be sanitized by wp_kses in table_data()).
	 */
	private function duplicateKiller_organize_array_breakdance( $array ) {
		$store = '';

		if ( empty( $array ) ) {
			return $store;
		}

		$uploads = wp_get_upload_dir();
		$baseurl = isset( $uploads['baseurl'] ) ? rtrim( (string) $uploads['baseurl'], '/' ) : '';
		$basedir = isset( $uploads['basedir'] ) ? rtrim( (string) $uploads['basedir'], DIRECTORY_SEPARATOR ) : '';

		foreach ( (array) $array as $arr => $value ) {
			$key = (string) $arr;

			// Normalize nested arrays (e.g. multi-select).
			if ( is_array( $value ) ) {
				$flat = array();
				foreach ( $value as $row ) {
					if ( is_scalar( $row ) ) {
						$flat[] = (string) $row;
					} else {
						$flat[] = wp_json_encode( $row );
					}
				}
				$store .= '<p><strong>' . esc_html( $key ) . ':</strong> ' . esc_html( implode( ', ', $flat ) ) . '</p>';
				continue;
			}

			$val = is_scalar( $value ) ? (string) $value : wp_json_encode( $value );
			$val = trim( $val );

			// Breakdance uploads can be multiple signed URLs in a single string (comma-separated).
			if ( $val !== '' && strpos( $val, 'breakdance_download=' ) !== false ) {
				$parts = array_map( 'trim', explode( ',', $val ) );
				$links = array();

				foreach ( $parts as $one ) {
					if ( $one === '' || ! filter_var( $one, FILTER_VALIDATE_URL ) ) {
						continue;
					}

					$parsed = wp_parse_url( $one );
					$q      = array();
					if ( ! empty( $parsed['query'] ) ) {
						parse_str( $parsed['query'], $q );
					}

					$form_id       = isset( $q['formId'] ) ? (int) $q['formId'] : 0;
					$download_path = isset( $q['breakdance_download'] ) ? urldecode( (string) $q['breakdance_download'] ) : '';
					$download_path = '/' . ltrim( $download_path, '/' );

					// Link text = filename when possible.
					$link_text = 'Download file';
					if ( $download_path !== '/' ) {
						$file = basename( $download_path );
						if ( $file !== '' ) {
							$link_text = $file;
						}
					}

					// Try to resolve to a direct URL under uploads/breakdance/submissions/{formId}-*/YYYY/MM/file
					$href = $one; // fallback to signed url
					if ( $form_id > 0 && $download_path !== '/' && $baseurl !== '' && $basedir !== '' ) {
						$sub_path = str_replace( '/', DIRECTORY_SEPARATOR, $download_path );

						$pattern = $basedir
							. DIRECTORY_SEPARATOR . 'breakdance'
							. DIRECTORY_SEPARATOR . 'submissions'
							. DIRECTORY_SEPARATOR . $form_id . '-*'
							. $sub_path;

						$matches = glob( $pattern );
						if ( ! empty( $matches ) && is_array( $matches ) ) {
							$real_file = (string) $matches[0];

							// Absolute path -> URL relative to uploads.
							$rel = ltrim( str_replace( $basedir, '', $real_file ), DIRECTORY_SEPARATOR );
							$rel = str_replace( DIRECTORY_SEPARATOR, '/', $rel );

							$href = $baseurl . '/' . $rel;
						}
					}

					$links[] = '<a href="' . esc_url( $href ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $link_text ) . '</a>';
				}

				if ( ! empty( $links ) ) {
					$store .= esc_html( $key ) . ' - ' . implode( ', ', $links ) . '<br>';
					continue;
				}
			}

			// Optional: any other URL becomes clickable.
			if ( $val !== '' && filter_var( $val, FILTER_VALIDATE_URL ) ) {
				$text = ( strlen( $val ) > 80 ) ? ( substr( $val, 0, 60 ) . '…' ) : $val;
				$link = '<a href="' . esc_url( $val ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $text ) . '</a>';
				$store .= esc_html( $key ) . ' - ' . $link . '<br>';
				continue;
			}

			// Plain text fallback.
			//$store .= esc_html( $key ) . ' - ' . esc_html( $val ) . '<br>';
			$store .= '<p><strong>' . esc_html($key) . ':</strong> ' . esc_html($val) . '</p>';
		}

		return $store;
	}

	private function organize_array_cf7($array){
		$store = "";
		foreach($array as $arr => $value){
			if(is_array($value)){
				$store .= $arr.' - ';
				foreach($value as $row){
					$store .= $row.', ';
				}
				$store .= '<br>';
			}else{
				if(strpos($arr, 'file') !== false ){
					$escaped_url = esc_url($value);
					$link_url = '<a href="'.$escaped_url.'" target="_blank">'.basename($value).'</a>';
					//$store .= $arr.' - '. $link_url.'<br>';
					$store .= '<p><strong>' . esc_html($arr) . ':</strong> ' . $link_url . '</p>';
				}else{
					//$store .= $arr.' - '. $value.'<br>';
					$store .= '<p><strong>' . esc_html($arr) . ':</strong> ' . esc_html($value) . '</p>';
				}
			}
		}
		return $store;
	}
	private function organize_array_wpforms($array){
		$store = "";
		foreach($array as $arr => $value){
			if(is_array($value)){
				//check if $value have multiple values (checkboxes,uploads)
				if(is_array($value['value'])){
					//check if upload
						$store .= $value['name'].' - ';
						foreach($value['value'] as $subrow){
							$store .= $subrow.', ';	
						}
						$store .='<br>';
				}else{
					//$store .= $value['name'].' - '.$value['value'].'<br>';
					$store .= '<p><strong>' . esc_html($value['name']) . ':</strong> ' . esc_html($value['value']) . '</p>';
				}
			}else{
				//$store .= $arr.' - '.$value.'<br>';
				$store .= '<p><strong>' . esc_html($arr) . ':</strong> ' . esc_html($value) . '</p>';
			}
		}
		return $store;
	}	
	private function organize_array_forminator($array){
		$store = "";
		foreach($array as $arr => $value){
			//inserted in 1.2.1
			if(is_array($value)){
				//check if $value have multiple values (checkboxes,uploads)
				if(is_array($value['value'])){
					//check if upload
					if(isset($value['value']['file_name'])){
						$escaped_url = esc_url($value['value']['file_url']);
						$link_url = '<a href="'.$escaped_url.'" target="_blank">'.$value['value']['file_name'].'</a>';
						$store .= $value['name'].' - '. $link_url.'<br>';
					}else{
						$store .= $value['name'].' - ';
						foreach($value['value'] as $subrow){
							$store .= $subrow.', ';	
						}
						$store .='<br>';
					}
				}else{
					//$store .= $value['name'].' - '.$value['value'].'<br>';
					$store .= '<p><strong>' . esc_html($value['name']) . ':</strong> ' . esc_html($value['value']) . '</p>';
				}
			//for version under 1.2.1
			}else{
				//$store .= $arr.' - '.$value.'<br>';
				$store .= '<p><strong>' . esc_html($arr) . ':</strong> ' . esc_html($value) . '</p>';
			}
		}
		return $store;
	}
	/**
	 * Render Ninja Forms values in a human-readable way.
	 *
	 * @param mixed  $form_value        Stored values (usually array: [field_id => value]).
	 * @param string $form_name         e.g. "contact-form.12"
	 * @param array  $ninjaforms_page   Cached NinjaForms_page option
	 * @return string Safe HTML (caller should still wrap with wp_kses_post if needed).
	 */
	private function organize_array_ninjaforms($form_value, string $form_name, array $ninjaforms_page): string {

		$form_value = maybe_unserialize($form_value);

		if (!is_array($form_value) || empty($form_value)) {
			return '';
		}

		// Fetch config and labels map
		$cfg    = (isset($ninjaforms_page[$form_name]) && is_array($ninjaforms_page[$form_name])) ? $ninjaforms_page[$form_name] : [];
		$labels = (isset($cfg['labels']) && is_array($cfg['labels'])) ? $cfg['labels'] : [];

		$out = '<div class="dk-form-values dk-form-values--ninjaforms">';

		foreach ($form_value as $fid => $val) {

			// Ninja Forms field IDs are typically numeric, but be tolerant.
			$fid_key_int = is_numeric($fid) ? (int)$fid : null;

			// Normalize value
			if (is_array($val)) {
				$val = wp_json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			} else {
				$val = trim(wp_unslash((string) $val));
			}

			if ($val === '') {
				continue;
			}

			// Resolve label: prefer int key when possible (matches how you saved labels)
			if ($fid_key_int !== null && isset($labels[$fid_key_int])) {
				$label = (string) $labels[$fid_key_int];
			} elseif (isset($labels[$fid])) {
				$label = (string) $labels[$fid];
			} else {
				$label = 'Field ' . (string) $fid;
			}

			$label = trim($label);
			if ($label === '') {
				$label = 'Field ' . (string) $fid;
			}

			// Special: signature field (NF often stores JSON with signature_data as base64 data URL)
			if (is_string($val) && $val !== '' && $this->dk_looks_like_signature_payload($val)) {

				$sig = $this->dk_extract_signature_from_payload($val);

				if (!empty($sig['data_url'])) {

					$thumb = '<img src="' . esc_attr($sig['data_url']) . '" style="max-width:220px; height:auto; display:block; margin-top:6px; border:1px solid #ddd; padding:6px; background:#fff;" alt="Signature" />';

					$meta = '';
					if (!empty($sig['width']) && !empty($sig['height'])) {
						$meta = ' <small style="opacity:.7;">(' . (int) $sig['width'] . '×' . (int) $sig['height'] . ')</small>';
					}

					$out .= '<p><strong>' . esc_html($label) . ':</strong>' . $meta . $thumb . '</p>';
					continue;
				}

				// Fallback: if it’s signature-ish but we can’t parse, don’t dump the whole payload
				$out .= '<p><strong>' . esc_html($label) . ':</strong> <em>Signature data stored (hidden to keep table readable).</em></p>';
				continue;
			}

			// Default rendering
			$out .= '<p><strong>' . esc_html($label) . ':</strong> ' . esc_html($val) . '</p>';
		}

		$out .= '</div>';

		return $out;
	}
	
	/**
	 * Quick heuristic: is this value likely a Ninja Forms signature JSON payload?
	 */
	private function dk_looks_like_signature_payload(string $val): bool {
		// cheap checks first
		if (strpos($val, 'signature_data') === false) return false;
		if (strpos($val, 'data:image') === false) return false;
		return true;
	}

	/**
	 * Extract signature data URL + optional canvas dimensions from a stored payload.
	 *
	 * @return array{data_url:string,width:int,height:int}
	 */
	private function dk_extract_signature_from_payload(string $val): array {

		$out = [
			'data_url' => '',
			'width'    => 0,
			'height'   => 0,
		];

		$decoded = json_decode($val, true);
		if (!is_array($decoded)) {
			return $out;
		}

		if (!empty($decoded['signature_data']) && is_string($decoded['signature_data'])) {
			$out['data_url'] = $decoded['signature_data'];
		}

		if (!empty($decoded['canvas_dimensions']) && is_array($decoded['canvas_dimensions'])) {
			$w = $decoded['canvas_dimensions']['width'] ?? 0;
			$h = $decoded['canvas_dimensions']['height'] ?? 0;
			$out['width']  = is_numeric($w) ? (int)$w : 0;
			$out['height'] = is_numeric($h) ? (int)$h : 0;
		}

		return $out;
	}
	/**
	 * Render Formidable values in a human-readable way.
	 *
	 * @param mixed  $form_value        Stored values (usually array: [field_id => value]).
	 * @param string $form_name         e.g. "contact-us.2"
	 * @param array  $formidable_page   Cached Formidable_page option
	 * @return string Safe HTML (caller should still wrap with wp_kses_post if needed).
	 */
	private function organize_array_formidable($form_value, string $form_name, array $formidable_page): string {

		// Accept legacy stored values (string) safely
		$form_value = maybe_unserialize($form_value);

		if (!is_array($form_value) || empty($form_value)) {
			return '';
		}

		// Fetch config and labels map (new structure: Formidable_page[$form_name]['labels'])
		$cfg = array();
		if (isset($formidable_page[$form_name]) && is_array($formidable_page[$form_name])) {
			$cfg = $formidable_page[$form_name];
		} else {
			// Fallback: sometimes DB has "contact-us.2" but option key might be different
			// Try to match by normalizing whitespace
			$normalized = trim((string)$form_name);
			if ($normalized !== $form_name && isset($formidable_page[$normalized]) && is_array($formidable_page[$normalized])) {
				$cfg = $formidable_page[$normalized];
			}
		}

		$labels = (isset($cfg['labels']) && is_array($cfg['labels'])) ? $cfg['labels'] : array();

		$out = '<div class="dk-form-values dk-form-values--formidable">';

		foreach ($form_value as $fid => $val) {

			// Formidable field IDs are numeric
			if (!is_numeric($fid)) {
				continue;
			}

			$fid = (int) $fid;
			if ($fid <= 0) {
				continue;
			}

			// Normalize value (arrays -> JSON, strings -> unslash + trim)
			if (is_array($val)) {
				$val = wp_json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			} else {
				$val = trim(wp_unslash((string) $val));
			}

			// Skip empty values
			if ($val === '') {
				continue;
			}

			// Resolve label by field ID (saved keys are usually ints, so $labels[$fid] works)
			$label = isset($labels[$fid]) ? (string) $labels[$fid] : ('Field ' . $fid);
			$label = trim($label);
			if ($label === '') {
				$label = 'Field ' . $fid;
			}

			$out .= '<p><strong>' . esc_html($label) . ':</strong> ' . esc_html($val) . '</p>';
		}

		$out .= '</div>';

		return $out;
	}
    //Define bulk action
    public function process_bulk_action() {
		$action = $this->current_action();

		// Bulk actions should be POST (not GET).
		if ( empty( $action )
			|| ! isset( $_SERVER['REQUEST_METHOD'] )
			|| 'POST' !== $_SERVER['REQUEST_METHOD']
		) {
			return;
		}

		// Nonce required for destructive actions.
		if ( empty( $_POST['_wpnonce'] ) ) {
			return;
		}

		$nonce        = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) );
		$nonce_action = 'bulk-' . $this->_args['plural'];

		if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			wp_die( esc_html__( 'You cannot do that!', 'duplicate-killer' ) );
		}

		$form_ids = array();
		if ( ! empty( $_POST['dk_contact_form'] ) ) {
			$form_ids = array_map( 'absint', (array) wp_unslash( $_POST['dk_contact_form'] ) );
			$form_ids = array_filter( $form_ids ); // remove 0s
		}

		if ( empty( $form_ids ) ) {
			return;
		}

		global $wpdb;
		$dkdb       = apply_filters( 'duplicate_killer_database', $wpdb );
		$table_name = $dkdb->prefix . 'dk_forms_duplicate';

		if ( 'delete' === $action ) {
			foreach ( $form_ids as $form_id ) {
				$dkdb->delete(
					$table_name,
					array( 'form_id' => $form_id ),
					array( '%d' )
				);
			}
		}
	}
	
	//Display the bulk actions dropdown.
    protected function bulk_actions( $which = '' ) {
        if ( is_null( $this->_actions ) ) {
            $this->_actions = $this->get_bulk_actions();
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core hook (WP_List_Table bulk actions).
            $this->_actions = apply_filters( "bulk_actions-{$this->screen->id}", $this->_actions );
            $two = '';
        } else {
            $two = '2';
        }

        if(empty($this->_actions))
            return;
?>
        <label for="bulk-action-selector-<?php echo esc_attr($which);?>" class="screen-reader-text"><?php esc_html_e( 'Select bulk action', 'duplicate-killer' ); ?></label>
        <select name="action<?php echo esc_attr( $two ); ?>" id="bulk-action-selector-<?php echo esc_attr( $which ); ?>">
        <option value="-1"><?php echo esc_html__( 'Bulk Actions', 'duplicate-killer' ); ?></option>
			<?php
			foreach ( $this->_actions as $name => $title ) {
				$class_attr = ( 'edit' === $name ) ? ' class="hide-if-no-js"' : '';

				echo "\t" . '<option value="' . esc_attr( $name ) . '"' . esc_attr($class_attr) . '>' . esc_html( $title ) . "</option>\n";
			}
?>
		</select>
<?php
        submit_button( __( 'Apply', 'duplicate-killer' ), 'action', '', false, array( 'id' => "doaction$two" ) );
        echo "\n";
        $nonce = wp_create_nonce( 'dknonce' );
    }
	public function countDKFormsDB( string $search = '' ) {
		global $wpdb;

		$table_name = esc_sql( $wpdb->prefix . 'dk_forms_duplicate' );

		if ( '' !== $search ) {
			$search = sanitize_text_field( $search );
			$like   = '%' . $wpdb->esc_like( $search ) . '%';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reading from plugin-owned custom table (admin-only).
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT COUNT(form_id) FROM {$table_name}
					 WHERE form_value  LIKE %s
						OR form_plugin LIKE %s
						OR form_name   LIKE %s",
					$like, $like, $like
				)
			);
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reading from plugin-owned custom table (admin-only).
		return (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT COUNT(form_id) FROM {$table_name}"
		);
	}
		
    public function column_default( $item, $column_name ){
        return $item[ $column_name ];
    }
	/**
	 * Resolve a Breakdance signed download URL to a direct file URL under uploads/breakdance/submissions.
	 * Returns empty string if it can't be resolved.
	 *
	 * @param string $signed_url
	 * @return string
	 */
	private function dk_resolve_breakdance_signed_url_to_direct_url( $signed_url ) {
		$parsed = wp_parse_url( $signed_url );
		if ( empty( $parsed['query'] ) ) {
			return '';
		}

		$q = array();
		parse_str( $parsed['query'], $q );

		$form_id = isset( $q['formId'] ) ? (int) $q['formId'] : 0;
		$download_path = isset( $q['breakdance_download'] ) ? urldecode( (string) $q['breakdance_download'] ) : '';
		$download_path = '/' . ltrim( $download_path, '/' );

		if ( $form_id <= 0 || $download_path === '/' ) {
			return '';
		}

		$uploads = wp_get_upload_dir();
		$baseurl = isset( $uploads['baseurl'] ) ? rtrim( (string) $uploads['baseurl'], '/' ) : '';
		$basedir = isset( $uploads['basedir'] ) ? rtrim( (string) $uploads['basedir'], DIRECTORY_SEPARATOR ) : '';

		if ( $baseurl === '' || $basedir === '' ) {
			return '';
		}

		// Pattern: {basedir}/breakdance/submissions/{formId}-*/YYYY/MM/file.ext
		$sub_path = str_replace( '/', DIRECTORY_SEPARATOR, $download_path );
		$pattern  = $basedir
			. DIRECTORY_SEPARATOR . 'breakdance'
			. DIRECTORY_SEPARATOR . 'submissions'
			. DIRECTORY_SEPARATOR . $form_id . '-*'
			. $sub_path;

		$matches = glob( $pattern );
		if ( empty( $matches ) || ! is_array( $matches ) ) {
			return '';
		}

		$real_file = (string) $matches[0];

		// Convert absolute path -> URL relative to uploads.
		$rel = ltrim( str_replace( $basedir, '', $real_file ), DIRECTORY_SEPARATOR );
		$rel = str_replace( DIRECTORY_SEPARATOR, '/', $rel );

		return $baseurl . '/' . $rel;
	}

	/**
	 * Build a clickable <a> tag (safe later through wp_kses in table_data()).
	 *
	 * @param string $href
	 * @param string $text
	 * @return string
	 */
	private function dk_build_link( $href, $text ) {
		return '<a href="' . esc_url( $href ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $text ) . '</a>';
	}
}