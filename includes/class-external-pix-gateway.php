<?php

// use Piggly\WooPixGateway\CoreConnector;

use Automattic\WooCommerce\GoogleListingsAndAds\Proxies\WC;

class WC_External_Pix_Gateway extends WC_Payment_Gateway
{

    public $api_key;
    public function __construct()
    {
        $this->id = 'external_pix';
        $this->method_title = __('Sttarpay Gateway', 'woocommerce');
        $this->method_description = __('Gateway de pagamentos via Pix. Habilite o pagamento de pedidos com pix via Sttarpay.', 'woocommerce');
        $this->has_fields = false;
        $this->supports = array(
            'products',
            'blocks'
        );

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->api_key = $this->get_option('api_key');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        // add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'payment_page'));
        // add_action( 
        // 	'woocommerce_thankyou_'.$this->id, 
        // 	$this, 
        // 	'payment_page', 
        // 	10, 
        // 	1 
        // );
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable External Pix Payment', 'woocommerce'),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __('Title', 'woocommerce'),
                'type' => 'text',
                'description' => __('This controls the title the user sees during checkout.', 'woocommerce'),
                'default' => __('Pagamento via Pix', 'woocommerce'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'woocommerce'),
                'type' => 'textarea',
                'description' => __('This controls the description the user sees during checkout.', 'woocommerce'),
                'default' => __('Pague com Pix usando um QRCode gerado.', 'woocommerce'),
                'desc_tip' => true,

            ),
            'api_key' => array(
                'title' => __('API Key', 'woocommerce'),
                'type' => 'text',
                'description' => __('Chave API para acessar o gateway de pagamento.', 'woocommerce'),
                'default' => ''
            )
        );
    }

    public function process_payment($order_id)
    {
        global $wpdb;

        $order = wc_get_order($order_id);

        $value = $order->get_total(); // Converter para centavos
        $expiration = 3600; // 1 hora

        $response = $this->generate_pix_qrcode($value, $expiration, $order_id);

        if ($response && $response->status) {
            // $order->update_status('on-hold', __('Aguardando pagamento via PIX', 'woocommerce'));
            $order->update_status(
                apply_filters(
                    'on-hold',
                    'pending',
                    $order->get_id(),
                    $order
                )
            );

            $metadata = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->prefix}wc_orders_meta WHERE order_id = {$order_id} AND meta_key = %s",
                '_sttarpay_reference_code'
            ));

            // var_dump($metadata);
            // exit;

            $order->add_meta_data('_sttarpay_reference_code', $response->content->qrcode->reference);

            if(isset($metadata)){
                $wpdb->get_var($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}wc_orders_meta SET meta_value = '{$response->content->qrcode->reference}' WHERE order_id = {$order_id}",
                    $response->content->qrcode->reference
                ));
            } else {
                $wpdb->get_var($wpdb->prepare(
                    "INSERT INTO {$wpdb->prefix}wc_orders_meta (order_id, meta_key, meta_value) VALUES ({$order_id}, '_sttarpay_reference_code', %s)",
                    $response->content->qrcode->reference
                ));
            }
            // var_dump($order->get_meta('_sttarpay_reference_code'));
            // exit;
            // Retorna uma URL customizada para a página de agradecimento
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
                // 'redirect' => $order->get_checkout_payment_url(true)
            );
        } else {
            wc_add_notice(__('Erro ao gerar QRCode Pix. Tente novamente.', 'woocommerce'), 'error');
            return;
        }
    }

    private function generate_pix_qrcode($value, $expiration, $order_id)
    {
        $body = array(
            'value' => $value,
            'expiration' => $expiration,
            'webhookie' => home_url('/wp-admin/admin-ajax.php?action=external_pix_webhook') // URL do webhook
        );

        $response = wp_remote_post('https://sttarpay.com/api/v1/generate-qrcode', array(
            'body' => json_encode($body),
            'headers' => array(
                'Content-Type' => 'application/json',
                'api-key' => $this->api_key
            ),
        ));

        if (is_wp_error($response)) {
            return false;
        }

        return json_decode(wp_remote_retrieve_body($response));
    }

    public function thankyou_page($order_id)
    {
        exit;
        $order = wc_get_order($order_id);
        $pix_data = $this->generate_pix_qrcode($order->get_total(), 3600, $order_id);

        if ($pix_data && $pix_data->status) {
            $qrCode = esc_attr($pix_data->content->qrcode->qrcode);
            $pixkey = esc_html($pix_data->content->qrcode->pixkey);
            echo "
            <div class='wp-block-column is-layout-flow wp-block-column-is-layout-flow' style='max-width: 50%;'>
                <div data-block-name='woocommerce/order-confirmation-billing-wrapper' class='wc-block-order-confirmation-billing-wrapper ' style=''>
                    <h3 class='wp-block-heading' style='font-size:clamp(15.747px, 0.984rem + ((1vw - 3.2px) * 0.86), 24px);'>QrCode para Pagamento</h3>

                    <div data-block-name='woocommerce/order-confirmation-billing-address' data-lock='{&quot;remove&quot;:true}' class='wc-block-order-confirmation-billing-address ' style=''>
                        <img src='{$qrCode}' style='width: 200px; height: 200px;' />
                        <p class='woocommerce-customer-details--phone'>
                            <input class='input-text regular-input ' type='text' name='woocommerce_external_pix_title' id='woocommerce_external_pix_title' style='' value='{$pixkey}' placeholder='chave copia-e-cola' readyonly>
                        </p>
                    </div>
                </div>
            </div>
            ";
            // echo '<h2>' . __('Pagamento via Pix', 'woocommerce') . '</h2>';
            // echo '<p>' . __('Escaneie o QRCode abaixo para realizar o pagamento:', 'woocommerce') . '</p>';
            // echo '<img src="' . esc_attr($pix_data->content->qrcode->qrcode) . '" alt="QR Code Pix"/>';
            // echo '<p>' . __('Ou use o código copia-e-cola abaixo:', 'woocommerce') . '</p>';
            // echo '<p><strong>' . esc_html($pix_data->content->qrcode->pixkey) . '</strong></p>';
        } else {
            echo '<p>' . __('Houve um problema ao gerar o QRCode Pix.', 'woocommerce') . '</p>';
        }
    }

    /**
     * Open the payment page.
     *
     * @param WC_Order|integer $order_id
     * @param boolean $echo
     * @since 2.0.0
     * @return void
     */
    public function payment_page($order_id)
    {
        $order = wc_get_order($order_id);
        $pix_data = $this->generate_pix_qrcode($order->get_total(), 3600, $order_id);
        // $settings = CoreConnector::settings();
        // $pix = null;

        if ($pix_data && $pix_data->status) {
            $qrCode = esc_attr($pix_data->content->qrcode->qrcode);
            $pixkey = esc_html($pix_data->content->qrcode->pixkey);
            // wc_get_template(
            //     'html-woocommerce-payment-instructions.php',
            //     array(
            //         'pix' => $pix,
            //         'order' => $order,
            //         // 'instructions' => str_replace('{{order_number}}', $order->get_order_number(), $settings->get('gateway')->get('instructions')),
            //         // 'receipt_page' => $settings->get('receipts')->get('receipt_page', true),
            //         // 'whatsapp_number' => $settings->get('receipts')->get('whatsapp_number', true),
            //         // 'whatsapp_message' => str_replace('{{order_number}}', $order->get_order_number(), $settings->get('receipts')->get('whatsapp_message', true)),
            //         // 'telegram_number' => str_replace('{{order_number}}', $order->get_order_number(), $settings->get('receipts')->get('telegram_number', true)),
            //         // 'telegram_message' => str_replace('{{order_number}}', $order->get_order_number(), $settings->get('receipts')->get('telegram_message', true)),
            //         // 'shows_qrcode' => $settings->get('gateway')->get('shows_qrcode', false),
            //         // 'shows_copypast' => $settings->get('gateway')->get('shows_copypast', false),
            //         // 'shows_manual' => $settings->get('gateway')->get('shows_manual', false),
            //         // 'shows_amount' => $settings->get('gateway')->get('shows_amount', false),
            //         // 'show_expiration' => $settings->get('orders')->get('show_expiration', false) && !empty($pix?->getExpiresAt()),
            //         // 'shows_receipt' => $settings->get('receipts')->get('shows_receipt', 'up')
            //     ),
            //     WC()->template_path().\dirname(CoreConnector::plugin()->getBasename()).'/',
            //     CoreConnector::plugin()->getTemplatePath().'woocommerce/'
            // );

            echo "
            <style>
                .hide-alert { display: none; }
                .show-alert { display: block; }
            </style>
            <div class='wp-block-column is-layout-flow wp-block-column-is-layout-flow' style='max-width: 100%;'>
                <div data-block-name='woocommerce/order-confirmation-billing-wrapper' class='wc-block-order-confirmation-billing-wrapper '>
                    <h3 class='wp-block-heading' style='font-size:clamp(15.747px, 0.984rem + ((1vw - 3.2px) * 0.86), 24px); display: flex; justify-content: center;'>QrCode para Pagamento</h3>

                    <div data-block-name='woocommerce/order-confirmation-billing-address' data-lock='{&quot;remove&quot;:true}' class='wc-block-order-confirmation-billing-address ' style='display: flex; justify-content: center;'>
                        <div>
                            <div class='col'>
                                <img src='{$qrCode}' style='max-width: 300px; height: 400px; object-fit: cover;' />
                            </div>
                            <div class='col' style='display: flex; justify-content: center; max-width: 90%;'>
                                <input class='input-text regular-input ' type='text' name='woocommerce_external_pix_title' id='woocommerce_external_pix_title' style='' value='{$pixkey}' placeholder='chave copia-e-cola' readyonly style='max-width: 90%; border-radius: 10px !important;'>
                            <div>

                            <div class='col alert hide-alert' style='border-radius: 5px; background: #03fc24; padding: 5px; margin-top: 5px; margin-left: 5px;'>
                                copiado!
                            </div>
                        <div>
                    </div>
                </div>
            </div>
            <!-- script de integração -->
            <script>
                /**
                 * Sttarpay - Soluções em pagamentos
                 * versão 0.0.3
                 * Todos os direitos reservados
                 */
                function COPY(textToCopy){
                    const input = document.createElement('input');
                    input.value = textToCopy;
                    document.body.appendChild(input);

                    input.select();
                    input.setSelectionRange(0, 99999); // Para dispositivos móveis

                    document.execCommand('copy');
                    document.body.removeChild(input);
                };

                document.querySelectorAll('.input-text').forEach(function(element) {
                    element.addEventListener('click', function(e) {
                        var value = e.target.getAttribute('value');
                        COPY(value);
                        document.querySelectorAll('.alert').forEach(function(element) {
                            element.classList.remove('hide-alert');
                            element.classList.add('show-alert');
                        });

                        setTimeout(() => {
                            document.querySelectorAll('.alert').forEach(function(element) {
                                element.classList.add('hide-alert');
                                element.classList.remove('show-alert');
                            });
                        }, 3000)
                    });
                });
            </script>
            ";
            // echo '<h2>' . __('Pagamento via Pix', 'woocommerce') . '</h2>';
            // echo '<p>' . __('Escaneie o QRCode abaixo para realizar o pagamento:', 'woocommerce') . '</p>';
            // echo '<img src="' . esc_attr($pix_data->content->qrcode->qrcode) . '" alt="QR Code Pix"/>';
            // echo '<p>' . __('Ou use o código copia-e-cola abaixo:', 'woocommerce') . '</p>';
            // echo '<p><strong>' . esc_html($pix_data->content->qrcode->pixkey) . '</strong></p>';
        } else {
            echo '<p>' . __('Houve um problema ao gerar o QRCode Pix.', 'woocommerce') . '</p>';
        }
    }
}


