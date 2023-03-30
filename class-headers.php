<?php defined('ABSPATH') or die();

class rsssl_headers {

	private static $_this;
	public $security_headers;
	public $directives;
	function __construct()
	{
		if (isset(self::$_this))
			wp_die(sprintf(__('%s is a singleton class and you cannot create a second instance.', 'really-simple-ssl'), get_class($this)));

		self::$_this = $this;
		// Key must match rsssl_recommended_security_headers filter in free
		//patterns have to be written as a regex. A match should
		$this->security_headers = apply_filters( 'rsssl_pro_detected_security_headers', array(
			[
				'name' => 'Upgrade Insecure Requests',
				'pattern' =>  ['header'=>'Content-Security-Policy', 'value' => 'upgrade-insecure-requests'],
				'option_name' => 'upgrade_insecure_requests',
				'recommended_value' => false,
				'is_recommended_header' => true,
			],
			[
				'name' => 'Content Security Policy',
				'pattern' =>  ['header'=>'Content-Security-Policy', 'value' => 'src'],
				'option_name' => 'content_security_policy',
				'recommended_value' => false,
				'is_recommended_header' => false,
			],
			[
				'name' => 'Frame Ancestors',
				'pattern' =>  ['header'=>'Content-Security-Policy', 'value' => 'frame-ancestors [\'|\"](.*?)[\'|\"]'],
				'option_name' => 'csp_frame_ancestors',
				'recommended_value' => false,
				'non_recommended_value' => "*",
				'is_recommended_header' => true,
			],
			[
				'name' => 'X-XSS protection',
				'pattern' =>  ['header'=>'X-XSS-Protection', 'value' => false],
				'option_name' => 'x_xss_protection',
				'recommended_value' => '0',
				'is_recommended_header' => true,
			],
			[
				'name' => 'X-Content Type Options',
				'pattern' =>  ['header'=>'X-Content-Type-Options', 'value' => false],
				'option_name' => 'x_content_type_options',
				'recommended_value' => 'nosniff',
				'is_recommended_header' => true,
			],
			[
				'name' => 'Referrer-Policy',
				'pattern' =>  ['header'=>'Referrer-Policy', 'value' => false],
				'option_name' => 'referrer_policy',
				'recommended_value' => array('strict-origin-when-cross-origin', 'no-referrer', 'strict-origin' ),
				'is_recommended_header' => true,
			],
			[
				'name' => 'Permissions-Policy',
				'pattern' =>  ['header'=>'Permissions-Policy', 'value' => false],
				'option_name' => 'permissions_policy',
				'recommended_value' => false,
				'is_recommended_header' => false,
			],
			[
				'name' => 'X-Frame-Options',
				'pattern' =>  ['header'=>'X-Frame-Options', 'value' => false],
				'option_name' => 'x_frame_options',
				'recommended_value' => ['SAMEORIGIN', 'DENY'],
				'is_recommended_header' => true,
			],
			[
				'name' => 'HSTS Max-age',
				'pattern' =>  ['header'=>'Strict-Transport-Security', 'value' => 'max-age=(.*?);'],
				'option_name' => 'hsts_max_age',
				'recommended_value' => array('31536000' , '63072000'),
				'is_recommended_header' => false,
			],
			[
				'name' => 'HSTS Preload',
				'pattern' =>  ['header'=>'Strict-Transport-Security', 'value' => 'preload'],
				'option_name' => 'hsts_preload',
				'recommended_value' => false,
				'is_recommended_header' => false,
			],
			[
				'name' => 'HSTS Include Subdomains',
				'pattern' =>  ['header'=>'Strict-Transport-Security', 'value' => 'includeSubDomains'],
				'option_name' => 'hsts_subdomains',
				'recommended_value' => false,
				'is_recommended_header' => false,
			],
			[
				'name' => 'HTTP Strict Transport Security',
				'pattern' =>  ['header'=>'Strict-Transport-Security', 'value' => false],
				'option_name' => 'hsts',
				'recommended_value' => false,
				'is_recommended_header' => true,
			],
			[
				'name' => 'Cross-Origin-Opener-Policy',
				'pattern' =>  ['header'=>'Cross-Origin-Opener-Policy', 'value' => false],
				'option_name' => 'cross_origin_opener_policy',
				'recommended_value' => array('disabled', 'unsafe-none', 'same-origin-allow-popups', 'same-origin-allow-popups'),
				'is_recommended_header' => false,
			],
			[
				'name' => 'Cross-Origin-Resource-Policy',
				'pattern' =>  ['header'=>'Cross-Origin-Resource-Policy', 'value' => false],
				'option_name' => 'cross_origin_resource_policy',
				'recommended_value' => array('disabled', 'same-site', 'same-origin', 'cross-origin'),
				'is_recommended_header' => false,
			],
			[
				'name' => 'Cross-Origin-Embedder-Policy',
				'pattern' =>  ['header'=>'Cross-Origin-Embedder-Policy', 'value' => false],
				'option_name' => 'cross_origin_embedder_policy',
				'recommended_value' => array('disabled' , 'require-corp', 'same-origin', 'unsafe-none'),
				'is_recommended_header' => false,
			],
		));

		add_filter( 'rocket_htaccess_mod_rewrite', '__return_false' );
		add_filter('rsssl_firewall_rules', array($this, 'insert_security_headers'));
		add_action( "rsssl_after_save_field", array($this, 'save_time_on_report_only_start'), 100, 4 );
		add_action( "admin_init", array($this, 'store_csp_endpoint'), 100 );
		add_action( "update_option_permalink_structure", array($this, 'update_csp_endpoint'), 10, 2 );
		add_filter( 'rsssl_notices', array($this,'get_notices_list'),20, 1 );
        add_filter( 'rsssl_notices', array( $this, 'add_non_recommended_header_notices'), 25, 1 );
		add_filter( 'rsssl_clear_test_caches', array( $this, 'rest_api_clear_cache' ), 20, 3 );
		$this->directives = array(
			'child-src'         => "child-src 'self' {uri}; ",
			'connect-src'       => "connect-src 'self' {uri}; ",
			'font-src'          => "font-src 'self' {uri}; ",
			'frame-src'         => "frame-src 'self' {uri}; ",
			'img-src'           => "img-src 'self' data: {uri}; ",
			'manifest-src'      => "manifest-src 'self' {uri}; ",
			'media-src'         => "media-src 'self' {uri}; ",
			'prefetch-src'      => "prefetch-src 'self' {uri}; ",
			'object-src'        => "object-src 'self' {uri}; ",
			'script-src'        => "script-src 'self' 'unsafe-inline' {uri}; ",
			'script-src-elem'   => "script-src-elem 'self' 'unsafe-inline' {uri}; ",
			'script-src-attr'   => "script-src-attr 'self' {uri}; ",
			'style-src'         => "style-src 'self' 'unsafe-inline' {uri}; ",
			'style-src-elem'    => "style-src-elem 'self' 'unsafe-inline' {uri}; ",
			'style-src-attr'    => "style-src-attr 'self' {uri}; ",
			'worker-src'        => "worker-src 'self' {uri}; ",
		);

		add_action( "rsssl_after_save_field", array( $this, 'clear_headers_check_cache'), 110, 4 );
		add_action( 'rsssl_after_saved_fields', array( $this, 'reload_headers_check_cache'), 20);
		add_filter( 'rsssl_fields', array($this,'mark_headers_with_non_recommended_values'), 10, 1);

	}

