<?php
/*
Plugin Name: Reportaj ADS Publisher
Description: This reportage plugin automatically publishes the reportage ads from the Reportaj Ads website on your site.
Version: 1.0.5
Author: Reportaj ADS
Author URI: https://reportaj.ir
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Domain Path: /languages
Text Domain: reportajads-publisher
*/

if (!defined('ABSPATH')) {
	exit;
} // Exit if accessed directly

if (!class_exists('Reportajads_Publisher')) {
	class Reportajads_Publisher
	{
		protected static $_instance = null;

		/**
		 * Get instance of the class
		 *
		 * This function creates a singleton instance of the class.
		 * It first checks if the instance already exists, and if not, creates a new instance.
		 * It then returns the instance of the class.
		 *
		 * @return Reportaj_Publisher Instance of the class
		 */
		public static function instance()
		{
			if ( is_null( self::$_instance ) ) { // Check if the static property "self::$_instance" is null
				self::$_instance = new self(); // If it's null, create a new instance of the current class and assign it to "self::$_instance"
			}

			return self::$_instance; // Return the static property "self::$_instance"
		}

		/**
		 * Construct the plugin object
		 */
		public function __construct()
		{
            define( 'REPORTAJ_PUBLISHER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
            define( 'REPORTAJ_PUBLISHER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
            
            // add sttings submenu to exchange post type
            add_action( 'admin_menu' , array( &$this, 'add_settings_submenu' ) );
            
            // cron jobs for get posts from api
            add_filter('cron_schedules', array($this, 'add_cron_interval'));
            add_action('init', array(&$this, 'import_reportaj_cronstarter_activation'));
            add_action('reportaj_ads_publisher_import_posts', array(&$this, 'import_reportaj_posts'));
            
            // rest api
            add_action('rest_api_init', array(&$this, 'register_api_routes'));
            
            // change status and send data to api when save post
            add_action('save_post', array(&$this, 'send_link_to_api'));
            
            // delete meta before delete post
            add_action('before_delete_post', array(&$this, 'delete_reportaj_ads_code_meta'));
            
            do_action('reportaj_ads_publisher_plugin_hooks');
			
		} // END public function __construct()

		/**
		 * Add settings submenu
		 * 
		 * @return void
		 */
        public function add_settings_submenu()
        {
            add_submenu_page(
                'options-general.php',
                __('Reportaj ADS Settings','reportajads-publisher'),
            	__('Reportaj ADS Settings','reportajads-publisher'),
                'manage_options',
                'reportaj-ads-settings',
                array( &$this, 'settings_callback' )
            );
        }

		/**
         * Output settings page markup
         *
         * @return void
         */
        public function settings_callback()
        {
            $reportaj_ads_settings = get_option('reportaj_ads_publisher_settings');
            if( isset( $_POST['reportaj_ads_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['reportaj_ads_settings_nonce'] ) ), 'reportaj_ads_settings_nonce' ) )
            {
                $exchange_settings = [];
                if( isset( $_POST['reportaj_ads_api'] ) && isset( $_POST['reportaj_ads_website_code'] ) )
                {
					// check exchange text
					if( !empty( $_POST['reportaj_ads_api'] ) )
						$api_key = wp_kses_post( wp_unslash( $_POST['reportaj_ads_api'] ) );
					else
						$api_key = NULL;
					
					// check exchange cat title
					if( !empty( $_POST['reportaj_ads_website_code'] ) )
						$website_code = sanitize_text_field( wp_unslash( $_POST['reportaj_ads_website_code'] ) );
					else
						$website_code = NULL;

					// check exchange cat
					if( !empty( $_POST['reportaj_ads_category'] ) && $_POST['reportaj_ads_category'] != 'none' )
						$category = intval( $_POST['reportaj_ads_category'] );
					else
						$category = NULL;

					do_action('reportaj_ads_publisher_settings_save_fields');
					
                    $reportaj_ads_settings = array( 
						'api_key' => $api_key,
						'website_code' => $website_code,
						'category' => $category
					);
        
                    // update option
                    update_option('reportaj_ads_publisher_settings', $reportaj_ads_settings );
        
                    echo '<div class="notice notice-success"><p>'. esc_attr__( 'Settings Updated.', 'reportajads-publisher' ). '</p></div>';
                }
            }
            ?>
            <div class="wrap">
                <h1><?php esc_attr_e('Reportaj ADS Settings','reportajads-publisher'); ?></h1>
                <form method="post" action="">
                    <input type="hidden" name="page" value="reportaj-ads-settings" />
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row" style="width:30%"><label for="reportaj_ads_api"><?php esc_attr_e('API Key','reportajads-publisher'); ?></th>
                            <td style="width:100%">
                                <input type="text" name="reportaj_ads_api" id="reportaj_ads_api" value="<?php if( !empty( $reportaj_ads_settings['api_key'] ) ) echo esc_attr( $reportaj_ads_settings['api_key'] ); ?>">
                            </td>
                        </tr>
						<tr valign="top">
                            <th scope="row" style="width:30%"><label for="reportaj_ads_website_code"><?php esc_attr_e('Website Code','reportajads-publisher'); ?></th>
                            <td style="width:100%">
                                <input type="text" name="reportaj_ads_website_code" id="reportaj_ads_website_code" value="<?php if( !empty( $reportaj_ads_settings['website_code'] ) ) echo esc_attr( $reportaj_ads_settings['website_code'] ); ?>">
                            </td>
                        </tr>
						<tr valign="top">
                            <th scope="row"><label for="reportaj_ads_category"><?php esc_attr_e('Default Category', 'reportajads-publisher'); ?></th>
                            <td>
                                <?php
                                $args = array(
                                    'show_option_none' => __('Select a category...', 'reportajads-publisher'),
                                    'orderby' => 'name',
                                    'echo' => 0,
									'hide_empty' => false,
                                    'name' => 'reportaj_ads_category',
                                    'id' => 'reportaj_ads_category',
                                    'taxonomy' => 'category',
                                    'selected' => isset( $reportaj_ads_settings['category'] ) ? $reportaj_ads_settings['category'] : '',
                                );
                                echo wp_dropdown_categories( $args );
                                ?>
                            </td>
                        </tr>

						<?php do_action('reportaj_ads_publisher_settings_fields'); ?>

                    </table>
                    <input type="hidden" name="reportaj_ads_settings_nonce" value="<?php echo esc_attr( wp_create_nonce( 'reportaj_ads_settings_nonce' ) ); ?>" />
                    <input type="submit" class="button button-primary" name="submit_settings" value="<?php esc_attr_e('Save Settings', 'reportajads-publisher'); ?>" />
                </form>
            </div>
            <?php
        }

		/**
         * Add custom interval to cron schedules
         *
         * @param array $schedules Existing cron schedules
         * @return array Updated cron schedules
         */
		public function add_cron_interval( $schedules )
		{
			$schedules['every_thirty_minutes'] = array(
				'interval' => 30 * MINUTE_IN_SECONDS,
				'display' => esc_html__( 'Every 30 Minutes', 'reportajads-publisher' ),
			);

			return $schedules;
		}
		
		/**
         * Schedule cron event on activation
         *
         * @return void
         */
		public function import_reportaj_cronstarter_activation()
		{
			if ( ! wp_next_scheduled( 'reportaj_ads_publisher_import_posts' ) )
			{   
                // Schedule the event
                wp_schedule_event( strtotime( time() ), 'every_thirty_minutes', 'reportaj_ads_publisher_import_posts' );
            }
		}

		/**
         * Import posts from API
         *
         * @return void
         */
		public static function import_reportaj_posts()
		{
			$original_time_limit = ini_get('max_execution_time');
    
			// Temporarily remove execution time limit for this task only
			ini_set('max_execution_time', '0');

			global $wpdb;
			
			$reportaj_ads_settings = get_option('reportaj_ads_publisher_settings');
			if(!empty($reportaj_ads_settings))
			{
				if( !empty( $reportaj_ads_settings['api_key'] ) && !empty( $reportaj_ads_settings['website_code'] ) )
				{
					// api arguments
					$url = 'https://reportaj.ir/shop/api/schedule/infos';
					$args = array(
						'method'      => 'POST',
						'sslverify'   => false,
						'body'        => array(
							'api_token' => $reportaj_ads_settings['api_key'],
							'code' => $reportaj_ads_settings['website_code']
						)
					);

					$request = wp_safe_remote_post( $url, $args );
					if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 )
					{
						error_log( print_r( $request, true ), 3, REPORTAJ_PUBLISHER_PLUGIN_DIR . '/error.log' );
					}
					else
					{
						$response = wp_remote_retrieve_body( $request );
						$result = json_decode( $response, true );

						if( !empty( $result ) && $result['success'] )
						{
							if( !empty( $result['items'] ) )
							{
								foreach( $result['items'] as $item )
								{
									$sql = $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'reportaj_ads_code' AND meta_value = %s GROUP BY post_id", $wpdb->esc_like( $item['code'] ) );
									$post_id = $wpdb->get_col( $sql );

									if ( empty( $post_id ) )
									{
										// No existing post found, create new one
										$new_post_id = wp_insert_post( array(
											'post_title' => esc_attr( $item['title']),
											'post_content' => $item['content'],
											'post_status' => ( $item['release_status'] ) ? 'publish' : 'pending',
											'post_excerpt' => $item['abstract'],
											'post_type' => 'post',
											'post_category' => array( $reportaj_ads_settings['category'] )
											// 'tags_input' => array_map( 'sanitize_text_field', $item['tags'] )
										) );

										if( $new_post_id )
										{
											// Set reportaj ads article code to post
											add_post_meta( $new_post_id, 'reportaj_ads_code', $item['code'] );

											// Change image urls of post and replace with uploaded version in wordpress media
											$post_content = get_post_field('post_content', $new_post_id);

											// Use preg_match_all to extract all img src URLs
											preg_match_all('/<img.*?src=\"(.*?)\"/', $post_content, $matches);
											$image_urls = $matches[1];

											// Loop through URLs and generate attachments
											foreach($image_urls as $url)
											{
												$attach_id = self::generate_image($url, $post_id, array(
													'filename' => basename( $url ), 
													'upload_dir' => wp_upload_dir()
												));

												if( $attach_id )
												{
													// Replace old URL with new attachment URL
													$post_content = str_replace( $url, wp_get_attachment_url( $attach_id ), $post_content );
												}
											}

											// update post content
											wp_update_post( array(
												'ID' => $new_post_id,
												'post_content' => wp_kses_post( $post_content )
											), $true );
										}
									}
								}
							}
						}
					}
				}
			}

			// Restore original execution time limit
			ini_set('max_execution_time', $original_time_limit);
		}
		
		/**
         * Register API endpoints
         *
         * @return void
         */
		public function register_api_routes()
		{
		    register_rest_route('reportaj/v1', '/insert-post/', array(
                'methods'  => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'reportaj_insert_post')
            ));
            
            register_rest_route('reportaj/v1', '/get-post/', array(
                'methods'  => WP_REST_Server::READABLE,
                'callback' => array($this, 'reportaj_get_post')
            ));
		}
		
		/**
         * Callback for inserting posts via API
         *
         * @param WP_REST_Request $request Request object
         * @return WP_REST_Response Response object
         */
		public function reportaj_insert_post( $request )
		{
		    global $wpdb;
		    $params = $request->get_params();
		    $reportaj_ads_settings = get_option('reportaj_ads_publisher_settings');
		    
		    // Check api_key and website_code fields
		    if( empty( $params['api_key'] ) && empty( $params['website_code'] ) )
			{
			    $response_data = array( 'success' => false, 'message' => __('The API key and website code fields were not found.', 'reportajads-publisher' ) );
                return new WP_REST_Response( $response_data, 400 );
			}
			
			if( !empty( $params['api_key'] ) && !empty( $params['website_code'] ) && $params['api_key'] == $reportaj_ads_settings['api_key'] && $params['website_code'] == $reportaj_ads_settings['website_code'] )
			{
			    // Check title field
    		    if ( empty( $params['title'] ) )
    		    {
                    $response_data = array( 'success' => false, 'message' => __('The title field was not found.', 'reportajads-publisher' ) );
                    return new WP_REST_Response( $response_data, 400 );
                }
                
                // Check content field
                if ( empty( $params['content'] ) )
    		    {
                    $response_data = array( 'success' => false, 'message' => __('The content field was not found.', 'reportajads-publisher' ) );
                    return new WP_REST_Response( $response_data, 400 );
                }
                
                // Check abstract field
                if ( empty( $params['abstract'] ) )
    		    {
                    $response_data = array( 'success' => false, 'message' => __('The abstract field was not found.', 'reportajads-publisher' ) );
                    return new WP_REST_Response( $response_data, 400 );
                }
                
                // Check release status field
                if ( empty( $params['release_status'] ) )
    		    {
                    $response_data = array( 'success' => false, 'message' => __('The release status field was not found.', 'reportajads-publisher' ) );
                    return new WP_REST_Response( $response_data, 400 );
                }
                
                // Check reportaj code field
                if ( empty( $params['reportaj_code'] ) )
    		    {
                    $response_data = array( 'success' => false, 'message' => __('The reportaj code field was not found.', 'reportajads-publisher' ) );
                    return new WP_REST_Response( $response_data, 400 );
                }
                
                $sql = $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'reportaj_ads_code' AND meta_value = %s GROUP BY post_id", $wpdb->esc_like( $params['reportaj_code'] ) );
    			$post_id = $wpdb->get_col( $sql );
    
    			if ( empty( $post_id ) )
    			{
    				// No existing post found, create new one
    				$new_post_id = wp_insert_post( array(
    					'post_title' => sanitize_text_field( $params['title'] ),
    					'post_content' => wp_kses_post( $params['content'] ),
    					'post_status' => ( $params['release_status'] ) ? 'publish' : 'pending',
    					'post_excerpt' => sanitize_text_field( $params['abstract'] ),
    					'post_type' => 'post',
    					'post_category' => array( $reportaj_ads_settings['category'] )
    					// 'tags_input' => array_map( 'sanitize_text_field', $item['tags'] )
    				) );
    
    				if( $new_post_id )
    				{
    					// Set reportaj ads article code to post
    					add_post_meta( $new_post_id, 'reportaj_ads_code', sanitize_text_field( $params['reportaj_code'] ) );
    
    					// Change image urls of post and replace with uploaded version in wordpress media
    					$post_content = get_post_field('post_content', $new_post_id);
    
    					// Use preg_match_all to extract all img src URLs
    					preg_match_all('/<img.*?src=\"(.*?)\"/', $post_content, $matches);
    					$image_urls = $matches[1];
    
    					// Loop through URLs and generate attachments
    					foreach($image_urls as $index => $url)
    					{
    						$attach_id = self::generate_image($url, $post_id, array(
    							'filename' => basename( $url ), 
    							'upload_dir' => wp_upload_dir()
    						));
    
    						if( $attach_id )
    						{
    							// Replace old URL with new attachment URL
    							$post_content = str_replace( $url, wp_get_attachment_url( $attach_id ), $post_content );
    							
    							if( $index === 0 )
    							    set_post_thumbnail( $new_post_id , $attach_id );
    						}
    					}
    
    					// update post content
    					wp_update_post( array(
    						'ID' => $new_post_id,
    						'post_content' => wp_kses_post( $post_content )
    					), $true );
    					
    					$response_data = array( 'status' => true , 'post_id' => $new_post_id, 'post_url' => get_permalink( $new_post_id ) );
                        return new WP_REST_Response( $response_data, 200 );
    				}
    			}
    			else
    			{
    			    $response_data = array( 'status' => true , 'message' => __('There is a post with the provided code.', 'reportajads-publisher' ), 'post_id' => $post_id[0], 'post_url' => get_permalink( $post_id[0] ) );
                    return new WP_REST_Response( $response_data, 200 );
    			}
			}
			else
			{
			    $response_data = array( 'status' => false , 'message' => __('The API key and website code fields are invalid.', 'reportajads-publisher' ) );
                return new WP_REST_Response( $response_data, 400 );
			}
		}
		
		/**
         * Callback for getting posts via API
         *
         * @param WP_REST_Request $request Request object
         * @return WP_REST_Response Response object
         */
		public function reportaj_get_post( $request )
		{
		    global $wpdb;
		    $params = $request->get_params();
		    $reportaj_ads_settings = get_option('reportaj_ads_publisher_settings');
		    
		    // Check api_key and website_code fields
		    if( empty( $params['api_key'] ) && empty( $params['website_code'] ) )
			{
			    $response_data = array( 'success' => false, 'message' => __('The API key and website code fields were not found.', 'reportajads-publisher' ) );
                return new WP_REST_Response( $response_data, 400 );
			}
			
			if( !empty( $params['api_key'] ) && !empty( $params['website_code'] ) && $params['api_key'] == $reportaj_ads_settings['api_key'] && $params['website_code'] == $reportaj_ads_settings['website_code'] )
			{
			    // Check title field
    		    if ( empty( $params['reportaj_code'] ) )
    		    {
                    $response_data = array( 'success' => false, 'message' => __('The reportaj code field was not found.', 'reportajads-publisher' ) );
                    return new WP_REST_Response( $response_data, 400 );
                }
                
                $sql = $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'reportaj_ads_code' AND meta_value = %s GROUP BY post_id", $wpdb->esc_like( $params['reportaj_code'] ) );
    			$post_id = $wpdb->get_col( $sql );
    
    			if ( empty( $post_id ) )
    			{
    			    $response_data = array( 'status' => true , 'message' => __('There is not any posts with the provided code.', 'reportajads-publisher' ) );
                    return new WP_REST_Response( $response_data, 200 );
    			}
    			else
    			{
    			    $post_info = get_post( $post_id[0] );
    			    if( ! is_wp_error( $post_info ) )
    			    {
    			        $response_data = array( 'status' => true , 'message' => __('There is a post with the provided code.', 'reportajads-publisher' ), 'post_id' => $post_info->ID, 'post_title' => get_the_title( $post_info->ID ), 'post_status' => get_post_status( $post_info->ID ), 'reportaj_code' => absint( $params['reportaj_code'] ) );
                        return new WP_REST_Response( $response_data, 200 );
    			    }
    			    else
    			    {
    			        $response_data = array( 'status' => true , 'message' => __('There is not any posts with the provided code.', 'reportajads-publisher' ) );
                        return new WP_REST_Response( $response_data, 200 );
    			    }
    			}
			}
			else
			{
			    $response_data = array( 'status' => false , 'message' => __('The API key and website code fields are invalid.', 'reportajads-publisher' ) );
                return new WP_REST_Response( $response_data, 400 );
			}
		}

		/**
		 * Send post link to API on publish
		 *
		 * @param int $post_id Post ID
		 * @return void
		 */
		public function send_link_to_api( $post_id )
		{
			$reportaj_ads_settings = get_option('reportaj_ads_publisher_settings');
			if(!empty($reportaj_ads_settings))
			{
				if( !empty( $reportaj_ads_settings['api_key'] ) && !empty( $reportaj_ads_settings['website_code'] ) )
				{
					$reportaj_article_code = get_post_meta( $post_id, 'reportaj_ads_code', true );
					if( !empty( $reportaj_article_code ) )
					{
						$post = get_post( $post_id );
						if ($post->post_status === 'publish' && get_post_meta($post_id, 'reportaj_ads_sent_api', true) != 'yes')
						{
							// api arguments
							$url = 'https://reportaj.ir/shop/api/schedule/setStatus';
							$args = array(
								'method'      => 'POST',
								'sslverify'   => false,
								'body'        => array(
									'api_token' => $reportaj_ads_settings['api_key'],
									'code' => $reportaj_ads_settings['website_code'],
									'article_code' => $reportaj_article_code,
									'link' => get_permalink( $post_id )
								)
							);

							$request = wp_safe_remote_post( $url, $args );
							if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 )
							{
								error_log( print_r( $request, true ), 3, REPORTAJ_PUBLISHER_PLUGIN_DIR . '/error.log' );
							}
							else
							{
								$response = wp_remote_retrieve_body( $request );
								$result = json_decode( $response, true );

								if( !empty( $result ) && $result['success'] )
								{
									// Mark the post as first published
									update_post_meta( $post_id, 'reportaj_ads_sent_api', 'yes');
								}
							}
						}
					}
				}
			}
		}
		
        /**
         * Delete reportaj_ads_code meta before deleting a post
         *
         * @param int $post_id Post ID
         * @return void
         */
		public function delete_reportaj_ads_code_meta( $post_id ) {
			if ( current_user_can( 'delete_post', $post_id ) ) {
				// Check if the post meta exists
				if (metadata_exists('post', $post_id, 'reportaj_ads_code')) {
					// Delete the post meta
					delete_post_meta( $post_id, 'reportaj_ads_code' );
				}
			}
		} 

		/**
		 * Generate attachment from image URL and attach to post.
		 *
		 * @param string $image_url 
		 * @param int $post_id
		 * @param array $overrides Custom overrides.
		 * @return int|false Attachment ID on success, false on failure.
		 */
		public static function generate_image($image_url, $post_id, $overrides = array())
		{
			// Check if wp_handle_sideload function exists, if not, include the necessary files
			require_once(ABSPATH . 'wp-admin/includes/file.php');
			require_once(ABSPATH . 'wp-admin/includes/media.php');
			require_once(ABSPATH . 'wp-admin/includes/image.php');

			// Default overrides
			$defaults = array(
				'filename' => basename($image_url),
				'upload_dir' => wp_upload_dir(),
				'upload_path' => 'images'
			);

			// Parse overrides
			$overrides = wp_parse_args($overrides, $defaults);

			// Download the image from the URL
			$image_contents = wp_remote_get($image_url);

			if (is_wp_error($image_contents)) {
				return false;
			}

			// Get the filename from the URL if not specified in overrides
			if (empty($overrides['filename'])) {
				$filename = basename($image_url);
			} else {
				$filename = $overrides['filename'];
			}

			// Prepare the upload directory
			$upload_dir = $overrides['upload_dir'];
			$upload_path = $overrides['upload_path'];
			$upload_path = trailingslashit($upload_dir['path']) . $upload_path;
			$upload_url = trailingslashit($upload_dir['url']) . $upload_path;

			// Generate a unique file name to avoid conflicts
			$unique_filename = wp_unique_filename($upload_path, $filename);

			// Define the file path and URL for the image
			$file_path = $upload_path . $unique_filename;
			$file_url = $upload_url . $unique_filename;

			// Save the image contents to the file
			$file_saved = wp_upload_bits($unique_filename, null, $image_contents['body']);

			if (isset($file_saved['file'])) {
				// Create attachment
				$attachment = array(
					'guid'           => $file_url,
					'post_mime_type' => $file_saved['type'],
					'post_title'     => sanitize_file_name($unique_filename),
					'post_content'   => '',
					'post_status'    => 'inherit',
				);

				$attach_id = wp_insert_attachment($attachment, $file_saved['file'], $post_id);

				if ($attach_id) {
					// Generate and update metadata
					$attach_data = wp_generate_attachment_metadata($attach_id, $file_saved['file']);
					wp_update_attachment_metadata($attach_id, $attach_data);

					return $attach_id;
				}
			}

			return false;
		}

		/**
		 * Activate plugin
		 *
		 * @return void
		 */
		public static function activate()
		{
            // Do nothing
		} // END public static function activate

		/**
		 * Deactivate plugin
		 *
		 * @return void 
		 */
		public static function deactivate()
		{
            // Do nothing
		} // END public static function deactivate	

	} // END class Reportajads_Publisher
} // END if(!class_exists('Reportajads_Publisher'))

if ( class_exists('Reportajads_Publisher') ) {
    
	// instantiate the plugin class
	new Reportajads_Publisher();
    register_activation_hook( __FILE__, array( 'Reportajads_Publisher', 'activate' ) );
    register_deactivation_hook( __FILE__, array( 'Reportajads_Publisher', 'deactivate' ) );
    
}