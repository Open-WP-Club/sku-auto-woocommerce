jQuery(document).ready(function ($) {
  // Tab functionality
  $('.nav-tab').on('click', function (e) {
    e.preventDefault();
    
    // Update active tab
    $('.nav-tab').removeClass('nav-tab-active');
    $(this).addClass('nav-tab-active');
    
    // Show corresponding content
    $('.tab-content').hide();
    const target = $(this).attr('href');
    $(target + '-content').show();
  });

  // Generate SKUs functionality
  $("#generate-skus").on("click", function (e) {
    e.preventDefault();

    const $button = $(this);
    const $progress = $("#progress-bar");
    const $progressBar = $progress.find("progress");
    const $progressText = $("#progress-text");

    $button.prop("disabled", true);
    $progress.show();

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
              $button.prop("disabled", false);
              $progress.hide();
              alert(response.data.message);
            } else {
              $progressBar.val(response.data.progress);
              $progressText.text(response.data.progress + "%");
              // Continue with next batch
              generateSKUs(response.data.offset);
            }
          } else {
            alert("Error generating SKUs. Please try again.");
            $button.prop("disabled", false);
            $progress.hide();
          }
        },
        error: function () {
          alert("Error generating SKUs. Please try again.");
          $button.prop("disabled", false);
          $progress.hide();
        },
      });
    }

    generateSKUs();
  });

  // Validate SKUs functionality
  $("#validate-skus").on("click", function (e) {
    e.preventDefault();

    const $button = $(this);
    const $progress = $("#validation-progress");
    const $progressBar = $progress.find("progress");
    const $progressText = $("#validation-progress-text");
    const $results = $("#validation-results");
    const $fixButton = $("#fix-invalid-skus");

    $button.prop("disabled", true);
    $progress.show();
    $results.hide();
    $fixButton.hide();
    $progressBar.val(0);
    $progressText.text("0%");

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
              $progressText.text("100%");
              $button.prop("disabled", false);
              $progress.hide();
              
              displayValidationResults(response.data);
            } else {
              $progressBar.val(response.data.progress);
              $progressText.text(response.data.progress + "%");
              // Continue with next batch
              validateSKUs(response.data.offset);
            }
          } else {
            alert("Error validating SKUs. Please try again.");
            $button.prop("disabled", false);
            $progress.hide();
          }
        },
        error: function () {
          alert("Error validating SKUs. Please try again.");
          $button.prop("disabled", false);
          $progress.hide();
        },
      });
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
    $button.prop("disabled", true).text("Fixing...");

    $.ajax({
      url: skuGeneratorAjax.ajaxurl,
      type: "POST",
      data: {
        action: "fix_invalid_skus",
        nonce: skuGeneratorAjax.nonce,
      },
      success: function (response) {
        if (response.success) {
          alert(response.data.message);
          // Clear validation results
          $("#validation-results").hide();
          $button.hide();
        } else {
          alert("Error fixing SKUs. Please try again.");
        }
        $button.prop("disabled", false).text("Fix Invalid SKUs");
      },
      error: function () {
        alert("Error fixing SKUs. Please try again.");
        $button.prop("disabled", false).text("Fix Invalid SKUs");
      },
    });
  });

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
        <div class="validation-summary success">
          <h4>✅ All SKUs are valid!</h4>
          <p>Scanned ${data.total_products} products. No issues found.</p>
        </div>
      `;
    } else {
      const totalIssues = data.total_invalid + data.total_duplicates;
      summaryHtml = `
        <div class="validation-summary warning">
          <h4>⚠️ ${totalIssues} SKU issues found</h4>
          <p>Scanned ${data.total_products} products:</p>
          <ul>
            <li>${data.total_invalid} products with invalid SKU format</li>
            <li>${data.total_duplicates} sets of duplicate SKUs</li>
          </ul>
        </div>
      `;

      // Show fix button if there are issues
      $fixButton.show();
    }

    $summary.html(summaryHtml);

    // Create detailed list of invalid SKUs
    if (data.invalid_skus && data.invalid_skus.length > 0) {
      invalidListHtml += '<h4>Invalid SKU Formats:</h4>';
      data.invalid_skus.forEach(function(item) {
        invalidListHtml += `
          <div class="invalid-sku-item">
            <strong>Product ID ${item.product_id}:</strong> ${item.product_name}<br>
            <strong>Current SKU:</strong> "${item.sku}"<br>
            <strong>Issues:</strong> ${item.issues.join(', ')}
          </div>
        `;
      });
    }

    // Create detailed list of duplicate SKUs
    if (data.duplicate_skus && Object.keys(data.duplicate_skus).length > 0) {
      invalidListHtml += '<h4>Duplicate SKUs:</h4>';
      Object.keys(data.duplicate_skus).forEach(function(sku) {
        const products = data.duplicate_skus[sku];
        invalidListHtml += `
          <div class="invalid-sku-item">
            <strong>Duplicate SKU:</strong> "${sku}"<br>
            <strong>Found in ${products.length} products:</strong><br>
            <ul>
        `;
        products.forEach(function(product) {
          invalidListHtml += `<li>ID ${product.id}: ${product.name}</li>`;
        });
        invalidListHtml += '</ul></div>';
      });
    }

    $invalidList.html(invalidListHtml);
    $results.show();
  }
});