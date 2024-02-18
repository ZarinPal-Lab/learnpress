<?php

require_once '../../../../wp-load.php';

class Zarinpal_Callback_Handler
{

    /**
     * @throws Exception
     */
    public function __construct()
    {
        $this->handle_callback();
    }

    /**
     * @throws Exception
     */
    public function handle_callback()
    {
        $request = $_REQUEST;

        if (isset($request['learn_press_zarinpal']) && intval($request['learn_press_zarinpal']) === 1) {
            $order   = LP_Order::instance($request['order_id']);
            $setting = LP()->settings;
            if (isset($request['Status']) && isset($request['Authority'])) {
                $data = array(
                    "merchant_id" => $setting->get('zarinpal.merchant'),
                    "authority"   => $_GET['Authority'],
                    "amount"      => $order->get_total(),
                );

                $result = $this->rest_payment_verification($data);
                if (empty($result['errors'])) {
                    if ($result['data']['code'] == 100) {
                        $request["RefID"] = $result['data']['ref_id'];
                        $this->authority  = $_GET['Authority'];
                        $this->payment_status_completed($order, $request);
                        $this->redirect_to_return_url($order);
                    } elseif ($result['data']['code'] == 101) {
                        $this->redirect_to_return_url($order);
                    }
                } else {
                    echo 'Error Code : ' . $result['errors']['code'];
                    $this->redirect_to_return_url($order);
                }
            } else {
                $this->redirect_to_return_url($order);
            }

            if ( ! isset($_SESSION)) {
                session_start();
            }
            $_SESSION['zarinpal_error'] = 1;
            $this->redirect_to_checkout();
        } else {
            $this->redirect_to_home();
        }
        exit();
    }

    public function rest_payment_verification($data)
    {
        $jsonData = json_encode($data);
        $ch       = curl_init('https://api.zarinpal.com/pg/v4/payment/verify.json');
        curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v1');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonData),
        ));
        $result = curl_exec($ch);
        $err    = curl_error($ch);
        $result = json_decode($result, true);
        curl_close($ch);
        if ($err) {
            $result = (object)array("success" => 0);
        }

        return $result;
    }

    public function payment_status_completed($order, $request)
    {
        if ($order->has_status('completed')) {
            exit;
        }

        $this->payment_complete(
            $order,
            (! empty($request["RefID"]) ? $request["RefID"] : ''),
            __('Payment has been successfully completed', 'learnpress-zarinpal')
        );

        update_post_meta($order->get_id(), '_zarinpal_RefID', $request['RefID']);
        update_post_meta($order->get_id(), '_zarinpal_authority', $request['Authority']);
    }

    public function payment_complete($order, $trans_id = '', $note = '')
    {
        $order->payment_complete($trans_id);
    }

    public function redirect_to_return_url($order = null)
    {
        if ($order) {
            $return_url = $order->get_checkout_order_received_url();
        } else {
            $return_url = learn_press_get_endpoint_url('lp-order-received', '', learn_press_get_page_link('checkout'));
        }

        wp_redirect(apply_filters('learn_press_get_return_url', $return_url, $order));
        exit();
    }

    public function redirect_to_checkout()
    {
        wp_redirect(esc_url(learn_press_get_page_link('checkout')));
        exit();
    }

    public function redirect_to_home()
    {
        wp_redirect(home_url());
        exit();
    }

    public function error_message()
    {
        if ( ! isset($_SESSION)) {
            session_start();
        }
        if (isset($_SESSION['zarinpal_error']) && intval($_SESSION['zarinpal_error']) === 1) {
            $_SESSION['zarinpal_error'] = 0;
            $template                   = learn_press_locate_template(
                'payment-error.php',
                learn_press_template_path() . '/addons/zarinpal-payment/',
                LP_ADDON_ZARINPAL_PAYMENT_TEMPLATE
            );
            include $template;
        }
    }
}

new Zarinpal_Callback_Handler();
