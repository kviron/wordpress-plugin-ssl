<?php
defined('ABSPATH') or die("you do not have access to this page!");

use Elementor\Utils;

class rsssl_premium_options {
	private static $_this;
	public $has_http_redirect=false;

	function __construct() {
		if ( isset( self::$_this ) ) {
			wp_die( sprintf( __( '%s is a singleton class and you cannot create a second instance.', 'really-simple-ssl-pro' ), get_class( $this ) ) );
		}

		self::$_this = $this;

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets'));
		add_action( 'admin_init', array( $this, 'save_permissions_policy' ), 30 );
		add_action( 'admin_init', array( $this, 'auto_update_elementor_url'), 15);
		add_action( 'activate_plugin', array( $this, 'delete_notice_cache'), 10, 3);

        add_action( 'admin_init', array( $this, 'check_upgrade' ), 10, 2 );
		add_action( "admin_init", array( $this, "insert_security_headers" ), 90 );
		add_action( "update_option_rlrsssl_options", array( $this, "maybe_clear_certificate_check_schedule" ), 30, 3 );

		add_action( "update_option_rsssl_enable_csp_reporting", array( $this, "maybe_update_csp_activation_time" ), 20, 3 );

		add_action( 'wp_loaded', array( $this, 'admin_mixed_content_fixer' ), 1 );
		add_action( 'admin_init', array( $this, 're_check_http_redirect' ), 2 );
		add_action( 'admin_init', array( $this, 'change_notices_free' ), 5 );
		add_action( 'admin_init', array($this, 'add_pro_settings') , 9);
        add_action( 'admin_init', array($this, 'add_permissions_policy_settings'), 65);
        add_action( 'admin_init', array($this, 'insert_secure_cookie_settings'), 70);
		add_action( "admin_notices", array($this, 'show_notice_wpconfig_not_writable'));
		add_action( "admin_notices", array($this, 'show_notice_csp_enabled_next_steps'));

		add_action( 'wp_ajax_dismiss_success_pro_multisite_notice', array($this,'dismiss_pro_multisite_notice_callback') );
		add_action( 'wp_ajax_dismiss_csp_next_steps_notice', array($this,'dismiss_csp_next_steps_notice_callback') );
		add_action( 'admin_print_footer_scripts', array($this, 'insert_csp_next_steps_dismiss'));
		$plugin = rsssl_pro_plugin;
		add_filter( "plugin_action_links_$plugin", array($this,'plugin_settings_link'));
		add_filter( "network_admin_plugin_action_links_$plugin", array($this,'plugin_settings_link'));

		add_filter( "rsssl_progress_footer_right", array($this,'progress_footer_right'));
		add_filter( "rsssl_progress_footer_left", array($this,'progress_footer_left'));

		add_filter( 'rsssl_grid_tabs', array($this,'add_pro_tabs'),10,1);
		add_filter( 'rsssl_notices', array($this,'get_notices_list'),20,1 );
		add_action( 'show_tab_security_headers', array($this, 'add_security_headers_page'));
		add_action( 'show_tab_premium', array($this, 'add_premium_page'));

		add_filter( 'rsssl_activation_notice_classes', array($this, 'activation_notice_color'), 10, 3 );

		add_filter( 'rsssl_grid_items', array($this, 'add_pro_grid_items'));
		add_action( 'rsssl_system_status', array($this, 'add_pro_system_status'));
		add_action('plugins_loaded', array($this, 'rsssl_pro_set_defaults'), 20 );
		add_action('rsssl_finished_text', array($this, 'finished_text'), 20 );
		add_action('rsssl_deactivate', array($this, 'deactivate'), 20 );

    }

	static function this() {
		return self::$_this;
	}
	public function progress_footer_right( $html ) {
		return '';
	}

	public function progress_footer_left( $html ) {
        if ( RSSSL()->really_simple_ssl->ssl_enabled || !RSSSL()->really_simple_ssl->site_has_ssl)  {
			return '<span class="rsssl-footer-left">Really Simple SSL pro '.rsssl_pro_version.'</span>';
		} else {
            return '';
		}
    }

	/**
     * Override the finished text for free
	 * @return string
	 */
	public function finished_text(){
	    return __("SSL configuration finished!", "really-simple-ssl-pro");
    }

	/**
	 * Set some defaults, then redirect to setttings page on activation.
	 */

	public function rsssl_pro_set_defaults(){
		if (get_transient('rsssl_set_defaults')){
			$this->set_defaults();
			delete_transient('rsssl_set_defaults');
			if (!$this->is_settings_page()) {
			    if ( is_multisite() && is_super_admin() ) {
				    wp_redirect( add_query_arg(array('page' => 'really-simple-ssl'), network_admin_url('settings.php') )  );
				    exit;
                } else {
				    wp_redirect( add_query_arg(array('page'=>'rlrsssl_really_simple_ssl','tab'=>'configuration'), admin_url('options-general.php') ) );
				    exit;
                }
            }

		}
	}

	/**
	 *
	 */
	public function add_pro_system_status(){
		echo "TLS version up to date: " . $this->get_tls_version();
		if ($this->redirects_to_homepage()) {
			echo "Redirect to homepage detected \n";
		}
		if ($this->has_redirect_to_http()) {
			echo "Redirect to http:// detected \n";
		}
		if ($this->site_uses_cache()) {
			echo "Site uses caching \n";
		}
	}

	/**
	 * Add some css for the settings page
	 * @param string $hook
	 * @since  1.0
	 *
	 * @access public
	 *
	 */

	public function enqueue_assets($hook) {

		if ( $hook !== 'settings_page_really-simple-ssl' && $hook !== 'settings_page_rlrsssl_really_simple_ssl' ) return;

		//Datatables plugin to hide pagination when it isn't needed
		wp_register_script('rsssl-datatables-pagination',
			trailingslashit(rsssl_pro_url)
			. 'js/dataTables.conditionalPaging.min.js', array("jquery"), rsssl_pro_version);
		wp_enqueue_script('rsssl-datatables-pagination');

		if ( RSSSL_PRO()->rsssl_scan->has_cleared_scan_data() ) {
			$emptyscantable = __( 'You have scanned your site before, but the scan results are cleared from the cache. Run a new scan to see the results.', 'really-simple-ssl-pro' );
		} elseif ( RSSSL_PRO()->rsssl_scan->scan_completed_no_errors() ) {
			$emptyscantable = __("No mixed content has been found!", "really-simple-ssl-pro");
		} else {
			$emptyscantable = __("No scan done yet. Start a quick or full scan to identify any issues.", "really-simple-ssl-pro");
		}

		wp_register_style('rsssl-pro-datatables', rsssl_pro_url . 'css/datatables.min.css', "", rsssl_pro_version);
		wp_enqueue_style('rsssl-pro-datatables');
		wp_register_style('rsssl-pro-table-css', rsssl_pro_url . 'css/jquery-table.css', "", rsssl_pro_version);
		wp_enqueue_style('rsssl-pro-table-css');
		wp_enqueue_script('rsssl-pro-datatables', rsssl_pro_url . "js/datatables.min.js", array('jquery'), rsssl_pro_version, false);

		wp_enqueue_script('rsssl-bootstrap', rsssl_pro_url . 'bootstrap/js/bootstrap.min.js', array('jquery'), rsssl_pro_version, true);
		wp_register_style( 'rsssl-main', rsssl_pro_url . 'css/main.css',"", rsssl_pro_version );
		wp_enqueue_style( 'rsssl-main');

		wp_register_style( 'rsssl-pro-grid', rsssl_pro_url . 'grid/grid.min.css',"", rsssl_pro_version );
		wp_enqueue_style( 'rsssl-pro-grid');

		wp_enqueue_script('rsssl-main', rsssl_pro_url . 'js/rsssl.js', array('jquery'), rsssl_pro_version, true);
		wp_localize_script('rsssl-main','rsssl_ajax', array(
			'ajaxurl'=> admin_url( 'admin-ajax.php' ),
			'progress' => get_option('rsssl_progress', 0.1),
			'searchPlaceholder' => __("Search", "really-simple-ssl-pro"),
			'emptyScanTable' => $emptyscantable,
			'emptyCspTable' => __("No items reported yet. Come back at a later time.", "really-simple-ssl-pro"),
			'previous' => __("Previous", "really-simple-ssl-pro"),
			'next' => __("Next", "really-simple-ssl-pro"),
			'first' => __("First", "really-simple-ssl-pro"),
			'last' => __("Last", "really-simple-ssl-pro"),
		));
	}

	/**
	 * Run Upgrade procedure
	 */
	public function check_upgrade() {
		if (!current_user_can('manage_options')) return;

		$prev_version = get_option( 'rsssl-pro-current-version', false );

		if ( rsssl_pro_version === $prev_version ) return;

		if ( $prev_version && version_compare( $prev_version, '2.1.19', '<' ) ) {
			do {
				if ( ! file_exists( RSSSL()->really_simple_ssl->htaccess_file() ) ) {
					break;
				}

				if ( RSSSL()->really_simple_ssl->do_not_edit_htaccess ) {
					break;
				}

				$htaccess = file_get_contents( RSSSL()->really_simple_ssl->htaccess_file() );
				if ( ! is_writable( RSSSL()->really_simple_ssl->htaccess_file() ) ) {
					break;
				}

				$htaccess = preg_replace( "/#\s?BEGIN\s?Really_Simple_SSL_HSTS.*?#\s?END\s?Really_Simple_SSL_HSTS/s",
					"", $htaccess );
				$htaccess = preg_replace( "/\n+/", "\n", $htaccess );

				// Save changes
				file_put_contents( RSSSL()->really_simple_ssl->htaccess_file(), $htaccess );

				// Re-run insertion of security headers to make sure HSTS is inserted.
				$this->insert_security_headers(true );

			} while (0);

			// Upgrade elementor option to prefixed one, also since 2.1.19
			update_option( 'rsssl_elementor_upgraded', get_option( 'elementor_upgraded' ) );
			delete_option( 'elementor_upgraded' );
		}

		if ( $prev_version && version_compare( $prev_version, '4.0.0', '<' ) ) {
			if (RSSSL()->really_simple_ssl->hsts) {
				update_option('rsssl_hsts', true);
			}
		}

		if ( $prev_version && version_compare( $prev_version, '4.0.5', '<' ) ) {
			$this->maybe_enable_php_security_headers_option();
		}

        if ( $prev_version && version_compare( $prev_version, '4.1', '<' ) ) {
	        // Upgrade Feature Policy to Permissions Policy
	        if ( $this->get_networkwide_option( 'rsssl_turn_on_feature_policy ' ) ) {
		        $this->update_networkwide_option( 'rsssl_turn_on_permissions_policy', true );
		        $this->delete_networkwide_option( 'rsssl_turn_on_feature_policy' );
	        }

	        if ( $this->get_networkwide_option( 'rsssl_feature_policy' ) ) {
		        $values = $this->get_networkwide_option( 'rsssl_feature_policy' );
		        $this->update_networkwide_option( 'rsssl_permissions_policy', $values );
		        $this->delete_networkwide_option( 'rsssl_feature_policy' );
	        }

	        if ( $this->get_networkwide_option( 'rsssl_pro_feature_policy_headers_for_php ' ) ) {
		        $this->update_networkwide_option( 'rsssl_pro_permissions_policy_headers_for_php', true );
		        $this->delete_networkwide_option( 'rsssl_pro_feature_policy_headers_for_php' );
	        }

	        if ( $this->get_networkwide_option( 'rsssl_turn_on_permissions_policy ' ) ) {
		        $this->replace_feature_policy_rules();
	        }
        }

		update_option( 'rsssl-pro-current-version', rsssl_pro_version );
	}

	/**
	 * Maybe enable the set security headers with php option
	 *
	 * @since 4.1
	 *
	 */

	public function maybe_enable_php_security_headers_option() {
		if ( $this->php_headers_conditions() ) {
			$this->update_networkwide_option('rsssl_enable_php_headers', true);
		} else {
			$this->update_networkwide_option('rsssl_enable_php_headers', false);
		}
	}

	/**
	 * Add pro tab
	 */

	public function add_pro_tabs($tabs)
	{

		$tabs['premium'] = "Premium";
		return $tabs;
	}

