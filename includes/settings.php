<?php

/**
 * SKU Generator Settings
 *
 * Handles WordPress Settings API registration and sanitization.
 *
 * @package SKU_Generator
 * @since 2.0.0
 */

defined('ABSPATH') || exit;

class SKU_Generator_Settings
{
  public function __construct()
  {
    add_action('admin_init', [$this, 'register_settings']);
  }

  /**
   * Register plugin settings with WordPress Settings API
   *
   * @since 2.0.0
   */
  public function register_settings(): void
  {
    register_setting('sku_generator_options', 'sku_generator_options', [
      'type' => 'array',
      'sanitize_callback' => [$this, 'sanitize_options'],
      'default' => $this->get_default_options(),
    ]);

    add_settings_section(
      'sku_generator_main',
      __('SKU Generator Settings', 'sku-generator'),
      [$this, 'section_description'],
      'sku-generator'
    );

    // Basic Settings
    add_settings_field(
      'prefix',
      __('SKU Prefix', 'sku-generator'),
      [$this, 'prefix_field'],
      'sku-generator',
      'sku_generator_main'
    );

    add_settings_field(
      'suffix',
      __('SKU Suffix', 'sku-generator'),
      [$this, 'suffix_field'],
      'sku-generator',
      'sku_generator_main'
    );

    add_settings_field(
      'separator',
      __('Separator Character', 'sku-generator'),
      [$this, 'separator_field'],
      'sku-generator',
      'sku_generator_main'
    );

    // Pattern Settings
    add_settings_field(
      'use_permalink',
      __('Use Product Permalink', 'sku-generator'),
      [$this, 'use_permalink_field'],
      'sku-generator',
      'sku_generator_main'
    );

    add_settings_field(
      'pattern_type',
      __('SKU Pattern Type', 'sku-generator'),
      [$this, 'pattern_type_field'],
      'sku-generator',
      'sku_generator_main'
    );

    add_settings_field(
      'pattern_length',
      __('Pattern Length', 'sku-generator'),
      [$this, 'pattern_length_field'],
      'sku-generator',
      'sku_generator_main'
    );

    // Component Settings
    add_settings_field(
      'include_product_id',
      __('Include Product ID', 'sku-generator'),
      [$this, 'include_product_id_field'],
      'sku-generator',
      'sku_generator_main'
    );

    add_settings_field(
      'include_category',
      __('Include Category', 'sku-generator'),
      [$this, 'include_category_field'],
      'sku-generator',
      'sku_generator_main'
    );

    add_settings_field(
      'category_chars',
      __('Category Characters', 'sku-generator'),
      [$this, 'category_chars_field'],
      'sku-generator',
      'sku_generator_main'
    );

    add_settings_field(
      'include_date',
      __('Include Date', 'sku-generator'),
      [$this, 'include_date_field'],
      'sku-generator',
      'sku_generator_main'
    );

    add_settings_field(
      'date_format',
      __('Date Format', 'sku-generator'),
      [$this, 'date_format_field'],
      'sku-generator',
      'sku_generator_main'
    );

    add_settings_field(
      'copy_to_gtin',
      __('Copy SKU to GTIN Field', 'sku-generator'),
      [$this, 'copy_to_gtin_field'],
      'sku-generator',
      'sku_generator_main'
    );
  }

  /**
   * Get default plugin options
   *
   * @since 2.1.0
   * @return array<string, mixed>
   */
  public function get_default_options(): array
  {
    return [
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
    ];
  }

  /**
   * Render section description
   *
   * @since 2.0.0
   */
  public function section_description(): void
  {
    echo '<p>' . esc_html__('Configure how SKUs are generated for your products.', 'sku-generator') . '</p>';
  }

  /**
   * Sanitize plugin options
   *
   * @since 2.0.0
   * @param array<string, mixed> $input Raw input values
   * @return array<string, mixed> Sanitized values
   */
  public function sanitize_options(array $input): array
  {
    $sanitized = [];

    // Sanitize text fields
    $sanitized['prefix'] = $this->sanitize_sku_component($input['prefix'] ?? '');
    $sanitized['suffix'] = $this->sanitize_sku_component($input['suffix'] ?? '');

    // Sanitize separator (whitelist)
    $separator = $input['separator'] ?? '-';
    $sanitized['separator'] = in_array($separator, ['-', '_'], true) ? $separator : '-';

    // Sanitize pattern type (whitelist)
    $pattern_type = $input['pattern_type'] ?? 'alphanumeric';
    $allowed_patterns = ['alphanumeric', 'numeric', 'alphabetic', 'custom'];
    $sanitized['pattern_type'] = in_array($pattern_type, $allowed_patterns, true) ? $pattern_type : 'alphanumeric';

    // Sanitize pattern length (clamp to valid range)
    $length = (int) ($input['pattern_length'] ?? 8);
    $sanitized['pattern_length'] = max(4, min(32, $length));

    // Sanitize checkboxes (boolean as string)
    $sanitized['include_product_id'] = isset($input['include_product_id']) ? '1' : '0';
    $sanitized['include_category'] = isset($input['include_category']) ? '1' : '0';
    $sanitized['include_date'] = isset($input['include_date']) ? '1' : '0';
    $sanitized['copy_to_gtin'] = isset($input['copy_to_gtin']) ? '1' : '0';
    $sanitized['use_permalink'] = isset($input['use_permalink']) ? '1' : '0';

    // Sanitize category chars (clamp to valid range)
    $cat_chars = (int) ($input['category_chars'] ?? 2);
    $sanitized['category_chars'] = max(1, min(5, $cat_chars));

    // Sanitize date format (whitelist)
    $date_format = $input['date_format'] ?? 'Ymd';
    $allowed_formats = ['Ymd', 'ymd', 'ym', 'y'];
    $sanitized['date_format'] = in_array($date_format, $allowed_formats, true) ? $date_format : 'Ymd';

    return $sanitized;
  }

