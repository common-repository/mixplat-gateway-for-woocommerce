<?php
/**
 * Plugin Name: Mixplat Gateway for WooCommerce
 * Plugin URI: https://github.com/MXPLTdev/mixplat_woocommerce_plugin
 * Description: Прием платежей на сайте под управлением Wordpress + WooCommerce с помощью банковских карт (интернет-эквайринг), СБП, Yandex.Pay и мобильных платежей.
 * Version: 1.0.4
 * Author: Миксплат
 * Author URI: https://mixplat.ru
 * Copyright: © ООО «Миксплат Процессинг».
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: woocommerce-gateway-mixplatpayment-payments
 * Domain Path: /languages
 **/

if (!defined('MIXPLAT_PLUGIN_FILE')) {
    define('MIXPLAT_PLUGIN_FILE', __FILE__);
}

class WC_Mixplatpayment
{

    private $settings;

    public function __construct()
    {
        register_activation_hook(MIXPLAT_PLUGIN_FILE, [$this, 'install_mixplatpayment']);

        $this->settings = get_option('woocommerce_mixplatpayment_settings');
        if (empty($this->settings['api_key']))
            $this->settings['api_key'] = '';

        add_action('init', [$this, 'init_gateway']);
    }

    public function init_gateway()
    {
        load_plugin_textdomain('woocommerce-gateway-mixplatpayment-payments', false, dirname(plugin_basename(MIXPLAT_PLUGIN_FILE)) . '/languages/');

        if (!class_exists('WC_Payment_Gateway'))
            return;

        include_once __DIR__ . '/includes/class-wc-gateway-mixplatpayment.php';
        include_once __DIR__ . '/includes/lib.php';

        add_filter('woocommerce_payment_gateways', [$this, 'add_gateway']);

        if ($this->settings['enabled'] == 'no')
            return;

        if (current_user_can('manage_woocommerce')) {
            add_action('admin_notices', [$this, 'bulk_action_handler']);

            add_action('admin_menu', [$this, 'mixplatpayment_menu']);
        }
    }

    public function add_gateway($methods)
    {
        $methods[] = 'WC_Gateway_Mixplatpayment';
        return $methods;
    }

    public function install_mixplatpayment()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'mixplatpayment';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name
        (
            `id` varchar(36)  NOT NULL,
            `order_id` int(11) NOT NULL,
            `status` varchar(20)  NOT NULL,
            `status_extended` varchar(30)  NOT NULL,
            `date` datetime NOT NULL,
            `extra` text,
            `amount` int(11) NOT NULL,
            PRIMARY KEY (order_id)
        ) $charset_collate;
        ";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    function mixplatpayment_menu()
    {
        $icon = 'data:image/svg+xml;base64,' . base64_encode(file_get_contents(dirname(MIXPLAT_PLUGIN_FILE) . '/assets/images/icons/menu.svg'));
        add_menu_page(__('Платежные транзакции', 'woocommerce-gateway-mixplatpayment-payments'), __('Mixplat: транзакции', 'woocommerce-gateway-mixplatpayment-payments'), 'manage_woocommerce', 'mixplatpayment', [$this, "mixplatpayment_transactions"], $icon, '55.6');
    }

    public function mixplatpayment_transactions()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        include_once __DIR__ . '/includes/mixplatpayment_transactions.php';

        ?>
        <div class="wrap">
            <h2><?php echo get_admin_page_title() ?></h2>
            <form method="POST" action="">
                <?php
                $table = new Mixplatpayment_Transactions();
                $table->search_box(esc_html__('Поиск по заказам', 'woocommerce-gateway-mixplatpayment-payments'), 'mixplat-search');
                $table->display();
                ?>
            </form>
        </div>
        <?php
    }

    public function bulk_action_handler()
    {
        if (empty($_POST['action']) || empty($_POST['_wpnonce']))
            return;

        if (!wp_verify_nonce($_POST['_wpnonce'], 'bulk-mixplatpayments'))
            wp_die('nonce error');

        try {
            switch ($_POST['action']) {
                case 'return':
                    $this->mixplatpayment_return_payment();
                    break;
                case 'cancel':
                    $this->mixplatpayment_cancel_payment();
                    break;
                case 'confirm':
                    $this->mixplatpayment_confirm_payment();
                    break;
            }
            printf('<div class="updated"><p>%s</p></div>', esc_html__('Выполнено успешно', 'woocommerce-gateway-mixplatpayment-payments'));
        } catch (Exception $e) {
            printf('<div class="error"><p>%s %s</p></div>', esc_html__('Ошибка запроса:', 'woocommerce-gateway-mixplatpayment-payments'), esc_html(print_r($e->getMessage(), true)));
        }
    }

    /**
     * @return array|object|stdClass
     * @throws MixplatException
     */
    private function mixplatpayment_get_payment()
    {
        if (empty($_POST['order_id']))
            throw new MixplatException(__('Заказ не найден', 'woocommerce-gateway-mixplatpayment-payments'));

        global $wpdb;
        $data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mixplatpayment WHERE order_id = %d", intval($_POST['order_id'])));

        if (empty($data))
            throw new MixplatException(__('Платеж не найден', 'woocommerce-gateway-mixplatpayment-payments'));

        return $data;
    }

    /**
     * @throws MixplatException
     */
    private function mixplatpayment_return_payment()
    {
        $data = $this->mixplatpayment_get_payment();
        $query = [
            'payment_id' => $data->id,
            'amount'     => intval($_POST['sum'] * 100),
        ];
        $query['signature'] = MixplatLib::calcActionSignature($query, $this->settings['api_key']);
        MixplatLib::refundPayment($query);
    }

    /**
     * @throws MixplatException
     */
    private function mixplatpayment_confirm_payment()
    {
        global $wpdb;
        $data = $this->mixplatpayment_get_payment();
        $amount = intval($_POST['sum'] * 100);
        $query = [
            'payment_id' => $data->id,
            'amount'     => $amount,
        ];
        $query['signature'] = MixplatLib::calcActionSignature($query, $this->settings['api_key']);
        MixplatLib::confirmPayment($query);
        $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}mixplatpayment set status = 'success', status_extended = 'success_success', amount = %d where id = %s", $amount, $data->id));
    }

    /**
     * @throws MixplatException
     */
    private function mixplatpayment_cancel_payment()
    {
        global $wpdb;
        $data = $this->mixplatpayment_get_payment();
        $query = [
            'payment_id' => $data->id,
        ];
        $query['signature'] = MixplatLib::calcActionSignature($query, $this->settings['api_key']);
        MixplatLib::cancelPayment($query);
        $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}mixplatpayment set status = 'failure', status_extended = 'failure_canceled_by_merchant' where id = %s", $data->id));
    }
}

$GLOBALS['wc_mixplatpayment'] = new WC_Mixplatpayment();