	/**
	 * Add pro page
	 */

	public function add_premium_page() {
		if (!current_user_can('manage_options')) return;

		RSSSL()->really_simple_ssl->render_grid( $this->premium_grid() );
	}

	/**
	 * Replace the free support block with the support form and add the premium settings block
	 *
	 * @param array $items
     * @return array
	 */
	public function add_pro_grid_items($items) {
		// Replace free blocks with pro if Really Simple SSL Pro is active
		if (!current_user_can('manage_options')) return $items;

		$items['support'] = array(
			'title' => __("Premium support", "really-simple-ssl-pro"),
			'secondary_header_item' => '',
			'content' => rsssl_pro_template_path . 'support.php',
			'footer' => rsssl_pro_template_path . 'support-footer.php',
			'class' => 'half-height support-form',
			'type' => 'support',
			'can_hide' => true,
		);
		$items['plugins'] = array(
			'title' => __("Premium settings", "really-simple-ssl-pro"),
			'header' => rsssl_template_path . 'header.php',
			'content' => rsssl_pro_template_path . 'settings.php',
			'footer' => rsssl_template_path.'/settings-footer.php',
			'type' => 'settings',
			'class' => 'rsssl-premium-settings half-height',
			'can_hide' => true,
		);
		return $items;
	}

	/**
	 * Add premium grid blocks
	 */
	public function premium_grid() {

		// Add hidden class for Permissions Policy and CSP if corresponding options are not enabled
		if (!$this->get_networkwide_option('rsssl_turn_on_permissions_policy')  || !$this->apply_networkwide_ssl_feature() ) {
			$permissions_policy_hidden = 'rsssl-hidden';
		} else {
			$permissions_policy_hidden = '';
		}

		if (!$this->get_networkwide_option('rsssl_enable_csp_reporting') || !$this->apply_networkwide_ssl_feature() ) {
			$csp_hidden = 'rsssl-hidden';
		} else {
			$csp_hidden = '';
		}

		$grid_items = array(
			'scan' => array(
				'title' => __("Mixed content scan", "really-simple-ssl-pro"),
				'content' => rsssl_pro_template_path.'/scan.php',
				'footer' => rsssl_pro_template_path.'/scan-footer.php',
				'class' => 'regular rsssl-scan-container',
				'type' => 'scan',
				'instructions' => 'https://really-simple-ssl.com/knowledge-base/mixed-content-scan-overview/',
			),
			'security-headers' => array(
				'title' => "Security headers",
				'content' => rsssl_pro_template_path.'/security-headers.php',
				'footer' => rsssl_template_path.'/settings-footer.php',
				'class' => 'regular rsssl-security-headers',
				'type' => 'settings',
                'instructions' => 'https://really-simple-ssl.com/everything-you-need-to-know-about-security-headers/',
			),
			'permissions-policy' => array(
				'title' => "Permissions Policy",
				'content' => rsssl_pro_template_path.'/permissions-policy.php',
				'footer' => rsssl_template_path.'/settings-footer.php',
				'class' => "regular rsssl-datatables rsssl-permissions-policy $permissions_policy_hidden",
				'type' => 'settings',
                'instructions' => 'https://really-simple-ssl.com/knowledge-base/how-to-use-the-permissions-policy-header/',
			),
			'content-security' => array(
				'title' => __("Content Security Policy configuration", "really-simple-ssl-pro"),
				'content' => rsssl_pro_template_path.'/csp.php',
				'class' => "regular rsssl-datatables  rsssl-content-security-policy $csp_hidden",
				'type' => 'all',
			),
		);

		return $grid_items;
	}

	/**
	 * If a user submits the re-check form, we run the check again.
	 */
	public function re_check_http_redirect(){
		if (!RSSSL()->really_simple_ssl->ssl_enabled) {
			$this->has_http_redirect = $this->has_redirect_to_http();
		} else {
			$this->has_http_redirect = false;
		}
	}

	/**
	 *  Run deactivation script
	 */
	public function deactivate(){
		if (!current_user_can('manage_options')) return;

		$this->remove_htaccess_rules('Really_Simple_SSL_SECURITY_HEADERS' );
		$this->remove_secure_cookie_settings();
		update_option('rsssl_pro_csp_notice_next_steps_notice_dismissed', false);
	}

	/**
	 * Change free notices
	 */

	public function change_notices_free(){

		remove_action('rsssl_activation_notice_inner', array(RSSSL()->really_simple_ssl, 'show_pro'), 40);

		if (!RSSSL()->really_simple_ssl->ssl_enabled && $this->has_http_redirect){
			remove_action('rsssl_activation_notice_inner', array(RSSSL()->really_simple_ssl, 'show_enable_ssl_button'), 50);
		}
		add_action('rsssl_activation_notice_inner' , array($this, 'show_scan_buttons_before_activation'), 40);
	}

	/**
	 * Run the replace url s function in Elementor to make sure all resources are loaded over https.
	 *
	 *@throws Exception
	 */

	public function auto_update_elementor_url() {

		if (!current_user_can('manage_options')) {
			return;
		}

		if ( !function_exists( 'rsssl_uses_elementor' ) ) {
			return;
		}

		if (defined('RSSSL_NO_ELEMENTOR_UPGRADE') && RSSSL_NO_ELEMENTOR_UPGRADE) {
			return;
		}

		if ( is_multisite() ) {
			if (!RSSSL()->rsssl_multisite->selected_networkwide_or_per_site ) {
				return;
			}

			if (!get_site_option('rsssl_ms_elementor_urls_upgraded')) {

				// Get sites chunked
				$nr_of_sites = 25;
				$current_public_offset = get_site_option('rsssl_ms_elementor_public_replace_progress');
				$current_private_offset = get_site_option('rsssl_ms_elementor_private_replace_progress');

				$args = array(
					'number'   => $nr_of_sites,
					'offset'   => $current_public_offset,
					'public'   => 1,
					'deleted'  => 0,
					'spam'     => 0,
					'archived' => 0,
				);
				$public_sites = get_sites( $args );

				$args = array(
					'number'   => $nr_of_sites,
					'offset'   => $current_private_offset,
					'public'   => 0,
					'deleted'  => 0,
					'spam'     => 0,
					'archived' => 0,
				);

				$private_sites = get_sites( $args );
				update_site_option('rsssl_ms_elementor_public_replace_progress', $current_public_offset+$nr_of_sites);
				update_site_option('rsssl_ms_elementor_private_replace_progress', $current_private_offset+$nr_of_sites);

				//set batch of sites
				if (count($public_sites) ==! 0) {
					foreach ( $public_sites as $site ) {
						$this->replace_elementor_url( $site );
					}
				}

				if (count($private_sites) ==! 0) {
					foreach ( $private_sites as $site ) {
						$this->replace_elementor_url( $site );
					}
				}

				if (count($public_sites) == 0 && count($private_sites) == 0) {
					update_site_option('rsssl_ms_elementor_urls_upgraded', true);
					update_site_option('rsssl_ms_elementor_public_replace_progress', 0);
					update_site_option('rsssl_ms_elementor_private_replace_progress', 0);
				}
			}

		}
		//run both for multisite as for single site. On ms, this ensures this function will run always when a specific site is loaded.
		$this->replace_elementor_url();


	}

	/**
	 * Replace URLs in elementor to https.
	 *
	 * @param  $site
	 *
	 * @throws Exception
	 */

	public function replace_elementor_url( $site=false ) {
		if (!current_user_can('manage_options')) {
			return;
		}

		if ($site) {switch_to_blog( $site->blog_id );}

		if ( function_exists( 'rsssl_uses_elementor' ) ) {
			if ( RSSSL()->really_simple_ssl->ssl_enabled && rsssl_uses_elementor() && !get_option( 'rsssl_elementor_upgraded' ) ) {

				$url  = home_url();
				$from = str_replace( 'https://', 'http://', $url );
				$to   = str_replace( 'http://', 'https://', $url );

				//non www
				$from_no_www = str_replace("http://www.", "http://", $from);
				$to_no_www = str_replace("https://www.", "https://", $to);

				//www
				$from_www = str_replace("http://", "http://www.", $from_no_www);
				$to_www = str_replace("https://", "https://www.", $to_no_www);

				try {
					if (class_exists('Elementor\Utils')) {
						Elementor\Utils::replace_urls( $from_no_www, $to_no_www );
						Elementor\Utils::replace_urls( $from_www, $to_www );
					}
				}
				catch(Exception $e) {
					error_log("replace URL from Elementor failed");
				}
				update_option( 'rsssl_elementor_upgraded', true );
			}
		}

		if ($site) {restore_current_blog();}
	}

	/**
	 * Activate the mixed content fixer on the admin when enabled.
	 */

	public function admin_mixed_content_fixer(){

		$admin_mixed_content_fixer = get_option("rsssl_admin_mixed_content_fixer");
		if (is_multisite() && RSSSL()->rsssl_multisite->mixed_content_admin) {
			$admin_mixed_content_fixer = true;
		}

		if (is_admin() && is_ssl() && $admin_mixed_content_fixer) {
			RSSSL()->rsssl_mixed_content_fixer->fix_mixed_content();
		}
	}

	/**
	 * Validate options on save
	 *
	 * @param $input
	 *
	 * @return int|string
	 */

	public function options_validate($input){
		if ($input==1){
			$validated_input = 1;
		}else{
			$validated_input = "";
		}
		return $validated_input;
	}

	/**
	 * Validate a text field
	 */

	public function options_validate_text($input)
	{
		if (!current_user_can('manage_options')) {return '';}
		return sanitize_text_field($input);
	}


	/**
	 *
	 * Checks if a redirect to http:// is active to prevent redirect loop issues
	 * Since 2.0.20
	 *
	 * @access public
	 *
	 */

	public function has_redirect_to_http()
	{
		//run this function only once
		$detected_redirect = get_option('rsssl_redirect_to_http_check');
		$force_check = false;

		//but if the user explicitly rechecks, run it again.
		if (isset($_POST['rsssl-check-redirect'])) $force_check = true;

		if ($force_check || !$detected_redirect){
			//make sure this redirect check only happens once by immediately setting a value
			update_option('rsssl_redirect_to_http_check', 'https');
			$url = site_url();
			if (!function_exists('curl_init')) {
				return false;
			}

			//CURLOPT_FOLLOWLOCATION might cause issues on php<5.4
			if (version_compare(PHP_VERSION, '5.4') < 0) {
				return false;
			}

			//Change the http:// domain to https:// to test for a possible redirect back to http://.
			$url = str_replace("http://", "https://", $url);
			//Follow the entire redirect chain.
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_NOBODY, 1);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // follow redirects
			curl_setopt($ch, CURLOPT_AUTOREFERER, 1); // set referer on redirect
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
			curl_setopt($ch, CURLOPT_TIMEOUT, 3); //timeout in seconds
			curl_exec($ch);
			//$target is the endpoint of the redirect chain
			$target = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
			curl_close($ch);

			//Check for http:// needle in target
			$http_needle = 'http://';

			$pos = strpos($target, $http_needle);

			if ($pos !== false) {
				//There is a redirect back to HTTP.
				$detected_redirect = 'http';
			} else {
				$detected_redirect = 'https';
			}
			update_option('rsssl_redirect_to_http_check', $detected_redirect);
		}