	static function this(): rsssl_headers {
		return self::$_this;
	}

	/**
	 * Add warning and tooltip to the fields array for each header that is set with a non recommended value
	 * @param array $fields
	 *
	 * @return array
	 */
	public function mark_headers_with_non_recommended_values($fields){
		$non_recommended_headers = $this->get_non_recommended_headers();
		$security_header_names = array_column($this->security_headers, 'name');
		foreach ($non_recommended_headers as $header => $value ) {
			$found_key = array_search($header, $security_header_names);
			$option_name = $this->security_headers[ $found_key ]['option_name'];
			$index = array_search($option_name, array_column($fields, 'id') );
			$fields[$index]['warning'] = true;
			$fields[$index]['tooltip'] = sprintf(__("The %s security header is not set by Really Simple SSL, but has a non-recommended value: \"%s\".", "really-simple-ssl-pro"), $header, $value );
		}
		return $fields;
	}

	/**
	 * Get list of notices for the dashboard
	 * @param array $notices
	 *
	 * @return array
	 */
	public function get_notices_list( $notices )
	{
		unset( $notices['recommended_security_headers_not_set']);
		$notices['hsts_preload'] = array(
			'condition' => array('rsssl_ssl_enabled'),
			'callback' => 'RSSSL_PRO()->headers->hsts_status',
			'score' => 10,
			'output' => array(
				'preload' => array(
					'title' => __("HSTS Preload", "really-simple-ssl-pro"),
					'msg' => sprintf(__("Your site has been configured for the HSTS preload list. If you have submitted your site, it will be preloaded. Click %shere%s to submit.", "really-simple-ssl-pro"),'<a target="_blank" href="https://hstspreload.org/?domain='.$this->non_www_domain().'">', '</a>' ),
					'icon' => 'success'
				),
				'no_preload' => array(
					'highlight_field_id' => 'hsts_preload',
					'title' => __("HSTS Preload", "really-simple-ssl-pro"),
					'msg' => __("Your site is not yet configured for the HSTS preload list.", "really-simple-ssl-pro"),
					'icon' => 'open',
					'dismissible' => true,
				),
				'no_hsts' => array(
					'highlight_field_id' => 'hsts',
					'title' => __("HSTS not enabled", "really-simple-ssl-pro"),
					'msg' => __("Your site is not configured for HSTS yet.", "really-simple-ssl-pro"),
					'icon' => 'open',
					'dismissible' => true,
				),
			),
		);

		return $notices;
	}

