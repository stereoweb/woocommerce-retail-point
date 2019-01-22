<?php
use Stereo\RTP\XMLParser;

class WC_RTP_Importer
{
    private $parser;
    private $file;
    private $images_path;

    public function __construct()
    {
        $this->file = getenv('RTP_XML_PATH');
        $this->images_path = getenv('RTP_IMAGES_PATH');

        $this->parser = new XMLParser($this->file, $this->images_path);
    }

    public function import()
    {
        $this->update_categories_tags();
        $this->update_products();
    }

    public function update_categories_tags()
    {
        $tree = $this->parser->get_category_tree();

        $dep_term_id = 0;

        if (!$departement_term = get_term_by('name', 'Départements', 'product_cat')) {
            $d = wp_insert_term(
                'Départements', // the term
                'product_cat' // the taxonomy
            );

            $dep_term_id = $d['term_id'];
        } else {
            $dep_term_id = $departement_term->term_id;
        }

        if (!get_term_by('name', 'Fournisseur', 'product_cat')) {
            $d = wp_insert_term(
                'Fournisseur', // the term
                'product_cat' // the taxonomy
            );
        }

        foreach ($tree as $dep => $subdepts) {
            wp_insert_term(
                $dep, // the term
                'product_cat', // the taxonomy
                [
                    'parent' => $dep_term_id
                ]
            );

            foreach ($subdepts as $sub) {
                wp_insert_term(
                    $sub, // the term
                    'product_tag' // the taxonomy
                );
            }
        }
    }

