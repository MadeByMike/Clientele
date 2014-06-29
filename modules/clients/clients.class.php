<?php
/**
 * clienteleClients class
 *
 * @package clientele
 *
 **/
if (!class_exists('clienteleClients')) {
  /**
   * Class Clients
   */
  class clienteleClients extends clienteleModule {
    /**
     * __construct
     */
    function __construct() {
      //Setup default options and register client module.
      $module_url = $this->get_module_url(__FILE__);
      $options = array(
        'title' => __('Clients', 'clientele'),
        'access_control' => array(
          'module_access' => 'publish_pages',
          'options_access' => 'manage_options'
        ),
        'short_description' => __('Manage clients and contacts, choose what information you capture by configuring "client fields". Use this informaiton for reference or download add-ons and use it for a wide range of purposes.', 'clientele'),
        'module_url' => $module_url,
        'slug' => 'clients',
        'version' => '1.0',
        'default_options' => array(
          'enabled' => 'on',
          'client_fields' => array( // These are the defaults created when plugin is first installed. Are they somewhere close to accurate?
            'email' => array(
              'label' => __('Email', 'clientele'),
              'type' => 'Email',
              'options' => '',
              'required' => 1
            ),
            'first-name' => array(
              'label' => __('First Name', 'clientele'),
              'type' => 'Text',
              'options' => ''
            ),
            'last-name' => array(
              'label' => __('Last Name', 'clientele'),
              'type' => 'Text',
              'options' => ''
            ),
            'address' => array(
              'label' => __('Address', 'clientele'),
              'type' => 'Address',
              'options' => ''
            ),
            'telephone' => array(
              'label' => __('Telephone', 'clientele'),
              'type' => 'Phone',
              'options' => ''
            )
          )
        ),
        'page_cb' => 'clients_settings_page',
        'cb_restrict' => 'manage_options',
        'link_text' => __('Client Fields', 'clientele'),
        'dashboard_cb' => 'clients_dashboard_element',
        'order' => 100
      );
      $this->module = $this->register_module('clients', $options);
      // init is called after the module is registered
    }

    /**
     * @author Mike
     * @since  1.0
     *
     * Create the client custom post type.
     * WordPress custom post types are an effective way of managing clients however the term 'post' can be a slightly misleading way of thinking about the client object.
     * A post is still the default object in WordPress. Clients share many properties of posts but the default behaviours are extensively changed to deal with differences.
     * Especially around the presentation of and publishing of posts. Client 'posts' are setup in a way to ensure that private data is never published or accessible for non-admin.
     *
     */
    function init() {
      global $wp_version;
      $labels = array(
        'name' => __('Clients', 'clientele'),
        'singular_name' => __('Client', 'clientele'),
        'add_new' => __('Add New', 'clientele'),
        'add_new_item' => __('Add new Client', 'clientele'),
        'edit_item' => __('Edit Client', 'clientele'),
        'new_item' => __('New Client', 'clientele'),
        'view_item' => __('View Client', 'clientele'),
        'search_items' => __('Find client', 'clientele'),
        'not_found' => __('No clients found', 'clientele'),
        'not_found_in_trash' => __('No clients found', 'clientele')
      );
      $args = array(
        'labels' => $labels,
        'rewrite' => array(
          'slug' => 'unsubscribe',
          'with_front' => true,
          'feed' => false
        ),
        // This is the publicly accessible part of a published client 'post' it is used for unsubscribing from emails.
        // Technically this should be in the email module as the client module should not even be aware of the need for this -if it had self awareness ToDo: add self awareness.
        // But I can't add this rewrite after the post type is defined, ...can I?
        'show_ui' => true,
        'show_in_admin_bar' => true,
        'public' => true,
        'query_var' => true,
        'capability_type' => 'page',
        'menu_icon' => (($wp_version >= 3.8) ? 'dashicons-id-alt' : clientele_URL . '/common/img/icon-client.png'),
        'supports' => array('')
      );
      register_post_type('clientele-client', $args);
      flush_rewrite_rules(); // Must have!
      // 'Client' post type is registered.
      // Let's register some custom status'.
      // Unfortunately these are fairly new and not well supported in WP but worth sticking with.
      // A bit of manual lifting is required to add functionality around them and changes are likely in new WP versions.
      if (function_exists('register_post_status')) {
        $custom_statuses[] = array('slug' => 'active', 'name' => __('Active', 'clientele'));
        $custom_statuses[] = array('slug' => 'unsubscribed', 'name' => __('Unsubscribed', 'clientele'));
        foreach ($custom_statuses as $status) {
          register_post_status($status['slug'], array(
            'label' => $status['name'],
            'protected' => true,
            '_builtin' => false,
            'public' => false,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'label_count' => _n_noop("{$status['name']} <span class='count'>(%s)</span>", "{$status['name']} <span class='count'>(%s)</span>")
          ));
        }
      }
      // Now register all the actions and filters to customise the viewing, saving and editing the client 'post' type.
      add_action('admin_enqueue_scripts', array($this, 'clients_scripts')); // Scripts specific to client pages. All global scripts are enqueued in the Module class.
      add_action('admin_print_styles', array($this, 'clients_styles')); // Styles specific to client pages. All global styles are enqueued in the Module class.
      add_filter("mce_external_plugins", array($this, "add_custom_tinymce_plugin"));
      add_action('admin_head', array($this, 'clientele_setup_client_edit_page')); // Registers custom meta boxes, removes some post defaults and other edit page customisation.
      add_action('admin_menu', array($this, 'clientele_client_add_menu'));
      add_action('save_post', array($this, 'clientele_save_client_meta')); // Handles saving of custom meta boxes
      add_action('wp_before_admin_bar_render', array($this, 'remove_admin_bar_link')); // Removes the view client link in the admin bar
      add_action('draft_to_active', array($this, 'new_client'), 2);
      add_action('before_delete_post', array($this, 'delete_client'), 10);
      // View Clients Page - Customising the shizzle out of this!
      add_filter('manage_edit-clientele-client_columns', array($this, 'add_new_client_columns')); // Customise the columns on the view clients page
      add_filter('manage_edit-clientele-client_sortable_columns', array($this, 'sort_new_client_columns')); // Customise the columns on the view clients page
      add_filter('post_row_actions', array($this, 'remove_quick_edit'), 10, 2); // Removes the quick edit option on the view clients page
      add_filter('bulk_actions-edit-clientele-client', array($this, 'remove_bulk_actions')); // Removes the edit option from the bulk actions on the view clients page
      add_filter('parse_query', array($this, 'clientele_do_filter')); // Implement the filters on the view clients page
      add_filter('get_search_query', array($this, 'clientele_set_search_query')); // Implement the filters on the view clients page
      add_action('manage_clientele-client_posts_custom_column', array($this, 'manage_client_columns'), 10, 2); // Retrives data for custom columns
      add_action('restrict_manage_posts', array($this, 'clientele_filter_client_columns')); // Adds the filters to the view clients page
      add_filter('post_updated_messages', array($this, 'client_updated_messages'));
      add_filter('bulk_post_updated_messages', array($this, 'clientele_client_bulk_messages'), 10, 2);
      //Pointers
      add_filter('clientele_pointers-edit-clientele-client', array($this, 'client_pointers'));
      //Help
      add_action('admin_head', array($this, 'client_help_tabs'));
      // Ajaxness
      add_action('wp_ajax_mce_client_fields', array($this, 'ajax_mce_client_fields')); //
      add_action('wp_ajax_client_list', array($this, 'ajax_client_list')); //
      register_deactivation_hook(clientele_uninst_path, array($this, 'plugin_uninstall'));
      wp_register_script('clientele-selectize', $this->get_module_url(__FILE__) . 'lib/selectize.min.js', 'jquery', clientele_VERSION);
      wp_register_style('clientele-selectize', $this->get_module_url(__FILE__) . 'lib/selectize.css', 'jquery', clientele_VERSION);
    }

    /**
     * @author Mike
     * @since  1.0
     *
     */
    function client_pointers($pointers) {
      //$pointers['posts-filter'] = array('target' => '#posts-filter', 'options' => array('content' => sprintf('<h3> %s </h3> <p> %s </p>', __('Title', 'plugindomain'), __('Lorem ipsum dolor sit amet, consectetur adipiscing elit.', 'plugindomain')), 'position' => array('edge' => 'top', 'align' => 'middle')));
      //None?
      return $pointers;
    }

    /**
     * @author Mike
     * @since  1.0
     *
     */
    function client_help_tabs() {
      $screen = get_current_screen();
      $settings_pages = array("clientele-client_page_clients-settings", "clientele-client_page_clients-settings");
      $client_pages = array("clientele-client", "edit-clientele-client");
      $dashboard_pages = array("clientele-dashboard");
      if (in_array($screen->id, $client_pages)) {
        $screen->add_help_tab(array(
          'id' => 'client_overview',
          'title' => __('Overview', 'clientele'),
          'content' => '<p>' . __('Clients have similarities to posts and pages in how they are managed however the information you enter when adding a client will depend on what you have configured in your Client Field settings.', 'clientele') . '</p><p>' . __('Clients always have an email address and a notes section, all the other fields can be configured. You can add a potentially infinite number of client fields to store selected data about your clients.', 'clientele') . '</p><p>' . __('Once you have added a few clients you will have a powerful address book and database of client information.', 'clientele') . '</p><p>' . __('This by itself is a useful reference tool, however client data is what gives power to add-ons modules to complete business tasks and start making your client data work for you.', 'clientele') . '</p><p>' . __('You can get Add-ons via the Clientele dashboard.', 'clientele') . '</p>'
        ));
      }
      if (in_array($screen->id, $settings_pages)) {
        $screen->add_help_tab(array(
          'id' => 'client_fields',
          'title' => __('Overview', 'clientele'),
          'content' => '<p>' . __('You can add, remove and re-arrange the information that appears on each client page. This is the information that you want to know about your clients. ', 'clientele') . '</p><p>' . __('You may find that the default client fields are sufficient, it\'s often good to start small and add new fields when required.', 'clientele') . '</p><p>' . __('If you are new to Clientele, start with only a small number of client fields and you will soon realise what information you need to capture.', 'clientele') . '</p><p>' . __('With the exception of Email, none of the default fields are required and fields can be added or removed at any time. ', 'clientele') . '</p><p>' . __('You can select from 11 different field types.', 'clientele') . '</p>'
        ));
        $screen->add_help_tab(array(
          'id' => 'client_fields_edit',
          'title' => __('Editing client fields', 'clientele'),
          'content' => '<p>' . __('Editing client fields is as easy as changing the field label and default value then clicking \'Save changes\'. ', 'clientele') . '</p><p>' . __('If the field type is Dropdown or Radio buttons you can add options in the field type column. Add one option per line, each line will be a selectable option in the dropdown menu or radio buttons of the client page.', 'clientele') . '</p><p>' . __('To change order client fields appear on client pages use the move handle in the right column to drag them to the location you wish.', 'clientele') . '</p>'
        ));
        $screen->add_help_tab(array(
          'id' => 'client_field_types',
          'title' => __('Fields types', 'clientele'),
          'content' => '<p>' . __('The following provides an example of each field type that can be added to the Clients fields.', 'clientele') . '</p>' . '<p><strong>' . __('Text', 'clientele') . '</strong>' . __('- A single line standard text field.', 'clientele') . '</p>' . '<input type="text" class="clientele-input regular-text input-default" />' . '<p><strong>' . __('Text Area', 'clientele') . '</strong>' . __('- Text Area - A single multi-line text area.', 'clientele') . '</p>' . '<textarea class="clientele-input input-textarea" name="text-area"></textarea>' . '<p><strong>' . __('Number', 'clientele') . '</strong> ' . __(' - A single line standard number input field.', 'clientele') . ' </p>' . '<input type="number" class="clientele-input regular-text input-number" name="number" value="">' . '<p><strong>' . __('Dropdown', 'clientele') . ' </strong> ' . __('- Add options to create a select box. You will be able to select one of these options.', 'clientele') . '</p>' . '<select name="dropdown" class="clientele-input input-select">' . '<option>' . __('Option 1', 'clientele') . '</option>' . '<option>' . __('Option 2', 'clientele') . '</option>' . '</select>' . '<p><strong>' . __('Radio buttons', 'clientele') . ' </strong> ' . __('- Add options to create radio buttons. You will be able to select one of these options.', 'clientele') . '</p>' . '<input type="radio" class="clientele-input input-radio" value="Option 1" name="radio-example"/> ' . __('Option 1', 'clientele') . '<br/>' . '<input type="radio" class="clientele-input input-radio" value="Option 2" name="radio-example"/> ' . __('Option 2', 'clientele') . '<br/>' . '<p><strong>' . __('Checkbox', 'clientele') . '</strong> ' . __('- A single option that can be either on or off.', 'clientele') . '</p>' . '<input type="checkbox" class="clientele-input input-checkbox" /> ' . __('Checkbox is awesome?', 'clientele') . '<br/>' . '<p><strong>Date </strong> ' . __('- A single line standard text field with the type "date", depending on the web browser this will display a date picker.', 'clientele') . '</p>' . '<input class="clientele-input regular-text input-default" type="date" />' . '<p><strong>Email </strong> ' . __('- A single line standard text field with the type "email".', 'clientele') . '</p>' . '<input class="clientele-input regular-text input-default" type="email" />' . '<p><strong>Address </strong> ' . __('- A multi-line text area for address entry. Will display a map below the address if the it can be validated using Google Maps.', 'clientele') . '</p>' . '<textarea class="clientele-input input-location"></textarea>' . '<p><a target="_blank" href="http://maps.google.com?q=">' . '<img src="http://maps.googleapis.com/maps/api/staticmap?center=&amp;zoom=14&amp;size=350x190&amp;markers=size:small%7Ccolor:red%7C&amp;maptype=terrain&amp;sensor=false"></a>' . '</p>' . '<p><strong>Phone </strong> ' . __('- A single line standard number input field with the type "tel"', 'clientele') . '</p>' . '<input type="tel" class="clientele-input regular-text input-tel" name="phone" value="" />' . '<p><strong>Website </strong>' . __('- A single line standard text field with the type "website".', 'clientele') . '</p>' . '<input type="text" class="clientele-input regular-text input-default" name="website" value=""/>' . '<p>' . '<p>' . __('Clientele does not require validation of any client fields.', 'clientele') . '</p>'
        ));
      }
      if (in_array($screen->id, $dashboard_pages)) {
        $screen->add_help_tab(array(
          'id' => 'clientele_welcome',
          'title' => __('Welcome to Clientele', 'clientele'),
          'content' => '<p>' . __('Clientele is a simple Client Relationship Management plugin for WordPress.', 'clientele') . '</p><p>' . __('Client Relationship Management is about helping you get the most out of your relationships with customers, clients, suppliers, associates or any other type of contact. If you\'re running a business, a website, a community organisation, if you are freelancing or involved in any type of group, you want to know as much as you can about the people you interact with.', 'clientele') . '</p><p>' . __('Keeping track of all your contact details, interactions and communication can quickly become unmanageable and not surprisingly when we use a number of different applications, tools and approaches for formal and informal communication in business and social settings.', 'clientele') . '</p><p>' . __('Clientele helps bring these different channels together into something more manageable. It\'s built with WordPress to allow you to do more of your work in one place.', 'clientele') . '</p>'
        ));
        $screen->add_help_tab(array(
          'id' => 'clientele_setup',
          'title' => __('Setting up Clientele', 'clientele'),
          'content' => '<p>' . __('Once Clientele is installed you will be guided to configure some "Client Fields". This is the information that you want to know about your clients. You can add, remove and re-arrange the information that appears on each client page using 11 different field types.', 'clientele') . '</p><p>' . __('With the exception of email, none of the fields are required and fields can be added or removed at any time. ', 'clientele') . '</p><p>' . __('You may find that the default client fields are sufficient and if so no configuration is required. ', 'clientele') . '</p>'
        ));
        $screen->add_help_tab(array(
          'id' => 'clientele_add',
          'title' => __('Adding clients', 'clientele'),
          'content' => '<p>' . __('Clients have similarities to posts and pages in how they are managed, however the information you enter when adding a client will depend on your configuration of Client Fields.', 'clientele') . '</p><p>' . __('Adding and updating client information is the central objective of Client Relationship Management. You can use this information as a powerful reference tool. ', 'clientele') . '</p><p>' . __('There are a number of add-ons that will help you collect and update client information and others that will help you put your client data to use by powering business and reporting activities.', 'clientele') . '</p>'
        ));
      }
    }

    /**
     * @author Mike
     * @since  1.0
     *
     */
    function clientele_client_bulk_messages($bulk_messages, $bulk_counts) {
      $bulk_messages['clientele-client'] = array(
        'updated' => _n('%s Client updated.', '%s Clients updated.', $bulk_counts['updated'], 'clientele'),
        'locked' => _n('%s Client not updated, somebody is editing it.', '%s Clients not updated, somebody is editing them.', $bulk_counts['locked'], 'clientele'),
        'deleted' => _n('%s Client permanently deleted.', '%s Clients permanently deleted.', $bulk_counts['deleted'], 'clientele'),
        'trashed' => _n('%s Client moved to the Trash.', '%s Clients moved to the Trash.', $bulk_counts['trashed'], 'clientele'),
        'untrashed' => _n('%s Client restored from the Trash.', '%s Clients restored from the Trash.', $bulk_counts['untrashed'], 'clientele'),
      );
      return $bulk_messages;
    }

    /**
     * @author Mike
     * @since  1.0
     *
     */
    function plugin_uninstall() {
      //Delete all custom posts with type client along with meta and relations
      global $table_prefix, $wpdb;
      $postsTable = $table_prefix . "posts";
      $metaTable = $table_prefix . "postmeta";
      $relationsTable = $table_prefix . "term_relationships";
      //The event horizon
      $wpdb->query("DELETE FROM $postsTable post_type = 'clientele-client'"); // Delete clients
      $wpdb->query("DELETE FROM $metaTable WHERE post_id NOT IN (SELECT id FROM $postsTable)"); // Delete metadata
      $wpdb->query("DELETE FROM $relationsTable WHERE post_id NOT IN (SELECT id FROM $postsTable)"); // Delete relations
    }

    /**
     * @author Mike
     * @since  1.0
     *
     * @param $plugin_array
     *
     * @return mixed
     */
    function add_custom_tinymce_plugin($plugin_array) {
      // This adds my client fields mce plugin so users do not need to remember shortcodes
      $plugin_array['clienteleclientfields'] = clientele_URL . 'common/js/mce-plugin.js';
      return $plugin_array;
    }

    /**
     * @author Mike
     * @since  1.0
     *
     * @param $key
     * @param $value
     *
     * @return array|string
     */
    function get_clients($post_status = 'any') {
      $args = array('post_type' => 'clientele-client','posts_per_page' => '-1', 'post_status' => $post_status);
      return get_posts($args);
    }

    /**
     * @author Mike
     * @since  1.0
     *
     * @param $key
     * @param $value
     *
     * @return array|string
     */
    function get_clients_by_meta($key, $value, $post_status = '') {
      $metaquery[] = array('key' => $key, 'value' => $value, 'compare' => '=');
      $args = array('post_type' => 'clientele-client', 'meta_query' => $metaquery, 'posts_per_page' => '-1', 'post_status' => $post_status);
      $myquery = new WP_Query($args);
      $clients = false;
      foreach ($myquery->posts as $client) {
        $clients[] = $client;
      }
      return $clients;
    }

    /**
     * @author Mike
     * @since  1.0
     *
     * @param $post_obj
     */
    function new_client($post_obj, $clientemail = '') {
      // This preforms a number of actions required when a new client is added
      // Including setting post status and triggering events.
      global $clientele_events, $clientele;
      if ($post_obj->post_type == 'clientele-client') {
        $clientemail = isset($_POST['email']) ? $_POST['email'] : $clientemail;
        $clientele_events->do_event('sys', 'client_added', __('Client added: ', 'clientele') . $clientemail, $post_obj->ID);
        wp_publish_post($post_obj->ID); // This makes the unsubscribe page published (interesting this function doesn't trigger wp_transition_post_status hooks), but I don't want the status to be 'published' - makes no sense for a client so...
        //Make active
        $clientele->change_post_status($post_obj, 'active');
      }
    }

    /**
     * @author Mike
     * @since  1.0
     *
     * @param $post_id
     */
    function delete_client($post_id) {
      // This preforms a number of actions required when a client is deleted
      global $clientele_events;
      $delete_user = get_post($post_id);
      
      if ($delete_user->post_type == 'clientele-client') {
        $clientemail = ": " . get_post_meta($delete_user->ID, 'email', true);
        $clientele_events->do_event('sys', 'client_deleted', __('Client deleted: ', 'clientele') . $clientemail, $delete_user->ID);
      }
    }

    /**
     * @author Mike
     * @since  1.0
     *
     *
     */
    function clients_scripts() {
      global $pagenow, $typenow;
      if (empty($typenow) && !empty($_GET['post_type'])) {
        $typenow = get_post($_GET['post_type']);
      }
      if (!empty($_GET['page'])) {
        $page = $_GET['page'];
      }
      if ((($pagenow == 'edit.php' || $pagenow == 'post.php' || $pagenow == 'post-new.php') && $typenow == 'clientele-client') || ($pagenow == 'admin.php' && ($page == 'clients-settings' || $page == 'clientele-dashboard'))) {
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('jquery-ui-draggable');
        wp_enqueue_script('clientele-clients', $this->get_module_url(__FILE__) . 'lib/clientele-clients.js', array('jquery', 'jquery-ui-sortable', 'jquery-ui-draggable'), clientele_VERSION);
      }
    }

    /**
     * @author Mike
     * @since  1.0
     *
     */
    function clients_styles() {
      global $pagenow, $typenow;
      if (empty($typenow) && !empty($_GET['post_type'])) {
        $typenow = get_post($_GET['post_type']);
      }
      if (!empty($_GET['page'])) {
        $page = $_GET['page'];
      }
      if ((($pagenow == 'edit.php' || $pagenow == 'post.php' || $pagenow == 'post-new.php') && $typenow == 'clientele-client') || ($pagenow == 'admin.php' && ($page == 'clients-settings' || $page == 'clientele-dashboard'))) {
        wp_enqueue_style('clientele-clients-style', $this->get_module_url(__FILE__) . 'lib/clientele-clients.css', false, clientele_VERSION);
      }
    }

    /**
     * @author Mike
     * @since  1.0
     *
     */
    function clientele_client_add_menu() {
      // Adds the client menu options to the client post menu
      $mod_data = $this->module;
      if (isset($mod_data->link_text)) {
        $linkText = $mod_data->link_text;
      } else {
        $linkText = $mod_data->title . ' Settings';
      }
      add_submenu_page('edit.php?post_type=clientele-client', $linkText, $linkText, $mod_data->cb_restrict, $mod_data->page_slug, array($this, 'page_controller'));
    }

    /**
     * @author Mike
     * @since  1.0
     *
     */
    function clientele_setup_client_edit_page() {
      // Remove unwanted meta boxes.
      // Create custom metaboxes.
      // Customise tinyMCE.
      global $post_type, $wp_meta_boxes;
      $wp_meta_boxes['clientele-client'] = ''; // Start with clean slate, remove ALL metaboxes. That's Right, take that 3rd party plugins! All-in-one-SEO I'm looking at you.
      do_action('clientele_client_edit_screen');
      if ($post_type == 'clientele-client') {
        remove_action('media_buttons', 'media_buttons'); // Soft
        remove_all_actions('media_buttons', 999); // Now strong
        add_action('admin_enqueue_scripts', array($this, 'disableAutoSave')); // @WP can we please have a better way of doing this
      }
      add_meta_box('clientele-client-save', __("Save", 'clientele'), array($this, 'clientele_client_save_meta'), "clientele-client", "side", "core");
      add_meta_box('clientele-client-fields', __("Details", 'clientele'), array($this, 'clientele_client_fields_meta'), "clientele-client", "normal", "core");
      add_meta_box('clientele-client-notes', __("Notes", 'clientele'), array($this, 'clientele_client_notes'), "clientele-client", "normal", "core");
    }

    /**
     * @author Mike
     * @since  1.0
     *
     */
    function disableAutoSave() {
      // Too complex to explain
      wp_dequeue_script('autosave');
    }

    /**
     * @author Mike
     * @since  1.0
     *
     */
    function clientele_client_save_meta() {
      // Renders save Client Meta Box.
      wp_nonce_field('save-client-nonce', 'save-client-nonce', false);
      ?>
      <input value="Save" type='submit' name="clientele_client_save" class="button" id="saveForm">
    <?php
    }

    /**
     * @author Mike
     * @since  1.0
     *
     * @param $post
     */
    function clientele_client_fields_meta($post) {
      // Renders the client fields metabox by looping through each of the fields.
      // Renders input according to field type.
      // Retrieves data for current client if it exists.
      // This is the meat on the bones of the client module.
      global $clientele, $pagenow;
      do_action('client_fields_meta_top', $post->ID);
      ?>
      <table class="form-table">
        <?php
        foreach ($clientele->clients->module->options->client_fields as $key => $field) {
          ?>
          <tr>
            <td valign="top">
              <label for="<?php echo $key; ?>"><?php echo $field['label']; ?></label>
            </td>
            <td>
              <?php
              $classes = array('clientele-input');
              $attributes = array();

              if (isset($field['required']) && ($field['required'] == true || $field['required'] == '1')) {
                $classes[] = 'required';
                $attributes[] = 'required="required"';
              }
              $clean_field_value = esc_attr(get_post_meta($post->ID, $key, true));
              if (isset($field['default']) && $pagenow == 'post-new.php' && trim($clean_field_value) == '') {
                $clean_field_value = esc_attr($field['default']);
              }
              switch ($field['type']) { // What type of field are we dealing with? Output the correct input and populate with client data. Looks hard, is easy.
                case 'Dropdown':
                  $options = explode("\n", $field['options']);
                  $classes[] = 'input-select';
                  ?>
                  <select name="<?php echo $key; ?>" <?php echo implode(' ', $attributes); ?> class="<?php echo implode(' ', $classes); ?>">
                    <?php
                    foreach ($options as $option) {
                      if ($clean_field_value === $options = trim($option)) {
                        echo '<option selected="selected" value="' . trim($option) . '">' . $option . '</option>';
                      } else {
                        echo '<option value="' . trim($option) . '">' . $option . '</option>';
                      }
                    }
                    ?>
                  </select>
                  <?php
                  break;
                case 'Radio buttons':
                  $options = explode("\n", $field['options']);
                  $classes[] = 'input-radio';
                  foreach ($options as $option) {
                    if ($clean_field_value === $options = trim($option)) {
                      echo '<input type="radio" ' . implode(' ', $attributes) . ' class="' . implode(' ', $classes) . '" name="' . $key . '" value="' . trim($option) . '" checked="checked"> ' . $option;
                    } else {
                      echo '<input type="radio" ' . implode(' ', $attributes) . ' class="' . implode(' ', $classes) . '" name="' . $key . '" value="' . trim($option) . '"> ' . $option;
                    }
                  }
                  break;
                case 'Number':
                  $classes[] = 'regular-text';
                  $classes[] = 'input-number';
                  ?>
                  <input type="number"  <?php echo implode(' ', $attributes); ?>
                         class="<?php echo implode(' ', $classes); ?>"
                         name="<?php echo $key; ?>"
                         value="<?php echo $clean_field_value; ?>"/><?php
                  break;
                case 'Date':
                  $classes[] = 'regular-text';
                  $classes[] = 'input-date';
                  ?><input type="date" <?php echo implode(' ', $attributes); ?>
                           class="<?php echo implode(' ', $classes); ?>"
                           name="<?php echo $key; ?>"
                           value="<?php echo $clean_field_value; ?>"/><?php
                  break;
                case 'Email':
                  $classes[] = 'regular-text';
                  $classes[] = 'input-email';
                  ?>
                  <input type="email" <?php echo implode(' ', $attributes); ?>
                         class="<?php echo implode(' ', $classes); ?>"
                         name="<?php echo $key; ?>"
                         value="<?php echo $clean_field_value; ?>" /><?php
                  break;
                case 'Phone':
                  $classes[] = 'regular-text';
                  $classes[] = 'input-tel';
                  ?>
                  <input type="tel" <?php echo implode(' ', $attributes); ?>
                         class="<?php echo implode(' ', $classes); ?>"
                         name="<?php echo $key; ?>"
                         value="<?php echo $clean_field_value; ?>"/><?php
                  break;
                case 'Address':
                  $classes[] = 'input-location';
                  ?>
                  <textarea <?php echo implode(' ', $attributes); ?>
                  class="<?php echo implode(' ', $classes); ?>"
                  <?php $value = $clean_field_value; ?>
                  name="<?php echo $key; ?>"><?php echo $value; ?></textarea>
                  <p>
                    <a class="map" target="_blank" href="http://maps.google.com?q=<?php echo $value; ?>">
                      <img src="http://maps.googleapis.com/maps/api/staticmap?center=<?php echo urlencode($value); ?>&zoom=14&size=350x190&markers=size:small%7Ccolor:red%7C<?php echo urlencode($value); ?>&maptype=terrain&sensor=false" /></a>
                  </p>
                  <?php
                  break;
                case 'Text Area':
                  $classes[] = 'input-textarea';
                  ?>
                  <textarea <?php echo implode(' ', $attributes); ?>
                  class="<?php echo implode(' ', $classes); ?>" name="<?php echo $key; ?>"><?php echo $clean_field_value; ?></textarea>
                  <?php
                  break;
                case 'Checkbox':
                  $classes[] = 'input-check-box';
                  ?>
                  <input <?php echo implode(' ', $attributes); ?> type="checkbox" value="yes" class="<?php echo implode(' ', $classes) ?>" name="<?php echo $key; ?>" <?php if ($clean_field_value == 'yes') {
                    echo 'checked="checked"';
                  }?>/>

                  <?php
                  break;
                default:
                  $classes[] = 'regular-text';
                  $classes[] = 'input-default';
                  ?>
                    <input type="text" <?php echo implode(' ', $attributes); ?>
                           class="<?php echo implode(' ', $classes) ?>"
                           name="<?php echo $key; ?>"
                           value="<?php echo $clean_field_value; ?>"/><?php
                  break;
              } // See wasn't hard
              ?>
            </td>
          </tr>
        <?php
        }
        ?>
      </table>
      <?php
      do_action('client_fields_meta_bottom', $post->ID);
    }

    /**
     * @author Mike
     * @since  1.0
     *
     * @param $post_id
     */
    function clientele_save_client_meta($post_id) {
      // Verify, create slug and save
      // This is another part of the client module engine room. This handles the saving of client details.
      // Not that this function is run on delete and create as well as save.
      global $clientele, $clientele_events;
      global $wpdb;
      // If a slug is not set, set it as the MD5 hash of the timestamp.
      // this creates a long and unique url for the client unsubscribe action.
      // Again I would like to delegate this to the email module but how?
      $post_obj = get_post($post_id);
      if ($post_obj->post_type == 'clientele-client') {
        if (isset($_POST['clientele_client_save'])) { // Only on 'save' action not create or delete
          $slug = $post_obj->post_name;
          if (!($slug) || stripos($slug, 'auto') !== false || stripos($slug, 'draft') !== false) {
            $slug = md5(time());
            $table = $wpdb->base_prefix . 'posts';
            $data = array('post_name' => $slug);
            $where = array('ID' => $post_id);
            $wpdb->update($table, $data, $where);
            $data = array('post_title' => $slug);
            $wpdb->update($table, $data, $where);
          }
          if (!wp_verify_nonce($_POST['save-client-nonce'], 'save-client-nonce')) {
            wp_die('Cheatin&#8217; uh?');
          }
          //Client Save hook
          do_action('clientele_before_client_save', $post_id);
          //Make active
          $clientele->change_post_status($post_obj, 'active');
          //Save client fields
          foreach ($clientele->clients->module->options->client_fields as $key => $field) {
            $new_meta_value = (isset($_POST[$key]) ? $_POST[$key] : '');
            // Do I need to do any data validation? What do I care if you enter nonsense? Power to you!
            // Maybe just some JS inline warnings to be nice?
            if ($field['type'] == 'Text' || $field['type'] == 'Text Area' || $field['type'] == 'Address') {
              $new_meta_value = trim($new_meta_value);
            }
            if ($field['type'] == 'date') {
              $new_meta_value = date('Y-m-d', strtotime($new_meta_value));
            }
            $clientele->add_update_or_delete_post_meta($new_meta_value, $post_id, $key); //@ WP why don't you have this function?
          }
          // Save subscription options -- ToDo Can I delegate to email module?
          if (isset($_POST['unsubscribe'])) {
            $setunsubscribe = 0;
          } else {
            $setunsubscribe = 1;
          }
          $clientele->add_update_or_delete_post_meta($setunsubscribe, $post_id, 'unsubscribe');
          if ($setunsubscribe == 1) {
            $clientele->change_post_status($post_obj, 'unsubscribed');
            $clientemail = ": " . get_post_meta($post_id, 'email', true);
            $clientele_events->do_event('sys', 'unsubscribe', __('Client ', 'clientele') . $clientemail . __(' was unsubscribed', 'clientele'), $post_id);
          }
          // Save notes
          $newnotes = (isset($_POST['notes']) ? $_POST['notes'] : '');
          $clientele->add_update_or_delete_post_meta($newnotes, $post_id, 'notes');
          do_action('clientele_after_client_save', $post_id);
        } // End of actions on save only
      }
    }

    /**
     * @author Mike
     * @since  1.0
     *
     * @param $post
     */
    function clientele_client_notes($post) {
      $content = get_post_meta($post->ID, 'notes', true);
      wp_editor(stripslashes($content), 'notes', array('media_buttons' => false, 'teeny' => true, 'textarea_name' => 'notes', 'textarea_rows' => 5));
    }

    /**
     * @author Mike
     * @since  1.0
     *
     * @return mixed
     */
    function add_new_client_columns() {
      // Adds custom columns to the view clients page
      global $clientele;
      $new_columns['cb'] = '<input type="checkbox">';
      foreach ($clientele->clients->module->options->client_fields as $key => $field) {
        $new_columns[$key] = $field['label'];
      }
      if (!$user = wp_get_current_user()) { // Let's get out of here if we can't determine the user
        die('angry!'); // May be illegal attempt to change user prefs so die angrily.
      }
      //If the user has no options set, this hides all but the first 5 cols (6 cause of checkbox)
      $screen = get_current_screen();
      $hidden = get_user_option('manage' . $screen->id . 'columnshidden');
      if (empty($hidden)) {
        $to_hide = array_slice($new_columns, 6);
        foreach ($to_hide as $key => $field) {
          $hide[] = $key;
        }
        update_user_option($user->ID, 'manage' . $screen->id . 'columnshidden', $hide, true);
      }
      return $new_columns;
    }

    /**
     * @author Mike
     * @since  1.0
     *
     * @param $columns
     *
     * @return mixed
     */
    function sort_new_client_columns($columns) {
      global $clientele;
      foreach ($clientele->clients->module->options->client_fields as $key => $field) {
        $columns[$key] = $key;
      }
      return $columns;
    }

    /**
     * @author Mike
     * @since  1.0
     *
     * @param $actions
     * @param $post
     *
     * @return mixed
     */
    function remove_quick_edit($actions, $post) {
      // This complicated function removes the edit and view link from the quick actions for client posts
      if ($post->post_type == "clientele-client") {
        unset($actions['inline hide-if-no-js']); // @WP this is stupid. It should say 'edit'.
        unset($actions['view']);
      }
      return $actions;
    }

    /**
     * @author Mike
     * @since  1.0
     *
     * @param $actions
     *
     * @return mixed
     */
    function remove_bulk_actions($actions) {
      // This complicated function removes the edit link from bulk actions
      unset($actions['edit']);
      return $actions;
    }

    /**
     * @author Mike
     * @since  1.0
     *
     * @param $query
     */
    function clientele_set_search_query($search) {
      global $wp_query;
      if ($wp_query->query_vars['post_type'] == "clientele-client") {
        $search_term = '';
        if (isset($_GET['s']) && $_GET['s'] != '') {
          $search_term .= $_GET['s'];
        }
        if (isset($_GET['filterField'], $_GET['filterCondition'], $_GET['filterValue']) && $_GET['filterValue'] != '') {
          $search_term .= ' ' . __("(search is also limited by a filter)", "clientele");
        }
        return $search_term;
      }
      return $search;
    }

    /**
     * @author Mike
     * @since  1.0
     *
     * @param $query
     */
    function clientele_do_filter($query) {
      if (isset($query->query_vars['post_type']) && $query->query_vars['post_type'] == "clientele-client") {
        // Applies filters to the view clients page by modifying the wp_query.
        if (isset($_GET['orderby']) && $_GET['orderby'] != '') {
          set_query_var('meta_key', $_GET['orderby']);
          set_query_var('orderby', 'meta_value');
        }
        if (isset($_GET['order']) && $_GET['order'] != '') {
          set_query_var('order', $_GET['order']);
        }
        if (isset($_GET['s']) && $_GET['s'] != '') {
          $filterValue = $_GET['s'];
          $metaquery[] = array('value' => $filterValue, 'compare' => 'LIKE', 'type' => 'CHAR');
          set_query_var('s', '');
          set_query_var('meta_query', $metaquery);
        }
        if (isset($_GET['filterField'], $_GET['filterCondition'], $_GET['filterValue']) && $_GET['filterValue'] != '') {
          $filterField = $_GET['filterField'];
          $filterCondition = $_GET['filterCondition'];
          $filterValue = $_GET['filterValue'];
          $metaquery[] = array('key' => $filterField, 'value' => $filterValue, 'compare' => $filterCondition);
          set_query_var('meta_query', $metaquery);
        }
      }
    }

    /**
     * @author Mike
     * @since  1.0
     *
     */
    function remove_admin_bar_link() {
      // Removes the view link in the admin bar on the edit client page
      global $wp_admin_bar;
      global $post;
      if (isset($post->post_type) && $post->post_type == "clientele-client") {
        $wp_admin_bar->remove_menu('view');
      }
    }

    /**
     * @author Mike
     * @since  1.0
     *
     * @param $messages
     *
     * @return mixed
     */
    function client_updated_messages($messages) {
      $messages['clientele-client'] = array(
        0 => '', // Unused. Messages start at index 1.
        1 => __('Client updated.', 'clientele'),
        2 => __('Custom field updated.', 'clientele'),
        3 => __('Custom field deleted.', 'clientele'),
        4 => __('Client updated.', 'clientele'),
        5 => isset($_GET['revision']) ? sprintf(__('Client restored to revision from ') . '%s', wp_post_revision_title((int) $_GET['revision'], false)) : false,
        6 => __('Client updated.', 'clientele'),
        7 => __('Client saved.', 'clientele'),
        8 => __('Client updated', 'clientele'),
        9 => __('Client updated', 'clientele'),
        10 => __('Client updated', 'clientele')
      );
      return $messages;
    }

    /**
     * @author Mike
     * @since  1.0
     *
     */
    function clientele_filter_client_columns() {
      // Creates the filter options on the view client page.
      global $clientele;
      if (isset($_GET['post_type']) && $_GET['post_type'] == 'clientele-client') {
        ?>
        <label for="filterField" id="filterLabel"><?php _e('Filter', 'clientele'); ?>: </label>
        <select class="filterField" tabindex="4" name="filterField" id="filterField">
          <?php
          if (isset($_GET['filterField'])) {
            $filterField = $_GET['filterField'];
          } else {
            $filterField = 'LIKE';
          }
          foreach ($clientele->clients->module->options->client_fields as $clientkey => $clientfield) {
            ?>
            <option value="<?php echo $clientkey; ?>"<?php if ($clientkey === $filterField) {
              echo 'selected="selected"';
            } ?> >
              <?php
              echo $clientfield['label'];
              ?>
            </option>
          <?php
          }
          if (isset($_GET['filterCondition'])) {
            $filterCondition = $_GET['filterCondition'];
          } else {
            $filterCondition = 'LIKE';
          }
          ?>
        </select>
        <select class="filterCondition" tabindex="4" name="filterCondition" id="filterCondition">
          <option <?php if ("LIKE" === $filterCondition) {
            echo 'selected="selected"';
          }?> value="LIKE"><?php _e('contains', 'clientele'); ?>
          </option>
          <option <?php if ("NOT LIKE" === $filterCondition) {
            echo 'selected="selected"';
          }?> value="NOT LIKE"><?php _e('does not contain', 'clientele'); ?>
          </option>
          <option <?php if ("=" === $filterCondition) {
            echo 'selected="selected"';
          } ?> value="="><?php _e('is', 'clientele'); ?>
          </option>
          <option <?php if (">" === $filterCondition) {
            echo 'selected="selected"';
          } ?> value=">"><?php _e('is greater than', 'clientele'); ?>
          </option>
          <option <?php if ("<" === $filterCondition) {
            echo 'selected="selected"';
          } ?> value="<"><?php _e('is less than', 'clientele'); ?>
          </option>
        </select>
        <input class="filterValue" type="text" name="filterValue" value="<?php if (isset($_GET['filterValue'])) {
          echo $_GET['filterValue'];
        } ?>" id="filterValue" />
      <?php
      }
    }

    /**
     * @author Mike
     * @since  1.0
     *
     * @param $column_name
     * @param $id
     */
    function manage_client_columns($column_name, $id) {
      // Retrieves the data for the rows in the custom columns on the view clients page
      if (get_post_meta($id, $column_name, true) == '') {
        $editlink = __('(None)', 'clientele');
        // To do if check box change to No.
      } else {
        $editlink = get_post_meta($id, $column_name, true);
      }
      echo edit_post_link($editlink, '<p>', '</p>', $id);
    }

    /**
     * @author Mike
     * @since  1.0
     *
     * @param $clientFields
     *
     * @return bool
     */
    function validate_client_fields($clientFields) {
      //Validation of client fields. Labels and by extension shortcodes must be unique.
      //Checks for duplication of labels in the client fields
      foreach ($clientFields as $val) {
        if (!$val['label'] || trim(strtolower($val['label'])) == '') {
          $validation_error = __('Field lables cannot be empty', 'clientele');
        } else {
          $dupe_array[] = $val['label']; // Bug? This does this not check the last field in the array?... maybe
        }
      }
      if (isset($validation_error)) {
        return $validation_error;
      }
      if (count($clientFields) != count(array_unique($dupe_array))) {
        return __('Field lables must be unique', 'clientele');
      }
      return false;
    }

    /**
     * @author Mike
     * @since  1.0
     *
     */
    function ajax_mce_client_fields() {
      global $clientele;
      foreach ($clientele->clients->module->options->client_fields as $key => $field) {
        $response[$key]['value'] = '{' . $clientele->make_slug($field['label']) . '}';
        $response[$key]['label'] = $field['label'];
      }
      echo json_encode($response);
      die(0);
    }

    /**
     * @author Mike
     * @since  1.0
     *
     */
    function ajax_client_list() {
      global $clientele, $wpdb;
      /// Need to use wpdb
      $query = isset($_REQUEST['query']) ? $_REQUEST['query'] : '';
      $limit = isset($_REQUEST['limit']) ? $_REQUEST['limit'] : 1000;
      $response = $wpdb->get_results($wpdb->prepare("SELECT DISTINCT $wpdb->posts.id
                    FROM $wpdb->posts, $wpdb->postmeta
                    WHERE $wpdb->posts.ID = $wpdb->postmeta.post_id
                    AND $wpdb->posts.post_type = %s 
                    AND $wpdb->postmeta.meta_value LIKE %s 
                    LIMIT %d", "clientele-client", "%$query%", $limit));
      $result = [];
      foreach ($response as $key => $data) {
        $result[$key] = get_post_meta($data->id);
        $result[$key]['id'] = $data->id;
      }
      echo json_encode($result);
      die(0);
    }

    /**
     * @author Mike
     * @since  1.0
     *
     */
    function clients_settings_page() {
      //Render the client settings page
      if (isset($_POST['client_fields'])) {
        echo $this->save_client_fields(); // Returns message or false;
      }
      include('client-fields.php');
    }

    /**
     * @author Mike
     * @since  1.0
     *
     * @return string
     */
    function save_client_fields() {
      // Saves Client Fields.
      // ToDo: I really want to improve the validation process here
      global $clientele;
      $new_options = $_POST['client_fields'];
      if (isset($_POST['new_fields'])) {
        $temp = $_POST['new_fields'];
        if (count($temp) > 0) {
          //validate new fields
          foreach ($temp as $field) {
            if (trim(strtolower($field['label'])) == '') {
              $validation_error = __('Field lables cannot be empty', 'clientele');
            } else {
              $betterId = $clientele->make_slug($field['label']); // When new fields are added with Javascript a time stamp id is assigned. We change this to a slug now.
              if (isset($new_options[$betterId])) {
                $validation_error = __('Field lables must be unique', 'clientele');
              } else {
                $new_fields[$betterId] = $field;
                $new_options = array_merge($new_options, $new_fields);
              }
            }
          }
        }
      }
      if (isset($validation_error)) {
        return '<div class="clientele-error"><p>' . $validation_error . '</p></div>';
      }
      $validate_part2 = $this->validate_client_fields($new_options);
      if ($validate_part2) {
        return '<div class="clientele-error error"><p>' . $validate_part2 . '</p></div>';
      }
      $clientele->update_module_option($this->module->name, 'client_fields', $new_options);
      return '<div class="clientle-success updated"><p>' . __('Client fields updated', 'clientele') . '</p></div>';
    }

    /**
     * @author Mike
     * @since  1.0
     *
     * @param $mod_data
     */
    function select_client_widget($client_id="", $name='clientele_client', $placeholder='Pick a client...') {
        echo '<select name="'.$name.'" class="clientele-select-client" placeholder="'.$placeholder.'">';
        $client = get_post($client_id);
        if(isset($client)){
          echo '<option value="'.$client_id.'">' .  get_post_meta($client_id, 'email', true) . '</option>';
        }
        echo '</select>';
    }

    /**
     * @author Mike
     * @since  1.0
     *
     * @param $mod_data
     */
    function clients_dashboard_element($mod_data) {
      //The client module has no disable button. So has a custom dashboard element.
      $classes = array('clientele-module', 'core-module');
      if (!isset($mod_data->options->enabled)) {
        $mod_data->options->enabled = 'on';
      }
      if ($mod_data->options->enabled == 'off') {
        $classes[] = 'clientele-disabled';
      } else {
        $classes[] = 'clientele-enabled';
      }
      echo '<div class="' . implode(' ', $classes) . '"  id="' . $mod_data->slug . '">';
      echo '<form method="get" action="' . admin_url('options.php') . '">';
      echo '<h3>' . esc_html($mod_data->title) . '</h3>';
      echo '<p>' . esc_html($mod_data->short_description) . '</p>';
      echo '<p class="clientele-buttons">';
      if ($mod_data->page_cb && (!($mod_data->cb_restrict) || current_user_can($mod_data->cb_restrict))) {
        $configure_url = add_query_arg('page', $mod_data->page_slug, admin_url('admin.php'));
        echo '<a type="button" href="' . $configure_url . '" class="button">' . __('Client Fields', 'clientele') . '</a>';
      }
      echo '</p>';
      wp_nonce_field('change-clientele-email-module-nonce', 'change-module-nonce', false);
      echo '</form>';
      echo '</div>';
    }
  }
}
