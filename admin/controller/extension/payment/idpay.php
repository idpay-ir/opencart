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
    private $error = array();

    public function index()
    {
        $this->load->language('extension/payment/idpay');

        $this->document->setTitle(strip_tags($this->language->get('heading_title')));

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_idpay', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->error['api_key'])) {
            $data['error_api_key'] = $this->error['api_key'];
        } else {
            $data['error_api_key'] = '';
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/idpay', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/payment/idpay', 'user_token=' . $this->session->data['user_token'], true);

        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

        if (isset($this->request->post['payment_idpay_api_key'])) {
            $data['payment_idpay_api_key'] = $this->request->post['payment_idpay_api_key'];
        } else {
            $data['payment_idpay_api_key'] = $this->config->get('payment_idpay_api_key');
        }


        if (isset($this->request->post['payment_idpay_order_status_id'])) {
            $data['payment_idpay_order_status_id'] = $this->request->post['payment_idpay_order_status_id'];
        } else {
            $data['payment_idpay_order_status_id'] = $this->config->get('payment_idpay_order_status_id');
        }

        $this->load->model('localisation/order_status');

        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        if (isset($this->request->post['payment_idpay_status'])) {
            $data['payment_idpay_status'] = $this->request->post['payment_idpay_status'];
        } else {
            $data['payment_idpay_status'] = $this->config->get('payment_idpay_status');
        }

        if (isset($this->request->post['payment_idpay_sort_order'])) {
            $data['payment_idpay_sort_order'] = $this->request->post['payment_idpay_sort_order'];
        } else {
            $data['payment_idpay_sort_order'] = $this->config->get('payment_idpay_sort_order');
        }

        if (isset($this->request->post['payment_idpay_failed_massage'])) {
            $data['payment_idpay_failed_massage'] = $this->request->post['payment_idpay_failed_massage'];
        } else {
            $data['payment_idpay_failed_massage'] = $this->config->get('payment_idpay_failed_massage');
        }

        if (isset($this->request->post['payment_idpay_success_massage'])) {
            $data['payment_idpay_success_massage'] = $this->request->post['payment_idpay_success_massage'];
        } else {
            $data['payment_idpay_success_massage'] = $this->config->get('payment_idpay_success_massage');
        }

        if (isset($this->request->post['payment_idpay_sandbox'])) {
            $data['payment_idpay_sandbox'] = $this->request->post['payment_idpay_sandbox'];
        } else {
            $data['payment_idpay_sandbox'] = $this->config->get('payment_idpay_sandbox');
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/idpay', $data));
    }

    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/payment/idpay')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!$this->request->post['payment_idpay_api_key']) {
            $this->error['api_key'] = $this->language->get('error_api_key');
        }

        return !$this->error;
    }
}

?>
