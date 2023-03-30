<?php
defined('ABSPATH') or die("you do not have access to this page!");

use Elementor\Utils;

class rsssl_pro_admin {
	private static $_this;
	public $has_http_redirect=false;

	function __construct() {
		if ( isset( self::$_this ) ) {
			wp_die( sprintf( __( '%s is a singleton class and you cannot create a second instance.', 'really-simple-ssl-pro' ), get_class( $this ) ) );
		}

		self::$_this = $this;
		add_action( 'admin_init', array( $this, 'auto_update_elementor_url' ), 15);
        add_action( 'admin_init', array( $this, 'maybe_redirect_to_settings_page' ), 40);
        add_action( 'activate_plugin', array( $this, 'delete_notice_cache' ), 10, 3);
		add_action( 'admin_init', array( $this, 're_check_http_redirect' ), 2 );
		$plugin = rsssl_pro_plugin;
		add_filter( "plugin_action_links_$plugin", array($this,'plugin_settings_link' ) );
		add_filter( "rsssl_settings_link", array($this,'free_settings_link') );
		add_filter( "network_admin_plugin_action_links_$plugin", array($this,'plugin_settings_link' ) );
		add_filter( 'rsssl_notices', array($this,'get_notices_list'),20, 1 );
		add_filter( 'rsssl_system_status', array($this, 'add_pro_system_status'));
		add_action( 'rsssl_deactivate', array($this, 'deactivate'), 90 );
		add_filter( 'site_status_tests', array($this, 'override_site_health_headers' ), 100 );
		add_filter( 'rsssl_localize_script', array($this, 'add_pro_version' ) );
	}

	/**
	 * Set some defaults
	 */

	public function activate() {

		set_transient( 'rsssl_pro_redirect_to_settings_page', true, DAY_IN_SECONDS );
		if ( RSSSL_PRO()->is_compatible() ) {
			/**
			 * Set some defaults, then redirect to settings page on activation.
			 */

			if ( ! rsssl_user_can_manage() ) {
				return;
			}

			if ( ! get_site_option( 'rsssl_pro_defaults_set' ) ) {
				//run test, if not already done
				RSSSL_PRO()->headers->reload_headers_check_cache();

				// Only update default values for permissions policy when option hasn't been created
				if ( ! rsssl_get_option( 'permissions_policy' ) ) {
					$fields            = rsssl_fields( false );
					$ids               = array_column( $fields, 'id' );
					$permissions_index = array_search( 'permissions_policy', $ids );
					$permissions_field = $fields[ $permissions_index ];
					rsssl_update_option( 'permissions_policy', $permissions_field['default'] );
				}

				if ( is_ssl() ) rsssl_update_option( 'upgrade_insecure_requests', true );
				rsssl_update_option( 'x_xss_protection', 'zero' );
				rsssl_update_option( 'x_content_type_options', true );
				rsssl_update_option( 'x_frame_options', 'SAMEORIGIN' );
				rsssl_update_option( 'referrer_policy', 'strict-origin-when-cross-origin' );
				rsssl_update_option( 'content_security_policy', 'disabled' );
				update_option( 'rsssl_enable_csp_defaults', true, false );
				update_site_option( 'rsssl_pro_defaults_set', true );
				RSSSL_PRO()->headers->insert_security_headers( true );
			}

		}
	}


	public function deactivate()
	{
		wp_clear_scheduled_hook('rsssl_pro_daily_hook');
		RSSSL_PRO()->headers->remove_advanced_headers();
	}

	/**
	 * Add our premium support link to the free plugin
	 * @param $link
	 *
	 * @return string
	 */
	public function free_settings_link($link){
		return '<a target="_blank" href="https://really-simple-ssl.com/support">' . __('Premium Support', 'really-simple-ssl') . '</a>';
	}

	static function this() {
		return self::$_this;
	}

