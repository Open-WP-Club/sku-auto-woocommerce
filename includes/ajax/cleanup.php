<?php

/**
 * SKU Generator AJAX - Cleanup
 *
 * Handles AJAX requests for SKU and GTIN cleanup operations.
 *
 * @package SKU_Generator
 * @since 2.0.0
 */

defined('ABSPATH') || exit;

class SKU_Generator_Ajax_Cleanup
{
  public function __construct()
  {
    add_action('wp_ajax_remove_all_skus', [$this, 'ajax_remove_all_skus']);
    add_action('wp_ajax_remove_generated_skus', [$this, 'ajax_remove_generated_skus']);
    add_action('wp_ajax_remove_all_gtin', [$this, 'ajax_remove_all_gtin']);
    add_action('wp_ajax_remove_empty_skus', [$this, 'ajax_remove_empty_skus']);
  }

  /**
   * Handle remove all SKUs AJAX request
   *
   * @since 2.0.0
   */
  public function ajax_remove_all_skus(): void
  {
    try {
      check_ajax_referer('sku_generator_nonce', 'nonce');

      if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(__('Insufficient permissions', 'sku-generator'));
        return;
      }

      $offset = isset($_POST['offset']) ? (int) $_POST['offset'] : 0;

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
          SKU_Generator_Helpers::debug_log("Cleanup: Removed SKU '$old_sku' from product ID $product_id");
        }
      }

      $batch_size = SKU_Generator_Helpers::get_batch_size();
      $progress = $total_products > 0
        ? min(100, round(($offset + $batch_size) / $total_products * 100))
        : 100;

      if (count($product_ids) < $batch_size || $progress >= 100) {
        delete_transient('sku_cleanup_total');

        wp_send_json_success([
          'complete' => true,
          'message' => sprintf(
            /* translators: %d: number of products */
            __('Removed SKUs from %d products!', 'sku-generator'),
            $removed_count
          ),
          'progress' => 100,
        ]);
      } else {
        wp_send_json_success([
          'complete' => false,
          'offset' => $offset + $batch_size,
          'progress' => $progress,
          'total' => $total_products,
          'removed_this_batch' => $removed_count,
        ]);
      }
    } catch (\Exception $e) {
      SKU_Generator_Helpers::debug_log('Removal Error: ' . $e->getMessage());
      wp_send_json_error(__('Removal error: ', 'sku-generator') . $e->getMessage());
    }
  }

  /**
   * Handle remove generated SKUs only AJAX request
   *
   * @since 2.0.0
   */
  public function ajax_remove_generated_skus(): void
  {
    try {
      check_ajax_referer('sku_generator_nonce', 'nonce');

      if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(__('Insufficient permissions', 'sku-generator'));
        return;
      }

      $offset = isset($_POST['offset']) ? (int) $_POST['offset'] : 0;
      $options = get_option('sku_generator_options', []);

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
          SKU_Generator_Helpers::debug_log("Cleanup: Removed generated SKU '$sku' from product ID $product_id");
        }
      }

      $batch_size = SKU_Generator_Helpers::get_batch_size();
      $progress = $total_products > 0
        ? min(100, round(($offset + $batch_size) / $total_products * 100))
        : 100;

      if (count($product_ids) < $batch_size || $progress >= 100) {
        delete_transient('sku_cleanup_total');

        wp_send_json_success([
          'complete' => true,
          'message' => sprintf(
            /* translators: %d: number of SKUs */
            __('Removed %d generated SKUs!', 'sku-generator'),
            $removed_count
          ),
          'progress' => 100,
        ]);
      } else {
        wp_send_json_success([
          'complete' => false,
          'offset' => $offset + $batch_size,
          'progress' => $progress,
          'total' => $total_products,
          'removed_this_batch' => $removed_count,
        ]);
      }
    } catch (\Exception $e) {
      SKU_Generator_Helpers::debug_log('Generated SKU Removal Error: ' . $e->getMessage());
      wp_send_json_error(__('Removal error: ', 'sku-generator') . $e->getMessage());
    }
  }

  /**
   * Handle remove all GTIN fields AJAX request
   *
   * @since 2.0.0
   */
  public function ajax_remove_all_gtin(): void
  {
    try {
      check_ajax_referer('sku_generator_nonce', 'nonce');

      if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(__('Insufficient permissions', 'sku-generator'));
        return;
      }

      $offset = isset($_POST['offset']) ? (int) $_POST['offset'] : 0;

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
            SKU_Generator_Helpers::debug_log("Cleanup: Removed GTIN field '$meta_key' from product ID $product_id");
          }
        }

        if ($had_gtin) {
          $product->save();
          $removed_count++;
        }
      }

      $batch_size = SKU_Generator_Helpers::get_batch_size();
      $progress = $total_products > 0
        ? min(100, round(($offset + $batch_size) / $total_products * 100))
        : 100;

      if (count($product_ids) < $batch_size || $progress >= 100) {
        delete_transient('sku_cleanup_total');

        wp_send_json_success([
          'complete' => true,
          'message' => sprintf(
            /* translators: %d: number of products */
            __('Removed GTIN fields from %d products!', 'sku-generator'),
            $removed_count
          ),
          'progress' => 100,
        ]);
      } else {
        wp_send_json_success([
          'complete' => false,
          'offset' => $offset + $batch_size,
          'progress' => $progress,
          'total' => $total_products,
          'removed_this_batch' => $removed_count,
        ]);
      }
    } catch (\Exception $e) {
      SKU_Generator_Helpers::debug_log('GTIN Removal Error: ' . $e->getMessage());
      wp_send_json_error(__('Removal error: ', 'sku-generator') . $e->getMessage());
    }
  }

  /**
   * Handle remove empty SKUs AJAX request
   *
   * @since 2.0.0
   */
  public function ajax_remove_empty_skus(): void
  {
    try {
      check_ajax_referer('sku_generator_nonce', 'nonce');

      if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(__('Insufficient permissions', 'sku-generator'));
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

      SKU_Generator_Helpers::debug_log("Cleanup: Cleaned up $affected_rows empty SKU fields");

      wp_send_json_success([
        'complete' => true,
        'message' => sprintf(
          /* translators: %d: number of fields */
          __('Cleaned up %d empty SKU fields!', 'sku-generator'),
          $affected_rows
        ),
        'progress' => 100,
      ]);
    } catch (\Exception $e) {
      SKU_Generator_Helpers::debug_log('Empty SKU Cleanup Error: ' . $e->getMessage());
      wp_send_json_error(__('Cleanup error: ', 'sku-generator') . $e->getMessage());
    }
  }
}
