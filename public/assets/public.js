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

  function serializeForm($form) {
    var data = {};
    $form.find('input, textarea, select').each(function () {
      var $el = $(this);
      var name = $el.attr('name');
      if (!name) return;

      // Ignora campos de sistema
      if (name === 'action' || name === 'twt_form_id' || name === '_wp_http_referer') return;
      if (name.indexOf('twt_tcrm_nonce') !== -1) return;

      var type = ($el.attr('type') || '').toLowerCase();

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
      var $els = $form.find('[name="' + CSS.escape(name) + '"]');
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
    } catch (e) {
      // Se falhar, não bloqueia o user
    }
  }

  function loadDraft($form) {
    try {
      var key = storageKey(getFormId(), getUserId());
      var raw = localStorage.getItem(key);
      if (!raw) return;

      var draft = JSON.parse(raw);
      if (!draft || typeof draft !== 'object') return;

      // Só tenta aplicar se o formulário estiver vazio na maioria dos campos
      var filled = 0;
      $form.find('input[name^="twt_q["], textarea[name^="twt_q["], select[name^="twt_q["]').each(function () {
        var $el = $(this);
        var t = ($el.attr('type') || '').toLowerCase();
        if (t === 'checkbox' || t === 'radio') return;

        var v = ($el.val() || '').toString().trim();
        if (v) filled++;
      });

      if (filled === 0) {
        applyDraft($form, draft);
        showHint($form, 'Rascunho restaurado');
      }
    } catch (e) {
      // ignora
    }
  }

  function clearDraft($form) {
    try {
      var key = storageKey(getFormId(), getUserId());
      localStorage.removeItem(key);
    } catch (e) {
      // ignora
    }
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

  function validateRequired($form) {
    var ok = true;
    var firstBad = null;

    $form.find('[required]').each(function () {
      var $el = $(this);
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
        var anyChecked = $form.find('input[type="radio"][name="' + CSS.escape(name) + '"]:checked').length > 0;
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
      } catch (e) {
        // ignora
      }
      firstBad.focus();
      showHint($form, 'Há campos obrigatórios por preencher');
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

  $(function () {
    var $form = $('.twt-tcrm-form');
    if (!$form.length) return;

    // IMPORTANTE: para o draft funcionar por user e por form
    // o HTML do form deve incluir data-form-id e data-user-id
    // vamos garantir isto no renderer/shortcode já a seguir, se ainda não tiveres

    // Se houver sucesso, limpa draft
    if (window.location.search.indexOf('twt_tcrm_ok=1') !== -1) {
      clearDraft($form);
    }

    loadDraft($form);
    bindAutosave($form);

    $form.on('submit', function (e) {
      if (!validateRequired($form)) {
        e.preventDefault();
        return false;
      }
      // última gravação rápida antes de enviar
      saveDraft($form);
      return true;
    });
  });

})(jQuery);
