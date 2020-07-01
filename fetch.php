<?php    
add_action('wp_ajax_fetch_rss', 'fetch_rss_ajax');
function fetch_rss_ajax() {

   $return = get_rss_xml(array(
      'url' => $_POST['url'],
      'cat' => $_POST['cat'],
   ));

   echo $return;

   wp_die();

}


// step 1
function get_rss_xml($args) {

   $file_headers = @get_headers($args['url']);

   if( !$file_headers || substr($file_headers[0], 9, 3) != "200") {
      return 'Status: ERROR (File not exist)';
   } else {

      $content = file_get_contents($args['url']); 

      try {
         $rss = new SimpleXmlElement($content); 
         if(!isset($rss->channel->item)) {
            return false;
         }
      } 
      catch(Exception $e){ 
         return 'Status: ERROR (Invalid RSS feed)';
         wp_die();
      }  

   }
      // create/update posts
      $ns = $rss->getNamespaces(true);

      $max_items = 500;
   
      $products = array();
   
      $items = $rss->channel->item;
      $count = count($items);

      if($count >= $max_items ) return 'Status: ERROR (Too much products)';

      $response_arr = array();

      for ($i = $count-1; $i >= 0; $i--) {

         $item = $items[$i];
      
         $itunes_data = $item->children($ns["itunes"]);
   
         // RSS item array
         $products[$i]['title'] = isset(($item->title)) ? ((string) $item->title) : '';
         $products[$i]['description'] = isset(($itunes_data->summary)) ? ((string) $itunes_data->summary) : '';
         $products[$i]['content'] = isset(($item->description)) ? ((string) $item->description) : '';
         $products[$i]['author'] = isset(($itunes_data->author)) ? ((string) $itunes_data->author) : '';
         $products[$i]['image'] =  isset(($itunes_data->image)) ? ((string) $itunes_data->image->attributes()->href) : '';
         $products[$i]['audio'] = isset(($item->enclosure)) ? ((string) $item->enclosure->attributes()->url) : '';
         $products[$i]['date'] = isset(($item->pubDate)) ? ((string) $item->pubDate) : '';
         $products[$i]['season'] = isset(($itunes_data->season)) ? ((string) $itunes_data->season) : '';
         $products[$i]['episode'] = isset(($itunes_data->episode)) ? ((string) $itunes_data->episode) : '';
         $products[$i]['buzzsprout_guid'] = isset(($item->guid)) ? ((string) $item->guid) : '';
   
   
         // post array
         $post_content = '
            <!-- wp:audio -->
            <figure class="wp-block-audio"><audio controls src="' . $products[$i]['audio'] . '"></audio></figure>
            <!-- /wp:audio -->      
         ';
         $post_content .= $products[$i]['content'];
   
         $date = DateTime::createFromFormat('D, d M Y H:i:s P', $products[$i]['date']);
   
         $response_arr[$i] = array(
            'post_date'     => $date->format('Y-m-d H:i:s'),
            'post_title'    => wp_strip_all_tags( $products[$i]['title'] ),
            'post_content'  => $post_content,
            'post_excerpt'  => wp_strip_all_tags( $products[$i]['description'] ),
            'post_status'   => 'publish',
            'post_author'   => get_current_user_id(),
            'post_category' => array($_POST['cat'],),
            'meta_input'    => [
               'bm_author'     => $products[$i]['author'],
               'bm_image'           => $products[$i]['image'],
               'bm_audio'           => $products[$i]['audio'],
               'bm_date'            => $products[$i]['date'],
               'bm_season'          => $products[$i]['season'],
               'bm_episode'         => $products[$i]['episode'],
               'bm_guid' => $products[$i]['buzzsprout_guid'],
            ]
         );

      }

      // reverse array
      $k = array_keys($response_arr);
      $v = array_values($response_arr);
      $rv = array_reverse($v);
      $response_arr = array_combine($k, $rv);


      return json_encode($response_arr);
}

add_action('wp_ajax_fetch_episode', 'fetch_episode_ajax');
function fetch_episode_ajax() {

   $data = json_decode( html_entity_decode( stripslashes ($_POST['data'] ) ), true );

      // check is post with GUID exist
      $query_args = array(
         'meta_key' => 'bm_guid',
         'meta_value' => $data['meta_input']['bm_guid'],
         'post_type' => 'post',
      );
      $posts = get_posts($query_args);

      if(array_key_exists('0', $posts)) {

         // post with same GUID exist
         if ($_POST['mode'] == 'update') {

            // update existing post
            $data['ID'] = $posts[0]->ID;

            $current_image = get_post_meta($data['ID'], 'bm_image', true);

            wp_update_post( wp_slash($data) );

            $response = 'Episode exist, updated';

            // update featured image
            if($current_image != $data['meta_input']['bm_image']) {
               error_log( 'updating image' );
               $old_thumbnail_id = get_post_thumbnail_id(  $data['ID'] );
               wp_delete_attachment( $old_thumbnail_id);
               bm_generate_featured_image($data['meta_input']['bm_image'],  $data['ID']);
               $response .= '<br>Image updated';
            }

         } else {

            $response = 'Episode exist, skipped';

         }

      } else {

         // create new post
         $post_id = wp_insert_post( $data );
         if($data['meta_input']['bm_image'] != "") {
            bm_generate_featured_image($data['meta_input']['bm_image'], $post_id);
         }
         $response = 'New episode added';


      }


      wp_reset_postdata();

      	
   
   echo $response;

   wp_die();

}




