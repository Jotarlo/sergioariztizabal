//Dismiss Getting Started Notice
jQuery(document).on('click', '.oum-getting-started-notice .notice-dismiss', function() {
    jQuery.ajax({
        url: ajaxurl,
        data: {
            action: 'oum_dismiss_getting_started_notice'
        }
    });
});

//Dismiss Update Notice
jQuery(document).on('click', '.oum-update-notice .notice-dismiss', function(e) {
    // Prevent WordPress default dismiss handler from interfering
    e.preventDefault();
    e.stopImmediatePropagation();
    
    var $notice = jQuery(this).closest('.oum-update-notice');
    var version = $notice.attr('data-version') || $notice.data('version') || '';
    
    if (!version) {
        console.error('Update notice: Version data missing');
        // Still allow WordPress to dismiss the notice visually
        return;
    }
    
    // Make AJAX call to save dismissed version
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'oum_dismiss_update_notice',
            version: version
        },
        success: function(response) {
            if (response && response.success) {
                // Option saved successfully
                // WordPress will handle hiding the notice via CSS
            } else {
                console.error('Failed to dismiss update notice:', response ? response.data : 'Unknown error');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error dismissing update notice:', error);
        }
    });
    
    // Manually hide the notice since we prevented default
    $notice.fadeOut(100, function() {
        jQuery(this).remove();
    });
});


