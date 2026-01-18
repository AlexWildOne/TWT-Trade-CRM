<?php

if (!defined('ABSPATH')) {
  exit;
}

final class TWT_TCRM_Emails_Admin {

  const PAGE_SLUG = 'twt-tcrm-emails';

  public static function boot() {
    add_action('admin_menu', [__CLASS__, 'register_menu'], 31);
    add_action('admin_post_twt_tcrm_send_email', [__CLASS__, 'handle_send']);
  }

  public static function register_menu() {
    add_submenu_page(
      'edit.php?post_type=twt_brand',
      'Emails',
      'Emails',
      'twt_tcrm_view_all_reports',
      self::PAGE_SLUG,
      [__CLASS__, 'render_page']
    );
  }

  /**
   * Templates agora vêm do CPT twt_email_template
   */
  private static function get_templates_posts() {
    $templates = get_posts([
      'post_type' => 'twt_email_template',
      'numberposts' => -1,
      'post_status' => ['publish', 'draft'],
      'orderby' => 'title',
      'order' => 'ASC',
    ]);

    return is_array($templates) ? $templates : [];
  }

  private static function get_dropdown_data($filters) {
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

    $forms = get_posts([
      'post_type' => 'twt_form',
      'numberposts' => -1,
      'orderby' => 'title',
      'order' => 'ASC',
    ]);

    // Users filtrados por assignments (se brand_id definido)
// Users filtrados por assignments (se brand_id definido)
$users = [];
if (!empty($filters['brand_id'])) {
  global $wpdb;
  $t_assign = TWT_TCRM_DB::table_assignments();

  $brand_id = (int) $filters['brand_id'];
  $campaign_id = (int) $filters['campaign_id'];
  $form_id = (int) $filters['form_id'];

  $where = "WHERE active = 1 AND brand_id = %d";
  $params = [$brand_id];

  // se campaign_id escolhido, inclui campaign_id e 0 (geral)
  if ($campaign_id > 0) {
    $where .= " AND (campaign_id = %d OR campaign_id = 0)";
    $params[] = $campaign_id;
  }

  // se form_id escolhido, filtra
  if ($form_id > 0) {
    $where .= " AND form_id = %d";
    $params[] = $form_id;
  }

  $sql = "SELECT DISTINCT user_id FROM {$t_assign} {$where} ORDER BY user_id ASC";
  $ids = $wpdb->get_col($wpdb->prepare($sql, $params));
  $ids = is_array($ids) ? $ids : [];

  foreach ($ids as $uid) {
    $u = get_user_by('id', (int) $uid);
    if ($u) $users[] = $u;
  }
}

    // Templates CPT
    $templates = self::get_templates_posts();

    return compact('brands', 'campaigns', 'forms', 'users', 'templates');
  }

  private static function get_filters_from_request() {
    $brand_id = isset($_GET['brand_id']) ? (int) $_GET['brand_id'] : 0;
    $campaign_id = isset($_GET['campaign_id']) ? (int) $_GET['campaign_id'] : 0;
    $form_id = isset($_GET['form_id']) ? (int) $_GET['form_id'] : 0;
    $user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

    $date_from = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '';
    $date_to = isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '';

    if ($date_from && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) $date_from = '';
    if ($date_to && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) $date_to = '';

    // Permitir escolher submissão diretamente (para evitar “não há submissões”)
    $submission_id = isset($_GET['submission_id']) ? (int) $_GET['submission_id'] : 0;

    // Template CPT (ID)
    $template_id = isset($_GET['template_id']) ? (int) $_GET['template_id'] : 0;

    return [
      'brand_id' => $brand_id,
      'campaign_id' => $campaign_id,
      'form_id' => $form_id,
      'user_id' => $user_id,
      'date_from' => $date_from,
      'date_to' => $date_to,
      'submission_id' => $submission_id,
      'template_id' => $template_id,
    ];
  }

  private static function build_where_sql($filters, &$params) {
    $where = "WHERE 1=1";
    $params = [];

    if (!empty($filters['brand_id'])) {
      $where .= " AND s.brand_id = %d";
      $params[] = (int) $filters['brand_id'];
    }
    if (!empty($filters['campaign_id'])) {
      $where .= " AND s.campaign_id = %d";
      $params[] = (int) $filters['campaign_id'];
    }
    if (!empty($filters['form_id'])) {
      $where .= " AND s.form_id = %d";
      $params[] = (int) $filters['form_id'];
    }
    if (!empty($filters['user_id'])) {
      $where .= " AND s.user_id = %d";
      $params[] = (int) $filters['user_id'];
    }
    if (!empty($filters['date_from'])) {
      $where .= " AND s.submitted_at >= %s";
      $params[] = $filters['date_from'] . ' 00:00:00';
    }
    if (!empty($filters['date_to'])) {
      $where .= " AND s.submitted_at <= %s";
      $params[] = $filters['date_to'] . ' 23:59:59';
    }

    return $where;
  }

