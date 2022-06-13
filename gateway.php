<?php

/**
 * @package Airtel for WooCommerce
 * @subpackage Payment Gateway Class
 * @author Osen Concepts < hi@osen.co.ke >
 * @since 1.0.0
 */

class WC_Airtel_Gateway extends WC_Payment_Gateway
{
    /**
     * Class constructor, more about it in Step 3
     */
    public function __construct()
    {
        $this->id                 = 'airtel';
        $this->icon               = apply_filters('woocommerce_airtel_icon', plugins_url('airtel.png', __FILE__));
        $this->has_fields         = true;
        $this->method_title       = 'Airtel Money (Africa)';
        $this->method_description = 'Receive and make payments via Airtel Money in ' . WC()->countries->countries[WC()->countries->get_base_country()];
        $this->supports           = array('products');

        // Method with all the options fields
        $this->init_form_fields();
        $this->init_settings();

        $this->title         = $this->get_option('title');
        $this->description   = $this->get_option('description');
        $this->enabled       = $this->get_option('enabled');
        $this->client_id     = $this->get_option('client_id');
        $this->client_secret = $this->get_option('client_secret');
        $this->testmode      = 'yes' === $this->get_option('testmode');
        $this->baseurl       = 'yes' === $this->get_option('testmode') ? 'https://openapiuat.airtel.africa/' : 'https://openapi.airtel.africa/';

        // This action hook saves the settings
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        add_action('woocommerce_thankyou_airtel', array($this, 'verify_payment_receipt'));

        // You can also register a webhook here
        add_action('woocommerce_api_airtel', array($this, 'process_callback'));
        add_action('woocommerce_api_airtel_receipt', array($this, 'receipt'));
    }

    /**
     * Plugin options, we deal with it in Step 3 too
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'webhook'       => array(
                'title'       => __('Webhook', 'woocommerce'),
                'description' => __('Set <code>' . site_url('wc-api/airtel') . '</code> as the callback URL in the Airtel developer portal.', 'woocommerce'),
                'type'        => 'title',
            ),
            'enabled'       => array(
                'title'       => 'Enable/Disable',
                'label'       => 'Enable Airtel Gateway',
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no',
            ),
            'title'         => array(
                'title'       => 'Title',
                'type'        => 'text',
                'description' => 'This controls the title which the user sees during checkout.',
                'default'     => 'Airtel',
                'desc_tip'    => true,
            ),
            'description'   => array(
                'title'       => 'Description',
                'type'        => 'textarea',
                'description' => 'This controls the description which the user sees during checkout.',
                'default'     => 'Pay with your Airtel via our super-cool payment gateway.',
            ),
            'testmode'      => array(
                'title'       => 'Test mode',
                'label'       => 'Check to enable test mode',
                'type'        => 'checkbox',
                'description' => 'Place the payment gateway in test mode using test API keys.',
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'client_id'     => array(
                'title' => 'Client ID',
                'type'  => 'text',
            ),
            'client_secret' => array(
                'title' => 'Client Secret',
                'type'  => 'text',
            ),
        );
    }

    /**
     * You will need it if you want your custom Airtel form, Step 4 is about it
     */
    public function payment_fields()
    {
        // ok, let's display some description before the payment form
        if ($this->description) {
            // display the description with <p> tags etc.
            echo wpautop(wp_kses_post($this->description));
        }

        // I recommend to use inique IDs, because other gateways could already use #ccNo, #expdate, #cvc
        woocommerce_form_field(
            'billing_airtel_phone',
            array(
                'type'        => 'tel',
                'class'       => array('form-row-wide', 'wc-phone-field'),
                'label'       => 'Airtel Number',
                'label_class' => 'wc-airtel-label',
                'placeholder' => 'Confirm Airtel Number',
                'required'    => true,
            )
        );
    }

    public function validate_fields()
    {
        if (empty($_POST['billing_airtel_phone'])) {
            wc_add_notice(' Airtel phone number is required!', 'error');
            return false;
        }

        return true;
    }

    /**
     * We're processing the payments here, everything about it is in Step 5
     */
    public function process_payment($order_id)
    {
        // we need it to get any order detailes
        $order     = wc_get_order($order_id);

        return array(
            'result'   => 'success',
            // 'redirect' => $this->get_return_url($order),
            'redirect' => add_query_arg(
                'order',
                $order->get_id(),
                add_query_arg('key', $order->get_order_key(), $order->get_checkout_payment_url(true))
            )
        );

        $phone     = sanitize_text_field($_POST['billing_airtel_phone']);
        $reference = array_pop(explode('_', $order->get_order_key()));
        $response  = $this->airtel_payment([
            'header'      => [
                'X-Currency' => 'CFA',
                'X-Country'  => 'GA',
            ],
            'reference'   => 'Payment for order : ' . $order_id,
            'subscriber'  => [
                'country'  => 'GA',
                'currency' => 'CFA',
                'msisdn'   => $phone,
            ],
            'transaction' => [
                'amount'   => $order->get_total(),
                'country'  => 'GA',
                'currency' => 'CFA',
                'id'       => $reference,
            ],
        ]);

        $result = json_decode($response);
        if ($result->status->success == true) {
            update_post_meta($order_id, 'airtel_phone', $phone);
            update_post_meta($order_id, 'airtel_transaction', $reference);
            $order->add_order_note(__("Awaiting Airtel confirmation of payment from {$phone} for transaction {$reference}.", 'woocommerce'));

            /**
             * Remove contents from cart
             */
            WC()->cart->empty_cart();

            // Redirect to the thank you page
            return array(
                'result'   => 'success',
                // 'redirect' => $this->get_return_url($order),
                'redirect' => add_query_arg(
                    'order',
                    $order->get_id(),
                    add_query_arg('key', $order->get_order_key(), $order->get_checkout_payment_url(true))
                )
            );
        } else {
            wc_add_notice($result->status->message, 'error');
            return;
        }
    }

