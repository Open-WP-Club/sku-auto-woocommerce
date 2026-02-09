<?php

/**
 * Plugin Name: SKU Automatics for WooCommerce 
 * Description: Comprehensive SKU generation and management for WooCommerce with validation, cleanup tools, and GTIN integration
 * Version: 2.2.0
 * Author: openwpclub.com
 * Author URI: https://openwpclub.com
 * License: Apache-2.0
 * License URI: https://www.apache.org/licenses/LICENSE-2.0
 * Text Domain: sku-generator
 * Domain Path: /languages
 * Requires at least: 6.7
 * Requires PHP: 8.2
 * WC requires at least: 9.0
 * WC tested up to: 9.6
 * Requires Plugins: woocommerce
 */

defined('ABSPATH') || exit;

// Define plugin constants
define('SKU_GENERATOR_VERSION', '2.2.0');
define('SKU_GENERATOR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SKU_GENERATOR_PLUGIN_URL', plugin_dir_url(__FILE__));

// Check if WooCommerce is active
function sku_generator_check_woocommerce()
{
  if (!class_exists('WooCommerce')) {
    add_action('admin_notices', function () {
      echo '<div class="notice notice-error"><p>' .
        __('SKU Automatics for WooCommerce requires WooCommerce to be installed and active.', 'sku-generator') .
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

// Declare WooCommerce feature compatibility
$sku_generator_plugin_file = __FILE__;
add_action('before_woocommerce_init', function () use ($sku_generator_plugin_file) {
  if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
    // HPOS (High-Performance Order Storage) - custom orders tables
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_orders_tables', $sku_generator_plugin_file, true);
    // Block-based Cart and Checkout
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', $sku_generator_plugin_file, true);
    // Block-based Product Editor
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('product_block_editor', $sku_generator_plugin_file, true);
  }
});

// Add plugin action links
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
  $settings_link = '<a href="' . admin_url('admin.php?page=sku-generator') . '">' . __('Settings', 'sku-generator') . '</a>';
  array_unshift($links, $settings_link);
  return $links;
});

// Hook into WordPress
add_action('plugins_loaded', 'sku_generator_init');

// Activation hook
register_activation_hook(__FILE__, function () {
  // Set default options
  $default_options = [
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
    'copy_to_gtin' => '0',
    'use_permalink' => '0',
    'variation_sku_mode' => 'numeric',
  ];

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
