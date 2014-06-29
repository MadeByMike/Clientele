<?php
/**
 * Event class
 *
 * @package clientele
 */
if (!class_exists('clienteleEvent')) {
  /**
   * Creates an even log for actions within Clientele.
   */
  class clienteleEvent {
    /**
     * __construct
     */
    function __construct() {
      global $table_prefix, $wpdb;
      $this->events = "";
      $table_name = $table_prefix . "clientele_events";
      if ($wpdb->get_var("show tables like '$table_name'") != $table_name) { //Check to see if the events table exists already, if not, create it
        $sql_evnt = "CREATE TABLE `" . $table_name . "` ( ";
        $sql_evnt .= "  `id`	int(11) NOT NULL auto_increment, ";
        $sql_evnt .= "  `date` datetime NOT NULL default '00:00:00', ";
        $sql_evnt .= "  `name` VARCHAR(50), ";
        $sql_evnt .= "  `type` VARCHAR(50), ";
        $sql_evnt .= "  `description` VARCHAR(100), ";
        $sql_evnt .= "  UNIQUE KEY `id` (`id`) ";
        $sql_evnt .= ") ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ; ";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_evnt);
      }
      register_deactivation_hook(clientele_uninst_path, array(&$this, 'plugin_uninstall'));
    }

    /**
     * Removes all traces of the event table
     *
     * @author Mike
     * @since  1.0
     */
    function plugin_uninstall() {
      global $table_prefix, $wpdb;
      $table_name = $table_prefix . "clientele_events";
      $wpdb->query("DROP TABLE IF EXISTS $table_name");
    }

    /**
     *  Adds an event to the database and trigger actions.
     *
     * Triggers 2 actions, one for the event type and one for the specific event.
     * There are a number of optional params to customise the display of these events within Clientele
     *
     * @author Mike
     * @since  1.0
     *
     * @param string $type        (Required) the type of event eg. 'change', 'delete', 'create' to be used in hooks
     * @param string $tag         (Required) a name specific to this event eg. 'first_name_updated' to be used in hooks
     * @param string $description (Optional) a detailed description of the event
     * @param array  $args        (Optional) arguments to be passed if the action is hooked
     *
     */
    function do_event($type, $tag, $description = '', $args = false) {
      //Should type be controlled to sys, change, or module name?
      global $table_prefix, $wpdb;
      $table_name = $table_prefix . "clientele_events";
      if ($description == "") {
        $description = $tag . " event occurred";
      }
      $datetime = current_time('mysql');
      #Create the sql statement
      $sql = "INSERT INTO `" . $table_name . "` VALUES (";
      $sql .= "'',";
      $sql .= "'{$datetime}',";
      $sql .= "'{$tag}',";
      $sql .= "'{$type}',";
      $sql .= "'{$description}')";
      #Run the sql query
      $result = $wpdb->query($sql);
      //do hooks
      do_action("event_{$type}", $args);
      do_action("event_{$type}_{$tag}", $args);
    }
  }
}

?>