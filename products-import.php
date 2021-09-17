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
   public $exclude_categories;
   public $log;
   
   // public $existing_categories;

   public function __construct($file) {

      $this->log = new WC_Logger();

      $this->bm_log('---- IMPORT START --------------------------------------------------------------------------------------------------------');
      $before = microtime(true);

      $this->uploads_dir = wp_get_upload_dir();

      $this->exclude_asortyments = explode(',', get_option('bm_exclude_asortyments'));
      // error_log( "exclude_asortyments\n" . print_r($this->exclude_asortyments, true) . "\n" );

      $this->exclude_categories = explode(',', get_option('bm_exclude_categories'));
      // error_log( "exclude_categories\n" . print_r($this->exclude_categories, true) . "\n" );


      $this->file = $file;
      $this->import_from_xml_file($file);

      // add_action('bm_set_product_image_by_url', [$this, 'set_product_image_by_url'], 10);

      $after = microtime(true);
      delete_transient( 'wc_products_onsale' );
      $this->bm_log($after-$before);
      $this->bm_log('---- IMPORT END ----------------------------------------------------------------------------------------------------------');
      
   }

   public function bm_log($string) {
      $this->log->info( $string, array( 'source' => 'bm-products-import' ) );
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
   
      $cat_result = $this->import_categories( $xml );
      $tag_result = $this->import_tags( $xml );
      $parameters_result = $this->import_parameters( $xml );

      $products_result = $this->import_products( $xml );

      // error_log( "cat_result\n" . print_r($cat_result, true) . "\n" );
      // error_log( "products_result\n" . print_r($products_result, true) . "\n" );


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
         $this->bm_log($notice_text);
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
         $this->bm_log('No asortyments to import');
         return false;
      }


      // no more than 2000 categories
      if (count($imported_categories->asortyment) >= 2000) {
         $this->admin_notice('Too mutch categories', 'error');
         return false;          
      }

   
      foreach($imported_categories->asortyment as $imported_category) {
   
         $imported_category = json_decode(json_encode($imported_category), true);

         if(in_array($imported_category['asortyment_id'], $this->exclude_asortyments)) {
            $this->bm_log('ignored asortyment - ' . $imported_category['asortyment_nazwa'] . ', asortyment_id - ' . $imported_category['asortyment_id']);
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
      
               $this->bm_log('updated term ' . $imported_category['asortyment_nazwa']);

            } elseif ($imported_category['do_usuniecia'] == 'Y') {

               $deleted = wp_delete_term( $existing_category[0]->term_id, 'product_cat' );
               $this->bm_log('removed term - ' . $imported_category['asortyment_nazwa']);
               $this->bm_log( "removed term result\n" . print_r($deleted, true) . "\n");

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
            $this->bm_log( "wp_insert_term\n" . print_r($term, true) . "\n" );




            
            // if term exist but not has asortyment_id meta set
            if ( is_wp_error($term) && $term->get_error_code() == "term_exists") {
               $term_id = $term->get_error_data();
               $term = array();
               $term['term_id'] = $term_id;
               $this->bm_log('--existing term without asortyment_id - ' . $imported_category['asortyment_nazwa']);
            } else {
               // error_log('--some error, skip term ' . $imported_category['asortyment_nazwa']);
               // error_log('skipped term name ' . $imported_category['asortyment_nazwa']);
               // error_log( "skipped term\n" . print_r($term, true) . "\n" );
               // continue;
            }

            update_term_meta( $term['term_id'], 'asortyment_id', $imported_category['asortyment_id']);
            update_term_meta( $term['term_id'], 'pod_asortymenty', array_shift($imported_category['pod_asortymenty']));
   
            $this->bm_log('created term - ' . $imported_category['asortyment_nazwa'] . ', asortyment_id - ' . $imported_category['asortyment_id']);
            $this->bm_log( "created term\n" . print_r($term, true) . "\n");
   
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
   
               $this->bm_log('updated term parent ' . $child_category_obj[0]->term_id);
   
               add_action('edited_product_cat', 'bm_save_taxonomy_custom_meta', 10, 1);
   
            }
   
         }
   
      }

      return true;
         
   }

   public function import_tags($xml) {

      // CREATE UPDATE PRODUCT TAGS
      $imported_tags = $xml->wykazy->kategorie;

      if (empty($imported_tags)) {
         $this->bm_log('No kategorie to import');
         return false;
      }


      // no more than 2000 tags
      if (count($imported_tags->kategoria) >= 2000) {
         $this->admin_notice('Too mutch categories', 'error');
         return false;          
      }

   
      foreach($imported_tags->kategoria as $imported_tag) {
   
         $imported_tag = json_decode(json_encode($imported_tag), true);

         if(in_array($imported_tag['kategoria_id'], $this->exclude_categories)) {
            $this->bm_log('ignored asortyment - ' . $imported_tag['nazwa_kategorii'] . ', kategoria_id - ' . $imported_tag['kategoria_id']);
            continue;
         }

         $args = array(
            'hide_empty' => false,
            'number' => 1,
            'meta_query' => array(
               array(
                  'key'         =>  'kategoria_id',
                  'value'       =>  $imported_tag['kategoria_id'],
                  'compare'     =>  '=='
               )
            ),
         );
         $existing_tag = get_terms( 'product_tag', $args );
         // error_log( "existing_category\n" . print_r($existing_tag, true) . "\n" );

   
   
         if(!empty($existing_tag)) {

            if ($imported_tag['do_usuniecia'] == 'N') {
   
               // update existing category
               $args = array(
                  'name' => $imported_tag['nazwa_kategorii'],
                  'slug' => '',
               );
               wp_update_term( $existing_tag[0]->term_id, 'product_tag', $args );
               update_term_meta( $existing_tag[0]->term_id, 'kategoria_id', $imported_tag['kategoria_id']);

               $this->bm_log('updated term ' . $imported_tag['nazwa_kategorii']);

            } elseif ($imported_tag['do_usuniecia'] == 'Y') {

               $deleted = wp_delete_term( $existing_tag[0]->term_id, 'product_tag' );
               $this->bm_log('removed term - ' . $imported_tag['nazwa_kategorii']);
               $this->bm_log( "removed term result\n" . print_r($deleted, true) . "\n");


            }
   
         } else {
   
            // create new category
            $term = wp_insert_term( 
               $imported_tag['nazwa_kategorii'],
               'product_tag', 
               array(
                  'description' => '',
                  // 'parent'      => 0,
                  'slug'        => '',
               ) 
            );
            $this->bm_log( "wp_insert_term\n" . print_r($term, true) . "\n" );




            
            // if term exist but not has kategoria_id meta set
            if ( is_wp_error($term) && $term->get_error_code() == "term_exists") {
               $term_id = $term->get_error_data();
               $term = array();
               $term['term_id'] = $term_id;
               $this->bm_log('--existing term without kategoria_id - ' . $imported_tag['nazwa_kategorii']);
            } else {
               // error_log('--some error, skip term ' . $imported_category['asortyment_nazwa']);
               // error_log('skipped term name ' . $imported_category['asortyment_nazwa']);
               // error_log( "skipped term\n" . print_r($term, true) . "\n" );
               // continue;
            }

                // error_log('--some error, skip term ' . $imported_category['asortyment_nazwa']);
               // error_log('skipped term name ' . $imported_category['asortyment_nazwa']);
        
            update_term_meta( $term['term_id'], 'kategoria_id', $imported_tag['kategoria_id']);
   
            $this->bm_log('created term - ' . $imported_tag['nazwa_kategorii'] . ', kategoria_id - ' . $imported_tag['kategoria_id']);
            $this->bm_log( "created term\n" . print_r($term, true) . "\n");
   
         }
   
      }

      return true;
         
   }

   public function import_parameters( $xml ) {
      $imported_parameters = $xml->wykazy->parametry;

      if ( empty( $imported_parameters ) ) return;
      foreach ( $imported_parameters->parametr as $parameter ) {
         if ( $parameter->parametr_id == '4' ) {
            $this->import_marki( $parameter );
         }
         if ( $parameter->parametr_id == '8' ) {
            $this->import_dodatkowa_kategoria( $parameter );
         }
      }
   }

   public function import_marki( $parameter ) {
      $parameter = json_decode( json_encode( $parameter ), true );

      // error_log( "parameter\n" . print_r($parameter, true) . "\n" );

      foreach ( $parameter['listy_wartosci']['lista_wartosci'] as $list_wartosci ) {

         $args = array(
            'hide_empty' => false,
            'number' => 1,
            'meta_query' => array(
               array(
                  'key'         =>  'wartosc',
                  'value'       =>  $list_wartosci['wartosc'],
                  'compare'     =>  '=='
               )
            ),
         );
         $existing_attribute = get_terms( 'pa_marka', $args );
   
         if( ! empty( $existing_attribute ) ) {

            // update existing attribute term
            $args = array(
               'name' => $list_wartosci['tekst'],
               'slug' => '',
            );
            wp_update_term( $existing_attribute[0]->term_id, 'pa_marka', $args );
            update_term_meta( $existing_attribute[0]->term_id, 'wartosc', $list_wartosci['wartosc'] );
   
            $this->bm_log( 'updated attribuet term (marka) ' . $list_wartosci['tekst'] );
   
         } else {
   
            // create new attribute term
            $term = wp_insert_term( 
               $list_wartosci['tekst'],
               'pa_marka', 
               array(
                  'description' => '',
                  'slug'        => '',
               ) 
            );
            $this->bm_log( "wp_insert_term\n" . print_r($term, true) . "\n" );

            // if term exist but not has asortyment_id meta set
            if ( is_wp_error($term) && $term->get_error_code() == "term_exists" ) {
               $term_id = $term->get_error_data();
               $term = array();
               $term['term_id'] = $term_id;
               $this->bm_log( '--existing attribute term (marka) without wartosc - ' . $list_wartosci['tekst'] );
            }

            update_term_meta( $term['term_id'], 'wartosc', $list_wartosci['wartosc'] );
   
            $this->bm_log( 'created attribute term (marka) - ' . $list_wartosci['tekst'] . ', wartosc - ' . $list_wartosci['wartosc'] );

         }
      }
   }

   public function import_dodatkowa_kategoria( $parameter ) {
      $parameter = json_decode( json_encode( $parameter ), true );

      $arr = array();
      // error_log( "parameter\n" . print_r($parameter, true) . "\n" );
      foreach ( $parameter['listy_wartosci']['lista_wartosci'] as $list_wartosci ) {
         $arr[$list_wartosci['wartosc']] = $list_wartosci['tekst'];
      }
      update_option( 'dodatkowe_kategorie', $arr );
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



         $args = array(
            'post_type' => 'product',
            'meta_key' => 'towar_id',
            'posts_per_page' => 1,
            'meta_value' => $imported_product['towar_id'],
            'meta_compare' => 'IN' //'meta_compare' => 'NOT IN'
         );
         $existing_product = wc_get_products($args);

         // // database lookup for $post_id if person already exists, null otherwise
         // global $wpdb;
         // $querystr = "SELECT `pm`.`post_id`
         //       FROM `$wpdb->postmeta` as `pm`
         //          INNER JOIN `$wpdb->posts` as `p`
         //          ON `pm`.`post_id` = `p`.`ID`
         //       WHERE
         //          (`pm`.`meta_key` = 'kontaktperson_id' AND `pm`.`meta_value` = %s AND
         //          `p`.`post_type` = %s)
         //       LIMIT 1;
         // ";
         // $person_post_id = $wpdb->get_var($wpdb->prepare($querystr, $openimmo_data['kontaktperson_id'], apply_filters( 'immomakler_person_post_type', 'immomakler_person', $openimmo_data )));
         

         // set qty label
         if($imported_product['jm_id'] == '1') {
            $qty_label = 'szt';
         } elseif ($imported_product['jm_id'] == '2') {
            $qty_label = 'kg';
         } elseif ($imported_product['jm_id'] == '3') {
            $qty_label = 'opak';
         } elseif ($imported_product['jm_id'] == '4') {
            $qty_label = 'szt.';
         } elseif ($imported_product['jm_id'] == '5') {
            $qty_label = 'gram';
         } else {
            $qty_label = '';
         }

         if (!empty($existing_product)) {

            // update existing product
            $existing_product_id = $existing_product[0]->get_id();



            // check to remove product
            if ($imported_product['do_usuniecia'] == 'N') { // UPDATE

               // ignore by assortyment
               if(in_array($imported_product['asortyment_id'], $this->exclude_asortyments)) {
                  $this->bm_log('ignored product - ' . $imported_product['nazwa'] . ', asortyment_id - ' . $imported_product['asortyment_id']);
                  continue;
               }

               // ignore by category
               if(in_array($imported_product['kategoria_id'], $this->exclude_categories)) {
                  $this->bm_log('ignored product - ' . $imported_product['nazwa'] . ', kategoria_id - ' . $imported_product['kategoria_id']);
                  continue;
               }

               wp_update_post(
                  array(
                     'ID' => $existing_product_id,
                     'post_title' => $imported_product['nazwa'] ?: '',
                     'post_content' => $imported_product['notatki'] ?: '',
                  )
               );

               if ( isset( $imported_product['parametry']['parametr'] ) ) {
                  $parametry = array_column( $imported_product['parametry']['parametr'], 'parametr_wartosc', 'parametr_id' );
               } 

               $this->set_product_data(
                  array(
                     'id' => $existing_product_id,
                     'towar_id' => $imported_product['towar_id'],
                     'asortyment_id' => $imported_product['asortyment_id'],
                     'kategoria_id' => $imported_product['kategoria_id'],
                     'marka' => $parametry[4] ?? 0,
                     'plik_zdjecia' => $imported_product['plik_zdjecia'],
                     'cena_detal' => $imported_product['cena_detal'], 
                     'cena_detal_przed_prom' => $imported_product['cena_detal_przed_prom'], 
                     'stock' => $imported_product['magazyny']['magazyn']['0']['stan_magazynu'], 
                     'sku' => $imported_product['kod'],
                     'min_qty' => $imported_product['opis1'] ?: '',
                     'qty_step' => $imported_product['opis2'] ?: '',
                     'qty_exact' => $imported_product['opis3'] ?: '',
                     'il_kg_litrow' => $imported_product['il_kg_litrow'] ?: '',
                     'qty_label' => $qty_label ?: '',
                     'acf_blokada_zakupu_online' => isset( $parametry[5] ) ? ( $parametry[5] ? 'tak' : 'nie' ) : '',
                     'acf_dodatkowa_kategoria' => isset( $parametry[8] ) ? $parametry[8] : '',
                  )
               );

               $this->bm_log('updated product ('.$imported_product['kod'].') ' . $imported_product['nazwa']);


            } elseif ($imported_product['do_usuniecia'] == 'Y') {
               
               $deleted = $this->delete_product($existing_product_id);

               if (!is_wp_error($deleted)) {
                  // also remove product attachments
                  $attachments = get_attached_media( '', $existing_product_id );
                  foreach ($attachments as $attachment) {
                    wp_delete_attachment( $attachment->ID, 'true' );
                  }
                  $this->bm_log('deleted product ' . $imported_product['nazwa']);
               } else {
                  $this->bm_log('error deleting product ' . $imported_product['nazwa'] . '\n' . print_r($deleted, true));
               }

            }


            

         } else {

            // ignore by assortyment
            if(in_array($imported_product['asortyment_id'], $this->exclude_asortyments)) {
               $this->bm_log('ignored product - ' . $imported_product['nazwa'] . ', asortyment_id - ' . $imported_product['asortyment_id']);
               continue;
            }

            // ignore by category
            if(in_array($imported_product['kategoria_id'], $this->exclude_categories)) {
               $this->bm_log('ignored product - ' . $imported_product['nazwa'] . ', kategoria_id - ' . $imported_product['kategoria_id']);
               continue;
            }

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
            
            if ( isset( $imported_product['parametry']['parametr'] ) ) {
               $parametry = array_column( $imported_product['parametry']['parametr'], 'parametr_wartosc', 'parametr_id' );
            } 

            $this->set_product_data(
               array(
                  'id' => $new_product_id,
                  'towar_id' => $imported_product['towar_id'],
                  'asortyment_id' => $imported_product['asortyment_id'],
                  'kategoria_id' => $imported_product['kategoria_id'],
                  'marka' => $parametry[4] ?? 0,
                  'plik_zdjecia' => $imported_product['plik_zdjecia'],
                  'cena_detal' => $imported_product['cena_detal'], 
                  'cena_detal_przed_prom' => $imported_product['cena_detal_przed_prom'], 
                  'stock' => $imported_product['magazyny']['magazyn']['0']['stan_magazynu'], 
                  'sku' => $imported_product['kod'],
                  'min_qty' => $imported_product['opis1'] ?: '',
                  'qty_step' => $imported_product['opis2'] ?: '',
                  'qty_exact' => $imported_product['opis3'] ?: '',
                  'il_kg_litrow' => $imported_product['il_kg_litrow'] ?: '',
                  'qty_label' => $qty_label ?: '',
                  'acf_blokada_zakupu_online' => $parametry[5] ? 'tak' : 'nie',
                  'acf_dodatkowa_kategoria' => $parametry[8],
               )
            );

            


            $this->bm_log('created product ('.$imported_product['kod'].') - ' . $imported_product['nazwa']);

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


         // get product additional category
         if ( isset( $data['acf_dodatkowa_kategoria'] ) && ! is_array( $data['acf_dodatkowa_kategoria'] ) ) {

            $additional_categories = get_option( 'dodatkowe_kategorie' );
            // ob_start();
            // var_dump( json_encode( $data['acf_dodatkowa_kategoria'] ) );
            // $debug = ob_get_clean();
            // error_log( "debug\n" . print_r( $debug, true ) . "\n" );
            // error_log( "sku\n" . print_r( $data['sku'], true ) . "\n" );
            $additional_term = get_term_by( 'name', $additional_categories[$data['acf_dodatkowa_kategoria']], 'product_cat' );
            
            if ( is_object( $additional_term ) ) {
               $additional_term = $additional_term->term_id;
               wp_set_object_terms( $data['id'], $additional_term, 'product_cat', true );
               update_post_meta( $data['id'], 'acf_dodatkowa_kategoria', $additional_term );       
            }

         }

         // set category
         if(!empty($product_category)) { // if exist
            wp_set_object_terms( $data['id'], [ $product_category[0]->term_id, $additional_term ?: '' ], 'product_cat' );
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
            wp_set_object_terms( $data['id'], [ $term['term_id'], $additional_term ?: '' ], 'product_cat' );   
            error_log('new not existing category created from product (temporary named by ID) ' . $data['asortyment_id']);

         }           
      }




      // set product tag
      if (isset($data['kategoria_id']) && !is_array($data['kategoria_id'])) {

         $args = array(
            'hide_empty' => false,
            'number' => 1,
            'meta_query' => array(
               array(
                  'key' =>  'kategoria_id',
                  'value' => $data['kategoria_id'],
                  'compare' => '=='
               )
            ),
         );
         $product_tag = get_terms( 'product_tag', $args );

         // set tag
         if(!empty($product_tag)) { // if exist
            wp_set_object_terms( $data['id'], $product_tag[0]->term_id, 'product_tag' );
         } else { // create new category if not exist
            $term = wp_insert_term( 
               $data['kategoria_id'],
               'product_tag', 
               array(
                  'description' => '',
                  // 'parent'      => 0,
                  'slug'        => '',
               ) 
            );   
            update_term_meta( $term['term_id'], 'kategoria_id', $data['kategoria_id']);
            wp_set_object_terms( $data['id'], $term['term_id'], 'product_tag' );   
            error_log('new not existing tag created from product (temporary named by ID) ' . $data['kategoria_id']);

         }           
      }


      // set product marka
      if ( isset( $data['marka'] ) && ! is_array( $data['marka'] ) ) {

         $args = array(
            'hide_empty' => false,
            'number' => 1,
            'meta_query' => array(
               array(
                  'key' =>  'wartosc',
                  'value' => $data['marka'],
                  'compare' => '=='
               )
            ),
         );
         $marka = get_terms( 'pa_marka', $args );

         // set marka
         if ( ! empty( $marka ) ) { // if exist
            wp_set_object_terms( $data['id'], $marka[0]->term_id, 'pa_marka' );
         } else { // create new category if not exist
            $term = wp_insert_term( 
               $data['marka'],
               'pa_marka', 
               array(
                  'description' => '',
                  'slug'        => '',
               ) 
            );   
            update_term_meta( $term['term_id'], 'wartosc', $data['marka']);
            wp_set_object_terms( $data['id'], $term['term_id'], 'pa_marka' );   
            error_log('new not existing marka created from product (temporary named by ID) ' . $data['marka']);
         }


         // set product attribute meta
         $attribute_args = array(
            'pa_marka' => array( 
               'name' => 'pa_marka', 
               'value' => $marka[0]->term_id ?? $term['term_id'],
               'is_visible' => '1',
               'is_taxonomy' => '1',
            )
         );
         update_post_meta( $data['id'], '_product_attributes', $attribute_args);    
         
         // set is_purchasable
         update_post_meta( $data['id'], 'acf_blokada_zakupu_online', $data['acf_blokada_zakupu_online'] );   

      }





      // set featured image
      if (isset($data['plik_zdjecia']) && !is_array($data['plik_zdjecia'])) {

         $img_path = $this->uploads_dir['basedir'] . '/xml_import/product-images/' . $data['plik_zdjecia'];

         if ( file_exists( $img_path ) ) {

            // process file
            $img_timestamp = filemtime ( $img_path );
            $pc_market_image = array(
               'img' => $data['plik_zdjecia'],
               'img_timestamp' => $img_timestamp,
            );

            // upload new image if file name or date changed
            if ( get_post_meta( $data['id'], '_pc_market_image', true) != $pc_market_image ) {

                  // error_log('UPDATING IMAGE ------------------------------------------------');
                  update_post_meta( $data['id'], '_pc_market_image', array( 'img' => $data['plik_zdjecia'], 'img_timestamp' => $img_timestamp ) );
                  $image_data = array(
                     'product_id' => $data['id'],
                     'file_name' => $data['plik_zdjecia'],
                     'file_path' => $img_path,
                     'sku' => $data['sku'],
                     // 'file_url' => $this->uploads_dir['baseurl'] . '/xml_import/product-images/' . $data['plik_zdjecia'],
                  );
                  $this->bm_log( '********************** SCHEDULE UPLOADING ('.wc_print_r( $image_data, true ).') ****************************' );
                  as_schedule_single_action( time() + 10, 'bm_set_product_image_by_url', $image_data ); // action scheduller job

            }

         
         } else {
            
            // file not exist
            $image_data = array(
               'product_id' => $data['id'],
               'file_name' => $data['plik_zdjecia'],
               'file_path' => $this->uploads_dir['basedir'] . '/xml_import/product-images/' . $data['plik_zdjecia'],
               'sku' => $data['sku'],
               'file_url' => $this->uploads_dir['baseurl'] . '/xml_import/product-images/' . $data['plik_zdjecia'],
            );

            $this->bm_log( '********************** IMAGE FILE NOT EXIST ('.wc_print_r( $image_data, true ).') ****************************' );
         }









      } else {
         // update_post_meta( $data['id'], '_knawatfibu_url', ''); // clear img_url meta field 
         update_post_meta( $data['id'], '_pc_market_image', array() );
      }





      // set price
      if ( isset( $data['cena_detal'] ) && ! is_array( $data['cena_detal'] ) ) {
         if ( $data['cena_detal_przed_prom'] ) { // if sale
            update_post_meta( $data['id'], '_price', $data['cena_detal'] ?: '');
            update_post_meta( $data['id'], '_regular_price', $data['cena_detal_przed_prom'] ?: ''); 
            update_post_meta( $data['id'], '_sale_price', $data['cena_detal'] ?: ''); 
         } else { // normal
            update_post_meta( $data['id'], '_price', $data['cena_detal'] ?: '');
            update_post_meta( $data['id'], '_regular_price', $data['cena_detal'] ?: ''); 
            update_post_meta( $data['id'], '_sale_price', '');
         }
      }

      // set stock
      if (isset($data['stock']) && !is_array($data['stock'])) {

         update_post_meta( $data['id'], '_manage_stock', 'yes');
         update_post_meta( $data['id'], '_stock', $data['stock'] ?: '');

         if ($data['stock'] == "0") {
            update_post_meta( $data['id'], '_stock_status', 'outofstock' );
            wp_set_post_terms( $data['id'], 'outofstock', 'product_visibility', true );
         } else {
            update_post_meta( $data['id'], '_stock_status', 'instock' );   
            wp_remove_object_terms( $data['id'], 'outofstock', 'product_visibility' );      
         }

      }

      // set sku
      if (isset($data['sku']) && !is_array($data['sku'])) {
         update_post_meta( $data['id'], '_sku', $data['sku'] ?: '');
      }


      // set min qty
      if (isset($data['min_qty']) && !is_array($data['min_qty'])) {
         update_post_meta( $data['id'], '_alg_wc_pq_min', $data['min_qty']);
      }
      // set qty step
      if (isset($data['qty_step']) && !is_array($data['qty_step'])) {
         update_post_meta( $data['id'], '_alg_wc_pq_step', $data['qty_step']);
      }
      // set qty step
      if (isset($data['qty_exact']) && !is_array($data['qty_exact'])) {
         update_post_meta( $data['id'], '_alg_wc_pq_exact_qty_allowed', $data['qty_exact']);
      }      
      // set weight
      if (isset($data['il_kg_litrow']) && !is_array($data['il_kg_litrow'])) {
         update_post_meta( $data['id'], '_weight', $data['il_kg_litrow']);
      }      
      // set qty labels
      if (isset($data['qty_label']) && !is_array($data['qty_label'])) {
         update_post_meta( $data['id'], '_alg_wc_pq_qty_price_by_qty_unit_label_template_singular', $data['qty_label']);
         update_post_meta( $data['id'], '_alg_wc_pq_qty_price_by_qty_unit_label_template_plural', $data['qty_label']);
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
add_action( 'init', function() {

   if (isset($_FILES['bm_uload_file']) && ($_FILES['bm_uload_file']['error'] == UPLOAD_ERR_OK)) {

      // $attributes = wc_get_attribute_taxonomies();
      // // error_log( "attributes\n" . print_r($attributes, true) . "\n" );
      // $terms = get_terms(array(
      //     'taxonomy' => 'pa_marka',
      //     'hide_empty' => false,
      // ));
      // // error_log( "terms\n" . print_r($terms, true) . "\n" );

      $import = new BM_XML_Products_Import($_FILES['bm_uload_file']['tmp_name']);

   }

   

});








