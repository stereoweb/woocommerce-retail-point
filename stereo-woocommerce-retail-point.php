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
}

