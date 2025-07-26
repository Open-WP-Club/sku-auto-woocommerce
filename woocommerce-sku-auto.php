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

// Define plugin constants
define('SKU_GENERATOR_VERSION', '1.1.0');
define('SKU_GENERATOR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SKU_GENERATOR_PLUGIN_URL', plugin_dir_url(__FILE__));

// Check if WooCommerce is active
function sku_generator_check_woocommerce()
{
  if (!class_exists('WooCommerce')) {
    add_action('admin_notices', function () {
      echo '<div class="notice notice-error"><p>' .
        __('SKU Generator requires WooCommerce to be installed and active.', 'sku-generator') .
        '</p></div>';
    });
    return false;
  }
  return true;
}

// Include required files
function sku_generator_include_files()
{
  require_once SKU_GENERATOR_PLUGIN_DIR . 'includes/admin.php';
  require_once SKU_GENERATOR_PLUGIN_DIR . 'includes/ajax/generation.php';
  require_once SKU_GENERATOR_PLUGIN_DIR . 'includes/ajax/validation.php';
  require_once SKU_GENERATOR_PLUGIN_DIR . 'includes/ajax/cleanup.php';
  require_once SKU_GENERATOR_PLUGIN_DIR . 'includes/ajax/gtin.php';
  require_once SKU_GENERATOR_PLUGIN_DIR . 'includes/ajax/debug.php';
  require_once SKU_GENERATOR_PLUGIN_DIR . 'includes/generator.php';
  require_once SKU_GENERATOR_PLUGIN_DIR . 'includes/validator.php';
  require_once SKU_GENERATOR_PLUGIN_DIR . 'includes/settings.php';
  require_once SKU_GENERATOR_PLUGIN_DIR . 'includes/helpers.php';
}

// Initialize the plugin
function sku_generator_init()
{
  if (!sku_generator_check_woocommerce()) {
    return;
  }

  sku_generator_include_files();

  // Initialize components
  new SKU_Generator_Admin();
  new SKU_Generator_Ajax_Generation();
  new SKU_Generator_Ajax_Validation();
  new SKU_Generator_Ajax_Cleanup();
  new SKU_Generator_Ajax_GTIN();
  new SKU_Generator_Ajax_Debug();
  new SKU_Generator_Settings();
}

// Hook into WordPress
add_action('plugins_loaded', 'sku_generator_init');

// Activation hook
register_activation_hook(__FILE__, function () {
  // Set default options
  $default_options = array(
    'prefix' => '',
    'suffix' => '',
    'pattern_type' => 'alphanumeric',
    'pattern_length' => 8,
    'separator' => '-',
    'include_product_id' => '0',
    'include_category' => '0',
    'category_chars' => 2,
    'include_date' => '0',
    'date_format' => 'Ymd',
    'copy_to_gtin' => '0'
  );

  if (!get_option('sku_generator_options')) {
    add_option('sku_generator_options', $default_options);
  }
});

// Deactivation hook
register_deactivation_hook(__FILE__, function () {
  // Clean up transients
  delete_transient('sku_validation_total');
  delete_transient('sku_validation_invalid');
  delete_transient('sku_validation_duplicates');
  delete_transient('sku_validation_all_skus');
});
