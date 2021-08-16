<?php
/**
 * Zarinpal payment gateway class.
 *
 * @author   erfan darvishnia
 * @link 	 https://zarinpal.com
 * @package  LearnPress/Zarinpal/Classes
 * @version  1.1
 */
// session_start();

// Prevent loading this file directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'LP_Gateway_Zarinpal' ) ) {
	/**
	 * Class LP_Gateway_Zarinpal
	 */
	class LP_Gateway_Zarinpal extends LP_Gateway_Abstract {

		/**
		 * @var array
		 */
		private $form_data = array();
		
		/**
		 * @var string
		 */
		private $startPay = 'https://www.zarinpal.com/pg/StartPay/';
		
		/**
		 * @var string
		 */
		private $verifyUrl = 'https://api.zarinpal.com/pg/v4/payment/verify.json';
		
		/**
		 * @var string
		 */
		private $restPaymentRequestUrl = 'https://api.zarinpal.com/pg/v4/payment/request.json';
		
		/**
		 * @var string
		 */
		private $restPaymentVerification = 'https://api.zarinpal.com/pg/v4/payment/verify.json';
		
		/**
		 * @var string
		 */
		private $soap = false;
		
		/**
		 * @var string
		 */
		private $merchant = null;

		/**
		 * @var array|null
		 */
		protected $settings = null;

		/**
		 * @var null
		 */
		protected $order = null;

		/**
		 * @var null
		 */
		protected $posted = null;

		/**
		 *
		 * @var string
		 */
		protected $authority = null;

		/**
		 * LP_Gateway_Zarinpal constructor.
		 */
		public function __construct() {
			$this->id = 'zarinpal';

			$this->method_title       =  __( 'Zarinpal', 'learnpress-zarinpal' );;
			$this->method_description = __( 'Make a payment with Zarinpal.', 'learnpress-zarinpal' );
			$this->icon               = '';

			// Get settings
			$this->title       = LP()->settings->get( "{$this->id}.title", $this->method_title );
			$this->description = LP()->settings->get( "{$this->id}.description", $this->method_description );

			$settings = LP()->settings;

			// Add default values for fresh installs
			if ( $settings->get( "{$this->id}.enable" ) ) {
				$this->settings                     = array();
				$this->settings['merchant']        = $settings->get( "{$this->id}.merchant" );
			}
			
			$this->merchant = $this->settings['merchant'];
			
			if ( did_action( 'learn_press/zarinpal-add-on/loaded' ) ) {
				return;
			}

			// check payment gateway enable
			add_filter( 'learn-press/payment-gateway/' . $this->id . '/available', array(
				$this,
				'zarinpal_available'
			), 10, 2 );

			do_action( 'learn_press/zarinpal-add-on/loaded' );

			parent::__construct();
			
			// web hook
			if ( did_action( 'init' ) ) {
				$this->register_web_hook();
			} else {
				add_action( 'init', array( $this, 'register_web_hook' ) );
			}
			add_action( 'learn_press_web_hooks_processed', array( $this, 'web_hook_process_zarinpal' ) );
			
			add_action("learn-press/before-checkout-order-review", array( $this, 'error_message' ));
		}
		
		/**
		 * Register web hook.
		 *
		 * @return array
		 */
		public function register_web_hook() {
			learn_press_register_web_hook( 'zarinpal', 'learn_press_zarinpal' );
		}
			
		/**
		 * Admin payment settings.
		 *
		 * @return array
		 */
		public function get_settings() {

			return apply_filters( 'learn-press/gateway-payment/zarinpal/settings',
				array(
					array(
						'title'   => __( 'Enable', 'learnpress-zarinpal' ),
						'id'      => '[enable]',
						'default' => 'no',
						'type'    => 'yes-no'
					),
					array(
						'type'       => 'text',
						'title'      => __( 'Title', 'learnpress-zarinpal' ),
						'default'    => __( 'Zarinpal', 'learnpress-zarinpal' ),
						'id'         => '[title]',
						'class'      => 'regular-text',
						'visibility' => array(
							'state'       => 'show',
							'conditional' => array(
								array(
									'field'   => '[enable]',
									'compare' => '=',
									'value'   => 'yes'
								)
							)
						)
					),
					array(
						'type'       => 'textarea',
						'title'      => __( 'Description', 'learnpress-zarinpal' ),
						'default'    => __( 'Pay with Zarinpal', 'learnpress-zarinpal' ),
						'id'         => '[description]',
						'editor'     => array(
							'textarea_rows' => 5
						),
						'css'        => 'height: 100px;',
						'visibility' => array(
							'state'       => 'show',
							'conditional' => array(
								array(
									'field'   => '[enable]',
									'compare' => '=',
									'value'   => 'yes'
								)
							)
						)
					),
					array(
						'title'      => __( 'Merchant ID', 'learnpress-zarinpal' ),
						'id'         => '[merchant]',
						'type'       => 'text',
						'visibility' => array(
							'state'       => 'show',
							'conditional' => array(
								array(
									'field'   => '[enable]',
									'compare' => '=',
									'value'   => 'yes'
								)
							)
						)
					)
				)
			);
		}

		/**
		 * Payment form.
		 */
		public function get_payment_form() {
			ob_start();
			$template = learn_press_locate_template( 'form.php', learn_press_template_path() . '/addons/zarinpal-payment/', LP_ADDON_ZARINPAL_PAYMENT_TEMPLATE );
			include $template;

			return ob_get_clean();
		}

		/**
		 * Error message.
		 *
		 * @return array
		 */
		public function error_message() {
			if(!isset($_SESSION))
				session_start();
			if(isset($_SESSION['zarinpal_error']) && intval($_SESSION['zarinpal_error']) === 1) {
				$_SESSION['zarinpal_error'] = 0;
				$template = learn_press_locate_template( 'payment-error.php', learn_press_template_path() . '/addons/zarinpal-payment/', LP_ADDON_ZARINPAL_PAYMENT_TEMPLATE );
				include $template;
			}
		}
		
		/**
		 * @return mixed
		 */
		public function get_icon() {
			if ( empty( $this->icon ) ) {
				$this->icon = LP_ADDON_ZARINPAL_PAYMENT_URL . 'assets/images/zarinpal.png';
			}

			return parent::get_icon();
		}

		/**
		 * Check gateway available.
		 *
		 * @return bool
		 */
		public function zarinpal_available() {

			if ( LP()->settings->get( "{$this->id}.enable" ) != 'yes' ) {
				return false;
			}

			return true;
		}
		
		/**
		 * Get form data.
		 *
		 * @return array
		 */
		public function get_form_data() {
			if ( $this->order ) {
				$user            = learn_press_get_current_user();
				$currency_code = learn_press_get_currency()  ;
				if ($currency_code == 'IRR') {
					$amount = $this->order->order_total;
				} else {
					$amount = $this->order->order_total * 10;
				}

				$this->form_data = array(
					'amount'      => $amount,
					'currency'    => strtolower( learn_press_get_currency() ),
					'token'       => $this->token,
					'description' => sprintf( __("Charge for %s","learnpress-zarinpal"), $user->get_data( 'email' ) ),
					'customer'    => array(
						'name'          => $user->get_data( 'display_name' ),
						'billing_email' => $user->get_data( 'email' ),
					),
					'errors'      => isset( $this->posted['form_errors'] ) ? $this->posted['form_errors'] : ''
				);
			}

			return $this->form_data;
		}
		
		/**
		 * Validate form fields.
		 *
		 * @return bool
		 * @throws Exception
		 * @throws string
		 */
		public function validate_fields() {
			$posted        = learn_press_get_request( 'learn-press-zarinpal' );
			$email   = !empty( $posted['email'] ) ? $posted['email'] : "";
			$mobile  = !empty( $posted['mobile'] ) ? $posted['mobile'] : "";
			$error_message = array();
			if ( !empty( $email ) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
				$error_message[] = __( 'Invalid email format.', 'learnpress-zarinpal' );
			}
			if ( !empty( $mobile ) && !preg_match("/^(09)(\d{9})$/", $mobile)) {
				$error_message[] = __( 'Invalid mobile format.', 'learnpress-zarinpal' );
			}
			
			if ( $error = sizeof( $error_message ) ) {
				throw new Exception( sprintf( '<div>%s</div>', join( '</div><div>', $error_message ) ), 8000 );
			}
			$this->posted = $posted;

			return $error ? false : true;
		}
		
		/**
		 * Zarinpal payment process.
		 *
		 * @param $order
		 *
		 * @return array
		 * @throws string
		 */
		public function process_payment( $order ) {
			$this->order = learn_press_get_order( $order );
			$zarinpal = $this->get_zarinpal_authority();
			$gateway_url = $this->startPay.$this->authority;
			$json = array(
				'result'   => $zarinpal ? 'success' : 'fail',
				'redirect'   => $zarinpal ? $gateway_url : ''
			);

			return $json;
		}

		
		/**
		 * Get Zarinpal authority.
		 *
		 * @return bool|object
		 * @throws string
		 */
		public function get_zarinpal_authority() {
			if ( $this->get_form_data() ) {
				$checkout = LP()->checkout();


                $data = array("merchant_id" => $this->merchant,
                    "amount" => $this->form_data['amount'],
                    "callback_url" => get_site_url() . '/?' . learn_press_get_web_hook( 'zarinpal' ) . '=1&order_id='.$this->order->get_id(),
                    "description" => $this->form_data['description'],
                    "metadata" => [ "email" => (!empty($this->posted['email'])) ? $this->posted['email'] : "0"
                        ,"mobile"=> (!empty($this->posted['mobile'])) ? $this->posted['mobile'] : "0"],
                );
								

                $jsonData = json_encode($data);
                $ch = curl_init('https://api.zarinpal.com/pg/v4/payment/request.json');
                curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v1');
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($jsonData)
                ));

                $result = curl_exec($ch);
                $err = curl_error($ch);
                $result = json_decode($result, true, JSON_PRETTY_PRINT);
                curl_close($ch);



                if ($err) {
                    echo "cURL Error #:" . $err;
                } else {
                    if (empty($result['errors'])) {
                        if ($result['data']['code'] == 100) {

                            $this->authority = $result['data']["authority"];
                            return true;
                          //  header('Location: https://www.zarinpal.com/pg/StartPay/' . $result['data']["authority"]);
                        }
                    } else {
                        echo'Error Code: ' . $result['errors']['code'];
                        echo'message: ' .  $result['errors']['message'];

                    }
                }
			}
			return false;
		}

		/**
		 * Handle a web hook
		 *
		 */
		public function web_hook_process_zarinpal() {
			$request = $_REQUEST;
			if(isset($request['learn_press_zarinpal']) && intval($request['learn_press_zarinpal']) === 1) {
				if ($request['Status'] == 'OK') {
					$order = LP_Order::instance( $request['order_id'] );
					$currency_code = learn_press_get_currency();
					if ($currency_code == 'IRR') {
						$amount = $order->order_total;
					} else {
						$amount = $order->order_total * 10;
					}	



                    $Authority = $_GET['Authority'];
                    $data = array("merchant_id" => $this->merchant, "authority" => $Authority, "amount" => $amount,);
                    $result = $this->rest_payment_verification($data);





                        if ($result['data']['code'] == 100) {
                            //echo 'Transation success. RefID:' . $result['data']['ref_id'];
                            $request["RefID"] = $result['data']['ref_id'];
                            $this->authority = intval($_GET['Authority']);
                            $this->payment_status_completed($order , $request);
                            wp_redirect(esc_url( $this->get_return_url( $order ) ));
                            exit();
                        } else {
                            echo'code: ' . $result['errors']['code'];
                            echo'message: ' .  $result['errors']['message'];
                        }


				}
				
				if(!isset($_SESSION))
					session_start();
				$_SESSION['zarinpal_error'] = 1;
				
				wp_redirect(esc_url( learn_press_get_page_link( 'checkout' ) ));
				exit();
			}
		}
		
		/**
		 * Zarinpal REST payment request
		 *
		 */ 
		public function rest_payment_request($data) {
			$jsonData = json_encode($data);
			$ch = curl_init($this->restPaymentRequestUrl);
			curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v1');
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
			curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				'Content-Type: application/json',
				'Content-Length: ' . strlen($jsonData)
			));
			$result = curl_exec($ch);
			$err = curl_error($ch);
			$result = json_decode($result);
			curl_close($ch);
			if ($err) {
				$result = (object) array("success"=>0);
			}
			return $result;
		}
		
		/**
		 * Zarinpal REST payment verification
		 *
		 */ 
		public function rest_payment_verification($data) {
			$jsonData = json_encode($data);
			$ch = curl_init($this->restPaymentVerification);
			curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v1');
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
			curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				'Content-Type: application/json',
				'Content-Length: ' . strlen($jsonData)
			));
			$result = curl_exec($ch);
			$err = curl_error($ch);
			$result = json_decode($result);
			curl_close($ch);
			if ($err) {
				$result = (object) array("success"=>0);
			}
			return $result;
		}
		
		/**
		 * Handle a completed payment
		 *
		 * @param LP_Order
		 * @param request
		 */
		protected function payment_status_completed( $order, $request ) {

			// order status is already completed
			if ( $order->has_status( 'completed' ) ) {
				exit;
			}

			$this->payment_complete( $order, ( !empty( $request["RefID"] ) ? $request["RefID"] : '' ), __( 'Payment has been successfully completed', 'learnpress-zarinpal' ) );

            update_post_meta( $order->get_id(), '_zarinpal_RefID', $request['RefID'] );
            update_post_meta( $order->get_id(), '_zarinpal_authority', $request['Authority'] );
		}

		/**
		 * Handle a pending payment
		 *
		 * @param  LP_Order
		 * @param  request
		 */
		protected function payment_status_pending( $order, $request ) {
			$this->payment_status_completed( $order, $request );
		}

		/**
		 * @param        LP_Order
		 * @param string $txn_id
		 * @param string $note - not use
		 */
		public function payment_complete( $order, $trans_id = '', $note = '' ) {
			$order->payment_complete( $trans_id );
		}
	}
}