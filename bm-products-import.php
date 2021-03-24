<?php
/*
Plugin Name: BM Products Importer 
Plugin URI: https://github.com/webdevs-pro/bm-products-import/
Description: This plugin imports products from locals store
Version: 1.8.3
Author: Magnific Soft
Author URI: https://github.com/webdevs-pro/
Text Domain:  bm-products-import
*/

load_plugin_textdomain( 'bm-products-import', false, basename( dirname( __FILE__ ) ) . '/languages' ); 

define('BM_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('BM_PLUGIN_DIR', dirname( __FILE__ ));


include( plugin_dir_path( __FILE__ ) . 'admin/admin.php');
include( plugin_dir_path( __FILE__ ) . 'files.php');
include( plugin_dir_path( __FILE__ ) . 'products-import.php');
include( plugin_dir_path( __FILE__ ) . 'orders-import.php');
include( plugin_dir_path( __FILE__ ) . 'orders-export.php');



// ADMIN JS SCRIPT
add_action('admin_enqueue_scripts', 'bm_admin_scripts');
function bm_admin_scripts( $hook ) {

    if( $hook == 'settings_page_bm_options' ) {

        wp_register_script('bm-products-import', plugin_dir_url( __FILE__ ) . '/bm-products-import.js', array('jquery'));
        wp_enqueue_script( 'bm-products-import');

    }

}





// plugin updates
require 'plugin-update-checker/plugin-update-checker.php';
$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
	'https://github.com/webdevs-pro/bm-products-import',
	__FILE__,
	'bm-products-import'
);





// регистрируем 5минутный интервал CRON
add_filter( 'cron_schedules', 'cron_add_ten_min' );
function cron_add_ten_min( $schedules ) {
	$schedules['10_min'] = array(
		'interval' => 60 * 10,
		'display' => 'Every 10 min'
    );

	return $schedules;
}


register_activation_hook(__FILE__, 'bm_activation');
function bm_activation() {
    
   //  $period = get_option('bm_import_period');

   //  if (! wp_next_scheduled ( 'bm_import_new_products' )) {
   //      if (!isset($period)) {
   //          $period = '10';
   //      }
   //      wp_schedule_event(time(), $period . '_min', 'bm_import_new_products');
   //      $timestamp = wp_next_scheduled( 'bm_import_new_products' );

   //      error_log($timestamp);
   //  }
}

$period = intval( get_option('bm_import_period') );
if ( ! wp_next_scheduled ( 'bm_import_new_products' ) && intval( $period ) != 0 ) {
    wp_schedule_event( time(), $period . '_min', 'bm_import_new_products' );
    // $timestamp = wp_next_scheduled( 'bm_import_new_products' );
    error_log( "ALARM !!!!!!!!!!!!!!!!!!!!  bm_import_new_products rescheduled !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!\n" );
}



 
add_action('bm_import_new_products', 'bm_check_new_products_fn');
function bm_check_new_products_fn() {
    error_log('DOING CRON');
    bm_check_files_and_import();
    
}

register_deactivation_hook( __FILE__, 'bm_deactivation' );
function bm_deactivation() {
    // wp_clear_scheduled_hook( 'bm_import_new_products' );
} 







add_action( 'woocommerce_created_customer', 'action_woocommerce_created_customer', 10, 3 ); 
function action_woocommerce_created_customer( $customer_id, $new_customer_data, $password_generated ) { 

}; 



