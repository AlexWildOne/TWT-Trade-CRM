<?php

if (!defined('ABSPATH')) {
  exit;
}

final class TWT_TCRM_Form_Builder {

  public static function boot() {
    add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes']);
    add_action('save_post_twt_form', [__CLASS__, 'save_form_meta'], 10, 2);
    add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
  }

  public static function enqueue_assets($hook) {
    if (!is_admin()) return;

    if ($hook !== 'post.php' && $hook !== 'post-new.php') return;

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;

    if ($screen && isset($screen->post_type) && $screen->post_type !== 'twt_form') return;

    if (!$screen || !isset($screen->post_type) || !$screen->post_type) {

      if ($hook === 'post-new.php') {
        $pt = isset($_GET['post_type']) ? sanitize_key($_GET['post_type']) : '';
        if ($pt && $pt !== 'twt_form') return;
      }

      $post_id = 0;
      if (isset($_GET['post'])) $post_id = (int) $_GET['post'];
      if (!$post_id && isset($_POST['post_ID'])) $post_id = (int) $_POST['post_ID'];
      if ($post_id) {
        $ptype = get_post_type($post_id);
        if ($ptype !== 'twt_form') return;
      }
    }

    wp_enqueue_script('jquery-ui-sortable');

    wp_enqueue_style(
      'twt-tcrm-form-builder',
      TWT_TCRM_PLUGIN_URL . 'admin/assets/form-builder.css',
      [],
      TWT_TCRM_VERSION
    );

    wp_enqueue_script(
      'twt-tcrm-form-builder',
      TWT_TCRM_PLUGIN_URL . 'admin/assets/form-builder.js',
      ['jquery', 'jquery-ui-sortable'],
      TWT_TCRM_VERSION,
      true
    );

    wp_enqueue_media();
  }

  public static function add_meta_boxes() {
    add_meta_box(
      'twt_tcrm_form_settings',
      'TWT CRM, Definições do Formulário',
      [__CLASS__, 'box_settings'],
      'twt_form',
      'side',
      'default'
    );

    add_meta_box(
      'twt_tcrm_form_builder',
      'TWT CRM, Form Builder',
      [__CLASS__, 'box_builder'],
      'twt_form',
      'normal',
      'high'
    );

    add_meta_box(
      'twt_tcrm_form_schema_raw',
      'TWT CRM, JSON (avançado)',
      [__CLASS__, 'box_schema_raw'],
      'twt_form',
      'normal',
      'low'
    );
  }

  public static function box_settings($post) {
    wp_nonce_field('twt_tcrm_form_builder_save', 'twt_tcrm_form_builder_nonce');

    $brand_id = (int) get_post_meta($post->ID, 'twt_brand_id', true);
    $campaign_id = (int) get_post_meta($post->ID, 'twt_campaign_id', true);
    $status = get_post_meta($post->ID, 'twt_form_status', true);
    if (!$status) $status = 'active';

    $brands = get_posts([
      'post_type' => 'twt_brand',
      'numberposts' => -1,
      'orderby' => 'title',
      'order' => 'ASC',
    ]);

    $campaigns = get_posts([
      'post_type' => 'twt_campaign',
      'numberposts' => -1,
      'orderby' => 'title',
      'order' => 'ASC',
    ]);

    echo '<p><label><strong>Estado</strong></label><br>';
    echo '<select name="twt_form_status" style="width:100%;">';
    echo '<option value="active"' . selected($status, 'active', false) . '>Activo</option>';
    echo '<option value="inactive"' . selected($status, 'inactive', false) . '>Inactivo</option>';
    echo '</select></p>';

    echo '<p><label><strong>Marca</strong></label><br>';
    echo '<select name="twt_brand_id" style="width:100%;">';
    echo '<option value="0">Sem marca</option>';
    foreach ($brands as $b) {
      echo '<option value="' . esc_attr($b->ID) . '"' . selected($brand_id, (int)$b->ID, false) . '>' . esc_html($b->post_title) . '</option>';
    }
    echo '</select></p>';

    echo '<p><label><strong>Campanha</strong></label><br>';
    echo '<select name="twt_campaign_id" style="width:100%;">';
    echo '<option value="0">Sem campanha</option>';
    foreach ($campaigns as $c) {
      echo '<option value="' . esc_attr($c->ID) . '"' . selected($campaign_id, (int)$c->ID, false) . '>' . esc_html($c->post_title) . '</option>';
    }
    echo '</select></p>';

    echo '<p style="margin-top:12px;">';
    echo '<code>[twt_form id="' . esc_html($post->ID) . '"]</code>';
    echo '</p>';

    echo '<p class="description">O builder cria o schema automaticamente. No front, a visibilidade é por atribuição (marca, campanha, user).</p>';
  }

