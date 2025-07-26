<?php

defined('ABSPATH') || exit;

class SKU_Generator_Ajax_GTIN
{
  public function __construct()
  {
    add_action('wp_ajax_copy_skus_to_gtin', array($this, 'ajax_copy_skus_to_gtin'));
  }

  public function ajax_copy_skus_to_gtin()
  {
    try {
      check_ajax_referer('sku_generator_nonce', 'nonce');

      if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('Insufficient permissions');
        return;
      }

      $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;

      if ($offset === 0) {
        $total_products = SKU_Generator_Helpers::count_products_with_skus();
        set_transient('sku_gtin_copy_total', $total_products, 300);
      } else {
        $total_products = get_transient('sku_gtin_copy_total') ?: 100;
      }

      $product_ids = SKU_Generator_Helpers::get_products_with_skus($offset);
      $copied_count = 0;

      foreach ($product_ids as $product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
          continue;
        }

        $sku = $product->get_sku();
        if (empty($sku)) {
          continue;
        }

        $this->copy_sku_to_gtin_field($product, $sku);
        $product->save();
        $copied_count++;

        error_log("GTIN Copy: Copied SKU '$sku' to GTIN field for product ID $product_id");
      }

      $batch_size = SKU_Generator_Helpers::get_batch_size();
      $progress = $total_products > 0 ? min(100, round(($offset + $batch_size) / $total_products * 100)) : 100;

      if (count($product_ids) < $batch_size || $progress >= 100) {
        delete_transient('sku_gtin_copy_total');

        wp_send_json_success(array(
          'complete' => true,
          'message' => sprintf(__('Copied SKUs to GTIN fields for %d products!', 'sku-generator'), $copied_count),
          'progress' => 100
        ));
      } else {
        wp_send_json_success(array(
          'complete' => false,
          'offset' => $offset + $batch_size,
          'progress' => $progress,
          'total' => $total_products,
          'copied_this_batch' => $copied_count
        ));
      }
    } catch (Exception $e) {
      error_log('SKU to GTIN Copy Error: ' . $e->getMessage());
      wp_send_json_error('Copy error: ' . $e->getMessage());
    }
  }

  private function copy_sku_to_gtin_field($product, $sku)
  {
    if (!$product) {
      return;
    }

    $gtin_meta_keys = SKU_Generator_Helpers::get_gtin_meta_keys();
    $used_meta_key = '';

    // Check if any GTIN field already has a value
    foreach ($gtin_meta_keys as $meta_key) {
      $value = $product->get_meta($meta_key);
      if (!empty($value)) {
        $used_meta_key = $meta_key;
        break;
      }
    }

    // If no existing GTIN field found, use the default WooCommerce GTIN field
    if (empty($used_meta_key)) {
      $used_meta_key = '_global_unique_id';
    }

    // Set the SKU as the GTIN value
    $product->update_meta_data($used_meta_key, $sku);

    error_log("GTIN Copy: Used meta key '$used_meta_key' for product ID " . $product->get_id());
  }
}
