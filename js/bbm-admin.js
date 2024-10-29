jQuery(document).ready(function(){
	jQuery('#bbm-update-button').bind('click', function(e){
		return checkBbmAdminForm();
	})
});

checkBbmAdminForm = function(){
	jQuery('.bbm-container table.form-table div.error').remove();
	jQuery('.bbm-container .updated').remove();
	var subdomain = jQuery('input[name="bbm_subdomain"]').val();
	var registrationId = jQuery('input[name="bbm_registration_id"]').val();
	var blogUrl = jQuery('input[name="bbm_blog_url"]').val().replace(/https?\:\/\//, '');
	var registrationUrl = jQuery('input[name="bbm_register_url"]').val().replace(/<subdomain>/, subdomain);
	success = false;
	jQuery.ajax({
		url: registrationUrl,
		dataType: jQuery.browser.msie ? 'jsonp' : 'json',
		type: 'get',
		data: jQuery('.register-field').serialize(),
		success: function(data){
			if(typeof(data.registrationId) != "undefined"){
				jQuery('input[name="bbm_registration_id"]').val(data.registrationId);
				success = true;
				if(jQuery.browser.msie)
				{
					jQuery('#bbm_form').submit();
				}
			}
			else
			{
				showRegistrationError();
			}
		},
		error: showRegistrationError,
		async: false
	});
	return success;
}

showRegistrationError = function(){
	jQuery('input[name="bbm_subdomain"]').parent('span').after('<div class="error">Unable to register site.</div>');
}