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

    <?php $this->render_styles(); ?>
  <?php
  }

  private function render_styles()
  {
  ?>
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
}
