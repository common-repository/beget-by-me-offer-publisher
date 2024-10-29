<div class="bbm-container">
	<div class="bbm-logo"><img src="http://c84588.r88.cf2.rackcdn.com/images/site/home/logo.jpg" width="199" height="76" /></div>
	<?php  if(get_option('bbm_registration_id') != ''){ ?>
		<div class="updated">Your microsite has been registered with your blog.</div>
	<?php } ?>
	<?php 
		global $wp_version, $wp_db_version, $_wp_theme_features;
	?>
	<form method="post" action="options.php" id="bbm_form">
		<?php settings_fields( 'bbm-settings-group' ); ?>
		<table class="form-table">
			<tr valign="top">
				<th scape="row"><label for="bbm_subdomain">Enter Your BegetByMe Subdomain:</label></th>
				<td><span><input type="text" name="bbm_subdomain" class="register-field" value="<?php echo get_option('bbm_subdomain'); ?>" /><?php echo BBM_DOMAIN; ?></span></td>
			</tr>
			
			<tr valign="top">
				<th scape="row"><label for="bbm_post_category">Default Category:</label></th>
				<td><span><input type="text" name="bbm_post_category" class="register-field" value="<?php echo get_option('bbm_post_category'); ?>" /></span></td>
			</tr>
		</table>
		<input type="hidden" name="bbm_register_url" value="<?php echo BBM_REGISTER_URL; ?>" />
		<input type="hidden" name="bbm_registration_id" value="<?php echo get_option('bbm_registration_id'); ?>" />
		<input type="hidden" class="register-field" name="bbm_blog_url" value="<?php echo get_option('siteurl'); ?>" />
		<?php foreach(array('wp_version','wp_db_version','_wp_theme_features') as $field) { ?>
			<?php foreach((array)$$field as $k=>$val) { ?>
				<input type="hidden" class="register-field" name="bbm_<?php echo $field; ?>[<?php echo $k; ?>]" value='<?php echo json_encode($val); ?>' />
			<?php } ?>
		<?php } ?>
		<p class="submit">
			<input type="submit" class="button-primary" id="bbm-update-button" value="<?php _e('Save Changes') ?>" />
		</p>
	</form>
</div>