	/**
	 * Clear the headers
	 * Called on plugin deactivation
	 * @return void
	 */
	public function remove_advanced_headers() {
		if ( !defined('rsssl_plugin') ) {
			return;
		}
		//update with cleared options
		$disable = [
			"csp_frame_ancestors",
			"upgrade_insecure_requests",
			"enable_permissions_policy",
			"mixedcontentscan",
			"cross_origin_opener_policy",
			"hsts",
			"referrer_policy",
			"x_frame_options",
			"x_content_type_options",
			"x_xss_protection",
			"content_security_policy",
			"csp_status",
		];
		foreach ($disable as $name){
			rsssl_update_option($name, false);
		}
		RSSSL_SECURITY()->firewall_manager->insert_advanced_header_file();
	}

	/**
	 * Get HSTS status
	 * @return string
	 */
	public function hsts_status() {
		$thirdparty_headers = RSSSL_PRO()->headers->get_detected_security_headers('thirdparty');
		$detected_max_age = rsssl_get_option( 'hsts_max_age' );
		$detected_preload = rsssl_get_option( 'hsts_preload' );
		$detected_subdomains = rsssl_get_option( 'hsts_subdomains' );
		$hsts = rsssl_get_option( 'hsts' );
		foreach( $thirdparty_headers as $header => $data ) {
			$detected_option = $data['option_name'];
			if ( $detected_option==='hsts') {
				$hsts = true;
			}

			if ($detected_option==='hsts_max_age') {
				$detected_max_age = $data['value'];
			}

			if ( $detected_option==='hsts_preload') {
				$detected_preload = true;
			}

			if ( $detected_option==='hsts_subdomains') {
				$detected_subdomains = true;
			}
		}

		if ( !$hsts ) {
			return 'no_hsts';
		}

		if (
			$detected_preload &&
			(int) $detected_max_age >= 31536000 &&
			$detected_subdomains )
		{
			return 'preload';
		}

		return 'no_preload';
	}

	/**
	 * Get list of headers with non recommended values
	 *
	 * @return array //in name => value format
	 */
	private function get_non_recommended_headers(){
		$non_recommended_headers = array();
		$used_headers = RSSSL_PRO()->headers->get_detected_security_headers( 'thirdparty' );
		$security_header_names = array_column($this->security_headers, 'name');
		foreach ( $used_headers as $header_name => $header_value ) {
			// Skip CORS && CSP
			if ( $header_name === 'Cross-Origin-Opener-Policy' || $header_name === 'Cross-Origin-Resource-Policy') continue;

			$found_key = array_search($header_name, $security_header_names);
			if ( isset( $header_value['value'] ) && $this->security_headers[ $found_key ]['recommended_value']!==false && $found_key ) {
				$recommended_values = $this->security_headers[ $found_key ]['recommended_value'];
				#ensure it's an array
				if ( !is_array($recommended_values) ) {
					$recommended_values = [ $recommended_values ];
				}

				$found = in_array( $header_value['value'], $recommended_values );
				if ( !$found ) {
					// If $header_value['value'] is contained in substr of one $recommended_value, e.g. in the HSTS max-age header we do not want to add it
					foreach( $recommended_values as $recommended_value ) {
						if ( stripos( $header_value['value'], $recommended_value) !== false ) {
							$found = true;
						}
					}
				}

				// Add to non-recommend when value if not found in substring
				if ( !$found ) {
					$non_recommended_headers[ $header_name ] = $header_value['value'];
				}
			}

			#check for non recommended values
			if ( isset( $header_value['value'], $this->security_headers[ $found_key ]['non_recommended_value'] ) && $this->security_headers[ $found_key ]['non_recommended_value'] !== false
			     && $found_key
			) {
				$non_recommended_values = $this->security_headers[ $found_key ]['non_recommended_value'];
				#ensure it's an array
				if ( !is_array($non_recommended_values) ) {
					$non_recommended_values = [ $non_recommended_values ];
				}

				$found = in_array( $header_value['value'], $non_recommended_values );
				if ( !$found ) {
					// If $header_value['value'] is contained in substr of one $recommended_value, e.g. in the HSTS max-age header we do not want to add it
					foreach( $non_recommended_values as $non_recommended_value ) {
						if ( stripos( $header_value['value'], $non_recommended_value) !== false ) {
							$found = true;
						}
					}
				}

				// Add to non-recommend when value if not found in substring
				if ( $found ) {
					$non_recommended_headers[ $header_name ] = $header_value['value'];
				}
			}
		}
		return $non_recommended_headers;
	}

	/**
	 * Add notice(s) for headers with non-recommended values
	 *
	 * @param array $notices
	 *
	 * @return array
	 */

