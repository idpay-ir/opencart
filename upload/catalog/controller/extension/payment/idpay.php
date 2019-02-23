<?php
/**
 * IDPay payment gateway
 *
 * @developer JMDMahdi
 * @publisher IDPay
 * @copyright (C) 2018 IDPay
 * @license http://www.gnu.org/licenses/gpl-2.0.html GPLv2 or later
 *
 * http://idpay.ir
 */
class ControllerExtensionPaymentIdpay extends Controller
{
    public function index()
    {
        $this->load->language('extension/payment/idpay');

        $data['text_connect'] = $this->language->get('text_connect');
        $data['text_loading'] = $this->language->get('text_loading');
        $data['text_wait'] = $this->language->get('text_wait');

        $data['button_confirm'] = $this->language->get('button_confirm');

        return $this->load->view('extension/payment/idpay', $data);
    }

    public function confirm()
    {
        $this->load->language('extension/payment/idpay');

        $this->load->model('checkout/order');
        /** @var \ModelCheckoutOrder $model */
        $model = $this->model_checkout_order;
        $order_id  = $this->session->data['order_id'];
        $order_info = $model->getOrder($order_id);

        $data['return'] = $this->url->link('checkout/success', '', true);
        $data['cancel_return'] = $this->url->link('checkout/payment', '', true);
        $data['back'] = $this->url->link('checkout/payment', '', true);
        $data['order_id'] = $this->session->data['order_id'];

        $api_key = $this->config->get('payment_idpay_api_key');
        $sandbox = $this->config->get('payment_idpay_sandbox') == 'yes' ? 'true' : 'false';
        $amount = $this->correctAmount($order_info);

        $desc = $this->language->get('text_order_no') . $order_info['order_id'];
        $callback = $this->url->link('extension/payment/idpay/callback', '', true);

        if (empty($amount)) {
            $json['error'] = 'واحد پول انتخاب شده پشتیبانی نمی شود.';
        }

        $idpay_data = array(
            'order_id' => $order_id,
            'amount' => $amount,
            'phone' => isset($order_info['telephone']) ? $order_info['telephone'] : "",
            'desc' => $desc,
            'callback' => $callback,
        );

        $ch = curl_init('https://api.idpay.ir/v1.1/payment');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($idpay_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'X-API-KEY:' . $api_key,
            'X-SANDBOX:' . $sandbox,
        ));

