/* global jQuery */
(function ($) {
  'use strict';

  /**
   * admin/assets/admin.js
   *
   * JS global do backoffice (genérico).
   * NÃO deve conter lógica do Form Builder (twt_form).
   *
   * O Form Builder vive em:
   *   admin/assets/form-builder.js
   */

  function initConfirmations() {
    // Ex: <a href="..." data-twt-confirm="Tens a certeza?">...</a>
    $(document).on('click', '[data-twt-confirm]', function (e) {
      var msg = $(this).attr('data-twt-confirm');
      if (msg && !window.confirm(msg)) {
        e.preventDefault();
        e.stopImmediatePropagation();
        return false;
      }
    });
  }

  function initAdminUx() {
    // Reserva para UX genérica do admin (tabs, toggles, etc.)
  }

  $(function () {
    initConfirmations();
    initAdminUx();
  });

})(jQuery);