    public function add_non_recommended_header_notices( array $notices ): array {

	    if ( RSSSL_PRO()->headers->is_header_check_running() ) {
		    return $notices;
	    }

	    $non_recommended_headers = $this->get_non_recommended_headers();
        foreach ( $non_recommended_headers as $header => $value ) {
			$notices[ 'wrong_value_'.$header ] = array(
                'callback' => '_true_',
                'score' => 5,
                'output' => array(
                    'true' => array(
	                    'url' => "https://really-simple-ssl.com/how-to-find-where-security-headers-are-set/",
	                    'msg' => sprintf(__("The %s security header is not set by Really Simple SSL, but has a non-recommended value: \"%s\".", "really-simple-ssl-pro"), $header, $value ),
                        'icon' => 'open',
                        'dismissible' => true,
	                    'clear_cache_id' => 'detected_headers',
                    ),
                ),
            );
        }
	    $all_headers = RSSSL_PRO()->headers->get_detected_security_headers( 'all' );
	    $security_header_names = array_column($this->get_recommended_security_headers(), 'name');
	    $missing_headers = [];
		foreach ( $security_header_names as $header ) {
			if ( isset($all_headers[$header]) ) {
				continue;
			}

			//not found yet. Check if it's enabled by RSSSL.
			$found_key = array_search($header, $security_header_names);
			$option_name = $this->security_headers[ $found_key ]['option_name'];
			if ( rsssl_get_option($option_name) ) continue;
			$missing_headers[] = $header;
	    }

		if ( count($missing_headers)>0 ) {
			$missing = implode(', ', $missing_headers);
			$notices[ 'missing_headers' ] = array(
				'callback' => '_true_',
				'score' => 5,
				'output' => array(
					'true' => array(
						'url' => "https://really-simple-ssl.com/how-to-find-where-security-headers-are-set/",
						'msg' => sprintf(__("The following recommended security headers have not been set: %s", "really-simple-ssl-pro"), $missing ),
						'icon' => 'open',
						'dismissible' => true,
						'clear_cache_id' => 'detected_headers',
					),
				),
			);
		}
		//remove free notice, to prevent duplicates
		unset($notices['recommended_security_headers_not_set']);
        return $notices;
    }

	/**
	 * Get array of recommended headers
	 * @return mixed|null
	 */
	public function get_recommended_security_headers(){
		return array_filter($this->security_headers, function($value, $key) {
			return $value['is_recommended_header'] === true;
		}, ARRAY_FILTER_USE_BOTH);
	}


	/**
	 * If csp reporting is enabled, save the time, so we track how long it's running
	 * @return void
	 */
	public function save_time_on_report_only_start($field_id, $field_value, $prev_value, $field_type ){
		if ( $field_id==='csp_status' && $field_value==='learning_mode' ){
			update_site_option("rsssl_csp_report_only_activation_time", time() );
		}
	}

	/**
	 * Retrieving it in the firewall update is too early for WP, so we store it here
	 * @return void
	 */

	public function store_csp_endpoint(): void {
		if ( !get_option('rsssl_csp_report_url') ) {
			update_option('rsssl_csp_report_url', get_rest_url(null, 'rsssl/v1/csp'), false );
		}
	}

	/**
	 * Update the rest URL if the permalink structure is changed
	 *
	 * @param string $old_value
	 * @param string $new_value
	 *
	 * @return void
	 */
	public function update_csp_endpoint( string $old_value, string $new_value): void {
		if ( $new_value !== $old_value ) {
			update_option('rsssl_csp_report_url', get_rest_url(null, 'rsssl/v1/csp'), false );
		}
	}

	/**
	 * Check for www
	 *
	 * @return array|string|string[]
	 */
	public function non_www_domain(){
		return str_replace(array("https://", "http://", "https://www.", "http://www.", "www."), "", get_home_url() );
	}

	/**
	 * This class has its own settings page, to ensure it can always be called
	 *
	 * @return bool
	 */
	public function is_settings_page(): bool {
		if ( rsssl_is_logged_in_rest()){
			return true;
		}

		if (isset($_GET["page"]) && ($_GET["page"] === "really-simple-security" || $_GET["page"] === "really-simple-ssl") ) {
			return true;
		}

		return false;
	}

	/**
	 * Get CSP rules for any type or output type
	 *
	 * @return string
	 * @throws Exception
	 */

