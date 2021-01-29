<?php

global $wpdb;
$table_name = $wpdb->base_prefix . "rsssl_csp_log";

$rows = $wpdb->get_results("SELECT * FROM $table_name ORDER BY time DESC LIMIT 100");

$style = '';

?>
<div class="rsssl-csp-table-container" <?php echo $style ?>>
    <table id="rsssl-csp-table" class="really-simple-ssl-table">
        <thead>
            <tr class="rsssl-csp-tr">
                <th class="rsssl-csp-th"><?php echo __("Found", "really-simple-ssl-pro")?></th>
                <!--Document-uri-->
                <th class="rsssl-csp-th"><?php echo __("On page", "really-simple-ssl-pro")?></th>
                <!--Violated Directive-->
                <th class="rsssl-csp-th"><?php echo __("Directive", "really-simple-ssl-pro")?></th>
                <!--Blocked-uri-->
                <th class="rsssl-csp-th"><?php echo __("Domain/protocol", "really-simple-ssl-pro")?></th>
                <th class="rsssl-csp-th"><?php echo __("Add to policy", "really-simple-ssl-pro")?></th>
            </tr>
        </thead>
        <?php

        foreach ($rows as $row) {
            //Only show results that aren't in policy (which don't have an inpolicy value yet)
            if ( (!empty($row->inpolicy)) ) {
                continue;
            }

            $uri = substr(str_replace(site_url(), "", $row->documenturi), 0, 40);
            if ($uri === '/' || $uri === '') $uri = 'Home';

            // Check if date is today
            if (date('Ymd') == date('Ymd', strtotime($row->time))) {
                $date = __("Today", "really-simple-ssl-pro");
            } else {
                $date = human_time_diff(strtotime($row->time), current_time('timestamp')) . " " . __("ago", "really-simple-ssl-pro");
            }

            ?>
            <tr>
                <td class="rsssl-csp-td"><?php echo $date; ?></td>
                <td class="rsssl-csp-td"><a target="_blank" href="<?php echo $row->documenturi?>"><?php echo $uri ?></a></td>
                <td class="rsssl-csp-td"><?php echo $row->violateddirective ?></td>
                <td class="rsssl-csp-td"><?php echo $row->blockeduri ?></td>
                <td class="rsssl-csp-td"><button type="button" data-id="<?php echo $row->id ?>" data-path=0 data-url=0 data-token="<?php echo wp_create_nonce('rsssl_fix_post');?>" class="button button-secondary start-add-to-csp" style="width: 80px; text-align: center;" ><?php _e("Allow", "really-simple-ssl-pro")?></button></td>
            </tr>
            <?php
        }
        ?>
    </table>
</div>