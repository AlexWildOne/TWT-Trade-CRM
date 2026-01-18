/* global jQuery */
(function ($) {
  'use strict';

  function storageKey(formId, userId) {
    return 'twt_tcrm_draft_' + String(userId || '0') + '_' + String(formId || '0');
  }

  function getUserId() {
    var v = $('.twt-tcrm-form').attr('data-user-id');
    return v ? String(v) : '0';
  }

  function getFormId() {
    var v = $('.twt-tcrm-form').attr('data-form-id');
    return v ? String(v) : '0';
  }

  function cssEscape(s) {
    if (window.CSS && typeof window.CSS.escape === 'function') {
      return window.CSS.escape(s);
    }
    return String(s).replace(/["\\]/g, '\\$&');
  }

  function serializeForm($form) {
    var data = {};

    $form.find('input, textarea, select').each(function () {
      var $el = $(this);
      var name = $el.attr('name');
      if (!name) return;

      if (name === 'action' || name === 'twt_form_id' || name === '_wp_http_referer') return;
      if (name.indexOf('twt_tcrm_nonce') !== -1) return;

      var type = ($el.attr('type') || '').toLowerCase();
      if (type === 'hidden') return;

      if (type === 'checkbox') {
        data[name] = $el.is(':checked') ? '1' : '0';
        return;
      }

      if (type === 'radio') {
        if ($el.is(':checked')) {
          data[name] = $el.val();
        }
        return;
      }

      data[name] = $el.val();
    });

    data._savedAt = Date.now();
    return data;
  }

  function applyDraft($form, draft) {
    if (!draft || typeof draft !== 'object') return;

    Object.keys(draft).forEach(function (name) {
      if (name === '_savedAt') return;

      var $els = $form.find('[name="' + cssEscape(name) + '"]');
      if (!$els.length) return;

      var $first = $els.eq(0);
      var type = ($first.attr('type') || '').toLowerCase();

      if (type === 'checkbox') {
        $first.prop('checked', draft[name] === '1');
        return;
      }

      if (type === 'radio') {
        $els.each(function () {
          var $r = $(this);
          $r.prop('checked', String($r.val()) === String(draft[name]));
        });
        return;
      }

      $first.val(draft[name]);
    });
  }

  function saveDraft($form) {
    try {
      var key = storageKey(getFormId(), getUserId());
      var payload = serializeForm($form);
      localStorage.setItem(key, JSON.stringify(payload));
      showHint($form, 'Rascunho guardado');
    } catch (e) {}
  }

  function loadDraft($form) {
    try {
      var key = storageKey(getFormId(), getUserId());
      var raw = localStorage.getItem(key);
      if (!raw) return;

      var draft = JSON.parse(raw);
      if (!draft || typeof draft !== 'object') return;

      var filled = 0;
      $form.find('input[name^="twt_q["], textarea[name^="twt_q["], select[name^="twt_q["]').each(function () {
        var $el = $(this);
        var t = ($el.attr('type') || '').toLowerCase();
        if (t === 'checkbox' || t === 'radio' || t === 'hidden') return;

        var v = ($el.val() || '').toString().trim();
        if (v) filled++;
      });

      if (filled === 0) {
        applyDraft($form, draft);
        showHint($form, 'Rascunho restaurado');
      }
    } catch (e) {}
  }

  function clearDraft($form) {
    try {
      var key = storageKey(getFormId(), getUserId());
      localStorage.removeItem(key);
    } catch (e) {}
  }

  function showHint($form, text) {
    var $box = $form.find('.twt-tcrm-hint');
    if (!$box.length) {
      $box = $('<div class="twt-tcrm-hint" role="status"></div>');
      $box.css({
        marginTop: '10px',
        fontSize: '13px',
        opacity: '0.8'
      });
      $form.append($box);
    }
    $box.text(text);
    $box.stop(true, true).fadeIn(120);
    setTimeout(function () {
      $box.fadeOut(220);
    }, 1200);
  }

  function validateRequired($scope) {
    var ok = true;
    var firstBad = null;

    $scope.find('[required]').each(function () {
      var $el = $(this);
      if ($el.is(':disabled')) return;

      var type = ($el.attr('type') || '').toLowerCase();

      if (type === 'checkbox') {
        if (!$el.is(':checked')) {
          ok = false;
          firstBad = firstBad || $el;
        }
        return;
      }

      if (type === 'radio') {
        var name = $el.attr('name');
        if (!name) return;
        var anyChecked = $scope.find('input[type="radio"][name="' + cssEscape(name) + '"]:checked').length > 0;
        if (!anyChecked) {
          ok = false;
          firstBad = firstBad || $el;
        }
        return;
      }

      var v = ($el.val() || '').toString().trim();
      if (!v) {
        ok = false;
        firstBad = firstBad || $el;
      }
    });

    if (!ok && firstBad) {
      try {
        firstBad[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
      } catch (e) {}
      firstBad.focus();
    }

    return ok;
  }

  function bindAutosave($form) {
    var t = null;
    $form.on('input change', 'input[name^="twt_q["], textarea[name^="twt_q["], select[name^="twt_q["]', function () {
      if (t) clearTimeout(t);
      t = setTimeout(function () {
        saveDraft($form);
      }, 600);
    });
  }

  function bindSubmissionsTableAutoSubmit(context) {
    $(context)
      .find('.twt-tcrm-submissions-table form')
      .each(function () {
        var $filterForm = $(this);
        if ($filterForm.data('twtBoundSubmissionsFilters')) return;
        $filterForm.data('twtBoundSubmissionsFilters', true);

        var $formSelect = $filterForm.find('select[name="form_id"]');
        if (!$formSelect.length) return;

        $formSelect.on('change', function () {
          var $camp = $filterForm.find('select[name="campaign_id"]');
          var $loc = $filterForm.find('select[name="location_id"]');

          if ($camp.length) $camp.val('0');
          if ($loc.length) $loc.val('0');

          $filterForm.find('input[name="twt_page"]').remove();
          $filterForm.trigger('submit');
        });
      });
  }

  // NEW: Wizard (steps + progress)
  function bindWizard($form) {
    var $wizard = $form.find('.twt-tcrm-wizard');
    if (!$wizard.length) return;

    var $steps = $wizard.find('[data-twt-step]');
    if (!$steps.length) return;

    var total = parseInt($wizard.attr('data-steps-total') || $steps.length, 10);
    if (!total || total < 1) total = $steps.length;

    var stepIndex = 1;

    function showStep(n) {
      stepIndex = Math.max(1, Math.min(total, n));
      $steps.each(function () {
        var $s = $(this);
        var idx = parseInt($s.attr('data-step-index') || '0', 10);
        if (idx === stepIndex) {
          $s.prop('hidden', false);
        } else {
          $s.prop('hidden', true);
        }
      });

      updateProgress();
      scrollToTopOfWizard();
    }

    function updateProgress() {
      var pct = total <= 1 ? 100 : Math.round(((stepIndex - 1) / (total - 1)) * 100);
      var $fill = $wizard.find('.twt-tcrm-progress-fill');
      var $label = $wizard.find('[data-twt-progress-label]');

      if ($fill.length) $fill.css('width', pct + '%');
      if ($label.length) $label.text(pct + '%');
    }

    function scrollToTopOfWizard() {
      try {
        $wizard[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
      } catch (e) {}
    }

    // next/prev buttons
    $wizard.on('click', '[data-twt-next]', function () {
      var $current = $wizard.find('[data-twt-step][data-step-index="' + stepIndex + '"]');
      if (!validateRequired($current)) {
        showHint($form, 'Há campos obrigatórios por preencher');
        return;
      }
      showStep(stepIndex + 1);
    });

    $wizard.on('click', '[data-twt-prev]', function () {
      showStep(stepIndex - 1);
    });

    // submit button (wizard)
    $wizard.on('click', '[data-twt-submit]', function () {
      var $current = $wizard.find('[data-twt-step][data-step-index="' + stepIndex + '"]');
      if (!validateRequired($current)) {
        showHint($form, 'Há campos obrigatórios por preencher');
        return;
      }

      // final guard: validate whole form required
      if (!validateRequired($form)) {
        showHint($form, 'Há campos obrigatórios por preencher');
        return;
      }

      $form.trigger('submit');
    });

    // hide default submit button area if wizard is active (we use step buttons)
    $form.find('.twt-tcrm-actions').prop('hidden', true);

    // initial
    showStep(1);
  }

  $(function () {
    var $form = $('.twt-tcrm-form');
    if ($form.length) {
      if (window.location.search.indexOf('twt_tcrm_ok=1') !== -1) {
        clearDraft($form);
      }

      loadDraft($form);
      bindAutosave($form);

      $form.on('submit', function (e) {
        if (!validateRequired($form)) {
          e.preventDefault();
          showHint($form, 'Há campos obrigatórios por preencher');
          return false;
        }
        saveDraft($form);
        return true;
      });

      // NEW
      bindWizard($form);
    }

    bindSubmissionsTableAutoSubmit(document);
  });

})(jQuery);