	public function get_csp_rules(): string {
		global $wpdb;
		$header = 'Content-Security-Policy';
    	$rules = '';

		//drop paused status if we're not in learning mode
		if ( rsssl_get_option('csp_status')!=='learning_mode' ) {
			delete_site_option( 'rsssl_csp_reporting_temp_paused' );
		}
		
		if ( !get_site_option( 'rsssl_csp_reporting_temp_paused' ) &&
		     (rsssl_get_option('csp_status')==='enforce' || rsssl_get_option('csp_status')==='learning_mode')
		) {
			#The base content security policy rules, used in later functions to generate the Content Security Policy
			$rules_array = [
				'img-src'         => "img-src 'self' data: ;",
				'default-src'     => "default-src 'self';",
				'script-src'      => "script-src 'self' 'unsafe-inline' 'unsafe-eval';",
				'script-src-elem' => "script-src-elem 'self' 'unsafe-inline';",
				'style-src'       => "style-src 'self' 'unsafe-inline';",
				'style-src-elem'  => "style-src-elem 'self' 'unsafe-inline';",
			];

			$table_name = $wpdb->base_prefix . "rsssl_csp_log";
			$rows = $wpdb->get_results("SELECT * FROM $table_name WHERE status=1 ORDER BY time DESC");
			if ( !empty($rows) ) {
				foreach ($rows as $row) {
					if ( $row->status == 1 ) {
						$violatedirective = $row->violateddirective;
						$blockeduri = $row->blockeduri;
						//Get uri value
						$uri = rsssl_sanitize_uri_value($blockeduri);
						//Generate CSP rule based on input
						$rules_array = $this->generate_csp_rule($violatedirective, $uri, $rules_array);
						if ($violatedirective==='script-src-elem') {
							$rules_array = $this->generate_csp_rule('script-src', $uri, $rules_array);
						}
						if ($violatedirective==='style-src-elem') {
							$rules_array = $this->generate_csp_rule('style-src', $uri, $rules_array);
						}
					}
				}
			}

			$rules = implode(" ", $rules_array);
			//if reporting is temporarily paused, return empty
			if ( rsssl_get_option('csp_status') === 'learning_mode' ) {
				$csp_violation_endpoint = get_option('rsssl_csp_report_url');
				$token = get_site_option('rsssl_csp_report_token');
				if ( !$token ) {
					$token = random_int(1000, 999999999);
					update_site_option('rsssl_csp_report_token', $token);
				}
				//allow for fallback method
				$glue = strpos($csp_violation_endpoint, '?') === false ? '?' : '&';
				$csp_violation_endpoint .= "{$glue}rsssl_apitoken=$token";

				//report-uri is deprecated, but report-to not yet supported widely
	//			$header = 'Report-To';
	//			$csp_endpoint_rules = "{'url': '".$csp_violation_endpoint."', 'group': 'csp-endpoint', 'max-age': 10886400}";
	//			$report_to_header = $this->wrap_header($header, $csp_endpoint_rules);
				$header = 'Content-Security-Policy-Report-Only';
	//			$rules =  "$rules report-uri $csp_violation_endpoint; report-to csp-endpoint";
				$rules =  "$rules report-uri $csp_violation_endpoint;";
			}
		}

		#no upgrade insecure requests in learning mode
		if ( rsssl_get_option('csp_status') !== 'learning_mode' && rsssl_get_option('upgrade_insecure_requests')) {
			// If the upgrade-insecure-requests header has been enabled, add it to this CSP.
			if ( rsssl_get_option('upgrade_insecure_requests') && !$this->header_is_set_by_thirdparty('upgrade_insecure_requests' )) {
				$rules = "upgrade-insecure-requests; $rules";
			}
		}

		if ( !empty(rsssl_get_option('csp_frame_ancestors')) && rsssl_get_option('csp_frame_ancestors') !=='disabled' && !$this->header_is_set_by_thirdparty('csp_frame_ancestors' ) )  {
			if ( rsssl_get_option('csp_frame_ancestors')==='none' ) {
				$rules = "frame-ancestors 'none';".$rules;
			} else if ( rsssl_get_option('csp_frame_ancestors') ==='self' ) {
				$urls = trim(rsssl_get_option('csp_frame_ancestors_urls'));
				if ( !empty($urls) ) {
					$urls = explode(",",$urls);
					foreach ( $urls as $index => $url ) {
						$urls[$index] = str_replace('http://', 'https://', esc_url_raw(trim($url)) );
					}
					$urls = implode(" ", $urls);
				}
				$rules = "frame-ancestors 'self' $urls;".$rules;
			}
		}

		if ( !empty($rules) ) {
			return $this->wrap_header($header, $rules);
		}

		return '';
	}

	public function header_is_set_by_thirdparty($option_name) {
		$headers_set_by_thirdparty = $this->get_detected_security_headers('thirdparty');
		$third_party_options = array_column($headers_set_by_thirdparty, 'option_name');
		return in_array($option_name, $third_party_options);
	}

