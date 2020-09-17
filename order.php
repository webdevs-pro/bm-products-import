<?php
class BM_XML_export_order {

   public function __construct($args) {

      $this->generate_order_xml($args);

   }

   public function generate_order_xml($args) {

      $xml = simplexml_load_file(BM_PLUGIN_DIR . '/templates/order-template.xml');

      $xml->transmisja_id = date('YmdHis');

      $xml->dokumenty_zamowien->dokument_zamowienia->dok_id = $args['dok_id'];
      $xml->dokumenty_zamowien->dokument_zamowienia->numer_dokumentu = $args['numer_dokumentu'];
      $xml->dokumenty_zamowien->dokument_zamowienia->nr_seryjny_sklepu = $args['nr_seryjny_sklepu'];
      $xml->dokumenty_zamowien->dokument_zamowienia->data_zamowienia = $args['data_zamowienia'];
      $xml->dokumenty_zamowien->dokument_zamowienia->data_realizacji_zamowienia = $args['data_realizacji_zamowienia'];
      $xml->dokumenty_zamowien->dokument_zamowienia->wymagac_pelnej_realizacji = $args['wymagac_pelnej_realizacji'];
      $xml->dokumenty_zamowien->dokument_zamowienia->do_usuniecia = $args['do_usuniecia'];
      $xml->dokumenty_zamowien->dokument_zamowienia->magazyn_id = $args['magazyn_id'];
      $xml->dokumenty_zamowien->dokument_zamowienia->uzytkownik_id = $args['uzytkownik_id'];
      $xml->dokumenty_zamowien->dokument_zamowienia->poziom_cen = $args['poziom_cen'];
      $xml->dokumenty_zamowien->dokument_zamowienia->platnosc_id = $args['platnosc_id'];
      $xml->dokumenty_zamowien->dokument_zamowienia->dokument_finansowy = $args['dokument_finansowy'];
      $xml->dokumenty_zamowien->dokument_zamowienia->kontrahent_id = $args['kontrahent_id'];
      $xml->dokumenty_zamowien->dokument_zamowienia->czy_nowy_kontrahent = $args['czy_nowy_kontrahent'];

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
         $node->addChild('wartosc_brutto',$pozycja_zamowienia['wartosc_brutto']);
         $node->addChild('komentarz1','');
         $node->addChild('komentarz2','');

      }
      
      // echo '<pre>' . print_r($xml, true) . '</pre><br>';


      $uploads_dir = wp_get_upload_dir();

      $formatxml = new DOMDocument('1.0');
      $formatxml->preserveWhiteSpace = false;
      $formatxml->formatOutput = true;
      $formatxml->loadXML($xml->asXML());

      // $formatxml->save($uploads_dir['basedir'] . '/xml_import/' . 'imp_dok_0000.xml');
      $formatxml->save($uploads_dir['basedir'] . '/xml_import/' . 'imp_dok_0000_' . date('YmdHis') . '.xml');



   }








}