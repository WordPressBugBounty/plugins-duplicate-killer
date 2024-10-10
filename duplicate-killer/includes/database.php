<?php
defined( 'ABSPATH' ) or die( 'You shall not pass!' );

function duplicateKiller_db_display_page(){
	return new DK_db_page();
}

class DK_db_page{
    public function __construct(){
        $this->list_table_page();
    }
    public function list_table_page(){
        $ListTable = new DK_Main_List_Table();
        $ListTable->prepare_items();
        ?>
            <div class="wrap">
                <div id="icon-users" class="icon32"></div>
                <h2>Duplicate Killer Database</h2>
                <form method="post" action="">
                    <?php $ListTable->search_box(__( 'Search', 'contact-form-cfdb7' ), 'search'); ?>
                    <?php $ListTable->display(); ?>
                </form>
            </div>
        <?php
    }

}

if( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class DK_Main_List_Table extends WP_List_Table{
	public function __construct() {
        parent::__construct(
            array(
                'singular' => 'dk_contact_form',
                'plural'   => 'dk_contact_forms',
                'ajax'     => false
            )
        );
    }

    public function prepare_items(){
		
		$search = empty( $_REQUEST['s'] ) ? false :  esc_sql( $_REQUEST['s'] );

        global $wpdb;
		$this->process_bulk_action();
        $dkdb        = apply_filters( 'duplicate_killer_database', $wpdb );
        $table_name  = $dkdb->prefix.'dk_forms_duplicate';
        $columns     = $this->get_columns();
        $hidden      = $this->get_hidden_columns();
        $data        = $this->table_data();
        $perPage     = 20;
        $currentPage = $this->get_pagenum();
        $totalItems  = $this->countDKFormsDB();
		

        $this->set_pagination_args( array(
            'total_items' => $totalItems,
            'per_page'    => $perPage
        ) );

        $this->_column_headers = array($columns, $hidden );
        $this->items = $data;
    }
    
    //Override the parent columns method. Defines the columns to use in your listing table
    public function get_columns(){

        $columns = array(
			'cb' => __('<input type="checkbox" />'),
			'form_plugin'=> __( 'Form plugin', 'duplicate_killer_database' ),
            'form_name' => __( 'Form Name', 'duplicate_killer_database' ),
            'form_value'=> __( 'Form Value', 'duplicate_killer_database' ),
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
            'delete' => __( 'Delete', 'duplicate_killer_database' )
        );
    }
	
	private function table_data(){
        global $wpdb;
		$search = empty($_REQUEST['s'])?false: esc_sql($_REQUEST['s']);
        $table_name = $wpdb->prefix.'dk_forms_duplicate';
        $page = $this->get_pagenum();
        $page = $page - 1;
        $start = $page * 20;
		
		if(!empty($search)){
			$result = $wpdb->get_results("SELECT * FROM $table_name 
                        WHERE  form_value LIKE '%$search%'
                        ORDER BY form_id DESC
                        LIMIT $start,20", OBJECT);
        }else{
			$result = $wpdb->get_results("SELECT * FROM $table_name 
                       ORDER BY form_id DESC
                        LIMIT $start,20", OBJECT);
        }
			foreach($result as $row){
				$data_value['form_id'] = esc_attr($row->form_id);
				$data_value['form_plugin']  = esc_attr($row->form_plugin);
				$data_value['form_name'] =  esc_attr($row->form_name);
				$store = "";
				$form_value = unserialize($row->form_value);
				foreach($form_value as $arr => $value){
					$store .= $arr.' - '.$value.'</br>';
				}
				$data_value['form_value'] =  wp_kses_post($store);
				$data[] = $data_value;
			}
			if(!empty($data)){
				return $data;
			}
        return;
    }
	
    //Define bulk action
    public function process_bulk_action(){

        global $wpdb;
        $dkdb = apply_filters( 'duplicate_killer_database', $wpdb );
        $table_name = $dkdb->prefix.'dk_forms_duplicate';
        $action = $this->current_action();

        if(!empty($action)){
            $nonce = isset( $_REQUEST['_wpnonce'] ) ? $_REQUEST['_wpnonce'] : '';
            $nonce_action = 'bulk-' . $this->_args['plural'];
            if(!wp_verify_nonce( $nonce, $nonce_action)){
                wp_die('You cannot do that!');
            }
        }

        $form_ids = isset($_POST['dk_contact_form'])?$_POST['dk_contact_form']:array();


        if('delete' === $action){
            foreach ($form_ids as $form_id){
                $dkdb->delete(
                    $table_name ,
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
            $this->_actions = apply_filters( "bulk_actions-{$this->screen->id}", $this->_actions );
            $two = '';
        } else {
            $two = '2';
        }

        if(empty($this->_actions))
            return;
?>
        <label for="bulk-action-selector-<?php echo esc_attr($which);?>" class="screen-reader-text"><?php __('Select bulk action', 'duplicate_killer_database')?></label>
        <select name="action<?php esc_attr($two);?>" id="bulk-action-selector-<?php echo esc_attr($which);?>">
        <option value="-1"><?php echo __('Bulk Actions', 'duplicate_killer_database');?></option>
<?php
        foreach ( $this->_actions as $name => $title ) {
            $class = 'edit' === $name ? esc_attr(' class="hide-if-no-js"') : '';

            echo "\t" . '<option value="' . $name . '"' . $class . '>' . $title . "</option>\n";
        }
?>
		</select>
<?php
        submit_button( __( 'Apply', 'duplicate_killer_database' ), 'action', '', false, array( 'id' => "doaction$two" ) );
        echo "\n";
        $nonce = wp_create_nonce( 'dknonce' );
    }
	public function countDKFormsDB(){
		global $wpdb;
        $dkdb = apply_filters( 'duplicate_killer_database', $wpdb );
		$table_name = $dkdb->prefix.'dk_forms_duplicate';
		return $dkdb->get_var("SELECT COUNT(form_id) FROM $table_name");
	}
		
    public function column_default( $item, $column_name ){
        return $item[ $column_name ];
    }
}