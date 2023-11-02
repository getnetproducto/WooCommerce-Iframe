<?php
/**
 * Plugin Name: Wanderlust - Getnet Iframe by Santander
 * Plugin URI: https://wanderlust-webdesign.com/
 * Description: Plugin que conecta la API Iframe de Getnet con WooCommerce.
 * Author: Wanderlust Web Design
 * Author URI: https://wanderlust-webdesign.com/
 * Version: 0.0.1
 * Text Domain: wc-gateway-getnetIframe
 * Domain Path: /i18n/languages/
 * WC tested up to: 7.9.1
 *
 * @package   WC-Gateway-getnetIframe
 * @author    Wanderlust Web Design
 * @copyright Copyright (c) 2010-2023, Wanderlust Web Design
 *
 */
 

 add_action('wp_ajax_wanderlust_revisar_pagoqrgetnet', 'wanderlust_revisar_pagoqrgetnet', 1);
 add_action('wp_ajax_nopriv_wanderlust_revisar_pagoqrgetnet', 'wanderlust_revisar_pagoqrgetnet', 1);		     
 
 function wanderlust_revisar_pagoqrgetnet(){
    if($_POST['dataid']){
      $order_id = $_POST['dataid'];
      $qr_data = get_post_meta($order_id, 'qr_status', true);
      if($qr_data == 'approved'){
        $order = wc_get_order($order_id);
        $urlok =  $order->get_checkout_order_received_url();
           
        echo $urlok;
        die();
      }
    }
    die();       
  }

add_filter('woocommerce_payment_gateways', 'wanderlustgetnet_Iframe_add_gateway_class');
 
function wanderlustgetnet_Iframe_add_gateway_class($gateways)
{
    $gateways[] = 'WC_wanderlustGetnetIframe_Gateway';
 
    return $gateways;
}

add_action('plugins_loaded', 'wanderlustgetnet_Iframe_init_gateway_class');
 
