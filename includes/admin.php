<?php

defined('ABSPATH') || exit;

class SKU_Generator_Admin
{
  public function __construct()
  {
    add_action('admin_menu', array($this, 'add_admin_menu'));
    add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
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

  public function enqueue_scripts($hook)
  {
    if ('woocommerce_page_sku-generator' !== $hook) {
      return;
    }

    // Enqueue CSS
    wp_enqueue_style(
      'sku-generator-admin',
      SKU_GENERATOR_PLUGIN_URL . 'css/admin.css',
      array(),
      SKU_GENERATOR_VERSION
    );

    // Enqueue JavaScript
    wp_enqueue_script(
      'sku-generator',
      SKU_GENERATOR_PLUGIN_URL . 'js/sku-generator.js',
      array('jquery'),
      SKU_GENERATOR_VERSION,
      true
    );

    wp_localize_script('sku-generator', 'skuGeneratorAjax', array(
      'ajaxurl' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('sku_generator_nonce')
    ));
  }

  public function admin_page()
  {
    ?>
    <div class="wrap sku-generator-wrap">
      <h1><?php echo esc_html(__('SKU Generator', 'sku-generator')); ?></h1>

      <div class="sku-nav-tabs nav-tab-wrapper">
        <a href="#settings" class="nav-tab nav-tab-active" id="settings-tab">
          <?php _e('Settings', 'sku-generator'); ?>
        </a>
        <a href="#generate" class="nav-tab" id="generate-tab">
          <?php _e('Generate SKUs', 'sku-generator'); ?>
        </a>
        <a href="#validate" class="nav-tab" id="validate-tab">
          <?php _e('Validate SKUs', 'sku-generator'); ?>
        </a>
      </div>

      <!-- Settings Tab -->
      <div id="settings-content" class="tab-content active">
        <div class="sku-card">
          <h3><?php _e('Configuration', 'sku-generator'); ?></h3>
          <p><?php _e('Configure how SKUs are generated for your products.', 'sku-generator'); ?></p>
          
          <form method="post" action="options.php" class="sku-settings-form">
            <?php
            settings_fields('sku_generator_options');
            do_settings_sections('sku-generator');
            ?>
            <div class="sku-action-section">
              <?php submit_button(__('Save Settings', 'sku-generator'), 'sku-button', 'submit', false); ?>
            </div>
          </form>
        </div>
      </div>

      <!-- Generate Tab -->
      <div id="generate-content" class="tab-content">
        <div class="sku-card">
          <h3><?php _e('Bulk Generate SKUs', 'sku-generator'); ?></h3>
          <p><?php _e('Generate SKUs for all products that don\'t have one. This process will only add SKUs to products without them - existing SKUs will never be changed.', 'sku-generator'); ?></p>
          
          <div class="sku-action-section">
            <button id="generate-skus" class="sku-button">
              <?php _e('Start Generating SKUs', 'sku-generator'); ?>
            </button>
            
            <div id="progress-container" class="sku-progress hidden">
              <progress value="0" max="100"></progress>
              <span id="progress-text" class="sku-progress-text">0%</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Validate Tab -->
      <div id="validate-content" class="tab-content">
        <div class="sku-card">
          <h3><?php _e('SKU Validation', 'sku-generator'); ?></h3>
          <p><?php _e('Check all product SKUs for validity and fix issues automatically.', 'sku-generator'); ?></p>
          
          <div class="sku-info-box">
            <h4><?php _e('WooCommerce SKU Requirements:', 'sku-generator'); ?></h4>
            <ul>
              <li><?php _e('Must be unique across all products', 'sku-generator'); ?></li>
              <li><?php _e('Only alphanumeric characters, hyphens (-), and underscores (_)', 'sku-generator'); ?></li>
              <li><?php _e('No spaces or special characters (@, #, %, etc.)', 'sku-generator'); ?></li>
              <li><?php _e('Maximum 100 characters', 'sku-generator'); ?></li>
              <li><?php _e('No leading or trailing whitespace', 'sku-generator'); ?></li>
            </ul>
          </div>

          <div class="sku-action-section">
            <div class="sku-actions">
              <button id="validate-skus" class="sku-button secondary">
                <?php _e('Scan for Invalid SKUs', 'sku-generator'); ?>
              </button>
              <button id="fix-invalid-skus" class="sku-button danger hidden">
                <?php _e('Fix Invalid SKUs', 'sku-generator'); ?>
              </button>
              <button id="debug-products" class="sku-button" style="background: #666;">
                <?php _e('Debug Products', 'sku-generator'); ?>
              </button>
            </div>
            
            <div id="validation-progress-container" class="sku-progress hidden">
              <progress value="0" max="100"></progress>
              <span id="validation-progress-text" class="sku-progress-text">0%</span>
            </div>
          </div>

          <div id="validation-results" class="sku-results hidden">
            <div id="validation-summary"></div>
            <div id="invalid-skus-list"></div>
          </div>
        </div>
      </div>
    </div>
    <?php
  }

  // Remove the old render_styles method since we're using external CSS now
}