  /**
   * Escolha de submissão:
   * - se user definiu submission_id, usa essa
   * - senão, vai buscar a mais recente pelos filtros
   */
  private static function pick_submission_id_for_send($filters) {
if (!empty($filters['submission_id'])) {
  $sid = (int) $filters['submission_id'];
  return $sid > 0 ? $sid : 0;
}

    global $wpdb;
    $t_sub = TWT_TCRM_DB::table_submissions();

    $params = [];
    $where = self::build_where_sql($filters, $params);

    $sql = "SELECT s.id FROM {$t_sub} s {$where} ORDER BY s.submitted_at DESC LIMIT 1";
    return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
  }

  private static function get_submission_with_answers($submission_id) {
    global $wpdb;

    $t_sub = TWT_TCRM_DB::table_submissions();
    $t_ans = TWT_TCRM_DB::table_answers();

    $sub = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t_sub} WHERE id = %d", $submission_id), ARRAY_A);
    if (!$sub) return null;

    $ans = $wpdb->get_results($wpdb->prepare(
      "SELECT question_key, value_text, value_number, value_currency, value_percent, value_json
       FROM {$t_ans}
       WHERE submission_id = %d
       ORDER BY question_key ASC",
      $submission_id
    ), ARRAY_A);
    $ans = is_array($ans) ? $ans : [];

    $answers = [];
    foreach ($ans as $a) {
      $k = (string) $a['question_key'];
      $val = '';
      if ($a['value_text'] !== null && $a['value_text'] !== '') $val = (string) $a['value_text'];
      elseif ($a['value_number'] !== null && $a['value_number'] !== '') $val = (string) $a['value_number'];
      elseif ($a['value_currency'] !== null && $a['value_currency'] !== '') $val = (string) $a['value_currency'];
      elseif ($a['value_percent'] !== null && $a['value_percent'] !== '') $val = (string) $a['value_percent'];
      elseif ($a['value_json'] !== null && $a['value_json'] !== '') $val = (string) $a['value_json'];

      $answers[$k] = $val;
    }

