<?php
if (!defined('ABSPATH')) {
  exit;
}

final class TWT_TCRM_Form_Builder
{
  const NONCE_ACTION = 'twt_tcrm_form_builder_save';
  const NONCE_FIELD  = 'twt_tcrm_form_builder_nonce';

  const RAW_OVERRIDE_FLAG = 'twt_form_schema_json_raw_enable';

  // Theme fields (metabox side)
  const THEME_PRIMARY_FIELD = 'twt_form_theme_primary';
  const THEME_PRIMARY_HOVER_FIELD = 'twt_form_theme_primary_hover';
  const THEME_RADIUS_FIELD = 'twt_form_theme_radius';

  public static function boot()
  {
    add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes']);
    add_action('save_post_twt_form', [__CLASS__, 'save_form_meta'], 10, 2);
    add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
  }

  public static function enqueue_assets($hook)
  {
    if (!is_admin()) return;

    if ($hook !== 'post.php' && $hook !== 'post-new.php') return;

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ($screen && !empty($screen->post_type) && $screen->post_type !== 'twt_form') return;

    if (!$screen || empty($screen->post_type)) {
      if ($hook === 'post-new.php') {
        $pt = isset($_GET['post_type']) ? sanitize_key(wp_unslash($_GET['post_type'])) : '';
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

  public static function add_meta_boxes()
  {
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

  private static function get_selected_form_location_ids($form_id)
  {
    if (!$form_id) return [];
    global $wpdb;
    $t = TWT_TCRM_DB::table_form_locations();
    $ids = $wpdb->get_col($wpdb->prepare("SELECT location_id FROM {$t} WHERE form_id = %d ORDER BY location_id ASC", (int) $form_id));
    if (!is_array($ids)) return [];
    $ids = array_map('intval', $ids);
    return array_values(array_unique(array_filter($ids)));
  }

  private static function get_selected_form_campaign_ids($form_id)
  {
    if (!$form_id) return [];
    global $wpdb;
    $t = TWT_TCRM_DB::table_form_campaigns();
    $ids = $wpdb->get_col($wpdb->prepare("SELECT campaign_id FROM {$t} WHERE form_id = %d ORDER BY campaign_id ASC", (int) $form_id));
    if (!is_array($ids)) return [];
    $ids = array_map('intval', $ids);
    return array_values(array_unique(array_filter($ids)));
  }

  private static function sync_form_locations($form_id, $location_ids)
  {
    global $wpdb;
    $t = TWT_TCRM_DB::table_form_locations();

    $form_id = (int) $form_id;
    $location_ids = is_array($location_ids) ? array_map('intval', $location_ids) : [];
    $location_ids = array_values(array_unique(array_filter($location_ids)));

    $wpdb->delete($t, ['form_id' => $form_id], ['%d']);

    foreach ($location_ids as $loc_id) {
      $wpdb->insert(
        $t,
        [
          'form_id' => $form_id,
          'location_id' => (int) $loc_id,
          'created_at' => current_time('mysql'),
        ],
        ['%d', '%d', '%s']
      );
    }
  }

  private static function sync_form_campaigns($form_id, $campaign_ids)
  {
    global $wpdb;
    $t = TWT_TCRM_DB::table_form_campaigns();

    $form_id = (int) $form_id;
    $campaign_ids = is_array($campaign_ids) ? array_map('intval', $campaign_ids) : [];
    $campaign_ids = array_values(array_unique(array_filter($campaign_ids)));

    $wpdb->delete($t, ['form_id' => $form_id], ['%d']);

    foreach ($campaign_ids as $cid) {
      $wpdb->insert(
        $t,
        [
          'form_id' => $form_id,
          'campaign_id' => (int) $cid,
          'created_at' => current_time('mysql'),
        ],
        ['%d', '%d', '%s']
      );
    }
  }

  private static function get_theme_defaults()
  {
    return [
      'primary' => '#2271b1',
      'primary_hover' => '#135e96',
      'radius' => 12,
    ];
  }

  private static function sanitize_hex_color_strict($color)
  {
    $color = is_string($color) ? trim($color) : '';
    if ($color === '') return '';
    return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtoupper($color) : '';
  }

  private static function sanitize_radius($radius)
  {
    if ($radius === null) return null;
    if ($radius === '') return null;
    $r = (int) $radius;
    if ($r < 0) $r = 0;
    if ($r > 30) $r = 30;
    return $r;
  }

  public static function box_settings($post)
  {
    // Nonce apenas num metabox (evita IDs duplicados)
    wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

    $brand_id = (int) get_post_meta($post->ID, 'twt_brand_id', true);

    // Mantemos este campo como "default campaign" (compatibilidade)
    $campaign_id_default = (int) get_post_meta($post->ID, 'twt_campaign_id', true);

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

    $locations = get_posts([
      'post_type' => 'twt_location',
      'numberposts' => -1,
      'orderby' => 'title',
      'order' => 'ASC',
    ]);

    $selected_location_ids = self::get_selected_form_location_ids($post->ID);
    $selected_campaign_ids = self::get_selected_form_campaign_ids($post->ID);

    $defaults = self::get_theme_defaults();
    $theme_primary = (string) get_post_meta($post->ID, self::THEME_PRIMARY_FIELD, true);
    $theme_primary_hover = (string) get_post_meta($post->ID, self::THEME_PRIMARY_HOVER_FIELD, true);
    $theme_radius = get_post_meta($post->ID, self::THEME_RADIUS_FIELD, true);

    $theme_primary = self::sanitize_hex_color_strict($theme_primary);
    $theme_primary_hover = self::sanitize_hex_color_strict($theme_primary_hover);
    $theme_radius = self::sanitize_radius($theme_radius);

    if (!$theme_primary) $theme_primary = $defaults['primary'];
    if (!$theme_primary_hover) $theme_primary_hover = $defaults['primary_hover'];
    if ($theme_radius === null) $theme_radius = $defaults['radius'];

    echo '<p><label><strong>Estado</strong></label><br>';
    echo '<select name="twt_form_status" style="width:100%;">';
    echo '<option value="active"' . selected($status, 'active', false) . '>Activo</option>';
    echo '<option value="inactive"' . selected($status, 'inactive', false) . '>Inactivo</option>';
    echo '</select></p>';

    echo '<p><label><strong>Marca</strong></label><br>';
    echo '<select name="twt_brand_id" style="width:100%;">';
    echo '<option value="0">Sem marca</option>';
    foreach ($brands as $b) {
      echo '<option value="' . esc_attr($b->ID) . '"' . selected($brand_id, (int) $b->ID, false) . '>' . esc_html($b->post_title) . '</option>';
    }
    echo '</select></p>';

    echo '<p><label><strong>Campanha (default)</strong></label><br>';
    echo '<select name="twt_campaign_id" style="width:100%;">';
    echo '<option value="0">Sem campanha</option>';
    foreach ($campaigns as $c) {
      echo '<option value="' . esc_attr($c->ID) . '"' . selected($campaign_id_default, (int) $c->ID, false) . '>' . esc_html($c->post_title) . '</option>';
    }
    echo '</select>';
    echo '<span class="description">Default sugerida no front (pode ser escolhida outra se estiver em “Campanhas disponíveis”).</span>';
    echo '</p>';

    echo '<hr style="margin:12px 0;">';

    echo '<p><label><strong>Campanhas disponíveis (multi)</strong></label><br>';
    echo '<select name="twt_form_campaign_ids[]" multiple size="8" style="width:100%;">';
    foreach ($campaigns as $c) {
      $sel = in_array((int) $c->ID, $selected_campaign_ids, true) ? ' selected' : '';
      echo '<option value="' . esc_attr((int) $c->ID) . '"' . $sel . '>' . esc_html($c->post_title) . '</option>';
    }
    echo '</select>';
    echo '<span class="description">Define em que campanhas este formulário pode ser usado.</span>';
    echo '</p>';

    echo '<p><label><strong>Locais (multi)</strong></label><br>';
    echo '<select name="twt_form_location_ids[]" multiple size="10" style="width:100%;">';
    foreach ($locations as $l) {
      $sel = in_array((int) $l->ID, $selected_location_ids, true) ? ' selected' : '';
      echo '<option value="' . esc_attr((int) $l->ID) . '"' . $sel . '>' . esc_html($l->post_title) . '</option>';
    }
    echo '</select>';
    echo '<span class="description">Se definires locais, no front o user só pode escolher um desses (e tem de o ter atribuído).</span>';
    echo '</p>';

    echo '<hr style="margin:12px 0;">';
    echo '<p><strong>Tema (front)</strong></p>';

    echo '<p><label><strong>Cor primária</strong></label><br>';
    echo '<input type="color" name="' . esc_attr(self::THEME_PRIMARY_FIELD) . '" value="' . esc_attr($theme_primary) . '" style="width:100%;max-width:160px;"></p>';

    echo '<p><label><strong>Cor primária (hover)</strong></label><br>';
    echo '<input type="color" name="' . esc_attr(self::THEME_PRIMARY_HOVER_FIELD) . '" value="' . esc_attr($theme_primary_hover) . '" style="width:100%;max-width:160px;"></p>';

    echo '<p><label><strong>Radius (px)</strong></label><br>';
    echo '<input type="number" min="0" max="30" step="1" name="' . esc_attr(self::THEME_RADIUS_FIELD) . '" value="' . esc_attr((string) $theme_radius) . '" style="width:100%;max-width:160px;"></p>';

    echo '<p class="description">Estas opções controlam o tema no front (botões, progresso, bordas). São guardadas no schema do formulário em <code>layout.theme</code>.</p>';

    echo '<p style="margin-top:12px;">';
    echo '<code>[twt_form id="' . esc_html($post->ID) . '"]</code>';
    echo '</p>';

    echo '<p class="description">O builder cria o schema automaticamente. No front, a visibilidade é por atribuição (marca, campanha, user) e a submissão escolhe campanha/local.</p>';
  }

  public static function box_builder($post)
  {
    $schema_json = (string) get_post_meta($post->ID, 'twt_form_schema_json', true);
    $schema = self::decode_json($schema_json);

    if (!$schema || !is_array($schema)) {
      $schema = self::default_schema_array();
    }

    if (empty($schema['meta']) || !is_array($schema['meta'])) $schema['meta'] = [];
    if (!isset($schema['meta']['title'])) $schema['meta']['title'] = '';
    if (!isset($schema['meta']['subtitle'])) $schema['meta']['subtitle'] = '';
    if (empty($schema['questions']) || !is_array($schema['questions'])) $schema['questions'] = [];

    if (empty($schema['layout']) || !is_array($schema['layout'])) {
      $schema['layout'] = self::default_layout_array();
    } else {
      $schema['layout'] = array_merge(self::default_layout_array(), $schema['layout']);
    }

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

    // Layout UI is printed from PHP (avoids admin cache issues)
    echo '<div class="twt-fb-layout">';
    echo '  <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:space-between;">';
    echo '    <div>';
    echo '      <strong>Layout</strong>';
    echo '      <div class="twt-fb-small">Configura steps (wizard), progresso e largura (%) por campo.</div>';
    echo '    </div>';
    echo '    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">';
    echo '      <label style="display:inline-flex;align-items:center;gap:8px;">';
    echo '        <input type="checkbox" data-fb-layout-mode> <span>Wizard (Steps)</span>';
    echo '      </label>';
    echo '      <label style="display:inline-flex;align-items:center;gap:8px;">';
    echo '        <input type="checkbox" data-fb-layout-progress> <span>Mostrar progresso</span>';
    echo '      </label>';
    echo '      <button type="button" class="button" data-fb-add-step>Adicionar step</button>';
    echo '    </div>';
    echo '  </div>';
    echo '  <div class="twt-fb-steps"></div>';
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

  public static function box_schema_raw($post)
  {
    $schema = (string) get_post_meta($post->ID, 'twt_form_schema_json', true);

    if (!$schema) {
      $schema = wp_json_encode(self::default_schema_array(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } else {
      $decoded = self::decode_json($schema);
      if (is_array($decoded)) {
        $schema = wp_json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
      }
    }

    echo '<p><label style="display:inline-flex;align-items:center;gap:10px;">';
    echo '<input type="checkbox" name="' . esc_attr(self::RAW_OVERRIDE_FLAG) . '" value="1"> ';
    echo '<strong>Usar este JSON como override (avançado)</strong>';
    echo '</label></p>';

    echo '<p class="description">Se não ativares o override, este JSON serve apenas para consulta. Assim o Form Builder não é sobrescrito acidentalmente.</p>';

    echo '<textarea name="twt_form_schema_json_raw" style="width:100%;min-height:220px;font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;font-size:12px;">' . esc_textarea($schema) . '</textarea>';
  }

  public static function save_form_meta($post_id, $post)
  {
    if (!$post || $post->post_type !== 'twt_form') return;

    if (
      !isset($_POST[self::NONCE_FIELD]) ||
      !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD])), self::NONCE_ACTION)
    ) {
      return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $status = isset($_POST['twt_form_status']) ? sanitize_key(wp_unslash($_POST['twt_form_status'])) : 'active';
    if (!in_array($status, ['active', 'inactive'], true)) $status = 'active';

    $brand_id = isset($_POST['twt_brand_id']) ? (int) wp_unslash($_POST['twt_brand_id']) : 0;

    // default campaign (compat)
    $campaign_id_default = isset($_POST['twt_campaign_id']) ? (int) wp_unslash($_POST['twt_campaign_id']) : 0;

    update_post_meta($post_id, 'twt_form_status', $status);
    update_post_meta($post_id, 'twt_brand_id', $brand_id);
    update_post_meta($post_id, 'twt_campaign_id', $campaign_id_default);

    // multi campaigns + multi locations
    $campaign_ids = isset($_POST['twt_form_campaign_ids']) && is_array($_POST['twt_form_campaign_ids'])
      ? array_map('intval', wp_unslash($_POST['twt_form_campaign_ids']))
      : [];
    $location_ids = isset($_POST['twt_form_location_ids']) && is_array($_POST['twt_form_location_ids'])
      ? array_map('intval', wp_unslash($_POST['twt_form_location_ids']))
      : [];

    if (class_exists('TWT_TCRM_DB') && method_exists('TWT_TCRM_DB', 'table_form_campaigns')) {
      self::sync_form_campaigns($post_id, $campaign_ids);
    }
    if (class_exists('TWT_TCRM_DB') && method_exists('TWT_TCRM_DB', 'table_form_locations')) {
      self::sync_form_locations($post_id, $location_ids);
    }

    // Theme inputs -> post meta (source of truth from BO)
    $defaults = self::get_theme_defaults();
    $theme_primary = isset($_POST[self::THEME_PRIMARY_FIELD]) ? sanitize_text_field(wp_unslash($_POST[self::THEME_PRIMARY_FIELD])) : '';
    $theme_primary_hover = isset($_POST[self::THEME_PRIMARY_HOVER_FIELD]) ? sanitize_text_field(wp_unslash($_POST[self::THEME_PRIMARY_HOVER_FIELD])) : '';
    $theme_radius = isset($_POST[self::THEME_RADIUS_FIELD]) ? sanitize_text_field(wp_unslash($_POST[self::THEME_RADIUS_FIELD])) : '';

    $theme_primary = self::sanitize_hex_color_strict($theme_primary);
    $theme_primary_hover = self::sanitize_hex_color_strict($theme_primary_hover);
    $theme_radius_int = self::sanitize_radius($theme_radius);

    if (!$theme_primary) $theme_primary = $defaults['primary'];
    if (!$theme_primary_hover) $theme_primary_hover = $defaults['primary_hover'];
    if ($theme_radius_int === null) $theme_radius_int = $defaults['radius'];

    update_post_meta($post_id, self::THEME_PRIMARY_FIELD, $theme_primary);
    update_post_meta($post_id, self::THEME_PRIMARY_HOVER_FIELD, $theme_primary_hover);
    update_post_meta($post_id, self::THEME_RADIUS_FIELD, (string) $theme_radius_int);

    // schema save
    $current_schema_json = (string) get_post_meta($post_id, 'twt_form_schema_json', true);
    $current_schema_arr = self::decode_json($current_schema_json);
    if (!is_array($current_schema_arr)) $current_schema_arr = self::default_schema_array();

    $schema_raw = isset($_POST['twt_form_schema_json']) ? wp_unslash($_POST['twt_form_schema_json']) : '';
    $schema_raw = is_string($schema_raw) ? trim($schema_raw) : '';

    $raw_override_enabled = isset($_POST[self::RAW_OVERRIDE_FLAG]) && (string) wp_unslash($_POST[self::RAW_OVERRIDE_FLAG]) === '1';
    if ($raw_override_enabled) {
      $raw_override = isset($_POST['twt_form_schema_json_raw']) ? wp_unslash($_POST['twt_form_schema_json_raw']) : '';
      $raw_override = is_string($raw_override) ? trim($raw_override) : '';
      if ($raw_override !== '') {
        $schema_raw = $raw_override;
      }
    }

    if ($schema_raw === '') {
      if (!$current_schema_json) {
        update_post_meta($post_id, 'twt_form_schema_json', wp_json_encode(self::default_schema_array(), JSON_UNESCAPED_UNICODE));
      }
      return;
    }

    $decoded = json_decode($schema_raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
      update_post_meta($post_id, 'twt_form_schema_json', wp_json_encode(self::sanitize_schema($current_schema_arr), JSON_UNESCAPED_UNICODE));
      return;
    }

    $decoded = self::sanitize_schema($decoded);

    // Inject theme into schema.layout.theme (so FE can read only schema)
    if (empty($decoded['layout']) || !is_array($decoded['layout'])) $decoded['layout'] = self::default_layout_array();
    if (empty($decoded['layout']['theme']) || !is_array($decoded['layout']['theme'])) $decoded['layout']['theme'] = [];

    $decoded['layout']['theme']['primary'] = $theme_primary;
    $decoded['layout']['theme']['primary_hover'] = $theme_primary_hover;
    $decoded['layout']['theme']['radius'] = (int) $theme_radius_int;

    // re-sanitize layout now that theme injected
    $decoded['layout'] = self::sanitize_layout($decoded['layout'], isset($decoded['questions']) ? $decoded['questions'] : []);

    update_post_meta($post_id, 'twt_form_schema_json', wp_json_encode($decoded, JSON_UNESCAPED_UNICODE));
  }

  private static function sanitize_schema($schema)
  {
    if (!is_array($schema)) return self::default_schema_array();

    if (empty($schema['meta']) || !is_array($schema['meta'])) $schema['meta'] = [];
    $schema['meta']['title'] = isset($schema['meta']['title']) ? sanitize_text_field($schema['meta']['title']) : '';
    $schema['meta']['subtitle'] = isset($schema['meta']['subtitle']) ? sanitize_text_field($schema['meta']['subtitle']) : '';

    if (empty($schema['questions']) || !is_array($schema['questions'])) $schema['questions'] = [];

    if (empty($schema['layout']) || !is_array($schema['layout'])) $schema['layout'] = self::default_layout_array();
    $schema['layout'] = self::sanitize_layout($schema['layout'], $schema['questions']);

    $allowed_types = ['text', 'textarea', 'number', 'currency', 'percent', 'date', 'time', 'checkbox', 'select', 'radio', 'image_upload', 'file_upload'];

    $clean = [];
    foreach ($schema['questions'] as $q) {
      if (!is_array($q)) continue;

      $type = isset($q['type']) ? sanitize_key($q['type']) : 'text';
      if (!in_array($type, $allowed_types, true)) $type = 'text';

      $label = isset($q['label']) ? sanitize_text_field($q['label']) : '';
      $key = isset($q['key']) ? self::sanitize_question_key($q['key']) : '';
      if (!$key && $label) $key = self::sanitize_question_key($label);

      if (!$label && !$key) continue;

      $required = !empty($q['required']);
      $help = isset($q['help_text']) ? sanitize_text_field($q['help_text']) : '';

      $item = [
        'key' => $key,
        'label' => $label ? $label : $key,
        'type' => $type,
        'required' => $required ? true : false,
      ];

      if ($help) $item['help_text'] = $help;

      if (in_array($type, ['number', 'currency', 'percent'], true)) {
        if (isset($q['min']) && $q['min'] !== '' && is_numeric($q['min'])) $item['min'] = (float) $q['min'];
        if (isset($q['max']) && $q['max'] !== '' && is_numeric($q['max'])) $item['max'] = (float) $q['max'];
        if (isset($q['unit']) && $q['unit'] !== '') $item['unit'] = sanitize_text_field($q['unit']);
      }

      if (in_array($type, ['select', 'radio'], true)) {
        $opts = [];

        if (!empty($q['options']) && is_array($q['options'])) {
          foreach ($q['options'] as $o) {
            $o = sanitize_text_field($o);
            if ($o !== '') $opts[] = $o;
          }
        } elseif (!empty($q['options']) && is_string($q['options'])) {
          $lines = preg_split("/\r\n|\n|\r/", $q['options']);
          if (is_array($lines)) {
            foreach ($lines as $o) {
              $o = sanitize_text_field($o);
              if ($o !== '') $opts[] = $o;
            }
          }
        }

        if ($opts) $item['options'] = array_values(array_unique($opts));
      }

      $clean[] = $item;
    }

    $schema['questions'] = $clean;

    $schema['layout'] = self::sanitize_layout($schema['layout'], $schema['questions']);

    return $schema;
  }

  private static function sanitize_layout($layout, $questions)
  {
    $layout = is_array($layout) ? $layout : self::default_layout_array();
    $layout = array_merge(self::default_layout_array(), $layout);

    $mode = isset($layout['mode']) ? sanitize_key($layout['mode']) : 'single';
    if (!in_array($mode, ['single', 'steps'], true)) $mode = 'single';

    $show_progress = !empty($layout['show_progress']);

    $keys = [];
    if (is_array($questions)) {
      foreach ($questions as $q) {
        if (!is_array($q)) continue;
        $k = isset($q['key']) ? sanitize_key($q['key']) : '';
        if ($k) $keys[$k] = true;
      }
    }

    $allowed_widths = [25, 33, 50, 66, 75, 100];
    $field_layout = [];
    if (!empty($layout['field_layout']) && is_array($layout['field_layout'])) {
      foreach ($layout['field_layout'] as $k => $conf) {
        $k = sanitize_key($k);
        if (!$k || !isset($keys[$k])) continue;
        $w = 100;
        if (is_array($conf) && isset($conf['width'])) $w = (int) $conf['width'];
        if (!in_array($w, $allowed_widths, true)) $w = 100;
        $field_layout[$k] = ['width' => $w];
      }
    }

    $steps = [];
    if (!empty($layout['steps']) && is_array($layout['steps'])) {
      foreach ($layout['steps'] as $st) {
        if (!is_array($st)) continue;
        $title = isset($st['title']) ? sanitize_text_field($st['title']) : '';
        $desc = isset($st['description']) ? sanitize_text_field($st['description']) : '';
        $fields = [];

        if (!empty($st['fields']) && is_array($st['fields'])) {
          foreach ($st['fields'] as $fk) {
            $fk = sanitize_key($fk);
            if ($fk && isset($keys[$fk])) $fields[] = $fk;
          }
        }

        $steps[] = [
          'title' => $title,
          'description' => $desc,
          'fields' => array_values(array_unique($fields)),
        ];
      }
    }

    // Theme (optional)
    $theme = [];
    if (!empty($layout['theme']) && is_array($layout['theme'])) {
      $primary = isset($layout['theme']['primary']) ? self::sanitize_hex_color_strict($layout['theme']['primary']) : '';
      $hover = isset($layout['theme']['primary_hover']) ? self::sanitize_hex_color_strict($layout['theme']['primary_hover']) : '';
      $radius = isset($layout['theme']['radius']) ? self::sanitize_radius($layout['theme']['radius']) : null;

      if ($primary) $theme['primary'] = $primary;
      if ($hover) $theme['primary_hover'] = $hover;
      if ($radius !== null) $theme['radius'] = (int) $radius;
    }

    return [
      'mode' => $mode,
      'show_progress' => $show_progress ? true : false,
      'steps' => $steps,
      'field_layout' => $field_layout,
      'theme' => $theme,
    ];
  }

  private static function sanitize_question_key($value)
  {
    $value = strtolower((string) $value);
    $value = remove_accents($value);
    $value = preg_replace('/[^a-z0-9_]+/', '_', $value);
    $value = preg_replace('/_{2,}/', '_', $value);
    $value = trim($value, '_');
    return $value;
  }

  private static function decode_json($json)
  {
    if (!$json) return null;
    $arr = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) return null;
    return $arr;
  }

  private static function default_layout_array()
  {
    return [
      'mode' => 'single',         // single | steps
      'show_progress' => false,   // show % by steps
      'steps' => [],              // [{title, description, fields:[key]}]
      'field_layout' => [],       // { key: { width: 25|33|50|66|75|100 } }
      'theme' => [],              // { primary, primary_hover, radius }
    ];
  }

  private static function default_schema_array()
  {
    return [
      'meta' => [
        'title' => '',
        'subtitle' => '',
      ],
      'layout' => self::default_layout_array(),
      'questions' => [],
    ];
  }
}