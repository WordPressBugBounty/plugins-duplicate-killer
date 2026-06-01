<?php
defined( 'ABSPATH' ) or die( 'You shall not pass!' );

class duplicateKiller_DatabaseMainListTable extends WP_List_Table{
	
	private int $total_items_count = 0;
	
	public function __construct() {
        parent::__construct(
            array(
                'singular' => 'dk_contact_form',
                'plural'   => 'dk_contact_forms',
                'ajax'     => false
            )
        );
    }
	/**
	 * Output a search box with an extra Reset button (no JS, no CSS hacks).
	 *
	 * @param string $text Button text.
	 * @param string $input_id Input ID.
	 */
	public function search_box( $text, $input_id ) {
		$search = DuplicateKiller_Admin_Submissions_Request::get_search();
		$view   = DuplicateKiller_Admin_Submissions_Request::get_view();

		$show_reset = ( '' !== $search );

		$reset_url = DuplicateKiller_Admin_Submissions_Request::get_base_url();
		$reset_url = add_query_arg( 'dk_view', $view, $reset_url );
		$reset_url = remove_query_arg( 'paged', $reset_url );

		$form_plugin = DuplicateKiller_Admin_Submissions_Request::get_form_plugin();
		$form_name   = DuplicateKiller_Admin_Submissions_Request::get_form_name();

		if ( '' !== $form_plugin ) {
			$reset_url = add_query_arg( 'dk_form_plugin', $form_plugin, $reset_url );
		}

		if ( '' !== $form_name ) {
			$reset_url = add_query_arg( 'dk_form_name', $form_name, $reset_url );
		}

		echo '<div class="dk-db-search">';
		echo '<div class="dk-db-search__field">';
		echo '<label class="screen-reader-text" for="' . esc_attr( $input_id ) . '-search-input">' . esc_html( $text ) . ':</label>';
		echo '<input type="search" id="' . esc_attr( $input_id ) . '-search-input" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search submissions, names, plugins...', 'duplicate-killer' ) . '" />';
		echo '</div>';

		echo '<div class="dk-db-search__actions">';
		submit_button( $text, 'primary', '', false, array( 'id' => 'search-submit' ) );

		if ( $show_reset ) {
			echo '<a href="' . esc_url( $reset_url ) . '" class="button dk-db-search__reset">' . esc_html__( 'Reset', 'duplicate-killer' ) . '</a>';
		}

