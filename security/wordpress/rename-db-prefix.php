<?php
defined( 'ABSPATH' ) or die( "you do not have access to this page!");


add_action('admin_init','rsssl_rename_db_prefix');

/**
 * Rename DB prefix
 * Copy all current wp_ tables
 * Replace required wp_ prefixed values with new prefix
 */

function rsssl_rename_db_prefix() {
	if ( !rsssl_user_can_manage() ) {
		return false;
	}

	global $wpdb;
	//only if it's wp_
	if ( $wpdb->prefix !== 'wp_' ) {
		return false;
	}

    // Get all tables starting with wp_
	$tables = $wpdb->get_results("SHOW TABLES LIKE '".$wpdb->prefix."%'", ARRAY_N);

	//make prefix persistent
	$new_prefix = get_site_option('rsssl_db_prefix');
	if ( !$new_prefix ) {
		$new_prefix = rsssl_generate_random_string( 5 ) . '_';
		update_site_option('rsssl_db_prefix', $new_prefix);
	}

	// Copy these tables with a new prefix
	foreach ( $tables as $table ) {
        $table_name = $table[0];
		if ( strpos($table_name, 'wp_') ===0 ) {
			$new_table = preg_replace('/wp_/', $new_prefix, $table_name, 1);
			$wpdb->query("CREATE TABLE IF NOT EXISTS $new_table LIKE $table_name");
			$wpdb->query("INSERT IGNORE $new_table SELECT * FROM $table_name");
		}
    }

    // Array containing the table, column and value to update
    $to_update = array(
        1 => array (
            'table' => 'usermeta',
            'column' => 'meta_key',
            'value_no_prefix' => 'capabilities',
        ),
        2 => array(
            'table' => 'usermeta',
            'column' => 'meta_key',
            'value_no_prefix' => 'user_level',
        ),
        3 => array(
            'table' => 'usermeta',
            'column' => 'meta_key',
            'value_no_prefix' => 'autosave_draft_ids',
        ),
        4 => array(
            'table' => 'options',
            'column' => 'option_name',
            'value_no_prefix' => 'user_roles',
        ),
    );

    // Loop through array and update options accordingly
    foreach ( $to_update as $key => $option ) {
        $table = $option['table'];
        $column = $option['column'];
        $value_no_prefix = $option['value_no_prefix'];

        // On multisite, each subsite has a wp_{site_id}_options table where the wp_{site_id}_user_roles option is located.
        // The main site does not use the {site_id}, the option here resides in the wp_options table, wp_user_roles option.
        // Usermeta for all sites including main site is saved in the wp_usermeta table, where each subsite has a wp_{site_id}_capabilities and wp_{site_id}_user_level option etc.
        // Now update these
        if ( is_multisite() ) {
            $sites = get_sites();
            foreach ($sites as $site) {
                // Get blog_id and update $value_no_prefix with {site_id}
                $blog_id = $site->blog_id;
                // Used in both options + usermeta table
                $new_prefix_plus_blog_id = $new_prefix . $blog_id . '_';
                // Used in options table
                $current_prefix_plus_blog_id = $wpdb->prefix . $blog_id . '_';
                // Also in options table
                $table_plus_blog_id = $blog_id . '_'. $table;
                switch_to_blog($site->blog_id);
                // Handle main site in normal way (see else)
                if ( ! is_main_site() ) {
                    // If the table is options, update wp_{site_id}_options
                    if (isset ($option['table']) && $option['table'] === 'options') {
                        $wpdb->query("UPDATE `$new_prefix$table_plus_blog_id` set `$column` = '$new_prefix_plus_blog_id$value_no_prefix' where `$column` = '$current_prefix_plus_blog_id$value_no_prefix'");
                    } else {
                        // Here it's the usermeta table. Usermeta is saved in the wp_usermeta table regardless of site_id, so no $blog_id here
                        $wpdb->query("UPDATE `$new_prefix$table` set `$column` = '$new_prefix_plus_blog_id$value_no_prefix' where `$column` = '$wpdb->prefix$value_no_prefix'");
                    }
                } else {
                    // Handle main site in the normal way
                    $wpdb->query("UPDATE `$new_prefix$table` set `$column` = '$new_prefix$value_no_prefix' where `$column` = '$wpdb->prefix$value_no_prefix'");
                }
                // Restore blog
                restore_current_blog();
            }
        } else {
            // This is either a normal site or a multisite main site
            $wpdb->query("UPDATE `$new_prefix$table` set `$column` = '$new_prefix$value_no_prefix' where `$column` = '$wpdb->prefix$value_no_prefix'");
        }
    }

    // Verify DB copy
    if ( rsssl_verify_database_copy($new_prefix) !== true ) return;

    // Update the prefix in wp-config.php
    $wpconfig_path = rsssl_find_wp_config_path();

    // Update wp_ prefix to new one
    if ( is_writable( $wpconfig_path ) ) {
        $wpconfig = file_get_contents($wpconfig_path);
		// Look for $table_prefix = 'wp_';. Match both ' " and replace with new prefix.
        $updated = preg_replace('/(\$table_prefix\s*=\s*)([\'"])wp_\2;/', '${1}\'' . $new_prefix . '\';', $wpconfig);
        file_put_contents($wpconfig_path, $updated);

        // Remove old wp_ tables
        foreach ( $tables as $table ) {
	        $wpdb->query("DROP TABLE IF EXISTS $table[0]");
        }

        // Clear DB cache
        $wpdb->flush();
        $wpdb->prefix = $new_prefix;
        $wpdb->set_prefix( $new_prefix );
    } else {
        // Cannot update. Remove new prefixed tables
        $new_prefix_tables = $wpdb->get_results("SHOW TABLES LIKE '".$new_prefix."%'", ARRAY_N);
        foreach ( $new_prefix_tables as $new_table ) {
            $wpdb->query("DROP TABLE IF EXISTS $new_table[0]");
        }

    }


	return true;
}

/**
 * @return bool
 * Verify database copy
 */
function rsssl_verify_database_copy($new_prefix) {

    global $wpdb;

    $original_tables = $wpdb->get_results("SHOW TABLES LIKE '".$wpdb->prefix."%'", ARRAY_N);
    $new_tables = $wpdb->get_results("SHOW TABLES LIKE '".$new_prefix."%'", ARRAY_N);

    if ( count( $original_tables ) === count( $new_tables ) ) {
        // Count rows in table
        return true;
    }

    return false;
}
