<?php defined('ABSPATH') or die("you do not have access to this page!");

	class rsssl_csp_backend
	{
		private static $_this;

		private $directives = array();
		private $reporting = false;
		private $enforce = false;

		function __construct()
		{

			if (isset(self::$_this))
				wp_die(sprintf(__('%s is a singleton class and you cannot create a second instance.', 'really-simple-ssl-pro'), get_class($this)));

			self::$_this = $this;
			$this->reporting = RSSSL_PRO()->rsssl_premium_options->get_networkwide_option('rsssl_enable_csp_reporting');
			$this->enforce = RSSSL_PRO()->rsssl_premium_options->get_networkwide_option('rsssl_add_csp_rules_to_htaccess');

			add_action('admin_init', array($this, 'update_db_check'), 9);

			//Only add CSP tab if reporting has been enabled
			if ( $this->reporting || $this->enforce ) {
				add_action('admin_init', array($this, 'add_rules_to_htaccess'));
			}

			//Remove report only rules on option update
			add_action( "update_option_rsssl_enable_csp_reporting", array( $this, "remove_csp_from_htaccess" ), 30,3);
			add_action( "update_option_rsssl_add_csp_rules_to_htaccess", array( $this, "remove_csp_from_htaccess" ), 30,3);
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ));
			add_action( 'wp_ajax_update_in_policy_value', array( $this, 'update_in_policy_value' ));

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
				'script-src'        => "script-src 'self' {uri}; ",
				'script-src-elem'   => "script-src-elem 'self' {uri}; ",
				'script-src-attr'   => "script-src-attr 'self' {uri}; ",
				'style-src'         => "style-src 'self' {uri}; ",
				'style-src-elem'    => "style-src-elem 'self' {uri}; ",
				'style-src-attr'    => "style-src-attr 'self' {uri}; ",
				'worker-src'        => "worker-src 'self' {uri}; ",
			);
		}

		static function this()
		{
			return self::$_this;
		}

		public function add_csp_tab($tabs)
		{
			$tabs['csp'] = "Content Security Policy";
			return $tabs;
		}

		/**
		 *
		 * Update the 'inpolicy' database value to true after 'Add to policy' button is clicked in Content Security Policy tab
		 *
		 * @since 2.5
		 */

		public function update_in_policy_value()
		{
			if (!current_user_can('manage_options')) return;

			global $wpdb;
			$table_name = $wpdb->base_prefix . "rsssl_csp_log";
			if (isset($_POST['id'])) {

				//Sanitize, id should always be an int
				$id = intval($_POST['id']);

				$wpdb->update(
					$table_name,
					//Value to update
					array(
						'inpolicy' => 'true',
					),
					//Update value where ID is
					array(
						'ID' => $id,
					)
				);
			}
		}

		/**
		 *
		 * Add CSP rules to the htaccess file
		 *
		 * @since 2.5
		 *
		 */

		public function add_rules_to_htaccess()
		{
			if ( !current_user_can('manage_options') ) return;
			if ( !RSSSL_PRO()->rsssl_premium_options->is_settings_page() || wp_doing_ajax() ) return;

            global $wpdb;

			$rules = array();

			//The base content security policy rules, used in later functions to generate the Content Security Policy
			$rules['default-src'] = "default-src 'self';";
			$rules['script-src'] = "script-src 'self' 'unsafe-inline';";
			$rules['script-src-elem'] = "script-src-elem 'self';";
			$rules['style-src'] = "style-src 'self' 'unsafe-inline';";
			$rules['style-src-elem'] = "style-src-elem 'self' 'unsafe-inline';";

			$table_name = $wpdb->base_prefix . "rsssl_csp_log";
			$rows = $wpdb->get_results("SELECT * FROM $table_name ORDER BY time DESC");
			if (!empty($rows)) {
				foreach ($rows as $row) {
					if (!empty($row->inpolicy)) {
						$violatedirective = $row->violateddirective;
						$blockeduri = $row->blockeduri;
						//Get uri value
						$uri = rsssl_sanitize_uri_value($blockeduri);
						//Generate CSP rule based on input
						$rules = $this->generate_csp_rule($violatedirective, $uri, $rules);
					}
				}
			}

			$rules = implode(" ", $rules);

			//Update CSP-Report-Only rules
			if ( $this->reporting && !$this->enforce ) {
				$csp_violation_endpoint = home_url('wp-json/rsssl/v1/csp');
				if (get_site_option('rsssl_csp_report_token')) {
					$token = get_site_option('rsssl_csp_report_token');
				} else {
					$token = time();
					update_site_option('rsssl_csp_report_token', $token);
				}

				$report_only_rules =  "Header always set Content-Security-Policy-Report-Only: \"$rules report-uri $csp_violation_endpoint?rsssl_apitoken=$token \"\n";
				RSSSL_PRO()->rsssl_premium_options->write_to_htaccess($report_only_rules, 'Really_Simple_SSL_CSP_Report_Only');
				$php_report_only_rules = str_replace("Header always set ", '' , $report_only_rules);
				update_option('rsssl_pro_csp_report_only_rules_for_php', $php_report_only_rules);
			} else {
				delete_option('rsssl_pro_csp_report_only_rules_for_php' );

				RSSSL_PRO()->rsssl_premium_options->remove_htaccess_rules( 'Really_Simple_SSL_CSP_Report_Only');
			}

			//Update CSP rules only when 'Add Content Security Policy to .htaccess option has been enabled.
            if ( $this->reporting && $this->enforce ) {
	            // If the upgrade-insecure-requests header has been enabled, add it to this CSP.
	            if ( RSSSL_PRO()->rsssl_premium_options->get_networkwide_option('rsssl_content_security_policy') ) {
		            $enforce_rules = "Header always set Content-Security-Policy: \"upgrade-insecure-requests; $rules\n";
	            } else {
		            $enforce_rules = "Header always set Content-Security-Policy: \"$rules\"\n";
	            }
	            //save in option for use in php headers
                $php_enforce_rules = str_replace("Header always set ", "", $enforce_rules);
	            update_option('rsssl_pro_csp_enforce_rules_for_php', $php_enforce_rules);
	            RSSSL_PRO()->rsssl_premium_options->write_to_htaccess($enforce_rules, 'Really_Simple_SSL_Content_Security_Policy');
            } else {
	            delete_option('rsssl_pro_csp_enforce_rules_for_php' );

	            RSSSL_PRO()->rsssl_premium_options->remove_htaccess_rules( 'Really_Simple_SSL_Content_Security_Policy');
			}
		}

		/**
		 * Remove Content Security Policy rules from .htaccess when Add Content Security Policy to .htaccess option is not enabled.
		 * @param $old_value
		 * @param $new_value
		 * @param $fieldname
		 *
		 * @since 2.5
		 */

		public function remove_csp_from_htaccess($old_value, $new_value, $fieldname)
		{
			if ($old_value === $new_value) return;

			if (!current_user_can('manage_options')) return;

			if ($fieldname === 'rsssl_add_csp_rules_to_htaccess' && !$new_value ) {
				RSSSL_PRO()->rsssl_premium_options->remove_htaccess_rules( 'Really_Simple_SSL_Content_Security_Policy');
			}

			if ($fieldname === 'rsssl_enable_csp_reporting' && !$new_value  ) {
				RSSSL_PRO()->rsssl_premium_options->remove_htaccess_rules( 'Really_Simple_SSL_CSP_Report_Only');
			}
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

		public function generate_csp_rule( $violateddirective, $uri, $rules )
		{
			// Check the violateddirective is valid
			if (isset($this->directives[$violateddirective])){

				// If the violated directive has an existing rule, update it
				if (isset($rules[$violateddirective])) {
					$rule_template = $this->directives[$violateddirective];
					//get existing rule
					$existing_rule = $rules[$violateddirective];
					//get part of directive before {uri}
					$rule_part = substr($rule_template, 0, strpos($rule_template, '{uri}'));
					// URI can be both URL or a directive (for example script-src)
					// Check if the current rule already contains the URI
					if (strpos($existing_rule, $uri) !== false) {
						// If it contains the uri, do not add it again. Keep existing rule
						$new_rule = $existing_rule;
					} else {
						// The rule already contains this URL, do not add it again
						$new_rule = str_replace($rule_part, $rule_part . $uri . " ", $existing_rule);
					}
					//insert in array
					$rules[$violateddirective] = $new_rule;
				} else {
					$rules[$violateddirective] = str_replace('{uri}', $uri, $this->directives[$violateddirective]);;
				}
			}

			return $rules;
		}

		/**
		 *
		 * @since 2.5
		 *
		 * Enqueue DataTables scripts and CSS
		 *
		 */

		public function enqueue_scripts($hook)
		{
			if ( $hook !== 'settings_page_really-simple-ssl' && $hook !== 'settings_page_rlrsssl_really_simple_ssl' ) return;

			wp_register_style('rsssl-pro-csp-datatables', rsssl_pro_url . 'css/datatables.min.css', "", rsssl_pro_version);
			wp_enqueue_style('rsssl-pro-csp-datatables');
			wp_register_style('rsssl-pro-csp-table-css', rsssl_pro_url . 'css/jquery-table.css', "", rsssl_pro_version);
			wp_enqueue_style('rsssl-pro-csp-table-css');
			wp_enqueue_script('rsssl-pro-csp-datatables', rsssl_pro_url . "js/datatables.min.js", array('jquery'), rsssl_pro_version, false);
		}

		/**
		 * Check if db should be updated
		 */
		public function update_db_check()
		{
			if (!current_user_can('manage_options')) return;

			if (get_option('rsssl_csp_db_version') !== rsssl_pro_version) {
				require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

				global $wpdb;
				$table_name = $wpdb->base_prefix . "rsssl_csp_log";
				$charset_collate = $wpdb->get_charset_collate();

				$sql = "CREATE TABLE $table_name (
                  id mediumint(9) NOT NULL AUTO_INCREMENT,
                  time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                  documenturi text  NOT NULL,
                  violateddirective text  NOT NULL,
                  blockeduri text  NOT NULL,
                  inpolicy text NOT NULL,
                  PRIMARY KEY  (id)
                ) $charset_collate";

				dbDelta($sql);
				update_option('rsssl_csp_db_version', rsssl_pro_version);
			}
		}

	}
