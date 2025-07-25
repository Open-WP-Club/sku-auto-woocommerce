<?php

/**
 * Plugin Name: SKU Generator
 * Description: Automatically generates SKUs for WooCommerce products with validation
 * Version: 1.1.0
 * Author: Your Name
 * Text Domain: sku-generator
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

class SKU_Generator
{
  private $options;
  private $batch_size = 50; // Process products in batches to avoid timeouts

  public function __construct()
  {
    add_action('admin_menu', array($this, 'add_admin_menu'));
    add_action('admin_init', array($this, 'register_settings'));
    add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    add_action('wp_ajax_generate_bulk_skus', array($this, 'ajax_generate_bulk_skus'));
    add_action('wp_ajax_validate_skus', array($this, 'ajax_validate_skus'));
    add_action('wp_ajax_fix_invalid_skus', array($this, 'ajax_fix_invalid_skus'));
  }

  public function add_admin_menu()
  {
    add_submenu_page(
      'woocommerce',
      __('SKU Generator', 'sku-generator'),
      __('SKU Generator', 'sku-generator'),
      'manage_woocommerce',
      'sku-generator',
      array($this, 'admin_page')
    );
  }

  public function register_settings()
  {
    register_setting('sku_generator_options', 'sku_generator_options');

    add_settings_section(
      'sku_generator_main',
      __('SKU Generator Settings', 'sku-generator'),
      null,
      'sku-generator'
    );

    // Basic Settings
    add_settings_field(
      'prefix',
      __('SKU Prefix', 'sku-generator'),
      array($this, 'prefix_field'),
      'sku-generator',
      'sku_generator_main'
    );

    add_settings_field(
      'suffix',
      __('SKU Suffix', 'sku-generator'),
      array($this, 'suffix_field'),
      'sku-generator',
      'sku_generator_main'
    );

    // Pattern Settings
    add_settings_field(
      'include_product_id',
      __('Include Product ID', 'sku-generator'),
      array($this, 'include_product_id_field'),
      'sku-generator',
      'sku_generator_main'
    );

    add_settings_field(
      'pattern_type',
      __('SKU Pattern Type', 'sku-generator'),
      array($this, 'pattern_type_field'),
      'sku-generator',
      'sku_generator_main'
    );

    add_settings_field(
      'pattern_length',
      __('Pattern Length', 'sku-generator'),
      array($this, 'pattern_length_field'),
      'sku-generator',
      'sku_generator_main'
    );

    add_settings_field(
      'include_category',
      __('Include Category', 'sku-generator'),
      array($this, 'include_category_field'),
      'sku-generator',
      'sku_generator_main'
    );

    add_settings_field(
      'category_chars',
      __('Category Characters', 'sku-generator'),
      array($this, 'category_chars_field'),
      'sku-generator',
      'sku_generator_main'
    );

    add_settings_field(
      'include_date',
      __('Include Date', 'sku-generator'),
      array($this, 'include_date_field'),
      'sku-generator',
      'sku_generator_main'
    );

    add_settings_field(
      'date_format',
      __('Date Format', 'sku-generator'),
      array($this, 'date_format_field'),
      'sku-generator',
      'sku_generator_main'
    );

    add_settings_field(
      'separator',
      __('Separator Character', 'sku-generator'),
      array($this, 'separator_field'),
      'sku-generator',
      'sku_generator_main'
    );
  }

  public function prefix_field()
  {
    $options = get_option('sku_generator_options');
    $prefix = isset($options['prefix']) ? $options['prefix'] : '';
    echo "<input type='text' name='sku_generator_options[prefix]' value='" . esc_attr($prefix) . "' />";
  }

  public function suffix_field()
  {
    $options = get_option('sku_generator_options');
    $suffix = isset($options['suffix']) ? $options['suffix'] : '';
    echo "<input type='text' name='sku_generator_options[suffix]' value='" . esc_attr($suffix) . "' />";
    echo "<p class='description'>" . __('Add a suffix to the end of each SKU.', 'sku-generator') . "</p>";
  }

  public function include_product_id_field()
  {
    $options = get_option('sku_generator_options');
    $include_product_id = isset($options['include_product_id']) ? $options['include_product_id'] : '0';
    echo "<input type='checkbox' name='sku_generator_options[include_product_id]' value='1' " . checked($include_product_id, '1', false) . "/>";
    echo "<p class='description'>" . __('Include product ID in SKU', 'sku-generator') . "</p>";
  }

  public function pattern_type_field()
  {
    $options = get_option('sku_generator_options');
    $pattern_type = isset($options['pattern_type']) ? $options['pattern_type'] : 'alphanumeric';
?>
    <select name="sku_generator_options[pattern_type]">
      <option value="alphanumeric" <?php selected($pattern_type, 'alphanumeric'); ?>><?php _e('Alphanumeric (A-Z, 0-9)', 'sku-generator'); ?></option>
      <option value="numeric" <?php selected($pattern_type, 'numeric'); ?>><?php _e('Numeric Only (0-9)', 'sku-generator'); ?></option>
      <option value="alphabetic" <?php selected($pattern_type, 'alphabetic'); ?>><?php _e('Alphabetic Only (A-Z)', 'sku-generator'); ?></option>
      <option value="custom" <?php selected($pattern_type, 'custom'); ?>><?php _e('Custom Pattern', 'sku-generator'); ?></option>
    </select>
  <?php
  }

  public function pattern_length_field()
  {
    $options = get_option('sku_generator_options');
    $length = isset($options['pattern_length']) ? $options['pattern_length'] : '8';
    echo "<input type='number' min='4' max='32' name='sku_generator_options[pattern_length]' value='" . esc_attr($length) . "' />";
    echo "<p class='description'>" . __('Length of the random part of the SKU (4-32 characters)', 'sku-generator') . "</p>";
  }

  public function include_category_field()
  {
    $options = get_option('sku_generator_options');
    $include_category = isset($options['include_category']) ? $options['include_category'] : '0';
    echo "<input type='checkbox' name='sku_generator_options[include_category]' value='1' " . checked($include_category, '1', false) . "/>";
    echo "<p class='description'>" . __('Include product category code in SKU', 'sku-generator') . "</p>";
  }

  public function category_chars_field()
  {
    $options = get_option('sku_generator_options');
    $category_chars = isset($options['category_chars']) ? $options['category_chars'] : '2';
    echo "<input type='number' min='1' max='5' name='sku_generator_options[category_chars]' value='" . esc_attr($category_chars) . "' />";
    echo "<p class='description'>" . __('Number of characters to use from category name (1-5)', 'sku-generator') . "</p>";
  }

  public function include_date_field()
  {
    $options = get_option('sku_generator_options');
    $include_date = isset($options['include_date']) ? $options['include_date'] : '0';
    echo "<input type='checkbox' name='sku_generator_options[include_date]' value='1' " . checked($include_date, '1', false) . "/>";
    echo "<p class='description'>" . __('Include date in SKU', 'sku-generator') . "</p>";
  }

  public function date_format_field()
  {
    $options = get_option('sku_generator_options');
    $date_format = isset($options['date_format']) ? $options['date_format'] : 'Ymd';
  ?>
    <select name="sku_generator_options[date_format]">
      <option value="Ymd" <?php selected($date_format, 'Ymd'); ?>><?php _e('YYYYMMDD', 'sku-generator'); ?></option>
      <option value="ymd" <?php selected($date_format, 'ymd'); ?>><?php _e('YYMMDD', 'sku-generator'); ?></option>
      <option value="ym" <?php selected($date_format, 'ym'); ?>><?php _e('YYMM', 'sku-generator'); ?></option>
      <option value="y" <?php selected($date_format, 'y'); ?>><?php _e('YY', 'sku-generator'); ?></option>
    </select>
  <?php
  }

  public function separator_field()
  {
    $options = get_option('sku_generator_options');
    $separator = isset($options['separator']) ? $options['separator'] : '-';
    echo "<input type='text' maxlength='1' name='sku_generator_options[separator]' value='" . esc_attr($separator) . "' />";
    echo "<p class='description'>" . __('Character to separate SKU parts (e.g., -)', 'sku-generator') . "</p>";
  }

  public function admin_page()
  {
  ?>
    <div class="wrap">
      <h1><?php echo esc_html(__('SKU Generator', 'sku-generator')); ?></h1>

      <div class="nav-tab-wrapper">
        <a href="#settings" class="nav-tab nav-tab-active" id="settings-tab"><?php _e('Settings', 'sku-generator'); ?></a>
        <a href="#generate" class="nav-tab" id="generate-tab"><?php _e('Generate SKUs', 'sku-generator'); ?></a>
        <a href="#validate" class="nav-tab" id="validate-tab"><?php _e('Validate SKUs', 'sku-generator'); ?></a>
      </div>

      <div id="settings-content" class="tab-content">
        <form method="post" action="options.php">
          <?php
          settings_fields('sku_generator_options');
          do_settings_sections('sku-generator');
          submit_button();
          ?>
        </form>
      </div>

      <div id="generate-content" class="tab-content" style="display: none;">
        <h2><?php echo esc_html(__('Bulk Generate SKUs', 'sku-generator')); ?></h2>
        <p><?php echo esc_html(__('Generate SKUs for all products that don\'t have one.', 'sku-generator')); ?></p>
        <button id="generate-skus" class="button button-primary">
          <?php echo esc_html(__('Generate SKUs', 'sku-generator')); ?>
        </button>
        <div id="progress-bar" style="display: none;">
          <progress value="0" max="100"></progress>
          <span id="progress-text">0%</span>
        </div>
      </div>

      <div id="validate-content" class="tab-content" style="display: none;">
        <h2><?php echo esc_html(__('SKU Validation', 'sku-generator')); ?></h2>
        <p><?php echo esc_html(__('Check all product SKUs for validity and fix issues.', 'sku-generator')); ?></p>

        <div class="sku-validation-info">
          <h3><?php _e('WooCommerce SKU Requirements:', 'sku-generator'); ?></h3>
          <ul>
            <li><?php _e('Must be unique across all products', 'sku-generator'); ?></li>
            <li><?php _e('Only alphanumeric characters, hyphens (-), and underscores (_)', 'sku-generator'); ?></li>
            <li><?php _e('No spaces or special characters (@, #, %, etc.)', 'sku-generator'); ?></li>
            <li><?php _e('Maximum 100 characters', 'sku-generator'); ?></li>
            <li><?php _e('No leading or trailing whitespace', 'sku-generator'); ?></li>
          </ul>
        </div>

        <div class="sku-validation-actions">
          <button id="validate-skus" class="button button-secondary">
            <?php echo esc_html(__('Scan for Invalid SKUs', 'sku-generator')); ?>
          </button>
          <button id="fix-invalid-skus" class="button button-primary" style="display: none;">
            <?php echo esc_html(__('Fix Invalid SKUs', 'sku-generator')); ?>
          </button>
        </div>

        <div id="validation-progress" style="display: none;">
          <progress value="0" max="100"></progress>
          <span id="validation-progress-text">0%</span>
        </div>

        <div id="validation-results" style="display: none;">
          <h3><?php _e('Validation Results', 'sku-generator'); ?></h3>
          <div id="validation-summary"></div>
          <div id="invalid-skus-list"></div>
        </div>
      </div>
    </div>

    <style>
      .tab-content {
        padding: 20px 0;
      }

      .nav-tab-wrapper {
        margin-bottom: 20px;
      }

      .sku-validation-info {
        background: #f1f1f1;
        padding: 15px;
        border-left: 4px solid #0073aa;
        margin: 20px 0;
      }

      .sku-validation-info ul {
        margin: 10px 0 0 20px;
      }

      .sku-validation-actions {
        margin: 20px 0;
      }

      .sku-validation-actions button {
        margin-right: 10px;
      }

      #validation-progress {
        margin: 20px 0;
      }

      #validation-progress progress {
        width: 100%;
        height: 20px;
      }

      .invalid-sku-item {
        background: #fff;
        border: 1px solid #ddd;
        padding: 10px;
        margin: 5px 0;
        border-left: 4px solid #dc3232;
      }

      .invalid-sku-item strong {
        color: #dc3232;
      }

      .validation-summary {
        background: #fff;
        border: 1px solid #ddd;
        padding: 15px;
        margin: 15px 0;
      }

      .validation-summary.success {
        border-left: 4px solid #46b450;
      }

      .validation-summary.warning {
        border-left: 4px solid #ffb900;
      }
    </style>
<?php
  }

  public function enqueue_scripts($hook)
  {
    if ('woocommerce_page_sku-generator' !== $hook) {
      return;
    }

    wp_enqueue_script(
      'sku-generator',
      plugins_url('js/sku-generator.js', __FILE__),
      array('jquery'),
      '1.1.0',
      true
    );

    wp_localize_script('sku-generator', 'skuGeneratorAjax', array(
      'ajaxurl' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('sku_generator_nonce')
    ));
  }

  public function ajax_generate_bulk_skus()
  {
    check_ajax_referer('sku_generator_nonce', 'nonce');

    if (!current_user_can('manage_woocommerce')) {
      wp_send_json_error('Insufficient permissions');
      return;
    }

    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;

    // Get products without SKUs or with empty SKUs
    $query = new \WC_Product_Query(array(
      'limit' => $this->batch_size,
      'offset' => $offset,
      'orderby' => 'date',
      'order' => 'DESC',
      'status' => 'publish',
      'return' => 'objects',
      'sku' => '',  // This gets both empty and non-existent SKUs
      'meta_query' => array(
        'relation' => 'OR',
        array(
          'key' => '_sku',
          'value' => '',
          'compare' => '='
        ),
        array(
          'key' => '_sku',
          'compare' => 'NOT EXISTS'
        )
      )
    ));

    $products = $query->get_products();
    $total_products = count(wc_get_products(array(
      'limit' => -1,
      'return' => 'ids',
      'sku' => '',
      'meta_query' => array(
        'relation' => 'OR',
        array(
          'key' => '_sku',
          'value' => '',
          'compare' => '='
        ),
        array(
          'key' => '_sku',
          'compare' => 'NOT EXISTS'
        )
      )
    )));

    if (empty($products)) {
      wp_send_json_success(array(
        'complete' => true,
        'message' => __('All SKUs generated successfully!', 'sku-generator')
      ));
      return;
    }

    $options = get_option('sku_generator_options', array());
    $prefix = isset($options['prefix']) ? $options['prefix'] : '';
    $suffix = isset($options['suffix']) ? $options['suffix'] : '';

    foreach ($products as $product) {
      // Double check that the product doesn't already have a non-empty SKU
      $current_sku = $product->get_sku();
      if (empty($current_sku)) {
        $sku = $this->generate_unique_sku($prefix, $suffix, $product);
        $product->set_sku($sku);
        $product->save();
      }
    }

    $progress = min(100, round(($offset + $this->batch_size) / $total_products * 100));

    wp_send_json_success(array(
      'complete' => false,
      'offset' => $offset + $this->batch_size,
      'progress' => $progress,
      'total' => $total_products
    ));
  }

  public function ajax_validate_skus()
  {
    check_ajax_referer('sku_generator_nonce', 'nonce');

    if (!current_user_can('manage_woocommerce')) {
      wp_send_json_error('Insufficient permissions');
      return;
    }

    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;

    // Get all products with SKUs
    $query = new \WC_Product_Query(array(
      'limit' => $this->batch_size,
      'offset' => $offset,
      'status' => 'publish',
      'return' => 'objects',
      'meta_query' => array(
        array(
          'key' => '_sku',
          'value' => '',
          'compare' => '!='
        )
      )
    ));

    $products = $query->get_products();

    // Get total count for progress calculation
    if ($offset === 0) {
      $total_products = count(wc_get_products(array(
        'limit' => -1,
        'return' => 'ids',
        'meta_query' => array(
          array(
            'key' => '_sku',
            'value' => '',
            'compare' => '!='
          )
        )
      )));

      // Store total in transient for subsequent requests
      set_transient('sku_validation_total', $total_products, 300);
    } else {
      $total_products = get_transient('sku_validation_total') ?: 100;
    }

    $invalid_skus = get_transient('sku_validation_invalid') ?: array();
    $duplicate_skus = get_transient('sku_validation_duplicates') ?: array();
    $all_skus = get_transient('sku_validation_all_skus') ?: array();

    foreach ($products as $product) {
      $sku = $product->get_sku();
      $product_id = $product->get_id();
      $product_name = $product->get_name();

      // Track all SKUs for duplicate detection
      if (!isset($all_skus[$sku])) {
        $all_skus[$sku] = array();
      }
      $all_skus[$sku][] = array(
        'id' => $product_id,
        'name' => $product_name
      );

      // Validate SKU format
      $validation_result = $this->validate_sku_format($sku);
      if (!$validation_result['valid']) {
        $invalid_skus[] = array(
          'product_id' => $product_id,
          'product_name' => $product_name,
          'sku' => $sku,
          'issues' => $validation_result['issues']
        );
      }
    }

    // Check for duplicates in the current batch
    foreach ($all_skus as $sku => $products_with_sku) {
      if (count($products_with_sku) > 1) {
        $duplicate_skus[$sku] = $products_with_sku;
      }
    }

    // Store progress
    set_transient('sku_validation_invalid', $invalid_skus, 300);
    set_transient('sku_validation_duplicates', $duplicate_skus, 300);
    set_transient('sku_validation_all_skus', $all_skus, 300);

    $progress = min(100, round(($offset + $this->batch_size) / $total_products * 100));

    if (empty($products) || $progress >= 100) {
      // Validation complete
      $total_invalid = count($invalid_skus);
      $total_duplicates = count($duplicate_skus);

      wp_send_json_success(array(
        'complete' => true,
        'total_products' => $total_products,
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
  }

  public function ajax_fix_invalid_skus()
  {
    check_ajax_referer('sku_generator_nonce', 'nonce');

    if (!current_user_can('manage_woocommerce')) {
      wp_send_json_error('Insufficient permissions');
      return;
    }

    $invalid_skus = get_transient('sku_validation_invalid') ?: array();
    $duplicate_skus = get_transient('sku_validation_duplicates') ?: array();

    $fixed_count = 0;
    $options = get_option('sku_generator_options', array());
    $prefix = isset($options['prefix']) ? $options['prefix'] : '';
    $suffix = isset($options['suffix']) ? $options['suffix'] : '';

    // Fix invalid format SKUs
    foreach ($invalid_skus as $invalid_sku) {
      $product = wc_get_product($invalid_sku['product_id']);
      if ($product) {
        $new_sku = $this->generate_unique_sku($prefix, $suffix, $product);
        $product->set_sku($new_sku);
        $product->save();
        $fixed_count++;
      }
    }

    // Fix duplicate SKUs (keep first, regenerate others)
    foreach ($duplicate_skus as $sku => $products_with_sku) {
      // Skip the first product, fix the rest
      for ($i = 1; $i < count($products_with_sku); $i++) {
        $product = wc_get_product($products_with_sku[$i]['id']);
        if ($product) {
          $new_sku = $this->generate_unique_sku($prefix, $suffix, $product);
          $product->set_sku($new_sku);
          $product->save();
          $fixed_count++;
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
  }

  private function validate_sku_format($sku)
  {
    $issues = array();
    $valid = true;

    // Check if empty
    if (empty($sku)) {
      $issues[] = __('SKU is empty', 'sku-generator');
      $valid = false;
    }

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

    // Check for invalid characters (only allow alphanumeric, hyphens, underscores)
    if (!preg_match('/^[A-Za-z0-9_-]+$/', $sku)) {
      $issues[] = __('SKU contains invalid characters (only A-Z, a-z, 0-9, -, _ allowed)', 'sku-generator');
      $valid = false;
    }

    // Check for spaces
    if (strpos($sku, ' ') !== false) {
      $issues[] = __('SKU contains spaces', 'sku-generator');
      $valid = false;
    }

    return array(
      'valid' => $valid,
      'issues' => $issues
    );
  }

  private function generate_unique_sku($prefix, $suffix, $product = null)
  {
    $options = get_option('sku_generator_options');
    $pattern_type = isset($options['pattern_type']) ? $options['pattern_type'] : 'alphanumeric';
    $length = isset($options['pattern_length']) ? intval($options['pattern_length']) : 8;
    $separator = isset($options['separator']) ? $options['separator'] : '-';

    // Ensure separator is valid for SKU format
    if (!in_array($separator, array('-', '_'))) {
      $separator = '-';
    }

    // Build character set based on pattern type
    switch ($pattern_type) {
      case 'numeric':
        $chars = '0123456789';
        break;
      case 'alphabetic':
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        break;
      case 'custom':
        // You can add custom pattern logic here
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        break;
      default: // alphanumeric
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    }

    do {
      $sku_parts = array();

      // Add prefix if set (sanitize it)
      if (!empty($prefix)) {
        $clean_prefix = preg_replace('/[^A-Za-z0-9_-]/', '', $prefix);
        if (!empty($clean_prefix)) {
          $sku_parts[] = $clean_prefix;
        }
      }

      // Add category if enabled
      if (!empty($options['include_category']) && $options['include_category'] == '1' && $product) {
        $categories = get_the_terms($product->get_id(), 'product_cat');
        if ($categories && !is_wp_error($categories)) {
          $first_cat = reset($categories);
          $cat_chars = isset($options['category_chars']) ? intval($options['category_chars']) : 2;
          $cat_code = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($first_cat->slug));
          $cat_code = substr($cat_code, 0, $cat_chars);
          if (!empty($cat_code)) {
            $sku_parts[] = $cat_code;
          }
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

      // Add random part if product ID is not used or pattern type is not set to just use ID
      if (empty($options['include_product_id']) || $options['include_product_id'] != '1') {
        $random = substr(str_shuffle($chars), 0, $length);
        $sku_parts[] = $random;
      }

      // Add suffix if set (sanitize it)
      if (!empty($suffix)) {
        $clean_suffix = preg_replace('/[^A-Za-z0-9_-]/', '', $suffix);
        if (!empty($clean_suffix)) {
          $sku_parts[] = $clean_suffix;
        }
      }

      // Combine all parts with separator
      $sku = implode($separator, array_filter($sku_parts));

      // Final validation to ensure SKU meets requirements
      $validation = $this->validate_sku_format($sku);
      if (!$validation['valid']) {
        error_log('SKU Generator: Generated invalid SKU: ' . $sku . ' - Issues: ' . implode(', ', $validation['issues']));
        continue; // Try again
      }
    } while ($this->sku_exists($sku));

    return $sku;
  }

  private function sku_exists($sku)
  {
    return wc_get_product_id_by_sku($sku) !== 0;
  }
}

// Initialize the plugin
add_action('plugins_loaded', function () {
  if (class_exists('WooCommerce')) {
    new SKU_Generator();
  }
});
