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
      __('SKU Automatics', 'sku-generator'),
      __('SKU Automatics', 'sku-generator'),
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
      <h1><?php echo esc_html(__('WooCommerce SKU Automatics', 'sku-generator')); ?></h1>

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
        <a href="#cleanup" class="nav-tab" id="cleanup-tab">
          <?php _e('Clean Up', 'sku-generator'); ?>
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
          
          <?php 
          $options = get_option('sku_generator_options', array());
          $copy_to_gtin = isset($options['copy_to_gtin']) ? $options['copy_to_gtin'] : '0';
          $use_permalink = isset($options['use_permalink']) ? $options['use_permalink'] : '0';
          
          if ($copy_to_gtin === '1') {
            echo '<div class="sku-info-box">';
            echo '<p><strong>' . __('GTIN Copy Enabled:', 'sku-generator') . '</strong> ' . __('Generated SKUs will automatically be copied to GTIN/UPC/EAN/ISBN fields.', 'sku-generator') . '</p>';
            echo '</div>';
          }
          
          if ($use_permalink === '1') {
            echo '<div class="sku-info-box">';
            echo '<p><strong>' . __('Permalink Mode Enabled:', 'sku-generator') . '</strong> ' . __('SKUs will be generated using product permalinks/slugs for better readability.', 'sku-generator') . '</p>';
            echo '<p>' . __('Example: A product with permalink "awesome-widget" will get SKU like "PRE-awesome-widget-SUF"', 'sku-generator') . '</p>';
            echo '</div>';
          }
          ?>
          
          <div class="sku-action-section">
            <button id="generate-skus" class="sku-button">
              <?php _e('Start Generating SKUs', 'sku-generator'); ?>
            </button>
            <button id="copy-skus-to-gtin" class="sku-button secondary">
              <?php _e('Copy Existing SKUs to GTIN', 'sku-generator'); ?>
            </button>
            <button id="debug-generation" class="sku-button" style="background: #666;">
              <?php _e('Debug Generation', 'sku-generator'); ?>
            </button>
            
            <div id="progress-container" class="sku-progress hidden">
              <progress value="0" max="100"></progress>
              <span id="progress-text" class="sku-progress-text">0%</span>
            </div>
            
            <div id="gtin-progress-container" class="sku-progress hidden">
              <progress value="0" max="100"></progress>
              <span id="gtin-progress-text" class="sku-progress-text">0%</span>
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

      <!-- Clean Up Tab -->
      <div id="cleanup-content" class="tab-content">
        <div class="sku-card">
          <h3><?php _e('Clean Up SKUs and GTIN Fields', 'sku-generator'); ?></h3>
          <p><?php _e('Remove SKUs and GTIN fields from your products. Use this if you want to start fresh or if you\'re not satisfied with the generated values.', 'sku-generator'); ?></p>
          
          <div class="sku-info-box" style="border-left-color: #d63638; background: #fff8f8;">
            <h4 style="color: #d63638;"><?php _e('⚠️ Warning', 'sku-generator'); ?></h4>
            <ul>
              <li><?php _e('These actions cannot be undone', 'sku-generator'); ?></li>
              <li><?php _e('Removing SKUs may affect inventory tracking and orders', 'sku-generator'); ?></li>
              <li><?php _e('Make sure to backup your database before proceeding', 'sku-generator'); ?></li>
              <li><?php _e('Consider exporting your products first', 'sku-generator'); ?></li>
            </ul>
          </div>

          <div class="sku-action-section">
            <div class="sku-actions">
              <button id="remove-all-skus" class="sku-button danger">
                <?php _e('Remove All SKUs', 'sku-generator'); ?>
              </button>
              <button id="remove-generated-skus" class="sku-button danger">
                <?php _e('Remove Generated SKUs Only', 'sku-generator'); ?>
              </button>
              <button id="remove-all-gtin" class="sku-button danger">
                <?php _e('Remove All GTIN Fields', 'sku-generator'); ?>
              </button>
              <button id="remove-empty-skus" class="sku-button secondary">
                <?php _e('Remove Empty SKUs', 'sku-generator'); ?>
              </button>
            </div>
            
            <div id="cleanup-progress-container" class="sku-progress hidden">
              <progress value="0" max="100"></progress>
              <span id="cleanup-progress-text" class="sku-progress-text">0%</span>
            </div>
          </div>

          <div class="sku-card" style="margin-top: 30px; background: #f8f9fa;">
            <h4><?php _e('Clean Up Options Explained:', 'sku-generator'); ?></h4>
            <div style="display: grid; gap: 15px; margin-top: 15px;">
              <div>
                <strong><?php _e('Remove All SKUs:', 'sku-generator'); ?></strong>
                <span><?php _e('Completely removes SKU values from all products (generated and manual)', 'sku-generator'); ?></span>
              </div>
              <div>
                <strong><?php _e('Remove Generated SKUs Only:', 'sku-generator'); ?></strong>
                <span><?php _e('Only removes SKUs that match your current generation pattern', 'sku-generator'); ?></span>
              </div>
              <div>
                <strong><?php _e('Remove All GTIN Fields:', 'sku-generator'); ?></strong>
                <span><?php _e('Removes GTIN/UPC/EAN/ISBN values from all products', 'sku-generator'); ?></span>
              </div>
              <div>
                <strong><?php _e('Remove Empty SKUs:', 'sku-generator'); ?></strong>
                <span><?php _e('Cleans up products that have empty or whitespace-only SKU values', 'sku-generator'); ?></span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php
  }
}