	/**
	 * Generate security headers, and insert in .htaccess file
	 *
	 * @param string $rules
	 *
	 * @return string
	 */
	public function insert_security_headers( string $rules ): string {


		$rule = '';
		$break = "\n";
		$rule .= 'if ( !headers_sent() ) {'.$break;
		if ( is_ssl() && rsssl_get_option( 'hsts' ) ) {
			$subdomains = rsssl_get_option( 'hsts_subdomains' ) ? " includeSubDomains;" : "";
			$preload = rsssl_get_option("hsts_preload") ? "preload":"";
			$max_age = rsssl_get_option( 'hsts_max_age', '31536000');
			# only add hsts when on SSL
			# the advanced headers file will be included after the RSSSL fixes, so we should also have a server_https var if the host does not provide any of the default ones
			# wordpress is_ssl is not available yet, so we need our own
			# In some cases, even if the $_SERVER['HTTPS'] variable is available in WordPress, it might not yet be defined here. So we do a generic check.

			$rule .= 'function rsssl_is_ssl() {'.$break;
			$rule .= '  if (';
			$rule .= '  ( isset($_SERVER["HTTPS"]) && ("on" === $_SERVER["HTTPS"] || "1" === $_SERVER["HTTPS"]) )' . "\n";
			$rule .= '  || (isset($_ENV["HTTPS"]) && ("on" === $_ENV["HTTPS"]))' . "\n";
			$rule .= '  || (isset($_SERVER["SERVER_PORT"]) && ( "443" === $_SERVER["SERVER_PORT"] ) )' . "\n";
			$rule .= '  || (isset($_SERVER["HTTP_X_FORWARDED_SSL"]) && (strpos($_SERVER["HTTP_X_FORWARDED_SSL"], "1") !== false))' . "\n";
			$rule .= '  || (isset($_SERVER["HTTP_X_FORWARDED_SSL"]) && (strpos($_SERVER["HTTP_X_FORWARDED_SSL"], "on") !== false))' . "\n";
			$rule .= '  || (isset($_SERVER["HTTP_CF_VISITOR"]) && (strpos($_SERVER["HTTP_CF_VISITOR"], "https") !== false))' . "\n";
			$rule .= '  || (isset($_SERVER["HTTP_CLOUDFRONT_FORWARDED_PROTO"]) && (strpos($_SERVER["HTTP_CLOUDFRONT_FORWARDED_PROTO"], "https") !== false))' . "\n";
			$rule .= '  || (isset($_SERVER["HTTP_X_FORWARDED_PROTO"]) && (strpos($_SERVER["HTTP_X_FORWARDED_PROTO"], "https") !== false))' . "\n";
			$rule .= '  || (isset($_SERVER["HTTP_X_PROTO"]) && (strpos($_SERVER["HTTP_X_PROTO"], "SSL") !== false))' . "\n";
			$rule .= '  ) {' .$break;
			$rule .= '    return true;' .$break;
			$rule .= '  }' .$break;
			$rule .= '    return false;'.$break;
			$rule .= '}'.$break;
			$rule .= 'if ( rsssl_is_ssl() ) '.$this->wrap_header('Strict-Transport-Security', "max-age=$max_age;$subdomains$preload");
		}

		$rule .= $this->get_csp_rules();
		if ( rsssl_get_option( 'x_xss_protection' )!=='disabled' && !$this->header_is_set_by_thirdparty('x_xss_protection') ) {
			if (rsssl_get_option( 'x_xss_protection' )==='zero') {
				$rule .= $this->wrap_header( 'X-XSS-Protection', "0" );
			}
			if (rsssl_get_option( 'x_xss_protection' )==='one') {
				$rule .= $this->wrap_header( 'X-XSS-Protection', "1" );
			}
			if (rsssl_get_option( 'x_xss_protection' )==='mode_block') {
				$rule .= $this->wrap_header( 'X-XSS-Protection', "1; mode=block");
			}
		}

		if ( rsssl_get_option( 'x_content_type_options' ) && ! $this->header_is_set_by_thirdparty('x_content_type_options' ) ) {
			$rule .= $this->wrap_header( 'X-Content-Type-Options', "nosniff" );
		}

		$referrer_policy = rsssl_get_option( 'referrer_policy' );
		if ( $referrer_policy && $referrer_policy !== 'disabled' && ! $this->header_is_set_by_thirdparty('referrer_policy' ) ) {
			$rule .= $this->wrap_header( 'Referrer-Policy', $referrer_policy );
		}

		if ( rsssl_get_option( 'enable_permissions_policy' ) ) {
			$rule .= $this->generate_permissions_policy_header( );
		}

		$x_frame_options = rsssl_get_option( 'x_frame_options' );
		if ( $x_frame_options && $x_frame_options !== 'disabled' && ! $this->header_is_set_by_thirdparty('x_frame_options' )) {
			$rule .= $this->wrap_header( 'X-Frame-Options', strtoupper($x_frame_options) );
		}

		$cross_origin_opener_policy = rsssl_get_option('cross_origin_opener_policy');
		if ( !empty($cross_origin_opener_policy) && $cross_origin_opener_policy !== 'disabled' && ! $this->header_is_set_by_thirdparty('cross_origin_opener_policy' ) ) {
			$rule .= $this->wrap_header( 'Cross-Origin-Opener-Policy', $cross_origin_opener_policy );
		}

		$cross_origin_resource_policy = rsssl_get_option('cross_origin_resource_policy');
		if ( !empty($cross_origin_resource_policy) && $cross_origin_resource_policy !== 'disabled' && ! $this->header_is_set_by_thirdparty('cross_origin_resource_policy' ) ) {
			$rule .= $this->wrap_header( 'Cross-Origin-Resource-Policy', $cross_origin_resource_policy );
		}

		$cross_origin_embedder_policy = rsssl_get_option('cross_origin_embedder_policy');
		if ( !empty($cross_origin_embedder_policy) && $cross_origin_embedder_policy !== 'disabled' && ! $this->header_is_set_by_thirdparty('cross_origin_embedder_policy' ) ) {
			$rule .= $this->wrap_header( 'Cross-Origin-Embedder-Policy', $cross_origin_embedder_policy );
		}
		//close headers already send if
		$rule .= '}'.$break;
		return $rules."\n".$rule;
	}

	/**
	 * Get permissions policy rules
	 * @return string
	 */