	/**
	 * Add pro version to localized args
	 * @param $args
	 *
	 * @return array
	 */
	public function add_pro_version($args){
		$args['pro_version'] = rsssl_pro_version;
		$args['pro_url'] = rsssl_pro_url;
		return $args;
	}

	/**
     * Drop de site health notices about headers, as we already have implemented them through pro
	 * @param array $tests
	 *
	 * @return array
	 */
	public function override_site_health_headers( $tests ) {
		unset($tests['direct']['rsssl-headers']);
    	return $tests;
	}

	/**
	 *
	 */
	public function add_pro_system_status($output){
		$output .= "Really Simple SSL Pro version: " . rsssl_pro_version . "\n";
		$output .= "TLS version up to date: " . $this->get_tls_version(). "\n";
		if ($this->redirects_to_homepage()) {
			$output .= "Redirect to homepage detected \n";
		}
		if ($this->has_redirect_to_http()) {
			$output .= "Redirect to http:// detected \n";
		}
		if ($this->site_uses_cache()) {
			$output .= "Site uses caching \n";
		}
		return $output;
	}

	/**
     * @deprecated, for upgrade only
	 * Update option, network or single site
	 * @param string $name
	 * @param mixed $value
	 */
	public function update_networkwide_option($name, $value) {
		if ( is_multisite() ) {
			update_site_option($name, $value);
		} else {
			update_option($name, $value);
		}
	}

	/**
     * @deprecated, for upgrade only
	 * Get option, network or single site
	 * @param string name
	 * @return mixed
	 */
	public function get_networkwide_option($name) {
		if ( is_multisite() ) {
			return get_site_option($name);
		} else {
			return get_option($name);
		}
	}
	/**
	 * @deprecated, for upgrade only
	 * Get option, network or single site
	 * @param string name
	 * @return mixed
	 */
	public function delete_networkwide_option($name) {
		if ( is_multisite() ) {
			return delete_site_option($name);
		} else {
			return delete_option($name);
		}
	}


    /**
     * Maybe redirect to settings page
     * @since 4.1.4
     */

	public function maybe_redirect_to_settings_page() {
        if ( get_transient('rsssl_pro_redirect_to_settings_page' ) ) {
            delete_transient('rsssl_pro_redirect_to_settings_page' );
            if ( !$this->is_settings_page() ) {
			    if ( is_multisite() && is_super_admin() ) {
				    wp_redirect( add_query_arg(array('page' => 'really-simple-security'), network_admin_url('settings.php') )  );
				    exit;
                } else {
				    wp_redirect( add_query_arg(array('page'=>'really-simple-security'), admin_url('options-general.php') ) );
				    exit;
                }
            }
        }
    }

	/**
	 * If a user submits the re-check form, we run the check again.
	 */
	public function re_check_http_redirect() {
		if ( !rsssl_get_option('ssl_enabled') ) {
			$this->has_http_redirect = $this->has_redirect_to_http();
		} else {
			$this->has_http_redirect = false;
		}
	}

	/**
	 * Run the replace url s function in Elementor to make sure all resources are loaded over https.
	 *
	 *@throws Exception
	 */

	public function auto_update_elementor_url() {

		if ( !rsssl_user_can_manage() ) {
			return;
		}

		if ( !function_exists( 'rsssl_uses_elementor' ) ) {
			return;
		}

		if ( defined('RSSSL_NO_ELEMENTOR_UPGRADE') && RSSSL_NO_ELEMENTOR_UPGRADE ) {
			return;
		}

		if ( is_multisite() ) {
			if (!rsssl_get_option('ssl_enabled') ) {
				return;
			}

			if ( !get_site_option('rsssl_ms_elementor_urls_upgraded') ) {

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
		if (!rsssl_user_can_manage()) {
			return;
		}

		if ($site) {switch_to_blog( $site->blog_id );}

		if ( function_exists( 'rsssl_uses_elementor' ) ) {
			if ( rsssl_get_option('ssl_enabled') && rsssl_uses_elementor() && !get_option( 'rsssl_elementor_upgraded' ) ) {

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
				}
				update_option( 'rsssl_elementor_upgraded', true, false );
			}
		}

		if ($site) {restore_current_blog();}
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
			update_option('rsssl_redirect_to_http_check', 'https', false );
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
			update_option('rsssl_redirect_to_http_check', $detected_redirect, false );
		}

		if ($detected_redirect === 'http') {
			return true;
		} else {
			return false;
		}
	}

