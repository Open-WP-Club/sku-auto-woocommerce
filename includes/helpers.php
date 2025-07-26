<?php

defined('ABSPATH') || exit;

class SKU_Generator_Helpers
{
  private static $batch_size = 50;

  public static function is_hpos_enabled()
  {
    // First check if the HPOS class exists
    if (!class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
      return false;
    }

    // Check if the method exists
    if (!method_exists('Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled')) {
      return false;
    }

    // Check if HPOS is actually enabled
    $hpos_enabled = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

    // Even if WooCommerce says HPOS is enabled, verify the tables actually exist
    if ($hpos_enabled) {
      global $wpdb;
      $tables_exist = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wc_products'") !== null &&
        $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wc_product_meta_lookup'") !== null;

      if (!$tables_exist) {
        error_log("SKU Generator: HPOS reported as enabled but tables don't exist. Falling back to legacy mode.");
        return false;
      }
    }

    error_log("SKU Generator: HPOS detection result: " . ($hpos_enabled ? 'Enabled' : 'Disabled'));
    return $hpos_enabled;
  }

  public static function get_products_without_skus($offset = 0)
  {
    global $wpdb;

    $hpos_enabled = self::is_hpos_enabled();
    error_log("Helpers: Getting products without SKUs at offset $offset, HPOS enabled: " . ($hpos_enabled ? 'Yes' : 'No'));

    if ($hpos_enabled) {
      // For HPOS, we need to check the product meta lookup table
      $query = $wpdb->prepare("
        SELECT p.id 
        FROM {$wpdb->prefix}wc_products p
        LEFT JOIN {$wpdb->prefix}wc_product_meta_lookup pml ON p.id = pml.product_id
        WHERE p.status = 'publish' 
        AND (pml.sku IS NULL OR pml.sku = '' OR TRIM(pml.sku) = '')
        LIMIT %d OFFSET %d
      ", self::$batch_size, $offset);
    } else {
      // For legacy, get all published products and then check their SKUs
      $query = $wpdb->prepare("
        SELECT p.ID 
        FROM {$wpdb->posts} p
        WHERE p.post_type = 'product' 
        AND p.post_status = 'publish'
        AND p.ID NOT IN (
          SELECT pm.post_id 
          FROM {$wpdb->postmeta} pm 
          WHERE pm.meta_key = '_sku' 
          AND pm.meta_value IS NOT NULL 
          AND pm.meta_value != '' 
          AND TRIM(pm.meta_value) != ''
        )
        LIMIT %d OFFSET %d
      ", self::$batch_size, $offset);
    }

    error_log("Helpers: Query: " . $query);
    $results = $wpdb->get_col($query);

    error_log("Helpers: Found " . count($results) . " products without SKUs: " . implode(', ', $results));

    return array_map('intval', $results);
  }

  public static function count_products_without_skus()
  {
    global $wpdb;

    if (self::is_hpos_enabled()) {
      $count = (int) $wpdb->get_var("
        SELECT COUNT(p.id) 
        FROM {$wpdb->prefix}wc_products p
        LEFT JOIN {$wpdb->prefix}wc_product_meta_lookup pml ON p.id = pml.product_id
        WHERE p.status = 'publish' 
        AND (pml.sku IS NULL OR pml.sku = '' OR TRIM(pml.sku) = '')
      ");
    } else {
      // Count products that don't have a valid SKU
      $count = (int) $wpdb->get_var("
        SELECT COUNT(p.ID) 
        FROM {$wpdb->posts} p
        WHERE p.post_type = 'product' 
        AND p.post_status = 'publish'
        AND p.ID NOT IN (
          SELECT pm.post_id 
          FROM {$wpdb->postmeta} pm 
          WHERE pm.meta_key = '_sku' 
          AND pm.meta_value IS NOT NULL 
          AND pm.meta_value != '' 
          AND TRIM(pm.meta_value) != ''
        )
      ");
    }

    error_log("Helpers: Total products without SKUs: $count");
    return $count;
  }

  public static function get_products_with_skus($offset = 0)
  {
    global $wpdb;

    if (self::is_hpos_enabled()) {
      $results = $wpdb->get_col($wpdb->prepare("
        SELECT p.id FROM {$wpdb->prefix}wc_products p
        INNER JOIN {$wpdb->prefix}wc_product_meta_lookup pml ON p.id = pml.product_id
        WHERE p.status = 'publish' AND pml.sku IS NOT NULL AND pml.sku != ''
        LIMIT %d OFFSET %d
      ", self::$batch_size, $offset));
    } else {
      $results = $wpdb->get_col($wpdb->prepare("
        SELECT p.ID FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'product' AND p.post_status = 'publish'
        AND pm.meta_key = '_sku' AND pm.meta_value IS NOT NULL AND pm.meta_value != ''
        LIMIT %d OFFSET %d
      ", self::$batch_size, $offset));
    }

    return array_map('intval', $results);
  }

  public static function count_products_with_skus()
  {
    global $wpdb;

    if (self::is_hpos_enabled()) {
      return (int) $wpdb->get_var("
        SELECT COUNT(p.id) FROM {$wpdb->prefix}wc_products p
        INNER JOIN {$wpdb->prefix}wc_product_meta_lookup pml ON p.id = pml.product_id
        WHERE p.status = 'publish' AND pml.sku IS NOT NULL AND pml.sku != ''
      ");
    } else {
      return (int) $wpdb->get_var("
        SELECT COUNT(p.ID) FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'product' AND p.post_status = 'publish'
        AND pm.meta_key = '_sku' AND pm.meta_value IS NOT NULL AND pm.meta_value != ''
      ");
    }
  }

  public static function get_all_products($offset = 0)
  {
    global $wpdb;

    if (self::is_hpos_enabled()) {
      $results = $wpdb->get_col($wpdb->prepare("
        SELECT id FROM {$wpdb->prefix}wc_products 
        WHERE status = 'publish' 
        LIMIT %d OFFSET %d
      ", self::$batch_size, $offset));
    } else {
      $results = $wpdb->get_col($wpdb->prepare("
        SELECT ID FROM {$wpdb->posts} 
        WHERE post_type = 'product' AND post_status = 'publish' 
        LIMIT %d OFFSET %d
      ", self::$batch_size, $offset));
    }

    return array_map('intval', $results);
  }

  public static function count_all_products()
  {
    global $wpdb;

    if (self::is_hpos_enabled()) {
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

  public static function get_gtin_meta_keys()
  {
    return array(
      '_global_unique_id',
      '_wpm_gtin_code',
      '_ywbc_barcode_value',
      '_ts_gtin',
      '_woo_upc',
      '_product_gtin',
      '_gtin',
      '_upc',
      '_ean',
      '_isbn',
      '_barcode'
    );
  }

  public static function looks_like_generated_sku($sku, $options)
  {
    $prefix = isset($options['prefix']) ? $options['prefix'] : '';
    $suffix = isset($options['suffix']) ? $options['suffix'] : '';
    $separator = isset($options['separator']) ? $options['separator'] : '-';

    if (!empty($prefix) && substr($sku, 0, strlen($prefix)) !== $prefix) {
      return false;
    }

    if (!empty($suffix) && substr($sku, -strlen($suffix)) !== $suffix) {
      return false;
    }

    if (!empty($separator) && strpos($sku, $separator) === false && (!empty($prefix) || !empty($suffix))) {
      return false;
    }

    return true;
  }

  public static function get_batch_size()
  {
    return self::$batch_size;
  }
}