  public static function box_builder($post) {
    $schema_json = get_post_meta($post->ID, 'twt_form_schema_json', true);
    $schema = self::decode_json($schema_json);

    if (!$schema || !is_array($schema)) {
      $schema = self::default_schema_array();
    }

    if (empty($schema['meta'])) $schema['meta'] = [];
    if (!isset($schema['meta']['title'])) $schema['meta']['title'] = '';
    if (!isset($schema['meta']['subtitle'])) $schema['meta']['subtitle'] = '';
    if (empty($schema['questions']) || !is_array($schema['questions'])) $schema['questions'] = [];

    $encoded = wp_json_encode($schema, JSON_UNESCAPED_UNICODE);

    echo '<div class="twt-tcrm-admin twt-tcrm-form-builder">';

    echo '<div class="twt-fb-meta">';
    echo '<div class="twt-fb-row">';
    echo '<label><strong>Título (opcional)</strong></label>';
    echo '<input type="text" class="twt-fb-input" data-fb-meta="title" value="' . esc_attr($schema['meta']['title']) . '" placeholder="Ex, Report Loja, Auditoria PDV">';
    echo '</div>';

    echo '<div class="twt-fb-row">';
    echo '<label><strong>Subtítulo (opcional)</strong></label>';
    echo '<input type="text" class="twt-fb-input" data-fb-meta="subtitle" value="' . esc_attr($schema['meta']['subtitle']) . '" placeholder="Ex, Semana 3, Norte, Campanha X">';
    echo '</div>';
    echo '</div>';

    echo '<hr style="margin:14px 0;">';

    echo '<div class="twt-fb-toolbar">';
    echo '<button type="button" class="button button-primary" id="twt-fb-add">Adicionar pergunta</button>';
    echo '<span class="twt-fb-hint">Arrasta para reordenar. Edita inline. Guarda o post para aplicar.</span>';
    echo '</div>';

    echo '<div id="twt-fb-list" class="twt-fb-list"></div>';

    echo '<input type="hidden" id="twt_form_schema_json" name="twt_form_schema_json" value="' . esc_attr($encoded) . '">';

    echo '<script type="text/template" id="twt-fb-item-tpl">
      <div class="twt-fb-item" data-id="{{id}}">
        <div class="twt-fb-head">
          <span class="twt-fb-drag" title="Arrastar">::</span>
          <strong class="twt-fb-label">{{label}}</strong>
          <span class="twt-fb-type">{{type}}</span>
          <button type="button" class="button-link-delete twt-fb-del">Apagar</button>
        </div>

        <div class="twt-fb-body">
          <div class="twt-fb-grid">
            <div class="twt-fb-row">
              <label>Label</label>
              <input type="text" data-fb="label" value="{{label}}">
            </div>

            <div class="twt-fb-row">
              <label>Key</label>
              <input type="text" data-fb="key" value="{{key}}" placeholder="auto, se vazio">
              <div class="twt-fb-small">Sem espaços, minúsculas, ex, ruptura_stock</div>
            </div>

            <div class="twt-fb-row">
  <label>Tipo</label>
  <select data-fb="type" class="twt-fb-type-select">
                <option value="text">Texto</option>
                <option value="textarea">Texto longo</option>
                <option value="number">Número</option>
                <option value="currency">Euro</option>
                <option value="percent">Percentagem</option>
                <option value="date">Data</option>
                <option value="time">Hora</option>
                <option value="checkbox">Checkbox</option>
                <option value="select">Selecção</option>
                <option value="radio">Radio</option>
                <option value="image_upload">Upload imagem</option>
                <option value="file_upload">Upload ficheiro</option>
              </select>
            </div>
<div class="twt-fb-preview" data-fb-preview></div>

            <div class="twt-fb-row">
              <label>Obrigatório</label>
              <label class="twt-fb-check">
                <input type="checkbox" data-fb="required" {{required}}>
                <span>Sim</span>
              </label>
            </div>
          </div>

          <div class="twt-fb-row">
            <label>Ajuda (opcional)</label>
            <input type="text" data-fb="help_text" value="{{help_text}}" placeholder="Ex, faz uma estimativa rápida">
          </div>

          <div class="twt-fb-row twt-fb-options">
            <label>Opções (uma por linha, só para select e radio)</label>
            <textarea data-fb="options" rows="4" placeholder="Ex,
Sim
Não
Talvez">{{options_text}}</textarea>
          </div>

          <div class="twt-fb-grid twt-fb-grid-3">
            <div class="twt-fb-row">
              <label>Mínimo (opcional)</label>
              <input type="number" step="0.01" data-fb="min" value="{{min}}">
            </div>

            <div class="twt-fb-row">
              <label>Máximo (opcional)</label>
              <input type="number" step="0.01" data-fb="max" value="{{max}}">
            </div>

            <div class="twt-fb-row">
              <label>Unidade (opcional)</label>
              <input type="text" data-fb="unit" value="{{unit}}" placeholder="Ex, un, caixas, ml">
            </div>
          </div>

          <div class="twt-fb-small">Uploads no BO estão prontos para configurar, o front vai tratar do upload no passo seguinte.</div>
        </div>
      </div>
    </script>';

    echo '</div>';
  }