jQuery(function($){
  // Color Picker
  if ( $.isFunction( $.fn.wpColorPicker ) ) {
    $( 'input.oum_colorpicker' ).wpColorPicker();
  }

  // Audio Uploader
  $('body').on('click', '.oum_upload_audio_button', function(e){
    e.preventDefault();

    const audio_uploader = wp.media({
        title: 'Custom audio',
        library : {
            type : 'audio'
        },
        button: {
            text: 'Use this audio'
        },
        multiple: false
    }).on('select', function() {
        const attachment = audio_uploader.state().get('selection').first().toJSON();
        const url = attachment.url;
        $('#oum_location_audio').val(url);
        $('#oum_location_audio_preview').addClass('has-audio');
        $('#oum_location_audio_preview').html(url + '<div onclick="oumRemoveAudioUpload()" class="remove-upload">&times;</div>');
    });

    audio_uploader.open();
  });

  // Icon Uploader
  $('body').on('click', '.oum_upload_icon_button', function(e){
    e.preventDefault();

    const icon_uploader = wp.media({
        title: 'Custom icon',
        library : {
            type : 'image'
        },
        button: {
            text: 'Use this image'
        },
        multiple: false
    }).on('select', function() {
        const attachment = icon_uploader.state().get('selection').first().toJSON();
        const url = attachment.url;
        $('#oum_marker_user_icon').val(url);
        $('#oum_marker_user_icon_preview').addClass('has-icon');
        $('#oum_marker_user_icon_preview').css("background-image", "url(" + url + ")");
        $('#oum_marker_user_icon_preview').next('input[type=radio]').prop('checked', true);
        $('#oum_marker_user_icon_preview').next('input[type=radio]').trigger('change');
    });
    
    icon_uploader.open();
  });

  // Multi-Categories Icon Uploader
  $('body').on('click', '.oum_upload_multicategories_icon_button', function(e){
    e.preventDefault();

    // Get the default icon URL from the preview's data-default attribute
    const $preview = $('#oum_marker_multicategories_icon_preview');
    const defaultIcon = $preview.data('default');

    const multicat_icon_uploader = wp.media({
        title: 'Multi-Categories Icon',
        library : {
            type : 'image'
        },
        button: {
            text: 'Use this image'
        },
        multiple: false
    }).on('select', function() {
        const attachment = multicat_icon_uploader.state().get('selection').first().toJSON();
        const url = attachment.url;
        $('#oum_marker_multicategories_icon').val(url);
        $preview.addClass('has-icon');
        $preview.css('background-image', 'url(' + url + ')');
        // Show remove button
        $('.oum_remove_multicategories_icon_button').show();
    });
    multicat_icon_uploader.open();
  });

  // Multi-Categories Icon Remove Button
  $('body').on('click', '.oum_remove_multicategories_icon_button', function(e){
    e.preventDefault();
    const $preview = $('#oum_marker_multicategories_icon_preview');
    const defaultIcon = $preview.data('default');
    // Clear the input
    $('#oum_marker_multicategories_icon').val('');
    // Reset the preview to default
    $preview.removeClass('has-icon');
    $preview.css('background-image', 'url(' + defaultIcon + ')');
    // Hide remove button
    $(this).hide();
  });

  // Export CSV
  $('body').on('click', '.oum_export_csv_button', function(e){
    e.preventDefault();

    var $button = $(this);
    var page = 1;
    var perPage = 200;
    var allRows = [];
    var csvHeader = null;
    var datetime = null;

    // Create simple export progress UI once.
    var $progressWrap = $('.oum-export-progress-wrap');
    if (!$progressWrap.length) {
      $progressWrap = $(
        '<div class="oum-export-progress-wrap" style="display:none; margin-top:10px;">' +
          '<progress class="oum-export-progress" value="0" max="100" style="width:320px;"></progress>' +
          '<div class="oum-export-progress-text" style="margin-top:6px;">Preparing export...</div>' +
        '</div>'
      );
      $button.after($progressWrap);
    }

    var $progressBar = $progressWrap.find('.oum-export-progress');
    var $progressText = $progressWrap.find('.oum-export-progress-text');

    $button.prop('disabled', true);
    $progressWrap.show();
    $progressText.text('Preparing export...');
    $progressBar.attr('value', 0).attr('max', 100);

    const download = function (data) {
      const blob = new Blob([data], { type: 'text/csv' });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.setAttribute('href', url);
      a.setAttribute('download', 'oum-locations_' + datetime + '.csv');
      a.click();
      window.URL.revokeObjectURL(url);
    };

    const escapeCsv = function (value) {
      // Keep CSV valid even with commas, quotes or line breaks.
      var stringValue = (value === null || typeof value === 'undefined') ? '' : String(value);
      return '"' + stringValue.replace(/"/g, '""') + '"';
    };

    const csvmaker = function (header, rows) {
      var csvRows = [];
      csvRows.push(header.map(escapeCsv).join(','));
      rows.forEach(function(row) {
        csvRows.push(row.map(escapeCsv).join(','));
      });
      return csvRows.join('\r\n');
    };

    const finalizeExport = function () {
      if (!csvHeader || allRows.length === 0) {
        alert('Something went wrong. Please see errors in console.');
        console.error('OUM: No public locations available to export.');
        $button.prop('disabled', false);
        $progressWrap.hide();
        return;
      }

      var csvdata = csvmaker(csvHeader, allRows);
      download(csvdata);

      $progressText.text('Export complete.');
      $button.prop('disabled', false);

      // Keep "complete" visible shortly for better UX, then hide.
      setTimeout(function() {
        $progressWrap.hide();
        $progressBar.attr('value', 0).attr('max', 100);
      }, 1200);
    };

    const fetchChunk = function () {
      jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'oum_csv_export',
          oum_location_nonce: oum_ajax.oum_location_nonce,
          page: page,
          per_page: perPage
        },
        success: function(response) {
          if (!response || !response.success || !response.data) {
            alert('Something went wrong. Please see errors in console.');
            console.error('OUM: Export response invalid.', response);
            $button.prop('disabled', false);
            $progressWrap.hide();
            return;
          }

          var locations = response.data.locations || [];
          var total = parseInt(response.data.total || 0, 10);
          var exported = parseInt(response.data.exported || 0, 10);
          var hasMore = !!response.data.has_more;

          if (!datetime) {
            datetime = response.data.datetime;
          }

          if (!csvHeader && locations.length > 0) {
            csvHeader = Object.keys(locations[0]);
          }

          locations.forEach(function(locationRow) {
            allRows.push(Object.values(locationRow));
          });

          if (total > 0) {
            $progressBar.attr('max', total);
            $progressBar.attr('value', Math.min(exported, total));
            $progressText.text('Exporting ' + Math.min(exported, total) + ' of ' + total + ' locations...');
          } else {
            $progressBar.attr('max', 100);
            $progressBar.attr('value', 0);
            $progressText.text('No published locations found.');
          }

          if (hasMore) {
            page++;
            fetchChunk();
          } else {
            finalizeExport();
          }
        },
        error: function(xhr, status, error) {
          alert('Something went wrong. Please try again.');
          console.error('OUM: AJAX export error:', status, error);
          $button.prop('disabled', false);
          $progressWrap.hide();
        }
      });
    };

    fetchChunk();
  });

  // Import CSV in chunks (same pattern as CSV export)
  $('body').on('click', '.oum_upload_csv_button', function(e){
    e.preventDefault();

    var button = $(this),
        $csvUpload = button.closest('.csv_upload'),
        $loading = $csvUpload.find('.oum-import-loading'),
        $progress = $loading.find('.oum-import-loading-progress');

    // Fallback if the loading markup is missing from the template.
    if (!$loading.length) {
      button.after(
        '<div class="oum-import-loading" aria-live="polite">' +
          '<span class="oum-spinner" aria-hidden="true"></span>' +
          '<span class="oum-import-loading-text">Be patient. This can take several minutes.</span>' +
          '<span class="oum-import-loading-progress"></span>' +
        '</div>'
      );
      $loading = button.next('.oum-import-loading');
      $progress = $loading.find('.oum-import-loading-progress');
    }

    var csv_uploader = wp.media({
        title: 'Upload CSV file',
        library : {
            type : 'file'
        },
        button: {
            text: 'Use this file'
        },
        multiple: false
    }).on('select', function() {
        var attachment = csv_uploader.state().get('selection').first().toJSON();
        var publish_immediately = $('#oum_csv_import_publish_immediately').is(':checked') ? 'on' : '';
        var page = 1;
        var perPage = 25;
        var importedTotal = 0;

        $loading.addClass('is-visible');
        $progress.text('');
        button.prop('disabled', true);

        var finishImport = function (successMessage) {
          $loading.removeClass('is-visible');
          button.prop('disabled', false);
          if (successMessage) {
            alert(successMessage);
          }
        };

        var showImportError = function (response) {
          finishImport();
          alert('Something went wrong. Please see errors in console.');
          if (response && response.data && Array.isArray(response.data)) {
            response.data.forEach(function(error) {
              console.error(error.code + ': ' + error.message);
            });
          }
        };

        var importChunk = function () {
          jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
              action: 'oum_csv_import',
              oum_location_nonce: oum_ajax.oum_location_nonce,
              url: attachment.url,
              oum_csv_import_publish_immediately: publish_immediately,
              page: page,
              per_page: perPage
            },
            success: function (response) {
              if (!response || !response.success) {
                showImportError(response);
                return;
              }

              var data = response.data || {};
              importedTotal += parseInt(data.imported || 0, 10);
              var processed = parseInt(data.processed || 0, 10);
              var total = parseInt(data.total || 0, 10);

              if (total > 0) {
                $progress.text(processed + ' / ' + total);
              }

              if (data.has_more) {
                page++;
                importChunk();
              } else {
                finishImport(importedTotal + ' Locations have been imported successfully.');
              }
            },
            error: function() {
              finishImport();
              alert('Something went wrong. Please try again.');
            }
          });
        };

        importChunk();
    })
    .open();
  });

  // Shortcode Display Auto Width
  jQuery(document).ready(function ($) {
    $('.shortcode-display').each(function() {
      var input = this;
      var span = document.createElement('span');
      span.style.visibility = 'hidden';
      span.style.whiteSpace = 'pre';
      span.style.font = getComputedStyle(input).font;
      document.body.appendChild(span);
      span.textContent = input.value;
      input.style.width = (span.offsetWidth + 16) + 'px'; // padding allowance
      span.remove();
    });
  });

});

function oumRemoveVideoUpload() {
    document.getElementById('oum_location_video').value = '';
    document.getElementById('oum_location_video_preview').classList.remove('has-video');
    document.getElementById('oum_location_video_preview').textContent = '';
}

function oumRemoveAudioUpload() {
    document.getElementById('oum_location_audio').value = '';
    document.getElementById('oum_location_audio_preview').classList.remove('has-audio');
    document.getElementById('oum_location_audio_preview').textContent = '';
}