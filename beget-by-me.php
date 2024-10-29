<?php
/*
	Plugin Name: Beget Offer Publisher
	Plugin URI: http://www.beget.com
	Version: 0.3.1
	Author: Beget
	Description: Plugin to pull in offers as posts.
*/


define('BBM_DOMAIN', '.begetbyme.com');
define('BBM_DEFAULT_CATEGORY_NAME', '');
define('BBM_DEFUAULT_USER_EMAIL', 'dev.support@gmail.com');

define('BBM_DEBUG', false);

define('BBM_REGISTER_URL', 'http://<subdomain>' . BBM_DOMAIN . '/feeds/register');
define('BBM_UNREGISTER_URL', 'http://<subdomain>' . BBM_DOMAIN . '/feeds/unregister');
define('BBM_PULL_URL', 'http://<subdomain>' . BBM_DOMAIN . '/feeds/pull');

define('BBM_PLUGIN_DIRECTORY', 'beget-by-me-offer-publisher');


if(!class_exists('BegetPublisher')) {
	class BegetPublisher{
		
		public function BegetPublisher(){
			$this->__construct();
		}
		
		public function __construct(){
			add_action('init', array(&$this, 'bbm_check_feeds'));
			add_action('admin_menu', array(&$this, 'bbm_create_menu'));
			add_action('admin_init', array(&$this, 'bbm_register_settings'));
			add_action('admin_head', array(&$this, 'bbm_admin_head'));
			
			register_activation_hook(__FILE__, array(&$this, 'bbm_activate'));
			register_deactivation_hook(__FILE__, array(&$this, 'bbm_deactivate'));
			register_uninstall_hook(__FILE__, 'bbm_uninstall');
			
			add_filter('post_link', array(&$this, 'bbm_permalink'));
		}
		
		public function bbm_check_feeds(){
			$registrationId = get_option('bbm_registration_id');
			if(isset($_REQUEST['update_begetbyme']) && $registrationId){
				$this->_log('Received request to check for new posts.');
				$pullUrl = str_replace('<subdomain>', get_option('bbm_subdomain'), BBM_PULL_URL);
				
				$posts = $this->bbm_get_posts($pullUrl); //unserialize($_POST['post']);
				if($posts && count($posts))
				{
					foreach($posts as $pulledPost)
					{
						$this->bbm_insert_post($pulledPost);
					}
				}
				exit();
			}
		}
		
		function bbm_insert_post($myPost)
		{
			$guid = $myPost['guid'];
		
			global $wpdb, $wp_version;
			$guidCheck = $wpdb->get_results('SELECT ID FROM ' . $wpdb->prefix . 'posts where guid = \'' . $this->buildGuid($guid) . '\'');
		
			$oldPost = false;
			if(count($guidCheck)){
				$oldPost = true;
			}
		
			if(!$oldPost)
			{
				$this->_log('Inserting new post');
				$this->_log($myPost);
				list($wp_major_version, $wp_minor_version, $wp_revision) = explode('.', $wp_version);
		
				$myPost['guid'] = $this->buildGuid($guid);
				$myPostUser = $myPost['post_author'];
		
				if(function_exists('get_users'))
				{
					$users = get_users();
				}
				else
				{
					$users = get_users_of_blog();
				}
				
				$myUserData = array
				(
						'user_nicename'=>$myPostUser,
						'display_name'=>$myPostUser,
						'user_login'=>$myPostUser,
						'role'=>'author',
						'user_url'=>$myPost['user_url'],
						'user_pass'=>uniqid(),
						'user_email'=>BBM_DEFUAULT_USER_EMAIL
				);
		
				$authorChanged = false;
				foreach($users as $user)
				{
					if($user->display_name == $myPostUser || $user->user_email == BBM_DEFUAULT_USER_EMAIL)
					{
						$myPost['post_author'] = $user->ID;
						$myUserData['ID'] = $user->ID;
						unset($myUserData['user_login']);
						wp_update_user($myUserData);
						$authorChanged = true;
						break;
					}
				}
		
				if(!$authorChanged)
				{
					
					if((int)$wp_major_version < 3 || (int)$wp_minor_version == 0)
					{
						require_once(ABSPATH.'/wp-includes/registration.php');
					}
						
					$myPostAuthor = wp_insert_user( $myUserData );
					if(!is_wp_error($myPostAuthor)){
						$myPost['post_author'] = $myPostAuthor;
						$this->_log('Created user ' . $myPost['post_author']);
					} else {
						$this->_log('Unable to create user');
						$this->_log($myUserData);
					}
				}
				unset($myPost['user_url']);
		
				$catName = get_option('bbm_post_category');
				if($catName)
				{
					require_once(ABSPATH.'/wp-admin/includes/taxonomy.php');
					$catId = wp_create_category($catName);
					$myPost['post_category'] = array($catId);
				}
		
		
				$myPostId = wp_insert_post( $myPost );
				$this->_log('Inserted post ' . $myPostId);
				if(!empty($myPost['permalink_url'])){
					if(update_post_meta($myPostId, 'deal_url', $myPost['permalink_url'])){
						$this->_log('Stored deal_url ' . $myPost['permalink_url']);
					} else {
						$this->_log('Unable to store deal_url ' . $myPost['permalink_url']);
					}
				}
		
				if(!empty($myPost['thumbnail_url'])){
					$this->_log('Trying to store thumbnail for post ' . $myPostId . ' with url ' . $myPost['thumbnail_url']);
					$thumbId = $this->bbm_get_image_from_url($myPost['thumbnail_url'], $myPostId);
					if(!is_null($thumbId))
					{
						$this->_log('Store thumbnail for post ' . $myPostId . ' Thumbnail ID: ' . $thumbId);
						update_post_meta($myPostId, '_thumbnail_id', $thumbId);
					}
					else
					{
						$this->_log('Unable to store thumbnail for post ' . $myPostId);
					}
				}
			}
		}
		
		function bbm_get_image_from_url($imageUrl, $post_id)
		{
		
			// Get the file name
			$filename = substr($imageUrl, (strrpos($imageUrl, '/'))+1);
		
			if (!(($uploads = wp_upload_dir(current_time('mysql')) ) && false === $uploads['error'])) {
				return null;
			}
		
			// Generate unique file name
			$filename = wp_unique_filename( $uploads['path'], $filename );
		
			// Move the file to the uploads dir
			$new_file = $uploads['path'] . "/$filename";
		
			if (!ini_get('allow_url_fopen')) {
				$file_data = curl_get_file_contents($imageUrl);
			} else {
				$file_data = @file_get_contents($imageUrl);
			}
		
			if (!$file_data) {
				return null;
			}
		
			file_put_contents($new_file, $file_data);
		
			// Set correct file permissions
			$stat = stat( dirname( $new_file ));
			$perms = $stat['mode'] & 0000666;
			@ chmod( $new_file, $perms );
		
			// Get the file type. Must to use it as a post thumbnail.
			$wp_filetype = wp_check_filetype( $filename );
		
			extract( $wp_filetype );
		
			// No file type! No point to proceed further
			if ( ( !$type || !$ext ) && !current_user_can( 'unfiltered_upload' ) ) {
				return null;
			}
		
			// Compute the URL
			$url = $uploads['url'] . "/$filename";
		
			// Construct the attachment array
			$attachment = array(
					'post_mime_type' => $type,
					'guid' => $url,
					'post_parent' => null,
					'post_title' => $filename,
					'post_content' => '',
			);
		
			$thumb_id = wp_insert_attachment($attachment, $new_file, $post_id);
			if ( !is_wp_error($thumb_id) ) {
				require_once(ABSPATH . '/wp-admin/includes/image.php');
		
				wp_update_attachment_metadata( $thumb_id, wp_generate_attachment_metadata( $thumb_id, $new_file ) );
		
				return $thumb_id;
			}
		
			return null;
		}
		
		public function bbm_get_posts($pullUrl)
		{
			$dealResponse = wp_remote_get($pullUrl);
			if(is_wp_error($dealResponse)){
				return false;
			}
			return unserialize(wp_remote_retrieve_body($dealResponse));
		}
		
		public function bbm_admin_head() {
			echo '<link rel="stylesheet" type="text/css" href="' .plugins_url('/css/bbm-admin.css', __FILE__). '" />',
			'<script type="text/javascript" src="' . plugins_url('/js/bbm-admin.js', __FILE__) . '"></script>';
		}
		
		
		public function bbm_create_menu(){
			add_menu_page(
					__('BegetByMe', ''),
					__('BegetByMe', ''),
					'edit_posts',
					BBM_PLUGIN_DIRECTORY.'/bbm_settings.php',
					'',
					plugins_url('/images/bbm-icon.png', __FILE__)
			);
		}
		
		public function bbm_update_permalink($permalink){
			return $permalink;
		}
		
		public function bbm_register_settings(){
			register_setting( 'bbm-settings-group', 'bbm_subdomain' );
			register_setting( 'bbm-settings-group', 'bbm_registration_id' );
			register_setting( 'bbm-settings-group', 'bbm_post_category' );
		}
		
		public function bbm_activate(){
			add_option('bbm_subdomain', '');
			add_option('bbm_registration_id', '');
			add_option('bbm_post_category', BBM_DEFAULT_CATEGORY_NAME);
		}
		
		public function bbm_deactivate(){
		
		}
		
		public static function bbm_uninstall(){
			$unregUrl = str_replace('<subdomain>', get_option('bbm_subdomain'), BBM_UNREGISTER_URL);
			wp_remote_get($unregUrl);
		
			delete_option('bbm_subdomain');
			delete_option('bbm_registration_id');
			delete_option('bbm_post_category');
		}
		
		public function bbm_permalink ($permalink = '', $post = null, $leavename = false) {
			global $id;
		
			if (is_object($post) and isset($post->ID) and !empty($post->ID)) :
			// Use the post ID we've been provided with.
			$postId = $post->ID;
			elseif (is_string($permalink) and strlen($permalink) > 0) :
			// Map this permalink to a post ID so we can get the correct
			// permalink even outside of the Post Loop. Props Björn.
			$postId = url_to_postid($permalink);
			else :
			// If the permalink string is empty but Post Loop context
			// provides an id.
			$postId = $id;
			endif;
		
			$uri = get_post_custom_values('deal_url', $id);
			$permalink = ((strlen($uri[0]) > 0) ? $uri[0] : $permalink);
			return $permalink;
		}
		
		private function buildGuid($guid)
		{
			return get_option('siteurl') . '/?guid=' . $guid;
		}
		
		private function _log($message){
			if( WP_DEBUG === true ){
				if(!is_string($message)){
					$message = var_export($message, true);
				}
				error_log('Beget Publisher: ' . $message);
			}
		}
		
	}
}


$BegetPublisher = new BegetPublisher();