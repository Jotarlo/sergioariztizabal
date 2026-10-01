(function($) {

  $(function() {
    // marker icon selector
    $('.marker_icons input[type=radio]').on('change', function(e) {
      $('.marker_icons label').removeClass('checked');
      $(this).parent('label').addClass('checked');
    });

    function toggleCategoryTypePanels() {
      const categoryType = $('#oum_marker_category_type').val() || 'point';

      $('[data-oum-category-type-panel]').each(function() {
        const panel = $(this);
        const panelType = panel.attr('data-oum-category-type-panel');
        const isVisible = panelType === 'point'
          ? categoryType === 'point'
          : categoryType === 'polyline' || categoryType === 'polygon';

        panel.prop('hidden', !isVisible);
        panel.find('input, select, textarea, button').each(function() {
          const field = $(this);
          const wasDisabled = field.data('oumCategoryTypeWasDisabled') === true;

          field.prop('disabled', !isVisible || wasDisabled);
        });
      });
    }

    $('[data-oum-category-type-panel] input, [data-oum-category-type-panel] select, [data-oum-category-type-panel] textarea, [data-oum-category-type-panel] button').each(function() {
      const field = $(this);
      field.data('oumCategoryTypeWasDisabled', field.prop('disabled'));
    });

    $('#oum_marker_category_type').on('change', toggleCategoryTypePanels);
    toggleCategoryTypePanels();
  });

})(jQuery);
