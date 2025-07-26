<?php

defined('ABSPATH') || exit;

class SKU_Generator_Ajax_Validation
{
  public function __construct()
  {
    add_action('wp_ajax_validate_skus', array($this, 'ajax_validate_skus'));
    add_action('wp_ajax_fix_invalid_skus', array($this, 'ajax_fix_invalid_skus'));
  }

  public function ajax_validate_skus()
  {
    try {
      check_ajax_referer('sku_generator_nonce', 'nonce');

      if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('Insufficient permissions');
        return;
      }

      $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
      error_log("SKU Validation: Starting batch at offset $offset");

      global $wpdb;

      if ($offset === 0) {
        if (SKU_Generator_Helpers::is_hpos_enabled()) {
          $total_products = (int) $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}wc_products WHERE status = 'publish'");
          error_log("SKU Validation: HPOS enabled - found $total_products total products");
        } else {
          $total_products = (int) $wpdb->get_var("SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'");
          error_log("SKU Validation: Legacy mode - found $total_products total products");
        }

        if ($total_products === 0) {
          wp_send_json_success(array(
            'complete' => true,
            'total_products' => 0,
            'total_invalid' => 0,
            'total_duplicates' => 0,
            'invalid_skus' => array(),
            'duplicate_skus' => array(),
            'progress' => 100
          ));
          return;
        }

        set_transient('sku_validation_total', $total_products, 300);
        delete_transient('sku_validation_invalid');
        delete_transient('sku_validation_duplicates');
        delete_transient('sku_validation_all_skus');
      } else {
        $total_products = get_transient('sku_validation_total') ?: 100;
      }

      $batch_size = SKU_Generator_Helpers::get_batch_size();

      if (SKU_Generator_Helpers::is_hpos_enabled()) {
        $product_ids = $wpdb->get_col($wpdb->prepare("
          SELECT id FROM {$wpdb->prefix}wc_products 
          WHERE status = 'publish' 
          LIMIT %d OFFSET %d
        ", $batch_size, $offset));
      } else {
        $product_ids = $wpdb->get_col($wpdb->prepare("
          SELECT ID FROM {$wpdb->posts} 
          WHERE post_type = 'product' AND post_status = 'publish' 
          LIMIT %d OFFSET %d
        ", $batch_size, $offset));
      }

      error_log("SKU Validation: Found " . count($product_ids) . " product IDs in this batch");

      $invalid_skus = get_transient('sku_validation_invalid') ?: array();
      $duplicate_skus = get_transient('sku_validation_duplicates') ?: array();
      $all_skus = get_transient('sku_validation_all_skus') ?: array();

      $validator = new SKU_Generator_Validator();
      $products_with_skus = 0;

      foreach ($product_ids as $product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
          continue;
        }

        $sku = $product->get_sku();
        $product_name = $product->get_name();

        if (empty($sku)) {
          continue;
        }

        $products_with_skus++;

        if (!isset($all_skus[$sku])) {
          $all_skus[$sku] = array();
        }
        $all_skus[$sku][] = array(
          'id' => $product_id,
          'name' => $product_name
        );

        $validation_result = $validator->validate_sku_format($sku);
        if (!$validation_result['valid']) {
          $invalid_skus[] = array(
            'product_id' => $product_id,
            'product_name' => $product_name,
            'sku' => $sku,
            'issues' => $validation_result['issues']
          );
          error_log("SKU Validation: Product ID $product_id has invalid SKU '$sku': " . implode(', ', $validation_result['issues']));
        }
      }

      // Update duplicate detection
      $duplicate_skus = array();
      foreach ($all_skus as $sku => $products_with_sku) {
        if (count($products_with_sku) > 1) {
          $duplicate_skus[$sku] = $products_with_sku;
          error_log("SKU Validation: Found duplicate SKU '$sku' in " . count($products_with_sku) . " products");
        }
      }

      set_transient('sku_validation_invalid', $invalid_skus, 300);
      set_transient('sku_validation_duplicates', $duplicate_skus, 300);
      set_transient('sku_validation_all_skus', $all_skus, 300);

      $progress = $total_products > 0 ? min(100, round(($offset + count($product_ids)) / $total_products * 100)) : 100;

      if (count($product_ids) < $batch_size || $progress >= 100) {
        $total_invalid = count($invalid_skus);
        $total_duplicates = count($duplicate_skus);
        $total_products_with_skus = count($all_skus);

        error_log("SKU Validation: Complete - $total_products_with_skus products with SKUs, $total_invalid invalid, $total_duplicates duplicates");

        wp_send_json_success(array(
          'complete' => true,
          'total_products' => $total_products_with_skus,
          'total_invalid' => $total_invalid,
          'total_duplicates' => $total_duplicates,
          'invalid_skus' => $invalid_skus,
          'duplicate_skus' => $duplicate_skus,
          'progress' => 100
        ));
      } else {
        wp_send_json_success(array(
          'complete' => false,
          'offset' => $offset + $batch_size,
          'progress' => $progress,
          'total' => $total_products
        ));
      }
    } catch (Exception $e) {
      error_log('SKU Validation Error: ' . $e->getMessage());
      wp_send_json_error('Validation error: ' . $e->getMessage());
    }
  }

  public function ajax_fix_invalid_skus()
  {
    try {
      check_ajax_referer('sku_generator_nonce', 'nonce');

      if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('Insufficient permissions');
        return;
      }

      $invalid_skus = get_transient('sku_validation_invalid') ?: array();
      $duplicate_skus = get_transient('sku_validation_duplicates') ?: array();

      $fixed_count = 0;
      $generator = new SKU_Generator_Core();

      // Fix invalid format SKUs
      foreach ($invalid_skus as $invalid_sku) {
        $product = wc_get_product($invalid_sku['product_id']);
        if ($product) {
          $old_sku = $product->get_sku();
          $new_sku = $generator->generate_unique_sku($product);
          $product->set_sku($new_sku);
          $product->save();
          $fixed_count++;
          error_log("SKU Fix: Product ID {$invalid_sku['product_id']} - Changed '$old_sku' to '$new_sku'");
        }
      }

      // Fix duplicate SKUs (keep first, regenerate others)
      foreach ($duplicate_skus as $sku => $products_with_sku) {
        for ($i = 1; $i < count($products_with_sku); $i++) {
          $product = wc_get_product($products_with_sku[$i]['id']);
          if ($product) {
            $new_sku = $generator->generate_unique_sku($product);
            $product->set_sku($new_sku);
            $product->save();
            $fixed_count++;
            error_log("SKU Fix: Duplicate Product ID {$products_with_sku[$i]['id']} - Changed '$sku' to '$new_sku'");
          }
        }
      }

      // Clear transients
      delete_transient('sku_validation_invalid');
      delete_transient('sku_validation_duplicates');
      delete_transient('sku_validation_all_skus');
      delete_transient('sku_validation_total');

      error_log("SKU Fix: Complete - Fixed $fixed_count SKUs");

      wp_send_json_success(array(
        'message' => sprintf(__('Fixed %d invalid SKUs successfully!', 'sku-generator'), $fixed_count),
        'fixed_count' => $fixed_count
      ));
    } catch (Exception $e) {
      error_log('SKU Fix Error: ' . $e->getMessage());
      wp_send_json_error('Fix error: ' . $e->getMessage());
    }
  }
}
