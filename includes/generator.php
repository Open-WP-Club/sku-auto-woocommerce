<?php

/**
 * SKU Generator Core
 *
 * Handles SKU generation logic with support for multiple patterns,
 * permalink-based SKUs, and GTIN field integration.
 *
 * @package SKU_Generator
 * @since 2.0.0
 */

defined('ABSPATH') || exit;

class SKU_Generator_Core
{
  /**
   * Generate a unique SKU for a product
   *
   * @since 2.0.0
   * @param \WC_Product|null $product WooCommerce product object
   * @return string Generated SKU
   */
  public function generate_unique_sku(?\WC_Product $product = null): string
  {
    $options = get_option('sku_generator_options', []);
    $prefix = $options['prefix'] ?? '';
    $suffix = $options['suffix'] ?? '';
    $pattern_type = $options['pattern_type'] ?? 'alphanumeric';
    $length = (int) ($options['pattern_length'] ?? 8);
    $separator = $options['separator'] ?? '-';
    $copy_to_gtin = $options['copy_to_gtin'] ?? '0';
    $use_permalink = $options['use_permalink'] ?? '0';

    // Ensure separator is valid for SKU format
    if (!in_array($separator, array('-', '_'))) {
      $separator = '-';
    }

    // Build character set based on pattern type (for fallback random generation)
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

      // Main SKU part: Use permalink if enabled, otherwise use random/ID
      if ($use_permalink === '1' && $product) {
        $permalink_part = $this->get_permalink_part($product);
        if (!empty($permalink_part)) {
          $sku_parts[] = $permalink_part;
        } else {
          // Fallback to random if permalink is not available
          $random = $this->generate_random_string($chars, $length);
          $sku_parts[] = $random;
          SKU_Generator_Helpers::debug_log("Permalink not available for product ID " . $product->get_id() . ", using random fallback");
        }
      } else {
        // Use random part if permalink is not enabled and product ID is not used
        if (empty($options['include_product_id']) || $options['include_product_id'] != '1') {
          $random = $this->generate_random_string($chars, $length);
          $sku_parts[] = $random;
        }
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
        SKU_Generator_Helpers::debug_log('Generated invalid SKU: ' . $sku . ' - Issues: ' . implode(', ', $validation['issues']));
        continue;
      }
    } while ($this->sku_exists($sku) && $attempts < $max_attempts);

    if ($attempts >= $max_attempts) {
      SKU_Generator_Helpers::debug_log('Failed to generate unique SKU after ' . $max_attempts . ' attempts');
      $sku = 'SKU-' . time() . '-' . $this->generate_random_string($chars, 4);
    }

    // Copy SKU to GTIN field if enabled and product is provided
    if ($copy_to_gtin === '1' && $product) {
      $this->copy_sku_to_gtin($product, $sku);
    }

