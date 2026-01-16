/* global jQuery */
(function ($) {
  'use strict';

  function applyPreview() {
    var $wrap = $('#twt-layout-preview');
    if (!$wrap.length) return;

    var primary = ($('input[name="primary"]').val() || '#111111');
    var background = ($('input[name="background"]').val() || '#ffffff');
    var card = ($('input[name="card"]').val() || '#ffffff');
    var text = ($('input[name="text"]').val() || '#111111');
    var border = ($('input[name="border"]').val() || '#e6e6e6');

    var fontFamily = ($('input[name="font_family"]').val() || 'inherit');
    var fontSize = parseInt($('input[name="font_size"]').val() || '16', 10);
    var h3Size = parseInt($('input[name="h3_size"]').val() || '16', 10);

    var radius = parseInt($('input[name="radius"]').val() || '12', 10);
    var cardPadding = parseInt($('input[name="card_padding"]').val() || '14', 10);
    var gap = parseInt($('input[name="gap"]').val() || '14', 10);
    var btnFontSize = parseInt($('input[name="btn_font_size"]').val() || '15', 10);

    $wrap.css({
      background: background,
      color: text,
      fontFamily: fontFamily,
      fontSize: fontSize + 'px'
    });

    $wrap.find('.twt-p-card').css({
      background: card,
      borderColor: border,
      borderRadius: radius + 'px',
      padding: cardPadding + 'px'
    });

    $wrap.find('.twt-p-kpi').css({
      borderColor: border,
      borderRadius: radius + 'px'
    });

    $wrap.find('h4').css({
      fontSize: h3Size + 'px'
    });

    $wrap.find('.twt-p-btn').css({
      background: primary,
      borderRadius: radius + 'px',
      fontSize: btnFontSize + 'px'
    });

    $wrap.css({ gap: gap + 'px' });
    $wrap.find('.twt-p-card + .twt-p-card').css({ marginTop: gap + 'px' });
  }

  $(function () {
    // color picker
    $('.twt-color').each(function () {
      var $el = $(this);
      if ($el.data('wpColorPicker')) return;
      $el.wpColorPicker({
        change: function () { setTimeout(applyPreview, 60); },
        clear: function () { setTimeout(applyPreview, 60); }
      });
    });

    $(document).on('input change', 'input, select, textarea', function () {
      applyPreview();
    });

    applyPreview();
  });

})(jQuery);
