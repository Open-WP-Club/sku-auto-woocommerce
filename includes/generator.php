<?php

defined('ABSPATH') || exit;

class SKU_Generator_Core
{
  public function generate_unique_sku($product = null)
  {
    $options = get_option('sku_generator_options', array());
    $prefix = isset($options['prefix']) ? $options['prefix'] : '';
    $suffix = isset($options['suffix']) ? $options['suffix'] : '';
    $pattern_type = isset($options['pattern_type']) ? $options['pattern_type'] : 'alphanumeric';
    $length = isset($options['pattern_length']) ? intval($options['pattern_length']) : 8;
    $separator = isset($options['separator']) ? $options['separator'] : '-';
    $copy_to_gtin = isset($options['copy_to_gtin']) ? $options['copy_to_gtin'] : '0';

    // Ensure separator is valid for SKU format
    if (!in_array($separator, array('-', '_'))) {
      $separator = '-';
    }

    // Build character set based on pattern type
    $chars = $this->get_character_set($pattern_type);

    $max_attempts = 100; // Prevent infinite loops
    $attempts = 0;

    do {
      $attempts++;
      $sku_parts = array();

      // Add prefix if set (sanitize it)
      if (!empty($prefix)) {
        $clean_prefix = $this->sanitize_sku_part($prefix);
        if (!empty($clean_prefix)) {
          $sku_parts[] = $clean_prefix;
        }
      }

      // Add category if enabled
      if (!empty($options['include_category']) && $options['include_category'] == '1' && $product) {
        $category_part = $this->get_category_part($product, $options);
        if (!empty($category_part)) {
          $sku_parts[] = $category_part;
        }
      }

      // Add date if enabled
      if (!empty($options['include_date']) && $options['include_date'] == '1') {
        $date_format = isset($options['date_format']) ? $options['date_format'] : 'Ymd';
        $sku_parts[] = date($date_format);
      }

      // Add product ID if enabled
      if (!empty($options['include_product_id']) && $options['include_product_id'] == '1' && $product) {
        $sku_parts[] = $product->get_id();
      }

      // Add random part if product ID is not used or we need additional uniqueness
      if (empty($options['include_product_id']) || $options['include_product_id'] != '1') {
        $random = $this->generate_random_string($chars, $length);
        $sku_parts[] = $random;
      }

      // Add suffix if set (sanitize it)
      if (!empty($suffix)) {
        $clean_suffix = $this->sanitize_sku_part($suffix);
        if (!empty($clean_suffix)) {
          $sku_parts[] = $clean_suffix;
        }
      }

      // Combine all parts with separator
      $sku = implode($separator, array_filter($sku_parts));

      // Final validation to ensure SKU meets requirements
      $validator = new SKU_Generator_Validator();
      $validation = $validator->validate_sku_format($sku);
      if (!$validation['valid']) {
        error_log('SKU Generator: Generated invalid SKU: ' . $sku . ' - Issues: ' . implode(', ', $validation['issues']));
        continue; // Try again
      }
    } while ($this->sku_exists($sku) && $attempts < $max_attempts);

    if ($attempts >= $max_attempts) {
      error_log('SKU Generator: Failed to generate unique SKU after ' . $max_attempts . ' attempts');
      // Fallback: use timestamp + random
      $sku = 'SKU-' . time() . '-' . $this->generate_random_string($chars, 4);
    }

    // Copy SKU to GTIN field if enabled and product is provided
    if ($copy_to_gtin === '1' && $product) {
      $this->copy_sku_to_gtin($product, $sku);
    }

    return $sku;
  }

  private function get_character_set($pattern_type)
  {
    switch ($pattern_type) {
      case 'numeric':
        return '0123456789';
      case 'alphabetic':
        return 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
      case 'custom':
        // You can add custom pattern logic here
        return '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
      default: // alphanumeric
        return '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    }
  }

  private function generate_random_string($chars, $length)
  {
    $random = '';
    $chars_length = strlen($chars);

    for ($i = 0; $i < $length; $i++) {
      $random .= $chars[random_int(0, $chars_length - 1)];
    }

    return $random;
  }

  private function sanitize_sku_part($part)
  {
    // Remove any characters that aren't allowed in SKUs
    return preg_replace('/[^A-Za-z0-9_-]/', '', $part);
  }

  private function get_category_part($product, $options)
  {
    $categories = get_the_terms($product->get_id(), 'product_cat');
    if ($categories && !is_wp_error($categories)) {
      $first_cat = reset($categories);
      $cat_chars = isset($options['category_chars']) ? intval($options['category_chars']) : 2;
      $cat_code = $this->sanitize_sku_part(strtoupper($first_cat->slug));
      return substr($cat_code, 0, $cat_chars);
    }
    return '';
  }

  private function sku_exists($sku)
  {
    global $wpdb;

    // Check if HPOS is enabled
    if ($this->is_hpos_enabled()) {
      // Use HPOS tables
      $exists = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM {$wpdb->prefix}wc_product_meta_lookup 
        WHERE sku = %s
      ", $sku));
    } else {
      // Use traditional post meta
      $exists = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM {$wpdb->postmeta} 
        WHERE meta_key = '_sku' AND meta_value = %s
      ", $sku));
    }

    return $exists > 0;
  }

  private function copy_sku_to_gtin($product, $sku)
  {
    if (!$product) {
      return;
    }

    // Common GTIN/UPC/EAN/ISBN meta keys used by various plugins
    $gtin_meta_keys = array(
      '_global_unique_id',           // WooCommerce core GTIN field
      '_wpm_gtin_code',             // WP Marketing Robot
      '_ywbc_barcode_value',        // YITH WooCommerce Barcodes
      '_ts_gtin',                   // ThemeSense GTIN
      '_woo_upc',                   // WooCommerce UPC
      '_product_gtin',              // Generic GTIN
      '_gtin',                      // Simple GTIN
      '_upc',                       // UPC
      '_ean',                       // EAN
      '_isbn',                      // ISBN
      '_barcode'                    // Generic barcode
    );

    // Check if any GTIN field already has a value
    $existing_gtin = '';
    $used_meta_key = '';

    foreach ($gtin_meta_keys as $meta_key) {
      $value = $product->get_meta($meta_key);
      if (!empty($value)) {
        $existing_gtin = $value;
        $used_meta_key = $meta_key;
        break;
      }
    }

    // If no existing GTIN field found, use the default WooCommerce GTIN field
    if (empty($used_meta_key)) {
      $used_meta_key = '_global_unique_id';
    }

    // Set the SKU as the GTIN value
    $product->update_meta_data($used_meta_key, $sku);

    // Log the action
    error_log("SKU Generator: Copied SKU '$sku' to GTIN field '$used_meta_key' for product ID " . $product->get_id());
  }

  private function is_hpos_enabled()
  {
    return class_exists('Automattic\WooCommerce\Utilities\OrderUtil') &&
      method_exists('Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled') &&
      \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
  }
}
