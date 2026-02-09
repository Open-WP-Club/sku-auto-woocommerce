<?php

/**
 * SKU Generator Admin Interface
 *
 * Handles admin menu registration and UI rendering.
 *
 * @package SKU_Generator
 * @since 2.0.0
 */

defined('ABSPATH') || exit;

class SKU_Generator_Admin
{
  public function __construct()
  {
    add_action('admin_menu', [$this, 'add_admin_menu']);
    add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
  }

  /**
   * Add plugin submenu under WooCommerce
   *
   * @since 2.0.0
   */
  public function add_admin_menu(): void
  {
    add_submenu_page(
      'woocommerce',
      __('SKU Automatics', 'sku-generator'),
      __('SKU Automatics', 'sku-generator'),
      'manage_woocommerce',
      'sku-generator',
      [$this, 'admin_page']
    );
  }

  /**
   * Enqueue admin scripts and styles
   *
   * @since 2.0.0
   * @param string $hook Current admin page hook
   */
  public function enqueue_scripts(string $hook): void
  {
    if ('woocommerce_page_sku-generator' !== $hook) {
      return;
    }

    // Enqueue CSS
    wp_enqueue_style(
      'sku-generator-admin',
      SKU_GENERATOR_PLUGIN_URL . 'css/admin.css',
      [],
      SKU_GENERATOR_VERSION
    );

    // Enqueue JavaScript
    wp_enqueue_script(
      'sku-generator',
      SKU_GENERATOR_PLUGIN_URL . 'js/sku-generator.js',
      ['jquery'],
      SKU_GENERATOR_VERSION,
      true
    );

    // Localize script with AJAX data and i18n strings
    wp_localize_script('sku-generator', 'skuGeneratorAjax', [
      'ajaxurl' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('sku_generator_nonce'),
      'i18n' => [
        'confirmGenerate' => __('This will generate SKUs for all products without one. Continue?', 'sku-generator'),
        'confirmRemoveAll' => __('This will remove ALL SKUs from all products. This cannot be undone. Are you sure?', 'sku-generator'),
        'confirmRemoveGenerated' => __('This will remove SKUs that match your current pattern. Continue?', 'sku-generator'),
        'confirmRemoveGTIN' => __('This will remove all GTIN/UPC/EAN/ISBN values. Continue?', 'sku-generator'),
        'generating' => __('Generating...', 'sku-generator'),
        'validating' => __('Validating...', 'sku-generator'),
        'complete' => __('Complete!', 'sku-generator'),
        'error' => __('An error occurred. Please try again.', 'sku-generator'),
      ],
    ]);
  }

