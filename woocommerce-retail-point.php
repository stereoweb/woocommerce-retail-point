<?php
/**
 * Plugin Name:       Stereo WooCommerce Retail Point
 * Description:       Un pont entre Retail Point XML et WooCommerce
 * Version:           1.0.0
 * Author:            Jonathan Grenier
 * Author URI:        http://stereo.ca
 * Text Domain:       stereo.ca
 */

// CLI happiness
if (defined('WP_CLI') && WP_CLI) {
    require_once dirname(__FILE__) . '/inc/RTP_CLI.php';
    require_once dirname(__FILE__) . '/inc/WC_RTP_CLI.php';
}

function trim_rtp($value)
{
    $value = trim($value, ' /"');
    return $value;
}

require_once dirname(__FILE__) . '/inc/WC_RTP_Importer.php';
