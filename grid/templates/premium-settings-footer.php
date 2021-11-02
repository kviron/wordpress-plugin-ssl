<?php defined('ABSPATH') or die("you do not have access to this page!");

if ( RSSSL_PRO()->rsssl_premium_options->site_uses_cache() ) {
    $caching_plugin = RSSSL_PRO()->rsssl_premium_options->site_uses_cache( $str=true );
	if ( RSSSL_PRO()->rsssl_premium_options->get_networkwide_option('rsssl_security_headers_method') === 'php' ) {
		$caching_icon = "rsssl-dot-error";
		$caching_text = sprintf(__("Does not work with %s", "really-simple-ssl"), $caching_plugin );;
	} else {
		$caching_icon = "rsssl-dot-success";
		$caching_text = sprintf(__("Works with %s", "really-simple-ssl"), $caching_plugin );
	}
} else {
	$caching_icon = "rsssl-dot-success";
	$caching_text = __("No caching", "really-simple-ssl");
}

// Server
$treat_as_apache = RSSSL()->rsssl_server->get_server() === 'apache' || RSSSL()->rsssl_server->get_server() === 'litespeed';
$server_type = RSSSL()->rsssl_server->get_server();
$server_nicename = ucfirst( RSSSL()->rsssl_server->get_server() );
$headers_method = RSSSL_PRO()->rsssl_premium_options->get_networkwide_option('rsssl_security_headers_method');
if ( $server_type === 'nginx' && $headers_method === 'htaccess' ) {
    $server_icon = "rsssl-dot-error";
    $text = __('.htaccess unavailable on nginx','really-simple-ssl');
} elseif ( $treat_as_apache && $headers_method === 'nginxconf' ) {
	$server_icon = "rsssl-dot-error";
	$text = sprintf(__('nginx.conf unavailable on %s','really-simple-ssl'), $server_nicename);
} elseif ( $treat_as_apache ) {
	$server_icon = "rsssl-dot-success";
	$text = $server_nicename;
} elseif ($server_type === 'nginx') {
	$server_icon = "rsssl-dot-success";
	$text = 'nginx';
} else {
	$server_icon = "rsssl-dot-success";
	$text = __("Server not recognized", 'really-simple-ssl');
}

$items = array(
	1 => array(
		'class' => 'footer-right',
		'dot_class' => $caching_icon,
		'text' => $caching_text,
	),
	2 => array(
		'class' => 'footer-right',
		'dot_class' => $server_icon,
		'text' => $text,
	),
);

?>
<div id="rsssl-premium-settings-footer">
    <span class="rsssl-footer-item footer-left">
        <input class="button button-rsssl-secondary rsssl-button-save" name="Submit" type="submit" value="<?php echo __("Save", "really-simple-ssl"); ?>"/>
    </span>
    <?php
    foreach ($items as $item) { ?>
        <span class="rsssl-footer-item <?php echo $item['class']?>">
                <span class="rsssl-grid-footer dot <?php echo $item['dot_class']?>"></span>
                <?php echo $item['text']?>
            </span>
    <?php } ?>
</div>

