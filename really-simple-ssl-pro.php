<?php
/**
 * Plugin Name: Really Simple SSL pro
 * Plugin URI: https://really-simple-ssl.com/pro
 * Description: Optimize your SSL security with the mixed content scan, secure cookies and advanced security headers.
 * Version: 4.1.2
 * Text Domain: really-simple-ssl-pro
 * Domain Path: /languages
 * Author: Really Simple Plugins
 * Author URI: https://www.really-simple-plugins.com
 */

/*  Copyright 2020  Really Simple Plugins B.V.  (email : support@really-simple-plugins.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

update_site_option('rsssl_pro_license_key','license_key');
update_site_option('rsssl_pro_license_activation_limit',0);
update_site_option('rsssl_pro_license_activations_left',9999 );
update_site_option('rsssl_pro_license_expires','01.01.2030' );
defined('ABSPATH') or die("you do not have access to this page!");

if (!function_exists('rsssl_pro_activation_check')) {
	/**
	 * Checks if the plugin can safely be activated, at least php 5.6 and wp 4.8
	 */
	function rsssl_pro_activation_check()
	{
		if (version_compare(PHP_VERSION, '5.6', '<')) {
			deactivate_plugins(plugin_basename(__FILE__));
			wp_die(__('Really Simple SSL pro cannot be activated. The plugin requires PHP 5.6 or higher', 'really-simple-ssl-pro'));
		}

		global $wp_version;
		if (version_compare($wp_version, '4.8', '<')) {
			deactivate_plugins(plugin_basename(__FILE__));
			wp_die(__('Really Simple SSL pro cannot be activated. The plugin requires WordPress 4.9 or higher', 'really-simple-ssl-pro'));
		}
	}
	register_activation_hook( __FILE__, 'rsssl_pro_activation_check' );
}

class REALLY_SIMPLE_SSL_PRO {

    private static $instance;
    public $rsssl_server;
    public $really_simple_ssl;
    public $rsssl_help;
    public $rsssl_support;
    public $rsssl_licensing;
    public $rsssl_csp_backend;

    private function __construct() {}

    public static function instance() {
        if ( ! isset( self::$instance ) && ! ( self::$instance instanceof REALLY_SIMPLE_SSL_PRO ) ) {
            self::$instance = new REALLY_SIMPLE_SSL_PRO;
            if (self::$instance->is_compatible()) {
                self::$instance->setup_constants();
                self::$instance->includes();

                if (is_admin() || defined('RSSSL_DOING_SYSTEM_STATUS')) {
	                self::$instance->rsssl_premium_options = new rsssl_premium_options();
	                self::$instance->rsssl_scan            = new rsssl_scan();
	                self::$instance->rsssl_importer        = new rsssl_importer();
                    self::$instance->rsssl_csp_backend     = new rsssl_csp_backend();
                    self::$instance->rsssl_support         = new rsssl_support();
                }
				//needs to be outside is_admin for automatic updates
	            self::$instance->rsssl_licensing       = new rsssl_licensing();

	            self::$instance->hooks();
            } else {
                add_action('admin_notices', array('REALLY_SIMPLE_SSL_PRO', 'admin_notices'));
            }

        }

        return self::$instance;
    }

    /**
       Checks if one of the necessary plugins is active, and of the required version.
    */

    public function is_compatible(){
        require_once(ABSPATH.'wp-admin/includes/plugin.php');
        $core_plugin = 'really-simple-ssl/rlrsssl-really-simple-ssl.php';
        if ( is_plugin_active($core_plugin)) $core_plugin_data = get_plugin_data( WP_PLUGIN_DIR .'/'. $core_plugin, false, false );
        if ( is_plugin_active($core_plugin) && version_compare($core_plugin_data['Version'] ,'4.0.0','>=') ) {
            return true;
        }

        $per_page_plugin = 'really-simple-ssl-on-specific-pages/really-simple-ssl-on-specific-pages.php';
        if (is_plugin_active($per_page_plugin)) $per_page_plugin_data = get_plugin_data( WP_PLUGIN_DIR .'/'. $per_page_plugin, false, false );
        if (is_plugin_active($per_page_plugin) && version_compare($per_page_plugin_data['Version'] , '4.0.0','>' )) {
            return true;
        }

        //nothing yet? then...sorry, but no, not compatible.
        return false;
    }

    private function setup_constants() {
        require_once(ABSPATH.'wp-admin/includes/plugin.php');
        $plugin_data = get_plugin_data( __FILE__ );

        define('rsssl_pro_url', plugin_dir_url(__FILE__ ));
        define('rsssl_pro_path', plugin_dir_path(__FILE__ ));
        define('rsssl_pro_plugin', plugin_basename( __FILE__ ) );
	    define('rsssl_pro_template_path', trailingslashit(plugin_dir_path(__FILE__)).'grid/templates/');

	    $debug = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? time() : '';
	    define('rsssl_pro_version', $plugin_data['Version'] . $debug );
        define('rsssl_pro_plugin_file', __FILE__);

        if (!defined('REALLY_SIMPLE_SSL_URL')) define( 'REALLY_SIMPLE_SSL_URL', 'https://really-simple-ssl.com');
        define( 'RSSSL_ITEM_ID', 860 );
        define( 'RSSSL_ITEM_NAME', 'Really Simple SSL Pro' );
        define( 'RSSSL_ITEM_VERSION', rsssl_pro_version );
    }

