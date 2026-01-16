(function($){
  'use strict';

  var FB = {
    state: {
      meta: { title: '', subtitle: '' },
      questions: []
    },
    els: {
      schema: null,
      list: null
    }
  };

  function safeJsonParse(str, fallback){
    try {
      var o = JSON.parse(str);
      return o && typeof o === 'object' ? o : fallback;
    } catch(e){
      return fallback;
    }
  }

  function slugifyKey(str){
    if (!str) return '';
    return String(str)
      .toLowerCase()
      .trim()
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')  // remove acentos
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/^_+|_+$/g, '')
      .replace(/_{2,}/g, '_');
  }

  function uniqKey(base){
    base = slugifyKey(base || 'pergunta');
    if (!base) base = 'pergunta';
    var key = base;
    var i = 2;
    var existing = {};
    FB.state.questions.forEach(function(q){
      if (q && q.key) existing[q.key] = true;
    });
    while(existing[key]){
      key = base + '_' + i;
      i++;
    }
    return key;
  }

  function normaliseQuestion(q){
    q = q || {};
    var type = q.type || 'text';
    var allowed = [
      'text','textarea','number','currency','percent',
      'date','time','checkbox','select','radio',
      'image_upload','file_upload'
    ];
    if (allowed.indexOf(type) === -1) type = 'text';

    var out = {
      id: q.id || ('q_' + Math.random().toString(36).slice(2)),
      label: q.label || '',
      key: q.key || '',
      type: type,
      required: !!q.required,
      help_text: q.help_text || '',
      options: Array.isArray(q.options) ? q.options : [],
      min: (q.min !== undefined && q.min !== null && q.min !== '') ? q.min : '',
      max: (q.max !== undefined && q.max !== null && q.max !== '') ? q.max : '',
      unit: q.unit || ''
    };
    return out;
  }

  function hydrateStateFromHidden(){
    var raw = FB.els.schema.val() || '';
    var data = safeJsonParse(raw, { meta: {title:'',subtitle:''}, questions: [] });

    if (!data.meta) data.meta = { title:'', subtitle:'' };
    if (!Array.isArray(data.questions)) data.questions = [];

    FB.state.meta = {
      title: data.meta.title || '',
      subtitle: data.meta.subtitle || ''
    };

    FB.state.questions = data.questions.map(function(q){
      return normaliseQuestion(q);
    });

    // Garantir keys válidas
    FB.state.questions.forEach(function(q){
      if (!q.key) {
        q.key = uniqKey(q.label || 'pergunta');
      } else {
        q.key = slugifyKey(q.key);
        if (!q.key) q.key = uniqKey(q.label || 'pergunta');
      }
      if (!q.label) q.label = q.key;
    });
  }

  function syncHidden(){
    var payload = {
      meta: {
        title: FB.state.meta.title || '',
        subtitle: FB.state.meta.subtitle || ''
      },
      questions: FB.state.questions.map(function(q){
        var item = {
          key: slugifyKey(q.key || ''),
          label: q.label || q.key || '',
          type: q.type || 'text',
          required: !!q.required
        };

        if (q.help_text) item.help_text = q.help_text;

        if (q.type === 'select' || q.type === 'radio') {
          var opts = (q.options || []).map(function(o){ return String(o || '').trim(); }).filter(Boolean);
          if (opts.length) item.options = opts;
        }

        if (q.min !== '' && q.min !== null && q.min !== undefined) item.min = q.min;
        if (q.max !== '' && q.max !== null && q.max !== undefined) item.max = q.max;
        if (q.unit) item.unit = q.unit;

        return item;
      })
    };

    FB.els.schema.val(JSON.stringify(payload));
  }

  function itemTemplateHTML(){
    var tpl = $('#twt-fb-item-tpl').html() || '';
    return tpl;
  }

  function renderAll(){
    FB.els.list.empty();

    FB.state.questions.forEach(function(q){
      FB.els.list.append(renderItem(q));
    });

    bindItemUI(FB.els.list);
    syncHidden();
  }

  function renderItem(q){
    var tpl = itemTemplateHTML();

    var optionsText = (q.options || []).join("\n");

    function rep(key, val){
      var rx = new RegExp('{{' + key + '}}', 'g');
      tpl = tpl.replace(rx, val);
    }

    rep('id', q.id);
    rep('label', escapeHtml(q.label || 'Pergunta'));
    rep('key', escapeHtml(q.key || ''));
    rep('type', escapeHtml(q.type || 'text'));
    rep('help_text', escapeHtml(q.help_text || ''));
    rep('options_text', escapeHtml(optionsText));
    rep('min', escapeHtml(q.min === '' ? '' : String(q.min)));
    rep('max', escapeHtml(q.max === '' ? '' : String(q.max)));
    rep('unit', escapeHtml(q.unit || ''));
    rep('required', q.required ? 'checked' : '');

    var $el = $(tpl);

    // Seleccionar tipo correto
    $el.find('select[data-fb="type"]').val(q.type);

    // Estado dos painéis (options, minmax, unit, preview)
    updateVisibilityForType($el, q.type);
    renderPreview($el, q);

    return $el;
  }

  function escapeHtml(s){
    s = (s === null || s === undefined) ? '' : String(s);
    return s
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function updateVisibilityForType($item, type){
    var isChoice = (type === 'select' || type === 'radio');
    var isNumber = (type === 'number' || type === 'currency' || type === 'percent');
    var isUpload = (type === 'image_upload' || type === 'file_upload');

    // Mostra opções só para select/radio
    $item.find('.twt-fb-options').toggle(isChoice);

    // Min/Max sempre fazem sentido só em número/currency/percent
    $item.find('input[data-fb="min"]').closest('.twt-fb-row').toggle(isNumber);
    $item.find('input[data-fb="max"]').closest('.twt-fb-row').toggle(isNumber);

    // Unidade só para números por agora
    $item.find('input[data-fb="unit"]').closest('.twt-fb-row').toggle(isNumber);

    // Mensagem uploads
    $item.find('.twt-fb-upload-hint').remove();
    if (isUpload) {
      var msg = (type === 'image_upload') ? 'Upload de imagem, no front usa ficheiro/imagem.' : 'Upload de ficheiro, no front usa ficheiro.';
      $item.find('.twt-fb-body').append('<div class="twt-fb-small twt-fb-upload-hint">' + escapeHtml(msg) + '</div>');
    }
  }

  function ensurePreviewBox($item){
    var $prev = $item.find('.twt-fb-preview');
    if ($prev.length) return $prev;

    var html = '<div class="twt-fb-preview-wrap">' +
      '<div class="twt-fb-small" style="margin:10px 0 6px 0;">Preview</div>' +
      '<div class="twt-fb-preview"></div>' +
    '</div>';

    $item.find('.twt-fb-body').prepend(html);
    return $item.find('.twt-fb-preview');
  }

  function renderPreview($item, q){
    var $prev = ensurePreviewBox($item);
    var type = q.type;

    var html = '';
    var req = q.required ? ' required' : '';

    if (type === 'textarea') {
      html = '<textarea rows="3" style="width:100%;" disabled placeholder="Texto longo"></textarea>';
} else if (type === 'date') {
  html = '<input type="date" style="width:100%;" readonly>';
} else if (type === 'time') {
  html = '<input type="time" style="width:100%;" readonly>';
    } else if (type === 'checkbox') {
      html = '<label style="display:inline-flex;align-items:center;gap:10px;"><input type="checkbox" disabled> <span>Sim</span></label>';
    } else if (type === 'select') {
      var opts = (q.options || []).filter(Boolean);
      html = '<select style="width:100%;" disabled>' +
        '<option>Seleccionar</option>' +
        opts.slice(0,6).map(function(o){ return '<option>' + escapeHtml(o) + '</option>'; }).join('') +
      '</select>';
    } else if (type === 'radio') {
      var ropts = (q.options || []).filter(Boolean);
      html = '<div style="display:grid;gap:8px;">' +
        ropts.slice(0,6).map(function(o){
          return '<label style="display:inline-flex;align-items:center;gap:10px;"><input type="radio" disabled> <span>' + escapeHtml(o) + '</span></label>';
        }).join('') +
      '</div>';
    } else if (type === 'image_upload') {
      html = '<input type="file" accept="image/*" style="width:100%;" disabled>';
    } else if (type === 'file_upload') {
      html = '<input type="file" style="width:100%;" disabled>';
    } else if (type === 'number' || type === 'currency' || type === 'percent') {
      var suffix = '';
      if (type === 'currency') suffix = '€';
      if (type === 'percent') suffix = '%';
      var min = (q.min !== '' && q.min !== null && q.min !== undefined) ? ' min="' + escapeHtml(q.min) + '"' : '';
      var max = (q.max !== '' && q.max !== null && q.max !== undefined) ? ' max="' + escapeHtml(q.max) + '"' : '';
      html = '<div style="display:flex;gap:10px;align-items:center;">' +
        '<input type="number" step="0.01" style="width:100%;" disabled' + min + max + '>' +
        (suffix ? '<span style="white-space:nowrap;opacity:.75;">' + suffix + '</span>' : '') +
      '</div>';
    } else {
      html = '<input type="text" style="width:100%;" disabled placeholder="Texto">';
    }

    $prev.html(html);
  }

  function addQuestion(){
    var q = normaliseQuestion({
      label: 'Nova pergunta',
      type: 'text',
      required: false
    });
    q.key = uniqKey(q.label);
    FB.state.questions.unshift(q);
    renderAll();
  }

  function removeQuestion(id){
    FB.state.questions = FB.state.questions.filter(function(q){ return q.id !== id; });
    renderAll();
  }

  function findQuestion(id){
    for (var i=0; i<FB.state.questions.length; i++){
      if (FB.state.questions[i].id === id) return FB.state.questions[i];
    }
    return null;
  }

  function bindItemUI($root){
    // Sortable
    $root.sortable({
      handle: '.twt-fb-drag',
      placeholder: 'twt-fb-placeholder',
      update: function(){
        var order = [];
        $root.find('.twt-fb-item').each(function(){
          order.push($(this).attr('data-id'));
        });
        FB.state.questions.sort(function(a,b){
          return order.indexOf(a.id) - order.indexOf(b.id);
        });
        syncHidden();
      }
    });

    // Change inputs
    $root.off('input.twtfb change.twtfb click.twtfb');

    $root.on('click.twtfb', '.twt-fb-del', function(){
      var $item = $(this).closest('.twt-fb-item');
      var id = $item.attr('data-id');
      if (!id) return;
      if (!confirm('Apagar esta pergunta?')) return;
      removeQuestion(id);
    });

    $root.on('input.twtfb', 'input[data-fb="label"]', function(){
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      if (!q) return;

      q.label = $(this).val();

      // Auto-key se estiver vazia
      if (!q.key) {
        q.key = uniqKey(q.label);
        $item.find('input[data-fb="key"]').val(q.key);
      }

      $item.find('.twt-fb-label').text(q.label || 'Pergunta');
      syncHidden();
    });

    $root.on('input.twtfb', 'input[data-fb="key"]', function(){
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      if (!q) return;

      q.key = slugifyKey($(this).val());
      $(this).val(q.key);
      syncHidden();
    });

    $root.on('change.twtfb', 'select[data-fb="type"]', function(){
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      if (!q) return;

      q.type = $(this).val();

      // se mudar para select/radio e não tiver opções, mete defaults
      if ((q.type === 'select' || q.type === 'radio') && (!q.options || !q.options.length)) {
        q.options = ['Opção 1', 'Opção 2'];
        $item.find('textarea[data-fb="options"]').val(q.options.join("\n"));
      }

      // se sair de select/radio, limpa options no estado
      if (!(q.type === 'select' || q.type === 'radio')) {
        q.options = [];
        $item.find('textarea[data-fb="options"]').val('');
      }

      // se sair de number/currency/percent, limpa min/max/unit
      if (!(q.type === 'number' || q.type === 'currency' || q.type === 'percent')) {
        q.min = '';
        q.max = '';
        q.unit = '';
        $item.find('input[data-fb="min"]').val('');
        $item.find('input[data-fb="max"]').val('');
        $item.find('input[data-fb="unit"]').val('');
      }

      $item.find('.twt-fb-type').text(q.type);
      updateVisibilityForType($item, q.type);
      renderPreview($item, q);
      syncHidden();
    });

    $root.on('change.twtfb', 'input[data-fb="required"]', function(){
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      if (!q) return;

      q.required = $(this).is(':checked');
      renderPreview($item, q);
      syncHidden();
    });

    $root.on('input.twtfb', 'input[data-fb="help_text"]', function(){
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      if (!q) return;

      q.help_text = $(this).val();
      syncHidden();
    });

    $root.on('input.twtfb', 'textarea[data-fb="options"]', function(){
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      if (!q) return;

      var lines = String($(this).val() || '').split(/\r?\n/).map(function(s){ return s.trim(); }).filter(Boolean);
      q.options = lines;
      renderPreview($item, q);
      syncHidden();
    });

    $root.on('input.twtfb', 'input[data-fb="min"], input[data-fb="max"], input[data-fb="unit"]', function(){
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      if (!q) return;

      q.min = $item.find('input[data-fb="min"]').val();
      q.max = $item.find('input[data-fb="max"]').val();
      q.unit = $item.find('input[data-fb="unit"]').val();
      renderPreview($item, q);
      syncHidden();
    });
  }

  function bindMetaUI(){
    $(document).on('input', 'input[data-fb-meta="title"]', function(){
      FB.state.meta.title = $(this).val();
      syncHidden();
    });
    $(document).on('input', 'input[data-fb-meta="subtitle"]', function(){
      FB.state.meta.subtitle = $(this).val();
      syncHidden();
    });
  }

  function init(){
    FB.els.schema = $('#twt_form_schema_json');
    FB.els.list = $('#twt-fb-list');

    if (!FB.els.schema.length || !FB.els.list.length) return;

    hydrateStateFromHidden();
    renderAll();

    $('#twt-fb-add').on('click', function(){
      addQuestion();
    });

    bindMetaUI();
  }

  $(init);

})(jQuery);
