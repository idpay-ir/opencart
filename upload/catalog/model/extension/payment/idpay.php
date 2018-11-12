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
class ModelExtensionPaymentIdpay extends Model
{
    public function getMethod($address)
    {
        if ($this->config->get('payment_idpay_status')) {
            $status = true;
        } else {
            $status = false;
        }
        $method_data = array();
        if ($status) {
            $method_data = array(
                'code' => 'idpay',
                'title' => $this->config->get('payment_idpay_title'),
                'terms' => '',
                'sort_order' => $this->config->get('payment_idpay_sort_order')
            );
        }
        return $method_data;
    }
}

?>