  /**
   * Render admin page
   *
   * @since 2.0.0
   * @since 2.2.0 Added SKU statistics widget
   */
  public function admin_page(): void
  {
    $stats = SKU_Generator_Helpers::get_sku_statistics();
    ?>
    <div class="wrap sku-generator-wrap">
      <h1><?php echo esc_html__('SKU Automatics for WooCommerce', 'sku-generator'); ?></h1>

      <!-- SKU Statistics Widget -->
      <div class="sku-stats-widget">
        <div class="sku-stats-grid">
          <div class="sku-stat-card">
            <div class="sku-stat-icon dashicons dashicons-products"></div>
            <div class="sku-stat-content">
              <span class="sku-stat-number"><?php echo esc_html($stats['products_total']); ?></span>
              <span class="sku-stat-label"><?php esc_html_e('Products', 'sku-generator'); ?></span>
            </div>
            <div class="sku-stat-detail">
              <span class="sku-stat-good"><?php echo esc_html($stats['products_with_sku']); ?> <?php esc_html_e('with SKU', 'sku-generator'); ?></span>
              <span class="sku-stat-warning"><?php echo esc_html($stats['products_without_sku']); ?> <?php esc_html_e('missing', 'sku-generator'); ?></span>
            </div>
          </div>

          <div class="sku-stat-card">
            <div class="sku-stat-icon dashicons dashicons-image-filter"></div>
            <div class="sku-stat-content">
              <span class="sku-stat-number"><?php echo esc_html($stats['variations_total']); ?></span>
              <span class="sku-stat-label"><?php esc_html_e('Variations', 'sku-generator'); ?></span>
            </div>
            <div class="sku-stat-detail">
              <span class="sku-stat-good"><?php echo esc_html($stats['variations_with_sku']); ?> <?php esc_html_e('with SKU', 'sku-generator'); ?></span>
              <span class="sku-stat-warning"><?php echo esc_html($stats['variations_without_sku']); ?> <?php esc_html_e('missing', 'sku-generator'); ?></span>
            </div>
          </div>

          <div class="sku-stat-card sku-stat-coverage">
            <div class="sku-stat-icon dashicons dashicons-chart-pie"></div>
            <div class="sku-stat-content">
              <span class="sku-stat-number <?php echo $stats['coverage_percent'] >= 100 ? 'sku-stat-complete' : ''; ?>"><?php echo esc_html($stats['coverage_percent']); ?>%</span>
              <span class="sku-stat-label"><?php esc_html_e('SKU Coverage', 'sku-generator'); ?></span>
            </div>
            <div class="sku-stat-progress">
              <div class="sku-stat-progress-bar" style="width: <?php echo esc_attr($stats['coverage_percent']); ?>%"></div>
            </div>
          </div>
        </div>
      </div>

      <nav class="sku-nav-tabs nav-tab-wrapper" aria-label="<?php esc_attr_e('SKU Generator sections', 'sku-generator'); ?>">
        <a href="#settings" class="nav-tab nav-tab-active" id="settings-tab" role="tab" aria-selected="true" aria-controls="settings-content">
          <?php esc_html_e('Settings', 'sku-generator'); ?>
        </a>
        <a href="#generate" class="nav-tab" id="generate-tab" role="tab" aria-selected="false" aria-controls="generate-content">
          <?php esc_html_e('Generate SKUs', 'sku-generator'); ?>
        </a>
        <a href="#validate" class="nav-tab" id="validate-tab" role="tab" aria-selected="false" aria-controls="validate-content">
          <?php esc_html_e('Validate SKUs', 'sku-generator'); ?>
        </a>
        <a href="#cleanup" class="nav-tab" id="cleanup-tab" role="tab" aria-selected="false" aria-controls="cleanup-content">
          <?php esc_html_e('Clean Up', 'sku-generator'); ?>
        </a>
      </nav>

      <!-- Settings Tab -->
      <div id="settings-content" class="tab-content active" role="tabpanel" aria-labelledby="settings-tab">
        <div class="sku-card">
          <h2><?php esc_html_e('Configuration', 'sku-generator'); ?></h2>
          <p><?php esc_html_e('Configure how SKUs are generated for your products.', 'sku-generator'); ?></p>

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
      <div id="generate-content" class="tab-content" role="tabpanel" aria-labelledby="generate-tab">
        <div class="sku-card">
          <h2><?php esc_html_e('Bulk Generate SKUs', 'sku-generator'); ?></h2>
          <p><?php esc_html_e('Generate SKUs for all products that don\'t have one. This process will only add SKUs to products without them - existing SKUs will never be changed.', 'sku-generator'); ?></p>

          <?php
          $options = get_option('sku_generator_options', []);
          $copy_to_gtin = $options['copy_to_gtin'] ?? '0';
          $use_permalink = $options['use_permalink'] ?? '0';
          $variation_sku_mode = $options['variation_sku_mode'] ?? 'numeric';

          if ($copy_to_gtin === '1') :
          ?>
            <div class="sku-info-box" role="status">
              <p><strong><?php esc_html_e('GTIN Copy Enabled:', 'sku-generator'); ?></strong> <?php esc_html_e('Generated SKUs will automatically be copied to GTIN/UPC/EAN/ISBN fields.', 'sku-generator'); ?></p>
            </div>
          <?php endif; ?>

          <?php if ($use_permalink === '1') : ?>
            <div class="sku-info-box" role="status">
              <p><strong><?php esc_html_e('Permalink Mode Enabled:', 'sku-generator'); ?></strong> <?php esc_html_e('SKUs will be generated using product permalinks/slugs for better readability.', 'sku-generator'); ?></p>
              <p><?php esc_html_e('Example: A product with permalink "awesome-widget" will get SKU like "PRE-awesome-widget-SUF"', 'sku-generator'); ?></p>
            </div>
          <?php endif; ?>

          <div class="sku-info-box" role="status">
            <p><strong><?php esc_html_e('Variation SKUs:', 'sku-generator'); ?></strong> <?php esc_html_e('Variations automatically receive SKUs based on their parent product.', 'sku-generator'); ?></p>
            <?php if ($variation_sku_mode === 'attributes') : ?>
              <p><?php esc_html_e('Mode: Attribute-based (e.g., PROD-123-red-xl, PROD-123-blue-m)', 'sku-generator'); ?></p>
            <?php else : ?>
              <p><?php esc_html_e('Mode: Numeric (e.g., PROD-123-1, PROD-123-2, PROD-123-3)', 'sku-generator'); ?></p>
            <?php endif; ?>
          </div>

          <div class="sku-action-section">
            <button type="button" id="generate-skus" class="sku-button" aria-describedby="generate-description">
              <?php esc_html_e('Start Generating SKUs', 'sku-generator'); ?>
            </button>
            <button type="button" id="generate-variation-skus" class="sku-button secondary">
              <?php esc_html_e('Generate Variation SKUs', 'sku-generator'); ?>
            </button>
            <button type="button" id="copy-skus-to-gtin" class="sku-button secondary">
              <?php esc_html_e('Copy Existing SKUs to GTIN', 'sku-generator'); ?>
            </button>
            <button type="button" id="debug-generation" class="sku-button" style="background: #666;">
              <?php esc_html_e('Debug Generation', 'sku-generator'); ?>
            </button>
            <p id="generate-description" class="screen-reader-text"><?php esc_html_e('Generates SKUs for products without existing SKUs', 'sku-generator'); ?></p>

            <div id="progress-container" class="sku-progress hidden" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
              <progress value="0" max="100"></progress>
              <span id="progress-text" class="sku-progress-text" aria-live="polite">0%</span>
            </div>

            <div id="gtin-progress-container" class="sku-progress hidden" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
              <progress value="0" max="100"></progress>
              <span id="gtin-progress-text" class="sku-progress-text" aria-live="polite">0%</span>
            </div>

            <div id="variation-progress-container" class="sku-progress hidden" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
              <progress value="0" max="100"></progress>
              <span id="variation-progress-text" class="sku-progress-text" aria-live="polite">0%</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Validate Tab -->
      <div id="validate-content" class="tab-content" role="tabpanel" aria-labelledby="validate-tab">
        <div class="sku-card">
          <h2><?php esc_html_e('SKU Validation', 'sku-generator'); ?></h2>
          <p><?php esc_html_e('Check all product SKUs for validity and fix issues automatically.', 'sku-generator'); ?></p>

          <div class="sku-info-box">
            <h3><?php esc_html_e('WooCommerce SKU Requirements:', 'sku-generator'); ?></h3>
            <ul>
              <li><?php esc_html_e('Must be unique across all products', 'sku-generator'); ?></li>
              <li><?php esc_html_e('Only alphanumeric characters, hyphens (-), and underscores (_)', 'sku-generator'); ?></li>
              <li><?php esc_html_e('No spaces or special characters (@, #, %, etc.)', 'sku-generator'); ?></li>
              <li><?php esc_html_e('Maximum 100 characters', 'sku-generator'); ?></li>
              <li><?php esc_html_e('No leading or trailing whitespace', 'sku-generator'); ?></li>
            </ul>
          </div>

          <div class="sku-action-section">
            <div class="sku-actions">
              <button type="button" id="validate-skus" class="sku-button secondary">
                <?php esc_html_e('Scan for Invalid SKUs', 'sku-generator'); ?>
              </button>
              <button type="button" id="fix-invalid-skus" class="sku-button danger hidden">
                <?php esc_html_e('Fix Invalid SKUs', 'sku-generator'); ?>
              </button>
              <button type="button" id="debug-products" class="sku-button" style="background: #666;">
                <?php esc_html_e('Debug Products', 'sku-generator'); ?>
              </button>
            </div>

            <div id="validation-progress-container" class="sku-progress hidden" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
              <progress value="0" max="100"></progress>
              <span id="validation-progress-text" class="sku-progress-text" aria-live="polite">0%</span>
            </div>
          </div>

          <div id="validation-results" class="sku-results hidden" aria-live="polite">
            <div id="validation-summary"></div>
            <div id="invalid-skus-list"></div>
          </div>
        </div>
      </div>

      <!-- Clean Up Tab -->
      <div id="cleanup-content" class="tab-content" role="tabpanel" aria-labelledby="cleanup-tab">
        <div class="sku-card">
          <h2><?php esc_html_e('Clean Up SKUs and GTIN Fields', 'sku-generator'); ?></h2>
          <p><?php esc_html_e('Remove SKUs and GTIN fields from your products. Use this if you want to start fresh or if you\'re not satisfied with the generated values.', 'sku-generator'); ?></p>

          <div class="sku-info-box sku-warning-box" role="alert">
            <h3><?php esc_html_e('Warning', 'sku-generator'); ?></h3>
            <ul>
              <li><?php esc_html_e('These actions cannot be undone', 'sku-generator'); ?></li>
              <li><?php esc_html_e('Removing SKUs may affect inventory tracking and orders', 'sku-generator'); ?></li>
              <li><?php esc_html_e('Make sure to backup your database before proceeding', 'sku-generator'); ?></li>
              <li><?php esc_html_e('Consider exporting your products first', 'sku-generator'); ?></li>
            </ul>
          </div>

          <div class="sku-action-section">
            <div class="sku-actions">
              <button type="button" id="remove-all-skus" class="sku-button danger">
                <?php esc_html_e('Remove All SKUs', 'sku-generator'); ?>
              </button>
              <button type="button" id="remove-generated-skus" class="sku-button danger">
                <?php esc_html_e('Remove Generated SKUs Only', 'sku-generator'); ?>
              </button>
              <button type="button" id="remove-all-gtin" class="sku-button danger">
                <?php esc_html_e('Remove All GTIN Fields', 'sku-generator'); ?>
              </button>
              <button type="button" id="remove-empty-skus" class="sku-button secondary">
                <?php esc_html_e('Remove Empty SKUs', 'sku-generator'); ?>
              </button>
            </div>

            <div id="cleanup-progress-container" class="sku-progress hidden" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
              <progress value="0" max="100"></progress>
              <span id="cleanup-progress-text" class="sku-progress-text" aria-live="polite">0%</span>
            </div>
          </div>

          <div class="sku-card sku-explainer-card">
            <h3><?php esc_html_e('Clean Up Options Explained:', 'sku-generator'); ?></h3>
            <dl class="sku-definitions">
              <dt><?php esc_html_e('Remove All SKUs:', 'sku-generator'); ?></dt>
              <dd><?php esc_html_e('Completely removes SKU values from all products (generated and manual)', 'sku-generator'); ?></dd>

              <dt><?php esc_html_e('Remove Generated SKUs Only:', 'sku-generator'); ?></dt>
              <dd><?php esc_html_e('Only removes SKUs that match your current generation pattern', 'sku-generator'); ?></dd>

              <dt><?php esc_html_e('Remove All GTIN Fields:', 'sku-generator'); ?></dt>
              <dd><?php esc_html_e('Removes GTIN/UPC/EAN/ISBN values from all products', 'sku-generator'); ?></dd>

              <dt><?php esc_html_e('Remove Empty SKUs:', 'sku-generator'); ?></dt>
              <dd><?php esc_html_e('Cleans up products that have empty or whitespace-only SKU values', 'sku-generator'); ?></dd>
            </dl>
          </div>
        </div>
      </div>
    </div>
    <?php
  }
}