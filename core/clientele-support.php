<?php
/**
 * This is a last minute inclusion largely borrowed from EDD by pipin williamson
 * I will further improve and customise this page as I discover what support is required
 */
if ('update' == isset($_POST['action'])) {
  $options = explode(',', wp_unslash($_POST['page_options']));
  if ($options) {
    foreach ($options as $option) {
      $option = trim($option);
      $value = null;
      if (isset($_POST[$option])) {
        $value = $_POST[$option];
        if (!is_array($value)) {
          $value = trim($value);
        }
        if ((json_decode($value) != null)) {
          $value = json_decode($value);
        } else {
          $value = wp_unslash($value);
        }
      }
      update_option($option, $value);
    }
  }
  /**
   * Handle settings errors and return to options page
   */
  // If no settings errors were registered add a general 'updated' message.
  if (!count(get_settings_errors())) {
    add_settings_error('general', 'settings_updated', __('Settings saved.'), 'updated');
  }
  set_transient('settings_errors', get_settings_errors(), 30);
  /**
   * Redirect back to the settings page that was submitted
   */
  $goback = add_query_arg('settings-updated', 'true', wp_get_referer());
  wp_redirect($goback);
  exit;
}


global $wpdb;

if (!class_exists('Browser')) {
  require_once clientele_ROOT . '/common/lib/browser.php';
}

$browser = new Browser();
if (get_bloginfo('version') < '3.4') {
  $theme_data = get_theme_data(get_stylesheet_directory() . '/style.css');
  $theme = $theme_data['Name'] . ' ' . $theme_data['Version'];
} else {
  $theme_data = wp_get_theme();
  $theme = $theme_data->Name . ' ' . $theme_data->Version;
}

// Try to identify the hosting provider
$host = false;
if (defined('WPE_APIKEY')) {
  $host = 'WP Engine';
} elseif (defined('PAGELYBIN')) {
  $host = 'Pagely';
}
?>
<div class="wrap">
  <h2><?php _e('System Information', 'clientele') ?></h2><br />

  <textarea readonly="readonly" style="width:100%; height: 600px;" onclick="this.focus();this.select()" id="system-info-textarea" title="<?php _e('To copy the system info, click below then press Ctrl + C (PC) or Cmd + C (Mac).', 'clientele'); ?>"> ### Begin System Info ###

    Multisite:                <?php echo is_multisite() ? 'Yes' . "\n" : 'No' . "\n" ?>

    SITE_URL:                 <?php echo site_url() . "\n"; ?>
    HOME_URL:                 <?php echo home_url() . "\n"; ?>

    Clientele Version:              <?php echo clientele_VERSION . "\n"; ?>
    WordPress Version:        <?php echo get_bloginfo('version') . "\n"; ?>
    Permalink Structure:      <?php echo get_option('permalink_structure') . "\n"; ?>
    Active Theme:             <?php echo $theme . "\n"; ?>
    <?php if ($host) : ?>
      Host:                     <?php echo $host . "\n"; ?>
    <?php endif; ?>

    Registered Post Stati:    <?php echo implode(', ', get_post_stati()) . "\n\n"; ?>

    <?php echo $browser; ?>

    PHP Version:              <?php echo PHP_VERSION . "\n"; ?>
    MySQL Version:            <?php echo mysql_get_server_info() . "\n"; ?>
    Web Server Info:          <?php echo $_SERVER['SERVER_SOFTWARE'] . "\n"; ?>

    PHP Safe Mode:            <?php echo ini_get('safe_mode') ? "Yes" : "No\n"; ?>
    PHP Memory Limit:         <?php echo ini_get('memory_limit') . "\n"; ?>
    PHP Upload Max Size:      <?php echo ini_get('upload_max_filesize') . "\n"; ?>
    PHP Post Max Size:        <?php echo ini_get('post_max_size') . "\n"; ?>
    PHP Upload Max Filesize:  <?php echo ini_get('upload_max_filesize') . "\n"; ?>
    PHP Time Limit:           <?php echo ini_get('max_execution_time') . "\n"; ?>
    PHP Max Input Vars:       <?php echo ini_get('max_input_vars') . "\n"; ?>

    WP_DEBUG:                 <?php echo defined('WP_DEBUG') ? WP_DEBUG ? 'Enabled' . "\n" : 'Disabled' . "\n" : 'Not set' . "\n" ?>

    WP Table Prefix:          <?php echo "Length: " . strlen($wpdb->prefix);
    echo " Status:";
    if (strlen($wpdb->prefix) > 16) {
      echo " ERROR: Too Long";
    } else {
      echo " Acceptable";
    }
    echo "\n"; ?>

    Show On Front:            <?php echo get_option('show_on_front') . "\n" ?>
    Page On Front:            <?php $id = get_option('page_on_front');
    echo get_the_title($id) . ' (#' . $id . ')' . "\n" ?>
    Page For Posts:           <?php $id = get_option('page_for_posts');
    echo get_the_title($id) . ' (#' . $id . ')' . "\n" ?>

    Session:                  <?php echo isset($_SESSION) ? 'Enabled' : 'Disabled'; ?><?php echo "\n"; ?>
    Session Name:             <?php echo esc_html(ini_get('session.name')); ?><?php echo "\n"; ?>
    Cookie Path:              <?php echo esc_html(ini_get('session.cookie_path')); ?><?php echo "\n"; ?>
    Save Path:                <?php echo esc_html(ini_get('session.save_path')); ?><?php echo "\n"; ?>
    Use Cookies:              <?php echo ini_get('session.use_cookies') ? 'On' : 'Off'; ?><?php echo "\n"; ?>
    Use Only Cookies:         <?php echo ini_get('session.use_only_cookies') ? 'On' : 'Off'; ?><?php echo "\n"; ?>

    WordPress Memory Limit:   <?php echo (let_to_num(WP_MEMORY_LIMIT) / (1024)) . "MB"; ?><?php echo "\n"; ?>
    DISPLAY ERRORS:           <?php echo (ini_get('display_errors')) ? 'On (' . ini_get('display_errors') . ')' : 'N/A'; ?><?php echo "\n"; ?>
    FSOCKOPEN:                <?php echo (function_exists('fsockopen')) ? __('Your server supports fsockopen.', 'clientele') : __('Your server does not support fsockopen.', 'clientele'); ?><?php echo "\n"; ?>
    cURL:                     <?php echo (function_exists('curl_init')) ? __('Your server supports cURL.', 'clientele') : __('Your server does not support cURL.', 'clientele'); ?><?php echo "\n"; ?>
    SOAP Client:              <?php echo (class_exists('SoapClient')) ? __('Your server has the SOAP Client enabled.', 'clientele') : __('Your server does not have the SOAP Client enabled.', 'clientele'); ?><?php echo "\n"; ?>
    SUHOSIN:                  <?php echo (extension_loaded('suhosin')) ? __('Your server has SUHOSIN installed.', 'clientele') : __('Your server does not have SUHOSIN installed.', 'clientele'); ?><?php echo "\n"; ?>

    Modules:

    <?php
    // Show modules
    $dir = clientele_ROOT . '/modules/*';
    if (!empty($dir)) {
      foreach (glob($dir) as $file) {
        echo "-" . basename($file) . "\n";
      }
    } else {
      echo 'No modules found';
    }
    ?>

    ACTIVE PLUGINS:

    <?php
    $plugins = get_plugins();
    $active_plugins = get_option('active_plugins', array());

    foreach ($plugins as $plugin_path => $plugin) {
      // If the plugin isn't active, don't show it.
      if (!in_array($plugin_path, $active_plugins)) {
        continue;
      }
      echo $plugin['Name'] . ': ' . $plugin['Version'] . "\n";
    }

    if (is_multisite()) :
      ?>

      NETWORK ACTIVE PLUGINS:

      <?php
      $plugins = wp_get_active_network_plugins();
      $active_plugins = get_site_option('active_sitewide_plugins', array());
      foreach ($plugins as $plugin_path) {
        $plugin_base = plugin_basename($plugin_path);
        // If the plugin isn't active, don't show it.
        if (!array_key_exists($plugin_base, $active_plugins)) {
          continue;
        }
        $plugin = get_plugin_data($plugin_path);
        echo $plugin['Name'] . ' :' . $plugin['Version'] . "\n";
      }

    endif;

    ?>
    ### End System Info ### </textarea>
