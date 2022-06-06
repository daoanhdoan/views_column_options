(function($) {
  Drupal.behaviors.viewsColumnOptions = {
    attach: function(context, settings) {
      $('.views-column-options').once().each(function () {
        new Sortable($(this)[0], {
          handle: ".views-column-options-item label",
          onUpdate: function(evt) {
            $(evt.from.children).each(function (index, item) {
              $(item).find('.item-weight').val(index);
            });
          }
        });
      });
    }
  }
})(jQuery);
