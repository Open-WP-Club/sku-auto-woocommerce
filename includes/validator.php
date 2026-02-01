<?php

/**
 * SKU Validator
 *
 * Validates SKUs against WooCommerce standards and checks for uniqueness.
 *
 * @package SKU_Generator
 * @since 2.0.0
 */

defined('ABSPATH') || exit;

class SKU_Generator_Validator
{
  /**
   * Validate SKU format against WooCommerce standards
   *
   * @since 2.0.0
   * @param string $sku SKU to validate
   * @return array{valid: bool, issues: array<string>}
   */
  public function validate_sku_format(string $sku): array
  {
    $issues = [];
    $valid = true;

    if (empty($sku)) {
      return [
        'valid' => false,
        'issues' => [__('SKU is empty', 'sku-generator')],
      ];
    }

    // Check length (WooCommerce limit is 100 characters)
    if (strlen($sku) > 100) {
      $issues[] = __('SKU is longer than 100 characters', 'sku-generator');
      $valid = false;
    }

    // Check for leading/trailing whitespace
    if ($sku !== trim($sku)) {
      $issues[] = __('SKU has leading or trailing whitespace', 'sku-generator');
      $valid = false;
    }

    // Check for spaces
    if (str_contains($sku, ' ')) {
      $issues[] = __('SKU contains spaces', 'sku-generator');
      $valid = false;
    }

    // Check for invalid characters (only allow alphanumeric, hyphens, underscores)
    if (!preg_match('/^[A-Za-z0-9_-]+$/', $sku)) {
      $invalid_chars = $this->find_invalid_characters($sku);
      $issues[] = sprintf(
        /* translators: %s: list of invalid characters */
        __('SKU contains invalid characters: %s (only A-Z, a-z, 0-9, -, _ allowed)', 'sku-generator'),
        implode(', ', $invalid_chars)
      );
      $valid = false;
    }

    // Check for problematic patterns
    $pattern_issues = $this->check_problematic_patterns($sku);
    if (!empty($pattern_issues)) {
      $issues = array_merge($issues, $pattern_issues);
      $valid = false;
    }

    return [
      'valid' => $valid,
      'issues' => $issues,
    ];
  }

  /**
   * Validate SKU uniqueness across all products
   *
   * @since 2.0.0
   * @param string $sku SKU to check
   * @param int $exclude_product_id Product ID to exclude from check
   * @return array{unique: bool, existing_product_id: int|null}
   */
  public function validate_sku_uniqueness(string $sku, int $exclude_product_id = 0): array
  {
    global $wpdb;

    if (SKU_Generator_Helpers::is_hpos_enabled()) {
      $query = "SELECT product_id FROM {$wpdb->prefix}wc_product_meta_lookup WHERE sku = %s";
      $params = [$sku];

      if ($exclude_product_id > 0) {
        $query .= " AND product_id != %d";
        $params[] = $exclude_product_id;
      }

      $existing_product_id = $wpdb->get_var($wpdb->prepare($query, ...$params));
    } else {
      $query = "
        SELECT post_id FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
        WHERE pm.meta_key = '_sku'
        AND pm.meta_value = %s
        AND p.post_type = 'product'
        AND p.post_status = 'publish'
      ";
      $params = [$sku];

      if ($exclude_product_id > 0) {
        $query .= " AND pm.post_id != %d";
        $params[] = $exclude_product_id;
      }

      $existing_product_id = $wpdb->get_var($wpdb->prepare($query, ...$params));
    }

    return [
      'unique' => empty($existing_product_id),
      'existing_product_id' => $existing_product_id ? (int) $existing_product_id : null,
    ];
  }

