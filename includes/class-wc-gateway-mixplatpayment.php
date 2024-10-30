<?php

if (!defined('ABSPATH')) {
    exit;
}
// Exit if accessed directly

/**
 * Mixplatpayment Payment Gateway
 *
 * Provides a Mixplatpayment Payment Gateway.
 *
 * @class        WC_Getaway_Mixplatpayment
 * @extends        WC_Payment_Gateway
 * @version        1.0.0
 * @author        Psbankpayment
 */
class WC_Gateway_Mixplatpayment extends WC_Payment_Gateway
{

    /**
     * Constructor for the gateway.
     *
     * @access public
     * @return void
     */
    public function __construct()
    {
        $this->id = 'mixplatpayment';
        $this->icon = apply_filters('woocommerce_mixplatpayment_icon', plugins_url('/assets/images/icons/card.jpg', MIXPLAT_PLUGIN_FILE));
        $this->has_fields = false;
        $this->order_button_text = __('Оплатить', 'woocommerce-gateway-mixplatpayment-payments');
        $this->method_title = __('Mixplat: Интернет эквайринг', 'woocommerce-gateway-mixplatpayment-payments');
        $this->method_description = __('Оплата картой Visa/Masrercard/Мир', 'woocommerce-gateway-mixplatpayment-payments');
        $this->supports = [
            'products',
        ];

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->instructions = $this->get_option('instructions', $this->description);
        $this->project_id = $this->get_option('project_id');
        $this->form_id = $this->get_option('form_id');
        $this->api_key = $this->get_option('api_key');
        $this->test_mode = $this->get_option('test_mode');
        $this->hold = $this->get_option('hold');
        $this->print_check = $this->get_option('print_check');
        $this->check_ip = $this->get_option('check_ip');
        $this->mixplat_ip_list = $this->get_option('mixplat_ip_list');
        $this->payment_description = $this->get_option('payment_description');
        $this->sno = $this->get_option('sno');
        $this->product_nds = $this->get_option('product_nds');
        $this->delivery_nds = $this->get_option('delivery_nds');
        $this->payment_method = $this->get_option('payment_method');
        $this->payment_object = $this->get_option('payment_object');
        $this->payment_object_delivery = $this->get_option('payment_object_delivery');
        $this->form_type = $this->get_option('form_type');
        $this->widget_key = $this->get_option('widget_key');

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

        // Payment listener/API hook
        add_action('woocommerce_api_wc_gateway_mixplatpayment', [$this, 'notification']);

        add_action('wp_enqueue_scripts', [$this, 'load_scripts']);

        if (!$this->is_valid_for_use()) {
            $this->enabled = false;
        }
    }

    /**
     * Check if this gateway is enabled and available in the user's country
     *
     * @access public
     * @return bool
     */
    public function is_valid_for_use()
    {
        /*if ( ! in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_mixplatpayment_supported_currencies', array('RUB' ) ) ) ) {
        return false;
        }*/

        return true;
    }

    public function load_scripts()
    {
        if ($this->is_js_widget()) {
            wp_enqueue_script('mixplat-js-widget', 'https://cdn.mixplat.ru/widget/v3/widget.js', [], MixplatLib::VERSION, false);
            wp_enqueue_script('mixplat-widget', plugins_url('/assets/js/widget.js', MIXPLAT_PLUGIN_FILE), ['mixplat-js-widget'], MixplatLib::VERSION, true);
        }
    }

    private function is_js_widget()
    {
        return $this->form_type === 'js_widget';
    }

    /**
     * Admin Panel Options
     * - Options for bits like 'title' and availability on a country-by-country basis
     *
     * @since 1.0.0
     */
    public function admin_options()
    {
        if ($this->is_valid_for_use()) {
            parent::admin_options();
        } else {
            ?>
            <div class="inline error"><p>
                    <strong><?php _e('Gateway Disabled', 'woocommerce'); ?></strong>: <?php _e('Mixplat does not support your store currency.', 'woocommerce-gateway-mixplatpayment-payments'); ?>
                </p></div>
            <?php
        }
    }