// Adiciona o endpoint para o webhook
add_action('wp_ajax_external_pix_webhook', 'external_pix_process_webhook');
add_action('wp_ajax_nopriv_external_pix_webhook', 'external_pix_process_webhook');

function external_pix_process_webhook()
{
    // Verifica se a solicitação é um POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Obtém o corpo da solicitação
        $body = file_get_contents('php://input');
        $data = json_decode($body, true);

        // Verifica se o status do pagamento é "completed"
        if (isset($data['request']['status']) && $data['request']['status'] === 'completed') {
            $order_id = external_pix_get_order_id_by_reference($data['request']['reference_code']);

            if ($order_id) {
                // Marca o pedido como pago
                $order = wc_get_order($order_id);
                $order->payment_complete();
                $info = ['payment' => true, 'order' => $order];
            } else {
                $info = ['payment' => false, 'order' => null];
            }
        }

        // Envia uma resposta para confirmar o recebimento da notificação
        wp_send_json(array('status' => 'success', 'process' => 'completed', 'uuid' => $data['request']['idempotent_id'], 'info' => $info));
    } else {
        wp_send_json_error(array('status' => 'error', 'message' => 'Invalid request method.'));
    }
}

// Obtém o ID do pedido com base no código de referência
function external_pix_get_order_id_by_reference($reference_code)
{
    global $wpdb;
    
    // $order->get_meta('_sttarpay_reference_code');
    // Consulta para encontrar o pedido com base no código de referência
    $order_id = $wpdb->get_var($wpdb->prepare(
        "SELECT order_id FROM {$wpdb->prefix}wc_orders_meta WHERE meta_value = %s",
        $reference_code
    ));

    return $order_id;
}
