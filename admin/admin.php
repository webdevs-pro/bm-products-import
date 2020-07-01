<?php 

// plugin settings link in plugins admin page
add_filter('plugin_action_links_' . BM_PLUGIN_BASENAME, function ( $links ) {
	$settings_link = '<a href="' . admin_url( 'options-general.php?page=bm_options' ) . '">' . __('Settings') . '</a>';

	array_unshift( $links, $settings_link );
	return $links;
});

/* ----------------------------------------------------------------------------- */
/* Add Menu Page */
/* ----------------------------------------------------------------------------- */ 


// create custom plugin settings menu
add_action('admin_menu', 'bm_create_menu');

function bm_create_menu() {

	//create new top-level menu
	//add_menu_page('My Cool Plugin Settings', 'Cool Settings', 'administrator', __FILE__, 'bm_settings_page' , plugins_url('/images/icon.png', __FILE__) );
   add_options_page(
      __('RSS Feed Podcast Importer Settings','bm-products-import'), 
      __('BM settings','bm-products-import'), 
      'manage_options', 
      'bm_options', 
      'bm_settings_page'
   );
	//call register settings function
	add_action( 'admin_init', 'register_bm_settings' );
}


function register_bm_settings() {
   //register our settings
   register_setting( 'bm-settings-group', 'bm_fetch_period' );
   register_setting( 'bm-settings-group', 'bm_update_existing' );
   register_setting( 'bm-settings-group', 'bm_category' );

   if ( get_option('bm_fetch_period') === false ) {
      update_option( 'bm_fetch_period', '48' ); // default checked
   } 
   if ( get_option('bm_update_existing') === false ) {
      update_option( 'bm_update_existing', '1' ); // default checked
   }    
   if ( get_option('bm_category') === false ) {
      update_option( 'bm_category', '1' ); // default cat id 1
   } 
}

function bm_settings_page() {
?>
<div class="wrap">
   <h1><?php echo __('BM Products Importer Settings','bm-products-import') ?></h1>

   <div class="card">
      <form method="post" action="options.php">
         <?php settings_fields( 'bm-settings-group' ); ?>
         <?php do_settings_sections( 'bm-settings-group' ); ?>
         <table class="form-table">

            <!-- CHECK PERIOD -->
            <tr valign="top">
               <th scope="row">Sync products every</th>
               <td>
                  <?php
                  $bm_fetch_period = get_option('bm_fetch_period');

                  ?>
                  <select id="bm_fetch_period" name="bm_fetch_period" style="width: 100%;" autocomplete="off">
                     <option value="48" <?php selected($bm_fetch_period, '60'); ?>>1 hour</option>
                     <option value="24" <?php selected($bm_fetch_period, '30'); ?>>30 min</option>
                     <option value="12" <?php selected($bm_fetch_period, '10'); ?>>10 min</option>
                     <option value="manual" <?php selected($bm_fetch_period, 'manual'); ?>>Manual</option>
                  </select>
               </td>
            </tr>




            <!-- FETCH NOW BUTTON -->
            <tr valign="top">
               <th scope="row"></th>
               <td>
                  <button id="bm_fetch_now" class="button button-primary">Sync now</button><span id="bm_spinner" style="float: none;" class="spinner"></span>
               </td>
            </tr>

         </table>

         <div class="products_progress_text"></div>         
         <progress id="products_progress" max="100" value="0" style="width: 100%; display: none;"></progress>

         <div id="bm_ajax_result"></div>

         
         <?php submit_button(); ?>

      </form>
   </div>

</div>
<?php }



function bm_change_cron_after_save( $old_value, $new_value ) {

	if ( $old_value != $new_value ) {
      // This value has been changed. Insert code here.
      error_log($old_value);
      error_log($new_value);
      if ($new_value == '60' || $new_value == '30' || $new_value == '10' ) {
         error_log('changing to ' . $new_value);
         if ( !wp_next_scheduled( 'bm_fetch_new_products' ) ) {
            error_log('not');
            error_log(HOUR_IN_SECONDS * intval($new_value));
            wp_schedule_event(time(), $new_value . '_min', 'bm_fetch_new_products');
         } else {
            error_log('re');
            wp_clear_scheduled_hook( 'bm_fetch_new_products' );
            wp_schedule_event(time(), $new_value . '_min', 'bm_fetch_new_products');

         }
      }
      if ($new_value == 'manual') {
         wp_clear_scheduled_hook( 'bm_fetch_new_products' );
      }

      $timestamp = wp_next_scheduled( 'bm_fetch_new_products' );

      error_log($timestamp);

 

	}

}
add_action( 'update_option_bm_fetch_period', 'bm_change_cron_after_save', 10, 2 );