    /**
     * Initialise Gateway Settings Form Fields
     *
     * @access public
     * @return void
     */
    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled' => [
                'title'   => __('Enable/Disable', 'woocommerce'),
                'type'    => 'checkbox',
                'label'   => __('Включить оплату по карте', 'woocommerce-gateway-mixplatpayment-payments'),
                'default' => 'yes',
            ],

            'notify_url' => [
                'title'       => __('Укажите данный url в качестве коллбэка в настройках проекта', 'woocommerce-gateway-mixplatpayment-payments'),
                'type'        => 'title',
                'description' => WC()->api_request_url('WC_Gateway_Mixplatpayment'),
            ],

            'title'        => [
                'title'       => __('Title', 'woocommerce'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                'default'     => __('Оплата по карте', 'woocommerce-gateway-mixplatpayment-payments'),
                'desc_tip'    => true,
            ],
            'description'  => [
                'title'       => __('Description', 'woocommerce'),
                'type'        => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', 'woocommerce'),
                'default'     => __('', 'woocommerce-gateway-mixplatpayment-payments'),
                'desc_tip'    => true,
            ],
            'instructions' => [
                'title'       => __('Instructions', 'woocommerce'),
                'type'        => 'textarea',
                'description' => __('Instructions that will be added to the thank you page and emails.', 'woocommerce'),
                'default'     => '',
                'desc_tip'    => true,
            ],
            'project_id'   => [
                'title'   => __('ID проекта', 'woocommerce-gateway-mixplatpayment-payments'),
                'type'    => 'text',
                'default' => '',
            ],
            'form_id'      => [
                'title'   => __('ID платежной формы', 'woocommerce-gateway-mixplatpayment-payments'),
                'type'    => 'text',
                'default' => '',
            ],

            'api_key' => [
                'title'   => __('Ключ API', 'woocommerce-gateway-mixplatpayment-payments'),
                'type'    => 'text',
                'default' => '',
            ],

            'widget_key' => [
                'title'   => __('Ключ платёжного виджета', 'woocommerce-gateway-mixplatpayment-payments'),
                'type'    => 'text',
                'default' => '',
            ],

            'form_type' => [
                'title'   => __('Форма оплаты', 'woocommerce-gateway-mixplatpayment-payments'),
                'type'    => 'select',
                'class'   => 'wc-enhanced-select',
                'options' => [
                    'redirect'  => __('перенаправление в Mixplat', 'woocommerce-gateway-mixplatpayment-payments'),
                    'js_widget' => __('платёжный виджет', 'woocommerce-gateway-mixplatpayment-payments'),
                ],
                'default' => 'redirect',
            ],

            'test_mode'               => [
                'title'   => __('Режим работы', 'woocommerce-gateway-mixplatpayment-payments'),
                'type'    => 'select',
                'class'   => 'wc-enhanced-select',
                'options' => [
                    '1' => __('Тестовый', 'woocommerce-gateway-mixplatpayment-payments'),
                    '0' => __('Рабочий', 'woocommerce-gateway-mixplatpayment-payments'),
                ],
                'default' => '1',
            ],
            'hold'                    => [
                'title'   => __('Сценарий работы', 'woocommerce-gateway-mixplatpayment-payments'),
                'type'    => 'select',
                'class'   => 'wc-enhanced-select',
                'options' => [
                    'sms' => __('Одноэтапные платежи', 'woocommerce-gateway-mixplatpayment-payments'),
                    'dms' => __('Двухэтапные платежи', 'woocommerce-gateway-mixplatpayment-payments'),
                ],
                'default' => 'sms',
            ],
            'check_ip'                => [
                'title'   => __('Разрешить запросы только с ip-адресов MIXPLAT', 'woocommerce-gateway-mixplatpayment-payments'),
                'type'    => 'select',
                'class'   => 'wc-enhanced-select',
                'options' => [
                    0 => __('Все адреса', 'woocommerce-gateway-mixplatpayment-payments'),
                    1 => __('Только Mixplat', 'woocommerce-gateway-mixplatpayment-payments'),
                ],
                'default' => 0,
            ],
            'mixplat_ip_list'         => [
                'title'       => __('Список IP Mixplat', 'woocommerce'),
                'type'        => 'textarea',
                'description' => __('Каждый IP на новой строке', 'woocommerce'),
                'default'     => __("185.77.233.27\n185.77.233.29", 'woocommerce-gateway-mixplatpayment-payments'),
                'desc_tip'    => true,
            ],
            'payment_description'     => [
                'title'       => __('Описание платежа', 'woocommerce-gateway-mixplatpayment-payments'),
                'type'        => 'text',
                'description' => __('Его вы увидите в личном кабинете и покупатель на станице оплаты. Текст может содержать метки: %order_number% - Номер заказа, %email% - Email покупателя', 'woocommerce'),
                'default'     => __('Оплата заказа %order_number%', 'woocommerce-gateway-mixplatpayment-payments'),
            ],
            'print_check'             => [
                'title'   => __('Печать чека', 'woocommerce-gateway-mixplatpayment-payments'),
                'type'    => 'select',
                'class'   => 'wc-enhanced-select',
                'options' => [
                    0 => __('Нет', 'woocommerce-gateway-mixplatpayment-payments'),
                    1 => __('Да', 'woocommerce-gateway-mixplatpayment-payments'),
                ],
                'default' => 1,
            ],
            'sno'                     => [
                'title'   => __('Система налогообложения', 'woocommerce-gateway-mixplatpayment-payments'),
                'type'    => 'select',
                'class'   => 'wc-enhanced-select',
                'options' => [
                    1 => __('Общая СН', 'woocommerce-gateway-mixplatpayment-payments'),
                    2 => __('упрощенная СН (доходы)', 'woocommerce-gateway-mixplatpayment-payments'),
                    3 => __('упрощенная СН (доходы минус расходы)', 'woocommerce-gateway-mixplatpayment-payments'),
                    4 => __('единый налог на вмененный доход', 'woocommerce-gateway-mixplatpayment-payments'),
                    5 => __('единый сельскохозяйственный налог', 'woocommerce-gateway-mixplatpayment-payments'),
                    6 => __('патентная СН', 'woocommerce-gateway-mixplatpayment-payments'),
                ],
                'default' => 1,
            ],
            'product_nds'             => [
                'title'   => __('НДС на товары', 'woocommerce-gateway-mixplatpayment-payments'),
                'type'    => 'select',
                'class'   => 'wc-enhanced-select',
                'options' => [
                    'none'   => __('Без НДС', 'woocommerce-gateway-mixplatpayment-payments'),
                    'vat0'   => __('НДС 0%', 'woocommerce-gateway-mixplatpayment-payments'),
                    'vat10'  => __('НДС 10%', 'woocommerce-gateway-mixplatpayment-payments'),
                    'vat20'  => __('НДС 20%', 'woocommerce-gateway-mixplatpayment-payments'),
                    'vat110' => __('НДС чека по расчетной ставке 10/110', 'woocommerce-gateway-mixplatpayment-payments'),
                    'vat120' => __('НДС чека по расчетной ставке 20/120', 'woocommerce-gateway-mixplatpayment-payments'),
                ],
                'default' => 'vat20',
            ],
            'delivery_nds'            => [
                'title'   => __('НДС на доставку', 'woocommerce-gateway-mixplatpayment-payments'),
                'type'    => 'select',
                'class'   => 'wc-enhanced-select',
                'options' => [
                    'none'   => __('Без НДС', 'woocommerce-gateway-mixplatpayment-payments'),
                    'vat0'   => __('НДС 0%', 'woocommerce-gateway-mixplatpayment-payments'),
                    'vat10'  => __('НДС 10%', 'woocommerce-gateway-mixplatpayment-payments'),
                    'vat20'  => __('НДС 20%', 'woocommerce-gateway-mixplatpayment-payments'),
                    'vat110' => __('НДС чека по расчетной ставке 10/110', 'woocommerce-gateway-mixplatpayment-payments'),
                    'vat120' => __('НДС чека по расчетной ставке 20/120', 'woocommerce-gateway-mixplatpayment-payments'),
                ],
                'default' => 'vat20',
            ],
            'payment_method'          => [
                'title'   => __('НДС на доставку', 'woocommerce-gateway-mixplatpayment-payments'),
                'type'    => 'select',
                'class'   => 'wc-enhanced-select',
                'options' => [
                    'full_prepayment'    => __('полная предоплата', 'woocommerce-gateway-mixplatpayment-payments'),
                    'partial_prepayment' => __('частичная предоплата', 'woocommerce-gateway-mixplatpayment-payments'),
                    'advance'            => __('аванс', 'woocommerce-gateway-mixplatpayment-payments'),
                    'full_payment'       => __('полный расчёт', 'woocommerce-gateway-mixplatpayment-payments'),
                    'partial_payment'    => __('частичный расчёт и кредит', 'woocommerce-gateway-mixplatpayment-payments'),
                    'credit'             => __('передача в кредит', 'woocommerce-gateway-mixplatpayment-payments'),
                    'credit_payment'     => __('оплата кредита', 'woocommerce-gateway-mixplatpayment-payments'),
                ],
                'default' => 'full_prepayment',
            ],
            'payment_object'          => [
                'title'   => __('Признак предмета расчёта', 'woocommerce-gateway-mixplatpayment-payments'),
                'type'    => 'select',
                'class'   => 'wc-enhanced-select',
                'options' => [
                    'commodity'             => __('товар', 'woocommerce-gateway-mixplatpayment-payments'),
                    'excise'                => __('подакцизный товар', 'woocommerce-gateway-mixplatpayment-payments'),
                    'job'                   => __('работа', 'woocommerce-gateway-mixplatpayment-payments'),
                    'service'               => __('услуга', 'woocommerce-gateway-mixplatpayment-payments'),
                    'gambling_bet'          => __('ставка азартной игры', 'woocommerce-gateway-mixplatpayment-payments'),
                    'gambling_prize'        => __('выигрыш азартной игры', 'woocommerce-gateway-mixplatpayment-payments'),
                    'lottery'               => __('лотерейный билет', 'woocommerce-gateway-mixplatpayment-payments'),
                    'lottery_prize'         => __('выигрыш лотереи', 'woocommerce-gateway-mixplatpayment-payments'),
                    'intellectual_activity' => __('предоставление результатов интеллектуальной деятельности', 'woocommerce-gateway-mixplatpayment-payments'),
                    'payment'               => __('платеж', 'woocommerce-gateway-mixplatpayment-payments'),
                    'agent_commission'      => __('агентское вознаграждение', 'woocommerce-gateway-mixplatpayment-payments'),
                    'composite'             => __('оставной предмет расчета', 'woocommerce-gateway-mixplatpayment-payments'),
                    'another'               => __('иной предмет расчета', 'woocommerce-gateway-mixplatpayment-payments'),

                ],
                'default' => 'commodity',
            ],
            'payment_object_delivery' => [
                'title'   => __('Признак предмета расчёта на доставку', 'woocommerce-gateway-mixplatpayment-payments'),
                'type'    => 'select',
                'class'   => 'wc-enhanced-select',
                'options' => [
                    'commodity'             => __('товар', 'woocommerce-gateway-mixplatpayment-payments'),
                    'excise'                => __('подакцизный товар', 'woocommerce-gateway-mixplatpayment-payments'),
                    'job'                   => __('работа', 'woocommerce-gateway-mixplatpayment-payments'),
                    'service'               => __('услуга', 'woocommerce-gateway-mixplatpayment-payments'),
                    'gambling_bet'          => __('ставка азартной игры', 'woocommerce-gateway-mixplatpayment-payments'),
                    'gambling_prize'        => __('выигрыш азартной игры', 'woocommerce-gateway-mixplatpayment-payments'),
                    'lottery'               => __('лотерейный билет', 'woocommerce-gateway-mixplatpayment-payments'),
                    'lottery_prize'         => __('выигрыш лотереи', 'woocommerce-gateway-mixplatpayment-payments'),
                    'intellectual_activity' => __('предоставление результатов интеллектуальной деятельности', 'woocommerce-gateway-mixplatpayment-payments'),
                    'payment'               => __('платеж', 'woocommerce-gateway-mixplatpayment-payments'),
                    'agent_commission'      => __('агентское вознаграждение', 'woocommerce-gateway-mixplatpayment-payments'),
                    'composite'             => __('оставной предмет расчета', 'woocommerce-gateway-mixplatpayment-payments'),
                    'another'               => __('иной предмет расчета', 'woocommerce-gateway-mixplatpayment-payments'),

                ],
                'default' => 'service',
            ],
        ];
    }

    private function getReceiptItems($order)
    {
        global $woocommerce;
        $receipt_items = [];
        $items = $order->get_items();
        if (version_compare($woocommerce->version, "3.0", ">=")) {
            foreach ($items as $product) {
                $receipt_items[] = [
                    "name"     => substr($product->get_name(), 0, 128),
                    "quantity" => $product->get_quantity(),
                    "sum"      => intval($product->get_total() * 100),
                    "vat"      => $this->product_nds,
                    "method"   => $this->payment_method,
                    "object"   => $this->payment_object,
                ];
            }
        } else {
            foreach ($items as $product) {
                $receipt_items[] = [
                    "name"     => substr($product['name'], 0, 128),
                    "quantity" => $product['qty'],
                    "sum"      => intval($order->get_line_subtotal($product) * 100),
                    "vat"      => $this->product_nds,
                    "method"   => $this->payment_method,
                    "object"   => $this->payment_object,
                ];
            }
        }
        if (version_compare($woocommerce->version, "3.0", ">=")) {
            $order_total = $order->get_total();
            $shipping_total = $order->get_shipping_total();
        } else {
            $order_total = number_format($order->order_total, 2, '.', '');
            $shipping_total = $order->get_total_shipping();
        }

        if ($shipping_total) {
            $receipt_items[] = [
                "name"     => __('Доставка', 'woocommerce-gateway-mixplatpayment-payments'),
                "quantity" => 1,
                "sum"      => intval($shipping_total * 100),
                "vat"      => $this->delivery_nds,
                "method"   => $this->payment_method,
                "object"   => $this->payment_object_delivery,
            ];
        }
        $total = intval($order_total * 100);

        return MixplatLib::normalizeReceiptItems($receipt_items, $total);
    }

    /**
     * Process the payment and return the result
     *
     * @access public
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id)
    {
        if (function_exists("wc_get_order")) {
            $order = wc_get_order($order_id);
        } else {
            $order = new WC_Order($order_id);
        }

        if ($this->is_js_widget()) {
            return [
                'result' => 'success',
                'data'   => $this->getPaymentRequestWidgetData($order),
            ];
        }

        $url = $this->createPayment($order);
        $order->update_status('pending');
        wc_reduce_stock_levels($order->get_id());
        return [
            'result'   => 'success',
            'redirect' => $url,
        ];

    }

    private function createPayment($order)
    {
        $data = $this->getPaymentRequestData($order);
        $result = MixplatLib::createPayment($data);
        $this->insertTransaction($result->payment_id, $data['merchant_payment_id'], $data['amount']);
        return $result->redirect_url;
    }

    private function getPaymentRequestData($order)
    {
        global $woocommerce;
        if (version_compare($woocommerce->version, "3.0", ">=")) {
            $billing_email = $order->get_billing_email();
            $order_total = $order->get_total();
        } else {
            $billing_email = $order->billing_email;
            $order_total = number_format($order->order_total, 2, '.', '');
        }

        $data = apply_filters('woocommerce_mixplatpayment_payment_request_data', [
            'amount'              => intval($order_total * 100),
            'test'                => intval($this->test_mode),
            'project_id'          => intval($this->project_id),
            'payment_form_id'     => $this->form_id,
            'request_id'          => MixplatLib::getIdempotenceKey(),
            'merchant_payment_id' => $order->get_id(),
            'user_email'          => $billing_email,
            'url_success'         => $this->get_return_url($order),
            'url_failure'         => $order->get_cancel_order_url_raw(),
            'notify_url'          => WC()->api_request_url('WC_Gateway_Mixplatpayment'),
            'payment_scheme'      => $this->hold,
            'description'         => $this->getPaymentDescription($order),
        ], $order, $this);

        $data['signature'] = MixplatLib::calcPaymentSignature($data, $this->api_key);

        if ($this->print_check) {
            $data['items'] = $this->getReceiptItems($order);
        }
        return $data;
    }

    private function getPaymentRequestWidgetData($order)
    {
        global $woocommerce;
        if (version_compare($woocommerce->version, "3.0", ">=")) {
            $billing_email = $order->get_billing_email();
            $order_total = $order->get_total();
        } else {
            $billing_email = $order->billing_email;
            $order_total = number_format($order->order_total, 2, '.', '');
        }

        $data = apply_filters('woocommerce_mixplatpayment_payment_request_widget_data', [
            'widget_key'          => $this->widget_key,
            'amount'              => intval($order_total * 100),
            'test'                => intval($this->test_mode),
            'merchant_payment_id' => (string)$order->get_id(),
            'user_email'          => $billing_email,
            'description'         => $this->getPaymentDescription($order),
            'url_success'         => $this->get_return_url($order),
            'url_failure'         => $order->get_cancel_order_url_raw(),
        ], $order, $this);

        if ($this->print_check) {
            $data['items'] = $this->getReceiptItems($order);
        }
        return $data;
    }

    private function getPaymentDescription($order)
    {
        global $woocommerce;
        if (version_compare($woocommerce->version, "3.0", ">=")) {
            $billing_email = $order->get_billing_email();
        } else {
            $billing_email = $order->billing_email;
        }
        return str_replace(
            ['%order_number%', '%email%'],
            [$order->get_order_number(), $billing_email],
            $this->payment_description);
    }

    private function insertTransaction($payment_id, $order_id, $amount)
    {
        global $wpdb;
        $wpdb->insert("{$wpdb->prefix}mixplatpayment", [
            'id'       => $payment_id,
            'order_id' => $order_id,
            'amount'   => $amount,
            'status'   => 'new',
            'date'     => current_time('mysql'),
        ], ['%s', '%d', '%s', '%s', '%s']);
    }

    private function updateTransaction($data)
    {
        global $wpdb;
        $wpdb->update("{$wpdb->prefix}mixplatpayment", [
            'status'          => $data['status'],
            'status_extended' => $data['status_extended'],
            'extra'           => json_encode($data),
        ], [
            'id' => $data['payment_id'],
        ], ['%s', '%s', '%s']);
    }

    /**
     * @throws Exception
     */
    public function notification()
    {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);
        if (!$data) {
            wp_die('Incorrect data', 'Incorrect data', ['response' => 403]);
        }
        if (!$this->isValidRequest()) {
            wp_die('Not valid source', 'Not valid source', ['response' => 403]);
        }
        $sign = MixplatLib::calcActionSignature($data, $this->api_key);
        if (strcmp($sign, $data['signature']) !== 0) {
            wp_die('Incorrect signature', 'Incorrect signature', ['response' => 403]);
        }
        $this->updateTransaction($data);
        if (
            $data['status'] === 'success'
            || $data['status_extended'] === 'pending_authorized') {
            $order_id = intval($data['merchant_payment_id']);
            if (function_exists("wc_get_order")) {
                $order = wc_get_order($order_id);
            } else {
                $order = new WC_Order($order_id);
            }
            if ($order->has_status('completed')) {
                exit();
            }
            $order->payment_complete();
            header('Content-Type: application/json');
            echo json_encode(['result' => 'ok']);
        }
        exit();
    }

    public function isValidRequest()
    {
        if ($this->check_ip) {
            $mixplatIpList = explode("\n", $this->mixplat_ip_list);
            $mixplatIpList = array_map(function ($item) {
                return trim($item);
            }, $mixplatIpList);
            $ip = $this->getClientIp();
            if (!in_array($ip, $mixplatIpList)) {
                return false;
            }
        }
        return true;
    }

    public function getClientIp()
    {
        $keys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];
        foreach ($keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip); // just to be safe

                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
    }
}
