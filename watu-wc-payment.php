<?php
/*
 * Plugin Name: Watupay
 * Plugin URI: https://watupay.com
 * Description: Secure and fast payments through multiple channels
 * Author: Watu
 * Author URI: https://watu.global
 * Version: 1.0.4
 *
*/

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'watu_add_gateway_class' );
function watu_add_gateway_class( $gateways ) {
    $gateways[] = 'WC_Watu_Gateway'; // your class name is here
    return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'watu_init_gateway_class' );
function watu_init_gateway_class() {

    class WC_watu_Gateway extends WC_Payment_Gateway {

        private $public_key;
        private $callback_url;
        private $global_order_id;
        public $base_url='https://api.watu.global/v1';
        private $redirect_url;

        /**
         * Class constructor, more about it in Step 3
         */
        public function __construct() {
            $this->id = 'watu-pay'; // payment gateway plugin ID
            $this->icon = 'https://watu.global/_nuxt/img/104f9ca.svg'; // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = false; // in case you need a custom credit card form
            $this->method_title = 'Watupay Online Payments';
            $this->method_description = __( 'Provide customers with a quick and easy payment experience', 'woocommerce' ); // will be displayed on the options page
            $this->order_button_text = __( 'Proceed to Watupay', 'woocommerce' );
            // gateways can support subscriptions, refunds, saved payment methods,
            // but in this tutorial we begin with simple payments
            $this->supports = array(
                'products'
            );


            // Method with all the options fields
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();
            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->enabled = $this->get_option( 'enabled' );
            $this->testmode = 'yes' === $this->get_option( 'testmode' );
            $this->public_key = $this->testmode ? $this->get_option( 'test_public_key' ) :
                $this->get_option( 'live_public_key' );
            $this->redirect_url=$this->get_option('redirect_url');
            $this->payment_methods=$this->get_option('payment_methods');

//            $this->title=$this->title."<b style=\"color:#01274d;font-size:11px;\">
//(Proceed & wait a few seconds to complete your payment.)</b>";
//            if ( $this->testmode ) {
//                /* translators: %s: Link to PayPal sandbox testing guide page */
//                $this->description .= ' ' . sprintf( __( 'SANDBOX ENABLED. You can use testing accounts and keys only. See <a href="%s">Documentation</a> for more details.', 'woocommerce' ), 'https://docs.watu.global/' );
//                $this->description  = trim( $this->description );
//            }





            // This action hook saves the settings
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

            // We need custom JavaScript to obtain a token
            add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );

            //register Callback method
            add_action( 'woocommerce_api_'. strtolower( get_class($this) ), array( $this, 'callback_handler' ) );

            // You can also register a webhook here
            // add_action( 'woocommerce_api_{webhook name}', array( $this, 'webhook' ) );
        }

        /**
         * Plugin options, we deal with it in Step 3 too
         */
        public function init_form_fields(){
            $this->form_fields = array(
                'enabled' => array(
                    'title'       => __( 'Enable/Disable', 'woocommerce' ),
                    'label'       => 'Enable Watu Payment',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => __( 'Title', 'woocommerce' ),
                    'type'        => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default'     => 'Watupay Online Payments',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => __( 'Customer Message', 'woocommerce' ),
                    'type'        => 'text',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default'     => __( 'Click the Order button and wait a few seconds 
                    to view checkout and complete your payment.', 'woocommerce' ),
                ),
                'testmode' => array(
                    'title'       => 'Test mode',
                    'label'       => 'Enable Test Mode',
                    'type'        => 'checkbox',
                    'description' => 'Place the payment gateway in test mode using test API keys.',
                    'default'     => 'yes',
                    'desc_tip'    => true,
                ),
                'test_public_key' => array(
                    'title'       => 'Test Public Key',
                    'type'        => 'text'
                ),
                'live_public_key' => array(
                    'title'       => 'Live Public Key',
                    'type'        => 'text'
                ),
                'payment_methods' => array(
                    'title'       => 'Payment Methods',
                    'type'        => 'text',
                    'description' => 'Leave this empty if you want all available payment methods.
                    If not, Enter payment methods separated by commas. Available options are 
                    ussd,card,dynamic-account-transfer,qr-code,mobile-money,bank-account',
                ),
                'redirect_url' => array(
                    'title'       => 'Redirect URL',
                    'type'        => 'text',
                    'description' => 'Where you want to redirect your user after payment',
                ),
//                'watulink' => array(
//                    'title'       => 'Watulink',
//                    'type'        => 'text'
//                )

            );
        }

        /**
         * You will need it if you want your custom credit card form, Step 4 is about it
         */
        public function payment_fields() {


        }

        /*
         * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
         */
        public function payment_scripts() {


        }

        /*
          * Fields validation, more in Step 5
         */
        public function validate_fields() {
            // if( empty( $_POST[ 'billing_email' ]) ) {
            //     wc_add_notice(  'Customer email is required!', 'error' );
            //     return false;
            // }

        }

        /*
         * We're processing the payments here, everything about it is in Step 5
         */
        public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );
            global $woocommerce;
            wc_print_notice("Processing Order, Please wait...",'notice');

