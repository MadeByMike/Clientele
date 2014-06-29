<?php
/**
 * Firstly thanks to Matt Van Andel for: http://wordpress.org/plugins/custom-list-table-example/
 */
if (!class_exists('WP_List_Table')) {
  require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
  /**
   * clienteleTable extends WP_List_Table
   */
  if (!class_exists('clienteleTable')) {
    class clienteleTable extends WP_List_Table {
      function __construct($query = '', $params = false, $no_items = false) {
        global $status, $page;
        if ($params == false) {
          $params = array(
            'singular' => 'clientele_table',
            'plural' => 'clientele_tables',
            'ajax' => false
          );
        }
        //Set parent defaults
        parent::__construct($params);
        $this->query = $query;
        $this->no_items = $no_items;
      }

      /**
       * @author Mike
       * @since  1.0
       *
       * @param array $item A singular item (one full row's worth of data)
       *
       * @return string Text to be placed inside the column <td>
       **/
      function column_default($item, $column_name) {
        $value = '';
        $value = apply_filters('manage_list_' . $this->_args['singular'] . '_custom_column', $value, $column_name, $item);
        if ($value == '' && $item->$column_name) {
          $value = $item->$column_name;
        }
        return $value;
      }
      
      function no_items() {
        if($this->no_items){
          echo $this->no_items;
        }else{
          $this->no_items();
        }
      }
      /**
       * @author Mike
       * @since  1.0
       *
       * @see    WP_List_Table::::single_row_columns()
       *
       * @param array $item A singular item (one full row's worth of data)
       *
       * @return string Text to be placed inside the column <td>
       **/
      function column_title($item) {
        $value = sprintf('%1$s <span style="color:silver">(id:%2$s)</span>%3$s', $item->title, /*$1%s*/
          $item->ID, /*$2%s*/
          $this->row_actions($actions)/*$3%s*/);
        return $value;
      }

      /**
       * @author Mike
       * @since  1.0
       *
       * @see    WP_List_Table::::single_row_columns()
       *
       * @param array $item A singular item (one full row's worth of data)
       *
       * @return string Text to be placed inside the column <td>
       **/
      function column_cb($item) {
        $val = isset($item->id) ? $item->id : '';
        $value = sprintf('<input type="checkbox" name="%1$s[]" value="%2$s" />', $this->_args['singular'], /*$1%s*/
          $val /*$2%s*/);
        return $value;
      }

      /**
       * @author Mike
       * @since  1.0
       *
       * @see    WP_List_Table::::single_row_columns()
       * @return array An associative array containing column information: 'slugs'=>'Visible Titles'
       *
       **/
      function get_columns($col_keys) {
        $cols = array();
        foreach ($col_keys as $key => $value) {
          $str = $value;
          $str = preg_replace('/([A-Z])/e', "' ' . strtolower('\\1')", $str);
          $str = preg_replace('/([_-])/e', "' '", $str);
          $cols[$value] = ucfirst($str);
        }
        $cols = apply_filters('manage_list_' . $this->_args['singular'] . '_columns', $cols);
        return $cols;
      }

      /**
       * @author Mike
       * @since  1.0
       *
       * @return array An associative array containing all the columns that should be sortable: 'slugs'=>array('data_values',bool)
       *
       **/
      function get_sortable_columns() {
        $sortable_columns = array();
        $sortable_columns = apply_filters('manage_list_' . $this->_args['singular'] . '_sortable_columns', $sortable_columns);
        return $sortable_columns;
      }

      /**
       * @author Mike
       * @since  1.0
       *
       * @return array An associative array containing all the bulk actions: 'slugs'=>'Visible Titles'
       *
       **/
      function get_bulk_actions() {
        $actions = array();
        $actions = apply_filters('manage_list_' . $this->_args['singular'] . '_bulk_actions', $actions);
        return $actions;
      }

      /**
       * @author Mike
       * @since  1.0
       *
       * @see    $this->prepare_items()
       *
       **/
      function process_bulk_action() {
        if ($this->current_action() !== false) {
          $items = array();
          if (isset($_REQUEST[$this->_args['singular']])) {
            $items = $_REQUEST[$this->_args['singular']];
          }
          do_action('manage_list_' . $this->_args['singular'] . '_process_bulk_actions', $this->current_action(), $items);
        }
      }

      /**
       * @author Mike
       * @since  1.0
       *
       * @global WPDB $wpdb
       * @uses   $this->_column_headers
       * @uses   $this->items
       * @uses   $this->get_columns()
       * @uses   $this->get_sortable_columns()
       * @uses   $this->get_pagenum()
       * @uses   $this->set_pagination_args()
       *
       *
       **/
      function prepare_items($params = array('per_page' => 10)) {
        global $wpdb;
        $per_page = isset($params['per_page']) ? $params['per_page'] : 10;
        $current_page = $this->get_pagenum();
        $query = isset($params['query']) ? $params['query'] : $this->query;
        $orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : false;
        $order = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'asc'; //If no order, default to asc
        $total_items = $wpdb->query($query);
        if ($orderby) {
          $query .= " ORDER BY `" . $orderby . "` " . $order . " ";
        }
        $query .= " LIMIT " . (($current_page - 1) * $per_page);
        $query .= ", " . $per_page . " ";
        $data = $wpdb->get_results($query);
        $col_keys = array();
        if ($data) {
          $col_keys = array_keys(get_object_vars($data[0]));
        }
        $columns = $this->get_columns($col_keys);
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
        $this->process_bulk_action();
        $this->items = $data;
        $this->set_pagination_args(array(
            'total_items' => $total_items, //WE have to calculate the total number of items
            'per_page' => $per_page, //WE have to determine how many items to show on a page
            'total_pages' => ceil($total_items / $per_page) //WE have to calculate the total number of pages
          ));
      }

      /**
       * @author Mike
       * @since  1.0
       *
       * Generate the table navigation above or below the table
       * Same as WP core function with nonce removed for use in metaboxes
       *
       * @param array $which table singular name
       *
       */
      function display_tablenav($which) {
        ?>
        <div class="tablenav <?php echo esc_attr($which); ?>">
          <div class="alignleft actions">
            <?php $this->bulk_actions(); ?>
          </div>
          <?php
          $this->extra_tablenav($which);
          $this->pagination($which);
          ?>
          <br class="clear" />
        </div>
      <?php
      }
    } // END class clienteleTable
  } // END if !class_exists( 'clienteleTable' )
} // END if !class_exists('WP_List_Table')
