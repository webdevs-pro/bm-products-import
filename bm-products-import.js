// jQuery(document).ready(function($) {

//    var readXml = null;
//    var xmlDoc = null;

//    var cats_xml = null;
//    var cats = {};
//    var cat = {};
//    var cat_id;

//    var sub_cats = [];

//    var products_xml = null;
//    var products = {};
//    var product = {};
//    var product_id;

//    $('#bm_import_now').click(function(e) {

//       e.preventDefault();



//       var folder = $('#bm_exclude_asortyments').val();


//       var selectedFile = document.getElementById('bm_uload_file').files[0];
//       // console.log(selectedFile); // info about file
//       var reader = new FileReader();
//       reader.onload = function(e) {
//          readXml = e.target.result; // get XML raw text

//          xmlDoc = $.parseXML(readXml); // parse XML
//          $xml = $(xmlDoc);

//          // $data = $($xml.find('dane'));


//          // CATS
//          cats_xml = $($xml.find('asortyment'));
//          $.each(cats_xml, function(index, value) {
//             cat = {};
//             $.each(cats_xml[index].children, function(key, value) {
//                // console.log(value);
//                cat[value.nodeName] = value.textContent;
//                if (value.nodeName == 'asortyment_id') {
//                   cat_id = value.textContent;
//                }

//                if (value.nodeName == 'pod_asortymenty') {
//                   sub_cats = [];
//                   $.each(value.children, function(key, value) {
//                      sub_cats.push($(value).find('asortyment_id').text());
//                   });
//                   cat[value.nodeName] = sub_cats;
//                }               
//             });
//             cats[cat_id] = cat;
//          });
//          console.log(cats);



//          // PRODUCTS
//          products_xml = $($xml.find('towar'));
//          $.each(products_xml, function(index, value) {
//             product = {};
//             $.each(products_xml[index].children, function(key, value) {
//                // console.log(value);
//                product[value.nodeName] = value.textContent;
//                if (value.nodeName == 'towar_id') {
//                   product_id = value.textContent;
//                }
//             });
//             products[product_id] = product;
//          });
//          console.log(products);


   

//       }
//       reader.readAsText(selectedFile);

      

   

// 		// var import_xml_data = {
// 		// 	action: 'import_xml',
//       //    url: url,
//       //    cat: cat,
// 		// };

//       // var resp_obj;


//       // // FETCHING XML
// 		// $.ajax({
// 		// 	url: ajaxurl,
// 		// 	type: 'POST',
//       //    data: import_xml_data,
//       //    async: false, 
// 		// 	beforeSend: function( xhr ) {
//       //       $('#bm_spinner').addClass('is-active');
//       //       $('#bm_import_now').addClass('disabled');
//       //       $('#products_progress').show();
//       //       // print_to_log('Fetching feed...');
//       //       $('.products_progress_text').html('Fetching feed...');
// 		// 	},
// 		// 	success: function( response ) {
            

//       //       resp_obj = $.parseJSON(response);

            
//       //       resp_obj_count = Object.keys(resp_obj).length;

//       //       // $('#bm_spinner').removeClass('is-active');
//       //       // $('#bm_import_now').removeClass('disabled');


//       //       // print_to_log('Total products: ' + resp_obj_count);
//       //       $('.products_progress_text').html('0 of ' + resp_obj_count);
//       //       $('#products_progress').attr('max', resp_obj_count);

//       //       // $('#bm_ajax_result').html($('#bm_ajax_result').html() + response);
// 		// 	}
//       // });



//       // // FETCHING ITEM
//       // var item = 0;

//       // fetch_item();
//       // function fetch_item() {
   
//       //    if(item >= resp_obj_count) {
//       //       stop_fetching();
//       //       return;
//       //    }

//       //    var value = resp_obj[item];
         
//       //    var json_data = JSON.stringify(value);

//       //    var episode_data = {
//       //       action: 'fetch_episode',
//       //       data: json_data,
//       //       mode: mode,
//       //    };

//       //    // fetching xml
//       //    $.ajax({
//       //       url: ajaxurl,
//       //       type: 'POST',
//       //       data: episode_data,
//       //       async: false, 
//       //       beforeSend: function( xhr ) {
//       //          // print_to_log('Fetching episode: ' + item);
//       //       },
//       //       success: function( response ) {


//       //          console.log(response);

//       //          // response = $.parseJSON(response);

               


//       //          // print_to_log('Episode: ' + item + ':');
//       //          // print_to_log(response);

//       //          // $('#bm_ajax_result').html($('#bm_ajax_result').html() + response);
//       //       }
//       //    });



//       //    item++;

//       //    setTimeout( function() { 
//       //       $('.products_progress_text').html(item + ' of ' + resp_obj_count);
//       //       $('#products_progress').val(item);
//       //       fetch_item(); 
//       //    }, 100 );


//       // };



//       // function stop_fetching() {
//       //    $('.products_progress_text').html($('.products_progress_text').html() + ', Done');
//       //    // print_to_log('Done');
//       //    $('#bm_spinner').removeClass('is-active');
//       //    $('#bm_import_now').removeClass('disabled');
//       // }







//       // function print_to_log(text) {
//       //    $('#bm_ajax_result').html(text + '<br>' + $('#bm_ajax_result').html());
//       // }




      
//    });




  
   
// });