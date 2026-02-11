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
			'form_date'=> __( 'Form Date', 'duplicate_killer_database' ),
			'form_ip'=> __( 'Form IP', 'duplicate_killer_database' )
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
		$search = empty($_REQUEST['s'])? false: esc_sql($_REQUEST['s']);
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
			// Cache options once (cheap + clean)
			$formidable_page = get_option('Formidable_page', []);
			if (!is_array($formidable_page)) {
				$formidable_page = [];
			}
			
			// Cache Ninja Forms options once (cheap + clean)
			$ninjaforms_page = get_option('NinjaForms_page', []);
			if (!is_array($ninjaforms_page)) {
				$ninjaforms_page = [];
			}
			foreach ($result as $row) {

				// Reset per row (prevents leaking values across iterations)
				$data_value = [];
				$store = '';

				$data_value['form_id']     = esc_attr($row->form_id);
				$data_value['form_plugin'] = esc_attr($row->form_plugin);
				$data_value['form_name']   = esc_attr($row->form_name);

				$form_value = maybe_unserialize($row->form_value);

				if ($data_value['form_plugin'] === 'CF7') {
					$store = $this->organize_array_cf7($form_value);

				} elseif ($data_value['form_plugin'] === 'Forminator') {
					$store = $this->organize_array_forminator($form_value);

				} elseif ($data_value['form_plugin'] === 'WPForms') {
					$store = $this->organize_array_wpforms($form_value);

				} elseif ($data_value['form_plugin'] === 'breakdance') {
					$store = $this->organize_array_cf7($form_value);

				} elseif ($data_value['form_plugin'] === 'Formidable') {
					$store = $this->organize_array_formidable($form_value, $data_value['form_name'], $formidable_page);
				
				} elseif ($data_value['form_plugin'] === 'NinjaForms') {
					$store = $this->organize_array_ninjaforms($form_value, $data_value['form_name'], $ninjaforms_page);
	
				} elseif ($data_value['form_plugin'] === 'elementor') {
					$store = $this->organize_array_cf7($form_value);

				} else {
					// Fallback for unknown plugins (prevents reusing previous $store)
					$store = is_string($form_value) ? $form_value : print_r($form_value, true);
				}

				$allowed_html = [
					'div' => ['class' => true],
					'p'   => [],
					'br'  => [],
					'strong' => [],
					'small'  => ['style' => true],
					'em'     => [],
					'a' => [
						'href'   => true,
						'target' => true,
						'rel'    => true,
					],
					'img' => [
						'src'   => true,
						'alt'   => true,
						'style' => true,
					],
				];

				$allowed_protocols = array_merge(wp_allowed_protocols(), ['data']);

				$data_value['form_value'] = wp_kses($store, $allowed_html, $allowed_protocols);
				$data_value['form_date']  = esc_attr($row->form_date);
				$data_value['form_ip']    = esc_attr($row->form_ip);

				$data[] = $data_value;
			}
			if(!empty($data)){
				return $data;
			}
        return;
    }
	private function organize_array_cf7($array){
		$store = "";
		foreach($array as $arr => $value){
			//inserted in 1.2.1
			if(is_array($value)){
				$store .= $arr.' - ';
				foreach($value as $row){
					$store .= $row.', ';
				}
				$store .= '</br>';
			//for version under 1.2.1
			}else{
				if(strpos($arr, 'file') !== false ){
					$escaped_url = esc_url($value);
					$link_url = '<a href="'.$escaped_url.'" target="_blank">'.basename($value).'</a>';
					$store .= $arr.' - '. $link_url.'</br>';
				}else{
					$store .= $arr.' - '. $value.'</br>';
				}
			}
		}
		return $store;
	}
	private function organize_array_wpforms($array){
		$store = "";
		foreach($array as $arr => $value){
			//inserted in 1.2.1
			if(is_array($value)){
				//check if $value have multiple values (checkboxes,uploads)
				if(is_array($value['value'])){
					//check if upload
						$store .= $value['name'].' - ';
						foreach($value['value'] as $subrow){
							$store .= $subrow.', ';	
						}
						$store .='</br>';
				}else{
					$store .= $value['name'].' - '.$value['value'].'</br>';
				}
			//for version under 1.2.1
			}else{
				$store .= $arr.' - '.$value.'</br>';
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
						$store .= $value['name'].' - '. $link_url.'</br>';
					}else{
						$store .= $value['name'].' - ';
						foreach($value['value'] as $subrow){
							$store .= $subrow.', ';	
						}
						$store .='</br>';
					}
				}else{
					$store .= $value['name'].' - '.$value['value'].'</br>';
				}
			//for version under 1.2.1
			}else{
				$store .= $arr.' - '.$value.'</br>';
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