  /**
   * Sanitize a SKU component (prefix/suffix)
   *
   * @since 2.0.0
   * @param string $value Raw value
   * @return string Sanitized value
   */
  private function sanitize_sku_component(string $value): string
  {
    // Remove characters not allowed in SKUs
    $sanitized = preg_replace('/[^A-Za-z0-9_-]/', '', $value);
    // Limit length
    return substr($sanitized, 0, 20);
  }

  /**
   * Render prefix field
   *
   * @since 2.0.0
   */
  public function prefix_field(): void
  {
    $options = get_option('sku_generator_options', []);
    $prefix = $options['prefix'] ?? '';
?>
    <div class="sku-form-group">
      <input type="text" name="sku_generator_options[prefix]" value="<?php echo esc_attr($prefix); ?>" maxlength="20" />
      <p class="description"><?php _e('Add a prefix to the beginning of each SKU (only letters, numbers, - and _).', 'sku-generator'); ?></p>
    </div>
  <?php
  }

  /**
   * Render suffix field
   *
   * @since 2.0.0
   */
  public function suffix_field(): void
  {
    $options = get_option('sku_generator_options', []);
    $suffix = $options['suffix'] ?? '';
  ?>
    <div class="sku-form-group">
      <input type="text" name="sku_generator_options[suffix]" value="<?php echo esc_attr($suffix); ?>" maxlength="20" />
      <p class="description"><?php esc_html_e('Add a suffix to the end of each SKU (only letters, numbers, - and _).', 'sku-generator'); ?></p>
    </div>
  <?php
  }

  /**
   * Render separator field
   *
   * @since 2.0.0
   */
  public function separator_field(): void
  {
    $options = get_option('sku_generator_options', []);
    $separator = $options['separator'] ?? '-';
  ?>
    <div class="sku-form-group">
      <select name="sku_generator_options[separator]" id="sku_separator">
        <option value="-" <?php selected($separator, '-'); ?>><?php esc_html_e('Hyphen (-)', 'sku-generator'); ?></option>
        <option value="_" <?php selected($separator, '_'); ?>><?php esc_html_e('Underscore (_)', 'sku-generator'); ?></option>
      </select>
      <p class="description"><?php esc_html_e('Character to separate SKU parts.', 'sku-generator'); ?></p>
    </div>
  <?php
  }

  /**
   * Render use permalink field
   *
   * @since 2.0.0
   */
  public function use_permalink_field(): void
  {
    $options = get_option('sku_generator_options', []);
    $use_permalink = $options['use_permalink'] ?? '0';
  ?>
    <div class="sku-checkbox-group">
      <input type="checkbox" id="use_permalink" name="sku_generator_options[use_permalink]" value="1" <?php checked($use_permalink, '1'); ?> />
      <label for="use_permalink"><?php esc_html_e('Use product permalink (URL slug) as the main part of SKU', 'sku-generator'); ?></label>
    </div>
    <p class="description"><?php esc_html_e('When enabled, the product\'s permalink/slug will be used instead of random characters. Example: "awesome-widget" becomes "PRE-awesome-widget-SUF"', 'sku-generator'); ?></p>
  <?php
  }

  /**
   * Render pattern type field
   *
   * @since 2.0.0
   */
  public function pattern_type_field(): void
  {
    $options = get_option('sku_generator_options', []);
    $pattern_type = $options['pattern_type'] ?? 'alphanumeric';
  ?>
    <div class="sku-form-group">
      <select name="sku_generator_options[pattern_type]" id="sku_pattern_type">
        <option value="alphanumeric" <?php selected($pattern_type, 'alphanumeric'); ?>><?php esc_html_e('Alphanumeric (A-Z, 0-9)', 'sku-generator'); ?></option>
        <option value="numeric" <?php selected($pattern_type, 'numeric'); ?>><?php esc_html_e('Numeric Only (0-9)', 'sku-generator'); ?></option>
        <option value="alphabetic" <?php selected($pattern_type, 'alphabetic'); ?>><?php esc_html_e('Alphabetic Only (A-Z)', 'sku-generator'); ?></option>
        <option value="custom" <?php selected($pattern_type, 'custom'); ?>><?php esc_html_e('Custom Pattern', 'sku-generator'); ?></option>
      </select>
      <p class="description"><?php esc_html_e('Type of characters to use in the random part of the SKU (only used when permalink mode is disabled).', 'sku-generator'); ?></p>
    </div>
  <?php
  }

