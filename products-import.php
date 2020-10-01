<?php
/**
* BM Import XML.
*
* Main import class
*
* @since 1.0.0
*/
class BM_XML_Products_Import {

   public $file;
   public $uploads_dir;
   public $exclude_asortyments;
   
   // public $existing_categories;

   public function __construct($file) {

      error_log('---- import start ----');
      $before = microtime(true);

      $this->uploads_dir = wp_get_upload_dir();
      $this->exclude_asortyments = explode(',', get_option('bm_exclude_asortyments'));
      error_log( "exclude_asortyments\n" . print_r($this->exclude_asortyments, true) . "\n" );


      $this->file = $file;
      $this->import_from_xml_file($file);

      $after = microtime(true);
      error_log($after-$before);
      error_log('---- import end ----');
      
   }
   
	/**
    * Main function.
    *
    * Proccess the XML file.
    *
    * @since 1.0.0
    *
    * @access public
    */
   public function import_from_xml_file($file) {


   
      $xml = simplexml_load_file($file);

      if (false === $xml) {
         $this->admin_notice('Failed loading XML', 'error');
      } 
   
      $cat_result = $this->import_categories($xml);
      $products_result = $this->import_products($xml);

      error_log( "cat_result\n" . print_r($cat_result, true) . "\n" );
      error_log( "products_result\n" . print_r($products_result, true) . "\n" );


      if($cat_result || $products_result) {
         $this->admin_notice('XML Successfully Imported', 'updated');
      } 
      
   }

   /**
    * Display admin notise after options page reload.
    *
    * Return admin notice
    *
    * @since 1.0.0
    *
    * @param string $notice_text Text that will be displayed in notice
    *
    * @param string $notice_type Type (colour) of admin notice
    *
    */
   public function admin_notice($notice_text, $notice_type) {
      if(is_admin() && !wp_doing_cron()) {
         add_settings_error('', '', $notice_text, $notice_type);
         error_log($notice_text);
      }
      
   }

