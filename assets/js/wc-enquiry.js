jQuery(function ($) {
  var $modal = $('#wc-enquiry-modal');
  var $form = $('#wc-enquiry-form');
  var $feedback = $('#wc-enquiry-feedback');
  var $productName = $('#wc-enquiry-product-name');
  var $productIdField = $('#wc-enquiry-product-id');
  var $productUrlField = $('#wc-enquiry-product-url');

  // ---------- HELPERS ----------

  function openModal() {
    $modal.addClass('wc-enquiry-open').attr('aria-hidden', 'false');
    $('body').addClass('wc-enquiry-body-lock');
  }

  function closeModal() {
    $modal.removeClass('wc-enquiry-open').attr('aria-hidden', 'true');
    $('body').removeClass('wc-enquiry-body-lock');
  }

  function resetForm() {
    // Clear only visible fields; keep the hidden product fields set by captureProductData
    $form.find('input[type="text"], input[type="email"], textarea').val('');
    $feedback
      .hide()
      .text('')
      .removeClass('wc-enquiry-success wc-enquiry-error');
  }

  function captureProductData($btn) {
    // Product ID – taken from WC data attributes if possible
    var productId =
      $btn.data('product_id') ||
      $btn.data('productid') ||
      $btn.val() ||
      '';

    // Try to scope lookups to the product card where possible
    var $loopProduct = $btn.closest('.product');

    // Product name
    var productName =
      $btn.data('product_name') ||
      $loopProduct.find('.woocommerce-loop-product__title').text() ||
      $('h1.product_title').text() ||
      '';

    // Product URL:
    // 1. Product card link (archive / homepage)
    // 2. Alternative class some themes use
    // 3. Fallback: current page URL (single product)
    var productUrl =
      $loopProduct.find('a.woocommerce-LoopProduct-link').attr('href') ||
      $loopProduct.find('a.woocommerce-loop-product__link').attr('href') ||
      window.location.href;

    // NOTE: we deliberately DO NOT use $btn.attr('href') here
    // because that is often "/?add-to-cart=ID" which is not a clean product URL.

    $productName.text($.trim(productName));
    $productIdField.val(productId);
    $productUrlField.val(productUrl);
  }

  // ---------- OVERRIDE ADD TO CART ----------

  // Remove WooCommerce default handlers so it doesn't add to cart.
  $(document.body).off('click', 'a.add_to_cart_button');
  $(document.body).off('click', 'button.single_add_to_cart_button');

  // Our handler: ALWAYS open modal, NEVER add to cart.
  $(document.body).on(
    'click',
    'a.add_to_cart_button, button.single_add_to_cart_button',
    function (e) {
      e.preventDefault();
      e.stopImmediatePropagation();

      var $btn = $(this);

      captureProductData($btn);
      resetForm();
      openModal();

      return false;
    }
  );

  // ---------- CLOSE MODAL ----------

  $('#wc-enquiry-close').on('click', function () {
    closeModal();
  });

  // Click outside inner box closes modal.
  $(document).on('click', function (e) {
    if ($(e.target).is('#wc-enquiry-modal')) {
      closeModal();
    }
  });

  // Esc key closes modal.
  $(document).on('keyup', function (e) {
    if (e.key === 'Escape' && $modal.hasClass('wc-enquiry-open')) {
      closeModal();
    }
  });

  // ---------- SUBMIT FORM (AJAX) ----------

  $form.on('submit', function (e) {
    e.preventDefault();

    // Safety: if WCEnquiry is missing, just bail gracefully
    if (typeof WCEnquiry === 'undefined' || !WCEnquiry.ajax_url) {
      console.error('WCEnquiry is not defined – cannot submit enquiry.');
      return;
    }

    var data = $form.serialize();

    $.post(WCEnquiry.ajax_url, data)
      .done(function (response) {
        if (response && response.success) {
          // Show thank you message
          $feedback
            .removeClass('wc-enquiry-error')
            .addClass('wc-enquiry-success')
            .text(response.data || WCEnquiry.success_message)
            .show();

          // After a short delay, reload the page
          setTimeout(function () {
            window.location.reload();
          }, 2500); // 2.5 seconds so they can read the message
        } else {
          $feedback
            .removeClass('wc-enquiry-success')
            .addClass('wc-enquiry-error')
            .text((response && response.data) || WCEnquiry.error_message)
            .show();
        }
      })
      .fail(function () {
        $feedback
          .removeClass('wc-enquiry-success')
          .addClass('wc-enquiry-error')
          .text(WCEnquiry.error_message || 'Something went wrong. Please try again.')
          .show();
      });
  });
});
