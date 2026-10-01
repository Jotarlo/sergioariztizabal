// Event: "Add Location"-Form send
jQuery('#oum_add_location').submit(function(event) {
  jQuery('#oum_submit_btn').addClass('oum-loading');
  
  event.preventDefault();
  // Persist the current map zoom with the submission.
  if (window.oumMap2 && typeof window.oumMap2.getZoom === 'function') {
    const zoomField = document.getElementById('oum_location_zoom');
    if (zoomField) {
      zoomField.value = window.oumMap2.getZoom();
    }
  }
  if (typeof OUMFormMap !== 'undefined' && typeof OUMFormMap.syncGeometryFields === 'function') {
    OUMFormMap.syncGeometryFields();
  }
  let formData = new FormData(this);

  // Process opening hours fields - validate and convert to JSON
  const openingHoursInputs = document.querySelectorAll('.oum-opening-hours-input');
  openingHoursInputs.forEach(function(input) {
    const fieldName = input.name;
    const fieldIndexMatch = fieldName.match(/\[(\d+)\]/);
    if (!fieldIndexMatch) {
      return;
    }
    const fieldIndex = fieldIndexMatch[1];
    const hoursValue = input.value.trim();

    // Remove the original field from formData
    formData.delete(fieldName);

    // Validate and convert to JSON (timezone will be added server-side using WordPress timezone)
    if (hoursValue !== '') {
      if (typeof OUMOpeningHours !== 'undefined' && OUMOpeningHours.validateFormat(hoursValue)) {
        // Just send the hours input - server will parse and add WordPress timezone
        formData.append(`oum_location_custom_fields[${fieldIndex}][hours]`, hoursValue);
      } else {
        oumShowError([{message: (typeof oum_opening_hours !== 'undefined' && oum_opening_hours.invalid_format) ? oum_opening_hours.invalid_format : 'Invalid opening hours format. Expected format: Mo 09:00-18:00 or Mo 9:00 AM-5:00 PM'}]);
        jQuery('#oum_submit_btn').removeClass('oum-loading');
        return false;
      }
    }
  });

  // Get all images (both existing and new) in their current order
  const previewContainer = document.getElementById('oum_location_images_preview');
  let imageOrder = [];

  // Only process images if the preview container exists
  if (previewContainer) {
    const previewItems = previewContainer.querySelectorAll('.image-preview-item');

    // Add existing and new images to formData in their current order
    previewItems.forEach((item, index) => {
      if (item.classList.contains('existing-image')) {
        // For existing images, get the URL from the hidden input
        const existingImageInput = item.querySelector('[name="existing_images[]"]');
        if (!existingImageInput) {
          return;
        }
        let imgUrl = existingImageInput.value;
        
        // Fix for handling URLs - extract just the path portion if it's a full URL
        if (imgUrl.startsWith('http')) {
          try {
            // Create a URL object to extract just the pathname
            const urlObj = new URL(imgUrl);
            imgUrl = urlObj.pathname; // This gives us just the path portion
          } catch (e) {
            // If URL parsing fails, keep the original value
            console.log('Could not parse URL:', imgUrl);
          }
        }
        
        formData.append('existing_images[]', imgUrl);
        imageOrder.push('existing:' + imgUrl);
      } else {
        // For new images, get the file from selectedFiles using the filename
        const fileName = item.dataset.fileName;
        const file = window.oumSelectedFiles.find(f => f.name === fileName);
        if (file) {
          formData.append('oum_location_images[]', file);
          imageOrder.push('new:' + fileName);
        }
      }
    });

    // Add the complete image order
    formData.append('image_order', JSON.stringify(imageOrder));
  }

  formData.append('action', 'oum_add_location_from_frontend');

  jQuery.ajax({
    type: 'POST',
    url: oum_ajax.ajaxurl,
    cache: false,
    contentType: false,
    processData: false,
    data: formData,
    success: function (response, textStatus, XMLHttpRequest) {
      jQuery('#oum_submit_btn').removeClass('oum-loading');

      if(response.success == false) {
        oumShowError(response.data);
      }
      if(response.success == true) {
        jQuery('#oum_add_location').trigger('reset');
        const deleteLocationInput = document.getElementById('oum_delete_location');
        const postIdInput = document.getElementById('oum_post_id');
        
        // Determine message type based on action
        if (deleteLocationInput && deleteLocationInput.value === 'true') {
            // For deletion
            OUMFormController.showFormMessage(
                'success',
                oum_custom_strings.location_deleted,
                oum_custom_strings.delete_success,
                oum_custom_strings.close_and_refresh,
                function() {
                    window.location.reload();
                }
            );
        } else if (postIdInput && postIdInput.value) {
            // For edits
            OUMFormController.showFormMessage(
                'success',
                oum_custom_strings.changes_saved,
                oum_custom_strings.changes_saved_message,
                oum_custom_strings.close_and_refresh,
                function() {
                    window.location.reload();
                }
            );
        } else {
            // For new locations
            if(typeof oum_action_after_submit !== 'undefined') {
                if(oum_action_after_submit === 'refresh') {
                    window.location.reload();
                } else if(oum_action_after_submit === 'redirect' && typeof thankyou_redirect !== 'undefined' && thankyou_redirect !== '') {
                    window.location.href = thankyou_redirect;
                } else {
                    // Show thank you message with refresh button (default)
                    const thankyouDiv = document.getElementById('oum_add_location_thankyou');
                    const thankyouHeadline = thankyouDiv?.querySelector('h3')?.textContent || oum_custom_strings.thank_you;
                    const thankyouText = thankyouDiv?.querySelector('.oum-add-location-thankyou-text')?.textContent || oum_custom_strings.thank_you_message;
                    
                    OUMFormController.showFormMessage(
                        'success',
                        thankyouHeadline,
                        thankyouText,
                        oum_custom_strings.close_and_refresh,
                        function() {
                            window.location.reload();
                        }
                    );
                }
            } else {
                // Fallback to thank you message with refresh button
                const thankyouDiv = document.getElementById('oum_add_location_thankyou');
                const thankyouHeadline = thankyouDiv?.querySelector('h3')?.textContent || oum_custom_strings.thank_you;
                const thankyouText = thankyouDiv?.querySelector('.oum-add-location-thankyou-text')?.textContent || oum_custom_strings.thank_you_message;
                
                OUMFormController.showFormMessage(
                    'success',
                    thankyouHeadline,
                    thankyouText,
                    oum_custom_strings.close_and_refresh,
                    function() {
                        window.location.reload();
                    }
                );
            }
        }
      }
    },
    error: function (XMLHttpRequest, textStatus, errorThrown) { 
      console.log(errorThrown);
    }
  });
});

function oumShowError(errors) {
  const errorWrapEl = jQuery('#oum_add_location_error');
  errorWrapEl.html('');
  errors.forEach(error => {
    errorWrapEl.append(error.message + '<br>');
  });
  errorWrapEl.show();
}