  /**
   * Get all duplicate SKUs in the database
   *
   * @since 2.0.0
   * @return array<string, array<array{id: int, name: string}>>
   */
  public function get_duplicate_skus(): array
  {
    global $wpdb;

    if (SKU_Generator_Helpers::is_hpos_enabled()) {
      $results = $wpdb->get_results("
        SELECT sku, COUNT(*) as count, GROUP_CONCAT(product_id) as product_ids
        FROM {$wpdb->prefix}wc_product_meta_lookup
        WHERE sku != '' AND sku IS NOT NULL
        GROUP BY sku
        HAVING count > 1
      ");
    } else {
      $results = $wpdb->get_results("
        SELECT pm.meta_value as sku, COUNT(*) as count, GROUP_CONCAT(pm.post_id) as product_ids
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
        WHERE pm.meta_key = '_sku'
        AND pm.meta_value != ''
        AND pm.meta_value IS NOT NULL
        AND p.post_type = 'product'
        AND p.post_status = 'publish'
        GROUP BY pm.meta_value
        HAVING count > 1
      ");
    }

    $duplicates = [];

    foreach ($results as $result) {
      $product_ids = explode(',', $result->product_ids);
      $products = [];

      foreach ($product_ids as $product_id) {
        $product = wc_get_product($product_id);
        if ($product) {
          $products[] = [
            'id' => (int) $product_id,
            'name' => $product->get_name(),
          ];
        }
      }

      if (!empty($products)) {
        $duplicates[$result->sku] = $products;
      }
    }

    return $duplicates;
  }

  /**
   * Get SKUs with invalid format
   *
   * @since 2.0.0
   * @param int $batch_size Number of SKUs to check per batch
   * @param int $offset Pagination offset
   * @return array<array{product_id: int, product_name: string, sku: string, issues: array<string>}>
   */
  public function get_invalid_format_skus(int $batch_size = 50, int $offset = 0): array
  {
    global $wpdb;

    if (SKU_Generator_Helpers::is_hpos_enabled()) {
      $results = $wpdb->get_results($wpdb->prepare("
        SELECT product_id, sku FROM {$wpdb->prefix}wc_product_meta_lookup
        WHERE sku != '' AND sku IS NOT NULL
        LIMIT %d OFFSET %d
      ", $batch_size, $offset));
    } else {
      $results = $wpdb->get_results($wpdb->prepare("
        SELECT pm.post_id as product_id, pm.meta_value as sku
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
        WHERE pm.meta_key = '_sku'
        AND pm.meta_value != ''
        AND pm.meta_value IS NOT NULL
        AND p.post_type = 'product'
        AND p.post_status = 'publish'
        LIMIT %d OFFSET %d
      ", $batch_size, $offset));
    }

    $invalid_skus = [];

    foreach ($results as $result) {
      $validation = $this->validate_sku_format($result->sku);

      if (!$validation['valid']) {
        $product = wc_get_product($result->product_id);
        if ($product) {
          $invalid_skus[] = [
            'product_id' => (int) $result->product_id,
            'product_name' => $product->get_name(),
            'sku' => $result->sku,
            'issues' => $validation['issues'],
          ];
        }
      }
    }

    return $invalid_skus;
  }

  /**
   * Find invalid characters in a SKU
   *
   * @since 2.0.0
   * @param string $sku SKU to check
   * @return array<string>
   */
  private function find_invalid_characters(string $sku): array
  {
    $invalid_chars = [];
    $length = strlen($sku);

    for ($i = 0; $i < $length; $i++) {
      $char = $sku[$i];
      if (!preg_match('/[A-Za-z0-9_-]/', $char)) {
        $display_char = $char === ' ' ? '[space]' : $char;
        $invalid_chars[] = "'" . $display_char . "'";
      }
    }

    return array_unique($invalid_chars);
  }

  /**
   * Check for problematic SKU patterns
   *
   * @since 2.0.0
   * @param string $sku SKU to check
   * @return array<string>
   */
  private function check_problematic_patterns(string $sku): array
  {
    $issues = [];

    // Check for consecutive separators
    if (preg_match('/[-_]{2,}/', $sku)) {
      $issues[] = __('SKU contains consecutive separators', 'sku-generator');
    }

    // Check for starting/ending with separators
    if (preg_match('/^[-_]|[-_]$/', $sku)) {
      $issues[] = __('SKU starts or ends with separator', 'sku-generator');
    }

    // Check for only separators
    if (preg_match('/^[-_]+$/', $sku)) {
      $issues[] = __('SKU contains only separators', 'sku-generator');
    }

    // Check for very short SKUs
    if (strlen($sku) < 3) {
      $issues[] = __('SKU is very short (less than 3 characters)', 'sku-generator');
    }

    return $issues;
  }
}
