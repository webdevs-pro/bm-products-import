<?php
/*
Plugin Name: BM Products Importer 
Plugin URI: https://github.com/webdevs-pro/bm-products-import/
Description: This plugin imports products from locals store
Version: 0.1
Author: Magnific Soft
Author URI: https://github.com/webdevs-pro/
Text Domain:  bm-products-import
*/

load_plugin_textdomain( 'bm-products-import', false, basename( dirname( __FILE__ ) ) . '/languages' ); 

define('BM_PLUGIN_BASENAME', plugin_basename(__FILE__));

include( plugin_dir_path( __FILE__ ) . 'admin/admin.php');
include( plugin_dir_path( __FILE__ ) . 'fetch.php');

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
    
   //  $period = get_option('bm_fetch_period');

   //  if (! wp_next_scheduled ( 'bm_fetch_new_products' )) {
   //      if (!isset($period)) {
   //          $period = '10';
   //      }
   //      wp_schedule_event(time(), $period . '_min', 'bm_fetch_new_products');
   //      $timestamp = wp_next_scheduled( 'bm_fetch_new_products' );

   //      error_log($timestamp);
   //  }
}
 
add_action('bm_fetch_new_products', 'bm_check_new_products_fn');
function bm_check_new_products_fn() {
    error_log('DOING CRON');
    cron_fetch_new_products();
}

register_deactivation_hook( __FILE__, 'bm_deactivation' );
function bm_deactivation() {
    wp_clear_scheduled_hook( 'bm_fetch_new_products' );
}
