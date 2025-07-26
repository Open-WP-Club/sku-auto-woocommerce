<?php

defined('ABSPATH') || exit;

class SKU_Generator_Ajax_Cleanup
{
  public function __construct()
  {
    add_action('wp_ajax_remove_all_skus', array($this, 'ajax_remove_all_skus'));
    add_action('wp_ajax_remove_generated_skus', array($this, 'ajax_remove_generated_skus'));
    add_action('wp_ajax_remove_all_gtin', array($this, 'ajax_remove_all_gtin'));
    add_action('wp_ajax_remove_empty_skus', array($this, 'ajax_remove_empty_skus'));
  }

  public function ajax_remove_all_skus()
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
        set_transient('sku_cleanup_total', $total_products, 300);
      } else {
        $total_products = get_transient('sku_cleanup_total') ?: 100;
      }

      $product_ids = SKU_Generator_Helpers::get_products_with_skus($offset);
      $removed_count = 0;

      foreach ($product_ids as $product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
          continue;
        }

        $old_sku = $product->get_sku();
        if (!empty($old_sku)) {
          $product->set_sku('');
          $product->save();
          $removed_count++;
          error_log("SKU Cleanup: Removed SKU '$old_sku' from product ID $product_id");
        }
      }

      $batch_size = SKU_Generator_Helpers::get_batch_size();
      $progress = $total_products > 0 ? min(100, round(($offset + $batch_size) / $total_products * 100)) : 100;

      if (count($product_ids) < $batch_size || $progress >= 100) {
        delete_transient('sku_cleanup_total');

        wp_send_json_success(array(
          'complete' => true,
          'message' => sprintf(__('Removed SKUs from %d products!', 'sku-generator'), $removed_count),
          'progress' => 100
        ));
      } else {
        wp_send_json_success(array(
          'complete' => false,
          'offset' => $offset + $batch_size,
          'progress' => $progress,
          'total' => $total_products,
          'removed_this_batch' => $removed_count
        ));
      }
    } catch (Exception $e) {
      error_log('SKU Removal Error: ' . $e->getMessage());
      wp_send_json_error('Removal error: ' . $e->getMessage());
    }
  }

  public function ajax_remove_generated_skus()
  {
    try {
      check_ajax_referer('sku_generator_nonce', 'nonce');

      if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('Insufficient permissions');
        return;
      }

      $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
      $options = get_option('sku_generator_options', array());

      if ($offset === 0) {
        $total_products = SKU_Generator_Helpers::count_products_with_skus();
        set_transient('sku_cleanup_total', $total_products, 300);
      } else {
        $total_products = get_transient('sku_cleanup_total') ?: 100;
      }

      $product_ids = SKU_Generator_Helpers::get_products_with_skus($offset);
      $removed_count = 0;

      foreach ($product_ids as $product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
          continue;
        }

        $sku = $product->get_sku();
        if (empty($sku)) {
          continue;
        }

        if (SKU_Generator_Helpers::looks_like_generated_sku($sku, $options)) {
          $product->set_sku('');
          $product->save();
          $removed_count++;
          error_log("SKU Cleanup: Removed generated SKU '$sku' from product ID $product_id");
        }
      }

      $batch_size = SKU_Generator_Helpers::get_batch_size();
      $progress = $total_products > 0 ? min(100, round(($offset + $batch_size) / $total_products * 100)) : 100;

      if (count($product_ids) < $batch_size || $progress >= 100) {
        delete_transient('sku_cleanup_total');

        wp_send_json_success(array(
          'complete' => true,
          'message' => sprintf(__('Removed %d generated SKUs!', 'sku-generator'), $removed_count),
          'progress' => 100
        ));
      } else {
        wp_send_json_success(array(
          'complete' => false,
          'offset' => $offset + $batch_size,
          'progress' => $progress,
          'total' => $total_products,
          'removed_this_batch' => $removed_count
        ));
      }
    } catch (Exception $e) {
      error_log('Generated SKU Removal Error: ' . $e->getMessage());
      wp_send_json_error('Removal error: ' . $e->getMessage());
    }
  }

  public function ajax_remove_all_gtin()
  {
    try {
      check_ajax_referer('sku_generator_nonce', 'nonce');

      if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('Insufficient permissions');
        return;
      }

      $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;

      if ($offset === 0) {
        $total_products = SKU_Generator_Helpers::count_all_products();
        set_transient('sku_cleanup_total', $total_products, 300);
      } else {
        $total_products = get_transient('sku_cleanup_total') ?: 100;
      }

      $product_ids = SKU_Generator_Helpers::get_all_products($offset);
      $removed_count = 0;
      $gtin_meta_keys = SKU_Generator_Helpers::get_gtin_meta_keys();

      foreach ($product_ids as $product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
          continue;
        }

        $had_gtin = false;
        foreach ($gtin_meta_keys as $meta_key) {
          $value = $product->get_meta($meta_key);
          if (!empty($value)) {
            $product->delete_meta_data($meta_key);
            $had_gtin = true;
            error_log("SKU Cleanup: Removed GTIN field '$meta_key' with value '$value' from product ID $product_id");
          }
        }

        if ($had_gtin) {
          $product->save();
          $removed_count++;
        }
      }

      $batch_size = SKU_Generator_Helpers::get_batch_size();
      $progress = $total_products > 0 ? min(100, round(($offset + $batch_size) / $total_products * 100)) : 100;

      if (count($product_ids) < $batch_size || $progress >= 100) {
        delete_transient('sku_cleanup_total');

        wp_send_json_success(array(
          'complete' => true,
          'message' => sprintf(__('Removed GTIN fields from %d products!', 'sku-generator'), $removed_count),
          'progress' => 100
        ));
      } else {
        wp_send_json_success(array(
          'complete' => false,
          'offset' => $offset + $batch_size,
          'progress' => $progress,
          'total' => $total_products,
          'removed_this_batch' => $removed_count
        ));
      }
    } catch (Exception $e) {
      error_log('GTIN Removal Error: ' . $e->getMessage());
      wp_send_json_error('Removal error: ' . $e->getMessage());
    }
  }

  public function ajax_remove_empty_skus()
  {
    try {
      check_ajax_referer('sku_generator_nonce', 'nonce');

      if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('Insufficient permissions');
        return;
      }

      global $wpdb;

      // Remove empty or whitespace-only SKUs directly from database
      if (SKU_Generator_Helpers::is_hpos_enabled()) {
        $affected_rows = $wpdb->query("
          UPDATE {$wpdb->prefix}wc_product_meta_lookup 
          SET sku = NULL 
          WHERE sku = '' OR sku IS NULL OR TRIM(sku) = ''
        ");
      } else {
        $affected_rows = $wpdb->query("
          DELETE FROM {$wpdb->postmeta} 
          WHERE meta_key = '_sku' 
          AND (meta_value = '' OR meta_value IS NULL OR TRIM(meta_value) = '')
        ");
      }

      error_log("SKU Cleanup: Cleaned up $affected_rows empty SKU fields");

      wp_send_json_success(array(
        'complete' => true,
        'message' => sprintf(__('Cleaned up %d empty SKU fields!', 'sku-generator'), $affected_rows),
        'progress' => 100
      ));
    } catch (Exception $e) {
      error_log('Empty SKU Cleanup Error: ' . $e->getMessage());
      wp_send_json_error('Cleanup error: ' . $e->getMessage());
    }
  }
}