    private function includes() {
	    require_once(rsssl_pro_path . '/csp-endpoint-public.php');

	    if (is_admin() || defined('RSSSL_DOING_SYSTEM_STATUS') ) {
	        require_once(rsssl_pro_path . '/csp-violation-endpoint.php');
	        require_once(rsssl_pro_path . '/class-premium-options.php');
            require_once(rsssl_pro_path . '/class-scan.php');
            require_once(rsssl_pro_path . '/class-cert-expiration.php');
	        require_once( rsssl_pro_path . '/class-importer.php' );
	        require_once(rsssl_pro_path . '/class-support.php');
        }

	    //needs to be outside is_admin for automatic updates
	    require_once(rsssl_pro_path . '/class-licensing.php');
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
	    if ( $screen->base === 'post' ) return;

	    $per_page_plugin_data = false;
	    $core_plugin_data = false;

        require_once(ABSPATH.'wp-admin/includes/plugin.php');
        $core_plugin = 'really-simple-ssl/rlrsssl-really-simple-ssl.php';
        if (is_plugin_active($core_plugin)) $core_plugin_data = get_plugin_data( trailingslashit(WP_PLUGIN_DIR) . $core_plugin, false, false );
        $per_page_plugin = 'really-simple-ssl-on-specific-pages/really-simple-ssl-on-specific-pages.php';
        if ( is_plugin_active($per_page_plugin)) $per_page_plugin_data = get_plugin_data( trailingslashit(WP_PLUGIN_DIR) . $per_page_plugin, false, false );
        if ( !is_plugin_active($core_plugin) && !is_plugin_active($per_page_plugin)) {
            ?>
            <style>
                .rsssl-notice-header {
                    border-bottom: 1px solid #DEDEDE;
                    padding-bottom: 15px;
                }
                #message {
                    padding: 0 !important;
                }
                #message h3 {
                    text-indent: 10px;
                }
                #message p {
                    text-indent: 10px;
                }
            </style>
            <div id="message" class="error notice really-simple-plugins">
                <h3 class="rsssl-notice-header"><?php echo __("Plugin dependency error","really-simple-ssl-pro");?></h3>
                <?php if (!is_rsssl_plugin_active()) {
                    ?>
                    <p> <?php echo __("Really Simple SSL pro is an add-on for Really Simple SSL, and cannot do it on its own :(","really-simple-ssl-pro"); ?> </p>
                    <p> <?php echo __("Please install and activate Really Simple SSL before activating this add-on.","really-simple-ssl-pro"); ?> </p> <?php
                } else { ?>
                    <p><?php echo __("Please upgrade to the latest version to be able use the full functionality of the plugin.","really-simple-ssl-pro");?></p>
                <?php }?>
            </div>
            <?php
        }elseif ( $core_plugin && isset($core_plugin_data['Version']) && version_compare($core_plugin_data['Version'], '4.0.0', '<')) {
            ?>
            <div id="message" class="error notice really-simple-plugins">
                <h1><?php echo __("Plugin dependency error","really-simple-ssl-pro");?></h1>
                <p><?php echo __("Really Simple SSL needs to be updated to the latest version to be compatible.","really-simple-ssl-pro");?></p>
                <p><?php echo __("Please upgrade to the latest version to be able use the full functionality of the plugin.","really-simple-ssl-pro");?></p>
            </div>
            <?php
        }
    }
}

if (!class_exists('REALLY_SIMPLE_SSL_PRO_MULTISITE')) {
	function RSSSL_PRO() {
        return REALLY_SIMPLE_SSL_PRO::instance();
    }
	add_action( 'plugins_loaded', 'RSSSL_PRO', 10 );
}

require_once( plugin_dir_path(__FILE__ ) . '/front-end.php' );

/**
 * Set some defaults
 */
if (!function_exists('rsssl_enable_security_header_options')) {
	function rsssl_enable_security_header_options()
	{
		set_transient('rsssl_set_defaults', true, DAY_IN_SECONDS);
	}
}
register_activation_hook(__FILE__ ,'rsssl_enable_security_header_options');

if ( !function_exists('rsssl_pro_deactivate') ) {
	function rsssl_pro_deactivate()
	{
		wp_clear_scheduled_hook('rsssl_pro_daily_hook');
		if (REALLY_SIMPLE_SSL_PRO::instance()->is_compatible() ) RSSSL_PRO()->rsssl_premium_options->deactivate();
	}
	register_deactivation_hook( __FILE__, 'rsssl_pro_deactivate');
}