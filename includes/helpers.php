<?php

/**
 * SKU Generator Helper Functions
 *
 * Provides database abstraction layer for HPOS compatibility
 * and utility functions for SKU operations.
 *
 * @package SKU_Generator
 * @since 2.0.0
 */

defined('ABSPATH') || exit;

class SKU_Generator_Helpers
{
  private static int $batch_size = 50;
  private static ?bool $hpos_cache = null;

  /**
   * Check if HPOS (High-Performance Order Storage) is enabled
   *
   * Uses WooCommerce's FeaturesUtil for modern detection (WC 9.0+)
   * with fallback to OrderUtil for backwards compatibility.
   *
   * @since 2.1.0
   * @return bool
   */
  public static function is_hpos_enabled(): bool
  {
    // Return cached result if available
    if (self::$hpos_cache !== null) {
      return self::$hpos_cache;
    }

    // Modern approach: Use FeaturesUtil (WC 8.2+)
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
      self::$hpos_cache = \Automattic\WooCommerce\Utilities\FeaturesUtil::feature_is_enabled('custom_orders_tables');
      self::debug_log('HPOS detection (FeaturesUtil): ' . (self::$hpos_cache ? 'Enabled' : 'Disabled'));
      return self::$hpos_cache;
    }

    // Fallback: Use OrderUtil (WC 7.1+)
    if (
      class_exists('Automattic\WooCommerce\Utilities\OrderUtil') &&
      method_exists('Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled')
    ) {
      self::$hpos_cache = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
      self::debug_log('HPOS detection (OrderUtil): ' . (self::$hpos_cache ? 'Enabled' : 'Disabled'));
      return self::$hpos_cache;
    }