//            echo "Processing Order, Please wait...";
            // we need it to get any order detailes

            $this->global_order_id=$order_id;


            /*
              * Array with parameters for API interaction
             */

            $shop_page_url = get_permalink( woocommerce_get_page_id( 'shop' ) );
//            wc_add_notice($shop_page_url);
//            return;
            $this->callback_url=$shop_page_url.'wc-api/wc_watu_gateway/';

            $email=$order->get_billing_email();
            $country=$order->get_billing_country();
            if(empty($email)){
                $current_user = wp_get_current_user();
                $email=$current_user->user_email;
            }
            if(empty($country)){
                $country="NG";
            }
            $body=array(
                "email"=>$email,
                "amount"=>$order->get_total(),
                "country"=>$country,
                "currency"=>$order->get_currency(),
                "merchant_reference"=>$this->generateRandomString(6).$order_id.time(),
                "service_type"=>"watu-pay",
                "callback_url"=>$this->callback_url,
            );
            if(!empty($this->payment_methods)){
                $body["payment_methods"]=$this->payment_methods;
            }
            $body=json_encode($body);

            $args = array(
                "body"=>$body,
                "headers"=>array(
                    "Accept"=>"application/json",
                    "Content-Type"=>"application/json",
                    "Authorization"=>"Bearer ".$this->public_key,
                )
            );

    /*
     * Your API interaction could be built with wp_remote_post()
     */

     $response = wp_remote_post( $this->base_url.'/payment/initiate', $args );
//            wc_add_notice(  $response,'error');

     if( !is_wp_error( $response ) ) {
         $body = json_decode($response['body'],true);
//         wc_add_notice($body['data']['url']);

         // it could be different depending on your payment processor
         if ( $body['status_code']==200 ) {
//             wc_add_notice(  $body['has_error'],'error');
//             return;

             $checkout_url=$body['data']['url'];
             // Redirect to the thank you page
             return array(
                 'result' => 'success',
                 'redirect' => $checkout_url,
             );

         } else {
             wc_add_notice(  $body['message'].', Please try Again.', 'error' );
             return;
         }

     } else {
         wc_add_notice(  'Connection error, please check your internet connection.', 'error' );
         return;
     }

        }

        /*
         * In case you need a webhook, like PayPal IPN etc
         */
        public function webhook() {


        }


        public function callback_handler(){
            $decoded = $_GET;
            $transaction_reference=$decoded['wtp_reference'];

            $args = array(
                "body"=>json_encode(array(
                    "transaction_id"=>$transaction_reference,
                )),
                "headers"=>array(
                    "Accept"=>"application/json",
                    "Content-Type"=>"application/json",
                )
            );

            $response = wp_remote_post( $this->base_url.'/public/transaction/verify', $args );
            $body = json_decode($response['body'],true);

            global $woocommerce;
            $order_id = $woocommerce->session->order_awaiting_payment;
            $order = new WC_Order( $order_id );
            $order->update_status( $body['data']['status'], sprintf( __( 'Paid successfully with Watu', 'wc-watu-gateway' ), get_woocommerce_currency(), $order->get_total() ) );

            if($body['status_code']==200){
                // we received the payment
                $order->payment_complete();
                // some notes to customer (replace true with false to make it private)
                $order->add_order_note( 'Hi, your order has been paid for successfully! Thank you!', true );
                // Empty cart
                $woocommerce->cart->empty_cart();

                if(!empty($this->redirect_url)) {
                    wp_redirect($this->redirect_url);
                }
                else {
                    $shop_page_url = get_permalink(woocommerce_get_page_id('shop'));
                    wp_redirect($shop_page_url);
                }

            }
            else{
                echo "Your order payment is unconfirmed or unsuccessful"."\n".$body['data']['message'];
            }

            die();
        }

        function generateRandomString($length = 6) {
            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $charactersLength = strlen($characters);
            $randomString = '';
            for ($i = 0; $i < $length; $i++) {
                $randomString .= $characters[rand(0, $charactersLength - 1)];
            }
            return $randomString;
        }
    }
}

?>
