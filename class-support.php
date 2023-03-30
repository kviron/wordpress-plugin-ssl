<?php
defined('ABSPATH') or die("you do not have access to this page!");

if ( ! class_exists( 'rsssl_support' ) ) {
	class rsssl_support
	{
		private static $_this;
		public $error_message = "";

		function __construct()
		{
			add_filter('rsssl_run_test', array($this, 'support_data'), 10, 3);
			if (isset(self::$_this)) wp_die();
			self::$_this = $this;
		}

		static function this()
		{
			return self::$_this;
		}

		/**
		 * @param array $response
		 * @param string $test
		 * @param mixed $data
		 *
		 * @return array
		 */
		public function support_data($response, $test, $data){
			if ( $test !== 'supportdata' ) {
				return $response;
			}

			if ( !rsssl_user_can_manage() ) {
				return $response;
			}

			$user_info = get_userdata(get_current_user_id());
			$email = urlencode($user_info->user_email);
			$name = urlencode($user_info->display_name);
			$htaccess = "";
			$htaccess_file = RSSSL()->admin->htaccess_file();
			if ( file_exists($htaccess_file) ) {
				$htaccess = file_get_contents($htaccess_file);
				if (strlen($htaccess)>6000){
					$htaccess = substr($htaccess,0, 6000).'--br----br--'.'## TRUNCATED HTACCESS - FILE TOO LONG ##'.'--br--';
				}
				$htaccess = str_replace("\n", '--br--', $htaccess );
				$htaccess = urlencode($htaccess);
			}

			$domain = site_url();
			$scan_results = get_transient("rsssl_scan");
			$results = '';

			if ( !empty($scan_results['posts_with_blocked_resources']) ) {
				$results = print_r($scan_results['posts_with_blocked_resources'], true);
			}

			if ( !empty($scan_results['css_js_with_mixed_content']) ) {
				$results .= print_r($scan_results['css_js_with_mixed_content'], true);
			}

			if ( !empty($scan_results['external_css_js_with_mixed_content']) ) {
				$results .= print_r($scan_results['external_css_js_with_mixed_content'], true);
			}

			if ( !empty($scan_results['postmeta_with_blocked_resources']) ) {
				$results .= print_r($scan_results['postmeta_with_blocked_resources'], true);
			}

			if ( !empty($scan_results['tables_with_blocked_resources']) ) {
				$results .= print_r($scan_results['tables_with_blocked_resources'], true);
			}

			if ( !empty($scan_results['widgets_with_blocked_resources']) ) {
				$results .= print_r($scan_results['widgets_with_blocked_resources'], true);
			}

            $user_id = get_current_user_id();
			$license_key = RSSSL_PRO()->licensing->license_key();
            if ( get_option('rsssl_pro_disable_license_for_other_users') == 1 && get_option('rsssl_licensing_allowed_user_id') == $user_id) {
                $license_key = RSSSL_PRO()->licensing->maybe_decode( $license_key );
            } elseif ( !get_option('rsssl_pro_disable_license_for_other_users') ) {
                $license_key = RSSSL_PRO()->licensing->maybe_decode( $license_key );
            } else {
                $license_key = 'protected';
            }

			//get system status file
			require_once(trailingslashit(rsssl_path).'system-status.php');
			$system_status = rsssl_get_system_status();
			$system_status = str_replace("\n", '--br--', $system_status );
			$system_status = urlencode(strip_tags( $system_status ) );

			$response = [
				'customer_name' => $name,
				'email' => $email,
				'domain' => $domain,
				'scan_results' => $results,
				'license_key' => $license_key,
				'htaccess_contents' => $htaccess,
				'system_status' => $system_status,
			];
			return $response;
		}
	}
}