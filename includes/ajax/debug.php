<?php

defined('ABSPATH') || exit;

class SKU_Generator_Ajax_Debug
{
  public function __construct()
  {
    add_action('wp_ajax_debug_products', array($this, 'ajax_debug_products'));
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
    $hpos_enabled = SKU_Generator_Helpers::is_hpos_enabled();
    $debug_info['hpos_enabled'] = $hpos_enabled;

    // Get basic product statistics
    if ($hpos_enabled) {
      $total_products = (int) $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}wc_products WHERE status = 'publish'");
      $debug_info['total_products_hpos'] = $total_products;

      // Check if HPOS tables exist
      $hpos_tables_exist = array(
        'wc_products' => $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wc_products'") !== null,
        'wc_product_meta_lookup' => $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wc_product_meta_lookup'") !== null
      );
      $debug_info['hpos_tables_exist'] = $hpos_tables_exist;

      // Get sample products with their SKUs from HPOS
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

      // Get sample products with their SKUs from legacy
      $products = $wpdb->get_results("
        SELECT p.ID as id, pm.meta_value as sku
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
        WHERE p.post_type = 'product' AND p.post_status = 'publish'
        LIMIT 5
      ");
    }

    // Process sample products
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

    // Get SKU statistics
    $debug_info['sku_stats'] = array(
      'products_with_skus' => SKU_Generator_Helpers::count_products_with_skus(),
      'products_without_skus' => SKU_Generator_Helpers::count_products_without_skus(),
      'total_products' => SKU_Generator_Helpers::count_all_products()
    );

    // Get plugin settings
    $debug_info['plugin_settings'] = get_option('sku_generator_options', array());

    // Check for common GTIN fields
    $gtin_stats = array();
    $gtin_meta_keys = SKU_Generator_Helpers::get_gtin_meta_keys();

    foreach ($gtin_meta_keys as $meta_key) {
      if ($hpos_enabled) {
        // For HPOS, we'd need to check the postmeta table for meta fields
        $count = (int) $wpdb->get_var($wpdb->prepare("
          SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} 
          WHERE meta_key = %s AND meta_value != '' AND meta_value IS NOT NULL
        ", $meta_key));
      } else {
        $count = (int) $wpdb->get_var($wpdb->prepare("
          SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} 
          WHERE meta_key = %s AND meta_value != '' AND meta_value IS NOT NULL
        ", $meta_key));
      }

      if ($count > 0) {
        $gtin_stats[$meta_key] = $count;
      }
    }

    $debug_info['gtin_field_usage'] = $gtin_stats;

    // Add system information
    $debug_info['system_info'] = array(
      'php_version' => PHP_VERSION,
      'wordpress_version' => get_bloginfo('version'),
      'woocommerce_version' => defined('WC_VERSION') ? WC_VERSION : 'Not available',
      'plugin_version' => SKU_GENERATOR_VERSION,
      'memory_limit' => ini_get('memory_limit'),
      'max_execution_time' => ini_get('max_execution_time')
    );

    error_log('SKU Generator Debug: Generated debug info for ' . count($debug_info['sample_products']) . ' sample products');

    wp_send_json_success($debug_info);
  }
}