function wanderlustgetnet_Iframe_init_gateway_class()
{
    class WC_wanderlustGetnetIframe_Gateway extends WC_Payment_Gateway
    {

        public function __construct()
        {
            define('WANDERLUST_MPQR_DIR_PATH', plugin_dir_path(__FILE__));
            define('WANDERLUST_MPQR_DIR_URL', plugin_dir_url(__FILE__));
            $this->id = 'wanderlustgetnet_iframe_gateway';
            $this->icon = apply_filters('woocommerce_wanderlustgetnet_Iframe_icon', plugins_url('wanderlust-getnet-iframe/img/getnetlogo.png', plugin_dir_path(__FILE__)));
            $this->has_fields = false;
            $this->method_title = 'Getnet Iframe';
            $this->method_description = 'Pasarela de pagos Getnet Iframe.';
            $this->supports = array(
              'products',
              'refunds'
            );
            $this->init_form_fields();
            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->billing_descriptor = $this->get_option('billing_descriptor');
          
          
            $this->enabled = $this->get_option('enabled');
            $this->client_id = $this->get_option('client_id');
            $this->client_secret_id = $this->get_option('client_secret_id');
            $this->entorno_api = $this->get_option('entorno_api');
            $timezone = "America/Buenos_Aires";
            date_default_timezone_set($timezone);
            $this->date_time = date('Y:m:d-H:i:s');

            if ($this->entorno_api == 'yes') {
                $this->client_id = $this->get_option('client_id');
                $this->client_secret_id = $this->get_option('client_secret_id');

                $this->url_pago = 'https://api.pre.globalgetnet.com/';
            } else {
                $this->client_id = $this->get_option('client_id');
                $this->client_secret_id = $this->get_option('client_secret_id');
                $this->url_pago = 'https://api.globalgetnet.com/';
            }

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ));
            add_action("woocommerce_api_getnet", [$this, "webhook"]);

            add_action('woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ));
            add_action( 'template_redirect', array( $this, 'rudr_order_received_custom_payment_redirect') );

        }

        public function init_form_fields() {

            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Habilitar/Deshabilitar',
                    'label' => 'Habilitar Getnet',
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => 'Título',
                    'type' => 'text',
                    'description' => 'Es nombre que se muestra en la opción de pagos del checkout.',
                    'default' => 'Pagar con Getnet',
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => 'Descripción',
                    'type' => 'textarea',
                    'description' => 'Es la descripción que se muestra en la opción de pagos del checkout.',
                    'default' => 'Pasarela de pagos de Getnet.',
                    'desc_tip' => true,
                ),
                'billing_descriptor' => array(
                    'title' => 'Billing Descriptor',
                    'type' => 'text',
                    'description' => 'Es la descripción que se muestra en la tarjeta.',
                    'default' => '',
                    'desc_tip' => true,
                ),
                'client_id' => array(
                    'title' => 'Client ID',
                    'description' => 'Es el Client ID proporcionado por Getnet a la hora de dar de alta el servicio.',
                    'type' => 'text',
                    'desc_tip' => true,
                ),
                'client_secret_id' => array(
                    'title' => 'Client Secret',
                    'description' => 'Es el Client Secret proporcionado por Getnet a la hora de dar de alta el servicio.',
                    'type' => 'text',
                    'desc_tip' => true,
                ), 
                'entorno_api' => array(
                    'title' => 'Activar modo testeo',
                    'label' => 'Activar',
                    'type' => 'checkbox',
                    'description' => 'Sirve para Activar / Desactivar el modo testeo. Es para hacer pruebas con el API de pruebas de Getnet.',
                    'default' => 'no',
                    'desc_tip' => true,
                ),
				        'webhook_user' => array(
                    'title' => 'Usuario WebHook',
                    'description' => 'Ingresar usuario para WebHook',
                    'type' => 'text',
                    'desc_tip' => true,
                ),
                'webhook_pass' => array(
                    'title' => 'Password WebHook',
                    'description' => 'Ingresar password para WebHook',
                    'type' => 'text',
                    'desc_tip' => true,
                ), 
            );
         

        }

        public function payment_fields() {
            if ($this->description) {

                echo wpautop(wp_kses_post($this->description));
            }
        }
      
        public function process_refund($order_id, $amount = null, $reason = '') {
            global $woocommerce;
            $order = wc_get_order($order_id);

            if ($amount != $order->get_total()) {
                return new WP_Error('partial_refund_not_allowed', 'Solo se permite reembolsar el total del pago.');
            }

            $transaction_id = get_post_meta($order_id, 'getnet_response_iframe', true);
            $transaction_id = json_decode($transaction_id);

            $transaction_id = $transaction_id->payment->result->payment_id;

            $params = array(
                'transaction_id' => $transaction_id,
                'amount' => $amount,
            );

            $psp_Amount =  preg_replace( '#[^\d.]#', '', $amount  );
            $amount = str_replace('.', '', $psp_Amount);

            $curl = curl_init();

            curl_setopt_array($curl, array(
              CURLOPT_URL =>  $this->url_pago .'authentication/oauth2/access_token?client_id='.$this->client_id.'&client_secret='.$this->client_secret_id.'&grant_type=client_credentials',
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => '',
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 0,
              CURLOPT_SSL_VERIFYHOST => false,
              CURLOPT_SSL_VERIFYPEER => false,                
              CURLOPT_HTTPHEADER => array(
                      'Content-Type: application/x-www-form-urlencoded',
                      'Accept-Encoding: gzip, deflate, br',
                      'Accept: */*',
                      'Content-Length: 0'
              ),
              
              CURLOPT_FOLLOWLOCATION => true,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => 'POST',
            ));

            $token_request = curl_exec($curl);

            curl_close($curl);
           
            $token_request = json_decode($token_request);

            $curl = curl_init();

            curl_setopt_array($curl, array(
             //CURLOPT_URL => 'https://api.globalgetnet.com/checkout/v1/payments/'.$transaction_id.'/refund', //cancellation
              CURLOPT_URL => 'https://api.globalgetnet.com/checkout/v1/payments/'.$transaction_id.'/cancellation', //cancellation
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => '',
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 0,
              CURLOPT_SSL_VERIFYHOST => false,
              CURLOPT_SSL_VERIFYPEER => false,  
              CURLOPT_FOLLOWLOCATION => true,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => 'POST',
              CURLOPT_POSTFIELDS =>'{ "amount": '.$amount.' }',      
              CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer '.$token_request->access_token,
                'Content-Type: application/json',
                ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);

            update_post_meta($order_id, 'refund_info', $response);

            return true;





        }

        public function generate_qr_form($order_id) {
            global $woocommerce;
            $notification_url = get_site_url();
            $notification_url = $notification_url . '/?wc-api=getnet';
            $order = wc_get_order($order_id);
            $order->reduce_order_stock();
            WC()->cart->empty_cart();
            update_post_meta($order_id, 'notification', $notification_url);
            $dni = get_post_meta($order_id, 'DNI', true);
            if(empty($dni)){
              $dni = '31313131';
            }
                       
            $total = $order->get_total();
            $total = str_replace(".","",$total);
            $total = str_replace(",","",$total);
 
            if (intval(get_option('woocommerce_price_num_decimals')) == 0) {
              $total = number_format($total, 2, '', '');
            }
			
			 $items = $order->get_items();
          $productos = array();
          foreach( $items as $item ) {    	
			  
			   
 			if ( $item['product_id'] > 0 ) {
				
 				if($item['variation_id'] > 0){
				  $product = wc_get_product( $item['variation_id'] );
			  } else {
					$product = wc_get_product( $item['product_id'] );
				}
 				
                  if(empty($nombre)){
                    $nombre = $product->get_name();
                  } else {
                    $nombre = $nombre . ' - ' .$product->get_name();
                  }
                  $productos[] = array(
					           'product_type' => 'cash_carry',
                    'title' => $product->get_name(),
                    'description' => $product->get_name(),
                    'quantity' => $item['quantity'],
                    'value' => intval($product->get_price() * $item['quantity']),             
                  );
                }
          }
   
 
            $curl = curl_init();

            curl_setopt_array($curl, array(
              CURLOPT_URL =>  $this->url_pago .'authentication/oauth2/access_token?client_id='.$this->client_id.'&client_secret='.$this->client_secret_id.'&grant_type=client_credentials',
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => '',
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 0,
              CURLOPT_SSL_VERIFYHOST => false,
              CURLOPT_SSL_VERIFYPEER => false,                
              CURLOPT_HTTPHEADER => array(
                      'Content-Type: application/x-www-form-urlencoded',
                      'Accept-Encoding: gzip, deflate, br',
                      'Accept: */*',
                      'Content-Length: 0'
              ),
              
              CURLOPT_FOLLOWLOCATION => true,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => 'POST',
            ));

            $token_request = curl_exec($curl);

            curl_close($curl);
           
            $token_request = json_decode($token_request);

            $post_data = array(
              "mode" => "instant",
               "seller" => array(
                  "reference"  => $order_id,
                  "mcc"  => 1000,
                  "billing_descriptor"  => $this->billing_descriptor
               ),
              
               "payment" => array(
                  "amount"  => (int)$total,
                  "currency"  => "ARS"
               ),                   
              
               "customer" => array(
                "customer_id" => "3fa85f64-5717-4562-b3fc-2c963f66afa6",
                "first_name" => $order->get_billing_first_name(),
                "last_name" => $order->get_billing_last_name(),
                "name" => $order->get_billing_first_name() .' '. $order->get_billing_last_name(),
                "email" => $order->get_billing_email(),
                "document_type" => "dni",
                "document_number" => $dni,
                "phone_number" => $order->get_billing_phone(),
                "checked_email" => true,
                "billing_address" => array(
                  "street" => $order->get_billing_address_1(),
                  "number" => '-',
                  "city" => $order->get_billing_city(),
                  "state" => $order->get_billing_state(),
                  "country" => "AR",
                  "postal_code" => $order->get_billing_postcode()
                 ),
               ) ,
              
               "product" => $productos,
               "authorization" => "Bearer " . $token_request->access_token
            );
          
          
 
 
            $post_data = json_encode($post_data);

  

            $curl = curl_init();

            curl_setopt_array($curl, array(
              CURLOPT_URL => 'https://api.globalgetnet.com/digital-payments/checkout/v1/payment-intent',
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => '',
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 0,
              CURLOPT_FOLLOWLOCATION => true,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => 'POST',
              CURLOPT_POSTFIELDS => $post_data,
              CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer '.$token_request->access_token,
                'Content-Type: application/json',
              ),
            ));
        

            $payment_intent_id = curl_exec($curl);

            curl_close($curl);
             
             $success = $order->get_checkout_order_received_url();
            $failed = $order->get_cancel_order_url();
 

            $intent_id = json_decode($payment_intent_id);
            update_post_meta($order_id, 'payment_intent_id', $intent_id->payment_intent_id );

 
           if ($this->entorno_api == 'yes') {  ?>

            <script src="https://www.pre.globalgetnet.com/digital-checkout/loader.js"></script>

            <?php } else { ?>

            <script src="https://www.globalgetnet.com/digital-checkout/loader.js"></script>

             
            <?php } ?>


  <div id="iframe-section"  data-id="<?php echo $order_id;?>"></div>

  <script>
  
    const config = {
        "paymentIntentId": "<?php echo $intent_id->payment_intent_id;?>",
        "checkoutType": "iframe",
        "accessToken": "Bearer <?php echo $token_request->access_token;?>"
      };
      
          loader.init(config);
          
          const iframeSection = document.getElementById("iframe-section");
          const iframe = document.querySelector("iframe");
          iframeSection.appendChild(iframe);
        
     

 
  </script>

 <script type="text/javascript">		
        
         
        setInterval(function(){
          
              var ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";
              var dataid = jQuery('#iframe-section').data("id");
              jQuery.ajax({
                type: 'POST',
                cache: false,
                url: ajaxurl,
                data: {
                  action: 'wanderlust_revisar_pagoqrgetnet',
                  dataid: dataid
                },
                success: function(data, textStatus, XMLHttpRequest){ 

                 if (data.indexOf("received") >= 0){
					window.location.href = data;
                 }  
 
                },
                error: function(MLHttpRequest, textStatus, errorThrown){

                }
              });
            
          

          
          }, 7000 );            
         



        </script>

          
<?php
        }

        public function receipt_page($order) {
            echo $this->generate_qr_form($order);
        }

        public function process_payment($order_id) {
            $order = new WC_Order($order_id);
            return array(
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url(true)
            );
        }
      

        public function rudr_order_received_custom_payment_redirect(){

          // do nothing if we are not on the order received page
          if( ! is_wc_endpoint_url( 'order-received' ) || empty( $_GET[ 'key' ] ) ) {
            return;	
          }

          // Get the order ID
          $order_id = wc_get_order_id_by_order_key( $_GET[ 'key' ] );

          // Get an instance of the WC_Order object
          $order = wc_get_order( $order_id );
           echo '<pre>';print_r($order_id);echo' $order_id</pre>';  die();
          // Now we can check what payment method was used for order
          if( 'cod' === $order->get_payment_method() ) {
            // if cash of delivery, redirecto to a custom thank you page
            wp_safe_redirect( site_url( '/custom-page/' ) );
            exit; // always exit
          }

        }
      
       public function webhook() {
            global $wpdb;
            $log = new WC_Logger();
            header("HTTP/1.1 200 OK");
            $postBody = file_get_contents("php://input");

            $log->add("GetNet log", "return " . $postBody);

            $responseipn = json_decode($postBody);
            if ($responseipn->payment_intent_id) {
                $terms = $responseipn->payment_intent_id;
                $products = $wpdb->get_results( 
                    $wpdb->prepare( "SELECT ID, post_parent FROM {$wpdb->posts} LEFT JOIN {$wpdb->postmeta} ON {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id WHERE meta_key IN ( 'payment_intent_id' ) AND meta_value LIKE %s;", '%' . $wpdb->esc_like( wc_clean( $terms ) ) . '%' ) 
                    );   

                if ($products[0]->ID) {
                    $order_id = $products[0]->ID;
                    $order = wc_get_order($order_id);
					
					 update_post_meta( $order_id, "getnet_response_iframe", $postBody );
					
					if($responseipn->payment->result->status == 'Authorized'){
						
						update_post_meta($order_id, 'qr_status', 'approved');

 						update_post_meta(
                            $order_id,
                            "_getnet_response",
                            $postBody
                        );
                        $order->add_order_note(
                            "GetNet: " .
                                __("Pago Aprobado.", "wc-gateway-getnet")
                        );
                        $order->payment_complete();
						
					} else {
 						$order->add_order_note(
                            "GetNet: " .
                                __("Pago Fallido.", "wc-gateway-getnet")
                        );
                        update_post_meta(
                            $order_id,
                            "_getnet_response",
                            $postBody
                        );
					}
					
					
                  
                    
                }
            }
        }
      
    }
}

 