  /**
   * Render pattern length field
   *
   * @since 2.0.0
   */
  public function pattern_length_field(): void
  {
    $options = get_option('sku_generator_options', []);
    $length = $options['pattern_length'] ?? 8;
  ?>
    <div class="sku-form-group">
      <input type="number" min="4" max="32" name="sku_generator_options[pattern_length]" id="sku_pattern_length" value="<?php echo (int) $length; ?>" />
      <p class="description"><?php esc_html_e('Length of the random part of the SKU (4-32 characters, only used when permalink mode is disabled).', 'sku-generator'); ?></p>
    </div>
  <?php
  }

  /**
   * Render include product ID field
   *
   * @since 2.0.0
   */
  public function include_product_id_field(): void
  {
    $options = get_option('sku_generator_options', []);
    $include_product_id = $options['include_product_id'] ?? '0';
  ?>
    <div class="sku-checkbox-group">
      <input type="checkbox" id="include_product_id" name="sku_generator_options[include_product_id]" value="1" <?php checked($include_product_id, '1'); ?> />
      <label for="include_product_id"><?php esc_html_e('Include product ID in SKU', 'sku-generator'); ?></label>
    </div>
  <?php
  }

  /**
   * Render include category field
   *
   * @since 2.0.0
   */
  public function include_category_field(): void
  {
    $options = get_option('sku_generator_options', []);
    $include_category = $options['include_category'] ?? '0';
  ?>
    <div class="sku-checkbox-group">
      <input type="checkbox" id="include_category" name="sku_generator_options[include_category]" value="1" <?php checked($include_category, '1'); ?> />
      <label for="include_category"><?php esc_html_e('Include product category code in SKU', 'sku-generator'); ?></label>
    </div>
  <?php
  }

  /**
   * Render category chars field
   *
   * @since 2.0.0
   */
  public function category_chars_field(): void
  {
    $options = get_option('sku_generator_options', []);
    $category_chars = $options['category_chars'] ?? 2;
  ?>
    <div class="sku-form-group">
      <input type="number" min="1" max="5" name="sku_generator_options[category_chars]" id="sku_category_chars" value="<?php echo (int) $category_chars; ?>" />
      <p class="description"><?php esc_html_e('Number of characters to use from category name (1-5).', 'sku-generator'); ?></p>
    </div>
  <?php
  }

  /**
   * Render include date field
   *
   * @since 2.0.0
   */
  public function include_date_field(): void
  {
    $options = get_option('sku_generator_options', []);
    $include_date = $options['include_date'] ?? '0';
  ?>
    <div class="sku-checkbox-group">
      <input type="checkbox" id="include_date" name="sku_generator_options[include_date]" value="1" <?php checked($include_date, '1'); ?> />
      <label for="include_date"><?php esc_html_e('Include date in SKU', 'sku-generator'); ?></label>
    </div>
  <?php
  }

  /**
   * Render date format field
   *
   * @since 2.0.0
   */
  public function date_format_field(): void
  {
    $options = get_option('sku_generator_options', []);
    $date_format = $options['date_format'] ?? 'Ymd';
  ?>
    <div class="sku-form-group">
      <select name="sku_generator_options[date_format]" id="sku_date_format">
        <option value="Ymd" <?php selected($date_format, 'Ymd'); ?>><?php esc_html_e('YYYYMMDD', 'sku-generator'); ?></option>
        <option value="ymd" <?php selected($date_format, 'ymd'); ?>><?php esc_html_e('YYMMDD', 'sku-generator'); ?></option>
        <option value="ym" <?php selected($date_format, 'ym'); ?>><?php esc_html_e('YYMM', 'sku-generator'); ?></option>
        <option value="y" <?php selected($date_format, 'y'); ?>><?php esc_html_e('YY', 'sku-generator'); ?></option>
      </select>
      <p class="description"><?php esc_html_e('Date format to use when including dates in SKUs.', 'sku-generator'); ?></p>
    </div>
  <?php
  }

  /**
   * Render copy to GTIN field
   *
   * @since 2.0.0
   */
  public function copy_to_gtin_field(): void
  {
    $options = get_option('sku_generator_options', []);
    $copy_to_gtin = $options['copy_to_gtin'] ?? '0';
  ?>
    <div class="sku-checkbox-group">
      <input type="checkbox" id="copy_to_gtin" name="sku_generator_options[copy_to_gtin]" value="1" <?php checked($copy_to_gtin, '1'); ?> />
      <label for="copy_to_gtin"><?php esc_html_e('Copy generated SKU to GTIN, UPC, EAN, or ISBN field', 'sku-generator'); ?></label>
    </div>
    <p class="description"><?php esc_html_e('When enabled, the generated SKU will also be copied to the product\'s GTIN/UPC/EAN/ISBN field.', 'sku-generator'); ?></p>
<?php
  }
}
