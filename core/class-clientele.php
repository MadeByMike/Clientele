<?php
/**
 *
 * @package Clientele
 */
if (!class_exists('clienteleApp')) {
  /**
   * Clientele core class
   * Much of the module loading architecture was was adapted from methods first seen in Automatic's Edit flow plugin #creditWhereDue
   */
  class clienteleApp {
    /**
     * A unique identifier to be used when prefixing stuff
     *
     * @var string
     */
    var $clientele_prefix = 'clientele_';

    /**
     * __construct
     */
    function __construct() {
      $this->modules = (object) array();
      add_action('plugins_loaded', array($this, 'load_modules'));
      add_action('init', array($this, 'load_default_options'));
      add_action('admin_menu', array($this, 'add_support_page'));
    }

    /**
     * This is a hidden page not linked or attached to any menu. It gives information that may be used for testing or support.
     */
    function add_support_page() {
      add_submenu_page(null, 'Clientele Support Info', 'Clientele Support Info', 'manage_options', 'clientele-support-info', array($this, 'clientele_support_page'));
    }

    /**
     * Loads the support page
     */
    function clientele_support_page() {
      require('clientele-support.php');
    }

    /**
     * Checks and loads modules from the modules folder.
     *
     * This function reads the modules folder looking for sub folders with a corresponding .php file.
     * It then passes the path for each modules .php file to the register_module function.
     *
     * @author Automatic
     * @since  1.0
     *
     */
    function load_modules() {
      $module_folders = scandir(clientele_ROOT . '/modules/');
      foreach ($module_folders as $key => $folder) {
        $module_folders[$key] = clientele_ROOT. '/modules/'. $module_folders[$key] . '/' . $module_folders[$key] . '.class.php'; 
      }
      $module_folders = apply_filters('clientele_module_folders', $module_folders);
      foreach ($module_folders as $module_folder) {
        if (file_exists($module_folder)) {
          require($module_folder);
          $file_name = basename($module_folder, '.class.php');
          $this->register_module($file_name);
        }
      }
      do_action('clientele_modules_loaded');
    }

    /**
     * Load a module at a specified path.
     *
     * If the path provided exists and contains a corresponding class will substantiate it.
     * If a module is not auto loading checking that the file and class name match what is expected.
     *
     * @author Mike
     * @since  1.0
     *
     */
    function register_module($file_name) {
        $words = explode('-', $file_name);
        $class_name = '';
        $slug_name = '';
        foreach ($words as $word) {
          $class_name .= ucfirst($word);
          $slug_name .= $word . '_';
        }
        $class_name = 'clientele' . $class_name;
        if (class_exists($class_name)) {
          $slug_name = rtrim($slug_name, '_');
          $this->$slug_name = new $class_name();
        }
    }

    /**
     * Loads options and initialises modules.
     *
     * This method of loading modules is basically copied from Automatic's 'EditFlow' plugin.
     * I wanted to manage modules in Clientele in a similar way and I cannot think of a better way.
     *
     * @author Mike
     * @since  1.0
     *
     */
    function load_default_options() {
      foreach ($this->modules as $name => $data) {
        $this->modules->$name->options = new stdClass();
        $saved_options = get_option($this->clientele_prefix . $name . '_options');
        if ($saved_options) {
          $this->modules->$name->options = (object) array_merge((array) $this->modules->$name->options, (array) $saved_options);
        }
        foreach ($data->default_options as $default_key => $default_value) {
          if (!isset($this->modules->$name->options->$default_key)) {
            $this->modules->$name->options->$default_key = $default_value;
          }
        }
        $this->$name->module = $this->modules->$name;
      }
      do_action('clientele_default_options_loaded');
      // Initialise modules if enabled.
      foreach ($this->modules as $name => $data) {
        if ($data->options->enabled == 'on') {
          if (method_exists($this->$name, 'init')) {
            $this->$name->init();
          }
        }
      }
      do_action('clientele_initialised');
    }

    /**
     * Updates a modules options
     *
     * @author Automatic\Mike
     * @since  1.0
     *
     * @param $mod_name
     * @param $key
     * @param $value
     *
     * @return mixed
     */
    function update_module_option($mod_name, $key, $value) {
      $this->modules->$mod_name->options->$key = $value;
      $new_options = $this->modules->$mod_name->options;
      $result = update_option($this->clientele_prefix . $mod_name . '_options', (array) $new_options);
      $this->$mod_name->module = $this->modules->$mod_name;
      return $result;
    }

    /**
     * Updates all module options
     *
     * @author Automatic\Mike
     * @since  1.0
     *
     * @param $mod_name
     * @param $new_options
     *
     * @return mixed
     *
     */
    function update_all_module_options($mod_name, $new_options) {
      if (is_array($new_options)) {
        $new_options = (object) $new_options;
      }
      $this->modules->$mod_name->options = $new_options;
      $this->$mod_name->module = $this->modules->$mod_name;
      return update_option($this->clientele_prefix . $mod_name . '_options', (array) $this->modules->$mod_name->options);
    }

    /**
     * A simple wrapper to allow metadata to be added, updated or deleted with in function while logging events
     *
     * @author Mike
     * @since  1.0
     *
     * @param $new_meta_value
     * @param $post_id
     * @param $key
     */
    function add_update_or_delete_post_meta($new_meta_value, $post_id, $key) {
      global $clientele_events;
      global $post_type;
      $args['post_id'] = $post_id;
      $args['key'] = $key;
      $old_meta_value = get_post_meta($post_id, $key, true);
      if ('' == $new_meta_value) { // If empty delete it
        delete_post_meta($post_id, $key, $old_meta_value);
        if ($post_type == 'clientele-client') {
          $clientele_events->do_event('change', 'delete_meta', 'Meta field "' . $key . '" deleted', $args);
        }
      } elseif (isset($new_meta_value) && '' == $old_meta_value) { // If new add it.
        add_post_meta($post_id, $key, $new_meta_value, true);
        if ($post_type == 'clientele-client') {
          $clientele_events->do_event('change', 'add_meta', 'Meta field "' . $key . '" added', $args);
        }
      } elseif (isset($new_meta_value) && $new_meta_value != $old_meta_value) { // If changed update it
        update_post_meta($post_id, $key, $new_meta_value);
        if ($post_type == 'clientele-client') {
          $clientele_events->do_event('change', 'update_meta', 'Meta field "' . $key . '" updated', $args);
        }
      }
    }

    /**
     * Makes use of wp_transition_post_status for custom post types. This allows add on developers to hook post status transitions
     *
     * I suspect this might not be required in future versions of WP
     *
     * @author Mike
     * @since  1.0
     *
     * @param $post_obj
     * @param $new_status
     */
    function change_post_status($post_obj, $new_status) {
      global $wpdb;
      if (!(get_post_status($post_obj->ID) == $new_status)) {
        $old_status = "new";
        if (isset($post_obj->post_status)) {
          $old_status = $post_obj->post_status;
        }
        $wpdb->update($wpdb->posts, array('post_status' => $new_status), array('ID' => $post_obj->ID));
        $post_obj->post_status = $new_status;
        wp_transition_post_status($new_status, $old_status, $post_obj);
      }
    }

    /**
     *
     * @author Mike
     * @since  1.0
     *
     * @param $content
     * @param $client_id
     *
     * @return string
     */
    function apply_clientele_shortcodes($content, $recipient) {
      global $clientele;
      // In process of replacing similar function
      global $clientele;
      if (is_int($recipient)) {
        $recipient = get_post($recipient);
      } else {
        $recipients = $clientele->clients->get_clients_by_meta('email', $recipient);
        $recipient = isset($recipients[0]) ? $recipients[0] : '';
      }
      if (isset($recipient->ID)) {
        $recipientfields = get_post_custom($recipient->ID);
      }
      foreach ($clientele->clients->module->options->client_fields as $key => $field) {
        $slug = '{' . $clientele->make_slug($field['label']) . '}';
        if (isset($recipientfields[$key])) {
          $content = str_replace($slug, $recipientfields[$key][0], $content);
        } else {
          if (isset($field['default'])) {
            $content = str_replace($slug, $field['default'], $content);
          }
        }
      }
      if (isset($recipient->ID)) {
        $unsubscribe_link = get_permalink($recipient->ID);
        $unsubscribe = '<a href="' . $unsubscribe_link . '">' . __('Unsubscribe', 'clientele') . '</a>';
        $content = str_replace('{unsubscribe}', $unsubscribe, $content);
        $content = str_replace('{unsubscribe-link}', $unsubscribe_link, $content);
      }
      return $content;
    }

    /**
     * Normalises a string and converts it to a slug
     *
     * @author Mike
     * @since  1.0
     *
     * @param $str
     *
     * @return string
     */
    function make_slug($str, $delim = '-') {
      strtolower($str);
      $str = preg_replace("/[^A-Za-z0-9 ]/", " ", $str); // invalid chars, make into spaces
      $str = preg_replace("/\-/", " ", $str); // hyphen to space ...for now
      $str = preg_replace("/\s\s+/", " ", $str); // convert multiple spaces into one
      $str = substr($str, 0, 20); // max length 10
      $str = trim($str); // trim
      $str = preg_replace("/\s/", $delim, $str); // add delimiter
      $str = strtolower($str); // make lower
      return $str;
    }
    /////
  }
}

?>