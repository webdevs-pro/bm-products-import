<?php
class BM_XML_Export_Order {

   public function __construct($args) {

      $this->generate_order_xml($args);

   }

   public function generate_order_xml($args) {

      $xml = simplexml_load_file(BM_PLUGIN_DIR . '/templates/order-template.xml');

      $xml->transmisja_id = date('YmdHis');


      foreach ($args as $key => $value) {
         if (!is_array($value)) {
            $xml->dokumenty_zamowien->dokument_zamowienia->$key = $value;
         } else {
            foreach($value as $sub_key => $sub_value) {
               $xml->dokumenty_zamowien->dokument_zamowienia->$key->$sub_key = $sub_value;
            }
         }
      }

      // add order items
      foreach($args['pozycje_zamowienia'] as $pozycja_zamowienia) {

         $node = $xml->dokumenty_zamowien->dokument_zamowienia->pozycje_zamowienia->addChild('pozycja_zamowienia','');

         $node->addChild('numer_pozycji',$pozycja_zamowienia['numer_pozycji']);
         $node->addChild('towar_id',$pozycja_zamowienia['towar_id']);
         $node->addChild('ilosc',$pozycja_zamowienia['ilosc']);
         $node->addChild('il_opak_zbiorczych','');
         $node->addChild('cena_po_rabacie','');
         $node->addChild('rabat_procentowy','');
         $node->addChild('wartosc_vat','');
         $node->addChild('wartosc_brutto', $pozycja_zamowienia['wartosc_brutto']);
         $node->addChild('komentarz1','');
         $node->addChild('komentarz2','');

      }

      $uploads_dir = wp_get_upload_dir();

      $formatxml = new DOMDocument('1.0');
      $formatxml->preserveWhiteSpace = false;
      $formatxml->formatOutput = true;
      $formatxml->loadXML($xml->asXML());

      $formatxml->save($uploads_dir['basedir'] . '/xml_import/' . 'imp_dok_0001_' . date('YmdHis') . '.xml');

   }








}