    return $sku;
  }

  /**
   * Get character set based on pattern type
   *
   * @since 2.0.0
   * @param string $pattern_type Pattern type identifier
   * @return string Character set for random generation
   */
  private function get_character_set(string $pattern_type): string
  {
    return match ($pattern_type) {
      'numeric' => '0123456789',
      'alphabetic' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
      default => '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ',
    };
  }

  /**
   * Generate a cryptographically secure random string
   *
   * @since 2.0.0
   * @param string $chars Character set to use
   * @param int $length Desired length
   * @return string Random string
   */
  private function generate_random_string(string $chars, int $length): string
  {
    $random = '';
    $chars_length = strlen($chars);

    for ($i = 0; $i < $length; $i++) {
      $random .= $chars[random_int(0, $chars_length - 1)];
    }

    return $random;
  }

  /**
   * Sanitize a SKU component
   *
   * Removes any characters not allowed in WooCommerce SKUs.
   *
   * @since 2.0.0
   * @param string $part SKU component to sanitize
   * @return string Sanitized component
   */
  private function sanitize_sku_part(string $part): string
  {
    return preg_replace('/[^A-Za-z0-9_-]/', '', $part);
  }

  /**
   * Get category-based SKU component
   *
   * @since 2.0.0
   * @param \WC_Product $product Product object
   * @param array<string, mixed> $options Plugin options
   * @return string Category code
   */
  private function get_category_part(\WC_Product $product, array $options): string
  {
    $categories = get_the_terms($product->get_id(), 'product_cat');

    if (!$categories || is_wp_error($categories)) {
      return '';
    }

    $first_cat = reset($categories);
    $cat_chars = (int) ($options['category_chars'] ?? 2);
    $cat_code = $this->sanitize_sku_part(strtoupper($first_cat->slug));

    return substr($cat_code, 0, $cat_chars);
  }

  /**
   * Get permalink-based SKU component
   *
   * Uses the product's URL slug to generate a human-readable SKU part.
   *
   * @since 2.0.0
   * @param \WC_Product $product Product object
   * @return string Permalink-based SKU part or empty string on failure
   */
  private function get_permalink_part(\WC_Product $product): string
  {
    $product_post = get_post($product->get_id());

    if (!$product_post) {
      return '';
    }

    $slug = $product_post->post_name;

    // If slug is empty or auto-generated, create one from the title
    if (empty($slug) || is_numeric($slug)) {
      $slug = sanitize_title($product->get_name());
    }

    $clean_slug = $this->sanitize_sku_part($slug);

    if (empty($clean_slug) || strlen($clean_slug) < 3) {
      return '';
    }

    // Limit length to prevent overly long SKUs
    $clean_slug = substr($clean_slug, 0, 50);

    SKU_Generator_Helpers::debug_log("Using permalink '$clean_slug' for product ID " . $product->get_id());

    return $clean_slug;
  }

  /**
   * Copy SKU to GTIN field
   *
   * Detects existing GTIN fields from various plugins and uses the
   * appropriate one, or falls back to WooCommerce core GTIN field.
   *
   * @since 2.0.0
   * @param \WC_Product $product Product object
   * @param string $sku SKU to copy
   */
  private function copy_sku_to_gtin(\WC_Product $product, string $sku): void
  {
    $gtin_meta_keys = SKU_Generator_Helpers::get_gtin_meta_keys();
    $used_meta_key = '';

    // Check if any GTIN field already has a value
    foreach ($gtin_meta_keys as $meta_key) {
      $value = $product->get_meta($meta_key);
      if (!empty($value)) {
        $used_meta_key = $meta_key;
        break;
      }
    }

    // Default to WooCommerce core GTIN field
    if (empty($used_meta_key)) {
      $used_meta_key = '_global_unique_id';
    }

    $product->update_meta_data($used_meta_key, $sku);

    SKU_Generator_Helpers::debug_log("Copied SKU '$sku' to GTIN field '$used_meta_key' for product ID " . $product->get_id());
  }

  /**
   * Check if a SKU already exists in the database
   *
   * Uses HPOS-aware query for compatibility with modern WooCommerce.
   *
   * @since 2.0.0
   * @param string $sku SKU to check
   * @return bool True if SKU exists
   */
  private function sku_exists(string $sku): bool
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

  /**
   * Generate SKUs for all variations of a variable product
   *
   * Creates variation SKUs based on parent SKU with sequential numbering.
   * Example: Parent SKU "PROD-123" → Variations "PROD-123-1", "PROD-123-2", etc.
   *
   * @since 2.1.0
   * @param \WC_Product_Variable $parent_product The parent variable product
   * @param string $parent_sku The parent product's SKU
   * @return int Number of variations processed
   */
  public function generate_variation_skus(\WC_Product_Variable $parent_product, string $parent_sku): int
  {
    $options = get_option('sku_generator_options', []);
    $copy_to_gtin = $options['copy_to_gtin'] ?? '0';
    $separator = $options['separator'] ?? '-';

    $variation_ids = $parent_product->get_children();
    $processed = 0;
    $variation_number = 1;

    foreach ($variation_ids as $variation_id) {
      $variation = wc_get_product($variation_id);

      if (!$variation || !$variation instanceof \WC_Product_Variation) {
        continue;
      }

      // Skip if variation already has a SKU
      $current_sku = $variation->get_sku();
      if (!empty($current_sku)) {
        SKU_Generator_Helpers::debug_log("Variation ID $variation_id already has SKU: '$current_sku', skipping");
        $variation_number++;
        continue;
      }

      // Generate variation SKU: parent_sku-1, parent_sku-2, etc.
      $variation_sku = $parent_sku . $separator . $variation_number;

      // Ensure uniqueness
      $attempt = 0;
      while ($this->sku_exists($variation_sku) && $attempt < 100) {
        $attempt++;
        $variation_sku = $parent_sku . $separator . $variation_number . $separator . $attempt;
      }

      $variation->set_sku($variation_sku);

      // Copy to GTIN if enabled
      if ($copy_to_gtin === '1') {
        $this->copy_sku_to_gtin_public($variation, $variation_sku);
      }

      $variation->save();
      $processed++;
      $variation_number++;

      SKU_Generator_Helpers::debug_log("Generated variation SKU '$variation_sku' for variation ID $variation_id (parent: {$parent_product->get_id()})");
    }

    return $processed;
  }

  /**
   * Generate SKUs for variations without a parent SKU
   *
   * For cases where we need to generate variation SKUs independently,
   * using a provided base SKU.
   *
   * @since 2.1.0
   * @param int $parent_id Parent product ID
   * @param string|null $base_sku Base SKU to use (if null, gets from parent)
   * @return int Number of variations processed
   */
  public function generate_variation_skus_by_parent_id(int $parent_id, ?string $base_sku = null): int
  {
    $parent_product = wc_get_product($parent_id);

    if (!$parent_product || !$parent_product instanceof \WC_Product_Variable) {
      return 0;
    }

    // Get parent SKU if not provided
    if ($base_sku === null) {
      $base_sku = $parent_product->get_sku();
    }

    // If parent still has no SKU, generate one
    if (empty($base_sku)) {
      $base_sku = $this->generate_unique_sku($parent_product);
      $parent_product->set_sku($base_sku);
      $parent_product->save();
      SKU_Generator_Helpers::debug_log("Generated parent SKU '$base_sku' for variable product ID $parent_id");
    }

    return $this->generate_variation_skus($parent_product, $base_sku);
  }

  /**
   * Copy SKU to GTIN field (public wrapper)
   *
   * @since 2.1.0
   * @param \WC_Product $product Product object
   * @param string $sku SKU to copy
   */
  public function copy_sku_to_gtin_public(\WC_Product $product, string $sku): void
  {
    $this->copy_sku_to_gtin($product, $sku);
  }
}
