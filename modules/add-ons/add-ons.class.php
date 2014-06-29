<?php
/**
 * clienteleAddOn class
 *
 * Displays AddOns on the dashboard
 *
 * @package clientele
 */
if (!class_exists('clienteleAddOns')) {
  /**
   * Displays extension modules on the dashboard
   *
   */
  class clienteleAddOns extends clienteleModule {
    /**
     * __construct
     */
    function __construct() {
      //Setup default options and register module.
      $module_url = $this->get_module_url(__FILE__);
      $options = array(
        'title' => __('Add Ons', 'clientele'),
        'short_description' => __('Download additional modules or plugins.', 'clientele'),
        'module_url' => $module_url,
        'slug' => 'addons',
        'version' => '1.0',
        'default_options' => array(
          'enabled' => 'on',
          'feed_address' => 'https://raw.githubusercontent.com/MadeByMike/Clientele/master/clientele-feed.json',
          'dash_safe' => array(
            'a' => array(
              'href' => array(),
              'title' => array(),
              'class' => array(),
              'style' => array()
            ),
            'div' => array(
              'class' => array(),
              'style' => array()
            ),
            'img' => array(
              'class' => array(),
              'style' => array(),
              'src' => array(),
              'alt' => array()
            ),
            'h2' => array(
              'class' => array(),
              'style' => array()
            ),
            'h3' => array(
              'class' => array(),
              'style' => array()
            ),
            'h4' => array(
              'class' => array(),
              'style' => array()
            ),
            'p' => array(
              'class' => array(),
              'style' => array()
            ),
            'br' => array(
              'class' => array(),
              'style' => array()
            ),
            'em' => array(
              'class' => array(),
              'style' => array()
            ),
            'span' => array(
              'class' => array(),
              'style' => array()
            ),
            'strong' => array(
              'class' => array(),
              'style' => array()
            )
          )
        ),
        'dashboard_cb' => 'addons_dashboard',
        'order' => 9999
      );
      $this->module = $this->register_module('add_ons', $options);
    }

    /**
     *
     * @author Mike
     * @since  1.0
     *
     */
    function init() {
      // This function is called immediately after the module is registered
      // This is a good time to call some hooks and do your addon magic
      add_action('admin_enqueue_scripts', array($this, 'addon_scripts')); // Scripts specific to this module. All global scripts are enqueued in the Module class.
      add_action('admin_print_styles', array($this, 'addon_styles')); // Styles specific to this module. All global styles are enqueued in the Module class.
    }

    /**
     * @author Mike
     * @since  1.0
     *
     */
    function addon_scripts() {
      global $pagenow, $typenow;
      if (empty($typenow) && !empty($_GET['post_type'])) {
        $typenow = get_post($_GET['post_type']);
      }
      if (!empty($_GET['page'])) {
        $page = $_GET['page'];
      }
      if ($pagenow == 'admin.php' && $page == 'clientele-dashboard') {
        wp_enqueue_script('clientele-addon-scripts', $this->get_module_url(__FILE__) . 'lib/clientele-addon-scripts.js', array('jquery'), clientele_VERSION);
      }
    }

    /**
     * @author Mike
     * @since  1.0
     *
     */
    function addon_styles() {
      global $pagenow, $typenow;
      if (empty($typenow) && !empty($_GET['post_type'])) {
        $typenow = get_post($_GET['post_type']);
      }
      if (!empty($_GET['page'])) {
        $page = $_GET['page'];
      }
      if ($pagenow == 'admin.php' && $page == 'clientele-dashboard') {
        wp_enqueue_style('clientele-addon-styles', $this->get_module_url(__FILE__) . 'lib/clientele-addon-styles.css', false, clientele_VERSION);
      }
    }

    /**
     * @author Mike
     * @since  1.0
     *
     *  Overrides the custom dashboard to display feed a from with extensions
     *
     * @author Mike
     * @since  1.0
     *
     * @param $mod_data
     *
     */
    function addons_dashboard($mod_data) {
      global $clientele;
      // Page controller for add-on page
      $feed_address = $this->module->options->feed_address;
      //delete_transient('clientele-addons-feed')
      if (false === ($json = get_transient('clientele-addons-feed'))) {
        $feed = wp_remote_get($feed_address);
        if (!is_wp_error($feed)) {
          if (isset($feed['body']) && strlen($feed['body']) > 0) {
            try {
              $json = json_decode($feed['body']);
            } catch (Exception $ex) {
              $json = false;
            }
            if ($json) {
              $json = $feed['body'];
              set_transient('clientele-addons-feed', $json, 60 * 60 * 24);
            }
          }
        }
      }
      $addons = array();
      if ($json) {
        $extensions = json_decode($json);
        usort($extensions, array($this, "sort_dashboard"));
      } else {
        //$error = '<div class="error"><p>' . __('There was an error retrieving the list of add-ons from the server. Please try again later.', 'clientele') . '</p></div>';
      }
      if (isset($error)) {
        echo $error;
      }
      if (isset($extensions)) {
        foreach ($extensions as $extension) {
          $slug = $extension->slug;
          if (!isset($clientele->modules->$slug)) {
            $addons[] = $extension->dashboard;
          } else {
            do_action('clientele_addons-' . $slug, $extension);
          }
        }
      }
      if (!empty($addons)) {
        echo '<div class="clientele-addons">';
        echo '<h2>' . __('Add-ons', 'clientele') . '</h2>';
        foreach ($addons as $addon) {
          $dash_safe = (array) $this->module->options->dash_safe;
          echo wp_kses($addon, $dash_safe);
        }
        echo '</div>';
      }
    }
  }
}