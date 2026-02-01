<?php

/**
 * SKU Generator AJAX - Bulk Generation
 *
 * Handles AJAX requests for bulk SKU generation.
 *
 * @package SKU_Generator
 * @since 2.0.0
 */

defined('ABSPATH') || exit;

class SKU_Generator_Ajax_Generation
{
  public function __construct()
  {
    add_action('wp_ajax_generate_bulk_skus', [$this, 'ajax_generate_bulk_skus']);
    add_action('wp_ajax_generate_variation_skus', [$this, 'ajax_generate_variation_skus']);
  }

  /**
   * Handle bulk SKU generation AJAX request
   *
   * @since 2.0.0
   */
  public function ajax_generate_bulk_skus(): void
  {
    try {
      check_ajax_referer('sku_generator_nonce', 'nonce');

      if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(__('Insufficient permissions', 'sku-generator'));
        return;
      }

      $offset = isset($_POST['offset']) ? (int) $_POST['offset'] : 0;
      SKU_Generator_Helpers::debug_log("Starting batch at offset $offset");

      $products_without_skus = SKU_Generator_Helpers::get_products_without_skus($offset);
      $total_products = SKU_Generator_Helpers::count_products_without_skus();

      SKU_Generator_Helpers::debug_log("Found " . count($products_without_skus) . " products without SKUs in this batch");

      if (empty($products_without_skus)) {
        wp_send_json_success([
          'complete' => true,
          'message' => __('All SKUs generated successfully!', 'sku-generator'),
        ]);
        return;
      }

      $generator = new SKU_Generator_Core();
      $generated_count = 0;
      $variations_count = 0;

      foreach ($products_without_skus as $product_id) {
        $product = wc_get_product($product_id);

        if (!$product) {
          SKU_Generator_Helpers::debug_log("Could not load product ID $product_id");
          continue;
        }

        $current_sku = $product->get_sku();
        if (!empty($current_sku)) {
          SKU_Generator_Helpers::debug_log("Product ID $product_id already has SKU: '$current_sku'");

          // Even if parent has SKU, check and generate for variations
          if ($product instanceof \WC_Product_Variable) {
            $var_count = $generator->generate_variation_skus($product, $current_sku);
            $variations_count += $var_count;
          }
          continue;
        }

        $sku = $generator->generate_unique_sku($product);
        $product->set_sku($sku);
        $product->save();
        $generated_count++;

        SKU_Generator_Helpers::debug_log("Generated SKU '$sku' for product ID $product_id");

        // If this is a variable product, generate SKUs for variations
        if ($product instanceof \WC_Product_Variable) {
          $var_count = $generator->generate_variation_skus($product, $sku);
          $variations_count += $var_count;
          SKU_Generator_Helpers::debug_log("Generated $var_count variation SKUs for product ID $product_id");
        }
      }

      $batch_size = SKU_Generator_Helpers::get_batch_size();
      $progress = $total_products > 0
        ? min(100, round(($offset + $batch_size) / $total_products * 100))
        : 100;

      SKU_Generator_Helpers::debug_log("Generated $generated_count product SKUs + $variations_count variation SKUs in this batch, progress: $progress%");

      wp_send_json_success([
        'complete' => false,
        'offset' => $offset + $batch_size,
        'progress' => $progress,
        'total' => $total_products,
        'generated_this_batch' => $generated_count,
        'variations_this_batch' => $variations_count,
      ]);
    } catch (\Exception $e) {
      SKU_Generator_Helpers::debug_log('Generation Error: ' . $e->getMessage());
      wp_send_json_error(__('Generation error: ', 'sku-generator') . $e->getMessage());
    }
  }

  /**
   * Handle variation SKU generation AJAX request
   *
   * Generates SKUs for variations based on their parent product SKU.
   * Format: parent_sku-1, parent_sku-2, etc.
   *
   * @since 2.1.0
   */
  public function ajax_generate_variation_skus(): void
  {
    try {
      check_ajax_referer('sku_generator_nonce', 'nonce');

      if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(__('Insufficient permissions', 'sku-generator'));
        return;
      }

      $offset = isset($_POST['offset']) ? (int) $_POST['offset'] : 0;
      SKU_Generator_Helpers::debug_log("Variation SKU generation: Starting batch at offset $offset");

      $variations_without_skus = SKU_Generator_Helpers::get_variations_without_skus($offset);
      $total_variations = SKU_Generator_Helpers::count_variations_without_skus();

      SKU_Generator_Helpers::debug_log("Found " . count($variations_without_skus) . " variations without SKUs in this batch");

      if (empty($variations_without_skus)) {
        wp_send_json_success([
          'complete' => true,
          'message' => __('All variation SKUs generated successfully!', 'sku-generator'),
        ]);
        return;
      }

      $generator = new SKU_Generator_Core();
      $options = get_option('sku_generator_options', []);
      $separator = $options['separator'] ?? '-';
      $copy_to_gtin = $options['copy_to_gtin'] ?? '0';
      $generated_count = 0;

      // Group variations by parent
      $parent_variations = [];
      foreach ($variations_without_skus as $variation_id) {
        $parent_id = SKU_Generator_Helpers::get_variation_parent_id($variation_id);
        if ($parent_id) {
          if (!isset($parent_variations[$parent_id])) {
            $parent_variations[$parent_id] = [];
          }
          $parent_variations[$parent_id][] = $variation_id;
        }
      }

      // Process each parent's variations
      foreach ($parent_variations as $parent_id => $variation_ids) {
        $parent_product = wc_get_product($parent_id);

        if (!$parent_product) {
          continue;
        }

        $parent_sku = $parent_product->get_sku();

        // Generate parent SKU if it doesn't exist
        if (empty($parent_sku)) {
          $parent_sku = $generator->generate_unique_sku($parent_product);
          $parent_product->set_sku($parent_sku);
          $parent_product->save();
          SKU_Generator_Helpers::debug_log("Generated parent SKU '$parent_sku' for product ID $parent_id");
        }

        // Get existing variation numbers to continue numbering
        $all_variations = $parent_product instanceof \WC_Product_Variable
          ? $parent_product->get_children()
          : [];

        $existing_numbers = [];
        foreach ($all_variations as $var_id) {
          $var = wc_get_product($var_id);
          if ($var) {
            $var_sku = $var->get_sku();
            if (!empty($var_sku) && str_starts_with($var_sku, $parent_sku . $separator)) {
              $suffix = substr($var_sku, strlen($parent_sku . $separator));
              if (is_numeric($suffix)) {
                $existing_numbers[] = (int) $suffix;
              }
            }
          }
        }

        $next_number = empty($existing_numbers) ? 1 : max($existing_numbers) + 1;

        // Generate SKUs for variations without SKUs
        foreach ($variation_ids as $variation_id) {
          $variation = wc_get_product($variation_id);

          if (!$variation) {
            continue;
          }

          $variation_sku = $parent_sku . $separator . $next_number;

          // Ensure uniqueness
          $attempt = 0;
          while ($this->sku_exists_check($variation_sku) && $attempt < 100) {
            $attempt++;
            $next_number++;
            $variation_sku = $parent_sku . $separator . $next_number;
          }

          $variation->set_sku($variation_sku);

          if ($copy_to_gtin === '1') {
            $generator->copy_sku_to_gtin_public($variation, $variation_sku);
          }

          $variation->save();
          $generated_count++;
          $next_number++;

          SKU_Generator_Helpers::debug_log("Generated variation SKU '$variation_sku' for variation ID $variation_id");
        }
      }

      $batch_size = SKU_Generator_Helpers::get_batch_size();
      $progress = $total_variations > 0
        ? min(100, round(($offset + $batch_size) / $total_variations * 100))
        : 100;

      SKU_Generator_Helpers::debug_log("Generated $generated_count variation SKUs in this batch, progress: $progress%");

      wp_send_json_success([
        'complete' => false,
        'offset' => $offset + $batch_size,
        'progress' => $progress,
        'total' => $total_variations,
        'generated_this_batch' => $generated_count,
      ]);
    } catch (\Exception $e) {
      SKU_Generator_Helpers::debug_log('Variation Generation Error: ' . $e->getMessage());
      wp_send_json_error(__('Generation error: ', 'sku-generator') . $e->getMessage());
    }
  }

  /**
   * Check if SKU exists
   *
   * @since 2.1.0
   * @param string $sku SKU to check
   * @return bool
   */
  private function sku_exists_check(string $sku): bool
  {
    global $wpdb;

    if (SKU_Generator_Helpers::is_hpos_enabled()) {
      $exists = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM {$wpdb->prefix}wc_product_meta_lookup
        WHERE sku = %s
      ", $sku));
    } else {
      $exists = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM {$wpdb->postmeta}
        WHERE meta_key = '_sku' AND meta_value = %s
      ", $sku));
    }

    return $exists > 0;
  }
}
