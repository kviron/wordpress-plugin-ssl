<?php defined('ABSPATH') or die();

	class rsssl_csp_backend
	{
		private static $_this;
		function __construct()
		{

			if (isset(self::$_this))
				wp_die(sprintf(__('%s is a singleton class and you cannot create a second instance.', 'really-simple-ssl-pro'), get_class($this)));

			self::$_this = $this;
            //doesn't execute on prio 10
			add_action('plugins_loaded', array( $this, 'update_db_check'), 11 );
			//Remove report only rules on option update
            add_action( "rsssl_after_saved_field", array( $this, "maybe_reset_csp_count" ), 30,4);
            add_action( 'admin_init', array( $this , 'add_csp_defaults' ) );
			add_filter( 'rsssl_notices', array($this,'csp_notices'), 20, 1 );
		}

		static function this()
		{
			return self::$_this;
		}

        /**
         * Delete the CSP track count when switching from report-paused to report-only
         * @param string $field_name
         * @param mixed $new_value
         * @param mixed $old_value
         * @param string $field_type
         * @since 4.1.1
         *
         */

        public function maybe_reset_csp_count($field_name, $new_value, $old_value, $field_type) {
	        if ( !rsssl_user_can_manage()) {
		        return;
	        }

	        if ( $field_name !== 'csp_status') {
		        return;
	        }

            if ( $old_value === 'completed' && $new_value === 'learning_mode') {
                delete_site_option('rsssl_csp_request_count');
            }
        }

		/**
		 * Add default WordPress rules to CSP table.
		 *
		 */

		public function add_csp_defaults() {
			if ( !rsssl_user_can_manage() ) {
				return;
			}

			if ( !get_option('rsssl_enable_csp_defaults') ) {
				return;
			}

			global $wpdb;
			$table_name = $wpdb->base_prefix . "rsssl_csp_log";
			$rules = array(
				'script-src-data' => array(
					'violateddirective' => 'script-src',
					'blockeduri' => 'data:',
				),
				'script-src-eval' => array(
					'violateddirective' => 'script-src',
					'blockeduri' => 'unsafe-eval',
				),
				'img-src-gravatar' => array(
					'violateddirective' => 'img-src',
					'blockeduri' => 'https://secure.gravatar.com',
				),
				'img-src-data' => array(
					'violateddirective' => 'img-src',
					'blockeduri' => 'data:',
				),
				'img-src-self' => array(
					'violateddirective' => 'img-src',
					'blockeduri' => 'self',
				),
			);

			foreach ( $rules as $rule ) {
				// add $rule to CSP table
				$wpdb->insert($table_name, array(
					'time' => current_time('mysql'),
					// Default rules, leave documenturi empty
					'documenturi' => 'WordPress',
					'violateddirective' => $rule['violateddirective'],
					'blockeduri' => $rule['blockeduri'],
					'status' => 1,
				));
			}
			delete_option('rsssl_enable_csp_defaults');
		}

		/**
         * Some custom notices for CSP
         *
		 * @param $notices
		 *
		 * @return mixed
		 */
		public function csp_notices($notices){

            $missing_tables = get_option('rsssl_table_missing');
            if ( !empty($missing_tables) ) {
                $tables = implode(', ', $missing_tables);
	            $notices['database_table_missing'] = array(
		            'condition' => array('rsssl_ssl_enabled'),
		            'callback' => '_true_',
		            'score' => 10,
		            'output' => array(
			            '_true_' => array(
				            'msg' => __("A required database table is missing. Please check if you have permissions to add this database table.", "really-simple-ssl-pro"). " ".$tables,
				            'icon' => 'warning',
				            'plusone' => true,
				            'dismissible' => true
			            ),
		            ),
	            );
            }
            if ( rsssl_get_option( 'csp_status' ) === 'learning_mode' ) {
	            $activation_time = get_site_option( 'rsssl_csp_report_only_activation_time' );
	            $nr_of_days_learning_mode = apply_filters( 'rsssl_pause_after_days', 7 );

                $deactivation_time = $activation_time + DAY_IN_SECONDS * $nr_of_days_learning_mode;
	            $time_left = $deactivation_time - time();
                $days = round($time_left / DAY_IN_SECONDS, 0);
                //if we're in learning mode, it should not show 0 days
                if ( $days == 0 ) $days = 1;
	            $notices['learning_mode_active'] = array(
                    'callback' => '_true_',
		            'score' => 10,
		            'output' => array(
			            'true' => array(
				            'msg' => sprintf(__("Learning Mode is active for your Content Security and will complete in %s days.", "really-simple-ssl-pro"), $days),
				            'icon' => 'open',
				            'plusone' => true,
				            'dismissible' => true
			            ),
		            ),
	            );
            }

			if ( rsssl_get_option( 'csp_status' ) === 'completed' ) {
				ob_start();
				?>
				<p><?php _e("Follow these steps to complete the setup:", "really-simple-ssl-pro"); ?></p>
				<ul class="message-ul">
					<li class="rsssl-activation-notice-li"><div class="rsssl-bullet"></div><?php _e("Review the detected configuration in 'Content Security Policy'.", "really-simple-ssl-pro"); ?></li>
					<li class="rsssl-activation-notice-li"><div class="rsssl-bullet"></div><?php _e("Click 'Enforce' to enforce the configuration on your site.", "really-simple-ssl-pro"); ?></li>
				</ul>
				<?php
				$content = ob_get_clean();
				$notices['csp_lm_completed'] = [
					'callback' => '_true_',
					'score'    => 10,
					'output'   => [
						'true' => [
							'url' => 'https://really-simple-ssl.com/knowledge-base/how-to-use-the-content-security-policy-generator',
							'msg'                => $content,
							'icon'               => 'open',
							'dismissible'        => true,
						],
					],
				];
			}
			return $notices;
		}

		/**
		 * Check if db should be updated
		 */
		public function update_db_check()
		{
			if ( get_option('rsssl_csp_db_version') !== rsssl_pro_version ) {
				require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
				global $wpdb;
				$table_name = $wpdb->base_prefix . "rsssl_csp_log";
				if ( !get_option('rsssl_csp_db_upgraded') ){
					if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name ) {
						$columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'inpolicy'");
						if (count($columns)>0) {
							$wpdb->query("ALTER TABLE $table_name CHANGE COLUMN inpolicy status text;");
						}

                        //convert string 'true' to 1.
						$wpdb->query("UPDATE $table_name set status = 1 where status = 'true'");
                    }
					update_option('rsssl_csp_db_upgraded', true);
				}

				$charset_collate = $wpdb->get_charset_collate();
				$sql = "CREATE TABLE $table_name (
                  id mediumint(9) NOT NULL AUTO_INCREMENT,
                  time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                  documenturi text  NOT NULL,
                  violateddirective text  NOT NULL,
                  blockeduri text  NOT NULL,
                  status text NOT NULL,
                  PRIMARY KEY  (id)
                ) $charset_collate";

				dbDelta($sql);
				update_option('rsssl_csp_db_version', rsssl_pro_version);
			}
		}

		/**
		 * Get current CSP data
		 * @return array
		 */
		public function get() {
			global $wpdb;
			$table_name = $wpdb->base_prefix . "rsssl_csp_log";
			$data = [];
			if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name ) {
				// Allow override of display limit
				$limit = defined('RSSSL_CSP_DISPLAY_LIMIT_OVERRIDE') ? (int) RSSSL_CSP_DISPLAY_LIMIT_OVERRIDE : 2000;
				$data = $wpdb->get_results("SELECT * FROM $table_name ORDER BY time DESC LIMIT $limit");
				$tables = get_option('rsssl_table_missing', []);
				if ( in_array($table_name, $tables)) {
					unset($tables[$table_name]);
					update_option('rsssl_table_missing', $tables, false);
				}
			} else {
                $tables = get_option('rsssl_table_missing', []);
                if ( !in_array($table_name, $tables)) {
	                $tables[] = $table_name;
                }
				update_site_option('rsssl_csp_db_version', false);
                update_option('rsssl_table_missing', $tables, false);
            }

			return $data;
		}

		/**
		 *
		 * Update the 'status' database value to true after 'Add to policy' button is clicked in Content Security Policy tab
		 *
		 * @since 2.5
		 */

		public function update($data, $update_item_id, $action='update')
		{
			if (!rsssl_user_can_manage()) {
				return;
			}
			global $wpdb;
			$table_name = $wpdb->base_prefix . "rsssl_csp_log";
			if ( !is_array($data) ) {
				return;
			}

			$ids = array_column($data, 'id');
			$index = array_search($update_item_id, $ids);
			if ( $index===false && $action==='update' ) {
				return;
			}

			$update_data = $data[$index];
			unset($update_data['title']);
            if ( $action === 'update' ) {
    			$wpdb->update($table_name, $update_data, ['id' => $update_item_id] );
            } else {
                $wpdb->delete( $table_name, [
                    'id' => $update_item_id
                    ]
                );
            }
		}
}