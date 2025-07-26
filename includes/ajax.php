<?php

defined('ABSPATH') || exit;

class SKU_Generator_Ajax
{
  private $batch_size = 50;

  public function __construct()
  {
    add_action('wp_ajax_generate_bulk_skus', array($this, 'ajax_generate_bulk_skus'));
    add_action('wp_ajax_validate_skus', array($this, 'ajax_validate_skus'));
    add_action('wp_ajax_fix_invalid_skus', array($this, 'ajax_fix_invalid_skus'));
    add_action('wp_ajax_debug_products', array($this, 'ajax_debug_products'));
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

      // Use HPOS-compatible queries
      $products_without_skus = $this->get_products_without_skus($offset);
      $total_products = $this->count_products_without_skus();

      if (empty($products_without_skus)) {
        wp_send_json_success(array(
          'complete' => true,
          'message' => __('All SKUs generated successfully!', 'sku-generator')
        ));
        return;
      }

      $options = get_option('sku_generator_options', array());
      $generator = new SKU_Generator_Core();

      foreach ($products_without_skus as $product_id) {
        $product = wc_get_product($product_id);
        if (!$product || !empty($product->get_sku())) {
          continue;
        }

        $sku = $generator->generate_unique_sku($product);
        $product->set_sku($sku);
        $product->save();
      }

      $progress = min(100, round(($offset + $this->batch_size) / $total_products * 100));

      wp_send_json_success(array(
        'complete' => false,
        'offset' => $offset + $this->batch_size,
        'progress' => $progress,
        'total' => $total_products
      ));
    } catch (Exception $e) {
      error_log('SKU Generation Error: ' . $e->getMessage());
      wp_send_json_error('Generation error: ' . $e->getMessage());
    }
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

      // Let's first try a very simple approach - get ALL products regardless of SKU status
      global $wpdb;