    public function update_products()
    {
        $this->parser->open();

        $fournisseur_term = get_term_by('name', 'fournisseur', 'product_cat');

        while ($product = $this->parser->fetch(true)) {

            if (!isset($product['skus']['sku'])) continue 1;

            $product_name = trim_rtp($product['@attributes']['desc']);
            $numref = trim_rtp($product['@attributes']['numref']);
            $product_id = 0;

            $args = array(
                'meta_query' => array(
                    array(
                        'key' => '_sku',
                        'value' => $numref
                    )
                ),
                'post_parent' => 0,
                'post_type' => 'product',
                'posts_per_page' => 1
            );

            if ($posts = get_posts($args)) {
                $product_id = $posts[0]->ID;
            } else {
                $product_id = wp_insert_post([
                    'post_title' => $product_name,
                    'post_content' => '',
                    'post_status' => 'publish',
                    'post_type' => "product",
                ]);

                update_post_meta($product_id, '_sku', $numref);
            }

            if (isset($product['skus']['sku']['@attributes'])) {  // Simple product
                $sku = $product['skus']['sku'];

                $modified = get_post_meta($product_id, '_rtp_modified', true);
                if ($modified !== false && $modified == $sku['DateModified']) continue 1;

                //wp_set_object_terms($product_id, 'simple', 'product_type');

                update_post_meta($product_id, '_rtp_modified', $sku['DateModified']);
                update_post_meta($product_id, '_rtp_updated', 1);

                $simple = new WC_Product_Simple($product_id);

                $simple->set_sku($numref);
                $simple->set_regular_price($sku['price']);
                $simple->set_stock_quantity(intval($sku['qt']));
                $simple->set_manage_stock(true);
                $simple->set_stock_status('');

                $categories_id = [];

                if ($dept = get_term_by('name', trim_rtp($sku['Dept']), 'product_cat')) {
                    $categories_id[] = $dept->term_id;
                }
                if ($tag = get_term_by('name', trim_rtp($sku['SubDept']), 'product_tag')) {
                    $simple->set_tag_ids([$tag->term_id]);
                }

                // Fournisseur
                if (!$fou = get_term_by('name', trim_rtp($sku['Fournisseur']), 'product_cat')) {
                    $fou = wp_insert_term(
                        trim_rtp($sku['Fournisseur']), // the term
                        'product_cat', // the taxonomy
                        [
                            'parent' => $fournisseur_term->term_id
                        ]
                    );

                    $categories_id[] = $fou['term_id'];
                } else {
                    $categories_id[] = $fou->term_id;
                }

                $simple->set_category_ids($categories_id);

                if (isset($sku['Promo'])) {
                    $simple->set_price($sku['Promo']);
                    $simple->set_sale_price($sku['Promo']);
                } else {
                    $simple->set_price($sku['price']);
                }

                $simple->set_weight(''); // weight (reseting)
                $simple->save(); // Save the data

            } else { // Variable product
                $product_obj = wc_get_product($product_id);

                $couleurs = [];
                $grandeurs = [];

                $attributes_array = [
                    'couleur' => [
                        'name' => 'Couleur',
                        'value' => '',
                        'is_visible' => '1',
                        'is_variation' => '1',
                        'is_taxonomy' => '0'
                    ],
                    'grandeur' => [
                        'name' => 'Grandeur',
                        'value' => '',
                        'is_visible' => '1',
                        'is_variation' => '1',
                        'is_taxonomy' => '0'
                    ]
                ];

                foreach ($product['skus']['sku'] as $sku) {
                    $couleur = trim_rtp($sku['couleur']);
                    $grandeur = trim_rtp($sku['grandeur']);

                    if (!empty($couleur) && !in_array($couleur, $couleurs)) {
                        $couleurs[] = $couleur;
                    }

                    if (!empty($grandeur) && !in_array($grandeur, $grandeurs)) {
                        $grandeurs[] = $grandeur;
                    }
                }

                if (count($couleurs)) {
                    asort($couleurs);
                    $attributes_array['couleur']['value'] = implode(' | ', $couleurs);
                } else {
                    unset($attributes_array['couleur']);
                }

                if (count($grandeurs)) {
                    asort($grandeurs);
                    $attributes_array['grandeur']['value'] = implode(' | ', $grandeurs);
                } else {
                    unset($attributes_array['grandeur']);
                }

                update_post_meta($product_id, '_product_attributes', $attributes_array);

                foreach ($product['skus']['sku'] as $sku) {
                    $variation_id = 0;
                    $id = trim_rtp($sku['@attributes']['id']);
                    $name = trim_rtp($sku['@attributes']['desc']);
                    $codebar = trim_rtp($sku['codebar']);
                    if (empty($codebar)) $codebar = $id;

                    $args = array(
                        'meta_query' => array(
                            array(
                                'key' => '_sku',
                                'value' => $codebar
                            )
                        ),
                        'post_parent' => $product_id,
                        'post_type' => 'product_variation',
                        'posts_per_page' => 1
                    );

                    if ($posts = get_posts($args)) {
                        $variation_id = $posts[0]->ID;
                    } else {
                        $variation = array(
                            'post_title' => $name,
                            'post_content' => '',
                            'post_status' => 'publish',
                            'post_parent' => $product_id,
                            'post_type' => 'product_variation'
                        );
                        $variation_id = wp_insert_post($variation);
                    }

                    $modified = get_post_meta($variation_id, '_rtp_modified', true);
                    if ($modified !== false && $modified == $sku['DateModified']) continue 1;

                    update_post_meta($variation_id, '_rtp_modified', $sku['DateModified']);
                    update_post_meta($variation_id, '_rtp_updated', 1);

                    if (trim_rtp($sku['couleur'])) update_post_meta($variation_id, 'attribute_couleur', trim_rtp($sku['couleur']));
                    if (trim_rtp($sku['grandeur'])) update_post_meta($variation_id, 'attribute_grandeur', trim_rtp($sku['grandeur']));

                    $variation = new WC_Product_Variation($variation_id);

                    $variation->set_sku($codebar);
                    $variation->set_regular_price($sku['price']);

                    $categories_id = [];
                    if ($dept = get_term_by('name', trim_rtp($sku['Dept']), 'product_cat')) {
                        $categories_id[] = $dept->term_id;
                    }
                    if ($tag = get_term_by('name', trim_rtp($sku['SubDept']), 'product_tag')) {
                        $product_obj->set_tag_ids([$tag->term_id]);
                    }

                    // Fournisseur
                    if (!$fou = get_term_by('name', trim_rtp($sku['Fournisseur']), 'product_cat')) {
                        $fou = wp_insert_term(
                            trim_rtp($sku['Fournisseur']), // the term
                            'product_cat', // the taxonomy
                            [
                                'parent' => $fournisseur_term->term_id
                            ]
                        );

                        $categories_id[] = $fou['term_id'];
                    } else {
                        $categories_id[] = $fou->term_id;
                    }

                    $product_obj->set_category_ids($categories_id);

                    if (isset($sku['Promo'])) {
                        $variation->set_price($sku['Promo']);
                        $variation->set_sale_price($sku['Promo']);
                    } else {
                        $variation->set_price($sku['price']);
                    }

                    $variation->set_stock_quantity(intval($sku['qt']));
                    $variation->set_manage_stock(true);
                    $variation->set_stock_status('');

                    $variation->set_weight(''); // weight (reseting)
                    $variation->save(); // Save the data
                }

                $product_obj->save();
                wp_set_object_terms($product_id, 'variable', 'product_type');
            }
        }
    }

    public static function clear()
    {
        global $wpdb;

        $wpdb->query("DELETE FROM wp_terms WHERE term_id IN (SELECT term_id FROM wp_term_taxonomy WHERE taxonomy LIKE 'pa_%')");
        $wpdb->query("DELETE FROM wp_term_taxonomy WHERE taxonomy LIKE 'pa_%'");
        $wpdb->query("DELETE FROM wp_term_relationships WHERE term_taxonomy_id not IN (SELECT term_taxonomy_id FROM wp_term_taxonomy)");
        $wpdb->query("DELETE FROM wp_term_relationships WHERE object_id IN (SELECT ID FROM wp_posts WHERE post_type IN ('product','product_variation'))");
        $wpdb->query("DELETE FROM wp_postmeta WHERE post_id IN (SELECT ID FROM wp_posts WHERE post_type IN ('product','product_variation'))");
        $wpdb->query("DELETE FROM wp_posts WHERE post_type IN ('product','product_variation')");
        $wpdb->query("DELETE pm FROM wp_postmeta pm LEFT JOIN wp_posts wp ON wp.ID = pm.post_id WHERE wp.ID IS NULL");
    }
}
