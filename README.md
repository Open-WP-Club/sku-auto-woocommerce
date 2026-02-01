# WooCommerce SKU Automatics

A comprehensive WordPress plugin that automatically generates and manages SKUs for WooCommerce products with advanced validation, cleanup tools, variation support, and GTIN integration.

## Features

### SKU Generation

- Bulk SKU generation for products without SKUs
- **Automatic variation SKU generation** (Parent-1, Parent-2, etc.)
- Customizable SKU patterns with multiple components
- Permalink-based SKUs for human-readable identifiers
- HPOS compatible with automatic detection
- Batch processing to handle large product catalogs
- Progress tracking with visual indicators

### Variation Support

- Automatic SKU generation for product variations
- Sequential numbering based on parent SKU
- Format: `ParentSKU-1`, `ParentSKU-2`, `ParentSKU-3`, etc.
- Respects existing variation SKUs
- Bulk generation for all variations without SKUs

### Pattern Customization

- Flexible prefixes and suffixes
- Multiple pattern types (alphanumeric, numeric, alphabetic, custom)
- Product permalink integration for meaningful SKUs
- Category codes with configurable length
- Date stamps in multiple formats
- Product ID inclusion
- Custom separators (hyphens or underscores)

### Validation & Quality Control

- Comprehensive SKU validation against WooCommerce standards
- Duplicate detection across all products
- Format checking for invalid characters
- Automatic fixing of problematic SKUs
- Detailed reporting of validation results

### GTIN Integration

- Automatic GTIN copying when generating SKUs
- Bulk copy existing SKUs to GTIN fields
- Multi-plugin compatibility (supports 10+ barcode plugins)
- Smart field detection for existing GTIN fields

### Cleanup Tools

- Remove all SKUs from products
- Smart removal of generated SKUs only
- GTIN field cleanup
- Empty SKU cleanup
- Pattern-based detection for safe removal

### Debug & Analysis

- Comprehensive debugging tools
- System information reporting
- Product analysis and statistics
- HPOS detection and compatibility checking

## Installation

1. Upload the `woocommerce-sku-auto` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Access the plugin settings through **WooCommerce > SKU Automatics**

## Configuration

### Basic Settings

#### SKU Components

- **SKU Prefix**: Add a prefix to all generated SKUs (e.g., "PROD")
- **SKU Suffix**: Add a suffix to all generated SKUs (e.g., "2026")
- **Separator Character**: Choose between hyphen (-) or underscore (_)

#### Main SKU Pattern

- **Use Product Permalink**: Use product URL slug for readable SKUs
- **Pattern Type**: Alphanumeric, Numeric, Alphabetic, or Custom
- **Pattern Length**: 4-32 characters for random generation

#### Additional Components

- **Include Product ID**: Add the WooCommerce product ID
- **Include Category**: Add category code (1-5 characters)
- **Include Date**: Add timestamp in various formats (YYYYMMDD, YYMMDD, YYMM, YY)

#### Integration Options

- **Copy SKU to GTIN Field**: Automatically copy generated SKUs to barcode fields

## SKU Pattern Examples

### Traditional Random SKUs

```
Settings: Prefix "PROD", Suffix "2026", Separator "-"
Result: PROD-A7K9X2M8-2026
```

### Permalink-Based SKUs

```
Product: "Awesome Gaming Mouse Pro"
Permalink: awesome-gaming-mouse-pro
Result: PROD-awesome-gaming-mouse-pro-2026
```

### Category + Date SKUs

```
Settings: Category (2 chars), Date (YYMMDD), Product ID
Product: Electronics > Gaming Mouse (ID: 123)
Result: EL-260201-123
```

### Variable Product with Variations

```
Parent Product: "T-Shirt" with SKU "TSHIRT-001"
Variations:
  - Small Red:   TSHIRT-001-1
  - Medium Red:  TSHIRT-001-2
  - Large Red:   TSHIRT-001-3
  - Small Blue:  TSHIRT-001-4
  - Medium Blue: TSHIRT-001-5
```

### Full Combination

```
Settings: All components enabled
Result: PROD-EL-260201-awesome-gaming-mouse-pro-123-2026
```

## Usage Guide

### 1. Generate SKUs

1. Go to **WooCommerce > SKU Automatics > Generate SKUs**
2. Configure your pattern in the Settings tab
3. Click **"Start Generating SKUs"**
4. Monitor progress with the real-time progress bar
5. Variations are automatically generated with parent SKU + number

### 2. Generate Variation SKUs Only

1. Navigate to the **Generate SKUs** tab
2. Click **"Generate Variation SKUs"**
3. All variations without SKUs will receive sequential numbering

### 3. Validate Existing SKUs

1. Navigate to the **Validate SKUs** tab
2. Click **"Scan for Invalid SKUs"**
3. Review the detailed validation report
4. Use **"Fix Invalid SKUs"** to automatically correct issues

### 4. GTIN Integration

1. Enable **"Copy SKU to GTIN Field"** in settings
2. Use **"Copy Existing SKUs to GTIN"** for bulk operations
3. Compatible with popular barcode plugins

### 5. Cleanup Operations

1. Access the **Clean Up** tab
2. Choose from four cleanup options:
   - **Remove All SKUs**: Complete removal
   - **Remove Generated SKUs Only**: Smart pattern-based removal
   - **Remove All GTIN Fields**: Barcode field cleanup
   - **Remove Empty SKUs**: Database optimization

## GTIN Field Compatibility

The plugin automatically detects and works with these GTIN/barcode fields:

- WooCommerce core GTIN field (`_global_unique_id`) - WC 8.4+
- WP Marketing Robot (`_wpm_gtin_code`)
- YITH WooCommerce Barcodes (`_ywbc_barcode_value`)
- ThemeSense GTIN (`_ts_gtin`)
- WooCommerce UPC (`_woo_upc`)
- Generic fields (`_gtin`, `_upc`, `_ean`, `_isbn`, `_barcode`)

### Data Safety

- Unique SKU guarantee with conflict resolution
- Validation before saving ensures WooCommerce compliance
- Comprehensive backups recommended before cleanup operations
- Reversible operations where possible

## WooCommerce SKU Requirements

Generated SKUs automatically comply with WooCommerce standards:

- Unique across all products
- Only alphanumeric characters, hyphens (-), and underscores (_)
- No spaces or special characters
- Maximum 100 characters
- No leading or trailing whitespace

## Troubleshooting

### Debug Tools

Use the built-in debug functions to diagnose issues:

1. **Debug Products**: General system information
2. **Debug Generation**: Specific SKU generation analysis

### Common Issues

- **"Scanned 0 products"**: Check debug output for HPOS detection
- **Invalid SKUs**: Review pattern settings and character limits
- **Permission errors**: Ensure user has `manage_woocommerce` capability
- **Variations not generating**: Ensure parent product has a SKU first

## System Requirements

- WordPress: 6.7 or higher
- WooCommerce: 9.0 or higher (tested up to 9.6)
- PHP: 8.2 or higher
- MySQL: 5.6 or higher

## License

This plugin is licensed under the Apache License 2.0. See the LICENSE file for complete terms.

## Support

For support and feature requests, please visit [openwpclub.com](https://openwpclub.com).