		echo '</div>';
		echo '</div>';
	}
    public function prepare_items() {

		$search = DuplicateKiller_Admin_Submissions_Request::get_search();
		$view   = DuplicateKiller_Admin_Submissions_Request::get_view();
		
		$form_plugin = DuplicateKiller_Admin_Submissions_Request::get_form_plugin();
		$form_name   = DuplicateKiller_Admin_Submissions_Request::get_form_name();

		if ( '' !== $form_plugin && '' !== $form_name ) {
			$repository_check = new DuplicateKiller_Submissions_Repository();

			if ( ! $repository_check->form_exists_for_plugin( $form_plugin, $form_name ) ) {
				$form_name = '';
			}
		}

		$this->process_bulk_action();
		$repository = new DuplicateKiller_Submissions_Repository();

		$columns     = $this->get_columns();
		$hidden      = $this->get_hidden_columns();

		$perPage     = 20;
		$currentPage = $this->get_pagenum();
		$data        = $this->table_data( $search, $view, $form_plugin, $form_name );
		$totalItems  = $repository->count_items( $search, $view, $form_plugin, $form_name );
		$this->total_items_count = (int) $totalItems;
		
		$this->set_pagination_args(
			array(
				'total_items' => $totalItems,
				'per_page'    => $perPage,
			)
		);

		$this->_column_headers = array( $columns, $hidden );
		$this->items           = $data;
	}
    
	public function get_total_items_count(): int {
		return $this->total_items_count;
	}
	public function no_items() {
		esc_html_e( 'No submissions found for the current filter.', 'duplicate-killer' );
	}
    //Override the parent columns method. Defines the columns to use in your listing table
    public function get_columns(){

        $columns = array(
			'cb'          => '<input type="checkbox" />',
			'form_source' => __( 'Source', 'duplicate-killer' ),
			'form_value'  => __( 'Matched preview', 'duplicate-killer' ),
			'form_date'   => __( 'Date', 'duplicate-killer' ),
			'actions'     => __( 'Actions', 'duplicate-killer' ),
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
             absint( $item['form_id'] )
        );
    }
	public function get_bulk_actions(){
        return array(
            'delete' => __( 'Delete', 'duplicate-killer' )
        );
    }
	
	private function table_data( string $search = '', string $view = 'forms', string $form_plugin = '', string $form_name = '' ){
		$data = array();

		$repository = new DuplicateKiller_Submissions_Repository();

		$per_page = 20;
		$page     = max( 1, (int) $this->get_pagenum() );

		$result = $repository->get_items( $search, $view, $per_page, $page, $form_plugin, $form_name );

		$allowed_html = array(
			'div'    => array( 'class' => true ),
			'p'      => array(),
			'br'     => array(),
			'strong' => array( 'class' => true ),
			'small'  => array( 'class' => true, 'style' => true ),
			'em'     => array( 'class' => true ),
			'span'   => array( 'class' => true ),
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

		$renderer = new DuplicateKiller_Submission_Value_Renderer();
		foreach ( (array) $result as $row ) {

			// Reset per row (prevents leaking values across iterations).
			$data_value = array();
			$store      = '';

			$data_value['form_id']     = absint( $row->form_id );
			$data_value['form_plugin'] = sanitize_text_field( (string) $row->form_plugin );
			$data_value['form_name']   = sanitize_text_field( (string) $row->form_name );

			$store = $renderer->render(
				$data_value['form_plugin'],
				$data_value['form_name'],
				$row->form_value
			);
			$preview = $renderer->render_preview(
				$data_value['form_plugin'],
				$data_value['form_name'],
				$row->form_value
			);
			$data_value['form_value'] = wp_kses( (string) $preview, $allowed_html, $allowed_protocols );
			$data_value['form_date']  = sanitize_text_field( (string) $row->form_date );
			$data_value['form_ip']    = sanitize_text_field( (string) $row->form_ip );
			
			$data_value['actions'] = sprintf(
				'<button
					type="button"
					class="button dk-db-view-submission"
					data-submission-id="%1$d"
					data-plugin="%4$s"
					data-form="%5$s"
					data-date="%6$s"
					data-ip="%7$s"
				>%2$s</button>
				<div class="dk-db-submission-full" id="dk-db-submission-full-%1$d" hidden>%3$s</div>',
				absint( $row->form_id ),
				esc_html__( 'View details', 'duplicate-killer' ),
				wp_kses( (string) $store, $allowed_html, $allowed_protocols ),
				esc_attr( $data_value['form_plugin'] ),
				esc_attr( $data_value['form_name'] ),
				esc_attr( $data_value['form_date'] ),
				esc_attr( $data_value['form_ip'] )
			);

			$data[] = $data_value;
		}

		return $data;
	}

    //Define bulk action
    public function process_bulk_action() {
		$action = $this->current_action();
		if ( empty( $action )
			|| ! isset( $_SERVER['REQUEST_METHOD'] )
			|| 'POST' !== $_SERVER['REQUEST_METHOD']
		) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot do that!', 'duplicate-killer' ) );
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

		if ( 'delete' === $action ) {
			$repository = new DuplicateKiller_Submissions_Repository();
			$repository->delete_items( $form_ids );
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
    }
		
    public function column_default( $item, $column_name ) {
		if ( 'form_source' === $column_name ) {
			$plugin = (string) ( $item['form_plugin'] ?? '' );
			$form   = (string) ( $item['form_name'] ?? '' );

			return '<div class="dk-db-source">'
				. '<span class="dk-db-source__plugin">' . esc_html( DuplicateKiller_Plugin_Labels::get_label( $plugin ) ) . '</span>'
				. '<strong class="dk-db-source__form">' . esc_html( $form ) . '</strong>'
				. '</div>';
		}

		if ( 'form_date' === $column_name ) {
			return '<span class="dk-db-date">' . esc_html( $item['form_date'] ?? '' ) . '</span>';
		}

		if ( 'form_ip' === $column_name ) {
			return '<span class="dk-db-ip">' . esc_html( $item['form_ip'] ?? '' ) . '</span>';
		}

		if ( 'actions' === $column_name ) {
			return $item['actions'] ?? '';
		}

		return $item[ $column_name ] ?? '';
	}
}