function bm_generate_featured_image( $image_url, $post_id  ) {

   $file_headers = @get_headers($image_url);

   if ($file_headers && substr($file_headers[0], 9, 3) == "200") {


      require_once ABSPATH . 'wp-admin/includes/file.php';
      include_once( ABSPATH . 'wp-admin/includes/image.php' );

      // upload image to wordpress
      $image_contents = file_get_contents($image_url);

      $upload = wp_upload_bits( basename($image_url), null, $image_contents );

      $wp_filetype = wp_check_filetype( basename( $upload['file'] ), null );

      $upload = apply_filters( 'wp_handle_upload', array(
         'file' => $upload['file'],
         'url'  => $upload['url'],
         'type' => $wp_filetype['type']
      ), 'sideload' );

      $attachment = array(
         'post_mime_type'	=> $upload['type'],
         'post_title'		=> get_the_title( $post_id ),
         'post_content'		=> '',
         'post_status'		=> 'inherit'
      );

      $attach_id = wp_insert_attachment( $attachment, $upload['file'], $post_id );
      $attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
      wp_update_attachment_metadata( $attach_id, $attach_data );
      set_post_thumbnail( $post_id, $attach_id );
      
      
   }

}

























function cron_fetch_new_products() {

   $args = array(
      'url' => get_option('bm_feed_url'),
      'mode' => 'new',
      'cat' => get_option('bm_category'),
   );

   error_log('--- RSS Podcast Import ---');

   $file_headers = @get_headers($args['url']);

   if( !$file_headers || substr($file_headers[0], 9, 3) != "200") {
      error_log('Status: ERROR (File not exist)');
      wp_die();
   } else {

      $content = file_get_contents($args['url']); 

      try {
         $rss = new SimpleXmlElement($content); 
         if(!isset($rss->channel->item)) {
            return false;
         }
      } 
      catch(Exception $e){ 
         error_log('Status: ERROR (Invalid RSS feed)');
         wp_die();
      }  

   }

   // create/update posts
   $ns = $rss->getNamespaces(true);

   $max_items = 500;

   $created = 0;

   $products = array();

   $items = $rss->channel->item;
   $count = count($items);

   if($count >= $max_items ) return 'Status: ERROR (Too much products)';

   for ($i = $count-1; $i >= 0; $i--) {

      $item = $items[$i];
      
      $itunes_data = $item->children($ns["itunes"]);

      // RSS item array
      $products[$i]['title'] = isset(($item->title)) ? ((string) $item->title) : '';
      $products[$i]['description'] = isset(($itunes_data->summary)) ? ((string) $itunes_data->summary) : '';
      $products[$i]['content'] = isset(($item->description)) ? ((string) $item->description) : '';
      $products[$i]['author'] = isset(($itunes_data->author)) ? ((string) $itunes_data->author) : '';
      $products[$i]['image'] =  isset(($itunes_data->image)) ? ((string) $itunes_data->image->attributes()->href) : '';
      $products[$i]['audio'] = isset(($item->enclosure)) ? ((string) $item->enclosure->attributes()->url) : '';
      $products[$i]['date'] = isset(($item->pubDate)) ? ((string) $item->pubDate) : '';
      $products[$i]['season'] = isset(($itunes_data->season)) ? ((string) $itunes_data->season) : '';
      $products[$i]['episode'] = isset(($itunes_data->episode)) ? ((string) $itunes_data->episode) : '';
      $products[$i]['buzzsprout_guid'] = isset(($item->guid)) ? ((string) $item->guid) : '';


      // post array
      $post_content = '
         <!-- wp:audio -->
         <figure class="wp-block-audio"><audio controls src="' . $products[$i]['audio'] . '"></audio></figure>
         <!-- /wp:audio -->      
      ';
      $post_content .= $products[$i]['content'];

      $date = DateTime::createFromFormat('D, d M Y H:i:s P', $products[$i]['date']);

      $post_data = array(
         'post_date'     => $date->format('Y-m-d H:i:s'),
         'post_title'    => wp_strip_all_tags( $products[$i]['title'] ),
         'post_content'  => $post_content,
         'post_excerpt'  => wp_strip_all_tags( $products[$i]['description'] ),
         'post_status'   => 'publish',
         'post_author'   => get_current_user_id(),
         'post_category' => array($args['cat'],),
         'meta_input'    => [
            'bm_author'     => $products[$i]['author'],
            'bm_image'           => $products[$i]['image'],
            'bm_audio'           => $products[$i]['audio'],
            'bm_date'            => $products[$i]['date'],
            'bm_season'          => $products[$i]['season'],
            'bm_episode'         => $products[$i]['episode'],
            'bm_guid' => $products[$i]['buzzsprout_guid'],
         ]
      );

      // error_log(print_r($post_data,true));
      
      // check is post with GUID exist
      $query_args = array(
         'meta_key' => 'bm_guid',
         'meta_value' => $products[$i]['buzzsprout_guid'],
         'post_type' => 'post',
      );
      $posts = get_posts($query_args);

      if(!array_key_exists('0', $posts)) {

         // create new post
         $post_id = wp_insert_post( $post_data );
         if($post_data['meta_input']['bm_image'] != "") {
            // error_log('Generating new image (new)' . $i . '-' . $post_data['meta_input']['bm_image']);
            bm_generate_featured_image($post_data['meta_input']['bm_image'], $post_id);
         }
         $created++;
         // error_log('Podcast ' . $products[$i]['buzzsprout_guid'] . ' not exist, creating new post.');

      }



      wp_reset_postdata();

      	
      
   }

   $response = 'Status: OK<br>';
   $response .= 'Total: ' . $count . '<br>';
   $response .= 'New: ' . $created . '<br>';

   
   error_log($response);
   error_log('--------------------------');


}
