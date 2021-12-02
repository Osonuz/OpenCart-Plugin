<?php

class ControllerExtensionPaymentOsongw extends Controller 
{

  const API_VERSION = 1;
  private static $query_error = '';

  public function index() 
  {
    $this->language->load('extension/payment/osongw');
    $this->load->model('checkout/order');
    $this->load->model('extension/payment/osongw');

    $response = $this->process_payment();

    if ($response && !self::$query_error) {
      $this->model_extension_payment_osongw->recordOrderId(
        $this->session->data['order_id'], 
        $response->transaction_id, 
        $response->bill_id);

      $this->model_checkout_order->addOrderHistory(
        $this->session->data['order_id'], 
        $this->config->get('payment_osongw_processing_status_id'), 
        "UID: {$response->transaction_id}", 
        true);

      $data['action'] = $response->pay_url;
      $data['token']  = $response->transaction_id;
    } else {
      $data['token'] = false;
    }

    $data['button_confirm'] = $this->language->get('button_confirm');
    $data['token_error']    = self::$query_error;
    $data['order_id']       = $this->session->data['order_id'];

    return $this->load->view('extension/payment/osongw', $data);
  }

  public function process_payment()
  {
    $this->load->model('checkout/order');

    $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
    $query_order_id = 'order_id='.$order_info['order_id'];
    $callback_url = $this->url->link('extension/payment/osongw/callback', '', 'SSL');
    $callback_url .= (mb_strripos($callback_url, '?') === false) ? ('?'.$query_order_id) : ('&'.$query_order_id);

    $notify_url = $this->url->link('extension/payment/osongw/webhook', '', 'SSL');

    return $this->query("create", [
        'transaction_id'=> uniqid(),
        'user_account'  => $order_info['email'],
        'comment'       => $this->language->get('text_order'). '::' .$order_info['order_id'],
        'currency'      => "UZS", 
        'amount'        => $order_info['total'],
        'phone'         => $order_info['telephone'],
        'lang'          => $this->_language($this->session->data['language']),
        'lifetime'      => 30,
        'return_url'    => $callback_url,
        'notify_url'    => $notify_url
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

    curl_setopt($ch, CURLOPT_URL, $this->config->get('payment_osongw_domain_payment_page') . $method );
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
    $errmsg = curl_error($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    $result = json_decode($response);


    if ($errno > 0 || @$result->errno > 0) {
      $this->language->load('extension/payment/osongw');
      $message = $this->language->get('token_error');

      $err = ($errno > 0) ? "Connection error #$errno : $errmsg" : "Response error #{$result->errno} : $result->errstr";
      
      self::$query_error = "$message <br><span style='font-size:10px;'>$err</span>";

      $this->log->write($err);
      return false;
    }

    return $result;
  }

  public function callback() {

    $this->load->model('checkout/order');
    $order_id = empty($this->session->data['order_id']) ? $_GET['order_id'] : $this->session->data['order_id'];
    if (@$order_id && $this->model_checkout_order->getOrder($order_id)) {
      $this->load->model('extension/payment/osongw');
      $tr_id = $this->model_extension_payment_osongw->getTransactionId($order_id);
      $merchant_id = $this->config->get('payment_osongw_companyid');
      
      $req = $this->query("status", [
        'transaction_id' => $tr_id,
        'merchant_id'    => $merchant_id,
      ]);

      if ( in_array($req->status, ['ON_PROGRESS', 'PAID']) ){
        $this->model_checkout_order->addOrderHistory(
          $order_id, 
          $this->config->get('payment_osongw_completed_status_id'), 
          "UID: {$req->transaction_id}", 
          true);
      }
      if ( in_array($req->status, ['DECLINED', 'PAY_ERROR', 'EXPIRED']) ){
        $this->model_checkout_order->addOrderHistory(
          $order_id, 
          $this->config->get('payment_osongw_failed_status_id'), 
          "UID: {$req->transaction_id}", 
          true);
      }
    }

    $this->response->redirect($this->url->link('checkout/success', '', 'SSL'));
  }

  public function webhook() 
  {
    $req = file_get_contents ("php://input");
    if ( empty($req) ) {
      echo "Invalid request";
      exit();
    }

    $data = json_decode ($req, true);
    
    $token       = $this->config->get('payment_osongw_encyptionkey');
    $merchant_id = $this->config->get('payment_osongw_companyid');
    $parameters = "{$data['transaction_id']}:{$data['bill_id']}:{$data['status']}";
    $signature  = hash('sha256', hash('sha256', "{$token}:{$merchant_id}").":{$parameters}");

    if ( $signature === $data['signature']) {
      $this->log->write("Webhook received: ".$req);
      $this->load->model('checkout/order');
      $this->load->model('extension/payment/osongw');

      $order_id = $this->model_extension_payment_osongw->getOrderId($data['bill_id']);
      if (!$order_id) {$this->log->write('Not found: bill_id#'.$data['bill_id']); exit;}

      $order_info = $this->model_checkout_order->getOrder($order_id);
      if (!$order_info) {$this->log->write('Not found: order_id#'.$order_id); exit;}

      $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('config_order_status_id'));

      if ( in_array($data['status'], ['ON_PROGRESS', 'PAID']) ){
        $this->model_checkout_order->addOrderHistory(
          $order_id, 
          $this->config->get('payment_osongw_completed_status_id'), 
          "UID: {$data['transaction_id']}", 
          true);
      }
      if ( in_array($data['status'], ['DECLINED', 'PAY_ERROR', 'EXPIRED']) ){
        $this->model_checkout_order->addOrderHistory(
          $order_id, 
          $this->config->get('payment_osongw_failed_status_id'), 
          "UID: {$data['transaction_id']}", 
          true);
      }
    }
    exit;
  }

  public function _language($lang_id) 
  {
    return strtolower(substr($lang_id, 0, 2));
  }
}