    public function generate_airtel_token()
    {
        $response = wp_remote_post(
            add_query_arg(
                array(
                    "client_id"     => $this->client_id,
                    "client_secret" => $this->client_secret,
                    "grant_type"    => "client_credentials",
                ),
                $this->baseurl . 'auth/oauth2/token'
            ),
            array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ),
            )
        );

        return wp_remote_retrieve_body($response);
    }

    public function airtel_payment($parameter)
    {
        $token    = json_decode($this->generate_airtel_token());
        $response = wp_remote_post(
            $this->baseurl . 'merchant/v1/payments/',
            array(
                'headers' => array(
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                    'X-Country'     => $parameter['header']['X-Country'],
                    'X-Currency'    => $parameter['header']['X-Currency'],
                    'Authorization' => "Bearer $token->access_token",
                ),
                'body'    => wp_json_encode(
                    array(
                        "reference"   => $parameter['reference'],
                        "subscriber"  => array(
                            "country"  => $parameter['subscriber']['country'],
                            "currency" => $parameter['subscriber']['currency'],
                            "msisdn"   => $parameter['subscriber']['msisdn'],
                        ),
                        'transaction' => array(
                            'amount'   => $parameter['transaction']['amount'],
                            'country'  => $parameter['transaction']['country'],
                            'currency' => $parameter['transaction']['currency'],
                            'id'       => $parameter['transaction']['id'],
                        ),
                    )
                ),
            )
        );

        return wp_remote_retrieve_body($response);
    }

    public function verify_payment_receipt($order_id)
    {
        $receipt_url = add_query_arg(array("order_id" => $order_id), home_url("wc-api/airtel_receipt"));

        echo '<p class="saving" id="airtel_receipt">Confirming payment receipt, please wait</p>';
        echo <<<JAVASCRIPT
    <script>
    jQuery(document).ready(function($) {
        var checker = setInterval(() => {
            $.get('$receipt_url', function(data) {
                if (data.receipt == '' || data.receipt == 'N/A') {
                        $("#airtel_receipt").html(
                            'Confirming payment <span>.</span><span>.</span><span>.</span><span>.</span><span>.</span><span>.</span>'
                        );
                    } else if (data.receipt == 'fail') {
                        $("#airtel_receipt").html('<b>'+ data.note?.content +'</b>');
                    } else {
                        clearInterval(checker);
                        if (!$("#airtel-receipt-overview").length) {
                            $(".woocommerce-order-overview").append('<li id="airtel-receipt-overview" class="woocommerce-order-overview__payment-method method">Receipt number: <strong>'+data.receipt+'</strong></li>');
                        }

                        if (!$("#airtel-receipt-table-row").length) {
                            $(".woocommerce-table--order-details > tfoot")
                                .find('tr:last-child')
                                .prev()
                                .after('<tr id="airtel-receipt-table-row"><th scope="row">Receipt number:</th><td>'+data.receipt+'</td></tr>');
                        }

                        $("#airtel_receipt").html('Payment confirmed. Receipt number: <b>'+ data . receipt +'</b>');
                        return false;
                    }
            });
        }, 5000);
    });
    </script>
JAVASCRIPT;
    }

    public function process_callback()
    {
        $input    = file_get_contents('php://input');
        $response = json_decode($input, true);

        if (!isset($response['transaction'])) {
            exit(wp_send_json(['Error' => 'No transaction data received'], 400));
        }

        $transaction = $response['transaction']['id'] ?? '';
        $message     = $response['transaction']['message'] ?? '';
        $order_key   = "wc_order_$transaction";
        $order_id    = wc_get_order_id_by_order_key($order_key);

        if (wc_get_order($order_id)) {
            $order = new \WC_Order($order_id);

            if (isset($response['transaction']['airtel_money_id'])) {
                $order->add_order_note($message);
                $order->payment_complete($response['transaction']['airtel_money_id']);
                wc_reduce_stock_levels($order_id);
                wp_send_json(['Success' => 'Order reconciled']);
            } else {
                $order->update_status('on-hold', __("Airtel Error {$transaction}: {$message}"));
                wp_send_json(['Error' => 'Could not reconcile order']);
            }
        } else {
            wp_send_json(['Error' => "Order with key $order_key not found"], 404);
        }
    }

    public function receipt()
    {
        $response = array('receipt' => '');

        if (!empty($_GET['order'])) {
            $order_id = sanitize_text_field($_GET['order']);
            $order    = wc_get_order(esc_attr($order_id));

            $notes    = wc_get_order_notes(array(
                'post_id' => $order_id,
                'number'  => 1,
            ));

            $response = array(
                'receipt' => $order->get_transaction_id(),
                'note'    => $notes[0],
            );
        }

        exit(wp_send_json($response));
    }
}
