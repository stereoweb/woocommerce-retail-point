<?php
use Stereo\RTP\XMLParser;
/**
 * Import, list categories, count products, last import date from Retail Point XML
 */
class WC_RTP_CLI
{
    /**
     * Clear all products.
     *
     * ## EXAMPLES
     *
     *     wp wc_rtp clear_products
     *
     * @when after_wp_load
     */
    public function clear_products()
    {
        WC_RTP_Importer::clear();

        WP_CLI::success('All products deleted');
    }
}

WP_CLI::add_command('wc_rtp', 'WC_RTP_CLI');