      // Get total products first for debugging
      if ($offset === 0) {
        // Count all published products
        if ($this->is_hpos_enabled()) {
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

        // Clear previous validation data
        delete_transient('sku_validation_invalid');
        delete_transient('sku_validation_duplicates');
        delete_transient('sku_validation_all_skus');
      } else {
        $total_products = get_transient('sku_validation_total') ?: 100;
      }

      // Get product IDs for this batch
      if ($this->is_hpos_enabled()) {
        $product_ids = $wpdb->get_col($wpdb->prepare("
          SELECT id FROM {$wpdb->prefix}wc_products 
          WHERE status = 'publish' 
          LIMIT %d OFFSET %d
        ", $this->batch_size, $offset));
      } else {
        $product_ids = $wpdb->get_col($wpdb->prepare("
          SELECT ID FROM {$wpdb->posts} 
          WHERE post_type = 'product' AND post_status = 'publish' 
          LIMIT %d OFFSET %d
        ", $this->batch_size, $offset));
      }

      error_log("SKU Validation: Found " . count($product_ids) . " product IDs in this batch: " . implode(', ', $product_ids));

      $invalid_skus = get_transient('sku_validation_invalid') ?: array();
      $duplicate_skus = get_transient('sku_validation_duplicates') ?: array();
      $all_skus = get_transient('sku_validation_all_skus') ?: array();

      $validator = new SKU_Generator_Validator();
      $products_with_skus = 0;
      $products_without_skus = 0;

      foreach ($product_ids as $product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
          error_log("SKU Validation: Could not load product ID $product_id");
          continue;
        }

        $sku = $product->get_sku();
        $product_name = $product->get_name();

        error_log("SKU Validation: Product ID $product_id ('$product_name') has SKU: '" . ($sku ?: '[EMPTY]') . "'");

        if (empty($sku)) {
          $products_without_skus++;
          continue;
        }

        $products_with_skus++;

        // Track all SKUs for duplicate detection
        if (!isset($all_skus[$sku])) {
          $all_skus[$sku] = array();
        }
        $all_skus[$sku][] = array(
          'id' => $product_id,
          'name' => $product_name
        );

        // Validate SKU format
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

      error_log("SKU Validation: In this batch - $products_with_skus products with SKUs, $products_without_skus without SKUs");

      // Update duplicate detection
      $duplicate_skus = array();
      foreach ($all_skus as $sku => $products_with_sku) {
        if (count($products_with_sku) > 1) {
          $duplicate_skus[$sku] = $products_with_sku;
          error_log("SKU Validation: Found duplicate SKU '$sku' in " . count($products_with_sku) . " products");
        }
      }

      // Store progress
      set_transient('sku_validation_invalid', $invalid_skus, 300);
      set_transient('sku_validation_duplicates', $duplicate_skus, 300);
      set_transient('sku_validation_all_skus', $all_skus, 300);

      $progress = $total_products > 0 ? min(100, round(($offset + count($product_ids)) / $total_products * 100)) : 100;

      error_log("SKU Validation: Processed " . count($product_ids) . " products, progress: $progress%");

      if (count($product_ids) < $this->batch_size || $progress >= 100) {
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
          'offset' => $offset + $this->batch_size,
          'progress' => $progress,
          'total' => $total_products
        ));
      }
    } catch (Exception $e) {
      error_log('SKU Validation Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
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

      wp_send_json_success(array(
        'message' => sprintf(__('Fixed %d invalid SKUs successfully!', 'sku-generator'), $fixed_count),
        'fixed_count' => $fixed_count
      ));
    } catch (Exception $e) {
      error_log('SKU Fix Error: ' . $e->getMessage());
      wp_send_json_error('Fix error: ' . $e->getMessage());
    }
  }

  public function ajax_debug_products()
  {
    check_ajax_referer('sku_generator_nonce', 'nonce');

    if (!current_user_can('manage_woocommerce')) {
      wp_send_json_error('Insufficient permissions');
      return;
    }

    global $wpdb;

    $debug_info = array();

    // Check if HPOS is enabled
    $hpos_enabled = $this->is_hpos_enabled();
    $debug_info['hpos_enabled'] = $hpos_enabled;

    // Check total products
    if ($hpos_enabled) {
      $total_products = (int) $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}wc_products WHERE status = 'publish'");
      $debug_info['total_products_hpos'] = $total_products;

      // Get first 5 products with their SKUs from HPOS
      $products = $wpdb->get_results("
        SELECT p.id, pml.sku 
        FROM {$wpdb->prefix}wc_products p
        LEFT JOIN {$wpdb->prefix}wc_product_meta_lookup pml ON p.id = pml.product_id
        WHERE p.status = 'publish' 
        LIMIT 5
      ");
    } else {
      $total_products = (int) $wpdb->get_var("SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'");
      $debug_info['total_products_legacy'] = $total_products;

      // Get first 5 products with their SKUs from legacy
      $products = $wpdb->get_results("
        SELECT p.ID as id, pm.meta_value as sku
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
        WHERE p.post_type = 'product' AND p.post_status = 'publish'
        LIMIT 5
      ");
    }

    $debug_info['sample_products'] = array();
    foreach ($products as $product_data) {
      $product = wc_get_product($product_data->id);
      $debug_info['sample_products'][] = array(
        'id' => $product_data->id,
        'name' => $product ? $product->get_name() : 'Could not load product',
        'sku_from_db' => $product_data->sku,
        'sku_from_wc' => $product ? $product->get_sku() : 'N/A',
        'type' => $product ? $product->get_type() : 'unknown'
      );
    }

    // Check if WooCommerce tables exist
    if ($hpos_enabled) {
      $hpos_tables_exist = array(
        'wc_products' => $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wc_products'") !== null,
        'wc_product_meta_lookup' => $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wc_product_meta_lookup'") !== null
      );
      $debug_info['hpos_tables_exist'] = $hpos_tables_exist;
    }

    wp_send_json_success($debug_info);
  }

  // HPOS-compatible methods
  private function get_products_without_skus($offset = 0)
  {
    global $wpdb;

    if ($this->is_hpos_enabled()) {
      // Use HPOS tables
      $results = $wpdb->get_col($wpdb->prepare("
        SELECT p.id 
        FROM {$wpdb->prefix}wc_products p
        LEFT JOIN {$wpdb->prefix}wc_product_meta_lookup pml ON p.id = pml.product_id
        WHERE p.status = 'publish' 
        AND (pml.sku IS NULL OR pml.sku = '')
        LIMIT %d OFFSET %d
      ", $this->batch_size, $offset));
    } else {
      // Use traditional post meta
      $results = $wpdb->get_col($wpdb->prepare("
        SELECT p.ID 
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
        WHERE p.post_type = 'product' 
        AND p.post_status = 'publish'
        AND (pm.meta_value IS NULL OR pm.meta_value = '')
        LIMIT %d OFFSET %d
      ", $this->batch_size, $offset));
    }

    return array_map('intval', $results);
  }

  private function count_products_without_skus()
  {
    global $wpdb;

    if ($this->is_hpos_enabled()) {
      return (int) $wpdb->get_var("
        SELECT COUNT(p.id) 
        FROM {$wpdb->prefix}wc_products p
        LEFT JOIN {$wpdb->prefix}wc_product_meta_lookup pml ON p.id = pml.product_id
        WHERE p.status = 'publish' 
        AND (pml.sku IS NULL OR pml.sku = '')
      ");
    } else {
      return (int) $wpdb->get_var("
        SELECT COUNT(p.ID) 
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
        WHERE p.post_type = 'product' 
        AND p.post_status = 'publish'
        AND (pm.meta_value IS NULL OR pm.meta_value = '')
      ");
    }
  }

  private function get_all_products($offset = 0)
  {
    global $wpdb;

    if ($this->is_hpos_enabled()) {
      $results = $wpdb->get_col($wpdb->prepare("
        SELECT id FROM {$wpdb->prefix}wc_products 
        WHERE status = 'publish' 
        LIMIT %d OFFSET %d
      ", $this->batch_size, $offset));
    } else {
      $results = $wpdb->get_col($wpdb->prepare("
        SELECT ID FROM {$wpdb->posts} 
        WHERE post_type = 'product' AND post_status = 'publish' 
        LIMIT %d OFFSET %d
      ", $this->batch_size, $offset));
    }

    return array_map('intval', $results);
  }

  private function count_all_products()
  {
    global $wpdb;

    if ($this->is_hpos_enabled()) {
      return (int) $wpdb->get_var("
        SELECT COUNT(id) FROM {$wpdb->prefix}wc_products 
        WHERE status = 'publish'
      ");
    } else {
      return (int) $wpdb->get_var("
        SELECT COUNT(ID) FROM {$wpdb->posts} 
        WHERE post_type = 'product' AND post_status = 'publish'
      ");
    }
  }

  private function is_hpos_enabled()
  {
    return class_exists('Automattic\WooCommerce\Utilities\OrderUtil') &&
      method_exists('Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled') &&
      \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
  }
}
