<?php
/**
 * Plugin Name: Really Simple SSL Pro
 * Plugin URI: https://really-simple-ssl.com/pro
 * Description: Heavyweight Security Features
 * Version: 6.2.3
 * Text Domain: really-simple-ssl-pro
 * Domain Path: /languages
 * Author: Really Simple Plugins
 * Author URI: https://www.really-simple-plugins.com
 */

/*  Copyright 2022  Really Simple Plugins B.V.  (email : support@really-simple-ssl.com)
    License: see license.txt
*/

defined('ABSPATH') or die("you do not have access to this page!");

update_site_option( 'rsssl_pro_license_key', 'activated' );
update_site_option( 'rsssl_pro_license_status', 'valid' );
update_site_option( 'rsssl_pro_license_activation_limit', '999' );
update_site_option( 'rsssl_pro_license_activations_left', '999' );
update_site_option( 'rsssl_pro_license_expires', 'lifetime' );
define( 'rsssl_pro_ms_version', true );

if (!function_exists('rsssl_pro_activation_check')) {
	/**
	 * Checks if the plugin can safely be activated, at least php 5.6 and wp 4.8
	 */
	function rsssl_pro_activation_check()
	{
		if (version_compare(PHP_VERSION, '7.2', '<')) {
			deactivate_plugins(plugin_basename(__FILE__));
			wp_die(__('Really Simple SSL Pro cannot be activated. The plugin requires PHP 7.2 or higher', 'really-simple-ssl-pro'));
		}

		global $wp_version;
		if (version_compare($wp_version, '5.7', '<')) {
			deactivate_plugins(plugin_basename(__FILE__));
			wp_die(__('Really Simple SSL Pro cannot be activated. The plugin requires WordPress 5.7 or higher', 'really-simple-ssl-pro'));
		}
	}
	register_activation_hook( __FILE__, 'rsssl_pro_activation_check' );
}
if (!class_exists('REALLY_SIMPLE_SSL_PRO')) {
    class REALLY_SIMPLE_SSL_PRO {
        private static $instance;
        public $server;
        public $admin;
        public $support;
        public $licensing;
        public $csp_backend;
        public $headers;
        public $scan;
        public $importer;

        private function __construct() {
            if (isset($_GET['rsssl_apitoken']) && $_GET['rsssl_apitoken'] == get_site_option('rsssl_csp_report_token') ) {
                if ( !defined('RSSSL_LEARNING_MODE') ) define( 'RSSSL_LEARNING_MODE' , true );
            }
        }

        public static function instance() {
            if ( ! isset( self::$instance ) && ! ( self::$instance instanceof REALLY_SIMPLE_SSL_PRO ) ) {
                self::$instance = new REALLY_SIMPLE_SSL_PRO;
                self::$instance->setup_constants();
                if (self::$instance->is_compatible()) {
                    self::$instance->includes();

                    if ( rsssl_is_logged_in_rest() || is_admin() || defined('RSSSL_DOING_SYSTEM_STATUS') || defined('RSSSL_LEARNING_MODE') || ( defined( 'WP_CLI' ) && WP_CLI ) ) {

                        //load before premium options, to ensure that header check has run first.
                        self::$instance->admin          = new rsssl_pro_admin();
	                    self::$instance->headers  = new rsssl_headers();
                        self::$instance->scan     = new rsssl_scan();
                        self::$instance->importer = new rsssl_importer();
                        self::$instance->support        = new rsssl_support();
                        self::$instance->csp_backend    = new rsssl_csp_backend();
                    }
                    self::$instance->licensing       = new rsssl_licensing();
                    self::$instance->hooks();
                    self::$instance->load_translation();
                } else {
                    add_action( 'admin_notices', array( 'REALLY_SIMPLE_SSL_PRO', 'admin_notices' ) );
                    add_action( 'admin_enqueue_scripts', array( 'REALLY_SIMPLE_SSL_PRO', 'enqueue_installer_script_styles' ) );
                    add_action( 'wp_ajax_rsssl_install_plugin', array( 'REALLY_SIMPLE_SSL_PRO', 'maybe_install_suggested_plugins' ) );
                }

            }

            return self::$instance;
        }

        /**
         * Enqueue plugin installer script
         */
        public static function enqueue_installer_script_styles() {
            $minified = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
            wp_enqueue_script( 'rsssl-admin', rsssl_pro_url . "assets/js/admin$minified.js", array( 'jquery' ), rsssl_pro_version, true );
            wp_localize_script(
                'rsssl-admin',
                'rsssl_admin',
                array(
                    'admin_url'        => admin_url( 'admin-ajax.php' ),
                    'plugin_page_url'  => add_query_arg( array(
                        "page" => "really-simple-security",
                        "tab" => 'configuration',
                    ), admin_url( "options-general.php" ) ),
                )
            );
        }

        /**
           Checks if one of the necessary plugins is active, and of the required version.
        */

        public function is_compatible(){
            require_once(ABSPATH.'wp-admin/includes/plugin.php');
            $core_plugin = 'really-simple-ssl/rlrsssl-really-simple-ssl.php';
            $core_plugin_data = false;

            if ( is_plugin_active($core_plugin)) $core_plugin_data = get_plugin_data( WP_PLUGIN_DIR .'/'. $core_plugin, false, false );

            if ( is_plugin_active($core_plugin) && $core_plugin_data && version_compare($core_plugin_data['Version'] ,'6.0.0','>=') ) {
                return true;
            }

            //drop per page plugin integration
            $per_page_plugin = 'really-simple-ssl-on-specific-pages/really-simple-ssl-on-specific-pages.php';
            if ( is_plugin_active($per_page_plugin) )  {
                return false;
            }

            //nothing yet? then...sorry, but no, not compatible.
            return false;
        }

        private function setup_constants() {
            define('rsssl_pro_url', plugin_dir_url(__FILE__ ));
            define('rsssl_pro_path', plugin_dir_path(__FILE__ ));
            define('rsssl_pro_plugin', plugin_basename( __FILE__ ) );

            $debug = ( defined( 'RSSSL_DEBUG' ) && RSSSL_DEBUG ) ? time() : '';
            define('rsssl_pro_version', '6.2.3' . $debug );
            define('rsssl_pro_plugin_file', __FILE__);
            if (!defined('REALLY_SIMPLE_SSL_URL')) define( 'REALLY_SIMPLE_SSL_URL', 'https://really-simple-ssl.com');
            define( 'RSSSL_ITEM_ID', 860 );
            define( 'RSSSL_ITEM_NAME', 'Really Simple SSL Pro' );
            define( 'RSSSL_ITEM_VERSION', rsssl_pro_version );
        }

        private function includes() {
            if ( rsssl_is_logged_in_rest() || is_admin() || defined('RSSSL_DOING_SYSTEM_STATUS') || defined('RSSSL_LEARNING_MODE') || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
                require_once( rsssl_pro_path . '/upgrade.php' );
                require_once(rsssl_pro_path . '/csp-violation-endpoint.php');
                require_once(rsssl_pro_path . '/class-headers.php' );
                require_once(rsssl_pro_path . '/class-admin.php');
                require_once(rsssl_pro_path . '/class-scan.php');
                require_once(rsssl_pro_path . '/class-importer.php' );
                require_once(rsssl_pro_path . '/class-support.php');
                require_once(rsssl_pro_path . '/settings/settings.php');
            }
            require_once( rsssl_pro_path . '/front-end.php' );
            require_once( rsssl_pro_path . '/security/security.php' );
            require_once( rsssl_pro_path . '/cron/cron.php' );
            require_once( rsssl_pro_path . '/csp-endpoint-public.php' );
            require_once(rsssl_pro_path . '/class-licensing.php');
        }

        /**
         * Load plugin translations.
         *
         * @since 1.0.0
         *
         * @return void
         */
        private function load_translation() {
            load_plugin_textdomain('really-simple-ssl-pro', FALSE, dirname(plugin_basename(__FILE__) ) . '/languages/');
        }

        private function hooks() {

        }

        /**
         * Handles the displaying of any notices in the admin area
         *
         * @since 1.0.28
         * @access public
         * @return void
         */

        public static function admin_notices() {
            //prevent showing the review on edit screen, as gutenberg removes the class which makes it editable.
            $screen = get_current_screen();
            if ( $screen->base === 'post' ) {
                return;
            }

            $core_plugin_data = false;
            require_once(ABSPATH.'wp-admin/includes/plugin.php');
            $core_plugin = 'really-simple-ssl/rlrsssl-really-simple-ssl.php';
            if ( is_plugin_active($core_plugin) ) {
                $core_plugin_data = get_plugin_data( trailingslashit(WP_PLUGIN_DIR) . $core_plugin, false, false );
            }
            if ( !is_plugin_active($core_plugin) ) {
                ?>
                <div id="rsssl-message" class="notice error really-simple-plugins">
                    <style>
                        #rsssl-message {
                            padding: 0 !important;
                        }
                        #rsssl-message .rsssl-notice {
                            padding-left: 20px;
                            text-indent: 10px;
                        }
                        #rsssl-message .rsssl-notice .rsssl-notice-header {
                            border-bottom: 1px solid #DEDEDE;
                            padding-bottom: 15px;
                        }
                        #rsssl-message .rsssl-notice .rsssl-notice-footer {
                            margin-bottom: 10px;
                        }
                        .rsssl-pro-loader {
                            width: 50px;
                            height: 15px;
                            text-align: center;
                            font-size: 10px;
                        }

                        .rsssl-pro-loader > div {
                            background-color: #333;
                            height: 100%;
                            width: 3px;
                            margin:1px;
                            display: inline-block;
                            -webkit-animation: sk-stretchdelay 1.2s infinite ease-in-out;
                            animation: sk-stretchdelay 1.2s infinite ease-in-out;
                        }

                        .rsssl-pro-loader .rect2 {
                            -webkit-animation-delay: -1.1s;
                            animation-delay: -1.1s;
                        }

                        .rsssl-pro-loader .rect3 {
                            -webkit-animation-delay: -1.0s;
                            animation-delay: -1.0s;
                        }

                        .rsssl-pro-loader .rect4 {
                            -webkit-animation-delay: -0.9s;
                            animation-delay: -0.9s;
                        }

                        .rsssl-pro-loader .rect5 {
                            -webkit-animation-delay: -0.8s;
                            animation-delay: -0.8s;
                        }

                        @-webkit-keyframes sk-stretchdelay {
                            0%, 40%, 100% { -webkit-transform: scaleY(0.4) }
                            20% { -webkit-transform: scaleY(1.0) }
                        }

                        @keyframes sk-stretchdelay {
                            0%, 40%, 100% {
                                transform: scaleY(0.4);
                                -webkit-transform: scaleY(0.4);
                            }  20% {
                                   transform: scaleY(1.0);
                                   -webkit-transform: scaleY(1.0);
                               }
                        }
                    </style>
                    <div class="rsssl-notice">
                        <div class="rsssl-notice-content">
                            <?php if ( !defined('rsssl_plugin') ) {
                                ?>
                                <p> <?php echo __("Really Simple SSL Pro is an add-on for Really Simple SSL, and cannot do it on its own :-(","really-simple-ssl-pro"); ?> </p>
                                <p> <?php echo __("Please install and activate Really Simple SSL to enable the add-on.","really-simple-ssl-pro"); ?> </p> <?php
                            } else { ?>
                                <p><?php echo sprintf(__("Please %supgrade%s to the latest version to be able use the full functionality of the plugin.","really-simple-ssl-pro"),'<a href="https://really-simple-ssl.com/pro" target="_blank">','</a>');?></p>
                            <?php } ?>
                        </div>
                        <div class="rsssl-notice-footer">
                            <?php
                                require_once( rsssl_pro_path . 'class-installer.php' );
                                new rsssl_installer( 'really-simple-ssl' );
                            ?>
                            <div>
                                <button type="button" class=" rsssl-install-plugin"><?php echo __("Install and activate Really Simple SSL","really-simple-ssl-pro") ?></button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
            } elseif ( isset($core_plugin_data['Version']) && version_compare($core_plugin_data['Version'], '6.0.0', '<')) {
                ?>
                <div id="rsssl-message" class="notice error really-simple-plugins">
                    <div class="rsssl-notice">
                        <div class="rsssl-notice-content">
                            <p><?php _e("Really Simple SSL Free needs to be updated to the latest version to be compatible.","really-simple-ssl-pro");?></p>
                            <p><?php _e("Visit the plugins overview to update Really Simple SSL.","really-simple-ssl-pro")?></p>
                        </div>
                    </div>
                </div>
                <?php
            }
        }

        /**
         * Install suggested plugins
         * @return void
         */
        public static function maybe_install_suggested_plugins(){
            if ( current_user_can('install_plugins') ) {
                $error = false;
                $step = isset($_GET['step']) ? sanitize_title($_GET['step']) : 'download';
                require_once( rsssl_pro_path . 'class-installer.php' );
                $installer = new rsssl_installer( 'really-simple-ssl' );
                $installer->install($step);
            }

            $response = json_encode( [ 'success' => $error ] );
            header( "Content-Type: application/json" );
            echo $response;
            exit;
        }
    }
}

if ( !class_exists('REALLY_SIMPLE_SSL_PRO_MULTISITE') ) {
	if ( !function_exists('RSSSL_PRO' ) ){
        function RSSSL_PRO() {
	        global $wp_version;
	        if ( version_compare($wp_version, '5.7', '>=') && version_compare(PHP_VERSION, '7.2', '>=')) {
		        return REALLY_SIMPLE_SSL_PRO::instance();
	        }
		}
    }
	add_action( 'plugins_loaded', 'RSSSL_PRO', 9 );
}

if (!function_exists('rsssl_pro_activate')) {
	register_activation_hook( __FILE__, 'rsssl_pro_activate' );
	function rsssl_pro_activate() {
		add_action( 'shutdown', array( RSSSL_PRO()->admin, 'activate' ) );
	}
}

if ( !function_exists('rsssl_pro_deactivate')) {
	register_deactivation_hook( __FILE__, 'rsssl_pro_deactivate' );
	function rsssl_pro_deactivate(){
		add_action('shutdown', array(RSSSL_PRO()->admin, 'deactivate'));
	}
}