    $sub['answers'] = $answers;
    return $sub;
  }

  private static function get_brand_recipients($brand_id) {
    $emails = [];

    $user_ids = get_post_meta($brand_id, 'twt_brand_user_ids', true);
    if (is_array($user_ids)) {
      foreach ($user_ids as $uid) {
        $u = get_user_by('id', (int) $uid);
        if ($u && $u->user_email && is_email($u->user_email)) {
          $emails[] = strtolower($u->user_email);
        }
      }
    }

    $raw = (string) get_post_meta($brand_id, 'twt_brand_user_emails', true);
    $emails = array_merge($emails, self::parse_extra_emails($raw));

    return array_values(array_unique(array_filter($emails)));
  }

  /**
   * NEW: destinatários por assignments (brand + campaign OR 0 + form)
   */
  private static function get_assigned_recipients($brand_id, $campaign_id, $form_id) {
    global $wpdb;
    $t_assign = TWT_TCRM_DB::table_assignments();

    $brand_id = (int) $brand_id;
    $campaign_id = (int) $campaign_id;
    $form_id = (int) $form_id;

    if (!$brand_id || !$form_id) return [];

    if ($campaign_id > 0) {
      $sql = "SELECT DISTINCT user_id FROM {$t_assign}
              WHERE active = 1
                AND brand_id = %d
                AND form_id = %d
                AND (campaign_id = %d OR campaign_id = 0)";
      $ids = $wpdb->get_col($wpdb->prepare($sql, $brand_id, $form_id, $campaign_id));
    } else {
      $sql = "SELECT DISTINCT user_id FROM {$t_assign}
              WHERE active = 1
                AND brand_id = %d
                AND form_id = %d
                AND campaign_id = 0";
      $ids = $wpdb->get_col($wpdb->prepare($sql, $brand_id, $form_id));
    }

    if (!is_array($ids)) return [];

    $emails = [];
    foreach ($ids as $uid) {
      $u = get_user_by('id', (int) $uid);
      if ($u && $u->user_email && is_email($u->user_email)) {
        $emails[] = strtolower($u->user_email);
      }
    }

    return array_values(array_unique(array_filter($emails)));
  }

  private static function parse_extra_emails($raw) {
    $raw = (string) $raw;
    $raw = trim($raw);
    if ($raw === '') return [];

    $lines = preg_split("/\r\n|\n|\r/", $raw);
    $out = [];
    if (is_array($lines)) {
      foreach ($lines as $ln) {
        $ln = trim((string) $ln);
        if (!$ln) continue;
        $e = sanitize_email($ln);
        if ($e && is_email($e)) $out[] = strtolower($e);
      }
    }
    return array_values(array_unique($out));
  }

  private static function handle_upload_attachments() {
    if (empty($_FILES['attachments']) || !is_array($_FILES['attachments'])) return [];

    require_once ABSPATH . 'wp-admin/includes/file.php';

    $allowed_mimes = [
      'pdf' => 'application/pdf',
      'csv' => 'text/csv',
      'txt' => 'text/plain',
      'jpg|jpeg|jpe' => 'image/jpeg',
      'png' => 'image/png',
      'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'xls' => 'application/vnd.ms-excel',
    ];

    $files = $_FILES['attachments'];

    $count = isset($files['name']) && is_array($files['name']) ? count($files['name']) : 0;
    if ($count <= 0) return [];

    $paths = [];

    for ($i = 0; $i < $count; $i++) {
      if (empty($files['name'][$i])) continue;
      if (!empty($files['error'][$i])) continue;

      $single = [
        'name' => $files['name'][$i],
        'type' => $files['type'][$i],
        'tmp_name' => $files['tmp_name'][$i],
        'error' => $files['error'][$i],
        'size' => $files['size'][$i],
      ];

      if (!empty($single['size']) && (int) $single['size'] > 10 * 1024 * 1024) {
        continue;
      }

      $overrides = ['test_form' => false, 'mimes' => $allowed_mimes];
      $moved = wp_handle_upload($single, $overrides);

      if (!empty($moved['file']) && empty($moved['error'])) {
        $paths[] = $moved['file'];
      }
    }

    return $paths;
  }

  public static function render_page() {
    if (!current_user_can('twt_tcrm_view_all_reports')) {
      wp_die('Sem permissões.');
    }

    $filters = self::get_filters_from_request();
    $data = self::get_dropdown_data($filters);

    // Template default: primeiro template publicado, ou 0
    $selected_template_id = (int) $filters['template_id'];
    if (!$selected_template_id && !empty($data['templates'][0])) {
      $selected_template_id = (int) $data['templates'][0]->ID;
    }
    // valida se o template_id existe mesmo
if ($selected_template_id) {
  $p = get_post($selected_template_id);
  if (!$p || $p->post_type !== 'twt_email_template') {
    $selected_template_id = !empty($data['templates'][0]) ? (int) $data['templates'][0]->ID : 0;
  }
}

    echo '<div class="wrap">';
    echo '<h1>Emails (envio manual)</h1>';

    if (isset($_GET['sent'])) {
      $sent = sanitize_text_field(wp_unslash($_GET['sent']));
      if ($sent === '1') {
        echo '<div class="notice notice-success"><p>Email enviado.</p></div>';
      } elseif ($sent === '0') {
        echo '<div class="notice notice-error"><p>Falhou o envio do email.</p></div>';
      }
    }

    // Filtros (GET)
    echo '<form method="get" style="margin: 12px 0 16px 0;">';
    echo '<input type="hidden" name="post_type" value="twt_brand">';
    echo '<input type="hidden" name="page" value="' . esc_attr(self::PAGE_SLUG) . '">';

    echo '<div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">';

    echo '<div><label><strong>Marca</strong></label><br><select name="brand_id" style="min-width:240px;">';
    echo '<option value="0">Todas</option>';
    foreach ($data['brands'] as $b) {
      echo '<option value="' . esc_attr((int) $b->ID) . '"' . selected($filters['brand_id'], (int) $b->ID, false) . '>' . esc_html($b->post_title) . '</option>';
    }
    echo '</select></div>';

    echo '<div><label><strong>Campanha</strong></label><br><select name="campaign_id" style="min-width:240px;">';
    echo '<option value="0">Todas</option>';
    foreach ($data['campaigns'] as $c) {
      echo '<option value="' . esc_attr((int) $c->ID) . '"' . selected($filters['campaign_id'], (int) $c->ID, false) . '>' . esc_html($c->post_title) . '</option>';
    }
    echo '</select></div>';

    echo '<div><label><strong>Formulário</strong></label><br><select name="form_id" style="min-width:240px;">';
    echo '<option value="0">Todos</option>';
    foreach ($data['forms'] as $f) {
      echo '<option value="' . esc_attr((int) $f->ID) . '"' . selected($filters['form_id'], (int) $f->ID, false) . '>' . esc_html($f->post_title) . '</option>';
    }
    echo '</select></div>';

    echo '<div><label><strong>Utilizador</strong></label><br><select name="user_id" style="min-width:320px;">';
    echo '<option value="0">' . ($filters['brand_id'] ? 'Todos (da marca)' : 'Escolhe uma marca') . '</option>';
    if ($filters['brand_id']) {
      foreach ($data['users'] as $u) {
        $label = $u->display_name . ' (' . $u->user_login . ')';
        echo '<option value="' . esc_attr((int) $u->ID) . '"' . selected($filters['user_id'], (int) $u->ID, false) . '>' . esc_html($label) . '</option>';
      }
    }
    echo '</select></div>';

    echo '<div><label><strong>De</strong></label><br><input type="date" name="date_from" value="' . esc_attr($filters['date_from']) . '"></div>';
    echo '<div><label><strong>Até</strong></label><br><input type="date" name="date_to" value="' . esc_attr($filters['date_to']) . '"></div>';

    echo '<div><label><strong>Submission ID (opcional)</strong></label><br><input type="number" name="submission_id" value="' . esc_attr((string) (int) $filters['submission_id']) . '" style="width:160px;" placeholder="ex: 123"></div>';

    echo '<div style="margin-top:14px;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">';
    echo '<div>';
    echo '<label><strong>Template (CPT)</strong></label><br>';
    echo '<select name="template_id" style="min-width:320px;">';
    if (!$data['templates']) {
      echo '<option value="0">Sem templates (cria em “Templates de Email”)</option>';
    } else {
      foreach ($data['templates'] as $t) {
        $label = $t->post_title . ($t->post_status !== 'publish' ? ' (draft)' : '');
        echo '<option value="' . esc_attr((int) $t->ID) . '"' . selected($selected_template_id, (int) $t->ID, false) . '>' . esc_html($label) . '</option>';
      }
    }
    echo '</select>';
    echo '</div>';
    echo '<div><button class="button button-primary" type="submit">Aplicar</button></div>';
    echo '</div>';

    echo '</div>';
    echo '</form>';

    $picked_submission_id = self::pick_submission_id_for_send($filters);

    echo '<h2>Enviar (com anexos)</h2>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data">';
    echo '<input type="hidden" name="action" value="twt_tcrm_send_email">';
    wp_nonce_field('twt_tcrm_send_email', 'nonce');

    foreach ($filters as $k => $v) {
      echo '<input type="hidden" name="filters[' . esc_attr($k) . ']" value="' . esc_attr((string) $v) . '">';
    }
    echo '<input type="hidden" name="template_id" value="' . esc_attr((string) $selected_template_id) . '">';

    echo '<p><strong>Submissão usada:</strong> ';
    echo $picked_submission_id ? ('#' . esc_html($picked_submission_id) . ' (a mais recente nos filtros / ou submission_id)') : 'Nenhuma encontrada com estes filtros.';
    echo '</p>';

    echo '<table class="form-table" role="presentation"><tbody>';

    echo '<tr><th scope="row">Enviar para</th><td>';
    echo '<label style="margin-right:14px;"><input type="radio" name="recipient_mode" value="brand" checked> Marca (users + emails extra)</label>';
    echo '<label style="margin-right:14px;"><input type="radio" name="recipient_mode" value="assigned"> Users atribuídos (assignments)</label>';
    echo '<label><input type="radio" name="recipient_mode" value="user"> Utilizador específico</label>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="recipient_user_id">Utilizador</label></th><td>';
    echo '<select id="recipient_user_id" name="recipient_user_id" style="min-width:420px;">';
    echo '<option value="0">—</option>';
    if ($filters['brand_id']) {
      foreach ($data['users'] as $u) {
        $label = $u->display_name . ' (' . $u->user_login . ') — ' . $u->user_email;
        echo '<option value="' . esc_attr((int) $u->ID) . '">' . esc_html($label) . '</option>';
      }
    }
    echo '</select>';
    echo '<p class="description">Só usado quando “Enviar para: Utilizador específico”.</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="extra_emails">Emails extra (opcional)</label></th><td>';
    echo '<textarea id="extra_emails" name="extra_emails" rows="3" style="width:100%;max-width:700px;" placeholder="um email por linha (opcional)"></textarea>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="attachments">Anexos</label></th><td>';
    echo '<input type="file" id="attachments" name="attachments[]" multiple>';
    echo '<p class="description">Tipos recomendados: PDF, XLSX, CSV, JPG/PNG. Máx 10MB por ficheiro.</p>';
    echo '</td></tr>';

    echo '</tbody></table>';

    echo '<p><button class="button button-primary" type="submit" ' . ($picked_submission_id && $selected_template_id ? '' : 'disabled') . '>Enviar email</button></p>';

    echo '</form>';
    echo '</div>';
  }

  public static function handle_send() {
    if (!current_user_can('twt_tcrm_view_all_reports')) {
      wp_die('Sem permissões.');
    }

    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!$nonce || !wp_verify_nonce($nonce, 'twt_tcrm_send_email')) {
      wp_die('Nonce inválido.');
    }

    $filters = isset($_POST['filters']) && is_array($_POST['filters']) ? (array) wp_unslash($_POST['filters']) : [];
    $filters = array_merge([
      'brand_id' => 0, 'campaign_id' => 0, 'form_id' => 0, 'user_id' => 0, 'date_from' => '', 'date_to' => '', 'submission_id' => 0
    ], $filters);

    $filters['brand_id'] = (int) $filters['brand_id'];
    $filters['campaign_id'] = (int) $filters['campaign_id'];
    $filters['form_id'] = (int) $filters['form_id'];
    $filters['user_id'] = (int) $filters['user_id'];
    $filters['date_from'] = sanitize_text_field((string) $filters['date_from']);
    $filters['date_to'] = sanitize_text_field((string) $filters['date_to']);
    $filters['submission_id'] = (int) $filters['submission_id'];

    $template_id = isset($_POST['template_id']) ? (int) wp_unslash($_POST['template_id']) : 0;
    if (!$template_id) {
      wp_die('Sem template selecionado.');
    }

    $recipient_mode = isset($_POST['recipient_mode']) ? sanitize_key(wp_unslash($_POST['recipient_mode'])) : 'brand';
    if (!in_array($recipient_mode, ['brand', 'user', 'assigned'], true)) $recipient_mode = 'brand';

    $recipient_user_id = isset($_POST['recipient_user_id']) ? (int) wp_unslash($_POST['recipient_user_id']) : 0;
    $extra = self::parse_extra_emails(isset($_POST['extra_emails']) ? wp_unslash($_POST['extra_emails']) : '');

    $picked_submission_id = self::pick_submission_id_for_send($filters);
    if (!$picked_submission_id) {
      wp_die('Nenhuma submissão encontrada para preencher o email.');
    }

    $sub = self::get_submission_with_answers($picked_submission_id);
    if (!$sub) {
      wp_die('Submissão não encontrada.');
    }

    // Recipients
    $to = [];

    if ($recipient_mode === 'brand') {
      if (!empty($sub['brand_id'])) {
        $to = array_merge($to, self::get_brand_recipients((int) $sub['brand_id']));
      }
    } elseif ($recipient_mode === 'assigned') {
      // usa o contexto real da submissão (brand/campaign/form)
      $to = array_merge($to, self::get_assigned_recipients((int) $sub['brand_id'], (int) $sub['campaign_id'], (int) $sub['form_id']));
    } else {
      if ($recipient_user_id) {
        $ru = get_user_by('id', $recipient_user_id);
        if ($ru && $ru->user_email && is_email($ru->user_email)) {
          $to[] = strtolower($ru->user_email);
        }
      }
    }

    $to = array_merge($to, $extra);
    $to = array_values(array_unique(array_filter($to)));

    if (!$to) {
      wp_die('Sem destinatários. Escolhe uma marca (com emails), users atribuídos, ou um utilizador.');
    }

    // Upload attachments
    $attachments = self::handle_upload_attachments();

    if (!class_exists('TWT_TCRM_Email_Service') || !method_exists('TWT_TCRM_Email_Service', 'send_template_for_submission')) {
      wp_die('Email service não disponível.');
    }

    $ok = TWT_TCRM_Email_Service::send_template_for_submission(
      $template_id,
      $sub,
      isset($sub['answers']) && is_array($sub['answers']) ? $sub['answers'] : [],
      $to,
      $attachments,
      0
    );

    // Redirect back
    $back = admin_url('edit.php?post_type=twt_brand&page=' . self::PAGE_SLUG);
    $back = add_query_arg([
      'sent' => $ok ? '1' : '0',
      'template_id' => (int) $template_id,
      'brand_id' => (int) $filters['brand_id'],
      'campaign_id' => (int) $filters['campaign_id'],
      'form_id' => (int) $filters['form_id'],
      'user_id' => (int) $filters['user_id'],
      'date_from' => $filters['date_from'],
      'date_to' => $filters['date_to'],
      'submission_id' => (int) $filters['submission_id'],
    ], $back);

    wp_safe_redirect($back);
    exit;
  }
}