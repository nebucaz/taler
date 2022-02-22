<?php
/**
 * Plugin to add support for the GNU Taler payment system to WooCommerce.
 *
 * @package GNUTalerPayment
 */

/**
 * Plugin Name: GNU Taler Payment for WooCommerce
 * Plugin URI: https://git.taler.net/woocommerce-taler
 * Description: This plugin enables payments via the GNU Taler payment system
 * Version: 0.8
 * Author: Dominique Hofmann, Jan Strübin, Christian Grothoff
 * Author URI: https://taler.net/
 *
 * License:           GNU General Public License v2.0
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.html
 * WC requires at least: 2.2
 **/

/*
	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <https://www.gnu.org/licenses/>.
*/

/**
 * Which version of the Taler merchant protocol is implemented
 * by this implementation?  Used to determine compatibility.
 */
define( 'GNU_TALER_MERCHANT_PROTOCOL_CURRENT', 1 );

/**
 * How many merchant protocol versions are we backwards compatible with?
 */
define( 'GNU_TALER_MERCHANT_PROTOCOL_AGE', 0 );

require_once ABSPATH . 'wp-admin/includes/plugin.php';

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */

/**
 * Adds the GNU Taler payment method to the other payment gateways.
 *
 * @param array $gateways all the payment gateways.
 * @return array
 */
function gnutaler_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_GNUTaler_Gateway';
	return $gateways;
}

add_filter( 'woocommerce_payment_gateways', 'gnutaler_add_gateway_class' );

/**
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'gnutaler_init_gateway_class' );


/**
 * Wrapper for the GNU Taler payment method class. Sets up internationalization.
 */
