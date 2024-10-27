<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/*
Plugin Name: WooCommerce Click Gateway
Plugin URI: http://woothemes.com/woocommerce
Description: A gateway for Click payments.
Version Date: 15 March 2016
Version: 1.00
Author: Paymark
Author URI: http://www.paymark.co.nz
*/

/**
 * Required functions
 */
if ( ! function_exists( 'woothemes_queue_update' ) )
	require_once( 'woo-includes/woo-functions.php' );

/**
 * Plugin updates
 */
woothemes_queue_update( plugin_basename( __FILE__ ), 'XXXXXXX', 'XXXXXX' );

require_once("includes/click.inc.php");

add_action('plugins_loaded', 'woocommerce_click', 0);

function woocommerce_click() {

	// If the WooCommerce payment gateway class is not available, do nothing
	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

	class WC_Gateway_Click extends WC_Payment_Gateway {

		public $click;


		public function __construct() {

			global $woocommerce;

			$this->id   = 'Click';
			$this->method_title = __('Click', 'woothemes');
			$this->icon   = '';
			$this->has_fields  = false;

			// Load the form fields.
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();

			// Define user set variables
			$this->title = $this->settings['title'];
			$this->description = $this->settings['description'];
			$this->test_mode = $this->settings['test_mode'];

			$this->access_userid = $this->settings['access_userid'];
			$this->access_accountid = $this->settings['access_accountid'];
			$this->access_password = $this->settings['access_password'];


			// test mode
			if($this->test_mode === 'yes'){

				$this->access_url = 'uat.paymarkclick.co.nz';

			} else {

				$this->access_url = 'secure.paymarkclick.co.nz';

			}


			$Click_Userid = $this->access_userid;
			$Click_Accountid = $this->access_accountid;
			$Click_Password = $this->access_password;


			$this->click  = new Click_OpenSSL( $this->access_url, $Click_Userid, $Click_Accountid, $Click_Password );


			// Actions

			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );

			/* Hook IPN callback logic*/
			if (version_compare (WOOCOMMERCE_VERSION, '2.0', '<'))
				add_action('init', array(&$this, 'check_click_callback'));
			else
				add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'check_click_callback' ) );

			add_action( 'woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
			add_action( 'valid-click-callback', array(&$this, 'successful_request') );
			add_action( 'woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 2);

			/* 1.6.6 */
			add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );

			/* 2.0.0 */
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			
			/* initiation of logging instance */
			$this->log = new WC_Logger();

		}


		/**
		 * Add relevant links to plugins page
		 * @param  array $links
		 * @return array
		 */
		public function plugin_action_links( $links ) {
			$plugin_links = array(
				'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_click' . $addons ) . '">' . __( 'Settings', 'woocommerce-gateway-click' ) . '</a>',
				'<a href="http://www.paymark.co.nz/products/click">' . __( 'Support', 'woocommerce-gateway-click' ) . '</a>',
				'<a href="http://www.paymark.co.nz/products/click">' . __( 'Docs', 'woocommerce-gateway-click' ) . '</a>',
			);
			return array_merge( $plugin_links, $links );
		}



		/**
		 * Initialise Gateway Settings Form Fields
		 */
		function init_form_fields() {

            $default_site_name = home_url() ;

			$this->form_fields = array(

				'enabled' => array(
					'title' => __( 'Enable/Disable', 'woothemes' ),
					'type' => 'checkbox',
					'label' => __( 'Enable Click&trade;', 'woothemes' ),
					'default' => 'yes'
				),

				'title' => array(
					'title' => __( 'Title', 'woothemes' ),
					'type' => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woothemes' ),
					'default' => __( 'Click&trade;', 'woothemes' ),
					'css' => 'width: 400px;'
				),

				'description' => array(
					'title' => __( 'Description', 'woothemes' ),
					'type' => 'textarea',
					'description' => __( 'This controls the description which the user sees during checkout.', 'woothemes' ),
					'default' => __("Allows payments by Click&trade;", 'woothemes')
				),

				'test_mode' => array(
					'title' => __( 'Test mode', 'woothemes' ),
					'type' => 'checkbox',
					'label' => __( 'Enable test mode', 'woothemes' ),
					'default' => 'no'
				),

				'access_userid' => array(
					'title' => __( 'Click&trade; User Name', 'woothemes' ),
					'type' => 'text',
					'default' => '',
					'css' => 'width: 400px;'
				),

				'access_accountid' => array(
					'title' => __( 'Click&trade; Account ID', 'woothemes' ),
					'type' => 'text',
					'default' => '',
					'css' => 'width: 400px;'
				),

				'access_password' => array(
					'title' => __( 'Click&trade; Password', 'woothemes' ),
					'type' => 'password',
					'default' => '',
					'css' => 'width: 400px;'
				)

			);

		} // End init_form_fields()




		/**
		 * Admin Panel Options
		 * - Options for bits like 'title' and availability on a country-by-country basis
		 *
		 * @since 1.0.0
		 */
		public function admin_options() {
			?>
	    	<h3><?php _e('Click&trade;', 'woothemes'); ?></h3>
	    	<p><?php _e('Allows payments by Click&trade;', 'woothemes'); ?></p>
	    	<table class="form-table">
	    	<?php
				// Generate the HTML For the settings form.
				$this->generate_settings_html();
			?>
			</table><!--/.form-table-->
	    	<?php
		} // End admin_options()



		/**
		 * There are no payment fields for paypal, but we want to show the description if set.
		 **/
		function payment_fields() {
			if ($this->description) echo wpautop(wptexturize($this->description));
		}



		/**
		 * receipt_page
		 **/
		function receipt_page( $order ) {
			echo $this->generate_Click_form( $order );
		}



		/**
		 * Generate the Click button link
		 **/
		public function generate_Click_form( $order_id ) {

			global $woocommerce;

			$order = new WC_Order( $order_id );
			$order_number = $order->get_order_number();
			$billing_name = $order->billing_first_name." ".$order->billing_last_name;
			$shipping_name = explode(' ', $order->shipping_method);
			
			//$request = new ClickRequest();

			$http_host   = getenv("HTTP_HOST");
			$request_uri = getenv("SCRIPT_NAME");
			$server_url  = "http://$http_host";

			if ( method_exists( $woocommerce, 'api_request_url' ) ) {
				$return_url = esc_url_raw( add_query_arg( 'wc-api', 'WC_Gateway_Payment_Express', trailingslashit( home_url( ) ) ) );
			} else {
				$return_url = $this->get_return_url();
			}
			
			$this->log->add( 'click', print_r( array( 'return url' => $script_url ), true ) );


			$parms = array();

			//static
			$parms['cmd'] = '_xclick'; 
			$parms['store_card'] = 0;

			// order details
			$parms['return_url'] = $return_url;
			$parms['reference'] = $order_number;
			$parms['particular'] = 'Order # ' . $order_number;
			$parms['amount'] = $order->order_total;


			$request = $this->click->makeUrlRequest($parms);

			$xml = new SimpleXMLElement($request);

			$error_message = $xml->errormessage;
			$error_type = $xml->errortype;

			if ($error_message) {
				throw new Exception('Failed: '.$error_message.'|'.$error_type);
			} else {

				$url = $xml;
			}

			$img_loader = apply_filters( 'filter_custom_loader_image', esc_url( plugins_url( 'images/ajax-loader.gif', __FILE__ ) ) );

			return '<form action="'.esc_url( $url ).'" method="post" id="click_payment_form" style="display:none;">
				<input type="submit" class="button-alt" id="submit_Click_payment_form" value="'.__('Pay via Click', 'woothemes').'" /> <a class="button cancel" href="'.esc_url( $order->get_cancel_order_url() ).'">'.__('Cancel order &amp; restore cart', 'woothemes').'</a>
				<script type="text/javascript">
					jQuery(function(){
						jQuery("body").block(
							{
								message: "<img src=\"'. $img_loader .'\" alt=\"Redirecting...\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to Click&trade; to make payment.', 'woothemes').'",
								overlayCSS:
								{
									background: "#fff",
									opacity: 0.6
								},
								css: {
							        padding:        20,
							        textAlign:      "center",
							        color:          "#555",
							        border:         "3px solid #aaa",
							        backgroundColor:"#fff",
							        cursor:         "wait",
							        lineHeight:		"32px",
							    }
							});
						jQuery("#submit_Click_payment_form").click();
					});
				</script>
			</form>';

		}




		/**
		 * Process the payment and return the result
		 **/
		function process_payment( $order_id ) {

			global $woocommerce;

			$order = new WC_Order( $order_id );

 			return array(
				'result' => 'success',
				'redirect' => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, $order->get_checkout_payment_url( true )))
			);

		}




		function email_instructions( $order, $sent_to_admin ) {
			if ( $sent_to_admin ) return;

			if ( $order->status !== 'on-hold') return;

			if ( $order->payment_method !== 'Click') return;

			if ($this->description) echo wpautop(wptexturize($this->description));
		}



		/*
		Get callback from Click and pass on to validate request
		*/

		function check_click_callback() {

		   	if ( isset($_REQUEST["AccountId"]) ) :

				do_action("valid-click-callback", $_REQUEST);

			endif;
		}




		/*
		Process response
		*/

		function successful_request ($data) {

			$this->Click_success_result($data);

		}



		public function Click_success_result($data) {

			$response = $this->click->validateResponse($data);

			$xml = new SimpleXMLElement($response);

			$order_id = $xml->Reference;
			$status = $xml->Status;

			$order = new WC_Order( (int) $xml->Reference );
			$this->log->add( 'click', print_r( array( 'click Response' => $response ), true ) );


			if($status != 'SUCCESSFUL'){

				$order->update_status('failed', sprintf(__('Payment %s via Click.', 'woothemes'), strtolower($ResponseText) ) );
				wp_redirect( $this->get_return_url( $order ) );
				exit();

			} else {

				$order->payment_complete();
				wp_redirect( $this->get_return_url( $order ) );
				exit();
			}

		}

	}




	$myplugin = new WC_Gateway_Click();

	add_action('init', array($myplugin, 'check_click_callback'));	



	/*
	Add the gateway to WooCommerce
	*/

	add_filter('woocommerce_payment_gateways', 'add_click_gateway' );
	
	function add_click_gateway( $methods ) {

		$methods[] = 'WC_Gateway_Click';

		return $methods;

	}



}