	public function generate_permissions_policy_header(): string {
		$permissions_policy_values = rsssl_get_option('permissions_policy');
		$rules = [];
		if ( !is_array($permissions_policy_values) ) {
			$permissions_policy_values = [];
		}
		foreach ( $permissions_policy_values as $policy ) {
			switch ($policy['value']) {
				case '*':
					$rules[] = $policy['id'] ."=(*)";
					break;
				case 'self':
					$rules[] = $policy['id'] ."=(self)";
					break;
				default:
				case '()':
					$rules[] = $policy['id'] ."=()";
			}
		}
		$php_rule = '';
		if ( !empty( $rules) ) {
			$rules = implode(', ', $rules);
			$php_rule = $this->wrap_header('Permissions-Policy', $rules);
		}

		update_option('rsssl_pro_permissions_policy_headers_for_php', $php_rule);
		return $php_rule;
	}

	/**
	 * Wrap a header in the correct format
	 *
	 * @param string $header
	 * @param string $rules
	 *
	 * @return string
	 */

	public function wrap_header( string $header, string $rules ): string {
		$break = "\n";
		return 'header(' . '"' . $header . ": " . $rules . '"' . ');' . $break;
	}

	/**
	 * Generate CSP rules
	 *
	 * @param string $violateddirective
	 * @param string $uri
	 * @param array  $rules //previously detected rules
	 *
	 * @return array
	 */

	public function generate_csp_rule( string $violateddirective, string $uri, array $rules ): array {
		$uri = rsssl_sanitize_uri_value($uri);
		// Check the violateddirective is valid
		if ( isset($this->directives[$violateddirective]) ){
			// If the violated directive has an existing rule, update it
			if ( isset($rules[$violateddirective]) ) {
				$rule_template = $this->directives[$violateddirective]; //'script-src-elem'   => "script-src-elem 'self' {uri}; ",
				//get existing rule
				$existing_rule = $rules[$violateddirective]; //'script-src-elem'   => "script-src-elem 'self' 'unsafe-inline';";
				//get part of directive before {uri}
				$rule_part = substr($rule_template, 0, strpos($rule_template, '{uri}')); //"script-src-elem 'self' "
				// URI can be both URL or a directive (for example script-src)
				// Check if the current rule already contains the URI
				if (strpos($existing_rule, $uri) !== false) {
					// If it contains the uri, do not add it again. Keep existing rule
					$new_rule = $existing_rule;
				} else {
					//does not contain the uri, add it. we add trim to remove the space at the end of the rule part.
					$new_rule = str_replace(trim($rule_part), $rule_part . $uri . " ", $existing_rule);
				}
				//insert in array
				$rules[$violateddirective] = $new_rule;
			} else {
				$rules[$violateddirective] = str_replace('{uri}', $uri, $this->directives[$violateddirective]);
			}
		}

		return $rules;
	}

	/**
	 * Check if a header check is currently running
	 *
	 * @return bool
	 */
	public function is_header_check_running(): bool {
		$headers = $this->get_admin_transient('detected_headers');
		if ( !$headers || !isset($headers['header_check_active']) ){
			return true;
		}

        return $headers['header_check_active'];
	}

	/**
	 * Clear the headers caches
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function rest_api_clear_cache( $data): array {
		if ( !rsssl_user_can_manage() ) {
			return [];
		}
		$cache_id = sanitize_title($data['cache_id']);

		if ($cache_id==='detected_headers') {
			$this->delete_admin_transient( 'detected_headers' );
			$this->get_detected_security_headers();
		}

		return [];
	}

	/**
	 * Delete the cached header option
	 * @return void
	 */
	public function clear_headers_check_cache($field_id, $field_value, $prev_value, $field_type) {
		if ( !rsssl_user_can_manage()) {
			return;
		}

		if ( $field_value === $prev_value ) {
			return;
		}

		$header_ids = array_column($this->security_headers, 'option_name');
		if ( in_array( $field_id, $header_ids, true ) ) {
			$this->delete_admin_transient( 'detected_headers' );
		}
	}

	public function reload_headers_check_cache(){
		if ( !rsssl_user_can_manage()) {
			return;
		}
		if ( !$this->get_admin_transient( 'detected_headers' )){
			$this->get_detected_security_headers();
		}
	}