</div>
<?php

function let_to_num($v) {
  $l = substr($v, -1);
  $ret = substr($v, 0, -1);
  switch (strtoupper($l)) {
    case 'P': // fall-through
    case 'T': // fall-through
    case 'G': // fall-through
    case 'M': // fall-through
    case 'K': // fall-through
      $ret *= 1024;
      break;
    default:
      break;
  }
  return $ret;
}

?>
<h2><?php _e('Clientele Options', 'clientele') ?></h2><br />

<form name="clientele-options" action="" method="post" id="clientele-options">
  <input type="hidden" name="action" value="update" />
  <table class="form-table">
    <?php
    $options = $wpdb->get_results("SELECT * FROM $wpdb->options WHERE `option_name` LIKE 'clientele%' ORDER BY `option_name`");

    foreach ((array) $options as $option) :
      $disabled = false;
      if ($option->option_name == '') {
        continue;
      }
      if (is_serialized($option->option_value)) {
        if (is_serialized_string($option->option_value)) {
          $value = maybe_unserialize($option->option_value);
        } else {
          $value = print_r(json_encode(maybe_unserialize($option->option_value)), true);
        }
      } else {
        $value = $option->option_value;
        $class = 'all-options';
      }
      $options_to_update[] = $option->option_name;
      $name = esc_attr($option->option_name);
      echo "
    <tr>
        <th scope='row'><label for='$name'>" . esc_html($option->option_name) . "</label></th>
    <td>";
      if (strpos($value, "\n") !== false) {
        echo "<textarea class='' name='$name' id='$name' cols='30' rows='8'>" . esc_textarea($value) . "</textarea>";
      } else {
        echo "<input class='regular-text' type='text' name='$name' id='$name' value='" . esc_attr($value) . "'" . disabled($disabled, true, false) . " />";
      }
      echo "</td>
</tr>";
    endforeach;
    ?>
  </table>
  <input type="hidden" name="page_options" value="<?php echo esc_attr(implode(',', $options_to_update)); ?>" />

  <?php submit_button(__('Update Options', 'clientele'), 'primary', 'update-clientele-options'); ?>
</form>