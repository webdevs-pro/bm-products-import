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
      __('BM Products Importer Settings','bm-products-import'), 
      __('BM Importer','bm-products-import'), 
      'manage_options', 
      'bm_options', 
      'bm_settings_page'
   );
	//call register settings function
	add_action( 'admin_init', 'register_bm_settings' );
}


function register_bm_settings() {
   //register our settings
   register_setting( 'bm-settings-group', 'bm_folder' );
   register_setting( 'bm-settings-group', 'bm_import_period' );

   if ( get_option('bm_import_period') === false ) {
      update_option( 'bm_import_period', '48' ); // default checked
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


            <!-- RSS URL FIELD -->
            <tr valign="top">
               <th scope="row">Folder with XML</th>
               <td>
                  <input id="bm_import_url" type="text" name="bm_folder" value="<?php echo esc_attr( get_option('bm_folder') ); ?>" style="width: 100%;" autocomplete="off" />
               </td>
            </tr>


            <!-- CHECK PERIOD -->
            <tr valign="top">
               <th scope="row">Sync products every</th>
               <td>
                  <?php
                  $bm_import_period = get_option('bm_import_period');

                  ?>
                  <select id="bm_import_period" name="bm_import_period" style="width: 100%;" autocomplete="off">
                     <option value="60" <?php selected($bm_import_period, '60'); ?>>1 hour</option>
                     <option value="30" <?php selected($bm_import_period, '30'); ?>>30 min</option>
                     <option value="10" <?php selected($bm_import_period, '10'); ?>>10 min</option>
                     <option value="manual" <?php selected($bm_import_period, 'manual'); ?>>Manual</option>
                  </select>
               </td>
            </tr>

            


         </table>

         <?php submit_button(); ?>

      </form>
   </div>


   <div class="card">
      <form method="post" action="" enctype="multipart/form-data">

         <table class="form-table">


            <!-- FETCH NOW BUTTON -->
            <tr valign="top">
               <th scope="row">Upload XML</th>
               <td>
                  <input type="file" id="bm_uload_file" name="bm_uload_file">
               </td>
            </tr>


         </table>

         
         <input type="submit" class="button button-primary" value="Import">

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
         if ( !wp_next_scheduled( 'bm_import_new_products' ) ) {
            error_log('not');
            error_log(HOUR_IN_SECONDS * intval($new_value));
            wp_schedule_event(time(), $new_value . '_min', 'bm_import_new_products');
         } else {
            error_log('re');
            wp_clear_scheduled_hook( 'bm_import_new_products' );
            wp_schedule_event(time(), $new_value . '_min', 'bm_import_new_products');

         }
      }
      if ($new_value == 'manual') {
         wp_clear_scheduled_hook( 'bm_import_new_products' );
      }

      $timestamp = wp_next_scheduled( 'bm_import_new_products' );

      error_log($timestamp);

	}

}
add_action( 'update_option_bm_import_period', 'bm_change_cron_after_save', 10, 2 );







// PRODUCT CATEGORY CUSTOM FIELD 
//Product Cat Create page
add_action('product_cat_add_form_fields', 'bm_taxonomy_add_new_meta_field', 10, 1);
add_action('product_cat_edit_form_fields', 'bm_taxonomy_edit_meta_field', 10, 1);
function bm_taxonomy_add_new_meta_field() {
    ?>   
    <div class="form-field">
        <label for="asortyment_id">Asortyment ID</label>
        <input type="text" name="asortyment_id" id="asortyment_id">
        <p class="description">Do not edit this field!!!</p>
    </div>
    <?php
}

//Product Cat Edit page
add_action('edited_product_cat', 'bm_save_taxonomy_custom_meta', 10, 1);
add_action('create_product_cat', 'bm_save_taxonomy_custom_meta', 10, 1);
function bm_taxonomy_edit_meta_field($term) {
    //getting term ID
    $term_id = $term->term_id;
    // retrieve the existing value(s) for this meta field.
    $asortyment_id = get_term_meta($term_id, 'asortyment_id', true);
    ?>
    <tr class="form-field">
        <th scope="row" valign="top"><label for="asortyment_id">Asortyment ID</label></th>
        <td>
            <input type="text" name="asortyment_id" id="asortyment_id" value="<?php echo esc_attr($asortyment_id); ?>">
            <p class="description">Do not edit this field!!!</p>
        </td>
    </tr>
    <?php
}

// Save extra taxonomy fields callback function.
function bm_save_taxonomy_custom_meta($term_id) {
    $bm_meta_title = filter_input(INPUT_POST, 'asortyment_id');
    update_term_meta($term_id, 'asortyment_id', $bm_meta_title);
}

// Displaying Additional Columns
add_filter( 'manage_edit-product_cat_columns', 'bm_customFieldsListTitle' ); //Register Function
add_action( 'manage_product_cat_custom_column', 'bm_customFieldsListDisplay' , 10, 3); //Populating the Columns
function bm_customFieldsListTitle( $columns ) {
    $columns['asortyment_id'] = 'Asortyment ID';
    return $columns;
}
function bm_customFieldsListDisplay( $columns, $column, $id ) {
    if ( 'asortyment_id' == $column ) {
        $columns = esc_html( get_term_meta($id, 'asortyment_id', true) );
    }
    return $columns;
}


// ADD/EDIT PRODUCT CATEGORY BANNER
add_action('product_cat_pre_add_form', 'product_cat_banner', 10, 3);
add_action('product_cat_term_edit_form_top', 'product_cat_banner', 10, 1);
function product_cat_banner() {
   ?>
      <style>
         .do_not_edit_category {
            /* max-width: 500px; */
            padding: 30px;
            background-color: #FC4E00;
            color: #fff;
            font-size: 20px;
            line-height: 1.4em;
         }

      </style>
      <div class="do_not_edit_category">
         Please do not create or edit product categories. <br>
         Product categories syncronized with offline shop.<br>
         <br>
         YOU CAN EDIT ONLY CATEGORY IMAGE.
      </div>
   <?php
}





// PRODUCT CUSTOM FIELD
function bm_add_metabox() {
   $screens = ['product'];
   foreach ($screens as $screen) {
       add_meta_box(
           'bm_product_metabox',           // Unique ID
           'Towar ID',  // Box title
           'bm_product_metabox_html',  // Content callback, must be of type callable
           $screen,                  // Post type
           'side'                    // side
       );
   }
}
add_action('add_meta_boxes', 'bm_add_metabox');

// display product fields
function bm_product_metabox_html($post) {
   $towar_id = get_post_meta($post->ID, 'towar_id', true);
   ?>
   <!-- <label for="towar_id"><div>Towar ID</div></label> -->
   <input type="text" name="towar_id" value="<?php echo $towar_id; ?>" style="width: 100%;"/>
   <?php
}

// save product fields
function bm_save_postdata($post_id) {
   if (array_key_exists('towar_id', $_POST)) {
       update_post_meta(
           $post_id,
           'towar_id',
           $_POST['towar_id']
       );
   }
}
add_action('save_post', 'bm_save_postdata');

