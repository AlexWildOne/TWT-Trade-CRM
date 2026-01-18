<?php

if (!defined('ABSPATH')) {
  exit;
}

final class TWT_TCRM_Forms {

  const ACTION_SUBMIT = 'twt_tcrm_submit_report';
  const NONCE_FIELD = 'twt_tcrm_nonce';
  const NONCE_ACTION = 'twt_tcrm_submit';

  public static function boot() {
    add_action('admin_post_' . self::ACTION_SUBMIT, [__CLASS__, 'handle_submit']);
    // Nota: nopriv removido porque o shortcode já exige login
  }

  /**
   * Shortcode: [twt_form id="123"]
   */
  public static function shortcode_form($atts) {
    $atts = shortcode_atts([
      'id' => 0,
    ], $atts);

    $form_id = (int) $atts['id'];
    if (!$form_id) {
      return '<p>Formulário inválido.</p>';
    }

    if (!is_user_logged_in()) {
      return '<p>Precisas de login para submeter reports.</p>';
    }

    $form_post = get_post($form_id);
    if (!$form_post || $form_post->post_type !== 'twt_form') {
      return '<p>Formulário não encontrado.</p>';
    }

    $user_id = get_current_user_id();

    if (!self::can_view_form($user_id, $form_id)) {
      return '<p>Não tens acesso a este formulário.</p>';
    }

    $schema = get_post_meta($form_id, 'twt_form_schema_json', true);
    $schema_arr = self::decode_json($schema);

    if (!$schema_arr || empty($schema_arr['questions']) || !is_array($schema_arr['questions'])) {
      return '<p>O schema do formulário está inválido ou vazio.</p>';
    }

    // NEW: layout agora vem dentro do schema (compatível)
    $layout_arr = [];
    if (!empty($schema_arr['layout']) && is_array($schema_arr['layout'])) {
      $layout_arr = $schema_arr['layout'];
    }

    $layout_mode = isset($layout_arr['mode']) ? sanitize_key($layout_arr['mode']) : 'single';
    if (!in_array($layout_mode, ['single', 'steps'], true)) $layout_mode = 'single';

    $steps_total = 0;
    if ($layout_mode === 'steps' && !empty($layout_arr['steps']) && is_array($layout_arr['steps'])) {
      $steps_total = count($layout_arr['steps']);
    }

    $action_url = admin_url('admin-post.php');

    // Se houver uploads no schema, o form tem de ser multipart
    $needs_multipart = false;
    if (class_exists('TWT_TCRM_Form_Renderer') && method_exists('TWT_TCRM_Form_Renderer', 'schema_needs_multipart')) {
      $needs_multipart = (bool) TWT_TCRM_Form_Renderer::schema_needs_multipart($schema_arr);
    } else {
      foreach ($schema_arr['questions'] as $q) {
        $t = isset($q['type']) ? sanitize_key($q['type']) : '';
        if ($t === 'image_upload' || $t === 'file_upload') {
          $needs_multipart = true;
          break;
        }
      }
    }
    $enctype = $needs_multipart ? ' enctype="multipart/form-data"' : '';

    // defaults (para UX)
    $default_campaign_id = (int) get_post_meta($form_id, 'twt_campaign_id', true);
    $selected_campaign_id = isset($_POST['twt_campaign_id']) ? (int) wp_unslash($_POST['twt_campaign_id']) : $default_campaign_id;

    $default_location_id = (int) get_post_meta($form_id, 'twt_location_id', true);
    $selected_location_id = isset($_POST['twt_location_id']) ? (int) wp_unslash($_POST['twt_location_id']) : $default_location_id;

    $out = '';
    $out .= '<div class="twt-tcrm twt-tcrm-form-wrap">';

    $out .= '<form method="post" action="' . esc_url($action_url) . '" class="twt-tcrm-form"'
      . ' data-form-id="' . esc_attr($form_id) . '"'
      . ' data-user-id="' . esc_attr($user_id) . '"'
      . ' data-layout-mode="' . esc_attr($layout_mode) . '"'
      . ' data-steps-total="' . esc_attr((string) (int) $steps_total) . '"'
      . $enctype . '>';

    $out .= '<input type="hidden" name="action" value="' . esc_attr(self::ACTION_SUBMIT) . '">';
    $out .= '<input type="hidden" name="twt_form_id" value="' . esc_attr($form_id) . '">';
    $out .= wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD, true, false);