   /**
    * Import/update categories.
    *
    * Return true if categories successfully imported and updated
    *
    * @since 1.0.0
    *
    * @param object $xml simplexml_load_file object
    *
    * @return boolean
    */
   public function import_categories($xml) {

      // CREATE UPDATE PRODUCT CATEGORIES
      $imported_categories = $xml->wykazy->asortymenty;

      if (empty($imported_categories)) {
         error_log('No asortyments to import');
         return false;
      }


      // no more than 500 categories
      if (count($imported_categories->asortyment) >= 2000) {
         $this->admin_notice('Too mutch categories', 'error');
         return false;          
      }

   
      foreach($imported_categories->asortyment as $imported_category) {
   
         $imported_category = json_decode(json_encode($imported_category), true);

         if(in_array($imported_category['asortyment_id'], $this->exclude_asortyments)) {
            error_log('ignored asortyment - ' . $imported_category['asortyment_nazwa'] . ', asortyment_id - ' . $imported_category['asortyment_id']);
            continue;
         }

         $args = array(
            'hide_empty' => false,
            'number' => 1,
            'meta_query' => array(
               array(
                  'key'         =>  'asortyment_id',
                  'value'       =>  $imported_category['asortyment_id'],
                  'compare'     =>  '=='
               )
            ),
         );
         $existing_category = get_terms( 'product_cat', $args );
         // error_log( "existing_category\n" . print_r($existing_category, true) . "\n" );

   
   
         if(!empty($existing_category)) {

            if ($imported_category['do_usuniecia'] == 'N') {
   
               // update existing category
               $args = array(
                  'name' => $imported_category['asortyment_nazwa'],
                  'slug' => '',
               );
               wp_update_term( $existing_category[0]->term_id, 'product_cat', $args );
               update_term_meta( $existing_category[0]->term_id, 'asortyment_id', $imported_category['asortyment_id']);
               update_term_meta( $existing_category[0]->term_id, 'pod_asortymenty', array_shift($imported_category['pod_asortymenty']));
      
               error_log('updated term ' . $imported_category['asortyment_nazwa']);

            } elseif ($imported_category['do_usuniecia'] == 'Y') {

               $deleted = wp_delete_term( $existing_category[0]->term_id, 'product_cat' );
               error_log('removed term - ' . $imported_category['asortyment_nazwa']);
               error_log( "removed term result\n" . print_r($deleted, true) . "\n");


            }
   
         } else {
   
            // create new category
            $term = wp_insert_term( 
               $imported_category['asortyment_nazwa'],
               'product_cat', 
               array(
                  'description' => '',
                  // 'parent'      => 0,
                  'slug'        => '',
               ) 
            );
            error_log( "wp_insert_term\n" . print_r($term, true) . "\n" );




            
            // if term exist but not has asortyment_id meta set
            if ( is_wp_error($term) && $term->get_error_code() == "term_exists") {
               $term_id = $term->get_error_data();
               $term = array();
               $term['term_id'] = $term_id;
               error_log('--existing term without asortyment_id - ' . $imported_category['asortyment_nazwa']);
            } else {
               // error_log('--some error, skip term ' . $imported_category['asortyment_nazwa']);
               // error_log('skipped term name ' . $imported_category['asortyment_nazwa']);
               // error_log( "skipped term\n" . print_r($term, true) . "\n" );
               // continue;
            }

            update_term_meta( $term['term_id'], 'asortyment_id', $imported_category['asortyment_id']);
            update_term_meta( $term['term_id'], 'pod_asortymenty', array_shift($imported_category['pod_asortymenty']));
   
            error_log('created term - ' . $imported_category['asortyment_nazwa'] . ', asortyment_id - ' . $imported_category['asortyment_id']);
            error_log( "created term\n" . print_r($term, true) . "\n");
   
         }
   
      }
   
      // product categories parent-child relationsip
      $args = array(
         'hide_empty' => false,
         'meta_query' => array(
            array(
               'key'         =>  'pod_asortymenty',
               'value'   => array(''),
               'compare' => 'NOT IN'
            )
         ),
      );
      $parent_categories = get_terms( 'product_cat', $args );
      // error_log(print_r($parent_categories, true)); 
   
   
      foreach($parent_categories as $parent_category) {
   
         $child_categories = get_term_meta($parent_category->term_id, 'pod_asortymenty');
         $child_categories = array_shift($child_categories);
         if (!isset($child_categories[0])) {
            $child_categories = array($child_categories);
         }
   
         foreach($child_categories as $child_category) {
   
            $args = array(
               'hide_empty' => false,
               'number' => 1,
               'meta_query' => array(
                  array(
                     'key'         =>  'asortyment_id',
                     'value'   => $child_category['asortyment_id'],
                     'compare' => '=='
                  )
               ),
            );
            $child_category_obj = get_terms( 'product_cat', $args );
   
            if(!empty($child_category_obj)) {
   
               remove_action('edited_product_cat', 'bm_save_taxonomy_custom_meta', 10, 1);
   
               wp_update_term( $child_category_obj[0]->term_id, 'product_cat', array('parent' => $parent_category->term_id));
   
               error_log('updated term parent ' . $child_category_obj[0]->term_id);
   
               add_action('edited_product_cat', 'bm_save_taxonomy_custom_meta', 10, 1);
   
            }
   
         }
   
      }

      return true;
         
   }

