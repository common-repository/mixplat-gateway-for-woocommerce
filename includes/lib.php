<?php

class MixplatLib
{
    const VERSION = "1.0.0";
    const URL = "https://api.mixplat.com/";

    /**
     * @param $data
     * @param $key
     * @return string
     */
    public static function calcPaymentSignature($data, $key)
    {
        return md5($data['request_id'] . $data['project_id'] . $data['merchant_payment_id'] . $key);
    }

    /**
     * @param $data
     * @param $key
     * @return string
     */
    public static function calcActionSignature($data, $key)
    {
        return md5($data['payment_id'] . $key);
    }

    /**
     * @return string
     */
    public static function getIdempotenceKey()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * @param $method
     * @param $data
     * @return mixed
     * @throws MixplatException
     */
    public static function request($method, $data)
    {
        $data = array_merge(['api_version' => 3], $data);
        $url = self::URL . $method;

        $response = wp_remote_post($url, [
            'timeout'    => apply_filters('woocommerce_mixplatpayment_http_request_timeout', 45),
            'user-agent' => 'MixplatLib ' . self::VERSION,
            'headers'    => ['Content-Type' => 'application/json'],
            'body'       => json_encode($data),
        ]);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $error_code = $response->get_error_code();
            throw new MixplatException("Response error: $error_code, $error_message");
        } else {
            $http_code = isset($response['response']['code']) ? $response['response']['code'] : 0;
            $body = isset($response['body']) ? $response['body'] : '';
            $result = json_decode($body);

            if ($http_code != 200)
                throw new MixplatException("Response code: $http_code, $body");

            if (!$result)
                throw new MixplatException("Response decoding error. $body");

            if ($result->result != "ok")
                throw new MixplatException($result->error_description);

            return $result;
        }
    }

    /**
     * @throws MixplatException
     */
    public static function createPayment($data)
    {
        return self::request('create_payment_form', $data);
    }

    /**
     * @throws MixplatException
     */
    public static function refundPayment($data)
    {
        return self::request('refund_payment', $data);
    }

    /**
     * @throws MixplatException
     */
    public static function cancelPayment($data)
    {
        return self::request('cancel_payment', $data);
    }

    /**
     * @throws MixplatException
     */
    public static function confirmPayment($data)
    {
        return self::request('confirm_payment', $data);
    }

    /**
     * @throws MixplatException
     */
    public static function getPaymentStatus($data)
    {
        return self::request('get_payment_status', $data);
    }

    public static function normalizeReceiptItems($items, $total)
    {
        $result = [];
        $realTotal = 0;
        foreach ($items as $item) {
            $realTotal += $item['sum'];
        }
        if (abs($realTotal - $total) > 0.0001) {
            $subtotal = 0;
            $coef = $total / $realTotal;
            $lastItem = count($items) - 1;
            foreach ($items as $id => $item) {
                if ($id == $lastItem) {
                    $sum = $total - $subtotal;
                    $item['sum'] = $sum;
                    $result[] = $item;
                } else {
                    $sum = intval(round($item['sum'] * $coef));
                    $item['sum'] = $sum;
                    $subtotal += $sum;
                    $result[] = $item;
                }
            }
        } else {
            $result = $items;
        }
        return $result;
    }
}

class MixplatException extends Exception
{
}
