<?php
/**
 * Module class
 *
 * Another heavy lifting part of the core, controls module options, callbacks, menus and much more.
 *
 * @package clientele
 */
if (!class_exists('clienteleModule')) {
  /**
   * Class clienteleModule
   */
  class clienteleModule {
    /**
     * __construct
     */
    function __construct() {
      add_action('admin_print_styles', array($this, 'load_clientele_admin_styles'));
      add_action('admin_enqueue_scripts', array($this, 'load_clientele_admin_scripts'));
      add_action('wp_enqueue_scripts', array($this, 'load_clientele_scripts'));
      add_action('admin_enqueue_scripts', array($this, 'clientele_setup_pointers'));
      add_action('clientele_pointers', array($this, 'initial_clientele_pointer'));
      add_action('clientele_pointers-clientele-client_page_clients-settings', array($this, 'client_settings_pointers'));
      add_action('wp_enqueue_styles', array($this, 'load_clientele_styles'));
      add_action('admin_menu', array($this, 'create_clientele_menu'));
      add_action('wp_ajax_change_module_state', array($this, 'ajax_change_module_state'));
      add_action('module_change_state', array($this, 'check_module_dependencies'));
      add_action('clientele_initialised', array($this, 'check_module_dependencies'));
    }

    /**
     * Uses the order attribute to sort modules
     *
     * @author Mike
     * @since  1.0
     *
     * @param $a
     * @param $b
     *
     * @return int
     */
    static function sort_dashboard($a, $b) {
      $al = intval($a->order);
      $bl = intval($b->order);
      if ($al == $bl) {
        return 0;
      }
      return ($al > $bl) ? +1 : -1;
    }

    /**
     * Creates the Clientele menu
     *
     * @author Mike
     * @since  1.0
     *
     */
    function create_clientele_menu() {
      // Creates a top level menu below settings,
      // Adds the dashboard and a menu item for each module
      global $clientele, $wp_version;
      add_menu_page(__('Clientele', 'clientele'), __('Clientele', 'clientele'), 'publish_pages', 'clientele-dashboard', array(
          &$this,
          'dashboard_controller'
        ), (($wp_version >= 3.8) ? 'dashicons-dashboard' : clientele_URL . '/common/img/icon-dash.png'));
      add_submenu_page('clientele-dashboard', __('Dashboard', 'clientele'), __('Dashboard', 'clientele'), 'publish_pages', 'clientele-dashboard', array(&$this, 'dashboard_controller'));
      $modules_array = (array) $clientele->modules;
      usort($modules_array, array($this, "sort_dashboard"));
      foreach ($modules_array as $key => $mod_data) {
        if (isset($mod_data->link_text)) {
          $linkText = $mod_data->link_text;
        } else {
          $linkText = $mod_data->title . ' ' . __('Settings', 'clientele');
        }
        if (isset($mod_data->options->enabled) && $mod_data->options->enabled == 'on' && $mod_data->page_cb) {
          if (isset($mod_data->cb_restrict)) {
            add_submenu_page('clientele-dashboard', $linkText, $linkText, $mod_data->cb_restrict, $mod_data->page_slug, array(&$this, 'page_controller'));
          } else {
            add_submenu_page('clientele-dashboard', $linkText, $linkText, 'publish_pages', $mod_data->page_slug, array(&$this, 'page_controller'));
          }
        }
      }
    }

    /**
     * Loads global scripts required on all non-admin pages
     *
     * @author Mike
     * @since  1.0
     *
     */
    function load_clientele_scripts() {
      wp_enqueue_script('clientele-js', clientele_URL . 'common/js/scripts.js', array('jquery'), clientele_VERSION);
    }

    /**
     * Loads global styles required on all non-admin pages
     *
     * @author Mike
     * @since  1.0
     *
     */
    function load_clientele_styles() {
      //No global styles required for now
      //wp_enqueue_style( 'clientele-css', clientele_URL . 'common/css/styles.css', false, clientele_VERSION );
    }

    /**
     * Loads global scripts required on all admin pages
     *
     * @author Mike
     * @since  1.0
     *
     */
    function load_clientele_admin_scripts() {
      wp_enqueue_script('clientele-admin-js', clientele_URL . 'common/js/admin-scripts.js', array('jquery'), clientele_VERSION);
    }

    /**
     * Loads global styles required on all admin pages
     *
     * @author Mike
     * @since  1.0
     *
     */
    function load_clientele_admin_styles() {
      wp_enqueue_style('wp-pointer');
      wp_enqueue_style('clientele-admin-css', clientele_URL . 'common/css/admin-styles.css', false, clientele_VERSION);
      wp_enqueue_style('clientele-admin-fa-css', clientele_URL . 'common/lib/fa/css/font-awesome.css', false, clientele_VERSION);
    }

    /**
     *
     * @author Mike
     * @since  1.0
     *
     */
    function clientele_setup_pointers() {
      // Don't run on WP < 3.3
      if (get_bloginfo('version') < '3.3') {
        return;
      }
      $screen = get_current_screen();
      $screen_id = $screen->id;
      //
      $admin_pointers = apply_filters('clientele_pointers', array());
      // Get pointers for this screen
      $screen_pointers = apply_filters('clientele_pointers-' . $screen_id, array());
      $pointers = array_merge($admin_pointers, $screen_pointers);
      if (!$pointers || !is_array($pointers)) {
        return;
      }
      // Get dismissed pointers
      $dismissed = explode(',', (string) get_user_meta(get_current_user_id(), 'dismissed_wp_pointers', true));
      $valid_pointers = array();
      // Check pointers and remove dismissed ones.
      foreach ($pointers as $pointer_id => $pointer) {
        // Sanity check
        if (in_array($pointer_id, $dismissed) || empty($pointer) || empty($pointer_id) || empty($pointer['target']) || empty($pointer['options'])) {
          continue;
        }
        $pointer['pointer_id'] = $pointer_id;
        // Add the pointer to $valid_pointers array
        $valid_pointers['pointers'][] = $pointer;
      }
      // No valid pointers? Stop here.
      if (empty($valid_pointers)) {
        return;
      }
      // Add pointers style to queue.
      wp_enqueue_style('wp-pointer');
      wp_enqueue_script('wp-pointer');
      // Add pointers script to queue. Add custom script.
      wp_enqueue_script('clientele-pointer', clientele_URL . 'common/js/admin-pointers.js', array('wp-pointer'));
      // Add pointer options to script.
      wp_localize_script('clientele-pointer', 'clientelePointer', $valid_pointers);
    }

    function client_settings_pointers($pointers) {
      $pointers['clientele-client-fields'] = array(
        'target' => '#addFieldButton',
        'options' => array(
          'content' => '<h3> ' . __('Add a client field', 'clientele') . ' </h3> <p> ' . __('You can add new client fields here. Select from one of the available field types and click \'Add field\'.', 'clientele') . ' </p>',
          'position' => array(
            'edge' => 'left',
            'align' => 'center'
          )
        )
      );
      return $pointers;
    }

    function initial_clientele_pointer($pointers) {
      $pointers['clientele-welcome'] = array(
        'target' => '#toplevel_page_clientele-dashboard',
        'options' => array(
          'content' => '<h3> ' . __('New Feature', 'clientele') . ' </h3> <p> ' . __('Welcome to Clientele! This is the dashboard where you can view installed modules, add new modules and access settings.', 'clientele') . ' </p><p> ' . __('One of the first things you might want to do is', 'clientele') . ' <a class="dismiss-clientele-welcome" href="' . get_admin_url(null, 'admin.php?page=clients-settings') . '">' . __('setup your client fields.', 'clientele') . '</a></p>',
          'position' => array(
            'edge' => 'left',
            'align' => 'center'
          )
        )
      );
      return $pointers;
    }

    /**
     * Ajaxness for turning modules on or off
     *
     * @author Mike
     * @since  1.0
     *
     */
    function ajax_change_module_state() {
      //Enable or disable modules
      global $clientele;
      if (!current_user_can('manage_options')) {
        wp_die('Cheatin&#8217; uh?');
      }
      if (!isset($_POST['module_action'], $_POST['slug'])) {
        die('');
      }
      $module_action = sanitize_key($_POST['module_action']);
      $slug = sanitize_key($_POST['slug']);
      $module = $clientele->modules->$slug;
      if (!$module) {
        die('Module not found');
      }
      $quite = false;
      if (isset($_POST['confirmation'])) {
        $quite = true;
      }
      if ($module_action == 'enable') {
        $dependants = '';
        if (isset($module->requires)) {
          $requires = explode(", ", $module->requires);
          foreach ($requires as $required) {
            if (!($this->is_module_enabled($required))) {
              $dependants[] = $required;
            }
          }
        }
        if ($dependants) {
          $alert = __('The ', 'clientele') . $module->name . __(' modules requires other modules including:', 'clientele') . "\n";
          $alert .= implode(", ", $dependants) . " \n";
          $alert .= __('Please enable all required modules first.', 'clientele');
          $return['alert'] = $alert;
          echo json_encode($return);
        } else {
          $clientele->update_module_option($module->name, 'enabled', 'on');
          do_action('module_change_state');
        }
      } else {
        if ($module_action == 'disable') {
          $dependencies = '';
          foreach ($clientele->modules as $mod_name => $mod_data) {
            if (isset($mod_data->requires)) {
              $requires = explode(", ", $mod_data->requires);
              foreach ($requires as $required) {
                if ($required == $module->name && $this->is_module_enabled($mod_name)) {
                  $dependencies[] = $mod_data->name;
                }
              }
            }
          }
          if ($dependencies && $quite == false) {
            $warning = __('WARNING: Disabling this module will also disable ', 'clientele');
            $warning .= join(' and ', array_filter(array_merge(array(join(', ', array_slice($dependencies, 0, -1))), array_slice($dependencies, -1)))) . ".";
            $return['warning'] = $warning;
            $return['slug'] = $slug;
            echo json_encode($return);
          } else {
            $clientele->update_module_option($module->name, 'enabled', 'off');
            do_action('module_change_state');
          }
        } else { // Incorrect module action
          die(-1);
        }
      }
      die(0); // Return results echoed
    }

    /**
     * Checks modules dependencies when disabling a module
     * Disables any modules that require the one being turned off
     *
     * @author Mike
     * @since  1.0
     *
     */
    function check_module_dependencies() {
      global $clientele;
      foreach ($clientele->modules as $mod_name => $mod_data) {
        if (isset($mod_data->requires)) {
          $requires = explode(", ", $mod_data->requires);
          foreach ($requires as $required) {
            if (!($this->is_module_enabled($required))) {
              $clientele->update_module_option($mod_name, 'enabled', 'off');
            }
          }
        }
      }
    }

    /**
     * Handles the rendering of dashboard items
     *
     * @author Mike
     * @since  1.0
     *
     */
    function dashboard_controller() {
      global $clientele;
      $this->print_default_header();
      echo '<div class="clientele-dashboard">';
      echo '<h2>' . __('Installed modules', 'clientele') . '</h2>';
      if (!count($clientele->modules)) {
        echo '<div class="error fade"><p>' . __('Golly gosh! There are no modules. How did this happen? Have you been playing with the source code?', 'clientele') . '</p></div>';
      } else {
        echo '<div class="clientele-modules">';
        $modules_array = (array) $clientele->modules;
        usort($modules_array, array($this, "sort_dashboard"));
        foreach ($modules_array as $key => $mod_data) {
          $dashboard_cb = $mod_data->dashboard_cb;
          $mod_name = $mod_data->name;
          $clientele->$mod_name->$dashboard_cb($mod_data);
        }
        echo '</div>';
      }
      echo '</div>';
      $this->print_default_footer();
      // Dashboard loaded hook
      do_action('clientele_dashboard_loaded');
    }

    /**
     * Handles rendering of settings pages
     *
     * @author Mike
     * @since  1.0
     *
     * @return bool
     */
    function page_controller() {
      global $clientele;
      // Add hook before page
      // Check requested page exists
      $requested_module = $this->get_module_by_page($_GET['page']);
      if (!$requested_module) {
        wp_die('Module not registered');
      }
      $page_callback = $requested_module->page_cb;
      $requested_module_name = $requested_module->name;
      // Don't show the settings page for the module if the module isn't activated
      if (!$this->is_module_enabled($requested_module_name)) {
        echo '<div class="error"><p>' . __('Module not enabled. Please enable it from the ', 'clientele') . sprintf('<a href="%1$s">' . __('Clientele dashboard', 'clientele') . '</a>', add_query_arg('page', 'clientele-dashboard', get_admin_url(null, 'admin.php')) . '</p></div>');
        return true;
      }
      // Having passed checks render the page
      $this->print_default_header($requested_module);
      $clientele->$requested_module_name->$page_callback();
      $this->print_default_footer($requested_module);
      return true;
    }

    /**
     * Prepends clientele admin pages
     *
     * @author Mike
     * @since  1.0
     *
     * @param string $current_module
     */
    function print_default_header($current_module = '') {
      echo '<div class="clientele-wrapper wrap">';
    }

    /**
     * After clientele admin pages
     *
     * @author Mike
     * @since  1.0
     *
     * @param string $current_module
     */
    function print_default_footer($current_module = '') {
      //echo '<div class="clientele-footer">Clientele was <a href="http://madebymike.com.au">Made by Mike</a>.</div>';
      echo '</div> <!-- / clientele-wrapper -->';
    }

    /**
     * The default callback for rendering modules on the dashboard
     *
     * @author Mike
     * @since  1.0
     *
     * @param $mod_data
     */
    function default_dashboard_element($mod_data) {
      //Most modules will have the same style dashboard element. This is a shortcut for a default module with a settings page and an enable\disable button.
      global $clientele;
      $classes = array('clientele-module');
      if (!isset($mod_data->options->enabled)) {
        $mod_data->options->enabled = 'on';
      }
      if ($mod_data->options->enabled == 'on') {
        $classes[] = 'clientele-enabled';
      } else {
        $classes[] = 'clientele-disabled';
      }
      echo '<div class="' . implode(' ', $classes) . '"  id="' . $mod_data->slug . '">';
      echo '<form method="get" action="' . admin_url('options.php') . '">';
      echo '<h3>' . esc_html($mod_data->title) . '</h3>';
      $checked = '';
      if ($mod_data->options->enabled == 'on') {
        $checked = 'checked';
      }
      if (current_user_can('manage_options')) {
        echo '<fieldset class="toggle">';
        echo '<input id="toggle-' . $mod_data->slug . '" class="clientele-enable-disable" type="checkbox" ' . $checked . ' />';
        echo '<label for="toggle-' . $mod_data->slug . '">enable\disable</label>';
        echo '<span class="toggle-button"></span>';
        echo '</fieldset>';
      }
      echo '<p>' . esc_html($mod_data->short_description) . '</p>';
      echo '<p class="clientele-buttons">';
      if ($mod_data->page_cb && (!(isset($mod_data->cb_restrict)) || current_user_can($mod_data->cb_restrict))) {
        $configure_url = add_query_arg('page', $mod_data->page_slug, get_admin_url(null, 'admin.php'));
        echo '<a type="button" href="' . $configure_url . '" class="button';
        if ($mod_data->options->enabled == 'off') {
          echo ' hidden" style="display:none;';
        }
        echo '">';
        if (isset($mod_data->dash_text)) {
          echo $mod_data->dash_text;
        } else {
          echo 'Settings';
        };
        echo '</a>';
      }
      echo '</p>';
      echo '</form>';
      echo '</div>';
    }

    /**
     * Returns the url of the module folder
     *
     * @author Mike
     * @since  1.0
     *
     * @param $file
     *
     * @return mixed
     */
    function get_module_url($file) {
      $module_url = plugins_url('/', $file);
      return trailingslashit($module_url);
    }

    /**
     * Registers a modules options
     *
     * @author Automatic\Mike
     * @since  1.0
     *
     * @param string $name
     * @param array  $options
     *
     * @return bool
     */
    public function register_module($name, $options = array()) {
      global $clientele;
      // A title, slug and name are minimum requirements for all modules
      if (!isset($options['title'], $name, $options['slug'])) {
        return false;
      }
      $defaults = array(
        'default_options' => array(
          'enabled' => 'on'
        ),
        'page_cb' => false,
        'dashboard_cb' => false
      );
      $options = array_merge($defaults, $options);
      $options['name'] = $name;
      if (!isset($options['page_slug'])) {
        $options['page_slug'] = $options['slug'] . '-settings';
      }
      $clientele->modules->$name = (object) $options; // Typecast to obj
      do_action('clientele_module_registered', $name);
      return $clientele->modules->$name;
    }

    /**
     * Simple check to see if a module is required
     *
     * @author Mike
     * @since  1.0
     *
     * @param $mod_name
     *
     * @return bool
     */
    function is_module_enabled($mod_name) {
      global $clientele;
      return isset($clientele->$mod_name) && $clientele->$mod_name->module->options->enabled == 'on';
    }

    /**
     * This gets a module by page ;)
     *
     * @param $page
     *
     * @return bool
     */
    function get_module_by_page($page) {
      global $clientele;
      foreach ($clientele->modules as $mod_name => $mod_data) {
        if ($mod_data->page_slug == $page) {
          return $clientele->modules->$mod_name;
        }
      }
      return false;
    }
  }
}