   /**
    * Import/update products.
    *
    * Return true if products successfully imported and updated
    *
    * @since 1.0.0
    *
    * @param object $xml simplexml_load_file object
    *
    * @return boolean
    */
   public function import_products($xml) {

      // CREATE UPDATE PRODUCTS
      $imported_products = $xml->wykazy->towary;

      if (empty($imported_products)) return false;
      
   
      // no more than 5000 products 
      if (count($imported_products->towar) >= 5000) {
         $this->admin_notice('Too mutch products', 'error');
         return false;           
      }

      foreach($imported_products->towar as $imported_product) {

         set_time_limit(0);
   
         $imported_product = json_decode(json_encode($imported_product), true);
         // error_log( "imported_product\n" . print_r($imported_product, true) . "\n" );

         if(in_array($imported_product['asortyment_id'], $this->exclude_asortyments)) {
            error_log('ignored product - ' . $imported_product['nazwa'] . ', asortyment_id - ' . $imported_product['asortyment_id']);
            continue;
         }

         $args = array(
            'post_type' => 'product',
            'meta_key' => 'towar_id',
            'posts_per_page' => 1,
            'meta_value' => $imported_product['towar_id'],
            'meta_compare' => 'IN' //'meta_compare' => 'NOT IN'
         );
         $existing_product = wc_get_products($args);

         if (!empty($existing_product)) {

            // update existing product

            $existing_product_id = $existing_product[0]->get_id();

            // check to remove product
            if ($imported_product['do_usuniecia'] == 'N') { // UPDATE

               wp_update_post(
                  array(
                     'ID' => $existing_product_id,
                     'post_title' => $imported_product['nazwa'] ?: '',
                     'post_content' => $imported_product['notatki'] ?: '',
                  )
               );

               $this->set_product_data(
                  array(
                     'id' => $existing_product_id,
                     'towar_id' => $imported_product['towar_id'],
                     'asortyment_id' => $imported_product['asortyment_id'],
                     'plik_zdjecia' => $imported_product['plik_zdjecia'],
                     'cena_detal' => $imported_product['cena_detal'], 
                     'stock' => $imported_product['magazyny']['magazyn']['0']['stan_magazynu'], 
                     'sku' => $imported_product['kod'],
                  )
               );

               error_log('updated product ' . $imported_product['nazwa']);

            } elseif ($imported_product['do_usuniecia'] == 'Y') {
               
               $deleted = $this->delete_product($existing_product_id);

               if (!is_wp_error($deleted)) {
                  error_log('deleted product ' . $imported_product['nazwa']);
               } else {
                  error_log('error deleting product ' . $imported_product['nazwa'] . '\n' . print_r($deleted, true));
               }

            }


            

         } else {

            // create new product

            $new_product_id = wp_insert_post( 
               array(
                  'post_title' => $imported_product['nazwa'],
                  'post_content' => $imported_product['notatki'] ?: '',
                  'post_status' => 'publish',
                  'post_type' => "product",
               ) 
            );

            wp_set_object_terms( $new_product_id, 'simple', 'product_type' ); // simple product

            $this->set_product_data(
               array(
                  'id' => $new_product_id,
                  'towar_id' => $imported_product['towar_id'],
                  'asortyment_id' => $imported_product['asortyment_id'],
                  'plik_zdjecia' => $imported_product['plik_zdjecia'],
                  'cena_detal' => $imported_product['cena_detal'], 
                  'stock' => $imported_product['magazyny']['magazyn']['0']['stan_magazynu'], 
                  'sku' => $imported_product['kod'],
               )
            );

            error_log('created product ' . $imported_product['nazwa']);

         }

      }

      return true;

   }