function gnutaler_init_gateway_class() {
	// Setup textdomain for gettext style translations.
	$plugin_rel_path = basename( dirname( __FILE__ ) ) . '/languages';
	load_plugin_textdomain( 'gnutaler', false, $plugin_rel_path );

	// Check if WooCommerce is active, if not then deactivate and show error message.
	if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			sprintf(
				wp_kses(
					/* translators: argument is the link to the plugin page */
					__(
						'<strong>GNU Taler</strong> requires <strong>WooCommerce</strong> plugin to work. Please activate it or install it from <a href="http://wordpress.org/plugins/woocommerce/" target="_blank">here</a>.<br /><br />Back to the WordPress <a href="%s">Plugins page</a>.',
						'gnutaler'
					),
					array(
						'strong' => array(),
						'a'      => array(
							'href'    => array(),
							'_target' => array(),
						),
					)
				),
				esc_url( get_admin_url( null, 'plugins.php' ) )
			)
		);
	}

	/**
	 * GNU Taler Payment Gateway class.
	 *
	 * Handles the payments from the Woocommerce Webshop and sends the transactions to the GNU Taler Backend and the GNU Taler Wallet.
	 */
	class WC_GNUTaler_Gateway extends WC_Payment_Gateway {

		/**
		 * Cached handle to logging class.
		 *
		 * @var Plugin loggger.
		 */
		private static $logger = false;

		/**
		 * True if logging is enabled in our configuration.
		 *
		 * @var Is logging enabled?
		 */
		private static $log_enabled = false;

		/**
		 * Class constructor
		 */
		public function __construct() {
			$this->id     = 'gnutaler'; // Payment gateway plugin ID.
			self::$logger = new WC_logger( $this->id ); // Setup logging.
			$this->icon   = plugins_url( '/assets/images/taler.png', __FILE__ );
			// We cannot use custom fields to show the QR code / do the wallet integration as WC doesn't give us the order_id at that time. Bummer.
			$this->has_fields = false;
			// The following texts will be displayed on the payment plugins settings page.
			$this->method_title       = 'GNU Taler';
			$this->method_description = __( 'This plugin enables payments via the GNU Taler payment system', 'gnutaler' );

			// This gateway can support refunds, saved payment methods.
			$this->supports = array(
				'products',
				'refunds',
			);

			// Setup logging.
			$this->debug       = 'yes' === $this->get_option( 'debug', 'no' );
			self::$log_enabled = $this->debug;

			// Setup 'form_fields'.
			$this->form_fields = array(
				'enabled'                   => array(
					'title'       => __( 'Enable/Disable', 'gnutaler' ),
					'label'       => __( 'Enable GNU Taler Gateway', 'gnutaler' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'title'                     => array(
					'title'       => __( 'Title', 'gnutaler' ),
					'type'        => 'text',
					'description' => __( 'This is what the customer will see when choosing payment methods.', 'gnutaler' ),
					'default'     => 'GNU Taler',
					'desc_tip'    => true,
				),
				'description'               => array(
					'title'       => __( 'Description', 'gnutaler' ),
					'type'        => 'textarea',
					'description' => __( 'This controls the description for the payment option which the customer sees during checkout.', 'gnutaler' ),
					'default'     => __( 'Pay with GNU Taler', 'gnutaler' ),
				),
				'gnu_taler_backend_url'     => array(
					'title'       => __( 'Taler backend URL', 'gnutaler' ),
					'type'        => 'text',
					'description' => __( 'Set the URL of the Taler backend. (Example: https://backend.demo.taler.net/)', 'gnutaler' ),
					'default'     => 'http://backend.demo.taler.net/instances/pos/',
				),
				'GNU_Taler_Backend_API_Key' => array(
					'title'       => __( 'Taler Backend API Key', 'gnutaler' ),
					'type'        => 'text',
					'description' => __( 'Enter your API key to authenticate with the Taler backend.', 'gnutaler' ),
					'default'     => 'Sandbox ApiKey',
				),
				'Order_text'                => array(
					'title'       => __( 'Summary Text of the Order', 'gnutaler' ),
					'type'        => 'text',
					'description' => __( 'Set the text the customer will see when confirming payment. #%%s will be substituted with the order number. (Example: MyShop #%%s)', 'gnutaler' ),
					'default'     => 'WooTalerShop #%s',
				),
				'GNU_Taler_refund_delay'    => array(
					'title'       => __( 'How long should refunds be possible', 'gnutaler' ),
					'type'        => 'number',
					'description' => __( 'Set the number of days a customer has to request a refund', 'gnutaler' ),
					'default'     => '14',
				),
				'debug'                     => array(
					'title'       => __( 'Debug Log', 'woocommerce' ),
					'label'       => __( 'Enable logging', 'woocommerce' ),
					'description' => sprintf(
						/* translators: placeholder will be replaced with the path to the log file */
						__( 'Log GNU Taler events inside %s.', 'gnutaler' ),
						'<code>' . WC_Log_Handler_File::get_log_file_path( 'gnutaler' ) . '</code>'
					),
					'type'        => 'checkbox',
					'default'     => 'no',
				),
			);

			// Load the settings.
			$this->init_settings();
			$this->title                 = $this->get_option( 'title' );
			$this->description           = $this->get_option( 'description' );
			$this->enabled               = $this->get_option( 'enabled' );
			$this->gnu_taler_backend_url = $this->get_option( 'gnu_taler_backend_url' );
			// Remove trailing '/', we add one always ourselves...
			if ( substr( $this->gnu_taler_backend_url, -1 ) === '/' ) {
				$this->gnu_taler_backend_url = substr( $this->gnu_taler_backend_url, 0, -1 );
			}

			// Make transaction ID a link. We use the public version
			// here, as a user clicking on the link could not supply
			// the authorization header.
			// See also: https://woocommerce.wordpress.com/2014/08/05/wc-2-2-payment-gateways-adding-refund-support-and-transaction-ids/.
			$this->view_transaction_url = $this->gnu_taler_backend_url . '/orders/%s';

			// Register handler for the fulfillment URL.
			$hname = 'woocommerce_api_' . strtolower( get_class( $this ) );
			add_action(
				$hname,
				array( &$this, 'fulfillment_url_handler' )
			);

			// This action hook saves the settings.
			add_action(
				'woocommerce_update_options_payment_gateways_' . $this->id,
				array( $this, 'process_admin_options' )
			);

			// Modify WC canonical refund e-mail notifications to add link to order status page.
			// (according to https://www.businessbloomer.com/woocommerce-add-extra-content-order-email/).
			add_action(
				'woocommerce_email_before_order_table',
				array( $this, 'add_content_refund_email' ),
				20,
				4
			);
		}

		/**
		 * Called when WC sends out the e-mail notification for refunds.
		 * Adds a Taler-specific notice for where to click to obtain
		 * the refund.
		 *
		 * @param WC_Order $wc_order      The order.
		 * @param bool     $sent_to_admin Not well documented by WooCommerce.
		 * @param string   $plain_text    The plain text of the email.
		 * @param string   $email         Target email address.
		 */
		public function add_content_refund_email( $wc_order, $sent_to_admin, $plain_text, $email ) {
			if ( 'customer_refunded_order' === $email->id ) {
				$backend_url = $this->gnu_taler_backend_url;
				$wc_order_id = $wc_order->get_order_key() . '-' . $wc_order->get_order_number();
				$refund_url  = $wc_order->get_meta( 'GNU_TALER_REFUND_URL' );
				echo sprintf(
					/* translators: placeholder will be replaced with the refund URL */
					esc_html( __( 'Refund granted. Visit <a href="%1$s">%1$s</a> to obtain the refund.', 'gnutaler' ) ),
					esc_url( $refund_url )
				);
			}
		}

		/**
		 * Processes and saves options.
		 * If there is an error thrown, will continue to save and validate fields, but
		 * will leave the erroring field out.
		 *
		 * @return bool was anything saved?
		 */
		public function process_admin_options() {
			$saved = parent::process_admin_options();

			// Maybe clear logs.
			if ( 'yes' !== $this->get_option( 'debug', 'no' ) ) {
				if ( empty( self::$log ) ) {
					self::$logger = wc_get_logger();
				}
				self::$logger->clear( 'gnutaler' );
			}

			return $saved;
		}

		/**
		 * Required function to add the fields we want to show in the
		 * payment method selection dialog. We show none.
		 */
		public function payment_fields() {
			// We do not have any.
		}

		/**
		 * Callback is called, when the user goes to the fulfillment URL.
		 *
		 * We check that the payment actually was made, and update WC accordingly.
		 * If the order ID is unknown and/or the payment did not succeed, we
		 * redirect to the home page and/or the user's order page (for logged in users).
		 */
		public function fulfillment_url_handler(): void {
			global $woocommerce;

			// We intentionally do NOT verify the nonce here, as this page
			// should work even if the deep link is shared with other users
			// or even non-users.
                        // phpcs:disable WordPress.Security.NonceVerification
			if ( ! isset( $_GET['order_id'] ) ) {
				$this->debug( __( "Lacking 'order_id', forwarding user to neutral page", 'gnutaler' ) );
				if ( is_user_logged_in() ) {
					wp_safe_redirect( get_home_url() . wc_get_page_permalink( 'myaccount' ) );
				} else {
					wp_safe_redirect( get_home_url() . wc_get_page_permalink( 'shop' ) );
				}
				exit;
			}

			// Gets the order id from the fulfillment url.
			$taler_order_id = sanitize_text_field( wp_unslash( $_GET['order_id'] ) );
                        // phpcs:enable
			$order_id_array = explode( '-', $taler_order_id );
			$order_id_name  = $order_id_array[0];
			$order_id       = $order_id_array[1];
			$wc_order       = wc_get_order( $order_id );
			$backend_url    = $this->gnu_taler_backend_url;

			$payment_confirmation = $this->call_api(
				'GET',
				$backend_url . '/private/orders/' . $taler_order_id,
				false
			);
			$payment_body         = $payment_confirmation['message'];
			$payment_http_status  = $payment_confirmation['http_code'];

			switch ( $payment_http_status ) {
				case 200:
					// Here we check what kind of http code came back from the backend.
					$merchant_order_status_response = json_decode(
						$payment_body,
						$assoc                      = true
					);
					if ( ! $merchant_order_status_response ) {
						wc_add_notice(
							__( 'Payment error:', 'gnutaler' ) .
							__( 'backend did not respond', 'gnutaler' )
						);
						$this->notice( __( 'Payment failed: no reply from Taler backend', 'gnutaler' ) );
						wp_safe_redirect( $this->get_return_url( $order_id ) );
						exit;
					}
					if ( 'paid' === $merchant_order_status_response['order_status'] ) {
						$this->notice( __( 'Payment succeeded and the user was forwarded to the order confirmed page', 'gnutaler' ) );
						// Completes the order, storing a transaction ID.
						$wc_order->payment_complete( $taler_order_id );
						// Empties the shopping cart.
						WC()->cart->empty_cart();
					} else {
						wc_add_notice(
							__( 'Payment error:', 'gnutaler' ) .
							__( 'backend did not confirm payment', 'gnutaler' )
						);
						$this->notice( __( 'Backend did not confirm payment', 'gnutaler' ) );
					}
					wp_safe_redirect( $this->get_return_url( $wc_order ) );
					exit;
				default:
					$this->error(
						__( 'An error occurred during the second request to the GNU Taler backend: ', 'gnutaler' )
							. $payment_http_status . ' - ' . $payment_body
					);
					wc_add_notice( __( 'Payment error:', 'gnutaler' ) . $payment_http_status . ' - ' . $payment_body );
					wp_safe_redirect( $this->get_return_url( $order_id ) );
					break;
			}
			$cart_url = $woocommerce->cart->wc_get_cart_url();
			if ( is_set( $cart_url ) ) {
				wp_safe_redirect( get_home_url() . $cart_url );
			} else {
				wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
			}
			exit;
		}

		/**
		 * Sends a request to a url via HTTP.
		 *
		 * Sends a request to a GNU Taler Backend over HTTP and returns the result.
		 * The request can be sent as POST or GET. PATCH is not supported.
		 *
		 * @param string $method POST or GET supported only. Thanks WordPress.
		 * @param string $url    URL for the request to make to the GNU Taler Backend.
		 * @param string $body   The content of the request (for POST).
		 *
		 * @return array The return array will either have the successful return value or a detailed error message.
		 */
		private function call_api( $method, $url, $body ): array {
			$apikey = $this->get_option( 'GNU_Taler_Backend_API_Key' );
			$args   = array(
				'timeout'             => 30, // In seconds.
				'redirection'         => 2, // How often.
				'httpversion'         => '1.1', // Taler will support.
				'user-agent'          => '', // Minimize information leakage.
				'blocking'            => true, // We do nothing without it.
				'headers'             => array(
					'Authorization: ' . $apikey,
				),
				'decompress'          => true,
				'limit_response_size' => 1024 * 1024, // More than enough.
			);
			if ( $body ) {
				$args['body']      = wp_json_encode( $body, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES, 16 );
				$args['headers'][] = 'Content-type: application/json';
				$args['compress']  = true;
			}
			$this->debug( 'Issuing HTTP ' . $method . ' request to ' . $url . ' with options ' . wp_json_encode( $args ) . ' and body ' . $body );

			switch ( $method ) {
				case 'POST':
					$response = wp_remote_post( $url, $args );
					break;
				case 'GET':
					$response = wp_remote_get( $url, $args );
					break;
				default:
					$this->debug( 'HTTP method ' . $method . ' not supported' );
					return null;
			}
			if ( is_wp_error( $response ) ) {
				$error_code = $response->get_error_code();
				$error_data = $response->get_error_data( $error_code );
				$this->warning(
					sprintf(
						/* translators: first placeholder is the error code, second the error data */
						__( 'HTTP failure %1$s with data %2$s', 'gnutaler' ),
						$error_code,
						$error_data
					)
				);

				return array(
					'http_code' => 0,
					'message'   => $error_code,
				);
			}
			$http_code = wp_remote_retrieve_response_code( $response );
			$body      = wp_remote_retrieve_body( $response );
			$this->debug(
				sprintf(
					/* translators: first placeholder is the HTTP status code, second the body of the HTTP reply */
					__( 'HTTP status %1$s with response body %2$s', 'gnutaler' ),
					$http_code,
					$body
				)
			);
			return array(
				'http_code' => $http_code,
				'message'   => $body,
			);
		}

		/**
		 * Checks if the configuration is valid. Used by WC to redirect
		 * the admin to the setup dialog if they enable the backend and
		 * it is not properly configured.
		 */
		public function needs_setup() {
			$backend_url = $this->gnu_taler_backend_url;
				return ! $this->verify_backend_url(
					$backend_url,
					null
				);
		}

		/**
		 * Verifying if the url to the backend given in the plugin options is valid or not.
		 *
		 * @param string $url       URL to the backend.
		 * @param string $ecurrency expected currency of the order.
		 *
		 * @return bool - Returns if valid or not.
		 */
		private function verify_backend_url( $url, $ecurrency ): bool {
			$config             = $this->call_api( 'GET', $url . '/config', false );
			$config_http_status = $config['http_code'];
			$config_body        = $config['message'];
			switch ( $config_http_status ) {
				case 200:
					$info = json_decode( $config_body, $assoc = true );
					if ( ! $info ) {
						$this->error(
							sprintf(
								/* translators: placeholder will be replaced with the URL of the backend */
								__( '/config response of backend at %s did not decode as JSON', 'gnutaler' ),
								$url
							)
						);
						return false;
					}
					$version = $info['version'];
					if ( ! $version ) {
						$this->error(
							sprintf(
								/* translators: placeholder will be replaced with the URL of the backend */
								__( "No 'version' field in /config reply from Taler backend at %s", 'gnutaler' ),
								$url
							)
						);
						return false;
					}
					$ver      = explode( ':', $version, 3 );
					$current  = $ver[0];
					$revision = $ver[1];
					$age      = $ver[2];
					if ( ( ! is_numeric( $current ) )
						 || ( ! is_numeric( $revision ) )
						 || ( ! is_numeric( $age ) )
					   ) {
						$this->error(
							sprintf(
								/* translators: placeholder will be replaced with the (malformed) version number */
								__( "/config response at backend malformed: '%s' is not a valid version", 'gnutaler' ),
								$version
							)
						);
						return false;
					}
					if ( GNU_TALER_MERCHANT_PROTOCOL_CURRENT < $current - $age ) {
						// Our implementation is too old!
						$this->error(
							sprintf(
								/* translators: placeholder will be replaced with the version number */
								__( 'Backend protocol version %s is too new: please update the GNU Taler plugin', 'gnutaler' ),
								$version
							)
						);
						return false;
					}
					if ( GNU_TALER_MERCHANT_PROTOCOL_CURRENT - GNU_TALER_MERCHANT_PROTOCOL_AGE > $current ) {
						// Merchant implementation is too old!
						$this->error(
							sprintf(
								/* translators: placeholder will be replaced with the version number */
								__( 'Backend protocol version %s unsupported: please update the backend', 'gnutaler' ),
								$version
							)
						);
						return false;
					}
					$currency = $info['currency'];
					if ( ( ! is_null( $ecurrency ) ) && ( 0 !== strcasecmp( $currency, $ecurrency ) ) ) {
						$this->error(
							sprintf(
								/* translators: first placeholder is the Taler backend currency, second the expected currency from WooCommerce */
								__( 'Backend currency %1$s does not match order currency %2$s', 'gnutaler' ),
								$currency,
								$ecurrency
							)
						);
						return false;
					}
					$this->debug(
						sprintf(
							/* translators: placeholder will be replaced with the URL of the backend */
							__( '/config check for Taler backend at %s succeeded', 'gnutaler' ),
							$url
						)
					);
					return true;
				default:
					$this->error(
						sprintf(
							/* translators: placeholder will be replaced with the HTTP status code returned by the backend */
							__( 'Backend failed /config request with unexpected HTTP status %s', 'gnutaler' ),
							$config_http_status
						)
					);
					return false;
			}
		}

		/**
		 * Processes the payment after the checkout
		 *
		 * If the payment process finished successfully the user is being redirected to its GNU Taler Wallet.
		 * If an error occurs it returns void and throws an error.
		 *
		 * @param string $order_id ID of the order to get the Order from the WooCommerce Webshop.
		 *
		 * @return array|void - Array with result => success and redirection url otherwise it returns void.
		 */
		public function process_payment( $order_id ) {
			// We need the order ID to get any order detailes.
			$wc_order = wc_get_order( $order_id );

			// Gets the url of the backend from the WooCommerce Settings.
			$backend_url = $this->gnu_taler_backend_url;

			// Log entry that the customer started the payment process.
			$this->info( __( 'User started the payment process with GNU Taler.', 'gnutaler' ) );

			if ( ! $this->verify_backend_url( $backend_url, $wc_order->get_currency() ) ) {
				wc_add_notice( __( 'Something went wrong please contact the system administrator of the webshop and send the following error: GNU Taler backend URL invalid', 'gnutaler' ), 'error' );
				$this->error( __( 'Checkout process failed: Invalid backend url.', 'gnutaler' ) );
				return;
			}
			$order_json = $this->convert_to_checkout_json( $order_id );

			$this->info( __( 'Sending POST /private/orders request send to Taler backend', 'gnutaler' ) );
			$order_confirmation = $this->call_api(
				'POST',
				$backend_url . '/private/orders',
				$order_json
			);
			$order_body         = $order_confirmation['message'];
			$order_http_status  = $order_confirmation['http_code'];
			switch ( $order_http_status ) {
				case 200:
					$post_order_response = json_decode( $order_body, $assoc = true );
					if ( ! $post_order_response ) {
						$this->error(
							__( 'POST /private/orders request to Taler backend returned 200 OK, but not a JSON body: ', 'gnutaler' )
							. $order_body
						);
							wc_add_notice( __( 'Malformed response from Taler backend. Please contact the system administrator.' ) );
						$wc_order->set_status( 'cancelled' );
						return;
					}
					$taler_order_id    = $post_order_response ['order_id'];
					$taler_order_token = $post_order_response ['token'];
					if ( ! $taler_order_id ) {
						$this->error(
							__( 'Response to POST /private/orders request to Taler backend lacks order_id field: ', 'gnutaler' )
							. $order_body
						);
												wc_add_notice( __( 'Malformed response from Taler backend. Please contact the system administrator.', 'gnutaler' ) );
						$wc_order->set_status( 'cancelled' );
						return;
					}
					$this->info( __( 'POST /private/orders successful. Redirecting user to Taler Backend.', 'gnutaler' ) );
					return array(
						'result'   => 'success',
						'redirect' => $backend_url . '/orders/' . $taler_order_id . '?token=' . $taler_order_token,
					);
				case 404:
					$post_order_error = json_decode( $order_body, $assoc = true );
					if ( ! $post_order_error ) {
						$this->error(
							__( 'POST /private/orders request to Taler backend returned 404, but not a JSON body: ', 'gnutaler' )
							. $order_body
						);
						wc_add_notice( __( 'Malformed response from Taler backend. Please contact the system administrator.', 'gnutaler' ) );
						$wc_order->set_status( 'cancelled' );
						return;
					}
					$this->error(
						__( 'POST /private/orders request to Taler backend failed: ', 'gnutaler' )
										. $post_order_error['code'] . '('
						. $order_http_status . '): ' . $order_body
					);
					wc_add_notice( __( 'Taler backend not configured correctly. Please contact the system administrator.', 'gnutaler' ) );
					$wc_order->set_status( 'cancelled' );
					return;
				case 410:
					// We don't use inventory management via GNU Taler's backend, so this error should never apply.
					// Handle with 'default' case below.
				default:
					$this->error(
						__( 'POST /private/orders request to Taler backend failed: ', 'gnutaler' )
								. $post_order_error['code'] . '('
							. $order_http_status . '): '
							. $order_body
					);
					wc_add_notice( __( 'Unexpected problem with the Taler backend. Please contact the system administrator.', 'gnutaler' ) );
					$wc_order->set_status( 'cancelled' );
					return;
			}
		}

		/**
		 * Converts the order into a JSON format that can be send to the GNU Taler Backend.
		 *
		 * @param string $order_id ID of the order to get the Order from the WooCommerce Webshop.
		 *
		 * @return array - return the JSON Format.
		 */
		public function convert_to_checkout_json( $order_id ): array {
			$wc_order                = wc_get_order( $order_id );
			$wc_order_total_amount   = $wc_order->get_total();
			$wc_order_currency       = $wc_order->get_currency();
			$wc_cart                 = WC()->cart->get_cart();
			$wc_order_id             = $wc_order->get_order_key() . '-' . $wc_order->get_order_number();
			$wc_order_products_array = $this->mutate_products_to_json_format( $wc_cart, $wc_order_currency );
			$refund_delay            = $this->get_option( 'GNU_Taler_refund_delay' );
			$order_json              = array(
				'order' => array(
					'amount'            => $wc_order_currency . ':' . $wc_order_total_amount,
					'summary'           => sprintf(
						$this->get_option( 'Order_text' ),
						$wc_order->get_order_number()
					),
					// NOTE: This interacts with the 'add_action' call
					// to invoke the 'fulfillment_url_handler' when the
					// user goes to this URL!
					'fulfillment_url'   => get_home_url()
							. '/?wc-api='
						. strtolower( get_class( $this ) )
						. '&order_id='
						. $wc_order_id,
					'order_id'          => $wc_order_id,
					'products'          => $wc_order_products_array,
					'delivery_location' => $this->mutate_shipping_information_to_json_format( $wc_order ),
				),
			);
			if ( isset( $refund_delay ) ) {
				$order_json['refund_delay'] = array(
					'd_ms' => 1000 * 60 * 60 * 24 * intval( $refund_delay ),
				);
			}
			return $order_json;
		}

		/**
		 * Mutates the products in the cart into a format which can be included in a JSON file.
		 *
		 * @param WC_Cart $wc_cart          The content of the WooCommerce Cart.
		 * @param string  $wc_order_currency The currency the WooCommerce Webshop uses.
		 *
		 * @return array - Returns an array of products.
		 */
		private function mutate_products_to_json_format( $wc_cart, $wc_order_currency ): array {
			$wc_order_products_array = array();
			foreach ( $wc_cart as $product ) {
				$wc_order_products_array[] = array(
					'description' => $product['data']->get_title(),
					'quantity'    => $product['quantity'],
					'price'       => $wc_order_currency . ':' . $product['data']->get_price(),
					'product_id'  => strval( $product['data']->get_id() ),
				);
			}
			return $wc_order_products_array;
		}

		/**
		 * Processes the refund transaction if requested by the system administrator of the webshop
		 *
		 * If the refund request is finished successfully it returns an refund url, which can be send to the customer to finish the refund transaction.
		 * If an error it will throw a WP_Error message and inform the system administrator.
		 *
		 * @param WC_Order $wc_order The WooCommerce order object we are processing.
		 *
		 * @return array
		 */
		private function mutate_shipping_information_to_json_format( $wc_order ): array {
			$whitechar_encounter        = false;
			$shipping_address_street    = '';
			$shipping_address_street_nr = '';

			$store_address          = $wc_order->get_shipping_address_1();
			$store_address_inverted = strrev( $store_address );
			$store_address_array    = str_split( $store_address_inverted );

			// Split the address into street and street number.
			foreach ( $store_address_array as $char ) {
				if ( ! $whitechar_encounter ) {
					$shipping_address_street .= $char;
				} elseif ( ctype_space( $char ) ) {
					$whitechar_encounter = true;
				} else {
					$shipping_address_street .= $char;
				}
			}
			$ret = array(
				'country'             => $wc_order->get_shipping_country(),
				'country_subdivision' => $wc_order->get_shipping_state(),
				'town'                => $wc_order->get_shipping_city(),
				'post_code'           => $wc_order->get_shipping_postcode(),
				'street'              => $shipping_address_street,
				'building_number'     => $shipping_address_street_nr,
			);
			if ( null !== $wc_order->get_shipping_address_2() ) {
				$address_lines        = array(
					$wc_order->get_shipping_address_1(),
					$wc_order->get_shipping_address_2(),
				);
				$ret['address_lines'] = $address_lines;
			}
			return $ret;
		}

		/**
		 * Processes the refund transaction if requested by the system administrator of the webshop
		 *
		 * If the refund request is finished successfully it returns an refund url, which can be send to the customer to finish the refund transaction.
		 * If an error it will throw a WP_Error message and inform the system administrator.
		 *
		 * @param string $order_id Order id for logging.
		 * @param string $amount Amount that is requested to be refunded.
		 * @param string $reason Reason for the refund request.
		 *
		 * @return bool|WP_Error - Returns true or throws an WP_Error message in case of error.
		 */
		public function process_refund( $order_id, $amount = null, $reason = '' ) {
			$wc_order = wc_get_order( $order_id );

			$this->info(
				sprintf(
					/* translators: first placeholder is the numeric amount, second the currency, and third the reason for the refund */
					__( 'Refund process started with the refunded amount %1$s %2$s and the reason %3$s.' ),
					$amount,
					$wc_order->get_currency(),
					$reason
				)
			);

			// Gets the url of the backend from the WooCommerce Settings.
			$backend_url = $this->gnu_taler_backend_url;

			// Get the current status of the order.
			$wc_order_status = $wc_order->get_status();

			// Checks if current status is already set as paid.
			if ( ! ( 'processing' === $wc_order_status
				|| 'on hold' === $wc_order_status
				|| 'completed' === $wc_order_status )
			) {
				$this->error( __( 'The status of the order does not allow a refund', 'gnutaler' ) );
				return new WP_Error( 'error', __( 'The status of the order does not allow for a refund.', 'gnutaler' ) );
			}

			$refund_request = array(
				'refund' => $wc_order->get_currency() . ':' . $amount,
				'reason' => $reason,
			);
			$wc_order_id    = $wc_order->get_order_key() . '-' . $wc_order->get_order_number();
			$refund_result  = $this->call_api(
				'POST',
				$backend_url . '/private/orders/' . $wc_order_id . '/refund',
				$refund_request
			);

			$refund_http_status = $refund_result['http_code'];
			$refund_body        = $refund_result['message'];
			switch ( $refund_http_status ) {
				case 200:
					$refund_response = json_decode( $refund_body, $assoc = true );
					if ( ! $refund_response ) {
						$this->error( __( 'Malformed 200 response from Taler backend: not even in JSON', 'gnutaler' ) );
						return new WP_Error( 'error', __( 'Malformed response from Taler backend', 'gnutaler' ) );
					}
					$refund_uri = $refund_response['taler_refund_uri'];
					$h_contract = $refund_response['h_contract'];
					if ( ( ! $refund_uri ) || ( ! $h_contract ) ) {
						$this->error( __( 'Malformed 200 response from Taler backend: lacks taler_refund_uri', 'gnutaler' ) );
						return new WP_Error( 'error', __( 'Malformed response from Taler backend', 'gnutaler' ) );
					}
					$refund_url = $backend_url
						. '/orders/'
						. $wc_order_id
						. '?h_contract='
						. $h_contract;
					$wc_order->add_meta_data( 'GNU_TALER_REFUND_URL', $refund_url );
					$wc_order->update_status( 'refunded' );
					$this->debug(
						sprintf(
							/* translators: argument is the Taler refund URI */

							__( 'Received refund URI %s from Taler backend', 'gnutaler' ),
							$refund_uri
						)
					);
					$this->notice(
						sprintf(
							/* translators: argument is the Taler refund URL */
							__( 'The user must visit %s to obtain the refund', 'gnutaler' ),
							$refund_url
						)
					);
					return true;
				case 403:
					return new WP_Error(
						'error',
						__( 'Refunds are disabled for this order. Check the refund_delay option for the Taler payment plugin.', 'gnutaler' )
					);
				case 404:
					$refund_error = json_decode( $refund_body, $assoc = true );
					if ( ! $refund_error ) {
						return new WP_Error(
							'error',
							sprintf(
								/* translators: argument is the HTTP status returned by the backend */
								__( 'Unexpected failure %s without Taler error code from Taler backend', 'gnutaler' ),
								$refund_http_status
							)
						);
					}
					$ec = $refund_error['code'];
					switch ( $ec ) {
						case 2000: // TALER_EC_INSTANCE_UNKNOWN!
							return new WP_Error(
								'error',
								__( 'Instance unknown reported by Taler backend', 'gnutaler' )
							);
						case 2601: // TALER_EC_REFUND_ORDER_ID_UNKNOWN!
							return new WP_Error(
								'error',
								__( 'Order unknown reported by Taler backend', 'gnutaler' )
							);
						default:
							return new WP_Error(
								'error',
								sprintf(
									/* translators: placeholder will be replaced with the numeric GNU Taler error code */
									__( 'Unexpected error %s reported by Taler backend', 'gnutaler' ),
									$ec
								)
							);
					}
					// This line is unreachable.
				case 409:
					return new WP_Error(
						'error',
						__( 'Requested refund amount exceeds original payment. This is not allowed!', 'gnutaler' )
					);
				case 410:
					return new WP_Error(
						'error',
						__( 'Wire transfer already happened. It is too late for a refund with Taler!', 'gnutaler' )
					);
				default:
					$refund_error = json_decode( $refund_body, $assoc = true );
					if ( ! $refund_error ) {
						$ec = $refund_error['code'];
					} else {
						$ec = 0;
					}
					return new WP_Error(
						'error',
						sprintf(
							/* translators: first placeholder is the HTTP status code, second the numeric GNU Taler error code */
							__( 'Unexpected failure %1$s/%2$s from Taler backend', 'gnutaler' ),
							$refund_http_status,
							$ec
						)
					);
			}
		}

		/**
		 * Log $msg for debugging
		 *
		 * @param string $msg message to log.
		 */
		private function debug( $msg ) : void {
			$this->log( 'debug', $msg );
		}

		/**
		 * Log $msg as a informational
		 *
		 * @param string $msg message to log.
		 */
		private function info( $msg ) : void {
			$this->log( 'info', $msg );
		}

		/**
		 * Log $msg as a notice
		 *
		 * @param string $msg message to log.
		 */
		private function notice( $msg ) : void {
			$this->log( 'notice', $msg );
		}

		/**
		 * Log $msg as a warning.
		 *
		 * @param string $msg message to log.
		 */
		private function warning( $msg ) : void {
			$this->log( 'warning', $msg );
		}

		/**
		 * Log $msg as an error
		 *
		 * @param string $msg message to log.
		 */
		private function error( $msg ) : void {
			$this->log( 'error', $msg );
		}

		/**
		 * Log $msg at log $level.
		 *
		 * @param string $level log level to use when logging.
		 * @param string $msg message to log.
		 */
		private function log( $level, $msg ) {
			if ( ! self::$log_enabled ) {
					return;
			}
			if ( function_exists( 'wp_get_current_user()' ) ) {
				$user_id = wp_get_current_user();
				if ( ! isset( $user_id ) ) {
					$user_id = __( '<user ID not set>', 'gnutaler' );
				}
			} else {
				$user_id = 'Guest';
			}
			// We intentionally do NOT verify the nonce here, as logging
			// should always work.
                        // phpcs:disable WordPress.Security.NonceVerification
			$order_id = sanitize_text_field( wp_unslash( $_GET['order_id'] ) );
                        // phpcs:enable
			if ( empty( self::$logger ) ) {
				self::$logger = wc_get_logger();
			}
			self::$logger->log( $level, $user_id . '-' . $order_id . ': ' . $msg, array( 'source' => 'gnutaler' ) );
		}

	}
}
