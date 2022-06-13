<?php

/**
 * @package Airtel for WooCommerce
 * @author Osen Concepts < hi@osen.co.ke >
 * @version 1.0.0
 *
 * Plugin Name: WC Airtel 
 * Plugin URI: https://wcairtel.co.ke/
 * Description: This plugin extends WordPress and WooCommerce functionality to integrate <cite>Airtel</cite> for making and receiving online payments.
 * Author: Osen Concepts Kenya < hi@osen.co.ke >
 * Version: 1.0.0
 * Author URI: https://osen.co.ke/
 *
 * Requires at least: 4.6
 * Tested up to: 5.5.1
 *
 * WC requires at least: 3.5.0
 * WC tested up to: 5.1
 *
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html

 * Copyright 2021  Osen Concepts

 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 3, as
 * published by the Free Software Foundation.

 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USAv
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

define('WCAIRTEL_VER', '2.3.6');
if (!defined('WCAIRTEL_PLUGIN_FILE')) {
    define('WCAIRTEL_PLUGIN_FILE', __FILE__);
}

/**
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action('plugins_loaded', 'airtel_init_gateway_class');

function airtel_init_gateway_class()
{
    require_once __DIR__ . '/gateway.php';
    require_once __DIR__ . '/admin.php';
}

/**
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter('woocommerce_payment_gateways', 'airtel_add_gateway_class');
function airtel_add_gateway_class($gateways)
{
    $gateways[] = 'WC_Airtel_Gateway'; // your class name is here
    return $gateways;
}