	/**
     * Detect if a redirect to homepage is active. Can cause issues with 404 images which are redirect to homepage, making it impossible to locate the origin
     *
	 * @return bool
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
     *
	 * @return string
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



    /**
     *
     * Delete notice transient after plugin activation
     * @since 4.1
     */

	public function delete_notice_cache( ) {
        delete_transient('rsssl_admin_notices');
    }

	/**
	 * Get list of notices for the dashboard
     * @param array $notices
     *
     * @return array
	 */
	public function get_notices_list($notices)
	{
        unset($notices['pro_upsell']);
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
			'callback' => 'RSSSL_PRO()->admin->has_redirect_to_http',
			'score' => 10,
			'output' => array(
				'false' => array(
					'title' => __("Potential redirect loop.", "really-simple-ssl-pro"),
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

		$notices['tls_version'] = array(
			'condition' => array('rsssl_ssl_enabled'),
			'callback' => 'RSSSL_PRO()->admin->get_tls_version',
			'score' => 10,
			'output' => array(
				'up-to-date' => array(
					'title' => __("TLS version", "really-simple-ssl-pro"),
					'msg' => __("TLS version is up-to-date","really-simple-ssl-pro"),
					'icon' => 'success',
				),
				'outdated' => array(
                    'url' => 'https://really-simple-ssl.com/knowledge-base/deprecation-of-tls-1-0-and-1-1/',
                    'title' => __("TLS version", "really-simple-ssl-pro"),
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

		$notices['free_compatibility'] = array(
			'condition' => array('rsssl_free_incompatible'),
			'callback' => '_true_',
			'score' => 5,
			'output' => array(
				'true' => array(
					'msg' =>  __("Update Really Simple SSL Free to the latest version for optimal compatibility.", "really-simple-ssl-pro"),
					'icon' => 'open',
					'dismissible' => 'true',
					'plusone' => true,
				),
			),
		);

		return $notices;
	}

	/**
	 * Add settings link on plugins overview page
	 * @param array $links
	 *
	 * @return array
	 */

	public function plugin_settings_link($links) {

		if ( !is_multisite() || ( is_multisite() && is_network_admin() ) ) {
			if ( is_multisite() ) {
				$link = add_query_arg( array('page' => 'really-simple-security' ), network_admin_url('settings.php') );
			} else {
				$link = add_query_arg( array('page' => 'really-simple-security' ), admin_url('options-general.php') );
			}
			$settings_link = '<a href="'.$link.'">'.__("Settings","really-simple-ssl-pro").'</a>';
			array_unshift($links, $settings_link);
		}

		return $links;
	}

	/**
	 * @deprecated for upgrade to 6.0 only
	 *
     * Remove contents from the .htaccess file from a certain type
	 * @param string $name
     * @param bool $force
	 */

	public function remove_htaccess_rules($name, $force=false ) {
		//Do not update if this is not the RSSSL settings page
		if ( !$force && !$this->is_settings_page() ) {
			return;
		}

		$htaccess_filename = RSSSL()->admin->htaccess_file();
		if (
			 wp_doing_ajax()
		     || (!rsssl_user_can_manage() && !defined('RSSSL_LEARNING_MODE'))
		     || !file_exists( $htaccess_filename )
		     || !is_writable( $htaccess_filename )
			 || rsssl_get_option('do_not_edit_htaccess')
		) {
		    return;
        }

		if ( RSSSL_PRO()->headers->is_header_check_running() ){
            return;
		}

        $htaccess = file_get_contents( $htaccess_filename );
        $htaccess = preg_replace("/#\s?BEGIN\s?$name.*?#\s?END\s?$name/s", "", $htaccess);
		$htaccess = str_replace("\n"."\n"."\n", "\n"."\n", $htaccess);
		file_put_contents( $htaccess_filename, $htaccess);
	}

	/**
	 * Check if site uses one of the most common caching tools.
	 *
	 * @return mixed
	 */

	public function site_uses_cache($str=false){
	    // W3 Total Cache
		if ( function_exists('w3tc_flush_all') ) {
		    if ($str) {
		        return 'W3 Total Cache';
		    } else {
			    return true;
		    }
		}

		// WP Fastest Cache
		if ( class_exists('WpFastestCache') ) {
			if ($str) {
				return 'Wp Fastest Cache';
			} else {
				return true;
			}
		}

		// WP Rocket
		if ( function_exists("rocket_clean_domain") ) {
			if ($str) {
				return 'WP Rocket';
			} else {
				return true;
			}
		}

		// WP Optimize
		if ( defined('WPO_PLUGIN_MAIN_PATH') ) {
			if ($str) {
				return 'WP Optimize';
			} else {
				return true;
			}
        }

		// WP Super Cache
        if ( defined('WPCACHEHOME') ) {
	        if ($str) {
		        return 'WP Super Cache';
	        } else {
		        return true;
	        }
        }

        // Hummingbird
        if ( defined('WPHB_VERSION') ) {
	        if ($str) {
		        return 'Hummingbird';
	        } else {
		        return true;
	        }
        }

        // Litespeed cache
        if ( defined('LSCWP_V') ) {
	        if ($str) {
		        return 'Litespeed cache';
	        } else {
		        return true;
	        }
        }

        // Autoptimize
        if ( defined('AUTOPTIMIZE_PLUGIN_VERSION') ) {
	        if ($str) {
		        return 'Autoptimize';
	        } else {
		        return true;
	        }
        }

        // Cache enabler
        if ( defined('CE_VERSION') ) {
	        if ($str) {
		        return 'Cache enabler';
	        } else {
		        return true;
	        }
        }

		return false;
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
        if ( rsssl_is_logged_in_rest() ){
            return true;
        }

		if (isset($_GET["page"]) && ($_GET["page"] == "really-simple-security" || $_GET["page"] == "really-simple-ssl") ) {
			return true;
		}

		return false;
	}
}//class closure

if (!function_exists('rsssl_pro_is_license_expired')) {
	function rsssl_pro_is_license_expired() {
		$status = RSSSL_PRO()->licensing->get_license_status();
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
		$redirect_to_homepage = RSSSL_PRO()->admin->redirects_to_homepage();

		if ( $redirect_to_homepage == true ) {
			return 'redirect-to-homepage';
		}
	}
}

if ( !function_exists('rsssl_csp_enabled') ) {
    function rsssl_csp_enabled() {
        if ( rsssl_get_option('content_security_policy') === 'report-only' ||
             rsssl_get_option('content_security_policy') === 'enforce' ) {
            return true;
        }
        return false;
    }
}

if ( !function_exists('rsssl_free_incompatible') ) {
	function rsssl_free_incompatible() {
		if ( version_compare( rsssl_version, '6.0.0', '<' ) ) {
			return true;
		}
		return false;
	}
}


if ( ! function_exists('rsssl_upgraded_to_current_version') ) {
	function rsssl_upgraded_to_current_version() {
		$current_version = get_option( 'rsssl_first_version' );
		//if there's no first version yet, we assume it's not upgraded
		if ( !$current_version ) {
			return false;
		}
		//if the first version is below current, we just upgraded.
		if ( version_compare($current_version,rsssl_pro_version ,'<') ){
			return true;
		}
		return false;
	}
}