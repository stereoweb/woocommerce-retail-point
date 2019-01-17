<?php
use Stereo\RTP\XMLParser;
/**
 * Import, list categories, count products, last import date from Retail Point XML
 */
class RTP_CLI
{
    private $parser;
    /**
     * List of functions:
     *  - list categories
     *  - products count
     *  - import all products  -- dry run maybe?
     */

    public function __construct()
    {
        $file = getenv('RTP_XML_PATH');
        $images_path = getenv('RTP_IMAGES_PATH');

        if ($file === false) {
            WP_CLI::error("Missing RTP_XML_PATH in .env");
        }

        if (empty($file)) {
            WP_CLI::error("RTP_XML_PATH empty in .env");
        }

        if (!file_exists($file)) {
            WP_CLI::error("RTP_XML_PATH : file doesn't exist");
        }

        if ($images_path === false) {
            WP_CLI::error("Missing RTP_IMAGES_PATH in .env");
        }

        if (empty($images_path)) {
            WP_CLI::error("RTP_IMAGES_PATH empty in .env");
        }

        if (!file_exists($images_path)) {
            WP_CLI::error("Missing images path in .env");
        }

        $this->parser = new XMLParser($file, $images_path);

        if (count($this->parser->errors)) {
            foreach ($this->parser->errors as $error) {
                WP_CLI::error($error);
            }
        }


    }

    /**
     * Prints a list of all products' attributes.
     *
     * ## EXAMPLES
     *
     *     wp rtp list_products_attributes
     *
     * @when after_wp_load
     */
    public function list_products_attributes()
    {
        $attributes = $this->parser->list_products_attributes();

        foreach ($attributes as $attr) {
            WP_CLI::line($attr);
        }

        WP_CLI::success('Here is the list');
    }

    /**
     * Prints the category tree.
     *
     * ## OPTIONS
     *
     * [--main-only]
     * : Print only main categories
     *
     * ## EXAMPLES
     *
     *     wp rtp get_category_tree --main-only
     *
     * @when after_wp_load
     */
    public function get_category_tree($args, $assoc_args)
    {
        $tree = $this->parser->get_category_tree();

        foreach ($tree as $dep => $subdepts) {
            WP_CLI::line($dep);

            if (!in_array('main-only', $assoc_args)) {
                foreach ($subdepts as $sub) {
                    WP_CLI::line('  ' . $sub);
                }
            }
        }

        WP_CLI::success('Here is the tree');
    }

    /**
     * Prints the products count
     *
     * @when after_wp_load
     */
    public function products_count()
    {
        $count = $this->parser->products_count();
        WP_CLI::success('Products count: ' . $count);
    }
}

WP_CLI::add_command('rtp', 'RTP_CLI');
