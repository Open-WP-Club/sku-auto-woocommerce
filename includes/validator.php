<?php

defined('ABSPATH') || exit;

class SKU_Generator_Validator
{
  public function validate_sku_format($sku)
  {
    $issues = array();
    $valid = true;

    // Check if empty
    if (empty($sku)) {
      $issues[] = __('SKU is empty', 'sku-generator');
      $valid = false;
      return array('valid' => $valid, 'issues' => $issues);
    }

    // Convert to string to handle any type issues
    $sku = (string) $sku;

    // Check length
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
    if (strpos($sku, ' ') !== false) {
      $issues[] = __('SKU contains spaces', 'sku-generator');
      $valid = false;
    }

    // Check for invalid characters (only allow alphanumeric, hyphens, underscores)
    if (!preg_match('/^[A-Za-z0-9_-]+$/', $sku)) {
      $invalid_chars = $this->find_invalid_characters($sku);
      $issues[] = sprintf(
        __('SKU contains invalid characters: %s (only A-Z, a-z, 0-9, -, _ allowed)', 'sku-generator'),
        implode(', ', $invalid_chars)
      );
      $valid = false;
    }

    // Check for common problematic patterns
    $pattern_issues = $this->check_problematic_patterns($sku);
    if (!empty($pattern_issues)) {
      $issues = array_merge($issues, $pattern_issues);
      $valid = false;
    }

    return array(
      'valid' => $valid,
      'issues' => $issues
    );
  }

  public function validate_sku_uniqueness($sku, $exclude_product_id = 0)
  {
    global $wpdb;

    if ($this->is_hpos_enabled()) {
      // Use HPOS tables
      $query = "SELECT product_id FROM {$wpdb->prefix}wc_product_meta_lookup WHERE sku = %s";
      $params = array($sku);

      if ($exclude_product_id > 0) {
        $query .= " AND product_id != %d";
        $params[] = $exclude_product_id;
      }

      $existing_product_id = $wpdb->get_var($wpdb->prepare($query, $params));
    } else {
      // Use traditional post meta
      $query = "
        SELECT post_id FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
        WHERE pm.meta_key = '_sku' 
        AND pm.meta_value = %s 
        AND p.post_type = 'product'
        AND p.post_status = 'publish'
      ";
      $params = array($sku);

      if ($exclude_product_id > 0) {
        $query .= " AND pm.post_id != %d";
        $params[] = $exclude_product_id;
      }

      $existing_product_id = $wpdb->get_var($wpdb->prepare($query, $params));
    }

    return array(
      'unique' => empty($existing_product_id),
      'existing_product_id' => $existing_product_id
    );
  }

  public function get_duplicate_skus()
  {
    global $wpdb;

    if ($this->is_hpos_enabled()) {
      // Use HPOS tables
      $results = $wpdb->get_results("
        SELECT sku, COUNT(*) as count, GROUP_CONCAT(product_id) as product_ids
        FROM {$wpdb->prefix}wc_product_meta_lookup 
        WHERE sku != '' AND sku IS NOT NULL
        GROUP BY sku 
        HAVING count > 1
      ");
    } else {
      // Use traditional post meta
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

    $duplicates = array();
    foreach ($results as $result) {
      $product_ids = explode(',', $result->product_ids);
      $products = array();

      foreach ($product_ids as $product_id) {
        $product = wc_get_product($product_id);
        if ($product) {
          $products[] = array(
            'id' => $product_id,
            'name' => $product->get_name()
          );
        }
      }

      if (!empty($products)) {
        $duplicates[$result->sku] = $products;
      }
    }

    return $duplicates;
  }

  public function get_invalid_format_skus($batch_size = 50, $offset = 0)
  {
    global $wpdb;

    if ($this->is_hpos_enabled()) {
      // Use HPOS tables
      $results = $wpdb->get_results($wpdb->prepare("
        SELECT product_id, sku FROM {$wpdb->prefix}wc_product_meta_lookup 
        WHERE sku != '' AND sku IS NOT NULL
        LIMIT %d OFFSET %d
      ", $batch_size, $offset));
    } else {
      // Use traditional post meta
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

    $invalid_skus = array();

    foreach ($results as $result) {
      $validation = $this->validate_sku_format($result->sku);
      if (!$validation['valid']) {
        $product = wc_get_product($result->product_id);
        if ($product) {
          $invalid_skus[] = array(
            'product_id' => $result->product_id,
            'product_name' => $product->get_name(),
            'sku' => $result->sku,
            'issues' => $validation['issues']
          );
        }
      }
    }

    return $invalid_skus;
  }

  private function find_invalid_characters($sku)
  {
    $invalid_chars = array();
    for ($i = 0; $i < strlen($sku); $i++) {
      $char = $sku[$i];
      if (!preg_match('/[A-Za-z0-9_-]/', $char)) {
        $display_char = $char === ' ' ? '[space]' : $char;
        $invalid_chars[] = "'" . $display_char . "'";
      }
    }
    return array_unique($invalid_chars);
  }

  private function check_problematic_patterns($sku)
  {
    $issues = array();

    // Check for consecutive separators
    if (preg_match('/[-_]{2,}/', $sku)) {
      $issues[] = __('SKU contains consecutive separators', 'sku-generator');
    }

    // Check for starting/ending with separators
    if (preg_match('/^[-_]|[-_]$/', $sku)) {
      $issues[] = __('SKU starts or ends with separator', 'sku-generator');
    }

    // Check for only separators and no actual content
    if (preg_match('/^[-_]+$/', $sku)) {
      $issues[] = __('SKU contains only separators', 'sku-generator');
    }

    // Check for extremely short SKUs (less than 3 characters might be problematic)
    if (strlen($sku) < 3) {
      $issues[] = __('SKU is very short (less than 3 characters)', 'sku-generator');
    }

    return $issues;
  }

  private function is_hpos_enabled()
  {
    return class_exists('Automattic\WooCommerce\Utilities\OrderUtil') &&
      method_exists('Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled') &&
      \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
  }
}
