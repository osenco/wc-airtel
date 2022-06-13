<?php

/**
 * @package Airtel for WooCommerce
 * @subpackage Admin Order Functionalities
 * @author Osen Concepts <span hi@osen.co.ke >
 * @since 1.0.0
 */

add_action('add_meta_boxes', function () {
    global $post;
    if (wc_get_order($post)) {
        $order = new WC_Order($post);

        if ($order->get_payment_method() === 'airtel') {
            add_meta_box(
                'c2b-payment-payment_details',
                __('Manual Reconciliation'),
                function ($post) {
                    $order   = new \WC_Order($post);
                    $request = array_pop(explode('_', $order->get_order_key()));
                    $receipt = $order->get_transaction_id();
                    echo '<p>You can manually update the Airtel transaction ID received for transaction <b>' . $request . '</b>.</p><p>Airtel Receipt Number <input type="text" class="input-text" style="width: 100%" name="receipt" value="' . esc_attr($receipt) . ' " /></p>';
                },
                'shop_order',
                'side',
                'high'
            );
        }
    }
});

add_action('save_post_shop_order', function (int $order_id, WP_Post $post = null, bool $update = false) {
    if ($update) {
        $order_status = sanitize_text_field($_POST['status']);
        $order_note   = sanitize_text_field($_POST['order_note']);
        $receipt      = sanitize_text_field($_POST['receipt']);

        if (wc_get_order($order_id)) {
            $order = new \WC_Order($order_id);
            $order->update_status(strip_tags($order_status));

            if (!empty($order_note)) {
                $order->add_order_note(__(strip_tags($order_note)));
            }

            if (!empty($receipt)) {
                $order->set_transaction_id($receipt);
                $order->save();
            }
        }
    }
});

add_action('manage_shop_order_posts_custom_column', function (string $column) {
    global $post;
    $the_order = wc_get_order($post->ID);

    if ('transaction_id' === $column && $the_order->get_payment_method() === 'airtel') {
        echo $the_order->get_transaction_id() ?? 'N/A';
    }
}, 10, 1);

add_filter('manage_edit-shop_order_columns', function (array $columns) {
    $ordered_columns = array();

    foreach ($columns as $key => $column) {
        $ordered_columns[$key] = $column;

        if ('order_date' === $key) {
            $ordered_columns['transaction_id'] = __('Receipt', 'woocommerce');
        }
    }

    return $ordered_columns;
}, 100);

add_filter('woocommerce_account_orders_columns', function (array $columns) {
    $new_columns = array();

    foreach ($columns as $key => $name) {
        $new_columns[$key] = $name;

        // add transaction ID after order total column
        if ('order-total' === $key) {
            $new_columns['receipt'] = __('Transaction ID', 'woocommerce');
        }
    }

    return $new_columns;
}, 10, 1);

add_action('woocommerce_my_account_my_orders_column_receipt', function (WC_Order $order) {
    // Example with a custom field
    if ($value = $order->get_transaction_id()) {
        echo esc_html($value);
    }
});

add_action('woocommerce_admin_order_data_after_shipping_address', function (WC_Order $order) {
    if ($order->get_payment_method() === 'airtel') {
        echo '<p class="form-field form-field-wide">
                <strong>' . __('Transaction ID', 'woocommerce') . ':</strong><br>
                <span class="woocommerce-Price-amount amount">' . $order->get_transaction_id() . '</span>
            </p>
            <p class="form-field form-field-wide">
                <strong>' . __('Paying Phone', 'woocommerce') . ':</strong><br>
                <a href="tel:' . $order->get_meta('airtel_phone', 'woocommerce') . '">' . $order->get_meta('airtel_phone', 'woocommerce') . '</a>
            </p>';
    }
});

add_action('woocommerce_order_details_after_order_table_items', function (WC_Order $order) {
    if ($order->get_payment_method() === 'airtel') {
        echo '<tfoot>
                <tr>
                    <th scope="row">' . __('Transaction ID', 'woocommerce') . ':</th>
                    <td><span class="woocommerce-Price-amount amount">' . $order->get_transaction_id() . '</td>
                </tr>
                <tr>
                    <th scope="row">' . __('Paying Phone', 'woocommerce') . ':</th>
                    <td>' . $order->get_meta('airtel_phone', 'woocommerce') . '</td>
                </tr>
            </tfoot>';
    }
}, 10, 1);

// add_filter('woocommerce_email_attachments', function (array $attachments, string $status, WC_Order $order) {
//     if (is_object($order) || isset($status) || !empty($order)) {
//         if (is_a($order, 'WC_Order') && method_exists($order, 'has_downloadable_item')) {
//             if ($order->has_downloadable_item()) {

//                 $allowed_statuses = array('customer_invoice', 'customer_completed_order');
//                 if (isset($status) && in_array($status, $allowed_statuses)) {
//                     foreach ($order->get_items() as $item) {
//                         foreach ($order->get_item_downloads($item) as $download) {
//                             $attachments[] = str_replace(content_url(), WP_CONTENT_DIR, $download['file']);
//                         }
//                     }
//                 }
//             }
//         }
//     }

//     return $attachments;
// }, 10, 3);
