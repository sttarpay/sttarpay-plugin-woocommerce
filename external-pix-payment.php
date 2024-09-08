<?php
/**
 * Plugin Name: Sttarpay Pix Payment Gateway
 * Description: Adiciona um método de pagamento com PIX via QRCode.
 * Version: 1.0.6
 * Author: Sttarpay
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', 'external_pix_payment_init', 11);

function external_pix_payment_init() {
    if (class_exists('WC_Payment_Gateway')) {
        include_once 'includes/class-external-pix-gateway.php';

        function add_external_pix_gateway($methods) {
            $methods[] = 'WC_External_Pix_Gateway';
            return $methods;
        }

        add_filter('woocommerce_payment_gateways', 'add_external_pix_gateway');
    }
}

// Carrega os scripts e estilos necessários para o sistema de blocos
add_action('wp_enqueue_scripts', 'external_pix_payment_enqueue_scripts');

function external_pix_payment_enqueue_scripts() {
    if (is_checkout()) {
        wp_enqueue_script(
            'external-pix-blocks-integration',
            plugins_url('/assets/js/external-pix-blocks.js', __FILE__),
            array('wc-blocks-checkout'),
            '1.0.0',
            true
        );
    }
}