    // Mensagens simples via querystring
    if (isset($_GET['twt_tcrm_ok']) && wp_unslash($_GET['twt_tcrm_ok']) === '1') {
      $out .= '<div class="twt-tcrm-notice twt-tcrm-success">Report submetido com sucesso.</div>';
    }
    if (isset($_GET['twt_tcrm_err'])) {
      $out .= '<div class="twt-tcrm-notice twt-tcrm-error">Falha ao submeter: ' . esc_html(sanitize_text_field(wp_unslash($_GET['twt_tcrm_err']))) . '.</div>';
    }

    // Contexto (Local + Campanha)
    if (class_exists('TWT_TCRM_Form_Renderer') && method_exists('TWT_TCRM_Form_Renderer', 'render_context_fields')) {
      $out .= TWT_TCRM_Form_Renderer::render_context_fields($form_id, $user_id, $selected_location_id, $selected_campaign_id);
    }

    // Perguntas (+ layout)
    if (class_exists('TWT_TCRM_Form_Renderer')) {
      $out .= TWT_TCRM_Form_Renderer::render_questions($schema_arr, $layout_arr);
    } else {
      $out .= '<p>Renderer em falta (TWT_TCRM_Form_Renderer).</p>';
    }

    // Actions: em modo steps o JS vai gerir botões; ainda assim deixamos submit para fallback
    $out .= '<div class="twt-tcrm-actions">';
    $out .= '<button type="submit" class="twt-tcrm-btn">Submeter report</button>';
    $out .= '</div>';

    $out .= '</form>';
    $out .= '</div>';

