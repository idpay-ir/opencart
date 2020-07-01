<?php

class ControllerPaymentIDPay extends Controller
{
    public function generateString($id)
    {
        return 'IDPay Transaction ID: ' . $id;
    }

    public function index()
    {
        $this->load->language('payment/idpay');
        $this->load->model('checkout/order');

        /** @var \ModelCheckoutOrder $model */
        $model = $this->model_checkout_order;

        $order_info = $model->getOrder($this->session->data['order_id']);

        $encryption = new Encryption($this->config->get('config_encryption'));
        $sandbox = $this->config->get('idpay_sandbox') == 'yes' ? 'true' : 'false';

        $amount = $this->correctAmount($order_info);

        $data['text_wait'] = $this->language->get('text_wait');

        $data['button_confirm'] = $this->language->get('button_confirm');

        $data['error_warning'] = false;

        if (extension_loaded('curl')) {

            $api = $this->config->get('idpay_api_key');
            $callback = $this->url->link('payment/idpay/callback', 'order_id=' . $encryption->encrypt($order_info['order_id']), '', 'SSL');

            $order_id = $order_info['order_id'];
            $desc = 'پرداخت سفارش ' . $order_info['order_id'];

            // Customer information
            $name = $order_info['firstname'] . ' ' . $order_info['lastname'];
            $mail = $order_info['email'];
            $phone = $order_info['telephone'];

            $params = array(
                'order_id' => $order_id,
                'amount' => $amount,
                'name' => $name,
                'phone' => $phone,
                'mail' => $mail,
                'desc' => $desc,
                'callback' => $callback,
            );

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.idpay.ir/v1.1/payment');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'X-API-KEY: ' . $api,
                'X-SANDBOX: ' . $sandbox,
            ));
            $result = curl_exec($ch);
            $result = json_decode($result);
            $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_status != 201 || empty($result) || empty($result->id) || empty($result->link)) {
                $msg = sprintf($this->language->get('error_create_payment'), $http_status, $result->error_code, $result->error_message);
                $data['error_warning'] = $msg;
                $model->addOrderHistory($order_id, 10, $msg, true);
            } else {
                $model->addOrderHistory($order_id, 1, $this->generateString($result->id), false);
                $model->addOrderHistory($order_id, 1, 'در حال هدایت به درگاه پرداخت آیدی پی', false);
                $data['action'] = $result->link;
            }

        } else {
            $data['error_warning'] = $this->language->get('error_curl');
        }

        return $this->load->view('default/template/payment/idpay.tpl', $data);
    }

    public function callback()
    {
        $this->load->language('payment/idpay');
        $this->load->model('checkout/order');

        /** @var \ModelCheckoutOrder $model */
        $model = $this->model_checkout_order;

        $this->document->setTitle($this->language->get('heading_title'));
        $sandbox = $this->config->get('idpay_sandbox') == 'yes' ? 'true' : 'false';

        $encryption = new Encryption($this->config->get('config_encryption'));

        $order_id = isset($this->session->data['order_id']) ? $this->session->data['order_id'] : false;
        $order_id = isset($order_id) ? $order_id : $encryption->decrypt($this->request->get['order_id']);

        $order_info = $model->getOrder($order_id);
        $data['heading_title'] = $this->language->get('heading_title');
        $data['button_continue'] = $this->language->get('button_continue');
        $data['continue'] = $this->url->link('common/home', '', 'SSL');
        $data['error_warning'] = '';

        $status = empty($this->request->post['status']) ? NULL : $this->request->post['status'];
        $track_id = empty($this->request->post['track_id']) ? NULL : $this->request->post['track_id'];
        $id = empty($this->request->post['id']) ? NULL : $this->request->post['id'];
        $order_id = empty($this->request->post['order_id']) ? NULL : $this->request->post['order_id'];
        //$amount = empty($this->request->post['amount']) ? NULL : $this->request->post['amount'];
        $card_no = empty($this->request->post['card_no']) ? NULL : $this->request->post['card_no'];
        $date = empty($this->request->post['date']) ? NULL : $this->request->post['date'];

        if (!$order_info) {
            $comment = $this->idpay_get_failed_message($track_id, $order_id);
            $model->addOrderHistory($order_id, 10, $this->otherStatusMessages(), true);
            $data['error_warning'] = $comment;
            $data['button_continue'] = $this->language->get('button_view_cart');
            $data['continue'] = $this->url->link('checkout/cart');

        } else {

            if ($status != 10) {
                $comment = $this->idpay_get_failed_message($track_id, $order_id, $status);
                $data['error_warning'] = $comment;
                $model->addOrderHistory($order_id, 10, $this->otherStatusMessages($status), true);
                $data['button_continue'] = $this->language->get('button_view_cart');
                $data['continue'] = $this->url->link('checkout/cart');
            } else {
                $amount = $this->correctAmount($order_info);
                $api_key = $this->config->get('idpay_api_key');
                $idpay_data = array(
                    'id' => $id,
                    'order_id' => $order_id,
                );
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://api.idpay.ir/v1.1/payment/verify');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($idpay_data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'X-API-KEY:' . $api_key,
                    'X-SANDBOX: ' . $sandbox,
                ));
                $result = curl_exec($ch);
                $result = json_decode($result);

                $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($http_status != 200) {
                    $comment = sprintf($this->language->get('error_verify_payment'), $http_status, $result->error_code, $result->error_message);
                    $data['error_warning'] = $comment;
                    $model->addOrderHistory($order_id, 10, $comment, true);
                    $data['button_continue'] = $this->language->get('button_view_cart');
                    $data['continue'] = $this->url->link('checkout/cart');
                } else {
                    $verify_status = empty($result->status) ? NULL : $result->status;
                    $verify_track_id = empty($result->track_id) ? NULL : $result->track_id;
                    $verify_order_id = empty($result->order_id) ? NULL : $result->order_id;
                    $verify_amount = empty($result->amount) ? NULL : $result->amount;

                    //get result id from database
                    $sql = $this->db->query('SELECT `comment`  FROM ' . DB_PREFIX . 'order_history WHERE order_id = ' . $order_id . ' AND `comment` LIKE "' . $this->generateString($result->id) . '"');
                    if (empty($verify_status) || empty($verify_track_id) || empty($verify_amount) || $verify_amount != $amount || $verify_status < 100) {
                        $comment = $this->idpay_get_failed_message($verify_track_id, $verify_order_id);
                        $data['error_warning'] = $comment;
                        $data['button_continue'] = $this->language->get('button_view_cart');
                        $data['continue'] = $this->url->link('checkout/cart');
                    } elseif ($order_id !== $result->order_id or count($sql->row) == 0) {
                        //check double spending
                        $comment = $this->idpay_get_failed_message($track_id, $order_id, 0);
                        $model->addOrderHistory($order_id, 10, $this->otherStatusMessages($status), true);
                        $data['error_warning'] = $comment;
                        $data['button_continue'] = $this->language->get('button_view_cart');
                        $data['continue'] = $this->url->link('checkout/cart');

                    } else { // Transaction is successful.
                        $comment = $this->idpay_get_success_message($verify_track_id, $verify_order_id);
                        $config_successful_payment_status = $this->config->get('idpay_order_status_id');
                        // Set Order status id to the configured status id and add a history.
                        $model->addOrderHistory($verify_order_id, $config_successful_payment_status, $comment, true);
                        // Add another history.
                        $comment2 = 'status: ' . $result->status . ' - track id: ' . $result->track_id . ' - card no: ' . $result->payment->card_no . ' - hashed card no: ' . $result->payment->hashed_card_no;
                        $model->addOrderHistory($verify_order_id, $config_successful_payment_status, $comment2, true);
                        $data['payment_result'] = $comment;
                        $data['button_continue'] = $this->language->get('button_complete');
                        $data['continue'] = $this->url->link('checkout/success');
                    }
                }
            }
        }

        // Breadcrumbs
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home', '', true)
        );

        if ($data['error_warning']) {
            $data['breadcrumbs'][] = array(

                'text' => $this->language->get('text_basket'),
                'href' => $this->url->link('checkout/cart', '', 'SSL')
            );

            $data['breadcrumbs'][] = array(

                'text' => $this->language->get('text_checkout'),
                'href' => $this->url->link('checkout/checkout', '', 'SSL')
            );
        }

        $data['header'] = $this->load->controller('common/header');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('default/template/payment/idpay_callback.tpl', $data));
    }

    private function idpay_get_success_message($track_id, $order_id)
    {
        return str_replace(["{track_id}", "{order_id}"], [$track_id, $order_id], $this->config->get('idpay_payment_successful_message'));
    }

    private function idpay_get_failed_message($track_id, $order_id, $msgNumber = null)
    {
        $msg = $this->otherStatusMessages($msgNumber);
        $msg = str_replace(["{track_id}", "{order_id}"], [$track_id, $order_id], $this->config->get('idpay_payment_failed_message')) . "<br>" . $msg;
        return $msg;
    }

    private function correctAmount($order_info)
    {
        $amount = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);
        $amount = round($amount);
        $amount = $this->currency->convert($amount, $order_info['currency_code'], "RLS");
        return (int)$amount;
    }

    /**
     * @param null $msgNumber
     * @return string
     */
    public function otherStatusMessages($msgNumber = null)
    {

        switch ($msgNumber) {
            case "1":
                $msg = "پرداخت انجام نشده است";
                break;
            case "2":
                $msg = "پرداخت ناموفق بوده است";
                break;
            case "3":
                $msg = "خطا رخ داده است";
                break;
            case "3":
                $msg = "بلوکه شده";
                break;
            case "5":
                $msg = "برگشت به پرداخت کننده";
                break;
            case "6":
                $msg = "برگشت خورده سیستمی";
                break;
            case "7":
                $msg = "انصراف از پرداخت";
                break;
            case "8":
                $msg = "به درگاه پرداخت منتقل شد";
                break;
            case "10":
                $msg = "در انتظار تایید پرداخت";
                break;
            case "100":
                $msg = "پرداخت تایید شده است";
                break;
            case "101":
                $msg = "پرداخت قبلا تایید شده است";
                break;
            case "200":
                $msg = "به دریافت کننده واریز شد";
                break;
            case "0":
                $msg = "سواستفاده از تراکنش قبلی";
                break;
            case null:
                $msg = "خطا دور از انتظار";
                $msgNumber = '1000';
                break;
        }

        return $msg . ' -وضعیت: ' . "$msgNumber";

    }

}

