//To Do: need to tidy this and add form validation.

jQuery(document).ready(function () {

    jQuery("#sortable").sortable({
        axis: 'y',
        handle: '.drag-me',
        revert: true
    });
    jQuery(".input-location").on('change', function(){
      var that = jQuery(this)
      that.parent('td').find('img').attr('src', 'http://maps.googleapis.com/maps/api/staticmap?center='+encodeURIComponent(jQuery(that).val())+'&zoom=14&size=350x190&markers=size:small%7Ccolor:red%7C'+encodeURIComponent(jQuery(that).val())+'&maptype=terrain&sensor=false')
    });

});
function clientele_remove_field(field) {
    var r = confirm("Are you sure you sure you want to remove this field? \nAny client data relating to this field cannot be recovered.");
    if (r == true) {
        jQuery(field).closest('.client-field').remove();
    }
}
function uniqid() { // Does this totally suck as a concept? See save_client_fields in clients.php for reasoning.
    // This is one of the few JS dependencies, perhaps a oho solution for non JS users (If there are any)?
    var newDate = new Date;
    return newDate.getTime();
}


function clientele_add_field(fieldName) {
    var id = uniqid();
    var options = '';
    if (fieldName == 'Radio buttons' || fieldName == 'Dropdown') {
        options = 'Options (one per line):<textarea name="new_fields[' + id + '][options]" >Option 1\nOption 2</textarea><br/><br/>'
    } else {
        options = '<input name="new_fields[' + id + '][options]" type="hidden" value="" />'
    }

    myhtml = '<div class="client-field clearfix">';
    myhtml += '<div class="client-col field-label">';
    myhtml += '<input name="new_fields[' + id + '][label]" class="clienteleFieldLabel" type="text" value="' + fieldName + '">';
    myhtml += '</div>';
    myhtml += '<div class="client-col field-type">';
    myhtml += '<input name="new_fields[' + id + '][type]" type="hidden" value="' + fieldName + '"/>' + fieldName;
    myhtml += '</div>';
    myhtml += '<div class="client-col field-default">';
    myhtml += options;
    myhtml += 'Default value: <input type="text" name="new_fields[' + id + '][default]" value="">';
    myhtml += '</div>';
    myhtml += '<div class="client-col field-util"">';
    myhtml += '<button class="button" onclick="clientele_remove_field(this)">Remove</button>';
    myhtml += '</div>';
    myhtml += '<div class="client-col field-drag"">';
    myhtml += ' <span class="drag-me">';
    myhtml += ' <a href="javascript:;">Move</a>';
    myhtml += '</span>';
    myhtml += '</div>';
    jQuery('#sortable').append(myhtml);
}


	


