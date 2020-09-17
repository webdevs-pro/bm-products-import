<?php
/*
Plugin Name: BM Products Importer 
Plugin URI: https://github.com/webdevs-pro/bm-products-import/
Description: This plugin imports products from locals store
Version: 0.6
Author: Magnific Soft
Author URI: https://github.com/webdevs-pro/
Text Domain:  bm-products-import
*/

load_plugin_textdomain( 'bm-products-import', false, basename( dirname( __FILE__ ) ) . '/languages' ); 

define('BM_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('BM_PLUGIN_DIR', dirname( __FILE__ ));


include( plugin_dir_path( __FILE__ ) . 'admin/admin.php');
include( plugin_dir_path( __FILE__ ) . 'files.php');
include( plugin_dir_path( __FILE__ ) . 'import.php');
include( plugin_dir_path( __FILE__ ) . 'order.php');



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
 
add_action('bm_import_new_products', 'bm_check_new_products_fn');
function bm_check_new_products_fn() {
    error_log('DOING CRON');
    bm_check_files_and_import();
    
}

register_deactivation_hook( __FILE__, 'bm_deactivation' );
function bm_deactivation() {
    wp_clear_scheduled_hook( 'bm_import_new_products' );
} 



// RAW PHP CODE PROTECTION 
add_action('init', function() {
	if( isset( $_GET['new_order']) ) {
        $args = array(
            'dok_id' => '29',
            'numer_dokumentu' => '29',
            'nr_seryjny_sklepu' => 'M33020CA93',
            'data_zamowienia' => '2020-09-12',
            'data_realizacji_zamowienia' => '2020-09-12',
            'wymagac_pelnej_realizacji' => 'N',
            'do_usuniecia' => 'N',
            'magazyn_id' => '1',
            'uzytkownik_id' => '1',
            'poziom_cen' => '1',
            'platnosc_id' => '1',
            'dokument_finansowy' => 'Faktura sprzedaży',
            'kontrahent_id' => '1',
            'czy_nowy_kontrahent' => 'N',
            'pozycje_zamowienia' => array(
                array(
                    'numer_pozycji' => '1',
                    'towar_id' => '1341',
                    'ilosc' => '1',
                    'wartosc_brutto' => '',
                ),
                array(
                    'numer_pozycji' => '2',
                    'towar_id' => '1342',
                    'ilosc' => '1',
                    'wartosc_brutto' => '',
                ),
            ),



        );
		$export = new BM_XML_export_order($args);
	}
});


add_action( 'woocommerce_thankyou', 'new_order_custom_email_notification', 10, 1 );


function new_order_custom_email_notification( $order_id ) {

    if ( ! $order_id ) return;

    if( ! get_post_meta( $order_id, '_thankyou_action_done', true ) ) {

        $order = wc_get_order( $order_id );

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
            'kontrahent_id' => '1',
            'czy_nowy_kontrahent' => 'N',
            'pozycje_zamowienia' => array(),
        );


        $index = 0;
        foreach ( $order->get_items() as $item_id => $item ) {

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
                'wartosc_brutto' => '',
            );

            $index++;

            

        }

        error_log( "args\n" . print_r($args, true) . "\n" );

        update_post_meta( $order_id, '_thankyou_action_done', true );

        $export = new BM_XML_export_order($args);

    }
}