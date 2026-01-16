/* global jQuery */
(function ($) {
  'use strict';

  function safeParse(json) {
    try { return JSON.parse(json); } catch (e) { return null; }
  }

  function slugify(s) {
    s = String(s || '').toLowerCase().trim();
    s = s.replace(/[^\w\s-]/g, '');
    s = s.replace(/\s+/g, '_');
    s = s.replace(/_+/g, '_');
    return s;
  }

  function tplRender(tpl, data) {
    return tpl
      .replace(/{{id}}/g, data.id)
      .replace(/{{label}}/g, escapeHtml(data.label || 'Pergunta'))
      .replace(/{{key}}/g, escapeHtml(data.key || ''))
      .replace(/{{type}}/g, escapeHtml(data.type || 'text'))
      .replace(/{{help_text}}/g, escapeHtml(data.help_text || ''))
      .replace(/{{required}}/g, data.required ? 'checked' : '')
      .replace(/{{min}}/g, data.min !== null && data.min !== undefined ? String(data.min) : '')
      .replace(/{{max}}/g, data.max !== null && data.max !== undefined ? String(data.max) : '')
      .replace(/{{unit}}/g, escapeHtml(data.unit || ''))
      .replace(/{{options_text}}/g, escapeHtml((data.options || []).join('\n')));
  }

  function escapeHtml(s) {
    return String(s || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function uuid() {
    return 'q_' + Math.random().toString(16).slice(2) + Date.now().toString(16);
  }

  function readSchema() {
    var $hidden = $('#twt_form_schema_json');
    var raw = $hidden.val() || '';
    var obj = safeParse(raw);
    if (!obj || typeof obj !== 'object') obj = { meta: { title: '', subtitle: '' }, questions: [] };
    if (!obj.meta) obj.meta = { title: '', subtitle: '' };
    if (!Array.isArray(obj.questions)) obj.questions = [];
    return obj;
  }

  function writeSchema(schema) {
    $('#twt_form_schema_json').val(JSON.stringify(schema));
  }

  function refreshList(schema) {
    var $list = $('#twt-fb-list');
    var tpl = $('#twt-fb-item-tpl').html() || '';
    $list.empty();

    schema.questions.forEach(function (q) {
      var html = tplRender(tpl, {
        id: q._id || uuid(),
        label: q.label || '',
        key: q.key || '',
        type: q.type || 'text',
        help_text: q.help_text || '',
        required: !!q.required,
        min: q.min,
        max: q.max,
        unit: q.unit || '',
        options: Array.isArray(q.options) ? q.options : []
      });

      var $item = $(html);

      // selected do type
      $item.find('select[data-fb="type"]').val(q.type || 'text');

      // esconder opções quando não são necessárias
      toggleOptionsUI($item);

      $list.append($item);
    });

    bindSortable();
  }

  function toggleOptionsUI($item) {
    var type = $item.find('select[data-fb="type"]').val();
    var $opts = $item.find('.twt-fb-options');
    if (type === 'select' || type === 'radio') {
      $opts.show();
    } else {
      $opts.hide();
    }
  }

  function buildSchemaFromUI(schema) {
    // meta
    schema.meta = schema.meta || {};
    schema.meta.title = String($('[data-fb-meta="title"]').val() || '');
    schema.meta.subtitle = String($('[data-fb-meta="subtitle"]').val() || '');

    var out = [];
    $('#twt-fb-list .twt-fb-item').each(function () {
      var $it = $(this);

      var label = String($it.find('[data-fb="label"]').val() || '').trim();
      var key = String($it.find('[data-fb="key"]').val() || '').trim();
      var type = String($it.find('[data-fb="type"]').val() || 'text');
      var required = $it.find('[data-fb="required"]').is(':checked');
      var help = String($it.find('[data-fb="help_text"]').val() || '').trim();

      if (!key && label) key = slugify(label);

      var min = $it.find('[data-fb="min"]').val();
      var max = $it.find('[data-fb="max"]').val();
      var unit = String($it.find('[data-fb="unit"]').val() || '').trim();

      var q = {
        key: slugify(key),
        label: label || key,
        type: type,
        required: required
      };

      if (help) q.help_text = help;

      if (min !== '') q.min = parseFloat(min);
      if (max !== '') q.max = parseFloat(max);
      if (unit) q.unit = unit;

      if (type === 'select' || type === 'radio') {
        var rawOpts = String($it.find('[data-fb="options"]').val() || '');
        var opts = rawOpts.split('\n').map(function (x) { return String(x).trim(); }).filter(Boolean);
        if (opts.length) q.options = opts;
      }

      out.push(q);
    });

    schema.questions = out;
    return schema;
  }

  function bindSortable() {
    var $list = $('#twt-fb-list');
    if (!$list.data('sortable')) {
      $list.sortable({
        handle: '.twt-fb-drag',
        placeholder: 'twt-fb-placeholder',
        update: function () {
          var schema = readSchema();
          schema = buildSchemaFromUI(schema);
          writeSchema(schema);
        }
      });
      $list.data('sortable', true);
    }
  }

  function addQuestion() {
    var schema = readSchema();
    schema.questions.push({
      _id: uuid(),
      key: '',
      label: 'Nova pergunta',
      type: 'text',
      required: false,
      help_text: ''
    });
    writeSchema(schema);
    refreshList(schema);
  }

  function deleteQuestion($item) {
    $item.remove();
    var schema = readSchema();
    schema = buildSchemaFromUI(schema);
    writeSchema(schema);
  }

  function setupAutoSync() {
    $(document).on('input change', '.twt-tcrm-form-builder input, .twt-tcrm-form-builder textarea, .twt-tcrm-form-builder select', function () {
      var schema = readSchema();
      schema = buildSchemaFromUI(schema);
      writeSchema(schema);

      var $item = $(this).closest('.twt-fb-item');
      if ($item.length) {
        $item.find('.twt-fb-label').text(String($item.find('[data-fb="label"]').val() || '').trim() || 'Pergunta');
        $item.find('.twt-fb-type').text(String($item.find('[data-fb="type"]').val() || 'text'));
        toggleOptionsUI($item);
      }
    });

    $(document).on('click', '.twt-fb-del', function () {
      if (!window.confirm('Apagar esta pergunta?')) return;
      deleteQuestion($(this).closest('.twt-fb-item'));
    });

    $('#twt-fb-add').on('click', function () {
      addQuestion();
    });
  }

  $(function () {
    var $hidden = $('#twt_form_schema_json');
    if (!$hidden.length) return;

    var schema = readSchema();

    // garante meta
    schema.meta = schema.meta || { title: '', subtitle: '' };
    $('[data-fb-meta="title"]').val(schema.meta.title || '');
    $('[data-fb-meta="subtitle"]').val(schema.meta.subtitle || '');

    refreshList(schema);
    setupAutoSync();

    // Ao gravar, força sync final para o hidden
    $('#post').on('submit', function () {
      var s = readSchema();
      s = buildSchemaFromUI(s);
      writeSchema(s);
    });
  });

})(jQuery);
