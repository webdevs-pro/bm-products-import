<?php
function bm_check_files_and_import() {

   error_log('---- cron import start ----');
   $before = microtime(true);

   $files = new BM_XML_files();
   

   $files_to_proccess = $files->files_to_proccess;

   if ($files_to_proccess) {
      foreach($files_to_proccess as $file) {
         error_log('proccessing file: '. $file['name']);
         $import = new BM_XML_Import($file['file_path']); // import XML

         // rename proccessed file with prefix "imported_"
         if(file_exists($file['file_path'])) {
            $path_info = pathinfo($file['file_path']);
            $new_file_name = $path_info['dirname'] . '/' . "imported_" . $path_info['filename'] . '.' . $path_info['extension'];
            rename($file['file_path'],$new_file_name);
            error_log('renamed file: ' . $new_file_name);
         } else {
            error_log('file not exist: ' . $file['file_path']);
         }
      }
   } else {
      error_log('not new files to import');
   }

   

   $after = microtime(true);
   error_log($after-$before);
   error_log('---- cron import end ----');
   error_log('');
   error_log('');


}






class BM_XML_files {

   public $uploads_dir;

   public $files_to_proccess;

   public function __construct() {

      error_log('---- check folder start ----');
      $before = microtime(true);

      $this->uploads_dir = wp_upload_dir();

      $files = $this->get_xml_files();
      // error_log( print_r($files, true) );

      $files_to_proccess = $this->get_files_to_proccess($files);
      // error_log( print_r($files_to_proccess, true) );

      $after = microtime(true);
      error_log($after-$before);
      error_log('---- check folder end ----');

      $this->files_to_proccess = $files_to_proccess;
      
   }


   /**
    * Get XML files from folder
    *
    * Return array of files data
    *
    * @since 1.0.0
    *
    * @return array
    */
   public function get_xml_files() {

      $upload_dir = $this->uploads_dir;
   
      $xml_dirname = $upload_dir['basedir'] . '/xml_import';

      if ( ! file_exists( $xml_dirname ) ) {
          error_log('no xml directory');
          return;
      }

      $files = array();
      
      foreach (glob($xml_dirname  . "/*.xml") as $file) {

         $name = basename($file); 

         preg_match('/[0-9]{14}/', $name, $date_match);
         if(isset($date_match[0])) {
            $date = strtotime($date_match[0]);
         } else {
            continue;
         }

         if (strpos($name, 'imported_') === FALSE) {
            $file_imported = 0;
         } else {
            $file_imported = 1;
         }

         $files[] = array(
            'file_path' => $file,
            'date' => $date,
            'imported' => $file_imported,
            'size' =>filesize($file),
            'name' => $name,
         );

      }

      if (!empty($files)) {
         return $files;
      } else {
         return false;
      }

   }


   /**
    * Get not imported XML files 
    *
    * Return sorted by date array of not importad XML files
    *
    * @since 1.0.0
    *
    * @return array
    */
   public function get_files_to_proccess($files) {

      error_log( print_r($files, true) );

      if (empty($files)) return false;
      
      $files_to_proccess = array();

      foreach ($files as $file) {
         if ($file['imported'] == '0') {
            $files_to_proccess[] = $file;
         } else {
            // delete old file older then 5 days
            unlink($file['file_path']);
            error_log('file deleted');
            error_log( print_r($file, true) );
         }
      }

      if (!empty($files_to_proccess)) {
         usort($files_to_proccess, function ($a, $b) {
            return $a['date'] <=> $b['date'];
         });
         
         return $files_to_proccess;
      } else {
         return false;
      }
   
   }


   // /**
   //  * Delete old imported XML files 
   //  *
   //  * @since 1.0.0
   //  *
   //  * @return void
   //  */
   //  public function delete_old_imported_file($files) {

   //    $files_to_proccess = array();

   //    foreach ($files as $file) {
   //       if ($file['imported'] == '0') {
   //          $files_to_proccess[] = $file;
   //       }
   //    }

   //    if (!empty($files_to_proccess)) {
   //       usort($files_to_proccess, function ($a, $b) {
   //          return $a['date'] <=> $b['date'];
   //       });
   //       return $files_to_proccess;
   //    } else {
   //       return false;
   //    }
   
   // }


}