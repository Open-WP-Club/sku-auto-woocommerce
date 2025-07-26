<?php

defined('ABSPATH') || exit;

class SKU_Generator_Settings
{
  public function __construct()
  {
    add_action('admin_init', array($this, 'register_settings'));
  }

  public function register_settings()
  {
    register_setting('sku_generator_options', 'sku_generator_options', array(
      'sanitize_callback' => array($this, 'sanitize_options')
    ));

    add_settings_section(
      'sku_generator_main',
      __('SKU Generator Settings', 'sku-generator'),
      array($this, 'section_description'),
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

    add_settings_field(
      'separator',
      __('Separator Character', 'sku-generator'),
      array($this, 'separator_field'),
      'sku-generator',
      'sku_generator_main'
    );

    // Pattern Settings
    add_settings_field(
      'use_permalink',
      __('Use Product Permalink', 'sku-generator'),
      array($this, 'use_permalink_field'),
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

    // Component Settings
    add_settings_field(
      'include_product_id',
      __('Include Product ID', 'sku-generator'),
      array($this, 'include_product_id_field'),
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
      'copy_to_gtin',
      __('Copy SKU to GTIN Field', 'sku-generator'),
      array($this, 'copy_to_gtin_field'),
      'sku-generator',
      'sku_generator_main'
    );
  }

  public function section_description()
  {
    echo '<p>' . __('Configure how SKUs are generated for your products.', 'sku-generator') . '</p>';
  }

  public function sanitize_options($input)
  {
    $sanitized = array();

    // Sanitize text fields
    $sanitized['prefix'] = $this->sanitize_sku_component($input['prefix'] ?? '');
    $sanitized['suffix'] = $this->sanitize_sku_component($input['suffix'] ?? '');

    // Sanitize separator
    $separator = $input['separator'] ?? '-';
    $sanitized['separator'] = in_array($separator, array('-', '_')) ? $separator : '-';

    // Sanitize pattern type
    $pattern_type = $input['pattern_type'] ?? 'alphanumeric';
    $allowed_patterns = array('alphanumeric', 'numeric', 'alphabetic', 'custom');
    $sanitized['pattern_type'] = in_array($pattern_type, $allowed_patterns) ? $pattern_type : 'alphanumeric';

    // Sanitize pattern length
    $length = intval($input['pattern_length'] ?? 8);
    $sanitized['pattern_length'] = max(4, min(32, $length));

    // Sanitize checkboxes
    $sanitized['include_product_id'] = isset($input['include_product_id']) ? '1' : '0';
    $sanitized['include_category'] = isset($input['include_category']) ? '1' : '0';
    $sanitized['include_date'] = isset($input['include_date']) ? '1' : '0';
    $sanitized['copy_to_gtin'] = isset($input['copy_to_gtin']) ? '1' : '0';
    $sanitized['use_permalink'] = isset($input['use_permalink']) ? '1' : '0';

    // Sanitize category chars
    $cat_chars = intval($input['category_chars'] ?? 2);
    $sanitized['category_chars'] = max(1, min(5, $cat_chars));

    // Sanitize date format
    $date_format = $input['date_format'] ?? 'Ymd';
    $allowed_formats = array('Ymd', 'ymd', 'ym', 'y');
    $sanitized['date_format'] = in_array($date_format, $allowed_formats) ? $date_format : 'Ymd';

    return $sanitized;
  }

  private function sanitize_sku_component($value)
  {
    // Remove any characters that aren't allowed in SKUs
    $sanitized = preg_replace('/[^A-Za-z0-9_-]/', '', $value);
    // Limit length to prevent overly long SKUs
    return substr($sanitized, 0, 20);
  }

  public function prefix_field()
  {
    $options = get_option('sku_generator_options', array());
    $prefix = $options['prefix'] ?? '';
?>
    <div class="sku-form-group">
      <input type="text" name="sku_generator_options[prefix]" value="<?php echo esc_attr($prefix); ?>" maxlength="20" />
      <p class="description"><?php _e('Add a prefix to the beginning of each SKU (only letters, numbers, - and _).', 'sku-generator'); ?></p>
    </div>
  <?php
  }

  public function suffix_field()
  {
    $options = get_option('sku_generator_options', array());
    $suffix = $options['suffix'] ?? '';
  ?>
    <div class="sku-form-group">
      <input type="text" name="sku_generator_options[suffix]" value="<?php echo esc_attr($suffix); ?>" maxlength="20" />
      <p class="description"><?php _e('Add a suffix to the end of each SKU (only letters, numbers, - and _).', 'sku-generator'); ?></p>
    </div>
  <?php
  }

  public function separator_field()
  {
    $options = get_option('sku_generator_options', array());
    $separator = $options['separator'] ?? '-';
  ?>
    <div class="sku-form-group">
      <select name="sku_generator_options[separator]">
        <option value="-" <?php selected($separator, '-'); ?>><?php _e('Hyphen (-)', 'sku-generator'); ?></option>
        <option value="_" <?php selected($separator, '_'); ?>><?php _e('Underscore (_)', 'sku-generator'); ?></option>
      </select>
      <p class="description"><?php _e('Character to separate SKU parts.', 'sku-generator'); ?></p>
    </div>
  <?php
  }

  public function use_permalink_field()
  {
    $options = get_option('sku_generator_options', array());
    $use_permalink = $options['use_permalink'] ?? '0';
  ?>
    <div class="sku-checkbox-group">
      <input type="checkbox" id="use_permalink" name="sku_generator_options[use_permalink]" value="1" <?php checked($use_permalink, '1'); ?> />
      <label for="use_permalink"><?php _e('Use product permalink (URL slug) as the main part of SKU', 'sku-generator'); ?></label>
    </div>
    <p class="description"><?php _e('When enabled, the product\'s permalink/slug will be used instead of random characters. Example: "awesome-widget" becomes "PRE-awesome-widget-SUF"', 'sku-generator'); ?></p>
  <?php
  }

  public function pattern_type_field()
  {
    $options = get_option('sku_generator_options', array());
    $pattern_type = $options['pattern_type'] ?? 'alphanumeric';
  ?>
    <div class="sku-form-group">
      <select name="sku_generator_options[pattern_type]">
        <option value="alphanumeric" <?php selected($pattern_type, 'alphanumeric'); ?>><?php _e('Alphanumeric (A-Z, 0-9)', 'sku-generator'); ?></option>
        <option value="numeric" <?php selected($pattern_type, 'numeric'); ?>><?php _e('Numeric Only (0-9)', 'sku-generator'); ?></option>
        <option value="alphabetic" <?php selected($pattern_type, 'alphabetic'); ?>><?php _e('Alphabetic Only (A-Z)', 'sku-generator'); ?></option>
        <option value="custom" <?php selected($pattern_type, 'custom'); ?>><?php _e('Custom Pattern', 'sku-generator'); ?></option>
      </select>
      <p class="description"><?php _e('Type of characters to use in the random part of the SKU (only used when permalink mode is disabled).', 'sku-generator'); ?></p>
    </div>
  <?php
  }

  public function pattern_length_field()
  {
    $options = get_option('sku_generator_options', array());
    $length = $options['pattern_length'] ?? 8;
  ?>
    <div class="sku-form-group">
      <input type="number" min="4" max="32" name="sku_generator_options[pattern_length]" value="<?php echo intval($length); ?>" />
      <p class="description"><?php _e('Length of the random part of the SKU (4-32 characters, only used when permalink mode is disabled).', 'sku-generator'); ?></p>
    </div>
  <?php
  }

  public function include_product_id_field()
  {
    $options = get_option('sku_generator_options', array());
    $include_product_id = $options['include_product_id'] ?? '0';
  ?>
    <div class="sku-checkbox-group">
      <input type="checkbox" id="include_product_id" name="sku_generator_options[include_product_id]" value="1" <?php checked($include_product_id, '1'); ?> />
      <label for="include_product_id"><?php _e('Include product ID in SKU', 'sku-generator'); ?></label>
    </div>
  <?php
  }

  public function include_category_field()
  {
    $options = get_option('sku_generator_options', array());
    $include_category = $options['include_category'] ?? '0';
  ?>
    <div class="sku-checkbox-group">
      <input type="checkbox" id="include_category" name="sku_generator_options[include_category]" value="1" <?php checked($include_category, '1'); ?> />
      <label for="include_category"><?php _e('Include product category code in SKU', 'sku-generator'); ?></label>
    </div>
  <?php
  }

  public function category_chars_field()
  {
    $options = get_option('sku_generator_options', array());
    $category_chars = $options['category_chars'] ?? 2;
  ?>
    <div class="sku-form-group">
      <input type="number" min="1" max="5" name="sku_generator_options[category_chars]" value="<?php echo intval($category_chars); ?>" />
      <p class="description"><?php _e('Number of characters to use from category name (1-5).', 'sku-generator'); ?></p>
    </div>
  <?php
  }

  public function include_date_field()
  {
    $options = get_option('sku_generator_options', array());
    $include_date = $options['include_date'] ?? '0';
  ?>
    <div class="sku-checkbox-group">
      <input type="checkbox" id="include_date" name="sku_generator_options[include_date]" value="1" <?php checked($include_date, '1'); ?> />
      <label for="include_date"><?php _e('Include date in SKU', 'sku-generator'); ?></label>
    </div>
  <?php
  }

  public function date_format_field()
  {
    $options = get_option('sku_generator_options', array());
    $date_format = $options['date_format'] ?? 'Ymd';
  ?>
    <div class="sku-form-group">
      <select name="sku_generator_options[date_format]">
        <option value="Ymd" <?php selected($date_format, 'Ymd'); ?>><?php _e('YYYYMMDD', 'sku-generator'); ?></option>
        <option value="ymd" <?php selected($date_format, 'ymd'); ?>><?php _e('YYMMDD', 'sku-generator'); ?></option>
        <option value="ym" <?php selected($date_format, 'ym'); ?>><?php _e('YYMM', 'sku-generator'); ?></option>
        <option value="y" <?php selected($date_format, 'y'); ?>><?php _e('YY', 'sku-generator'); ?></option>
      </select>
      <p class="description"><?php _e('Date format to use when including dates in SKUs.', 'sku-generator'); ?></p>
    </div>
  <?php
  }

  public function copy_to_gtin_field()
  {
    $options = get_option('sku_generator_options', array());
    $copy_to_gtin = $options['copy_to_gtin'] ?? '0';
  ?>
    <div class="sku-checkbox-group">
      <input type="checkbox" id="copy_to_gtin" name="sku_generator_options[copy_to_gtin]" value="1" <?php checked($copy_to_gtin, '1'); ?> />
      <label for="copy_to_gtin"><?php _e('Copy generated SKU to GTIN, UPC, EAN, or ISBN field', 'sku-generator'); ?></label>
    </div>
    <p class="description"><?php _e('When enabled, the generated SKU will also be copied to the product\'s GTIN/UPC/EAN/ISBN field.', 'sku-generator'); ?></p>
<?php
  }
}
