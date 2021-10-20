<?php

class ControllerExtensionPaymentOsongw extends Controller 
{

  const API_VERSION = 1;

  public function index() 
  {
    $this->language->load('extension/payment/osongw');
    $this->load->model('checkout/order');

    $checkout_data = $this->process_payment();

    //error_log('checkout_data '.json_encode($checkout_data));

    if ($checkout_data) {
      $data['action'] = $checkout_data->pay_url;
      $data['token']  = $checkout_data->transaction_id;
    } else {
      $data['token'] = false;
    }

    $data['button_confirm'] = $this->language->get('button_confirm');
    $data['token_error']    = $this->language->get('token_error');
    $data['order_id']       = $this->session->data['order_id'];

    return $this->load->view('extension/payment/osongw', $data);
  }

  public function process_payment()
  {
    $this->load->model('checkout/order');

    $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
    $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

    $callback_url = $this->url->link('extension/payment/osongw/webhook', '', 'SSL');

    return $this->query("invoice/create", [
        'transaction_id'=> uniqid(),
        'user_account'  => $order_info['email'],
        'comment'		    => $this->language->get('text_order'). '::' .$order_info['order_id'],
        'currency'		  => "UZS", 
        'amount'		    => $order_info['total'],
        'phone'    		  => $order_info['telephone'],
        'lang'			    => $this->_language($this->session->data['language']),
        'lifetime'		  => 30,
        'return_url'
            => $this->url->link('extension/payment/osongw/callback', '', 'SSL').'?order_id='.$order_info['order_id'],
        'notify_url'
            => $callback_url.'?order_id='.$order_info['order_id']
      ]
    );
  }

  public function query ($method, $params_post = null)
  {
    $ch  = curl_init();

    if (is_array($params_post)) {       
    	curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS,  
        json_encode(array_merge($params_post, ['merchant_id' => $this->config->get('payment_osongw_companyid')])));
    } 

    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'token: '.$this->config->get('payment_osongw_encyptionkey')
    ];

    curl_setopt($ch, CURLOPT_URL,  $this->config->get('payment_osongw_domain_payment_page') . $method );
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    $result = json_decode($response);

    if (isset($result->type) &&  $result->type === 'ERROR' || $errno > 0) {
      $this->log->write("Error #{$errno}. {$result->message}");
      return false;
    }

    return $result;
  }

  public function callback() {

    $order_id = empty($this->session->data['order_id']) ? $_GET['order_id'] : $this->session->data['order_id'];
    $this->load->model('checkout/order');
    $order_info = $this->model_checkout_order->getOrder($order_id);
    $this->response->redirect($this->url->link('checkout/success', '', 'SSL'));
  }

  public function webhook() 
  {
    $data = file_get_contents ("php://input");
    if ( empty($data) ) {
      echo "Invalid request";
      exit();
    }

    $data = json_decode ($data, true);
    
    $token 		   = $this->config->get('payment_osongw_encyptionkey');
    $merchant_id = $this->config->get('payment_osongw_companyid');
    $parameters = "{$data['transaction_id']}:{$data['bill_id']}:{$data['status']}";
    $signature  = hash('sha256', hash('sha256', "{$token}:{$merchant_id}").":{$parameters}");
    
    if ( $signature === $data['signature']) { 

      $order_id = empty($this->session->data['order_id']) ? $_GET['order_id'] : $this->session->data['order_id'];
      $this->log->write("Webhook received: $data");
      $this->load->model('checkout/order');

      //error_log('order_id:'.$order_id);
  
      $order_info = $this->model_checkout_order->getOrder($order_id);
  
      //error_log('order_info:'.json_encode($order_info));

      if ($order_info) {
        $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('config_order_status_id'));
  
        if ( in_array($data['status'], ['REGISTRED', 'PAID']) ){
          $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('payment_osongw_completed_status_id'),
              "UID: {$data['transaction_id']}", true);
        }
        if ( in_array($data['status'], ['DECLINED', 'PAY_ERROR', 'EXPIRED']) ){
          $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('payment_osongw_failed_status_id'), 
              "UID: {$data['transaction_id']}", true);
        }
      } 
    }
    exit;
  }

  public function _language($lang_id) 
  {
    return strtolower(substr($lang_id, 0, 2));
  }
}