		if ($detected_redirect === 'http') {
			return true;
		} else {
			return false;
		}
	}


	/**
	 * @return bool
	 *
	 * Detect if a redirect to homepage is active. Can cause issues with 404 images which are redirect to homepage, making it impossible to locate the origin
	 *
	 */

	public function redirects_to_homepage() {
		$redirect_checked = get_transient('rsssl_redirects_to_homepage');
		if (!$redirect_checked) {
			$redirect_checked = 'OK';
			try {
				if ( ini_get('allow_url_fopen') ) {
					$non_existing_page = str_replace('http://', 'https://', site_url() . "/really-simple-ssl-404-test");

					stream_context_set_default( array(
						'ssl' => array(
							'verify_peer' => false,
							'verify_peer_name' => false,
						),
					));

					$http_headers  = @get_headers( $non_existing_page );
					if ( $http_headers && isset( $http_headers[0]) ) {
						$response_code = substr( $http_headers[0], 9, 3 );

						if ( $response_code === '301' || $response_code === '302' ) {
							//301/302 detected, check if destination matches the site URL. If so, we have a redirect to homepage
							foreach ($http_headers as $key => $header ){
								if (stripos($header, 'location') !== false ) {
									if ( preg_match( '/(http:\/\/|https:\/\/|\/\/)([\w.,;@?^=%&:()\/~+#!\-*].*)/i', $header, $matches )
									) {
										$location = $matches[0];
										//should contain http (end point is http) AND match either with http or https site_url.
										if ( strpos($location, 'http://' )!==false && (str_replace('http://', 'https://', $location) === site_url() ||  $location === site_url()) ) {
											$redirect_checked = 'REDIRECTING';
										}
									}

									break;
								}
							}
						}
					}

				}
			} catch (Exception $e) {
				$redirect_checked = 'OK';
			}
			$expiration = HOUR_IN_SECONDS;
			if ( $redirect_checked === 'OK' ) {
				$expiration = YEAR_IN_SECONDS;
			}
			set_transient('rsssl_redirects_to_homepage', $redirect_checked, $expiration );
		}

		if ( $redirect_checked === 'OK' ) {
			return false;
		} else {
			return true;
		}

	}

	/**
	 * Get the TLS version. Default to version 1.2 if cURL cannot complete.
	 * @return string
	 *
	 *
	 *
	 */

	public function get_tls_version() {

		$tls_version = 'not-found';
		if ( function_exists( 'curl_init' ) ) {
			$tls_version = get_transient('rsssl_tls_version');

			$possible_outcomes = array(
				'outdated',
				'up-to-date',
				'not-found'
			);

			//upgrade to new values
			if (!in_array($tls_version, $possible_outcomes)) $tls_version = false;

			if (!$tls_version) {
				$tls_version = 'not-found';

				$ch = curl_init( 'https://www.howsmyssl.com/a/check' );
				curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
				curl_setopt( $ch, CURLOPT_TIMEOUT, 3 ); //timeout in seconds
				$data = curl_exec( $ch );
				curl_close( $ch );
				$json = json_decode( $data );
				if (!empty($json->tls_version) ) {
					$tls_version = str_replace( "TLS ", "", $json->tls_version );
				}

				if ( $tls_version === '1.0' || $tls_version === '1.1' ) {
					$tls_version = 'outdated';
				} else {
					$tls_version = 'up-to-date';
				}

				set_transient('rsssl_tls_version', $tls_version, MONTH_IN_SECONDS);
			}
		}
		return $tls_version;
	}

	public function maybe_update_csp_activation_time($oldvalue, $newvalue, $option) {

		if (get_option("rsssl_csp_reporting_activation_time") ) {return;}

		if ($oldvalue!=$newvalue) {
			update_option("rsssl_csp_reporting_activation_time", time());
		}

	}

    /**
     *
     * Delete notice transient after plugin activation
     * @since 4.1
     */

	public function delete_notice_cache( ) {
        delete_transient('rsssl_admin_notices');
    }

	/**
	 * Add settings fields
	 */

	public function add_pro_settings(){
		if (!class_exists('REALLY_SIMPLE_SSL')) {return;}

		if (!current_user_can('manage_options')) {return;}

		add_settings_section('rlrsssl_pro_settings_section', '', '', 'rlrsssl_pro_settings_page');

		$help_tip = RSSSL()->rsssl_help->get_help_tip( __("If your certificate expires, your site goes offline. Uptime robots don't alert you when this happens.", "really-simple-ssl-pro")." ".
		                                              sprintf(__("This option sends an email to the administrator email address (%s) when your certificate is about to expire within 2 weeks.", "really-simple-ssl-pro"), get_option('admin_email') ), $return=true );
		add_settings_field('id_cert_expiration_warning', $help_tip . __("Receive an email when your certificate is about to expire","really-simple-ssl-pro"), array($this,'get_option_cert_expiration_warning'), 'rlrsssl_pro_settings_page', 'rlrsssl_pro_settings_section');
		$help_tip = RSSSL()->rsssl_help->get_help_tip(__("Use this option if you do not have the green lock in the WordPress admin.", "really-simple-ssl-pro"), $return=true );
		add_settings_field('id_admin_mixed_content_fixer', $help_tip . __("Mixed content fixer on the WordPress back-end","really-simple-ssl-pro"), array($this,'get_option_admin_mixed_content_fixer'), 'rlrsssl_pro_settings_page', 'rlrsssl_pro_settings_section');

		//add_settings_section('section_rssslpp', __("Pro", "really-simple-ssl-pro"), array($this, "section_text"), 'rlrsssl');
		register_setting( 'rlrsssl_pro_options', 'rsssl_admin_mixed_content_fixer', array($this,'options_validate') );
		register_setting( 'rlrsssl_pro_options', 'rsssl_cert_expiration_warning', array($this,'options_validate') );
		add_settings_section('rlrsssl_security_headers_section', '', '', 'rlrsssl_security_headers_page');

        $help_tip = RSSSL()->rsssl_help->get_help_tip(__("This is an additional header to force all incoming http:// requests to https://.", "really-simple-ssl-pro"), $return=true );
        add_settings_field('id_content_security_policy', $help_tip . __("Upgrade insecure request", "really-simple-ssl-pro"), array($this, 'get_option_content_security_policy'), 'rlrsssl_security_headers_page', 'rlrsssl_security_headers_section');
        register_setting('rlrsssl_security_headers', 'rsssl_content_security_policy', array($this, 'options_validate'));
		$help_tip = RSSSL()->rsssl_help->get_help_tip(__("This header protects your site from cross-site scripting attacks. If a cross-site scripting attack is detected, the browser will automatically sanitize (remove) unsafe parts (scripts) when this header is enabled.", "really-simple-ssl-pro"), $return=true );
		add_settings_field('id_x_xss_protection', $help_tip . "Cross-site scripting (X-XSS) protection", array($this, 'get_option_x_xss_protection'), 'rlrsssl_security_headers_page', 'rlrsssl_security_headers_section');
		register_setting('rlrsssl_security_headers', 'rsssl_x_xss_protection', array($this, 'options_validate'));
		$help_tip = RSSSL()->rsssl_help->get_help_tip(__("This header prevents MIME-sniffing, which can be used to upload malicious files disguised as another content type.", "really-simple-ssl-pro"), $return=true );
		add_settings_field('id_x_content_type_options', $help_tip . "X Content Type Options", array($this, 'get_option_x_content_type_options'), 'rlrsssl_security_headers_page', 'rlrsssl_security_headers_section');
		register_setting('rlrsssl_security_headers', 'rsssl_x_content_type_options', array($this, 'options_validate'));
		$help_tip = RSSSL()->rsssl_help->get_help_tip(__("This header only sets a referrer when navigating to the same protocol (HTTPS->HTTPS) and not when downgrading (HTTPS->HTTP).", "really-simple-ssl-pro"), $return=true );
		add_settings_field('id_no_referrer_when_downgrade', $help_tip . "Referrer Policy", array($this, 'get_option_no_referrer_when_downgrade'), 'rlrsssl_security_headers_page', 'rlrsssl_security_headers_section');
		register_setting('rlrsssl_security_headers', 'rsssl_no_referrer_when_downgrade', array($this, 'options_validate'));
		$help_tip = RSSSL()->rsssl_help->get_help_tip(__("This header enforces certificate transparency. This is done by expecting valid Signed Certificate Timestamps (SCTs).", "really-simple-ssl-pro"), $return=true );
		add_settings_field('id_expect_ct', $help_tip . "Expect CT", array($this, 'get_option_expect_ct'), 'rlrsssl_security_headers_page', 'rlrsssl_security_headers_section');
		register_setting('rlrsssl_security_headers', 'rsssl_expect_ct', array($this, 'options_validate'));
		$help_tip = RSSSL()->rsssl_help->get_help_tip(__("This header prevents your site from being loaded in an iFrame on other domains. This is used to prevent clickjacking attacks.", "really-simple-ssl-pro"), $return=true );
		add_settings_field('id_x-frame-options', $help_tip . "X Frame Options", array($this, 'get_option_x_frame_options'), 'rlrsssl_security_headers_page', 'rlrsssl_security_headers_section');
		register_setting('rlrsssl_security_headers', 'rsssl_x_frame_options', array($this, 'options_validate'));
		
		$help_tip = RSSSL()->rsssl_help->get_help_tip(__("This header allows you to restrict browser features on your own and embedded pages.", "really-simple-ssl-pro")." ".
                                                      __("It is recommended to enable this feature as soon as your site is running smoothly on SSL, as it greatly improves security.", "really-simple-ssl-pro"), $return=true );

		add_settings_field('id_turn_on_permissions_policy',  $help_tip . "Permissions Policy", array($this,'get_option_turn_on_permissions_policy'), 'rlrsssl_security_headers_page', 'rlrsssl_security_headers_section');
		register_setting('rlrsssl_security_headers', 'rsssl_turn_on_permissions_policy', array($this, 'options_validate'));

		$help_tip = RSSSL()->rsssl_help->get_help_tip(__("HSTS, HTTP Strict Transport Security improves your security by forcing all your visitors to go to the SSL version of your website for at least a year.", "really-simple-ssl-pro")." ".__("It is recommended to enable this feature as soon as your site is running smoothly on SSL, as it improves your security.", "really-simple-ssl-pro"), $return=true );
		add_settings_field('id_hsts', $help_tip . "Strict Transport Security (HSTS)", array($this,'get_option_hsts'), 'rlrsssl_security_headers_page', 'rlrsssl_security_headers_section');
		register_setting( 'rlrsssl_security_headers', 'rsssl_hsts', array($this,'options_validate') );

		$help_tip = RSSSL()->rsssl_help->get_help_tip(__("The preload list offers even more security, as browsers will be instructed to load the site over HTTPS, even before the first visit.", "really-simple-ssl-pro")." ".
		                                              __("Please ensure that your site is running over SSL smoothly, and note that all subdomains, including www and non-www domain need to be HTTPS!", "really-simple-ssl-pro"), $return=true );
		add_settings_field('id_hsts_preload', $help_tip . __("Configure your site for the HSTS preload list","really-simple-ssl-pro"), array($this,'get_option_hsts_preload'), 'rlrsssl_security_headers_page', 'rlrsssl_security_headers_section');
		register_setting( 'rlrsssl_security_headers', 'rsssl_hsts_preload', array($this,'options_validate') );

		//CSP Reporting
		$help_tip = RSSSL()->rsssl_help->get_help_tip(__("Content Security Policy reporting. This will detect new entries for the Content Security Policy, but won't enforce them yet.", "really-simple-ssl-pro"), $return=true );
		add_settings_field('id_csp_reporting', $help_tip . __("Content Security Policy reporting", "really-simple-ssl-pro"), array($this, 'get_option_enable_csp_reporting'), 'rlrsssl_security_headers_page', 'rlrsssl_security_headers_section');
		register_setting('rlrsssl_security_headers', 'rsssl_enable_csp_reporting', array($this, 'options_validate'));

		if ($this->get_networkwide_option('rsssl_enable_csp_reporting')) {
			$help_tip = RSSSL()->rsssl_help->get_help_tip(__("A Content Security Policy is a whitelist of trusted sources. This will add an additional block to the settings page where you can select which trusted sources should be added to your Content Security Policy", "really-simple-ssl-pro"), $return=true );
			add_settings_field('id_rsssl_add_csp_rules_to_htaccess', $help_tip . __("Enforce Content Security Policy", "really-simple-ssl-pro"), array($this, 'get_option_rsssl_add_csp_rules_to_htaccess'), 'rlrsssl_security_headers_page', 'rlrsssl_security_headers_section');
			register_setting('rlrsssl_security_headers', 'rsssl_add_csp_rules_to_htaccess', array($this, 'options_validate'));
		}

		if ( $this->php_headers_conditions() ) {
            $help_tip = RSSSL()->rsssl_help->get_help_tip(__("Set the security headers with PHP. Should be enabled when using NGINX as the webserver and one of the headers has been enabled", "really-simple-ssl-pro"), $return = true);
            add_settings_field('id_rsssl_enable_php_headers', $help_tip . __("Set headers with PHP", "really-simple-ssl-pro"), array($this, 'get_option_enable_php_headers'), 'rlrsssl_security_headers_page', 'rlrsssl_security_headers_section');
            register_setting('rlrsssl_security_headers', 'rsssl_enable_php_headers', array($this, 'options_validate'));
        }

		//add an overlay
		if ( !$this->apply_networkwide_ssl_feature() ) {
			$link_open = $link_close = '';
			if (is_super_admin()) {
				$link_open = '<a href="'.add_query_arg(array('page' => 'really-simple-ssl'), network_admin_url('settings.php') ).'">';
				$link_close = '</a>';
			}
			$string = sprintf(__("These settings are only available under the %snetwork settings%s.","really-simple-ssl-pro"), $link_open, $link_close);
			if (!defined('rsssl_pro_ms_template_path')) {
				$link_open = '<a href="https://really-simple-ssl.com/pro">';
				$link_close = '</a>';
				$string = sprintf(__("These settings are only available on the network settings, available with the %sAgency license%s.","really-simple-ssl-pro"), $link_open, $link_close);
            }
			add_settings_field('id_network_admin_only',  '<div class="rsssl-networksettings-overlay"><div class="rsssl-disabled-settings-overlay"><span class="rsssl-progress-status rsssl-open">multisite</span>'.
             $string
             .'</div></div>', array($this,'get_option_network_admin_only'), 'rlrsssl_security_headers_page', 'rlrsssl_security_headers_section');
		}
	}

    /**
     * Add Permissions Policy field
     */
    public function add_permissions_policy_settings() {
        if (!class_exists('REALLY_SIMPLE_SSL') && (!class_exists('REALLY_SIMPLE_SSL_PP'))) {return;}
        if (!current_user_can('manage_options')) {return;}
        add_settings_section('rlrsssl_permissions_policy_section', '', '', 'rlrsssl_permissions_policy_page');
        add_settings_field('id_permissions_policy', '', array($this, 'get_option_permissions_policy'), 'rlrsssl_permissions_policy_page', 'rlrsssl_permissions_policy_section');
        register_setting( 'rlrsssl_permissions_policy_group', 'rsssl_permissions_policy_option ' );
    }

	/**
	 * Check if the recommended headers are enabled
	 *
	 * @return bool
	 */

	public function recommended_headers_enabled() {
		if ($this->get_networkwide_option('rsssl_content_security_policy'  ) &&
		$this->get_networkwide_option('rsssl_x_xss_protection') &&
		$this->get_networkwide_option('rsssl_x_content_type_options') &&
		$this->get_networkwide_option('rsssl_no_referrer_when_downgrade') ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Get list of notices for the dashboard
     * @param array $notices
     *
     * @return array
	 */
	public function get_notices_list($notices)
	{

		//we remove some notices only for free:
		$notices['elementor'] = array(
			'callback' => 'rsssl_uses_elementor',
			'score' => 5,
			'output' => array(
				'uses-elementor' => array(
					'msg' => __("Elementor mixed content successfully converted.", "really-simple-ssl-pro"),
					'icon' => 'success',
					'dismissible' => true
				),
			),
		);

		$notices['redirect_loop_warning'] = array(
			'condition' => array('NOT rsssl_ssl_enabled'),
			'callback' => 'RSSSL_PRO()->rsssl_premium_options->has_redirect_to_http',
			'score' => 10,
			'output' => array(
				'false' => array(
					'msg' => __("No redirect to http detected.", "really-simple-ssl-pro"),
					'icon' => 'success'
				),
				'true' => array(
					'title' => __("Potential redirect loop.", "really-simple-ssl-pro"),
					'msg' => __("A redirect to http was detected. This might result in redirect loops.", "really-simple-ssl-pro").' '.sprintf(__("%sRead more%s.", "really-simple-ssl-pro"), '<a target="_blank" href="https://really-simple-ssl.com/knowledge-base/my-website-is-in-a-redirect-loop/">', '</a>')
                            .'<form action="" method="POST"><input type="submit" class="button" name="rsssl-check-redirect" value="'.__("Re-check the redirect","complianz").'"></form>',
					'icon' => 'warning',
					'admin_notice' => true,
				),
			),
		);

		$notices['certificate_renewal'] = array(
			'condition' => array('rsssl_ssl_enabled', 'rsssl_pro_renewal_notice_enabled'),
			'callback' => 'rsssl_pro_certificate_renewal',
			'score' => 10,
			'output' => array(
				'expiring' => array(
					'msg' => sprintf(__("Your certificate needs to be renewed soon, it is valid to: %s", "really-simple-ssl-pro") , rsssl_pro_expiration_date_nice()),
					'icon' => 'open'
				),
				'not-expiring' => array(
					'msg' => sprintf(__("Your certificate is valid to: %s", "really-simple-ssl-pro") , rsssl_pro_expiration_date_nice()),
					'icon' => 'success'
				),
			),
		);

		$notices['recommended_security_headers_not_set'] = array(
			'callback' => 'RSSSL_PRO()->rsssl_premium_options->recommended_headers_enabled',
			'condition' => array('rsssl_ssl_enabled'),
			'score' => 5,
			'output' => array(
				'false' => array(
					'msg' => sprintf(__("Recommended security headers not enabled (%sRead more%s).", "really-simple-ssl-pro"), '<a target="_blank" href="https://really-simple-ssl.com/everything-you-need-to-know-about-security-headers/">', '</a>'),
					'icon' => 'open',
					'dismissible' => true
				),
				'true' => array(
					'msg' => __("Recommended security headers enabled.", "really-simple-ssl-pro"),
					'icon' => 'success',
				),
			),
		);

		$notices['hsts_enabled'] = array(
			'condition' => array('rsssl_ssl_enabled'),
			'callback' => 'rsssl_pro_hsts_enabled',
			'score' => 10,
			'output' => array(
				'true' => array(
					'msg' =>__("HTTP Strict Transport Security was set.", "really-simple-ssl-pro"),
					'icon' => 'success'
				),
				'false' => array(
                    'url' => RSSSL()->really_simple_ssl->generate_enable_link($setting_name = 'hsts-enabled', 'premium' ),
					'msg' => __("HTTP Strict Transport Security is not enabled. ", "really-simple-ssl-pro"),
					'icon' => 'open',
					'dismissible' => true,
				),
			),
		);

        $notices['security_headers_htaccess_not_writable'] = array(
            'condition' => array('rsssl_security_headers_htaccess_conditions', 'rsssl_ssl_enabled'),
            'callback' => 'rsssl_security_headers_htaccess_writable',
            'score' => 10,
            'output' => array(
                'not-writable' => array(
                    'url' => 'https://really-simple-ssl.com/knowledge-base/htaccess-wp-config-file-not-writable/',
                    'msg' => __(".htaccess file not writable. Set the .htaccess file permissions to writeable or add the following lines manually:", "really-simple-ssl-pro")
                        . "<br>" . " <code>".$this->generate_security_header_rules($html_output=true)."</code>",
                    'icon' => 'warning',
                    'plusone' => true,
                ),
            ),
        );

        $notices['security_headers_php_headers_set'] = array(
            'condition' => array('RSSSL_PRO()->rsssl_premium_options->php_headers_conditions'),
            'callback' => 'rsssl_php_headers_enabled',
            'score' => 10,
            'output' => array(
                'php-headers-caching' => array(
                    'url' => 'https://really-simple-ssl.com/knowledge-base/security-headers-on-nginx/',
                    'msg' => __("Security headers have been set via PHP but your site uses caching. Caching prevents the headers from working correctly. We recommend to add the following security headers to your NGINX configuration file:", "really-simple-ssl-pro")
                        . "<br>" . " <code>".$this->generate_security_header_rules($html_output=true, $type='nginx')."</code>",
                    'icon' => 'warning',
                    'dismissible' => true,
                    'plusone' => true,
                ),
                'php-headers-option-disabled' => array(
                    'url' => 'https://really-simple-ssl.com/knowledge-base/security-headers-on-nginx/',
                    'msg' => __("You have disabled the PHP security header option. Check if the following security headers have been added to NGINX configuration file:", "really-simple-ssl-pro")
                        . "<br>" . " <code>".$this->generate_security_header_rules($html_output=true, $type='nginx')."</code>",
                    'icon' => 'warning',
                    'dismissible' => true,
                    'plusone' => true,
                ),
            ),
        );

		$notices['hsts_preload'] = array(
			'condition' => array('rsssl_ssl_enabled', 'RSSSL()->really_simple_ssl->contains_hsts'),
			'callback' => 'rsssl_pro_hsts_preload',
			'score' => 10,
			'output' => array(
				'true' => array(
					'msg' => sprintf(__("Your site has been configured for the HSTS preload list. If you have submitted your site, it will be preloaded. Click %shere%s to submit.", "really-simple-ssl-pro"),'<a target="_blank" href="https://hstspreload.org/?domain='.$this->non_www_domain().'">', '</a>' ),
					'icon' => 'success'
				),
				'false' => array(
					'url' => RSSSL()->really_simple_ssl->generate_enable_link($setting_name = 'hsts_preload', 'premium' ),
					'msg' => __("Your site is not yet configured for the HSTS preload list.", "really-simple-ssl-pro"),
					'icon' => 'open',
					'dismissible' => true,
				),
			),
		);

		if (isset($notices['secure_cookies_set'])) $notices['secure_cookies_set']['output']['false']['icon'] = 'open';

		$notices['tls_version'] = array(
			'condition' => array('rsssl_ssl_enabled'),
			'callback' => 'RSSSL_PRO()->rsssl_premium_options->get_tls_version',
			'score' => 10,
			'output' => array(
				'up-to-date' => array(
					'msg' => __("TLS version is up-to-date","really-simple-ssl-pro"),
					'icon' => 'success',
				),
				'outdated' => array(
                    'url' => 'https://really-simple-ssl.com/knowledge-base/deprecation-of-tls-1-0-and-1-1/',
					'msg' => __('Your site uses an outdated version of TLS. Upgrade to TLS 1.2 or TLS 1.3 to keep your site secure.',"really-simple-ssl-pro"),
					'icon' => 'warning',
					'dismissible' => true,
					'plusone' => true
				),
			),
		);
		$notices['redirect_to_homepage'] = array(
			'callback' => 'rsssl_redirect_to_homepage',
			'score' => 10,
			'output' => array(
				'redirect-to-homepage' => array(
                    'url' => "https://really-simple-ssl.com/knowledge-base/mixed-content-from-a-domain-image-source-caused-by-a-404-redirect-to-homepage/",
					'msg' => __('Your site redirects 404 pages to the http:// version of your homepage. This can cause mixed content issues with images.',"really-simple-ssl-pro"),
					'icon' => 'warning',
					'dismissible' => true,
					'plusone' => true
				),
			),
		);

		if ($this->get_networkwide_option('rsssl_enable_csp_reporting') ) {
			$notices['new_csp_entries'] = array(
				'condition' => array('rsssl_ssl_enabled'),
				'callback' => 'rsssl_pro_check_for_new_csp_entries',
				'score' => 10,
				'output' => array(
					'new-csp-rules' => array(
						'msg' => __("You have new rules that can be added to your Content Security Policy", "really-simple-ssl-pro"),
						'icon' => 'open',
						'plusone' => true,
						'dismissible' => true
					),
					'no-new-csp-rules' => array(
						'msg' => __("No Content Security Policy violations found", "really-simple-ssl-pro"),
						'icon' => 'success'
					),
				),
			);
		}
        $start_scan = '&nbsp;<a href="'.add_query_arg(array('page'=>'rlrsssl_really_simple_ssl', 'tab' => 'premium', 'rsssl_start_scan' => 1 ), admin_url('options-general.php')).'">'.__("Start scan", "really-simple-ssl-pro").'</a>';
		$notices['mixed_content_scan'] = array(
			'callback' => 'rsssl_pro_scan_notice',
			'score' => 10,
			'output' => array(
				'has-ssl-no-scan-errors' => array(
					'msg' => __("Great! Your scan last completed without errors.", "really-simple-ssl-pro"),
					'icon' => 'success'
				),
				'has-ssl-scan-has-errors' => array(
					'msg' => __("The last scan was completed with errors. Only migrate if you are sure the found errors are not a problem for your site.", "really-simple-ssl-pro"),
					'icon' => 'warning',
					'dismissible' => true
				),
				'no-scan-done' => array(
					'msg' => __("You haven't scanned the site yet, you should scan your site to check for possible issues.", "really-simple-ssl-pro"). $start_scan,
					'icon' => 'open'
				),
				'no-ssl-no-scan-errors' => array(
					'msg' => __("Great! Your scan last completed without errors.", "really-simple-ssl-pro"),
					'icon' => 'success'
				),
				'no-ssl-scan-has-errors' => array(
					'msg' => __("The last scan was completed with errors. Are you sure these issues don't impact your site?", "really-simple-ssl-pro"),
					'icon' => 'warning',
					'dismissible' => true
				),
			),
		);

		$notices['ssl_enabled_networkwide'] = array(
			'callback' => 'rsssl_not_enabled_networkwide',
			'score' => 5,
			'output' => array(
				'htaccess' => array(
					'msg' => sprintf(__("You have a multisite environment. To leverage all configuration options in Really Simple SSL, we recommend to enabled the plugin networkwide, then use the 'activate per site' option to enable SSL per site.", "really-simple-ssl-pro"), '<a target="_blank" href="https://really-simple-ssl.com/pro#multisite">', '</a>'),
					'icon' => 'open',
					'dismissible' => 'true',
				),
			),
		);

		$link =  ' '.sprintf(__("You can upgrade on your %saccount%s.", "really-simple-ssl-pro"), '<a target="blank" href="https://really-simple-ssl.com/account">', '</a>');
		$activate_link = add_query_arg(
		        array(
		                'page' => 'rlrsssl_really_simple_ssl',
                        'tab' => 'license'
                ), admin_url('options-general.php'));
		$activate =  ' '.sprintf(__("%sActivate%s your license.", "really-simple-ssl-pro"), '<a href="'.$activate_link.'">', '</a>');
        $notices['rsssl_pro_license_valid'] = array(
            'callback' => 'rsssl_pro_is_license_expired',
            'score' => 10,
            'output' => array(
                'expired' => array(
                    'title' => __("License", 'really-simple-ssl-pro'),
                    'msg' => __("Your Really Simple SSL Pro license key has expired. Please renew your license to continue receiving updates and premium support.", "really-simple-ssl-pro").$link,
                    'icon' => 'warning',
                    'plusone' => true,
                    'admin_notice' => true,
                ),
                'invalid' => array(
                    'title' => __("License", 'really-simple-ssl-pro'),
                    'msg' => __("Your Really Simple SSL Pro license key is not activated. Please activate your license to continue receiving updates and premium support.", "really-simple-ssl-pro").$activate,
                    'icon' => 'warning',
                    'plusone' => true,
	                'admin_notice' => true,
                ),
                'site_inactive' => array(
                    'title' => __("License", 'really-simple-ssl-pro'),
                    'msg' => __("This domain is not activated for this Really Simple SSL Pro license. Please activate the license for this domain.", "really-simple-ssl-pro").$link,
                    'icon' => 'warning',
                    'plusone' => true,
	                'admin_notice' => true,
                ),
                'no_activations_left' => array(
                    'title' => __("License", 'really-simple-ssl-pro'),
                    'msg' => __("You do not have any activations left on your Really Simple SSL Pro license. Please upgrade your plan for additional activations.", "really-simple-ssl-pro").$link,
                    'icon' => 'warning',
                    'plusone' => false,
	                'admin_notice' => false,
                ),
                'not-activated' => array(
	                'title' => __("License", 'really-simple-ssl-pro'),
	                'msg' => __("Your Really Simple SSL Pro license key hasn't been activated yet. You can activate your license key on the license tab.", "really-simple-ssl-pro").$link,
                    'icon' => 'warning',
	                'plusone' => true,
	                'admin_notice' => true,
                ),
            ),
        );

		return $notices;
	}

	/**
	 * Update option, network or single site
	 * @param string $name
	 * @param mixed $value
	 */
	public function update_networkwide_option($name, $value) {
		if (!current_user_can('manage_options')) return;
		if (is_multisite()) {
			update_site_option($name, $value);
		} else {
			update_option($name, $value);
		}
	}

	/**
	 * Get option, network or single site
	 * @param string name
	 * @return mixed
	 */
	public function get_networkwide_option($name) {
		if (!current_user_can('manage_options')) return;
		if (is_multisite()) {
			return get_site_option($name);
		} else {
			return get_option($name);
		}
	}

    /**
     * Delete option, network or single site
     * @param string name
     * @return mixed
     */
    public function delete_networkwide_option($name) {
        if (!current_user_can('manage_options')) return;
        if (is_multisite()) {
            return delete_site_option($name);
        } else {
            return delete_option($name);
        }
    }

	/**
	 * Insert option into settings form
	 * When multisite is enabled, these options are not accessible here
	 *
	 * @since  1.0.3
	 *
	 * @access public
	 *
	 */

	public function get_option_hsts() {
		$hsts = $this->get_networkwide_option('rsssl_hsts');
		?>
        <label class="rsssl-switch" id="rsssl-maybe-highlight-hsts-enabled">
            <input name="rsssl_hsts" size="40" value="1"
                   type="checkbox" <?php checked(1, $hsts, true) ?> />
            <span class="rsssl-slider rsssl-round"></span>
        </label>
		<?php
	}
	/**
	 * Insert option into settings form
	 * When multisite is enabled, these options are not accessible here
	 *
	 * @since  2.0.0
	 *
	 * @access public
	 *
	 */

	public function get_option_content_security_policy() {
		$content_security_policy = $this->get_networkwide_option('rsssl_content_security_policy');
		?>
        <label class="rsssl-switch">
            <input name="rsssl_content_security_policy" size="40" value="1"
                   type="checkbox" <?php checked(1, $content_security_policy, true) ?> />
            <span class="rsssl-slider rsssl-round"></span>
        </label>
		<?php
	}

    /**
     * Insert option into settings form
     * When multisite is enabled, these options are not accessible here
     *
     * @since  2.0.0
     *
     * @access public
     *
     */

    public function get_option_enable_php_headers() {
        $rsssl_enable_php_headers = $this->get_networkwide_option('rsssl_enable_php_headers');
        ?>
        <label class="rsssl-switch">
            <input name="rsssl_enable_php_headers" size="40" value="1"
                   type="checkbox" <?php checked(1, $rsssl_enable_php_headers, true) ?> />
            <span class="rsssl-slider rsssl-round"></span>
        </label>
        <?php
    }

	/**
	 * Insert option into settings form
	 * When multisite is enabled, these options are not accessible here
	 *
	 * @since  2.0.0
	 *
	 * @access public
	 *
	 */
	public function get_option_x_xss_protection() {
		$x_xss_protection = $this->get_networkwide_option('rsssl_x_xss_protection');
		?>
        <label class="rsssl-switch">
            <input name="rsssl_x_xss_protection" size="40" value="1"
                   type="checkbox" <?php checked(1, $x_xss_protection, true) ?> />
            <span class="rsssl-slider rsssl-round"></span>
        </label>
		<?php
	}
	/**
	 * Insert option into settings form
	 * When multisite is enabled, these options are not accessible here
	 *
	 * @since  2.0.0
	 *
	 * @access public
	 *
	 */
	public function get_option_x_content_type_options() {
		$x_content_type_options = $this->get_networkwide_option('rsssl_x_content_type_options');
		?>
        <label class="rsssl-switch">
            <input name="rsssl_x_content_type_options" size="40" value="1"
                   type="checkbox" <?php checked(1, $x_content_type_options, true) ?> />
            <span class="rsssl-slider rsssl-round"></span>
        </label>
		<?php
	}
	/**
	 * Insert option into settings form
	 * When multisite is enabled, these options are not accessible here
	 *
	 * @since  2.0.0
	 *
	 * @access public
	 *
	 */
	public function get_option_no_referrer_when_downgrade() {
		$no_referrer_when_downgrade = $this->get_networkwide_option('rsssl_no_referrer_when_downgrade');
		?>
        <label class="rsssl-switch">
            <input name="rsssl_no_referrer_when_downgrade" size="40" value="1"
                   type="checkbox" <?php checked(1, $no_referrer_when_downgrade, true) ?> />
            <span class="rsssl-slider rsssl-round"></span>
        </label>
		<?php
	}
	/**
	 * Insert option into settings form
	 * When multisite is enabled, these options are not accessible here
	 *
	 * @since  2.0.0
	 *
	 * @access public
	 *
	 */
	public function get_option_expect_ct() {
		$expect_ct = $this->get_networkwide_option('rsssl_expect_ct');
		?>
        <label class="rsssl-switch">
            <input name="rsssl_expect_ct" size="40" value="1"
                   type="checkbox" <?php checked(1, $expect_ct, true) ?> />
            <span class="rsssl-slider rsssl-round"></span>
        </label>
		<?php
	}
	/**
	 * Insert option into settings form
	 * When multisite is enabled, these options are not accessible here
	 *
	 * @since  2.0.0
	 *
	 * @access public
	 *
	 */
	public function get_option_x_frame_options() {
		$x_frame_options = $this->get_networkwide_option('rsssl_x_frame_options');
		?>
        <label class="rsssl-switch">
            <input name="rsssl_x_frame_options" size="40" value="1"
                   type="checkbox" <?php checked(1, $x_frame_options, true) ?> />
            <span class="rsssl-slider rsssl-round"></span>
        </label>
		<?php
	}
	/**
	 * Insert option into settings form
	 * When multisite is enabled, these options are not accessible here
	 *
	 * @since  2.0.0
	 *
	 * @access public
	 *
	 */
	public function get_option_enable_csp_reporting() {
		$enable_csp_reporting = $this->get_networkwide_option('rsssl_enable_csp_reporting');
		?>
        <label class="rsssl-switch">
            <input name="rsssl_enable_csp_reporting" size="40" value="1"
                   type="checkbox" <?php checked(1, $enable_csp_reporting, true) ?> />
            <span class="rsssl-slider rsssl-round"></span>
        </label>

		<?php
	}
	/**
	 * Insert option into settings form
	 * When multisite is enabled, these options are not accessible here
	 *
	 * @since  2.0.0
	 *
	 * @access public
	 *
	 */
	public function get_option_rsssl_add_csp_rules_to_htaccess() {
		$add_csp_to_htaccess = $this->get_networkwide_option('rsssl_add_csp_rules_to_htaccess');
		?>
        <label class="rsssl-switch">
            <input name="rsssl_add_csp_rules_to_htaccess" size="40" value="1"
                   type="checkbox" <?php checked(1, $add_csp_to_htaccess, true) ?> />
            <span class="rsssl-slider rsssl-round"></span>
        </label>
		<?php
	}
	/**
	 * Insert option into settings form
	 * When multisite is enabled, these options take their value from the multisite settings if these are enabled
	 *
	 * @since  2.0.0
	 *
	 * @access public
	 *
	 */
	public function get_option_cert_expiration_warning() {
		$comment = "";
		$disabled = "";
		$cert_expiration_warning = get_option('rsssl_cert_expiration_warning');
		if ( is_multisite() && rsssl_multisite::this()->cert_expiration_warning) {
			$disabled = "disabled";
			$cert_expiration_warning = TRUE;
			$comment = __("This option is enabled on the network menu.", "really-simple-ssl-pro");
		}

		?>
        <label class="rsssl-switch">
            <input id="rlrsssl_options" name="rsssl_cert_expiration_warning" size="40" value="1"
                   type="checkbox" <?php echo $disabled?> <?php checked(1, $cert_expiration_warning, true) ?> />
            <span class="rsssl-slider rsssl-round"></span>
        </label>
		<?php
		RSSSL()->rsssl_help->get_comment($comment);
	}

	/**
	 * Insert option into settings form
	 * When multisite is enabled, these options are not accessible here
	 *
	 * @since  2.0.0
	 *
	 * @access public
	 *
	 */

	public function get_option_admin_mixed_content_fixer() {
		$admin_mixed_content_fixer = get_option('rsssl_admin_mixed_content_fixer');
		$disabled = "";
		$comment = "";

		if (is_multisite() && RSSSL()->rsssl_multisite->mixed_content_admin) {
			$disabled = "disabled";
			$admin_mixed_content_fixer = true;
			$comment = __( "This option is enabled on the network menu.", "really-simple-ssl-pro" );
		}

		?>
        <label class="rsssl-switch">
            <input id="rlrsssl_options" name="rsssl_admin_mixed_content_fixer" size="40" value="1" <?php echo $disabled?>
                   type="checkbox" <?php checked(1, $admin_mixed_content_fixer, true) ?> />
            <span class="rsssl-slider rsssl-round"></span>
        </label>
		<?php
		RSSSL()->rsssl_help->get_comment($comment);
	}

	/**
	 * Insert option into settings form
	 * When multisite is enabled, these options are not accessible here
	 *
	 * @since  2.0.0
	 *
	 * @access public
	 *
	 */

	public function get_option_hsts_preload() {
		$enabled = $this->get_networkwide_option('rsssl_hsts_preload');

		?>
        <label class="rsssl-switch" id="rsssl-maybe-highlight-hsts_preload">
            <input id="hsts_preload" class="hsts_preload" name="rsssl_hsts_preload" size="40" value="1"
                   type="checkbox" <?php checked(1, $enabled, true) ?> />
            <span class="rsssl-slider rsssl-round"></span>
        </label>
		<?php
		$link_start ='<a target="_blank" href="https://hstspreload.appspot.com/?domain='.$this->non_www_domain().'">';
		$link_close = "</a>";
		$comment = sprintf(__("After enabling this option, you have to %ssubmit%s your site. Please read the instructions on this page carefully before doing so.", "really-simple-ssl-pro"), $link_start, $link_close );
		RSSSL()->rsssl_help->get_comment($comment, 'hsts_preload_expl');
	}

	public function get_option_network_admin_only() {
		/**
		 * Placeholder function to be able to show a notice about network admin settings
		 */
	}

	/**
	 * Insert option into settings form
	 * When multisite is enabled, these options are not accessible here
	 * If .htaccess is not editable, the setting is disabled
	 *
	 * @since  2.0.0
	 *
	 * @access public
	 *
	 */
	public function get_option_turn_on_permissions_policy() {
		?>
        <label class="rsssl-switch">
            <input name="rsssl_turn_on_permissions_policy" size="40" value="1"
                   type="checkbox" <?php checked(1, $this->get_networkwide_option('rsssl_turn_on_permissions_policy'), true) ?> />
            <span class="rsssl-slider rsssl-round"></span>
        </label>
		<?php
	}

	/**
	 * Check if the headers should be set with PHP
	 * @return bool
	 */

	public function php_headers_conditions() {
		if (
			!RSSSL_PRO()->rsssl_premium_options->get_networkwide_option("rsssl_nginx_message_shown")
			&& RSSSL()->rsssl_server->get_server() === 'nginx'
			&& RSSSL_PRO()->rsssl_premium_options->security_header_enabled() )
		{
			return true;
		}
		return false;
	}

	/**
	 * Insert option into settings form
	 *
	 * @since  2.0.0
	 *
	 * @access public
	 *
	 */
	public function get_option_permissions_policy() {
		$permissions_policy_values = $this->get_networkwide_option('rsssl_permissions_policy');
		if (!$permissions_policy_values) {
			$this->set_defaults();
			$permissions_policy_values = $this->get_networkwide_option('rsssl_permissions_policy');
		}
		$options = array(
			'all' => '*',
			'self' => 'self',
			'none' => 'none',
		);

		?>
		<?php foreach( $permissions_policy_values as $option_key => $option_value ) {
			?>
            <tr class="permissions-policy-setting">
                <td class="permissions-policy-name"> <?php
					echo $option_key ?>
                </td>
				<?php foreach ( $options as $key => $value ) {
					?>
                    <td>
                        <input type="radio" name="permissions-policy-<?php echo $option_key ?>" value="<?php echo $value ?>" <?php if ($value == $option_value) echo "checked='checked'" ?>>
                        <label for="<?php echo $option_key ?>">
                    </td>
					<?php
				}
				?>
            </tr>
		<?php } ?>
		<?php
	}

	/**
	 * Save the Permissions Policy values
	 */

	public function save_permissions_policy() {
		if (!current_user_can('manage_options')) {return;}

		if (isset($_POST['security_headers_update']) && wp_verify_nonce($_POST['security_headers_update'], 'submit_security_headers')) {
			$permissions_policy_values = $this->get_networkwide_option( 'rsssl_permissions_policy' );

			if ( empty( $permissions_policy_values ) ) {
				return;
			}

			$safe_keys = array(
				'*',
				'self',
				'none',
			);

			foreach ( $permissions_policy_values as $option_key => $option_value ) {
				if ( isset( $_POST["permissions-policy-$option_key"] ) ) {
					if ( in_array( $_POST["permissions-policy-$option_key"], $safe_keys ) ) {
						$permissions_policy_values[ $option_key ] = $_POST["permissions-policy-$option_key"];
					}
				}
			}

			$this->update_networkwide_option( 'rsssl_permissions_policy', $permissions_policy_values );
		}
	}

    /**
     * Get permissions policy rules
     * @return string
     */

    public function generate_permissions_policy_header() {
        $permissions_policy_values = $this->get_networkwide_option('rsssl_permissions_policy');

        $rule = 'Header always set Permissions-Policy: "';

        foreach ($permissions_policy_values as $policy => $value) {
            $rule .= $policy ."=(" . $value . ")" .", ";
        }

        // Remove last space and , from string as it's not required
        $rule = substr_replace($rule ,"",-2);
        // Add line break
        $rule .= '"'. "\n";

        $php_rule = str_replace("Header always set ", '', $rule);
        update_option('rsssl_pro_permissions_policy_headers_for_php', $php_rule);
        return $rule;
    }

	/**
	 * Set some default values
	 */
	public function set_defaults(){
		if (!current_user_can('manage_options')) return;
		$defaults = array(
			'accelerometer' => '*',
			'autoplay' => '*',
			'camera' => '*',
			'document-domain' => '*',
			'encrypted-media' => '*',
			'fullscreen' => '*',
			'geolocation' => '*',
			'gyroscope' => '*',
			'magnetometer' => '*',
			'microphone' => '*',
			'midi' => '*',
			'payment' => '*',
			'picture-in-picture' => '*',
			'sync-xhr' => '*',
			'usb' => '*',

            //not supported by Chrome:

            //'ambient-light-sensor' => '*',
			//'battery' => '*',
			//'display-capture' => '*',
			//'layout-animations' => '*',
			//'legacy-image-formats' => '*',
			//'oversized-images' => '*',
			//'publickey-credentials' => '*',
			//'wake-lock' => '*',
			//'notifications' => '*',
			//'push' => '*',
			//'speaker' => '*',
			//'vibrate' => '*',
		);
		$this->update_networkwide_option('rsssl_content_security_policy' , true );
		$this->update_networkwide_option('rsssl_permissions_policy' , $defaults );
		$this->update_networkwide_option('rsssl_x_xss_protection', true);
		$this->update_networkwide_option('rsssl_x_content_type_options', true);
		$this->update_networkwide_option('rsssl_no_referrer_when_downgrade', true);

		$this->maybe_enable_php_security_headers_option();
	}

	/**
	 * Get the non www domain.
	 *
	 * @return string
	 */

	public function non_www_domain(){
		$domain = get_home_url();
		return str_replace(array("https://", "http://", "https://www.", "http://www.", "www."), "", $domain);
	}

	/**
	 * Add settings link on plugins overview page
	 * @param array $links
	 *
	 * @return array
	 */

	public function plugin_settings_link($links) {

		if ( is_network_admin() ) {
			$link = add_query_arg( array('page' => 'really-simple-ssl' ), network_admin_url('settings.php') );
		} else {
			$link = add_query_arg( array('page' => 'rlrsssl_really_simple_ssl' ), admin_url('options-general.php') );
		}
		$settings_link = '<a href="'.$link.'">'.__("Settings","really-simple-ssl-pro").'</a>';

		array_unshift($links, $settings_link);
		return $links;
	}

	/**
	 * Generate security headers, and insert in .htaccess file
	 * @param bool $force
	 */

	public function insert_security_headers( $force = false ){
	    if (!$force && !$this->is_settings_page()) return;

		if (defined('rsssl_pp_version') ) return;

		$rules = $this->generate_security_header_rules();

        if (empty($rules)) {
            $this->remove_htaccess_rules('Really_Simple_SSL_SECURITY_HEADERS');
        } else {
            $this->write_to_htaccess($rules, 'Really_Simple_SSL_SECURITY_HEADERS', $force);
        }
    }

    /**
     * @param false $html_output
     * @param false $type
     * @return mixed
     *
     * Get the security headers rules. Use $html_output to get HTML output, set $type to nginx to show NGINX rules
     */

	public function generate_security_header_rules($html_output=false, $type=false) {

		//Get values for each security header
		$hsts = $this->get_networkwide_option('rsssl_hsts');
		$content_security_policy = $this->get_networkwide_option('rsssl_content_security_policy');
		$x_xss_protection = $this->get_networkwide_option('rsssl_x_xss_protection');
		$x_content_type_options = $this->get_networkwide_option('rsssl_x_content_type_options');
		$no_referrer_when_downgrade = $this->get_networkwide_option('rsssl_no_referrer_when_downgrade');
		$expect_ct = $this->get_networkwide_option('rsssl_expect_ct');
		$x_frame_options = $this->get_networkwide_option('rsssl_x_frame_options');
		$permissions_policy = $this->get_networkwide_option('rsssl_turn_on_permissions_policy');

	    // When the code should be shown in a notice, use HTML <br> instead of \n
	    if ($html_output) {
	        $break = "<br>";
        } else {
	        $break = "\n";
        }

        $rule = '';

        if ( $hsts) {
            //not adding  env=HTTPS causes errors on lots of servers.
            // Remove the HSTS header from the old block before adding it to the new block
            $hsts_preload = $this->get_networkwide_option("rsssl_hsts_preload");
            if ($hsts_preload){
                if ($type==='nginx') {
                    $rule .= 'add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;' . $break;
                } else {
                    $rule .= 'Header always set Strict-Transport-Security: "max-age=63072000; includeSubDomains; preload" env=HTTPS';
                }
            } else {
                if ($type==='nginx') {
                    $rule .= 'add_header Strict-Transport-Security: max-age=31536000' . $break;
                } else {
                    $rule .= 'Header always set Strict-Transport-Security: "max-age=31536000" env=HTTPS';
                }
            }
            $rule .= $break;
        }

        // Do not add the upgrade-insecure-requests header here when CSP is enforced, CSP will include this option when it is enabled
        if ($content_security_policy && !$this->get_networkwide_option('rsssl_add_csp_rules_to_htaccess') && !$this->get_networkwide_option('rsssl_enable_csp_reporting')) {
            if ($type==='nginx') {
                $rule .= 'add_header Content-Security-Policy upgrade-insecure-requests;' . $break;
            } else {
                $rule .= 'Header always set Content-Security-Policy "upgrade-insecure-requests"' . $break;
            }
        }

        if ($x_xss_protection) {
            if ($type==='nginx') {
                $rule .= 'add_header X-XSS-Protection "1; mode=block";' . $break;
            } else {
                $rule .= 'Header always set X-XSS-Protection "1; mode=block"' . $break;
            }
        }

        if ($x_content_type_options) {
            if ($type==='nginx') {
                $rule .= 'add_header X-Content-Type-Options nosniff;' . $break;
            } else {
                $rule .= 'Header always set X-Content-Type-Options "nosniff"' . $break;
            }
        }

        if ($no_referrer_when_downgrade) {
            if ($type==='nginx') {
                $rule .= 'add_header Referrer-Policy "no-referrer-when-downgrade";' . $break;
            } else {
                $rule .= 'Header always set Referrer-Policy: "no-referrer-when-downgrade"' . $break;
            }
        }

		if ($permissions_policy) {
			$rule .= $this->generate_permissions_policy_header();
		}

        if ($expect_ct) {
            if ($type==='nginx') {
                $rule .= 'add_header Expect-CT "enforce; max-age=7776000";' . $break;
            } else {
                $rule .= 'Header always set Expect-CT "max-age=7776000, enforce"' . $break;
            }
        }

        if ($x_frame_options) {
            if ($type==='nginx') {
                $rule .= 'add_header X-Frame-Options SAMEORIGIN;' . $break;
            } else {
                $rule .= 'Header always set X-Frame-Options "sameorigin"' . $break;
            }
        }

        if ($html_output) {
            // Remove last line break to end </code> on same line
            $rule = preg_replace('~<br>(?!.*<br>)~', '', $rule);
        }

        return $rule;
    }

	/**
     * Write rules to the .htaccess file
	 * @param string $rules
	 * @param string $name
	 * @param bool $force
	 */

	public function write_to_htaccess( $rules, $name, $force=false ) {

	    //Do not update if this is not the RSSSL settings page
		if ( !$force && !$this->is_settings_page()) return;

		$htaccess_filename = RSSSL()->really_simple_ssl->htaccess_file();

		if ( wp_doing_ajax()
		     || !RSSSL()->really_simple_ssl->ssl_enabled
		     || !current_user_can("activate_plugins")
             || !file_exists( $htaccess_filename )
             || !is_writable( $htaccess_filename )
             || RSSSL()->really_simple_ssl->do_not_edit_htaccess
             || $this->php_headers_conditions()
        ) return;

		$htaccess = file_get_contents( $htaccess_filename );

		//wrap rules
        $output = "# BEGIN ".$name."\n";
        $output .= "<IfModule mod_headers.c>"."\n";
        $output .= $rules;
        $output .= "</IfModule>"."\n";
        $output .= "# END ".$name;
        $output = preg_replace("/\n+/","\n", $output);

		if ( strpos($htaccess, $name) !==false ){
			//replace existing set
			$htaccess = preg_replace("/#\s?BEGIN\s?$name.*?#\s?END\s?$name/s", $output, $htaccess);
		} else {
            //nothing yet, insert fresh set
            $wptag = "# BEGIN WordPress";
			$output = "\n".$output."\n";
            if (strpos($htaccess, $wptag) !== false) {
                $htaccess = str_replace($wptag, $output . $wptag, $htaccess);
            } else {
                $htaccess =  $output . $htaccess;
            }
		}
		$htaccess = str_replace("\n"."\n"."\n", "\n"."\n", $htaccess);
		file_put_contents( $htaccess_filename , $htaccess);
    }

    /**
     * Check if the .htaccess file contains security headers
     * @since 4.1
     */

    public function htaccess_contains_security_headers() {
        $htaccess_file = RSSSL()->really_simple_ssl->htaccess_file();
        if (file_exists($htaccess_file) ) {
            $htaccess = file_get_contents($htaccess_file);
            if (strpos($htaccess, 'Really_Simple_SSL_SECURITY_HEADERS') !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if one of the security header options has been enabled
     * @since 4.1
     */

    public function security_header_enabled() {

        if ($this->get_networkwide_option('rsssl_content_security_policy'  ) ||
            $this->get_networkwide_option('rsssl_x_xss_protection') ||
            $this->get_networkwide_option('rsssl_x_content_type_options') ||
            $this->get_networkwide_option('rsssl_no_referrer_when_downgrade') ||
            $this->get_networkwide_option('rsssl_expect_ct') ||
            $this->get_networkwide_option('rsssl_x_frame_options') ||
            $this->get_networkwide_option('rsssl_turn_on_permissions_policy') ) {
            return true;
        } else {
            return false;
        }
    }

	/**
     * Set the class of the activation notice to error if there are errors, or no scan has been done.
	 * @param $class
	 *
	 * @return string
	 */

	public function activation_notice_color($class){
		$result = RSSSL_PRO()->rsssl_scan->scan_completed_no_errors();
		if ($result !== "COMPLETED") {
			$class = str_replace( 'updated', 'error' , $class );
		}
		return $class;
	}

	public function show_scan_buttons_before_activation() {
		$result = RSSSL_PRO()->rsssl_scan->scan_completed_no_errors();
		$scan_link = add_query_arg(array('page'=>'rlrsssl_really_simple_ssl', 'tab' => 'premium', 'rsssl_start_scan' => 1 ), admin_url('options-general.php'));
		if ( $result == "COMPLETED" ) { ?>
            <div class="rsssl-scan-text-in-activate-notice"><p><?php _e( "You finished a scan without errors.", "really-simple-ssl-pro" ) ?></p></div>
		<?php } elseif ( $result == "NEVER" ) { ?>
            <div class="rsssl-scan-text-in-activate-notice">
                <p>
					<?php
					$link_start = '<a href="'.$scan_link.'">';
					echo sprintf( __( "No scan completed yet. Before migrating to SSL, you should do a %sscan%s", "really-simple-ssl-pro" ), $link_start, "</a>" );
					?>
                </p>
            </div>
		<?php } else { ?>
            <div class="rsssl-scan-text-in-activate-notice">
                <p><?php _e( "Previous scan completed with issues", "really-simple-ssl-pro" ); ?></p>
            </div>
		<?php } ?>

        <div class="rsssl-scan-button" style="margin-right: 10px">
			<?php
			if ( $result != "NEVER" ) {
				$link_start = '<a href="'.$scan_link.'" class="button button-secondary">';
				echo sprintf( __( "%sScan again%s", "really-simple-ssl-pro" ), $link_start, "</a>" );

			} else {
				$link_start = '<a href="'.$scan_link.'" class="button button-secondary">';
				echo sprintf( __( "%sScan for issues%s", "really-simple-ssl-pro" ), $link_start, "</a>" );
			}
			?>
        </div>
		<?php
	}

	/**
     * Remove contents from the .htaccess file from a certain type
	 * @param $name
	 */

	public function remove_htaccess_rules($name) {
		$htaccess_filename = RSSSL()->really_simple_ssl->htaccess_file();

		if ( wp_doing_ajax()
		     || !current_user_can("activate_plugins")
		     || !file_exists( $htaccess_filename )
		     || !is_writable( $htaccess_filename )
		     || RSSSL()->really_simple_ssl->do_not_edit_htaccess
		) return;

        $htaccess = file_get_contents( $htaccess_filename );
        $htaccess = preg_replace("/#\s?BEGIN\s?$name.*?#\s?END\s?$name/s", "", $htaccess);
		$htaccess = str_replace("\n"."\n"."\n", "\n"."\n", $htaccess);

		file_put_contents( $htaccess_filename, $htaccess);
	}


    /**
     * Remove old Feature Policy rules from .htaccess
     * @since 4.1
     */

	public function replace_feature_policy_rules() {

        if ( ! file_exists( RSSSL()->really_simple_ssl->htaccess_file() ) ) {
            return;
        }

        if ( RSSSL()->really_simple_ssl->do_not_edit_htaccess ) {
            return;
        }

        $htaccess = file_get_contents( RSSSL()->really_simple_ssl->htaccess_file() );
        if ( ! is_writable( RSSSL()->really_simple_ssl->htaccess_file() ) ) {
            return;
        }

        $pattern = '/Header always set Feature-Policy(.*?)(.*);/m';
        $replacement = '';
        preg_replace($pattern, $replacement, $htaccess);

        file_put_contents( RSSSL()->really_simple_ssl->htaccess_file(), $htaccess );

        $this->insert_security_headers();
    }

	/**
	 * Insert secure cookie settings
	 */

	public function insert_secure_cookie_settings(){

		if (!current_user_can("activate_plugins")) return;

		if ( wp_doing_ajax() || !$this->is_settings_page() ) return;

		//only if this site has SSL activated, otherwise, remove cookie settings and exit. 
		if (!RSSSL()->really_simple_ssl->ssl_enabled) {
		    $this->remove_secure_cookie_settings();
		    return;
		}

		//do not set on per page installations
		if (defined('rsssl_pp_version')) return;

		//if multisite, only on network wide activated setups
		if ( !$this->apply_networkwide_ssl_feature() ) return;

		//only if cookie settings were not inserted yet
		if (!RSSSL()->really_simple_ssl->contains_secure_cookie_settings() ) {
			$wpconfig_path = RSSSL()->really_simple_ssl->find_wp_config_path();
			$wpconfig = file_get_contents($wpconfig_path);
			if ((strlen($wpconfig)!=0) && is_writable($wpconfig_path)) {
				$rule  = "\n"."//Begin Really Simple SSL session cookie settings"."\n";
				$rule .= "@ini_set('session.cookie_httponly', true);"."\n";
				$rule .= "@ini_set('session.cookie_secure', true);"."\n";
				$rule .= "@ini_set('session.use_only_cookies', true);"."\n";
				$rule .= "//END Really Simple SSL"."\n";

				$insert_after = "<?php";
				$pos = strpos($wpconfig, $insert_after);
				if ($pos !== false) {
					$wpconfig = substr_replace($wpconfig,$rule,$pos+1+strlen($insert_after),0);
				}

				file_put_contents($wpconfig_path, $wpconfig);
			}
		}
	}

	/**
	 * remove secure cookie settings
	 *
	 * @since  2.1
	 *
	 * @access public
	 *
	 */

	public function remove_secure_cookie_settings() {

		if (wp_doing_ajax() || !current_user_can("activate_plugins")) return;

		if (!RSSSL()->really_simple_ssl->contains_secure_cookie_settings()) return;

		$wpconfig_path = RSSSL()->really_simple_ssl->find_wp_config_path();

		if ( !is_writable($wpconfig_path) ) return;

		if (!empty($wpconfig_path)) {
			$wpconfig = file_get_contents($wpconfig_path);
			$wpconfig = preg_replace("/\/\/Begin\s?Really\s?Simple\s?SSL\s?session\s?cookie\s?settings.*?\/\/END\s?Really\s?Simple\s?SSL/s", "", $wpconfig);
			$wpconfig = preg_replace("/\n+/","\n", $wpconfig);
			file_put_contents($wpconfig_path, $wpconfig);
		}
	}

	/**
	 * Show notice if the wp config is not writable
	 */

	public function show_notice_wpconfig_not_writable(){
		//prevent showing on edit screen, as gutenberg removes the class which makes it editable.
		$screen = get_current_screen();
		if ( $screen->base === 'post' ) return;

		if (!current_user_can("activate_plugins")) return;

		//only if this site has SSL activated.
		if (!RSSSL()->really_simple_ssl->ssl_enabled) return;

		//if multisite, only on network wide activated setups
		if (!$this->apply_networkwide_ssl_feature()) return;

		//on multistie, only show this message on the network admin.
		if (is_multisite() && !is_network_admin()) return;

		//do not set on per page installations
		if (defined('rsssl_pp_version')) return;

		$wpconfig_path = RSSSL()->really_simple_ssl->find_wp_config_path();
		if (empty($wpconfig_path)) return;

		if (!RSSSL()->really_simple_ssl->contains_secure_cookie_settings()) {
			ob_start();
			?>
            <p><?php echo __("To set the httponly secure cookie settings, your wp-config.php has to be edited, but the file is not writable.","really-simple-ssl-pro");?></p>
            <p><?php echo __("Add the following lines of code to your wp-config.php.","really-simple-ssl-pro");?></p>
            <br><br>
            <code>
                //Begin Really Simple SSL session cookie settings <br>
                &nbsp;&nbsp;@ini_set('session.cookie_httponly', true); <br>
                &nbsp;&nbsp;@ini_set('session.cookie_secure', true); <br>
                &nbsp;&nbsp;@ini_set('session.use_only_cookies', true); <br>
                //END Really Simple SSL cookie settings <br>
            </code>
            <br>
            <p><?php echo __("Or set your wp-config.php to writable and reload this page.", "really-simple-ssl-pro");?></p>

			<?php
			$content = ob_get_clean();
			$class = "error";
			$title = __("Could not insert httponly secure cookie settings.","really-simple-ssl-pro");
			echo RSSSL()->really_simple_ssl->notice_html( $class, $title, $content );
		}
	}

	/**
	 * Show notice CSP next step
	 */

	public function show_notice_csp_enabled_next_steps()
	{
		if ($this->get_networkwide_option('rsssl_enable_csp_reporting') ) {

			// If notice has been permanently dismissed, or notice has been temporarily dismissed for a week which hasn't passed, return
			if (get_option("rsssl_pro_csp_notice_next_steps_notice_dismissed") || (get_option('rsssl_pro_csp_notice_next_steps_notice_postponed') && !$this->csp_noticed_dismissed_more_than_one_week_ago() ) ) {
				return;
			}

			add_action('admin_print_footer_scripts', array($this, 'insert_csp_next_steps_dismiss'));

			$link_open = '<a target="_blank" href="https://really-simple-ssl.com/knowledge-base/how-to-use-the-content-security-policy-generator/">';
			$link_close = '</a>';
			$premium_tab = esc_url( admin_url("options-general.php?page=rlrsssl_really_simple_ssl&tab=premium") );
			$csp_link_open = "<a href='$premium_tab'/>";
			$csp_link_close = '</a>';

			ob_start();

			?>
            <p><?php _e("Follow these steps to complete the setup:", "really-simple-ssl-pro"); ?></p>
            <ul class="message-ul">
                <li class="rsssl-activation-notice-li"><div class="rsssl-bullet"></div><?php _e("Let it gather data from the website for a couple of days", "really-simple-ssl-pro"); ?></li>
                <li class="rsssl-activation-notice-li"><div class="rsssl-bullet"></div><?php _e("Newly found rules can be found in the Content Security Policy tab", "really-simple-ssl-pro"); ?></li>
                <li class="rsssl-activation-notice-li"><div class="rsssl-bullet"></div><?php printf(__("When no new exceptions have been found, you can enforce the Content Security Policy rules by enabling the 'Enforce Content Security Policy' option in the %ssecurity headers block%s", "really-simple-ssl-pro"), $csp_link_open, $csp_link_close); ?></li>
                <li class="rsssl-activation-notice-li"><div class="rsssl-bullet"></div><?php printf(__("For a detailed explanation of the Content Security Policy, see this %slink%s", "really-simple-ssl-pro"), $link_open, $link_close); ?></li>
            </ul>
			<?php
			$content = ob_get_clean();
			$class = "updated is-dismissible";
			$title = __("Content Security Policy reporting enabled", "really-simple-ssl-pro");
			$footer = "<a class='button button-rsssl-primary' id='rsssl-csp-reporting-remind-me' href=''>" . __("Remind me in one week", "really-simple-ssl-pro") . "</a>";
			echo RSSSL()->really_simple_ssl->notice_html( $class, $title, $content, $footer );
		}
	}

	public function insert_csp_next_steps_dismiss() {
		if (!get_option("rsssl_pro_csp_notice_next_steps_notice_dismissed") ) {
			$ajax_nonce = wp_create_nonce( "really-simple-ssl-dismiss" );
			?>
            <script type='text/javascript'>
                jQuery(document).ready(function($) {
                    $(".notice.updated.is-dismissible").on("click", ".notice-dismiss", function(event){
                        rsssl_dismiss_csp_notice('dismiss');
                    });
                    $( "#rsssl-csp-reporting-remind-me" ).click(function() {
                        rsssl_dismiss_csp_notice('later');
                    });
                    function rsssl_dismiss_csp_notice(type){
                        var data = {
                            'action': 'dismiss_csp_next_steps_notice',
                            'type' : type,
                            'security': '<?php echo $ajax_nonce; ?>'
                        };
                        $.post(ajaxurl, data, function (response) {});
                    }
                });
            </script>
			<?php
		}
	}

	public function dismiss_csp_next_steps_notice_callback()
	{
		$type = isset($_POST['type']) ? $_POST['type'] : false;

		if ($type === 'dismiss') {
			update_option('rsssl_pro_csp_notice_next_steps_notice_dismissed', true);
		}

		if ($type === 'later') {
			update_option('rsssl_csp_reporting_dismissed_timestamp', time());
			update_option('rsssl_pro_csp_notice_next_steps_notice_postponed', true);
		}
		wp_die(); // this is required to terminate immediately and return a proper response
	}

	public function csp_noticed_dismissed_more_than_one_week_ago() {

		$enabled_csp_reporting_time = get_option( 'rsssl_csp_reporting_dismissed_timestamp' );
		$one_week_after_dismiss = $enabled_csp_reporting_time + 7 * DAY_IN_SECONDS;

		if ( time() < $one_week_after_dismiss ) {
			return false;
		} else {
			return true;
		}
	}


	public function update_nginx_conf() {
		$file = 'nginx.conf';

		if (!is_file($file)) {
			if (!file_exists("nginx.conf")) {
				makefile("nginx.conf");
			}
		}
	}

	/**
	 * Check if site uses one of the most common caching tools.
	 *
	 * @return bool
	 */

	public function site_uses_cache(){

	    // W3 Total Cache
		if ( function_exists('w3tc_flush_all') ) {
			return true;
		}

		// WP Fastest Cache
		if ( class_exists('WpFastestCache') ) {
			return true;
		}

		// WP Rocket
		if ( function_exists("rocket_clean_domain") ) {
			return true;
		}

		// WP Optimize
		if ( defined('WPO_PLUGIN_MAIN_PATH') ) {
		    return true;
        }

		// WP Super Cache
        if ( defined('WPCACHEHOME') ) {
            return true;
        }

        // Hummingbird
        if ( defined('WPHB_VERSION') ) {
            return true;
        }

        // Litespeed cache
        if ( defined('LSCWP_V') ) {
            return true;
        }

        // Autoptimize
        if ( defined('AUTOPTIMIZE_PLUGIN_VERSION') ) {
            return true;
        }

        // Cache enabler
        if ( defined('CE_VERSION') ) {
            return true;
        }

		return false;
	}

	public function maybe_clear_certificate_check_schedule($oldvalue, $newvalue, $option){

		if (!get_option('rsssl_cert_expiration_warning')){
			wp_clear_scheduled_hook('rsssl_pro_daily_hook');
		}
	}

	/**
	 * Check to see if we are on the settings page, action hook independent
	 *
	 * @since  2.5
	 *
	 * @access public
	 *
	 */

	public function is_settings_page()
	{
		if (isset($_GET["page"]) && ($_GET["page"] == "rlrsssl_really_simple_ssl" || $_GET["page"] == "really-simple-ssl") ) {
			return true;
		}
		return false;
	}

	/**
	 * Check if we can apply a setting network wide
	 */

	public function apply_networkwide_ssl_feature(){

		//if single site, always apply
		if (!is_multisite()) return true;

		//if multisite, only apply if we're on the network admin
		if ( is_multisite() && is_network_admin() ) return true;

		return false;
	}

}//class closure

if (!function_exists('rsssl_pro_certificate_renewal')) {
	function rsssl_pro_certificate_renewal() {
		if ( is_ssl() && RSSSL_PRO()->rsssl_premium_options->get_networkwide_option( 'rsssl_cert_expiration_warning' ) || ( is_multisite() && RSSSL()->rsssl_multisite->cert_expiration_warning ) ) {

			$expiring = rsssl_pro_almost_expired();

			if ( $expiring ) {
				return 'expiring';
			}
		}

		return 'not-expiring';
	}
}

if (!function_exists('rsssl_pro_renewal_notice_enabled')) {
	function rsssl_pro_renewal_notice_enabled() {
		if ( RSSSL_PRO()->rsssl_premium_options->get_networkwide_option( 'rsssl_cert_expiration_warning' ) ) {
			return true;
		} else {
			return false;
		}
	}
}

if (!function_exists('rsssl_not_enabled_networkwide')) {
	function rsssl_not_enabled_networkwide() {
		if(is_multisite() && !RSSSL()->rsssl_multisite->plugin_network_wide_active() ){
			return true;
		}
		return false;
	}
}

if (!function_exists('rsssl_pro_hsts_enabled')) {
	function rsssl_pro_hsts_enabled() {
		return RSSSL_PRO()->rsssl_premium_options->get_networkwide_option( 'rsssl_hsts' );
	}
}

if (!function_exists('rsssl_security_headers_htaccess_writeble') ) {
    function rsssl_security_headers_htaccess_writable()
    {
        return 'not-writable';
    }
}

if (!function_exists('rsssl_security_headers_htaccess_conditions') ) {
    function rsssl_security_headers_htaccess_conditions()
    {
        if ( RSSSL()->rsssl_server->uses_htaccess()
            && ( !is_writable(RSSSL()->really_simple_ssl->htaccess_file() ) || RSSSL()->really_simple_ssl->do_not_edit_htaccess )
            && RSSSL_PRO()->rsssl_premium_options->security_header_enabled()
            && !RSSSL_PRO()->rsssl_premium_options->htaccess_contains_security_headers() )
        {
            return true;
        }
        return false;
    }
}

if (!function_exists('rsssl_php_headers_enabled') ) {
    function rsssl_php_headers_enabled() {
        if ( RSSSL_PRO()->rsssl_premium_options->site_uses_cache() ) {
            return 'php-headers-caching';
        } elseif( !get_option('rsssl_enable_php_headers') ) {
            return 'php-headers-option-disabled';
        }
    }
}

if (!function_exists('rsssl_pro_hsts_preload')) {
	function rsssl_pro_hsts_preload() {
		if ( RSSSL_PRO()->rsssl_premium_options->get_networkwide_option('rsssl_hsts') && RSSSL_PRO()->rsssl_premium_options->get_networkwide_option( 'rsssl_hsts_preload' ) ) {
			return true;
		}

		return false;
	}
}

if (!function_exists('rsssl_pro_check_for_new_csp_entries')) {
	function rsssl_pro_check_for_new_csp_entries() {

		global $wpdb;

		$table_name = $wpdb->base_prefix . "rsssl_csp_log";
		//Check if there are any inpolicy values that are not true. If so, new rules can be added to the Content Security Policy. Show a warning in dashboard when new rules can be added, if all rules have been added show a checkmark
		$count = $wpdb->get_var( "SELECT count(*) FROM $table_name where inpolicy != 'true'" );

		if ( $count > 0 ) {
			return 'new-csp-rules';
		}

		return 'no-new-csp-rules';
	}
}

if (!function_exists('rsssl_pro_admin_mixed_content_fixer')) {
	function rsssl_pro_admin_mixed_content_fixer() {
		/*  Display the current settings for the admin mixed content. */
		$admin_mixed_content_fixer = get_option( "rsssl_admin_mixed_content_fixer" );

		if ( $admin_mixed_content_fixer ) {
			return 'admin-mixed-content-fixer-activated';
		}

		return 'admin-mixed-content-fixer-not-activated';
	}
}

if ( !function_exists('rsssl_pro_scan_notice') ) {
	function rsssl_pro_scan_notice() {
		if ( ! RSSSL()->really_simple_ssl->site_has_ssl ) {
			if ( RSSSL_PRO()->rsssl_scan->scan_completed_no_errors() == "COMPLETED" ) {
				return 'has-ssl-no-scan-errors';
			} elseif ( RSSSL_PRO()->rsssl_scan->scan_completed_no_errors() == "ERRORS" ) {
				return 'has-ssl-scan-has-errors';
			} else {
				return 'no-scan-done';
			}
		} else {
			if ( RSSSL_PRO()->rsssl_scan->scan_completed_no_errors() == "COMPLETED" ) {
				return 'no-ssl-no-scan-errors';
			} elseif ( RSSSL_PRO()->rsssl_scan->scan_completed_no_errors() == "ERRORS" ) {
				return 'no-ssl-scan-has-errors';
			} else {
				return 'no-scan-done';
			}
		}
	}
}

if (!function_exists('rsssl_pro_is_license_expired')) {
	function rsssl_pro_is_license_expired() {
		$status = RSSSL_PRO()->rsssl_licensing->get_license_status();
		if ( ! $status || $status === 'empty' || $status === 'site_inactive' || $status === 'deactivated' || $status === 'inactive' ) {
			return 'not-activated';
		} else if ( $status === 'revoked' || $status === 'missing' || $status ==='item_name_mismatch') {
		    return 'expired';
		} else if ($status === 'no_activations_left') {
		    return 'no_activations_left';
		} else {
		    return $status;
        }

	}
}

if (!function_exists('rsssl_redirect_to_homepage')) {
	function rsssl_redirect_to_homepage() {
		$redirect_to_homepage = RSSSL_PRO()->rsssl_premium_options->redirects_to_homepage();

		if ( $redirect_to_homepage == true ) {
			return 'redirect-to-homepage';
		}
	}
}