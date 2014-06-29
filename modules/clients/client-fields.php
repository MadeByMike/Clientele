<?php
/**
 * Client fields - Included by clients.php
 *
 * @package clientele
 *
 **/
?>
  <h2><?php _e('Client Fields', 'clientele'); ?></h2>
  <p><?php _e('Add the fields you need to store information about your clients.', 'clientele'); ?></p>
  <p><?php _e('For added security and for the protection of your clients, never store financial information or passwords.', 'clientele'); ?></p>
<?php do_action('before_edit_client_fields'); ?>
  <form name="client_fields" action="<?php echo add_query_arg('action', 'save-fields'); ?>" method="post" />
  <div class="grid">
    <div class="header-row">
      <div class="client-col field-label"><strong><?php _e('Field label', 'clientele'); ?></strong></div>
      <div class="client-col field-type"><strong><?php _e('Field type', 'clientele'); ?></strong></div>
      <div class="client-col field-default"><strong><?php _e('Field options', 'clientele'); ?></strong></div>
      <div class="client-col field-util">&nbsp;</div>
      <div class="client-col field-drag">&nbsp;</div>
    </div>
    <div id="sortable">
      <?php
      foreach ($this->module->options->client_fields as $key => $field) {
        ?>
        <div class="client-field clearfix">
        <div class="client-col field-label">
          <?php
          if (isset($field['required']) && ($field['required'] == '1' || $field['required'] == true)) {
            echo '<input name="faux" class="clienteleFieldLabel" disabled="disabled" type="text" value="' . esc_attr(stripslashes($field['label'])) . '">';
            echo '<input name="client_fields[' . esc_attr(stripslashes($key)) . '][label]" class="clienteleFieldLabel" type="hidden" value="' . esc_attr(stripslashes($field['label'])) . '">';
          } else {
            echo '<input name="client_fields[' . esc_attr(stripslashes($key)) . '][label]" class="clienteleFieldLabel" type="text" value="' . esc_attr(stripslashes($field['label'])) . '">';
          }
          ?>
        </div>
        <div class="client-col field-type">
          <input name="client_fields[<?php echo esc_attr(stripslashes($key)) ?>][type]" type="hidden" value="<?php echo esc_attr(stripslashes($field['type'])); ?>" />
          <?php
          echo esc_attr(stripslashes(stripslashes($field['type'])));
          ?>
        </div>
        <div class="client-col field-default">
          <?php
          switch ($field['type']) {
            case 'Dropdown':
            case 'Radio buttons':
              echo __('Options (one&nbsp;per&nbsp;line)', 'clientele') . ':';
              echo '<textarea name="client_fields[' . esc_attr(stripslashes($key)) . '][options]">';
              echo esc_textarea(stripslashes($field['options']));
              echo '</textarea>';
              echo '<br/><br/>';
              echo __('Default value', 'clientele') . ':';
              echo '<input type="text" name="client_fields[' . esc_attr(stripslashes($key)) . '][default]" value="' . ((isset($field['default'])) ? esc_attr(stripslashes($field['default'])) : '') . '"/>';
              break;
            default:
              echo __('Default value', 'clientele') . ':';
              echo '<input type="text" name="client_fields[' . esc_attr(stripslashes($key)) . '][default]" value="' . ((isset($field['default'])) ? esc_attr(stripslashes($field['default'])) : '') . '"/>';
              break;
          }
          ?>
        </div>
        <div class="client-col field-util">
          <?php
          if (!isset($field['required']) || $field['required'] == '' || $field['required'] == 0) {
            ?>
            <button class="button" onclick="clientele_remove_field(this)"><?php _e('Remove', 'clientele'); ?></button>
            <input name="client_fields[<?php echo esc_attr(stripslashes($key)); ?>][required]" type="hidden" value="0" />
          <?php
          } else {
            if ($field['required']) {
              ?>
              <input name="client_fields[<?php echo esc_attr(stripslashes($key)); ?>][required]" type="hidden" value="<?php echo esc_attr(stripslashes($field['required'])); ?>" />
              <em><?php _e('Required', 'clientele'); ?></em>
            <?php
            }
          }
          ?>
        </div>
        <div class="client-col field-drag">
          <span class="drag-me"><a href="javascript:;"><?php _e('Move', 'clientele'); ?></a></span>
        </div>
        </div><?php
      }
      ?></div>
  </div>
  <p>
    <?php _e('Add field type:', 'clientele'); ?>
    <select id="AddField">
      <option value='Text'><?php _e('Text', 'clientele'); ?></option>
      <option value='Text Area'><?php _e('Text Area', 'clientele'); ?></option>
      <option value='Number'><?php _e('Number', 'clientele'); ?></option>
      <option value='Dropdown'><?php _e('Dropdown', 'clientele'); ?></option>
      <option value='Radio buttons'><?php _e('Radio buttons', 'clientele'); ?></option>
      <option value='Checkbox'><?php _e('Checkbox', 'clientele'); ?></option>
      <option value='Date'><?php _e('Date', 'clientele'); ?></option>
      <option value='Email'><?php _e('Email', 'clientele'); ?></option>
      <option value='Address'><?php _e('Address', 'clientele'); ?></option>
      <option value='Phone'><?php _e('Phone', 'clientele'); ?></option>
      <option value='Website'><?php _e('Website', 'clientele'); ?></option>
    </select> <input type="button" onclick="clientele_add_field(jQuery('#AddField').val())" class="button" id="addFieldButton" value="<?php _e('Add Field', 'clientele'); ?>" /></p>
  <p><input type="submit" class="button-primary" value="<?php _e('Save changes', 'clientele'); ?>" /></p>

  </form>
<?php do_action('after_edit_client_fields'); ?>