        $result = curl_exec($ch);
        $result = json_decode($result);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_status != 201 || empty($result) || empty($result->id) || empty($result->link)) {
            $json['error'] = sprintf('خطا هنگام ایجاد تراکنش. وضعیت خطا: %s - کد خطا: %s - پیام خطا: %s', $http_status, $result->error_code, $result->error_message);
        } else {
            // Add a specific history to the order with order status 1 (Pending);
            $model->addOrderHistory($order_id, 1, 'IDPay Transaction ID: '. $result->id, false);
            $model->addOrderHistory($order_id, 1, 'در حال هدایت به درگاه پرداخت آیدی پی', false);

            $data['action'] = $result->link;
            $json['success'] = $data['action'];
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function callback()
    {
        if ($this->session->data['payment_method']['code'] == 'idpay') {
            $this->load->language('extension/payment/idpay');

            $this->document->setTitle($this->config->get('payment_idpay_title'));

            $data['heading_title'] = $this->config->get('payment_idpay_title');
            $data['peyment_result'] = "";

            $data['breadcrumbs'] = array();
            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/home', '', true)
            );
            $data['breadcrumbs'][] = array(
                'text' => $this->config->get('payment_idpay_title'),
                'href' => $this->url->link('extension/payment/idpay/callback', '', true)
            );


            if (isset($this->request->get['clientrefid'])) {
                $orderid = $this->encryption->decrypt($this->config->get('config_encryption'), $this->request->get['clientrefid']);
            } else {
                if (isset($this->session->data['order_id'])) {
                    $orderid = $this->session->data['order_id'];
                } else {
                    $orderid = 0;
                }
            }

            $this->load->model('checkout/order');
            $order_info = $this->model_checkout_order->getOrder($orderid);

            if (!$order_info) {
                $data['peyment_result'] = 'سفارش موجود نیست یا قبلا اعتبار سنجی شده است.';
                $data['button_continue'] = $this->language->get('button_view_cart');
                $data['continue'] = $this->url->link('checkout/cart');
            }
            $pid = $this->request->post['id'];
            $porder_id = $this->request->post['order_id'];
            $amount = $this->correctAmount($order_info);

            if (!empty($pid) && !empty($porder_id) && $porder_id == $orderid) {
                $api_key = $this->config->get('payment_idpay_api_key');
                $sandbox = $this->config->get('payment_idpay_sandbox') == 'yes' ? 'true' : 'false';

                $idpay_data = array(
                    'id' => $pid,
                    'order_id' => $orderid,
                );

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://api.idpay.ir/v1.1/payment/verify');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($idpay_data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'X-API-KEY:' . $api_key,
                    'X-SANDBOX:' . $sandbox,
                ));

                $result = curl_exec($ch);
                $result = json_decode($result);
                $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($http_status != 200) {
                    $data['peyment_result'] = sprintf('خطا هنگام بررسی وضعیت تراکنش. کد خطا: %s', $http_status);
                    $data['button_continue'] = $this->language->get('button_view_cart');
                    $data['continue'] = $this->url->link('checkout/cart');
                }

                $inquiry_status = empty($result->status) ? NULL : $result->status;
                $inquiry_track_id = empty($result->track_id) ? NULL : $result->track_id;
                $inquiry_order_id = empty($result->order_id) ? NULL : $result->order_id;
                $inquiry_amount = empty($result->amount) ? NULL : $result->amount;

                if (empty($inquiry_status) || empty($inquiry_track_id) || empty($inquiry_amount) || $inquiry_amount != $amount || $inquiry_status != 100) {
                    $data['peyment_result'] = $this->idpay_get_failed_message($inquiry_track_id, $inquiry_order_id);
                    $data['button_continue'] = $this->language->get('button_view_cart');
                    $data['continue'] = $this->url->link('checkout/cart');
                } else {
                    $comment = $this->idpay_get_success_message($inquiry_track_id, $inquiry_order_id);
                    $this->model_checkout_order->addOrderHistory($inquiry_order_id, $this->config->get('payment_idpay_order_status_id'), $comment, true);
                    $data['peyment_result'] = $comment;
                    $data['button_continue'] = $this->language->get('button_complete');
                    $data['continue'] = $this->url->link('checkout/success');
                }
            } else {
                $data['peyment_result'] = 'کاربر از انجام تراکنش منصرف شده است';
                $data['button_continue'] = $this->language->get('button_view_cart');
                $data['continue'] = $this->url->link('checkout/cart');
            }

            $data['column_left'] = $this->load->controller('common/column_left');
            $data['column_right'] = $this->load->controller('common/column_right');
            $data['content_top'] = $this->load->controller('common/content_top');
            $data['content_bottom'] = $this->load->controller('common/content_bottom');
            $data['footer'] = $this->load->controller('common/footer');
            $data['header'] = $this->load->controller('common/header');
            $this->response->setOutput($this->load->view('extension/payment/idpay_confirm', $data));

        }
    }

    public function idpay_get_success_message($track_id, $order_id)
    {
        return str_replace(["{track_id}", "{order_id}"], [$track_id, $order_id], $this->config->get('payment_idpay_success_massage'));
    }

    public function idpay_get_failed_message($track_id, $order_id)
    {
        return str_replace(["{track_id}", "{order_id}"], [$track_id, $order_id], $this->config->get('payment_idpay_failed_massage'));
    }

    private function correctAmount($order_info)
    {
        $amount = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);
        $amount = round($amount);
        $amount = $this->currency->convert($amount, $order_info['currency_code'], "IRR");
        return (int)$amount;
    }
}

?>