	/**
	 * Get recommended security header function
	 * The result is stored in a transient, but this transient is cleared on recommended headers save.
	 *
	 * @param string $type
	 *
	 * @return mixed
	 */
	public function get_detected_security_headers($type=false)
	{
		$nonce = get_site_option("rsssl_header_detection_nonce");
		if ( ! in_array($type, ['thirdparty', 'all']) ) {
			$type = 'thirdparty';
		}

		$headers = $this->get_admin_transient('detected_headers');
		if ( ! $headers ) {
            //set a default
            $headers = [
                'curl_exists' => function_exists('curl_init'),
                'thirdparty' => [],
                'all' => [],
                'header_check_active' => true,
            ];
            $this->set_admin_transient('detected_headers', $headers, DAY_IN_SECONDS );

            if (function_exists('curl_init')) {
                $url = get_site_url();
                $ch = curl_init();
                $detected_headers_with_rsssl = [];
                curl_setopt($ch, CURLOPT_URL, $url);
	            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_TIMEOUT, 3); //timeout in seconds
                curl_setopt($ch, CURLOPT_HEADERFUNCTION,
                    function ($curl, $header) use (&$detected_headers_with_rsssl) {
                        $len = strlen($header);
                        $header = explode(':', $header, 2);
                        if (count($header) < 2) // ignore invalid headers
                        {
                            return $len;
                        }
                        $detected_headers_with_rsssl[] = [
                            'name' => strtolower(trim($header[0])),
                            'value' => trim($header[1]),
                        ];

                        return $len;
                    }
                );
                curl_exec($ch);
                /**
                 * also do a check without headers set by RSSSL
                 */

                $url_exclude_rsssl = get_site_url() . '?rsssl_header_test=' . $nonce;
                $ch = curl_init();
                $detected_headers_without_rsssl = [];
                curl_setopt($ch, CURLOPT_URL, $url_exclude_rsssl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	            curl_setopt($ch, CURLOPT_TIMEOUT, 3); //timeout in seconds
                curl_setopt($ch, CURLOPT_HEADERFUNCTION,
                    function ($curl, $header) use (&$detected_headers_without_rsssl) {
                        $len = strlen($header);
                        $header = explode(':', $header, 2);
                        if (count($header) < 2) // ignore invalid headers
                        {
                            return $len;
                        }
                        $detected_headers_without_rsssl[] = [
                            'name' => strtolower(trim($header[0])),
                            'value' => trim($header[1]),
                        ];

                        return $len;
                    }
                );
                curl_exec($ch);

                $headers['thirdparty'] = $this->parse_headers($detected_headers_without_rsssl);
	            $headers['all'] = $this->parse_headers($detected_headers_with_rsssl);
            }
            $headers['header_check_active'] = false;
            $this->set_admin_transient('detected_headers', $headers, DAY_IN_SECONDS);
        }

		$headers = wp_parse_args( $headers, ['curl_exists'=>true, 'all'=>[], 'thirdparty'=>[] ]);
		return $headers[$type];
    }

	/**
	 * We user our own transient, as the wp transient is not always persistent
	 * Specifically made for license transients, as it stores on network level if multisite.
	 *
	 * @param string $name
	 *
	 * @return mixed
	 */
	private function get_admin_transient( string $name ){
		if ( !rsssl_user_can_manage() ) return false;

		$value = false;
		$now = time();
		$transients = get_option('rsssl_transients', array());
		if (isset($transients[$name])) {
			$data = $transients[$name];
			$expires = isset($data['expires']) ? $data['expires'] : 0;
			$value = isset($data['value']) ? $data['value'] : false;
			if ( $expires < $now ) {
				unset($transients[$name]);
				update_option('rsssl_transients', $transients, false );
				$value = false;
			}
		}
		return $value;
	}

	/**
	 * @param string $name
	 *
	 * @return void
	 */
	private function delete_admin_transient( string $name ){
		if ( !rsssl_user_can_manage() ) return;

		$transients = get_option('rsssl_transients', array());
		if (isset($transients[$name])) {
			unset($transients[$name]);
			update_option('rsssl_transients', $transients, false );
		}
	}

	/**
	 * We use our own transient, as the wp transient is not always persistent AND we don't want to autoload these transients
	 *
	 * @param string $name
	 * @param mixed  $value
	 * @param int    $expiration
	 *
	 * @return void
	 */
	private function set_admin_transient( string $name, $value, int $expiration ){
		if ( !rsssl_user_can_manage() ) return;

		$transients = get_option('rsssl_transients', array());
		$transients[$name] = array(
			'value' => $value,
			'expires' => time() + intval($expiration),
		);
		update_option('rsssl_transients', $transients, false );
	}

	/**
	 * Parse detected headers in structured array
	 * @param $headers
	 *
	 * @return array
	 */
	public function parse_headers($headers){
		$output_headers = [];
		if ( !empty($headers) ) {
			// Loop through each header and check if it's one of the recommended security headers. If so, add to used_headers array.
			foreach ($headers as $detected_header) {
				$name = $detected_header['name'];
				$value = $detected_header['value']; //e.g. "upgrade-insecure-requests; frame-ancestors 'none'; font-src 'self'"
				if ( !is_string($value) ) {
					continue;
				}

				foreach ( $this->security_headers as $header) {
					$name_pattern = $header['pattern']['header'];
					$value_pattern = $header['pattern']['value'];
					$value = str_replace(PHP_EOL, '', $value);

					//the header pattern should always match. If there is a value pattern, it has to match as well.
					$actual_value = $value;
					$value_pattern_has_match = false;
					if ( $value_pattern && preg_match( '/'.$value_pattern.'/i', $value, $matches ) ) {
						$actual_value = $matches[1] ?? '';
						$value_pattern_has_match = true;
					}

					if (( !$value_pattern || $value_pattern_has_match) && stripos($name, $name_pattern) !== false ) {
						$output_headers[$header['name']]['value'] = $actual_value;
						#add option name info
						$output_headers[$header['name']]['option_name'] = $header['option_name'];

						#check if it's duplicate
						if ( in_array($header['name'], $output_headers) ) {
							$output_headers[$header['name']]['count'] = 2;
						} else {
							$output_headers[$header['name']]['count'] = 1;
						}
					}
				}
			}
		}
		return $output_headers;
	}
}