    self::$hpos_cache = false;
    return false;
  }

  /**
   * Log debug messages only when WP_DEBUG is enabled
   *
   * @since 2.1.0
   * @param string $message
   */
  public static function debug_log(string $message): void
  {
    if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log('SKU Generator: ' . $message);
    }
  }

  /**
   * Get products without SKUs with pagination
   *
   * @since 2.0.0
   * @param int $offset Pagination offset
   * @return array<int> Product IDs
   */
  public static function get_products_without_skus(int $offset = 0): array
  {
    global $wpdb;

    $hpos_enabled = self::is_hpos_enabled();
    self::debug_log("Getting products without SKUs at offset $offset, HPOS: " . ($hpos_enabled ? 'Yes' : 'No'));

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

    self::debug_log("Query: " . $query);
    $results = $wpdb->get_col($query);

    self::debug_log("Found " . count($results) . " products without SKUs");

    return array_map('intval', $results);
  }

  /**
   * Count total products without SKUs
   *
   * @since 2.0.0
   * @return int
   */
  public static function count_products_without_skus(): int
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

    self::debug_log("Total products without SKUs: $count");
    return $count;
  }

  /**
   * Get products with SKUs with pagination
   *
   * @since 2.0.0
   * @param int $offset Pagination offset
   * @return array<int> Product IDs
   */
  public static function get_products_with_skus(int $offset = 0): array
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

  /**
   * Count total products with SKUs
   *
   * @since 2.0.0
   * @return int
   */
  public static function count_products_with_skus(): int
  {
    global $wpdb;

    if (self::is_hpos_enabled()) {
      return (int) $wpdb->get_var("
        SELECT COUNT(p.id) FROM {$wpdb->prefix}wc_products p
        INNER JOIN {$wpdb->prefix}wc_product_meta_lookup pml ON p.id = pml.product_id
        WHERE p.status = 'publish' AND pml.sku IS NOT NULL AND pml.sku != ''
      ");
    }

    return (int) $wpdb->get_var("
      SELECT COUNT(p.ID) FROM {$wpdb->posts} p
      INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
      WHERE p.post_type = 'product' AND p.post_status = 'publish'
      AND pm.meta_key = '_sku' AND pm.meta_value IS NOT NULL AND pm.meta_value != ''
    ");
  }

  /**
   * Get all published products with pagination
   *
   * @since 2.0.0
   * @param int $offset Pagination offset
   * @return array<int> Product IDs
   */
  public static function get_all_products(int $offset = 0): array
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

  /**
   * Count total published products
   *
   * @since 2.0.0
   * @return int
   */
  public static function count_all_products(): int
  {
    global $wpdb;

    if (self::is_hpos_enabled()) {
      return (int) $wpdb->get_var("
        SELECT COUNT(id) FROM {$wpdb->prefix}wc_products
        WHERE status = 'publish'
      ");
    }

    return (int) $wpdb->get_var("
      SELECT COUNT(ID) FROM {$wpdb->posts}
      WHERE post_type = 'product' AND post_status = 'publish'
    ");
  }

  /**
   * Get list of supported GTIN meta keys
   *
   * Supports WooCommerce core GTIN field and popular third-party plugins.
   *
   * @since 2.0.0
   * @return array<string>
   */
  public static function get_gtin_meta_keys(): array
  {
    return [
      '_global_unique_id',     // WooCommerce core GTIN field (WC 8.4+)
      '_wpm_gtin_code',        // WP Marketing Robot
      '_ywbc_barcode_value',   // YITH WooCommerce Barcodes
      '_ts_gtin',              // ThemeSense GTIN
      '_woo_upc',              // WooCommerce UPC
      '_product_gtin',         // Generic GTIN
      '_gtin',                 // Simple GTIN
      '_upc',                  // UPC
      '_ean',                  // EAN
      '_isbn',                 // ISBN
      '_barcode',              // Generic barcode
    ];
  }

  /**
   * Check if a SKU looks like it was generated by this plugin
   *
   * @since 2.0.0
   * @param string $sku SKU to check
   * @param array<string, mixed> $options Plugin options
   * @return bool
   */
  public static function looks_like_generated_sku(string $sku, array $options): bool
  {
    $prefix = $options['prefix'] ?? '';
    $suffix = $options['suffix'] ?? '';
    $separator = $options['separator'] ?? '-';
    $use_permalink = $options['use_permalink'] ?? '0';

    // If we have a prefix, check if SKU starts with it
    if (!empty($prefix) && !str_starts_with($sku, $prefix)) {
      return false;
    }

    // If we have a suffix, check if SKU ends with it
    if (!empty($suffix) && !str_ends_with($sku, $suffix)) {
      return false;
    }

    // Check if SKU uses our separator
    if (!empty($separator) && !str_contains($sku, $separator) && (!empty($prefix) || !empty($suffix))) {
      return false;
    }

    // If using permalink, the SKU should contain readable text
    if ($use_permalink === '1') {
      $main_part = $sku;
      if (!empty($prefix)) {
        $main_part = substr($main_part, strlen($prefix . $separator));
      }
      if (!empty($suffix)) {
        $main_part = substr($main_part, 0, -(strlen($separator . $suffix)));
      }

      // Main part should contain some letters (not just numbers)
      if (!preg_match('/[a-zA-Z]/', $main_part)) {
        return false;
      }

      // Should not look like a random alphanumeric string
      if (preg_match('/^[A-Z0-9]{6,}$/', $main_part)) {
        return false;
      }
    }

    return true;
  }

  /**
   * Get batch size for pagination
   *
   * @since 2.0.0
   * @return int
   */
  public static function get_batch_size(): int
  {
    return self::$batch_size;
  }

  /**
   * Clear HPOS cache (useful for testing)
   *
   * @since 2.1.0
   */
  public static function clear_hpos_cache(): void
  {
    self::$hpos_cache = null;
  }

  /**
   * Get variations without SKUs
   *
   * @since 2.1.0
   * @param int $offset Pagination offset
   * @return array<int> Variation IDs
   */
  public static function get_variations_without_skus(int $offset = 0): array
  {
    global $wpdb;

    if (self::is_hpos_enabled()) {
      $results = $wpdb->get_col($wpdb->prepare("
        SELECT p.ID
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->prefix}wc_product_meta_lookup pml ON p.ID = pml.product_id
        WHERE p.post_type = 'product_variation'
        AND p.post_status = 'publish'
        AND (pml.sku IS NULL OR pml.sku = '' OR TRIM(pml.sku) = '')
        LIMIT %d OFFSET %d
      ", self::$batch_size, $offset));
    } else {
      $results = $wpdb->get_col($wpdb->prepare("
        SELECT p.ID
        FROM {$wpdb->posts} p
        WHERE p.post_type = 'product_variation'
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
      ", self::$batch_size, $offset));
    }

    self::debug_log("Found " . count($results) . " variations without SKUs at offset $offset");

    return array_map('intval', $results);
  }

  /**
   * Count variations without SKUs
   *
   * @since 2.1.0
   * @return int
   */
  public static function count_variations_without_skus(): int
  {
    global $wpdb;

    if (self::is_hpos_enabled()) {
      $count = (int) $wpdb->get_var("
        SELECT COUNT(p.ID)
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->prefix}wc_product_meta_lookup pml ON p.ID = pml.product_id
        WHERE p.post_type = 'product_variation'
        AND p.post_status = 'publish'
        AND (pml.sku IS NULL OR pml.sku = '' OR TRIM(pml.sku) = '')
      ");
    } else {
      $count = (int) $wpdb->get_var("
        SELECT COUNT(p.ID)
        FROM {$wpdb->posts} p
        WHERE p.post_type = 'product_variation'
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

    self::debug_log("Total variations without SKUs: $count");
    return $count;
  }

  /**
   * Get parent product ID for a variation
   *
   * @since 2.1.0
   * @param int $variation_id Variation ID
   * @return int|null Parent product ID or null
   */
  public static function get_variation_parent_id(int $variation_id): ?int
  {
    $variation = wc_get_product($variation_id);

    if (!$variation || !$variation instanceof \WC_Product_Variation) {
      return null;
    }

    return $variation->get_parent_id();
  }

  /**
   * Get comprehensive SKU statistics
   *
   * Returns counts for products and variations with and without SKUs.
   *
   * @since 2.2.0
   * @return array{
   *   products_total: int,
   *   products_with_sku: int,
   *   products_without_sku: int,
   *   variations_total: int,
   *   variations_with_sku: int,
   *   variations_without_sku: int,
   *   coverage_percent: float
   * }
   */
  public static function get_sku_statistics(): array
  {
    global $wpdb;

    $stats = [
      'products_total' => 0,
      'products_with_sku' => 0,
      'products_without_sku' => 0,
      'variations_total' => 0,
      'variations_with_sku' => 0,
      'variations_without_sku' => 0,
      'coverage_percent' => 0.0,
    ];

    if (self::is_hpos_enabled()) {
      // Products with SKU
      $stats['products_with_sku'] = (int) $wpdb->get_var("
        SELECT COUNT(p.id)
        FROM {$wpdb->prefix}wc_products p
        INNER JOIN {$wpdb->prefix}wc_product_meta_lookup pml ON p.id = pml.product_id
        WHERE p.status = 'publish'
        AND pml.sku IS NOT NULL AND pml.sku != '' AND TRIM(pml.sku) != ''
      ");

      // Products without SKU
      $stats['products_without_sku'] = (int) $wpdb->get_var("
        SELECT COUNT(p.id)
        FROM {$wpdb->prefix}wc_products p
        LEFT JOIN {$wpdb->prefix}wc_product_meta_lookup pml ON p.id = pml.product_id
        WHERE p.status = 'publish'
        AND (pml.sku IS NULL OR pml.sku = '' OR TRIM(pml.sku) = '')
      ");

      // Variations with SKU
      $stats['variations_with_sku'] = (int) $wpdb->get_var("
        SELECT COUNT(p.ID)
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->prefix}wc_product_meta_lookup pml ON p.ID = pml.product_id
        WHERE p.post_type = 'product_variation'
        AND p.post_status = 'publish'
        AND pml.sku IS NOT NULL AND pml.sku != '' AND TRIM(pml.sku) != ''
      ");

      // Variations without SKU
      $stats['variations_without_sku'] = (int) $wpdb->get_var("
        SELECT COUNT(p.ID)
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->prefix}wc_product_meta_lookup pml ON p.ID = pml.product_id
        WHERE p.post_type = 'product_variation'
        AND p.post_status = 'publish'
        AND (pml.sku IS NULL OR pml.sku = '' OR TRIM(pml.sku) = '')
      ");
    } else {
      // Products with SKU (legacy)
      $stats['products_with_sku'] = (int) $wpdb->get_var("
        SELECT COUNT(p.ID)
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'product'
        AND p.post_status = 'publish'
        AND pm.meta_key = '_sku'
        AND pm.meta_value IS NOT NULL AND pm.meta_value != '' AND TRIM(pm.meta_value) != ''
      ");

      // Products without SKU (legacy)
      $stats['products_without_sku'] = (int) $wpdb->get_var("
        SELECT COUNT(p.ID)
        FROM {$wpdb->posts} p
        WHERE p.post_type = 'product'
        AND p.post_status = 'publish'
        AND p.ID NOT IN (
          SELECT pm.post_id
          FROM {$wpdb->postmeta} pm
          WHERE pm.meta_key = '_sku'
          AND pm.meta_value IS NOT NULL AND pm.meta_value != '' AND TRIM(pm.meta_value) != ''
        )
      ");

      // Variations with SKU (legacy)
      $stats['variations_with_sku'] = (int) $wpdb->get_var("
        SELECT COUNT(p.ID)
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'product_variation'
        AND p.post_status = 'publish'
        AND pm.meta_key = '_sku'
        AND pm.meta_value IS NOT NULL AND pm.meta_value != '' AND TRIM(pm.meta_value) != ''
      ");

      // Variations without SKU (legacy)
      $stats['variations_without_sku'] = (int) $wpdb->get_var("
        SELECT COUNT(p.ID)
        FROM {$wpdb->posts} p
        WHERE p.post_type = 'product_variation'
        AND p.post_status = 'publish'
        AND p.ID NOT IN (
          SELECT pm.post_id
          FROM {$wpdb->postmeta} pm
          WHERE pm.meta_key = '_sku'
          AND pm.meta_value IS NOT NULL AND pm.meta_value != '' AND TRIM(pm.meta_value) != ''
        )
      ");
    }

    // Calculate totals
    $stats['products_total'] = $stats['products_with_sku'] + $stats['products_without_sku'];
    $stats['variations_total'] = $stats['variations_with_sku'] + $stats['variations_without_sku'];

    // Calculate coverage percentage
    $total_items = $stats['products_total'] + $stats['variations_total'];
    $items_with_sku = $stats['products_with_sku'] + $stats['variations_with_sku'];

    if ($total_items > 0) {
      $stats['coverage_percent'] = round(($items_with_sku / $total_items) * 100, 1);
    }

    return $stats;
  }
}
