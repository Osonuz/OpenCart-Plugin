<?php
class ModelExtensionPaymentOsongw extends Model {
  public function getMethod($address, $total) {
    $this->load->language('extension/payment/osongw');

    $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('payment_osongw_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");

    if ($this->config->get('payment_osongw_total') > 0 && $this->config->get('payment_osongw_total') > $total) {
      $status = false;
    } elseif (!$this->config->get('payment_osongw_geo_zone_id')) {
      $status = true;
    } elseif ($query->num_rows) {
      $status = true;
    } else {
      $status = false;
    }

    $method_data = array();

    if ($status) {
      $method_data = array(
        'code'       => 'osongw',
        'title'      => $this->language->get('text_title'),
        'terms'      => '',
        'sort_order' => $this->config->get('payment_osongw_sort_order')
      );
    }

    return $method_data;
  }


  public function recordOrderId($id, $tr_id, $bill_id) {
    if ($id > 0) {
      $table_name = DB_PREFIX.'oson_table_manager';
      $create_table = "CREATE TABLE `$table_name` (
                      `id` int(11) NOT NULL AUTO_INCREMENT KEY,
                      `order_id` mediumint(9) NOT NULL ,
                      `transaction_id` varchar(200) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
                      `bill_id`  mediumint(9) NOT NULL ,
                      `created_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
                    );";
      $insert = "INSERT INTO `$table_name` (`order_id`, `transaction_id`, `bill_id`) VALUES ($id, '$tr_id', $bill_id);";

      try{
        $this->db->query("SELECT COUNT(*) FROM `$table_name`");
      }catch(\Exception $e){
        $this->db->query($create_table);
      }

      if ($this->db->query($insert)) {
        return true;
      }
      return false;

    }
  }

  public function getOrderId($bill_id = 0) {
    if ($bill_id > 0) {
      $select = "SELECT * FROM `oc_oson_table_manager` WHERE `bill_id` = $bill_id";
      $order = $this->db->query($select);
      if ($order->num_rows) {
        return $order->row['order_id'];
      }
    }
    return false;
  }

  public function getTransactionId($order_id = 0) {
    if ($order_id > 0) {
      $select = "SELECT * FROM `oc_oson_table_manager` WHERE `order_id` = $order_id";
      $order = $this->db->query($select);
      if ($order->num_rows) {
        return $order->row['transaction_id'];
      }
    }
    return false;
  }
}

?>
