<?php

defined('ABSPATH') || exit;

class SKU_Generator_Ajax_Generation
{
  public function __construct()
  {
    add_action('wp_ajax_generate_bulk_skus', array($this, 'ajax_generate_bulk_skus'));
  }

  public function ajax_generate_bulk_skus()
  {
    try {
      check_ajax_referer('sku_generator_nonce', 'nonce');

      if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('Insufficient permissions');
        return;
      }

      $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;

      // Use helper functions for database queries
      $products_without_skus = SKU_Generator_Helpers::get_products_without_skus($offset);
      $total_products = SKU_Generator_Helpers::count_products_without_skus();

      if (empty($products_without_skus)) {
        wp_send_json_success(array(
          'complete' => true,
          'message' => __('All SKUs generated successfully!', 'sku-generator')
        ));
        return;
      }

      $generator = new SKU_Generator_Core();
      $generated_count = 0;

      foreach ($products_without_skus as $product_id) {
        $product = wc_get_product($product_id);
        if (!$product || !empty($product->get_sku())) {
          continue;
        }

        $sku = $generator->generate_unique_sku($product);
        $product->set_sku($sku);
        $product->save();
        $generated_count++;

        error_log("SKU Generation: Generated SKU '$sku' for product ID $product_id");
      }

      $batch_size = SKU_Generator_Helpers::get_batch_size();
      $progress = min(100, round(($offset + $batch_size) / $total_products * 100));

      wp_send_json_success(array(
        'complete' => false,
        'offset' => $offset + $batch_size,
        'progress' => $progress,
        'total' => $total_products,
        'generated_this_batch' => $generated_count
      ));
    } catch (Exception $e) {
      error_log('SKU Generation Error: ' . $e->getMessage());
      wp_send_json_error('Generation error: ' . $e->getMessage());
    }
  }
}
