<?php
/**
* BM Import XML.
*
* Main import class
*
* @since 1.0.0
*/
class BM_XML_Orders_Import {

   public $file;
   public $uploads_dir;
   public $exclude_asortyments;
   
   // public $existing_categories;

   public function __construct($file) {

      error_log('---- import start ----');
      $before = microtime(true);

      $this->uploads_dir = wp_get_upload_dir();

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
   
      $orders_result = $this->import_orders($xml);


      error_log( "orders_result\n" . print_r($orders_result, true) . "\n" );



      
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
    * Import/update orders.
    *
    * Return true if orders successfully imported and updated
    *
    * @since 1.0.0
    *
    * @param object $xml simplexml_load_file object
    *
    * @return boolean
    */
    public function import_orders($xml) {


      $orders = $xml->potwierdzenia;

      if (empty($orders)) {
         error_log('No orders to import');
         return false;
      }

      foreach($orders->potwierdzenie as $order) {

         $order = json_decode(json_encode($order), true);

         error_log( "order\n" . print_r($order, true) . "\n" );

         $order_id = $order['dok_id'];

         $order_status = $order['status_zamowienia'];
         
         // order completed 
         if($order_status == 'zrealizowane') {
            $order = new WC_Order($order_id);
            $order->update_status('completed');
            error_log('ORDER ' . $order_id . ' COMPLETED');
         } else {
            error_log('ORDER ' . $order_id . ' SKIPPED');
         }



      }

      return true;




   }








}