   /**
    * Set/update product data.
    *
    * Set or update product data and metafields
    *
    * @since 1.0.0
    *
    * @param array $data array of data to set/update
    *
    * @return boolean
    */
    public function set_product_data($data) {

      // $data['id'] - ID of product in WP
      // $data['towar_id'] - ID of product in PCMarket24
      // $data['asortyment_id'] - ID of category to set to product
      // $data['plik_zdjecia'] - image file name to set as featured (plugin https://wordpress.org/plugins/featured-image-by-url/)
      // $data['cena_detal'] - product price


      // $data['id'] - ID of product to set data
      // $data['id'] - ID of product to set data
      // $data['id'] - ID of product to set data
      // $data['id'] - ID of product to set data


      // set towar_id
      if (isset($data['towar_id']) && !is_array($data['towar_id'])) {
         update_post_meta( $data['id'], 'towar_id', $data['towar_id']); // set img_url meta field 
      } else {
         update_post_meta( $data['id'], 'towar_id', '');
      }

      // set product category
      if (isset($data['asortyment_id']) && !is_array($data['asortyment_id'])) {
         $args = array(
            'hide_empty' => false,
            'number' => 1,
            'meta_query' => array(
               array(
                  'key'         =>  'asortyment_id',
                  'value'   => $data['asortyment_id'],
                  'compare' => '=='
               )
            ),
         );
         $product_category = get_terms( 'product_cat', $args );
         error_log( "asortyment_id\n" . print_r($data['asortyment_id'], true) . "\n" );
         error_log( "product_category\n" . print_r($product_category, true) . "\n" );

         // set category
         if(!empty($product_category)) { // if exist
            wp_set_object_terms( $data['id'], $product_category[0]->term_id, 'product_cat' );
         } else { // create new category if not exist
            $term = wp_insert_term( 
               $data['asortyment_id'],
               'product_cat', 
               array(
                  'description' => '',
                  // 'parent'      => 0,
                  'slug'        => '',
               ) 
            );   
            update_term_meta( $term['term_id'], 'asortyment_id', $data['asortyment_id']);
            wp_set_object_terms( $data['id'], $term['term_id'], 'product_cat' );   
            error_log('new not existing category created from product (temporary named by ID) ' . $data['asortyment_id']);

         }           
      }

      // set custom featured image
      if (isset($data['plik_zdjecia']) && !is_array($data['plik_zdjecia'])) {
         $img_url = $this->uploads_dir['baseurl'] . '/xml_import/product-images/' . $data['plik_zdjecia'];
         update_post_meta( $data['id'], '_knawatfibu_url', array('img_url' => $img_url)); // set img_url meta field 
      } else {
         update_post_meta( $data['id'], '_knawatfibu_url', ''); // clear img_url meta field 
      }

      // set price
      if (isset($data['cena_detal']) && !is_array($data['cena_detal'])) {
         update_post_meta( $data['id'], '_price', $data['cena_detal'] ?: '');
         update_post_meta( $data['id'], '_regular_price', $data['cena_detal'] ?: ''); 
      }

      // set stock
      if (isset($data['stock']) && !is_array($data['stock'])) {
         update_post_meta( $data['id'], '_manage_stock', 'yes');
         update_post_meta( $data['id'], '_stock', $data['stock'] ?: '');
      }

      // set sku
      if (isset($data['sku']) && !is_array($data['sku'])) {
         update_post_meta( $data['id'], '_sku', $data['sku'] ?: '');
      }

    }


   /**
   * Method to delete Woo Product
   * 
   * @param int $id the product ID.
   * @param bool $force true to permanently delete product, false to move to trash.
   * @return \WP_Error|boolean
   */
   public function delete_product($id, $force = FALSE) {

      $product = wc_get_product($id);

      if(empty($product))
         return new WP_Error(999, sprintf(__('No %s is associated with #%d', 'woocommerce'), 'product', $id));

      // If we're forcing, then delete permanently.
      if ($force) {
         if ($product->is_type('variable')) {
            foreach ($product->get_children() as $child_id) {
               $child = wc_get_product($child_id);
               $child->delete(true);
            }
         } elseif ($product->is_type('grouped')) {
            foreach ($product->get_children() as $child_id) {
               $child = wc_get_product($child_id);
               $child->set_parent_id(0);
               $child->save();
            }
         }

         $product->delete(true);
         $result = $product->get_id() > 0 ? false : true;

      } else {
         $product->delete();
         $result = 'trash' === $product->get_status();
      }

      if (!$result) {
         return new WP_Error(999, sprintf(__('This %s cannot be deleted', 'woocommerce'), 'product'));
      }

      // Delete parent product transients.
      if ($parent_id = wp_get_post_parent_id($id)) {
         wc_delete_product_transients($parent_id);
      }

      return true;
   }

}



/**
 * Manually import XML from plugin settings page.
 *
 * @since 1.0.0
 *
 */
add_action( 'admin_init', function() {

   if (isset($_FILES['bm_uload_file']) && ($_FILES['bm_uload_file']['error'] == UPLOAD_ERR_OK)) {

      $import = new BM_XML_Products_Import($_FILES['bm_uload_file']['tmp_name']);

   }

   

});