  public static function box_schema_raw($post) {
    $schema = get_post_meta($post->ID, 'twt_form_schema_json', true);
    if (!$schema) $schema = wp_json_encode(self::default_schema_array(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    echo '<p class="description">Avançado. Se editares manualmente, tens de manter JSON válido.</p>';
    echo '<textarea name="twt_form_schema_json_raw" style="width:100%;min-height:220px;font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;font-size:12px;">' . esc_textarea($schema) . '</textarea>';
  }

  public static function save_form_meta($post_id, $post) {
    if (!isset($_POST['twt_tcrm_form_builder_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['twt_tcrm_form_builder_nonce'])), 'twt_tcrm_form_builder_save')) {
      return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $status = isset($_POST['twt_form_status']) ? sanitize_key(wp_unslash($_POST['twt_form_status'])) : 'active';
    if (!in_array($status, ['active', 'inactive'], true)) $status = 'active';

    $brand_id = isset($_POST['twt_brand_id']) ? (int) $_POST['twt_brand_id'] : 0;
    $campaign_id = isset($_POST['twt_campaign_id']) ? (int) $_POST['twt_campaign_id'] : 0;

    update_post_meta($post_id, 'twt_form_status', $status);
    update_post_meta($post_id, 'twt_brand_id', $brand_id);
    update_post_meta($post_id, 'twt_campaign_id', $campaign_id);

    $schema_raw = isset($_POST['twt_form_schema_json']) ? wp_unslash($_POST['twt_form_schema_json']) : '';
    $schema_raw = is_string($schema_raw) ? trim($schema_raw) : '';

    $raw_override = isset($_POST['twt_form_schema_json_raw']) ? wp_unslash($_POST['twt_form_schema_json_raw']) : '';
    $raw_override = is_string($raw_override) ? trim($raw_override) : '';
    if ($raw_override !== '') {
      $schema_raw = $raw_override;
    }

    if ($schema_raw !== '') {
      $decoded = json_decode($schema_raw, true);
      if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $decoded = self::sanitize_schema($decoded);
        update_post_meta($post_id, 'twt_form_schema_json', wp_json_encode($decoded, JSON_UNESCAPED_UNICODE));
        return;
      }
    }

    // fallback: garantir sempre schema base válido
    update_post_meta($post_id, 'twt_form_schema_json', wp_json_encode(self::default_schema_array(), JSON_UNESCAPED_UNICODE));
  }

  private static function sanitize_schema($schema) {
    if (!is_array($schema)) return self::default_schema_array();

    if (empty($schema['meta']) || !is_array($schema['meta'])) $schema['meta'] = [];
    $schema['meta']['title'] = isset($schema['meta']['title']) ? sanitize_text_field($schema['meta']['title']) : '';
    $schema['meta']['subtitle'] = isset($schema['meta']['subtitle']) ? sanitize_text_field($schema['meta']['subtitle']) : '';

    if (empty($schema['questions']) || !is_array($schema['questions'])) $schema['questions'] = [];

    $clean = [];
    foreach ($schema['questions'] as $q) {
      if (!is_array($q)) continue;

      $type = isset($q['type']) ? sanitize_key($q['type']) : 'text';
      $allowed = ['text','textarea','number','currency','percent','date','time','checkbox','select','radio','image_upload','file_upload'];
      if (!in_array($type, $allowed, true)) $type = 'text';

      $label = isset($q['label']) ? sanitize_text_field($q['label']) : '';
      $key = isset($q['key']) ? self::sanitize_question_key($q['key']) : '';
      if (!$key && $label) $key = self::sanitize_question_key($label);

      if (!$label && !$key) continue;

      $required = !empty($q['required']) ? 1 : 0;
      $help = isset($q['help_text']) ? sanitize_text_field($q['help_text']) : '';

      $item = [
        'key' => $key,
        'label' => $label ? $label : $key,
        'type' => $type,
        'required' => $required ? true : false,
      ];

      if ($help) $item['help_text'] = $help;

      if (isset($q['min']) && $q['min'] !== '' && in_array($type, ['number','currency','percent'], true)) $item['min'] = (float) $q['min'];
      if (isset($q['max']) && $q['max'] !== '' && in_array($type, ['number','currency','percent'], true)) $item['max'] = (float) $q['max'];
      if (isset($q['unit']) && $q['unit'] !== '' && in_array($type, ['number','currency','percent'], true)) $item['unit'] = sanitize_text_field($q['unit']);

      if (in_array($type, ['select','radio'], true)) {
        $opts = [];
        if (!empty($q['options']) && is_array($q['options'])) {
          foreach ($q['options'] as $o) {
            $o = sanitize_text_field($o);
            if ($o !== '') $opts[] = $o;
          }
        }
        if ($opts) $item['options'] = $opts;
      }

      $clean[] = $item;
    }

    $schema['questions'] = $clean;
    return $schema;
  }

  private static function sanitize_question_key($value) {
    $value = strtolower((string) $value);
    $value = remove_accents($value);
    $value = preg_replace('/[^a-z0-9_]+/', '_', $value);
    $value = preg_replace('/_{2,}/', '_', $value);
    $value = trim($value, '_');
    return $value;
  }

  private static function decode_json($json) {
    if (!$json) return null;
    $arr = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) return null;
    return $arr;
  }

  private static function default_schema_array() {
    return [
      'meta' => [
        'title' => '',
        'subtitle' => '',
      ],
      'questions' => [],
    ];
  }
}

TWT_TCRM_Form_Builder::boot();
