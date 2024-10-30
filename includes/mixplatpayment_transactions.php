<?php class Mixplatpayment_Transactions extends WP_List_Table
{
    function __construct()
    {
        parent::__construct([
            'singular' => 'mixplatpayment',
            'plural'   => 'mixplatpayments',
            'ajax'     => false,
        ]);

        $this->_column_headers = [$this->get_columns()];

        $this->prepare_items();
    }

    public function no_items()
    {
        esc_html_e('- нет данных -', 'woocommerce-gateway-mixplatpayment-payments');
    }

    public function get_columns()
    {
        return [
            'id'       => __('Id платежа', 'woocommerce-gateway-mixplatpayment-payments'),
            'order_id' => __('Id заказа', 'woocommerce-gateway-mixplatpayment-payments'),
            'amount'   => __('Сумма заказа', 'woocommerce-gateway-mixplatpayment-payments'),
            'date'     => __('Дата транзакции', 'woocommerce-gateway-mixplatpayment-payments'),
            'status'   => __('Статус', 'woocommerce-gateway-mixplatpayment-payments'),
            'action'   => __('Действие', 'woocommerce-gateway-mixplatpayment-payments'),
        ];
    }

    public function prepare_items()
    {
        global $wpdb;
        $per_page = 30;
        $cur_page = $this->get_pagenum();
        $from = ($cur_page - 1) * $per_page;

        $search_term = trim(sanitize_text_field(wp_unslash(isset($_REQUEST['s']) ? $_REQUEST['s'] : '')));
        $where = '1';
        if (!empty($search_term)) {
            $like = "%%%$search_term%%%";
            $where .= $wpdb->prepare(' AND ( id LIKE %s OR order_id LIKE %s)', $like, $like);
        }

        $data = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mixplatpayment WHERE $where order by order_id DESC limit %d, %d", $from, $per_page), ARRAY_A);
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}mixplatpayment WHERE $where");

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);

        $this->items = $data;
    }

    function column_default($item, $column_name)
    {
        $current_status = isset($item['status']) ? $item['status'] : '';
        $status_extended = isset($item['status_extended']) ? $item['status_extended'] : '';
        $amount = isset($item['amount']) ? $item['amount'] : 0;
        $order_id = isset($item['order_id']) ? $item['order_id'] : '';
        switch ($column_name) {
            case 'amount':
                return number_format(round($amount / 100, 2), 2, '.', '');
            case 'status':
                return $this->mixplatpayment_get_status_name($current_status, $status_extended);
            case 'action':
                if ($current_status === 'pending')
                    $current_status = $status_extended;

                if (in_array($current_status, ["success", "pending_authorized"]) && $amount > 0) {
                    ob_start(); ?>
                    <form method="POST" action="">
                        <?php wp_nonce_field('bulk-mixplatpayments'); ?>
                        <input type="text" name="sum" value="<?php echo esc_attr($amount) ?>" style="width:100%"
                               size="9">
                        <input type="hidden" name="order_id" value="<?php echo esc_attr($order_id) ?>">
                        <br>
                        <div style="white-space:nowrap">
                            <?php if ($current_status == "success") { ?>
                                <button class="button" type="submit" name="action" value="return">
                                    <?php esc_html_e('Возврат', 'woocommerce-gateway-mixplatpayment-payments') ?>
                                </button>
                            <?php } ?>
                            <?php if ($current_status == "pending_authorized") { ?>
                                <button class="button" type="submit" name="action" value="cancel">
                                    <?php esc_html_e(__('Отмена', 'woocommerce-gateway-mixplatpayment-payments')) ?>
                                </button>
                                <button class="button" type="submit" name="action" value="confirm">
                                    <?php esc_html_e(__('Завершение', 'woocommerce-gateway-mixplatpayment-payments')) ?>
                                </button>
                            <?php } ?>
                        </div>
                    </form>
                    <?php return ob_get_clean();
                }
                return '';
            default:
                return isset($item[$column_name]) ? $item[$column_name] : '';
        }
    }

    private function mixplatpayment_get_status_name($status, $extended)
    {
        if (in_array($status, ['pending', 'failure'])) {
            $status = $extended;
        }

        switch ($status) {
            case 'new':
                return __('Платеж создан', 'woocommerce-gateway-mixplatpayment-payments');
            case 'pending':
                return __('Ожидается оплата', 'woocommerce-gateway-mixplatpayment-payments');
            case 'success':
                return __('Оплачен', 'woocommerce-gateway-mixplatpayment-payments');
            case 'failure_not_enough_money':
            case 'failure_no_money':
                return __('Платёж неуспешен: Недостаточно средств у плательщика', 'woocommerce-gateway-mixplatpayment-payments');
            case 'failure_gate_error':
                return __('Платёж неуспешен: Ошибка платёжного шлюза', 'woocommerce-gateway-mixplatpayment-payments');
            case 'failure_canceled_by_user':
                return __('Платёж неуспешен: Отменён плательщиком', 'woocommerce-gateway-mixplatpayment-payments');
            case 'failure_canceled_by_merchant':
                return __('Платёж неуспешен: Отменён ТСП', 'woocommerce-gateway-mixplatpayment-payments');
            case 'failure_previous_payment':
                return __('Платёж неуспешен: Не завершён предыдущий платёж', 'woocommerce-gateway-mixplatpayment-payments');
            case 'failure_not_available':
                return __('Платёж неуспешен: Услуга недоступна плательщику', 'woocommerce-gateway-mixplatpayment-payments');
            case 'failure_accept_timeout':
                return __('Платёж неуспешен: Превышено время ожидания подтверждения платежа плательщиком', 'woocommerce-gateway-mixplatpayment-payments');
            case 'failure_limits':
                return __('Платёж неуспешен: Превышены лимиты оплат', 'woocommerce-gateway-mixplatpayment-payments');
            case 'failure_other':
                return __('Платёж неуспешен: Прочая ошибка', 'woocommerce-gateway-mixplatpayment-payments');
            case 'failure_min_amount':
                return __('Платёж неуспешен: Сумма платежа меньше минимально допустимой', 'woocommerce-gateway-mixplatpayment-payments');
            case 'failure_pending_timeout':
                return __('Платёж неуспешен: Превышено время обработки платежа', 'woocommerce-gateway-mixplatpayment-payments');
            case 'pending_draft':
                return __('Платёж обрабатывается: Ещё не выбран платёжный метод', 'woocommerce-gateway-mixplatpayment-payments');
            case 'pending_queued':
                return __('Платёж обрабатывается: Ожидание отправки в шлюз', 'woocommerce-gateway-mixplatpayment-payments');
            case 'pending_processing':
                return __('Платёж обрабатывается: Обрабатывается шлюзом', 'woocommerce-gateway-mixplatpayment-payments');
            case 'pending_check':
                return __('Платёж обрабатывается: Ожидается ответ ТСП на CHECK-запрос', 'woocommerce-gateway-mixplatpayment-payments');
            case 'pending_authorized':
                return __('Платёж авторизован', 'woocommerce-gateway-mixplatpayment-payments');
        }
        return '';
    }
}