// add_action( 'woocommerce_thankyou', 'new_order_export_xml', 10, 1 );
add_action( 'woocommerce_payment_complete', 'new_order_export_xml', 10 );
function new_order_export_xml( $order_id ) {

    if ( ! $order_id ) return;

    if( ! get_post_meta( $order_id, '_thankyou_action_done', true ) ) {

        // // Only for logged in users
        // if ( $order_id && is_user_logged_in() ) {
        //     $udata = wp_get_current_user();
        //     $registered = new \DateTime($udata->user_registered);
        //     $current = new \DateTime();

        //     // get seconds elapsed after user registration
        //     $interval = $current->format('U') - $registered->format('U');

        //     if ($interval <= 30) {
        //         // echo 'tracking code';
        //     }
        // }


        $order = wc_get_order( $order_id );

        // error_log( "woocommerce_payment_complete new order \n" . print_r($order, true) . "\n" );


        $args = array(
            'dok_id' => $order_id,
            'numer_dokumentu' => $order_id,
            'nr_seryjny_sklepu' => 'M33020CA93',
            'data_zamowienia' => date('Y-m-d'),
            'data_realizacji_zamowienia' => date('Y-m-d'),
            'wymagac_pelnej_realizacji' => 'N',
            'do_usuniecia' => 'N',
            'magazyn_id' => '1',
            'uzytkownik_id' => '1',
            'poziom_cen' => '1',
            'platnosc_id' => '1',
            'dokument_finansowy' => 'Faktura sprzedaży',
            'pozycje_zamowienia' => array(),
        );

        // registered customer
        if ($order->get_customer_id() != 0) {

            // new customer
            $customer_orders = get_posts( array(
                'numberposts' => 1,
                'meta_key'    => '_customer_user',
                'meta_value'  => $order->get_customer_id(),
                'post_type'   => wc_get_order_types(),
                'post_status' => array_keys( wc_get_order_statuses() ),
            ) );

            // new customer registered
            if($customer_orders[0]->ID == $order_id) {

                $args['czy_nowy_kontrahent'] = 'Y';
                $args['kontrahent_id'] = '';
                $args['nowy_kontrahent']['kod'] = $order->get_customer_id();

            // existing customer
            } else {

                $args['czy_nowy_kontrahent'] = 'N';
                $args['kontrahent_id'] = $order->get_customer_id();
                $args['nowy_kontrahent']['kod'] = $order->get_customer_id();

            }

        // not registered customer     
        } else {
            $args['czy_nowy_kontrahent'] = 'NZ';

        }

        $order_data = $order->get_data();
        $billing_first_name = $order_data['billing']['first_name'];
        $billing_last_name = $order_data['billing']['last_name'];
        $billing_city = $order_data['billing']['city'];
        $billing_postcode = $order_data['billing']['postcode'];
        $billing_address_1 = $order_data['billing']['address_1'];
        $billing_email = $order_data['billing']['email'];
        $billing_phone = $order_data['billing']['phone'];


        $shipping_first_name = $order_data['shipping']['first_name'];
        $shipping_last_name = $order_data['shipping']['last_name'];
        $shipping_city = $order_data['shipping']['city'];
        $shipping_postcode = $order_data['shipping']['postcode'];
        $shipping_address_1 = $order_data['shipping']['address_1']; 

        $customer_note = $order_data['customer_note'];
        
        if ($billing_first_name == $shipping_first_name &&
        $billing_last_name == $shipping_last_name &&
        $billing_city == $shipping_city &&
        $billing_postcode == $shipping_postcode &&
        $billing_address_1 == $shipping_address_1) {
            $args['nowy_kontrahent']['czy_odbiorca'] = 'Y';
        } else {
            $args['nowy_kontrahent']['czy_odbiorca'] = 'N';
        }
        
        
        
        

        $args['nowy_kontrahent'] = array(
            'nazwa' => $billing_first_name . ' ' . $billing_last_name,
            'miejscowosc' => $billing_city,
            'kod_pocztowy' => $billing_postcode,
            'nazwa_ulicy' => $billing_address_1,
            'email' => $billing_email,
            'telefon' => $billing_phone,
        );

        $args['dane_dostawy'] = array(
            'nazwa' => $shipping_first_name . ' ' . $shipping_last_name,
            'miejscowosc' => $shipping_city,
            'kod_pocztowy' => $shipping_postcode,
            'nazwa_ulicy' => $shipping_address_1,
        );

        $args['opis_zamowienia']['komentarz'] = $customer_note;

        $index = 0;
        foreach ( $order->get_items() as $item_id => $item ) {

            // error_log( "item\n" . print_r($item, true) . "\n" );

            // $product_id = $item->get_product_id();
            // $variation_id = $item->get_variation_id();
            // $product = $item->get_product();
            // $name = $item->get_name();
            // $quantity = $item->get_quantity();
            // $subtotal = $item->get_subtotal();
            // $total = $item->get_total();
            // $tax = $item->get_subtotal_tax();
            // $taxclass = $item->get_tax_class();
            // $taxstat = $item->get_tax_status();
            // $allmeta = $item->get_meta_data();
            // $somemeta = $item->get_meta( 'towar_id', true );
            // $type = $item->get_type();


            $args['pozycje_zamowienia'][$index] = array(
                'numer_pozycji' => $index + 1,
                'towar_id' => get_post_meta($item->get_product_id(), 'towar_id', true ),
                'ilosc' => $item->get_quantity(),
                'wartosc_brutto' => get_post_meta( $item->get_product_id(), '_regular_price', true),
                'cena_po_rabacie' => get_post_meta( $item->get_product_id(), '_sale_price', true),
            );

            $index++;

            

        }

        error_log( "ORDER EXPORT args\n" . print_r($args, true) . "\n" );

        update_post_meta( $order_id, '_thankyou_action_done', true );

        $export = new BM_XML_Export_Order($args);

    }
}



      
// IMAGES UPLOAD SCHEDULED ACTION
add_action( 'bm_set_product_image_by_url', 'bm_set_product_image_by_url', 10, 4 );
function bm_set_product_image_by_url( $product_id, $img_name, $img_path, $sku ) {
    

    $log = new WC_Logger();
    

    // remove current thumbnail
    $current_thumbnail = get_post_thumbnail_id( $product_id );
    if ( $current_thumbnail ) {
        wp_delete_attachment( $current_thumbnail, 'true' );
        $log->info( 'Removed old thumbnail for: (' . wc_print_r( $sku, true ) . ') ' . get_the_title( $product_id ), array( 'source' => 'bm-products-import-images' ) );
    }




    // $wp_filetype = wp_check_filetype( $img_name );
    // $log->info( 'img_name:' . wc_print_r( $img_name, true ), array( 'source' => 'bm-products-import-images' ) );
    // $log->info( 'wp_filetype:' . wc_print_r( $wp_filetype, true ), array( 'source' => 'bm-products-import-images' ) );



    
    // $log->info( 'wp_get_current_user' . wc_print_r( wp_get_current_user(), true ), array( 'source' => 'bm-products-import-images' ) );




    
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $image_contents = file_get_contents( $img_path );
    $upload = wp_upload_bits( $img_name, null, $image_contents );
    // $log->info( 'upload 1:' . wc_print_r( $upload, true ), array( 'source' => 'bm-products-import-images' ) );
 
    $wp_filetype = wp_check_filetype( basename( $upload['file'] ), null );
 
    $upload = apply_filters( 'wp_handle_upload', array(
       'file' => $upload['file'],
       'url'  => $upload['url'],
       'type' => $wp_filetype['type']
    ), 'sideload' );
    // $log->info( 'upload 2:' . wc_print_r( $upload, true ), array( 'source' => 'bm-products-import-images' ) );
 
    $attachment = array(
       'post_mime_type'	=> $upload['type'],
       'post_title'		=> get_the_title( $product_id ),
       'post_content'		=> '',
       'post_status'		=> 'inherit'
    );
 
    // generate attachment metadata
    $attach_id = wp_insert_attachment( $attachment, $upload['file'], $product_id );
    $attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
    wp_update_attachment_metadata( $attach_id, $attach_data );

    // set product featured image
    set_post_thumbnail( $product_id, $attach_id );
    
    // set WP Media Folder taxonomy term for uploaded image
    $wpmf_term = get_option( 'bm_wpmf_category' );
    if ( $wpmf_term ) {
       wp_set_object_terms( $attach_id, intval( $wpmf_term ), WPMF_TAXO, false );
    }

    $log_arr = array(
        // 'img_path' => $img_path,
        'upload' => $upload['url'],
        'sku' => $sku,
        // 'attachment' => $attachment,
        // 'attach_data' => $attach_data,
    );

    $log->info( 'Uploaded thumbnail for: (' . wc_print_r( $sku, true ) . ') ' . get_the_title( $product_id ) . ' ' . wc_print_r( $log_arr, true ), array( 'source' => 'bm-products-import-images' ) );
    
    
}

function custom_action_scheduler_failed_action( $action_id, $timeout ){ 

    $log = new WC_Logger();
    
    $arr = array(
        'action_id' => $action_id,
        'timeout' => $timeout,
    );
    
    if ( class_exists( 'ActionScheduler' ) ) {
        $arr['action'] = ActionScheduler::store()->fetch_action( $action_id );
    }
    $log->info( '******************* ERROR *******************:' . wc_print_r( $arr, true ), array( 'source' => 'bm-products-import' ) );

} 
 
 //add the action 
 add_action('action_scheduler_failed_action', 'custom_action_scheduler_failed_action', 10, 2);
 

// if ( class_exists( 'ActionScheduler' ) ) {
//     error_log( "ActionScheduler exist\n" );
// } else {
//     error_log( "ActionScheduler not exist\n" );
// }