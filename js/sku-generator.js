jQuery(document).ready(function ($) {
  // Tab functionality
  $('.nav-tab').on('click', function (e) {
    e.preventDefault();
    
    // Update active tab
    $('.nav-tab').removeClass('nav-tab-active');
    $(this).addClass('nav-tab-active');
    
    // Show corresponding content
    $('.tab-content').removeClass('active').hide();
    const target = $(this).attr('href');
    $(target + '-content').addClass('active').show();
  });

  // Generate SKUs functionality
  $("#generate-skus").on("click", function (e) {
    e.preventDefault();

    const $button = $(this);
    const $progress = $("#progress-container");
    const $progressBar = $progress.find("progress");
    const $progressText = $("#progress-text");

    // Disable button and show progress
    $button.prop("disabled", true).addClass('sku-loading');
    $progress.removeClass('hidden');
    $progressBar.val(0);
    $progressText.text('0%');

    function generateSKUs(offset = 0) {
      $.ajax({
        url: skuGeneratorAjax.ajaxurl,
        type: "POST",
        data: {
          action: "generate_bulk_skus",
          nonce: skuGeneratorAjax.nonce,
          offset: offset,
        },
        success: function (response) {
          if (response.success) {
            if (response.data.complete) {
              $progressBar.val(100);
              $progressText.text("100%");
              $button.prop("disabled", false).removeClass('sku-loading');
              $progress.addClass('hidden');
              showNotification(response.data.message, 'success');
            } else {
              $progressBar.val(response.data.progress);
              $progressText.text(response.data.progress + "%");
              generateSKUs(response.data.offset);
            }
          } else {
            showNotification("Error generating SKUs. Please try again.", 'error');
            resetGenerateButton();
          }
        },
        error: function () {
          showNotification("Error generating SKUs. Please try again.", 'error');
          resetGenerateButton();
        },
      });
    }

    function resetGenerateButton() {
      $button.prop("disabled", false).removeClass('sku-loading');
      $progress.addClass('hidden');
    }

    generateSKUs();
  });

  // Copy SKUs to GTIN functionality
  $("#copy-skus-to-gtin").on("click", function (e) {
    e.preventDefault();

    if (!confirm("This will copy all existing SKUs to GTIN fields. Continue?")) {
      return;
    }

    const $button = $(this);
    const $progress = $("#gtin-progress-container");
    const $progressBar = $progress.find("progress");
    const $progressText = $("#gtin-progress-text");

    // Disable button and show progress
    $button.prop("disabled", true).addClass('sku-loading');
    $progress.removeClass('hidden');
    $progressBar.val(0);
    $progressText.text('0%');

    function copySkusToGtin(offset = 0) {
      $.ajax({
        url: skuGeneratorAjax.ajaxurl,
        type: "POST",
        data: {
          action: "copy_skus_to_gtin",
          nonce: skuGeneratorAjax.nonce,
          offset: offset,
        },
        success: function (response) {
          if (response.success) {
            if (response.data.complete) {
              $progressBar.val(100);
              $progressText.text("100%");
              $button.prop("disabled", false).removeClass('sku-loading');
              $progress.addClass('hidden');
              showNotification(response.data.message, 'success');
            } else {
              $progressBar.val(response.data.progress);
              $progressText.text(response.data.progress + "%");
              copySkusToGtin(response.data.offset);
            }
          } else {
            showNotification("Error copying SKUs to GTIN. Please try again.", 'error');
            resetCopyButton();
          }
        },
        error: function () {
          showNotification("Error copying SKUs to GTIN. Please try again.", 'error');
          resetCopyButton();
        },
      });
    }

    function resetCopyButton() {
      $button.prop("disabled", false).removeClass('sku-loading');
      $progress.addClass('hidden');
    }

    copySkusToGtin();
  });

  // Cleanup functionality
  $("#remove-all-skus").on("click", function (e) {
    e.preventDefault();
    executeCleanupAction('remove_all_skus', 'This will remove ALL SKUs from ALL products. This action cannot be undone!', $(this));
  });

  $("#remove-generated-skus").on("click", function (e) {
    e.preventDefault();
    executeCleanupAction('remove_generated_skus', 'This will remove SKUs that match your generation pattern. Continue?', $(this));
  });

  $("#remove-all-gtin").on("click", function (e) {
    e.preventDefault();
    executeCleanupAction('remove_all_gtin', 'This will remove ALL GTIN/UPC/EAN/ISBN fields from ALL products. Continue?', $(this));
  });

  $("#remove-empty-skus").on("click", function (e) {
    e.preventDefault();
    executeCleanupAction('remove_empty_skus', 'This will clean up empty SKU fields. Continue?', $(this));
  });

  function executeCleanupAction(action, confirmMessage, $button) {
    if (!confirm(confirmMessage)) {
      return;
    }

    const $progress = $("#cleanup-progress-container");
    const $progressBar = $progress.find("progress");
    const $progressText = $("#cleanup-progress-text");
    const originalText = $button.text();

    // Disable button and show progress
    $button.prop("disabled", true).addClass('sku-loading').text("Processing...");
    $progress.removeClass('hidden');
    $progressBar.val(0);
    $progressText.text('0%');

    function performCleanup(offset = 0) {
      $.ajax({
        url: skuGeneratorAjax.ajaxurl,
        type: "POST",
        data: {
          action: action,
          nonce: skuGeneratorAjax.nonce,
          offset: offset,
        },
        success: function (response) {
          if (response.success) {
            if (response.data.complete) {
              $progressBar.val(100);
              $progressText.text("100%");
              $button.prop("disabled", false).removeClass('sku-loading').text(originalText);
              $progress.addClass('hidden');
              showNotification(response.data.message, 'success');
            } else {
              $progressBar.val(response.data.progress);
              $progressText.text(response.data.progress + "%");
              performCleanup(response.data.offset);
            }
          } else {
            showNotification("Error during cleanup. Please try again.", 'error');
            resetCleanupButton();
          }
        },
        error: function () {
          showNotification("Error during cleanup. Please try again.", 'error');
          resetCleanupButton();
        },
      });
    }

    function resetCleanupButton() {
      $button.prop("disabled", false).removeClass('sku-loading').text(originalText);
      $progress.addClass('hidden');
    }

    performCleanup();
  }

  // Validate SKUs functionality
  $("#validate-skus").on("click", function (e) {
    e.preventDefault();

    const $button = $(this);
    const $progress = $("#validation-progress-container");
    const $progressBar = $progress.find("progress");
    const $progressText = $("#validation-progress-text");
    const $results = $("#validation-results");
    const $fixButton = $("#fix-invalid-skus");

    // Reset UI
    $button.prop("disabled", true).addClass('sku-loading');
    $progress.removeClass('hidden');
    $results.addClass('hidden');
    $fixButton.addClass('hidden');
    $progressBar.val(0);
    $progressText.text('0%');

    function validateSKUs(offset = 0) {
      $.ajax({
        url: skuGeneratorAjax.ajaxurl,
        type: "POST",
        data: {
          action: "validate_skus",
          nonce: skuGeneratorAjax.nonce,
          offset: offset,
        },
        success: function (response) {
          if (response.success) {
            if (response.data.complete) {
              $progressBar.val(100);
              $progressText.text('100%');
              $button.prop("disabled", false).removeClass('sku-loading');
              $progress.addClass('hidden');
              
              displayValidationResults(response.data);
            } else {
              $progressBar.val(response.data.progress);
              $progressText.text(response.data.progress + "%");
              validateSKUs(response.data.offset);
            }
          } else {
            showNotification("Error validating SKUs. Please try again.", 'error');
            resetValidateButton();
          }
        },
        error: function () {
          showNotification("Error validating SKUs. Please try again.", 'error');
          resetValidateButton();
        },
      });
    }

    function resetValidateButton() {
      $button.prop("disabled", false).removeClass('sku-loading');
      $progress.addClass('hidden');
    }

    validateSKUs();
  });

  // Fix invalid SKUs functionality
  $("#fix-invalid-skus").on("click", function (e) {
    e.preventDefault();

    if (!confirm("This will regenerate all invalid SKUs. Are you sure you want to continue?")) {
      return;
    }

    const $button = $(this);
    const originalText = $button.text();
    
    $button.prop("disabled", true).addClass('sku-loading').text("Fixing...");

    $.ajax({
      url: skuGeneratorAjax.ajaxurl,
      type: "POST",
      data: {
        action: "fix_invalid_skus",
        nonce: skuGeneratorAjax.nonce,
      },
      success: function (response) {
        if (response.success) {
          showNotification(response.data.message, 'success');
          $("#validation-results").addClass('hidden');
          $button.addClass('hidden');
        } else {
          showNotification("Error fixing SKUs. Please try again.", 'error');
        }
        $button.prop("disabled", false).removeClass('sku-loading').text(originalText);
      },
      error: function () {
        showNotification("Error fixing SKUs. Please try again.", 'error');
        $button.prop("disabled", false).removeClass('sku-loading').text(originalText);
      },
    });
  });

  // Debug generation functionality
  $("#debug-generation").on("click", function (e) {
    e.preventDefault();

    const $button = $(this);
    const originalText = $button.text();
    
    $button.prop("disabled", true).text("Debugging...");

    $.ajax({
      url: skuGeneratorAjax.ajaxurl,
      type: "POST",
      data: {
        action: "debug_sku_generation",
        nonce: skuGeneratorAjax.nonce,
      },
      success: function (response) {
        if (response.success) {
          const data = response.data;
          let debugHtml = '<div style="background: #f0f0f0; padding: 15px; margin: 20px 0; border-radius: 4px; font-family: monospace; font-size: 12px;">';
          debugHtml += '<h4>SKU Generation Debug Information:</h4>';
          debugHtml += '<p><strong>HPOS Enabled:</strong> ' + (data.hpos_enabled ? 'Yes' : 'No') + '</p>';
          debugHtml += '<p><strong>Total Products:</strong> ' + data.raw_queries.total_products + '</p>';
          debugHtml += '<p><strong>Products Without SKUs:</strong> ' + data.raw_queries.products_without_skus + '</p>';
          debugHtml += '<p><strong>Found in Helper Query:</strong> ' + data.products_without_skus.length + '</p>';
          
          debugHtml += '<h5>Product Details:</h5>';
          debugHtml += '<table border="1" style="border-collapse: collapse; width: 100%;">';
          debugHtml += '<tr><th>ID</th><th>Name</th><th>SKU (DB)</th><th>SKU (WC)</th><th>Empty?</th><th>Should Generate?</th></tr>';
          
          data.detailed_product_check.forEach(function(product) {
            debugHtml += '<tr>';
            debugHtml += '<td>' + product.id + '</td>';
            debugHtml += '<td>' + product.name + '</td>';
            debugHtml += '<td>' + (product.sku_from_db || '[EMPTY]') + '</td>';
            debugHtml += '<td>' + (product.sku_from_wc || '[EMPTY]') + '</td>';
            debugHtml += '<td>' + product.sku_empty_check + '</td>';
            debugHtml += '<td>' + product.should_generate + '</td>';
            debugHtml += '</tr>';
          });
          
          debugHtml += '</table>';
          debugHtml += '<p><strong>Products Without SKUs (IDs):</strong> [' + data.products_without_skus.join(', ') + ']</p>';
          debugHtml += '</div>';
          
          // Show debug info
          $('#validation-results').html(debugHtml).removeClass('hidden');
        } else {
          showNotification("Debug failed: " + response.data, 'error');
        }
        $button.prop("disabled", false).text(originalText);
      },
      error: function () {
        showNotification("Debug request failed.", 'error');
        $button.prop("disabled", false).text(originalText);
      },
    });
  });

  // Debug products functionality
  $("#debug-products").on("click", function (e) {
    e.preventDefault();

    const $button = $(this);
    const originalText = $button.text();
    
    $button.prop("disabled", true).text("Debugging...");

    $.ajax({
      url: skuGeneratorAjax.ajaxurl,
      type: "POST",
      data: {
        action: "debug_products",
        nonce: skuGeneratorAjax.nonce,
      },
      success: function (response) {
        if (response.success) {
          const data = response.data;
          let debugHtml = '<div style="background: #f0f0f0; padding: 15px; margin: 20px 0; border-radius: 4px; font-family: monospace; font-size: 12px;">';
          debugHtml += '<h4>Debug Information:</h4>';
          debugHtml += '<p><strong>HPOS Enabled:</strong> ' + (data.hpos_enabled ? 'Yes' : 'No') + '</p>';
          
          if (data.hpos_enabled) {
            debugHtml += '<p><strong>Total Products (HPOS):</strong> ' + data.total_products_hpos + '</p>';
            debugHtml += '<p><strong>HPOS Tables Exist:</strong> ' + JSON.stringify(data.hpos_tables_exist) + '</p>';
          } else {
            debugHtml += '<p><strong>Total Products (Legacy):</strong> ' + data.total_products_legacy + '</p>';
          }
          
          debugHtml += '<h5>Sample Products (first 5):</h5>';
          debugHtml += '<table border="1" style="border-collapse: collapse; width: 100%;">';
          debugHtml += '<tr><th>ID</th><th>Name</th><th>SKU (DB)</th><th>SKU (WC)</th><th>Type</th></tr>';
          
          data.sample_products.forEach(function(product) {
            debugHtml += '<tr>';
            debugHtml += '<td>' + product.id + '</td>';
            debugHtml += '<td>' + product.name + '</td>';
            debugHtml += '<td>' + (product.sku_from_db || '[EMPTY]') + '</td>';
            debugHtml += '<td>' + (product.sku_from_wc || '[EMPTY]') + '</td>';
            debugHtml += '<td>' + product.type + '</td>';
            debugHtml += '</tr>';
          });
          
          debugHtml += '</table></div>';
          
          // Show debug info
          $('#validation-results').html(debugHtml).removeClass('hidden');
        } else {
          showNotification("Debug failed: " + response.data, 'error');
        }
        $button.prop("disabled", false).text(originalText);
      },
      error: function () {
        showNotification("Debug request failed.", 'error');
        $button.prop("disabled", false).text(originalText);
      },
    });
  });

  // Notification system
  function showNotification(message, type = 'info') {
    // Remove existing notifications
    $('.sku-notification').remove();
    
    const typeClass = type === 'error' ? 'notice-error' : 
                     type === 'success' ? 'notice-success' : 'notice-info';
    
    const $notification = $(`
      <div class="notice ${typeClass} is-dismissible sku-notification" style="margin: 20px 0;">
        <p>${message}</p>
        <button type="button" class="notice-dismiss">
          <span class="screen-reader-text">Dismiss this notice.</span>
        </button>
      </div>
    `);
    
    $('.sku-generator-wrap h1').after($notification);
    
    // Auto dismiss after 5 seconds for success messages
    if (type === 'success') {
      setTimeout(() => {
        $notification.fadeOut();
      }, 5000);
    }
    
    // Handle manual dismiss
    $notification.on('click', '.notice-dismiss', function() {
      $notification.fadeOut();
    });
  }

  function displayValidationResults(data) {
    const $results = $("#validation-results");
    const $summary = $("#validation-summary");
    const $invalidList = $("#invalid-skus-list");
    const $fixButton = $("#fix-invalid-skus");

    let summaryHtml = '';
    let invalidListHtml = '';

    // Create summary
    if (data.total_invalid === 0 && data.total_duplicates === 0) {
      summaryHtml = `
        <div class="sku-summary success">
          <h4>✅ All SKUs are valid!</h4>
          <p>Scanned ${data.total_products} products. No issues found.</p>
        </div>
      `;
    } else {
      const totalIssues = data.total_invalid + data.total_duplicates;
      summaryHtml = `
        <div class="sku-summary warning">
          <h4>⚠️ ${totalIssues} SKU issues found</h4>
          <p>Scanned ${data.total_products} products:</p>
          <ul>
            <li><strong>${data.total_invalid}</strong> products with invalid SKU format</li>
            <li><strong>${data.total_duplicates}</strong> sets of duplicate SKUs</li>
          </ul>
        </div>
      `;

      // Show fix button if there are issues
      $fixButton.removeClass('hidden');
    }

    $summary.html(summaryHtml);

    // Create detailed list of invalid SKUs
    if (data.invalid_skus && data.invalid_skus.length > 0) {
      invalidListHtml += '<h4>Invalid SKU Formats:</h4>';
      invalidListHtml += '<div class="sku-invalid-items">';
      
      data.invalid_skus.forEach(function(item) {
        invalidListHtml += `
          <div class="sku-invalid-item">
            <div class="product-info">Product ID ${item.product_id}: ${item.product_name}</div>
            <div><strong>Current SKU:</strong> <span class="sku-value">${item.sku}</span></div>
            <div class="issues"><strong>Issues:</strong> ${item.issues.join(', ')}</div>
          </div>
        `;
      });
      
      invalidListHtml += '</div>';
    }

    // Create detailed list of duplicate SKUs
    if (data.duplicate_skus && Object.keys(data.duplicate_skus).length > 0) {
      invalidListHtml += '<h4>Duplicate SKUs:</h4>';
      invalidListHtml += '<div class="sku-invalid-items">';
      
      Object.keys(data.duplicate_skus).forEach(function(sku) {
        const products = data.duplicate_skus[sku];
        invalidListHtml += `
          <div class="sku-invalid-item">
            <div class="product-info">Duplicate SKU: <span class="sku-value">${sku}</span></div>
            <div><strong>Found in ${products.length} products:</strong></div>
            <ul style="margin: 8px 0 0 20px;">
        `;
        products.forEach(function(product) {
          invalidListHtml += `<li>ID ${product.id}: ${product.name}</li>`;
        });
        invalidListHtml += '</ul></div>';
      });
      
      invalidListHtml += '</div>';
    }

    $invalidList.html(invalidListHtml);
    $results.removeClass('hidden');
  }
});