    return $out;
  }

  /**
   * Shortcode: [twt_assigned_forms]
   */
  public static function shortcode_assigned_forms($atts) {
    if (!is_user_logged_in()) {
      return '<p>Precisas de login.</p>';
    }

    $user_id = get_current_user_id();

    if (TWT_TCRM_Roles::is_admin_like($user_id)) {
      $forms = get_posts([
        'post_type' => 'twt_form',
        'numberposts' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'meta_query' => [
          [
            'key' => 'twt_form_status',
            'value' => 'active',
            'compare' => '=',
          ]
        ],
      ]);
    } else {
      $forms = self::get_assigned_forms($user_id);
    }

    if (!$forms) {
      return '<p>Não há formulários atribuídos.</p>';
    }

    $out = '<div class="twt-tcrm twt-tcrm-assigned-forms"><ul>';

    foreach ($forms as $form_post) {
      $brand_id = (int) get_post_meta($form_post->ID, 'twt_brand_id', true);
      $campaign_id = (int) get_post_meta($form_post->ID, 'twt_campaign_id', true);

      $label = esc_html($form_post->post_title);
      $meta = [];

      if ($brand_id) $meta[] = 'Marca: ' . esc_html(get_the_title($brand_id));
      if ($campaign_id) $meta[] = 'Campanha (default): ' . esc_html(get_the_title($campaign_id));

      $out .= '<li>';
      $out .= '<strong>' . $label . '</strong>';

      if ($meta) {
        $out .= '<div class="twt-tcrm-meta">' . esc_html(implode(' | ', $meta)) . '</div>';
      }

      $out .= '<div class="twt-tcrm-code">Shortcode: <code>[twt_form id="' . esc_html($form_post->ID) . '"]</code></div>';
      $out .= '</li>';
    }

    $out .= '</ul></div>';

    return $out;
  }

  /**
   * Handler de submissão (admin-post.php)
   */
  public static function handle_submit() {
    if (!is_user_logged_in()) {
      wp_safe_redirect(self::redirect_back_with_error('sem_login'));
      exit;
    }

    $nonce = isset($_POST[self::NONCE_FIELD]) ? sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD])) : '';
    if (!$nonce || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
      wp_safe_redirect(self::redirect_back_with_error('nonce'));
      exit;
    }

    $user_id = get_current_user_id();
    $form_id = isset($_POST['twt_form_id']) ? (int) $_POST['twt_form_id'] : 0;

    if (!$form_id) {
      wp_safe_redirect(self::redirect_back_with_error('form'));
      exit;
    }

    if (!self::can_view_form($user_id, $form_id)) {
      wp_safe_redirect(self::redirect_back_with_error('acesso'));
      exit;
    }

    $schema = get_post_meta($form_id, 'twt_form_schema_json', true);
    $schema_arr = self::decode_json($schema);

    if (!$schema_arr || empty($schema_arr['questions']) || !is_array($schema_arr['questions'])) {
      wp_safe_redirect(self::redirect_back_with_error('schema'));
      exit;
    }

    $brand_id = (int) get_post_meta($form_id, 'twt_brand_id', true);

    // campanha escolhida (0 permitido), validada contra pivot do form
    $campaign_id = isset($_POST['twt_campaign_id']) ? (int) wp_unslash($_POST['twt_campaign_id']) : 0;
    if (!self::campaign_allowed_for_form($form_id, $campaign_id)) {
      wp_safe_redirect(self::redirect_back_with_error('campanha'));
      exit;
    }

    // local escolhido (0 permitido apenas se o form não exigir local)
    $location_id = isset($_POST['twt_location_id']) ? (int) wp_unslash($_POST['twt_location_id']) : 0;
    if (!self::location_allowed_for_form_and_user($form_id, $user_id, $location_id)) {
      wp_safe_redirect(self::redirect_back_with_error('local'));
      exit;
    }

    $questions_index = self::index_questions($schema_arr['questions']);

    $answers = [];
    $errors = [];

    foreach ($schema_arr['questions'] as $q) {
      $key = isset($q['key']) ? sanitize_key($q['key']) : '';
      if (!$key) continue;

      $type = isset($q['type']) ? sanitize_key($q['type']) : 'text';
      $required = !empty($q['required']);

      if ($type === 'image_upload' || $type === 'file_upload') {
        $file = self::extract_uploaded_file($key);

        $is_empty_upload = (!$file || (int) $file['error'] === 4);
        if ($required && $is_empty_upload) {
          $errors[] = $key;
          continue;
        }

        if ($is_empty_upload) {
          $answers[] = [
            'question_key' => $key,
            'type' => $type,
            'value' => ['is_empty' => true, 'kind' => 'json', 'json' => null],
          ];
          continue;
        }

        $allowed_mimes = [];
        if ($type === 'image_upload') {
          $allowed_mimes = ['jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
        }

        $uploaded = self::handle_upload($file, $allowed_mimes);
        if (!$uploaded || !empty($uploaded['error'])) {
          $errors[] = $key;
          continue;
        }

        $answers[] = [
          'question_key' => $key,
          'type' => $type,
          'value' => [
            'is_empty' => false,
            'kind' => 'json',
            'json' => wp_json_encode([
              'url' => isset($uploaded['url']) ? esc_url_raw($uploaded['url']) : '',
              'attachment_id' => isset($uploaded['attachment_id']) ? (int) $uploaded['attachment_id'] : 0,
              'mime' => isset($uploaded['mime']) ? sanitize_text_field($uploaded['mime']) : '',
              'filename' => isset($uploaded['filename']) ? sanitize_file_name($uploaded['filename']) : '',
              'size' => isset($uploaded['size']) ? (int) $uploaded['size'] : 0,
            ], JSON_UNESCAPED_UNICODE),
          ],
        ];
        continue;
      }

      if ($type === 'checkbox') {
        $raw = isset($_POST['twt_q'][$key]) ? $_POST['twt_q'][$key] : '0';
      } else {
        $raw = isset($_POST['twt_q'][$key]) ? $_POST['twt_q'][$key] : null;
      }

      $parsed = self::parse_value_by_type($raw, $type);

      if (($type === 'select' || $type === 'radio') && !$parsed['is_empty']) {
        $allowed = self::get_allowed_options($questions_index, $key);
        if ($allowed && !in_array((string) $parsed['text'], $allowed, true)) {
          $errors[] = $key;
          continue;
        }
      }

      if ($required && $parsed['is_empty']) {
        $errors[] = $key;
        continue;
      }

      $answers[] = [
        'question_key' => $key,
        'type' => $type,
        'value' => $parsed,
      ];
    }

    if ($errors) {
      wp_safe_redirect(self::redirect_back_with_error('obrigatorio'));
      exit;
    }

    global $wpdb;

    $t_sub = TWT_TCRM_DB::table_submissions();
    $t_ans = TWT_TCRM_DB::table_answers();

    $meta = [
      'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
      'ip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
      'ref' => isset($_POST['_wp_http_referer']) ? esc_url_raw(wp_unslash($_POST['_wp_http_referer'])) : '',
    ];

    $inserted = $wpdb->insert(
      $t_sub,
      [
        'form_id' => $form_id,
        'brand_id' => $brand_id,
        'campaign_id' => (int) $campaign_id,
        'location_id' => (int) $location_id,
        'user_id' => $user_id,
        'submitted_at' => current_time('mysql'),
        'status' => 'submitted',
        'meta_json' => wp_json_encode($meta, JSON_UNESCAPED_UNICODE),
      ],
      [
        '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s'
      ]
    );

    if (!$inserted) {
      wp_safe_redirect(self::redirect_back_with_error('db_sub'));
      exit;
    }

    $submission_id = (int) $wpdb->insert_id;

    foreach ($answers as $a) {
      $row = [
        'submission_id' => $submission_id,
        'question_key' => $a['question_key'],
        'value_text' => null,
        'value_number' => null,
        'value_currency' => null,
        'value_percent' => null,
        'value_json' => null,
        'created_at' => current_time('mysql'),
      ];

      $formats = ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s'];

      $v = $a['value'];

      if ($v['kind'] === 'text') $row['value_text'] = isset($v['text']) ? (string) $v['text'] : '';
      if ($v['kind'] === 'number') $row['value_number'] = isset($v['number']) ? (string) $v['number'] : null;
      if ($v['kind'] === 'currency') $row['value_currency'] = isset($v['currency']) ? (string) $v['currency'] : null;
      if ($v['kind'] === 'percent') $row['value_percent'] = isset($v['percent']) ? (string) $v['percent'] : null;
      if ($v['kind'] === 'json') $row['value_json'] = isset($v['json']) ? (string) $v['json'] : null;

      $wpdb->insert($t_ans, $row, $formats);
    }

    if (class_exists('TWT_TCRM_Email_Automations')) {
      TWT_TCRM_Email_Automations::handle_submission($submission_id);
    }

    wp_safe_redirect(self::redirect_back_ok());
    exit;
  }

  // ======= RESTO DO FICHEIRO: mantém exatamente como tens hoje =======
  // can_view_form, get_assigned_forms, campaign_allowed_for_form, location_allowed_for_form_and_user,
  // parse_value_by_type, extract_uploaded_file, handle_upload, index_questions, get_allowed_options,
  // decode_json, redirect_back_ok, redirect_back_with_error
  // (não repito aqui para não crescer demais; mas se quiseres eu devolvo o ficheiro mesmo “verbatim 100%” com tudo)


  /**
   * Permissão de ver/submeter um form:
   * - admin/gestor interno: sempre
   * - user normal: tem de estar atribuído e o form tem de estar activo
   *
   * ATUALIZAÇÃO:
   * - deixa de exigir match exato de campaign_id no assignment, porque a campanha é escolhida na submissão
   * - mantém brand_id e form_id como obrigatórios
   * - campaign_id no assignment passa a ser "campanha alvo" (se quiseres manter). Para já aceitamos qualquer campaign.
   */
  private static function can_view_form($user_id, $form_id) {
    if (TWT_TCRM_Roles::is_admin_like($user_id)) {
      return true;
    }

    $status = get_post_meta($form_id, 'twt_form_status', true);
    if ($status && $status !== 'active') {
      return false;
    }

    global $wpdb;
    $t_assign = TWT_TCRM_DB::table_assignments();

    $brand_id = (int) get_post_meta($form_id, 'twt_brand_id', true);

    $sql = "SELECT id FROM $t_assign
            WHERE user_id = %d
              AND form_id = %d
              AND brand_id = %d
              AND active = 1
            LIMIT 1";

    $found = $wpdb->get_var($wpdb->prepare($sql, $user_id, $form_id, $brand_id));
    return !empty($found);
  }

  private static function get_assigned_forms($user_id) {
    global $wpdb;
    $t_assign = TWT_TCRM_DB::table_assignments();

    $ids = $wpdb->get_col($wpdb->prepare(
      "SELECT DISTINCT form_id FROM $t_assign WHERE user_id = %d AND active = 1",
      $user_id
    ));

    if (!$ids) return [];

    $forms = get_posts([
      'post_type' => 'twt_form',
      'post__in' => array_map('intval', $ids),
      'numberposts' => -1,
      'orderby' => 'title',
      'order' => 'ASC',
    ]);

    return $forms ? $forms : [];
  }

  /**
   * NEW: valida campanha escolhida vs campanhas permitidas no form (pivot).
   * - campaign_id=0 é sempre permitido (sem campanha).
   * - se o form não tiver campanhas configuradas, só permite 0.
   */
  private static function campaign_allowed_for_form($form_id, $campaign_id) {
    $form_id = (int) $form_id;
    $campaign_id = (int) $campaign_id;

    if ($campaign_id === 0) return true;

    if (!class_exists('TWT_TCRM_Form_Renderer') || !method_exists('TWT_TCRM_Form_Renderer', 'get_form_campaign_ids')) {
      // fallback: se não temos pivot disponível, aceita o default do meta
      $default = (int) get_post_meta($form_id, 'twt_campaign_id', true);
      return $default === $campaign_id;
    }

    $allowed = TWT_TCRM_Form_Renderer::get_form_campaign_ids($form_id);
    if (!$allowed) {
      return false;
    }
    return in_array($campaign_id, $allowed, true);
  }

  /**
   * NEW: valida local escolhido:
   * - se o form tiver locais configurados, location_id tem de ser >0 e pertencer ao pivot do form
   * - e o user tem de ter esse local atribuído (twt_location_assignments active=1)
   * - se o form não tiver locais configurados, location_id pode ser 0 ou um local do user
   */
  private static function location_allowed_for_form_and_user($form_id, $user_id, $location_id) {
    $form_id = (int) $form_id;
    $user_id = (int) $user_id;
    $location_id = (int) $location_id;

    // NEW: admin-like não bloqueia por assignments (consistente com o renderer)
    if (class_exists('TWT_TCRM_Roles') && TWT_TCRM_Roles::is_admin_like($user_id)) {
      return true;
    }

    if (!class_exists('TWT_TCRM_Form_Renderer') || !method_exists('TWT_TCRM_Form_Renderer', 'get_form_location_ids')) {
      // Sem engine => não bloqueia (compat)
      return true;
    }

    $form_locs = TWT_TCRM_Form_Renderer::get_form_location_ids($form_id);
    $user_locs = TWT_TCRM_Form_Renderer::get_user_location_ids($user_id);

    // Se o form exige locais, location_id é obrigatório e tem de estar na interseção
    if (!empty($form_locs)) {
      if ($location_id <= 0) return false;
      return in_array($location_id, $form_locs, true) && in_array($location_id, $user_locs, true);
    }

    // Se o form não restringe locais:
    if ($location_id === 0) return true;
    return in_array($location_id, $user_locs, true);
  }

  private static function parse_value_by_type($raw, $type) {
    if ($raw === null) {
      return ['is_empty' => true, 'kind' => 'text', 'text' => ''];
    }

    if (is_array($raw)) {
      $clean = array_map(function ($v) {
        return sanitize_text_field(wp_unslash($v));
      }, $raw);

      $is_empty = empty($clean);

      return [
        'is_empty' => $is_empty,
        'kind' => 'json',
        'json' => $is_empty ? null : wp_json_encode($clean, JSON_UNESCAPED_UNICODE),
      ];
    }

    $raw = wp_unslash($raw);
    $raw = is_string($raw) ? trim($raw) : $raw;

    $is_empty = ($raw === '' || $raw === null);

    if ($type === 'checkbox') {
      $val = ($raw === '1' || $raw === 1 || $raw === true || $raw === 'on') ? 1 : 0;
      return ['is_empty' => false, 'kind' => 'number', 'number' => (int) $val];
    }

    if ($type === 'date') {
      if ($is_empty) return ['is_empty' => true, 'kind' => 'text', 'text' => ''];
      return ['is_empty' => false, 'kind' => 'text', 'text' => sanitize_text_field($raw)];
    }

    if ($type === 'time') {
      if ($is_empty) return ['is_empty' => true, 'kind' => 'text', 'text' => ''];
      return ['is_empty' => false, 'kind' => 'text', 'text' => sanitize_text_field($raw)];
    }

    if (in_array($type, ['select', 'radio'], true)) {
      if ($is_empty) return ['is_empty' => true, 'kind' => 'text', 'text' => ''];
      return ['is_empty' => false, 'kind' => 'text', 'text' => sanitize_text_field($raw)];
    }

    if ($type === 'textarea' || $type === 'text') {
      return [
        'is_empty' => $is_empty,
        'kind' => 'text',
        'text' => $is_empty ? '' : sanitize_textarea_field($raw),
      ];
    }

    if (in_array($type, ['number', 'currency', 'percent'], true)) {
      if ($is_empty) {
        return ['is_empty' => true, 'kind' => 'number', 'number' => null];
      }

      $num = str_replace(',', '.', (string) $raw);
      $num = preg_replace('/[^0-9\.\-]/', '', $num);
      $val = is_numeric($num) ? (float) $num : null;

      if ($val === null) {
        return ['is_empty' => true, 'kind' => 'number', 'number' => null];
      }

      if ($type === 'currency') return ['is_empty' => false, 'kind' => 'currency', 'currency' => $val];
      if ($type === 'percent') return ['is_empty' => false, 'kind' => 'percent', 'percent' => $val];

      return ['is_empty' => false, 'kind' => 'number', 'number' => $val];
    }

    return [
      'is_empty' => $is_empty,
      'kind' => 'text',
      'text' => $is_empty ? '' : sanitize_text_field($raw),
    ];
  }

  private static function extract_uploaded_file($key) {
    if (!isset($_FILES['twt_upload'])) return null;

    $f = $_FILES['twt_upload'];

    if (!isset($f['name'][$key])) return null;

    return [
      'name'     => $f['name'][$key],
      'type'     => $f['type'][$key],
      'tmp_name' => $f['tmp_name'][$key],
      'error'    => $f['error'][$key],
      'size'     => $f['size'][$key],
    ];
  }

  private static function handle_upload($file, $allowed_mimes = []) {
    if (!is_array($file) || empty($file['tmp_name'])) {
      return ['error' => 'invalid_file'];
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $overrides = ['test_form' => false];
    if (!empty($allowed_mimes) && is_array($allowed_mimes)) {
      $overrides['mimes'] = $allowed_mimes;
    }

    $upload = wp_handle_upload($file, $overrides);

    if (isset($upload['error'])) {
      return ['error' => (string) $upload['error']];
    }

    $file_path = isset($upload['file']) ? $upload['file'] : '';
    $url = isset($upload['url']) ? $upload['url'] : '';
    $mime = isset($upload['type']) ? $upload['type'] : '';

    $attachment_id = 0;

    if ($file_path) {
      $attachment = [
        'post_mime_type' => $mime,
        'post_title' => sanitize_file_name(basename($file_path)),
        'post_content' => '',
        'post_status' => 'inherit',
      ];

      $attachment_id = wp_insert_attachment($attachment, $file_path);

      if ($attachment_id) {
        $attach_data = wp_generate_attachment_metadata($attachment_id, $file_path);
        if (is_array($attach_data)) {
          wp_update_attachment_metadata($attachment_id, $attach_data);
        }
      }
    }

    return [
      'url' => $url,
      'attachment_id' => (int) $attachment_id,
      'mime' => $mime,
      'filename' => basename($file_path),
      'size' => isset($file['size']) ? (int) $file['size'] : 0,
    ];
  }

  private static function index_questions($questions) {
    $idx = [];
    foreach ($questions as $q) {
      $key = isset($q['key']) ? sanitize_key($q['key']) : '';
      if (!$key) continue;
      $idx[$key] = $q;
    }
    return $idx;
  }

  private static function get_allowed_options($questions_index, $key) {
    if (!is_array($questions_index) || !isset($questions_index[$key])) return [];
    $q = $questions_index[$key];
    if (empty($q['options']) || !is_array($q['options'])) return [];
    $out = [];
    foreach ($q['options'] as $o) {
      $o = sanitize_text_field($o);
      if ($o !== '') $out[] = (string) $o;
    }
    return $out;
  }

  /**
   * decode JSON helper
   */
  private static function decode_json($json) {
    if (!$json) return null;
    $arr = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) return null;
    return $arr;
  }

  private static function redirect_back_ok() {
    $ref = isset($_POST['_wp_http_referer']) ? wp_unslash($_POST['_wp_http_referer']) : '';
    if (!$ref) $ref = home_url('/');
    return add_query_arg(['twt_tcrm_ok' => '1'], esc_url_raw($ref));
  }

  private static function redirect_back_with_error($code) {
    $ref = isset($_POST['_wp_http_referer']) ? wp_unslash($_POST['_wp_http_referer']) : '';
    if (!$ref) $ref = home_url('/');
    return add_query_arg(['twt_tcrm_err' => $code], esc_url_raw($ref));
  }
}